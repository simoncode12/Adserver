<?php
$pageTitle = 'Publisher Management';
$breadcrumb = [
    ['text' => 'Publisher Management']
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
        
        // Publisher-specific data
        $company_name = sanitize($_POST['company_name']);
        $contact_person = sanitize($_POST['contact_person']);
        $phone = sanitize($_POST['phone']);
        $address = sanitize($_POST['address']);
        $payment_email = sanitize($_POST['payment_email']);
        $payment_method = sanitize($_POST['payment_method']);
        $revenue_share = (float)($_POST['revenue_share'] ?? 50.00);
        $minimum_payout = (float)($_POST['minimum_payout'] ?? 100.00);
        
        // Validate required fields
        if (empty($username) || empty($email) || empty($_POST['password'])) {
            throw new Exception('Please fill in all required fields');
        }
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please provide a valid email address');
        }
        
        // Validate revenue share (0-100%)
        if ($revenue_share < 0 || $revenue_share > 100) {
            throw new Exception('Revenue share must be between 0% and 100%');
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
                'user_type' => 'publisher',
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $db->insert('users', $userData);
            $userId = $db->lastInsertId();
            
            // Create publisher profile
            $publisherData = [
                'user_id' => $userId,
                'company_name' => $company_name,
                'contact_person' => $contact_person,
                'phone' => $phone,
                'address' => $address,
                'payment_email' => $payment_email ?: $email,
                'payment_method' => $payment_method,
                'revenue_share' => $revenue_share,
                'minimum_payout' => $minimum_payout,
                'current_earnings' => 0.00,
                'total_paid' => 0.00,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $db->insert('publishers', $publisherData);
            
            // Commit transaction
            $db->getConnection()->commit();
            
            $_SESSION['success'] = "Publisher '{$username}' created successfully with {$revenue_share}% revenue share!";
            header('Location: publisher.php');
            exit;
            
        } catch (Exception $e) {
            $db->getConnection()->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get publishers with user info and website counts
try {
    $publishers = $db->fetchAll("
        SELECT p.*, u.username, u.email, u.first_name, u.last_name, u.status as user_status,
               (SELECT COUNT(*) FROM websites w WHERE w.publisher_id = p.id) as website_count,
               (SELECT COUNT(*) FROM websites w WHERE w.publisher_id = p.id AND w.status = 'active') as active_websites,
               (SELECT COUNT(*) FROM zones z JOIN websites w ON z.website_id = w.id WHERE w.publisher_id = p.id) as zone_count
        FROM publishers p
        JOIN users u ON p.user_id = u.id
        ORDER BY p.created_at DESC
    ");
} catch (Exception $e) {
    error_log("Publishers query error: " . $e->getMessage());
    $publishers = [];
}

include 'includes/header.php';
?>

<?php include 'includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="main-content">
    <div class="content-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-title">Publisher Management</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Publishers</li>
                    </ol>
                </nav>
            </div>
            <div>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createPublisherModal">
                    <i class="fas fa-plus me-2"></i>Add Publisher
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
        
        <!-- Revenue Sharing Info -->
        <div class="alert alert-info mb-4">
            <div class="row align-items-center">
                <div class="col-md-1 text-center">
                    <i class="fas fa-percentage fa-2x text-info"></i>
                </div>
                <div class="col-md-11">
                    <h5 class="mb-1">Revenue Sharing Model</h5>
                    <p class="mb-0">
                        Publishers earn a percentage of ad revenue generated from their websites. The default revenue share 
                        is 50%, meaning publishers keep 50% of all earnings from ads served on their sites. This can be 
                        customized per publisher based on traffic quality and volume.
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="stat-number"><?php echo count($publishers); ?></h3>
                    <p class="stat-label">Total Publishers</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3 class="stat-number"><?php echo count(array_filter($publishers, fn($p) => $p['status'] == 'active')); ?></h3>
                    <p class="stat-label">Active Publishers</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-globe"></i>
                    </div>
                    <h3 class="stat-number"><?php echo array_sum(array_column($publishers, 'website_count')); ?></h3>
                    <p class="stat-label">Total Websites</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-map-marked-alt"></i>
                    </div>
                    <h3 class="stat-number"><?php echo array_sum(array_column($publishers, 'zone_count')); ?></h3>
                    <p class="stat-label">Total Ad Zones</p>
                </div>
            </div>
        </div>
        
        <!-- Publishers Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-users me-2"></i>Publishers
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($publishers)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No Publishers Found</h4>
                        <p class="text-muted mb-4">Add your first publisher to start monetizing websites</p>
                        <button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#createPublisherModal">
                            <i class="fas fa-plus me-2"></i>Add Your First Publisher
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="publishersTable">
                            <thead>
                                <tr>
                                    <th>Publisher</th>
                                    <th>Contact</th>
                                    <th>Account</th>
                                    <th>Websites</th>
                                    <th>Revenue Share</th>
                                    <th>Earnings</th>
                                    <th>Payment</th>
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
                                                <strong><?php echo htmlspecialchars($publisher['company_name'] ?: ($publisher['first_name'] . ' ' . $publisher['last_name'])); ?></strong>
                                                <br><small class="text-muted">ID: <?php echo $publisher['id']; ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($publisher['contact_person'] ?: ($publisher['first_name'] . ' ' . $publisher['last_name'])); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($publisher['email']); ?></small>
                                                <?php if ($publisher['phone']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($publisher['phone']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($publisher['username']); ?></strong>
                                                <br><span class="badge bg-<?php echo $publisher['user_status'] == 'active' ? 'success' : 'secondary'; ?> badge-sm">
                                                    <?php echo ucfirst($publisher['user_status']); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <span class="badge bg-primary"><?php echo $publisher['active_websites']; ?> active</span>
                                                <br><small class="text-muted"><?php echo $publisher['website_count']; ?> total</small>
                                                <br><small class="text-muted"><?php echo $publisher['zone_count']; ?> zones</small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <span class="badge bg-success badge-lg"><?php echo number_format($publisher['revenue_share'], 1); ?>%</span>
                                                <?php if ($publisher['revenue_share'] != 50): ?>
                                                    <br><small class="text-warning">Custom rate</small>
                                                <?php else: ?>
                                                    <br><small class="text-muted">Default rate</small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong>$<?php echo number_format($publisher['current_earnings'], 2); ?></strong>
                                                <br><small class="text-muted">
                                                    Min: $<?php echo number_format($publisher['minimum_payout'], 2); ?>
                                                </small>
                                                <br><small class="text-success">
                                                    Paid: $<?php echo number_format($publisher['total_paid'], 2); ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <span class="badge bg-info"><?php echo ucfirst(str_replace('_', ' ', $publisher['payment_method'])); ?></span>
                                                <?php if ($publisher['payment_email'] != $publisher['email']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($publisher['payment_email']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo match($publisher['status']) {
                                                'active' => 'success',
                                                'inactive' => 'secondary',
                                                'suspended' => 'danger',
                                                default => 'warning'
                                            }; ?>">
                                                <?php echo ucfirst($publisher['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo date('M j, Y', strtotime($publisher['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-info" onclick="viewPublisher(<?php echo $publisher['id']; ?>)" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-primary" onclick="editPublisher(<?php echo $publisher['id']; ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="website.php?publisher_id=<?php echo $publisher['id']; ?>" 
                                                   class="btn btn-outline-success" title="Manage Websites">
                                                    <i class="fas fa-globe"></i>
                                                </a>
                                                <button class="btn btn-outline-warning" onclick="managePayments(<?php echo $publisher['id']; ?>)" title="Payments">
                                                    <i class="fas fa-dollar-sign"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="deletePublisher(<?php echo $publisher['id']; ?>)" title="Delete">
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

<!-- Create Publisher Modal -->
<div class="modal fade" id="createPublisherModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>Add New Publisher
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <div class="modal-body">
                    <div class="row">
                        <!-- Account Information -->
                        <div class="col-md-6">
                            <h6 class="text-success mb-3">Account Information</h6>
                            
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
                            
                            <div class="mb-3">
                                <label for="company_name" class="form-label">Company Name</label>
                                <input type="text" class="form-control" id="company_name" name="company_name" 
                                       placeholder="Website Network Inc.">
                            </div>
                            
                            <div class="mb-3">
                                <label for="contact_person" class="form-label">Contact Person</label>
                                <input type="text" class="form-control" id="contact_person" name="contact_person" 
                                       placeholder="John Doe">
                            </div>
                        </div>
                        
                        <!-- Revenue & Payment Settings -->
                        <div class="col-md-6">
                            <h6 class="text-success mb-3">Revenue & Payment Settings</h6>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="revenue_share" class="form-label">Revenue Share (%) <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="revenue_share" name="revenue_share" 
                                               value="50.00" step="0.01" min="0" max="100" required>
                                        <div class="form-text">Publisher's share of ad revenue (default: 50%)</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="minimum_payout" class="form-label">Minimum Payout ($)</label>
                                        <input type="number" class="form-control" id="minimum_payout" name="minimum_payout" 
                                               value="100.00" step="0.01" min="0" placeholder="100.00">
                                        <div class="form-text">Minimum earnings for payout</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="payment_method" class="form-label">Payment Method</label>
                                <select class="form-select" id="payment_method" name="payment_method">
                                    <option value="paypal">PayPal</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="wire">Wire Transfer</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="payment_email" class="form-label">Payment Email</label>
                                <input type="email" class="form-control" id="payment_email" name="payment_email" 
                                       placeholder="payments@example.com">
                                <div class="form-text">Leave empty to use account email</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               placeholder="+1 (555) 123-4567">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2" 
                                          placeholder="Company address"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-success mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Revenue Sharing:</strong> Publishers will earn <span id="revenue_display">50%</span> 
                        of all ad revenue generated from their websites. They can access their own dashboard to 
                        manage websites, view earnings, and track performance.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-2"></i>Create Publisher
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#publishersTable').DataTable({
        order: [[8, 'desc']], // Sort by created date
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
    
    // Auto-fill payment email from email field
    $('#email').on('input', function() {
        const email = $(this).val();
        if (email && !$('#payment_email').val()) {
            $('#payment_email').val(email);
        }
    });
    
    // Update revenue share display
    $('#revenue_share').on('input', function() {
        const value = $(this).val();
        $('#revenue_display').text(value + '%');
    });
});

function viewPublisher(id) {
    // TODO: Implement view details functionality
    showWarning('View details functionality coming soon!');
}

function editPublisher(id) {
    // TODO: Implement edit functionality
    showWarning('Edit functionality coming soon!');
}

function managePayments(id) {
    // TODO: Implement payment management
    showWarning('Payment management coming soon!');
}

function deletePublisher(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: 'This will permanently delete the publisher account and all associated websites.',
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