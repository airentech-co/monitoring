<?php
require_once BASEPATH . 'app/controllers/Base.php';
require_once BASEPATH . 'app/services/DataSyncService.php';

class Dashboard extends Base {
    private $dataSync;
    
    public function __construct() {
        parent::__construct();
        $this->dataSync = new DataSyncService();
    }
    
    public function index() {
        // Sync monitors with actual data
        $synced_count = $this->dataSync->syncMonitorsWithData();
        
        // Get statistics
        $totalUsers = $this->db->fetch("SELECT COUNT(*) as count FROM users")['count'];
        $totalMonitors = $this->db->fetch("SELECT COUNT(*) as count FROM tbl_monitor_ips")['count'];
        $activeMonitors = $this->db->fetch("SELECT COUNT(*) as count FROM tbl_monitor_ips WHERE monitor_status = 1")['count'];
        
        // Get recent monitors with actual data
        $recentMonitors = $this->db->fetchAll(
            "SELECT * FROM tbl_monitor_ips ORDER BY last_monitor_tic DESC LIMIT 10"
        );
        
        // Add actual data information to monitors
        foreach ($recentMonitors as &$monitor) {
            $ip_identifier = 'ip_' . str_replace([':', '.'], '_', $monitor['ip_address']);
            
            // Check if monitor has actual data
            $has_screenshots = $this->dataSync->getLatestScreenshot($ip_identifier) !== null;
            $logs = $this->dataSync->getLogs($ip_identifier);
            $has_logs = !empty($logs['browser_history']) || !empty($logs['key_logs']) || !empty($logs['usb_logs']);
            
            $monitor['has_data'] = $has_screenshots || $has_logs;
            $monitor['has_screenshots'] = $has_screenshots;
            $monitor['has_logs'] = $has_logs;
        }
        
        $this->view('dashboard/index', [
            'totalUsers' => $totalUsers,
            'totalMonitors' => $totalMonitors,
            'activeMonitors' => $activeMonitors,
            'recentMonitors' => $recentMonitors,
            'syncedCount' => $synced_count
        ]);
    }
}
?> 