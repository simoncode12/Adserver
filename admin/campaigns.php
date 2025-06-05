<?php
$pageTitle = 'Campaign Management';
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
            case 'update_status':
                $campaignId = (int)$_POST['campaign_id'];
                $status = sanitize($_POST['status']);
                
                if (in_array($status, ['active', 'paused', 'completed'])) {
                    $db->update('campaigns', 
                        ['status' => $status], 
                        'id = ?', 
                        [$campaignId]
                    );
                    $success = 'Campaign status updated successfully';
                } else {
                    $error = 'Invalid status';
                }
                break;
                
            case 'delete':
                $campaignId = (int)$_POST['campaign_id'];
                $db->delete('campaigns', 'id = ?', [$campaignId]);
                $success = 'Campaign deleted successfully';
                break;
        }
    }
}

// Filters
$status = $_GET['status'] ?? '';
$userType = $_GET['user_type'] ?? '';
$search = sanitize($_GET['search'] ?? '');

// Build query
$whereConditions = [];
$params = [];

if ($status) {
    $whereConditions[] = 'c.status = ?';
    $params[] = $status;
}

if ($search) {
    $whereConditions[] = '(c.name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get campaigns
$campaigns = $db->fetchAll(
    "SELECT c.*, u.username, u.email, u.company_name,
            (SELECT COUNT(*) FROM campaign_creatives cc WHERE cc.campaign_id = c.id) as creative_count,
            (SELECT SUM(te.cost) FROM tracking_events te WHERE te.campaign_id = c.id AND DATE(te.created_at) = CURDATE()) as today_spend,
            (SELECT COUNT(te.id) FROM tracking_events te WHERE te.campaign_id = c.id AND te.event_type = 'impression' AND DATE(te.created_at) = CURDATE()) as today_impressions
     FROM campaigns c
     JOIN users u ON c.user_id = u.id
     {$whereClause}
     ORDER BY c.created_at DESC",
    $params
);

// Statistics
$stats = $db->fetch(
    "SELECT 
        COUNT(*) as total_campaigns,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_campaigns,
        COUNT(CASE WHEN status = 'paused' THEN 1 END) as paused_campaigns,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_campaigns,
        SUM(budget_amount) as total_budget,
        SUM(spent_amount) as total_spent
     FROM campaigns"
);

$csrf_token = generateCSRFToken();

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
                <i class="fas fa-bullhorn fa-2x mb-2"></i>
                <h4><?php echo formatNumber($stats['total_campaigns']); ?></h4>
                <p class="mb-0">Total Campaigns</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card-success">
            <div class="card-body text-center">
                <i class="fas fa-play fa-2x mb-2"></i>
                <h4><?php echo formatNumber($stats['active_campaigns']); ?></h4>
                <p class="mb-0">Active Campaigns</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card-warning">
            <div class="card-body text-center">
                <i class="fas fa-dollar-sign fa-2x mb-2"></i>
                <h4><?php echo formatCurrency($stats['total_budget']); ?></h4>
                <p class="mb-0">Total Budget</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card-info">
            <div class="card-body text-center">
                <i class="fas fa-chart-line fa-2x mb-2"></i>
                <h4><?php echo formatCurrency($stats['total_spent']); ?></h4>
                <p class="mb-0">Total Spent</p>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Statuses</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="paused" <?php echo $status === 'paused' ? 'selected' : ''; ?>>Paused</option>
                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       placeholder="Search campaigns, advertisers..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
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

<!-- Campaigns Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Campaigns</h5>
        <a href="campaign_create.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Create Campaign
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Campaign</th>
                        <th>Advertiser</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Budget</th>
                        <th>Spent</th>
                        <th>Today</th>
                        <th>Creatives</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($campaigns as $campaign): ?>
                        <tr>
                            <td><?php echo $campaign['id']; ?></td>
                            <td>
                                <div class="fw-bold"><?php echo htmlspecialchars($campaign['name']); ?></div>
                                <small class="text-muted">
                                    <?php echo ucfirst($campaign['bid_type']); ?>: 
                                    <?php echo formatCurrency($campaign['bid_amount']); ?>
                                </small>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($campaign['username']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($campaign['company_name'] ?: $campaign['email']); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-info">
                                    <?php echo ucfirst($campaign['campaign_type']); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $statusClass = [
                                    'active' => 'success',
                                    'paused' => 'warning',
                                    'completed' => 'info',
                                    'pending' => 'secondary'
                                ];
                                ?>
                                <span class="badge bg-<?php echo $statusClass[$campaign['status']] ?? 'secondary'; ?>">
                                    <?php echo ucfirst($campaign['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div><?php echo formatCurrency($campaign['budget_amount']); ?></div>
                                <small class="text-muted"><?php echo ucfirst($campaign['budget_type']); ?></small>
                            </td>
                            <td>
                                <div><?php echo formatCurrency($campaign['spent_amount']); ?></div>
                                <?php 
                                $spentPercentage = $campaign['budget_amount'] > 0 
                                    ? ($campaign['spent_amount'] / $campaign['budget_amount']) * 100 
                                    : 0;
                                ?>
                                <div class="progress" style="height: 4px;">
                                    <div class="progress-bar" style="width: <?php echo min($spentPercentage, 100); ?>%"></div>
                                </div>
                            </td>
                            <td>
                                <div class="text-success"><?php echo formatCurrency($campaign['today_spend'] ?? 0); ?></div>
                                <small class="text-muted"><?php echo formatNumber($campaign['today_impressions'] ?? 0); ?> imp</small>
                            </td>
                            <td>
                                <span class="badge bg-primary"><?php echo $campaign['creative_count']; ?></span>
                            </td>
                            <td>
                                <small><?php echo date('M j, Y', strtotime($campaign['created_at'])); ?></small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" onclick="viewCampaign(<?php echo $campaign['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="fas fa-cog"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                                                                        <button class="dropdown-item" onclick="updateStatus(<?php echo $campaign['id']; ?>, 'active')">
                                                <i class="fas fa-play me-2 text-success"></i>Activate
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item" onclick="updateStatus(<?php echo $campaign['id']; ?>, 'paused')">
                                                <i class="fas fa-pause me-2 text-warning"></i>Pause
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item" onclick="updateStatus(<?php echo $campaign['id']; ?>, 'completed')">
                                                <i class="fas fa-stop me-2 text-info"></i>Complete
                                            </button>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <button class="dropdown-item text-danger" onclick="deleteCampaign(<?php echo $campaign['id']; ?>)">
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

<!-- Hidden Forms -->
<form id="statusForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="campaign_id" id="statusCampaignId">
    <input type="hidden" name="status" id="statusValue">
</form>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="campaign_id" id="deleteCampaignId">
</form>

<script>
function updateStatus(campaignId, status) {
    if (confirm(`Are you sure you want to ${status} this campaign?`)) {
        document.getElementById('statusCampaignId').value = campaignId;
        document.getElementById('statusValue').value = status;
        document.getElementById('statusForm').submit();
    }
}

function deleteCampaign(campaignId) {
    if (confirm('Are you sure you want to delete this campaign? This action cannot be undone.')) {
        document.getElementById('deleteCampaignId').value = campaignId;
        document.getElementById('deleteForm').submit();
    }
}

function viewCampaign(campaignId) {
    window.open(`campaign_view.php?id=${campaignId}`, '_blank');
}
</script>

<?php include 'templates/footer.php'; ?>
                
