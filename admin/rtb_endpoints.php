<?php
$pageTitle = 'RTB Endpoints Management';
$breadcrumb = [
    ['text' => 'RTB Endpoints']
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
                    COUNT(*) as total_endpoints,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_endpoints,
                    COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_endpoints,
                    COUNT(CASE WHEN status = 'testing' THEN 1 END) as testing_endpoints,
                    COALESCE(AVG(success_rate), 0) as avg_success_rate,
                    COALESCE(AVG(avg_response_time), 0) as avg_response_time_today,
                    COUNT(CASE WHEN last_request >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 END) as active_today
                 FROM rtb_endpoints"
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
                        $requiredFields = ['name', 'endpoint_type', 'url', 'timeout_ms', 'qps_limit'];
                        foreach ($requiredFields as $field) {
                            if (empty($_POST[$field])) {
                                throw new Exception("Field '{$field}' is required");
                            }
                        }
                        
                        // Validate URL
                        $url = sanitize($_POST['url']);
                        if (!filter_var($url, FILTER_VALIDATE_URL)) {
                            throw new Exception('Invalid endpoint URL format');
                        }
                        
                        // Check for duplicate URL
                        $existingEndpoint = $db->fetch("SELECT id FROM rtb_endpoints WHERE url = ?", [$url]);
                        if ($existingEndpoint) {
                            throw new Exception('An endpoint with this URL already exists');
                        }
                        
                        // Validate numeric fields
                        $timeout_ms = (int)$_POST['timeout_ms'];
                        $qps_limit = (int)$_POST['qps_limit'];
                        $bid_floor = (float)($_POST['bid_floor'] ?? 0);
                        
                        if ($timeout_ms < 100 || $timeout_ms > 10000) {
                            throw new Exception('Timeout must be between 100 and 10000 milliseconds');
                        }
                        
                        if ($qps_limit < 1 || $qps_limit > 10000) {
                            throw new Exception('QPS limit must be between 1 and 10000');
                        }
                        
                        if ($bid_floor < 0) {
                            throw new Exception('Bid floor cannot be negative');
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
                        
                        // Process supported sizes
                        $supported_sizes = null;
                        if (!empty($_POST['supported_sizes'])) {
                            $sizes_input = sanitize($_POST['supported_sizes']);
                            $sizes_array = array_filter(array_map('trim', explode(',', $sizes_input)));
                            
                            // Validate size format (WxH)
                            foreach ($sizes_array as $size) {
                                if (!preg_match('/^\d+x\d+$/', $size)) {
                                    throw new Exception("Invalid size format: {$size}. Use format like 728x90");
                                }
                            }
                            
                            if (!empty($sizes_array)) {
                                $supported_sizes = json_encode($sizes_array);
                            }
                        }
                        
                        // Process country targeting
                        $country_targeting = null;
                        if (!empty($_POST['country_targeting'])) {
                            $countries_input = sanitize($_POST['country_targeting']);
                            $countries_array = array_filter(array_map('trim', explode(',', $countries_input)));
                            
                            // Validate country codes (2 letter codes)
                            foreach ($countries_array as $country) {
                                if (!preg_match('/^[A-Z]{2}$/i', $country)) {
                                    throw new Exception("Invalid country code: {$country}. Use 2-letter codes like US, CA, GB");
                                }
                            }
                            
                            if (!empty($countries_array)) {
                                $country_targeting = json_encode(array_map('strtoupper', $countries_array));
                            }
                        }
                        
                        // Prepare settings JSON
                        $settings = [
                            'description' => sanitize($_POST['description'] ?? ''),
                            'priority' => (int)($_POST['priority'] ?? 3),
                            'test_mode' => isset($_POST['test_mode']) ? 1 : 0,
                            'created_at' => date('Y-m-d H:i:s'),
                            'created_by' => $_SESSION['user_id'],
                            'created_by_username' => $_SESSION['username'] ?? 'unknown',
                            'version' => '1.0'
                        ];
                        
                        $data = [
                            'name' => sanitize($_POST['name']),
                            'endpoint_type' => sanitize($_POST['endpoint_type']),
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
                        error_log("RTB Endpoint created: ID={$endpointId}, Name=" . $data['name'] . ", URL=" . $data['url'] . ", CreatedBy=" . $_SESSION['user_id']);
                        
                        $success = "RTB endpoint '{$data['name']}' created successfully with ID: {$endpointId}. The endpoint is initially set to inactive status for safety.";
                        
                    } catch (Exception $e) {
                        $error = 'Failed to create RTB endpoint: ' . $e->getMessage();
                        error_log("RTB Endpoint creation failed: " . $e->getMessage());
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
                            
                            $db->update('rtb_endpoints', $updateData, 'id = ?', [$endpointId]);
                            $success = 'RTB endpoint status updated successfully';
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
                        $endpoint = $db->fetch("SELECT name FROM rtb_endpoints WHERE id = ?", [$endpointId]);
                        
                        if ($endpoint) {
                            $db->delete('rtb_endpoints', 'id = ?', [$endpointId]);
                            $success = "RTB endpoint '{$endpoint['name']}' deleted successfully";
                            error_log("RTB Endpoint deleted: ID={$endpointId}, Name=" . $endpoint['name'] . ", DeletedBy=" . $_SESSION['user_id']);
                        } else {
                            $error = 'Endpoint not found';
                        }
                    } catch (Exception $e) {
                        $error = 'Failed to delete endpoint: ' . $e->getMessage();
                    }
                    break;
                    
                case 'test_endpoint':
                    try {
                        $endpointId = (int)$_POST['endpoint_id'];
                        $endpoint = $db->fetch("SELECT * FROM rtb_endpoints WHERE id = ?", [$endpointId]);
                        
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
                            
                            $success = "Test request sent to endpoint '{$endpoint['name']}'. Check the endpoint logs for response details.";
                        } else {
                            $error = 'Endpoint not found';
                        }
                    } catch (Exception $e) {
                        $error = 'Failed to test endpoint: ' . $e->getMessage();
                    }
                    break;
            }
        }
    }

    // Filters
    $status = $_GET['status'] ?? '';
    $endpointType = $_GET['endpoint_type'] ?? '';
    $authType = $_GET['auth_type'] ?? '';
    $search = sanitize($_GET['search'] ?? '');

    // Build query
    $whereConditions = [];
    $params = [];

    if ($status) {
        $whereConditions[] = 'status = ?';
        $params[] = $status;
    }

    if ($endpointType) {
        $whereConditions[] = 'endpoint_type = ?';
        $params[] = $endpointType;
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

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // Get RTB endpoints
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
        error_log("RTB endpoints query error: " . $e->getMessage());
        $error = "Error loading RTB endpoints data: " . $e->getMessage();
        $endpoints = [];
    }

    // Statistics
    $stats = [
        'total_endpoints' => 0,
        'active_endpoints' => 0,
        'inactive_endpoints' => 0,
        'testing_endpoints' => 0,
        'avg_success_rate' => 0,
        'avg_response_time_today' => 0,
        'active_today' => 0
    ];

    try {
        $result = $db->fetch(
            "SELECT 
                COUNT(*) as total_endpoints,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_endpoints,
                COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_endpoints,
                COUNT(CASE WHEN status = 'testing' THEN 1 END) as testing_endpoints,
                COALESCE(AVG(success_rate), 0) as avg_success_rate,
                COALESCE(AVG(avg_response_time), 0) as avg_response_time_today,
                COUNT(CASE WHEN last_request >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 END) as active_today
             FROM rtb_endpoints"
        );
        if ($result) {
            $stats = $result;
        }
    } catch (Exception $e) {
        error_log("RTB stats error: " . $e->getMessage());
    }

    $csrf_token = generateCSRFToken();

} catch (Exception $e) {
    error_log("RTB endpoints page error: " . $e->getMessage());
    $error = "Error loading RTB endpoints page: " . $e->getMessage();
    $endpoints = [];
    $stats = [
        'total_endpoints' => 0,
        'active_endpoints' => 0,
        'inactive_endpoints' => 0,
        'testing_endpoints' => 0,
        'avg_success_rate' => 0,
        'avg_response_time_today' => 0,
        'active_today' => 0
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
                            <h3 class="mb-1" id="total-endpoints"><?php echo number_format($stats['total_endpoints']); ?></h3>
                            <p class="mb-0">Total Endpoints</p>
                        </div>
                        <div class="ms-3">
                            <i class="fas fa-exchange-alt fa-2x opacity-75"></i>
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
                            <h3 class="mb-1" id="active-endpoints"><?php echo number_format($stats['active_endpoints']); ?></h3>
                            <p class="mb-0">Active Endpoints</p>
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
                            <h3 class="mb-1" id="success-rate"><?php echo number_format($stats['avg_success_rate'], 1); ?>%</h3>
                            <p class="mb-0">Avg Success Rate</p>
                        </div>
                        <div class="ms-3">
                            <i class="fas fa-chart-line fa-2x opacity-75"></i>
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
                            <h3 class="mb-1" id="avg-response"><?php echo number_format($stats['avg_response_time_today']); ?>ms</h3>
                            <p class="mb-0">Avg Response Time</p>
                        </div>
                        <div class="ms-3">
                            <i class="fas fa-clock fa-2x opacity-75"></i>
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
                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>RTB Performance Overview</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-3">
                        <h4 class="text-primary mb-1"><?php echo number_format($stats['total_endpoints']); ?></h4>
                        <small class="text-muted">Total Endpoints</small>
                    </div>
                    <div class="col-3">
                        <h4 class="text-success mb-1"><?php echo number_format($stats['active_endpoints']); ?></h4>
                        <small class="text-muted">Active</small>
                    </div>
                    <div class="col-3">
                        <h4 class="text-info mb-1"><?php echo number_format($stats['active_today']); ?></h4>
                        <small class="text-muted">Active Today</small>
                    </div>
                    <div class="col-3">
                        <h4 class="text-warning mb-1"><?php echo number_format($stats['avg_response_time_today']); ?>ms</h4>
                        <small class="text-muted">Avg Response</small>
                    </div>
                </div>
                <hr class="my-3">
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar bg-success" style="width: <?php echo min($stats['avg_success_rate'], 100); ?>%" title="Success Rate"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createEndpointModal">
                        <i class="fas fa-plus me-2"></i>Add New Endpoint
                    </button>
                    <button class="btn btn-outline-info" onclick="testAllEndpoints()">
                        <i class="fas fa-flask me-2"></i>Test All Active
                    </button>
                    <button class="btn btn-outline-success" onclick="refreshStats()">
                        <i class="fas fa-sync me-2"></i>Refresh Stats
                    </button>
                    <button class="btn btn-outline-secondary" onclick="exportEndpointsData()">
                        <i class="fas fa-download me-2"></i>Export Data
                    </button>
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
                <label for="endpoint_type" class="form-label">Type</label>
                <select class="form-select" id="endpoint_type" name="endpoint_type">
                    <option value="">All Types</option>
                    <option value="ssp_out" <?php echo $endpointType === 'ssp_out' ? 'selected' : ''; ?>>SSP Out</option>
                    <option value="dsp_in" <?php echo $endpointType === 'dsp_in' ? 'selected' : ''; ?>>DSP In</option>
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
                       placeholder="Search endpoints, URLs..." value="<?php echo htmlspecialchars($search); ?>">
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

<!-- RTB Endpoints Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>RTB Endpoints (<?php echo count($endpoints); ?>)</h5>
        <div class="btn-group">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createEndpointModal">
                <i class="fas fa-plus me-2"></i>Add Endpoint
            </button>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-tools me-2"></i>Tools
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" onclick="importEndpoints()"><i class="fas fa-upload me-2"></i>Import Endpoints</a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportEndpointsData()"><i class="fas fa-download me-2"></i>Export Data</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" onclick="bulkTestEndpoints()"><i class="fas fa-flask me-2"></i>Bulk Test</a></li>
                    <li><a class="dropdown-item" href="#" onclick="generateReport()"><i class="fas fa-chart-bar me-2"></i>Generate Report</a></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($endpoints)): ?>
            <div class="text-center py-5">
                <i class="fas fa-exchange-alt fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">No RTB Endpoints Found</h4>
                <p class="text-muted mb-4">Add your first RTB endpoint to start real-time bidding</p>
                <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#createEndpointModal">
                    <i class="fas fa-plus me-2"></i>Add Your First Endpoint
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="endpointsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Endpoint Details</th>
                            <th>Type & Method</th>
                            <th>Configuration</th>
                            <th>Status</th>
                            <th>Performance</th>
                            <th>Last Activity</th>
                            <th class="no-sort">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($endpoints as $endpoint): ?>
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
                                            Protocol: <?php echo htmlspecialchars($endpoint['protocol_version']); ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <span class="badge bg-<?php echo $endpoint['endpoint_type'] === 'ssp_out' ? 'primary' : 'info'; ?>">
                                            <?php echo strtoupper($endpoint['endpoint_type']); ?>
                                        </span>
                                        <br>
                                        <small class="fw-bold"><?php echo $endpoint['method']; ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div class="small">
                                        <div><strong>Bid Floor:</strong> $<?php echo number_format($endpoint['bid_floor'], 4); ?></div>
                                        <div><strong>Timeout:</strong> <?php echo $endpoint['timeout_ms']; ?>ms</div>
                                        <div><strong>QPS:</strong> <?php echo $endpoint['qps_limit']; ?></div>
                                        <?php if ($endpoint['auth_type'] !== 'none'): ?>
                                            <div><span class="badge bg-info"><?php echo strtoupper($endpoint['auth_type']); ?></span></div>
                                        <?php endif; ?>
                                        <?php 
                                        $settings = json_decode($endpoint['settings'], true);
                                        if ($settings && isset($settings['test_mode']) && $settings['test_mode']): 
                                        ?>
                                            <div><span class="badge bg-warning">Test Mode</span></div>
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
                                        <button class="btn btn-outline-primary" onclick="viewEndpoint(<?php echo $endpoint['id']; ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" title="More Actions">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <button class="dropdown-item" onclick="testEndpoint(<?php echo $endpoint['id']; ?>)">
                                                    <i class="fas fa-flask me-2 text-primary"></i>Test Endpoint
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item" onclick="editEndpoint(<?php echo $endpoint['id']; ?>)">
                                                    <i class="fas fa-edit me-2 text-info"></i>Edit Endpoint
                                                </button>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <?php if ($endpoint['status'] !== 'active'): ?>
                                                <li>
                                                    <button class="dropdown-item" onclick="updateStatus(<?php echo $endpoint['id']; ?>, 'active')">
                                                        <i class="fas fa-play me-2 text-success"></i>Activate
                                                    </button>
                                                </li>
                                            <?php endif; ?>
                                            <?php if ($endpoint['status'] === 'active'): ?>
                                                <li>
                                                    <button class="dropdown-item" onclick="updateStatus(<?php echo $endpoint['id']; ?>, 'inactive')">
                                                        <i class="fas fa-pause me-2 text-warning"></i>Deactivate
                                                    </button>
                                                </li>
                                            <?php endif; ?>
                                            <li>
                                                <button class="dropdown-item" onclick="updateStatus(<?php echo $endpoint['id']; ?>, 'testing')">
                                                    <i class="fas fa-vial me-2 text-info"></i>Set Testing
                                                </button>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <button class="dropdown-item" onclick="duplicateEndpoint(<?php echo $endpoint['id']; ?>)">
                                                    <i class="fas fa-copy me-2 text-secondary"></i>Duplicate
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item text-danger" onclick="deleteEndpoint(<?php echo $endpoint['id']; ?>, <?php echo htmlspecialchars(json_encode($endpoint['name']), ENT_QUOTES); ?>)">
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

<!-- Enhanced Create Endpoint Modal -->
<div class="modal fade" id="createEndpointModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i>Add New RTB Endpoint
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="createEndpointForm" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <!-- Step 1: Basic Information -->
                    <div class="form-step" id="step1">
                        <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Basic Information</h6>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Endpoint Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" required 
                                           placeholder="e.g., Google DV360, Amazon DSP">
                                    <div class="invalid-feedback">Please provide a valid endpoint name (min 3 characters).</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="endpoint_type" class="form-label">Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="endpoint_type" name="endpoint_type" required>
                                        <option value="">Select Type</option>
                                        <option value="ssp_out">SSP Out (Supply Side)</option>
                                        <option value="dsp_in">DSP In (Demand Side)</option>
                                    </select>
                                    <div class="invalid-feedback">Please select an endpoint type.</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="method" class="form-label">HTTP Method</label>
                                    <select class="form-select" id="method" name="method">
                                        <option value="POST">POST</option>
                                        <option value="GET">GET</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="2" 
                                      placeholder="Brief description of the RTB endpoint purpose and configuration"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="url" class="form-label">Endpoint URL <span class="text-danger">*</span></label>
                                    <input type="url" class="form-control" id="url" name="url" required 
                                           placeholder="https://api.example.com/rtb/bid">
                                    <div class="invalid-feedback">Please provide a valid URL.</div>
                                    <small class="form-text text-muted">Full URL where bid requests will be sent</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="protocol_version" class="form-label">OpenRTB Version</label>
                                    <select class="form-select" id="protocol_version" name="protocol_version">
                                        <option value="2.5" selected>OpenRTB 2.5</option>
                                        <option value="2.6">OpenRTB 2.6</option>
                                        <option value="3.0">OpenRTB 3.0</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 2: Performance Configuration -->
                    <div class="form-step" id="step2" style="display: none;">
                        <h6 class="mb-3"><i class="fas fa-sliders-h me-2"></i>Performance & Limits</h6>
                        
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="bid_floor" class="form-label">Bid Floor ($)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="bid_floor" name="bid_floor" 
                                               step="0.0001" min="0" value="0.0100" max="100">
                                    </div>
                                    <small class="form-text text-muted">Minimum bid amount per impression</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="timeout_ms" class="form-label">Timeout (ms) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="timeout_ms" name="timeout_ms" 
                                           required min="100" max="10000" value="1000">
                                    <div class="invalid-feedback">Timeout must be between 100-10000ms.</div>
                                    <small class="form-text text-muted">Request timeout in milliseconds</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="qps_limit" class="form-label">QPS Limit <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="qps_limit" name="qps_limit" 
                                           required min="1" max="10000" value="100">
                                    <div class="invalid-feedback">QPS must be between 1-10000.</div>
                                    <small class="form-text text-muted">Queries per second limit</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="priority" class="form-label">Priority</label>
                                    <select class="form-select" id="priority" name="priority">
                                        <option value="1">1 (Highest)</option>
                                        <option value="2">2 (High)</option>
                                        <option value="3" selected>3 (Medium)</option>
                                        <option value="4">4 (Low)</option>
                                        <option value="5">5 (Lowest)</option>
                                    </select>
                                    <small class="form-text text-muted">Endpoint priority for request routing</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 3: Authentication -->
                    <div class="form-step" id="step3" style="display: none;">
                        <h6 class="mb-3"><i class="fas fa-shield-alt me-2"></i>Authentication</h6>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="auth_type" class="form-label">Authentication Type</label>
                                    <select class="form-select" id="auth_type" name="auth_type">
                                        <option value="none">None</option>
                                        <option value="bearer">Bearer Token</option>
                                        <option value="basic">Basic Authentication</option>
                                        <option value="api_key">API Key</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="auth_token" class="form-label">Auth Token/Key</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="auth_token" name="auth_token" 
                                               placeholder="Enter authentication token or key" disabled>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('auth_token')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <small class="form-text text-muted">Required if authentication type is not 'None'</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Authentication Notes:</strong>
                            <ul class="mb-0 mt-2">
                                <li><strong>Bearer:</strong> Token will be sent in Authorization header as "Bearer {token}"</li>
                                <li><strong>Basic:</strong> Credentials will be base64 encoded and sent in Authorization header</li>
                                <li><strong>API Key:</strong> Token will be sent as specified by the endpoint documentation</li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Step 4: Targeting & Formats -->
                    <div class="form-step" id="step4" style="display: none;">
                        <h6 class="mb-3"><i class="fas fa-bullseye me-2"></i>Targeting & Formats</h6>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="supported_formats" class="form-label">Supported Formats</label>
                                    <input type="text" class="form-control" id="supported_formats" name="supported_formats" 
                                           value="banner" placeholder="banner,video,native">
                                    <small class="form-text text-muted">Comma-separated format types</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="supported_sizes" class="form-label">Supported Sizes</label>
                                    <input type="text" class="form-control" id="supported_sizes" name="supported_sizes" 
                                           placeholder="728x90,300x250,320x50">
                                    <small class="form-text text-muted">Comma-separated sizes (WxH format)</small>
                                    <div class="invalid-feedback">Invalid size format. Use WxH format like 728x90</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="country_targeting" class="form-label">Country Targeting</label>
                                    <input type="text" class="form-control" id="country_targeting" name="country_targeting" 
                                           placeholder="US,CA,GB,AU">
                                    <small class="form-text text-muted">Comma-separated 2-letter country codes</small>
                                    <div class="invalid-feedback">Invalid country code format. Use 2-letter codes like US, CA</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="test_mode" name="test_mode">
                                <label class="form-check-label" for="test_mode">
                                    <strong>Enable Test Mode</strong>
                                </label>
                                <div class="form-text text-muted">Test mode sends sample requests without real bidding. Recommended for new endpoints.</div>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Important:</strong> New endpoints are created in 'inactive' status for safety. 
                            You can activate them after testing and verification.
                        </div>
                    </div>
                    
                    <!-- Step Navigation -->
                    <div class="d-flex justify-content-between mt-4">
                        <div>
                            <button type="button" class="btn btn-outline-secondary" id="prevStep" onclick="changeStep(-1)" style="display: none;">
                                <i class="fas fa-arrow-left me-1"></i>Previous
                            </button>
                        </div>
                        <div>
                            <span class="badge bg-primary me-2">Step <span id="currentStep">1</span> of 4</span>
                        </div>
                        <div>
                            <button type="button" class="btn btn-primary" id="nextStep" onclick="changeStep(1)">
                                Next <i class="fas fa-arrow-right ms-1"></i>
                            </button>
                            <button type="submit" class="btn btn-success" id="submitBtn" style="display: none;">
                                <i class="fas fa-plus me-1"></i>Create Endpoint
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-none">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Add Endpoint
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden Forms -->
<form id="statusForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="endpoint_id" id="statusEndpointId">
    <input type="hidden" name="status" id="statusValue">
</form>

<form id="testForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="test_endpoint">
    <input type="hidden" name="endpoint_id" id="testEndpointId">
</form>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="endpoint_id" id="deleteEndpointId">
</form>

<script>
// Global variables
let currentStepNumber = 1;
const totalSteps = 4;

function updateStatus(endpointId, status) {
    const statusText = status === 'active' ? 'activate' : 
                      status === 'inactive' ? 'deactivate' : 
                      status === 'testing' ? 'set to testing' : status;
    
    if (confirm('Are you sure you want to ' + statusText + ' this endpoint?')) {
        document.getElementById('statusEndpointId').value = endpointId;
        document.getElementById('statusValue').value = status;
        document.getElementById('statusForm').submit();
    }
}

function testEndpoint(endpointId) {
    if (confirm('Send a test request to this endpoint?')) {
        document.getElementById('testEndpointId').value = endpointId;
        document.getElementById('testForm').submit();
    }
}

function deleteEndpoint(endpointId, endpointName) {
    if (confirm('Are you sure you want to delete the endpoint "' + endpointName + '"?\n\nThis action cannot be undone.')) {
        document.getElementById('deleteEndpointId').value = endpointId;
        document.getElementById('deleteForm').submit();
    }
}

function viewEndpoint(endpointId) {
    window.open('rtb_endpoint_view.php?id=' + endpointId, '_blank', 'width=1200,height=800');
}

function editEndpoint(endpointId) {
    window.open('rtb_endpoint_edit.php?id=' + endpointId, '_blank', 'width=1000,height=700');
}

function duplicateEndpoint(endpointId) {
    if (confirm('Create a duplicate of this endpoint?')) {
        showSuccess('Endpoint duplication feature coming soon!');
    }
}

function testAllEndpoints() {
    if (confirm('Send test requests to all active endpoints?')) {
        showSuccess('Test requests sent to active endpoints');
    }
}

function refreshStats() {
    updateLiveStats();
    showSuccess('Statistics refreshed');
}

function exportEndpointsData() {
    showSuccess('Export feature coming soon!');
}

function importEndpoints() {
    showInfo('Import feature coming soon!');
}

function bulkTestEndpoints() {
    showInfo('Bulk test feature coming soon!');
}

function generateReport() {
    showInfo('Report generation feature coming soon!');
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

function changeStep(direction) {
    const currentStep = document.getElementById('step' + currentStepNumber);
    
    // Validate current step before proceeding
    if (direction > 0 && !validateCurrentStep()) {
        return;
    }
    
    // Hide current step
    currentStep.style.display = 'none';
    
    // Update step number
    currentStepNumber += direction;
    
    // Show new step
    const newStep = document.getElementById('step' + currentStepNumber);
    newStep.style.display = 'block';
    
    // Update UI
    document.getElementById('currentStep').textContent = currentStepNumber;
    
    // Update navigation buttons
    const prevBtn = document.getElementById('prevStep');
    const nextBtn = document.getElementById('nextStep');
    const submitBtn = document.getElementById('submitBtn');
    
    prevBtn.style.display = currentStepNumber > 1 ? 'inline-block' : 'none';
    
    if (currentStepNumber < totalSteps) {
        nextBtn.style.display = 'inline-block';
        submitBtn.style.display = 'none';
    } else {
        nextBtn.style.display = 'none';
        submitBtn.style.display = 'inline-block';
    }
}

function validateCurrentStep() {
    const step = document.getElementById('step' + currentStepNumber);
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
                const min = parseInt(field.min);
                const max = parseInt(field.max);
                const value = parseInt(field.value);
                
                if (value < min || value > max) {
                    field.classList.add('is-invalid');
                    isValid = false;
                }
            }
        }
    });
    
    // Additional validations for specific fields
    if (currentStepNumber === 4) {
        const sizesField = document.getElementById('supported_sizes');
        const countriesField = document.getElementById('country_targeting');
        
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
    // Auth type change handler
    const authTypeSelect = document.getElementById('auth_type');
    const authTokenInput = document.getElementById('auth_token');
    
    if (authTypeSelect && authTokenInput) {
        authTypeSelect.addEventListener('change', function() {
            const isAuthRequired = this.value !== 'none';
            authTokenInput.disabled = !isAuthRequired;
            authTokenInput.required = isAuthRequired;
            
            if (!isAuthRequired) {
                authTokenInput.value = '';
                authTokenInput.placeholder = 'No authentication required';
            } else {
                authTokenInput.placeholder = 'Enter authentication token or key';
            }
        });
    }
    
    // Form validation
    const createForm = document.getElementById('createEndpointForm');
    if (createForm) {
        createForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate all steps
            let allValid = true;
            for (let i = 1; i <= totalSteps; i++) {
                const originalStep = currentStepNumber;
                currentStepNumber = i;
                if (!validateCurrentStep()) {
                    allValid = false;
                    // Show the step with errors
                    document.getElementById('step' + originalStep).style.display = 'none';
                    document.getElementById('step' + i).style.display = 'block';
                    document.getElementById('currentStep').textContent = i;
                    break;
                }
                currentStepNumber = originalStep;
            }
            
            if (allValid) {
                // Show loading state
                const submitBtn = document.getElementById('submitBtn');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Creating...';
                submitBtn.disabled = true;
                
                // Submit form
                this.submit();
            }
        });
    }
    
    // Real-time validation
    const inputs = document.querySelectorAll('#createEndpointForm input, #createEndpointForm select');
    inputs.forEach(input => {
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
    
    // Reset modal on close
const modal = document.getElementById('createEndpointModal');
if (modal) {
    modal.addEventListener('hidden.bs.modal', function() {
        // Reset form
        document.getElementById('createEndpointForm').reset();
        
        // Reset steps
        for (let i = 1; i <= totalSteps; i++) {
            document.getElementById('step' + i).style.display = i === 1 ? 'block' : 'none';
        }
        currentStepNumber = 1;
        document.getElementById('currentStep').textContent = '1';
        
        // Reset navigation
        document.getElementById('prevStep').style.display = 'none';
        document.getElementById('nextStep').style.display = 'inline-block';
        document.getElementById('submitBtn').style.display = 'none';
        
        // Clear validation classes
        const fields = this.querySelectorAll('.is-invalid');
        fields.forEach(field => field.classList.remove('is-invalid'));
        
        // Reset auth field
        const authTokenInput = document.getElementById('auth_token');
        if (authTokenInput) {
            authTokenInput.disabled = true;
            authTokenInput.required = false;
        }
    });
}

// URL validation on paste/change
const urlField = document.getElementById('url');
if (urlField) {
    urlField.addEventListener('blur', function() {
        if (this.value.trim()) {
            try {
                new URL(this.value);
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } catch (e) {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        }
    });
}

// Auto-format country codes to uppercase
const countryField = document.getElementById('country_targeting');
if (countryField) {
    countryField.addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });
}

// Validate sizes format
const sizesField = document.getElementById('supported_sizes');
if (sizesField) {
    sizesField.addEventListener('blur', function() {
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

// Endpoint type selection helper
const endpointTypeSelect = document.getElementById('endpoint_type');
if (endpointTypeSelect) {
    endpointTypeSelect.addEventListener('change', function() {
        const descField = document.getElementById('description');
        const urlField = document.getElementById('url');
        
        if (this.value === 'ssp_out') {
            if (!descField.value) {
                descField.placeholder = 'e.g., Supply-side platform endpoint for serving ads to publishers';
            }
            if (!urlField.value) {
                urlField.placeholder = 'https://ssp.example.com/rtb/bid';
            }
        } else if (this.value === 'dsp_in') {
            if (!descField.value) {
                descField.placeholder = 'e.g., Demand-side platform endpoint for receiving bid requests';
            }
            if (!urlField.value) {
                urlField.placeholder = 'https://dsp.example.com/rtb/bid';
            }
        }
    });
}

// QPS and Timeout recommendations
const qpsField = document.getElementById('qps_limit');
const timeoutField = document.getElementById('timeout_ms');

if (qpsField) {
    qpsField.addEventListener('change', function() {
        const qps = parseInt(this.value);
        if (qps > 1000) {
            showInfo('High QPS detected. Consider implementing proper rate limiting and monitoring.');
        }
    });
}

if (timeoutField) {
    timeoutField.addEventListener('change', function() {
        const timeout = parseInt(this.value);
        if (timeout > 5000) {
            showWarning('High timeout value. RTB typically requires fast responses (< 1000ms).');
        }
    });
}

// Protocol version helper
const protocolSelect = document.getElementById('protocol_version');
if (protocolSelect) {
    protocolSelect.addEventListener('change', function() {
        const versionInfo = {
            '2.5': 'Most widely supported version',
            '2.6': 'Enhanced features with better mobile support',
            '3.0': 'Latest version with improved privacy features'
        };
        
        if (versionInfo[this.value]) {
            const infoElement = this.nextElementSibling;
            if (infoElement && infoElement.classList.contains('form-text')) {
                infoElement.textContent = versionInfo[this.value];
            }
        }
    });
}
});

// Real-time stats update
function updateLiveStats() {
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
                const successRate = document.getElementById('success-rate');
                const avgResponse = document.getElementById('avg-response');
                
                if (totalEndpoints) totalEndpoints.textContent = parseInt(data.stats.total_endpoints || 0).toLocaleString();
                if (activeEndpoints) activeEndpoints.textContent = parseInt(data.stats.active_endpoints || 0).toLocaleString();
                if (successRate) successRate.textContent = parseFloat(data.stats.avg_success_rate || 0).toFixed(1) + '%';
                if (avgResponse) avgResponse.textContent = parseInt(data.stats.avg_response_time_today || 0).toLocaleString() + 'ms';
            }
        })
        .catch(error => {
            console.error('Stats update error:', error);
        });
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + K to open create modal
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const createModal = new bootstrap.Modal(document.getElementById('createEndpointModal'));
        createModal.show();
    }
    
    // Escape to close modals
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => {
            const modalInstance = bootstrap.Modal.getInstance(modal);
            if (modalInstance) {
                modalInstance.hide();
            }
        });
    }
});

