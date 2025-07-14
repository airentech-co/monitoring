<?php
$title = 'Create Monitor - ' . APP_NAME;
$active_page = 'monitors';
$page_title = 'Create Monitor';
ob_start();
?>
<div class="card">
    <div class="card-body">
        <form method="POST" action="<?php echo BASE_URL; ?>monitors/create">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="ip_address" class="form-label">IP Address</label>
                        <input type="text" class="form-control" id="ip_address" name="ip_address" required>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="type" class="form-label">Type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="desktop">Desktop</option>
                            <option value="laptop">Laptop</option>
                            <option value="server">Server</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="d-flex justify-content-between">
                <a href="<?php echo BASE_URL; ?>monitors" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Create Monitor</button>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
include BASEPATH . 'app/views/layouts/main.php';
?> 