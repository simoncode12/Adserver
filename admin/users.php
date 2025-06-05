<?php
$pageTitle = 'User Management';
$breadcrumb = [
    ['text' => 'Users']
];

try {
    require_once '../config/init.php';
    require_once '../config/database.php';
    require_once '../config/constants.php';
    require_once '../includes/auth.php';
    require_once '../includes/helpers.php';

    $auth = new Auth();
    $db = Database::getInstance();

    // Handle actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $csrf_token = $_POST['csrf_token'] ?? '';
        
        if (!verifyCSRFToken($csrf_token)) {
            $error = 'Invalid security token';
        } else {
            switch ($action) {
                case 'update_status':
                    $userId = (int)$_POST['user_id'];
                    $status = sanitize($_POST['status']);
                    
                    if (in_array($status, ['active', 'inactive', 'suspended'])) {
                        $db->update('users', 
                            ['status' => $status], 
                            'id = ?', 
                            [$userId]
                        );
                        $success = 'User status updated successfully';
                    } else {
                        $error = 'Invalid status';
                    }
                    break;
                    
                case 'delete':
                    $userId = (int)$_POST['user_id'];
                    $db->delete('users', 'id = ?', [$userId]);
                    $success = 'User deleted successfully';
                    break;
            }
        }
    }

    // Filters
    $status = $_GET['status'] ?? '';
    $userType = $_GET['user_type'] ?? '';
    $search = sanitize($_GET['search'] ?? '');

    // Build query
    $whereConditions = [];
    $params = [];

    if ($status) {
        $whereConditions[] = 'status = ?';
        $params[] = $status;
    }

    if ($userType) {
        $whereConditions[] = 'user_type = ?';
        $params[] = $userType;
    }

    if ($search) {
        $whereConditions[] = '(username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)';
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // Get users with basic error handling
    $users = [];
    try {
        $users = $db->fetchAll(
            "SELECT id, username, email, first_name, last_name, user_type, status, 
                    balance, created_at, last_login, login_attempts, locked_until
             FROM users 
             {$whereClause}
             ORDER BY created_at DESC",
            $params
        );
    } catch (Exception $e) {
        error_log("Users query error: " . $e->getMessage());
        $error = "Error loading users data";
    }

    // Statistics
    $stats = [
        'total_users' => 0,
        'active_users' => 0,
        'publishers' => 0,
        'advertisers' => 0,
        'total_balance' => 0
    ];

    try {
        $stats = $db->fetch(
            "SELECT 
                COUNT(*) as total_users,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users,
                COUNT(CASE WHEN user_type = 'publisher' THEN 1 END) as publishers,
                COUNT(CASE WHEN user_type = 'advertiser' THEN 1 END) as advertisers,
                SUM(balance) as total_balance
             FROM users"
        );
    } catch (Exception $e) {
        error_log("User stats error: " . $e->getMessage());
    }

    $csrf_token = generateCSRFToken();

} catch (Exception $e) {
    die("Error loading users page: " . $e->getMessage());
}

include 'templates/header.php';
?>

<div id="alerts-container">
    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card stats-card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h3 class="mb-1"><?php echo number_format($stats['total_users']); ?></h3>
                        <p class="mb-0">Total Users</p>
                    </div>
                    <div class="ms-3">
                        <i class="fas fa-users fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card stats-card-success">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h3 class="mb-1"><?php echo number_format($stats['active_users']); ?></h3>
                        <p class="mb-0">Active Users</p>
                    </div>
                    <div class="ms-3">
                        <i class="fas fa-user-check fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card stats-card-warning">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h3 class="mb-1"><?php echo number_format($stats['publishers']); ?></h3>
                        <p class="mb-0">Publishers</p>
                    </div>
                    <div class="ms-3">
                        <i class="fas fa-globe fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card stats-card-info">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h3 class="mb-1">$<?php echo number_format($stats['total_balance'], 2); ?></h3>
                        <p class="mb-0">Total Balance</p>
                    </div>
                    <div class="ms-3">
                        <i class="fas fa-dollar-sign fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Statuses</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="suspended" <?php echo $status === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="user_type" class="form-label">User Type</label>
                <select class="form-select" id="user_type" name="user_type">
                    <option value="">All Types</option>
                    <option value="admin" <?php echo $userType === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="publisher" <?php echo $userType === 'publisher' ? 'selected' : ''; ?>>Publisher</option>
                    <option value="advertiser" <?php echo $userType === 'advertiser' ? 'selected' : ''; ?>>Advertiser</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Users</h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
            <i class="fas fa-plus me-2"></i>Add User
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Balance</th>
                        <th>Created</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                    </div>
                                    <span class="fw-bold"><?php echo htmlspecialchars($user['username']); ?></span>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $user['user_type'] === 'admin' ? 'danger' : 
                                        ($user['user_type'] === 'publisher' ? 'success' : 'primary'); 
                                ?>">
                                    <?php echo ucfirst($user['user_type']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $user['status'] === 'active' ? 'success' : 
                                        ($user['status'] === 'suspended' ? 'danger' : 'secondary'); 
                                ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                                <?php if ($user['locked_until'] && strtotime($user['locked_until']) > time()): ?>
                                    <br><small class="text-danger">Locked</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="fw-bold text-success">$<?php echo number_format($user['balance'], 2); ?></span>
                            </td>
                                <td>
                                <small><?php echo date('M j, Y', strtotime($user['created_at'])); ?></small>
                            </td>
                            <td>
                                <?php if ($user['last_login']): ?>
                                    <small><?php echo date('M j, Y H:i', strtotime($user['last_login'])); ?></small>
                                <?php else: ?>
                                    <small class="text-muted">Never</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" onclick="viewUser(<?php echo $user['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="fas fa-cog"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <button class="dropdown-item" onclick="updateStatus(<?php echo $user['id']; ?>, 'active')">
                                                <i class="fas fa-check me-2 text-success"></i>Activate
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item" onclick="updateStatus(<?php echo $user['id']; ?>, 'suspended')">
                                                <i class="fas fa-ban me-2 text-warning"></i>Suspend
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item" onclick="updateStatus(<?php echo $user['id']; ?>, 'inactive')">
                                                <i class="fas fa-pause me-2 text-secondary"></i>Deactivate
                                            </button>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <button class="dropdown-item text-danger" onclick="deleteUser(<?php echo $user['id']; ?>)">
                                                <i class="fas fa-trash me-2"></i>Delete
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="user_create.php">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="user_type" class="form-label">User Type</label>
                                <select class="form-select" id="user_type" name="user_type" required>
                                    <option value="">Select Type</option>
                                    <option value="publisher">Publisher</option>
                                    <option value="advertiser">Advertiser</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden Forms -->
<form id="statusForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="user_id" id="statusUserId">
    <input type="hidden" name="status" id="statusValue">
</form>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="user_id" id="deleteUserId">
</form>

<script>
function updateStatus(userId, status) {
    if (confirm(`Are you sure you want to ${status} this user?`)) {
        document.getElementById('statusUserId').value = userId;
        document.getElementById('statusValue').value = status;
        document.getElementById('statusForm').submit();
    }
}

function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        document.getElementById('deleteUserId').value = userId;
        document.getElementById('deleteForm').submit();
    }
}

function viewUser(userId) {
    window.open(`user_view.php?id=${userId}`, '_blank');
}
</script>

<?php include 'templates/footer.php'; ?>
