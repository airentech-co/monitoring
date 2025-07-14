<?php
class Base {
    protected $db;
    protected $user;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->loadUser();
    }
    
    protected function loadUser() {
        if (isset($_SESSION['user_id'])) {
            $this->user = $this->db->fetch(
                "SELECT * FROM users WHERE id = :id",
                ['id' => $_SESSION['user_id']]
            );
        }
    }
    
    protected function view($view, $data = []) {
        // Extract data to variables
        extract($data);
        
        // Include the view file
        $view_file = BASEPATH . 'app/views/' . $view . '.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            die("View not found: $view");
        }
    }
    
    protected function redirect($url) {
        // If URL doesn't start with http, assume it's relative and add BASE_URL
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = BASE_URL . $url;
        }
        header('Location: ' . $url);
        exit;
    }
    
    protected function isAdmin() {
        return isset($this->user['role']) && $this->user['role'] === ROLE_ADMIN;
    }
    
    protected function requireAdmin() {
        if (!$this->isAdmin()) {
            $this->redirect('dashboard');
        }
    }
    
    protected function json($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
?> 