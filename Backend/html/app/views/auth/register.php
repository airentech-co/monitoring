<?php
$title = 'Register User - ' . APP_NAME;
$active_page = 'users';
$page_title = 'Register User';
ob_start();
?>
<div class="card">
    <div class="card-body">
        <form method="POST" action="<?php echo BASE_URL; ?>auth/register">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" id="role" name="role">
                            <option value="<?php echo ROLE_USER; ?>">User</option>
                            <option value="<?php echo ROLE_ADMIN; ?>">Admin</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="d-flex justify-content-between">
                <a href="<?php echo BASE_URL; ?>users" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
include BASEPATH . 'app/views/layouts/main.php';
?> 