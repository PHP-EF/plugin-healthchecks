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
	'version' => '0.0.2', // SemVer of plugin
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
			)
		);
	}

public function _pluginGetServicesSettings() {

        $AppendNone = array(
            [
                "name" => 'None',
                "value" => ''
            ]
        );

		return array(
			'Health Checks' => array(
				$this->settingsOption('input', 'id', ['label' => 'Service ID', 'attr' => 'hidden']),
				$this->settingsOption('input', 'name', ['label' => 'Service Name', 'placeholder' => 'My Service']),
				$this->settingsOption('select', 'type', ['label' => 'Service Type', 'options' => [
					['name' => 'Web (HTTP/S)', 'value' => 'web'],
					['name' => 'TCP', 'value' => 'tcp'],
					['name' => 'ICMP (Ping)', 'value' => 'icmp']
				]]),
				$this->settingsOption('input', 'host', ['label' => 'FQDN / IP', 'placeholder' => 'app.example.com']),
				$this->settingsOption('input', 'port', ['label' => 'Port', 'placeholder' => '80']),
				$this->settingsOption('select', 'protocol', ['label' => 'Protocol', 'options' => [
					['name' => 'HTTP', 'value' => 'http'],
					['name' => 'HTTPS', 'value' => 'https']
				], 'default' => 'http']),
				$this->settingsOption('input', 'http_path', ['label' => 'HTTP Path', 'placeholder' => '/', 'help' => 'Path to check, e.g. /status']),
				$this->settingsOption('input', 'http_expected_status', ['label' => 'Expected HTTP Status', 'placeholder' => '200', 'default' => '200']),
				$this->settingsOption('checkbox', 'verify_ssl', ['label' => 'Verify SSL/TLS', 'default' => true, 'help' => 'Enable SSL/TLS verification']),
				$this->settingsOption('input', 'timeout', ['label' => 'Timeout (seconds)', 'placeholder' => '15', 'default' => '15']),
				$this->settingsOption('input', 'schedule', ['label' => 'Schedule (Cron Format)', 'placeholder' => '*/5 * * * *', 'default' => '*/5 * * * * *']),
				$this->settingsOption('checkbox', 'enabled', ['label' => 'Enabled', 'default' => true, 'help' => 'Enable this service for health checks'])
			)
		);
	}
}