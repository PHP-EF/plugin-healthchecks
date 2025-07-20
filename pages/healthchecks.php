<?php
  $healthChecksPlugin = new healthChecksPlugin();
  if ($healthChecksPlugin->auth->checkAccess($healthChecksPlugin->auth->checkAccess('Plugins','Health Checks')['ACL-READ'] ?? null) == false) {
    $ib->api->setAPIResponse('Error','Unauthorized',401);
    return false;
  }
  return <<<EOF
  <section class="section">
    <div class="row">
      <div class="col-lg-12">
        <div class="card">
          <div class="card-body">
            <center>
              <h4>Health Checks</h4>
              <p>View all health checks and their history.</p>
            </center>
          </div>
        </div>
      </div>
    </div>
    <br>
    <div class="row">
      <div class="col-lg-12">
        <div class="card">
          <div class="card-body">
            <div class="container">
              <div class="row justify-content-center">

                <div id="healthCheckContent"></p>

              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <br>
  </section>

  <!-- Inspect Modal -->
  <div class="modal fade" id="healthCheckInspectModal" tabindex="-1" role="dialog" aria-labelledby="healthCheckInspectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="healthCheckInspectModalLabel"></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true"></span>
          </button>
        </div>
        <div class="modal-body" id="healthCheckInspectModalBody">
          <pre><code id="healthCheckResponse" style="color:#FFF;">
          </code></pre>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Event listener for accordion expansion
    document.addEventListener('click', function(event) {
      if (event.target.matches('.accordion-button')) {
        const serviceId = event.target.getAttribute('data-service-id');
        if (serviceId) {
          loadHealthHistory(serviceId);
        }
      }
    });

    function convertUTCHealthLastCheckedStringToLocal(utcString) {
        const [datePart, timePart] = utcString.split(' ');
        const [year, month, day] = datePart.split('-').map(Number);
        const [hours, minutes, seconds] = timePart.split(':').map(Number);
        const utcDate = new Date(Date.UTC(year, month - 1, day, hours, minutes, seconds));
        return utcDate.toLocaleString();
    }

    // Use queryAPI to fetch health check data and populate the content
    function fetchHealthChecks() {
      queryAPI('GET','/api/plugin/healthchecks/enabled_services').done(function(data) {
      let content = '';
      if (data.data && data.data.length > 0) {
          data.data.forEach(service => {
              const lastChecked = convertUTCHealthLastCheckedStringToLocal(service.last_checked);
              let serviceName = service.name || 'Unknown Service';
              let serviceLastChecked = convertUTCHealthLastCheckedStringToLocal(service.last_checked);
              let serviceStatus = service.status || 'unknown';
              content += `
              <div class="accordion pb-1" id="accordion\${service.id}">
                  <div class="accordion-item">
                      <div class="accordion-header" id="heading\${service.id}">
                          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse\${service.id}" aria-expanded="true" aria-controls="collapse\${service.id}" data-service-id="\${service.id}">
                              \${serviceName} &nbsp;&nbsp; \${healthStatusFormatter(serviceStatus)}
                              <small class="text-muted ms-2">
                                  <strong>Last Checked:</strong> \${serviceLastChecked}
                              </small>
                          </button>
                      </div>
                      <div id="collapse\${service.id}" class="accordion-collapse collapse" aria-labelledby="heading\${service.id}" data-bs-parent="#accordion\${service.id}">
                          <div class="accordion-body">
                              <table class="table table-striped" id="historyTable\${service.id}"></table>
                          </div>
                      </div>
                  </div>
              </div>`;
          });
      } else {
          content = '<p>No health checks available.</p>';
      }
      document.getElementById('healthCheckContent').innerHTML = content;
      }).fail(function() {
          document.getElementById('healthCheckContent').innerHTML = '<p>Error fetching health checks.</p>';
      });
    }
    // Initial fetch of health checks
    fetchHealthChecks();
  </script>
EOF;