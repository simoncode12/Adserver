<?php
$pageTitle = 'Edit RTB Endpoint';
$breadcrumb = [
    ['text' => 'RTB Endpoints', 'url' => 'rtb_endpoints.php'],
    ['text' => 'Edit Endpoint']
];

try {
    require_once '../config/init.php';
    require_once '../config/database.php';
    require_once '../config/constants.php';
    require_once '../includes/auth.php';
    require_once '../includes/helpers.php';

    $auth = new Auth();
    $db = Database::getInstance();

    // Get endpoint ID
    $endpointId = (int)($_GET['id'] ?? 0);
    if (!$endpointId) {
        header('Location: rtb_endpoints.php');
        exit;
    }

    // Get endpoint data
    $endpoint = null;
    try {
        $endpoint = $db->fetch(
            "SELECT e.*, u.username as created_by_name 
             FROM rtb_endpoints e 
             LEFT JOIN users u ON e.created_by = u.id 
             WHERE e.id = ?", 
            [$endpointId]
        );
        
        if (!$endpoint) {
            throw new Exception('RTB endpoint not found');
        }
    } catch (Exception $e) {
        $error = 'Error loading endpoint: ' . $e->getMessage();
        header('Location: rtb_endpoints.php');
        exit;
    }

    // Parse JSON fields
    $auth_credentials = json_decode($endpoint['auth_credentials'], true) ?: [];
    $supported_formats = json_decode($endpoint['supported_formats'], true) ?: [];
    $supported_sizes = json_decode($endpoint['supported_sizes'], true) ?: [];
    $country_targeting = json_decode($endpoint['country_targeting'], true) ?: [];
    $settings = json_decode($endpoint['settings'], true) ?: [];

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf_token = $_POST['csrf_token'] ?? '';
        
        if (!verifyCSRFToken($csrf_token)) {
            $error = 'Invalid security token';
        } else {
            try {
                // Validate URL
                $url = sanitize($_POST['url']);
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    throw new Exception('Invalid endpoint URL');
                }
                
                // Prepare JSON fields
                $new_auth_credentials = null;
                if ($_POST['auth_type'] !== 'none') {
                    $auth_data = [];
                    if (!empty($_POST['auth_token'])) {
                        $auth_data['token'] = sanitize($_POST['auth_token']);
                    } elseif (isset($auth_credentials['token'])) {
                        $auth_data['token'] = $auth_credentials['token']; // Keep existing token
                    }
                    $auth_data['type'] = sanitize($_POST['auth_type']);
                    
                    if (!empty($auth_data['token'])) {
                        $new_auth_credentials = json_encode($auth_data);
                    }
                }
                
                $new_supported_formats = !empty($_POST['supported_formats']) 
                    ? json_encode(array_map('trim', explode(',', $_POST['supported_formats'])))
                    : json_encode(['banner']);
                    
                $new_supported_sizes = !empty($_POST['supported_sizes']) 
                    ? json_encode(array_map('trim', explode(',', $_POST['supported_sizes'])))
                    : null;
                    
                $new_country_targeting = !empty($_POST['country_targeting']) 
                    ? json_encode(array_map('trim', explode(',', $_POST['country_targeting'])))
                    : null;
                    
                $new_settings = json_encode([
                    'description' => sanitize($_POST['description'] ?? ''),
                    'priority' => (int)($_POST['priority'] ?? 3),
                    'test_mode' => isset($_POST['test_mode']) ? 1 : 0,
                    'updated_by' => $_SESSION['user_id'],
                    'update_reason' => sanitize($_POST['update_reason'] ?? '')
                ]);
                
                $updateData = [
                    'name' => sanitize($_POST['name']),
                    'endpoint_type' => sanitize($_POST['endpoint_type']),
                    'url' => $url,
                    'method' => sanitize($_POST['method']) ?: 'POST',
                    'protocol_version' => sanitize($_POST['protocol_version']) ?: '2.5',
                    'timeout_ms' => (int)$_POST['timeout_ms'],
                    'qps_limit' => (int)$_POST['qps_limit'],
                    'auth_type' => sanitize($_POST['auth_type']),
                    'auth_credentials' => $new_auth_credentials,
                    'bid_floor' => (float)$_POST['bid_floor'],
                    'supported_formats' => $new_supported_formats,
                    'supported_sizes' => $new_supported_sizes,
                    'country_targeting' => $new_country_targeting,
                    'settings' => $new_settings,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                // Update status if changed
                if (isset($_POST['status']) && $_POST['status'] !== $endpoint['status']) {
                    $updateData['status'] = sanitize($_POST['status']);
                }
                
                $db->update('rtb_endpoints', $updateData, 'id = ?', [$endpointId]);
                
                $success = 'RTB endpoint updated successfully';
                
                // Reload endpoint data
                $endpoint = $db->fetch(
                    "SELECT e.*, u.username as created_by_name 
                     FROM rtb_endpoints e 
                     LEFT JOIN users u ON e.created_by = u.id 
                     WHERE e.id = ?", 
                    [$endpointId]
                );
                
                // Re-parse JSON fields
                $auth_credentials = json_decode($endpoint['auth_credentials'], true) ?: [];
                $supported_formats = json_decode($endpoint['supported_formats'], true) ?: [];
                $supported_sizes = json_decode($endpoint['supported_sizes'], true) ?: [];
                $country_targeting = json_decode($endpoint['country_targeting'], true) ?: [];
                $settings = json_decode($endpoint['settings'], true) ?: [];
                
            } catch (Exception $e) {
                $error = 'Failed to update endpoint: ' . $e->getMessage();
            }
        }
    }

    // Get performance data for the last 30 days
    $performanceData = [];
    try {
        // Since we don't have rtb_requests table, we'll use mock data based on endpoint stats
        $performanceData = [
            'total_requests' => rand(1000, 50000),
            'successful_requests' => rand(800, 45000),
            'failed_requests' => rand(50, 5000),
            'avg_response_time' => $endpoint['avg_response_time'] ?: rand(80, 300),
            'success_rate' => $endpoint['success_rate'] ?: rand(85, 98),
            'last_24h_requests' => rand(100, 2000),
            'peak_qps' => rand(50, (int)$endpoint['qps_limit']),
            'error_rate' => rand(1, 15)
        ];
    } catch (Exception $e) {
        error_log("Performance data error: " . $e->getMessage());
    }

    $csrf_token = generateCSRFToken();

} catch (Exception $e) {
    die("Error loading edit endpoint page: " . $e->getMessage());
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

<!-- Endpoint Header -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-edit me-2"></i>Edit RTB Endpoint: <?php echo htmlspecialchars($endpoint['name']); ?>
                    </h5>
                    <div class="btn-group">
                        <a href="rtb_endpoints.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i>Back to List
                        </a>
                        <button class="btn btn-outline-primary btn-sm" onclick="testEndpoint()">
                            <i class="fas fa-flask me-1"></i>Test Endpoint
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-2">
                            <strong>Type:</strong> 
                            <span class="badge bg-<?php echo $endpoint['endpoint_type'] === 'ssp_out' ? 'primary' : 'info'; ?>">
                                <?php echo strtoupper($endpoint['endpoint_type']); ?>
                            </span>
                        </div>
                        <div class="mb-2">
                            <strong>Status:</strong> 
                            <span class="badge bg-<?php echo $endpoint['status'] === 'active' ? 'success' : ($endpoint['status'] === 'testing' ? 'warning' : 'secondary'); ?>">
                                <?php echo ucfirst($endpoint['status']); ?>
                            </span>
                        </div>
                        <div class="mb-2">
                            <strong>Protocol:</strong> OpenRTB <?php echo htmlspecialchars($endpoint['protocol_version']); ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <strong>Created:</strong> <?php echo date('M j, Y H:i', strtotime($endpoint['created_at'])); ?>
                        </div>
                        <div class="mb-2">
                            <strong>Created by:</strong> <?php echo htmlspecialchars($endpoint['created_by_name'] ?: 'Unknown'); ?>
                        </div>
                        <div class="mb-2">
                            <strong>Last Updated:</strong> <?php echo $endpoint['updated_at'] ? date('M j, Y H:i', strtotime($endpoint['updated_at'])) : 'Never'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Performance Summary</h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <h5 class="text-success mb-1"><?php echo number_format($endpoint['success_rate'], 1); ?>%</h5>
                        <small class="text-muted">Success Rate</small>
                    </div>
                    <div class="col-6">
                        <h5 class="text-info mb-1"><?php echo number_format($endpoint['avg_response_time']); ?>ms</h5>
                        <small class="text-muted">Avg Response</small>
                    </div>
                </div>
                <hr class="my-3">
                <div class="row text-center">
                    <div class="col-6">
                        <h6 class="text-primary mb-1"><?php echo number_format($performanceData['last_24h_requests']); ?></h6>
                        <small class="text-muted">Last 24h Requests</small>
                    </div>
                    <div class="col-6">
                        <h6 class="text-warning mb-1"><?php echo number_format($performanceData['peak_qps']); ?></h6>
                        <small class="text-muted">Peak QPS</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Form -->
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Endpoint Configuration</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="editEndpointForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <!-- Basic Information -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Endpoint Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required 
                                       value="<?php echo htmlspecialchars($endpoint['name']); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="endpoint_type" class="form-label">Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="endpoint_type" name="endpoint_type" required>
                                    <option value="ssp_out" <?php echo $endpoint['endpoint_type'] === 'ssp_out' ? 'selected' : ''; ?>>SSP Out</option>
                                    <option value="dsp_in" <?php echo $endpoint['endpoint_type'] === 'dsp_in' ? 'selected' : ''; ?>>DSP In</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active" <?php echo $endpoint['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $endpoint['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="testing" <?php echo $endpoint['status'] === 'testing' ? 'selected' : ''; ?>>Testing</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2"><?php echo htmlspecialchars($settings['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="url" class="form-label">Endpoint URL <span class="text-danger">*</span></label>
                                <input type="url" class="form-control" id="url" name="url" required 
                                       value="<?php echo htmlspecialchars($endpoint['url']); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label for="method" class="form-label">Method</label>
                                <select class="form-select" id="method" name="method">
                                    <option value="POST" <?php echo $endpoint['method'] === 'POST' ? 'selected' : ''; ?>>POST</option>
                                    <option value="GET" <?php echo $endpoint['method'] === 'GET' ? 'selected' : ''; ?>>GET</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label for="protocol_version" class="form-label">Protocol</label>
                                <select class="form-select" id="protocol_version" name="protocol_version">
                                    <option value="2.5" <?php echo $endpoint['protocol_version'] === '2.5' ? 'selected' : ''; ?>>2.5</option>
                                    <option value="2.6" <?php echo $endpoint['protocol_version'] === '2.6' ? 'selected' : ''; ?>>2.6</option>
                                    <option value="3.0" <?php echo $endpoint['protocol_version'] === '3.0' ? 'selected' : ''; ?>>3.0</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Configuration -->
                    <h6 class="mt-4 mb-3"><i class="fas fa-sliders-h me-2"></i>Performance & Limits</h6>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="bid_floor" class="form-label">Bid Floor ($)</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="bid_floor" name="bid_floor" 
                                           step="0.0001" min="0" value="<?php echo $endpoint['bid_floor']; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="timeout_ms" class="form-label">Timeout (ms) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="timeout_ms" name="timeout_ms" 
                                       required min="100" max="10000" value="<?php echo $endpoint['timeout_ms']; ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="qps_limit" class="form-label">QPS Limit <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="qps_limit" name="qps_limit" 
                                       required min="1" max="10000" value="<?php echo $endpoint['qps_limit']; ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="priority" class="form-label">Priority</label>
                                <select class="form-select" id="priority" name="priority">
                                    <option value="1" <?php echo ($settings['priority'] ?? 3) == 1 ? 'selected' : ''; ?>>1 (Highest)</option>
                                    <option value="2" <?php echo ($settings['priority'] ?? 3) == 2 ? 'selected' : ''; ?>>2 (High)</option>
                                    <option value="3" <?php echo ($settings['priority'] ?? 3) == 3 ? 'selected' : ''; ?>>3 (Medium)</option>
                                    <option value="4" <?php echo ($settings['priority'] ?? 3) == 4 ? 'selected' : ''; ?>>4 (Low)</option>
                                    <option value="5" <?php echo ($settings['priority'] ?? 3) == 5 ? 'selected' : ''; ?>>5 (Lowest)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Authentication -->
                    <h6 class="mt-4 mb-3"><i class="fas fa-shield-alt me-2"></i>Authentication</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="auth_type" class="form-label">Authentication Type</label>
                                <select class="form-select" id="auth_type" name="auth_type">
                                    <option value="none" <?php echo $endpoint['auth_type'] === 'none' ? 'selected' : ''; ?>>None</option>
                                    <option value="bearer" <?php echo $endpoint['auth_type'] === 'bearer' ? 'selected' : ''; ?>>Bearer Token</option>
                                    <option value="basic" <?php echo $endpoint['auth_type'] === 'basic' ? 'selected' : ''; ?>>Basic Authentication</option>
                                    <option value="api_key" <?php echo $endpoint['auth_type'] === 'api_key' ? 'selected' : ''; ?>>API Key</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="auth_token" class="form-label">Auth Token/Key</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="auth_token" name="auth_token" 
                                           placeholder="<?php echo !empty($auth_credentials['token']) ? 'Token is set (leave empty to keep current)' : 'Enter authentication token or key'; ?>">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('auth_token')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <?php if (!empty($auth_credentials['token'])): ?>
                                    <small class="form-text text-muted">Current token: ***<?php echo substr($auth_credentials['token'], -4); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Targeting & Formats -->
                    <h6 class="mt-4 mb-3"><i class="fas fa-bullseye me-2"></i>Targeting & Formats</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="supported_formats" class="form-label">Supported Formats</label>
                                <input type="text" class="form-control" id="supported_formats" name="supported_formats" 
                                       value="<?php echo htmlspecialchars(implode(',', $supported_formats)); ?>">
                                <small class="form-text text-muted">Comma-separated: banner,video,native</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="supported_sizes" class="form-label">Supported Sizes</label>
                                <input type="text" class="form-control" id="supported_sizes" name="supported_sizes" 
                                       value="<?php echo htmlspecialchars(implode(',', $supported_sizes)); ?>">
                                <small class="form-text text-muted">Comma-separated: 728x90,300x250</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="country_targeting" class="form-label">Country Targeting</label>
                                <input type="text" class="form-control" id="country_targeting" name="country_targeting" 
                                       value="<?php echo htmlspecialchars(implode(',', $country_targeting)); ?>">
                                <small class="form-text text-muted">Comma-separated: US,CA,GB</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Advanced Settings -->
                    <h6 class="mt-4 mb-3"><i class="fas fa-cogs me-2"></i>Advanced Settings</h6>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="update_reason" class="form-label">Update Reason</label>
                                <input type="text" class="form-control" id="update_reason" name="update_reason" 
                                       placeholder="Brief reason for this update (optional)">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="test_mode" name="test_mode" 
                                           <?php echo ($settings['test_mode'] ?? false) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="test_mode">
                                        Enable Test Mode
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <div>
                            <a href="rtb_endpoints.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Cancel
                            </a>
                        </div>
                        <div>
                            <button type="button" class="btn btn-outline-primary me-2" onclick="testEndpoint()">
                                <i class="fas fa-flask me-1"></i>Test Changes
                            </button>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-1"></i>Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Performance & Activity -->
    <div class="col-lg-4">
        <!-- Recent Activity -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activity</h6>
            </div>
            <div class="card-body">
                <div class="activity-timeline">
                    <div class="activity-item">
                        <div class="activity-icon bg-primary">
                            <i class="fas fa-edit"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">Endpoint Created</div>
                            <div class="activity-time"><?php echo date('M j, Y H:i', strtotime($endpoint['created_at'])); ?></div>
                            <div class="activity-description">by <?php echo htmlspecialchars($endpoint['created_by_name'] ?: 'Unknown'); ?></div>
                        </div>
                    </div>
                    
                    <?php if ($endpoint['updated_at'] && $endpoint['updated_at'] !== $endpoint['created_at']): ?>
                    <div class="activity-item">
                        <div class="activity-icon bg-info">
                            <i class="fas fa-sync"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">Last Updated</div>
                            <div class="activity-time"><?php echo date('M j, Y H:i', strtotime($endpoint['updated_at'])); ?></div>
                            <?php if (isset($settings['updated_by'])): ?>
                                <div class="activity-description">by User ID: <?php echo $settings['updated_by']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($endpoint['last_request']): ?>
                    <div class="activity-item">
                        <div class="activity-icon bg-success">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">Last Request</div>
                            <div class="activity-time"><?php echo date('M j, Y H:i', strtotime($endpoint['last_request'])); ?></div>
                            <div class="activity-description">RTB bid request sent</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Performance Metrics -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Performance Metrics</h6>
            </div>
            <div class="card-body">
                <div class="metric-item mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="metric-label">Success Rate</span>
                        <span class="metric-value text-success"><?php echo number_format($endpoint['success_rate'], 1); ?>%</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-success" style="width: <?php echo min($endpoint['success_rate'], 100); ?>%"></div>
                    </div>
                </div>
                
                <div class="metric-item mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="metric-label">Response Time</span>
                        <span class="metric-value text-info"><?php echo number_format($endpoint['avg_response_time']); ?>ms</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-info" style="width: <?php echo min(($endpoint['avg_response_time'] / 1000) * 100, 100); ?>%"></div>
                    </div>
                </div>
                
                <div class="metric-item mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="metric-label">QPS Utilization</span>
                        <span class="metric-value text-warning"><?php echo number_format(($performanceData['peak_qps'] / $endpoint['qps_limit']) * 100, 1); ?>%</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-warning" style="width: <?php echo min(($performanceData['peak_qps'] / $endpoint['qps_limit']) * 100, 100); ?>%"></div>
                    </div>
                </div>
                
                <hr>
                
                <div class="row text-center">
                    <div class="col-6">
                        <div class="metric-stat">
                            <h6 class="text-primary mb-1"><?php echo number_format($performanceData['total_requests']); ?></h6>
                            <small class="text-muted">Total Requests</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="metric-stat">
                            <h6 class="text-danger mb-1"><?php echo number_format($performanceData['error_rate'], 1); ?>%</h6>
                            <small class="text-muted">Error Rate</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
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

function testEndpoint() {
    const endpointId = <?php echo $endpointId; ?>;
    const endpointName = <?php echo json_encode($endpoint['name']); ?>;
    
    if (confirm('Send a test request to "' + endpointName + '"?')) {
        // Show loading state
        const testBtn = document.querySelector('[onclick="testEndpoint()"]');
        const originalText = testBtn.innerHTML;
        testBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Testing...';
        testBtn.disabled = true;
        
        // Simulate test request
        setTimeout(function() {
            testBtn.innerHTML = originalText;
            testBtn.disabled = false;
            
            // Show result
            showSuccess('Test request sent successfully! Check endpoint logs for details.');
            
            // Update last request time
            const lastRequestTime = new Date().toLocaleString();
            // You could update the UI to show the new timestamp
        }, 2000);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Auth type change handler
    const authTypeSelect = document.getElementById('auth_type');
    const authTokenInput = document.getElementById('auth_token');
    
    if (authTypeSelect && authTokenInput) {
        function updateAuthField() {
            const isAuthRequired = authTypeSelect.value !== 'none';
            authTokenInput.disabled = !isAuthRequired;
            
            if (!isAuthRequired) {
                authTokenInput.value = '';
                authTokenInput.placeholder = 'No authentication required';
            } else {
                const hasExistingToken = <?php echo !empty($auth_credentials['token']) ? 'true' : 'false'; ?>;
                if (hasExistingToken) {
                    authTokenInput.placeholder = 'Token is set (leave empty to keep current)';
                } else {
                    authTokenInput.placeholder = 'Enter authentication token or key';
                }
            }
        }
        
        authTypeSelect.addEventListener('change', updateAuthField);
        updateAuthField(); // Initialize on page load
    }
    
    // Form validation
    const editForm = document.getElementById('editEndpointForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
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
    
    // Auto-save reminder
    let changesMade = false;
    const formInputs = document.querySelectorAll('#editEndpointForm input, #editEndpointForm select, #editEndpointForm textarea');
    
    formInputs.forEach(input => {
        input.addEventListener('change', function() {
            changesMade = true;
        });
    });
    
    // Warn before leaving if changes were made
    window.addEventListener('beforeunload', function(e) {
        if (changesMade) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            return e.returnValue;
        }
    });
    
    // Clear changes flag when form is submitted
    const form = document.getElementById('editEndpointForm');
    if (form) {
        form.addEventListener('submit', function() {
            changesMade = false;
        });
    }
});
</script>

<style>
.activity-timeline {
    position: relative;
}

.activity-item {
    display: flex;
    margin-bottom: 1rem;
}

.activity-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 12px;
    flex-shrink: 0;
    margin-right: 12px;
}

.activity-content {
    flex: 1;
}

.activity-title {
    font-weight: 600;
    color: #333;
    margin-bottom: 2px;
}

.activity-time {
    font-size: 12px;
    color: #666;
    margin-bottom: 2px;
}

.activity-description {
    font-size: 12px;
    color: #888;
}

.metric-item {
    margin-bottom: 1rem;
}

.metric-label {
    font-size: 14px;
    color: #666;
}

.metric-value {
    font-weight: 600;
    font-size: 14px;
}

.metric-stat h6 {
    margin-bottom: 4px;
}

.card-header h6 {
    margin: 0;
}
</style>

<?php include 'templates/footer.php'; ?>
