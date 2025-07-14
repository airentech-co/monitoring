<?php
$title = 'Users - ' . APP_NAME;
$active_page = 'users';
$page_title = 'User Management';
ob_start();
?>
<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Users</h3>
    <?php if ($_SESSION['role'] === ROLE_ADMIN): ?>
    <a href="<?php echo BASE_URL; ?>users/create" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i>Add User
    </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
<?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><span class="badge bg-<?php echo $user['role'] === ROLE_ADMIN ? 'danger' : 'secondary'; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                        <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                        <td>
                            <?php if ($_SESSION['role'] === ROLE_ADMIN): ?>
                            <a href="<?php echo BASE_URL; ?>users/edit/<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <a href="<?php echo BASE_URL; ?>users/delete/<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure?')">Delete</a>
                            <?php endif; ?>
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