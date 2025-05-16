<?php
/**
 * Authentication Middleware
 * 
 * This file contains the middleware for JWT authentication verification.
 * It validates the JWT token from the Authorization header and makes sure
 * the user has appropriate permissions to access the resources.
 * 
 * @package RTB-AdServer
 * @subpackage API
 */

// Load JWT handler
require_once __DIR__ . '/../../config/jwt.php';

/**
 * Middleware class for authentication
 */
class AuthMiddleware {
    /**
     * @var JWTHandler JWT handler instance
     */
    private $jwt;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->jwt = new JWTHandler();
    }
    
    /**
     * Verify JWT token from Authorization header
     * 
     * @param array $protected_endpoints Array of protected endpoints with required roles
     * @param string $resource Current resource (endpoint)
     * @param string $method HTTP method
     * @return array|false User data if authenticated, false otherwise
     */
    public function verifyToken($protected_endpoints, $resource, $method) {
        // Check if endpoint is protected
        if (!isset($protected_endpoints[$resource][$method])) {
            return true; // Not protected, allow access
        }
        
        // Get token from Authorization header
        $token = $this->extractToken();
        
        if ($token === null) {
            header("HTTP/1.1 401 Unauthorized");
            echo json_encode(['error' => 'Access token required']);
            return false;
        }
        
        // Validate token
        $user = $this->jwt->validateToken($token);
        if (!$user) {
            header("HTTP/1.1 401 Unauthorized");
            echo json_encode(['error' => 'Invalid or expired token']);
            return false;
        }
        
        // Check user role authorization
        $allowed_roles = $protected_endpoints[$resource][$method];
        if (!in_array($user['role'], $allowed_roles)) {
            header("HTTP/1.1 403 Forbidden");
            echo json_encode(['error' => 'Permission denied']);
            return false;
        }
        
        return $user; // Return user data
    }
    
    /**
     * Extract token from Authorization header
     * 
     * @return string|null Token if found, null otherwise
     */
    private function extractToken() {
        $auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
        
        if (!empty($auth_header) && preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Check if user has admin privileges
     * 
     * @param array $user User data
     * @return bool True if admin, false otherwise
     */
    public function isAdmin($user) {
        return isset($user['role']) && $user['role'] === 'admin';
    }
    
    /**
     * Check if user can access a specific resource
     * 
     * @param array $user User data
     * @param int $resource_id Resource ID
     * @param string $resource_type Resource type (e.g. 'user', 'publisher', 'advertiser')
     * @param PDO $db Database connection
     * @return bool True if authorized, false otherwise
     */
    public function canAccessResource($user, $resource_id, $resource_type, $db) {
        // Admin can access all resources
        if ($this->isAdmin($user)) {
            return true;
        }
        
        switch ($resource_type) {
            case 'user':
                // Users can only access their own data
                return $user['id'] == $resource_id;
                
            case 'publisher':
                // Check if user is the owner of this publisher
                $query = "SELECT user_id FROM publishers WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $resource_id);
                $stmt->execute();
                
                if ($stmt->rowCount() === 0) {
                    return false; // Resource not found
                }
                
                $publisher = $stmt->fetch();
                return $user['id'] == $publisher['user_id'];
                
            case 'advertiser':
                // Check if user is the owner of this advertiser
                $query = "SELECT user_id FROM advertisers WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $resource_id);
                $stmt->execute();
                
                if ($stmt->rowCount() === 0) {
                    return false; // Resource not found
                }
                
                $advertiser = $stmt->fetch();
                return $user['id'] == $advertiser['user_id'];
                
            case 'campaign':
                // Check if user is the owner of this campaign
                $query = "SELECT a.user_id 
                         FROM campaigns c 
                         JOIN advertisers a ON c.advertiser_id = a.id 
                         WHERE c.id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $resource_id);
                $stmt->execute();
                
                if ($stmt->rowCount() === 0) {
                    return false; // Resource not found
                }
                
                $campaign = $stmt->fetch();
                return $user['id'] == $campaign['user_id'];
                
            case 'ad_zone':
                // Check if user is the owner of this ad zone
                $query = "SELECT p.user_id 
                         FROM ad_zones z 
                         JOIN publishers p ON z.publisher_id = p.id 
                         WHERE z.id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $resource_id);
                $stmt->execute();
                
                if ($stmt->rowCount() === 0) {
                    return false; // Resource not found
                }
                
                $ad_zone = $stmt->fetch();
                return $user['id'] == $ad_zone['user_id'];
                
            default:
                return false; // Unknown resource type
        }
    }
}
