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
            '0.0.2' => []
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
            protocol TEXT DEFAULT 'http',
            http_path TEXT,
            timeout INTEGER DEFAULT 5,
            http_expected_status INTEGER DEFAULT 200,
            last_checked DATETIME,
            status TEXT
        )");

        ## Create first example entries in the services table
        $stmt = $this->sql->prepare("INSERT INTO services (name, enabled, type, host, port, protocol, http_expected_status, http_path) VALUES (:name, :enabled, :type, :host, :port, :protocol, :http_expected_status, :http_path)");
        $stmt->execute([
            ':name' => 'Example Service',
            ':enabled' => 0,
            ':type' => 'web',
            ':host' => 'example.com',
            ':port' => 80,
            ':protocol' => 'http',
            ':http_expected_status' => 200,
            ':http_path' => '/'
        ]);
        $stmt->execute([
            ':name' => 'Example TCP Service',
            ':enabled' => 0,
            ':type' => 'tcp',
            ':host' => 'example.com',
            ':port' => 80,
            ':protocol' => 'tcp',
            ':http_expected_status' => null,
            ':http_path' => null
        ]);
        $stmt->execute([
            ':name' => 'Example ICMP Service',
            ':enabled' => 0,
            ':type' => 'icmp',
            ':host' => 'example.com',
            ':port' => null,
            ':protocol' => 'icmp',
            ':http_expected_status' => null,
            ':http_path' => null
        ]);

        $this->sql->exec("CREATE TABLE IF NOT EXISTS history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            service_id INTEGER NOT NULL,
            checked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            status TEXT,
            response TEXT,
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

    // Get a list of Services from Database
    public function getServices() {
        $stmt = $this->sql->prepare("SELECT * FROM services");
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
        $stmt = $this->sql->prepare("INSERT INTO services (name, type, host, port, protocol, http_path, timeout, http_expected_status) VALUES (:name, :type, :host, :port, :protocol, :http_path, :timeout, :http_expected_status)");
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
                $data['http_expected_status'] = $data['http_expected_status'] ?? 200; // Default expected status to 200 if not specified
                break;
            default:
                break;
        }
        $stmt->execute([
            ':name' => $data['name'],
            ':type' => $data['type'],
            ':host' => $data['host'],
            ':port' => $data['port'] ?? null,
            ':protocol' => $data['protocol'] ?? null,
            ':http_path' => $data['http_path'] ?? null,
            ':timeout' => $data['timeout'] ?? 5,
            ':http_expected_status' => $data['http_expected_status'] ?? null
        ]);
        return $this->sql->lastInsertId();
    }

    // Delete a service from the database
    public function deleteService($id) {
        $stmt = $this->sql->prepare("DELETE FROM services WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // Save a new check history entry
    private function saveCheckHistory($result) {
        $stmt = $this->sql->prepare("INSERT INTO history (service_id, status, response, error) VALUES (:service_id, :status, :response, :error)");
        $stmt->execute([
            ':service_id' => $result['id'],
            ':status' => $result['status'],
            ':response' => $result['response'] ?? null,
            ':error' => $result['error'] ?? null
        ]);
        $this->saveCheckStatus($result);
    }

    // Save the status of a service after a check
    private function saveCheckStatus($result) {
        $stmt = $this->sql->prepare("
            UPDATE services SET
                last_checked = datetime('now'),
                status = :status
            WHERE id = :id
        ");

        $stmt->execute([
            ':status' => $result['status'],
            ':id' => $result['id']
        ]);
    }

}