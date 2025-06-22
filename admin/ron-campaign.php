<?php
$pageTitle = 'RON Campaigns';
include 'includes/header.php';

$success = $error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid security token.';
    } else {
        try {
            switch ($_POST['action']) {
                case 'create':
                    $name = sanitize($_POST['name']);
                    $advertiser_id = (int)$_POST['advertiser_id'];
                    $bid_type = sanitize($_POST['bid_type']);
                    $bid_amount = (float)$_POST['bid_amount'];
                    $category_id = (int)$_POST['category_id'];
                    
                    // Handle targeting arrays
                    $banner_sizes = isset($_POST['banner_sizes']) ? json_encode($_POST['banner_sizes']) : '[]';
                    $target_countries = isset($_POST['target_countries']) ? json_encode($_POST['target_countries']) : '[]';
                    $target_browsers = isset($_POST['target_browsers']) ? json_encode($_POST['target_browsers']) : '[]';
                    $target_devices = isset($_POST['target_devices']) ? json_encode($_POST['target_devices']) : '[]';
                    $target_os = isset($_POST['target_os']) ? json_encode($_POST['target_os']) : '[]';
                    
                    $db->insert('ron_campaigns', [
                        'advertiser_id' => $advertiser_id,
                        'name' => $name,
                        'bid_type' => $bid_type,
                        'bid_amount' => $bid_amount,
                        'category_id' => $category_id,
                        'banner_sizes' => $banner_sizes,
                        'target_countries' => $target_countries,
                        'target_browsers' => $target_browsers,
                        'target_devices' => $target_devices,
                        'target_os' => $target_os,
                        'status' => 'active'
                    ]);
                    
                    $success = 'RON campaign created successfully!';
                    break;
                    
                case 'update_status':
                    $campaign_id = (int)$_POST['campaign_id'];
                    $status = sanitize($_POST['status']);
                    
                    $db->update('ron_campaigns', 
                        ['status' => $status], 
                        'id = ?', 
                        [$campaign_id]
                    );
                    
                    $success = 'Campaign status updated successfully!';
                    break;
                    
                case 'delete':
                    $campaign_id = (int)$_POST['campaign_id'];
                    $db->delete('ron_campaigns', 'id = ?', [$campaign_id]);
                    $success = 'Campaign deleted successfully!';
                    break;
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get campaigns with advertiser info
$campaigns = $db->fetchAll("
    SELECT r.*, a.name as advertiser_name, c.name as category_name 
    FROM ron_campaigns r 
    LEFT JOIN advertisers a ON r.advertiser_id = a.id 
    LEFT JOIN categories c ON r.category_id = c.id 
    ORDER BY r.created_at DESC
");

// Get data for forms
$advertisers = $db->fetchAll("SELECT id, name FROM advertisers WHERE status = 'active' ORDER BY name");
$categories = $db->fetchAll("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name");
$banner_sizes = $db->fetchAll("SELECT name FROM banner_sizes WHERE status = 'active' ORDER BY name");
$countries = $db->fetchAll("SELECT code, name FROM countries WHERE status = 'active' ORDER BY name");
$browsers = $db->fetchAll("SELECT name FROM browsers WHERE status = 'active' ORDER BY name");
$devices = $db->fetchAll("SELECT name, type FROM devices WHERE status = 'active' ORDER BY name");
$operating_systems = $db->fetchAll("SELECT name FROM operating_systems WHERE status = 'active' ORDER BY name");
?>

<!-- Success/Error Messages -->
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- RON Info Card -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card bg-gradient-warning text-white">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="card-title mb-2">
                            <i class="fas fa-globe-americas"></i> Run of Network (RON) Campaigns
                        </h5>
                        <p class="card-text mb-0">
                            RON campaigns run across our entire publisher network with broad targeting.
                            They compete with RTB campaigns in real-time auctions for premium inventory.
                        </p>
                    </div>
                    <div class="col-md-4 text-center">
                        <i class="fas fa-network-wired fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create New RON Campaign -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus"></i> Create New RON Campaign</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Campaign Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                            <div class="invalid-feedback">Please provide a campaign name.</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="advertiser_id" class="form-label">Advertiser <span class="text-danger">*</span></label>
                            <select class="form-select" id="advertiser_id" name="advertiser_id" required>
                                <option value="">Select Advertiser</option>
                                <?php foreach ($advertisers as $advertiser): ?>
                                    <option value="<?php echo $advertiser['id']; ?>">
                                        <?php echo htmlspecialchars($advertiser['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select an advertiser.</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="bid_type" class="form-label">Bid Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="bid_type" name="bid_type" required>
                                <option value="">Select Bid Type</option>
                                <option value="cpm">CPM (Cost Per Mille)</option>
                                <option value="cpc">CPC (Cost Per Click)</option>
                            </select>
                            <div class="invalid-feedback">Please select a bid type.</div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="bid_amount" class="form-label">Bid Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="bid_amount" name="bid_amount" 
                                       step="0.0001" min="0.0001" required>
                            </div>
                            <div class="form-text">Higher bids compete better against RTB campaigns</div>
                            <div class="invalid-feedback">Please provide a valid bid amount.</div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a category.</div>
                        </div>
                    </div>
                    
                    <!-- Targeting Options -->
                    <div class="card border-0 bg-light mb-3">
                        <div class="card-header bg-transparent">
                            <h6 class="mb-0"><i class="fas fa-crosshairs"></i> Targeting Options</h6>
                            <small class="text-muted">Leave empty for broader reach across the network</small>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="banner_sizes" class="form-label">Banner Sizes</label>
                                    <select class="form-select" id="banner_sizes" name="banner_sizes[]" multiple>
                                        <?php foreach ($banner_sizes as $size): ?>
                                            <option value="<?php echo $size['name']; ?>">
                                                <?php echo $size['name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Hold Ctrl/Cmd to select multiple sizes</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="target_countries" class="form-label">Target Countries</label>
                                    <select class="form-select" id="target_countries" name="target_countries[]" multiple>
                                        <?php foreach ($countries as $country): ?>
                                            <option value="<?php echo $country['code']; ?>">
                                                <?php echo htmlspecialchars($country['name']) . ' (' . $country['code'] . ')'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Leave empty for global reach</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="target_browsers" class="form-label">Target Browsers</label>
                                    <select class="form-select" id="target_browsers" name="target_browsers[]" multiple>
                                        <?php foreach ($browsers as $browser): ?>
                                            <option value="<?php echo $browser['name']; ?>">
                                                <?php echo htmlspecialchars($browser['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="target_devices" class="form-label">Target Devices</label>
                                    <select class="form-select" id="target_devices" name="target_devices[]" multiple>
                                        <?php foreach ($devices as $device): ?>
                                            <option value="<?php echo $device['name']; ?>">
                                                <?php echo htmlspecialchars($device['name']); ?> (<?php echo $device['type']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="target_os" class="form-label">Target OS</label>
                                    <select class="form-select" id="target_os" name="target_os[]" multiple>
                                        <?php foreach ($operating_systems as $os): ?>
                                            <option value="<?php echo $os['name']; ?>">
                                                <?php echo htmlspecialchars($os['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save"></i> Create RON Campaign
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Existing RON Campaigns -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list"></i> RON Campaigns</h5>
            </div>
            <div class="card-body">
                <?php if (empty($campaigns)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-globe-americas fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No RON campaigns found</h5>
                        <p class="text-muted">Create your first RON campaign to start running ads across our entire publisher network.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Advertiser</th>
                                    <th>Category</th>
                                    <th>Bid Type</th>
                                    <th>Bid Amount</th>
                                    <th>Targeting</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($campaigns as $campaign): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($campaign['name']); ?></strong>
                                            <br>
                                            <span class="campaign-type-ron">RON</span>
                                        </td>
                                        <td><?php echo htmlspecialchars($campaign['advertiser_name'] ?? 'Unknown'); ?></td>
                                        <td><?php echo htmlspecialchars($campaign['category_name'] ?? 'Unknown'); ?></td>
                                        <td>
                                            <span class="badge bg-warning">
                                                <?php echo strtoupper($campaign['bid_type']); ?>
                                            </span>
                                        </td>
                                        <td>$<?php echo number_format($campaign['bid_amount'], 4); ?></td>
                                        <td>
                                            <?php
                                            $targeting = [];
                                            $countries = json_decode($campaign['target_countries'], true);
                                            $devices = json_decode($campaign['target_devices'], true);
                                            
                                            if (!empty($countries)) {
                                                $targeting[] = count($countries) . ' countries';
                                            }
                                            if (!empty($devices)) {
                                                $targeting[] = count($devices) . ' devices';
                                            }
                                            
                                            echo empty($targeting) ? 
                                                '<span class="text-muted">Broad</span>' : 
                                                '<small>' . implode(', ', $targeting) . '</small>';
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-status-<?php echo $campaign['status']; ?>">
                                                <?php echo ucfirst($campaign['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo date('M j, Y', strtotime($campaign['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                                                    <input type="hidden" name="status" value="<?php echo $campaign['status'] === 'active' ? 'paused' : 'active'; ?>">
                                                    <button type="submit" class="btn <?php echo $campaign['status'] === 'active' ? 'btn-warning' : 'btn-success'; ?> btn-sm">
                                                        <i class="fas <?php echo $campaign['status'] === 'active' ? 'fa-pause' : 'fa-play'; ?>"></i>
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this campaign?')">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
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
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>

<?php include 'includes/footer.php'; ?>