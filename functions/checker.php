<?php
trait HealthChecksServiceChecker {
    private $HealthCheckServices;

    public function check($id) {
        $service = $this->getServiceById($id);
        if (!$service) {
            return ['status' => 'error', 'message' => 'Service not found'];
        }
        return $this->checkService($service);
    }

    public function checkAll() {
        $results = [];
        $services = $this->getServices();
        foreach ($services as $service) {
            $results[] = $this->checkService($service);
        }
        return $results;
    }

    public function checkService($service) {
        if ($service['enabled'] == 1) {
            switch ($service['type']) {
                case 'web':
                    return $this->checkHttpService($service);
                case 'tcp':
                    return $this->checkTcpPort($service);
                case 'icmp':
                    return $this->checkIcmpPing($service);
                default:
                    return ['status' => 'unknown', 'message' => 'Unsupported service type'];
            }
        }
    }

    private function checkHttpService($service) {
        $url = "{$service['protocol']}://{$service['host']}:{$service['port']}{$service['http_path']}";
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $service['timeout'] ?: 5);

        // Handle SSL verification based on $service['verify_ssl']
        if (isset($service['verify_ssl']) && $service['verify_ssl'] == false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $responseTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        $errorCode = curl_errno($ch);
        $errorCodeString = curl_strerror($errorCode);
        curl_close($ch);

        $Result = [
            'id' => $service['id'],
            'name' => $service['name'],
            'type' => $service['type'],
            'host' => $service['host'],
            'port' => $service['port'],
            'timeout' => $service['timeout'] ?: 5,
            'status' => $httpCode == $service['http_expected_status'] ? 'healthy' : 'unhealthy',
            'http_code' => $httpCode,
            'path' => $service['http_path'] ?? '/',
            'protocol' => $service['protocol'] ?: 'http',
            'response_time' => $responseTime,
            'response' => $response,
            'status_message' => $httpCode == $service['http_expected_status'] ? 'Service is healthy' : 'Service is unhealthy',
            'error' => empty($error) ? null : $error,
            'error_message' => empty($error) ? null : "Error occurred: $error",
            'error_code' => $errorCode,
            'error_code_string' => $errorCodeString,
        ];

        if ($Result['status'] == 'unhealthy') {
            $this->sendNotification($Result, $service['notified']);
            $Result['notified'] = true;
        } else {
            $Result['notified'] = false;
        }

        $this->saveCheckHistory($Result);
        return $Result;
    }

    private function checkTcpPort($service) {
        $timeout = (int)($service['timeout'] ?: 5);
        $fp = @fsockopen($service['host'], $service['port'], $errno, $errstr, $timeout);
        $Result = [
            'id' => $service['id'],
            'name' => $service['name'],
            'type' => $service['type'],
            'host' => $service['host'],
            'port' => $service['port'],
            'timeout' => $timeout,
        ];
        if ($fp) {
            fclose($fp);
            $Result['status'] = 'healthy';
        } else {
            $Result['status'] = 'unhealthy';
            $Result['error'] = $errstr;
        }
        $this->saveCheckHistory($Result);
        return $Result;
    }

    private function checkIcmpPing($service) {
        $host = escapeshellarg($service['host']);
        $count = $service['count'] ?? 1;
        $timeout = $service['timeout'] ?: 5;

        $cmd = stripos(PHP_OS, 'WIN') === 0
            ? "ping -n $count -w " . ($timeout * 1000) . " $host"
            : "ping -c $count -W $timeout $host";

        exec($cmd, $output, $resultCode);

        $Result = [
            'id' => $service['id'],
            'name' => $service['name'],
            'type' => $service['type'],
            'host' => $service['host'],
            'timeout' => $service['timeout'] ?: 5,
            'status' => $resultCode === 0 ? 'healthy' : 'unhealthy',
            'response' => implode("\n", $output),
            'error_code' => $resultCode,
            'status_message' => $resultCode === 0 ? 'Ping successful' : 'Ping failed'
        ];
        $this->saveCheckHistory($Result);
        return $Result;
    }

    private function sendNotification($result, $notifed = false) {
        if (isset($this->pluginConfig['smtpEnable']) && $this->pluginConfig['smtpEnable'] == true) {
            if (isset($this->pluginConfig['sendOnce']) && $this->pluginConfig['sendOnce'] == true && $notifed) {
                return; // Skip sending if already notified and sendOnce is true
            }
            if (isset($this->pluginConfig['smtpFrom']) && !empty($this->pluginConfig['smtpFrom'])) {
                $this->notifications->setSMTPConfig(['from_email' => $this->pluginConfig['smtpFrom']]);
            }
            if (isset($this->pluginConfig['smtpName']) && !empty($this->pluginConfig['smtpName'])) {
                $this->notifications->setSMTPConfig(['from_name' => $this->pluginConfig['smtpName']]);
            }
            if (isset($this->pluginConfig['smtpTo']) && !empty($this->pluginConfig['smtpTo'])) {
                $smtpTo = $this->pluginConfig['smtpTo'];
            } else {
                $smtpTo = $this->config->get('SMTP', 'to_email') ?? '';
            }
            $this->notifications->sendSmtpEmail(
                $smtpTo,
                "Service Alert: {$result['name']} - {$result['status']}",
                "The service {$result['name']} is currently {$result['status']}.\n\nDetails:\n" . json_encode($result)
            );
        }
    }

}