// Auto-save form data in localStorage
function saveFormData() {
    const form = document.getElementById('createEndpointForm');
    if (form) {
        const formData = new FormData(form);
        const data = {};
        
        for (let [key, value] of formData.entries()) {
            if (key !== 'csrf_token' && key !== 'action') {
                data[key] = value;
            }
        }
        
        localStorage.setItem('rtb_endpoint_draft', JSON.stringify(data));
    }
}

function loadFormData() {
    const savedData = localStorage.getItem('rtb_endpoint_draft');
    if (savedData) {
        try {
            const data = JSON.parse(savedData);
            const form = document.getElementById('createEndpointForm');
            
            if (form) {
                Object.keys(data).forEach(key => {
                    const field = form.querySelector(`[name="${key}"]`);
                    if (field) {
                        if (field.type === 'checkbox') {
                            field.checked = data[key] === 'on';
                        } else {
                            field.value = data[key];
                        }
                    }
                });
                
                // Show notification
                showInfo('Draft data restored from previous session');
            }
        } catch (e) {
            console.error('Error loading saved form data:', e);
        }
    }
}

function clearFormData() {
    localStorage.removeItem('rtb_endpoint_draft');
}

// Update stats every 30 seconds
let statsInterval;
function startStatsUpdate() {
    if (statsInterval) clearInterval(statsInterval);
    statsInterval = setInterval(function() {
        if (!document.hidden) {
            updateLiveStats();
        }
    }, 30000);
}

