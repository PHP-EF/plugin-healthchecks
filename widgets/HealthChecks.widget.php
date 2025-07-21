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
        $HealthChecks = new healthChecksPlugin();
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
                $this->phpef->settingsOption('checkbox', 'sortUnhealthyFirst', ['label' => 'Always show unhealthy services first', 'help' => 'Always display unhealthy services first on the widget, regardless of the default sort option.']),
                $this->phpef->settingsOption('select', 'defaultSort', ['label' => 'Default Sort Field', 'options' => $HealthChecks->buildSortMenu(), 'help' => 'Default sort field for the health checks on the dashboard.']),
                $this->phpef->settingsOption('select', 'defaultSortOrder', ['label' => 'Default Sort Order', 'options' => [['name' => 'Descending', 'value' => 'desc'],['name' => 'Ascending', 'value' => 'asc']], 'help' => 'Default sort order for the health checks on the dashboard.']),
            ]
        ];
        return $SettingsArr;
    }

    private function getWidgetConfig() {
        $WidgetConfig = $this->phpef->config->get('Widgets','Health Checks') ?? [];
        $WidgetConfig['enabled'] = $WidgetConfig['enabled'] ?? false;
        $WidgetConfig['auth'] = $WidgetConfig['auth'] ?? 'ACL-HEALTHCHECKS';
        $WidgetConfig['headerEnabled'] = $WidgetConfig['headerEnabled'] ?? true;
        $WidgetConfig['header'] = $WidgetConfig['header'] ?? 'Health Checks';
        $WidgetConfig['sortUnhealthyFirst'] = $WidgetConfig['sortUnhealthyFirst'] ?? false;
        $WidgetConfig['defaultSort'] = $WidgetConfig['defaultSort'] ?? 'status';
        $WidgetConfig['defaultSortOrder'] = $WidgetConfig['defaultSortOrder'] ?? 'asc';
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

            $defaultSort = $this->widgetConfig['defaultSort'];
            $defaultSortOrder = $this->widgetConfig['defaultSortOrder'];
            $sortUnhealthyFirst = $this->widgetConfig['sortUnhealthyFirst'] ? 'true' : 'false';
            $output .= <<<EOF
                </div>

                <!-- Use queryAPI to fetch health check data and create the widget content -->
                <script>

                function convertUTCHealthLastCheckedStringToLocal(utcString) {
                    const [datePart, timePart] = utcString.split(' ');
                    const [year, month, day] = datePart.split('-').map(Number);
                    const [hours, minutes, seconds] = timePart.split(':').map(Number);
                    const utcDate = new Date(Date.UTC(year, month - 1, day, hours, minutes, seconds));
                    return utcDate.toLocaleString();
                }

                function loadHealthData() {
                    queryAPI('GET','/api/plugin/healthchecks/enabled_services?sort=$defaultSort&order=$defaultSortOrder').done(function(data) {
                        var sortUnhealthyFirst = $sortUnhealthyFirst;
                        $('#healthChecks-collapse').html('');
                        if (data.data && data.data.length > 0) {
                            if (sortUnhealthyFirst) {
                                data.data.sort(function(a, b) {
                                    if (a.status === 'unhealthy' && b.status !== 'unhealthy') return -1;
                                    if (b.status === 'unhealthy' && a.status !== 'unhealthy') return 1;
                                    return a.name.localeCompare(b.name);
                                });
                            }
                            data.data.forEach(function(service) {
                                let serviceStatus = service.status || 'unknown';
                                let serviceName = service.name || 'Unknown Service';
                                let serviceType = service.type || 'unknown';
                                let serviceHost = service.host || 'unknown';
                                let servicePort = service.port || 'unknown';
                                // Convert last_checked from UTC to current browser timezone (GMT)
                                let serviceLastChecked = service.last_checked ? convertUTCHealthLastCheckedStringToLocal(service.last_checked) : 'unknown';
                                let serviceProtocol = service.protocol || 'http';
                                let servicePath = service.http_path || '/';
                                let serviceExpectedStatus = service.http_expected_status || 200;
                                let healthClass = (serviceStatus === 'healthy') ? 'success' : (serviceStatus === 'unhealthy') ? 'danger' : 'warning';

                                // Group healthchecks into rows of 3
                                if ($('#healthChecks-collapse .row').length === 0 || $('#healthChecks-collapse .row:last-child .col-xl-4').length >= 3) {
                                    $('#healthChecks-collapse').append('<div class="row pb-2"></div>');
                                }
                                $('#healthChecks-collapse .row:last-child').append(`
                                    <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 col-xs-12">
                                        <div class="card card-rounded bg-inverse mb-lg-0 mb-2 monitorr-card">
                                            <div class="card-body pt-1 pb-1">
                                                <div class="d-flex no-block align-items-center justify-content-between">
                                                    <div class="d-flex align-items-center">
                                                        <div class="left-health bg-info"></div>
                                                        <div class="ms-1 w-100 d-flex">
                                                            <i class="float-right mt-2 mb-2 me-2 fa fa-check-circle h3 text-\${healthClass}"></i>
                                                            <h4 class="d-flex no-block align-items-center mt-2 mb-2">\${serviceName}</h4>
                                                            <div class="clearfix"></div>
                                                        </div>
                                                    </div>
                                                    <!-- <span class="badge text-bg-\${healthClass} float-end">Last checked: \${serviceLastChecked}</span> -->
                                                    <small class="text-muted me-2">
                                                        Last checked: \${serviceLastChecked}
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                `);
                            });
                        } else {
                            $('#healthChecks-collapse').append('<p>No health checks available.</p>');
                        }
                    })
                }

                async function refreshHealthData() {
                    const delay = ms => new Promise(res => setTimeout(res, ms));
                    try {
                        while (true) {
                            loadHealthData();
                            await delay(30000);
                        }
                    } catch (err) {
                        console.log(err);
                    }
                }

                refreshHealthData();
                </script>
                EOF;

            return $output;
        }
    }
}
// Register Custom HTML Widgets
$phpef->dashboard->registerWidget('Health Checks', new HealthChecksWidget($phpef));