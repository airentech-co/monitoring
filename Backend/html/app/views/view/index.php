<?php
$title = 'Real-time Monitoring - ' . APP_NAME;
$active_page = 'view';
$page_title = 'Real-time Monitoring';
ob_start();
?>
<style>
.live-status-badge {
    position: relative;
    display: inline-block;
}

.live-status-badge .pulse {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border-radius: inherit;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.1);
        opacity: 0.7;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

.status-online {
    background: linear-gradient(45deg, #28a745, #20c997);
    border: none;
}

.status-inactive {
    background: linear-gradient(45deg, #ffc107, #fd7e14);
    border: none;
}

.status-offline {
    background: linear-gradient(45deg, #dc3545, #e83e8c);
    border: none;
}

.activity-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

.monitor-name {
    font-weight: 600;
    color: #495057;
}

.monitor-username {
    font-size: 0.8rem;
    color: #6c757d;
}

.time-ago {
    font-size: 0.75rem;
    color: #6c757d;
}

/* Dashboard Layout */
.dashboard-container {
    display: flex;
    height: calc(100vh - 80px);
    gap: 0.75rem;
}

.sidebar-panel {
    width: 320px;
    background: #f8f9fa;
    border-radius: 0.5rem;
    border: 1px solid #dee2e6;
    display: flex;
    flex-direction: column;
}

.main-content {
    flex: 1;
    background: white;
    border-radius: 0.5rem;
    border: 1px solid #dee2e6;
    display: flex;
    flex-direction: column;
}

/* Status Overview - Moved to right panel */
.status-overview {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1rem;
    border-radius: 0.5rem 0.5rem 0 0;
    margin-bottom: 0;
}

.status-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.75rem;
    margin-top: 0.75rem;
}

.stat-card {
    background: rgba(255, 255, 255, 0.1);
    padding: 0.75rem;
    border-radius: 0.375rem;
    text-align: center;
}

.stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.125rem;
}

.stat-label {
    font-size: 0.75rem;
    opacity: 0.9;
}

/* Sidebar Styles - Optimized */
.sidebar-header {
    padding: 0.75rem;
    border-bottom: 1px solid #dee2e6;
    background: #fff;
    border-radius: 0.5rem 0.5rem 0 0;
}

.filter-container {
    margin-top: 0.5rem;
}

.filter-container .form-control {
    font-size: 0.8rem;
    border-radius: 0.375rem;
}

.filter-container .form-control:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.sidebar-content {
    flex: 1;
    overflow-y: auto;
    padding: 0;
}

.monitor-item {
    padding: 0.75rem;
    border-bottom: 1px solid #dee2e6;
    cursor: pointer;
    transition: background-color 0.2s;
    background: white;
}

.monitor-item:hover {
    background: #f8f9fa;
}

.monitor-item.selected {
    background: #e3f2fd;
    border-left: 4px solid #2196f3;
}

.monitor-item input[type="checkbox"] {
    margin-right: 0.5rem;
}

.monitor-item-header {
    display: flex;
    align-items: center;
    margin-bottom: 0.375rem;
}

.monitor-item-name {
    font-weight: 600;
    color: #495057;
    flex: 1;
    font-size: 0.9rem;
}

.monitor-item-status {
    margin-left: auto;
}

.monitor-item-username {
    font-size: 0.75rem;
    color: #6c757d;
    margin-left: 1.25rem;
    line-height: 1.2;
}

/* Main Content Styles */
.main-header {
    padding: 0.75rem;
    border-bottom: 1px solid #dee2e6;
    background: #fff;
    border-radius: 0 0.5rem 0.5rem 0;
}

.main-content-area {
    flex: 1;
    overflow-y: auto;
    padding: 0.75rem;
}

.screenshot-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 0.75rem;
}

.screenshot-card {
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    background: white;
}

.screenshot-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.screenshot-card img {
    width: 100%;
    height: 180px;
    object-fit: cover;
}

.screenshot-info {
    padding: 0.625rem;
    background: #f8f9fa;
}

.screenshot-badges {
    display: flex;
    gap: 0.25rem;
    margin-bottom: 0.375rem;
    flex-wrap: wrap;
}

.screenshot-badges .badge {
    font-size: 0.65rem;
}

.screenshot-details {
    font-size: 0.75rem;
    color: #6c757d;
    line-height: 1.2;
}

.screenshot-timestamp {
    font-size: 0.75rem;
    color: #6c757d;
    text-align: center;
    font-weight: 500;
}

/* Responsive Design */
@media (max-width: 768px) {
    .dashboard-container {
        flex-direction: column;
        height: auto;
    }
    
    .sidebar-panel {
        width: 100%;
        max-height: 250px;
    }
    
    .status-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Checkbox Styles */
.monitor-checkbox {
    transform: scale(1.1);
    margin-right: 0.5rem;
}

/* Loading States */
.loading-container {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 200px;
    color: #6c757d;
}

.loading-container i {
    margin-right: 0.5rem;
}

/* Empty States */
.empty-state {
    text-align: center;
    padding: 2rem 1rem;
    color: #6c757d;
}

.empty-state i {
    font-size: 2.5rem;
    margin-bottom: 0.75rem;
    opacity: 0.5;
}

/* Modal Styles */
.modal-fullscreen .modal-body {
    padding: 0;
}

.modal-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-actions .btn {
    font-size: 0.8rem;
    padding: 0.375rem 0.75rem;
}

.modal-layout {
    height: calc(100vh - 120px);
}

.screenshot-section {
    height: 100%;
    background: #000;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.screenshot-container {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
}

.screenshot-container img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    border-radius: 0;
}

/* Log Sub-Modal Styles */
.log-sub-modal .modal-dialog {
    max-width: 90%;
    max-height: 80vh;
}

.log-sub-modal .modal-content {
    max-height: 80vh;
}

.log-sub-modal .modal-body {
    overflow-y: auto;
    max-height: calc(80vh - 120px);
}

/* Log Table Styles */
.log-table {
    font-size: 0.75rem;
}

.log-table th {
    font-size: 0.7rem;
    padding: 0.375rem;
    background: #f8f9fa;
    position: sticky;
    top: 0;
    z-index: 10;
}

.log-table td {
    padding: 0.375rem;
    vertical-align: middle;
}

.log-table .badge {
    font-size: 0.65rem;
}

/* Modal Log Table Container */
.modal-log-container {
    height: 100%;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    background: white;
}

