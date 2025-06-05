<?php
$pageTitle = 'Site Management';
$breadcrumb = [
    ['text' => 'Sites']
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
                case 'approve':
                    $siteId = (int)$_POST['site_id'];
                    $db->update('sites', [
                        'status' => 'active',
                        'approved_at' => date('Y-m-d H:i:s'),
                        'approved_by' => $_SESSION['user_id']
                    ], 'id = ?', [$siteId]);
                    $success = 'Site approved successfully';
                    break;
                    
                case 'reject':
                    $siteId = (int)$_POST['site_id'];
                    $notes = sanitize($_POST['approval_notes']);
                    $db->update('sites', [
                        'status' => 'rejected',
                        'approval_notes' => $notes
                    ], 'id = ?', [$siteId]);
                    $success = 'Site rejected';
                    break;
                    
                case 'suspend':
                    $siteId = (int)$_POST['site_id'];
                    $db->update('sites', ['status' => 'inactive'], 'id = ?', [$siteId]);
                    $success = 'Site suspended';
                    break;
            }
        }
    }

    // Filters
    $status = $_GET['status'] ?? '';
    $category = $_GET['category'] ?? '';
    $search = sanitize($_GET['search'] ?? '');

    // Build query
    $whereConditions = [];
    $params = [];

    if ($status) {
        $whereConditions[] = 's.status = ?';
        $params[] = $status;
    }

    if ($category) {
        $whereConditions[] = 's.category_id = ?';
        $params[] = $category;
    }

    if ($search) {
        $whereConditions[] = '(s.name LIKE ? OR s.url LIKE ? OR u.username LIKE ?)';
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // Get sites
    $sites = [];
    try {
        $sites = $db->fetchAll(
            "SELECT s.*, u.username, u.email, c.name as category_name,
                    (SELECT COUNT(*) FROM zones z WHERE z.site_id = s.id) as zone_count,
                    (SELECT SUM(te.revenue) FROM tracking_events te 
                     JOIN zones z ON te.zone_id = z.id 
                     WHERE z.site_id = s.id AND DATE(te.created_at) = CURDATE()) as today_revenue
             FROM sites s
             JOIN users u ON s.user_id = u.id
             LEFT JOIN categories c ON s.category_id = c.id
             {$whereClause}
             ORDER BY s.created_at DESC",
            $params
        );
    } catch (Exception $e) {
        error_log("Sites query error: " . $e->getMessage());
        $error = "Error loading sites data";
    }

    // Get categories for filter
    $categories = [];
    try {
        $categories = $db->fetchAll("SELECT id, name FROM categories ORDER BY name");
    } catch (Exception $e) {
        error_log("Categories query error: " . $e->getMessage());
    }

    // Statistics
    $stats = [
        'total_sites' => 0,
        'active_sites' => 0,
        'pending_sites' => 0,
        'rejected_sites' => 0
    ];

    try {
        $stats = $db->fetch(
            "SELECT 
                COUNT(*) as total_sites,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_sites,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_sites,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_sites
             FROM sites"
        );
    } catch (Exception $e) {
        error_log("Site stats error: " . $e->getMessage());
    }

    $csrf_token = generateCSRFToken();

} catch (Exception $e) {
    die("Error loading sites page: " . $e->getMessage());
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
                        <h3 class="mb-1"><?php echo number_format($stats['total_sites']); ?></h3>
                        <p class="mb-0">Total Sites</p>
                    </div>
                    <div class="ms-3">
                        <i class="fas fa-globe fa-2x opacity-75"></i>
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
                        <h3 class="mb-1"><?php echo number_format($stats['active_sites']); ?></h3>
                        <p class="mb-0">Active Sites</p>
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
                        <h3 class="mb-1"><?php echo number_format($stats['pending_sites']); ?></h3>
                        <p class="mb-0">Pending Approval</p>
                    </div>
                    <div class="ms-3">
                        <i class="fas fa-clock fa-2x opacity-75"></i>
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
                        <h3 class="mb-1"><?php echo number_format($stats['rejected_sites']); ?></h3>
                        <p class="mb-0">Rejected Sites</p>
                    </div>
                    <div class="ms-3">
                        <i class="fas fa-times fa-2x opacity-75"></i>
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
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Statuses</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Suspended</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="category" class="form-label">Category</label>
                <select class="form-select" id="category" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       placeholder="Search sites, publishers..." value="<?php echo htmlspecialchars($search); ?>">
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

<!-- Sites Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Sites</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Site</th>
                        <th>Publisher</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Zones</th>
                        <th>Today Revenue</th>
                        <th>Monthly Views</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sites as $site): ?>
                        <tr>
                            <td><?php echo $site['id']; ?></td>
                            <td>
                                <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($site['name']); ?></div>
                                    <small class="text-muted">
                                        <a href="<?php echo htmlspecialchars($site['url']); ?>" target="_blank" class="text-decoration-none">
                                            <?php echo htmlspecialchars($site['url']); ?>
                                            <i class="fas fa-external-link-alt ms-1"></i>
                                        </a>
                                    </small>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($site['username']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($site['email']); ?></small>
                                </div>
                            </td>
                            <td>
                                <?php if ($site['category_name']): ?>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($site['category_name']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $statusClass = [
                                    'active' => 'success',
                                    'pending' => 'warning',
                                    'rejected' => 'danger',
                                    'inactive' => 'secondary'
                                ];
                                ?>
                                <span class="badge bg-<?php echo $statusClass[$site['status']] ?? 'secondary'; ?>">
                                    <?php echo ucfirst($site['status']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-primary"><?php echo $site['zone_count']; ?></span>
                            </td>
                            <td>
                                <span class="text-success fw-bold">
                                    $<?php echo number_format($site['today_revenue'] ?? 0, 2); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo number_format($site['monthly_pageviews'] ?? 0); ?>
                            </td>
                            <td>
                                <small><?php echo date('M j, Y', strtotime($site['created_at'])); ?></small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" onclick="viewSite(<?php echo $site['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="fas fa-cog"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <?php if ($site['status'] === 'pending'): ?>
                                            <li>
                                                <button class="dropdown-item" onclick="approveSite(<?php echo $site['id']; ?>)">
                                                    <i class="fas fa-check me-2 text-success"></i>Approve
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item" onclick="rejectSite(<?php echo $site['id']; ?>)">
                                                    <i class="fas fa-times me-2 text-danger"></i>Reject
                                                </button>
                                            </li>
                                        <?php endif; ?>
                                        <?php if ($site['status'] === 'active'): ?>
                                            <li>
                                                <button class="dropdown-item" onclick="suspendSite(<?php echo $site['id']; ?>)">
                                                    <i class="fas fa-ban me-2 text-warning"></i>Suspend
                                                </button>
                                            </li>
                                        <?php endif; ?>
                                        <li>
                                            <a class="dropdown-item" href="zones.php?site_id=<?php echo $site['id']; ?>">
                                                <i class="fas fa-map-marker-alt me-2"></i>View Zones
                                            </a>
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

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Site</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="rejectForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="site_id" id="rejectSiteId">
                    
                    <div class="mb-3">
                        <label for="approval_notes" class="form-label">Rejection Reason</label>
                        <textarea class="form-control" id="approval_notes" name="approval_notes" rows="3" required
                                  placeholder="Please provide a reason for rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Site</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden Forms -->
<form id="approveForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="approve">
    <input type="hidden" name="site_id" id="approveSiteId">
</form>

<form id="suspendForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="suspend">
    <input type="hidden" name="site_id" id="suspendSiteId">
</form>

<script>
function approveSite(siteId) {
    if (confirm('Are you sure you want to approve this site?')) {
        document.getElementById('approveSiteId').value = siteId;
        document.getElementById('approveForm').submit();
    }
}

function rejectSite(siteId) {
    document.getElementById('rejectSiteId').value = siteId;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

function suspendSite(siteId) {
    if (confirm('Are you sure you want to suspend this site?')) {
        document.getElementById('suspendSiteId').value = siteId;
        document.getElementById('suspendForm').submit();
    }
}

function viewSite(siteId) {
    window.open(`site_view.php?id=${siteId}`, '_blank');
}
</script>

<?php include 'templates/footer.php'; ?>
