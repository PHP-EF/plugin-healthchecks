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