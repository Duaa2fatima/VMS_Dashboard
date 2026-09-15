<?php
/**
 * Hospital Directory & Management (Add, Update, Delete, List) - Admin View
 * Child Vaccination Management System (VMS)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
requireAdmin();

$pageTitle = 'List of Hospitals & Management';
$activePage = 'admin_hospitals';

// Handle Add, Edit, Delete Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $hospitalName = trim($_POST['hospital_name'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $phone        = trim($_POST['phone'] ?? '');
        $location     = trim($_POST['location'] ?? '');
        $address      = trim($_POST['address'] ?? '');
        $username     = trim($_POST['username'] ?? '');
        $password     = trim($_POST['password'] ?? 'hospital123');
        $status       = $_POST['status'] === 'Inactive' ? 'Inactive' : 'Active';

        if (empty($hospitalName) || empty($email) || empty($username)) {
            setFlash('error', 'Please fill in all required hospital details (Name, Email, Username).');
        } else {
            try {
                // Check if email or username already exists
                $stmtCheck = $pdo->prepare("SELECT hospital_id FROM hospitals WHERE username = ? OR email = ?");
                $stmtCheck->execute([$username, $email]);
                if ($stmtCheck->fetch()) {
                    setFlash('error', 'A hospital with this username or email already exists.');
                } else {
                    $hashed = password_hash($password, PASSWORD_BCRYPT);
                    $stmtHosp = $pdo->prepare("INSERT INTO hospitals (role_id, hospital_name, address, location, phone, email, username, password, status) VALUES (3, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmtHosp->execute([$hospitalName, $address, $location, $phone, $email, $username, $hashed, $status]);

                    setFlash('success', "Hospital '{$hospitalName}' added successfully.");
                }
            } catch (Exception $e) {
                setFlash('error', 'Error adding hospital: ' . $e->getMessage());
            }
        }
        header('Location: hospitals.php');
        exit;
    } elseif ($action === 'edit') {
        $id           = (int)($_POST['hospital_id'] ?? 0);
        $hospitalName = trim($_POST['hospital_name'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $phone        = trim($_POST['phone'] ?? '');
        $location     = trim($_POST['location'] ?? '');
        $address      = trim($_POST['address'] ?? '');
        $username     = trim($_POST['username'] ?? '');
        $status       = $_POST['status'] === 'Inactive' ? 'Inactive' : 'Active';
        $newPassword  = trim($_POST['password'] ?? '');

        if ($id > 0 && !empty($hospitalName) && !empty($email) && !empty($username)) {
            try {
                // Check username or email uniqueness excluding current hospital
                $stmtCheck = $pdo->prepare("SELECT hospital_id FROM hospitals WHERE (username = ? OR email = ?) AND hospital_id != ?");
                $stmtCheck->execute([$username, $email, $id]);
                if ($stmtCheck->fetch()) {
                    setFlash('error', 'Another hospital is already using this username or email.');
                } else {
                    if (!empty($newPassword)) {
                        $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
                        $stmtUp = $pdo->prepare("UPDATE hospitals SET hospital_name = ?, address = ?, location = ?, phone = ?, email = ?, username = ?, password = ?, status = ? WHERE hospital_id = ?");
                        $stmtUp->execute([$hospitalName, $address, $location, $phone, $email, $username, $hashed, $status, $id]);
                    } else {
                        $stmtUp = $pdo->prepare("UPDATE hospitals SET hospital_name = ?, address = ?, location = ?, phone = ?, email = ?, username = ?, status = ? WHERE hospital_id = ?");
                        $stmtUp->execute([$hospitalName, $address, $location, $phone, $email, $username, $status, $id]);
                    }

                    setFlash('success', "Hospital '{$hospitalName}' updated successfully.");
                }
            } catch (Exception $e) {
                setFlash('error', 'Error updating hospital: ' . $e->getMessage());
            }
        }
        header('Location: hospitals.php');
        exit;
    } elseif ($action === 'approve') {
        $id = (int)($_POST['hospital_id'] ?? 0);
        if ($id > 0) {
            try {
                $stmtApprove = $pdo->prepare("UPDATE hospitals SET status = 'Active' WHERE hospital_id = ?");
                $stmtApprove->execute([$id]);
                setFlash('success', 'Hospital registration request APPROVED and facility activated successfully.');
            } catch (Exception $e) {
                setFlash('error', 'Error approving hospital: ' . $e->getMessage());
            }
        }
        header('Location: hospitals.php' . (!empty($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''));
        exit;
    } elseif ($action === 'reject') {
        $id = (int)($_POST['hospital_id'] ?? 0);
        if ($id > 0) {
            try {
                $stmtReject = $pdo->prepare("UPDATE hospitals SET status = 'Rejected' WHERE hospital_id = ?");
                $stmtReject->execute([$id]);
                setFlash('info', 'Hospital registration request has been REJECTED.');
            } catch (Exception $e) {
                setFlash('error', 'Error rejecting hospital: ' . $e->getMessage());
            }
        }
        header('Location: hospitals.php' . (!empty($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''));
        exit;
    } elseif ($action === 'delete') {
        $id = (int)($_POST['hospital_id'] ?? 0);
        if ($id > 0) {
            try {
                $stmtDel = $pdo->prepare("DELETE FROM hospitals WHERE hospital_id = ?");
                $stmtDel->execute([$id]);
                setFlash('success', 'Hospital deleted successfully.');
            } catch (Exception $e) {
                setFlash('error', 'Cannot delete hospital with existing booking records. Consider setting status to Inactive instead.');
            }
        }
        header('Location: hospitals.php');
        exit;
    }
}

// Filter Tabs (all, pending, active, inactive)
$filter = $_GET['filter'] ?? 'all';
$whereSql = "";
if ($filter === 'pending') {
    $whereSql = "WHERE h.status = 'Pending'";
} elseif ($filter === 'active') {
    $whereSql = "WHERE h.status = 'Active'";
} elseif ($filter === 'inactive') {
    $whereSql = "WHERE h.status IN ('Inactive', 'Rejected')";
}

// Fetch hospitals and counts
$hospitals = [];
$pendingCount = 0;
$activeCount = 0;
$allCount = 0;

if ($pdo) {
    try {
        $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM hospitals WHERE status = 'Pending'")->fetchColumn();
        $activeCount  = (int)$pdo->query("SELECT COUNT(*) FROM hospitals WHERE status = 'Active'")->fetchColumn();
        $allCount     = (int)$pdo->query("SELECT COUNT(*) FROM hospitals")->fetchColumn();

        $stmt = $pdo->query("SELECT h.*, 
            (SELECT COUNT(*) FROM bookings b WHERE b.hospital_id = h.hospital_id AND b.status = 'Approved') as active_bookings,
            (SELECT COUNT(*) FROM bookings b WHERE b.hospital_id = h.hospital_id AND b.status = 'Completed') as completed_vaccinations
            FROM hospitals h
            $whereSql
            ORDER BY CASE WHEN h.status = 'Pending' THEN 0 ELSE 1 END, h.hospital_name ASC");
        $hospitals = $stmt->fetchAll();
    } catch (Exception $e) {
        setFlash('error', 'Error loading hospitals: ' . $e->getMessage());
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
                    <h3 class="fw-bold mb-1"><i class="fas fa-hospital text-primary me-2"></i> List of Hospitals</h3>
                    <ul class="breadcrumbs mb-0 ps-0 list-unstyled d-flex gap-2 text-muted small">
                        <li><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                        <li>/</li>
                        <li class="active">Hospitals Directory & Management</li>
                    </ul>
                </div>
                <button type="button" class="btn btn-primary btn-round shadow-sm" data-bs-toggle="modal" data-bs-target="#addHospitalModal">
                    <i class="fas fa-plus me-1"></i> Add Hospital
                </button>
            </div>

            <?= displayFlash() ?>

            <!-- Hospitals List Card -->
            <div class="card card-round shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <div class="card-title text-dark">Accredited Child Vaccination Facilities</div>
                    <span class="badge bg-secondary"><?= count($hospitals) ?> Hospitals</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover datatable-custom align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Hospital Name & Username</th>
                                    <th>Location / Area</th>
                                    <th>Contact Info</th>
                                    <th>Active Scheduled</th>
                                    <th>Completed Vaccinations</th>
                                    <th>Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($hospitals)): ?>
                                    <?php foreach ($hospitals as $h): ?>
                                        <tr>
                                            <td class="font-monospace fw-bold text-muted"><?= $h['hospital_id'] ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle me-2 bg-success text-white">
                                                        <i class="fas fa-hospital-alt"></i>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold text-dark"><?= htmlspecialchars($h['hospital_name']) ?></div>
                                                        <small class="text-muted">Username: <code><?= htmlspecialchars($h['username']) ?></code></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($h['location'] ?: 'Central') ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($h['address']) ?></small>
                                            </td>
                                            <td>
                                                <div><i class="fas fa-envelope text-muted me-1"></i> <?= htmlspecialchars($h['email'] ?: 'N/A') ?></div>
                                                <small class="text-muted"><i class="fas fa-phone-alt text-muted me-1"></i> <?= htmlspecialchars($h['phone'] ?: 'N/A') ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info text-white"><?= $h['active_bookings'] ?> scheduled</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?= $h['completed_vaccinations'] ?> completed</span>
                                            </td>
                                            <td>
                                                <?= getStatusBadge($h['status']) ?>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-outline-info me-1" onclick='openEditHospitalModal(<?= json_encode($h) ?>)' title="Update Hospital">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" action="hospitals.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this hospital?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="hospital_id" value="<?= $h['hospital_id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Hospital">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
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

    <!-- Add Hospital Modal -->
    <div class="modal fade" id="addHospitalModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="hospitals.php">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fas fa-hospital me-2"></i> Add Hospital Details</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Hospital Name <span class="text-danger">*</span></label>
                                <input type="text" name="hospital_name" class="form-control" placeholder="e.g. City Children General Hospital" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Location / Area <span class="text-danger">*</span></label>
                                <input type="text" name="location" class="form-control" placeholder="e.g. Downtown Springfield" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" placeholder="info@hospital.org" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Contact Phone</label>
                                <input type="tel" name="phone" class="form-control" placeholder="+1 (555) 100-2000">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Login Username <span class="text-danger">*</span></label>
                                <input type="text" name="username" class="form-control" placeholder="hospital_user" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Initial Password <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control" value="hospital123" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Status</label>
                                <select name="status" class="form-select">
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Full Physical Address</label>
                                <textarea name="address" class="form-control" rows="2" placeholder="Street address, building, suite..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Add Hospital</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Hospital Modal -->
    <div class="modal fade" id="editHospitalModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="hospitals.php">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="hospital_id" id="edit_hosp_id">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Update Hospital Details</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Hospital Name <span class="text-danger">*</span></label>
                                <input type="text" name="hospital_name" id="edit_hosp_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Location / Area <span class="text-danger">*</span></label>
                                <input type="text" name="location" id="edit_hosp_location" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" id="edit_hosp_email" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Contact Phone</label>
                                <input type="tel" name="phone" id="edit_hosp_phone" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Login Username <span class="text-danger">*</span></label>
                                <input type="text" name="username" id="edit_hosp_username" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Reset Password (leave blank to keep)</label>
                                <input type="password" name="password" class="form-control" placeholder="••••••••">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Status</label>
                                <select name="status" id="edit_hosp_status" class="form-select">
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Full Physical Address</label>
                                <textarea name="address" id="edit_hosp_address" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info text-white"><i class="fas fa-save me-1"></i> Update Hospital</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function openEditHospitalModal(h) {
        document.getElementById('edit_hosp_id').value = h.hospital_id;
        document.getElementById('edit_hosp_name').value = h.hospital_name;
        document.getElementById('edit_hosp_location').value = h.location || '';
        document.getElementById('edit_hosp_email').value = h.email || '';
        document.getElementById('edit_hosp_phone').value = h.phone || '';
        document.getElementById('edit_hosp_username').value = h.username;
        document.getElementById('edit_hosp_status').value = h.status;
        document.getElementById('edit_hosp_address').value = h.address || '';

        var modal = new bootstrap.Modal(document.getElementById('editHospitalModal'));
        modal.show();
    }
    </script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
