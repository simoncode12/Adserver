<?php
$pageTitle = 'Website Management';
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
                    $publisher_id = (int)$_POST['publisher_id'];
                    $name = sanitize($_POST['name']);
                    $url = sanitize($_POST['url']);
                    $category_id = (int)$_POST['category_id'];
                    
                    // Validate URL format
                    if (!filter_var($url, FILTER_VALIDATE_URL)) {
                        $error = 'Please provide a valid URL.';
                        break;
                    }
                    
                    $db->insert('websites', [
                        'publisher_id' => $publisher_id,
                        'name' => $name,
                        'url' => $url,
                        'category_id' => $category_id,
                        'status' => 'active'
                    ]);
                    
                    $success = 'Website created successfully!';
                    break;
                    
                case 'update_status':
                    $website_id = (int)$_POST['website_id'];
                    $status = sanitize($_POST['status']);
                    
                    $db->update('websites', 
                        ['status' => $status], 
                        'id = ?', 
                        [$website_id]
                    );
                    
                    $success = 'Website status updated successfully!';
                    break;
                    
                case 'delete':
                    $website_id = (int)$_POST['website_id'];
                    
                    // Check if website has zones
                    $zone_count = $db->fetch("SELECT COUNT(*) as count FROM zones WHERE website_id = ?", [$website_id])['count'];
                    if ($zone_count > 0) {
                        $error = "Cannot delete website. It has {$zone_count} associated zones. Please delete zones first.";
                        break;
                    }
                    
                    $db->delete('websites', 'id = ?', [$website_id]);
                    $success = 'Website deleted successfully!';
                    break;
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get websites with publisher and category info and zone count
$websites = $db->fetchAll("
    SELECT w.*, p.name as publisher_name, p.revenue_share, 
           c.name as category_name, c.type as category_type,
           (SELECT COUNT(*) FROM zones z WHERE z.website_id = w.id) as zone_count
    FROM websites w 
    LEFT JOIN publishers p ON w.publisher_id = p.id 
    LEFT JOIN categories c ON w.category_id = c.id 
    ORDER BY w.created_at DESC
");

// Get data for forms
$publishers = $db->fetchAll("SELECT id, name, email FROM publishers WHERE status = 'active' ORDER BY name");
$categories = $db->fetchAll("SELECT id, name, type FROM categories WHERE status = 'active' ORDER BY name");
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

<!-- Website Info -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card bg-gradient-success text-white">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="card-title mb-2">
                            <i class="fas fa-globe"></i> Website Management
                        </h5>
                        <p class="card-text mb-0">
                            Manage publisher websites and categorize them for proper ad targeting.
                            Each website can have multiple ad zones for different placement types.
                        </p>
                    </div>
                    <div class="col-md-4 text-center">
                        <i class="fas fa-sitemap fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Website Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h4 class="text-primary"><?php echo count($websites); ?></h4>
                <p class="mb-0 text-muted">Total Websites</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h4 class="text-success">
                    <?php echo count(array_filter($websites, function($w) { return $w['status'] === 'active'; })); ?>
                </h4>
                <p class="mb-0 text-muted">Active Websites</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h4 class="text-info">
                    <?php echo array_sum(array_column($websites, 'zone_count')); ?>
                </h4>
                <p class="mb-0 text-muted">Total Zones</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h4 class="text-warning">
                    <?php echo count(array_unique(array_column($websites, 'publisher_id'))); ?>
                </h4>
                <p class="mb-0 text-muted">Publishers</p>
            </div>
        </div>
    </div>
</div>

<!-- Create New Website -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus"></i> Add New Website</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Website Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                            <div class="form-text">Descriptive name for the website</div>
                            <div class="invalid-feedback">Please provide a website name.</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="publisher_id" class="form-label">Publisher <span class="text-danger">*</span></label>
                            <select class="form-select" id="publisher_id" name="publisher_id" required>
                                <option value="">Select Publisher</option>
                                <?php foreach ($publishers as $publisher): ?>
                                    <option value="<?php echo $publisher['id']; ?>">
                                        <?php echo htmlspecialchars($publisher['name']); ?> 
                                        (<?php echo htmlspecialchars($publisher['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a publisher.</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="url" class="form-label">Website URL <span class="text-danger">*</span></label>
                            <input type="url" class="form-control" id="url" name="url" 
                                   placeholder="https://example.com" required>
                            <div class="form-text">Full URL including http:// or https://</div>
                            <div class="invalid-feedback">Please provide a valid website URL.</div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?> 
                                        (<?php echo ucfirst($category['type']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a category.</div>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Add Website
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Existing Websites -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list"></i> Websites</h5>
            </div>
            <div class="card-body">
                <?php if (empty($websites)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-globe fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No websites found</h5>
                        <p class="text-muted">Add your first website to start creating ad zones and managing placements.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Website</th>
                                    <th>Publisher</th>
                                    <th>Category</th>
                                    <th>Revenue Share</th>
                                    <th>Zones</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($websites as $website): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($website['name']); ?></strong>
                                                <a href="<?php echo htmlspecialchars($website['url']); ?>" 
                                                   target="_blank" class="ms-2">
                                                    <i class="fas fa-external-link-alt text-muted"></i>
                                                </a>
                                            </div>
                                            <small class="text-muted"><?php echo htmlspecialchars($website['url']); ?></small>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($website['publisher_name'] ?? 'Unknown'); ?></div>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($website['category_name'] ?? 'Unknown'); ?></div>
                                            <small class="text-muted">
                                                <?php echo ucfirst($website['category_type'] ?? 'unknown'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="revenue-share">
                                                <span><?php echo number_format($website['revenue_share'] ?? DEFAULT_REVENUE_SHARE, 1); ?>%</span>
                                                <div class="revenue-share-bar">
                                                    <div class="revenue-share-fill" 
                                                         style="width: <?php echo $website['revenue_share'] ?? DEFAULT_REVENUE_SHARE; ?>%">
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo $website['zone_count']; ?> zones
                                            </span>
                                            <?php if ($website['zone_count'] > 0): ?>
                                                <a href="zone.php?website_id=<?php echo $website['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary ms-1">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-status-<?php echo $website['status']; ?>">
                                                <?php echo ucfirst($website['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo date('M j, Y', strtotime($website['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-info btn-sm" 
                                                        onclick="showWebsiteDetails(<?php echo htmlspecialchars(json_encode($website)); ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="website_id" value="<?php echo $website['id']; ?>">
                                                    <input type="hidden" name="status" value="<?php echo $website['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                                    <button type="submit" class="btn <?php echo $website['status'] === 'active' ? 'btn-warning' : 'btn-success'; ?> btn-sm">
                                                        <i class="fas <?php echo $website['status'] === 'active' ? 'fa-pause' : 'fa-play'; ?>"></i>
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this website? This will also delete all associated zones.')">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="website_id" value="<?php echo $website['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" 
                                                            <?php echo $website['zone_count'] > 0 ? 'disabled title="Cannot delete website with zones"' : ''; ?>>
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

<!-- Website Details Modal -->
<div class="modal fade" id="websiteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Website Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="website-details">
                    Loading...
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" id="create-zone-btn" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Zone
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Show website details in modal
function showWebsiteDetails(website) {
    const detailsHtml = `
        <div class="row">
            <div class="col-md-6">
                <h6>Website Information</h6>
                <table class="table table-sm">
                    <tr><td><strong>Name:</strong></td><td>${website.name}</td></tr>
                    <tr><td><strong>URL:</strong></td><td><a href="${website.url}" target="_blank">${website.url}</a></td></tr>
                    <tr><td><strong>Category:</strong></td><td>${website.category_name} (${website.category_type})</td></tr>
                    <tr><td><strong>Status:</strong></td><td><span class="badge badge-status-${website.status}">${website.status}</span></td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Publisher Information</h6>
                <table class="table table-sm">
                    <tr><td><strong>Publisher:</strong></td><td>${website.publisher_name}</td></tr>
                    <tr><td><strong>Revenue Share:</strong></td><td>${parseFloat(website.revenue_share || 50).toFixed(1)}%</td></tr>
                    <tr><td><strong>Zone Count:</strong></td><td>${website.zone_count} zones</td></tr>
                    <tr><td><strong>Created:</strong></td><td>${new Date(website.created_at).toLocaleDateString()}</td></tr>
                </table>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12">
                <h6>Quick Actions</h6>
                <div class="d-flex gap-2">
                    <a href="${website.url}" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-external-link-alt"></i> Visit Website
                    </a>
                    <a href="zone.php?website_id=${website.id}" class="btn btn-sm btn-outline-success">
                        <i class="fas fa-th-large"></i> Manage Zones
                    </a>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('website-details').innerHTML = detailsHtml;
    document.getElementById('create-zone-btn').href = `zone.php?website_id=${website.id}`;
    new bootstrap.Modal(document.getElementById('websiteModal')).show();
}

// URL validation
document.getElementById('url').addEventListener('blur', function() {
    const url = this.value;
    if (url && !url.match(/^https?:\/\/.+/)) {
        this.setCustomValidity('URL must start with http:// or https://');
    } else {
        this.setCustomValidity('');
    }
});

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