<?php
$pageTitle = 'Publisher Management';
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
                    $email = sanitize($_POST['email']);
                    $contact_person = sanitize($_POST['contact_person']);
                    $phone = sanitize($_POST['phone']);
                    $revenue_share = (float)$_POST['revenue_share'];
                    
                    // Validate email format
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $error = 'Please provide a valid email address.';
                        break;
                    }
                    
                    // Validate revenue share
                    if ($revenue_share < 0 || $revenue_share > 100) {
                        $error = 'Revenue share must be between 0% and 100%.';
                        break;
                    }
                    
                    // Check if email already exists
                    $existing = $db->fetch("SELECT id FROM publishers WHERE email = ?", [$email]);
                    if ($existing) {
                        $error = 'A publisher with this email already exists.';
                        break;
                    }
                    
                    $db->insert('publishers', [
                        'name' => $name,
                        'email' => $email,
                        'contact_person' => $contact_person,
                        'phone' => $phone,
                        'revenue_share' => $revenue_share,
                        'status' => 'active'
                    ]);
                    
                    $success = 'Publisher created successfully!';
                    break;
                    
                case 'update':
                    $publisher_id = (int)$_POST['publisher_id'];
                    $name = sanitize($_POST['name']);
                    $email = sanitize($_POST['email']);
                    $contact_person = sanitize($_POST['contact_person']);
                    $phone = sanitize($_POST['phone']);
                    $revenue_share = (float)$_POST['revenue_share'];
                    
                    // Validate email format
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $error = 'Please provide a valid email address.';
                        break;
                    }
                    
                    // Validate revenue share
                    if ($revenue_share < 0 || $revenue_share > 100) {
                        $error = 'Revenue share must be between 0% and 100%.';
                        break;
                    }
                    
                    // Check if email already exists for another publisher
                    $existing = $db->fetch("SELECT id FROM publishers WHERE email = ? AND id != ?", [$email, $publisher_id]);
                    if ($existing) {
                        $error = 'Another publisher with this email already exists.';
                        break;
                    }
                    
                    $db->update('publishers', [
                        'name' => $name,
                        'email' => $email,
                        'contact_person' => $contact_person,
                        'phone' => $phone,
                        'revenue_share' => $revenue_share
                    ], 'id = ?', [$publisher_id]);
                    
                    $success = 'Publisher updated successfully!';
                    break;
                    
                case 'update_status':
                    $publisher_id = (int)$_POST['publisher_id'];
                    $status = sanitize($_POST['status']);
                    
                    $db->update('publishers', 
                        ['status' => $status], 
                        'id = ?', 
                        [$publisher_id]
                    );
                    
                    $success = 'Publisher status updated successfully!';
                    break;
                    
                case 'delete':
                    $publisher_id = (int)$_POST['publisher_id'];
                    
                    // Check if publisher has websites
                    $website_count = $db->fetch("SELECT COUNT(*) as count FROM websites WHERE publisher_id = ?", [$publisher_id])['count'];
                    
                    if ($website_count > 0) {
                        $error = "Cannot delete publisher. They have {$website_count} associated websites. Please delete websites first.";
                        break;
                    }
                    
                    $db->delete('publishers', 'id = ?', [$publisher_id]);
                    $success = 'Publisher deleted successfully!';
                    break;
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get publishers with website and zone counts
$publishers = $db->fetchAll("
    SELECT p.*,
           (SELECT COUNT(*) FROM websites WHERE publisher_id = p.id) as website_count,
           (SELECT COUNT(*) FROM zones z 
            JOIN websites w ON z.website_id = w.id 
            WHERE w.publisher_id = p.id) as zone_count
    FROM publishers p 
    ORDER BY p.created_at DESC
");

// Calculate revenue share statistics
$avg_revenue_share = 0;
$min_revenue_share = 100;
$max_revenue_share = 0;

if (!empty($publishers)) {
    $revenue_shares = array_column($publishers, 'revenue_share');
    $avg_revenue_share = array_sum($revenue_shares) / count($revenue_shares);
    $min_revenue_share = min($revenue_shares);
    $max_revenue_share = max($revenue_shares);
}
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

<!-- Publisher Info -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card bg-gradient-success text-white">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="card-title mb-2">
                            <i class="fas fa-newspaper"></i> Publisher Management
                        </h5>
                        <p class="card-text mb-0">
                            Manage publishers who provide ad inventory through their websites.
                            Set individual revenue share percentages for each publisher.
                        </p>
                    </div>
                    <div class="col-md-4 text-center">
                        <i class="fas fa-handshake fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Publisher Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h4 class="text-primary"><?php echo count($publishers); ?></h4>
                <p class="mb-0 text-muted">Total Publishers</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h4 class="text-success">
                    <?php echo count(array_filter($publishers, function($p) { return $p['status'] === 'active'; })); ?>
                </h4>
                <p class="mb-0 text-muted">Active Publishers</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h4 class="text-info">
                    <?php echo array_sum(array_column($publishers, 'website_count')); ?>
                </h4>
                <p class="mb-0 text-muted">Total Websites</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h4 class="text-warning">
                    <?php echo number_format($avg_revenue_share, 1); ?>%
                </h4>
                <p class="mb-0 text-muted">Avg. Revenue Share</p>
            </div>
        </div>
    </div>
</div>

<!-- Revenue Share Overview -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-info">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="fas fa-chart-pie"></i> Revenue Share Overview</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="text-center">
                            <h6>Default Share</h6>
                            <h4 class="text-primary"><?php echo DEFAULT_REVENUE_SHARE; ?>%</h4>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center">
                            <h6>Range</h6>
                            <h4 class="text-success">
                                <?php echo number_format($min_revenue_share, 1); ?>% - <?php echo number_format($max_revenue_share, 1); ?>%
                            </h4>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center">
                            <h6>Platform Average</h6>
                            <h4 class="text-warning"><?php echo 100 - $avg_revenue_share; ?>%</h4>
                            <small class="text-muted">Platform Keep</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create New Publisher -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus"></i> Add New Publisher</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Company Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                            <div class="form-text">Publisher company or brand name</div>
                            <div class="invalid-feedback">Please provide a company name.</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required>
                            <div class="form-text">Primary contact email</div>
                            <div class="invalid-feedback">Please provide a valid email address.</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="contact_person" class="form-label">Contact Person <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="contact_person" name="contact_person" required>
                            <div class="form-text">Name of primary contact</div>
                            <div class="invalid-feedback">Please provide a contact person name.</div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone">
                            <div class="form-text">Contact phone number (optional)</div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="revenue_share" class="form-label">Revenue Share <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="revenue_share" name="revenue_share" 
                                       min="0" max="100" step="0.01" value="<?php echo DEFAULT_REVENUE_SHARE; ?>" required>
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text">Publisher's share of revenue (0-100%)</div>
                            <div class="invalid-feedback">Please provide a valid revenue share percentage.</div>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Add Publisher
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Existing Publishers -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list"></i> Publishers</h5>
            </div>
            <div class="card-body">
                <?php if (empty($publishers)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-newspaper fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No publishers found</h5>
                        <p class="text-muted">Add your first publisher to start managing websites and ad inventory.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Publisher</th>
                                    <th>Contact Info</th>
                                    <th>Revenue Share</th>
                                    <th>Websites</th>
                                    <th>Zones</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($publishers as $publisher): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($publisher['name']); ?></strong>
                                            </div>
                                            <small class="text-muted">ID: <?php echo $publisher['id']; ?></small>
                                        </td>
                                        <td>
                                            <div>
                                                <i class="fas fa-user text-muted me-1"></i>
                                                <?php echo htmlspecialchars($publisher['contact_person']); ?>
                                            </div>
                                            <div>
                                                <i class="fas fa-envelope text-muted me-1"></i>
                                                <a href="mailto:<?php echo htmlspecialchars($publisher['email']); ?>">
                                                    <?php echo htmlspecialchars($publisher['email']); ?>
                                                </a>
                                            </div>
                                            <?php if ($publisher['phone']): ?>
                                                <div>
                                                    <i class="fas fa-phone text-muted me-1"></i>
                                                    <a href="tel:<?php echo htmlspecialchars($publisher['phone']); ?>">
                                                        <?php echo htmlspecialchars($publisher['phone']); ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="revenue-share">
                                                <span><?php echo number_format($publisher['revenue_share'], 1); ?>%</span>
                                                <div class="revenue-share-bar">
                                                    <div class="revenue-share-fill" 
                                                         style="width: <?php echo $publisher['revenue_share']; ?>%">
                                                    </div>
                                                </div>
                                            </div>
                                            <small class="text-muted">
                                                Platform keeps <?php echo number_format(100 - $publisher['revenue_share'], 1); ?>%
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo $publisher['website_count']; ?> websites
                                            </span>
                                            <?php if ($publisher['website_count'] > 0): ?>
                                                <a href="website.php?publisher_id=<?php echo $publisher['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary ms-1">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">
                                                <?php echo $publisher['zone_count']; ?> zones
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-status-<?php echo $publisher['status']; ?>">
                                                <?php echo ucfirst($publisher['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo date('M j, Y', strtotime($publisher['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-info btn-sm" 
                                                        onclick="editPublisher(<?php echo htmlspecialchars(json_encode($publisher)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="publisher_id" value="<?php echo $publisher['id']; ?>">
                                                    <input type="hidden" name="status" value="<?php echo $publisher['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                                    <button type="submit" class="btn <?php echo $publisher['status'] === 'active' ? 'btn-warning' : 'btn-success'; ?> btn-sm">
                                                        <i class="fas <?php echo $publisher['status'] === 'active' ? 'fa-pause' : 'fa-play'; ?>"></i>
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this publisher?')">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="publisher_id" value="<?php echo $publisher['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" 
                                                            <?php echo $publisher['website_count'] > 0 ? 'disabled title="Cannot delete publisher with websites"' : ''; ?>>
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

<!-- Edit Publisher Modal -->
<div class="modal fade" id="editPublisherModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Publisher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="publisher_id" id="edit_publisher_id">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Company Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                        <div class="invalid-feedback">Please provide a company name.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="edit_email" name="email" required>
                        <div class="invalid-feedback">Please provide a valid email address.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_contact_person" class="form-label">Contact Person <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_contact_person" name="contact_person" required>
                        <div class="invalid-feedback">Please provide a contact person name.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="edit_phone" name="phone">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_revenue_share" class="form-label">Revenue Share <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="edit_revenue_share" name="revenue_share" 
                                   min="0" max="100" step="0.01" required>
                            <span class="input-group-text">%</span>
                        </div>
                        <div class="form-text">Publisher's share of revenue (0-100%)</div>
                        <div class="invalid-feedback">Please provide a valid revenue share percentage.</div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Update Publisher
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Edit publisher function
function editPublisher(publisher) {
    document.getElementById('edit_publisher_id').value = publisher.id;
    document.getElementById('edit_name').value = publisher.name;
    document.getElementById('edit_email').value = publisher.email;
    document.getElementById('edit_contact_person').value = publisher.contact_person;
    document.getElementById('edit_phone').value = publisher.phone || '';
    document.getElementById('edit_revenue_share').value = publisher.revenue_share;
    
    new bootstrap.Modal(document.getElementById('editPublisherModal')).show();
}

// Revenue share validation
function validateRevenueShare(value) {
    return value >= 0 && value <= 100;
}

// Real-time validation
document.getElementById('revenue_share').addEventListener('input', function() {
    const value = parseFloat(this.value);
    if (!validateRevenueShare(value)) {
        this.setCustomValidity('Revenue share must be between 0% and 100%');
    } else {
        this.setCustomValidity('');
    }
});

document.getElementById('edit_revenue_share').addEventListener('input', function() {
    const value = parseFloat(this.value);
    if (!validateRevenueShare(value)) {
        this.setCustomValidity('Revenue share must be between 0% and 100%');
    } else {
        this.setCustomValidity('');
    }
});

// Email validation
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

document.getElementById('email').addEventListener('blur', function() {
    if (this.value && !validateEmail(this.value)) {
        this.setCustomValidity('Please enter a valid email address');
    } else {
        this.setCustomValidity('');
    }
});

document.getElementById('edit_email').addEventListener('blur', function() {
    if (this.value && !validateEmail(this.value)) {
        this.setCustomValidity('Please enter a valid email address');
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