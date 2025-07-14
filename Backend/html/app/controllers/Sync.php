<?php
require_once BASEPATH . 'app/controllers/Base.php';
require_once BASEPATH . 'app/services/DataSyncService.php';

class Sync extends Base {
    private $dataSync;
    
    public function __construct() {
        parent::__construct();
        $this->dataSync = new DataSyncService();
    }
    
    public function index() {
        $this->requireAdmin();
        
        // Get IP directories info
        $ip_dirs = $this->dataSync->getIPDirectories();
        
        // Sync monitors with data
        $synced_count = $this->dataSync->syncMonitorsWithData();
        
        $this->view('sync/index', [
            'ip_dirs' => $ip_dirs,
            'synced_count' => $synced_count
        ]);
    }
    
    public function api_sync() {
        $this->requireAdmin();
        
        $synced_count = $this->dataSync->syncMonitorsWithData();
        $ip_dirs = $this->dataSync->getIPDirectories();
        
        $this->json([
            'success' => true,
            'synced_count' => $synced_count,
            'ip_directories' => $ip_dirs,
            'message' => "Successfully synced $synced_count new monitors"
        ]);
    }
    
    public function api_status() {
        $this->requireAdmin();
        
        $ip_dirs = $this->dataSync->getIPDirectories();
        $total_monitors = $this->db->fetch("SELECT COUNT(*) as count FROM tbl_monitor_ips")['count'];
        $active_monitors = $this->db->fetch("SELECT COUNT(*) as count FROM tbl_monitor_ips WHERE monitor_status = 1")['count'];
        
        $this->json([
            'ip_directories' => $ip_dirs,
            'total_monitors' => $total_monitors,
            'active_monitors' => $active_monitors,
            'data_directories' => [
                'screens' => SCREENS_DIR,
                'thumbnails' => THUMBNAILS_DIR,
                'logs' => LOGS_DIR
            ]
        ]);
    }
}
?> 