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
    }
}