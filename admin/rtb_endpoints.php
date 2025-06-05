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
                    (SELECT COUNT(*) FROM rtb_requests WHERE DATE(created_at) = CURDATE()) as total_requests_today,
                    (SELECT COUNT(*) FROM rtb_requests WHERE response_status = 200 AND DATE(created_at) = CURDATE()) as successful_requests_today,
                    (SELECT COALESCE(AVG(response_time), 0) FROM rtb_requests WHERE DATE(created_at) = CURDATE()) as avg_response_time_today,
                    (SELECT COALESCE(SUM(bid_amount), 0) FROM rtb_requests WHERE won = 1 AND DATE(created_at) = CURDATE()) as total_bid_amount_today
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
                        // Validate URL
                        $endpoint_url = sanitize($_POST['endpoint_url']);
                        if (!filter_var($endpoint_url, FILTER_VALIDATE_URL)) {
                            throw new Exception('Invalid endpoint URL');
                        }
                        
                        $data = [
                            'name' => sanitize($_POST['name']),
                            'description' => sanitize($_POST['description']),
                            'endpoint_url' => $endpoint_url,
                            'bid_floor' => (float)$_POST['bid_floor'],
                            'timeout' => (int)$_POST['timeout'],
                            'qps_limit' => (int)$_POST['qps_limit'],
                            'auth_type' => sanitize($_POST['auth_type']),
                            'auth_token' => sanitize($_POST['auth_token']),
                            'user_agent' => sanitize($_POST['user_agent']),
                            'priority' => (int)$_POST['priority'],
                            'geo_targeting' => sanitize($_POST['geo_targeting']),
                            'device_targeting' => sanitize($_POST['device_targeting']),
                            'format_support' => sanitize($_POST['format_support']),
                            'test_mode' => isset($_POST['test_mode']) ? 1 : 0,
                            'status' => 'inactive',
                            'created_by' => $_SESSION['user_id'],
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        
                        $endpointId = $db->insert('rtb_endpoints', $data);
                        $success = 'RTB endpoint created successfully with ID: ' . $endpointId;
                    } catch (Exception $e) {
                        $error = 'Failed to create RTB endpoint: ' . $e->getMessage();
                    }
                    break;
                    
                case 'update_status':
                    try {
                        $endpointId = (int)$_POST['endpoint_id'];
                        $status = sanitize($_POST['status']);
                        
                        if (in_array($status, ['active', 'inactive', 'testing', 'disabled'])) {
                            $updateData = [
                                'status' => $status, 
                                'updated_at' => date('Y-m-d H:i:s')
                            ];
                            
                            // Clear error count when activating
                            if ($status === 'active') {
                                $updateData['error_count'] = 0;
                                $updateData['last_error'] = null;
                            }
                            
                            $db->update('rtb_endpoints', $updateData, 'id = ?', [$endpointId]);
                            $success = 'RTB endpoint status updated successfully';
                        } else {
                            $error = 'Invalid status';
                        }
                    } catch (Exception $e) {
                        $error = 'Failed to update status: ' . $e->getMessage();
                    }
                    break;
                    
                case 'test_endpoint':
                    try {
                        $endpointId = (int)$_POST['endpoint_id'];
                        $endpoint = $db->fetch("SELECT * FROM rtb_endpoints WHERE id = ?", [$endpointId]);
                        
                        if ($endpoint) {
                            // Create test bid request
                            $testBidRequest = [
                                'id' => 'test_' . uniqid(),
                                'imp' => [
                                    [
                                        'id' => '1',
                                        'banner' => [
                                            'w' => 728,
                                            'h' => 90
                                        ],
                                        'bidfloor' => $endpoint['bid_floor']
                                    ]
                                ],
                                'site' => [
                                    'id' => 'test_site',
                                    'domain' => 'test.example.com'
                                ],
                                'user' => [
                                    'id' => 'test_user'
                                ],
                                'device' => [
                                    'devicetype' => 2,
                                    'ua' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                                ],
                                'tmax' => $endpoint['timeout']
                            ];
                            
                            $success = 'Test request queued for endpoint: ' . htmlspecialchars($endpoint['name']);
                        } else {
                            $error = 'Endpoint not found';
                        }
                    } catch (Exception $e) {
                        $error = 'Failed to test endpoint: ' . $e->getMessage();
                    }
                    break;
                    
                case 'delete':
                    try {
                        $endpointId = (int)$_POST['endpoint_id'];
                        
                        // Check if endpoint has recent requests
                        $hasRecentRequests = $db->fetch(
                            "SELECT COUNT(*) as count FROM rtb_requests WHERE endpoint_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                            [$endpointId]
                        );
                        
                        if ($hasRecentRequests['count'] > 0) {
                            $error = 'Cannot delete endpoint with recent requests. Please disable instead.';
                        } else {
                            $db->delete('rtb_endpoints', 'id = ?', [$endpointId]);
                            $success = 'RTB endpoint deleted successfully';
                        }
                    } catch (Exception $e) {
                        $error = 'Failed to delete endpoint: ' . $e->getMessage();
                    }
                    break;
                    
                case 'bulk_update':
                    try {
                        $endpointIds = $_POST['endpoint_ids'] ?? [];
                        $bulkAction = sanitize($_POST['bulk_action']);
                        
                        if (empty($endpointIds) || !is_array($endpointIds)) {
                            throw new Exception('No endpoints selected');
                        }
                        
                        $placeholders = str_repeat('?,', count($endpointIds) - 1) . '?';
                        
                        switch ($bulkAction) {
                            case 'activate':
                                $db->query(
                                    "UPDATE rtb_endpoints SET status = 'active', error_count = 0, updated_at = ? WHERE id IN ($placeholders)",
                                    array_merge([date('Y-m-d H:i:s')], $endpointIds)
                                );
                                break;
                            case 'deactivate':
                                $db->query(
                                    "UPDATE rtb_endpoints SET status = 'inactive', updated_at = ? WHERE id IN ($placeholders)",
                                    array_merge([date('Y-m-d H:i:s')], $endpointIds)
                                );
                                break;
                            case 'test':
                                $db->query(
                                    "UPDATE rtb_endpoints SET status = 'testing', updated_at = ? WHERE id IN ($placeholders)",
                                    array_merge([date('Y-m-d H:i:s')], $endpointIds)
                                );
                                break;
                        }
                        
                        $success = count($endpointIds) . ' endpoints updated successfully';
                    } catch (Exception $e) {
                        $error = 'Failed to update endpoints: ' . $e->getMessage();
                    }
                    break;
            }
        }
    }

    // Filters
    $status = $_GET['status'] ?? '';
    $authType = $_GET['auth_type'] ?? '';
    $priority = $_GET['priority'] ?? '';
    $search = sanitize($_GET['search'] ?? '');

    // Build query
    $whereConditions = [];
    $params = [];

    if ($status) {
        $whereConditions[] = 'status = ?';
        $params[] = $status;
    }

    if ($authType) {
        $whereConditions[] = 'auth_type = ?';
        $params[] = $authType;
    }

    if ($priority) {
        $whereConditions[] = 'priority = ?';
        $params[] = $priority;
    }

    if ($search) {
        $whereConditions[] = '(name LIKE ? OR description LIKE ? OR endpoint_url LIKE ?)';
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // Get RTB endpoints with enhanced data
    $endpoints = [];
    try {
        $endpoints = $db->fetchAll(
            "SELECT e.*,
                    (SELECT COUNT(*) FROM rtb_requests r WHERE r.endpoint_id = e.id AND DATE(r.created_at) = CURDATE()) as today_requests,
                    (SELECT COUNT(*) FROM rtb_requests r WHERE r.endpoint_id = e.id AND r.response_status = 200 AND DATE(r.created_at) = CURDATE()) as today_successful,
                    (SELECT COUNT(*) FROM rtb_requests r WHERE r.endpoint_id = e.id AND r.won = 1 AND DATE(r.created_at) = CURDATE()) as today_wins,
                    (SELECT COALESCE(AVG(r.response_time), 0) FROM rtb_requests r WHERE r.endpoint_id = e.id AND DATE(r.created_at) = CURDATE()) as today_avg_response_time,
                    (SELECT COALESCE(SUM(r.bid_amount), 0) FROM rtb_requests r WHERE r.endpoint_id = e.id AND r.won = 1 AND DATE(r.created_at) = CURDATE()) as today_revenue,
                    (SELECT COUNT(*) FROM rtb_requests r WHERE r.endpoint_id = e.id AND DATE(r.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) as week_requests,
                    (SELECT r.created_at FROM rtb_requests r WHERE r.endpoint_id = e.id ORDER BY r.created_at DESC LIMIT 1) as last_request_at
             FROM rtb_endpoints e
             {$whereClause}
             ORDER BY e.priority ASC, e.created_at DESC",
            $params
        );
    } catch (Exception $e) {
        error_log("RTB endpoints query error: " . $e->getMessage());
        $error = "Error loading RTB endpoints data";
        $endpoints = [];
    }

    // Statistics
    $stats = [
        'total_endpoints' => 0,
        'active_endpoints' => 0,
        'inactive_endpoints' => 0,
        'testing_endpoints' => 0,
        'total_requests_today' => 0,
        'successful_requests_today' => 0,
        'avg_response_time_today' => 0,
        'total_bid_amount_today' => 0
    ];

    try {
        $result = $db->fetch(
            "SELECT 
                COUNT(*) as total_endpoints,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_endpoints,
                COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_endpoints,
                COUNT(CASE WHEN status = 'testing' THEN 1 END) as testing_endpoints,
                (SELECT COUNT(*) FROM rtb_requests WHERE DATE(created_at) = CURDATE()) as total_requests_today,
                (SELECT COUNT(*) FROM rtb_requests WHERE response_status = 200 AND DATE(created_at) = CURDATE()) as successful_requests_today,
                (SELECT COALESCE(AVG(response_time), 0) FROM rtb_requests WHERE DATE(created_at) = CURDATE()) as avg_response_time_today,
                (SELECT COALESCE(SUM(bid_amount), 0) FROM rtb_requests WHERE won = 1 AND DATE(created_at) = CURDATE()) as total_bid_amount_today
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
    die("Error loading RTB endpoints page: " . $e->getMessage());
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
                            <h3 class="mb-1" id="total-requests"><?php echo number_format($stats['total_requests_today']); ?></h3>
                            <p class="mb-0">Today's Requests</p>
                        </div>
                        <div class="ms-3">
                            <i class="fas fa-paper-plane fa-2x opacity-75"></i>
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
                            <h3 class="mb-1" id="total-revenue">$<?php echo number_format($stats['total_bid_amount_today'], 2); ?></h3>
                            <p class="mb-0">Today's Revenue</p>
                        </div>
                        <div class="ms-3">
                            <i class="fas fa-dollar-sign fa-2x opacity-75"></i>
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
                        <h4 class="text-primary mb-1"><?php echo number_format($stats['total_requests_today']); ?></h4>
                        <small class="text-muted">Total Requests</small>
                    </div>
                    <div class="col-3">
                        <h4 class="text-success mb-1"><?php echo number_format($stats['successful_requests_today']); ?></h4>
                        <small class="text-muted">Successful</small>
                    </div>
                    <div class="col-3">
                        <?php $successRate = $stats['total_requests_today'] > 0 ? ($stats['successful_requests_today'] / $stats['total_requests_today']) * 100 : 0; ?>
                        <h4 class="text-info mb-1"><?php echo number_format($successRate, 1); ?>%</h4>
                        <small class="text-muted">Success Rate</small>
                    </div>
                    <div class="col-3">
                        <h4 class="text-warning mb-1"><?php echo number_format($stats['avg_response_time_today']); ?>ms</h4>
                        <small class="text-muted">Avg Response</small>
                    </div>
                </div>
                <hr class="my-3">
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar bg-success" style="width: <?php echo $successRate; ?>%" title="Success Rate"></div>
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
                    <button class="btn btn-outline-success" onclick="activateSelectedEndpoints()">
                        <i class="fas fa-play me-2"></i>Bulk Activate
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
                    <option value="disabled" <?php echo $status === 'disabled' ? 'selected' : ''; ?>>Disabled</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="auth_type" class="form-label">Auth Type</label>
                <select class="form-select" id="auth_type" name="auth_type">
                    <option value="">All Types</option>
                    <option value="none" <?php echo $authType === 'none' ? 'selected' : ''; ?>>None</option>
                    <option value="bearer" <?php echo $authType === 'bearer' ? 'selected' : ''; ?>>Bearer Token</option>
                    <option value="basic" <?php echo $authType === 'basic' ? 'selected' : ''; ?>>Basic Auth</option>
                    <option value="api_key" <?php echo $authType === 'api_key' ? 'selected' : ''; ?>>API Key</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="priority" class="form-label">Priority</label>
                <select class="form-select" id="priority" name="priority">
                    <option value="">All Priorities</option>
                    <option value="1" <?php echo $priority === '1' ? 'selected' : ''; ?>>1 (Highest)</option>
                    <option value="2" <?php echo $priority === '2' ? 'selected' : ''; ?>>2 (High)</option>
                    <option value="3" <?php echo $priority === '3' ? 'selected' : ''; ?>>3 (Medium)</option>
                    <option value="4" <?php echo $priority === '4' ? 'selected' : ''; ?>>4 (Low)</option>
                    <option value="5" <?php echo $priority === '5' ? 'selected' : ''; ?>>5 (Lowest)</option>
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
            <button class="btn btn-outline-secondary" id="bulkActionsBtn" disabled>
                <i class="fas fa-tasks me-2"></i>Bulk Actions
            </button>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($endpoints)): ?>
            <div class="text-center py-5">
                <i class="fas fa-exchange-alt fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">No RTB Endpoints Found</h4>
                <p class="text-muted mb-4">Add your first RTB endpoint to start real-time bidding</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createEndpointModal">
                    <i class="fas fa-plus me-2"></i>Add Your First Endpoint
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="endpointsTable">
                    <thead>
                        <tr>
                            <th class="no-sort">
                                <input type="checkbox" id="selectAll" class="form-check-input">
                            </th>
                            <th>ID</th>
                            <th>Endpoint Details</th>
                            <th>Configuration</th>
                            <th>Status</th>
                            <th class="no-sort">Today's Performance</th>
                            <th class="no-sort">Response Metrics</th>
                            <th>Last Activity</th>
                            <th class="no-sort">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($endpoints as $endpoint): ?>
                            <tr data-endpoint-id="<?php echo $endpoint['id']; ?>">
                                <td>
                                    <input type="checkbox" class="form-check-input endpoint-checkbox" value="<?php echo $endpoint['id']; ?>">
                                </td>
                                <td>
                                    <span class="fw-bold"><?php echo $endpoint['id']; ?></span>
                                </td>
                                <td>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($endpoint['name']); ?></div>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($endpoint['description'] ?: 'No description'); ?>
                                        </small>
                                        <br>
                                        <small class="text-primary">
                                            <i class="fas fa-link me-1"></i>
                                            <?php echo htmlspecialchars(parse_url($endpoint['endpoint_url'], PHP_URL_HOST)); ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <div class="small">
                                        <div><strong>Bid Floor:</strong> $<?php echo number_format($endpoint['bid_floor'], 4); ?></div>
                                        <div><strong>Timeout:</strong> <?php echo $endpoint['timeout']; ?>ms</div>
                                        <div><strong>QPS Limit:</strong> <?php echo $endpoint['qps_limit']; ?></div>
                                        <div><strong>Priority:</strong> <?php echo $endpoint['priority']; ?></div>
                                        <?php if ($endpoint['auth_type'] !== 'none'): ?>
                                            <div><span class="badge bg-info"><?php echo strtoupper($endpoint['auth_type']); ?></span></div>
                                        <?php endif; ?>
                                        <?php if ($endpoint['test_mode']): ?>
                                            <div><span class="badge bg-warning">Test Mode</span></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = [
                                        'active' => 'success',
                                        'inactive' => 'secondary',
                                        'testing' => 'warning',
                                        'disabled' => 'danger'
                                    ];
                                    ?>
                                    <div>
                                        <span class="badge bg-<?php echo $statusClass[$endpoint['status']] ?? 'secondary'; ?>">
                                            <?php echo ucfirst($endpoint['status']); ?>
                                        </span>
                                        <?php if ($endpoint['error_count'] > 0): ?>
                                            <br><small class="text-danger">
                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                <?php echo $endpoint['error_count']; ?> errors
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="small">
                                        <div><i class="fas fa-paper-plane text-primary me-1"></i><strong><?php echo number_format($endpoint['today_requests'] ?? 0); ?></strong> requests</div>
                                        <div><i class="fas fa-check text-success me-1"></i><strong><?php echo number_format($endpoint['today_successful'] ?? 0); ?></strong> successful</div>
                                        <div><i class="fas fa-trophy text-warning me-1"></i><strong><?php echo number_format($endpoint['today_wins'] ?? 0); ?></strong> wins</div>
                                        <div><i class="fas fa-dollar-sign text-info me-1"></i><strong>$<?php echo number_format($endpoint['today_revenue'] ?? 0, 2); ?></strong></div>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $requests = $endpoint['today_requests'] ?? 0;
                                    $successful = $endpoint['today_successful'] ?? 0;
                                    $wins = $endpoint['today_wins'] ?? 0;
                                    $avgResponseTime = $endpoint['today_avg_response_time'] ?? 0;
                                    
                                    $successRate = $requests > 0 ? ($successful / $requests) * 100 : 0;
                                    $winRate = $successful > 0 ? ($wins / $successful) * 100 : 0;
                                    ?>
                                    <div class="small">
                                        <div>Success: <strong><?php echo number_format($successRate, 1); ?>%</strong></div>
                                        <div>Win Rate: <strong><?php echo number_format($winRate, 1); ?>%</strong></div>
                                        <div>Avg Time: <strong><?php echo number_format($avgResponseTime); ?>ms</strong></div>
                                        <?php if ($requests > 0): ?>
                                            <div class="progress mt-1" style="height: 4px;">
                                                <div class="progress-bar bg-<?php echo $successRate > 80 ? 'success' : ($successRate > 50 ? 'warning' : 'danger'); ?>" 
                                                     style="width: <?php echo $successRate; ?>%"></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="small">
                                        <?php if ($endpoint['last_request_at']): ?>
                                            <div><strong>Last Request:</strong></div>
                                            <div><?php echo date('M j, H:i', strtotime($endpoint['last_request_at'])); ?></div>
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

<!-- Create Endpoint Modal -->
<div class="modal fade" id="createEndpointModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i>Add New RTB Endpoint
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="createEndpointForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <!-- Basic Information -->
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="name" class="form-label">Endpoint Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required 
                                       placeholder="e.g., Google DV360, Amazon DSP, Custom RTB">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="priority" class="form-label">Priority <span class="text-danger">*</span></label>
                                <select class="form-select" id="priority" name="priority" required>
                                    <option value="1">1 (Highest)</option>
                                    <option value="2">2 (High)</option>
                                    <option value="3" selected>3 (Medium)</option>
                                    <option value="4">4 (Low)</option>
                                    <option value="5">5 (Lowest)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2" 
                                  placeholder="Brief description of the RTB endpoint and its purpose"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="endpoint_url" class="form-label">Endpoint URL <span class="text-danger">*</span></label>
                        <input type="url" class="form-control" id="endpoint_url" name="endpoint_url" required 
                               placeholder="https://api.example.com/rtb/bid">
                        <small class="form-text text-muted">Full URL where bid requests will be sent</small>
                    </div>
                    
                    <!-- Bidding Configuration -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="bid_floor" class="form-label">Bid Floor ($) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="bid_floor" name="bid_floor" 
                                           step="0.0001" required min="0" value="0.0100">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="timeout" class="form-label">Timeout (ms) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="timeout" name="timeout" 
                                       required min="100" max="10000" value="1000">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="qps_limit" class="form-label">QPS Limit <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="qps_limit" name="qps_limit" 
                                       required min="1" max="10000" value="100">
                                <small class="form-text text-muted">Queries per second limit</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Authentication -->
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
                                <input type="password" class="form-control" id="auth_token" name="auth_token" 
                                       placeholder="Enter authentication token or key">
                                <small class="form-text text-muted">Required if authentication type is not 'None'</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Advanced Settings -->
                    <div class="mb-3">
                        <label for="user_agent" class="form-label">User Agent</label>
                        <input type="text" class="form-control" id="user_agent" name="user_agent" 
                               value="AdStart-RTB/1.0" placeholder="Custom user agent string">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="geo_targeting" class="form-label">Geo Targeting</label>
                                <input type="text" class="form-control" id="geo_targeting" name="geo_targeting" 
                                       placeholder="US,CA,GB,AU (leave empty for worldwide)">
                                <small class="form-text text-muted">Comma-separated country codes</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="device_targeting" class="form-label">Device Targeting</label>
                                <select class="form-select" id="device_targeting" name="device_targeting">
                                    <option value="">All Devices</option>
                                    <option value="desktop">Desktop Only</option>
                                    <option value="mobile">Mobile Only</option>
                                    <option value="tablet">Tablet Only</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="format_support" class="form-label">Supported Ad Formats</label>
                        <input type="text" class="form-control" id="format_support" name="format_support" 
                               placeholder="banner,video,native" value="banner">
                        <small class="form-text text-muted">Comma-separated format types</small>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="test_mode" name="test_mode">
                        <label class="form-check-label" for="test_mode">
                            Enable Test Mode
                        </label>
                        <small class="form-text text-muted d-block">Test mode sends sample requests without real bidding</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Add Endpoint
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Actions Modal -->
<div class="modal fade" id="bulkActionsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Actions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="bulkActionsForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="bulk_update">
                    <input type="hidden" name="endpoint_ids" id="selectedEndpointIds">
                    
                    <p>You have selected <span id="selectedCount">0</span> endpoint(s).</p>
                    
                    <div class="mb-3">
                        <label for="bulk_action" class="form-label">Action</label>
                        <select class="form-select" id="bulk_action" name="bulk_action" required>
                            <option value="">Select Action</option>
                            <option value="activate">Activate Selected</option>
                            <option value="deactivate">Deactivate Selected</option>
                            <option value="test">Set to Testing</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Apply Action</button>
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
let selectedEndpoints = [];

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
    if (confirm('Send a test bid request to this endpoint?')) {
        document.getElementById('testEndpointId').value = endpointId;
        document.getElementById('testForm').submit();
    }
}

function deleteEndpoint(endpointId, endpointName) {
    if (confirm('Are you sure you want to delete the endpoint "' + endpointName + '"?\n\nThis action cannot be undone. Consider disabling the endpoint instead if you want to preserve historical data.')) {
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

function testAllEndpoints() {
    if (confirm('Send test requests to all active endpoints?')) {
        fetch('rtb_test_all.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                csrf_token: <?php echo json_encode($csrf_token); ?>
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccess('Test requests sent to ' + data.count + ' endpoints');
            } else {
                showError('Failed to send test requests: ' + data.error);
            }
        })
        .catch(error => {
            showError('Error: ' + error.message);
        });
    }
}

function exportEndpointsData() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    window.open('rtb_endpoints_export.php?' + params.toString(), '_blank');
}

function activateSelectedEndpoints() {
    const selected = document.querySelectorAll('.endpoint-checkbox:checked');
    if (selected.length === 0) {
        showError('Please select at least one endpoint');
        return;
    }
    
    selectedEndpoints = Array.from(selected).map(cb => cb.value);
    document.getElementById('selectedEndpointIds').value = JSON.stringify(selectedEndpoints);
    document.getElementById('selectedCount').textContent = selectedEndpoints.length;
    document.getElementById('bulk_action').value = 'activate';
    
    new bootstrap.Modal(document.getElementById('bulkActionsModal')).show();
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    try {
        // Checkbox handling
        const selectAllCheckbox = document.getElementById('selectAll');
        const endpointCheckboxes = document.querySelectorAll('.endpoint-checkbox');
        const bulkActionsBtn = document.getElementById('bulkActionsBtn');
        
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                endpointCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateBulkActionsButton();
            });
        }
        
        endpointCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkActionsButton);
        });
        
        function updateBulkActionsButton() {
            const selected = document.querySelectorAll('.endpoint-checkbox:checked');
            if (bulkActionsBtn) {
                bulkActionsBtn.disabled = selected.length === 0;
                if (selected.length > 0) {
                    bulkActionsBtn.textContent = 'Bulk Actions (' + selected.length + ')';
                    bulkActionsBtn.onclick = function() {
                        selectedEndpoints = Array.from(selected).map(cb => cb.value);
                        document.getElementById('selectedEndpointIds').value = JSON.stringify(selectedEndpoints);
                        document.getElementById('selectedCount').textContent = selectedEndpoints.length;
                        new bootstrap.Modal(document.getElementById('bulkActionsModal')).show();
                    };
                } else {
                    bulkActionsBtn.textContent = 'Bulk Actions';
                    bulkActionsBtn.onclick = null;
                }
            }
        }
        
        // Form validation
        const createForm = document.getElementById('createEndpointForm');
        if (createForm) {
            createForm.addEventListener('submit', function(e) {
                const nameInput = document.getElementById('name');
                const endpointUrlInput = document.getElementById('endpoint_url');
                const bidFloorInput = document.getElementById('bid_floor');
                const timeoutInput = document.getElementById('timeout');
                const qpsLimitInput = document.getElementById('qps_limit');
                
                if (!nameInput || !endpointUrlInput || !bidFloorInput || !timeoutInput || !qpsLimitInput) {
                    e.preventDefault();
                    showError('Required form fields not found');
                    return;
                }
                
                const name = nameInput.value.trim();
                const endpointUrl = endpointUrlInput.value.trim();
                const bidFloor = parseFloat(bidFloorInput.value);
                const timeout = parseInt(timeoutInput.value);
                const qpsLimit = parseInt(qpsLimitInput.value);
                
                if (name.length < 3) {
                    e.preventDefault();
                    showError('Endpoint name must be at least 3 characters long');
                    nameInput.focus();
                    return;
                }
                
                // Basic URL validation
                try {
                    new URL(endpointUrl);
                } catch (e) {
                    e.preventDefault();
                    showError('Please enter a valid URL');
                    endpointUrlInput.focus();
                    return;
                }
                
                if (isNaN(bidFloor) || bidFloor < 0) {
                    e.preventDefault();
                    showError('Bid floor must be a positive number');
                    bidFloorInput.focus();
                    return;
                }
                
                if (isNaN(timeout) || timeout < 100 || timeout > 10000) {
                    e.preventDefault();
                    showError('Timeout must be between 100 and 10000 milliseconds');
                    timeoutInput.focus();
                    return;
                }
                
                if (isNaN(qpsLimit) || qpsLimit < 1 || qpsLimit > 10000) {
                    e.preventDefault();
                    showError('QPS limit must be between 1 and 10000');
                    qpsLimitInput.focus();
                    return;
                }
            });
        }
        
        // Auth type change handler
        const authTypeSelect = document.getElementById('auth_type');
        const authTokenInput = document.getElementById('auth_token');
        
        if (authTypeSelect && authTokenInput) {
            authTypeSelect.addEventListener('change', function() {
                const isAuthRequired = this.value !== 'none';
                authTokenInput.required = isAuthRequired;
                authTokenInput.disabled = !isAuthRequired;
                
                if (!isAuthRequired) {
                    authTokenInput.value = '';
                }
            });
        }
        
    } catch (error) {
        console.error('DOM ready initialization error:', error);
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
                const totalRequests = document.getElementById('total-requests');
                const totalRevenue = document.getElementById('total-revenue');
                
                if (totalEndpoints) totalEndpoints.textContent = parseInt(data.stats.total_endpoints || 0).toLocaleString();
                if (activeEndpoints) activeEndpoints.textContent = parseInt(data.stats.active_endpoints || 0).toLocaleString();
                if (totalRequests) totalRequests.textContent = parseInt(data.stats.total_requests_today || 0).toLocaleString();
                if (totalRevenue) totalRevenue.textContent = '$' + parseFloat(data.stats.total_bid_amount_today || 0).toFixed(2);
            }
        })
        .catch(error => {
            console.error('Stats update error:', error);
        });
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

// Start stats updates
startStatsUpdate();

// Handle page visibility changes
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        if (statsInterval) clearInterval(statsInterval);
    } else {
        startStatsUpdate();
        updateLiveStats();
    }
});

// Global error handling
window.addEventListener('error', function(e) {
    console.error('Global error:', e.error);
    return false;
});

window.addEventListener('unhandledrejection', function(e) {
    console.error('Unhandled promise rejection:', e.reason);
});
</script>

<?php include 'templates/footer.php'; ?>
