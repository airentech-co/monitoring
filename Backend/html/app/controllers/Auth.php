<?php
require_once BASEPATH . 'app/controllers/Base.php';

class Auth extends Base {
    
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                $error = 'Username and password are required';
            } else {
                $user = $this->db->fetch(
                    "SELECT * FROM users WHERE username = :username",
                    ['username' => $username]
                );
                
                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    
                    $this->redirect('dashboard');
                } else {
                    $error = 'Invalid username or password';
                }
            }
        }
        
        $this->view('auth/login', ['error' => $error ?? null]);
    }
    
    public function logout() {
        session_destroy();
        $this->redirect('auth/login');
    }
    
    public function register() {
        $this->requireAdmin();
        
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
                        $success = 'User created successfully';
                    } else {
                        $error = 'Failed to create user';
                    }
                }
            }
        }
        
        $this->view('auth/register', [
            'error' => $error ?? null,
            'success' => $success ?? null
        ]);
    }
}
?> 