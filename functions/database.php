<?php
trait HealthChecksDatabase {
        // **
    // DATABASE
    // **

    // Check if Database & Tables Exist
    private function hasDB() {
        if ($this->sql) {
            try {
                // Query to check if both tables exist
                $result = $this->sql->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('services','history','options')");
                $tables = $result->fetchAll(PDO::FETCH_COLUMN);

                if (in_array('services', $tables) && in_array('history', $tables) && in_array('options', $tables)) {
                    return true;
                } else {
                    $this->createHealthChecksTables();
                }
            } catch (PDOException $e) {
                $this->api->setAPIResponse("Error",$e->getMessage());
                return false;
            }
        } else {
            $this->api->setAPIResponse("Error","Database Not Initialized");
            return false;
        }
    }

    // Initiate Database Migration if required
    private function checkDB() {
        $currentVersion = $this->sqlHelper->getDatabaseVersion();
        $newVersion = $GLOBALS['plugins']['Health Checks']['version'];
        if ($currentVersion < $newVersion) {
            $this->sqlHelper->updateDatabaseSchema($currentVersion, $newVersion, $this->migrationScripts());
        }
    }

    // Database Migration Script(s) for changes between versions
    public function migrationScripts() {
        return [
            '0.0.1' => [],
            '0.0.2' => [
                "ALTER TABLE services ADD COLUMN schedule TEXT DEFAULT '*/5 * * * *'",
            ],
            '0.0.3' => [
                "ALTER TABLE services ADD COLUMN notified BOOLEAN DEFAULT 0",
            ],
            '0.0.4' => [
                "ALTER TABLE history RENAME COLUMN response TO result",
            ],
            '0.0.5' => [],
            '0.0.6' => [],
            '0.0.7' => [],
            '0.0.8' => [
                'ALTER TABLE services ADD COLUMN priority INTEGER DEFAULT 0',
            ],
            '0.0.9' => [],
            '0.1.0' => [],
            '0.1.1' => [
                'ALTER TABLE services ADD COLUMN image TEXT DEFAULT NULL',
            ],
            '0.1.2' => [
                'ALTER TABLE services ADD COLUMN http_expected_status_match_type TEXT DEFAULT "any"',
                'ALTER TABLE services ADD COLUMN http_body_match_type TEXT DEFAULT "none"',
                'ALTER TABLE services ADD COLUMN http_body_match TEXT DEFAULT NULL'
            ],
            '0.1.3' => [
                'ALTER TABLE services ADD COLUMN http_method TEXT DEFAULT "get"',
            ],
            '0.1.4' => [],
            '0.1.5' => []            
        ];
    }

    // Create Media Manager Tables
    private function createHealthChecksTables() {
        $this->sql->exec("CREATE TABLE IF NOT EXISTS services (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            enabled BOOLEAN,
            name TEXT NOT NULL,
            type TEXT NOT NULL,
            host TEXT NOT NULL,
            port INTEGER,
            timeout INTEGER DEFAULT 15,
            protocol TEXT,
            http_path TEXT,
            http_method TEXT DEFAULT 'get',
            http_expected_status INTEGER,
            http_expected_status_match_type TEXT DEFAULT 'any',
            http_body_match_type TEXT DEFAULT 'none',
            http_body_match TEXT,
            verify_ssl BOOLEAN DEFAULT 0,
            schedule TEXT DEFAULT '*/5 * * * *', -- Default to every 5 minutes
            last_checked DATETIME,
            status TEXT,
            priority INTEGER DEFAULT 0,
            notified BOOLEAN DEFAULT 0
        )");

        ## Create first example entries in the services table
        $stmt = $this->sql->prepare("INSERT INTO services (name, enabled, type, host, port, protocol, http_expected_status, http_expected_status_match_type, http_path, http_method, schedule) VALUES (:name, :enabled, :type, :host, :port, :protocol, :http_expected_status, :http_expected_status_match_type, :http_path, :http_method, :schedule)");
        $stmt->execute([
            ':name' => 'Example Service',
            ':enabled' => 0,
            ':type' => 'web',
            ':host' => 'example.com',
            ':port' => 80,
            ':protocol' => 'http',
            ':http_expected_status_match_type' => 'exact',
            ':http_expected_status' => 200,
            ':http_path' => '/',
            ':http_method' => 'get',
            ':schedule' => '*/5 * * * *'
        ]);
        $stmt->execute([
            ':name' => 'Example TCP Service',
            ':enabled' => 0,
            ':type' => 'tcp',
            ':host' => 'example.com',
            ':port' => 80,
            ':protocol' => 'tcp',
            ':http_expected_status' => null,
            ':http_expected_status_match_type' => null,
            ':http_path' => null,
            ':schedule' => '*/5 * * * *'
        ]);
        $stmt->execute([
            ':name' => 'Example ICMP Service',
            ':enabled' => 0,
            ':type' => 'icmp',
            ':host' => 'example.com',
            ':port' => null,
            ':protocol' => 'icmp',
            ':http_expected_status' => null,
            ':http_expected_status_match_type' => null,
            ':http_path' => null,
            ':schedule' => '*/5 * * * *'
        ]);

        $this->sql->exec("CREATE TABLE IF NOT EXISTS history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            service_id INTEGER NOT NULL,
            checked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            status TEXT,
            result TEXT,
            error TEXT,
            FOREIGN KEY (service_id) REFERENCES services(id)
        )");

        $this->sql->exec("CREATE TABLE IF NOT EXISTS options (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            Key TEXT,
            Value TEXT
        )");

        $this->sql->exec('INSERT INTO options (Key,Value) VALUES ("dbVersion","'.$GLOBALS['plugins']['Health Checks']['version'].'");');
    }

