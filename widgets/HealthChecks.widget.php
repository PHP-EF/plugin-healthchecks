<?php
// Define Custom HTML Widgets
class HealthChecksWidget implements WidgetInterface {
    private $phpef;
    public $widgetConfig;

    public function __construct($phpef) {
        $this->phpef = $phpef;
        $this->widgetConfig = $this->getWidgetConfig();
    }

    public function settings() {
        $customHTMLQty = 5;
        $SettingsArr = [];
        $SettingsArr['info'] = [
            'name' => 'Health Checks',
            'description' => 'Health Checks Widget',
			'image' => ''
        ];
        $SettingsArr['Settings'] = [
            'Widget Settings' => [
				$this->phpef->settingsOption('enable', 'enabled'),
				$this->phpef->settingsOption('auth', 'auth', ['label' => 'Role Required']),
                $this->phpef->settingsOption('checkbox', 'headerEnabled', ['label' => 'Enable Header', 'attr' => 'checked']),
                $this->phpef->settingsOption('input', 'header', ['label' => 'Header Title', 'placeholder' => 'Health Checks']),
            ]
        ];
        return $SettingsArr;
    }

    private function getWidgetConfig() {
        $WidgetConfig = $this->phpef->config->get('Widgets','Health Checks') ?? [];
        $WidgetConfig['enabled'] = $WidgetConfig['enabled'] ?? false;
        $WidgetConfig['auth'] = $WidgetConfig['auth'] ?? 'ACL-HEALTHCHECKS';
        $WidgetConfig['headerEnabled'] = $this->widgetConfig['headerEnabled'] ?? true;
        $WidgetConfig['header'] = $this->widgetConfig['header'] ?? 'Health Checks';
        return $WidgetConfig;
    }

    public function render() {
        if ($this->phpef->auth->checkAccess($this->widgetConfig['auth']) !== false && $this->widgetConfig['enabled']) {
            $output = '';
            if ($this->widgetConfig['headerEnabled']) {
                $widgetHeader = $this->widgetConfig['header'];
                $output = <<<EOF
                <div class="col-md-12 homepage-item-collapse" data-bs-toggle="collapse" href="#healthChecks-collapse" data-bs-parent="#healthChecks" aria-expanded="true" aria-controls="healthChecks-collapse">
                    <h4 class="float-left homepage-item-title"><span lang="en">$widgetHeader</span></h4>
                    <h4 class="float-left">&nbsp;</h4>
                    <hr class="hr-alt ml-2">
                </div>
                <div class="panel-collapse collapse show" id="healthChecks-collapse" aria-labelledby="healthChecks-heading" role="tabpanel" aria-expanded="true" style="">
                EOF;
            }

            $output .= <<<EOF
                <div class="card card-rounded pt-3">
                  <h1> Example Widget </h1>
                </div>
            EOF;

            return $output;
        }
    }
}
// Register Custom HTML Widgets
$phpef->dashboard->registerWidget('Health Checks', new HealthChecksWidget($phpef));