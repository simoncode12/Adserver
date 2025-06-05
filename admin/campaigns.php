<?php
$pageTitle = 'Campaign Management';
$breadcrumb = [
    ['text' => 'Campaigns']
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
                    COUNT(*) as total_campaigns,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_campaigns,
                    COUNT(CASE WHEN status = 'paused' THEN 1 END) as paused_campaigns,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_campaigns,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_campaigns,
                    COALESCE(SUM(budget_amount), 0) as total_budget,
                    COALESCE(SUM(spent_amount), 0) as total_spent,
                    (SELECT COUNT(*) FROM tracking_events te WHERE te.campaign_id IS NOT NULL AND DATE(te.created_at) = CURDATE()) as total_impressions_today,
                    (SELECT COALESCE(SUM(te.cost), 0) FROM tracking_events te WHERE te.campaign_id IS NOT NULL AND DATE(te.created_at) = CURDATE()) as total_cost_today
                 FROM campaigns"
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
                            'user_id' => (int)$_POST['user_id'],
                            'name' => sanitize($_POST['name']),
                            'description' => sanitize($_POST['description']),
                            'campaign_type' => sanitize($_POST['campaign_type']),
                            'budget_type' => sanitize($_POST['budget_type']),
                            'budget_amount' => (float)$_POST['budget_amount'],
                            'daily_budget' => (float)($_POST['daily_budget'] ?? 0),
                            'bid_type' => sanitize($_POST['bid_type']),
                            'bid_amount' => (float)$_POST['bid_amount'],
                            'start_date' => sanitize($_POST['start_date']),
                            'end_date' => sanitize($_POST['end_date']),
                            'target_countries' => sanitize($_POST['target_countries']),
                            'target_categories' => sanitize($_POST['target_categories']),
                            'frequency_cap' => (int)($_POST['frequency_cap'] ?? 0),
                            'priority' => (int)($_POST['priority'] ?? 5),
                            'status' => 'pending',
                            'created_by' => $_SESSION['user_id'],
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        
                        $campaignId = $db->insert('campaigns', $data);
                        $success = 'Campaign created successfully with ID: ' . $campaignId;
                    } catch (Exception $e) {
                        $error = 'Failed to create campaign: ' . $e->getMessage();
                    }
                    break;
                    
                case 'update_status':
                    try {
                        $campaignId = (int)$_POST['campaign_id'];
                        $status = sanitize($_POST['status']);
                        
                        if (in_array($status, ['active', 'paused', 'completed', 'pending'])) {
                            $updateData = ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')];
                            
                            // Set completion date if completed
                            if ($status === 'completed') {
                                $updateData['completed_at'] = date('Y-m-d H:i:s');
                            }
                            
                            $db->update('campaigns', $updateData, 'id = ?', [$campaignId]);
                            $success = 'Campaign status updated successfully';
                        } else {
                            $error = 'Invalid status';
                        }
                    } catch (Exception $e) {
                        $error = 'Failed to update status: ' . $e->getMessage();
                    }
                    break;
                    
                case 'delete':
                    try {
                        $campaignId = (int)$_POST['campaign_id'];
                        
                        // Check if campaign has active tracking events
                        $hasEvents = $db->fetch(
                            "SELECT COUNT(*) as count FROM tracking_events WHERE campaign_id = ?",
                            [$campaignId]
                        );
                        
                        if ($hasEvents['count'] > 0) {
                            $error = 'Cannot delete campaign with existing tracking data. Please archive instead.';
                        } else {
                            $db->delete('campaigns', 'id = ?', [$campaignId]);
                            $success = 'Campaign deleted successfully';
                        }
                    } catch (Exception $e) {
                        $error = 'Failed to delete campaign: ' . $e->getMessage();
                    }
                    break;
                    
                case 'duplicate':
                    try {
                        $campaignId = (int)$_POST['campaign_id'];
                        $originalCampaign = $db->fetch("SELECT * FROM campaigns WHERE id = ?", [$campaignId]);
                        
                        if ($originalCampaign) {
                            unset($originalCampaign['id']);
                            $originalCampaign['name'] = 'Copy of ' . $originalCampaign['name'];
                            $originalCampaign['status'] = 'pending';
                            $originalCampaign['spent_amount'] = 0;
                            $originalCampaign['created_at'] = date('Y-m-d H:i:s');
                            $originalCampaign['created_by'] = $_SESSION['user_id'];
                            
                            $newCampaignId = $db->insert('campaigns', $originalCampaign);
                            $success = 'Campaign duplicated successfully with ID: ' . $newCampaignId;
                        } else {
                            $error = 'Campaign not found';
                        }
                    } catch (Exception $e) {
                        $error = 'Failed to duplicate campaign: ' . $e->getMessage();
                    }
                    break;
            }
        }
    }

    // Filters
    $status = $_GET['status'] ?? '';
    $userId = (int)($_GET['user_id'] ?? 0);
    $campaignType = $_GET['campaign_type'] ?? '';
    $search = sanitize($_GET['search'] ?? '');

    // Build query
    $whereConditions = [];
    $params = [];

    if ($status) {
        $whereConditions[] = 'c.status = ?';
        $params[] = $status;
    }

    if ($userId) {
        $whereConditions[] = 'c.user_id = ?';
        $params[] = $userId;
    }

    if ($campaignType) {
        $whereConditions[] = 'c.campaign_type = ?';
        $params[] = $campaignType;
    }

    if ($search) {
        $whereConditions[] = '(c.name LIKE ? OR c.description LIKE ? OR u.username LIKE ? OR u.company_name LIKE ?)';
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // Get campaigns with enhanced data
    $campaigns = [];
    try {
        $campaigns = $db->fetchAll(
            "SELECT c.*, u.username, u.email, u.company_name,
                    (SELECT COUNT(*) FROM campaign_creatives cc WHERE cc.campaign_id = c.id) as creative_count,
                    (SELECT COALESCE(SUM(te.cost), 0) FROM tracking_events te WHERE te.campaign_id = c.id AND DATE(te.created_at) = CURDATE()) as today_spend,
                    (SELECT COUNT(*) FROM tracking_events te WHERE te.campaign_id = c.id AND te.event_type = 'impression' AND DATE(te.created_at) = CURDATE()) as today_impressions,
                    (SELECT COUNT(*) FROM tracking_events te WHERE te.campaign_id = c.id AND te.event_type = 'click' AND DATE(te.created_at) = CURDATE()) as today_clicks,
                    (SELECT COUNT(*) FROM tracking_events te WHERE te.campaign_id = c.id AND te.event_type = 'conversion' AND DATE(te.created_at) = CURDATE()) as today_conversions,
                    (SELECT COALESCE(SUM(te.cost), 0) FROM tracking_events te WHERE te.campaign_id = c.id AND DATE(te.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) as week_spend,
                    (SELECT COUNT(*) FROM tracking_events te WHERE te.campaign_id = c.id AND te.event_type = 'impression' AND DATE(te.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) as week_impressions
             FROM campaigns c
             JOIN users u ON c.user_id = u.id
             {$whereClause}
             ORDER BY c.created_at DESC",
            $params
        );
    } catch (Exception $e) {
        error_log("Campaigns query error: " . $e->getMessage());
        $error = "Error loading campaigns data";
        $campaigns = [];
    }

    // Get advertisers for filter and create form
    $advertisers = [];
    try {
        $advertisers = $db->fetchAll(
            "SELECT id, username, email, company_name, first_name, last_name 
             FROM users 
             WHERE user_type = 'advertiser' AND status = 'active' 
             ORDER BY username"
        );
    } catch (Exception $e) {
        error_log("Advertisers query error: " . $e->getMessage());
        $advertisers = [];
    }

    // Get categories for targeting
    $categories = [];
    try {
        $categories = $db->fetchAll("SELECT id, name FROM categories ORDER BY name");
    } catch (Exception $e) {
        error_log("Categories query error: " . $e->getMessage());
        $categories = [];
    }

    // Statistics
    $stats = [
        'total_campaigns' => 0,
        'active_campaigns' => 0,
        'paused_campaigns' => 0,
        'completed_campaigns' => 0,
        'pending_campaigns' => 0,
        'total_budget' => 0,
        'total_spent' => 0,
        'total_impressions_today' => 0,
        'total_cost_today' => 0
    ];

    try {
        $result = $db->fetch(
            "SELECT 
                COUNT(*) as total_campaigns,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_campaigns,
                COUNT(CASE WHEN status = 'paused' THEN 1 END) as paused_campaigns,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_campaigns,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_campaigns,
                COALESCE(SUM(budget_amount), 0) as total_budget,
                COALESCE(SUM(spent_amount), 0) as total_spent,
                (SELECT COUNT(*) FROM tracking_events te WHERE te.campaign_id IS NOT NULL AND DATE(te.created_at) = CURDATE()) as total_impressions_today,
                (SELECT COALESCE(SUM(te.cost), 0) FROM tracking_events te WHERE te.campaign_id IS NOT NULL AND DATE(te.created_at) = CURDATE()) as total_cost_today
             FROM campaigns"
        );
        if ($result) {
            $stats = $result;
        }
    } catch (Exception $e) {
        error_log("Campaign stats error: " . $e->getMessage());
    }

    $csrf_token = generateCSRFToken();

} catch (Exception $e) {
    die("Error loading campaigns page: " . $e->getMessage());
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
                            <h3 class="mb-1" id="total-campaigns"><?php echo number_format($stats['total_campaigns']); ?></h3>
                            <p class="mb-0">Total Campaigns</p>
                        </div>
                        <div class="ms-3">
                            <i class="fas fa-bullhorn fa-2x opacity-75"></i>
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
                            <h3 class="mb-1" id="active-campaigns"><?php echo number_format($stats['active_campaigns']); ?></h3>
                            <p class="mb-0">Active Campaigns</p>
                        </div>
                        <div class="ms-3">
                            <i class="fas fa-play fa-2x opacity-75"></i>
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
                            <h3 class="mb-1" id="total-budget">$<?php echo number_format($stats['total_budget'], 2); ?></h3>
                            <p class="mb-0">Total Budget</p>
                        </div>
                        <div class="ms-3">
                            <i class="fas fa-dollar-sign fa-2x opacity-75"></i>
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
                            <h3 class="mb-1" id="total-spent">$<?php echo number_format($stats['total_spent'], 2); ?></h3>
                            <p class="mb-0">Total Spent</p>
                        </div>
                        <div class="ms-3">
                            <i class="fas fa-chart-line fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Stats Row -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Campaign Distribution</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-3">
                        <h4 class="text-success mb-1"><?php echo number_format($stats['active_campaigns']); ?></h4>
                        <small class="text-muted">Active</small>
                    </div>
                    <div class="col-3">
                        <h4 class="text-warning mb-1"><?php echo number_format($stats['paused_campaigns']); ?></h4>
                        <small class="text-muted">Paused</small>
                    </div>
                    <div class="col-3">
                        <h4 class="text-info mb-1"><?php echo number_format($stats['completed_campaigns']); ?></h4>
                        <small class="text-muted">Completed</small>
                    </div>
                    <div class="col-3">
                        <h4 class="text-secondary mb-1"><?php echo number_format($stats['pending_campaigns']); ?></h4>
                        <small class="text-muted">Pending</small>
                    </div>
                </div>
                <hr class="my-3">
                <div class="row text-center">
                    <div class="col-6">
                        <h5 class="text-primary mb-1"><?php echo number_format($stats['total_impressions_today']); ?></h5>
                        <small class="text-muted">Today's Impressions</small>
                    </div>
                    <div class="col-6">
                        <h5 class="text-danger mb-1">$<?php echo number_format($stats['total_cost_today'], 2); ?></h5>
                        <small class="text-muted">Today's Spend</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-tachometer-alt me-2"></i>Performance Overview</h5>
            </div>
            <div class="card-body">
                <?php 
                $totalBudget = $stats['total_budget'];
                $totalSpent = $stats['total_spent'];
                $spentPercentage = $totalBudget > 0 ? ($totalSpent / $totalBudget) * 100 : 0;
                $avgCPM = $stats['total_impressions_today'] > 0 ? ($stats['total_cost_today'] / $stats['total_impressions_today']) * 1000 : 0;
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Budget Utilization</span>
                        <span class="fw-bold"><?php echo number_format($spentPercentage, 1); ?>%</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-<?php echo $spentPercentage > 80 ? 'danger' : ($spentPercentage > 50 ? 'warning' : 'success'); ?>" 
                             style="width: <?php echo min($spentPercentage, 100); ?>%"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Average CPM Today</span>
                        <span class="fw-bold">$<?php echo number_format($avgCPM, 2); ?></span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Active Campaigns</span>
                        <span class="fw-bold text-success"><?php echo number_format($stats['active_campaigns']); ?></span>
                    </div>
                </div>
                
                <div class="text-center">
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createCampaignModal">
                        <i class="fas fa-plus me-1"></i>New Campaign
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
                    <option value="paused" <?php echo $status === 'paused' ? 'selected' : ''; ?>>Paused</option>
                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="user_id" class="form-label">Advertiser</label>
                <select class="form-select" id="user_id" name="user_id">
                    <option value="">All Advertisers</option>
                    <?php foreach ($advertisers as $advertiser): ?>
                        <option value="<?php echo $advertiser['id']; ?>" <?php echo $userId == $advertiser['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($advertiser['username']); ?>
                            <?php if ($advertiser['company_name']): ?>
                                (<?php echo htmlspecialchars($advertiser['company_name']); ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="campaign_type" class="form-label">Type</label>
                <select class="form-select" id="campaign_type" name="campaign_type">
                    <option value="">All Types</option>
                    <option value="display" <?php echo $campaignType === 'display' ? 'selected' : ''; ?>>Display</option>
                    <option value="video" <?php echo $campaignType === 'video' ? 'selected' : ''; ?>>Video</option>
                    <option value="native" <?php echo $campaignType === 'native' ? 'selected' : ''; ?>>Native</option>
                    <option value="mobile" <?php echo $campaignType === 'mobile' ? 'selected' : ''; ?>>Mobile</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       placeholder="Search campaigns, advertisers..." value="<?php echo htmlspecialchars($search); ?>">
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

<!-- Campaigns Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Campaigns (<?php echo count($campaigns); ?>)</h5>
        <div class="btn-group">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCampaignModal">
                <i class="fas fa-plus me-2"></i>Create Campaign
            </button>
            <button class="btn btn-outline-secondary" onclick="exportCampaigns()">
                <i class="fas fa-download me-2"></i>Export
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <?php if (empty($campaigns)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-bullhorn fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">No Campaigns Found</h4>
                    <p class="text-muted mb-4">Create your first campaign to start advertising</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCampaignModal">
                        <i class="fas fa-plus me-2"></i>Create Your First Campaign
                    </button>
                </div>
            <?php else: ?>
                <table class="table table-hover" id="campaignsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Campaign Details</th>
                            <th>Advertiser</th>
                            <th>Type & Bidding</th>
                            <th>Status</th>
                            <th>Budget & Spend</th>
                            <th class="no-sort">Today's Performance</th>
                            <th class="no-sort">Metrics</th>
                            <th>Dates</th>
                            <th class="no-sort">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($campaigns as $campaign): ?>
                            <tr data-campaign-id="<?php echo $campaign['id']; ?>">
                                <td>
                                    <span class="fw-bold"><?php echo $campaign['id']; ?></span>
                                </td>
                                <td>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($campaign['name']); ?></div>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($campaign['description'] ?: 'No description'); ?>
                                        </small>
                                        <?php if ($campaign['creative_count'] > 0): ?>
                                            <br><span class="badge bg-info"><?php echo $campaign['creative_count']; ?> creatives</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($campaign['username']); ?></div>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($campaign['company_name'] ?: $campaign['email']); ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <span class="badge bg-secondary mb-1">
                                            <?php echo ucfirst($campaign['campaign_type'] ?? 'display'); ?>
                                        </span>
                                        <br>
                                        <small class="fw-bold">
                                            <?php echo strtoupper($campaign['bid_type'] ?? 'cpm'); ?>: 
                                            $<?php echo number_format($campaign['bid_amount'] ?? 0, 4); ?>
                                        </small>
                                    </div>
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
                                    <?php if ($campaign['priority']): ?>
                                        <br><small class="text-muted">Priority: <?php echo $campaign['priority']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $budgetAmount = $campaign['budget_amount'] ?? 0;
                                    $spentAmount = $campaign['spent_amount'] ?? 0;
                                    $spentPercentage = $budgetAmount > 0 ? ($spentAmount / $budgetAmount) * 100 : 0;
                                    ?>
                                    <div>
                                        <div><strong>$<?php echo number_format($budgetAmount, 2); ?></strong></div>
                                        <small class="text-muted"><?php echo ucfirst($campaign['budget_type'] ?? 'total'); ?></small>
                                        <div class="mt-1">
                                            <small>Spent: $<?php echo number_format($spentAmount, 2); ?></small>
                                            <div class="progress" style="height: 4px;">
                                                <div class="progress-bar bg-<?php echo $spentPercentage > 80 ? 'danger' : 'primary'; ?>" 
                                                     style="width: <?php echo min($spentPercentage, 100); ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small">
                                        <div><i class="fas fa-eye text-primary me-1"></i><strong><?php echo number_format($campaign['today_impressions'] ?? 0); ?></strong></div>
                                        <div><i class="fas fa-mouse-pointer text-info me-1"></i><strong><?php echo number_format($campaign['today_clicks'] ?? 0); ?></strong></div>
                                        <div><i class="fas fa-target text-success me-1"></i><strong><?php echo number_format($campaign['today_conversions'] ?? 0); ?></strong></div>
                                        <div><i class="fas fa-dollar-sign text-danger me-1"></i><strong>$<?php echo number_format($campaign['today_spend'] ?? 0, 2); ?></strong></div>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $impressions = $campaign['today_impressions'] ?? 0;
                                    $clicks = $campaign['today_clicks'] ?? 0;
                                    $conversions = $campaign['today_conversions'] ?? 0;
                                    $spend = $campaign['today_spend'] ?? 0;
                                    
                                    $ctr = $impressions > 0 ? ($clicks / $impressions) * 100 : 0;
                                    $cvr = $clicks > 0 ? ($conversions / $clicks) * 100 : 0;
                                    $cpm = $impressions > 0 ? ($spend / $impressions) * 1000 : 0;
                                    ?>
                                    <div class="small">
                                        <div>CTR: <strong><?php echo number_format($ctr, 2); ?>%</strong></div>
                                        <div>CVR: <strong><?php echo number_format($cvr, 2); ?>%</strong></div>
                                        <div>CPM: <strong>$<?php echo number_format($cpm, 2); ?></strong></div>
                                        <?php if ($impressions > 0): ?>
                                            <div class="progress mt-1" style="height: 4px;">
                                                <div class="progress-bar bg-<?php echo $ctr > 2 ? 'success' : ($ctr > 1 ? 'warning' : 'danger'); ?>" 
                                                     style="width: <?php echo min($ctr * 20, 100); ?>%"></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="small">
                                        <div><strong>Start:</strong> <?php echo date('M j, Y', strtotime($campaign['start_date'])); ?></div>
                                        <?php if ($campaign['end_date']): ?>
                                            <div><strong>End:</strong> <?php echo date('M j, Y', strtotime($campaign['end_date'])); ?></div>
                                        <?php endif; ?>
                                        <div class="text-muted">Created: <?php echo date('M j', strtotime($campaign['created_at'])); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" onclick="viewCampaign(<?php echo $campaign['id']; ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" title="More Actions">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <button class="dropdown-item" onclick="editCampaign(<?php echo $campaign['id']; ?>)">
                                                    <i class="fas fa-edit me-2 text-info"></i>Edit Campaign
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item" onclick="manageCreatives(<?php echo $campaign['id']; ?>)">
                                                    <i class="fas fa-images me-2 text-primary"></i>Manage Creatives
                                                </button>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <?php if ($campaign['status'] !== 'active'): ?>
                                                <li>
                                                    <button class="dropdown-item" onclick="updateStatus(<?php echo $campaign['id']; ?>, 'active')">
                                                        <i class="fas fa-play me-2 text-success"></i>Activate
                                                    </button>
                                                </li>
                                            <?php endif; ?>
                                            <?php if ($campaign['status'] === 'active'): ?>
                                                <li>
                                                    <button class="dropdown-item" onclick="updateStatus(<?php echo $campaign['id']; ?>, 'paused')">
                                                        <i class="fas fa-pause me-2 text-warning"></i>Pause
                                                    </button>
                                                </li>
                                            <?php endif; ?>
                                            <?php if ($campaign['status'] !== 'completed'): ?>
                                                <li>
                                                    <button class="dropdown-item" onclick="updateStatus(<?php echo $campaign['id']; ?>, 'completed')">
                                                        <i class="fas fa-stop me-2 text-info"></i>Complete
                                                    </button>
                                                </li>
                                            <?php endif; ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <button class="dropdown-item" onclick="duplicateCampaign(<?php echo $campaign['id']; ?>)">
                                                    <i class="fas fa-copy me-2 text-secondary"></i>Duplicate
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item text-danger" onclick="deleteCampaign(<?php echo $campaign['id']; ?>, <?php echo htmlspecialchars(json_encode($campaign['name']), ENT_QUOTES); ?>)">
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
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Create Campaign Modal -->
<div class="modal fade" id="createCampaignModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i>Create New Campaign
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="createCampaignForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <!-- Basic Information -->
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="name" class="form-label">Campaign Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required 
                                       placeholder="e.g., Summer Sale 2025, Mobile App Promotion">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="user_id_create" class="form-label">Advertiser <span class="text-danger">*</span></label>
                                <select class="form-select" id="user_id_create" name="user_id" required>
                                    <option value="">Select Advertiser</option>
                                    <?php foreach ($advertisers as $advertiser): ?>
                                        <option value="<?php echo $advertiser['id']; ?>">
                                            <?php echo htmlspecialchars($advertiser['username']); ?>
                                            <?php if ($advertiser['company_name']): ?>
                                                (<?php echo htmlspecialchars($advertiser['company_name']); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2" 
                                  placeholder="Brief description of the campaign goals and target audience"></textarea>
                    </div>
                    
                    <!-- Campaign Type and Bidding -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="campaign_type" class="form-label">Campaign Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="campaign_type" name="campaign_type" required>
                                    <option value="">Select Type</option>
                                    <option value="display">Display</option>
                                    <option value="video">Video</option>
                                    <option value="native">Native</option>
                                    <option value="mobile">Mobile</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="bid_type" class="form-label">Bid Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="bid_type" name="bid_type" required>
                                    <option value="">Select Bid Type</option>
                                    <option value="cpm">CPM (Cost Per Mille)</option>
                                    <option value="cpc">CPC (Cost Per Click)</option>
                                    <option value="cpa">CPA (Cost Per Action)</option>
                                    <option value="cpi">CPI (Cost Per Install)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="bid_amount" class="form-label">Bid Amount ($) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="bid_amount" name="bid_amount" 
                                           step="0.0001" required min="0.0001" placeholder="0.0050">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="priority" class="form-label">Priority</label>
                                <select class="form-select" id="priority" name="priority">
                                    <option value="1">1 (Highest)</option>
                                    <option value="2">2 (High)</option>
                                    <option value="3">3 (Medium-High)</option>
                                    <option value="4">4 (Medium)</option>
                                    <option value="5" selected>5 (Normal)</option>
                                    <option value="6">6 (Medium-Low)</option>
                                    <option value="7">7 (Low)</option>
                                    <option value="8">8 (Lower)</option>
                                    <option value="9">9 (Lowest)</option>
                                    <option value="10">10 (Background)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Budget Settings -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="budget_type" class="form-label">Budget Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="budget_type" name="budget_type" required>
                                    <option value="">Select Budget Type</option>
                                    <option value="total">Total Budget</option>
                                    <option value="daily">Daily Budget</option>
                                    <option value="monthly">Monthly Budget</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="budget_amount" class="form-label">Budget Amount ($) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="budget_amount" name="budget_amount" 
                                           step="0.01" required min="1" placeholder="1000.00">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="daily_budget" class="form-label">Daily Cap ($)</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="daily_budget" name="daily_budget" 
                                           step="0.01" min="0" placeholder="100.00">
                                </div>
                                <small class="form-text text-muted">Optional daily spending limit</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Schedule -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required 
                                       value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date">
                                <small class="form-text text-muted">Leave empty for ongoing campaign</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Targeting -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="target_countries" class="form-label">Target Countries</label>
                                <input type="text" class="form-control" id="target_countries" name="target_countries" 
                                       placeholder="US,CA,GB,AU (leave empty for worldwide)">
                                <small class="form-text text-muted">Comma-separated country codes</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="target_categories" class="form-label">Target Categories</label>
                                <select class="form-select" id="target_categories" name="target_categories" multiple>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">Hold Ctrl/Cmd to select multiple</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Advanced Settings -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="frequency_cap" class="form-label">Frequency Cap</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="frequency_cap" name="frequency_cap" 
                                           min="0" placeholder="0">
                                    <span class="input-group-text">impressions per user per day</span>
                                </div>
                                <small class="form-text text-muted">0 = no limit</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Create Campaign
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
    <input type="hidden" name="campaign_id" id="statusCampaignId">
    <input type="hidden" name="status" id="statusValue">
</form>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="campaign_id" id="deleteCampaignId">
</form>

<form id="duplicateForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="duplicate">
    <input type="hidden" name="campaign_id" id="duplicateCampaignId">
</form>

<script>
// Global variables
let currentCampaignId = null;

function updateStatus(campaignId, status) {
    const statusText = status === 'active' ? 'activate' : 
                      status === 'paused' ? 'pause' : 
                      status === 'completed' ? 'complete' : status;
    
    if (confirm('Are you sure you want to ' + statusText + ' this campaign?')) {
        document.getElementById('statusCampaignId').value = campaignId;
        document.getElementById('statusValue').value = status;
        document.getElementById('statusForm').submit();
    }
}

function deleteCampaign(campaignId, campaignName) {
    if (confirm('Are you sure you want to delete the campaign "' + campaignName + '"?\n\nThis action cannot be undone. Consider pausing the campaign instead if you want to preserve tracking data.')) {
        document.getElementById('deleteCampaignId').value = campaignId;
        document.getElementById('deleteForm').submit();
    }
}

function duplicateCampaign(campaignId) {
    if (confirm('Create a copy of this campaign?')) {
        document.getElementById('duplicateCampaignId').value = campaignId;
        document.getElementById('duplicateForm').submit();
    }
}

function viewCampaign(campaignId) {
    window.open('campaign_view.php?id=' + campaignId, '_blank', 'width=1200,height=800');
}

function editCampaign(campaignId) {
    window.open('campaign_edit.php?id=' + campaignId, '_blank', 'width=1000,height=700');
}

function manageCreatives(campaignId) {
    window.open('campaign_creatives.php?campaign_id=' + campaignId, '_blank', 'width=1200,height=800');
}

function exportCampaigns() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    window.open('campaigns_export.php?' + params.toString(), '_blank');
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    try {
        // Form validation
        const createForm = document.getElementById('createCampaignForm');
        if (createForm) {
            createForm.addEventListener('submit', function(e) {
                const nameInput = document.getElementById('name');
                const userIdInput = document.getElementById('user_id_create');
                const campaignTypeInput = document.getElementById('campaign_type');
                const bidTypeInput = document.getElementById('bid_type');
                const bidAmountInput = document.getElementById('bid_amount');
                const budgetTypeInput = document.getElementById('budget_type');
                const budgetAmountInput = document.getElementById('budget_amount');
                const startDateInput = document.getElementById('start_date');
                
                if (!nameInput || !userIdInput || !campaignTypeInput || !bidTypeInput || 
                    !bidAmountInput || !budgetTypeInput || !budgetAmountInput || !startDateInput) {
                    e.preventDefault();
                    showError('Required form fields not found');
                    return;
                }
                
                const name = nameInput.value.trim();
                const bidAmount = parseFloat(bidAmountInput.value);
                const budgetAmount = parseFloat(budgetAmountInput.value);
                const startDate = new Date(startDateInput.value);
                const endDate = document.getElementById('end_date').value ? new Date(document.getElementById('end_date').value) : null;
                
                if (name.length < 3) {
                    e.preventDefault();
                    showError('Campaign name must be at least 3 characters long');
                    nameInput.focus();
                    return;
                }
                
                if (isNaN(bidAmount) || bidAmount < 0.0001) {
                    e.preventDefault();
                    showError('Bid amount must be at least $0.0001');
                    bidAmountInput.focus();
                    return;
                }
                
                if (isNaN(budgetAmount) || budgetAmount < 1) {
                    e.preventDefault();
                    showError('Budget amount must be at least $1.00');
                    budgetAmountInput.focus();
                    return;
                }
                
                if (startDate < new Date().setHours(0,0,0,0)) {
                    e.preventDefault();
                    showError('Start date cannot be in the past');
                    startDateInput.focus();
                    return;
                }
                
                if (endDate && endDate <= startDate) {
                    e.preventDefault();
                    showError('End date must be after start date');
                    document.getElementById('end_date').focus();
                    return;
                }
            });
        }
        
        // Budget type change handler
        const budgetTypeSelect = document.getElementById('budget_type');
        const dailyBudgetInput = document.getElementById('daily_budget');
        
        if (budgetTypeSelect && dailyBudgetInput) {
            budgetTypeSelect.addEventListener('change', function() {
                if (this.value === 'daily') {
                    dailyBudgetInput.disabled = true;
                    dailyBudgetInput.value = '';
                    dailyBudgetInput.placeholder = 'Same as budget amount';
                } else {
                    dailyBudgetInput.disabled = false;
                    dailyBudgetInput.placeholder = '100.00';
                }
            });
        }
        
        // Bid type help text
        const bidTypeSelect = document.getElementById('bid_type');
        if (bidTypeSelect) {
            bidTypeSelect.addEventListener('change', function() {
                const bidAmountInput = document.getElementById('bid_amount');
                if (bidAmountInput) {
                    switch(this.value) {
                        case 'cpm':
                            bidAmountInput.placeholder = '2.5000';
                            break;
                        case 'cpc':
                            bidAmountInput.placeholder = '0.2500';
                            break;
                        case 'cpa':
                            bidAmountInput.placeholder = '15.0000';
                            break;
                        case 'cpi':
                            bidAmountInput.placeholder = '1.5000';
                            break;
                        default:
                            bidAmountInput.placeholder = '0.0050';
                    }
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
                const totalCampaigns = document.getElementById('total-campaigns');
                const activeCampaigns = document.getElementById('active-campaigns');
                const totalBudget = document.getElementById('total-budget');
                const totalSpent = document.getElementById('total-spent');
                
                if (totalCampaigns) totalCampaigns.textContent = parseInt(data.stats.total_campaigns || 0).toLocaleString();
                if (activeCampaigns) activeCampaigns.textContent = parseInt(data.stats.active_campaigns || 0).toLocaleString();
                if (totalBudget) totalBudget.textContent = '$' + parseFloat(data.stats.total_budget || 0).toFixed(2);
                if (totalSpent) totalSpent.textContent = '$' + parseFloat(data.stats.total_spent || 0).toFixed(2);
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
