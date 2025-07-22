<?php
// **
// USED TO DEFINE PLUGIN INFORMATION & CLASS
// **

// PLUGIN INFORMATION - This should match what is in plugin.json
$GLOBALS['plugins']['Health Checks'] = [ // Plugin Name
	'name' => 'Health Checks', // Plugin Name
	'author' => 'TehMuffinMoo', // Who wrote the plugin
	'category' => 'Monitoring', // One to Two Word Description
	'link' => 'https://github.com/php-ef/plugin-healthchecks', // Link to plugin info
	'version' => '0.1.2', // SemVer of plugin
	'image' => 'logo.png', // 1:1 non transparent image for plugin
	'settings' => true, // does plugin need a settings modal?
	'api' => '/api/plugin/healthchecks/settings', // api route for settings page, or null if no settings page
];

// Include Health Checks Functions
foreach (glob(__DIR__.'/functions/*.php') as $function) {
    require_once $function; // Include each PHP file
}

class healthChecksPlugin extends phpef {
    use HealthChecksGeneral,
	HealthChecksDatabase,
	HealthChecksServiceChecker;

    public $pluginConfig;
    private $sql;
    private $sqlHelper;

    public function __construct() {
        parent::__construct();
        $this->loadConfig();
		$servicesPath = dirname(__DIR__,2). DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'HealthChecks.json';
        $dbFile = dirname(__DIR__,2). DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'HealthChecks.db';
        $this->sql = new PDO("sqlite:$dbFile");
        $this->sql->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->hasDB();
        $this->sqlHelper = new dbHelper($this->sql);
        $this->checkDB();
    }

