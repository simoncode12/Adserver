<?php
$pageTitle = 'Category Management';
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
                    $type = sanitize($_POST['type']);
                    
                    // Check if category name already exists
                    $existing = $db->fetch("SELECT id FROM categories WHERE name = ?", [$name]);
                    if ($existing) {
                        $error = 'A category with this name already exists.';
                        break;
                    }
                    
                    $db->insert('categories', [
                        'name' => $name,
                        'type' => $type,
                        'status' => 'active'
                    ]);
                    
                    $success = 'Category created successfully!';
                    break;
                    
                case 'update':
                    $category_id = (int)$_POST['category_id'];
                    $name = sanitize($_POST['name']);
                    $type = sanitize($_POST['type']);
                    
                    // Check if category name already exists for another category
                    $existing = $db->fetch("SELECT id FROM categories WHERE name = ? AND id != ?", [$name, $category_id]);
                    if ($existing) {
                        $error = 'Another category with this name already exists.';
                        break;
                    }
                    
                    $db->update('categories', [
                        'name' => $name,
                        'type' => $type
                    ], 'id = ?', [$category_id]);
                    
                    $success = 'Category updated successfully!';
                    break;
                    
                case 'update_status':
                    $category_id = (int)$_POST['category_id'];
                    $status = sanitize($_POST['status']);
                    
                    $db->update('categories', 
                        ['status' => $status], 
                        'id = ?', 
                        [$category_id]
                    );
                    
                    $success = 'Category status updated successfully!';
                    break;
                    
                case 'delete':
                    $category_id = (int)$_POST['category_id'];
                    
                    // Check if category is used by websites
                    $website_count = $db->fetch("SELECT COUNT(*) as count FROM websites WHERE category_id = ?", [$category_id])['count'];
                    if ($website_count > 0) {
                        $error = "Cannot delete category. It is used by {$website_count} websites.";
                        break;
                    }
                    
                    // Check if category is used by RTB campaigns
                    $rtb_count = $db->fetch("SELECT COUNT(*) as count FROM rtb_campaigns WHERE category_id = ?", [$category_id])['count'];
                    if ($rtb_count > 0) {
                        $error = "Cannot delete category. It is used by {$rtb_count} RTB campaigns.";
                        break;
                    }
                    
                    // Check if category is used by RON campaigns
                    $ron_count = $db->fetch("SELECT COUNT(*) as count FROM ron_campaigns WHERE category_id = ?", [$category_id])['count'];
                    if ($ron_count > 0) {
                        $error = "Cannot delete category. It is used by {$ron_count} RON campaigns.";
                        break;
                    }
                    
                    // Check if category is used by RTB endpoints
                    $endpoint_count = $db->fetch("SELECT COUNT(*) as count FROM rtb_endpoints WHERE category_id = ?", [$category_id])['count'];
                    if ($endpoint_count > 0) {
                        $error = "Cannot delete category. It is used by {$endpoint_count} RTB endpoints.";
                        break;
                    }
                    
                    $db->delete('categories', 'id = ?', [$category_id]);
                    $success = 'Category deleted successfully!';
                    break;
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get categories with usage counts
$categories = $db->fetchAll("
    SELECT c.*,
           (SELECT COUNT(*) FROM websites WHERE category_id = c.id) as website_count,
           (SELECT COUNT(*) FROM rtb_campaigns WHERE category_id = c.id) as rtb_campaign_count,
           (SELECT COUNT(*) FROM ron_campaigns WHERE category_id = c.id) as ron_campaign_count,
           (SELECT COUNT(*) FROM rtb_endpoints WHERE category_id = c.id) as endpoint_count
    FROM categories c 
    ORDER BY c.type, c.name
");

// Calculate statistics
$adult_count = count(array_filter($categories, function($c) { return $c['type'] === 'adult'; }));
$mainstream_count = count(array_filter($categories, function($c) { return $c['type'] === 'mainstream'; }));
$total_websites = array_sum(array_column($categories, 'website_count'));
$total_campaigns = array_sum(array_column($categories, 'rtb_campaign_count')) + array_sum(array_column($categories, 'ron_campaign_count'));
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

<!-- Category Info -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card bg-gradient-warning text-white">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="card-title mb-2">
                            <i class="fas fa-tags"></i> Category Management
                        </h5>
                        <p class="card-text mb-0">
                            Manage content categories for proper ad targeting and compliance.
                            Categories help match appropriate ads with relevant website content.
                        </p>
                    </div>
                    <div class="col-md-4 text-center">
                        <i class="fas fa-layer-group fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Category Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h4 class="text-warning"><?php echo count($categories); ?></h4>
                <p class="mb-0 text-muted">Total Categories</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h4 class="text-success"><?php echo $mainstream_count; ?></h4>
                <p class="mb-0 text-muted">Mainstream</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h4 class="text-danger"><?php echo $adult_count; ?></h4>
                <p class="mb-0 text-muted">Adult</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h4 class="text-info"><?php echo $total_websites; ?></h4>
                <p class="mb-0 text-muted">Websites Using</p>
            </div>
        </div>
    </div>
</div>

<!-- Category Types Info -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card border-success">
            <div class="card-body">
                <h6 class="card-title text-success">
                    <i class="fas fa-check-circle"></i> Mainstream Categories
                </h6>
                <p class="card-text small">
                    General audience content suitable for all advertisers.
                    Includes news, technology, finance, health, sports, and entertainment.
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-danger">
            <div class="card-body">
                <h6 class="card-title text-danger">
                    <i class="fas fa-exclamation-triangle"></i> Adult Categories
                </h6>
                <p class="card-text small">
                    Adult content requiring special handling and compliance.
                    Includes adult entertainment, dating, and gambling content.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Create New Category -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus"></i> Add New Category</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="name" class="form-label">Category Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                            <div class="form-text">Descriptive name for the content category</div>
                            <div class="invalid-feedback">Please provide a category name.</div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="type" class="form-label">Category Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="type" name="type" required>
                                <option value="">Select Type</option>
                                <option value="mainstream">Mainstream</option>
                                <option value="adult">Adult</option>
                            </select>
                            <div class="invalid-feedback">Please select a category type.</div>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save"></i> Add Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Existing Categories -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list"></i> Categories</h5>
            </div>
            <div class="card-body">
                <?php if (empty($categories)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No categories found</h5>
                        <p class="text-muted">Add your first category to start organizing content and campaigns.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Websites</th>
                                    <th>RTB Campaigns</th>
                                    <th>RON Campaigns</th>
                                    <th>Endpoints</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                            <br>
                                            <small class="text-muted">ID: <?php echo $category['id']; ?></small>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $category['type'] === 'adult' ? 'bg-danger' : 'bg-success'; ?>">
                                                <?php echo ucfirst($category['type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo $category['website_count']; ?>
                                            </span>
                                            <?php if ($category['website_count'] > 0): ?>
                                                <a href="website.php?category_id=<?php echo $category['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary ms-1">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?php echo $category['rtb_campaign_count']; ?>
                                            </span>
                                            <?php if ($category['rtb_campaign_count'] > 0): ?>
                                                <a href="rtb-sell.php?category_id=<?php echo $category['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary ms-1">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning">
                                                <?php echo $category['ron_campaign_count']; ?>
                                            </span>
                                            <?php if ($category['ron_campaign_count'] > 0): ?>
                                                <a href="ron-campaign.php?category_id=<?php echo $category['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary ms-1">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo $category['endpoint_count']; ?>
                                            </span>
                                            <?php if ($category['endpoint_count'] > 0): ?>
                                                <a href="rtb-buy.php?category_id=<?php echo $category['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary ms-1">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-status-<?php echo $category['status']; ?>">
                                                <?php echo ucfirst($category['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-info btn-sm" 
                                                        onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                    <input type="hidden" name="status" value="<?php echo $category['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                                    <button type="submit" class="btn <?php echo $category['status'] === 'active' ? 'btn-warning' : 'btn-success'; ?> btn-sm">
                                                        <i class="fas <?php echo $category['status'] === 'active' ? 'fa-pause' : 'fa-play'; ?>"></i>
                                                    </button>
                                                </form>
                                                
                                                <?php 
                                                $total_usage = $category['website_count'] + $category['rtb_campaign_count'] + 
                                                              $category['ron_campaign_count'] + $category['endpoint_count'];
                                                ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this category?')">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" 
                                                            <?php echo $total_usage > 0 ? 'disabled title="Cannot delete category in use"' : ''; ?>>
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

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="category_id" id="edit_category_id">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                        <div class="invalid-feedback">Please provide a category name.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_type" class="form-label">Category Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit_type" name="type" required>
                            <option value="">Select Type</option>
                            <option value="mainstream">Mainstream</option>
                            <option value="adult">Adult</option>
                        </select>
                        <div class="invalid-feedback">Please select a category type.</div>
                    </div>
                    
                    <div class="alert alert-warning" id="edit_usage_warning" style="display: none;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> This category is currently in use. Changes may affect existing content and campaigns.
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save"></i> Update Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Edit category function
function editCategory(category) {
    document.getElementById('edit_category_id').value = category.id;
    document.getElementById('edit_name').value = category.name;
    document.getElementById('edit_type').value = category.type;
    
    // Show usage warning if category is in use
    const totalUsage = parseInt(category.website_count) + parseInt(category.rtb_campaign_count) + 
                       parseInt(category.ron_campaign_count) + parseInt(category.endpoint_count);
    
    const warningDiv = document.getElementById('edit_usage_warning');
    if (totalUsage > 0) {
        warningDiv.style.display = 'block';
    } else {
        warningDiv.style.display = 'none';
    }
    
    new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
}

// Category name validation
function validateCategoryName(name) {
    return name.trim().length >= 2;
}

// Real-time validation
document.getElementById('name').addEventListener('blur', function() {
    if (this.value && !validateCategoryName(this.value)) {
        this.setCustomValidity('Category name must be at least 2 characters long');
    } else {
        this.setCustomValidity('');
    }
});

document.getElementById('edit_name').addEventListener('blur', function() {
    if (this.value && !validateCategoryName(this.value)) {
        this.setCustomValidity('Category name must be at least 2 characters long');
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