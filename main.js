function hideOrShowFields() {
    const type = $("#SettingsModalBody_Health_Check").find("select[name=\"type\"]").val();
    const http_expected_status_match_type = $("#SettingsModalBody_Health_Check").find("select[name=\"http_expected_status_match_type\"] :selected").val();
    const http_body_match_type = $("#SettingsModalBody_Health_Check").find("select[name=\"http_body_match_type\"] :selected").val();
    $("#SettingsModalBody_Health_Check").find("input[name=\"port\"]").closest(".col-md-6").hide();
    $("#SettingsModalBody_Health_Check").find("input[name=\"http_path\"]").closest(".col-md-6").hide();
    $("#SettingsModalBody_Health_Check").find("input[name=\"http_expected_status\"]").closest(".col-md-6").hide();
    $("#SettingsModalBody_Health_Check").find("select[name=\"http_expected_status_match_type\"]").closest(".col-md-6").hide();
    $("#SettingsModalBody_Health_Check").find("input[name=\"http_body_match\"]").closest(".col-md-6").hide();
    $("#SettingsModalBody_Health_Check").find("select[name=\"http_body_match_type\"]").closest(".col-md-6").hide();
    $("#SettingsModalBody_Health_Check").find("select[name=\"protocol\"]").closest(".col-md-6").hide();
    $("#SettingsModalBody_Health_Check").find("input[name=\"verify_ssl\"]").closest(".col-md-6").hide();
    $("#SettingsModalBody_Health_Check #httpSettingsTitle, #SettingsModalBody_Health_Check #httpSettingsBreak").hide();
    switch(type) {
        case "web":
            $("#SettingsModalBody_Health_Check").find("input[name=\"http_path\"]").closest(".col-md-6").show();
            $("#SettingsModalBody_Health_Check").find("select[name=\"protocol\"]").closest(".col-md-6").show();
            $("#SettingsModalBody_Health_Check").find("input[name=\"port\"]").closest(".col-md-6").show();
            $("#SettingsModalBody_Health_Check").find("input[name=\"verify_ssl\"]").closest(".col-md-6").show();
            $("#SettingsModalBody_Health_Check").find("select[name=\"http_body_match_type\"]").closest(".col-md-6").show();
            $("#SettingsModalBody_Health_Check").find("select[name=\"http_expected_status_match_type\"]").closest(".col-md-6").show();
            $("#SettingsModalBody_Health_Check #httpSettingsTitle, #SettingsModalBody_Health_Check #httpSettingsBreak").show();
            if (http_expected_status_match_type === "exact") {
                $("#SettingsModalBody_Health_Check").find("input[name=\"http_expected_status\"]").closest(".col-md-6").show();
            }
            if (http_body_match_type === "word" || http_body_match_type === "regex") {
                $("#SettingsModalBody_Health_Check").find("input[name=\"http_body_match\"]").closest(".col-md-6").show();
            }
            break;
        case "tcp":
            $("#SettingsModalBody_Health_Check").find("input[name=\"port\"]").closest(".col-md-6").show();
            break;
        case "icmp":
            break;
        default:
            break;
    }
}

function addCheckboxValueToFormData(formData, checkboxId, fieldName) {
    const isChecked = $(checkboxId).is(":checked") ? "1" : "0";
    formData.push({ name: fieldName, value: isChecked });
}

function healthStatusFormatter(value) {
    if (value === 'healthy') {
    return '<span class="badge bg-success">Healthy</span>';
    } else if (value === 'unhealthy') {
    return '<span class="badge bg-danger">Unhealthy</span>';
    } else {
    return '<span class="badge bg-secondary">Unknown</span>';
    }
}

function priorityFormatter(value) {
    switch(value) {
        case -2:
            return '<span class="badge bg-primary">Very Low</span>';
        case -1:
            return '<span class="badge bg-info">Low</span>';
        case 0:
            return '<span class="badge bg-secondary">Normal</span>';
        case 1:
            return '<span class="badge bg-warning">High</span>';
        case 2:
            return '<span class="badge bg-danger">Critical</span>';
        default:
            return '<span class="badge bg-secondary">Normal</span>';
    }
}

function healthHistoryActionFormatter(value, row, index) {
    return [
        `<a class="inspect" title="Inspect">`,
        `<i class="fa fa-search"></i>`,
        "</a>"
    ].join("")
}

window.healthHistoryActionEvents = {
    "click .inspect": function (e, value, row, index) {
        if (row.result != "") {
            var jsonPretty = JSON.stringify(JSON.parse(row.result),null,2);
        } else {
            var jsonPretty = "No response data";
        }
        document.getElementById("healthCheckResponse").innerHTML = jsonPretty;
        $("#healthCheckInspectModal").modal("show");
    }
}

function loadHealthHistory(serviceId) {
    let historyElem = '#historyTable' + serviceId;
    $(historyElem).bootstrapTable('destroy'); // Optional: clear previous table
    $(historyElem).bootstrapTable({
        url: `/api/plugin/healthchecks/services/${serviceId}/history`,
        dataField: 'data',
        columns: [
            {
                field: 'checked_at',
                title: 'Timestamp',
                formatter: function(value) {
                    return convertUTCHealthLastCheckedStringToLocal(value);
                }
            },
            {
                field: 'status',
                title: 'Status',
                formatter: 'healthStatusFormatter'
            },
            {
                field: 'error',
                title: 'Error(s)'
            },
            {
                title: 'Actions',
                formatter: 'healthHistoryActionFormatter',
                events: 'healthHistoryActionEvents'
            }
        ],
        pagination: true,
        search: true,
        showRefresh: true
    });
}

function healthChecksServiceCallback(row = null) {
    const modalElement = document.getElementById("SettingsModal_Health_Check");
    if (modalElement) {
        modalElement.addEventListener('hidden.bs.modal', () => {
          $("#SettingsModal_Plugin").modal("show");
          $("#HealthChecksTable").bootstrapTable('refresh');
        });
    }
    hideOrShowFields();
    $(`#SettingsModalBody_Health_Check [name="type"], #SettingsModalBody_Health_Check [name="http_body_match_type"], #SettingsModalBody_Health_Check [name="http_expected_status_match_type"]`).off("change").on("change", function() {hideOrShowFields();});
}

function buildHealthCheckServiceSettingsModal(row) {
    createSettingsModal(row, {
        apiUrl: "/api/plugin/healthchecks/settings/services/"+row.id,
        configUrl: "/api/plugin/healthchecks/services/"+row.id,
        name: row.name,
        id: row.id,
        saveFunction: `submitSettingsModal("healthchecks/services","Health Check",false,"/api/plugin/");`,
        labelPrefix: "Health Check",
        dataLocation: "data",
        noTabs: true,
        noRows: true,
        callback: "healthChecksServiceCallback(row)"
    },"lg");
}

function buildNewHealthCheckServiceSettingsModal() {
    createSettingsModal([], {
        apiUrl: "/api/plugin/healthchecks/settings/services",
        configUrl: null,
        name: "New Service",
        saveFunction: `submitSettingsModal("healthchecks/services","Health Check",true,"/api/plugin/");`,
        labelPrefix: "Health Check",
        dataLocation: "data",
        noTabs: true,
        noRows: true,
        callback: "healthChecksServiceCallback(null)"
    },"lg");
}