	public function _pluginGetSettings() {

		$TableAttributes = [
			'data-field' => 'data',
			'toggle' => 'table',
			'search' => 'true',
			'filter-control' => 'true',
			'show-refresh' => 'true',
			'pagination' => 'true',
			'toolbar' => '#toolbar',
			'show-columns' => 'true',
			'page-size' => '25',
			'buttons' => 'healthChecksServicesButtons',
			'response-handler' => 'responseHandler',
		];
	
		$HealthChecksTableColumns = [
			[
				'field' => 'id',
				'title' => 'Id',
				'dataAttributes' => ['visible' => 'false']
			],
			[
				'field' => 'name',
				'title' => 'Name',
                'dataAttributes' => ['sortable' => 'true'],
			],
			[
				'field' => 'type',
				'title' => 'Type',
                'dataAttributes' => ['sortable' => 'true'],
			],
			[
				'field' => 'host',
				'title' => 'Host',
                'dataAttributes' => ['sortable' => 'true'],
			],
			[
				'field' => 'port',
				'title' => 'Port',
                'dataAttributes' => ['sortable' => 'true'],
			],
			[
				'field' => 'protocol',
				'title' => 'Protocol',
                'dataAttributes' => ['sortable' => 'true'],
			],
			[
				'field' => 'status',
				'title' => 'Status',
                'dataAttributes' => ['sortable' => 'true'],
				'dataAttributes' => ['sortable' => 'true', 'formatter' => 'healthStatusFormatter'],
			],
			[
				'field' => 'last_checked',
				'title' => 'Last Checked',
                'dataAttributes' => ['sortable' => 'true'],
			],
			[
				'field' => 'schedule',
				'title' => 'Schedule',
                'dataAttributes' => ['sortable' => 'true', 'visible' => 'false'],
			],
			[
				'field' => 'priority',
				'title' => 'Priority',
                'dataAttributes' => ['sortable' => 'true', 'visible' => 'false', 'formatter' => 'priorityFormatter'],
			],
			[
				'field' => 'enabled',
				'title' => 'Enabled',
                'dataAttributes' => ['sortable' => 'true', 'formatter' => 'booleanTickCrossFormatter'],
			],
			[
				'title' => 'Actions',
				'dataAttributes' => ['events' => 'healthChecksTableActionEvents', 'formatter' => 'healthChecksServicesTableFormatter'],
			]
		];

		$HealthChecksTableAttributes = $TableAttributes;
		$HealthChecksTableAttributes['url'] = '/api/plugin/healthchecks/services';
		$HealthChecksTableAttributes['search'] = 'true';
		$HealthChecksTableAttributes['filter-control'] = 'true';
		$HealthChecksTableAttributes['show-refresh'] = 'true';
		$HealthChecksTableAttributes['pagination'] = 'true';
		$HealthChecksTableAttributes['toolbar'] = '#toolbar';
		$HealthChecksTableAttributes['sort-name'] = $this->pluginConfig['defaultSort'];
		$HealthChecksTableAttributes['sort-order'] = $this->pluginConfig['defaultSortOrder'];
		$HealthChecksTableAttributes['show-columns'] = 'true';
		$HealthChecksTableAttributes['page-size'] = '25';

        $AppendNone = array(
            [
                "name" => 'None',
                "value" => ''
            ]
        );

		$NotificationProviders = array(
            "SMTP" => array(
				$this->settingsOption('checkbox', 'smtpEnable', ['label' => 'Enable SMTP Notifications', 'help' => 'Enable to send email notifications for service status changes.']),
				$this->settingsOption('input', 'smtpName', ['label' => 'From Name', 'help' => 'The name displayed that notifications will be sent from. This will default to the globally configured SMTP From Name if not set.', 'placeholder' => $this->config->get('SMTP', 'from_name') ?? '']),
				$this->settingsOption('input', 'smtpFrom', ['label' => 'From Address', 'help' => 'The email address that notifications will be sent from. This will default to the globally configured SMTP From Address if not set.', 'placeholder' => $this->config->get('SMTP', 'from_email') ?? '']),
				$this->settingsOption('input', 'smtpTo', ['label' => 'To Address', 'help' => 'The email address that notifications will be sent to. This will default to the globally configured SMTP To Address if not set.', 'placeholder' => $this->config->get('SMTP', 'to_email') ?? ''])
            ),
            "Pushover" => array(
				$this->settingsOption('checkbox', 'pushoverEnable', ['label' => 'Enable Pushover Notifications', 'help' => 'Enable to send pushover notifications for service status changes.']),
				$this->settingsOption('blank'),
				$this->settingsOption('password-alt', 'pushoverApiToken', ['label' => 'Pushover API Token', 'help' => 'The Pushover API Token to use for sending notifications. This will default to the globally configured API Token if not set.', 'placeholder' => '']),
				$this->settingsOption('password-alt', 'pushoverUserKey', ['label' => 'Pushover User Key', 'help' => 'The Pushover User Key to send notifications to. This will default to the globally configured User Key if not set.', 'placeholder' => '']),
				$this->settingsOption('hr'),
				$this->settingsOption('title','criticalNotifications',['text' => 'Critical Notifications']),
				$this->settingsOption('number', 'pushoverRetry', ['label' => 'Retry Interval (seconds)', 'help' => 'The interval in seconds to retry sending Critical notifications upon failure. This will default to 60 seconds if not set.', 'placeholder' => '60']),
				$this->settingsOption('number', 'pushoverExpire', ['label' => 'Expiration Time (seconds)', 'help' => 'The time in seconds after which Critical the notification will expire. This will default to 3600 seconds if not set.', 'placeholder' => '3600']),
            ),
			"Webhooks (Not Implemented)" => array(
			)
        );

		return array(
			'About' => array (
				$this->settingsOption('js', 'pluginJs', ['src' => '/api/page/plugin/Health Checks/js']),
				$this->settingsOption('js', 'pluginScript', ['id' => 'servicesScripts', 'script' => '
				function healthChecksServicesButtons() {
					return {
						btnAddService: {
							text: "Create new Service",
							icon: "bi bi-plus-lg",
							event: function() {
								$("#SettingsModal_Plugin").modal("hide");
								buildNewHealthCheckServiceSettingsModal();
							},
							attributes: {
								title: "Create new Service",
								style: "background-color:#4bbe40;border-color:#4bbe40;"
							}
						}
					}
				}

				function healthChecksServicesTableFormatter(value, row, index) {
					var buttons = [
						`<a class="test" title="Test"><i class="fa-solid fa-vial-circle-check"></i></a>&nbsp;`,
						`<a class="edit" title="Edit"><i class="fa fa-pencil"></i></a>&nbsp;`,
						`<a class="delete" title="Delete"><i class="fa fa-trash"></i></a>&nbsp;`
					];
					return buttons.join("");
				}

				window.healthChecksTableActionEvents = {
					"click .delete": function (e, value, row, index) {
						if(confirm("Are you sure you want to delete the Service: "+row.name+"? This is irriversible.") == true) {
							queryAPI("DELETE","/api/plugin/healthchecks/services/"+row.id).done(function(data) {
							if (data["result"] == "Success") {
								toast("Success","","Successfully deleted "+row.name+" from Services","success");
								var tableId = `#${$(e.currentTarget).closest("table").attr("id")}`;
								$(tableId).bootstrapTable("refresh");
							} else if (data["result"] == "Error") {
								toast(data["result"],"",data["message"],"danger","30000");
							} else {
								toast("Error","","Failed to delete "+row.name+" from Services","danger");
							}
							}).fail(function() {
								toast("Error", "", "Failed to remove " + row.name + " from Services", "danger");
							});
						}
					},
					"click .edit": function (e, value, row, index) {
						$("#SettingsModal_Plugin").modal("hide");
						buildHealthCheckServiceSettingsModal(row);
					},
					"click .test": function (e, value, row, index) {
						if (row.enabled == 0) {
							toast("Error", "", "Service is not enabled, please enable it before testing.", "danger");
							return;
						}
						toast("Test Started", row.name, "Testing " + row.name, "info","10000");
						queryAPI("GET", "/api/plugin/healthchecks/check/"+row.id).done(function(data) {
							if (data["result"] == "Success") {
								if (data["data"]["status"] == "healthy") {
									toast("Test Successful", row.name, "Service is online", "success");
								} else {
									if (data["data"]["error"]) {
										toast("Test Failed", row.name, data["data"]["error"], "danger","30000");
									} else {
										if (data["data"]["type"] == "web") {
											toast("Test Failed", row.name, "HTTP Status Code: " + data["data"]["http_code"] + " - <b>Expected:</b> " + row.http_expected_status + "\n\n<b>Response: </b> " + escapeHTML(data["data"]["response"]), "danger","30000");
										} else {
											toast("Test Failed", row.name, escapeHTML(data["data"]["response"]), "danger","30000");
										}
									}
								}
								$("#HealthChecksTable").bootstrapTable("refresh");
							} else {
								toast("Error", "", "Failed to check service: " + data["message"], "danger","30000");
							}
						}).fail(function() {
							toast("Error", "", "Failed to check service", "danger","30000");
						});
					}
				};
				']),
				$this->settingsOption('notice', '', ['title' => 'Information', 'body' => '
				<p>This plugin enables support for ICMP, TCP & HTTP/S Health Checks.</p>']),
			),
			'Plugin Settings' => array(
				$this->settingsOption('auth', 'ACL-READ', ['label' => 'Plugin User ACL', 'help' => 'This ACL is used to determine who can query the health of services. (Required for viewing the widget)']),
				$this->settingsOption('auth', 'ACL-WRITE', ['label' => 'Plugin Admin ACL', 'help' => 'This ACL is used to determine who can manage the Health Checks plugin.']),
				$this->settingsOption('select', 'defaultSort', ['label' => 'Default Sort Field', 'options' => $this->buildSortMenu(), 'help' => 'The default sort field for the health checks page and settings. The widget sort field is set in the widget settings.']),
				$this->settingsOption('select', 'defaultSortOrder', ['label' => 'Default Sort Order', 'options' => [['name' => 'Descending', 'value' => 'desc'],['name' => 'Ascending', 'value' => 'asc']], 'help' => 'The default sort order for the health checks page and settings. The widget sort order is set in the widget settings.']),
				$this->settingsOption('checkbox', 'sortUnhealthyFirst', ['label' => 'Always show unhealthy services first', 'help' => 'Always display unhealthy services first for the health checks page and settings, regardless of the default sort option. The widget has its own setting is set in the widget configuration.']),
			),
			'Health Checks' => array(
				$this->settingsOption('bootstrap-table', 'HealthChecksTable', ['id' => 'HealthChecksTable', 'columns' => $HealthChecksTableColumns, 'dataAttributes' => $HealthChecksTableAttributes, 'width' => '12']),
			),
			'Notifications' => array(
				$this->settingsOption('checkbox', 'sendOnce', ['label' => 'Only send notifications once', 'help' => 'For each service state change, only send each notification type once.']),
				$this->settingsOption('checkbox', 'notifyOnHealthy', ['label' => 'Notify on Healthy', 'help' => 'Send a notification when a service returns to a healthy state. (This will only be sent once)']),
				$this->settingsOption('hr'),
				$this->settingsOption('accordion', 'NotificationProviders', ['id' => 'NotificationProviders', 'options' => $NotificationProviders, 'label' => 'Notification Providers', 'width' => '12']),
			)
		);
	}

	public function _pluginGetServicesSettings($id = null) {
		if ($id) {
			$service = $this->getServiceById($id);
			$image = $service['image'] ?? null;
		} else {
			$image = null;
		}
		return array(
			'Settings' => array(
				$this->settingsOption('input', 'name', ['label' => 'Service Name', 'help' => 'This is the name of the service you want to monitor.']),
				$this->settingsOption('select', 'type', ['label' => 'Service Type', 'options' => [['name' => 'Web', 'value' => 'web'],['name' => 'TCP', 'value' => 'tcp'],['name' => 'ICMP', 'value' => 'icmp']], 'help' => 'This is the type of service to monitor.']),
				$this->settingsOption('select', 'priority', ['label' => 'Priority', 'options' => [['name' => 'Very Low', 'value' => '-2'],['name' => 'Low', 'value' => '-2'],['name' => 'Normal', 'value' => '0'],['name' => 'High', 'value' => '1'],['name' => 'Critical', 'value' => '2']], 'help' => 'This is the type of service to monitor.']),
				$this->settingsOption('input', 'host', ['label' => 'FQDN / IP', 'help' => 'The FQDN / IP of the service to monitor.']),
				$this->settingsOption('number', 'port', ['label' => 'Port', 'help' => 'The port of the service to monitor.']),
				$this->settingsOption('number', 'timeout', ['label' => 'Timeout (Seconds)', 'placeholder' => '5', 'help' => 'The timeout for this monitored service. It is strongly suggested to keep this <30 seconds.']),
				$this->settingsOption('cron', 'schedule', ['label' => 'Schedule', 'placeholder' => '*/5 * * * *']),
				$this->settingsOption('hr', 'httpSettingsBreak', ['id' => 'httpSettingsBreak']),
				$this->settingsOption('title', 'httpSettings', ['text' => 'HTTP Settings', 'id' => 'httpSettingsTitle']),
				$this->settingsOption('input', 'http_path', ['label' => 'HTTP Path', 'placeholder' => '/', 'help' => 'The HTTP Path of the monitored service / endpoint.']),
				$this->settingsOption('select', 'protocol', ['label' => 'Protocol', 'options' => [['name' => 'HTTP', 'value' => 'http'],['name' => 'HTTPS', 'value' => 'https']], 'help' => 'This is the HTTP Protocol of monitored service.']),
				$this->settingsOption('select', 'http_expected_status_match_type', [
					'label' => 'Expected HTTP Status Match Type',
					'options' => [
						['name' => 'Any Status', 'value' => 'any'],
						['name' => 'Exact Match', 'value' => 'exact']
						// ['name' => 'Range Match', 'value' => 'range'],
					],
					'help' => 'How to match the HTTP status code. <br><b>Exact Match:</b> The status code must match exactly.<b>Any Status:</b> Any status code is considered healthy.'
					//<br><b>Range Match:</b> The status code must be within a specified range.<br>
				]),
				$this->settingsOption('number', 'http_expected_status', ['label' => 'Expected HTTP Status', 'placeholder' => '200', 'help' => 'The expected HTTP status to consider the service healthy.']),
				$this->settingsOption('select', 'http_body_match_type', ['label' => 'Body Match Type', 'options' => [['name' => 'None', 'value' => 'none'], ['name' => 'Word', 'value' => 'word'], ['name' => 'Regex', 'value' => 'regex']], 'help' => 'The type of match to use for the HTTP Body. <br><b>None:</b> Ignore response body.<br><b>Word:</b> The body must contain the word.<br><b>Regex:</b> The body must match the regex.']),
				$this->settingsOption('input', 'http_body_match', ['label' => 'HTTP Body Match', 'placeholder' => '', 'help' => 'The word or regex to match in the HTTP response body. This is only used if the Body Match Type is set to Word or Regex.']),
				$this->settingsOption('checkbox', 'verify_ssl', ['label' => 'Verify SSL/TLS', 'help' => 'Whether to verify certificates when using HTTP/S.']),
				$this->settingsOption('hr'),
				$this->settingsOption('checkbox', 'enabled', ['label' => 'Enable Health Check', 'help' => 'Enable/Disable the service health check.']),
				$this->settingsOption('imageselect', 'image', ['label' => 'Image', 'help' => 'An Image / Icon to use for the Health Check', 'value' => $image])
			)
		);
	}
}

// Include Health Checks Widgets
foreach (glob(__DIR__.'/widgets/*.php') as $widget) {
    require_once $widget; // Include each PHP file
}