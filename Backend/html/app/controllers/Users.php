<?php
require_once BASEPATH . 'app/controllers/Base.php';

class Users extends Base {
    
    public function __construct() {
        parent::__construct();
        $this->requireAdmin();
    }
    
    public function index() {
        $users = $this->db->fetchAll("SELECT * FROM users ORDER BY created_at DESC");
        
        $this->view('users/index', ['users' => $users]);
    }
    
    public function create() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $email = $_POST['email'] ?? '';
            $role = $_POST['role'] ?? ROLE_USER;
            
            if (empty($username) || empty($password) || empty($email)) {
                $error = 'All fields are required';
            } else {
                // Check if username already exists
                $existing = $this->db->fetch(
                    "SELECT id FROM users WHERE username = :username",
                    ['username' => $username]
                );
                
                if ($existing) {
                    $error = 'Username already exists';
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    $userId = $this->db->insert('users', [
                        'username' => $username,
                        'password' => $hashedPassword,
                        'email' => $email,
                        'role' => $role,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    if ($userId) {
                        $this->redirect('users');
                    } else {
                        $error = 'Failed to create user';
                    }
                }
            }
        }
        
        $this->view('users/create', ['error' => $error ?? null]);
    }
    
    public function edit($id) {
        $user = $this->db->fetch("SELECT * FROM users WHERE id = :id", ['id' => $id]);
        
        if (!$user) {
            $this->redirect('users');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $email = $_POST['email'] ?? '';
            $role = $_POST['role'] ?? ROLE_USER;
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($email)) {
                $error = 'Username and email are required';
            } else {
                // Check if username already exists for another user
                $existing = $this->db->fetch(
                    "SELECT id FROM users WHERE username = :username AND id != :id",
                    ['username' => $username, 'id' => $id]
                );
                
                if ($existing) {
                    $error = 'Username already exists';
                } else {
                    $data = [
                        'username' => $username,
                        'email' => $email,
                        'role' => $role
                    ];
                    
                    if (!empty($password)) {
                        $data['password'] = password_hash($password, PASSWORD_DEFAULT);
                    }
                    
                    $result = $this->db->update('users', $data, 'id = :id', ['id' => $id]);
                    
                    // Check if data actually changed by comparing with original user data
                    $dataChanged = false;
                    foreach ($data as $key => $value) {
                        if ($user[$key] !== $value) {
                            $dataChanged = true;
                            break;
                        }
                    }
                    
                    if ($result > 0 || !$dataChanged) {
                        // Success: either rows were updated OR no changes were needed
                        $_SESSION['success_message'] = 'User updated successfully';
                        $this->redirect('users');
                    } else {
                        $error = 'Update failed';
                    }
                }
            }
        }
        
        $this->view('users/edit', ['user' => $user, 'error' => $error ?? null]);
    }
    
    public function delete($id) {
        if ($id == $_SESSION['user_id']) {
            $this->redirect('users');
        }
        
        $this->db->delete('users', 'id = :id', ['id' => $id]);
        $this->redirect('users');
    }
}
?> 