<?php
/**
 * Authentication System (Fixed)
 * AdServer Platform User Authentication and Authorization
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Login user
     */
    public function login($email, $password, $userType = null) {
        try {
            // Check login attempts
            if ($this->isUserLocked($email)) {
                return ['success' => false, 'message' => 'Account temporarily locked due to too many failed attempts'];
            }
            
            $sql = "SELECT * FROM users WHERE email = ? AND status = ?";
            $params = [$email, USER_STATUS_ACTIVE];
            
            if ($userType) {
                $sql .= " AND user_type = ?";
                $params[] = $userType;
            }
            
            $user = $this->db->fetch($sql, $params);
            
            if (!$user || !verifyPassword($password, $user['password'])) {
                $this->recordFailedAttempt($email);
                return ['success' => false, 'message' => 'Invalid email or password'];
            }
            
            // Clear failed attempts
            $this->clearFailedAttempts($user['id']);
            
            // Update last login
            $this->db->update('users', 
                ['last_login' => date('Y-m-d H:i:s')], 
                'id = ?', 
                [$user['id']]
            );
            
            // Create session
            $this->createSession($user);
            
            return ['success' => true, 'user' => $user];
            
        } catch (Exception $e) {
            logMessage("Login error: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'message' => 'Login failed'];
        }
    }
    
    /**
     * Register new user
     */
    public function register($data) {
        try {
            // Validate required fields
            $required = ['username', 'email', 'password', 'first_name', 'last_name', 'user_type'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => "Field {$field} is required"];
                }
            }
            
            // Validate email
            if (!validateEmail($data['email'])) {
                return ['success' => false, 'message' => 'Invalid email address'];
            }
            
            // Check if user exists
            $existing = $this->db->fetch(
                "SELECT id FROM users WHERE email = ? OR username = ?",
                [$data['email'], $data['username']]
            );
            
            if ($existing) {
                return ['success' => false, 'message' => 'User already exists'];
            }
            
            // Hash password
            $data['password'] = hashPassword($data['password']);
            $data['api_key'] = generateApiKey();
            $data['created_at'] = date('Y-m-d H:i:s');
            
            // Insert user
            $stmt = $this->db->insert('users', $data);
            $userId = $this->db->lastInsertId();
            
            // Log activity
            $this->logActivity($userId, 'user_registered');
            
            return ['success' => true, 'user_id' => $userId];
            
        } catch (Exception $e) {
            logMessage("Registration error: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'message' => 'Registration failed'];
        }
    }
    
    /**
     * Logout user
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->logActivity($_SESSION['user_id'], 'user_logout');
            
            // Delete session from database
            if (isset($_SESSION['session_token'])) {
                $this->db->delete('user_sessions', 'session_token = ?', [$_SESSION['session_token']]);
            }
        }
        
        session_destroy();
        session_start();
    }
    
    /**
     * Check if user is authenticated
     */
    public function isAuthenticated($userType = null) {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
            return false;
        }
        
        // Verify session in database
        $session = $this->db->fetch(
            "SELECT s.*, u.user_type, u.status FROM user_sessions s 
             JOIN users u ON s.user_id = u.id 
             WHERE s.session_token = ? AND s.expires_at > NOW()",
            [$_SESSION['session_token']]
        );
        
        if (!$session) {
            return false;
        }
        
        if ($session['status'] !== USER_STATUS_ACTIVE) {
            return false;
        }
        
        if ($userType && $session['user_type'] !== $userType) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get current user
     */
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return $this->db->fetch(
            "SELECT * FROM users WHERE id = ?",
            [$_SESSION['user_id']]
        );
    }
    
    /**
     * Require authentication
     */
    public function requireAuth($userType = null, $redirectUrl = null) {
        if (!$this->isAuthenticated($userType)) {
            if (!$redirectUrl) {
                $redirectUrl = $this->getLoginUrl($userType);
            }
            redirect($redirectUrl);
        }
    }
    
    /**
     * Check permission
     */
    public function hasPermission($permission) {
        $user = $this->getCurrentUser();
        if (!$user) {
            return false;
        }
        
        // Admin has all permissions
        if ($user['user_type'] === USER_TYPE_ADMIN) {
            return true;
        }
        
        // Add more granular permissions as needed
        return false;
    }
    
    /**
     * Create user session
     */
    private function createSession($user) {
        $sessionToken = generateToken(64);
        $expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
        
        // Store session in database
        $this->db->insert('user_sessions', [
            'user_id' => $user['id'],
            'session_token' => $sessionToken,
            'ip_address' => getClientIp(),
            'user_agent' => getUserAgent(),
            'expires_at' => $expiresAt
        ]);
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['session_token'] = $sessionToken;
        
        // Clean old sessions
        $this->cleanOldSessions($user['id']);
    }
    
    /**
     * Clean old sessions
     */
    private function cleanOldSessions($userId) {
        $this->db->delete(
            'user_sessions',
            'user_id = ? AND expires_at < NOW()',
            [$userId]
        );
    }
    
    /**
     * Record failed login attempt (FIXED)
     */
    private function recordFailedAttempt($email) {
        $ip = getClientIp();
        
        // Get current user data first
        $user = $this->db->fetch("SELECT id, login_attempts FROM users WHERE email = ?", [$email]);
        
        if ($user) {
            $newAttempts = $user['login_attempts'] + 1;
            $lockedUntil = null;
            
            // If max attempts reached, set locked_until
            if ($newAttempts >= MAX_LOGIN_ATTEMPTS) {
                $lockedUntil = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
            }
            
            // Update with proper parameter binding
            $this->db->update(
                'users',
                [
                    'login_attempts' => $newAttempts,
                    'locked_until' => $lockedUntil
                ],
                'id = ?',
                [$user['id']]
            );
        }
    }
    
    /**
     * Clear failed attempts
     */
    private function clearFailedAttempts($userId) {
        $this->db->update('users', 
            ['login_attempts' => 0, 'locked_until' => null], 
            'id = ?', 
            [$userId]
        );
    }
    
    /**
     * Check if user is locked
     */
    private function isUserLocked($email) {
        $user = $this->db->fetch(
            "SELECT login_attempts, locked_until FROM users WHERE email = ?",
            [$email]
        );
        
        if (!$user) {
            return false;
        }
        
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Log user activity
     */
    private function logActivity($userId, $action, $details = null) {
        $this->db->insert('activity_logs', [
            'user_id' => $userId,
            'action' => $action,
            'ip_address' => getClientIp(),
            'user_agent' => getUserAgent(),
            'details' => $details ? json_encode($details) : null
        ]);
    }
    
    /**
     * Get login URL based on user type
     */
    private function getLoginUrl($userType) {
        switch ($userType) {
            case USER_TYPE_ADMIN:
                return '/admin/login.php';
            case USER_TYPE_PUBLISHER:
                return '/publisher/login.php';
            case USER_TYPE_ADVERTISER:
                return '/advertiser/login.php';
            default:
                return '/admin/login.php';
        }
    }
    
    /**
     * Change password
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            $user = $this->db->fetch("SELECT password FROM users WHERE id = ?", [$userId]);
            
            if (!$user || !verifyPassword($currentPassword, $user['password'])) {
                return ['success' => false, 'message' => 'Current password is incorrect'];
            }
            
            $this->db->update('users', 
                ['password' => hashPassword($newPassword)], 
                'id = ?', 
                [$userId]
            );
            
            $this->logActivity($userId, 'password_changed');
            
            return ['success' => true, 'message' => 'Password changed successfully'];
            
        } catch (Exception $e) {
            logMessage("Password change error: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'message' => 'Failed to change password'];
        }
    }
    
    /**
     * Reset password
     */
    public function resetPassword($email) {
        try {
            $user = $this->db->fetch("SELECT id FROM users WHERE email = ?", [$email]);
            
            if (!$user) {
                return ['success' => false, 'message' => 'User not found'];
            }
            
            $token = generateToken(32);
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
            
            // For now, just return success (implement email sending later)
            return ['success' => true, 'message' => 'Password reset functionality will be implemented'];
            
        } catch (Exception $e) {
            logMessage("Password reset error: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'message' => 'Failed to send password reset'];
        }
    }
}
?>
