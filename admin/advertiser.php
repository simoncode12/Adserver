<?php
$pageTitle = 'Advertiser Management';
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
                    
                    // Validate email format
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $error = 'Please provide a valid email address.';
                        break;
                    }
                    
                    // Check if email already exists
                    $existing = $db->fetch("SELECT id FROM advertisers WHERE email = ?", [$email]);
                    if ($existing) {
                        $error = 'An advertiser with this email already exists.';
                        break;
                    }
                    
                    $db->insert('advertisers', [
                        'name' => $name,
                        'email' => $email,
                        'contact_person' => $contact_person,
                        'phone' => $phone,
                        'status' => 'active'
                    ]);
                    
                    $success = 'Advertiser created successfully!';
                    break;
                    
                case 'update':
                    $advertiser_id = (int)$_POST['advertiser_id'];
                    $name = sanitize($_POST['name']);
                    $email = sanitize($_POST['email']);
                    $contact_person = sanitize($_POST['contact_person']);
                    $phone = sanitize($_POST['phone']);
                    
                    // Validate email format
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $error = 'Please provide a valid email address.';
                        break;
                    }
                    
                    // Check if email already exists for another advertiser
                    $existing = $db->fetch("SELECT id FROM advertisers WHERE email = ? AND id != ?", [$email, $advertiser_id]);
                    if ($existing) {
                        $error = 'Another advertiser with this email already exists.';
                        break;
                    }
                    
                    $db->update('advertisers', [
                        'name' => $name,
                        'email' => $email,
                        'contact_person' => $contact_person,
                        'phone' => $phone
                    ], 'id = ?', [$advertiser_id]);
                    
                    $success = 'Advertiser updated successfully!';
                    break;
                    
                case 'update_status':
                    $advertiser_id = (int)$_POST['advertiser_id'];
                    $status = sanitize($_POST['status']);
                    
                    $db->update('advertisers', 
                        ['status' => $status], 
                        'id = ?', 
                        [$advertiser_id]
                    );
                    
                    $success = 'Advertiser status updated successfully!';
                    break;
                    
                case 'delete':
                    $advertiser_id = (int)$_POST['advertiser_id'];
                    
                    // Check if advertiser has campaigns
                    $rtb_count = $db->fetch("SELECT COUNT(*) as count FROM rtb_campaigns WHERE advertiser_id = ?", [$advertiser_id])['count'];
                    $ron_count = $db->fetch("SELECT COUNT(*) as count FROM ron_campaigns WHERE advertiser_id = ?", [$advertiser_id])['count'];
                    $total_campaigns = $rtb_count + $ron_count;
                    
                    if ($total_campaigns > 0) {
                        $error = "Cannot delete advertiser. They have {$total_campaigns} associated campaigns. Please delete campaigns first.";
                        break;
                    }
                    
                    $db->delete('advertisers', 'id = ?', [$advertiser_id]);
                    $success = 'Advertiser deleted successfully!';
                    break;
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get advertisers with campaign counts
$advertisers = $db->fetchAll("
    SELECT a.*,
           (SELECT COUNT(*) FROM rtb_campaigns WHERE advertiser_id = a.id) as rtb_campaigns,
           (SELECT COUNT(*) FROM ron_campaigns WHERE advertiser_id = a.id) as ron_campaigns
    FROM advertisers a 
    ORDER BY a.created_at DESC
");
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

<!-- Advertiser Info -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card bg-gradient-primary text-white">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="card-title mb-2">
                            <i class="fas fa-ad"></i> Advertiser Management
                        </h5>
                        <p class="card-text mb-0">
                            Manage advertisers who run RTB and RON campaigns on your platform.
                            Track their campaign activity and maintain contact information.
                        </p>
                    </div>
                    <div class="col-md-4 text-center">
                        <i class="fas fa-building fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Advertiser Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h4 class="text-primary"><?php echo count($advertisers); ?></h4>
                <p class="mb-0 text-muted">Total Advertisers</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h4 class="text-success">
                    <?php echo count(array_filter($advertisers, function($a) { return $a['status'] === 'active'; })); ?>
                </h4>
                <p class="mb-0 text-muted">Active Advertisers</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h4 class="text-info">
                    <?php echo array_sum(array_column($advertisers, 'rtb_campaigns')); ?>
                </h4>
                <p class="mb-0 text-muted">RTB Campaigns</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h4 class="text-warning">
                    <?php echo array_sum(array_column($advertisers, 'ron_campaigns')); ?>
                </h4>
                <p class="mb-0 text-muted">RON Campaigns</p>
            </div>
        </div>
    </div>
</div>

<!-- Create New Advertiser -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus"></i> Add New Advertiser</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Company Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                            <div class="form-text">Full company or brand name</div>
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
                        <div class="col-md-6 mb-3">
                            <label for="contact_person" class="form-label">Contact Person <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="contact_person" name="contact_person" required>
                            <div class="form-text">Name of primary contact</div>
                            <div class="invalid-feedback">Please provide a contact person name.</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone">
                            <div class="form-text">Contact phone number (optional)</div>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add Advertiser
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Existing Advertisers -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list"></i> Advertisers</h5>
            </div>
            <div class="card-body">
                <?php if (empty($advertisers)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-ad fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No advertisers found</h5>
                        <p class="text-muted">Add your first advertiser to start creating and managing ad campaigns.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Company</th>
                                    <th>Contact Info</th>
                                    <th>Campaigns</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($advertisers as $advertiser): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($advertiser['name']); ?></strong>
                                            </div>
                                            <small class="text-muted">ID: <?php echo $advertiser['id']; ?></small>
                                        </td>
                                        <td>
                                            <div>
                                                <i class="fas fa-user text-muted me-1"></i>
                                                <?php echo htmlspecialchars($advertiser['contact_person']); ?>
                                            </div>
                                            <div>
                                                <i class="fas fa-envelope text-muted me-1"></i>
                                                <a href="mailto:<?php echo htmlspecialchars($advertiser['email']); ?>">
                                                    <?php echo htmlspecialchars($advertiser['email']); ?>
                                                </a>
                                            </div>
                                            <?php if ($advertiser['phone']): ?>
                                                <div>
                                                    <i class="fas fa-phone text-muted me-1"></i>
                                                    <a href="tel:<?php echo htmlspecialchars($advertiser['phone']); ?>">
                                                        <?php echo htmlspecialchars($advertiser['phone']); ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <?php if ($advertiser['rtb_campaigns'] > 0): ?>
                                                    <span class="badge bg-info">
                                                        <?php echo $advertiser['rtb_campaigns']; ?> RTB
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($advertiser['ron_campaigns'] > 0): ?>
                                                    <span class="badge bg-warning">
                                                        <?php echo $advertiser['ron_campaigns']; ?> RON
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($advertiser['rtb_campaigns'] == 0 && $advertiser['ron_campaigns'] == 0): ?>
                                                    <span class="text-muted">No campaigns</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-status-<?php echo $advertiser['status']; ?>">
                                                <?php echo ucfirst($advertiser['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo date('M j, Y', strtotime($advertiser['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-info btn-sm" 
                                                        onclick="editAdvertiser(<?php echo htmlspecialchars(json_encode($advertiser)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="advertiser_id" value="<?php echo $advertiser['id']; ?>">
                                                    <input type="hidden" name="status" value="<?php echo $advertiser['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                                    <button type="submit" class="btn <?php echo $advertiser['status'] === 'active' ? 'btn-warning' : 'btn-success'; ?> btn-sm">
                                                        <i class="fas <?php echo $advertiser['status'] === 'active' ? 'fa-pause' : 'fa-play'; ?>"></i>
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this advertiser?')">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="advertiser_id" value="<?php echo $advertiser['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" 
                                                            <?php echo ($advertiser['rtb_campaigns'] + $advertiser['ron_campaigns']) > 0 ? 'disabled title="Cannot delete advertiser with campaigns"' : ''; ?>>
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

<!-- Edit Advertiser Modal -->
<div class="modal fade" id="editAdvertiserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Advertiser</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="advertiser_id" id="edit_advertiser_id">
                
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
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Advertiser
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Edit advertiser function
function editAdvertiser(advertiser) {
    document.getElementById('edit_advertiser_id').value = advertiser.id;
    document.getElementById('edit_name').value = advertiser.name;
    document.getElementById('edit_email').value = advertiser.email;
    document.getElementById('edit_contact_person').value = advertiser.contact_person;
    document.getElementById('edit_phone').value = advertiser.phone || '';
    
    new bootstrap.Modal(document.getElementById('editAdvertiserModal')).show();
}

// Email validation
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Real-time email validation
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