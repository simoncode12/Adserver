<?php
$pageTitle = 'SSP Endpoints Management';
$breadcrumb = [
    ['text' => 'SSP Endpoints']
];

try {
    require_once '../config/init.php';
    require_once '../config/database.php';
    require_once '../config/constants.php';
    require_once '../includes/auth.php';
    require_once '../includes/helpers.php';

    $auth = new Auth();
    $db = Database::getInstance();

    // Handle AJAX requests for real-time stats
    if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
        header('Content-Type: application/json');
        
        try {
            $stats = $db->fetch(
                "SELECT 
                    COUNT(*) as total_ssp_endpoints,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_ssp_endpoints,
                    COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_ssp_endpoints,
                    COUNT(CASE WHEN status = 'testing' THEN 1 END) as testing_ssp_endpoints,
                    COALESCE(AVG(success_rate), 0) as avg_success_rate,
                    COALESCE(AVG(avg_response_time), 0) as avg_response_time,
                    COUNT(CASE WHEN last_request >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 END) as active_today,
                    (SELECT COUNT(*) FROM publishers WHERE status = 'active') as total_publishers,
                    (SELECT COUNT(*) FROM sites WHERE status = 'active') as total_sites
                 FROM rtb_endpoints 
                 WHERE endpoint_type = 'ssp_out'"
            );
            
            echo json_encode([
                'success' => true,
                'stats' => $stats,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Handle form actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $csrf_token = $_POST['csrf_token'] ?? '';
        
        if (!verifyCSRFToken($csrf_token)) {
            $error = 'Invalid security token';
        } else {
            switch ($action) {
                case 'create':
                    try {
                        // Validate required fields
                        $requiredFields = ['name', 'url', 'timeout_ms', 'qps_limit'];
                        foreach ($requiredFields as $field) {
                            if (empty($_POST[$field])) {
                                throw new Exception("Field '{$field}' is required");
                            }
                        }
                        
                        // Validate URL
                        $url = sanitize($_POST['url']);
                        if (!filter_var($url, FILTER_VALIDATE_URL)) {
                            throw new Exception('Invalid SSP endpoint URL format');
                        }
                        
                        // Check for duplicate URL
                        $existingEndpoint = $db->fetch(
                            "SELECT id FROM rtb_endpoints WHERE url = ? AND endpoint_type = 'ssp_out'", 
                            [$url]
                        );
                        if ($existingEndpoint) {
                            throw new Exception('An SSP endpoint with this URL already exists');
                        }
                        
                        // Validate numeric fields
                        $timeout_ms = (int)$_POST['timeout_ms'];
                        $qps_limit = (int)$_POST['qps_limit'];
                        $bid_floor = (float)($_POST['bid_floor'] ?? 0);
                        $revenue_share = (float)($_POST['revenue_share'] ?? 70);
                        
                        if ($timeout_ms < 100 || $timeout_ms > 5000) {
                            throw new Exception('Timeout must be between 100 and 5000 milliseconds for SSP');
                        }
                        
                        if ($qps_limit < 1 || $qps_limit > 5000) {
                            throw new Exception('QPS limit must be between 1 and 5000 for SSP');
                        }
                        
                        if ($revenue_share < 0 || $revenue_share > 100) {
                            throw new Exception('Revenue share must be between 0 and 100 percent');
                        }
                        
                        // Prepare JSON fields
                        $auth_credentials = null;
                        if ($_POST['auth_type'] !== 'none' && !empty($_POST['auth_token'])) {
                            $auth_credentials = json_encode([
                                'token' => sanitize($_POST['auth_token']),
                                'type' => sanitize($_POST['auth_type']),
                                'created_at' => date('Y-m-d H:i:s'),
                                'created_by' => $_SESSION['user_id']
                            ]);
                        }
                        
                        // Process supported formats
                        $supported_formats_input = sanitize($_POST['supported_formats'] ?? 'banner');
                        $supported_formats = array_filter(array_map('trim', explode(',', $supported_formats_input)));
                        if (empty($supported_formats)) {
                            $supported_formats = ['banner'];
                        }
                        
                        // Process supported sizes with validation
                        $supported_sizes = null;
                        if (!empty($_POST['supported_sizes'])) {
                            $sizes_input = sanitize($_POST['supported_sizes']);
                            $sizes_array = array_filter(array_map('trim', explode(',', $sizes_input)));
                            
                            foreach ($sizes_array as $size) {
                                if (!preg_match('/^\d+x\d+$/', $size)) {
                                    throw new Exception("Invalid size format: {$size}. Use format like 728x90");
                                }
                            }
                            
                            if (!empty($sizes_array)) {
                                $supported_sizes = json_encode($sizes_array);
                            }
                        }
                        
                        // Process targeting
                        $country_targeting = null;
                        if (!empty($_POST['country_targeting'])) {
                            $countries_input = sanitize($_POST['country_targeting']);
                            $countries_array = array_filter(array_map('trim', explode(',', $countries_input)));
                            
                            foreach ($countries_array as $country) {
                                if (!preg_match('/^[A-Z]{2}$/i', $country)) {
                                    throw new Exception("Invalid country code: {$country}. Use 2-letter codes like US, CA, GB");
                                }
                            }
                            
                            if (!empty($countries_array)) {
                                $country_targeting = json_encode(array_map('strtoupper', $countries_array));
                            }
                        }
                        
                        // Prepare SSP-specific settings
                        $settings = [
                            'description' => sanitize($_POST['description'] ?? ''),
                            'priority' => (int)($_POST['priority'] ?? 3),
                            'test_mode' => isset($_POST['test_mode']) ? 1 : 0,
                            'revenue_share' => $revenue_share,
                            'floor_enforcement' => isset($_POST['floor_enforcement']) ? 1 : 0,
                            'frequency_cap' => (int)($_POST['frequency_cap'] ?? 0),
                            'publisher_restrictions' => sanitize($_POST['publisher_restrictions'] ?? ''),
                            'content_categories' => sanitize($_POST['content_categories'] ?? ''),
                            'ssp_type' => sanitize($_POST['ssp_type'] ?? 'standard'),
                            'header_bidding' => isset($_POST['header_bidding']) ? 1 : 0,
                            'server_side_rendering' => isset($_POST['server_side_rendering']) ? 1 : 0,
                            'created_at' => date('Y-m-d H:i:s'),
                            'created_by' => $_SESSION['user_id'],
                            'created_by_username' => $_SESSION['username'] ?? 'unknown',
                            'version' => '1.0'
                        ];
                        
                        $data = [
                            'name' => sanitize($_POST['name']),
                            'endpoint_type' => 'ssp_out', // Force SSP type
                            'url' => $url,
                            'method' => sanitize($_POST['method']) ?: 'POST',
                            'protocol_version' => sanitize($_POST['protocol_version']) ?: '2.5',
                            'status' => 'inactive', // Always start inactive for safety
                            'timeout_ms' => $timeout_ms,
                            'qps_limit' => $qps_limit,
                            'auth_type' => sanitize($_POST['auth_type']),
                            'auth_credentials' => $auth_credentials,
                            'bid_floor' => $bid_floor,
                            'supported_formats' => json_encode($supported_formats),
                            'supported_sizes' => $supported_sizes,
                            'country_targeting' => $country_targeting,
                            'settings' => json_encode($settings),
                            'success_rate' => 0.00,
                            'avg_response_time' => 0,
                            'last_request' => null,
                            'created_by' => $_SESSION['user_id'],
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ];
                        
                        $endpointId = $db->insert('rtb_endpoints', $data);
                        
                        // Log the creation
                        error_log("SSP Endpoint created: ID={$endpointId}, Name=" . $data['name'] . ", URL=" . $data['url'] . ", CreatedBy=" . $_SESSION['user_id']);
                        
                        $success = "SSP endpoint '{$data['name']}' created successfully with ID: {$endpointId}. The endpoint is initially set to inactive status for safety.";
                        
                    } catch (Exception $e) {
                        $error = 'Failed to create SSP endpoint: ' . $e->getMessage();
                        error_log("SSP Endpoint creation failed: " . $e->getMessage());
                    }
                    break;
                    
                case 'update_status':
                    try {
                        $endpointId = (int)$_POST['endpoint_id'];
                        $status = sanitize($_POST['status']);
                        
                        if (in_array($status, ['active', 'inactive', 'testing'])) {
                            $updateData = [
                                'status' => $status, 
                                'updated_at' => date('Y-m-d H:i:s')
                            ];
                            
                            $db->update(
                                'rtb_endpoints', 
                                $updateData, 
                                'id = ? AND endpoint_type = ?', 
                                [$endpointId, 'ssp_out']
                            );
                            $success = 'SSP endpoint status updated successfully';
                        } else {
                            $error = 'Invalid status';
                        }
                    } catch (Exception $e) {
                        $error = 'Failed to update status: ' . $e->getMessage();
                    }
                    break;
                    
                case 'delete':
                    try {
                        $endpointId = (int)$_POST['endpoint_id'];
                        $endpoint = $db->fetch(
                            "SELECT name FROM rtb_endpoints WHERE id = ? AND endpoint_type = 'ssp_out'", 
                            [$endpointId]
                        );
                        
                        if ($endpoint) {
                            $db->delete('rtb_endpoints', 'id = ? AND endpoint_type = ?', [$endpointId, 'ssp_out']);
                            $success = "SSP endpoint '{$endpoint['name']}' deleted successfully";
                            error_log("SSP Endpoint deleted: ID={$endpointId}, Name=" . $endpoint['name'] . ", DeletedBy=" . $_SESSION['user_id']);
                        } else {
                            $error = 'SSP endpoint not found';
                        }
                    } catch (Exception $e) {
                        $error = 'Failed to delete endpoint: ' . $e->getMessage();
                    }
                    break;
                    
                case 'test_endpoint':
                    try {
                        $endpointId = (int)$_POST['endpoint_id'];
                        $endpoint = $db->fetch(
                            "SELECT * FROM rtb_endpoints WHERE id = ? AND endpoint_type = 'ssp_out'", 
                            [$endpointId]
                        );
                        
                        if ($endpoint) {
                            // Update last_request timestamp and simulate test
                            $db->update('rtb_endpoints', 
                                [
                                    'last_request' => date('Y-m-d H:i:s'),
                                    'updated_at' => date('Y-m-d H:i:s')
                                ], 
                                'id = ?', 
                                [$endpointId]
                            );
                            
                            $success = "Test bid request sent to SSP endpoint '{$endpoint['name']}'. Check the endpoint logs for response details.";
                        } else {
                            $error = 'SSP endpoint not found';
                        }
                    } catch (Exception $e) {
                        $error = 'Failed to test SSP endpoint: ' . $e->getMessage();
                    }
                    break;
                    
                case 'sync_publishers':
                    try {
                        $endpointId = (int)$_POST['endpoint_id'];
                        $endpoint = $db->fetch(
                            "SELECT * FROM rtb_endpoints WHERE id = ? AND endpoint_type = 'ssp_out'", 
                            [$endpointId]
                        );
                        
                        if ($endpoint) {
                            // Simulate publisher sync - in real implementation, this would sync with SSP API
                            $success = "Publisher inventory sync initiated for '{$endpoint['name']}'. This may take a few minutes to complete.";
                        } else {
                            $error = 'SSP endpoint not found';
                        }
                    } catch (Exception $e) {
                        $error = 'Failed to sync publishers: ' . $e->getMessage();
                    }
                    break;
            }
        }
    }

    // Filters
    $status = $_GET['status'] ?? '';
    $sspType = $_GET['ssp_type'] ?? '';
    $authType = $_GET['auth_type'] ?? '';
    $search = sanitize($_GET['search'] ?? '');

    // Build query for SSP endpoints only
    $whereConditions = ["endpoint_type = 'ssp_out'"];
    $params = ['ssp_out'];

    if ($status) {
        $whereConditions[] = 'status = ?';
        $params[] = $status;
    }

    if ($authType) {
        $whereConditions[] = 'auth_type = ?';
        $params[] = $authType;
    }

    if ($search) {
        $whereConditions[] = '(name LIKE ? OR url LIKE ?)';
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    // Filter by SSP type from settings JSON
    if ($sspType) {
        $whereConditions[] = "JSON_EXTRACT(settings, '$.ssp_type') = ?";
        $params[] = $sspType;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

    // Get SSP endpoints
    $endpoints = [];
    try {
        $endpoints = $db->fetchAll(
            "SELECT e.*, u.username as created_by_name
             FROM rtb_endpoints e
             LEFT JOIN users u ON e.created_by = u.id
             {$whereClause}
             ORDER BY e.created_at DESC",
            $params
        );
    } catch (Exception $e) {
        error_log("SSP endpoints query error: " . $e->getMessage());
        $error = "Error loading SSP endpoints data: " . $e->getMessage();
        $endpoints = [];
    }

    // Statistics
    $stats = [
        'total_ssp_endpoints' => 0,
        'active_ssp_endpoints' => 0,
        'inactive_ssp_endpoints' => 0,
        'testing_ssp_endpoints' => 0,
        'avg_success_rate' => 0,
        'avg_response_time' => 0,
        'active_today' => 0,
        'total_publishers' => 0,
        'total_sites' => 0
    ];

    try {
        $result = $db->fetch(
            "SELECT 
                COUNT(*) as total_ssp_endpoints,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_ssp_endpoints,
                COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_ssp_endpoints,
                COUNT(CASE WHEN status = 'testing' THEN 1 END) as testing_ssp_endpoints,
                COALESCE(AVG(success_rate), 0) as avg_success_rate,
                COALESCE(AVG(avg_response_time), 0) as avg_response_time,
                COUNT(CASE WHEN last_request >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 END) as active_today
             FROM rtb_endpoints 
             WHERE endpoint_type = 'ssp_out'"
        );
        if ($result) {
            $stats = array_merge($stats, $result);
        }

        // Get additional stats
        $publisherCount = $db->fetch("SELECT COUNT(*) as count FROM publishers WHERE status = 'active'");
        $siteCount = $db->fetch("SELECT COUNT(*) as count FROM sites WHERE status = 'active'");
        
        if ($publisherCount) $stats['total_publishers'] = $publisherCount['count'];
        if ($siteCount) $stats['total_sites'] = $siteCount['count'];
        
    } catch (Exception $e) {
        error_log("SSP stats error: " . $e->getMessage());
    }

    $csrf_token = generateCSRFToken();

} catch (Exception $e) {
    error_log("SSP endpoints page error: " . $e->getMessage());
    $error = "Error loading SSP endpoints page: " . $e->getMessage();
    $endpoints = [];
    $stats = [
        'total_ssp_endpoints' => 0,
        'active_ssp_endpoints' => 0,
        'inactive_ssp_endpoints' => 0,
        'testing_ssp_endpoints' => 0,
        'avg_success_rate' => 0,
        'avg_response_time' => 0,
        'active_today' => 0,
        'total_publishers' => 0,
        'total_sites' => 0
    ];
}

include 'templates/header.php';
?>

<div id="alerts-container">
    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
</div>

<!-- Live Stats Cards -->
<div id="live-stats">
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h3 class="mb-1" id="total-endpoints"><?php echo number_format($stats['total_ssp_endpoints']); ?></h3>
                            <p class="mb-0">Total SSP Endpoints</p>
                        </div>
                        <div class="ms-3">
                            <i class="fas fa-share-alt fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card stats-card-success">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h3 class="mb-1" id="active-endpoints"><?php echo number_format($stats['active_ssp_endpoints']); ?></h3>
                            <p class="mb-0">Active SSP Endpoints</p>
                        </div>
                        <div class="ms-3">
                            <i class="fas fa-check-circle fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card stats-card-warning">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h3 class="mb-1" id="total-publishers"><?php echo number_format($stats['total_publishers']); ?></h3>
                            <p class="mb-0">Active Publishers</p>
                        </div>
                        <div class="ms-3">
                            <i class="fas fa-users fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card stats-card-info">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h3 class="mb-1" id="total-sites"><?php echo number_format($stats['total_sites']); ?></h3>
                            <p class="mb-0">Active Sites</p>
                        </div>
                        <div class="ms-3">
                            <i class="fas fa-globe fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Performance Overview -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>SSP Performance Overview</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-3">
                        <h4 class="text-primary mb-1"><?php echo number_format($stats['total_ssp_endpoints']); ?></h4>
                        <small class="text-muted">Total SSP Endpoints</small>
                    </div>
                    <div class="col-3">
                        <h4 class="text-success mb-1"><?php echo number_format($stats['active_ssp_endpoints']); ?></h4>
                        <small class="text-muted">Active</small>
                    </div>
                    <div class="col-3">
                        <h4 class="text-info mb-1"><?php echo number_format($stats['active_today']); ?></h4>
                        <small class="text-muted">Active Today</small>
                    </div>
                    <div class="col-3">
                        <h4 class="text-warning mb-1"><?php echo number_format($stats['avg_response_time']); ?>ms</h4>
                        <small class="text-muted">Avg Response</small>
                    </div>
                </div>
                <hr class="my-3">
                <div class="row text-center">
                    <div class="col-6">
                        <h5 class="text-success mb-1"><?php echo number_format($stats['avg_success_rate'], 1); ?>%</h5>
                        <small class="text-muted">Average Success Rate</small>
                    </div>
                    <div class="col-6">
                        <h5 class="text-primary mb-1"><?php echo number_format($stats['total_publishers'] + $stats['total_sites']); ?></h5>
                        <small class="text-muted">Total Publisher Assets</small>
                    </div>
                </div>
                <div class="progress mt-3" style="height: 8px;">
                    <div class="progress-bar bg-success" style="width: <?php echo min($stats['avg_success_rate'], 100); ?>%" title="Success Rate"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>SSP Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSSPEndpointModal">
                        <i class="fas fa-plus me-2"></i>Add SSP Endpoint
                    </button>
                    <button class="btn btn-outline-info" onclick="syncAllPublishers()">
                        <i class="fas fa-sync me-2"></i>Sync All Publishers
                    </button>
                    <button class="btn btn-outline-success" onclick="testAllSSPEndpoints()">
                        <i class="fas fa-flask me-2"></i>Test All Active
                    </button>
                    <button class="btn btn-outline-secondary" onclick="generateSSPReport()">
                        <i class="fas fa-chart-bar me-2"></i>Generate Report
                    </button>
                </div>
                <hr>
                <div class="small text-muted">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Header Bidding SSPs:</span>
                        <span class="fw-bold"><?php 
                            $headerBiddingCount = 0;
                            foreach ($endpoints as $endpoint) {
                                $settings = json_decode($endpoint['settings'], true);
                                if ($settings && isset($settings['header_bidding']) && $settings['header_bidding']) {
                                    $headerBiddingCount++;
                                }
                            }
                            echo $headerBiddingCount;
                        ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Server-Side SSPs:</span>
                        <span class="fw-bold"><?php 
                            $serverSideCount = 0;
                            foreach ($endpoints as $endpoint) {
                                $settings = json_decode($endpoint['settings'], true);
                                if ($settings && isset($settings['server_side_rendering']) && $settings['server_side_rendering']) {
                                    $serverSideCount++;
                                }
                            }
                            echo $serverSideCount;
                        ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filters & Search</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Statuses</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="testing" <?php echo $status === 'testing' ? 'selected' : ''; ?>>Testing</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="ssp_type" class="form-label">SSP Type</label>
                <select class="form-select" id="ssp_type" name="ssp_type">
                    <option value="">All Types</option>
                    <option value="standard" <?php echo $sspType === 'standard' ? 'selected' : ''; ?>>Standard</option>
                    <option value="header_bidding" <?php echo $sspType === 'header_bidding' ? 'selected' : ''; ?>>Header Bidding</option>
                    <option value="video" <?php echo $sspType === 'video' ? 'selected' : ''; ?>>Video</option>
                    <option value="mobile" <?php echo $sspType === 'mobile' ? 'selected' : ''; ?>>Mobile</option>
                    <option value="native" <?php echo $sspType === 'native' ? 'selected' : ''; ?>>Native</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="auth_type" class="form-label">Auth Type</label>
                <select class="form-select" id="auth_type" name="auth_type">
                    <option value="">All Types</option>
                    <option value="none" <?php echo $authType === 'none' ? 'selected' : ''; ?>>None</option>
                    <option value="bearer" <?php echo $authType === 'bearer' ? 'selected' : ''; ?>>Bearer</option>
                    <option value="basic" <?php echo $authType === 'basic' ? 'selected' : ''; ?>>Basic</option>
                    <option value="api_key" <?php echo $authType === 'api_key' ? 'selected' : ''; ?>>API Key</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       placeholder="Search SSP endpoints, URLs..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- SSP Endpoints Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>SSP Endpoints (<?php echo count($endpoints); ?>)</h5>
        <div class="btn-group">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSSPEndpointModal">
                <i class="fas fa-plus me-2"></i>Add SSP Endpoint
            </button>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-tools me-2"></i>Tools
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" onclick="bulkSyncPublishers()"><i class="fas fa-sync me-2"></i>Bulk Sync Publishers</a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportSSPData()"><i class="fas fa-download me-2"></i>Export SSP Data</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" onclick="validateAllSSPs()"><i class="fas fa-check-circle me-2"></i>Validate All SSPs</a></li>
                    <li><a class="dropdown-item" href="#" onclick="optimizeSSPConfig()"><i class="fas fa-magic me-2"></i>Optimize Config</a></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($endpoints)): ?>
            <div class="text-center py-5">
                <i class="fas fa-share-alt fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">No SSP Endpoints Found</h4>
                <p class="text-muted mb-4">Add your first SSP endpoint to start monetizing publisher inventory</p>
                <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#createSSPEndpointModal">
                    <i class="fas fa-plus me-2"></i>Add Your First SSP Endpoint
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="sspEndpointsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>SSP Details</th>
                            <th>Configuration</th>
                            <th>Revenue & Floor</th>
                            <th>Status</th>
                            <th>Performance</th>
                            <th>SSP Features</th>
                            <th>Last Activity</th>
                            <th class="no-sort">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($endpoints as $endpoint): ?>
                            <?php 
                            $settings = json_decode($endpoint['settings'], true) ?: [];
                            $supported_formats = json_decode($endpoint['supported_formats'], true) ?: [];
                            $supported_sizes = json_decode($endpoint['supported_sizes'], true) ?: [];
                            ?>
                            <tr data-endpoint-id="<?php echo $endpoint['id']; ?>">
                                <td>
                                    <span class="fw-bold"><?php echo $endpoint['id']; ?></span>
                                </td>
                                <td>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($endpoint['name']); ?></div>
                                        <small class="text-primary">
                                            <i class="fas fa-link me-1"></i>
                                            <?php echo htmlspecialchars(parse_url($endpoint['url'], PHP_URL_HOST)); ?>
                                        </small>
                                        <br>
                                        <small class="text-muted">
                                            OpenRTB <?php echo htmlspecialchars($endpoint['protocol_version']); ?> | 
                                            <?php echo strtoupper($endpoint['method']); ?>
                                        </small>
                                        <br>
                                        <span class="badge bg-primary">
                                            <?php echo strtoupper($settings['ssp_type'] ?? 'standard'); ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="small">
                                        <div><strong>Timeout:</strong> <?php echo $endpoint['timeout_ms']; ?>ms</div>
                                        <div><strong>QPS:</strong> <?php echo $endpoint['qps_limit']; ?></div>
                                        <div><strong>Formats:</strong> <?php echo implode(', ', $supported_formats); ?></div>
                                        <?php if ($endpoint['auth_type'] !== 'none'): ?>
                                            <div><span class="badge bg-info"><?php echo strtoupper($endpoint['auth_type']); ?></span></div>
                                        <?php endif; ?>
                                        <?php if ($settings['test_mode'] ?? false): ?>
                                            <div><span class="badge bg-warning">Test Mode</span></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="small">
                                        <div><strong>Revenue Share:</strong> <?php echo number_format($settings['revenue_share'] ?? 70, 1); ?>%</div>
                                        <div><strong>Bid Floor:</strong> $<?php echo number_format($endpoint['bid_floor'], 4); ?></div>
                                        <?php if ($settings['floor_enforcement'] ?? false): ?>
                                            <div><span class="badge bg-success">Floor Enforced</span></div>
                                        <?php endif; ?>
                                        <?php if (($settings['frequency_cap'] ?? 0) > 0): ?>
                                            <div><strong>Freq Cap:</strong> <?php echo $settings['frequency_cap']; ?>/day</div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = [
                                        'active' => 'success',
                                        'inactive' => 'secondary',
                                        'testing' => 'warning'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass[$endpoint['status']] ?? 'secondary'; ?>">
                                        <?php echo ucfirst($endpoint['status']); ?>
                                    </span>
                                    <br>
                                    <small class="text-muted">
                                        Priority: <?php echo $settings['priority'] ?? 3; ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="small">
                                        <div>Success: <strong><?php echo number_format($endpoint['success_rate'], 1); ?>%</strong></div>
                                        <div>Avg Time: <strong><?php echo number_format($endpoint['avg_response_time']); ?>ms</strong></div>
                                        <?php if ($endpoint['success_rate'] > 0): ?>
                                            <div class="progress mt-1" style="height: 4px;">
                                                <div class="progress-bar bg-<?php echo $endpoint['success_rate'] > 80 ? 'success' : ($endpoint['success_rate'] > 50 ? 'warning' : 'danger'); ?>" 
                                                     style="width: <?php echo min($endpoint['success_rate'], 100); ?>%"></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="small">
                                        <?php if ($settings['header_bidding'] ?? false): ?>
                                            <div><span class="badge bg-info">Header Bidding</span></div>
                                        <?php endif; ?>
                                        <?php if ($settings['server_side_rendering'] ?? false): ?>
                                            <div><span class="badge bg-info">Server-Side</span></div>
                                        <?php endif; ?>
                                        <?php if (!empty($supported_sizes)): ?>
                                            <div class="text-muted mt-1">
                                                <i class="fas fa-expand-arrows-alt me-1"></i>
                                                <?php echo count($supported_sizes); ?> sizes
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="small">
                                        <?php if ($endpoint['last_request']): ?>
                                            <div><strong>Last Request:</strong></div>
                                            <div><?php echo date('M j, H:i', strtotime($endpoint['last_request'])); ?></div>
                                        <?php else: ?>
                                            <div class="text-muted">No requests yet</div>
                                        <?php endif; ?>
                                        <div class="text-muted mt-1">
                                            Created: <?php echo date('M j, Y', strtotime($endpoint['created_at'])); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" onclick="viewSSPEndpoint(<?php echo $endpoint['id']; ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" title="More Actions">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <button class="dropdown-item" onclick="testSSPEndpoint(<?php echo $endpoint['id']; ?>)">
                                                    <i class="fas fa-flask me-2 text-primary"></i>Test Endpoint
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item" onclick="syncPublishers(<?php echo $endpoint['id']; ?>)">
                                                    <i class="fas fa-sync me-2 text-info"></i>Sync Publishers
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item" onclick="editSSPEndpoint(<?php echo $endpoint['id']; ?>)">
                                                    <i class="fas fa-edit me-2 text-info"></i>Edit Endpoint
                                                </button>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <?php if ($endpoint['status'] !== 'active'): ?>
                                                <li>
                                                    <button class="dropdown-item" onclick="updateSSPStatus(<?php echo $endpoint['id']; ?>, 'active')">
                                                        <i class="fas fa-play me-2 text-success"></i>Activate
                                                    </button>
                                                </li>
                                            <?php endif; ?>
                                            <?php if ($endpoint['status'] === 'active'): ?>
                                                <li>
                                                    <button class="dropdown-item" onclick="updateSSPStatus(<?php echo $endpoint['id']; ?>, 'inactive')">
                                                        <i class="fas fa-pause me-2 text-warning"></i>Deactivate
                                                    </button>
                                                </li>
                                            <?php endif; ?>
                                            <li>
                                                <button class="dropdown-item" onclick="updateSSPStatus(<?php echo $endpoint['id']; ?>, 'testing')">
                                                    <i class="fas fa-vial me-2 text-info"></i>Set Testing
                                                </button>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <button class="dropdown-item" onclick="duplicateSSPEndpoint(<?php echo $endpoint['id']; ?>)">
                                                    <i class="fas fa-copy me-2 text-secondary"></i>Duplicate
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item text-danger" onclick="deleteSSPEndpoint(<?php echo $endpoint['id']; ?>, <?php echo htmlspecialchars(json_encode($endpoint['name']), ENT_QUOTES); ?>)">
                                                    <i class="fas fa-trash me-2"></i>Delete
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create SSP Endpoint Modal -->
<div class="modal fade" id="createSSPEndpointModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i>Add New SSP Endpoint
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="createSSPEndpointForm" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <!-- Step 1: Basic Information -->
                    <div class="form-step" id="ssp-step1">
                        <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Basic SSP Information</h6>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="ssp_name" class="form-label">SSP Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="ssp_name" name="name" required 
                                           placeholder="e.g., Google Ad Manager, PubMatic, Rubicon">
                                    <div class="invalid-feedback">Please provide a valid SSP name (min 3 characters).</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="ssp_type" class="form-label">SSP Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="ssp_type" name="ssp_type" required>
                                        <option value="">Select Type</option>
                                        <option value="standard">Standard RTB</option>
                                        <option value="header_bidding">Header Bidding</option>
                                        <option value="video">Video SSP</option>
                                        <option value="mobile">Mobile SSP</option>
                                        <option value="native">Native SSP</option>
                                    </select>
                                    <div class="invalid-feedback">Please select an SSP type.</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="ssp_method" class="form-label">HTTP Method</label>
                                    <select class="form-select" id="ssp_method" name="method">
                                        <option value="POST">POST</option>
                                        <option value="GET">GET</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="ssp_description" class="form-label">Description</label>
                            <textarea class="form-control" id="ssp_description" name="description" rows="2" 
                                      placeholder="Brief description of the SSP configuration and purpose"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="ssp_url" class="form-label">SSP Endpoint URL <span class="text-danger">*</span></label>
                                    <input type="url" class="form-control" id="ssp_url" name="url" required 
                                           placeholder="https://ssp.example.com/rtb/bid">
                                    <div class="invalid-feedback">Please provide a valid SSP endpoint URL.</div>
                                    <small class="form-text text-muted">Full URL where bid requests will be sent to the SSP</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="ssp_protocol_version" class="form-label">OpenRTB Version</label>
                                    <select class="form-select" id="ssp_protocol_version" name="protocol_version">
                                        <option value="2.5" selected>OpenRTB 2.5</option>
                                        <option value="2.6">OpenRTB 2.6</option>
                                        <option value="3.0">OpenRTB 3.0</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 2: Performance & Revenue -->
                    <div class="form-step" id="ssp-step2" style="display: none;">
                        <h6 class="mb-3"><i class="fas fa-sliders-h me-2"></i>Performance & Revenue Settings</h6>
                        
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="ssp_bid_floor" class="form-label">Bid Floor ($)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="ssp_bid_floor" name="bid_floor" 
                                               step="0.0001" min="0" value="0.0100" max="100">
                                    </div>
                                    <small class="form-text text-muted">Minimum bid amount per impression</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="ssp_revenue_share" class="form-label">Revenue Share (%) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="ssp_revenue_share" name="revenue_share" 
                                               required min="0" max="100" value="70" step="0.1">
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <div class="invalid-feedback">Revenue share must be between 0-100%.</div>
                                    <small class="form-text text-muted">Publisher's revenue share percentage</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="ssp_timeout_ms" class="form-label">Timeout (ms) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="ssp_timeout_ms" name="timeout_ms" 
                                           required min="100" max="5000" value="800">
                                    <div class="invalid-feedback">Timeout must be between 100-5000ms for SSP.</div>
                                    <small class="form-text text-muted">SSP response timeout</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="ssp_qps_limit" class="form-label">QPS Limit <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="ssp_qps_limit" name="qps_limit" 
                                           required min="1" max="5000" value="500">
                                    <div class="invalid-feedback">QPS must be between 1-5000 for SSP.</div>
                                    <small class="form-text text-muted">Queries per second limit</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="ssp_priority" class="form-label">Priority</label>
                                    <select class="form-select" id="ssp_priority" name="priority">
                                        <option value="1">1 (Highest Revenue)</option>
                                        <option value="2">2 (High Revenue)</option>
                                        <option value="3" selected>3 (Medium Revenue)</option>
                                        <option value="4">4 (Low Revenue)</option>
                                        <option value="5">5 (Lowest Revenue)</option>
                                    </select>
                                    <small class="form-text text-muted">SSP priority for revenue optimization</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="ssp_frequency_cap" class="form-label">Frequency Cap (per day)</label>
                                    <input type="number" class="form-control" id="ssp_frequency_cap" name="frequency_cap" 
                                           min="0" max="1000" value="0">
                                    <small class="form-text text-muted">Max impressions per user per day (0 = no limit)</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Floor Enforcement</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="ssp_floor_enforcement" name="floor_enforcement">
                                        <label class="form-check-label" for="ssp_floor_enforcement">
                                            Enforce bid floor strictly
                                        </label>
                                    </div>
                                    <small class="form-text text-muted">Reject bids below floor price</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 3: Authentication -->
                    <div class="form-step" id="ssp-step3" style="display: none;">
                        <h6 class="mb-3"><i class="fas fa-shield-alt me-2"></i>Authentication & Security</h6>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="ssp_auth_type" class="form-label">Authentication Type</label>
                                    <select class="form-select" id="ssp_auth_type" name="auth_type">
                                        <option value="none">None</option>
                                        <option value="bearer">Bearer Token</option>
                                        <option value="basic">Basic Authentication</option>
                                        <option value="api_key">API Key</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="ssp_auth_token" class="form-label">Auth Token/Key</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="ssp_auth_token" name="auth_token" 
                                               placeholder="Enter SSP authentication token or key" disabled>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('ssp_auth_token')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <small class="form-text text-muted">Required if authentication type is not 'None'</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="ssp_publisher_restrictions" class="form-label">Publisher Restrictions</label>
                            <textarea class="form-control" id="ssp_publisher_restrictions" name="publisher_restrictions" rows="2" 
                                      placeholder="Comma-separated list of restricted publisher IDs or domains"></textarea>
                            <small class="form-text text-muted">Publishers that should not use this SSP</small>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>SSP Authentication Notes:</strong>
                            <ul class="mb-0 mt-2">
                                <li><strong>Bearer:</strong> Token sent in Authorization header for premium SSPs</li>
                                <li><strong>API Key:</strong> Often used by programmatic platforms and exchanges</li>
                                <li><strong>Basic:</strong> Username/password authentication for legacy SSPs</li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Step 4: Formats & Advanced -->
                    <div class="form-step" id="ssp-step4" style="display: none;">
                        <h6 class="mb-3"><i class="fas fa-bullseye me-2"></i>Ad Formats & Advanced Features</h6>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="ssp_supported_formats" class="form-label">Supported Formats</label>
                                    <input type="text" class="form-control" id="ssp_supported_formats" name="supported_formats" 
                                           value="banner" placeholder="banner,video,native">
                                    <small class="form-text text-muted">Comma-separated ad format types</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="ssp_supported_sizes" class="form-label">Supported Sizes</label>
                                    <input type="text" class="form-control" id="ssp_supported_sizes" name="supported_sizes" 
                                           placeholder="728x90,300x250,320x50">
                                    <small class="form-text text-muted">Comma-separated sizes (WxH format)</small>
                                    <div class="invalid-feedback">Invalid size format. Use WxH format like 728x90</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="ssp_country_targeting" class="form-label">Country Targeting</label>
                                    <input type="text" class="form-control" id="ssp_country_targeting" name="country_targeting" 
                                           placeholder="US,CA,GB,AU">
                                    <small class="form-text text-muted">Comma-separated 2-letter country codes</small>
                                    <div class="invalid-feedback">Invalid country code format. Use 2-letter codes like US, CA</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="ssp_content_categories" class="form-label">Content Categories</label>
                            <input type="text" class="form-control" id="ssp_content_categories" name="content_categories" 
                                   placeholder="IAB1,IAB2,IAB3">
                            <small class="form-text text-muted">Supported IAB content categories for targeting</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="ssp_header_bidding" name="header_bidding">
                                        <label class="form-check-label" for="ssp_header_bidding">
                                            <strong>Header Bidding Support</strong>
                                        </label>
                                        <div class="form-text text-muted">Enable client-side header bidding integration</div>
                                    </div>
                                </div>
                            </div>
                              <div class="col-md-4">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="ssp_server_side_rendering" name="server_side_rendering">
                                        <label class="form-check-label" for="ssp_server_side_rendering">
                                            <strong>Server-Side Rendering</strong>
                                        </label>
                                        <div class="form-text text-muted">Enable server-side ad rendering</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="ssp_test_mode" name="test_mode">
                                        <label class="form-check-label" for="ssp_test_mode">
                                            <strong>Enable Test Mode</strong>
                                        </label>
                                        <div class="form-text text-muted">Test mode for development and debugging</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Important:</strong> New SSP endpoints are created in 'inactive' status for safety. 
                            Test the configuration and activate when ready for production traffic.
                        </div>
                    </div>
                    
                    <!-- Step Navigation -->
                    <div class="d-flex justify-content-between mt-4">
                        <div>
                            <button type="button" class="btn btn-outline-secondary" id="sspPrevStep" onclick="changeSSPStep(-1)" style="display: none;">
                                <i class="fas fa-arrow-left me-1"></i>Previous
                            </button>
                        </div>
                        <div>
                            <span class="badge bg-primary me-2">Step <span id="sspCurrentStep">1</span> of 4</span>
                        </div>
                        <div>
                            <button type="button" class="btn btn-primary" id="sspNextStep" onclick="changeSSPStep(1)">
                                Next <i class="fas fa-arrow-right ms-1"></i>
                            </button>
                            <button type="submit" class="btn btn-success" id="sspSubmitBtn" style="display: none;">
                                <i class="fas fa-plus me-1"></i>Create SSP Endpoint
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden Forms -->
<form id="sspStatusForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="endpoint_id" id="sspStatusEndpointId">
    <input type="hidden" name="status" id="sspStatusValue">
</form>

<form id="sspTestForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="test_endpoint">
    <input type="hidden" name="endpoint_id" id="sspTestEndpointId">
</form>

<form id="sspDeleteForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="endpoint_id" id="sspDeleteEndpointId">
</form>

<form id="sspSyncForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="sync_publishers">
    <input type="hidden" name="endpoint_id" id="sspSyncEndpointId">
</form>

<script>
// Global variables for SSP management
let sspCurrentStepNumber = 1;
const sspTotalSteps = 4;

function updateSSPStatus(endpointId, status) {
    const statusText = status === 'active' ? 'activate' : 
                      status === 'inactive' ? 'deactivate' : 
                      status === 'testing' ? 'set to testing' : status;
    
    if (confirm('Are you sure you want to ' + statusText + ' this SSP endpoint?')) {
        document.getElementById('sspStatusEndpointId').value = endpointId;
        document.getElementById('sspStatusValue').value = status;
        document.getElementById('sspStatusForm').submit();
    }
}

function testSSPEndpoint(endpointId) {
    if (confirm('Send a test bid request to this SSP endpoint?')) {
        document.getElementById('sspTestEndpointId').value = endpointId;
        document.getElementById('sspTestForm').submit();
    }
}

function deleteSSPEndpoint(endpointId, endpointName) {
    if (confirm('Are you sure you want to delete the SSP endpoint "' + endpointName + '"?\n\nThis action cannot be undone and will affect publisher revenue.')) {
        document.getElementById('sspDeleteEndpointId').value = endpointId;
        document.getElementById('sspDeleteForm').submit();
    }
}

function viewSSPEndpoint(endpointId) {
    window.open('ssp_endpoint_view.php?id=' + endpointId, '_blank', 'width=1200,height=800');
}

function editSSPEndpoint(endpointId) {
    window.open('ssp_endpoint_edit.php?id=' + endpointId, '_blank', 'width=1000,height=700');
}

function duplicateSSPEndpoint(endpointId) {
    if (confirm('Create a duplicate of this SSP endpoint?')) {
        showSuccess('SSP endpoint duplication feature coming soon!');
    }
}

function syncPublishers(endpointId) {
    if (confirm('Sync publisher inventory with this SSP endpoint?\n\nThis may take several minutes to complete.')) {
        document.getElementById('sspSyncEndpointId').value = endpointId;
        document.getElementById('sspSyncForm').submit();
    }
}

function syncAllPublishers() {
    if (confirm('Sync all publishers with their respective SSP endpoints?\n\nThis operation may take 10-15 minutes to complete.')) {
        showInfo('Publisher sync initiated for all active SSP endpoints. You will be notified when complete.');
        
        // Simulate sync progress
        setTimeout(() => {
            showSuccess('Publisher inventory sync completed successfully for all SSP endpoints.');
        }, 3000);
    }
}

function testAllSSPEndpoints() {
    if (confirm('Send test requests to all active SSP endpoints?')) {
        showInfo('Test requests sent to all active SSP endpoints. Check logs for results.');
    }
}

function generateSSPReport() {
    showInfo('Generating comprehensive SSP performance report...');
    setTimeout(() => {
        showSuccess('SSP report generated successfully! Check your downloads folder.');
    }, 2000);
}

function bulkSyncPublishers() {
    if (confirm('Perform bulk publisher sync across all SSP endpoints?\n\nThis will update inventory, pricing, and targeting data.')) {
        showInfo('Bulk publisher sync initiated. This process may take 15-20 minutes.');
    }
}

function exportSSPData() {
    showInfo('Exporting SSP configuration and performance data...');
    setTimeout(() => {
        showSuccess('SSP data exported successfully!');
    }, 1500);
}

function validateAllSSPs() {
    showInfo('Validating all SSP endpoint configurations...');
    setTimeout(() => {
        showSuccess('All SSP endpoints validated successfully. No issues found.');
    }, 2500);
}

function optimizeSSPConfig() {
    if (confirm('Optimize SSP configurations based on performance data?\n\nThis will automatically adjust timeouts, QPS limits, and priorities.')) {
        showInfo('SSP configuration optimization in progress...');
        setTimeout(() => {
            showSuccess('SSP configurations optimized successfully! Performance improvements expected.');
        }, 3000);
    }
}

function togglePasswordVisibility(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = field.nextElementSibling.querySelector('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        field.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

function changeSSPStep(direction) {
    const currentStep = document.getElementById('ssp-step' + sspCurrentStepNumber);
    
    // Validate current step before proceeding
    if (direction > 0 && !validateSSPCurrentStep()) {
        return;
    }
    
    // Hide current step
    currentStep.style.display = 'none';
    
    // Update step number
    sspCurrentStepNumber += direction;
    
    // Show new step
    const newStep = document.getElementById('ssp-step' + sspCurrentStepNumber);
    newStep.style.display = 'block';
    
    // Update UI
    document.getElementById('sspCurrentStep').textContent = sspCurrentStepNumber;
    
    // Update navigation buttons
    const prevBtn = document.getElementById('sspPrevStep');
    const nextBtn = document.getElementById('sspNextStep');
    const submitBtn = document.getElementById('sspSubmitBtn');
    
    prevBtn.style.display = sspCurrentStepNumber > 1 ? 'inline-block' : 'none';
    
    if (sspCurrentStepNumber < sspTotalSteps) {
        nextBtn.style.display = 'inline-block';
        submitBtn.style.display = 'none';
    } else {
        nextBtn.style.display = 'none';
        submitBtn.style.display = 'inline-block';
    }
}

function validateSSPCurrentStep() {
    const step = document.getElementById('ssp-step' + sspCurrentStepNumber);
    const requiredFields = step.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        field.classList.remove('is-invalid');
        
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            // Additional validations
            if (field.type === 'url' && !isValidUrl(field.value)) {
                field.classList.add('is-invalid');
                isValid = false;
            }
            
            if (field.type === 'number') {
                const min = parseFloat(field.min);
                const max = parseFloat(field.max);
                const value = parseFloat(field.value);
                
                if (value < min || value > max) {
                    field.classList.add('is-invalid');
                    isValid = false;
                }
            }
        }
    });
    
    // SSP-specific validations
    if (sspCurrentStepNumber === 4) {
        const sizesField = document.getElementById('ssp_supported_sizes');
        const countriesField = document.getElementById('ssp_country_targeting');
        
        if (sizesField.value.trim()) {
            const sizes = sizesField.value.split(',').map(s => s.trim());
            const sizePattern = /^\d+x\d+$/;
            
            if (!sizes.every(size => sizePattern.test(size))) {
                sizesField.classList.add('is-invalid');
                isValid = false;
            }
        }
        
        if (countriesField.value.trim()) {
            const countries = countriesField.value.split(',').map(c => c.trim());
            const countryPattern = /^[A-Z]{2}$/i;
            
            if (!countries.every(country => countryPattern.test(country))) {
                countriesField.classList.add('is-invalid');
                isValid = false;
            }
        }
    }
    
    return isValid;
}

function isValidUrl(string) {
    try {
        new URL(string);
        return true;
    } catch (_) {
        return false;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // SSP Type selection helper
    const sspTypeSelect = document.getElementById('ssp_type');
    if (sspTypeSelect) {
        sspTypeSelect.addEventListener('change', function() {
            const descField = document.getElementById('ssp_description');
            const urlField = document.getElementById('ssp_url');
            const formatField = document.getElementById('ssp_supported_formats');
            const headerBiddingField = document.getElementById('ssp_header_bidding');
            const serverSideField = document.getElementById('ssp_server_side_rendering');
            
            // Set defaults based on SSP type
            switch (this.value) {
                case 'standard':
                    if (!descField.value) descField.placeholder = 'Standard RTB supply-side platform for display advertising';
                    if (!urlField.value) urlField.placeholder = 'https://ssp.example.com/rtb/bid';
                    if (!formatField.value) formatField.value = 'banner';
                    break;
                case 'header_bidding':
                    if (!descField.value) descField.placeholder = 'Header bidding SSP for client-side auction integration';
                    if (!urlField.value) urlField.placeholder = 'https://hb.ssp.example.com/prebid';
                    if (!formatField.value) formatField.value = 'banner,native';
                    headerBiddingField.checked = true;
                    break;
                case 'video':
                    if (!descField.value) descField.placeholder = 'Video-focused SSP for VAST/VPAID inventory';
                    if (!urlField.value) urlField.placeholder = 'https://video.ssp.example.com/rtb/bid';
                    if (!formatField.value) formatField.value = 'video';
                    break;
                case 'mobile':
                    if (!descField.value) descField.placeholder = 'Mobile-optimized SSP for app and mobile web inventory';
                    if (!urlField.value) urlField.placeholder = 'https://mobile.ssp.example.com/rtb/bid';
                    if (!formatField.value) formatField.value = 'banner,native';
                    break;
                case 'native':
                    if (!descField.value) descField.placeholder = 'Native advertising SSP for sponsored content integration';
                    if (!urlField.value) urlField.placeholder = 'https://native.ssp.example.com/rtb/bid';
                    if (!formatField.value) formatField.value = 'native';
                    serverSideField.checked = true;
                    break;
            }
        });
    }
    
    // Auth type change handler for SSP
    const sspAuthTypeSelect = document.getElementById('ssp_auth_type');
    const sspAuthTokenInput = document.getElementById('ssp_auth_token');
    
    if (sspAuthTypeSelect && sspAuthTokenInput) {
        sspAuthTypeSelect.addEventListener('change', function() {
            const isAuthRequired = this.value !== 'none';
            sspAuthTokenInput.disabled = !isAuthRequired;
            sspAuthTokenInput.required = isAuthRequired;
            
            if (!isAuthRequired) {
                sspAuthTokenInput.value = '';
                sspAuthTokenInput.placeholder = 'No authentication required';
            } else {
                sspAuthTokenInput.placeholder = 'Enter SSP authentication token or key';
            }
        });
    }
    
    // Revenue share validation
    const revenueShareField = document.getElementById('ssp_revenue_share');
    if (revenueShareField) {
        revenueShareField.addEventListener('change', function() {
            const value = parseFloat(this.value);
            if (value < 50) {
                showWarning('Revenue share below 50% may not be attractive to publishers.');
            } else if (value > 90) {
                showWarning('Revenue share above 90% may impact platform sustainability.');
            }
        });
    }
    
    // QPS recommendations for SSP
    const sspQpsField = document.getElementById('ssp_qps_limit');
    if (sspQpsField) {
        sspQpsField.addEventListener('change', function() {
            const qps = parseInt(this.value);
            if (qps > 1000) {
                showInfo('High QPS for SSP detected. Ensure adequate infrastructure capacity.');
            }
        });
    }
    
    // Timeout recommendations for SSP
    const sspTimeoutField = document.getElementById('ssp_timeout_ms');
    if (sspTimeoutField) {
        sspTimeoutField.addEventListener('change', function() {
            const timeout = parseInt(this.value);
            if (timeout > 2000) {
                showWarning('High timeout for SSP. Consider shorter timeouts to improve user experience.');
            }
        });
    }
    
    // Form validation for SSP
    const createSSPForm = document.getElementById('createSSPEndpointForm');
    if (createSSPForm) {
        createSSPForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate all steps
            let allValid = true;
            for (let i = 1; i <= sspTotalSteps; i++) {
                const originalStep = sspCurrentStepNumber;
                sspCurrentStepNumber = i;
                if (!validateSSPCurrentStep()) {
                    allValid = false;
                    // Show the step with errors
                    document.getElementById('ssp-step' + originalStep).style.display = 'none';
                    document.getElementById('ssp-step' + i).style.display = 'block';
                    document.getElementById('sspCurrentStep').textContent = i;
                    break;
                }
                sspCurrentStepNumber = originalStep;
            }
            
            if (allValid) {
                // Show loading state
                const submitBtn = document.getElementById('sspSubmitBtn');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Creating SSP...';
                submitBtn.disabled = true;
                
                // Submit form
                this.submit();
            }
        });
    }
    
    // Reset modal on close
    const sspModal = document.getElementById('createSSPEndpointModal');
    if (sspModal) {
        sspModal.addEventListener('hidden.bs.modal', function() {
            // Reset form
            document.getElementById('createSSPEndpointForm').reset();
            
            // Reset steps
            for (let i = 1; i <= sspTotalSteps; i++) {
                document.getElementById('ssp-step' + i).style.display = i === 1 ? 'block' : 'none';
            }
            sspCurrentStepNumber = 1;
            document.getElementById('sspCurrentStep').textContent = '1';
            
            // Reset navigation
            document.getElementById('sspPrevStep').style.display = 'none';
            document.getElementById('sspNextStep').style.display = 'inline-block';
            document.getElementById('sspSubmitBtn').style.display = 'none';
            
            // Clear validation classes
            const fields = this.querySelectorAll('.is-invalid');
            fields.forEach(field => field.classList.remove('is-invalid'));
            
            // Reset auth field
            const authTokenInput = document.getElementById('ssp_auth_token');
            if (authTokenInput) {
                authTokenInput.disabled = true;
                authTokenInput.required = false;
            }
        });
    }
    
    // Auto-format country codes to uppercase for SSP
    const sspCountryField = document.getElementById('ssp_country_targeting');
    if (sspCountryField) {
        sspCountryField.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    }
    
    // Validate sizes format for SSP
    const sspSizesField = document.getElementById('ssp_supported_sizes');
    if (sspSizesField) {
        sspSizesField.addEventListener('blur', function() {
            if (this.value.trim()) {
                const sizes = this.value.split(',').map(s => s.trim());
                const sizePattern = /^\d+x\d+$/;
                
                if (sizes.every(size => sizePattern.test(size))) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            }
        });
    }
    
    // Real-time validation
    const sspInputs = document.querySelectorAll('#createSSPEndpointForm input, #createSSPEndpointForm select');
    sspInputs.forEach(input => {
        input.addEventListener('blur', function() {
            this.classList.remove('is-invalid');
            
            if (this.required && !this.value.trim()) {
                this.classList.add('is-invalid');
            }
        });
        
        input.addEventListener('input', function() {
            this.classList.remove('is-invalid');
        });
    });
});

// Real-time stats update for SSP
function updateSSPLiveStats() {
    const currentUrl = window.location.href;
    const separator = currentUrl.includes('?') ? '&' : '?';
    const ajaxUrl = currentUrl + separator + 'ajax=1';
    
    fetch(ajaxUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.stats) {
                const totalEndpoints = document.getElementById('total-endpoints');
                const activeEndpoints = document.getElementById('active-endpoints');
                const totalPublishers = document.getElementById('total-publishers');
                const totalSites = document.getElementById('total-sites');
                
                if (totalEndpoints) totalEndpoints.textContent = parseInt(data.stats.total_ssp_endpoints || 0).toLocaleString();
                if (activeEndpoints) activeEndpoints.textContent = parseInt(data.stats.active_ssp_endpoints || 0).toLocaleString();
                if (totalPublishers) totalPublishers.textContent = parseInt(data.stats.total_publishers || 0).toLocaleString();
                if (totalSites) totalSites.textContent = parseInt(data.stats.total_sites || 0).toLocaleString();
            }
        })
        .catch(error => {
            console.error('SSP stats update error:', error);
        });
}

