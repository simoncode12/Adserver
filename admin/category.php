<?php
$pageTitle = 'Category Management';
$breadcrumb = [
    ['text' => 'Category Management']
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
        $slug = sanitize($_POST['slug']);
        $type = sanitize($_POST['type']);
        $description = sanitize($_POST['description']);
        
        // Validate required fields
        if (empty($name) || empty($slug) || empty($type)) {
            throw new Exception('Please fill in all required fields');
        }
        
        // Auto-generate slug if empty
        if (empty($slug)) {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $name));
            $slug = preg_replace('/-+/', '-', $slug);
            $slug = trim($slug, '-');
        }
        
        // Check if slug already exists
        $existingCategory = $db->fetch("SELECT id FROM categories WHERE slug = ?", [$slug]);
        if ($existingCategory) {
            throw new Exception('Category slug already exists');
        }
        
        $categoryData = [
            'name' => $name,
            'slug' => $slug,
            'type' => $type,
            'description' => $description,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $db->insert('categories', $categoryData);
        $categoryId = $db->lastInsertId();
        
        $_SESSION['success'] = "Category '{$name}' created successfully!";
        header('Location: category.php');
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle status updates
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    
    try {
        switch ($action) {
            case 'activate':
                $db->update('categories', ['status' => 'active'], 'id = ?', [$id]);
                $_SESSION['success'] = 'Category activated successfully!';
                break;
            case 'deactivate':
                $db->update('categories', ['status' => 'inactive'], 'id = ?', [$id]);
                $_SESSION['success'] = 'Category deactivated successfully!';
                break;
            case 'delete':
                // Check if category is being used
                $rtbUsage = $db->fetch("SELECT COUNT(*) as count FROM rtb_campaigns WHERE category_id = ?", [$id]);
                $ronUsage = $db->fetch("SELECT COUNT(*) as count FROM ron_campaigns WHERE category_id = ?", [$id]);
                $websiteUsage = $db->fetch("SELECT COUNT(*) as count FROM websites WHERE category_id = ?", [$id]);
                
                if ($rtbUsage['count'] > 0 || $ronUsage['count'] > 0 || $websiteUsage['count'] > 0) {
                    $_SESSION['error'] = 'Cannot delete category: it is being used by campaigns or websites.';
                } else {
                    $db->delete('categories', 'id = ?', [$id]);
                    $_SESSION['success'] = 'Category deleted successfully!';
                }
                break;
        }
        header('Location: category.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
        header('Location: category.php');
        exit;
    }
}

// Get categories with usage statistics
try {
    $categories = $db->fetchAll("
        SELECT c.*,
               (SELECT COUNT(*) FROM rtb_campaigns rc WHERE rc.category_id = c.id) as rtb_campaigns,
               (SELECT COUNT(*) FROM ron_campaigns roc WHERE roc.category_id = c.id) as ron_campaigns,
               (SELECT COUNT(*) FROM websites w WHERE w.category_id = c.id) as websites
        FROM categories c
        ORDER BY c.created_at DESC
    ");
} catch (Exception $e) {
    error_log("Categories query error: " . $e->getMessage());
    $categories = [];
}

include 'includes/header.php';
?>

<?php include 'includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">
    <div class="content-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-title">Category Management</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Categories</li>
                    </ol>
                </nav>
            </div>
            <div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCategoryModal">
                    <i class="fas fa-plus me-2"></i>Add Category
                </button>
            </div>
        </div>
    </div>
    
    <div class="content-wrapper">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
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
        
        <!-- Category Types Info -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-shield-alt fa-3x text-success mb-3"></i>
                        <h5>Mainstream Categories</h5>
                        <p class="text-muted">
                            Safe, family-friendly content categories suitable for general audiences. 
                            These include news, sports, technology, lifestyle, business, and entertainment.
                        </p>
                        <span class="badge bg-success">
                            <?php echo count(array_filter($categories, fn($c) => $c['type'] == 'mainstream')); ?> Categories
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                        <h5>Adult Categories</h5>
                        <p class="text-muted">
                            Adult content categories that require age verification and special handling. 
                            These are separated to ensure appropriate targeting and compliance.
                        </p>
                        <span class="badge bg-warning">
                            <?php echo count(array_filter($categories, fn($c) => $c['type'] == 'adult')); ?> Categories
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-tags"></i>
                    </div>
                    <h3 class="stat-number"><?php echo count($categories); ?></h3>
                    <p class="stat-label">Total Categories</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3 class="stat-number"><?php echo count(array_filter($categories, fn($c) => $c['status'] == 'active')); ?></h3>
                    <p class="stat-label">Active Categories</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <h3 class="stat-number"><?php echo array_sum(array_column($categories, 'rtb_campaigns')) + array_sum(array_column($categories, 'ron_campaigns')); ?></h3>
                    <p class="stat-label">Total Campaigns</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-globe"></i>
                    </div>
                    <h3 class="stat-number"><?php echo array_sum(array_column($categories, 'websites')); ?></h3>
                    <p class="stat-label">Categorized Websites</p>
                </div>
            </div>
        </div>
        
        <!-- Categories Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-tags me-2"></i>Categories
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($categories)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No Categories Found</h4>
                        <p class="text-muted mb-4">Add your first category to start organizing content</p>
                        <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#createCategoryModal">
                            <i class="fas fa-plus me-2"></i>Add Your First Category
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="categoriesTable">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Type</th>
                                    <th>Slug</th>
                                    <th>Usage</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                                <?php if ($category['description']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($category['description']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $category['type'] == 'adult' ? 'warning' : 'success'; ?>">
                                                <?php echo ucfirst($category['type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <code><?php echo htmlspecialchars($category['slug']); ?></code>
                                        </td>
                                        <td>
                                            <div class="small">
                                                <span class="badge bg-primary me-1"><?php echo $category['rtb_campaigns']; ?> RTB</span>
                                                <span class="badge bg-success me-1"><?php echo $category['ron_campaigns']; ?> RON</span>
                                                <br><span class="badge bg-info"><?php echo $category['websites']; ?> Websites</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $category['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($category['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo date('M j, Y', strtotime($category['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <?php if ($category['status'] == 'active'): ?>
                                                    <a href="?action=deactivate&id=<?php echo $category['id']; ?>" 
                                                       class="btn btn-outline-warning" title="Deactivate">
                                                        <i class="fas fa-pause"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="?action=activate&id=<?php echo $category['id']; ?>" 
                                                       class="btn btn-outline-success" title="Activate">
                                                        <i class="fas fa-play"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <button class="btn btn-outline-primary" onclick="editCategory(<?php echo $category['id']; ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <?php if ($category['rtb_campaigns'] == 0 && $category['ron_campaigns'] == 0 && $category['websites'] == 0): ?>
                                                    <a href="?action=delete&id=<?php echo $category['id']; ?>" 
                                                       class="btn btn-outline-danger delete-action" 
                                                       data-name="<?php echo htmlspecialchars($category['name']); ?>" 
                                                       title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-outline-danger" disabled title="Cannot delete: Category is in use">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
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

<!-- Create Category Modal -->
<div class="modal fade" id="createCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>Add New Category
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Category Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required 
                                       placeholder="Technology">
                                <div class="invalid-feedback">Please provide a category name.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="slug" class="form-label">Slug <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="slug" name="slug" required 
                                       placeholder="technology">
                                <div class="form-text">URL-friendly identifier (auto-generated from name if empty)</div>
                                <div class="invalid-feedback">Please provide a slug.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="type" class="form-label">Category Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="">Select Type</option>
                                    <option value="mainstream">Mainstream</option>
                                    <option value="adult">Adult</option>
                                </select>
                                <div class="invalid-feedback">Please select a category type.</div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4" 
                                          placeholder="Brief description of this category"></textarea>
                            </div>
                            
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle me-2"></i>Category Usage</h6>
                                <ul class="mb-0 small">
                                    <li>Categories help organize and target campaigns</li>
                                    <li>Adult categories require special handling</li>
                                    <li>Mainstream categories are suitable for all audiences</li>
                                    <li>Categories can be used for website classification</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Create Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#categoriesTable').DataTable({
        order: [[5, 'desc']], // Sort by created date
        pageLength: 25,
        responsive: true
    });
    
    // Auto-generate slug from name
    $('#name').on('input', function() {
        const name = $(this).val();
        const slug = name.toLowerCase()
                        .replace(/[^a-z0-9\s-]/g, '') // Remove special chars
                        .replace(/\s+/g, '-')         // Replace spaces with hyphens
                        .replace(/-+/g, '-')          // Replace multiple hyphens with single
                        .replace(/^-|-$/g, '');       // Remove leading/trailing hyphens
        
        $('#slug').val(slug);
    });
    
    // Validate slug format
    $('#slug').on('input', function() {
        const slug = $(this).val();
        const isValid = /^[a-z0-9-]+$/.test(slug) && !slug.startsWith('-') && !slug.endsWith('-');
        
        if (slug && !isValid) {
            $(this).addClass('is-invalid');
            $(this).siblings('.invalid-feedback').text('Slug must contain only lowercase letters, numbers, and hyphens');
        } else {
            $(this).removeClass('is-invalid');
        }
    });
});

function editCategory(id) {
    // TODO: Implement edit functionality
    showWarning('Edit functionality coming soon!');
}
</script>

<?php include 'includes/footer.php'; ?>