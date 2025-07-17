<?php
trait HealthChecksServiceChecker {
    private $HealthCheckServices;

    public function checkAll() {
        $results = [];
        $services = $this->getServices();
        foreach ($services as $service) {
            $results[] = $this->checkService($service);
        }
        return $results;
    }

    private function checkService($service) {
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

    private function checkHttpService($service) {
        $url = "{$service['protocol']}://{$service['host']}:{$service['port']}{$service['http_path']}";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $service['timeout'] ?? 5);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $Result = [
            'id' => $service['id'],
            'name' => $service['name'],
            'type' => $service['type'],
            'host' => $service['host'],
            'port' => $service['port'],
            'timeout' => $service['timeout'] ?? 5,
            'status' => $httpCode == $service['http_expected_status'] ? 'healthy' : 'unhealthy',
            'http_code' => $httpCode,
            'path' => $service['http_path'] ?? '/',
            'protocol' => $service['protocol'] ?? 'http',
            'response_time' => curl_getinfo($ch, CURLINFO_TOTAL_TIME),
            'response' => curl_multi_getcontent($ch),
            'status_message' => $httpCode == $service['http_expected_status'] ? 'Service is healthy' : 'Service is unhealthy',
            'error' => empty($error) ? null : $error,
            'error_message' => empty($error) ? null : "Error occurred: $error",
            'error_code' => curl_errno($ch),
            'error_code_string' => curl_strerror(curl_errno($ch)),
        ];
        $this->saveCheckHistory($Result);
        return $Result;
    }

    private function checkTcpPort($service) {
        $fp = @fsockopen($service['host'], $service['port'], $errno, $errstr, $service['timeout'] ?? 3);
        $Result = [
            'id' => $service['id'],
            'name' => $service['name'],
            'type' => $service['type'],
            'host' => $service['host'],
            'port' => $service['port'],
            'timeout' => $service['timeout'] ?? 5,
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
        $timeout = $service['timeout'] ?? 5;

        $cmd = stripos(PHP_OS, 'WIN') === 0
            ? "ping -n $count -w " . ($timeout * 1000) . " $host"
            : "ping -c $count -W $timeout $host";

        exec($cmd, $output, $resultCode);

        $Result = [
            'id' => $service['id'],
            'name' => $service['name'],
            'type' => $service['type'],
            'host' => $service['host'],
            'timeout' => $service['timeout'] ?? 5,
            'status' => $resultCode === 0 ? 'healthy' : 'unhealthy',
            'response' => implode("\n", $output),
            'error_code' => $resultCode,
            'status_message' => $resultCode === 0 ? 'Ping successful' : 'Ping failed'
        ];
        $this->saveCheckHistory($Result);
        return $Result;
    }

}