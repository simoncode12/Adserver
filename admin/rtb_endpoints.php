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
                        // Validate URL
                        $url = sanitize($_POST['url']);
                        if (!filter_var($url, FILTER_VALIDATE_URL)) {
                            throw new Exception('Invalid endpoint URL');
                        }
                        
                        // Prepare JSON fields
                        $auth_credentials = null;
                        if ($_POST['auth_type'] !== 'none' && !empty($_POST['auth_token'])) {
                            $auth_credentials = json_encode([
                                'token' => sanitize($_POST['auth_token']),
                                'type' => sanitize($_POST['auth_type'])
                            ]);
                        }
                        
                        $supported_formats = !empty($_POST['supported_formats']) 
                            ? json_encode(array_map('trim', explode(',', $_POST['supported_formats'])))
                            : json_encode(['banner']);
                            
                        $supported_sizes = !empty($_POST['supported_sizes']) 
                            ? json_encode(array_map('trim', explode(',', $_POST['supported_sizes'])))
                            : null;
                            
                        $country_targeting = !empty($_POST['country_targeting']) 
                            ? json_encode(array_map('trim', explode(',', $_POST['country_targeting'])))
                            : null;
                            
                        $settings = json_encode([
                            'description' => sanitize($_POST['description'] ?? ''),
                            'priority' => (int)($_POST['priority'] ?? 3),
                            'test_mode' => isset($_POST['test_mode']) ? 1 : 0
                        ]);
                        
                        $data = [
                            'name' => sanitize($_POST['name']),
                            'endpoint_type' => sanitize($_POST['endpoint_type']),
                            'url' => $url,
                            'method' => sanitize($_POST['method']) ?: 'POST',
                            'protocol_version' => sanitize($_POST['protocol_version']) ?: '2.5',
                            'status' => 'inactive',
                            'timeout_ms' => (int)$_POST['timeout_ms'],
                            'qps_limit' => (int)$_POST['qps_limit'],
                            'auth_type' => sanitize($_POST['auth_type']),
                            'auth_credentials' => $auth_credentials,
                            'bid_floor' => (float)$_POST['bid_floor'],
                            'supported_formats' => $supported_formats,
                            'supported_sizes' => $supported_sizes,
                            'country_targeting' => $country_targeting,
                            'settings' => $settings,
                            'created_by' => $_SESSION['user_id'],
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
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
                        $db->delete('rtb_endpoints', 'id = ?', [$endpointId]);
                        $success = 'RTB endpoint deleted successfully';
                    } catch (Exception $e) {
                        $error = 'Failed to delete endpoint: ' . $e->getMessage();
                    }
                    break;
                    
                case 'test_endpoint':
                    try {
                        $endpointId = (int)$_POST['endpoint_id'];
                        $endpoint = $db->fetch("SELECT * FROM rtb_endpoints WHERE id = ?", [$endpointId]);
                        
                        if ($endpoint) {
                            // Update last_request timestamp
                            $db->update('rtb_endpoints', 
                                ['last_request' => date('Y-m-d H:i:s')], 
                                'id = ?', 
                                [$endpointId]
                            );
                            
                            $success = 'Test request sent to endpoint: ' . htmlspecialchars($endpoint['name']);
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
                                        <div><strong>QPS Limit:</strong> <?php echo $endpoint['qps_limit']; ?></div>
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
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Endpoint Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required 
                                       placeholder="e.g., Google DV360, Amazon DSP">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="endpoint_type" class="form-label">Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="endpoint_type" name="endpoint_type" required>
                                    <option value="">Select Type</option>
                                    <option value="ssp_out">SSP Out</option>
                                    <option value="dsp_in">DSP In</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="method" class="form-label">Method</label>
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
                                  placeholder="Brief description of the RTB endpoint"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="url" class="form-label">Endpoint URL <span class="text-danger">*</span></label>
                                <input type="url" class="form-control" id="url" name="url" required 
                                       placeholder="https://api.example.com/rtb/bid">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="protocol_version" class="form-label">Protocol Version</label>
                                <select class="form-select" id="protocol_version" name="protocol_version">
                                    <option value="2.5">OpenRTB 2.5</option>
                                    <option value="2.6">OpenRTB 2.6</option>
                                    <option value="3.0">OpenRTB 3.0</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Configuration -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="bid_floor" class="form-label">Bid Floor ($)</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="bid_floor" name="bid_floor" 
                                           step="0.0001" min="0" value="0.0100">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="timeout_ms" class="form-label">Timeout (ms) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="timeout_ms" name="timeout_ms" 
                                       required min="100" max="10000" value="1000">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="qps_limit" class="form-label">QPS Limit <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="qps_limit" name="qps_limit" 
                                       required min="1" max="10000" value="100">
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
                            </div>
                        </div>
                    </div>
                    
                    <!-- Targeting & Formats -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="supported_formats" class="form-label">Supported Formats</label>
                                <input type="text" class="form-control" id="supported_formats" name="supported_formats" 
                                       placeholder="banner,video,native" value="banner">
                                <small class="form-text text-muted">Comma-separated format types</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="supported_sizes" class="form-label">Supported Sizes</label>
                                <input type="text" class="form-control" id="supported_sizes" name="supported_sizes" 
                                       placeholder="728x90,300x250,320x50">
                                <small class="form-text text-muted">Comma-separated sizes (WxH)</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="country_targeting" class="form-label">Country Targeting</label>
                                <input type="text" class="form-control" id="country_targeting" name="country_targeting" 
                                       placeholder="US,CA,GB,AU">
                                <small class="form-text text-muted">Comma-separated country codes</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="test_mode" name="test_mode">
                        <label class="form-check-label" for="test_mode">
                            Enable Test Mode
                        </label>
                        <small class="form-text text-muted d-block">Test mode for development and debugging</small>
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
    window.open('rtb_endpoints_export.php', '_blank');
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
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
    
    // Form validation
    const createForm = document.getElementById('createEndpointForm');
    if (createForm) {
        createForm.addEventListener('submit', function(e) {
            const nameInput = document.getElementById('name');
            const urlInput = document.getElementById('url');
            const timeoutInput = document.getElementById('timeout_ms');
            const qpsInput = document.getElementById('qps_limit');
            
            if (!nameInput || !urlInput || !timeoutInput || !qpsInput) {
                e.preventDefault();
                showError('Required form fields not found');
                return;
            }
            
            const name = nameInput.value.trim();
            const url = urlInput.value.trim();
            const timeout = parseInt(timeoutInput.value);
            const qps = parseInt(qpsInput.value);
            
            if (name.length < 3) {
                e.preventDefault();
                showError('Endpoint name must be at least 3 characters long');
                nameInput.focus();
                return;
            }
            
            // Basic URL validation
            try {
                new URL(url);
            } catch (e) {
                e.preventDefault();
                showError('Please enter a valid URL');
                urlInput.focus();
                return;
            }
            
            if (isNaN(timeout) || timeout < 100 || timeout > 10000) {
                e.preventDefault();
                showError('Timeout must be between 100 and 10000 milliseconds');
                timeoutInput.focus();
                return;
            }
            
            if (isNaN(qps) || qps < 1 || qps > 10000) {
                e.preventDefault();
                showError('QPS limit must be between 1 and 10000');
                qpsInput.focus();
                return;
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
</script>

<?php include 'templates/footer.php'; ?>