    public $validServiceSorts = ['name', 'type', 'host', 'port', 'protocol', 'status', 'enabled', 'last_checked'];

    private function validateSort($sort,$order) {
        if (!in_array($sort, $this->validServiceSorts)) {
            $sort = 'name';
        }
        if ($order !== 'asc' && $order !== 'desc') {
            $order = 'asc';
        }
        return [$sort, $order];
    }

    // Get a list of Services from Database
    public function getServices($sort = 'name', $order = 'asc') {
        list($sort, $order) = $this->validateSort($sort, $order);
        $stmt = $this->sql->prepare("SELECT * FROM services ORDER BY $sort $order");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get a Service by ID from Database
    public function getServiceById($id) {
        $stmt = $this->sql->prepare("SELECT * FROM services WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get enabled Services from Database
    public function getEnabledServices($sort = 'name', $order = 'asc') {
        list($sort, $order) = $this->validateSort($sort, $order);
        $stmt = $this->sql->prepare("SELECT * FROM services WHERE enabled = 1 ORDER BY $sort $order");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get the service history for a service
    public function getServiceHistory($serviceId) {
        $stmt = $this->sql->prepare("SELECT * FROM history WHERE service_id = :service_id ORDER BY checked_at DESC");
        $stmt->execute([':service_id' => $serviceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Create a new service in the database
    public function createService($data) {
        $stmt = $this->sql->prepare("INSERT INTO services (name, enabled, type, host, port, protocol, http_path, timeout, schedule, priority, http_expected_status, http_expected_status_match_type, http_body_match, http_body_match_type, verify_ssl) VALUES (:name, :enabled, :type, :host, :port, :protocol, :http_path, :timeout, :schedule, :priority, :http_expected_status, :http_expected_status_match_type, :http_body_match, :http_body_match_type, :verify_ssl)");
        switch($data['type']) {
            case 'icmp':
                $data['protocol'] = 'icmp'; // Set protocol to ICMP
                break;
            case 'tcp':
                $data['protocol'] = 'tcp'; // Set protocol to TCP
                break;
            case 'web':
                $data['protocol'] = $data['protocol'] ?? 'http'; // Default to HTTP if not specified
                $data['http_path'] = $data['http_path'] ?? '/'; // Default to root path if not specified
                $data['http_method'] = $data['http_method'] ?? 'get'; // Default to GET if not specified
                if ($data['http_expected_status_match_type'] === 'exact') {
                    $data['http_expected_status'] = $data['http_expected_status'] ?? 200; // Default expected status to 200 if not specified
                } elseif ($data['http_expected_status_match_type'] === 'any') {
                    $data['http_expected_status'] = 0; // Any status means no specific expectation
                }
                $data['verify_ssl'] = $data['verify_ssl'] ?? 0; // Default to not verifying SSL
                break;
            default:
                break;
        }
        $stmt->execute([
            ':name' => $data['name'],
            ':enabled' => $data['enabled'] ?? 0,
            ':type' => $data['type'],
            ':host' => $data['host'],
            ':port' => $data['port'] ?? null,
            ':protocol' => $data['protocol'] ?? null,
            ':http_path' => $data['http_path'] ?? null,
            ':http_method' => $data['http_method'] ?? 'get',
            ':timeout' => $data['timeout'] ?: 5,
            ':schedule' => $data['schedule'] ?: '*/5 * * * *',
            ':priority' => $data['priority'] ?: 0,
            ':http_expected_status' => $data['http_expected_status'] ?? null,
            ':http_expected_status_match_type' => $data['http_expected_status_match_type'] ?? 'any',
            ':http_body_match' => $data['http_body_match'] ?? null,
            ':http_body_match_type' => $data['http_body_match_type'] ?? 'none',
            ':verify_ssl' => $data['verify_ssl'] ?? 0
        ]);
        $lastInsertId = $this->sql->lastInsertId();
        if ($lastInsertId) {
            $this->api->setAPIResponseMessage('Service created successfully');
            $this->api->setAPIResponseData($this->getServiceById($lastInsertId));
        } else {
            $this->api->setAPIResponse('error', 'Error creating service');
        }
        return $lastInsertId;
    }

    public function updateService($id,$data) {
        $set = [];
        $params = [':id' => $id];

        // Update defaults when type is changed
        if (isset($data['type'])) {
            switch($data['type']) {
                case 'icmp':
                    $data['protocol'] = 'icmp'; // Set protocol to ICMP
                    break;
                case 'tcp':
                    $data['protocol'] = 'tcp'; // Set protocol to TCP
                    break;
                case 'web':
                    $data['protocol'] = $data['protocol'] ?? 'http'; // Default to HTTP if not specified
                    $data['http_path'] = $data['http_path'] ?? '/'; // Default to root path if not specified
                    $data['http_method'] = $data['http_method'] ?? 'get'; // Default to GET if not specified
                    $data['http_expected_status'] = $data['http_expected_status'] ?? 200; // Default expected status to 200 if not specified
                    $data['verify_ssl'] = $data['verify_ssl'] ?? 0; // Default to not verifying SSL
                    $data['http_expected_status_match_type'] = $data['http_expected_status_match_type'] ?? 'any'; // Default to exact match
                    $data['http_body_match'] = $data['http_body_match'] ?? null; // Default to no body match
                    $data['http_body_match_type'] = $data['http_body_match_type'] ?? 'none'; // Default to no body match type
                    break;
                default:
                    break;
            }
        }

        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                if (is_bool($value)) {
                    $value = $value ? true : false;
                    $set[] = "$key = :$key";
                    $params[":$key"] = $value;
                } elseif (is_numeric($value)) {
                    $set[] = "$key = :$key";
                    $params[":$key"] = $value;
                } elseif (isset($value)) {
                    $set[] = "$key = :$key";
                    $params[":$key"] = $value;
                }
            }
        }

        if (empty($set)) {
            $this->api->setAPIResponseMessage('Nothing to update');
            return false; // No fields to update
        }

        $setString = implode(', ', $set);
        $stmt = $this->sql->prepare("UPDATE services SET $setString WHERE id = :id");
        $stmt->execute($params);
        if ($stmt->rowCount() > 0) {
            $this->api->setAPIResponseMessage('Service updated successfully');
            $this->api->setAPIResponseData($this->getServiceById($id));
            return true;
        } else {
            $this->api->setAPIResponseMessage('Error updating service, no changes were made');
            return false;
        }
    }

    // Delete a service from the database
    public function deleteService($id) {
        $stmt = $this->sql->prepare("DELETE FROM services WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // Save a new check history entry
    private function saveCheckHistory($result,$service) {
        // Check if state has changed
        if ($service['status'] != $result['status']) {
            $stmt = $this->sql->prepare("INSERT INTO history (service_id, status, result, error) VALUES (:service_id, :status, :result, :error)");
            $stmt->execute([
                ':service_id' => $result['id'],
                ':status' => $result['status'],
                ':result' => json_encode($result ?? []),
                ':error' => $result['error'] ?? null,
            ]);
        }
        $this->saveCheckStatus($result);
    }

    // Save the status of a service after a check
    private function saveCheckStatus($result) {
        $stmt = $this->sql->prepare("
            UPDATE services SET
                last_checked = datetime('now'),
                status = :status,
                notified = :notified
            WHERE id = :id
        ");

        $stmt->execute([
            ':status' => $result['status'],
            ':id' => $result['id'],
            ':notified' => $result['notified'] ?? 0
        ]);
    }

}