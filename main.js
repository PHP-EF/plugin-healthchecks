function clearServiceConfiguration() {
    $("#healthChecksServiceForm").find("input, select").val("");
}

function loadServiceConfiguration(row) {
    $("#healthChecksServiceForm").find("input[name=\"id\"]").val(row.id);
    $("#healthChecksServiceForm").find("input[name=\"enabled\"]").prop( "checked", (row.enabled ? true : false) );
    $("#healthChecksServiceForm").find("input[name=\"name\"]").val(row.name);
    $("#healthChecksServiceForm").find("select[name=\"type\"]").val(row.type);
    $("#healthChecksServiceForm").find("input[name=\"host\"]").val(row.host);
    $("#healthChecksServiceForm").find("input[name=\"port\"]").val(row.port);
    $("#healthChecksServiceForm").find("select[name=\"protocol\"]").val(row.protocol);
    $("#healthChecksServiceForm").find("input[name=\"timeout\"]").val(row.timeout);
    $("#healthChecksServiceForm").find("input[name=\"schedule\"]").val(row.schedule);
    $("#healthChecksServiceForm").find("input[name=\"http_path\"]").val(row.http_path);
    $("#healthChecksServiceForm").find("input[name=\"http_expected_status\"]").val(row.http_expected_status);
    $("#healthChecksServiceForm").find("input[name=\"verify_ssl\"]").prop( "checked", (row.verify_ssl ? true : false) );
    hideOrShowFields(row.type);
}

function hideOrShowFields(type) {
    $("#healthChecksServiceForm").find("input[name=\"port\"]").closest(".col-md-6").hide();
    $("#healthChecksServiceForm").find("input[name=\"http_path\"]").closest(".col-md-6").hide();
    $("#healthChecksServiceForm").find("input[name=\"http_expected_status\"]").closest(".col-md-6").hide();
    $("#healthChecksServiceForm").find("select[name=\"protocol\"]").closest(".col-md-6").hide();
    $("#healthChecksServiceForm").find("input[name=\"verify_ssl\"]").closest(".col-md-6").hide();
    switch(type) {
        case "web":
            $("#healthChecksServiceForm").find("input[name=\"http_path\"]").closest(".col-md-6").show();
            $("#healthChecksServiceForm").find("input[name=\"http_expected_status\"]").closest(".col-md-6").show();
            $("#healthChecksServiceForm").find("select[name=\"protocol\"]").closest(".col-md-6").show();
            $("#healthChecksServiceForm").find("input[name=\"port\"]").closest(".col-md-6").show();
            $("#healthChecksServiceForm").find("input[name=\"verify_ssl\"]").closest(".col-md-6").show();
            break;
        case "tcp":
            $("#healthChecksServiceForm").find("input[name=\"port\"]").closest(".col-md-6").show();
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