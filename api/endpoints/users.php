<?php
/**
 * API Endpoints untuk Manajemen Pengguna
 * 
 * File ini berisi implementasi endpoint API untuk manajemen pengguna:
 * - GET /api/users - Mendapatkan daftar semua pengguna (admin only)
 * - GET /api/users/{id} - Mendapatkan detail pengguna berdasarkan ID
 * - POST /api/users - Membuat pengguna baru (admin only)
 * - PUT /api/users/{id} - Mengubah data pengguna
 * - DELETE /api/users/{id} - Menghapus pengguna (admin only)
 * 
 * @package RTB-AdServer
 * @subpackage API
 */

// Pastikan file ini hanya diakses melalui router utama
if (!defined('DIRECT_ACCESS')) {
    header("HTTP/1.1 403 Forbidden");
    echo json_encode(['error' => 'Direct access forbidden']);
    exit;
}

/**
 * Class UsersController
 * 
 * Controller untuk mengelola endpoint API pengguna
 */
class UsersController {
    /**
     * @var PDO Database connection
     */
    private $db;
    
    /**
     * Constructor
     * 
     * @param PDO $db Database connection
     */
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Process API request based on method and params
     * 
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param int|null $id User ID (optional)
     * @param array|null $data Request data (optional)
     * @param array $current_user Current authenticated user
     * @return void
     */
    public function process($method, $id, $data, $current_user) {
        switch ($method) {
            case 'GET':
                if ($id) {
                    $this->getUser($id, $current_user);
                } else {
                    $this->getAllUsers($current_user);
                }
                break;
                
            case 'POST':
                $this->createUser($data, $current_user);
                break;
                
            case 'PUT':
                $this->updateUser($id, $data, $current_user);
                break;
                
            case 'DELETE':
                $this->deleteUser($id, $current_user);
                break;
                
            default:
                header("HTTP/1.1 405 Method Not Allowed");
                echo json_encode(['error' => 'Method not allowed']);
                break;
        }
    }
    