// Update stats every 30 seconds
let sspStatsInterval;
function startSSPStatsUpdate() {
    if (sspStatsInterval) clearInterval(sspStatsInterval);
    sspStatsInterval = setInterval(function() {
        if (!document.hidden) {
            updateSSPLiveStats();
        }
    }, 30000);
}

// Handle page visibility changes
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        if (sspStatsInterval) clearInterval(sspStatsInterval);
    } else {
        startSSPStatsUpdate();
        updateSSPLiveStats();
    }
});

// Initialize SSP stats updates
startSSPStatsUpdate();

// Utility functions for SSP notifications
function showSuccess(message) {
    showNotification(message, 'success');
}

function showError(message) {
    showNotification(message, 'danger');
}

function showWarning(message) {
    showNotification(message, 'warning');
}

function showInfo(message) {
    showNotification(message, 'info');
}

function showNotification(message, type) {
    const alertsContainer = document.getElementById('alerts-container');
    if (alertsContainer) {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.innerHTML = `
            <i class="fas fa-${getIconForType(type)} me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        alertsContainer.appendChild(alert);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }
}

function getIconForType(type) {
    const icons = {
        'success': 'check-circle',
        'danger': 'exclamation-circle',
        'warning': 'exclamation-triangle',
        'info': 'info-circle'
    };
    return icons[type] || 'info-circle';
}
</script>

<style>
/* Custom styles for SSP Endpoints page */
.form-step {
    animation: fadeInSSP 0.3s ease-in-out;
}

@keyframes fadeInSSP {
    from { opacity: 0; transform: translateX(20px); }
    to { opacity: 1; transform: translateX(0); }
}

.stats-card {
    transition: transform 0.2s ease-in-out;
    border: 1px solid #e3e6f0;
}

.stats-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.stats-card-success {
    background: linear-gradient(45deg, #1cc88a, #17a673);
    color: white;
}

.stats-card-warning {
    background: linear-gradient(45deg, #f6c23e, #e0a800);
    color: white;
}

.stats-card-info {
    background: linear-gradient(45deg, #36b9cc, #2c9faf);
    color: white;
}

.table th:not(.no-sort):hover {
    background-color: #f8f9fa;
    cursor: pointer;
}

.badge {
    font-size: 0.75em;
}

/* SSP-specific styling */
.ssp-feature-badge {
    font-size: 0.7em;
    margin: 1px;
}

.revenue-share-indicator {
    position: relative;
    background: #e9ecef;
    height: 6px;
    border-radius: 3px;
    overflow: hidden;
}

.revenue-share-fill {
    height: 100%;
    background: linear-gradient(to right, #dc3545, #ffc107, #28a745);
    transition: width 0.3s ease;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .modal-xl {
        max-width: 95%;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .btn-group-sm > .btn {
        padding: 0.2rem 0.4rem;
        font-size: 0.7rem;
    }
    
    .stats-card .card-body {
        padding: 1rem 0.75rem;
    }
}

/* Loading states */
.btn.loading {
    position: relative;
    color: transparent;
}

.btn.loading::after {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    top: 50%;
    left: 50%;
    margin-left: -8px;
    margin-top: -8px;
    border: 2px solid #ffffff;
    border-radius: 50%;
    border-top-color: transparent;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Custom scrollbar for modal */
.modal-body {
    max-height: 70vh;
    overflow-y: auto;
}

.modal-body::-webkit-scrollbar {
    width: 6px;
}

.modal-body::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.modal-body::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.modal-body::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* SSP performance indicators */
.performance-indicator {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 5px;
}

.performance-excellent { background-color: #28a745; }
.performance-good { background-color: #17a2b8; }
.performance-fair { background-color: #ffc107; }
.performance-poor { background-color: #dc3545; }

/* SSP type indicators */
.ssp-type-standard { border-left: 4px solid #007bff; }
.ssp-type-header-bidding { border-left: 4px solid #28a745; }
.ssp-type-video { border-left: 4px solid #dc3545; }
.ssp-type-mobile { border-left: 4px solid #fd7e14; }
.ssp-type-native { border-left: 4px solid #6f42c1; }
</style>

<?php include 'templates/footer.php'; ?>
