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
}