<?php
$pageTitle = 'Zone Management';
$breadcrumb = [
    ['text' => 'Zones']
];

try {
    require_once '../config/init.php';
    require_once '../config/database.php';
    require_once '../config/constants.php';
    require_once '../includes/auth.php';
    require_once '../includes/helpers.php';

    $auth = new Auth();
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
                        'site_id' => (int)$_POST['site_id'],
                        'name' => sanitize($_POST['name']),
                        'description' => sanitize($_POST['description']),
                        'ad_format_id' => (int)$_POST['ad_format_id'],
                        'width' => (int)$_POST['width'],
                        'height' => (int)$_POST['height'],
                        'default_ad_content' => sanitize($_POST['default_ad_content']),
                        'floor_price' => (float)$_POST['floor_price'],
                        'passback_url' => sanitize($_POST['passback_url']),
                        'created_by' => $_SESSION['user_id']
                    ];
                    
                    $db->insert('zones', $data);
                    $success = 'Zone created successfully';
                    break;
                    
                case 'update_status':
                    $zoneId = (int)$_POST['zone_id'];
                    $status = sanitize($_POST['status']);
                    
                    if (in_array($status, ['active', 'inactive', 'pending'])) {
                        $db->update('zones', 
                            ['status' => $status], 
                            'id = ?', 
                            [$zoneId]
                        );
                        $success = 'Zone status updated successfully';
                    } else {
                        $error = 'Invalid status';
                    }
                    break;
                    
                case 'delete':
                    $zoneId = (int)$_POST['zone_id'];
                    $db->delete('zones', 'id = ?', [$zoneId]);
                    $success = 'Zone deleted successfully';
                    break;
            }
        }
    }

    // Filters
    $status = $_GET['status'] ?? '';
    $siteId = (int)($_GET['site_id'] ?? 0);
    $formatId = (int)($_GET['format_id'] ?? 0);
    $search = sanitize($_GET['search'] ?? '');

    // Build query
    $whereConditions = [];
    $params = [];

    if ($status) {
        $whereConditions[] = 'z.status = ?';
        $params[] = $status;
    }

    if ($siteId) {
        $whereConditions[] = 'z.site_id = ?';
        $params[] = $siteId;
    }

    if ($formatId) {
        $whereConditions[] = 'z.ad_format_id = ?';
        $params[] = $formatId;
    }

    if ($search) {
        $whereConditions[] = '(z.name LIKE ? OR s.name LIKE ? OR u.username LIKE ?)';
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // Get zones
    $zones = [];
    try {
        $zones = $db->fetchAll(
            "SELECT z.*, s.name as site_name, s.url as site_url, u.username, 
                    af.name as format_name, af.slug as format_slug,
                    (SELECT COUNT(*) FROM tracking_events te WHERE te.zone_id = z.id AND DATE(te.created_at) = CURDATE()) as today_impressions,
                    (SELECT SUM(te.revenue) FROM tracking_events te WHERE te.zone_id = z.id AND DATE(te.created_at) = CURDATE()) as today_revenue,
                    (SELECT COUNT(*) FROM tracking_events te WHERE te.zone_id = z.id AND te.event_type = 'click' AND DATE(te.created_at) = CURDATE()) as today_clicks
             FROM zones z
             JOIN sites s ON z.site_id = s.id
             JOIN users u ON s.user_id = u.id
             LEFT JOIN ad_formats af ON z.ad_format_id = af.id
             {$whereClause}
             ORDER BY z.created_at DESC",
            $params
        );
    } catch (Exception $e) {
        error_log("Zones query error: " . $e->getMessage());
        $error = "Error loading zones data";
    }

    // Get sites for filter and create form
    $sites = [];
    try {
        $sites = $db->fetchAll("SELECT id, name, url FROM sites WHERE status = 'active' ORDER BY name");
    } catch (Exception $e) {
        error_log("Sites query error: " . $e->getMessage());
    }

    // Get ad formats for filter and create form
    $adFormats = [];
    try {
        $adFormats = $db->fetchAll("SELECT id, name, slug FROM ad_formats ORDER BY name");
    } catch (Exception $e) {
        error_log("Ad formats query error: " . $e->getMessage());
    }

    // Statistics
    $stats = [
        'total_zones' => 0,
        'active_zones' => 0,
        'inactive_zones' => 0,
        'pending_zones' => 0,
        'total_impressions_today' => 0,
        'total_revenue_today' => 0
    ];

    try {
        $stats = $db->fetch(
            "SELECT 
                COUNT(*) as total_zones,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_zones,
                COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_zones,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_zones,
                (SELECT COUNT(*) FROM tracking_events te JOIN zones z ON te.zone_id = z.id WHERE DATE(te.created_at) = CURDATE()) as total_impressions_today,
                (SELECT SUM(te.revenue) FROM tracking_events te JOIN zones z ON te.zone_id = z.id WHERE DATE(te.created_at) = CURDATE()) as total_revenue_today
             FROM zones"
        );
    } catch (Exception $e) {
        error_log("Zone stats error: " . $e->getMessage());
    }

    $csrf_token = generateCSRFToken();

} catch (Exception $e) {
    die("Error loading zones page: " . $e->getMessage());
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
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card stats-card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h3 class="mb-1"><?php echo number_format($stats['total_zones']); ?></h3>
                        <p class="mb-0">Total Zones</p>
                    </div>
                    <div class="ms-3">
                        <i class="fas fa-map-marker-alt fa-2x opacity-75"></i>
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
                        <h3 class="mb-1"><?php echo number_format($stats['active_zones']); ?></h3>
                        <p class="mb-0">Active Zones</p>
                    </div>
                    <div class="ms-3">
                        <i class="fas fa-check fa-2x opacity-75"></i>
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
                        <h3 class="mb-1"><?php echo number_format($stats['total_impressions_today']); ?></h3>
                        <p class="mb-0">Today's Impressions</p>
                    </div>
                    <div class="ms-3">
                        <i class="fas fa-eye fa-2x opacity-75"></i>
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
                        <h3 class="mb-1">$<?php echo number_format($stats['total_revenue_today'], 2); ?></h3>
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

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Statuses</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="site_id" class="form-label">Site</label>
                <select class="form-select" id="site_id" name="site_id">
                    <option value="">All Sites</option>
                    <?php foreach ($sites as $site): ?>
                        <option value="<?php echo $site['id']; ?>" <?php echo $siteId == $site['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($site['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="format_id" class="form-label">Format</label>
                <select class="form-select" id="format_id" name="format_id">
                    <option value="">All Formats</option>
                    <?php foreach ($adFormats as $format): ?>
                        <option value="<?php echo $format['id']; ?>" <?php echo $formatId == $format['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($format['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       placeholder="Search zones, sites..." value="<?php echo htmlspecialchars($search); ?>">
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

<!-- Zones Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Ad Zones</h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createZoneModal">
            <i class="fas fa-plus me-2"></i>Create Zone
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Zone</th>
                        <th>Site</th>
                        <th>Format</th>
                        <th>Size</th>
                        <th>Status</th>
                        <th>Floor Price</th>
                        <th>Today Stats</th>
                        <th>Performance</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($zones as $zone): ?>
                        <tr>
                            <td><?php echo $zone['id']; ?></td>
                            <td>
                                <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($zone['name']); ?></div>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($zone['description'] ?: 'No description'); ?>
                                    </small>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($zone['site_name']); ?></div>
                                    <small class="text-muted">
                                        <a href="<?php echo htmlspecialchars($zone['site_url']); ?>" target="_blank" class="text-decoration-none">
                                            <?php echo htmlspecialchars(parse_url($zone['site_url'], PHP_URL_HOST)); ?>
                                            <i class="fas fa-external-link-alt ms-1"></i>
                                        </a>
                                    </small>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-secondary">
                                    <?php echo htmlspecialchars($zone['format_name'] ?: 'Unknown'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="fw-bold">
                                    <?php echo $zone['width']; ?>×<?php echo $zone['height']; ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $statusClass = [
                                    'active' => 'success',
                                    'inactive' => 'secondary',
                                    'pending' => 'warning'
                                ];
                                ?>
                                <span class="badge bg-<?php echo $statusClass[$zone['status']] ?? 'secondary'; ?>">
                                    <?php echo ucfirst($zone['status']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="text-success fw-bold">
                                    $<?php echo number_format($zone['floor_price'], 4); ?>
                                </span>
                            </td>
                            <td>
                                <div class="small">
                                    <div><strong><?php echo number_format($zone['today_impressions'] ?? 0); ?></strong> imp</div>
                                    <div><strong><?php echo number_format($zone['today_clicks'] ?? 0); ?></strong> clicks</div>
                                    <div class="text-success"><strong>$<?php echo number_format($zone['today_revenue'] ?? 0, 2); ?></strong></div>
                                </div>
                            </td>
                            <td>
                                <?php 
                                $ctr = ($zone['today_impressions'] ?? 0) > 0 
                                    ? (($zone['today_clicks'] ?? 0) / $zone['today_impressions']) * 100 
                                    : 0;
                                $rpm = ($zone['today_impressions'] ?? 0) > 0 
                                    ? (($zone['today_revenue'] ?? 0) / $zone['today_impressions']) * 1000 
                                    : 0;
                                ?>
                                <div class="small">
                                    <div>CTR: <strong><?php echo number_format($ctr, 2); ?>%</strong></div>
                                    <div>RPM: <strong>$<?php echo number_format($rpm, 2); ?></strong></div>
                                </div>
                            </td>
                            <td>
                                <small><?php echo date('M j, Y', strtotime($zone['created_at'])); ?></small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" onclick="viewZone(<?php echo $zone['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="fas fa-cog"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <button class="dropdown-item" onclick="getZoneCode(<?php echo $zone['id']; ?>)">
                                                <i class="fas fa-code me-2 text-primary"></i>Get Code
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item" onclick="updateStatus(<?php echo $zone['id']; ?>, 'active')">
                                                <i class="fas fa-play me-2 text-success"></i>Activate
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item" onclick="updateStatus(<?php echo $zone['id']; ?>, 'inactive')">
                                                <i class="fas fa-pause me-2 text-warning"></i>Deactivate
                                            </button>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <button class="dropdown-item text-danger" onclick="deleteZone(<?php echo $zone['id']; ?>)">
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

<!-- Create Zone Modal -->
<div class="modal fade" id="createZoneModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Zone</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Zone Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="site_id" class="form-label">Site</label>
                                <select class="form-select" id="site_id" name="site_id" required>
                                    <option value="">Select Site</option>
                                    <?php foreach ($sites as $site): ?>
                                        <option value="<?php echo $site['id']; ?>">
                                            <?php echo htmlspecialchars($site['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="ad_format_id" class="form-label">Ad Format</label>
                                <select class="form-select" id="ad_format_id" name="ad_format_id" required>
                                    <option value="">Select Format</option>
                                    <?php foreach ($adFormats as $format): ?>
                                        <option value="<?php echo $format['id']; ?>">
                                            <?php echo htmlspecialchars($format['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="width" class="form-label">Width (px)</label>
                                <input type="number" class="form-control" id="width" name="width" required min="1">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="height" class="form-label">Height (px)</label>
                                <input type="number" class="form-control" id="height" name="height" required min="1">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="floor_price" class="form-label">Floor Price ($)</label>
                                <input type="number" class="form-control" id="floor_price" name="floor_price" 
                                       step="0.0001" value="0.0000" min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="passback_url" class="form-label">Passback URL (Optional)</label>
                                <input type="url" class="form-control" id="passback_url" name="passback_url">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="default_ad_content" class="form-label">Default Ad Content (Optional)</label>
                        <textarea class="form-control" id="default_ad_content" name="default_ad_content" rows="3"
                                  placeholder="HTML content to show when no ads are available"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Zone</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Zone Code Modal -->
<div class="modal fade" id="zoneCodeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Zone Ad Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label">JavaScript Code</label>
                            <textarea class="form-control" id="jsCode" rows="4" readonly></textarea>
                            <button class="btn btn-sm btn-outline-primary mt-2" onclick="copyCode('jsCode')">
                                <i class="fas fa-copy me-1"></i>Copy JS Code
                            </button>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label">Async Code</label>
                            <textarea class="form-control" id="asyncCode" rows="4" readonly></textarea>
                            <button class="btn btn-sm btn-outline-primary mt-2" onclick="copyCode('asyncCode')">
                                <i class="fas fa-copy me-1"></i>Copy Async Code
                            </button>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label">Direct API URL</label>
                            <input type="text" class="form-control" id="apiUrl" readonly>
                            <button class="btn btn-sm btn-outline-primary mt-2" onclick="copyCode('apiUrl')">
                                <i class="fas fa-copy me-1"></i>Copy API URL
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Forms -->
<form id="statusForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="zone_id" id="statusZoneId">
    <input type="hidden" name="status" id="statusValue">
</form>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="zone_id" id="deleteZoneId">
</form>

<script>
function updateStatus(zoneId, status) {
    if (confirm(`Are you sure you want to ${status} this zone?`)) {
        document.getElementById('statusZoneId').value = zoneId;
        document.getElementById('statusValue').value = status;
        document.getElementById('statusForm').submit();
    }
}

function deleteZone(zoneId) {
    if (confirm('Are you sure you want to delete this zone? This action cannot be undone.')) {
        document.getElementById('deleteZoneId').value = zoneId;
        document.getElementById('deleteForm').submit();
    }
}

function viewZone(zoneId) {
    window.open(`zone_view.php?id=${zoneId}`, '_blank');
}

function getZoneCode(zoneId) {
    const baseUrl = '<?php echo AD_SERVER_URL; ?>';
    
    // Generate different code formats
    const jsCode = `<script src="${baseUrl}/api/serve.php?zone=${zoneId}&format=js"></script>`;
    
    const asyncCode = `<div id="ad-zone-${zoneId}"></div>
<script>
(function() {
    var script = document.createElement('script');
    script.src = '${baseUrl}/api/serve.php?zone=${zoneId}&format=async';
    document.head.appendChild(script);
})();
</script>`;

    const apiUrl = `${baseUrl}/api/serve.php?zone=${zoneId}`;
    
    // Populate modal
    document.getElementById('jsCode').value = jsCode;
    document.getElementById('asyncCode').value = asyncCode;
    document.getElementById('apiUrl').value = apiUrl;
    
    // Show modal
    new bootstrap.Modal(document.getElementById('zoneCodeModal')).show();
}

function copyCode(elementId) {
    const element = document.getElementById(elementId);
    element.select();
    element.setSelectionRange(0, 99999); // For mobile devices
    
    try {
        document.execCommand('copy');
        showSuccess('Code copied to clipboard!');
    } catch (err) {
        showError('Failed to copy code');
    }
}

// Auto-populate common banner sizes
document.getElementById('ad_format_id').addEventListener('change', function() {
    const formatId = this.value;
    const widthInput = document.getElementById('width');
    const heightInput = document.getElementById('height');
    
    // Common banner sizes (you can expand this based on your ad_formats table)
    const commonSizes = {
        '1': {width: 728, height: 90},   // Leaderboard
        '2': {width: 300, height: 250}, // Medium Rectangle
        '3': {width: 320, height: 50},  // Mobile Banner
        '4': {width: 160, height: 600}, // Wide Skyscraper
        '5': {width: 300, height: 600}  // Half Page
    };
    
    if (commonSizes[formatId]) {
        widthInput.value = commonSizes[formatId].width;
        heightInput.value = commonSizes[formatId].height;
    }
});
</script>

<?php include 'templates/footer.php'; ?>