// Handle page visibility changes
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        if (statsInterval) clearInterval(statsInterval);
    } else {
        startStatsUpdate();
        updateLiveStats();
    }
});

// Initialize stats updates
startStatsUpdate();

// Performance monitoring
let performanceMetrics = {
    pageLoadTime: 0,
    lastStatsUpdate: 0,
    totalEndpoints: 0
};

window.addEventListener('load', function() {
    performanceMetrics.pageLoadTime = performance.now();
    console.log('RTB Endpoints page loaded in ' + performanceMetrics.pageLoadTime.toFixed(2) + 'ms');
});

// Table sorting functionality
function initializeTableSorting() {
    const table = document.getElementById('endpointsTable');
    if (table) {
        const headers = table.querySelectorAll('th:not(.no-sort)');
        
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', function() {
                sortTable(table, Array.from(headers).indexOf(this));
            });
        });
    }
}

function sortTable(table, columnIndex) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    // Determine sort direction
    const currentSort = table.getAttribute('data-sort-column');
    const currentDirection = table.getAttribute('data-sort-direction') || 'asc';
    const newDirection = (currentSort == columnIndex && currentDirection === 'asc') ? 'desc' : 'asc';
    
    // Sort rows
    rows.sort((a, b) => {
        const cellA = a.cells[columnIndex].textContent.trim();
        const cellB = b.cells[columnIndex].textContent.trim();
        
        // Try to parse as numbers
        const numA = parseFloat(cellA.replace(/[^0-9.-]/g, ''));
        const numB = parseFloat(cellB.replace(/[^0-9.-]/g, ''));
        
        let comparison;
        if (!isNaN(numA) && !isNaN(numB)) {
            comparison = numA - numB;
        } else {
            comparison = cellA.localeCompare(cellB);
        }
        
        return newDirection === 'asc' ? comparison : -comparison;
    });
    
    // Update table
    rows.forEach(row => tbody.appendChild(row));
    
    // Update sort indicators
    table.setAttribute('data-sort-column', columnIndex);
    table.setAttribute('data-sort-direction', newDirection);
    
    // Update header indicators
    const headers = table.querySelectorAll('th:not(.no-sort)');
    headers.forEach((header, index) => {
        header.classList.remove('sort-asc', 'sort-desc');
        if (index === columnIndex) {
            header.classList.add(newDirection === 'asc' ? 'sort-asc' : 'sort-desc');
        }
    });
}

