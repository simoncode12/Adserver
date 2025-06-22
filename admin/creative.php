<?php
$pageTitle = 'Creative Management';
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
                    $campaign_id = (int)$_POST['campaign_id'];
                    $campaign_type = sanitize($_POST['campaign_type']);
                    $name = sanitize($_POST['name']);
                    $creative_type = sanitize($_POST['creative_type']);
                    $content = $_POST['content']; // Don't sanitize HTML content
                    $size = sanitize($_POST['size']);
                    $bid_amount = (float)$_POST['bid_amount'];
                    
                    $db->insert('creatives', [
                        'campaign_id' => $campaign_id,
                        'campaign_type' => $campaign_type,
                        'name' => $name,
                        'creative_type' => $creative_type,
                        'content' => $content,
                        'size' => $size,
                        'bid_amount' => $bid_amount,
                        'status' => 'active'
                    ]);
                    
                    $success = 'Creative created successfully!';
                    break;
                    
                case 'update_status':
                    $creative_id = (int)$_POST['creative_id'];
                    $status = sanitize($_POST['status']);
                    
                    $db->update('creatives', 
                        ['status' => $status], 
                        'id = ?', 
                        [$creative_id]
                    );
                    
                    $success = 'Creative status updated successfully!';
                    break;
                    
                case 'delete':
                    $creative_id = (int)$_POST['creative_id'];
                    $db->delete('creatives', 'id = ?', [$creative_id]);
                    $success = 'Creative deleted successfully!';
                    break;
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get creatives with campaign info
$creatives = $db->fetchAll("
    SELECT c.*, 
           CASE 
               WHEN c.campaign_type = 'rtb' THEN r.name
               WHEN c.campaign_type = 'ron' THEN ro.name
           END as campaign_name,
           CASE 
               WHEN c.campaign_type = 'rtb' THEN a1.name
               WHEN c.campaign_type = 'ron' THEN a2.name
           END as advertiser_name
    FROM creatives c 
    LEFT JOIN rtb_campaigns r ON c.campaign_id = r.id AND c.campaign_type = 'rtb'
    LEFT JOIN ron_campaigns ro ON c.campaign_id = ro.id AND c.campaign_type = 'ron'
    LEFT JOIN advertisers a1 ON r.advertiser_id = a1.id
    LEFT JOIN advertisers a2 ON ro.advertiser_id = a2.id
    ORDER BY c.created_at DESC
");

// Get RTB campaigns for dropdown
$rtb_campaigns = $db->fetchAll("
    SELECT r.id, r.name, a.name as advertiser_name 
    FROM rtb_campaigns r 
    LEFT JOIN advertisers a ON r.advertiser_id = a.id 
    WHERE r.status = 'active' 
    ORDER BY r.name
");

// Get RON campaigns for dropdown
$ron_campaigns = $db->fetchAll("
    SELECT r.id, r.name, a.name as advertiser_name 
    FROM ron_campaigns r 
    LEFT JOIN advertisers a ON r.advertiser_id = a.id 
    WHERE r.status = 'active' 
    ORDER BY r.name
");

// Get banner sizes
$banner_sizes = $db->fetchAll("SELECT name FROM banner_sizes WHERE status = 'active' ORDER BY name");
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

<!-- Creative Types Info -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card border-info">
            <div class="card-body">
                <h6 class="card-title text-info">
                    <i class="fas fa-code"></i> HTML5 Creatives
                </h6>
                <p class="card-text small">
                    Complete HTML/CSS/JavaScript code with responsive design.
                    Perfect for interactive banners and rich media content.
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-warning">
            <div class="card-body">
                <h6 class="card-title text-warning">
                    <i class="fas fa-file-code"></i> Script Creatives
                </h6>
                <p class="card-text small">
                    Third-party scripts and embeds from ad networks.
                    Ideal for Exoclick and other external ad providers.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Create New Creative -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus"></i> Create New Creative</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Creative Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                            <div class="invalid-feedback">Please provide a creative name.</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="campaign_type" class="form-label">Campaign Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="campaign_type" name="campaign_type" required onchange="updateCampaignDropdown()">
                                <option value="">Select Campaign Type</option>
                                <option value="rtb">RTB Campaign</option>
                                <option value="ron">RON Campaign</option>
                            </select>
                            <div class="invalid-feedback">Please select a campaign type.</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="campaign_id" class="form-label">Campaign <span class="text-danger">*</span></label>
                            <select class="form-select" id="campaign_id" name="campaign_id" required disabled>
                                <option value="">Select campaign type first</option>
                            </select>
                            <div class="invalid-feedback">Please select a campaign.</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="creative_type" class="form-label">Creative Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="creative_type" name="creative_type" required>
                                <option value="">Select Creative Type</option>
                                <option value="html5">HTML5 (Interactive)</option>
                                <option value="script">Script (Third-party)</option>
                            </select>
                            <div class="invalid-feedback">Please select a creative type.</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="size" class="form-label">Size <span class="text-danger">*</span></label>
                            <select class="form-select" id="size" name="size" required>
                                <option value="">Select Size</option>
                                <?php foreach ($banner_sizes as $size): ?>
                                    <option value="<?php echo $size['name']; ?>">
                                        <?php echo $size['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a size.</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="bid_amount" class="form-label">Bid Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="bid_amount" name="bid_amount" 
                                       step="0.0001" min="0.0001" required>
                            </div>
                            <div class="form-text">Individual bid amount for this creative</div>
                            <div class="invalid-feedback">Please provide a valid bid amount.</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="content" class="form-label">Creative Content <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="content" name="content" rows="8" required 
                                  placeholder="Enter your HTML5 code or third-party script here..."></textarea>
                        <div class="form-text">
                            <strong>HTML5:</strong> Complete HTML with CSS and JavaScript<br>
                            <strong>Script:</strong> Third-party embed codes or JavaScript tags
                        </div>
                        <div class="invalid-feedback">Please provide creative content.</div>
                    </div>
                    
                    <!-- Live Preview -->
                    <div class="mb-3">
                        <label class="form-label">Live Preview</label>
                        <div class="creative-preview border rounded p-3" id="creative-preview">
                            <i class="fas fa-eye text-muted"></i>
                            <span class="text-muted">Preview will appear here as you type</span>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Create Creative
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Existing Creatives -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list"></i> Creatives</h5>
            </div>
            <div class="card-body">
                <?php if (empty($creatives)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-paint-brush fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No creatives found</h5>
                        <p class="text-muted">Create your first creative to start displaying ads in your campaigns.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Campaign</th>
                                    <th>Type</th>
                                    <th>Size</th>
                                    <th>Bid Amount</th>
                                    <th>Preview</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($creatives as $creative): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($creative['name']); ?></strong>
                                            <br>
                                            <span class="campaign-type-<?php echo $creative['campaign_type']; ?>">
                                                <?php echo strtoupper($creative['campaign_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($creative['campaign_name'] ?? 'Unknown'); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($creative['advertiser_name'] ?? 'Unknown'); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $creative['creative_type'] === 'html5' ? 'bg-info' : 'bg-warning'; ?>">
                                                <?php echo ucfirst($creative['creative_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($creative['size']); ?></td>
                                        <td>$<?php echo number_format($creative['bid_amount'], 4); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="showCreativePreview(<?php echo $creative['id']; ?>)">
                                                <i class="fas fa-eye"></i> Preview
                                            </button>
                                        </td>
                                        <td>
                                            <span class="badge badge-status-<?php echo $creative['status']; ?>">
                                                <?php echo ucfirst($creative['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="creative_id" value="<?php echo $creative['id']; ?>">
                                                    <input type="hidden" name="status" value="<?php echo $creative['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                                    <button type="submit" class="btn <?php echo $creative['status'] === 'active' ? 'btn-warning' : 'btn-success'; ?> btn-sm">
                                                        <i class="fas <?php echo $creative['status'] === 'active' ? 'fa-pause' : 'fa-play'; ?>"></i>
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this creative?')">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="creative_id" value="<?php echo $creative['id']; ?>">
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

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Creative Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="modal-preview-content" class="text-center">
                    Loading preview...
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Campaign data for dropdowns
const rtbCampaigns = <?php echo json_encode($rtb_campaigns); ?>;
const ronCampaigns = <?php echo json_encode($ron_campaigns); ?>;
const creatives = <?php echo json_encode($creatives); ?>;

// Update campaign dropdown based on type selection
function updateCampaignDropdown() {
    const typeSelect = document.getElementById('campaign_type');
    const campaignSelect = document.getElementById('campaign_id');
    const selectedType = typeSelect.value;
    
    campaignSelect.innerHTML = '<option value="">Select Campaign</option>';
    
    if (selectedType === 'rtb') {
        rtbCampaigns.forEach(campaign => {
            const option = document.createElement('option');
            option.value = campaign.id;
            option.textContent = `${campaign.name} (${campaign.advertiser_name})`;
            campaignSelect.appendChild(option);
        });
        campaignSelect.disabled = false;
    } else if (selectedType === 'ron') {
        ronCampaigns.forEach(campaign => {
            const option = document.createElement('option');
            option.value = campaign.id;
            option.textContent = `${campaign.name} (${campaign.advertiser_name})`;
            campaignSelect.appendChild(option);
        });
        campaignSelect.disabled = false;
    } else {
        campaignSelect.disabled = true;
    }
}

// Live preview functionality
document.getElementById('content').addEventListener('input', function() {
    const content = this.value;
    const preview = document.getElementById('creative-preview');
    
    if (content.trim()) {
        preview.innerHTML = content;
        preview.classList.add('has-content');
    } else {
        preview.innerHTML = '<i class="fas fa-eye text-muted"></i><span class="text-muted">Preview will appear here as you type</span>';
        preview.classList.remove('has-content');
    }
});

// Show creative preview in modal
function showCreativePreview(creativeId) {
    const creative = creatives.find(c => c.id == creativeId);
    if (creative) {
        document.getElementById('modal-preview-content').innerHTML = creative.content;
        new bootstrap.Modal(document.getElementById('previewModal')).show();
    }
}

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