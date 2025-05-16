<?php
/**
 * API Router for User Endpoints
 * 
 * This file demonstrates how to integrate the UsersController with the main API router.
 * 
 * @package RTB-AdServer
 * @subpackage API
 */

// Define direct access constant
define('DIRECT_ACCESS', true);

// Load required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/middleware/auth.php';
require_once __DIR__ . '/endpoints/users.php';

// Set HTTP headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("HTTP/1.1 200 OK");
    exit;
}

// Get request method and URI path
$request_method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode('/', trim($uri, '/'));

// Expecting URI format: api/users/[id]
// Check if API call is for users
if (!isset($uri[0]) || $uri[0] !== 'api' || !isset($uri[1]) || $uri[1] !== 'users') {
    header("HTTP/1.1 404 Not Found");
    echo json_encode(['error' => 'API endpoint not found']);
    exit;
}

// Get the resource ID if provided
$id = isset($uri[2]) ? $uri[2] : null;

// Parse JSON input for POST and PUT requests
$data = null;
if ($request_method == 'POST' || $request_method == 'PUT') {
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);
    
    if ($data === null && $input) {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(['error' => 'Invalid JSON input']);
        exit;
    }
}

// Database connection
$database = new Database();
$db = $database->getConnection();

// Auth middleware
$auth = new AuthMiddleware();

// Define protected endpoints
$protected_endpoints = [
    'users' => [
        'GET' => ['admin', 'publisher', 'advertiser'], // Anyone logged in can access their own data
        'POST' => ['admin'], // Only admin can create users
        'PUT' => ['admin', 'publisher', 'advertiser'], // Anyone logged in can update their own data
        'DELETE' => ['admin'] // Only admin can delete users
    ]
];

// Authenticate request
$current_user = $auth->verifyToken($protected_endpoints, 'users', $request_method);

if ($current_user === false) {
    // Authentication failed, response already sent
    exit;
}

// Initialize controller
$controller = new UsersController($db);

// Process request
$controller->process($request_method, $id, $data, $current_user);