.modal-log-pagination {
    padding: 0.5rem;
    background: #f8f9fa;
    border-top: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-log-pagination .pagination {
    margin: 0;
}

.modal-log-pagination .page-link {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

.modal-log-pagination .page-item.active .page-link {
    background-color: #007bff;
    border-color: #007bff;
}

.modal-log-pagination .page-item.disabled .page-link {
    color: #6c757d;
    background-color: #fff;
    border-color: #dee2e6;
}

.modal-log-info {
    font-size: 0.75rem;
    color: #6c757d;
}

/* Tab Content Height */
.tab-content {
    height: calc(100vh - 300px);
    overflow-y: auto;
}

.tab-pane {
    height: 100%;
    padding: 0.75rem;
}

.tab-pane .table-responsive {
    height: calc(100% - 60px);
    overflow-y: auto;
}

/* Header Log Buttons */
.log-buttons {
    display: flex;
    gap: 0.5rem;
}

.log-buttons .btn {
    font-size: 0.8rem;
    padding: 0.375rem 0.75rem;
}

.log-buttons .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
</style>

<!-- Dashboard Container -->
<div class="dashboard-container">
    <!-- Left Sidebar - Monitor List -->
    <div class="sidebar-panel">
        <div class="sidebar-header">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Monitors</h6>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="selectAllMonitors">
                    <label class="form-check-label" for="selectAllMonitors">
                        Select All
                    </label>
                </div>
            </div>
            <div class="filter-container">
                <input type="text" class="form-control form-control-sm" id="monitorFilter" placeholder="Filter by name, username, or IP...">
            </div>
        </div>
        <div class="sidebar-content" id="monitorList">
            <!-- Monitor items will be loaded here -->
        </div>
    </div>
    
    <!-- Right Main Content - Status Overview + Screenshots -->
    <div class="main-content">
        <!-- Status Overview -->
        <div class="status-overview">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">Real-time Monitoring Dashboard</h5>
                    <p class="mb-0 opacity-75" style="font-size: 0.875rem;">Live status and activity tracking</p>
                </div>
                <div>
                    <span class="badge bg-success" id="status-indicator">Auto-refresh: ON</span>
                    <button class="btn btn-sm btn-outline-light ms-2" id="refresh-btn">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
            
            <div class="status-stats">
                <div class="stat-card">
                    <div class="stat-number" id="total-monitors">0</div>
                    <div class="stat-label">Total</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="online-monitors">0</div>
                    <div class="stat-label">Online</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="active-monitors">0</div>
                    <div class="stat-label">Active</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="total-screenshots">0</div>
                    <div class="stat-label">Screenshots</div>
                </div>
            </div>
        </div>
        
        <!-- Screenshots Section -->
        <div class="main-header">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-image me-2"></i>
                    Screenshots
                    <span class="badge bg-secondary ms-2" id="screenshotCount">0</span>
                </h6>
                <div class="d-flex align-items-center">
                    <div id="headerDateFilterContainer" class="me-3"></div>
                    <label class="form-label mb-0 me-2" style="font-size: 0.875rem;">Per page:</label>
                    <select class="form-select form-select-sm" id="perPageSelect" style="width: auto;">
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="150" selected>150</option>
                        <option value="200">200</option>
                        <option value="300">300</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="main-content-area">
            <div id="screenshotsContainer">
                <div class="loading-container">
                    <i class="fas fa-spinner fa-spin"></i>
                    Loading monitors...
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Screenshot Modal -->
<div class="modal fade" id="screenshotModal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Screenshot Details</h5>
                <div class="modal-actions">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="browserLogBtn" title="View Browser Logs">
                        <i class="fas fa-globe"></i> Browser
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-warning" id="keyLogBtn" title="View Key Logs">
                        <i class="fas fa-keyboard"></i> Keys
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-info" id="usbLogBtn" title="View USB Logs">
                        <i class="fas fa-usb"></i> USB
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body p-0">
                <div class="modal-layout">
                    <!-- Full Screenshot Area -->
                    <div class="screenshot-section">
                        <div class="screenshot-container">
                            <img id="modalImage" src="" alt="Full Screenshot" class="img-fluid">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Log Sub-Modals -->
<div class="modal fade" id="browserLogModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-globe text-primary me-2"></i>
                    Browser Activity Logs
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="browserLogsContent"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="keyLogModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-keyboard text-warning me-2"></i>
                    Keyboard Activity Logs
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="keyLogsContent"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="usbLogModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-usb text-info me-2"></i>
                    USB Activity Logs
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="usbLogsContent"></div>
            </div>
        </div>
    </div>
</div>

<!-- Log Modal -->
<div class="modal fade" id="logModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logModalTitle">Log File Content</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Log View Tabs -->
                <ul class="nav nav-tabs" id="logViewTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="table-tab" data-bs-toggle="tab" data-bs-target="#table-content" type="button" role="tab">
                            <i class="fas fa-table me-2"></i>Table View
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="raw-tab" data-bs-toggle="tab" data-bs-target="#raw-content" type="button" role="tab">
                            <i class="fas fa-file-alt me-2"></i>Raw Text
                        </button>
                    </li>
                </ul>
                
                <!-- Tab Content -->
                <div class="tab-content mt-3" id="logViewTabContent">
                    <!-- Table View Tab -->
                    <div class="tab-pane fade show active" id="table-content" role="tabpanel">
                        <div id="logContentContainer">
                            <div class="text-center py-4">
                                <i class="fas fa-spinner fa-spin fa-2x text-muted mb-3"></i>
                                <p class="text-muted">Loading log content...</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Raw Text Tab -->
                    <div class="tab-pane fade" id="raw-content" role="tabpanel">
                        <div id="rawContentContainer">
                            <div class="text-center py-4">
                                <i class="fas fa-spinner fa-spin fa-2x text-muted mb-3"></i>
                                <p class="text-muted">Loading raw content...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Wait for jQuery to be available
function waitForJQuery(callback) {
    if (typeof jQuery !== 'undefined') {
        callback();
    } else {
        setTimeout(function() {
            waitForJQuery(callback);
        }, 100);
    }
}

let refreshInterval;
let isAutoRefresh = true;
let currentPage = 1;
let currentPerPage = 150;
let selectedMonitors = [];
let allMonitors = [];
let filteredMonitors = [];

// Initialize when jQuery is ready
waitForJQuery(function() {
    // Load initial data
    loadMonitors();
    loadScreenshots();
    
    // Auto-refresh every 30 seconds
    setInterval(function() {
        if (isAutoRefresh) {
            loadMonitors();
            // Preserve current view - if single monitor is selected, keep showing historical screenshots
            if (selectedMonitors.length === 1) {
                loadScreenshotsForMonitor(selectedMonitors[0], currentPage);
            } else {
                loadScreenshots(currentPage);
            }
        }
    }, 30000);
    
    // Event handlers
    setupEventHandlers();
});

function setupEventHandlers() {
    // Select all monitors
    $('#selectAllMonitors').change(function() {
        const isChecked = $(this).is(':checked');
        $('.monitor-checkbox').prop('checked', isChecked);
        
        if (isChecked) {
            selectedMonitors = filteredMonitors.map(m => m.id);
            $('.monitor-item').addClass('selected');
        } else {
            selectedMonitors = [];
            $('.monitor-item').removeClass('selected');
        }
        
        loadScreenshots();
    });
    
    // Individual monitor selection
    $(document).on('change', '.monitor-checkbox', function() {
        const monitorId = parseInt($(this).val());
        const isChecked = $(this).is(':checked');
        
        if (isChecked) {
            if (!selectedMonitors.includes(monitorId)) {
                selectedMonitors.push(monitorId);
            }
            $(this).closest('.monitor-item').addClass('selected');
        } else {
            selectedMonitors = selectedMonitors.filter(id => id !== monitorId);
            $(this).closest('.monitor-item').removeClass('selected');
        }
        
        // Update select all checkbox
        updateSelectAllCheckbox();
        
        loadScreenshots();
    });
    
    // Click anywhere on monitor item to toggle checkbox
    $(document).on('click', '.monitor-item', function(e) {
        // Don't trigger if clicking directly on the checkbox
        if ($(e.target).is('input[type="checkbox"]') || $(e.target).is('label')) {
            return;
        }
        
        const checkbox = $(this).find('.monitor-checkbox');
        const monitorId = parseInt(checkbox.val());
        
        // Clear all selections first
        $('.monitor-checkbox').prop('checked', false);
        $('.monitor-item').removeClass('selected');
        selectedMonitors = [];
        
        // Select only this monitor
        checkbox.prop('checked', true);
        $(this).addClass('selected');
        selectedMonitors = [monitorId];
        
        // Update select all checkbox
        updateSelectAllCheckbox();
        
        // Load screenshots for this specific monitor
        loadScreenshotsForMonitor(monitorId);
        
        // Also load logs for this monitor
        loadLogsForMonitor(monitorId);
    });
    
    // Monitor filter
    $('#monitorFilter').on('input', function() {
        filterMonitors();
    });
    
    // Per-page selector
    $('#perPageSelect').change(function() {
        currentPerPage = parseInt($(this).val());
        loadScreenshots(1);
    });
    
    // Refresh button
    $('#refresh-btn').click(function() {
        loadMonitors();
        // Preserve current view - if single monitor is selected, keep showing historical screenshots
        if (selectedMonitors.length === 1) {
            loadScreenshotsForMonitor(selectedMonitors[0], currentPage);
        } else {
            loadScreenshots(currentPage);
        }
    });
    
    // Auto-refresh toggle
    $('#status-indicator').click(function() {
        isAutoRefresh = !isAutoRefresh;
        if (isAutoRefresh) {
            $(this).removeClass("bg-secondary").addClass("bg-success").text("Auto-refresh: ON");
        } else {
            $(this).removeClass("bg-success").addClass("bg-secondary").text("Auto-refresh: OFF");
        }
    });
}

function filterMonitors() {
    const filterText = $('#monitorFilter').val().toLowerCase().trim();
    
    if (filterText === '') {
        filteredMonitors = [...allMonitors];
    } else {
        filteredMonitors = allMonitors.filter(monitor => {
            const name = monitor.name.toLowerCase();
            const username = (monitor.username || '').toLowerCase();
            const ip = monitor.ip_address.toLowerCase();
            
            return name.includes(filterText) || 
                   username.includes(filterText) || 
                   ip.includes(filterText);
        });
    }
    
    updateMonitorList(filteredMonitors);
    updateSelectAllCheckbox();
}

function updateSelectAllCheckbox() {
    const totalMonitors = filteredMonitors.length;
    const selectedCount = selectedMonitors.length;
    
    if (selectedCount === 0) {
        $('#selectAllMonitors').prop('indeterminate', false).prop('checked', false);
    } else if (selectedCount === totalMonitors) {
        $('#selectAllMonitors').prop('indeterminate', false).prop('checked', true);
    } else {
        $('#selectAllMonitors').prop('indeterminate', true).prop('checked', false);
    }
}

function loadMonitors() {
    $.ajax({
        url: "<?php echo BASE_URL; ?>view/api_live_status",
        method: "GET",
        success: function(data) {
            allMonitors = data;
            
            // Preserve current selection when refreshing
            if (selectedMonitors.length > 0) {
                // Keep only monitors that still exist
                selectedMonitors = selectedMonitors.filter(id => 
                    data.some(monitor => monitor.id === id)
                );
            }
            
            filteredMonitors = [...data];
            updateMonitorList(filteredMonitors);
            updateStatusStats(data);
        },
        error: function(xhr, status, error) {
            console.log("Failed to load monitors:", error);
        }
    });
}

// Enhanced time formatting function
function formatTimeAgo(seconds) {
    if (seconds < 60) {
        return `${seconds}s ago`;
    } else if (seconds < 3600) {
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;
        return `${minutes}m ${remainingSeconds}s ago`;
    } else if (seconds < 86400) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const remainingSeconds = seconds % 60;
        return `${hours}h ${minutes}m ${remainingSeconds}s ago`;
    } else {
        const days = Math.floor(seconds / 86400);
        const hours = Math.floor((seconds % 86400) / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        return `${days}d ${hours}h ${minutes}m ago`;
    }
}

function updateMonitorList(monitors) {
    let html = '';
    
    monitors.forEach(function(monitor) {
        const isSelected = selectedMonitors.includes(monitor.id);
        
        // Use the status calculated by the backend
        let statusText = monitor.status_text || 'Offline';
        let statusClass = monitor.live_status || 'offline';
        

        
        // Fallback calculation if backend status is not available
        if (!monitor.live_status && monitor.latest_activity) {
            const timeAgo = Math.floor((Date.now() / 1000) - monitor.latest_activity);
            if (timeAgo < 600) { // 10 minutes - match backend logic (2 Tic cycles)
                statusText = 'Online';
                statusClass = 'online';
            } else if (timeAgo < 1800) { // 30 minutes - match backend logic (6 Tic cycles)
                statusText = 'Online';
                statusClass = 'online';
            } else if (timeAgo < 3600) { // 1 hour - match backend logic (12 Tic cycles)
                statusText = 'Inactive';
                statusClass = 'inactive';
            } else {
                statusText = 'Offline';
                statusClass = 'offline';
            }
        } else if (!monitor.latest_activity) {
            statusText = 'Never';
            statusClass = 'offline';
        }
        
        html += `
            <div class="monitor-item ${isSelected ? 'selected' : ''}">
                <div class="monitor-item-header">
                    <input type="checkbox" class="monitor-checkbox" value="${monitor.id}" ${isSelected ? 'checked' : ''}>
                    <div class="monitor-item-name">${monitor.name}</div>
                    <div class="monitor-item-status">
                        <span class="badge status-${statusClass}">
                            <i class="fas fa-circle me-1"></i>${statusText}
                            ${statusClass === 'online' ? '<div class="pulse"></div>' : ''}
                        </span>
                    </div>
                </div>
                ${monitor.username && monitor.username !== 'Unknown' ? 
                    `<div class="monitor-item-username">
                        <i class="fas fa-user me-1"></i>${monitor.username}
                    </div>` : ''}
            </div>
        `;
    });
    
    $('#monitorList').html(html);
    
    // Update select all checkbox after restoring selection
    updateSelectAllCheckbox();
}

function updateStatusStats(monitors) {
    const total = monitors.length;
    const online = monitors.filter(m => m.live_status === 'online').length;
    const inactive = monitors.filter(m => m.live_status === 'inactive').length;
    const offline = monitors.filter(m => m.live_status === 'offline').length;
    
    $('#total-monitors').text(total);
    $('#online-monitors').text(online);
    $('#active-monitors').text(inactive + online); // Active = online + inactive
    $('#total-screenshots').text(monitors.filter(m => m.live_status === 'online' || m.live_status === 'inactive').length);
}

function loadScreenshotsForMonitor(monitorId) {
    currentPage = 1;
    
    const params = new URLSearchParams({
        page: currentPage,
        per_page: currentPerPage,
        monitor_id: monitorId
    });
    
    $.ajax({
        url: "<?php echo BASE_URL; ?>view/api_screenshots?" + params.toString(),
        method: "GET",
        success: function(response) {
            if (response.screenshots) {
                displayScreenshots(response.screenshots, response.pagination);
            } else {
                displayScreenshots(response, null);
            }
        },
        error: function(xhr, status, error) {
            console.log("Failed to load screenshots:", error);
            $('#screenshotsContainer').html(`
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h5>Failed to Load Screenshots</h5>
                    <p>Error: ${error}</p>
                </div>
            `);
        }
    });
}

function loadScreenshots(page = 1) {
    currentPage = page;
    
    const params = new URLSearchParams({
        page: page,
        per_page: currentPerPage
    });
    
    if (selectedMonitors.length > 0) {
        params.append('monitors', selectedMonitors.join(','));
    }
    
    $.ajax({
        url: "<?php echo BASE_URL; ?>view/api_screenshots?" + params.toString(),
        method: "GET",
        success: function(response) {
            if (response.screenshots) {
                displayScreenshots(response.screenshots, response.pagination);
            } else {
                displayScreenshots(response, null);
            }
        },
        error: function(xhr, status, error) {
            console.log("Failed to load screenshots:", error);
            $('#screenshotsContainer').html(`
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h5>Failed to Load Screenshots</h5>
                    <p>Error: ${error}</p>
                </div>
            `);
        }
    });
}

function displayScreenshots(screenshots, pagination = null) {
    const container = $('#screenshotsContainer');
    
    if (screenshots.length === 0) {
        container.html(`
            <div class="empty-state">
                <i class="fas fa-image"></i>
                <h5>No Screenshots Available</h5>
                <p>No screenshots have been captured for the selected monitors.</p>
            </div>
        `);
        $('#screenshotCount').text("0");
        return;
    }
    
    // Update screenshot count
    if (pagination) {
        $('#screenshotCount').text(pagination.total_screenshots);
    } else {
        $('#screenshotCount').text(screenshots.length);
    }
    
    // Check if this is a single monitor view
    const isSingleMonitor = selectedMonitors.length === 1;
    
    // Update header buttons based on view type
    updateHeaderButtons(isSingleMonitor);
    
    let html = '<div class="screenshot-grid">';
    screenshots.forEach(function(item) {
        const monitor = item.monitor;
        const thumbnailUrl = "<?php echo BASE_URL; ?>" + item.thumbnail;
        const fullUrl = "<?php echo BASE_URL; ?>" + item.screenshot;
        
        let timestamp;
        if (item.modified_time) {
            timestamp = new Date(item.modified_time).toLocaleString();
        } else {
            timestamp = new Date().toLocaleString();
        }
        
        // Calculate time ago
        const screenshotTime = new Date(item.modified_time);
        const timeDiff = Date.now() - screenshotTime.getTime();
        const timeDiffSeconds = Math.floor(timeDiff / 1000);
        let timeAgo = '';

        if (timeDiffSeconds <= 0) { // exactly 0 seconds or negative (future time)
            timeAgo = 'Just now';
        } else {
            timeAgo = formatTimeAgo(timeDiffSeconds);
        }
        
        if (isSingleMonitor) {
            // Single monitor view - show enhanced time ago and timestamp
            html += `
                <div class="screenshot-card" onclick="viewScreenshot('${fullUrl}', '${monitor.name}', '${monitor.ip_address}')">
                    <img src="${thumbnailUrl}" alt="Screenshot" loading="lazy">
                    <div class="screenshot-info">
                        <div class="screenshot-timestamp">
                            <i class="fas fa-clock me-1"></i>${timeAgo}
                        </div>
                        <div class="screenshot-timestamp">
                            <i class="fas fa-calendar me-1"></i>${timestamp}
                        </div>
                    </div>
                </div>
            `;
        } else {
            // Multiple monitor view - show full info
            const usernameHtml = (monitor.username && monitor.username !== 'Unknown') 
                ? `<span class="badge bg-primary"><i class="fas fa-user me-1"></i>${monitor.username}</span>` 
                : '';
            
            html += `
                <div class="screenshot-card" onclick="viewScreenshot('${fullUrl}', '${monitor.name}', '${monitor.ip_address}')">
                    <img src="${thumbnailUrl}" alt="Screenshot" loading="lazy">
                    <div class="screenshot-info">
                        <div class="screenshot-badges">
                            ${usernameHtml}
                            <span class="badge bg-secondary">${monitor.ip_address}</span>
                            <span class="badge bg-info">${timeAgo}</span>
                        </div>
                        <div class="screenshot-details">
                            <div><i class="fas fa-clock me-1"></i>${timestamp}</div>
                        </div>
                    </div>
                </div>
            `;
        }
    });
    html += '</div>';
    
    // Add pagination if needed
    if (pagination && pagination.total_pages > 1) {
        html += generatePagination(pagination);
    }
    
    container.html(html);
}

function updateHeaderButtons(isSingleMonitor) {
    const headerRight = $('.main-header .d-flex.justify-content-between.align-items-center > div:last-child');
    
    if (isSingleMonitor) {
        // Add log buttons to header
        if (headerRight.find('.log-buttons').length === 0) {
            headerRight.prepend(`
                <div class="log-buttons me-3">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="headerBrowserLogBtn" title="View Browser Logs">
                        <i class="fas fa-globe"></i> Browser
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-warning" id="headerKeyLogBtn" title="View Key Logs">
                        <i class="fas fa-keyboard"></i> Keys
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-info" id="headerUsbLogBtn" title="View USB Logs">
                        <i class="fas fa-usb"></i> USB
                    </button>
                </div>
            `);
            
            // Add date filter
            const today = new Date().toISOString().split('T')[0];
            const dateFilterHtml = `
                <form id="headerDateFilterForm" class="d-flex flex-wrap align-items-center gap-2 mb-0">
                    <label for="headerStartDate" class="form-label mb-0">Start:</label>
                    <input type="date" id="headerStartDate" name="start_date" class="form-control form-control-sm" style="width: 140px;" value="${today}">
                    <label for="headerEndDate" class="form-label mb-0">End:</label>
                    <input type="date" id="headerEndDate" name="end_date" class="form-control form-control-sm" style="width: 140px;" value="${today}">
                    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                    <button type="button" id="headerClearDateFilter" class="btn btn-sm btn-secondary">Clear</button>
                </form>
            `;
            $('#headerDateFilterContainer').html(dateFilterHtml);
            
            // Setup date filter handlers
            $('#headerDateFilterForm').off('submit').on('submit', function(e) {
                e.preventDefault();
                // Set global date filter values and reload screenshots
                window.headerStartDate = $('#headerStartDate').val();
                window.headerEndDate = $('#headerEndDate').val();
                console.log('Date filter applied:', window.headerStartDate, 'to', window.headerEndDate);
                loadScreenshotsForSelectedMonitor(1);
            });
            $('#headerClearDateFilter').off('click').on('click', function() {
                $('#headerStartDate').val('');
                $('#headerEndDate').val('');
                window.headerStartDate = '';
                window.headerEndDate = '';
                console.log('Date filter cleared');
                loadScreenshotsForSelectedMonitor(1);
            });
            
            // Setup header log button handlers
            setupHeaderLogButtonHandlers();
        }
    } else {
        // Remove log buttons and date filter from header
        headerRight.find('.log-buttons').remove();
        $('#headerDateFilterContainer').empty();
    }
}

function setupHeaderLogButtonHandlers() {
    // Browser log button
    $('#headerBrowserLogBtn').off('click').on('click', function() {
        if (currentLogData.browser.data.length > 0) {
            displayLogTableWithPagination('browser', $("#browserLogsContent"));
            $('#browserLogModal').modal('show');
        } else {
            alert('No browser logs available for this monitor.');
        }
    });
    
    // Key log button
    $('#headerKeyLogBtn').off('click').on('click', function() {
        if (currentLogData.key.data.length > 0) {
            displayLogTableWithPagination('key', $("#keyLogsContent"));
            $('#keyLogModal').modal('show');
        } else {
            alert('No key logs available for this monitor.');
        }
    });
    
    // USB log button
    $('#headerUsbLogBtn').off('click').on('click', function() {
        if (currentLogData.usb.data.length > 0) {
            displayLogTableWithPagination('usb', $("#usbLogsContent"));
            $('#usbLogModal').modal('show');
        } else {
            alert('No USB logs available for this monitor.');
        }
    });
}

function generatePagination(pagination) {
    return `
        <div class="d-flex justify-content-between align-items-center mt-4">
            <div class="text-muted">
                Showing ${((pagination.current_page - 1) * pagination.per_page) + 1} to ${Math.min(pagination.current_page * pagination.per_page, pagination.total_screenshots)} of ${pagination.total_screenshots} screenshots
            </div>
            <nav aria-label="Screenshots Pagination">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
                        <a class="page-link" href="#" onclick="loadScreenshotsForSelectedMonitor(${pagination.current_page - 1}); return false;">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    
                    ${generatePageNumbers(pagination.current_page, pagination.total_pages)}
                    
                    <li class="page-item ${pagination.current_page === pagination.total_pages ? 'disabled' : ''}">
                        <a class="page-link" href="#" onclick="loadScreenshotsForSelectedMonitor(${pagination.current_page + 1}); return false;">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    `;
}

function generatePageNumbers(currentPage, totalPages) {
    let html = '';
    const maxVisible = 5;
    
    if (totalPages <= maxVisible) {
        for (let i = 1; i <= totalPages; i++) {
            html += `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="loadScreenshotsForSelectedMonitor(${i}); return false;">${i}</a>
                </li>
            `;
        }
    } else {
        let start = Math.max(1, currentPage - 2);
        let end = Math.min(totalPages, start + maxVisible - 1);
        
        if (end - start + 1 < maxVisible) {
            start = Math.max(1, end - maxVisible + 1);
        }
        
        if (start > 1) {
            html += `
                <li class="page-item">
                    <a class="page-link" href="#" onclick="loadScreenshotsForSelectedMonitor(1); return false;">1</a>
                </li>
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
            `;
        }
        
        for (let i = start; i <= end; i++) {
            html += `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="loadScreenshotsForSelectedMonitor(${i}); return false;">${i}</a>
                </li>
            `;
        }
        
        if (end < totalPages) {
            html += `
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
                <li class="page-item">
                    <a class="page-link" href="#" onclick="loadScreenshotsForSelectedMonitor(${totalPages}); return false;">${totalPages}</a>
                </li>
            `;
        }
    }
    
    return html;
}

function loadScreenshotsForSelectedMonitor(page) {
    if (selectedMonitors.length === 1) {
        loadScreenshotsForMonitor(selectedMonitors[0], page);
        // Also load logs for the selected monitor
        loadLogsForMonitor(selectedMonitors[0]);
    } else {
        loadScreenshots(page);
        // Clear log data when no single monitor is selected
        currentLogData = {
            browser: { data: [], headers: [], currentPage: 1, perPage: 20 },
            key: { data: [], headers: [], currentPage: 1, perPage: 20 },
            usb: { data: [], headers: [], currentPage: 1, perPage: 20 }
        };
    }
}

function loadScreenshotsForMonitor(monitorId, page = 1) {
    currentPage = page;
    
    const params = new URLSearchParams({
        page: currentPage,
        per_page: currentPerPage,
        monitor_id: monitorId
    });
    
    // Add date filter parameters if they exist
    if (window.headerStartDate) {
        params.append('start_date', window.headerStartDate);
    }
    if (window.headerEndDate) {
        params.append('end_date', window.headerEndDate);
    }
    
    console.log('Loading screenshots with params:', params.toString());
    
    $.ajax({
        url: "<?php echo BASE_URL; ?>view/api_screenshots?" + params.toString(),
        method: "GET",
        success: function(response) {
            if (response.screenshots) {
                displayScreenshots(response.screenshots, response.pagination);
            } else {
                displayScreenshots(response, null);
            }
        },
        error: function(xhr, status, error) {
            console.log("Failed to load screenshots:", error);
            $('#screenshotsContainer').html(`
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h5>Failed to Load Screenshots</h5>
                    <p>Error: ${error}</p>
                </div>
            `);
        }
    });
}

function viewScreenshot(imageUrl, monitorName, ipAddress) {
    $("#modalImage").attr("src", imageUrl);
    $("#modalTitle").text(`Screenshot - ${monitorName} (${ipAddress})`);
    $("#screenshotModal").modal("show");
    
    // Load logs for this IP
    loadLogsForScreenshot(ipAddress);
    
    // Setup log button event handlers
    setupLogButtonHandlers();
}

function setupLogButtonHandlers() {
    // Browser log button
    $('#browserLogBtn').off('click').on('click', function() {
        if (currentLogData.browser.data.length > 0) {
            displayLogTableWithPagination('browser', $("#browserLogsContent"));
            $('#browserLogModal').modal('show');
        } else {
            alert('No browser logs available for this monitor.');
        }
    });
    
    // Key log button
    $('#keyLogBtn').off('click').on('click', function() {
        if (currentLogData.key.data.length > 0) {
            displayLogTableWithPagination('key', $("#keyLogsContent"));
            $('#keyLogModal').modal('show');
        } else {
            alert('No key logs available for this monitor.');
        }
    });
    
    // USB log button
    $('#usbLogBtn').off('click').on('click', function() {
        if (currentLogData.usb.data.length > 0) {
            displayLogTableWithPagination('usb', $("#usbLogsContent"));
            $('#usbLogModal').modal('show');
        } else {
            alert('No USB logs available for this monitor.');
        }
    });
}

function loadLogsForMonitor(monitorId) {
    // Find monitor by ID
    const monitor = allMonitors.find(m => m.id === monitorId);
    
    if (monitor) {
        $.ajax({
            url: "<?php echo BASE_URL; ?>view/api_logs/" + monitor.id,
            method: "GET",
            success: function(data) {
                displayLogsInModal(data);
            },
            error: function(xhr, status, error) {
                console.log("Failed to load logs:", error);
                // Reset log data on error
                currentLogData = {
                    browser: { data: [], headers: [], currentPage: 1, perPage: 20 },
                    key: { data: [], headers: [], currentPage: 1, perPage: 20 },
                    usb: { data: [], headers: [], currentPage: 1, perPage: 20 }
                };
            }
        });
    }
}

function loadLogsForScreenshot(ipAddress) {
    // Show loading state
    $("#browserLogsContent, #keyLogsContent, #usbLogsContent").html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>');
    
    // Find monitor by IP address
    const monitor = allMonitors.find(m => m.ip_address === ipAddress);
    
    if (monitor) {
        $.ajax({
            url: "<?php echo BASE_URL; ?>view/api_logs/" + monitor.id,
            method: "GET",
            success: function(data) {
                displayLogsInModal(data);
            },
            error: function(xhr, status, error) {
                console.log("Failed to load logs:", error);
                $("#browserLogsContent, #keyLogsContent, #usbLogsContent").html('<div class="text-danger">Failed to load logs</div>');
            }
        });
    }
}

// Global variables for log pagination
let currentLogData = {
    browser: { data: [], headers: [], currentPage: 1, perPage: 20 },
    key: { data: [], headers: [], currentPage: 1, perPage: 20 },
    usb: { data: [], headers: [], currentPage: 1, perPage: 20 }
};

function displayLogsInModal(logs) {
    // Display browser logs
    if (logs.browser_history && logs.browser_history.length > 0) {
        const latestBrowserLog = logs.browser_history[0];
        $.get("<?php echo BASE_URL; ?>" + latestBrowserLog.path, function(data) {
            const parsedData = parseLogData(data, 'browser_history');
            if (parsedData.data.length > 0) {
                currentLogData.browser.data = parsedData.data;
                currentLogData.browser.headers = parsedData.headers;
                currentLogData.browser.currentPage = 1;
                // Only update modal content if modal exists
                if ($("#browserLogsContent").length > 0) {
                    displayLogTableWithPagination('browser', $("#browserLogsContent"));
                }
            } else {
                if ($("#browserLogsContent").length > 0) {
                    $("#browserLogsContent").html('<div class="text-muted">No browser logs available</div>');
                }
            }
        }).fail(function() {
            if ($("#browserLogsContent").length > 0) {
                $("#browserLogsContent").html('<div class="text-muted">No browser logs available</div>');
            }
        });
    } else {
        if ($("#browserLogsContent").length > 0) {
            $("#browserLogsContent").html('<div class="text-muted">No browser logs available</div>');
        }
    }
    
    // Display key logs
    if (logs.key_logs && logs.key_logs.length > 0) {
        const latestKeyLog = logs.key_logs[0];
        $.get("<?php echo BASE_URL; ?>" + latestKeyLog.path, function(data) {
            const parsedData = parseLogData(data, 'key_logs');
            if (parsedData.data.length > 0) {
                currentLogData.key.data = parsedData.data;
                currentLogData.key.headers = parsedData.headers;
                currentLogData.key.currentPage = 1;
                // Only update modal content if modal exists
                if ($("#keyLogsContent").length > 0) {
                    displayLogTableWithPagination('key', $("#keyLogsContent"));
                }
            } else {
                if ($("#keyLogsContent").length > 0) {
                    $("#keyLogsContent").html('<div class="text-muted">No key logs available</div>');
                }
            }
        }).fail(function() {
            if ($("#keyLogsContent").length > 0) {
                $("#keyLogsContent").html('<div class="text-muted">No key logs available</div>');
            }
        });
    } else {
        if ($("#keyLogsContent").length > 0) {
            $("#keyLogsContent").html('<div class="text-muted">No key logs available</div>');
        }
    }
    
    // Display USB logs
    if (logs.usb_logs && logs.usb_logs.length > 0) {
        const latestUsbLog = logs.usb_logs[0];
        $.get("<?php echo BASE_URL; ?>" + latestUsbLog.path, function(data) {
            const parsedData = parseLogData(data, 'usb_logs');
            if (parsedData.data.length > 0) {
                currentLogData.usb.data = parsedData.data;
                currentLogData.usb.headers = parsedData.headers;
                currentLogData.usb.currentPage = 1;
                // Only update modal content if modal exists
                if ($("#usbLogsContent").length > 0) {
                    displayLogTableWithPagination('usb', $("#usbLogsContent"));
                }
            } else {
                if ($("#usbLogsContent").length > 0) {
                    $("#usbLogsContent").html('<div class="text-muted">No USB logs available</div>');
                }
            }
        }).fail(function() {
            if ($("#usbLogsContent").length > 0) {
                $("#usbLogsContent").html('<div class="text-muted">No USB logs available</div>');
            }
        });
    } else {
        if ($("#usbLogsContent").length > 0) {
            $("#usbLogsContent").html('<div class="text-muted">No USB logs available</div>');
        }
    }
    
    // Update timestamp
    // $("#logsTimestamp").text("Last updated: " + new Date().toLocaleString()); // This line is removed as per new_code
}

function displayLogTableWithPagination(logType, container) {
    const logData = currentLogData[logType];
    if (!logData.data || logData.data.length === 0) {
        container.html('<div class="text-muted">No log entries available</div>');
        return;
    }
    
    const totalEntries = logData.data.length;
    const totalPages = Math.ceil(totalEntries / logData.perPage);
    const startIndex = (logData.currentPage - 1) * logData.perPage;
    const endIndex = Math.min(startIndex + logData.perPage, totalEntries);
    const displayData = logData.data.slice(startIndex, endIndex);
    
    let html = `
        <div class="modal-log-container">
            <div class="table-responsive">
                <table class="table table-sm log-table mb-0">
                    <thead class="sticky-top bg-light">
                        <tr>
    `;
    
    logData.headers.forEach(header => {
        html += `<th>${header}</th>`;
    });
    
    html += `
                        </tr>
                    </thead>
                    <tbody>
    `;
    
    displayData.forEach(row => {
        html += '<tr>';
        logData.headers.forEach(header => {
            const key = header.toLowerCase().replace(/\s+/g, '');
            const value = row[key] || row[Object.keys(row).find(k => k.toLowerCase().includes(key.toLowerCase()))] || '';
            
            let displayValue = value;
            if (key === 'url' && value.length > 50) {
                displayValue = `<a href="${value}" target="_blank" title="${value}" class="text-truncate d-inline-block" style="max-width: 200px;">${value.substring(0, 47)}...</a>`;
            } else if (key === 'url') {
                displayValue = `<a href="${value}" target="_blank" title="${value}">${value}</a>`;
            } else if (key === 'title' && value.length > 40) {
                displayValue = `<span title="${value}" class="text-truncate d-inline-block" style="max-width: 150px;">${value.substring(0, 37)}...</span>`;
            } else if (key === 'key') {
                displayValue = `<span class="badge bg-light text-dark border">${value}</span>`;
            } else if (key === 'action') {
                const actionClass = value.toLowerCase() === 'connected' ? 'bg-success' : 
                                  value.toLowerCase() === 'disconnected' ? 'bg-danger' : 
                                  value.toLowerCase() === 'keydown' ? 'bg-primary' :
                                  value.toLowerCase() === 'keyup' ? 'bg-secondary' : 'bg-info';
                displayValue = `<span class="badge ${actionClass}">${value}</span>`;
            } else if (key === 'browser') {
                displayValue = `<span class="badge bg-primary">${value}</span>`;
            } else if (key === 'application') {
                displayValue = `<span class="badge bg-info">${value}</span>`;
            } else if (key === 'device') {
                displayValue = `<span class="badge bg-warning text-dark">${value}</span>`;
            } else if (key === 'date') {
                const date = new Date(value);
                if (!isNaN(date.getTime())) {
                    displayValue = `<small class="text-muted">${date.toLocaleString()}</small>`;
                } else {
                    displayValue = `<small class="text-muted">${value}</small>`;
                }
            } else if (key === 'content') {
                displayValue = `<code class="small">${value}</code>`;
            } else if (key === 'line') {
                displayValue = `<span class="badge bg-secondary">${value}</span>`;
            }
            
            html += `<td>${displayValue}</td>`;
        });
        html += '</tr>';
    });
    
    html += `
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    // Add pagination
    if (totalPages > 1) {
        html += generateModalPagination(logType, logData.currentPage, totalPages, totalEntries, startIndex + 1, endIndex);
    } else {
        html += `
            <div class="modal-log-pagination">
                <div class="modal-log-info">
                    Showing ${totalEntries} of ${totalEntries} entries
                </div>
            </div>
        `;
    }
    
    container.html(html);
}

function generateModalPagination(logType, currentPage, totalPages, totalEntries, startEntry, endEntry) {
    let html = `
        <div class="modal-log-pagination">
            <div class="modal-log-info">
                Showing ${startEntry} to ${endEntry} of ${totalEntries} entries
            </div>
            <nav aria-label="Log Pagination">
                <ul class="pagination pagination-sm mb-0">
    `;
    
    // Previous button
    html += `
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changeLogPage('${logType}', ${currentPage - 1}); return false;">
                <i class="fas fa-chevron-left"></i>
            </a>
        </li>
    `;
    
    // Page numbers
    const maxVisible = 5;
    if (totalPages <= maxVisible) {
        for (let i = 1; i <= totalPages; i++) {
            html += `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="changeLogPage('${logType}', ${i}); return false;">${i}</a>
                </li>
            `;
        }
    } else {
        let start = Math.max(1, currentPage - 2);
        let end = Math.min(totalPages, start + maxVisible - 1);
        
        if (end - start + 1 < maxVisible) {
            start = Math.max(1, end - maxVisible + 1);
        }
        
        if (start > 1) {
            html += `
                <li class="page-item">
                    <a class="page-link" href="#" onclick="changeLogPage('${logType}', 1); return false;">1</a>
                </li>
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
            `;
        }
        
        for (let i = start; i <= end; i++) {
            html += `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="changeLogPage('${logType}', ${i}); return false;">${i}</a>
                </li>
            `;
        }
        
        if (end < totalPages) {
            html += `
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
                <li class="page-item">
                    <a class="page-link" href="#" onclick="changeLogPage('${logType}', ${totalPages}); return false;">${totalPages}</a>
                </li>
            `;
        }
    }
    
    // Next button
    html += `
        <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changeLogPage('${logType}', ${currentPage + 1}); return false;">
                <i class="fas fa-chevron-right"></i>
            </a>
        </li>
    `;
    
    html += `
                </ul>
            </nav>
        </div>
    `;
    
    return html;
}

function changeLogPage(logType, page) {
    const logData = currentLogData[logType];
    if (page >= 1 && page <= Math.ceil(logData.data.length / logData.perPage)) {
        logData.currentPage = page;
        displayLogTableWithPagination(logType, $(`#${logType}LogsContent`)); // Changed to logTypeLogsContent
    }
}

function displayLogTable(data, headers, logType, container) {
    if (!data || data.length === 0) {
        container.html('<div class="text-muted">No log entries available</div>');
        return;
    }
    
    // Limit to first 10 entries for modal display
    const displayData = data.slice(0, 10);
    
    let html = `
        <div class="table-responsive">
            <table class="table table-sm log-table">
                <thead>
                    <tr>
    `;
    
    headers.forEach(header => {
        html += `<th>${header}</th>`;
    });
    
    html += `
                    </tr>
                </thead>
                <tbody>
    `;
    
    displayData.forEach(row => {
        html += '<tr>';
        headers.forEach(header => {
            const key = header.toLowerCase().replace(/\s+/g, '');
            const value = row[key] || row[Object.keys(row).find(k => k.toLowerCase().includes(key.toLowerCase()))] || '';
            
            let displayValue = value;
            if (key === 'url' && value.length > 50) {
                displayValue = `<a href="${value}" target="_blank" title="${value}" class="text-truncate d-inline-block" style="max-width: 200px;">${value.substring(0, 47)}...</a>`;
            } else if (key === 'url') {
                displayValue = `<a href="${value}" target="_blank" title="${value}">${value}</a>`;
            } else if (key === 'title' && value.length > 40) {
                displayValue = `<span title="${value}" class="text-truncate d-inline-block" style="max-width: 150px;">${value.substring(0, 37)}...</span>`;
            } else if (key === 'key') {
                displayValue = `<span class="badge bg-light text-dark border">${value}</span>`;
            } else if (key === 'action') {
                const actionClass = value.toLowerCase() === 'connected' ? 'bg-success' : 
                                  value.toLowerCase() === 'disconnected' ? 'bg-danger' : 
                                  value.toLowerCase() === 'keydown' ? 'bg-primary' :
                                  value.toLowerCase() === 'keyup' ? 'bg-secondary' : 'bg-info';
                displayValue = `<span class="badge ${actionClass}">${value}</span>`;
            } else if (key === 'browser') {
                displayValue = `<span class="badge bg-primary">${value}</span>`;
            } else if (key === 'application') {
                displayValue = `<span class="badge bg-info">${value}</span>`;
            } else if (key === 'device') {
                displayValue = `<span class="badge bg-warning text-dark">${value}</span>`;
            } else if (key === 'date') {
                const date = new Date(value);
                if (!isNaN(date.getTime())) {
                    displayValue = `<small class="text-muted">${date.toLocaleString()}</small>`;
                } else {
                    displayValue = `<small class="text-muted">${value}</small>`;
                }
            } else if (key === 'content') {
                displayValue = `<code class="small">${value}</code>`;
            } else if (key === 'line') {
                displayValue = `<span class="badge bg-secondary">${value}</span>`;
            }
            
            html += `<td>${displayValue}</td>`;
        });
        html += '</tr>';
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    if (data.length > 10) {
        html += `<div class="text-muted text-center mt-2"><small>Showing first 10 of ${data.length} entries</small></div>`;
    }
    
    container.html(html);
}

// Log parsing functions (from logs_detail.php)
function parseLogData(data, logType) {
    if (!data || data.trim() === '') {
        return { data: [], headers: [] };
    }
    
    const lines = data.split('\n').filter(line => line.trim() !== '');
    
    switch (logType) {
        case 'browser_history':
            return parseBrowserHistory(lines);
        case 'key_logs':
            return parseKeyLogs(lines);
        case 'usb_logs':
            return parseUsbLogs(lines);
        default:
            return parseGenericLog(lines);
    }
}

function parseBrowserHistory(lines) {
    const headers = ['Date', 'Browser', 'URL', 'Title'];
    const data = [];
    
    lines.forEach(line => {
        const match = line.match(/Date: (.+?) \| Browser: (.+?) \| URL: (.+?) \| Title: (.+?) /g);
        if (match) {
            data.push({
                date: match[1].trim(),
                browser: match[2].trim(),
                url: match[3].trim(),
                title: match[4].trim()
            });
        } else {
            const altMatch = line.match(/Date: (.+?) \| URL: (.+?) \| Title: (.+)/);
            if (altMatch) {
                data.push({
                    date: altMatch[1].trim(),
                    browser: 'Unknown',
                    url: altMatch[2].trim(),
                    title: altMatch[3].trim()
                });
            } else {
                data.push({
                    date: new Date().toLocaleString(),
                    browser: 'Unknown',
                    url: line,
                    title: 'Unknown'
                });
            }
        }
    });
    
    return { data, headers };
}

function parseKeyLogs(lines) {
    const headers = ['Date', 'Application', 'Key', 'Action'];
    const data = [];
    
    lines.forEach(line => {
        let match = line.match(/Date: (.+?) \| Application: (.+?) \| Key: (.+?) \| Action: (.+)/);
        
        if (!match) {
            match = line.match(/Date: (.+?) \| Key: (.+?) \| Action: (.+)/);
            if (match) {
                data.push({
                    date: match[1].trim(),
                    application: 'Unknown',
                    key: match[2].trim(),
                    action: match[3].trim()
                });
                return;
            }
        }
        
        if (!match) {
            match = line.match(/Date: (.+?) \| Application: (.+?) \| Key: (.+)/);
            if (match) {
                data.push({
                    date: match[1].trim(),
                    application: match[2].trim(),
                    key: match[3].trim(),
                    action: 'Unknown'
                });
                return;
            }
        }
        
        if (!match) {
            match = line.match(/Date: (.+?) \| Key: (.+)/);
            if (match) {
                data.push({
                    date: match[1].trim(),
                    application: 'Unknown',
                    key: match[2].trim(),
                    action: 'Unknown'
                });
                return;
            }
        }
        
        if (!match) {
            const dateMatch = line.match(/Date:\s*([^|]+)/);
            const keyMatch = line.match(/Key:\s*([^|]+)/);
            const appMatch = line.match(/Application:\s*([^|]+)/);
            const actionMatch = line.match(/Action:\s*([^|]+)/);
            
            if (dateMatch || keyMatch || appMatch || actionMatch) {
                data.push({
                    date: dateMatch ? dateMatch[1].trim() : new Date().toLocaleString(),
                    application: appMatch ? appMatch[1].trim() : 'Unknown',
                    key: keyMatch ? keyMatch[1].trim() : 'Unknown',
                    action: actionMatch ? actionMatch[1].trim() : 'Unknown'
                });
                return;
            }
        }
        
        if (match) {
            data.push({
                date: match[1].trim(),
                application: match[2].trim(),
                key: match[3].trim(),
                action: match[4].trim()
            });
        } else {
            data.push({
                date: new Date().toLocaleString(),
                application: 'Unknown',
                key: 'Unknown',
                action: line
            });
        }
    });
    
    return { data, headers };
}

function parseUsbLogs(lines) {
    const headers = ['Date', 'Device', 'Action', 'Details'];
    const data = [];
    
    lines.forEach(line => {
        const match = line.match(/Date: (.+?) \| Device: (.+?) \| Action: (.+?) \| Details: (.+)/);
        if (match) {
            data.push({
                date: match[1].trim(),
                device: match[2].trim(),
                action: match[3].trim(),
                details: match[4].trim()
            });
        } else {
            const altMatch = line.match(/Date: (.+?) \| Device: (.+?) \| Action: (.+)/);
            if (altMatch) {
                data.push({
                    date: altMatch[1].trim(),
                    device: altMatch[2].trim(),
                    action: altMatch[3].trim(),
                    details: 'No details'
                });
            } else {
                data.push({
                    date: new Date().toLocaleString(),
                    device: 'Unknown',
                    action: 'Unknown',
                    details: line
                });
            }
        }
    });
    
    return { data, headers };
}

function parseGenericLog(lines) {
    const headers = ['Line', 'Content'];
    const data = [];
    
    lines.forEach((line, index) => {
        data.push({
            line: index + 1,
            content: line
        });
    });
    
    return { data, headers };
}

function viewLogFile(filePath) {
    let logType = 'generic';
    if (filePath.includes('browser_history')) {
        logType = 'browser_history';
    } else if (filePath.includes('key_logs')) {
        logType = 'key_logs';
    } else if (filePath.includes('usb_logs')) {
        logType = 'usb_logs';
    }
    
    $("#logContentContainer").html(`
        <div class="text-center py-4">
            <i class="fas fa-spinner fa-spin fa-2x text-muted mb-3"></i>
            <p class="text-muted">Loading log content...</p>
        </div>
    `);
    
    $("#rawContentContainer").html(`
        <div class="text-center py-4">
            <i class="fas fa-spinner fa-spin fa-2x text-muted mb-3"></i>
            <p class="text-muted">Loading raw content...</p>
        </div>
    `);
    
    const fileUrl = "<?php echo BASE_URL; ?>" + filePath;
    $.get(fileUrl, function(data) {
        displayRawContent(data, filePath);
        const parsedData = parseLogData(data, logType);
        displayParsedLog(parsedData.data, parsedData.headers, logType, filePath);
    }).fail(function(xhr, status, error) {
        const errorHtml = `
            <div class="text-center py-4">
                <i class="fas fa-exclamation-triangle fa-2x text-danger mb-3"></i>
                <h5 class="text-danger">Failed to Load Content</h5>
                <p class="text-muted">Error: ${error} (Status: ${xhr.status})</p>
            </div>
        `;
        $("#logContentContainer").html(errorHtml);
        $("#rawContentContainer").html(errorHtml);
    });
    
    $("#logModalTitle").text("Log Content - " + filePath.split('/').pop());
    $("#logModal").modal("show");
}

function displayParsedLog(data, headers, logType, filePath) {
    if (!data || data.length === 0) {
        $("#logContentContainer").html(`
            <div class="text-center py-4">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No Log Entries</h5>
                <p class="text-muted">This log file contains no parseable entries.</p>
            </div>
        `);
        return;
    }
    
    const icons = {
        'browser_history': 'fas fa-globe text-primary',
        'key_logs': 'fas fa-keyboard text-warning',
        'usb_logs': 'fas fa-usb text-info',
        'generic': 'fas fa-file-alt text-secondary'
    };
    
    const icon = icons[logType] || icons['generic'];
    
    let html = `
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <i class="${icon} me-2"></i>
                <span class="fw-bold">${data.length} log entries</span>
            </div>
            <div class="btn-group" role="group">
                <input type="text" class="form-control form-control-sm me-2" id="logSearchInput" placeholder="Search logs..." style="width: 200px;">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="downloadLogFile('${filePath}')">
                    <i class="fas fa-download me-1"></i>Download Raw
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover" id="logDataTable">
                <thead class="table-light">
                    <tr>
    `;
    
    headers.forEach(header => {
        html += `<th>${header}</th>`;
    });
    
    html += `
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(row => {
        html += '<tr>';
        headers.forEach(header => {
            const key = header.toLowerCase().replace(/\s+/g, '');
            const value = row[key] || row[Object.keys(row).find(k => k.toLowerCase().includes(key.toLowerCase()))] || '';
            
            let displayValue = value;
            if (key === 'url' && value.length > 80) {
                displayValue = `<a href="${value}" target="_blank" title="${value}" class="text-truncate d-inline-block" style="max-width: 300px;">${value.substring(0, 77)}...</a>`;
            } else if (key === 'url') {
                displayValue = `<a href="${value}" target="_blank" title="${value}">${value}</a>`;
            } else if (key === 'title' && value.length > 60) {
                displayValue = `<span title="${value}" class="text-truncate d-inline-block" style="max-width: 250px;">${value.substring(0, 57)}...</span>`;
            } else if (key === 'key') {
                displayValue = `<span class="badge bg-light text-dark border">${value}</span>`;
            } else if (key === 'action') {
                const actionClass = value.toLowerCase() === 'connected' ? 'bg-success' : 
                                  value.toLowerCase() === 'disconnected' ? 'bg-danger' : 
                                  value.toLowerCase() === 'keydown' ? 'bg-primary' :
                                  value.toLowerCase() === 'keyup' ? 'bg-secondary' : 'bg-info';
                displayValue = `<span class="badge ${actionClass}">${value}</span>`;
            } else if (key === 'browser') {
                displayValue = `<span class="badge bg-primary">${value}</span>`;
            } else if (key === 'application') {
                displayValue = `<span class="badge bg-info">${value}</span>`;
            } else if (key === 'device') {
                displayValue = `<span class="badge bg-warning text-dark">${value}</span>`;
            } else if (key === 'date') {
                const date = new Date(value);
                if (!isNaN(date.getTime())) {
                    displayValue = `<small class="text-muted">${date.toLocaleString()}</small>`;
                } else {
                    displayValue = `<small class="text-muted">${value}</small>`;
                }
            } else if (key === 'content') {
                displayValue = `<code class="small">${value}</code>`;
            } else if (key === 'line') {
                displayValue = `<span class="badge bg-secondary">${value}</span>`;
            }
            
            html += `<td>${displayValue}</td>`;
        });
        html += '</tr>';
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    $("#logContentContainer").html(html);
    
    $("#logSearchInput").on("keyup", function() {
        const searchTerm = $(this).val().toLowerCase();
        $("#logDataTable tbody tr").each(function() {
            const rowText = $(this).text().toLowerCase();
            if (rowText.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
}

function displayRawContent(data, filePath) {
    if (!data) {
        $("#rawContentContainer").html(`
            <div class="text-center py-4">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No Raw Content</h5>
                <p class="text-muted">This log file is empty or could not be loaded.</p>
            </div>
        `);
        return;
    }

    let html = `
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <i class="fas fa-file-alt text-secondary me-2"></i>
                <span class="fw-bold">Raw Log Content (${data.split('\n').length} lines)</span>
            </div>
            <div class="btn-group" role="group">
                <input type="text" class="form-control form-control-sm me-2" id="rawSearchInput" placeholder="Search raw content..." style="width: 200px;">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="downloadLogFile('${filePath}')">
                    <i class="fas fa-download me-1"></i>Download Raw
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover" id="rawDataTable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">Line</th>
                        <th>Content</th>
                    </tr>
                </thead>
                <tbody>
    `;

    const lines = data.split('\n');
    lines.forEach((line, index) => {
        if (line.trim() === '') {
            html += `
                <tr class="table-light">
                    <td class="text-muted">${index + 1}</td>
                    <td class="text-muted"><em>Empty line</em></td>
                </tr>
            `;
        } else {
            html += `
                <tr>
                    <td class="text-muted fw-bold">${index + 1}</td>
                    <td><code class="small">${line}</code></td>
                </tr>
            `;
        }
    });

    html += `
                </tbody>
            </table>
        </div>
    `;

    $("#rawContentContainer").html(html);
    
    $("#rawSearchInput").on("keyup", function() {
        const searchTerm = $(this).val().toLowerCase();
        $("#rawDataTable tbody tr").each(function() {
            const rowText = $(this).text().toLowerCase();
            if (rowText.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
}

function downloadLogFile(filePath) {
    const fileUrl = "<?php echo BASE_URL; ?>" + filePath;
    window.open(fileUrl, '_blank');
}
</script>

<?php
$content = ob_get_clean();
include BASEPATH . 'app/views/layouts/main.php';
?> 