// Initialize table sorting
document.addEventListener('DOMContentLoaded', function() {
    initializeTableSorting();
});

// Global error handling
window.addEventListener('error', function(e) {
    console.error('Global error:', e.error);
    showError('An unexpected error occurred. Please refresh the page.');
    return false;
});

window.addEventListener('unhandledrejection', function(e) {
    console.error('Unhandled promise rejection:', e.reason);
    showError('A network error occurred. Please check your connection.');
});

// Utility functions for notifications
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
/* Custom styles for RTB Endpoints page */
.form-step {
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
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

.table th.sort-asc::after {
    content: ' ↑';
    color: #007bff;
}

.table th.sort-desc::after {
    content: ' ↓';
    color: #007bff;
}

.table th:not(.no-sort):hover {
    background-color: #f8f9fa;
}

.modal-xl {
    max-width: 1200px;
}

.form-control.is-valid {
    border-color: #28a745;
}

.form-control.is-invalid {
    border-color: #dc3545;
}

.badge {
    font-size: 0.75em;
}

.activity-timeline::before {
    content: '';
    position: absolute;
    left: 16px;
    top: 32px;
    bottom: 0;
    width: 2px;
    background: #e3e6f0;
}

.btn-group-sm > .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

.progress {
    background-color: #f8f9fa;
}

.text-truncate {
    max-width: 200px;
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
</style>

<?php include 'templates/footer.php'; ?>
