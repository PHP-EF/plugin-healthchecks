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
	'version' => '0.0.4', // SemVer of plugin
	'image' => 'logo.png', // 1:1 non transparent image for plugin
	'settings' => true, // does plugin need a settings modal?
	'api' => '/api/plugin/healthchecks/settings', // api route for settings page, or null if no settings page
];

// Include Health Checks Functions
foreach (glob(__DIR__.'/functions/*.php') as $function) {
    require_once $function; // Include each PHP file
}

// Include Health Checks Widgets
foreach (glob(__DIR__.'/widgets/*.php') as $widget) {
    require_once $widget; // Include each PHP file
}

class healthChecksPlugin extends phpef {
    use HealthChecksGeneral,
	HealthChecksDatabase,
	HealthChecksServiceChecker;

    private $pluginConfig;
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
		$HealthChecksTableAttributes['sort-name'] = 'datetime';
		$HealthChecksTableAttributes['sort-order'] = 'asc';
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
				$this->settingsOption('select','pushoverPriority', [
					'label' => 'Pushover Priority',
					'options' => [
						['name' => 'Normal', 'value' => 0],
						['name' => 'High', 'value' => 1],
						['name' => 'Emergency', 'value' => 2]
					],
					'help' => 'The priority of the Pushover notification. Normal is the default, High will send a notification immediately, and Emergency will resend until acknowledged.'
				]),
				$this->settingsOption('password-alt', 'pushoverApiToken', ['label' => 'Pushover API Token', 'help' => 'The Pushover API Token to use for sending notifications. This will default to the globally configured API Token if not set.', 'placeholder' => '']),
				$this->settingsOption('password-alt', 'pushoverUserKey', ['label' => 'Pushover User Key', 'help' => 'The Pushover User Key to send notifications to. This will default to the globally configured User Key if not set.', 'placeholder' => ''])
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
								clearServiceConfiguration();
								$("#SettingsModal").modal("hide");
								$("#healthChecksServiceModal").modal("show");
								$("#saveServiceButton").off("click").on("click", function(e) {
									e.preventDefault();
									var formData = $("#healthChecksServiceForm").serializeArray();
									addCheckboxValueToFormData(formData, "#serviceEnabled", "enabled");
									addCheckboxValueToFormData(formData, "#serviceVerifySSL", "verify_ssl");
									var data = {};
									$.each(formData, function(i, field) {
										data[field.name] = field.value;
									});
									queryAPI("POST", "/api/plugin/healthchecks/services", data).done(function(response) {
										if (response["result"] == "Success") {
											toast("Success", "", "Successfully added service: " + data.name, "success");
											$("#healthChecksServiceModal").modal("hide");
											$("#HealthChecksTable").bootstrapTable("refresh");
										} else {
											toast("Error", "", "Failed to add service: " + response["message"], "danger");
										}
									}).fail(function() {
										toast("Error", "", "Failed to add service", "danger");
									});
								});
							},
							attributes: {
								title: "Create new Service",
								style: "background-color:#4bbe40;border-color:#4bbe40;"
							}
						}
					}
				}

				// Create modal if healthChecksServiceModal does not exist
				if (!document.getElementById("healthChecksServiceModal")) {
					var modalHtml = `
					<div class="modal fade" id="healthChecksServiceModal" tabindex="-1" aria-labelledby="healthChecksServiceModalLabel" aria-hidden="true">
						<div class="modal-dialog modal-lg">
							<div class="modal-content">
								<div class="modal-header">
									<h5 class="modal-title" id="healthChecksServiceModalLabel">Edit Service</h5>
									<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
								</div>
								<div class="modal-body">
									<form id="healthChecksServiceForm">
										<input type="hidden" name="id">
										<div class="row">
											<div class="col-md-6 pb-2">
												<label for="serviceName" class="form-label">Service Name</label>
												<input type="text" class="form-control" id="serviceName" name="name" required>
											</div>
											<div class="col-md-6 pb-2">
												<label for="serviceType" class="form-label">Service Type</label>
												<select class="form-select" id="serviceType" name="type" required>
													<option disabled>Select Service Type</option>
													<option value="web">Web</option>
													<option value="tcp">TCP</option>
													<option value="icmp">ICMP</option>
												</select>
											</div>
											<div class="col-md-6 pb-2">
												<label for="serviceHost" class="form-label">FQDN / IP</label>
												<input type="text" class="form-control" id="serviceHost" name="host" placeholder="app.example.com" required>
											</div>
											<div class="col-md-6 pb-2">
												<label for="servicePort" class="form-label">Port</label>
												<input type="number" class="form-control" id="servicePort" name="port" placeholder="80">
											</div>
											<div class="col-md-6 pb-2">
												<label for="serviceProtocol" class="form-label">Protocol</label>
												<select class="form-select" id="serviceProtocol" name="protocol">
													<option disabled>Select Protocol</option>
													<option value="http">HTTP</option>
													<option value="https">HTTPS</option>
												</select>
											</div>
											<div class="col-md-6 pb-2">
												<label for="serviceHttpPath" class="form-label">HTTP Path</label>
												<input type="text" class="form-control" id="serviceHttpPath" name="http_path" placeholder="/">
											</div>
											<div class="col-md-6 pb-2">
												<label for="serviceExpectedStatus" class="form-label">Expected HTTP Status</label>
												<input type="number" class="form-control" id="serviceExpectedStatus" name="http_expected_status" placeholder="200">
											</div>
											<div class="col-md-6 pb-2">
												<label for="serviceTimeout" class="form-label">Timeout (seconds)</label>
												<input type="number" class="form-control" id="serviceTimeout" name="timeout" placeholder="15">
											</div>
											<div class="col-md-6 pb-2">
												<label for="serviceSchedule" class="form-label">Schedule (Cron Format)</label>
												<input type="text" class="form-control" id="serviceSchedule" name="schedule" placeholder="*/5 * * * *">
											</div>
										</div>
										<hr>
										<div class="row">
											<div class="col-md-6 pb-2">
												<label for="serviceEnabled" class="form-label">Enabled</label>
												<div class="form-check form-switch">
													<input class="form-check-input info-field" type="checkbox" name="enabled" id="serviceEnabled" data-type="checkbox" data-label="Enabled">
												</div>
											</div>
											<div class="col-md-6 pb-2">
												<label for="serviceVerifySSL" class="form-label">Verify SSL/TLS</label>
												<div class="form-check form-switch">
													<input class="form-check-input info-field" type="checkbox" name="verify_ssl" id="serviceVerifySSL" data-type="checkbox" data-label="Enabled">
												</div>
											</div>
										</div>
									</form>
								</div>
								<div class="modal-footer">
									<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"> Close </button>
									<button type="button" class="btn btn-primary" id="saveServiceButton">Save Service</button>
								</div>
							</div>
						</div>
					</div>`;
					$("body").append(modalHtml);
				}
				$("#serviceType").off("change").on("change", function() {hideOrShowFields($(this).val())});
				$("#healthChecksServiceModal").on("hidden.bs.modal", function () {
					$("#SettingsModal").modal("show");
					$("#HealthChecksTable").bootstrapTable("refresh");
				});

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
						var modal = new bootstrap.Modal(document.getElementById("healthChecksServiceModal"));
						loadServiceConfiguration(row)
						$("#saveServiceButton").off("click").on("click", function(e) {
							e.preventDefault();
							var formData = $("#healthChecksServiceForm").serializeArray();
							addCheckboxValueToFormData(formData, "#serviceEnabled", "enabled");
							addCheckboxValueToFormData(formData, "#serviceVerifySSL", "verify_ssl");
							var data = {};
							$.each(formData, function(i, field) {
								data[field.name] = field.value;
							});
							queryAPI("PUT", "/api/plugin/healthchecks/services/"+data.id, data).done(function(response) {
								if (response["result"] == "Success") {
									toast("Success", "", "Successfully updated service: " + data.name, "success");
									modal.hide();
									$("#HealthChecksTable").bootstrapTable("refresh");
								} else {
									toast("Error", "", "Failed to update service: " + response["message"], "danger");
								}
							}).fail(function() {
								toast("Error", "", "Failed to update service", "danger");
							});
						});
						modal.show();
						$("#SettingsModal").modal("hide");
					},
					"click .test": function (e, value, row, index) {
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
}