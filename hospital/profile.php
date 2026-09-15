<?php
/**
 * Hospital Profile & Facility Details
 * Child Vaccination Management System (VMS)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
requireHospital();

$currentHospital = getCurrentHospital();
$hospitalId = $currentHospital['id'];

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $hospitalName = trim($_POST['hospital_name'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $phone        = trim($_POST['phone'] ?? '');
        $location     = trim($_POST['location'] ?? '');
        $address      = trim($_POST['address'] ?? '');

        if (empty($hospitalName) || empty($email)) {
            setFlash('error', 'Hospital name and official email are required fields.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Please provide a valid official email address.');
        } else {
            try {
                // Check if email taken by another hospital
                $stmtChk = $pdo->prepare("SELECT hospital_id FROM hospitals WHERE email = ? AND hospital_id != ?");
                $stmtChk->execute([$email, $hospitalId]);
                if ($stmtChk->fetch()) {
                    setFlash('error', 'This official email address is already in use by another facility.');
                } else {
                    $stmtUp = $pdo->prepare("
                        UPDATE hospitals 
                        SET hospital_name = ?, email = ?, phone = ?, location = ?, address = ? 
                        WHERE hospital_id = ?
                    ");
                    $stmtUp->execute([$hospitalName, $email, $phone, $location, $address, $hospitalId]);

                    $_SESSION['hospital_name'] = $hospitalName;
                    $_SESSION['hospital_email'] = $email;

                    setFlash('success', 'Facility details have been updated successfully.');
                    header('Location: profile.php');
                    exit;
                }
            } catch (Exception $e) {
                setFlash('error', 'Error updating facility profile: ' . $e->getMessage());
            }
        }

    } elseif ($action === 'change_password') {
        $currentPw = $_POST['current_password'] ?? '';
        $newPw     = $_POST['new_password'] ?? '';
        $confirmPw = $_POST['confirm_password'] ?? '';

        if (empty($currentPw) || empty($newPw) || empty($confirmPw)) {
            setFlash('error', 'Please fill in all password fields.');
        } elseif ($newPw !== $confirmPw) {
            setFlash('error', 'New password and confirmation do not match.');
        } elseif (strlen($newPw) < 6) {
            setFlash('error', 'New password must be at least 6 characters long.');
        } else {
            try {
                $stmt = $pdo->prepare("SELECT password FROM hospitals WHERE hospital_id = ?");
                $stmt->execute([$hospitalId]);
                $hosp = $stmt->fetch();

                if (!$hosp || !password_verify($currentPw, $hosp['password'])) {
                    setFlash('error', 'Incorrect current password entered.');
                } else {
                    $hashed = password_hash($newPw, PASSWORD_BCRYPT);
                    $stmtUp = $pdo->prepare("UPDATE hospitals SET password = ? WHERE hospital_id = ?");
                    $stmtUp->execute([$hashed, $hospitalId]);
                    setFlash('success', 'Facility login password updated successfully.');
                    header('Location: profile.php');
                    exit;
                }
            } catch (Exception $e) {
                setFlash('error', 'Error changing password: ' . $e->getMessage());
            }
        }
    }
}

$pageTitle = 'Facility Profile';
$activePage = 'hospital_profile';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-panel">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>
    <div class="container">
        <div class="page-inner">

            <!-- Page Header -->
            <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
                <div>
                    <h3 class="fw-bold mb-1">Healthcare Facility Profile</h3>
                    <h6 class="op-7 mb-0 text-muted">Update hospital name, location details, facility address, contact numbers, and credentials</h6>
                </div>
            </div>

            <?= displayFlash() ?>

            <div class="row">
                <!-- Facility Information Form -->
                <div class="col-lg-7 mb-4">
                    <div class="card card-round shadow-sm">
                        <div class="card-header bg-light">
                            <h5 class="card-title fw-bold mb-0">
                                <i class="fas fa-clinic-medical text-primary me-2"></i> Hospital Center Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="profile.php">
                                <input type="hidden" name="action" value="update_profile">

                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Hospital Facility Name <span class="text-danger">*</span></label>
                                    <input type="text" name="hospital_name" class="form-control" value="<?= htmlspecialchars($hospData['hospital_name'] ?? '') ?>" required>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label small fw-bold">Facility Username</label>
                                        <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($hospData['username'] ?? '') ?>" disabled>
                                        <div class="form-text small">Facility username cannot be changed.</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label small fw-bold">Accreditation Status</label>
                                        <div>
                                            <span class="badge bg-success rounded-pill px-3 py-2">
                                                <i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($hospData['status'] ?? 'Active') ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label small fw-bold">Official Email <span class="text-danger">*</span></label>
                                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($hospData['email'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label small fw-bold">Contact Phone Number</label>
                                        <input type="text" name="phone" class="form-control" placeholder="+1 (555) 000-0000" value="<?= htmlspecialchars($hospData['phone'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Location / District</label>
                                    <input type="text" name="location" class="form-control" placeholder="e.g. Downtown Springfield, North District" value="<?= htmlspecialchars($hospData['location'] ?? '') ?>">
                                    <div class="form-text small">This is used by parents when filtering hospitals by area.</div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label small fw-bold">Physical Facility Address</label>
                                    <textarea name="address" class="form-control" rows="3" placeholder="Full street address, suite/wing number"><?= htmlspecialchars($hospData['address'] ?? '') ?></textarea>
                                </div>

                                <button type="submit" class="btn btn-primary btn-round px-4">
                                    <i class="fas fa-save me-1"></i> Save Facility Details
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Password Change Form -->
                <div class="col-lg-5 mb-4">
                    <div class="card card-round shadow-sm">
                        <div class="card-header bg-light">
                            <h5 class="card-title fw-bold mb-0">
                                <i class="fas fa-lock text-warning me-2"></i> Update Security Password
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="profile.php">
                                <input type="hidden" name="action" value="change_password">

                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Current Password <span class="text-danger">*</span></label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold">New Password <span class="text-danger">*</span></label>
                                    <input type="password" name="new_password" class="form-control" minlength="6" required>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label small fw-bold">Confirm New Password <span class="text-danger">*</span></label>
                                    <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                                </div>

                                <button type="submit" class="btn btn-warning text-dark btn-round px-4 fw-bold">
                                    <i class="fas fa-key me-1"></i> Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
