<?php
// api/endpoints/register.php - Endpoint untuk registrasi pengguna

class RegisterController {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function process($method, $data) {
        if ($method !== 'POST') {
            header("HTTP/1.1 405 Method Not Allowed");
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $this->register($data);
    }
    
    private function register($data) {
        // Validasi input
        if (!isset($data['username']) || !isset($data['password']) || !isset($data['email']) || !isset($data['role'])) {
            header("HTTP/1.1 400 Bad Request");
            echo json_encode(['error' => 'Missing required fields: username, password, email, role']);
            return;
        }
        
        // Validasi role
        $allowed_roles = ['publisher', 'advertiser'];
        if (!in_array($data['role'], $allowed_roles)) {
            header("HTTP/1.1 400 Bad Request");
            echo json_encode(['error' => 'Invalid role. Allowed roles: publisher, advertiser']);
            return;
        }
        
        try {
            // Check if username or email already exists
            $check_query = "SELECT id FROM users WHERE username = :username OR email = :email";
            $check_stmt = $this->db->prepare($check_query);
            $check_stmt->bindParam(':username', $data['username']);
            $check_stmt->bindParam(':email', $data['email']);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                header("HTTP/1.1 409 Conflict");
                echo json_encode(['error' => 'Username or email already exists']);
                return;
            }
            
            // Hash password
            $hashed_password = password_hash($data['password'], PASSWORD_BCRYPT);
            
            // Begin transaction
            $this->db->beginTransaction();
            
            // Insert user
            $user_query = "INSERT INTO users 
                          (role, username, password, email, first_name, last_name, phone, status) 
                          VALUES 
                          (:role, :username, :password, :email, :first_name, :last_name, :phone, 'pending')";
            
            $user_stmt = $this->db->prepare($user_query);
            $user_stmt->bindParam(':role', $data['role']);
            $user_stmt->bindParam(':username', $data['username']);
            $user_stmt->bindParam(':password', $hashed_password);
            $user_stmt->bindParam(':email', $data['email']);
            $user_stmt->bindParam(':first_name', $data['first_name'] ?? null);
            $user_stmt->bindParam(':last_name', $data['last_name'] ?? null);
            $user_stmt->bindParam(':phone', $data['phone'] ?? null);
            $user_stmt->execute();
            
            $user_id = $this->db->lastInsertId();
            
            // Create role-specific record
            if ($data['role'] === 'publisher') {
                if (!isset($data['website_url']) || !isset($data['website_name'])) {
                    $this->db->rollBack();
                    header("HTTP/1.1 400 Bad Request");
                    echo json_encode(['error' => 'Missing required fields for publisher: website_url, website_name']);
                    return;
                }
                
                $publisher_query = "INSERT INTO publishers 
                                  (user_id, website_url, website_name, website_category, website_description, status) 
                                  VALUES 
                                  (:user_id, :website_url, :website_name, :website_category, :website_description, 'pending')";
                
                $publisher_stmt = $this->db->prepare($publisher_query);
                $publisher_stmt->bindParam(':user_id', $user_id);
                $publisher_stmt->bindParam(':website_url', $data['website_url']);
                $publisher_stmt->bindParam(':website_name', $data['website_name']);
                $publisher_stmt->bindParam(':website_category', $data['website_category'] ?? null);
                $publisher_stmt->bindParam(':website_description', $data['website_description'] ?? null);
                $publisher_stmt->execute();
            } 
            elseif ($data['role'] === 'advertiser') {
                if (!isset($data['company_name'])) {
                    $this->db->rollBack();
                    header("HTTP/1.1 400 Bad Request");
                    echo json_encode(['error' => 'Missing required field for advertiser: company_name']);
                    return;
                }
                
                $advertiser_query = "INSERT INTO advertisers 
                                   (user_id, company_name, contact_person, industry, status) 
                                   VALUES 
                                   (:user_id, :company_name, :contact_person, :industry, 'pending')";
                
                $advertiser_stmt = $this->db->prepare($advertiser_query);
                $advertiser_stmt->bindParam(':user_id', $user_id);
                $advertiser_stmt->bindParam(':company_name', $data['company_name']);
                $advertiser_stmt->bindParam(':contact_person', $data['contact_person'] ?? null);
                $advertiser_stmt->bindParam(':industry', $data['industry'] ?? null);
                $advertiser_stmt->execute();
            }
            
            // Commit transaction
            $this->db->commit();
            
            // Return success response
            header("HTTP/1.1 201 Created");
            echo json_encode([
                'message' => 'Registration successful',
                'user_id' => $user_id,
                'status' => 'pending',
                'role' => $data['role']
            ]);
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(['error' => 'Registration failed: ' . $e->getMessage()]);
        }
    }
}
