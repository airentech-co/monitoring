<?php
$title = 'Monitors - ' . APP_NAME;
$active_page = 'monitors';
$page_title = 'Monitor IPs';
ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Monitor IPs</h3>
    <?php if ($_SESSION['role'] === ROLE_ADMIN): ?>
    <a href="<?php echo BASE_URL; ?>monitors/create" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i>Add Monitor
    </a>
    <?php endif; ?>
</div>

<div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Note:</strong> Usernames can be updated by monitoring clients via API or tic events. The username field shows the current username from the client.
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>IP Address</th>
                        <th>Username</th>
                        <th>Status</th>
                        <th>Last Activity</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
<?php foreach ($monitors as $monitor): 
    $status_class = $monitor['monitor_status'] ? 'success' : 'danger';
    $status_text = $monitor['monitor_status'] ? 'Active' : 'Inactive';
    $last_activity = $monitor['last_monitor_tic'] ? date('M j, Y H:i', strtotime($monitor['last_monitor_tic'])) : 'Never';
?>
                    <tr>
                        <td><?php echo htmlspecialchars($monitor['name']); ?></td>
                        <td><?php echo htmlspecialchars($monitor['ip_address']); ?></td>
                        <td><?php echo htmlspecialchars($monitor['username']); ?></td>
                        <td><span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                        <td><?php echo $last_activity; ?></td>
                        <td>
                            <?php if ($_SESSION['role'] === ROLE_ADMIN): ?>
                            <a href="<?php echo BASE_URL; ?>monitors/edit/<?php echo $monitor['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                            <?php endif; ?>
                            <a href="<?php echo BASE_URL; ?>view/logs/<?php echo $monitor['id']; ?>" class="btn btn-sm btn-outline-info">View Logs</a>
                            <?php if ($_SESSION['role'] === ROLE_ADMIN): ?>
                            <a href="<?php echo BASE_URL; ?>monitors/delete/<?php echo $monitor['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure?')">Delete</a>
                            <?php endif; ?>
                        </td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include BASEPATH . 'app/views/layouts/main.php';
?> 