    /**
     * Get all users (admin only)
     * 
     * @param array $current_user Current authenticated user
     * @return void
     */
    private function getAllUsers($current_user) {
        try {
            // Cek jika pengguna adalah admin
            if ($current_user['role'] !== 'admin') {
                header("HTTP/1.1 403 Forbidden");
                echo json_encode(['error' => 'Only admin can access this endpoint']);
                return;
            }
            
            // Pagination parameters
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $offset = ($page - 1) * $limit;
            
            // Filtering
            $where_clause = "";
            $params = [];
            
            // Filter by role
            if (isset($_GET['role'])) {
                $where_clause = " WHERE role = :role";
                $params[':role'] = $_GET['role'];
            }
            
            // Filter by status
            if (isset($_GET['status'])) {
                if (empty($where_clause)) {
                    $where_clause = " WHERE status = :status";
                } else {
                    $where_clause .= " AND status = :status";
                }
                $params[':status'] = $_GET['status'];
            }
            
            // Search by username or email
            if (isset($_GET['search'])) {
                $search = '%' . $_GET['search'] . '%';
                if (empty($where_clause)) {
                    $where_clause = " WHERE (username LIKE :search OR email LIKE :search)";
                } else {
                    $where_clause .= " AND (username LIKE :search OR email LIKE :search)";
                }
                $params[':search'] = $search;
            }
            
            // Get total count for pagination
            $count_query = "SELECT COUNT(*) as total FROM users" . $where_clause;
            $count_stmt = $this->db->prepare($count_query);
            
            foreach ($params as $key => $value) {
                $count_stmt->bindValue($key, $value);
            }
            
            $count_stmt->execute();
            $total = $count_stmt->fetch()['total'];
            
            // Query untuk mendapatkan daftar pengguna
            $query = "SELECT id, role, username, email, status, first_name, last_name, 
                     company, last_login, created_at, updated_at
                     FROM users" . 
                     $where_clause . 
                     " ORDER BY created_at DESC
                     LIMIT :offset, :limit";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            $users = $stmt->fetchAll();
            
            // Response
            header("HTTP/1.1 200 OK");
            echo json_encode([
                'data' => $users,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            
        } catch (PDOException $e) {
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(['error' => 'Failed to retrieve users: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Get user by ID
     * 
     * @param int $id User ID
     * @param array $current_user Current authenticated user
     * @return void
     */
    private function getUser($id, $current_user) {
        try {
            // Admin dapat mengakses semua pengguna
            // Pengguna lain hanya dapat mengakses data mereka sendiri
            if ($current_user['role'] !== 'admin' && $current_user['id'] != $id) {
                header("HTTP/1.1 403 Forbidden");
                echo json_encode(['error' => 'Permission denied']);
                return;
            }
            
            // Query untuk mendapatkan detail pengguna
            $query = "SELECT id, role, username, email, status, first_name, last_name, 
                     phone, address, company, account_balance, last_login, 
                     created_at, updated_at
                     FROM users
                     WHERE id = :id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                header("HTTP/1.1 404 Not Found");
                echo json_encode(['error' => 'User not found']);
                return;
            }
            
            $user = $stmt->fetch();
            
            // Tambahan detail berdasarkan role
            switch ($user['role']) {
                case 'publisher':
                    // Dapatkan detail publisher
                    $publisher_query = "SELECT id, website_url, website_name, website_category, 
                                      status, verification_status
                                      FROM publishers
                                      WHERE user_id = :user_id";
                    
                    $publisher_stmt = $this->db->prepare($publisher_query);
                    $publisher_stmt->bindParam(':user_id', $id);
                    $publisher_stmt->execute();
                    
                    if ($publisher_stmt->rowCount() > 0) {
                        $user['publisher_details'] = $publisher_stmt->fetch();
                        
                        // Count ad zones
                        $zones_query = "SELECT COUNT(*) as total_zones
                                       FROM ad_zones
                                       WHERE publisher_id = :publisher_id";
                        
                        $zones_stmt = $this->db->prepare($zones_query);
                        $zones_stmt->bindParam(':publisher_id', $user['publisher_details']['id']);
                        $zones_stmt->execute();
                        
                        $user['publisher_details']['total_zones'] = $zones_stmt->fetch()['total_zones'];
                    }
                    break;
                    
                case 'advertiser':
                    // Dapatkan detail advertiser
                    $advertiser_query = "SELECT id, company_name, contact_person, industry, status
                                       FROM advertisers
                                       WHERE user_id = :user_id";
                    
                    $advertiser_stmt = $this->db->prepare($advertiser_query);
                    $advertiser_stmt->bindParam(':user_id', $id);
                    $advertiser_stmt->execute();
                    
                    if ($advertiser_stmt->rowCount() > 0) {
                        $user['advertiser_details'] = $advertiser_stmt->fetch();
                        
                        // Count campaigns
                        $campaigns_query = "SELECT COUNT(*) as total_campaigns
                                          FROM campaigns
                                          WHERE advertiser_id = :advertiser_id";
                        
                        $campaigns_stmt = $this->db->prepare($campaigns_query);
                        $campaigns_stmt->bindParam(':advertiser_id', $user['advertiser_details']['id']);
                        $campaigns_stmt->execute();
                        
                        $user['advertiser_details']['total_campaigns'] = $campaigns_stmt->fetch()['total_campaigns'];
                    }
                    break;
            }
            
            // Response
            header("HTTP/1.1 200 OK");
            echo json_encode([
                'data' => $user
            ]);
            
        } catch (PDOException $e) {
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(['error' => 'Failed to retrieve user details: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Create new user (admin only)
     * 
     * @param array $data User data
     * @param array $current_user Current authenticated user
     * @return void
     */
    private function createUser($data, $current_user) {
        try {
            // Cek jika pengguna adalah admin
            if ($current_user['role'] !== 'admin') {
                header("HTTP/1.1 403 Forbidden");
                echo json_encode(['error' => 'Only admin can create users']);
                return;
            }
            
            // Validasi input
            $required_fields = ['username', 'password', 'email', 'role'];
            foreach ($required_fields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    header("HTTP/1.1 400 Bad Request");
                    echo json_encode(['error' => 'Missing required field: ' . $field]);
                    return;
                }
            }
            
            // Validasi role
            $allowed_roles = ['admin', 'publisher', 'advertiser'];
            if (!in_array($data['role'], $allowed_roles)) {
                header("HTTP/1.1 400 Bad Request");
                echo json_encode(['error' => 'Invalid role. Allowed roles: admin, publisher, advertiser']);
                return;
            }
            
            // Validasi email format
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                header("HTTP/1.1 400 Bad Request");
                echo json_encode(['error' => 'Invalid email format']);
                return;
            }
            
            // Validasi password strength
            if (strlen($data['password']) < 8) {
                header("HTTP/1.1 400 Bad Request");
                echo json_encode(['error' => 'Password must be at least 8 characters long']);
                return;
            }
            
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
                          (role, username, password, email, first_name, last_name, 
                          phone, address, company, status) 
                          VALUES 
                          (:role, :username, :password, :email, :first_name, :last_name, 
                          :phone, :address, :company, :status)";
            
            $user_stmt = $this->db->prepare($user_query);
            $user_stmt->bindParam(':role', $data['role']);
            $user_stmt->bindParam(':username', $data['username']);
            $user_stmt->bindParam(':password', $hashed_password);
            $user_stmt->bindParam(':email', $data['email']);
            $user_stmt->bindParam(':first_name', $data['first_name'] ?? null);
            $user_stmt->bindParam(':last_name', $data['last_name'] ?? null);
            $user_stmt->bindParam(':phone', $data['phone'] ?? null);
            $user_stmt->bindParam(':address', $data['address'] ?? null);
            $user_stmt->bindParam(':company', $data['company'] ?? null);
            $status = isset($data['status']) ? $data['status'] : 'active';
            $user_stmt->bindParam(':status', $status);
            
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
                                  (:user_id, :website_url, :website_name, :website_category, :website_description, :status)";
                
                $publisher_stmt = $this->db->prepare($publisher_query);
                $publisher_stmt->bindParam(':user_id', $user_id);
                $publisher_stmt->bindParam(':website_url', $data['website_url']);
                $publisher_stmt->bindParam(':website_name', $data['website_name']);
                $publisher_stmt->bindParam(':website_category', $data['website_category'] ?? null);
                $publisher_stmt->bindParam(':website_description', $data['website_description'] ?? null);
                $publisher_status = isset($data['publisher_status']) ? $data['publisher_status'] : 'active';
                $publisher_stmt->bindParam(':status', $publisher_status);
                
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
                                   (:user_id, :company_name, :contact_person, :industry, :status)";
                
                $advertiser_stmt = $this->db->prepare($advertiser_query);
                $advertiser_stmt->bindParam(':user_id', $user_id);
                $advertiser_stmt->bindParam(':company_name', $data['company_name']);
                $advertiser_stmt->bindParam(':contact_person', $data['contact_person'] ?? null);
                $advertiser_stmt->bindParam(':industry', $data['industry'] ?? null);
                $advertiser_status = isset($data['advertiser_status']) ? $data['advertiser_status'] : 'active';
                $advertiser_stmt->bindParam(':status', $advertiser_status);
                
                $advertiser_stmt->execute();
            }
            
            // Commit transaction
            $this->db->commit();
            
            // Response
            header("HTTP/1.1 201 Created");
            echo json_encode([
                'message' => 'User created successfully',
                'user_id' => $user_id,
                'role' => $data['role'],
                'status' => $status
            ]);
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $this->db->rollBack();
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(['error' => 'Failed to create user: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Update user
     * 
     * @param int $id User ID
     * @param array $data User data
     * @param array $current_user Current authenticated user
     * @return void
     */
    private function updateUser($id, $data, $current_user) {
        try {
            // Admin dapat mengakses semua pengguna
            // Pengguna lain hanya dapat mengakses data mereka sendiri
            if ($current_user['role'] !== 'admin' && $current_user['id'] != $id) {
                header("HTTP/1.1 403 Forbidden");
                echo json_encode(['error' => 'Permission denied']);
                return;
            }
            
            // Cek apakah user ada
            $check_query = "SELECT * FROM users WHERE id = :id";
            $check_stmt = $this->db->prepare($check_query);
            $check_stmt->bindParam(':id', $id);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() === 0) {
                header("HTTP/1.1 404 Not Found");
                echo json_encode(['error' => 'User not found']);
                return;
            }
            
            $user = $check_stmt->fetch();
            
            // Non-admin users can't change their role
            if ($current_user['role'] !== 'admin' && isset($data['role']) && $data['role'] !== $user['role']) {
                header("HTTP/1.1 403 Forbidden");
                echo json_encode(['error' => 'You cannot change your role']);
                return;
            }
            
            // Validasi email format jika disediakan
            if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                header("HTTP/1.1 400 Bad Request");
                echo json_encode(['error' => 'Invalid email format']);
                return;
            }
            
            // Cek username/email jika diubah
            if ((isset($data['username']) && $data['username'] !== $user['username']) ||
                (isset($data['email']) && $data['email'] !== $user['email'])) {
                $check_duplicates_query = "SELECT id FROM users WHERE (username = :username OR email = :email) AND id != :id";
                $check_duplicates_stmt = $this->db->prepare($check_duplicates_query);
                $check_duplicates_stmt->bindParam(':id', $id);
                $check_duplicates_stmt->bindParam(':username', $data['username'] ?? $user['username']);
                $check_duplicates_stmt->bindParam(':email', $data['email'] ?? $user['email']);
                $check_duplicates_stmt->execute();
                
                if ($check_duplicates_stmt->rowCount() > 0) {
                    header("HTTP/1.1 409 Conflict");
                    echo json_encode(['error' => 'Username or email already exists']);
                    return;
                }
            }
            
            // Build update query dynamically
            $updateFields = [];
            $params = [':id' => $id];
            
            // Fields that can be updated
            $allowedFields = [
                'username', 'email', 'first_name', 'last_name', 
                'phone', 'address', 'company'
            ];
            
            // Admin only fields
            $adminFields = ['role', 'status', 'account_balance'];
            
            if ($current_user['role'] === 'admin') {
                $allowedFields = array_merge($allowedFields, $adminFields);
            }
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "$field = :$field";
                    $params[":$field"] = $data[$field];
                }
            }
            
            // Handle password update separately
            if (isset($data['password']) && !empty($data['password'])) {
                // Validasi password strength
                if (strlen($data['password']) < 8) {
                    header("HTTP/1.1 400 Bad Request");
                    echo json_encode(['error' => 'Password must be at least 8 characters long']);
                    return;
                }
                
                $updateFields[] = "password = :password";
                $params[':password'] = password_hash($data['password'], PASSWORD_BCRYPT);
            }
            
            // If nothing to update
            if (empty($updateFields)) {
                header("HTTP/1.1 400 Bad Request");
                echo json_encode(['error' => 'No fields to update']);
                return;
            }
            
            // Begin transaction
            $this->db->beginTransaction();
            
            // Update user
            $query = "UPDATE users SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            
            // Update role-specific records if needed
            if ($user['role'] === 'publisher' && isset($data['publisher_details'])) {
                $publisher_query = "SELECT id FROM publishers WHERE user_id = :user_id";
                $publisher_stmt = $this->db->prepare($publisher_query);
                $publisher_stmt->bindParam(':user_id', $id);
                $publisher_stmt->execute();
                
                if ($publisher_stmt->rowCount() > 0) {
                    $publisher_id = $publisher_stmt->fetch()['id'];
                    $publisher_fields = [];
                    $publisher_params = [':publisher_id' => $publisher_id];
                    
                    $allowed_publisher_fields = [
                        'website_url', 'website_name', 'website_category', 
                        'website_description', 'payout_method', 'payout_details'
                    ];
                    
                    // Admin can update status
                    if ($current_user['role'] === 'admin') {
                        $allowed_publisher_fields[] = 'status';
                        $allowed_publisher_fields[] = 'verification_status';
                    }
                    
                    foreach ($allowed_publisher_fields as $field) {
                        if (isset($data['publisher_details'][$field])) {
                            $publisher_fields[] = "$field = :$field";
                            $publisher_params[":$field"] = $data['publisher_details'][$field];
                        }
                    }
                    
                    if (!empty($publisher_fields)) {
                        $publisher_update_query = "UPDATE publishers SET " . implode(', ', $publisher_fields) . ", updated_at = NOW() WHERE id = :publisher_id";
                        $publisher_update_stmt = $this->db->prepare($publisher_update_query);
                        
                        foreach ($publisher_params as $key => $value) {
                            $publisher_update_stmt->bindValue($key, $value);
                        }
                        
                        $publisher_update_stmt->execute();
                    }
                }
            }
            elseif ($user['role'] === 'advertiser' && isset($data['advertiser_details'])) {
                $advertiser_query = "SELECT id FROM advertisers WHERE user_id = :user_id";
                $advertiser_stmt = $this->db->prepare($advertiser_query);
                $advertiser_stmt->bindParam(':user_id', $id);
                $advertiser_stmt->execute();
                
                if ($advertiser_stmt->rowCount() > 0) {
                    $advertiser_id = $advertiser_stmt->fetch()['id'];
                    $advertiser_fields = [];
                    $advertiser_params = [':advertiser_id' => $advertiser_id];
                    
                    $allowed_advertiser_fields = [
                        'company_name', 'contact_person', 'industry', 
                        'billing_address', 'payment_method', 'payment_details'
                    ];
                    
                    // Admin can update status and budget
                    if ($current_user['role'] === 'admin') {
                        $allowed_advertiser_fields[] = 'status';
                        $allowed_advertiser_fields[] = 'budget';
                    }
                    
                    foreach ($allowed_advertiser_fields as $field) {
                        if (isset($data['advertiser_details'][$field])) {
                            $advertiser_fields[] = "$field = :$field";
                            $advertiser_params[":$field"] = $data['advertiser_details'][$field];
                        }
                    }
                    
                    if (!empty($advertiser_fields)) {
                        $advertiser_update_query = "UPDATE advertisers SET " . implode(', ', $advertiser_fields) . ", updated_at = NOW() WHERE id = :advertiser_id";
                        $advertiser_update_stmt = $this->db->prepare($advertiser_update_query);
                        
                        foreach ($advertiser_params as $key => $value) {
                            $advertiser_update_stmt->bindValue($key, $value);
                        }
                        
                        $advertiser_update_stmt->execute();
                    }
                }
            }
            
            // Commit transaction
            $this->db->commit();
            
            // Response
            header("HTTP/1.1 200 OK");
            echo json_encode([
                'message' => 'User updated successfully',
                'user_id' => $id
            ]);
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $this->db->rollBack();
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(['error' => 'Failed to update user: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Delete user (admin only)
     * 
     * @param int $id User ID
     * @param array $current_user Current authenticated user
     * @return void
     */
    private function deleteUser($id, $current_user) {
        try {
            // Only admin can delete users
            if ($current_user['role'] !== 'admin') {
                header("HTTP/1.1 403 Forbidden");
                echo json_encode(['error' => 'Only admin can delete users']);
                return;
            }
            
            // Admin can't delete themselves
            if ($current_user['id'] == $id) {
                header("HTTP/1.1 400 Bad Request");
                echo json_encode(['error' => 'You cannot delete your own account']);
                return;
            }
            
            // Check if user exists
            $check_query = "SELECT role FROM users WHERE id = :id";
            $check_stmt = $this->db->prepare($check_query);
            $check_stmt->bindParam(':id', $id);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() === 0) {
                header("HTTP/1.1 404 Not Found");
                echo json_encode(['error' => 'User not found']);
                return;
            }
            
            $role = $check_stmt->fetch()['role'];
            
            // Begin transaction
            $this->db->beginTransaction();
            
            // Delete role-specific records first
            if ($role === 'publisher') {
                // Delete ad zones
                $zones_query = "DELETE az FROM ad_zones az 
                              JOIN publishers p ON az.publisher_id = p.id 
                              WHERE p.user_id = :user_id";
                $zones_stmt = $this->db->prepare($zones_query);
                $zones_stmt->bindParam(':user_id', $id);
                $zones_stmt->execute();
                
                // Delete publisher record
                $publisher_query = "DELETE FROM publishers WHERE user_id = :user_id";
                $publisher_stmt = $this->db->prepare($publisher_query);
                $publisher_stmt->bindParam(':user_id', $id);
                $publisher_stmt->execute();
            }
            elseif ($role === 'advertiser') {
                // Delete ads
                $ads_query = "DELETE a FROM ads a 
                            JOIN campaigns c ON a.campaign_id = c.id 
                            JOIN advertisers adv ON c.advertiser_id = adv.id 
                            WHERE adv.user_id = :user_id";
                $ads_stmt = $this->db->prepare($ads_query);
                $ads_stmt->bindParam(':user_id', $id);
                $ads_stmt->execute();
                
                // Delete campaigns
                $campaigns_query = "DELETE c FROM campaigns c 
                                  JOIN advertisers adv ON c.advertiser_id = adv.id 
                                  WHERE adv.user_id = :user_id";
                $campaigns_stmt = $this->db->prepare($campaigns_query);
                $campaigns_stmt->bindParam(':user_id', $id);
                $campaigns_stmt->execute();
                
                // Delete advertiser record
                $advertiser_query = "DELETE FROM advertisers WHERE user_id = :user_id";
                $advertiser_stmt = $this->db->prepare($advertiser_query);
                $advertiser_stmt->bindParam(':user_id', $id);
                $advertiser_stmt->execute();
            }
            
            // Delete API keys
            $api_keys_query = "DELETE FROM api_keys WHERE user_id = :user_id";
            $api_keys_stmt = $this->db->prepare($api_keys_query);
            $api_keys_stmt->bindParam(':user_id', $id);
            $api_keys_stmt->execute();
            
            // Delete user
            $query = "DELETE FROM users WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // Commit transaction
            $this->db->commit();
            
            // Response
            header("HTTP/1.1 200 OK");
            echo json_encode([
                'message' => 'User deleted successfully',
                'user_id' => $id
            ]);
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $this->db->rollBack();
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(['error' => 'Failed to delete user: ' . $e->getMessage()]);
        }
    }
}
