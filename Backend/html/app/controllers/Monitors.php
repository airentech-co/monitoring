<?php
require_once BASEPATH . 'app/controllers/Base.php';

class Monitors extends Base {
    
    public function index() {
        $monitors = $this->db->fetchAll(
            "SELECT * FROM tbl_monitor_ips ORDER BY last_monitor_tic DESC"
        );
        
        $this->view('monitors/index', ['monitors' => $monitors]);
    }
    
    public function create() {
        $this->requireAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ip_address = $_POST['ip_address'] ?? '';
            $name = $_POST['name'] ?? '';
            $username = $_POST['username'] ?? '';
            $mac_address = $_POST['mac_address'] ?? '';
            $type = $_POST['type'] ?? 'user';
            
            if (empty($ip_address) || empty($name)) {
                $error = 'IP address and name are required';
            } else {
                // Check if IP already exists
                $existing = $this->db->fetch(
                    "SELECT id FROM tbl_monitor_ips WHERE ip_address = :ip_address",
                    ['ip_address' => $ip_address]
                );
                
                if ($existing) {
                    $error = 'IP address already exists';
                } else {
                    // Check if username already exists
                    if (!empty($username)) {
                        $existing_username = $this->db->fetch(
                            "SELECT id FROM tbl_monitor_ips WHERE username = :username",
                            ['username' => $username]
                        );
                        
                        if ($existing_username) {
                            $error = 'Username already exists';
                        }
                    }
                    
                    if (empty($error)) {
                        $monitorId = $this->db->insert('tbl_monitor_ips', [
                            'ip_address' => $ip_address,
                            'name' => $name,
                            'username' => $username,
                            'mac_address' => $mac_address,
                            'type' => $type,
                            'os_status' => 0,
                            'monitor_status' => 0,
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                        
                        if ($monitorId) {
                            $this->redirect('monitors');
                        } else {
                            $error = 'Failed to create monitor';
                        }
                    }
                }
            }
        }
        
        $this->view('monitors/create', ['error' => $error ?? null]);
    }
    
    public function edit($id) {
        $this->requireAdmin();
        
        $monitor = $this->db->fetch(
            "SELECT * FROM tbl_monitor_ips WHERE id = :id",
            ['id' => $id]
        );
        
        if (!$monitor) {
            $this->redirect('monitors');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ip_address = $_POST['ip_address'] ?? '';
            $name = $_POST['name'] ?? '';
            $username = $_POST['username'] ?? '';
            $mac_address = $_POST['mac_address'] ?? '';
            $type = $_POST['type'] ?? 'user';
            
            if (empty($ip_address) || empty($name)) {
                $error = 'IP address and name are required';
            } else {
                // Check if username already exists for another monitor
                if (!empty($username)) {
                    $existing = $this->db->fetch(
                        "SELECT id FROM tbl_monitor_ips WHERE username = :username AND id != :id",
                        ['username' => $username, 'id' => $id]
                    );
                    
                    if ($existing) {
                        $error = 'Username already exists for another monitor';
                    }
                }
                
                if (empty($error)) {
                    $this->db->update('tbl_monitor_ips', [
                        'ip_address' => $ip_address,
                        'name' => $name,
                        'username' => $username,
                        'mac_address' => $mac_address,
                        'type' => $type
                    ], 'id = :id', ['id' => $id]);
                    
                    $this->redirect('monitors');
                }
            }
        }
        
        $this->view('monitors/edit', ['monitor' => $monitor, 'error' => $error ?? null]);
    }
    
    public function delete($id) {
        $this->requireAdmin();
        $this->db->delete('tbl_monitor_ips', 'id = :id', ['id' => $id]);
        $this->redirect('monitors');
    }
}
?> 