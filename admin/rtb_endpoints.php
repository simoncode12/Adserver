<?php
$pageTitle = 'RTB Endpoints';
require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth = new Auth();
$auth->requireAuth(USER_TYPE_ADMIN);

$db = Database::getInstance();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Invalid security token';
    } else {
        switch ($action) {
            case 'create':
                $data = [
                    'name' => sanitize($_POST['name']),
                    'endpoint_type' => $_POST['endpoint_type'],
                    'url' => $_POST['url'],
                    'method' => $_POST['method'],
                    'protocol_version' => $_POST['protocol_version'],
                    'timeout_ms' => (int)$_POST['timeout_ms'],
                    'qps_limit' => (int)$_POST['qps_limit'],
                    'auth_type' => $_POST['auth_type'],
                    'bid_floor' => (float)$_POST['bid_floor'],
                    'supported_formats' => json_encode($_POST['supported_formats'] ?? []),
                    'created_by' => $_SESSION['user_id']
                ];
                
                if ($data['auth_type'] !== 'none') {
                    $authData = [];
                    switch ($data['auth_type']) {
                        case 'api_key':
                            $authData['api_key'] = $_POST['api_key'];
                            break;
                        case 'basic':
                            $authData['username'] = $_POST['auth_username'];
                            $authData['password'] = $_POST['auth_password'];
                            break;
                        case 'bearer':
                            $authData['token'] = $_POST['auth_token'];
                            break;
                    }
                    $data['auth_credentials'] = json_encode($authData);
                }
                
                $db->insert('rtb_endpoints', $data);
                $success = 'RTB endpoint created successfully';
                break;
                
            case 'update_status':
                $endpointId = (int)$_POST['endpoint_id'];
                $status = $_POST['status'];
                
                if (in_array($status, ['active', 'inactive', 'testing'])) {
                    $db->update('rtb_endpoints', 
                        ['status' => $status], 
                        'id = ?', 
                        [$endpointId]
                    );
                    $success = 'Endpoint status updated successfully';
                }
                break;
                
            case 'delete':
                $endpointId = (int)$_POST['endpoint_id'];
                $db->delete('rtb_endpoints', 'id = ?', [$endpointId]);
                $success = 'Endpoint deleted successfully';
                break;
                
            case 'test':
                $endpointId = (int)$_POST['endpoint_id'];
                // Test endpoint functionality
                $testResult = testRTBEndpoint($endpointId);
                if ($testResult['success']) {
                    $success = 'Endpoint test successful: ' . $testResult['message'];
                } else {
                    $error = 'Endpoint test failed: ' . $testResult['message'];
                }
                break;
        }
    }
}

// Get endpoints
$endpoints = $db->fetchAll(
    "SELECT e.*, u.username as created_by_name,
            (SELECT COUNT(*) FROM rtb_logs rl WHERE rl.endpoint_id = e.id AND DATE(rl.created_at) = CURDATE()) as today_requests,
            (SELECT COUNT(*) FROM rtb_logs rl WHERE rl.endpoint_id = e.id AND rl.status = 'success' AND DATE(rl.created_at) = CURDATE()) as today_success
     FROM rtb_endpoints e
     LEFT JOIN users u ON e.created_by = u.id
     ORDER BY e.created_at DESC"
);

// Statistics
$stats = $db->fetch(
    "SELECT 
        COUNT(*) as total_endpoints,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_endpoints,
        COUNT(CASE WHEN status = 'testing' THEN 1 END) as testing_endpoints,
        COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_endpoints
     FROM rtb_endpoints"
);

$csrf_token = generateCSRFToken();

