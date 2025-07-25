<?php
trait HealthChecksGeneral {
    // *API Helper for building queries
    private function buildAPIQuery($Params = []) {
        $QueryParams = http_build_query($Params);
        if ($QueryParams) {
            $Query = '?'.$QueryParams;
            return $Query;
        }
    }

    private function loadConfig() {
        $this->pluginConfig = $this->config->get('Plugins', 'Health Checks');
        $this->pluginConfig['defaultSort'] = $this->pluginConfig['defaultSort'] ?? 'name';
        $this->pluginConfig['defaultSortOrder'] = $this->pluginConfig['defaultSortOrder'] ?? 'asc';
        $this->pluginConfig['sortUnhealthyFirst'] = $this->pluginConfig['sortUnhealthyFirst'] ?? false;
        $this->pluginConfig['pushoverRetry'] = $this->pluginConfig['pushoverRetry'] ?? 60;
        $this->pluginConfig['pushoverExpire'] = $this->pluginConfig['pushoverExpire'] ?? 3600;
    }

    public function buildSortMenu() {
        $sortOptions = array();
        foreach ($this->validServiceSorts as $sort) {
            $sortOptions[] = array(
                'name' => ucfirst(str_replace('_', ' ', $sort)),
                'value' => $sort
            );
        }
        return $sortOptions;
    }

    public function getPriorityText($priorityId) {
        switch($priorityId) {
            case '-2':
                return 'Very Low';
            case '-1':
                return 'Low';
            case '0':
                return 'Normal';
            case '1':
                return 'High';
            case '2':
                return 'Critical';
            default:
                return 'Unknown';
        }
    }
}