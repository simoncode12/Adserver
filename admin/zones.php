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

    // Handle AJAX requests for real-time stats
    if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
        header('Content-Type: application/json');
        
        try {
            $stats = $db->fetch(
                "SELECT 
                    COUNT(*) as total_zones,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_zones,
                    COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_zones,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_zones,
                    (SELECT COUNT(*) FROM tracking_events te JOIN zones z ON te.zone_id = z.id WHERE DATE(te.created_at) = CURDATE()) as total_impressions_today,
                    (SELECT COALESCE(SUM(te.revenue), 0) FROM tracking_events te JOIN zones z ON te.zone_id = z.id WHERE DATE(te.created_at) = CURDATE()) as total_revenue_today
                 FROM zones"
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
                            'status' => 'active',
                            'created_by' => $_SESSION['user_id'],
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        
                        $db->insert('zones', $data);
                        $success = 'Zone created successfully';
                    } catch (Exception $e) {
                        $error = 'Failed to create zone: ' . $e->getMessage();
                    }
                    break;
                    
                case 'update_status':
                    try {
                        $zoneId = (int)$_POST['zone_id'];
                        $status = sanitize($_POST['status']);
                        
                        if (in_array($status, ['active', 'inactive', 'pending'])) {
                            $db->update('zones', 
                                ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')], 
                                'id = ?', 
                                [$zoneId]
                            );
                            $success = 'Zone status updated successfully';
                        } else {
                            $error = 'Invalid status';
                        }
                    } catch (Exception $e) {
                        $error = 'Failed to update status: ' . $e->getMessage();
                    }
                    break;
                    
                case 'delete':
                    try {
                        $zoneId = (int)$_POST['zone_id'];
                        $db->delete('zones', 'id = ?', [$zoneId]);
                        $success = 'Zone deleted successfully';
                    } catch (Exception $e) {
                        $error = 'Failed to delete zone: ' . $e->getMessage();
                    }
                    break;
                    
                case 'update_zone':
                    try {
                        $zoneId = (int)$_POST['zone_id'];
                        $data = [
                            'name' => sanitize($_POST['name']),
                            'description' => sanitize($_POST['description']),
                            'floor_price' => (float)$_POST['floor_price'],
                            'passback_url' => sanitize($_POST['passback_url']),
                            'default_ad_content' => sanitize($_POST['default_ad_content']),
                            'updated_at' => date('Y-m-d H:i:s')
                        ];
                        
                        $db->update('zones', $data, 'id = ?', [$zoneId]);
                        $success = 'Zone updated successfully';
                    } catch (Exception $e) {
                        $error = 'Failed to update zone: ' . $e->getMessage();
                    }
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

    // Get zones with enhanced data
    $zones = [];
    try {
        $zones = $db->fetchAll(
            "SELECT z.*, s.name as site_name, s.url as site_url, u.username, u.email,
                    af.name as format_name, af.slug as format_slug,
                    (SELECT COUNT(*) FROM tracking_events te WHERE te.zone_id = z.id AND DATE(te.created_at) = CURDATE()) as today_impressions,
                    (SELECT COALESCE(SUM(te.revenue), 0) FROM tracking_events te WHERE te.zone_id = z.id AND DATE(te.created_at) = CURDATE()) as today_revenue,
                    (SELECT COUNT(*) FROM tracking_events te WHERE te.zone_id = z.id AND te.event_type = 'click' AND DATE(te.created_at) = CURDATE()) as today_clicks,
                    (SELECT COUNT(*) FROM tracking_events te WHERE te.zone_id = z.id AND DATE(te.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) as week_impressions,
                    (SELECT COALESCE(SUM(te.revenue), 0) FROM tracking_events te WHERE te.zone_id = z.id AND DATE(te.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) as week_revenue
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
        $zones = [];
    }

    // Get sites for filter and create form
    $sites = [];
    try {
        $sites = $db->fetchAll(
            "SELECT s.id, s.name, s.url, u.username 
             FROM sites s 
             JOIN users u ON s.user_id = u.id 
             WHERE s.status = 'active' 
             ORDER BY s.name"
        );
    } catch (Exception $e) {
        error_log("Sites query error: " . $e->getMessage());
        $sites = [];
    }

    // Get ad formats for filter and create form
    $adFormats = [];
    try {
        $adFormats = $db->fetchAll("SELECT id, name, slug, width, height FROM ad_formats ORDER BY name");
    } catch (Exception $e) {
        error_log("Ad formats query error: " . $e->getMessage());
        $adFormats = [];
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
        $result = $db->fetch(
            "SELECT 
                COUNT(*) as total_zones,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_zones,
                COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_zones,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_zones,
                (SELECT COUNT(*) FROM tracking_events te JOIN zones z ON te.zone_id = z.id WHERE DATE(te.created_at) = CURDATE()) as total_impressions_today,
                (SELECT COALESCE(SUM(te.revenue), 0) FROM tracking_events te JOIN zones z ON te.zone_id = z.id WHERE DATE(te.created_at) = CURDATE()) as total_revenue_today
             FROM zones"
        );
        if ($result) {
            $stats = $result;
        }
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
                            <h3 class="mb-1" id="total-zones"><?php echo number_format($stats['total_zones']); ?></h3>
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
                            <h3 class="mb-1" id="active-zones"><?php echo number_format($stats['active_zones']); ?></h3>
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
                            <h3 class="mb-1" id="today-impressions"><?php echo number_format($stats['total_impressions_today']); ?></h3>
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
                            <h3 class="mb-1" id="today-revenue">$<?php echo number_format($stats['total_revenue_today'], 2); ?></h3>
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
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="site_id" class="form-label">Site</label>
                <select class="form-select" id="site_id" name="site_id">
                    <option value="">All Sites</option>
                    <?php foreach ($sites as $site): ?>
                        <option value="<?php echo $site['id']; ?>" <?php echo $siteId == $site['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($site['name']); ?> (<?php echo htmlspecialchars($site['username']); ?>)
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
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Ad Zones (<?php echo count($zones); ?>)</h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createZoneModal">
            <i class="fas fa-plus me-2"></i>Create Zone
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <?php if (empty($zones)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-map-marker-alt fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">No Zones Found</h4>
                    <p class="text-muted mb-4">Create your first zone to get started with ad serving</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createZoneModal">
                        <i class="fas fa-plus me-2"></i>Create Your First Zone
                    </button>
                </div>
            <?php else: ?>
                <table class="table table-hover" id="zonesTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Zone Details</th>
                            <th>Site & Publisher</th>
                            <th class="no-sort">Format & Size</th>
                            <th>Status</th>
                            <th>Floor Price</th>
                            <th class="no-sort">Today Stats</th>
                            <th class="no-sort">Performance</th>
                            <th>Created</th>
                            <th class="no-sort">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($zones as $zone): ?>
                            <tr data-zone-id="<?php echo $zone['id']; ?>">
                                <td>
                                    <span class="fw-bold"><?php echo $zone['id']; ?></span>
                                </td>
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
                                            <?php echo htmlspecialchars($zone['username']); ?> • 
                                            <a href="<?php echo htmlspecialchars($zone['site_url']); ?>" target="_blank" class="text-decoration-none">
                                                <?php echo htmlspecialchars(parse_url($zone['site_url'], PHP_URL_HOST) ?: $zone['site_url']); ?>
                                                <i class="fas fa-external-link-alt ms-1"></i>
                                            </a>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <span class="badge bg-secondary mb-1">
                                            <?php echo htmlspecialchars($zone['format_name'] ?: 'Custom'); ?>
                                        </span>
                                        <br>
                                        <span class="fw-bold small">
                                            <?php echo $zone['width']; ?>×<?php echo $zone['height']; ?>px
                                        </span>
                                    </div>
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
                                        <div><i class="fas fa-eye text-primary me-1"></i><strong><?php echo number_format($zone['today_impressions'] ?? 0); ?></strong></div>
                                        <div><i class="fas fa-mouse-pointer text-info me-1"></i><strong><?php echo number_format($zone['today_clicks'] ?? 0); ?></strong></div>
                                        <div><i class="fas fa-dollar-sign text-success me-1"></i><strong>$<?php echo number_format($zone['today_revenue'] ?? 0, 2); ?></strong></div>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $impressions = $zone['today_impressions'] ?? 0;
                                    $clicks = $zone['today_clicks'] ?? 0;
                                    $revenue = $zone['today_revenue'] ?? 0;
                                    
                                    $ctr = $impressions > 0 ? ($clicks / $impressions) * 100 : 0;
                                    $rpm = $impressions > 0 ? ($revenue / $impressions) * 1000 : 0;
                                    ?>
                                    <div class="small">
                                        <div>CTR: <strong><?php echo number_format($ctr, 2); ?>%</strong></div>
                                        <div>RPM: <strong>$<?php echo number_format($rpm, 2); ?></strong></div>
                                        <?php if ($impressions > 0): ?>
                                            <div class="progress mt-1" style="height: 4px;">
                                                <div class="progress-bar bg-<?php echo $ctr > 1 ? 'success' : ($ctr > 0.5 ? 'warning' : 'danger'); ?>" 
                                                     style="width: <?php echo min($ctr * 10, 100); ?>%"></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('M j, Y', strtotime($zone['created_at'])); ?><br>
                                        <?php echo date('H:i', strtotime($zone['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" onclick="viewZone(<?php echo $zone['id']; ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" title="More Actions">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <button class="dropdown-item" onclick="getZoneCode(<?php echo $zone['id']; ?>, <?php echo htmlspecialchars(json_encode($zone['name']), ENT_QUOTES); ?>, <?php echo $zone['width']; ?>, <?php echo $zone['height']; ?>)">
                                                    <i class="fas fa-code me-2 text-primary"></i>Get Implementation Code
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item" onclick="editZone(<?php echo $zone['id']; ?>)">
                                                    <i class="fas fa-edit me-2 text-info"></i>Edit Zone
                                                </button>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <?php if ($zone['status'] !== 'active'): ?>
                                                <li>
                                                    <button class="dropdown-item" onclick="updateStatus(<?php echo $zone['id']; ?>, 'active')">
                                                        <i class="fas fa-play me-2 text-success"></i>Activate
                                                    </button>
                                                </li>
                                            <?php endif; ?>
                                            <?php if ($zone['status'] !== 'inactive'): ?>
                                                <li>
                                                    <button class="dropdown-item" onclick="updateStatus(<?php echo $zone['id']; ?>, 'inactive')">
                                                        <i class="fas fa-pause me-2 text-warning"></i>Deactivate
                                                    </button>
                                                </li>
                                            <?php endif; ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <button class="dropdown-item text-danger" onclick="deleteZone(<?php echo $zone['id']; ?>, <?php echo htmlspecialchars(json_encode($zone['name']), ENT_QUOTES); ?>)">
                                                    <i class="fas fa-trash me-2"></i>Delete Zone
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Create Zone Modal -->
<div class="modal fade" id="createZoneModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i>Create New Zone
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="createZoneForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="name" class="form-label">Zone Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required 
                                       placeholder="e.g., Header Banner, Sidebar Ad">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="site_id_create" class="form-label">Site <span class="text-danger">*</span></label>
                                <select class="form-select" id="site_id_create" name="site_id" required>
                                    <option value="">Select Site</option>
                                    <?php foreach ($sites as $site): ?>
                                        <option value="<?php echo $site['id']; ?>">
                                            <?php echo htmlspecialchars($site['name']); ?> (<?php echo htmlspecialchars($site['username']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2" 
                                  placeholder="Brief description of zone placement and purpose"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="ad_format_id" class="form-label">Ad Format</label>
                                <select class="form-select" id="ad_format_id" name="ad_format_id">
                                    <option value="">Select Format (Optional)</option>
                                    <?php foreach ($adFormats as $format): ?>
                                        <option value="<?php echo $format['id']; ?>" 
                                                data-width="<?php echo $format['width'] ?? ''; ?>" 
                                                data-height="<?php echo $format['height'] ?? ''; ?>">
                                            <?php echo htmlspecialchars($format['name']); ?>
                                            <?php if ($format['width'] && $format['height']): ?>
                                                (<?php echo $format['width']; ?>×<?php echo $format['height']; ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="width" class="form-label">Width (px) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="width" name="width" required min="1" max="2000" value="728">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="height" class="form-label">Height (px) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="height" name="height" required min="1" max="2000" value="90">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="floor_price" class="form-label">Floor Price ($)</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="floor_price" name="floor_price" 
                                           step="0.0001" value="0.0010" min="0" max="100">
                                </div>
                                <small class="form-text text-muted">Minimum bid price per impression</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="passback_url" class="form-label">Passback URL</label>
                                <input type="url" class="form-control" id="passback_url" name="passback_url" 
                                       placeholder="https://backup-ads.example.com/ad">
                                <small class="form-text text-muted">Fallback ad source when no ads available</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="default_ad_content" class="form-label">Default Ad Content</label>
                        <textarea class="form-control font-monospace" id="default_ad_content" name="default_ad_content" rows="3"
                                  placeholder="<div style='width:728px;height:90px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;'>Your Ad Here</div>"></textarea>
                        <small class="form-text text-muted">HTML content to display when no ads are available</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Create Zone
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Zone Code Modal -->
<div class="modal fade" id="zoneCodeModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-code me-2"></i>Zone Implementation Codes
                    <span id="zoneCodeTitle" class="text-muted"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Choose the implementation method that best fits your website setup:</strong>
                    <ul class="mb-0 mt-2">
                        <li><strong>JavaScript:</strong> Simple synchronous loading (best for static placements)</li>
                        <li><strong>Async:</strong> Non-blocking load (recommended for better performance)</li>
                        <li><strong>iFrame:</strong> Safest method (isolates ad content from your page)</li>
                        <li><strong>Direct API:</strong> For custom implementations and server-side requests</li>
                    </ul>
                </div>
                
                <ul class="nav nav-tabs" id="codeTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="js-tab" data-bs-toggle="tab" data-bs-target="#js-code" type="button" role="tab">
                            <i class="fab fa-js-square me-1"></i>JavaScript
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="async-tab" data-bs-toggle="tab" data-bs-target="#async-code" type="button" role="tab">
                            <i class="fas fa-code me-1"></i>Async
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="iframe-tab" data-bs-toggle="tab" data-bs-target="#iframe-code" type="button" role="tab">
                            <i class="fas fa-window-maximize me-1"></i>iFrame
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="api-tab" data-bs-toggle="tab" data-bs-target="#api-code" type="button" role="tab">
                            <i class="fas fa-link me-1"></i>Direct API
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content mt-3" id="codeTabContent">
                    <div class="tab-pane fade show active" id="js-code" role="tabpanel">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Standard JavaScript Implementation</label>
                            <p class="text-muted small">Simple synchronous loading. Best for static placements.</p>
                            <div class="input-group">
                                <textarea class="form-control font-monospace" id="jsCode" rows="3" readonly></textarea>
                                <button class="btn btn-outline-primary" onclick="copyCode('jsCode')" title="Copy to clipboard">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="async-code" role="tabpanel">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Asynchronous Implementation</label>
                            <p class="text-muted small">Non-blocking load. Recommended for better page performance.</p>
                            <div class="input-group">
                                <textarea class="form-control font-monospace" id="asyncCode" rows="10" readonly></textarea>
                                <button class="btn btn-outline-primary" onclick="copyCode('asyncCode')" title="Copy to clipboard">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="iframe-code" role="tabpanel">
                        <div class="mb-3">
                            <label class="form-label fw-bold">iFrame Implementation</label>
                            <p class="text-muted small">Safest method. Isolates ad content from your page.</p>
                            <div class="input-group">
                                <textarea class="form-control font-monospace" id="iframeCode" rows="6" readonly></textarea>
                                <button class="btn btn-outline-primary" onclick="copyCode('iframeCode')" title="Copy to clipboard">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="api-code" role="tabpanel">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Direct API Endpoint</label>
                            <p class="text-muted small">For custom implementations and server-side requests.</p>
                            <div class="input-group">
                                <input type="text" class="form-control font-monospace" id="apiUrl" readonly>
                                <button class="btn btn-outline-primary" onclick="copyCode('apiUrl')" title="Copy to clipboard">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <h6 class="fw-bold">Supported Parameters:</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <tbody>
                                            <tr><td><code>format</code></td><td>json, html, js, iframe</td></tr>
                                            <tr><td><code>cb</code></td><td>cache buster (timestamp)</td></tr>
                                            <tr><td><code>ip</code></td><td>visitor IP override</td></tr>
                                            <tr><td><code>ua</code></td><td>user agent override</td></tr>
                                            <tr><td><code>ref</code></td><td>referrer URL override</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fw-bold">Response Formats:</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <tbody>
                                            <tr><td><strong>JSON:</strong></td><td>Structured ad data</td></tr>
                                            <tr><td><strong>HTML:</strong></td><td>Ready-to-display markup</td></tr>
                                            <tr><td><strong>JS:</strong></td><td>JavaScript ad code</td></tr>
                                            <tr><td><strong>iframe:</strong></td><td>Complete HTML page</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Implementation Tips:</strong>
                    <ul class="mb-0 small mt-2">
                        <li>Always test ad implementation in a staging environment first</li>
                        <li>Async method is recommended for better user experience and SEO</li>
                        <li>iFrame method provides the best security isolation</li>
                        <li>Add cache buster parameter (?cb=<?php echo time(); ?>) when testing</li>
                        <li>Ensure your zone is active before implementing</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="zone_documentation.php" class="btn btn-outline-primary" target="_blank">
                    <i class="fas fa-book me-1"></i>View Documentation
                </a>
                <button class="btn btn-success" onclick="testZone()">
                    <i class="fas fa-play me-1"></i>Test Zone
                </button>
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
// Global variables
let currentZoneId = null;
let currentZoneWidth = null;
let currentZoneHeight = null;

function updateStatus(zoneId, status) {
    const statusText = status === 'active' ? 'activate' : 'deactivate';
    if (confirm('Are you sure you want to ' + statusText + ' this zone?')) {
        document.getElementById('statusZoneId').value = zoneId;
        document.getElementById('statusValue').value = status;
        document.getElementById('statusForm').submit();
    }
}

function deleteZone(zoneId, zoneName) {
    if (confirm('Are you sure you want to delete the zone "' + zoneName + '"?\n\nThis action cannot be undone and will remove all associated data.')) {
        document.getElementById('deleteZoneId').value = zoneId;
        document.getElementById('deleteForm').submit();
    }
}

function viewZone(zoneId) {
    window.open('zone_view.php?id=' + zoneId, '_blank', 'width=1200,height=800');
}

function editZone(zoneId) {
    window.open('zone_edit.php?id=' + zoneId, '_blank', 'width=1000,height=700');
}

function getZoneCode(zoneId, zoneName, width, height) {
    currentZoneId = zoneId;
    currentZoneWidth = width;
    currentZoneHeight = height;
    
    // Set zone name in modal title
    const titleElement = document.getElementById('zoneCodeTitle');
    if (titleElement) {
        titleElement.textContent = '- ' + zoneName;
    }
    
    // Base URL for ad serving
    const baseUrl = <?php echo json_encode(rtrim(AD_SERVER_URL, '/')); ?>;
    
    // Generate different implementation codes
    const jsCode = '<script src="' + baseUrl + '/api/serve/' + zoneId + '"><\/script>';
    
    const asyncCode = '<!-- AdStart Zone: ' + zoneName + ' (' + width + 'x' + height + ') -->\n' +
        '<div id="adstart-zone-' + zoneId + '" style="width:' + width + 'px;height:' + height + 'px;"></div>\n' +
        '<script>\n' +
        '(function() {\n' +
        '    var adContainer = document.getElementById(\'adstart-zone-' + zoneId + '\');\n' +
        '    if (!adContainer) return;\n' +
        '    \n' +
        '    var xhr = new XMLHttpRequest();\n' +
        '    xhr.open(\'GET\', \'' + baseUrl + '/api/serve/' + zoneId + '?format=json&cb=\' + Date.now(), true);\n' +
        '    xhr.onreadystatechange = function() {\n' +
        '        if (xhr.readyState === 4 && xhr.status === 200) {\n' +
        '            try {\n' +
        '                var response = JSON.parse(xhr.responseText);\n' +
        '                if (response && response.html) {\n' +
        '                    adContainer.innerHTML = response.html;\n' +
        '                } else if (response && response.script) {\n' +
        '                    var script = document.createElement(\'script\');\n' +
        '                    script.innerHTML = response.script;\n' +
        '                    adContainer.appendChild(script);\n' +
        '                } else {\n' +
        '                    adContainer.innerHTML = response.fallback || \'\';\n' +
        '                }\n' +
        '            } catch (e) {\n' +
        '                console.error(\'AdStart: Error loading zone ' + zoneId + ':\', e);\n' +
        '                adContainer.innerHTML = \'<p>Ad loading error</p>\';\n' +
        '            }\n' +
        '        } else if (xhr.readyState === 4) {\n' +
        '            console.error(\'AdStart: HTTP error\', xhr.status);\n' +
        '            adContainer.innerHTML = \'<p>Ad server unavailable</p>\';\n' +
        '        }\n' +
        '    };\n' +
        '    xhr.send();\n' +
        '})();\n' +
        '<\/script>';

    const iframeCode = '<!-- AdStart iFrame: ' + zoneName + ' (' + width + 'x' + height + ') -->\n' +
        '<iframe src="' + baseUrl + '/api/serve/' + zoneId + '?format=iframe&cb=' + Date.now() + '" \n' +
        '        width="' + width + '" \n' +
        '        height="' + height + '" \n' +
        '        frameborder="0" \n' +
        '        scrolling="no"\n' +
        '        style="border: none; overflow: hidden;"\n' +
        '        title="AdStart Zone ' + zoneId + '">\n' +
        '    <p>Your browser doesn\'t support iframes. <a href="' + baseUrl + '/api/serve/' + zoneId + '" target="_blank">View ads</a></p>\n' +
        '</iframe>';

    const apiUrl = baseUrl + '/api/serve/' + zoneId;
    
    // Safely populate modal fields
    const jsCodeElement = document.getElementById('jsCode');
    const asyncCodeElement = document.getElementById('asyncCode');
    const iframeCodeElement = document.getElementById('iframeCode');
    const apiUrlElement = document.getElementById('apiUrl');
    
    if (jsCodeElement) jsCodeElement.value = jsCode;
    if (asyncCodeElement) asyncCodeElement.value = asyncCode;
    if (iframeCodeElement) iframeCodeElement.value = iframeCode;
    if (apiUrlElement) apiUrlElement.value = apiUrl;
    
    // Show modal
    const modal = document.getElementById('zoneCodeModal');
    if (modal) {
        new bootstrap.Modal(modal).show();
    }
}

function copyCode(elementId) {
    const element = document.getElementById(elementId);
    if (!element) {
        showError('Element not found');
        return;
    }
    
    element.select();
    element.setSelectionRange(0, 99999);
    
    // Try modern clipboard API first
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(element.value).then(function() {
            showSuccess('Code copied to clipboard!');
        }).catch(function(err) {
            console.error('Clipboard API failed:', err);
            fallbackCopy(element);
        });
    } else {
        fallbackCopy(element);
    }
}

function fallbackCopy(element) {
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            showSuccess('Code copied to clipboard!');
        } else {
            showError('Failed to copy code. Please copy manually.');
        }
    } catch (err) {
        console.error('Fallback copy failed:', err);
        showError('Failed to copy code. Please copy manually.');
    }
}

function testZone() {
    if (currentZoneId) {
        const baseUrl = <?php echo json_encode(rtrim(AD_SERVER_URL, '/')); ?>;
        const testUrl = baseUrl + '/api/serve/' + currentZoneId + '?format=html&test=1&cb=' + Date.now();
        window.open(testUrl, 'test-zone-' + currentZoneId, 'width=800,height=600,scrollbars=yes');
    } else {
        showError('No zone selected for testing');
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    try {
        // Auto-populate banner sizes when format is selected
        const formatSelect = document.getElementById('ad_format_id');
        if (formatSelect) {
            formatSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const width = selectedOption.getAttribute('data-width');
                const height = selectedOption.getAttribute('data-height');
                
                const widthInput = document.getElementById('width');
                const heightInput = document.getElementById('height');
                
                if (width && height && widthInput && heightInput) {
                    widthInput.value = width;
                    heightInput.value = height;
                }
            });
        }
        
        // Form validation
        const createForm = document.getElementById('createZoneForm');
        if (createForm) {
            createForm.addEventListener('submit', function(e) {
                const nameInput = document.getElementById('name');
                const widthInput = document.getElementById('width');
                const heightInput = document.getElementById('height');
                
                if (!nameInput || !widthInput || !heightInput) {
                    e.preventDefault();
                    showError('Required form fields not found');
                    return;
                }
                
                const name = nameInput.value.trim();
                const width = parseInt(widthInput.value);
                const height = parseInt(heightInput.value);
                
                if (name.length < 3) {
                    e.preventDefault();
                    showError('Zone name must be at least 3 characters long');
                    nameInput.focus();
                    return;
                }
                
                if (isNaN(width) || width < 1 || width > 2000) {
                    e.preventDefault();
                    showError('Width must be between 1 and 2000 pixels');
                    widthInput.focus();
                    return;
                }
                
                if (isNaN(height) || height < 1 || height > 2000) {
                    e.preventDefault();
                    showError('Height must be between 1 and 2000 pixels');
                    heightInput.focus();
                    return;
                }
            });
        }
        
    } catch (error) {
        console.error('DOM ready initialization error:', error);
    }
});

// Real-time stats update with proper error handling
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
                const totalZones = document.getElementById('total-zones');
                const activeZones = document.getElementById('active-zones');
                const todayImpressions = document.getElementById('today-impressions');
                const todayRevenue = document.getElementById('today-revenue');
                
                if (totalZones) totalZones.textContent = parseInt(data.stats.total_zones || 0).toLocaleString();
                if (activeZones) activeZones.textContent = parseInt(data.stats.active_zones || 0).toLocaleString();
                if (todayImpressions) todayImpressions.textContent = parseInt(data.stats.total_impressions_today || 0).toLocaleString();
                if (todayRevenue) todayRevenue.textContent = '$' + parseFloat(data.stats.total_revenue_today || 0).toFixed(2);
            }
        })
        .catch(error => {
            console.error('Stats update error:', error);
        });
}

// Update stats every 30 seconds, but only if page is visible
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
