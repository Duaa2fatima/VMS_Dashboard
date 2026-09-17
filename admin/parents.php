<?php
/**
 * Parents Registration & Directory Management - Admin View
 * Child Vaccination Management System (VMS)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
requireAdmin();

$pageTitle = 'Parents Registration & Directory';
$activePage = 'admin_parents';

// Handle Add, Edit, Delete Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? 'parent123');
        $address  = trim($_POST['address'] ?? '');

        if (empty($name) || empty($email) || empty($username)) {
            setFlash('error', 'Full Name, Email Address, and Username are required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Please enter a valid email address.');
        } elseif (strlen($password) < 6) {
            setFlash('error', 'Password must be at least 6 characters long.');
        } else {
            try {
                // Check if username or email is taken
                $stmtCheck = $pdo->prepare("SELECT parent_id FROM parents WHERE username = ? OR email = ? LIMIT 1");
                $stmtCheck->execute([$username, $email]);
                if ($stmtCheck->fetch()) {
                    setFlash('error', 'A parent with this username or email already exists.');
                } else {
                    $hashed = password_hash($password, PASSWORD_BCRYPT);
                    $stmtAdd = $pdo->prepare("INSERT INTO parents (role_id, name, email, phone, username, password, address) VALUES (2, ?, ?, ?, ?, ?, ?)");
                    $stmtAdd->execute([$name, $email, $phone, $username, $hashed, $address]);

                    setFlash('success', "Parent '{$name}' registered successfully!");
                }
            } catch (Exception $e) {
                setFlash('error', 'Error registering parent: ' . $e->getMessage());
            }
        }
        header('Location: parents.php');
        exit;

    }
}

// Fetch all parents with statistics
$parents = [];
$totalParents = 0;
$totalChildren = 0;
$totalBookings = 0;

if ($pdo) {
    try {
        $totalParents = (int)$pdo->query("SELECT COUNT(*) FROM parents")->fetchColumn();
        $totalChildren = (int)$pdo->query("SELECT COUNT(*) FROM children")->fetchColumn();
        $totalBookings = (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();

        $stmt = $pdo->query("SELECT p.*,
            (SELECT COUNT(*) FROM children c WHERE c.parent_id = p.parent_id) as children_count,
            (SELECT GROUP_CONCAT(c.child_name SEPARATOR ', ') FROM children c WHERE c.parent_id = p.parent_id) as children_names,
            (SELECT COUNT(*) FROM bookings b JOIN children c ON b.child_id = c.child_id WHERE c.parent_id = p.parent_id) as bookings_count
            FROM parents p
            ORDER BY p.parent_id DESC");
        $parents = $stmt->fetchAll();
    } catch (Exception $e) {
        setFlash('error', 'Error loading parents: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php'; 
?>

<div class="main-panel">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-inner">
            
            <div class="page-header d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <div>
                    <h3 class="fw-bold mb-1"><i class="fas fa-user-friends text-primary me-2"></i> Parents Registration</h3>
                    <ul class="breadcrumbs mb-0 ps-0 list-unstyled d-flex gap-2 text-muted small">
                        <li><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                        <li>/</li>
                        <li class="active">Registered Parents & Guardians</li>
                    </ul>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-primary btn-round shadow-sm" data-bs-toggle="modal" data-bs-target="#addParentModal">
                        <i class="fas fa-user-plus me-1"></i> Register New Parent
                    </button>
                    <a href="children.php" class="btn btn-outline-secondary btn-round">
                        <i class="fas fa-baby me-1"></i> Child Details
                    </a>
                </div>
            </div>

            <?= displayFlash() ?>

            <!-- Metric Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-md-4">
                    <div class="card card-stats card-round shadow-sm mb-0">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="icon-big text-center text-primary me-3">
                                    <i class="fas fa-user-friends fs-2"></i>
                                </div>
                                <div>
                                    <p class="card-category text-muted mb-0 small">Registered Parents</p>
                                    <h4 class="card-title fw-bold mb-0"><?= $totalParents ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-md-4">
                    <div class="card card-stats card-round shadow-sm mb-0">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="icon-big text-center text-success me-3">
                                    <i class="fas fa-baby fs-2"></i>
                                </div>
                                <div>
                                    <p class="card-category text-muted mb-0 small">Enrolled Children</p>
                                    <h4 class="card-title fw-bold mb-0"><?= $totalChildren ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-md-4">
                    <div class="card card-stats card-round shadow-sm mb-0">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="icon-big text-center text-info me-3">
                                    <i class="fas fa-calendar-check fs-2"></i>
                                </div>
                                <div>
                                    <p class="card-category text-muted mb-0 small">Vaccination Bookings</p>
                                    <h4 class="card-title fw-bold mb-0"><?= $totalBookings ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Parents List Card -->
            <div class="card card-round shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <div class="card-title text-dark">
                        <i class="fas fa-address-book text-primary me-2"></i> Registered Parents & Guardians Directory
                    </div>
                    <span class="badge bg-primary rounded-pill"><?= count($parents) ?> Parents</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover datatable-custom align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#ID</th>
                                    <th>Parent / Guardian</th>
                                    <th>Contact Details</th>
                                    <th>Residential Address</th>
                                    <th>Children Registered</th>
                                    <th>Bookings</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($parents)): ?>
                                    <?php foreach ($parents as $p): ?>
                                        <tr>
                                            <td class="font-monospace fw-bold text-muted">
                                                #PR-<?= str_pad($p['parent_id'], 4, '0', STR_PAD_LEFT) ?>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle me-2 bg-info text-white">
                                                        <i class="fas fa-user"></i>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold text-dark"><?= htmlspecialchars($p['name']) ?></div>
                                                        <small class="text-muted">Username: <code><?= htmlspecialchars($p['username']) ?></code></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div><i class="fas fa-envelope text-muted me-1 small"></i> <?= htmlspecialchars($p['email']) ?></div>
                                                <small class="text-muted"><i class="fas fa-phone-alt text-muted me-1 small"></i> <?= htmlspecialchars($p['phone'] ?: 'N/A') ?></small>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?= htmlspecialchars($p['address'] ?: 'Not provided') ?></small>
                                            </td>
                                            <td>
                                                <?php if ($p['children_count'] > 0): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle fw-bold mb-1">
                                                        <i class="fas fa-baby me-1"></i> <?= $p['children_count'] ?> Child(ren)
                                                    </span>
                                                    <div class="small text-muted text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($p['children_names']) ?>">
                                                        <?= htmlspecialchars($p['children_names']) ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary-subtle text-secondary small">No children yet</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border">
                                                    <?= $p['bookings_count'] ?> booked
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5">
                                            <div class="text-muted">
                                                <i class="fas fa-users-slash fa-3x mb-3 d-block text-secondary"></i>
                                                <h5>No Parents Registered Yet</h5>
                                                <p class="small mb-0">Use the "Register New Parent" button above to register the first parent.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Add Parent Modal -->
    <div class="modal fade" id="addParentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="parents.php">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i> Register New Parent / Guardian</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" placeholder="e.g. John Doe" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" placeholder="john.doe@example.com" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Contact Phone</label>
                                <input type="tel" name="phone" class="form-control" placeholder="+1 (555) 000-0000">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Login Username <span class="text-danger">*</span></label>
                                <input type="text" name="username" class="form-control" placeholder="john_doe" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-bold">Initial Password <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control" value="parent123" required>
                                <small class="text-muted">Default: <code>parent123</code> (at least 6 characters)</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Residential Address</label>
                                <textarea name="address" class="form-control" rows="2" placeholder="Street address, apartment, city..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Register Parent</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
