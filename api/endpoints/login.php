<?php
// api/endpoints/login.php - Endpoint untuk login pengguna

class LoginController {
    private $db;
    private $jwt;
    
    public function __construct($db, $jwt) {
        $this->db = $db;
        $this->jwt = $jwt;
    }
    
    public function process($method, $data) {
        if ($method !== 'POST') {
            header("HTTP/1.1 405 Method Not Allowed");
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $this->login($data);
    }
    
    private function login($data) {
        // Validasi input
        if (!isset($data['username']) || !isset($data['password'])) {
            header("HTTP/1.1 400 Bad Request");
            echo json_encode(['error' => 'Missing required fields: username, password']);
            return;
        }
        
        try {
            // Cek user berdasarkan username
            $query = "SELECT u.id, u.username, u.password, u.role, u.email, u.status, 
                      CASE 
                          WHEN u.role = 'publisher' THEN p.id 
                          WHEN u.role = 'advertiser' THEN a.id
                          ELSE NULL
                      END as role_id
                      FROM users u
                      LEFT JOIN publishers p ON u.id = p.user_id AND u.role = 'publisher'
                      LEFT JOIN advertisers a ON u.id = a.user_id AND u.role = 'advertiser'
                      WHERE u.username = :username";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $data['username']);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                header("HTTP/1.1 401 Unauthorized");
                echo json_encode(['error' => 'Invalid credentials']);
                return;
            }
            
            $user = $stmt->fetch();
            
            // Verifikasi password
            if (!password_verify($data['password'], $user['password'])) {
                header("HTTP/1.1 401 Unauthorized");
                echo json_encode(['error' => 'Invalid credentials']);
                return;
            }
            
            // Cek status user
            if ($user['status'] !== 'active') {
                header("HTTP/1.1 403 Forbidden");
                echo json_encode(['error' => 'Account is not active. Status: ' . $user['status']]);
                return;
            }
            
            // Update last login
            $update_query = "UPDATE users SET last_login = NOW() WHERE id = :id";
            $update_stmt = $this->db->prepare($update_query);
            $update_stmt->bindParam(':id', $user['id']);
            $update_stmt->execute();
            
            // Generate JWT token
            $token = $this->jwt->generateToken($user['id'], $user['username'], $user['role']);
            
            // Response success
            header("HTTP/1.1 200 OK");
            echo json_encode([
                'message' => 'Login successful',
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'role_id' => $user['role_id']
                ],
                'expires_in' => JWT_EXPIRATION
            ]);
            
        } catch (PDOException $e) {
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(['error' => 'Login failed: ' . $e->getMessage()]);
        }
    }
}
