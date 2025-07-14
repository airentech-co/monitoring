<?php
$title = 'Data Sync - ' . APP_NAME;
$active_page = 'sync';
$page_title = 'Data Synchronization';
ob_start();
?>

<?php if (isset($synced_count) && $synced_count > 0): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i>
    <strong>Sync Complete!</strong> <?php echo $synced_count; ?> new monitor(s) were discovered and added to the database.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Data Directories</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Screenshots:</strong> <?php echo SCREENS_DIR; ?>
                </div>
                <div class="mb-3">
                    <strong>Thumbnails:</strong> <?php echo THUMBNAILS_DIR; ?>
                </div>
                <div class="mb-3">
                    <strong>Logs:</strong> <?php echo LOGS_DIR; ?>
                </div>
                <button id="syncBtn" class="btn btn-primary">
                    <i class="fas fa-sync-alt me-2"></i>Sync Now
                </button>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Sync Status</h5>
            </div>
            <div class="card-body">
                <div id="syncStatus">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading sync status...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">IP Directories Found</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>IP Directory</th>
                                <th>IP Address</th>
                                <th>Screenshots</th>
                                <th>Thumbnails</th>
                                <th>Logs</th>
                                <th>Total Size</th>
                            </tr>
                        </thead>
                        <tbody>
<?php if (empty($ip_dirs)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">
                                    <i class="fas fa-folder-open fa-2x mb-2"></i>
                                    <p>No IP directories found in data folders</p>
                                </td>
                            </tr>
<?php else: ?>
    <?php foreach ($ip_dirs as $identifier => $data): 
        $total_size = 0;
        if ($data['screens']) {
            $total_size += $this->dataSync->getDirectorySize(SCREENS_DIR . $identifier);
        }
        if ($data['thumbnails']) {
            $total_size += $this->dataSync->getDirectorySize(THUMBNAILS_DIR . $identifier);
        }
        if ($data['logs']) {
            $total_size += $this->dataSync->getDirectorySize(LOGS_DIR . $identifier);
        }
    ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($identifier); ?></code></td>
                                <td><?php echo $data['ip_address'] ? htmlspecialchars($data['ip_address']) : 'Unknown'; ?></td>
                                <td>
                                    <?php if ($data['screens']): ?>
                                        <span class="badge bg-success">Available</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($data['thumbnails']): ?>
                                        <span class="badge bg-success">Available</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($data['logs']): ?>
                                        <span class="badge bg-success">Available</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">None</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $this->dataSync->formatFileSize($total_size); ?></td>
                            </tr>
    <?php endforeach; ?>
<?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Load sync status
    loadSyncStatus();
    
    // Sync button click handler
    $('#syncBtn').click(function() {
        const btn = $(this);
        const originalText = btn.html();
        
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Syncing...');
        
        $.ajax({
            url: '<?php echo BASE_URL; ?>sync/api_sync',
            method: 'POST',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message);
                    loadSyncStatus();
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showAlert('danger', 'Sync failed: ' + response.message);
                }
            },
            error: function() {
                showAlert('danger', 'Sync failed. Please try again.');
            },
            complete: function() {
                btn.prop('disabled', false).html(originalText);
            }
        });
    });
    
    function loadSyncStatus() {
        $.ajax({
            url: '<?php echo BASE_URL; ?>sync/api_status',
            method: 'GET',
            success: function(response) {
                $('#syncStatus').html(`
                    <div class="row">
                        <div class="col-6">
                            <div class="text-center">
                                <h4>${response.total_monitors}</h4>
                                <small class="text-muted">Total Monitors</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center">
                                <h4>${response.active_monitors}</h4>
                                <small class="text-muted">Active Monitors</small>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <h6>${Object.keys(response.ip_directories).length}</h6>
                        <small class="text-muted">IP Directories Found</small>
                    </div>
                `);
            },
            error: function() {
                $('#syncStatus').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Failed to load sync status
                    </div>
                `);
            }
        });
    }
    
    function showAlert(type, message) {
        const alert = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        $('.container-fluid').prepend(alert);
    }
});
</script>

<?php
$content = ob_get_clean();
include BASEPATH . 'app/views/layouts/main.php';
?> 