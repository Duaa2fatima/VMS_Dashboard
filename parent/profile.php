<?php
/**
 * Parent Profile & Account Settings
 * Child Vaccination Management System (VMS)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
requireParent();

$currentParent = getCurrentParent();
$parentId = $currentParent['id'];

// Handle Profile Updates BEFORE sending any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name    = trim($_POST['name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (empty($name) || empty($email)) {
            setFlash('error', 'Name and email are required fields.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Please enter a valid email address.');
        } else {
            try {
                // Check if email already used by another parent
                $stmtChk = $pdo->prepare("SELECT parent_id FROM parents WHERE email = ? AND parent_id != ?");
                $stmtChk->execute([$email, $parentId]);
                if ($stmtChk->fetch()) {
                    setFlash('error', 'This email is already registered to another account.');
                } else {
                    $stmtUp = $pdo->prepare("UPDATE parents SET name = ?, email = ?, phone = ?, address = ? WHERE parent_id = ?");
                    $stmtUp->execute([$name, $email, $phone, $address, $parentId]);
                    $_SESSION['parent_name'] = $name;
                    $_SESSION['parent_email'] = $email;
                    setFlash('success', 'Your profile details have been successfully updated.');
                    header('Location: profile.php');
                    exit;
                }
            } catch (Exception $e) {
                setFlash('error', 'Error updating profile: ' . $e->getMessage());
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
                $stmt = $pdo->prepare("SELECT password FROM parents WHERE parent_id = ?");
                $stmt->execute([$parentId]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($currentPw, $user['password'])) {
                    setFlash('error', 'Incorrect current password entered.');
                } else {
                    $hashed = password_hash($newPw, PASSWORD_BCRYPT);
                    $stmtUp = $pdo->prepare("UPDATE parents SET password = ? WHERE parent_id = ?");
                    $stmtUp->execute([$hashed, $parentId]);
                    setFlash('success', 'Your password has been changed successfully.');
                    header('Location: profile.php');
                    exit;
                }
            } catch (Exception $e) {
                setFlash('error', 'Error changing password: ' . $e->getMessage());
            }
        }
    }
}

// Refresh parent data
$parentData = getCurrentParent();

$pageTitle = 'Parent Profile';
$activePage = 'parent_profile';
require_once __DIR__ . '/includes/header.php';
?>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-panel">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>
    <div class="container">
        <div class="page-inner">

            <!-- Page Header -->
            <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
                <div>
                    <h3 class="fw-bold mb-1">Parent Account Settings</h3>
                    <h6 class="op-7 mb-0 text-muted">Update your contact information, emergency phone, residential address, and security settings</h6>
                </div>
            </div>

            <?= displayFlash() ?>

            <div class="row">
                <!-- Profile Information Form -->
                <div class="col-lg-7 mb-4">
                    <div class="card card-round shadow-sm">
                        <div class="card-header bg-light">
                            <h5 class="card-title fw-bold mb-0">
                                <i class="fas fa-user-edit text-primary me-2"></i> Personal & Contact Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="profile.php">
                                <input type="hidden" name="action" value="update_profile">

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label small fw-bold">Full Name <span class="text-danger">*</span></label>
                                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($parentData['name'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label small fw-bold">Account Username</label>
                                        <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($parentData['username'] ?? '') ?>" disabled>
                                        <div class="form-text small">Username cannot be changed.</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label small fw-bold">Email Address <span class="text-danger">*</span></label>
                                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($parentData['email'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label small fw-bold">Contact Phone Number</label>
                                        <input type="text" name="phone" class="form-control" placeholder="+1 (555) 000-0000" value="<?= htmlspecialchars($parentData['phone'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label small fw-bold">Home / Residential Address</label>
                                    <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($parentData['address'] ?? '') ?></textarea>
                                    <div class="form-text small">Used for clinic directions and official child immunization records.</div>
                                </div>

                                <button type="submit" class="btn btn-primary btn-round px-4">
                                    <i class="fas fa-save me-1"></i> Save Changes
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
                                <i class="fas fa-lock text-warning me-2"></i> Change Password
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
                                    <i class="fas fa-key me-1"></i> Update Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
