<?php
$pageTitle = 'RON Campaigns';
$breadcrumb = [
    ['text' => 'Campaigns'],
    ['text' => 'RON Campaigns']
];

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth = new Auth();
$auth->requireAuth();

$db = Database::getInstance();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = sanitize($_POST['name']);
        $advertiser_id = (int)$_POST['advertiser_id'];
        $bid_type = sanitize($_POST['bid_type']);
        $bid_amount = (float)$_POST['bid_amount'];
        $daily_budget = $_POST['daily_budget'] ? (float)$_POST['daily_budget'] : null;
        $total_budget = $_POST['total_budget'] ? (float)$_POST['total_budget'] : null;
        $category_id = $_POST['category_id'] ? (int)$_POST['category_id'] : null;
        $start_date = $_POST['start_date'] ?: null;
        $end_date = $_POST['end_date'] ?: null;
        
        // Process targeting data
        $target_countries = !empty($_POST['target_countries']) ? json_encode($_POST['target_countries']) : null;
        $target_devices = !empty($_POST['target_devices']) ? json_encode($_POST['target_devices']) : null;
        $target_browsers = !empty($_POST['target_browsers']) ? json_encode($_POST['target_browsers']) : null;
        $target_os = !empty($_POST['target_os']) ? json_encode($_POST['target_os']) : null;
        $banner_sizes = !empty($_POST['banner_sizes']) ? json_encode($_POST['banner_sizes']) : null;
        
        // Validate required fields
        if (empty($name) || empty($advertiser_id) || empty($bid_amount)) {
            throw new Exception('Please fill in all required fields');
        }
        
        $campaignData = [
            'advertiser_id' => $advertiser_id,
            'name' => $name,
            'bid_type' => $bid_type,
            'bid_amount' => $bid_amount,
            'daily_budget' => $daily_budget,
            'total_budget' => $total_budget,
            'category_id' => $category_id,
            'target_countries' => $target_countries,
            'target_devices' => $target_devices,
            'target_browsers' => $target_browsers,
            'target_os' => $target_os,
            'banner_sizes' => $banner_sizes,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'status' => 'draft',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $stmt = $db->insert('ron_campaigns', $campaignData);
        $campaignId = $db->lastInsertId();
        
        $_SESSION['success'] = "RON Campaign '{$name}' created successfully!";
        header('Location: ron-campaign.php');
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get campaigns with advertiser info
try {
    $campaigns = $db->fetchAll("
        SELECT rc.*, a.company_name as advertiser_name, c.name as category_name,
               (SELECT COUNT(*) FROM creatives cr WHERE cr.campaign_id = rc.id AND cr.campaign_type = 'ron') as creative_count
        FROM ron_campaigns rc
        LEFT JOIN advertisers a ON rc.advertiser_id = a.id
        LEFT JOIN categories c ON rc.category_id = c.id
        ORDER BY rc.created_at DESC
    ");
} catch (Exception $e) {
    error_log("RON Campaigns query error: " . $e->getMessage());
    $campaigns = [];
}

// Get advertisers for dropdown
try {
    $advertisers = $db->fetchAll("
        SELECT a.*, u.username, u.email 
        FROM advertisers a
        JOIN users u ON a.user_id = u.id
        WHERE a.status = 'active'
        ORDER BY a.company_name
    ");
} catch (Exception $e) {
    $advertisers = [];
}

// Get categories for dropdown
try {
    $categories = $db->fetchAll("SELECT * FROM categories WHERE status = 'active' ORDER BY name");
} catch (Exception $e) {
    $categories = [];
}

// Get reference data for targeting
try {
    $countries = $db->fetchAll("SELECT * FROM countries WHERE status = 'active' ORDER BY name");
    $devices = $db->fetchAll("SELECT * FROM devices WHERE status = 'active' ORDER BY name");
    $browsers = $db->fetchAll("SELECT * FROM browsers WHERE status = 'active' ORDER BY name");
    $operatingSystems = $db->fetchAll("SELECT * FROM operating_systems WHERE status = 'active' ORDER BY name");
    $bannerSizes = $db->fetchAll("SELECT * FROM banner_sizes WHERE status = 'active' ORDER BY width, height");
} catch (Exception $e) {
    $countries = $devices = $browsers = $operatingSystems = $bannerSizes = [];
}

include 'includes/header.php';
?>

<?php include 'includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">
    <div class="content-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-title">RON Campaigns</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">RON Campaigns</li>
                    </ol>
                </nav>
            </div>
            <div>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createCampaignModal">
                    <i class="fas fa-plus me-2"></i>Create RON Campaign
                </button>
            </div>
        </div>
    </div>
    
    <div class="content-wrapper">
        <!-- RON Campaign Info -->
        <div class="alert alert-info mb-4">
            <div class="row align-items-center">
                <div class="col-md-1 text-center">
                    <i class="fas fa-network-wired fa-2x text-info"></i>
                </div>
                <div class="col-md-11">
                    <h5 class="mb-1">
                        <i class="fas fa-info-circle me-2"></i>What is RON (Run of Network)?
                    </h5>
                    <p class="mb-0">
                        RON campaigns compete with RTB campaigns for traffic across our entire publisher network. 
                        RON campaigns use your own creatives and compete in real-time auctions against RTB traffic sources. 
                        Higher bids increase your chances of winning ad placements.
                    </p>
                </div>
            </div>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Campaigns Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-network-wired me-2"></i>RON Campaigns
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($campaigns)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-network-wired fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No RON Campaigns Found</h4>
                        <p class="text-muted mb-4">Create your first RON campaign to compete for traffic across our network</p>
                        <button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#createCampaignModal">
                            <i class="fas fa-plus me-2"></i>Create Your First RON Campaign
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="campaignsTable">
                            <thead>
                                <tr>
                                    <th>Campaign</th>
                                    <th>Advertiser</th>
                                    <th>Bid Type</th>
                                    <th>Bid Amount</th>
                                    <th>Budget</th>
                                    <th>Creatives</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($campaigns as $campaign): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($campaign['name']); ?></strong>
                                                <?php if ($campaign['target_countries']): ?>
                                                    <br><small class="text-muted">
                                                        <i class="fas fa-globe-americas me-1"></i>
                                                        <?php 
                                                        $countries_list = json_decode($campaign['target_countries'], true);
                                                        echo count($countries_list) . ' countries';
                                                        ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($campaign['advertiser_name'] ?? 'Unknown'); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $campaign['bid_type'] == 'CPM' ? 'primary' : 'success'; ?>">
                                                <?php echo $campaign['bid_type']; ?>
                                            </span>
                                        </td>
                                        <td>$<?php echo number_format($campaign['bid_amount'], 4); ?></td>
                                        <td>
                                            <?php if ($campaign['daily_budget']): ?>
                                                <small>Daily: $<?php echo number_format($campaign['daily_budget'], 2); ?></small><br>
                                            <?php endif; ?>
                                            <?php if ($campaign['total_budget']): ?>
                                                <small>Total: $<?php echo number_format($campaign['total_budget'], 2); ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">Unlimited</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $campaign['creative_count'] > 0 ? 'success' : 'warning'; ?>">
                                                <?php echo $campaign['creative_count']; ?> creatives
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($campaign['category_name'] ?? 'All'); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($campaign['status']) {
                                                    'active' => 'success',
                                                    'paused' => 'warning',
                                                    'completed' => 'secondary',
                                                    default => 'info'
                                                };
                                            ?>">
                                                <?php echo ucfirst($campaign['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo date('M j, Y', strtotime($campaign['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="editCampaign(<?php echo $campaign['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="creative.php?campaign_id=<?php echo $campaign['id']; ?>&type=ron" 
                                                   class="btn btn-outline-success" title="Manage Creatives">
                                                    <i class="fas fa-images"></i>
                                                </a>
                                                <button class="btn btn-outline-info" onclick="viewStats(<?php echo $campaign['id']; ?>)">
                                                    <i class="fas fa-chart-line"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="deleteCampaign(<?php echo $campaign['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
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
        
        <!-- Competition Info -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-trophy me-2"></i>RON vs RTB Bidding Competition
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-success">RON Campaigns</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>Use your own creative assets</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Full control over ad content</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Compete in real-time auctions</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Revenue sharing with publishers</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-primary">RTB Campaigns</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-primary me-2"></i>Third-party RTB traffic sources</li>
                                    <li><i class="fas fa-check text-primary me-2"></i>External endpoint integration</li>
                                    <li><i class="fas fa-check text-primary me-2"></i>Real-time bidding protocol</li>
                                    <li><i class="fas fa-check text-primary me-2"></i>Automatic optimization</li>
                                </ul>
                            </div>
                        </div>
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-lightbulb me-2"></i>
                            <strong>Tip:</strong> Higher bid amounts increase your chances of winning ad placements. 
                            RON and RTB campaigns compete against each other in real-time auctions for the same traffic.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Campaign Modal -->
<div class="modal fade" id="createCampaignModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>Create RON Campaign
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <div class="modal-body">
                    <div class="row">
                        <!-- Basic Info -->
                        <div class="col-md-6">
                            <h6 class="text-success mb-3">Campaign Information</h6>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Campaign Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required 
                                       placeholder="Enter campaign name">
                                <div class="invalid-feedback">Please provide a campaign name.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="advertiser_id" class="form-label">Advertiser <span class="text-danger">*</span></label>
                                <select class="form-select select2" id="advertiser_id" name="advertiser_id" required>
                                    <option value="">Select Advertiser</option>
                                    <?php foreach ($advertisers as $advertiser): ?>
                                        <option value="<?php echo $advertiser['id']; ?>">
                                            <?php echo htmlspecialchars($advertiser['company_name'] . ' (' . $advertiser['username'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select an advertiser.</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="bid_type" class="form-label">Bid Type</label>
                                        <select class="form-select" id="bid_type" name="bid_type">
                                            <option value="CPM">CPM (Cost Per Mille)</option>
                                            <option value="CPC">CPC (Cost Per Click)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="bid_amount" class="form-label">Bid Amount ($) <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="bid_amount" name="bid_amount" 
                                               step="0.0001" min="0.0001" required placeholder="0.0010">
                                        <div class="invalid-feedback">Please provide a bid amount.</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="daily_budget" class="form-label">Daily Budget ($)</label>
                                        <input type="number" class="form-control" id="daily_budget" name="daily_budget" 
                                               step="0.01" min="0" placeholder="100.00">
                                        <div class="form-text">Leave empty for unlimited</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="total_budget" class="form-label">Total Budget ($)</label>
                                        <input type="number" class="form-control" id="total_budget" name="total_budget" 
                                               step="0.01" min="0" placeholder="1000.00">
                                        <div class="form-text">Leave empty for unlimited</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="category_id" class="form-label">Category</label>
                                <select class="form-select" id="category_id" name="category_id">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                            (<?php echo ucfirst($category['type']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="start_date" class="form-label">Start Date</label>
                                        <input type="date" class="form-control" id="start_date" name="start_date" 
                                               value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="end_date" class="form-label">End Date</label>
                                        <input type="date" class="form-control" id="end_date" name="end_date">
                                        <div class="form-text">Leave empty for ongoing campaign</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Targeting -->
                        <div class="col-md-6">
                            <h6 class="text-success mb-3">Targeting Options</h6>
                            
                            <div class="mb-3">
                                <label for="target_countries" class="form-label">Target Countries</label>
                                <select class="form-select select2" id="target_countries" name="target_countries[]" multiple>
                                    <?php foreach ($countries as $country): ?>
                                        <option value="<?php echo $country['code']; ?>">
                                            <?php echo htmlspecialchars($country['name']); ?> (<?php echo $country['code']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Leave empty for worldwide targeting</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="target_devices" class="form-label">Target Devices</label>
                                <select class="form-select select2" id="target_devices" name="target_devices[]" multiple>
                                    <?php foreach ($devices as $device): ?>
                                        <option value="<?php echo $device['slug']; ?>">
                                            <?php echo htmlspecialchars($device['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="target_browsers" class="form-label">Target Browsers</label>
                                <select class="form-select select2" id="target_browsers" name="target_browsers[]" multiple>
                                    <?php foreach ($browsers as $browser): ?>
                                        <option value="<?php echo $browser['slug']; ?>">
                                            <?php echo htmlspecialchars($browser['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="target_os" class="form-label">Target Operating Systems</label>
                                <select class="form-select select2" id="target_os" name="target_os[]" multiple>
                                    <?php foreach ($operatingSystems as $os): ?>
                                        <option value="<?php echo $os['slug']; ?>">
                                            <?php echo htmlspecialchars($os['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="banner_sizes" class="form-label">Banner Sizes</label>
                                <select class="form-select select2" id="banner_sizes" name="banner_sizes[]" multiple>
                                    <?php foreach ($bannerSizes as $size): ?>
                                        <option value="<?php echo $size['width'] . 'x' . $size['height']; ?>">
                                            <?php echo $size['width'] . 'x' . $size['height']; ?> - <?php echo htmlspecialchars($size['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select specific banner sizes or leave empty for all sizes</div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Next Step:</strong> After creating the campaign, you'll need to add creatives 
                                (HTML5 banners, third-party scripts, or images) to start serving ads.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-2"></i>Create Campaign
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#campaignsTable').DataTable({
        order: [[8, 'desc']], // Sort by created date
        pageLength: 25,
        responsive: true
    });
    
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: $('#createCampaignModal')
    });
});

function editCampaign(id) {
    // TODO: Implement edit functionality
    showWarning('Edit functionality coming soon!');
}

function viewStats(id) {
    // TODO: Implement stats view
    showWarning('Statistics view coming soon!');
}

function deleteCampaign(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: 'This will permanently delete the RON campaign and all associated creatives.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            // TODO: Implement delete functionality
            showWarning('Delete functionality coming soon!');
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>