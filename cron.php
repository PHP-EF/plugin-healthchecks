<?php
// Get a list of all services and their schedules
$healthChecksPlugin = new healthChecksPlugin();
$services = $healthChecksPlugin->getServices();

try {
    foreach ($services as $service) {
        $scheduler->call(function() use ($service, $healthChecksPlugin) {
            $healthChecksPlugin->checkService($service);
        })->at($service['schedule'] ?? '*/5 * * * *'); // Default to every 5 minutes if no schedule is set
    }
    $healthChecksPlugin->updateCronStatus('Health Checks','Service Checks', 'success');
} catch (Exception $e) {
    $healthChecksPlugin->updateCronStatus('Health Checks','Service Checks', 'error', $e->getMessage());
}