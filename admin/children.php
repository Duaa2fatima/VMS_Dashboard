<?php
/**
 * All Child Details - Admin View
 * Child Vaccination Management System (VMS)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
requireAdmin();

// Handle Child Registration Form Submission by Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_child') {
    $parentMode = $_POST['parent_mode'] ?? 'existing';
    $parentId   = (int)($_POST['parent_id'] ?? 0);
    $childName  = trim($_POST['child_name'] ?? '');
    $gender     = in_array($_POST['gender'] ?? '', ['Male', 'Female', 'Other']) ? $_POST['gender'] : 'Male';
    $dob        = trim($_POST['date_of_birth'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');

    if (empty($childName) || empty($dob)) {
        setFlash('error', 'Child full name and date of birth are required.');
    } else {
        try {
            if ($parentMode === 'new') {
                $pName     = trim($_POST['new_parent_name'] ?? '');
                $pEmail    = trim($_POST['new_parent_email'] ?? '');
                $pPhone    = trim($_POST['new_parent_phone'] ?? '');
                $pUsername = trim($_POST['new_parent_username'] ?? '');
                $pPassword = $_POST['new_parent_password'] ?? 'parent123';

                if (empty($pName) || empty($pEmail) || empty($pUsername)) {
                    throw new Exception('Parent name, email, and username are required.');
                }

                $stmtCheck = $pdo->prepare("SELECT parent_id FROM parents WHERE username = ? OR email = ? LIMIT 1");
                $stmtCheck->execute([$pUsername, $pEmail]);
                if ($stmtCheck->fetch()) {
                    throw new Exception('A parent with this username or email already exists.');
                }

                $hashed = password_hash($pPassword, PASSWORD_BCRYPT);
                $stmtP = $pdo->prepare("INSERT INTO parents (role_id, name, email, phone, username, password, address) VALUES (2, ?, ?, ?, ?, ?, ?)");
                $stmtP->execute([$pName, $pEmail, $pPhone, $pUsername, $hashed, $address]);
                $parentId = (int)$pdo->lastInsertId();
            }

            if ($parentId <= 0) {
                throw new Exception('Please select or specify a valid parent/guardian.');
            }

            $stmtC = $pdo->prepare("INSERT INTO children (parent_id, child_name, gender, date_of_birth, address, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtC->execute([$parentId, $childName, $gender, $dob, $address, $notes]);

            setFlash('success', "Child '{$childName}' registered successfully!");
            header('Location: children.php');
            exit;

        } catch (Exception $e) {
            setFlash('error', 'Child registration failed: ' . $e->getMessage());
        }
    }
}

$pageTitle = 'All Child Details';
$activePage = 'admin_children';

$children = [];
$parentsList = [];
$totalVaccinesCount = 12;

if ($pdo) {
    try {
        $totalVaccinesCount = (int)$pdo->query("SELECT COUNT(*) FROM vaccines WHERE stock_status = 'Available'")->fetchColumn();

        $stmt = $pdo->query("SELECT c.*, p.name as parent_name, p.phone as parent_phone, p.email as parent_email, p.address as parent_address,
            (SELECT COUNT(*) FROM vaccination_records vr WHERE vr.child_id = c.child_id AND vr.status = 'Vaccinated') as vaccinated_count,
            (SELECT COUNT(*) FROM bookings b WHERE b.child_id = c.child_id AND b.status IN ('Approved', 'Pending')) as pending_count
            FROM children c
            JOIN parents p ON c.parent_id = p.parent_id
            ORDER BY c.child_id DESC");
        $children = $stmt->fetchAll();

        $parentsList = $pdo->query("SELECT parent_id, name, email, phone FROM parents ORDER BY name ASC")->fetchAll();
    } catch (Exception $e) {
        setFlash('error', 'Error loading child records: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-panel">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-inner">
            
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1"><i class="fas fa-baby text-primary me-2"></i> All Child Details</h3>
                    <ul class="breadcrumbs mb-0 ps-0 list-unstyled d-flex gap-2 text-muted small">
                        <li><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                        <li>/</li>
                        <li class="active">Child Registry</li>
                    </ul>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary fs-6 px-3 py-2">
                        Total Registered: <?= count($children) ?> Children
                    </span>
                    <button type="button" class="btn btn-primary btn-round shadow-sm" data-bs-toggle="modal" data-bs-target="#registerChildModal">
                        <i class="fas fa-plus-circle me-1"></i> Register New Child
                    </button>
                </div>
            </div>

            <?= displayFlash() ?>

            <div class="card card-round shadow-sm">
                <div class="card-header bg-light">
                    <div class="card-title text-dark">Registered Children & Immunization Progress</div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover datatable-custom align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Child Profile</th>
                                    <th>Date of Birth & Age</th>
                                    <th>Gender</th>
                                    <th>Parent / Guardian</th>
                                    <th>Residential Address</th>
                                    <th>Vaccination Progress</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($children)): ?>
                                    <?php foreach ($children as $index => $c): ?>
                                        <?php 
                                            $vaxPct = $totalVaccinesCount > 0 ? round(($c['vaccinated_count'] / $totalVaccinesCount) * 100) : 0;
                                        ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle me-2 bg-primary text-white">
                                                        <?= strtoupper(substr($c['child_name'], 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <a href="child-details.php?id=<?= $c['child_id'] ?>" class="fw-bold text-primary text-decoration-none">
                                                            <?= htmlspecialchars($c['child_name']) ?>
                                                        </a>
                                                        <?php if (!empty($c['notes'])): ?>
                                                            <div class="small text-muted text-truncate" style="max-width: 150px;">
                                                                <?= htmlspecialchars($c['notes']) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= formatDate($c['date_of_birth']) ?></div>
                                                <small class="text-muted"><i class="fas fa-birthday-cake text-warning me-1"></i> Age: <?= calculateAge($c['date_of_birth']) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $c['gender'] === 'Male' ? 'info' : ($c['gender'] === 'Female' ? 'danger' : 'secondary') ?>">
                                                    <?= htmlspecialchars($c['gender']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($c['parent_name']) ?></div>
                                                <div class="small text-muted"><i class="fas fa-phone-alt me-1"></i> <?= htmlspecialchars($c['parent_phone'] ?: 'N/A') ?></div>
                                                <div class="small text-muted"><i class="fas fa-envelope me-1"></i> <?= htmlspecialchars($c['parent_email']) ?></div>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?= htmlspecialchars($c['address'] ?: $c['parent_address']) ?></small>
                                            </td>
                                            <td style="min-width: 140px;">
                                                <div class="d-flex justify-content-between small mb-1">
                                                    <span class="fw-bold text-success"><?= $c['vaccinated_count'] ?> doses</span>
                                                    <span class="text-muted"><?= $vaxPct ?>%</span>
                                                </div>
                                                <div class="progress" style="height: 6px;">
                                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $vaxPct ?>%" aria-valuenow="<?= $vaxPct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                                <?php if ($c['pending_count'] > 0): ?>
                                                    <small class="text-warning mt-1 d-block"><i class="fas fa-clock"></i> <?= $c['pending_count'] ?> scheduled</small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <a href="child-details.php?id=<?= $c['child_id'] ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="View Full Child Details">
                                                    <i class="fas fa-eye me-1"></i> View Profile
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Register New Child Modal -->
    <div class="modal fade" id="registerChildModal" tabindex="-1" aria-labelledby="registerChildModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <form method="POST" action="children.php">
                    <input type="hidden" name="action" value="register_child">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold text-white" id="registerChildModalLabel">
                            <i class="fas fa-baby me-2"></i> Register New Child
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <!-- Child Demographics -->
                        <h6 class="fw-bold text-primary mb-3 border-bottom pb-2"><i class="fas fa-id-card me-1"></i> Child Demographics</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-7">
                                <label class="form-label small fw-bold">Child Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="child_name" class="form-control" placeholder="e.g. Noah Robinson" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small fw-bold">Gender <span class="text-danger">*</span></label>
                                <select name="gender" class="form-select" required>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Date of Birth <span class="text-danger">*</span></label>
                                <input type="date" name="date_of_birth" class="form-control" max="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Residential Address</label>
                                <input type="text" name="address" class="form-control" placeholder="Child / Family Address">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold">Medical / Allergy Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Normal birth, no known vaccine allergies"></textarea>
                        </div>

                        <!-- Parent Information Section -->
                        <h6 class="fw-bold text-primary mb-3 border-bottom pb-2"><i class="fas fa-user-friends me-1"></i> Parent / Guardian</h6>
                        <div class="mb-3">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="parent_mode" id="parentModeExisting" value="existing" checked onchange="toggleAdminParentMode()">
                                <label class="form-check-label fw-bold small" for="parentModeExisting">Assign to Existing Parent</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="parent_mode" id="parentModeNew" value="new" onchange="toggleAdminParentMode()">
                                <label class="form-check-label fw-bold small" for="parentModeNew">Register New Parent</label>
                            </div>
                        </div>

                        <div id="existingParentSection">
                            <label class="form-label small fw-bold">Select Registered Parent <span class="text-danger">*</span></label>
                            <select name="parent_id" class="form-select" id="parentSelect">
                                <option value="">-- Choose a Parent --</option>
                                <?php foreach ($parentsList as $p): ?>
                                    <option value="<?= $p['parent_id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['email']) ?> - <?= htmlspecialchars($p['phone'] ?: 'No Phone') ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="newParentSection" style="display: none;">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Parent Name <span class="text-danger">*</span></label>
                                    <input type="text" name="new_parent_name" class="form-control" placeholder="Parent Full Name">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Parent Email <span class="text-danger">*</span></label>
                                    <input type="email" name="new_parent_email" class="form-control" placeholder="parent@example.com">
                                </div>
                            </div>
                            <div class="row g-3 mb-2">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Phone Number</label>
                                    <input type="tel" name="new_parent_phone" class="form-control" placeholder="+1 (555) 000-0000">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Username <span class="text-danger">*</span></label>
                                    <input type="text" name="new_parent_username" class="form-control" placeholder="username">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Password (Default: parent123)</label>
                                    <input type="password" name="new_parent_password" class="form-control" placeholder="parent123" value="parent123">
                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                            <i class="fas fa-save me-1"></i> Register Child
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleAdminParentMode() {
            const isNew = document.getElementById('parentModeNew').checked;
            document.getElementById('newParentSection').style.display = isNew ? 'block' : 'none';
            document.getElementById('existingParentSection').style.display = isNew ? 'none' : 'block';
        }
    </script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
