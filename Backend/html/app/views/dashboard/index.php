<?php
$title = 'Dashboard - ' . APP_NAME;
$active_page = 'dashboard';
$page_title = 'Dashboard';
ob_start();
?>
<?php if (isset($syncedCount) && $syncedCount > 0): ?>
<div class="alert alert-info alert-dismissible fade show" role="alert">
    <i class="fas fa-sync-alt me-2"></i>
    <strong>Data Sync Complete!</strong> <?php echo $syncedCount; ?> new monitor(s) were discovered and added to the database.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card text-white bg-primary mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="card-title">Total Users</h5>
                        <h2 class="card-text"><?php echo $totalUsers; ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card text-white bg-success mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="card-title">Total Monitors</h5>
                        <h2 class="card-text"><?php echo $totalMonitors; ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-desktop fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card text-white bg-info mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="card-title">Active Monitors</h5>
                        <h2 class="card-text"><?php echo $activeMonitors; ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Recent Monitor Activity</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>IP Address</th>
                                <th>Status</th>
                                <th>Data Available</th>
                                <th>Last Activity</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
<?php foreach ($recentMonitors as $monitor): 
    $status_class = $monitor['monitor_status'] ? 'success' : 'danger';
    $status_text = $monitor['monitor_status'] ? 'Active' : 'Inactive';
    $last_activity = $monitor['last_monitor_tic'] ? date('M j, Y H:i', strtotime($monitor['last_monitor_tic'])) : 'Never';
?>
                            <tr>
                                <td><?php echo htmlspecialchars($monitor['name']); ?></td>
                                <td><?php echo htmlspecialchars($monitor['ip_address']); ?></td>
                                <td><span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                <td>
                                    <?php if ($monitor['has_data']): ?>
                                        <span class="badge bg-success">Data Available</span>
                                        <?php if ($monitor['has_screenshots']): ?>
                                            <i class="fas fa-image text-primary ms-1" title="Screenshots"></i>
                                        <?php endif; ?>
                                        <?php if ($monitor['has_logs']): ?>
                                            <i class="fas fa-file-alt text-info ms-1" title="Logs"></i>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No Data</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $last_activity; ?></td>
                                <td>
                                    <?php if ($monitor['has_data']): ?>
                                        <a href="<?php echo BASE_URL; ?>view/logs/<?php echo $monitor['id']; ?>" class="btn btn-sm btn-outline-primary">View Logs</a>
                                        <a href="<?php echo BASE_URL; ?>view/screenshots" class="btn btn-sm btn-outline-info">View Screenshots</a>
                                    <?php else: ?>
                                        <span class="text-muted">No data available</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include BASEPATH . 'app/views/layouts/main.php';
?> 