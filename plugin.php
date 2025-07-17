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
	'version' => '0.0.1', // SemVer of plugin
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
				'title' => 'Actions',
				'dataAttributes' => ['events' => 'healthChecksTableActionEvents', 'formatter' => 'editAndDeleteActionFormatter'],
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
				$this->settingsOption('js', 'pluginScript', ['id' => 'servicesScripts', 'script' => '
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
									<!-- Form will be injected here -->
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
				$("#healthChecksServiceModal").on("hidden.bs.modal", function () {
					$("#SettingsModal").modal("show");
					$("#HealthChecksTable").bootstrapTable("refresh");
				});

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
						$("#healthChecksServiceModal").find(".modal-title").text("Edit Service: " + row.name);
						$("#healthChecksServiceModal").find(".modal-body").html(`
							<form id="healthChecksServiceForm">
								<input type="hidden" name="id" value="${row.id}">
								<div class="mb-3">
									<label for="serviceName" class="form-label">Service Name</label>
									<input type="text" class="form-control" id="serviceName" name="name" value="${row.name}" required>
								</div>
								<div class="mb-3">
									<label for="serviceType" class="form-label">Service Type</label>
									<select class="form-select" id="serviceType" name="type" required>
										<option value="" disabled>Select Service Type</option>
										<option value="web" ${row.type === "web" ? "selected" : ""}>Web</option>
										<option value="tcp" ${row.type === "tcp" ? "selected" : ""}>TCP</option>
										<option value="icmp" ${row.type === "icmp" ? "selected" : ""}>ICMP</option>
									</select>
								</div>
								<div class="mb-3">
									<label for="serviceHost" class="form-label">Host</label>
									<input type="text" class="form-control" id="serviceHost" name="host" value="${row.host}" required>
								</div>
								<div class="mb-3">
									<label for="servicePort" class="form-label">Port</label>
									<input type="number" class="form-control" id="servicePort" name="port" value="${row.port}">
								</div>
								<div class="mb-3">
									<label for="serviceProtocol" class="form-label">Protocol</label>
									<select class="form-select" id="serviceProtocol" name="protocol">
										<option value="" disabled>Select Protocol</option>
										<option value="http" ${row.protocol === "http" ? "selected" : ""}>HTTP</option>
										<option value="https" ${row.protocol === "https" ? "selected" : ""}>HTTPS</option>
										<option value="tcp" ${row.protocol === "tcp" ? "selected" : ""}>TCP</option>
									</select>
								</div>
								<div class="mb-3">
									<label for="serviceHttpPath" class="form-label">HTTP Path (if applicable)</label>
									<input type="text" class="form-control" id="serviceHttpPath" name="http_path" value="${row.http_path || "/"}">
								</div>
								<div class="mb-3">
									<label for="serviceTimeout" class="form-label">Timeout (seconds)</label>
									<input type="number" class="form-control" id="serviceTimeout" name="timeout" value="${row.timeout || 5}">
								</div>
								<div class="mb-3">
									<label for="serviceExpectedStatus" class="form-label">Expected HTTP Status (if applicable)</label>
									<input type="number" class="form-control" id="serviceExpectedStatus" name="http_expected_status" value="${row.http_expected_status || 200}">
								</div>
							</form>
						`);
						$("#saveServiceButton").on("click", function(e) {
							e.preventDefault();
							var formData = $("#healthChecksServiceForm").serializeArray();
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
					}
				};
				']),
				$this->settingsOption('notice', '', ['title' => 'Information', 'body' => '
				<p>This plugin enables support for ICMP, TCP & HTTP/S Health Checks.</p>
				<br/>']),
			),
			'Plugin Settings' => array(
				$this->settingsOption('auth', 'ACL-WRITE', ['label' => 'Plugin Admin ACL']),
			),
			'Health Checks' => array(
				$this->settingsOption('bootstrap-table', 'HealthChecksTable', ['id' => 'HealthChecksTable', 'columns' => $HealthChecksTableColumns, 'dataAttributes' => $HealthChecksTableAttributes, 'width' => '12']),
			)
		);
	}
}