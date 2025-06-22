<?php
$pageTitle = 'Advertiser Management';
$breadcrumb = [
    ['text' => 'Advertiser Management']
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
        // Create user account first
        $username = sanitize($_POST['username']);
        $email = sanitize($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $first_name = sanitize($_POST['first_name']);
        $last_name = sanitize($_POST['last_name']);
        
        // Advertiser-specific data
        $company_name = sanitize($_POST['company_name']);
        $contact_person = sanitize($_POST['contact_person']);
        $phone = sanitize($_POST['phone']);
        $address = sanitize($_POST['address']);
        $website = sanitize($_POST['website']);
        $billing_email = sanitize($_POST['billing_email']);
        $credit_limit = (float)($_POST['credit_limit'] ?? 0);
        
        // Validate required fields
        if (empty($username) || empty($email) || empty($_POST['password']) || empty($company_name)) {
            throw new Exception('Please fill in all required fields');
        }
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please provide a valid email address');
        }
        
        // Validate website URL if provided
        if ($website && !filter_var($website, FILTER_VALIDATE_URL)) {
            throw new Exception('Please provide a valid website URL');
        }
        
        // Check if username or email already exists
        $existingUser = $db->fetch("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
        if ($existingUser) {
            throw new Exception('Username or email already exists');
        }
        
        // Start transaction
        $db->getConnection()->beginTransaction();
        
        try {
            // Create user
            $userData = [
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'user_type' => 'advertiser',
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $db->insert('users', $userData);
            $userId = $db->lastInsertId();
            
            // Create advertiser profile
            $advertiserData = [
                'user_id' => $userId,
                'company_name' => $company_name,
                'contact_person' => $contact_person,
                'phone' => $phone,
                'address' => $address,
                'website' => $website,
                'billing_email' => $billing_email ?: $email,
                'credit_limit' => $credit_limit,
                'current_balance' => 0.00,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $db->insert('advertisers', $advertiserData);
            
            // Commit transaction
            $db->getConnection()->commit();
            
            $_SESSION['success'] = "Advertiser '{$company_name}' created successfully!";
            header('Location: advertiser.php');
            exit;
            
        } catch (Exception $e) {
            $db->getConnection()->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get advertisers with user info and campaign counts
try {
    $advertisers = $db->fetchAll("
        SELECT a.*, u.username, u.email, u.first_name, u.last_name, u.status as user_status,
               (SELECT COUNT(*) FROM rtb_campaigns rc WHERE rc.advertiser_id = a.id) as rtb_campaigns,
               (SELECT COUNT(*) FROM ron_campaigns roc WHERE roc.advertiser_id = a.id) as ron_campaigns,
               (SELECT SUM(spent_amount) FROM rtb_campaigns rc WHERE rc.advertiser_id = a.id) +
               (SELECT SUM(spent_amount) FROM ron_campaigns roc WHERE roc.advertiser_id = a.id) as total_spent
        FROM advertisers a
        JOIN users u ON a.user_id = u.id
        ORDER BY a.created_at DESC
    ");
} catch (Exception $e) {
    error_log("Advertisers query error: " . $e->getMessage());
    $advertisers = [];
}

include 'includes/header.php';
?>

<?php include 'includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">
    <div class="content-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-title">Advertiser Management</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Advertisers</li>
                    </ol>
                </nav>
            </div>
            <div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAdvertiserModal">
                    <i class="fas fa-plus me-2"></i>Add Advertiser
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
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <h3 class="stat-number"><?php echo count($advertisers); ?></h3>
                    <p class="stat-label">Total Advertisers</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3 class="stat-number"><?php echo count(array_filter($advertisers, fn($a) => $a['status'] == 'active')); ?></h3>
                    <p class="stat-label">Active Advertisers</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <h3 class="stat-number"><?php echo array_sum(array_column($advertisers, 'rtb_campaigns')); ?></h3>
                    <p class="stat-label">RTB Campaigns</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-network-wired"></i>
                    </div>
                    <h3 class="stat-number"><?php echo array_sum(array_column($advertisers, 'ron_campaigns')); ?></h3>
                    <p class="stat-label">RON Campaigns</p>
                </div>
            </div>
        </div>
        
        <!-- Advertisers Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user-tie me-2"></i>Advertisers
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($advertisers)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-user-tie fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No Advertisers Found</h4>
                        <p class="text-muted mb-4">Add your first advertiser to start managing campaigns</p>
                        <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#createAdvertiserModal">
                            <i class="fas fa-plus me-2"></i>Add Your First Advertiser
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="advertisersTable">
                            <thead>
                                <tr>
                                    <th>Company</th>
                                    <th>Contact</th>
                                    <th>Account</th>
                                    <th>Campaigns</th>
                                    <th>Balance</th>
                                    <th>Total Spent</th>
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
                                                <strong><?php echo htmlspecialchars($advertiser['company_name']); ?></strong>
                                                <?php if ($advertiser['website']): ?>
                                                    <br><a href="<?php echo htmlspecialchars($advertiser['website']); ?>" 
                                                           target="_blank" class="text-decoration-none small">
                                                        <i class="fas fa-external-link-alt me-1"></i>
                                                        <?php echo htmlspecialchars(parse_url($advertiser['website'], PHP_URL_HOST)); ?>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($advertiser['contact_person'] ?: ($advertiser['first_name'] . ' ' . $advertiser['last_name'])); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($advertiser['email']); ?></small>
                                                <?php if ($advertiser['phone']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($advertiser['phone']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($advertiser['username']); ?></strong>
                                                <br><span class="badge bg-<?php echo $advertiser['user_status'] == 'active' ? 'success' : 'secondary'; ?> badge-sm">
                                                    <?php echo ucfirst($advertiser['user_status']); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <span class="badge bg-primary me-1"><?php echo $advertiser['rtb_campaigns']; ?> RTB</span>
                                                <br><span class="badge bg-success"><?php echo $advertiser['ron_campaigns']; ?> RON</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong>$<?php echo number_format($advertiser['current_balance'], 2); ?></strong>
                                                <br><small class="text-muted">
                                                    Limit: $<?php echo number_format($advertiser['credit_limit'], 2); ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <strong>$<?php echo number_format($advertiser['total_spent'] ?: 0, 2); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo match($advertiser['status']) {
                                                'active' => 'success',
                                                'inactive' => 'secondary',
                                                'suspended' => 'danger',
                                                default => 'warning'
                                            }; ?>">
                                                <?php echo ucfirst($advertiser['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo date('M j, Y', strtotime($advertiser['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-info" onclick="viewAdvertiser(<?php echo $advertiser['id']; ?>)" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-primary" onclick="editAdvertiser(<?php echo $advertiser['id']; ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="rtb-sell.php?advertiser_id=<?php echo $advertiser['id']; ?>" 
                                                   class="btn btn-outline-success" title="Create Campaign">
                                                    <i class="fas fa-plus"></i>
                                                </a>
                                                <button class="btn btn-outline-danger" onclick="deleteAdvertiser(<?php echo $advertiser['id']; ?>)" title="Delete">
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
    </div>
</div>

<!-- Create Advertiser Modal -->
<div class="modal fade" id="createAdvertiserModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>Add New Advertiser
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <div class="modal-body">
                    <div class="row">
                        <!-- Account Information -->
                        <div class="col-md-6">
                            <h6 class="text-primary mb-3">Account Information</h6>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="first_name" class="form-label">First Name</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                               placeholder="John">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="last_name" class="form-label">Last Name</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                               placeholder="Doe">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="username" name="username" required 
                                       placeholder="johndoe">
                                <div class="invalid-feedback">Please provide a username.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required 
                                       placeholder="john@example.com">
                                <div class="invalid-feedback">Please provide a valid email.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password" name="password" required 
                                       minlength="6" placeholder="Enter password">
                                <div class="invalid-feedback">Password must be at least 6 characters.</div>
                            </div>
                        </div>
                        
                        <!-- Company Information -->
                        <div class="col-md-6">
                            <h6 class="text-primary mb-3">Company Information</h6>
                            
                            <div class="mb-3">
                                <label for="company_name" class="form-label">Company Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="company_name" name="company_name" required 
                                       placeholder="Acme Corporation">
                                <div class="invalid-feedback">Please provide a company name.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="contact_person" class="form-label">Contact Person</label>
                                <input type="text" class="form-control" id="contact_person" name="contact_person" 
                                       placeholder="John Doe">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               placeholder="+1 (555) 123-4567">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="website" class="form-label">Website</label>
                                        <input type="url" class="form-control" id="website" name="website" 
                                               placeholder="https://example.com">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2" 
                                          placeholder="Company address"></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="billing_email" class="form-label">Billing Email</label>
                                        <input type="email" class="form-control" id="billing_email" name="billing_email" 
                                               placeholder="billing@example.com">
                                        <div class="form-text">Leave empty to use account email</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="credit_limit" class="form-label">Credit Limit ($)</label>
                                        <input type="number" class="form-control" id="credit_limit" name="credit_limit" 
                                               step="0.01" min="0" placeholder="1000.00">
                                        <div class="form-text">Maximum credit allowed</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> The advertiser will receive login credentials via email and can access 
                        their own dashboard to manage campaigns and view reports.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Create Advertiser
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#advertisersTable').DataTable({
        order: [[7, 'desc']], // Sort by created date
        pageLength: 25,
        responsive: true
    });
    
    // Auto-fill contact person from name fields
    $('#first_name, #last_name').on('input', function() {
        const firstName = $('#first_name').val();
        const lastName = $('#last_name').val();
        const fullName = (firstName + ' ' + lastName).trim();
        
        if (fullName && !$('#contact_person').val()) {
            $('#contact_person').val(fullName);
        }
    });
    
    // Auto-fill billing email from email field
    $('#email').on('input', function() {
        const email = $(this).val();
        if (email && !$('#billing_email').val()) {
            $('#billing_email').val(email);
        }
    });
});

function viewAdvertiser(id) {
    // TODO: Implement view details functionality
    showWarning('View details functionality coming soon!');
}

function editAdvertiser(id) {
    // TODO: Implement edit functionality
    showWarning('Edit functionality coming soon!');
}

function deleteAdvertiser(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: 'This will permanently delete the advertiser account and all associated campaigns.',
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