// Test function
function testRTBEndpoint($endpointId) {
    global $db;
    
    $endpoint = $db->fetch("SELECT * FROM rtb_endpoints WHERE id = ?", [$endpointId]);
    if (!$endpoint) {
        return ['success' => false, 'message' => 'Endpoint not found'];
    }
    
    // Create test bid request
    $testRequest = [
        'id' => 'test_' . time(),
        'imp' => [
            [
                'id' => '1',
                'bidfloor' => 0.1,
                'banner' => [
                    'w' => 300,
                    'h' => 250
                ]
            ]
        ],
        'site' => [
            'id' => 'test_site',
            'domain' => 'test.com'
        ],
        'device' => [
            'ua' => 'Test User Agent',
            'ip' => '127.0.0.1'
        ],
        'at' => 1,
        'tmax' => $endpoint['timeout_ms']
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $endpoint['url'],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($testRequest),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT_MS => $endpoint['timeout_ms'],
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-openrtb-version: ' . $endpoint['protocol_version']
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $responseTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000;
    curl_close($ch);
    
    if ($httpCode === 200) {
        return ['success' => true, 'message' => "Response time: {$responseTime}ms"];
    } else {
        return ['success' => false, 'message' => "HTTP {$httpCode}"];
    }
}

include 'templates/header.php';
?>

<div id="alerts-container">
    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="fas fa-exchange-alt fa-2x mb-2"></i>
                <h4><?php echo formatNumber($stats['total_endpoints']); ?></h4>
                <p class="mb-0">Total Endpoints</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card-success">
            <div class="card-body text-center">
                <i class="fas fa-check fa-2x mb-2"></i>
                <h4><?php echo formatNumber($stats['active_endpoints']); ?></h4>
                <p class="mb-0">Active Endpoints</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card-warning">
            <div class="card-body text-center">
                <i class="fas fa-flask fa-2x mb-2"></i>
                <h4><?php echo formatNumber($stats['testing_endpoints']); ?></h4>
                <p class="mb-0">Testing</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card-info">
            <div class="card-body text-center">
                <i class="fas fa-pause fa-2x mb-2"></i>
                <h4><?php echo formatNumber($stats['inactive_endpoints']); ?></h4>
                <p class="mb-0">Inactive</p>
            </div>
        </div>
    </div>
</div>

<!-- RTB Endpoints Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>RTB Endpoints</h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="fas fa-plus me-2"></i>Add Endpoint
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>URL</th>
                        <th>Status</th>
                        <th>Success Rate</th>
                        <th>Avg Response</th>
                        <th>Today</th>
                        <th>QPS Limit</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($endpoints as $endpoint): ?>
                        <tr>
                            <td><?php echo $endpoint['id']; ?></td>
                            <td>
                                <div class="fw-bold"><?php echo htmlspecialchars($endpoint['name']); ?></div>
                                <small class="text-muted">Floor: <?php echo formatCurrency($endpoint['bid_floor']); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $endpoint['endpoint_type'] === 'ssp_out' ? 'primary' : 'secondary'; ?>">
                                    <?php echo strtoupper($endpoint['endpoint_type']); ?>
                                </span>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars(parse_url($endpoint['url'], PHP_URL_HOST)); ?>
                                </small>
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
                                <div class="d-flex align-items-center">
                                    <span><?php echo number_format($endpoint['success_rate'], 1); ?>%</span>
                                    <div class="progress ms-2" style="width: 50px; height: 6px;">
                                        <div class="progress-bar bg-<?php echo $endpoint['success_rate'] > 80 ? 'success' : ($endpoint['success_rate'] > 50 ? 'warning' : 'danger'); ?>" 
                                             style="width: <?php echo $endpoint['success_rate']; ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo number_format($endpoint['avg_response_time']); ?>ms</td>
                            <td>
                                <div><?php echo formatNumber($endpoint['today_requests']); ?> req</div>
                                <small class="text-success"><?php echo formatNumber($endpoint['today_success']); ?> success</small>
                            </td>
                            <td><?php echo formatNumber($endpoint['qps_limit']); ?></td>
                            <td>
                                <small><?php echo date('M j, Y', strtotime($endpoint['created_at'])); ?></small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" onclick="testEndpoint(<?php echo $endpoint['id']; ?>)">
                                        <i class="fas fa-play"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="fas fa-cog"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <button class="dropdown-item" onclick="updateStatus(<?php echo $endpoint['id']; ?>, 'active')">
                                                <i class="fas fa-play me-2 text-success"></i>Activate
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item" onclick="updateStatus(<?php echo $endpoint['id']; ?>, 'testing')">
                                                <i class="fas fa-flask me-2 text-warning"></i>Set Testing
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item" onclick="updateStatus(<?php echo $endpoint['id']; ?>, 'inactive')">
                                                <i class="fas fa-pause me-2 text-secondary"></i>Deactivate
                                            </button>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <button class="dropdown-item text-danger" onclick="deleteEndpoint(<?php echo $endpoint['id']; ?>)">
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
    </div>
</div>

<!-- Create Endpoint Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add RTB Endpoint</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Endpoint Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="endpoint_type" class="form-label">Type</label>
                                <select class="form-select" id="endpoint_type" name="endpoint_type" required>
                                    <option value="ssp_out">SSP OUT (Buy Traffic)</option>
                                    <option value="dsp_in">DSP IN (Sell Traffic)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="url" class="form-label">Endpoint URL</label>
                        <input type="url" class="form-control" id="url" name="url" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="method" class="form-label">Method</label>
                                <select class="form-select" id="method" name="method">
                                    <option value="POST">POST</option>
                                    <option value="GET">GET</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="protocol_version" class="form-label">OpenRTB Version</label>
                                <select class="form-select" id="protocol_version" name="protocol_version">
                                    <option value="2.5">2.5</option>
                                    <option value="2.4">2.4</option>
                                    <option value="2.3">2.3</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="timeout_ms" class="form-label">Timeout (ms)</label>
                                <input type="number" class="form-control" id="timeout_ms" name="timeout_ms" value="200" min="50" max="1000">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="qps_limit" class="form-label">QPS Limit</label>
                                <input type="number" class="form-control" id="qps_limit" name="qps_limit" value="100" min="1" max="10000">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bid_floor" class="form-label">Bid Floor ($)</label>
                                <input type="number" class="form-control" id="bid_floor" name="bid_floor" step="0.0001" value="0.0000" min="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Supported Formats</label>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="supported_formats[]" value="banner" id="format_banner">
                                    <label class="form-check-label" for="format_banner">Banner</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="supported_formats[]" value="video" id="format_video">
                                    <label class="form-check-label" for="format_video">Video</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="supported_formats[]" value="native" id="format_native">
                                    <label class="form-check-label" for="format_native">Native</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="supported_formats[]" value="popunder" id="format_popunder">
                                    <label class="form-check-label" for="format_popunder">Popunder</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="auth_type" class="form-label">Authentication</label>
                        <select class="form-select" id="auth_type" name="auth_type" onchange="toggleAuthFields()">
                            <option value="none">None</option>
                            <option value="api_key">API Key</option>
                            <option value="basic">Basic Auth</option>
                            <option value="bearer">Bearer Token</option>
                        </select>
                    </div>
                    
                    <div id="auth_fields" style="display: none;">
                        <div id="api_key_field" style="display: none;">
                            <div class="mb-3">
                                <label for="api_key" class="form-label">API Key</label>
                                <input type="text" class="form-control" id="api_key" name="api_key">
                            </div>
                        </div>
                        
                        <div id="basic_auth_fields" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="auth_username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="auth_username" name="auth_username">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="auth_password" class="form-label">Password</label>
                                        <input type="password" class="form-control" id="auth_password" name="auth_password">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div id="bearer_token_field" style="display: none;">
                            <div class="mb-3">
                                <label for="auth_token" class="form-label">Bearer Token</label>
                                <input type="text" class="form-control" id="auth_token" name="auth_token">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Endpoint</button>
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

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="endpoint_id" id="deleteEndpointId">
</form>

<form id="testForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="test">
    <input type="hidden" name="endpoint_id" id="testEndpointId">
</form>

<script>
function toggleAuthFields() {
    const authType = document.getElementById('auth_type').value;
    const authFields = document.getElementById('auth_fields');
    const apiKeyField = document.getElementById('api_key_field');
    const basicAuthFields = document.getElementById('basic_auth_fields');
    const bearerTokenField = document.getElementById('bearer_token_field');
    
    // Hide all auth fields first
    authFields.style.display = 'none';
    apiKeyField.style.display = 'none';
    basicAuthFields.style.display = 'none';
    bearerTokenField.style.display = 'none';
    
    if (authType !== 'none') {
        authFields.style.display = 'block';
        
        switch (authType) {
            case 'api_key':
                apiKeyField.style.display = 'block';
                break;
            case 'basic':
                basicAuthFields.style.display = 'block';
                break;
            case 'bearer':
                bearerTokenField.style.display = 'block';
                break;
        }
    }
}

function updateStatus(endpointId, status) {
    if (confirm(`Are you sure you want to ${status} this endpoint?`)) {
        document.getElementById('statusEndpointId').value = endpointId;
        document.getElementById('statusValue').value = status;
        document.getElementById('statusForm').submit();
    }
}

function deleteEndpoint(endpointId) {
    if (confirm('Are you sure you want to delete this endpoint? This action cannot be undone.')) {
        document.getElementById('deleteEndpointId').value = endpointId;
        document.getElementById('deleteForm').submit();
    }
}

function testEndpoint(endpointId) {
    if (confirm('Test this endpoint with a sample bid request?')) {
        document.getElementById('testEndpointId').value = endpointId;
        document.getElementById('testForm').submit();
    }
}
</script>

<?php include 'templates/footer.php'; ?>
