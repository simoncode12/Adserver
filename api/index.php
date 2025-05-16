<?php
// api/index.php - Entry point utama API

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("HTTP/1.1 200 OK");
    exit;
}

// Load configuration files
require_once '../config/database.php';
require_once '../config/jwt.php';

// Get request method and URI path
$request_method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode('/', trim($uri, '/'));

// Expecting URI format: api/[resource]/[id]
// Check if API call
if (!isset($uri[0]) || $uri[0] !== 'api') {
    header("HTTP/1.1 404 Not Found");
    echo json_encode(['error' => 'API endpoint not found']);
    exit;
}

// Get the resource (endpoint)
$resource = isset($uri[1]) ? $uri[1] : null;
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

// JWT handler
$jwt = new JWTHandler();

// Check auth for protected endpoints
$protected_endpoints = [
    'publishers' => ['GET' => ['admin']],
    'advertisers' => ['GET' => ['admin']],
    'campaigns' => ['POST' => ['advertiser', 'admin'], 'GET' => ['advertiser', 'admin']],
    'ad_zones' => ['GET' => ['publisher', 'admin']],
    'statistics' => ['GET' => ['publisher', 'advertiser', 'admin']]
];

// Get token from Authorization header
$token = null;
$auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
if (!empty($auth_header) && preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
    $token = $matches[1];
}

// Validate token for protected endpoints
$current_user = null;
if (isset($protected_endpoints[$resource][$request_method])) {
    if ($token === null) {
        header("HTTP/1.1 401 Unauthorized");
        echo json_encode(['error' => 'Access token required']);
        exit;
    }
    
    $current_user = $jwt->validateToken($token);
    if (!$current_user) {
        header("HTTP/1.1 401 Unauthorized");
        echo json_encode(['error' => 'Invalid or expired token']);
        exit;
    }
    
    // Check user role authorization
    $allowed_roles = $protected_endpoints[$resource][$request_method];
    if (!in_array($current_user['role'], $allowed_roles)) {
        header("HTTP/1.1 403 Forbidden");
        echo json_encode(['error' => 'Permission denied']);
        exit;
    }
}

// Route API request to the appropriate handler
switch ($resource) {
    case 'register':
        require_once 'endpoints/register.php';
        $controller = new RegisterController($db);
        $controller->process($request_method, $data);
        break;
        
    case 'login':
        require_once 'endpoints/login.php';
        $controller = new LoginController($db, $jwt);
        $controller->process($request_method, $data);
        break;
        
    case 'publishers':
        require_once 'endpoints/publishers.php';
        $controller = new PublishersController($db);
        $controller->process($request_method, $id, $data, $current_user);
        break;
        
    case 'ad_zones':
        require_once 'endpoints/ad_zones.php';
        $controller = new AdZonesController($db);
        $controller->process($request_method, $id, $data, $current_user);
        break;
        
    case 'campaigns':
        require_once 'endpoints/campaigns.php';
        $controller = new CampaignsController($db);
        $controller->process($request_method, $id, $data, $current_user);
        break;
        
    case 'statistics':
        require_once 'endpoints/statistics.php';
        $controller = new StatisticsController($db);
        $controller->process($request_method, $data, $current_user);
        break;
        
    default:
        header("HTTP/1.1 404 Not Found");
        echo json_encode(['error' => 'Resource not found']);
        break;
}
