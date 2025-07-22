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

        $status = 'healthy';
        if ($error) {
            $status = 'unhealthy';
            $error = "CURL Error: $error";
        } elseif ($httpCode != $service['http_expected_status']) {
            $status = 'unhealthy';
            $error = "Expected HTTP status {$service['http_expected_status']}, got $httpCode";
        }        

        $Result = [
            'id' => $service['id'],
            'name' => $service['name'],
            'type' => $service['type'],
            'host' => $service['host'],
            'port' => $service['port'],
            'timeout' => $service['timeout'] ?: 5,
            'priority' => $service['priority'],
            'status' => $status,
            'http_expected_status' => $service['http_expected_status'],
            'http_code' => $httpCode,
            'path' => $service['http_path'] ?? '/',
            'protocol' => $service['protocol'] ?: 'http',
            'response_time' => $responseTime,
            'response' => $response,
            'status_message' => $status == 'healthy' ? 'Service is healthy' : 'Service is unhealthy',
            'error' => empty($error) ? null : $error,
            'curl_error_code' => $errorCode,
            'curl_error_code_string' => $errorCodeString,
        ];

        if ($Result['status'] == 'unhealthy') {
            $this->sendNotification($Result, $service['notified']);
            $Result['notified'] = true;
        } else {
            if (($this->pluginConfig['notifyOnHealthy'] ?? false) && $service['status'] == 'unhealthy') {
                $this->sendNotification($Result, false);
            }
            $Result['notified'] = false;
        }

        $this->saveCheckHistory($Result,$service);
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
            'priority' => $service['priority']
        ];
        if ($fp) {
            fclose($fp);
            $Result['status'] = 'healthy';
        } else {
            $Result['status'] = 'unhealthy';
            $Result['error'] = $errstr;
        }
        $this->saveCheckHistory($Result,$service);
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
            'priority' => $service['priority'],
            'status' => $resultCode === 0 ? 'healthy' : 'unhealthy',
            'response' => implode("\n", $output),
            'error_code' => $resultCode,
            'status_message' => $resultCode === 0 ? 'Ping successful' : 'Ping failed'
        ];
        $this->saveCheckHistory($Result,$service);
        return $Result;
    }

    private function sendNotification($result, $notifed = false) {
        if (isset($this->pluginConfig['sendOnce']) && $this->pluginConfig['sendOnce'] == true && $notifed) {
            return; // Skip sending if already notified and sendOnce is true
        }

        $NotificationHeader = "";
        if ($result['priority'] > 0) {
            $NotificationHeader .= "(".$this->getPriorityText($result['priority']).") - ";
        }
        $NotificationHeader .= $result['name']." - ".ucfirst($result['status']);

        $NotificationDetails = "\r\n\r\n<b>Health Check Details:</b><br><ul style='margin:0; padding-left:15px;'>";
        foreach ($result as $key => $value) {
            if ($key !== "response") {
                $NotificationDetails .= "<li><b>" . htmlspecialchars($key) . ":</b> " . nl2br(htmlspecialchars($value)) . "</li>";
            }
        }
        $NotificationDetails .= "</ul>";
        $status = strtolower($result['status']);
        $NotificationBody = "The service <b>" . htmlspecialchars($result['name']) . "</b> is <b>" . htmlspecialchars($result['status']) . "</b>.<br><br>" . $NotificationDetails;

        // Check if Health Checks Page is added to Pages and website Url is populated, if so, these can be used as the Url in notifications
        $HealthChecksPage = $this->pages->getPageByUrl('plugin/Health Checks/healthchecks') ?? null;
        $WebsiteUrl = $this->config->get('System', 'websiteURL') ?? null;
        $UrlEnabled = false;
        if ($HealthChecksPage && $WebsiteUrl) {
            $UrlEnabled = true;
            $HealthChecksPageUrl = $WebsiteUrl . '/?page=' . $HealthChecksPage['Name'];
        }

        // SMTP Notification
        if (isset($this->pluginConfig['smtpEnable']) && $this->pluginConfig['smtpEnable'] == true) {
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
                $NotificationHeader,
                $NotificationBody
            );
        }

        // Pushover Notification
        if (isset($this->pluginConfig['pushoverEnable']) && $this->pluginConfig['pushoverEnable'] == true) {
            $Pushover = new Pushover();
            $globalPushoverApiToken = $this->config->get('Pushover', 'ApiToken');
            $globalPushoverUserKey = $this->config->get('Pushover', 'UserKey');
            if (isset($this->pluginConfig['pushoverApiToken']) && !empty($this->pluginConfig['pushoverApiToken'])) {
                $Pushover->setToken($this->pluginConfig['pushoverApiToken']);
            } elseif (!empty($globalPushoverApiToken)) {
                $Pushover->setToken($globalPushoverApiToken);
            }
            if (isset($this->pluginConfig['pushoverUserKey']) && !empty($this->pluginConfig['pushoverUserKey'])) {
                $Pushover->setUser($this->pluginConfig['pushoverUserKey']);
            } elseif (!empty($globalPushoverUserKey)) {
                $Pushover->setUser($globalPushoverUserKey);
            }

            $emoji = ($status === 'healthy') ? '✅' : (($status === 'unhealthy') ? '❌' : '⚠️');

            if ($result['status'] == 'unhealthy') {
                $Pushover->setPriority($result['priority'] ?? 0); // Set priority based on service priority
                if ($result['priority'] == 2) {
                    $emoji = '‼️';
                    $Pushover->setRetry($this->pluginConfig['pushoverRetry']); //Used with Priority = 2; Pushover will resend the notification every 60 seconds until the user accepts.
                    $Pushover->setExpire($this->pluginConfig['pushoverExpire']); //Used with Priority = 2; Pushover will resend the notification every 60 seconds for 3600 seconds. After that point, it stops sending notifications.
                }
            } else {
                $Pushover->setPriority(0); // Normal priority for healthy status
            }
           
            $NotificationHeader = $emoji.' '.$NotificationHeader;

            $Pushover->setTitle($NotificationHeader);
            $Pushover->setMessage($NotificationBody);
            $Pushover->setHtml(1);
            if ($UrlEnabled) {
                $Pushover->setUrl($HealthChecksPageUrl);
                $Pushover->setUrlTitle('View Health Check Status');
            }
            $Pushover->setTimestamp(time());
            // $Pushover->setDebug(true);
            // $Pushover->setCallback('https://example.com/'); // Notification callback URL
            // $Pushover->setSound('bike');
            $Pushover->send();
        }
    }

}