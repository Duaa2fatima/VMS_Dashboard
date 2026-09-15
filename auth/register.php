<?php
/**
 * User & Facility Registration Portal
 * Child Vaccination Management System (VaxCare)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

// If already logged in as Admin, offer redirect
if (isAdminLoggedIn()) {
    header('Location: ' . BASE_URL . 'admin/index.php');
    exit;
}

$error   = '';
$success = '';
$accountType = $_POST['account_type'] ?? ($_GET['type'] ?? 'parent');
$fieldErrors = [];

// Preserve form values on error
$formData = [
    // Parent fields
    'parent_name'      => trim($_POST['parent_name'] ?? ''),
    'parent_email'     => trim($_POST['parent_email'] ?? ''),
    'parent_phone'     => trim($_POST['parent_phone'] ?? ''),
    'parent_address'   => trim($_POST['parent_address'] ?? ''),
    'parent_username'  => trim($_POST['parent_username'] ?? ''),
    // Hospital fields
    'hospital_name'    => trim($_POST['hospital_name'] ?? ''),
    'hospital_email'   => trim($_POST['hospital_email'] ?? ''),
    'hospital_phone'   => trim($_POST['hospital_phone'] ?? ''),
    'hospital_loc'     => trim($_POST['hospital_loc'] ?? ''),
    'hospital_address' => trim($_POST['hospital_address'] ?? ''),
    'hospital_username'=> trim($_POST['hospital_username'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$pdo) {
        $error = 'MySQL Database connection could not be established. Please make sure MySQL is running in XAMPP.';
    } else {
        if ($accountType === 'hospital') {
            // Hospital Facility Registration
            $password        = $_POST['hospital_password'] ?? '';
            $confirmPassword = $_POST['hospital_password_confirm'] ?? '';

            // 1. Facility Name Validation
            if (empty($formData['hospital_name'])) {
                $fieldErrors['hospital_name'] = 'Healthcare facility name is required.';
            } elseif (strlen($formData['hospital_name']) < 3) {
                $fieldErrors['hospital_name'] = 'Facility name must be at least 3 characters long.';
            } elseif (strlen($formData['hospital_name']) > 150) {
                $fieldErrors['hospital_name'] = 'Facility name cannot exceed 150 characters.';
            } elseif (!preg_match("/^[a-zA-Z\s]+$/", $formData['hospital_name'])) {
                $fieldErrors['hospital_name'] = 'Hospital name can only contain alphabetic letters and spaces (no numbers, dots, or special characters).';
            }

            // 2. Official Email Validation
            if (empty($formData['hospital_email'])) {
                $fieldErrors['hospital_email'] = 'Official email address is required.';
            } elseif (!filter_var($formData['hospital_email'], FILTER_VALIDATE_EMAIL)) {
                $fieldErrors['hospital_email'] = 'Please provide a valid official email address.';
            } else {
                $stmtChkEmail = $pdo->prepare("SELECT hospital_id FROM hospitals WHERE email = ? LIMIT 1");
                $stmtChkEmail->execute([$formData['hospital_email']]);
                if ($stmtChkEmail->fetch()) {
                    $fieldErrors['hospital_email'] = 'This email address is already registered to a healthcare facility.';
                }
            }

            // 3. Contact Phone Validation (Strictly 11 digits)
            if (empty($formData['hospital_phone'])) {
                $fieldErrors['hospital_phone'] = 'Contact phone number is required and must be exactly 11 digits.';
            } elseif (strlen($formData['hospital_phone']) !== 11 || !preg_match('/^[0-9]{11}$/', $formData['hospital_phone'])) {
                $fieldErrors['hospital_phone'] = 'Phone number must be exactly 11 digits (not more, not less).';
            }

            // 4. Facility Username Validation
            if (empty($formData['hospital_username'])) {
                $fieldErrors['hospital_username'] = 'Facility username is required.';
            } elseif (strlen($formData['hospital_username']) < 3 || strlen($formData['hospital_username']) > 30) {
                $fieldErrors['hospital_username'] = 'Username must be between 3 and 30 characters long.';
            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $formData['hospital_username'])) {
                $fieldErrors['hospital_username'] = 'Username can only contain letters, numbers, and underscores.';
            } else {
                $stmtChkUser = $pdo->prepare("SELECT hospital_id FROM hospitals WHERE username = ? LIMIT 1");
                $stmtChkUser->execute([$formData['hospital_username']]);
                if ($stmtChkUser->fetch()) {
                    $fieldErrors['hospital_username'] = 'This username is already taken. Please choose another.';
                }
            }

            // 5. Password Validation
            if (empty($password)) {
                $fieldErrors['hospital_password'] = 'Password is required.';
            } elseif (strlen($password) < 6) {
                $fieldErrors['hospital_password'] = 'Password must be at least 6 characters long.';
            }

            // 6. Confirm Password Validation
            if (empty($confirmPassword)) {
                $fieldErrors['hospital_password_confirm'] = 'Please confirm your password.';
            } elseif ($password !== $confirmPassword) {
                $fieldErrors['hospital_password_confirm'] = 'Passwords do not match.';
            }

            if (empty($fieldErrors)) {
                $regResult = registerHospital([
                    'hospital_name' => $formData['hospital_name'],
                    'email'         => $formData['hospital_email'],
                    'phone'         => $formData['hospital_phone'],
                    'location'      => $formData['hospital_loc'],
                    'address'       => $formData['hospital_address'],
                    'username'      => $formData['hospital_username'],
                    'password'      => $password,
                ]);

                if ($regResult['success']) {
                    setFlash('info', 'Registration request submitted! Your hospital application has been sent to the Admin Portal. Once an administrator reviews and approves your facility, you will be able to log in.');
                    header('Location: ' . BASE_URL . 'auth/login.php?pending_hospital=1&username=' . urlencode($formData['hospital_username']));
                    exit;
                } else {
                    $error = $regResult['message'];
                }
            } else {
                $error = 'Please correct the highlighted errors below before submitting.';
            }
        } else {
            // Parent / Guardian Registration (Default)
            $password        = $_POST['parent_password'] ?? '';
            $confirmPassword = $_POST['parent_password_confirm'] ?? '';

            // 1. Full Name Validation
            if (empty($formData['parent_name'])) {
                $fieldErrors['parent_name'] = 'Full name is required.';
            } elseif (strlen($formData['parent_name']) < 3) {
                $fieldErrors['parent_name'] = 'Full name must be at least 3 characters long.';
            } elseif (strlen($formData['parent_name']) > 100) {
                $fieldErrors['parent_name'] = 'Full name cannot exceed 100 characters.';
            } elseif (!preg_match("/^[a-zA-Z\s]+$/", $formData['parent_name'])) {
                $fieldErrors['parent_name'] = 'Name can only contain alphabetic letters and spaces (no numbers, dots, or special characters).';
            }

            // 2. Email Validation
            if (empty($formData['parent_email'])) {
                $fieldErrors['parent_email'] = 'Email address is required.';
            } elseif (!filter_var($formData['parent_email'], FILTER_VALIDATE_EMAIL)) {
                $fieldErrors['parent_email'] = 'Please enter a valid email address (e.g. name@example.com).';
            } else {
                $stmtChkEmail = $pdo->prepare("SELECT parent_id FROM parents WHERE email = ? LIMIT 1");
                $stmtChkEmail->execute([$formData['parent_email']]);
                if ($stmtChkEmail->fetch()) {
                    $fieldErrors['parent_email'] = 'An account with this email address is already registered.';
                }
            }

            // 3. Phone Validation (Strictly 11 digits)
            if (empty($formData['parent_phone'])) {
                $fieldErrors['parent_phone'] = 'Phone number is required and must be exactly 11 digits.';
            } elseif (strlen($formData['parent_phone']) !== 11 || !preg_match('/^[0-9]{11}$/', $formData['parent_phone'])) {
                $fieldErrors['parent_phone'] = 'Phone number must be exactly 11 digits (not more, not less).';
            }

            // 4. Username Validation
            if (empty($formData['parent_username'])) {
                $fieldErrors['parent_username'] = 'Username is required.';
            } elseif (strlen($formData['parent_username']) < 3 || strlen($formData['parent_username']) > 30) {
                $fieldErrors['parent_username'] = 'Username must be between 3 and 30 characters long.';
            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $formData['parent_username'])) {
                $fieldErrors['parent_username'] = 'Username can only contain letters, numbers, and underscores.';
            } else {
                $stmtChkUser = $pdo->prepare("SELECT parent_id FROM parents WHERE username = ? LIMIT 1");
                $stmtChkUser->execute([$formData['parent_username']]);
                if ($stmtChkUser->fetch()) {
                    $fieldErrors['parent_username'] = 'This username is already taken. Please choose another.';
                }
            }

            // 5. Password Validation
            if (empty($password)) {
                $fieldErrors['parent_password'] = 'Password is required.';
            } elseif (strlen($password) < 6) {
                $fieldErrors['parent_password'] = 'Password must be at least 6 characters long.';
            }

            // 6. Confirm Password Validation
            if (empty($confirmPassword)) {
                $fieldErrors['parent_password_confirm'] = 'Please confirm your password.';
            } elseif ($password !== $confirmPassword) {
                $fieldErrors['parent_password_confirm'] = 'Passwords do not match.';
            }

            if (empty($fieldErrors)) {
                $parentData = [
                    'name'     => $formData['parent_name'],
                    'email'    => $formData['parent_email'],
                    'phone'    => $formData['parent_phone'],
                    'address'  => $formData['parent_address'],
                    'username' => $formData['parent_username'],
                    'password' => $password,
                ];

                $regResult = registerParent($parentData);

                if ($regResult['success']) {
                    setFlash('success', 'Registration successful! Parent account created. You can now log in.');
                    header('Location: ' . BASE_URL . 'auth/login.php?registered=1&username=' . urlencode($formData['parent_username']));
                    exit;
                } else {
                    $error = $regResult['message'];
                }
            } else {
                $error = 'Please correct the highlighted errors below before submitting.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration | Child Vaccination Management System</title>
    <link rel="icon" href="<?= BASE_URL ?>assets/img/kaiadmin/favicon.ico" type="image/x-icon" />
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: 'Public Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1a2035 0%, #202940 50%, #1572e8 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
        }
        .register-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 20px 45px rgba(0,0,0,0.3);
            max-width: 680px;
            width: 100%;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .register-header {
            background: #1a2035;
            color: #ffffff;
            padding: 32px 30px 24px;
            text-align: center;
            position: relative;
        }
        .brand-icon {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: rgba(21, 114, 232, 0.2);
            color: #3b82f6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin: 0 auto 12px;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        .register-body {
            padding: 32px 36px;
        }
        .role-nav-pills {
            background: #f1f5f9;
            padding: 4px;
            border-radius: 50px;
            display: inline-flex;
            width: 100%;
            margin-bottom: 24px;
        }
        .role-nav-pills .nav-link {
            flex: 1;
            border-radius: 50px;
            padding: 10px 16px;
            font-size: 0.92rem;
            font-weight: 600;
            color: #64748b;
            text-align: center;
            transition: all 0.2s ease;
            border: none;
        }
        .role-nav-pills .nav-link.active {
            background: #1572e8;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(21, 114, 232, 0.3);
        }
        .form-section-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 14px;
            padding-bottom: 6px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.92rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #1572e8;
            box-shadow: 0 0 0 0.2rem rgba(21, 114, 232, 0.15);
        }
        .input-group-text {
            border-radius: 8px 0 0 8px;
            background-color: #f8fafc;
        }
        .btn-toggle-pw {
            cursor: pointer;
            border-radius: 0 8px 8px 0;
            background-color: #f8fafc;
            border-left: none;
        }
        .btn-register-submit {
            background: #1572e8;
            border-color: #1572e8;
            padding: 12px;
            font-weight: 700;
            font-size: 1rem;
            border-radius: 50px;
            transition: all 0.25s ease;
        }
        .btn-register-submit:hover {
            background: #125ec4;
            border-color: #125ec4;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(21, 114, 232, 0.35);
        }
        .badge-step {
            background: rgba(21, 114, 232, 0.12);
            color: #1572e8;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        .input-group.has-validation > .form-control:not(:last-child) {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }
        .input-group.has-validation > .btn-toggle-pw {
            border-top-right-radius: 8px;
            border-bottom-right-radius: 8px;
        }
        .invalid-feedback {
            font-size: 0.8rem;
            margin-top: 5px;
            font-weight: 600;
        }
        .valid-feedback {
            font-size: 0.8rem;
            margin-top: 5px;
            font-weight: 500;
        }
        .form-control.is-invalid, .form-control.is-valid {
            background-position: right calc(0.375em + 0.5rem) center;
        }
        .input-group.has-validation > .form-control.is-invalid,
        .input-group.has-validation > .form-control.is-valid {
            z-index: 2;
        }
        .password-meter {
            height: 5px;
            border-radius: 4px;
            background-color: #e2e8f0;
            margin-top: 8px;
            overflow: hidden;
        }
        .password-meter-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease, background-color 0.3s ease;
        }
        .field-requirement-hint {
            font-size: 0.76rem;
            color: #64748b;
            margin-top: 4px;
        }
    </style>
</head>
<body>

    <div class="register-card">
        <div class="register-header">
            <div class="brand-icon">
                <i class="fas fa-shield-virus"></i>
            </div>
            <h4 class="fw-bold mb-1">VaxCare Registration Portal</h4>
            <p class="text-white-50 small mb-0">Join the Child Vaccination & Immunization Network</p>
        </div>

        <div class="register-body">

            <?= displayFlash() ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show small d-flex align-items-center mb-4" role="alert">
                    <i class="fas fa-exclamation-circle me-2 fs-5 flex-shrink-0"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Role Selector Tabs -->
            <ul class="nav role-nav-pills" id="registerTab" role="tablist">
                <li class="nav-item flex-fill" role="presentation">
                    <button class="nav-link w-100 <?= ($accountType !== 'hospital') ? 'active' : '' ?>" 
                            id="parent-tab" 
                            data-bs-toggle="pill" 
                            data-bs-target="#parent-pane" 
                            type="button" 
                            role="tab" 
                            aria-controls="parent-pane" 
                            aria-selected="<?= ($accountType !== 'hospital') ? 'true' : 'false' ?>">
                        <i class="fas fa-baby me-1"></i> Parent / Guardian
                    </button>
                </li>
                <li class="nav-item flex-fill" role="presentation">
                    <button class="nav-link w-100 <?= ($accountType === 'hospital') ? 'active' : '' ?>" 
                            id="hospital-tab" 
                            data-bs-toggle="pill" 
                            data-bs-target="#hospital-pane" 
                            type="button" 
                            role="tab" 
                            aria-controls="hospital-pane" 
                            aria-selected="<?= ($accountType === 'hospital') ? 'true' : 'false' ?>">
                        <i class="fas fa-hospital me-1"></i> Healthcare Facility
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="registerTabContent">

                <!-- 1. PARENT REGISTRATION FORM -->
                <div class="tab-pane fade <?= ($accountType !== 'hospital') ? 'show active' : '' ?>" id="parent-pane" role="tabpanel" aria-labelledby="parent-tab">
                    <form method="POST" action="register.php" id="parentForm" novalidate>
                        <input type="hidden" name="account_type" value="parent">

                        <div class="form-section-title">
                            <span class="badge-step"><i class="fas fa-user"></i></span> Parent / Guardian Information
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted" for="parent_name">Full Name <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                                    <input type="text" name="parent_name" id="parent_name" class="form-control <?= isset($fieldErrors['parent_name']) ? 'is-invalid' : '' ?>" placeholder="e.g. John Doe" value="<?= htmlspecialchars($formData['parent_name']) ?>" required minlength="3" maxlength="100" pattern="[a-zA-Z\s]+" title="Alphabetic letters and spaces only">
                                    <div class="invalid-feedback" id="parent_name_feedback"><?= $fieldErrors['parent_name'] ?? 'Please enter your full name (alphabetic letters and spaces only).' ?></div>
                                </div>
                                <div class="field-requirement-hint">Alphabetic letters and spaces only (no numbers, dots, or special characters).</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted" for="parent_email">Email Address <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="fas fa-envelope text-muted"></i></span>
                                    <input type="email" name="parent_email" id="parent_email" class="form-control <?= isset($fieldErrors['parent_email']) ? 'is-invalid' : '' ?>" placeholder="name@example.com" value="<?= htmlspecialchars($formData['parent_email']) ?>" required>
                                    <div class="invalid-feedback" id="parent_email_feedback"><?= $fieldErrors['parent_email'] ?? 'Please provide a valid email address (e.g. name@example.com).' ?></div>
                                </div>
                                <div class="field-requirement-hint">Used for appointment confirmations & notifications.</div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted" for="parent_phone">Phone Number <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="fas fa-phone text-muted"></i></span>
                                    <input type="tel" name="parent_phone" id="parent_phone" class="form-control <?= isset($fieldErrors['parent_phone']) ? 'is-invalid' : '' ?>" placeholder="03001234567" value="<?= htmlspecialchars($formData['parent_phone']) ?>" required maxlength="11" minlength="11" pattern="[0-9]{11}" autocomplete="tel">
                                    <div class="invalid-feedback" id="parent_phone_feedback"><?= $fieldErrors['parent_phone'] ?? 'Phone number must be exactly 11 digits (e.g. 03001234567).' ?></div>
                                </div>
                                <div class="field-requirement-hint">Must be exactly 11 digits (no more, no less).</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted" for="parent_username">Choose Username <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="fas fa-at text-muted"></i></span>
                                    <input type="text" name="parent_username" id="parent_username" class="form-control <?= isset($fieldErrors['parent_username']) ? 'is-invalid' : '' ?>" placeholder="username" value="<?= htmlspecialchars($formData['parent_username']) ?>" required minlength="3" maxlength="30" autocomplete="username">
                                    <div class="invalid-feedback" id="parent_username_feedback"><?= $fieldErrors['parent_username'] ?? 'Username must be 3-30 characters (letters, numbers, underscores).' ?></div>
                                </div>
                                <div class="field-requirement-hint">Letters, numbers, and underscores only.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted" for="parent_address">Residential Address</label>
                            <div class="input-group has-validation">
                                <span class="input-group-text"><i class="fas fa-map-marker-alt text-muted"></i></span>
                                <input type="text" name="parent_address" id="parent_address" class="form-control <?= isset($fieldErrors['parent_address']) ? 'is-invalid' : '' ?>" placeholder="Street Address, City, Province" value="<?= htmlspecialchars($formData['parent_address']) ?>" maxlength="255">
                                <div class="invalid-feedback" id="parent_address_feedback"><?= $fieldErrors['parent_address'] ?? 'Address is too long.' ?></div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted" for="parent_password">Password (min 6 chars) <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                                    <input type="password" name="parent_password" id="parent_password" class="form-control <?= isset($fieldErrors['parent_password']) ? 'is-invalid' : '' ?>" placeholder="Create password" required minlength="6" autocomplete="new-password">
                                    <button class="btn btn-outline-secondary btn-toggle-pw" type="button" onclick="togglePasswordVisibility('parent_password', this)">
                                        <i class="fas fa-eye text-muted"></i>
                                    </button>
                                    <div class="invalid-feedback" id="parent_password_feedback"><?= $fieldErrors['parent_password'] ?? 'Password must be at least 6 characters long.' ?></div>
                                </div>
                                <div class="password-meter d-none" id="parent_pw_meter">
                                    <div class="password-meter-bar" id="parent_pw_bar"></div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <small class="text-muted" id="parent_pw_strength_text" style="font-size: 0.75rem;"></small>
                                    <small class="text-muted" style="font-size: 0.72rem;">Min. 6 chars</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted" for="parent_password_confirm">Confirm Password <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="fas fa-shield-alt text-muted"></i></span>
                                    <input type="password" name="parent_password_confirm" id="parent_password_confirm" class="form-control <?= isset($fieldErrors['parent_password_confirm']) ? 'is-invalid' : '' ?>" placeholder="Re-type password" required minlength="6" autocomplete="new-password">
                                    <button class="btn btn-outline-secondary btn-toggle-pw" type="button" onclick="togglePasswordVisibility('parent_password_confirm', this)">
                                        <i class="fas fa-eye text-muted"></i>
                                    </button>
                                    <div class="invalid-feedback" id="parent_password_confirm_feedback"><?= $fieldErrors['parent_password_confirm'] ?? 'Passwords do not match.' ?></div>
                                </div>
                                <div class="field-requirement-hint" id="parent_match_hint">Must match password entered on the left.</div>
                            </div>
                        </div>

                        <button type="submit" id="parent_submit_btn" class="btn btn-primary btn-register-submit w-100 shadow-sm text-white">
                            <i class="fas fa-user-plus me-2"></i> Complete Parent Registration
                        </button>
                    </form>
                </div>

                <!-- 2. HOSPITAL FACILITY REGISTRATION FORM -->
                <div class="tab-pane fade <?= ($accountType === 'hospital') ? 'show active' : '' ?>" id="hospital-pane" role="tabpanel" aria-labelledby="hospital-tab">
                    <form method="POST" action="register.php" id="hospitalForm" novalidate>
                        <input type="hidden" name="account_type" value="hospital">

                        <div class="form-section-title">
                            <span class="badge-step"><i class="fas fa-hospital-alt"></i></span> Healthcare Center Information
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-12">
                                <label class="form-label small fw-bold text-muted" for="hospital_name">Hospital / Clinic Facility Name <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="fas fa-hospital text-muted"></i></span>
                                    <input type="text" name="hospital_name" id="hospital_name" class="form-control <?= isset($fieldErrors['hospital_name']) ? 'is-invalid' : '' ?>" placeholder="e.g. Memorial Pediatric Center" value="<?= htmlspecialchars($formData['hospital_name']) ?>" required minlength="3" maxlength="150" pattern="[a-zA-Z\s]+" title="Alphabetic letters and spaces only">
                                    <div class="invalid-feedback" id="hospital_name_feedback"><?= $fieldErrors['hospital_name'] ?? 'Please enter the facility name (alphabetic letters and spaces only).' ?></div>
                                </div>
                                <div class="field-requirement-hint">Alphabetic letters and spaces only (no numbers, dots, or special characters).</div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted" for="hospital_email">Official Email <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="fas fa-envelope text-muted"></i></span>
                                    <input type="email" name="hospital_email" id="hospital_email" class="form-control <?= isset($fieldErrors['hospital_email']) ? 'is-invalid' : '' ?>" placeholder="info@hospital.org" value="<?= htmlspecialchars($formData['hospital_email']) ?>" required>
                                    <div class="invalid-feedback" id="hospital_email_feedback"><?= $fieldErrors['hospital_email'] ?? 'Please provide a valid official email address.' ?></div>
                                </div>
                                <div class="field-requirement-hint">Facility contact email for notifications and admin review.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted" for="hospital_phone">Contact Phone <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="fas fa-phone text-muted"></i></span>
                                    <input type="tel" name="hospital_phone" id="hospital_phone" class="form-control <?= isset($fieldErrors['hospital_phone']) ? 'is-invalid' : '' ?>" placeholder="03001234567" value="<?= htmlspecialchars($formData['hospital_phone']) ?>" required maxlength="11" minlength="11" pattern="[0-9]{11}" autocomplete="tel">
                                    <div class="invalid-feedback" id="hospital_phone_feedback"><?= $fieldErrors['hospital_phone'] ?? 'Phone number must be exactly 11 digits (e.g. 03001234567).' ?></div>
                                </div>
                                <div class="field-requirement-hint">Must be exactly 11 digits (no more, no less).</div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted" for="hospital_loc">Location / District</label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="fas fa-map-pin text-muted"></i></span>
                                    <input type="text" name="hospital_loc" id="hospital_loc" class="form-control <?= isset($fieldErrors['hospital_loc']) ? 'is-invalid' : '' ?>" placeholder="e.g. Downtown Central" value="<?= htmlspecialchars($formData['hospital_loc']) ?>" maxlength="100">
                                    <div class="invalid-feedback" id="hospital_loc_feedback"><?= $fieldErrors['hospital_loc'] ?? 'Location is too long.' ?></div>
                                </div>
                                <div class="field-requirement-hint">District, town, or primary city zone.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted" for="hospital_username">Facility Username <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="fas fa-at text-muted"></i></span>
                                    <input type="text" name="hospital_username" id="hospital_username" class="form-control <?= isset($fieldErrors['hospital_username']) ? 'is-invalid' : '' ?>" placeholder="hospital_username" value="<?= htmlspecialchars($formData['hospital_username']) ?>" required minlength="3" maxlength="30" autocomplete="username">
                                    <div class="invalid-feedback" id="hospital_username_feedback"><?= $fieldErrors['hospital_username'] ?? 'Username must be 3-30 characters (letters, numbers, underscores).' ?></div>
                                </div>
                                <div class="field-requirement-hint">Used by hospital personnel to log in to the portal.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted" for="hospital_address">Full Physical Address</label>
                            <div class="input-group has-validation">
                                <span class="input-group-text"><i class="fas fa-map-marker-alt text-muted"></i></span>
                                <input type="text" name="hospital_address" id="hospital_address" class="form-control <?= isset($fieldErrors['hospital_address']) ? 'is-invalid' : '' ?>" placeholder="100 Medical Center Blvd, Suite 400" value="<?= htmlspecialchars($formData['hospital_address']) ?>" maxlength="255">
                                <div class="invalid-feedback" id="hospital_address_feedback"><?= $fieldErrors['hospital_address'] ?? 'Address is too long.' ?></div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted" for="hospital_password">Facility Password <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                                    <input type="password" name="hospital_password" id="hospital_password" class="form-control <?= isset($fieldErrors['hospital_password']) ? 'is-invalid' : '' ?>" placeholder="Create password" required minlength="6" autocomplete="new-password">
                                    <button class="btn btn-outline-secondary btn-toggle-pw" type="button" onclick="togglePasswordVisibility('hospital_password', this)">
                                        <i class="fas fa-eye text-muted"></i>
                                    </button>
                                    <div class="invalid-feedback" id="hospital_password_feedback"><?= $fieldErrors['hospital_password'] ?? 'Password must be at least 6 characters long.' ?></div>
                                </div>
                                <div class="password-meter d-none" id="hospital_pw_meter">
                                    <div class="password-meter-bar" id="hospital_pw_bar"></div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <small class="text-muted" id="hospital_pw_strength_text" style="font-size: 0.75rem;"></small>
                                    <small class="text-muted" style="font-size: 0.72rem;">Min. 6 chars</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted" for="hospital_password_confirm">Confirm Password <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="fas fa-shield-alt text-muted"></i></span>
                                    <input type="password" name="hospital_password_confirm" id="hospital_password_confirm" class="form-control <?= isset($fieldErrors['hospital_password_confirm']) ? 'is-invalid' : '' ?>" placeholder="Confirm password" required minlength="6" autocomplete="new-password">
                                    <button class="btn btn-outline-secondary btn-toggle-pw" type="button" onclick="togglePasswordVisibility('hospital_password_confirm', this)">
                                        <i class="fas fa-eye text-muted"></i>
                                    </button>
                                    <div class="invalid-feedback" id="hospital_password_confirm_feedback"><?= $fieldErrors['hospital_password_confirm'] ?? 'Passwords do not match.' ?></div>
                                </div>
                                <div class="field-requirement-hint" id="hospital_match_hint">Must match facility password entered on the left.</div>
                            </div>
                        </div>

                        <button type="submit" id="hospital_submit_btn" class="btn btn-primary btn-register-submit w-100 shadow-sm text-white">
                            <i class="fas fa-plus-circle me-2"></i> Register Healthcare Facility
                        </button>
                    </form>
                </div>

            </div>

            <!-- Footer Links -->
            <div class="text-center mt-4 pt-3 border-top">
                <p class="small text-muted mb-2">
                    Already have an account? 
                    <a href="<?= BASE_URL ?>auth/login.php" class="text-primary fw-bold text-decoration-none">
                        <i class="fas fa-sign-in-alt me-1"></i> Log In Here
                    </a>
                </p>
                <a href="<?= BASE_URL ?>" class="text-muted small text-decoration-none">
                    <i class="fas fa-arrow-left me-1"></i> Back to Homepage
                </a>
            </div>

        </div>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="<?= BASE_URL ?>assets/js/core/bootstrap.min.js"></script>
    <script>
        // Password Visibility Toggle
        function togglePasswordVisibility(fieldId, btn) {
            const field = document.getElementById(fieldId);
            const icon = btn.querySelector('i');
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // --- CLIENT-SIDE VALIDATION MODULE ---

        // Helper to update field state and feedback
        function setFieldStatus(inputEl, feedbackEl, isValid, message) {
            if (!inputEl) return isValid;
            if (isValid) {
                inputEl.classList.remove('is-invalid');
                inputEl.classList.add('is-valid');
                if (feedbackEl) {
                    feedbackEl.innerText = '';
                }
            } else {
                inputEl.classList.remove('is-valid');
                inputEl.classList.add('is-invalid');
                if (feedbackEl) {
                    feedbackEl.innerText = message;
                }
            }
            return isValid;
        }

        // Validate Name (Parent full name or hospital name)
        function validateName(inputEl, feedbackEl, isFacility = false) {
            // Instantly strip any numbers, dots, and special characters (letters and spaces only)
            inputEl.value = inputEl.value.replace(/[^a-zA-Z\s]/g, '');
            const val = inputEl.value.trim();
            if (!val) {
                return setFieldStatus(inputEl, feedbackEl, false, isFacility ? 'Healthcare facility name is required.' : 'Full name is required.');
            }
            if (val.length < 3) {
                return setFieldStatus(inputEl, feedbackEl, false, 'Name must be at least 3 characters long.');
            }
            if (val.length > (isFacility ? 150 : 100)) {
                return setFieldStatus(inputEl, feedbackEl, false, 'Name is too long.');
            }
            const nameRegex = /^[a-zA-Z\s]+$/;
            if (!nameRegex.test(val)) {
                return setFieldStatus(inputEl, feedbackEl, false, (isFacility ? 'Hospital name' : 'Name') + ' can only contain alphabetic letters and spaces (no numbers, dots, or special characters).');
            }
            return setFieldStatus(inputEl, feedbackEl, true, '');
        }

        // Validate Email
        function validateEmail(inputEl, feedbackEl) {
            const val = inputEl.value.trim();
            if (!val) {
                return setFieldStatus(inputEl, feedbackEl, false, 'Email address is required.');
            }
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(val)) {
                return setFieldStatus(inputEl, feedbackEl, false, 'Please enter a valid email address (e.g. name@example.com).');
            }
            return setFieldStatus(inputEl, feedbackEl, true, '');
        }

        // Validate Phone (Must be exactly 11 digits)
        function validatePhone(inputEl, feedbackEl) {
            // Keep only digits and cap strictly at 11 characters
            inputEl.value = inputEl.value.replace(/[^0-9]/g, '').slice(0, 11);
            const val = inputEl.value.trim();

            if (!val) {
                return setFieldStatus(inputEl, feedbackEl, false, 'Phone number is required (must be exactly 11 digits).');
            }
            if (val.length < 11) {
                return setFieldStatus(inputEl, feedbackEl, false, `Phone number must be exactly 11 digits (currently ${val.length} of 11).`);
            }
            if (val.length > 11) {
                return setFieldStatus(inputEl, feedbackEl, false, 'Phone number cannot be more than 11 digits.');
            }
            return setFieldStatus(inputEl, feedbackEl, true, '');
        }

        // Validate Username
        function validateUsername(inputEl, feedbackEl) {
            const val = inputEl.value.trim();
            if (!val) {
                return setFieldStatus(inputEl, feedbackEl, false, 'Username is required.');
            }
            if (val.length < 3 || val.length > 30) {
                return setFieldStatus(inputEl, feedbackEl, false, 'Username must be between 3 and 30 characters long.');
            }
            const usernameRegex = /^[a-zA-Z0-9_]+$/;
            if (!usernameRegex.test(val)) {
                return setFieldStatus(inputEl, feedbackEl, false, 'Username can only contain letters, numbers, and underscores (no spaces).');
            }
            return setFieldStatus(inputEl, feedbackEl, true, '');
        }

        // Validate Password and update strength meter
        function validatePassword(inputEl, feedbackEl, meterEl, barEl, textEl) {
            const val = inputEl.value;
            if (meterEl) meterEl.classList.remove('d-none');

            if (!val) {
                if (barEl) { barEl.style.width = '0%'; barEl.className = 'password-meter-bar'; }
                if (textEl) textEl.innerText = '';
                return setFieldStatus(inputEl, feedbackEl, false, 'Password is required.');
            }
            if (val.length < 6) {
                if (barEl) {
                    barEl.style.width = '25%';
                    barEl.style.backgroundColor = '#dc3545';
                }
                if (textEl) {
                    textEl.innerText = 'Weak (minimum 6 characters required)';
                    textEl.className = 'text-danger fw-bold';
                }
                return setFieldStatus(inputEl, feedbackEl, false, 'Password must be at least 6 characters long.');
            }

            // Calculate password strength
            let score = 0;
            if (val.length >= 8) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[a-z]/.test(val) && /[A-Z]/.test(val)) score++;
            if (/[^a-zA-Z0-9]/.test(val)) score++;

            if (score <= 1) {
                if (barEl) {
                    barEl.style.width = '50%';
                    barEl.style.backgroundColor = '#ffc107';
                }
                if (textEl) {
                    textEl.innerText = 'Fair - Add numbers or mixed case letters';
                    textEl.className = 'text-warning fw-bold';
                }
            } else if (score === 2) {
                if (barEl) {
                    barEl.style.width = '75%';
                    barEl.style.backgroundColor = '#0dcaf0';
                }
                if (textEl) {
                    textEl.innerText = 'Good password';
                    textEl.className = 'text-info fw-bold';
                }
            } else {
                if (barEl) {
                    barEl.style.width = '100%';
                    barEl.style.backgroundColor = '#198754';
                }
                if (textEl) {
                    textEl.innerText = 'Strong password!';
                    textEl.className = 'text-success fw-bold';
                }
            }

            return setFieldStatus(inputEl, feedbackEl, true, '');
        }

        // Validate Confirm Password
        function validateConfirmPassword(confirmInputEl, pwInputEl, feedbackEl, matchHintEl) {
            const pwVal = pwInputEl.value;
            const confirmVal = confirmInputEl.value;

            if (!confirmVal) {
                if (matchHintEl) matchHintEl.style.display = 'block';
                return setFieldStatus(confirmInputEl, feedbackEl, false, 'Please confirm your password.');
            }
            if (confirmVal !== pwVal) {
                if (matchHintEl) matchHintEl.style.display = 'none';
                return setFieldStatus(confirmInputEl, feedbackEl, false, 'Passwords do not match.');
            }
            if (matchHintEl) matchHintEl.style.display = 'none';
            return setFieldStatus(confirmInputEl, feedbackEl, true, '');
        }

        // --- ATTACH REAL-TIME VALIDATION LISTENERS ---

        // 1. Parent Form Fields
        const pName = document.getElementById('parent_name');
        const pNameFb = document.getElementById('parent_name_feedback');
        const pEmail = document.getElementById('parent_email');
        const pEmailFb = document.getElementById('parent_email_feedback');
        const pPhone = document.getElementById('parent_phone');
        const pPhoneFb = document.getElementById('parent_phone_feedback');
        const pUser = document.getElementById('parent_username');
        const pUserFb = document.getElementById('parent_username_feedback');
        const pPw = document.getElementById('parent_password');
        const pPwFb = document.getElementById('parent_password_feedback');
        const pPwMeter = document.getElementById('parent_pw_meter');
        const pPwBar = document.getElementById('parent_pw_bar');
        const pPwTxt = document.getElementById('parent_pw_strength_text');
        const pPwc = document.getElementById('parent_password_confirm');
        const pPwcFb = document.getElementById('parent_password_confirm_feedback');
        const pMatchHint = document.getElementById('parent_match_hint');
        const parentForm = document.getElementById('parentForm');
        const parentSubmitBtn = document.getElementById('parent_submit_btn');

        if (pName) {
            pName.addEventListener('keydown', function(e) {
                // Allow control keys (Backspace, Tab, Enter, Arrows, Ctrl+A/C/V/X, etc.)
                if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
                    if (!/^[a-zA-Z\s]$/.test(e.key)) {
                        e.preventDefault();
                    }
                }
            });
            pName.addEventListener('input', () => validateName(pName, pNameFb, false));
            pName.addEventListener('blur', () => validateName(pName, pNameFb, false));
        }
        if (pEmail) {
            pEmail.addEventListener('input', () => validateEmail(pEmail, pEmailFb));
            pEmail.addEventListener('blur', () => validateEmail(pEmail, pEmailFb));
        }
        if (pPhone) {
            pPhone.addEventListener('input', () => validatePhone(pPhone, pPhoneFb));
            pPhone.addEventListener('blur', () => validatePhone(pPhone, pPhoneFb));
        }
        if (pUser) {
            pUser.addEventListener('input', () => validateUsername(pUser, pUserFb));
            pUser.addEventListener('blur', () => validateUsername(pUser, pUserFb));
        }
        if (pPw) {
            pPw.addEventListener('input', () => {
                validatePassword(pPw, pPwFb, pPwMeter, pPwBar, pPwTxt);
                if (pPwc.value) validateConfirmPassword(pPwc, pPw, pPwcFb, pMatchHint);
            });
            pPw.addEventListener('blur', () => validatePassword(pPw, pPwFb, pPwMeter, pPwBar, pPwTxt));
        }
        if (pPwc) {
            pPwc.addEventListener('input', () => validateConfirmPassword(pPwc, pPw, pPwcFb, pMatchHint));
            pPwc.addEventListener('blur', () => validateConfirmPassword(pPwc, pPw, pPwcFb, pMatchHint));
        }

        if (parentForm) {
            parentForm.addEventListener('submit', function(e) {
                const v1 = validateName(pName, pNameFb, false);
                const v2 = validateEmail(pEmail, pEmailFb);
                const v3 = validatePhone(pPhone, pPhoneFb);
                const v4 = validateUsername(pUser, pUserFb);
                const v5 = validatePassword(pPw, pPwFb, pPwMeter, pPwBar, pPwTxt);
                const v6 = validateConfirmPassword(pPwc, pPw, pPwcFb, pMatchHint);

                if (!v1 || !v2 || !v3 || !v4 || !v5 || !v6) {
                    e.preventDefault();
                    // Focus on the first invalid field
                    const firstInvalid = parentForm.querySelector('.is-invalid');
                    if (firstInvalid) {
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstInvalid.focus();
                    }
                    return false;
                }

                // Show spinner on submit button
                if (parentSubmitBtn) {
                    parentSubmitBtn.disabled = true;
                    parentSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Creating Account...';
                }
            });
        }

        // 2. Hospital Form Fields
        const hName = document.getElementById('hospital_name');
        const hNameFb = document.getElementById('hospital_name_feedback');
        const hEmail = document.getElementById('hospital_email');
        const hEmailFb = document.getElementById('hospital_email_feedback');
        const hPhone = document.getElementById('hospital_phone');
        const hPhoneFb = document.getElementById('hospital_phone_feedback');
        const hUser = document.getElementById('hospital_username');
        const hUserFb = document.getElementById('hospital_username_feedback');
        const hPw = document.getElementById('hospital_password');
        const hPwFb = document.getElementById('hospital_password_feedback');
        const hPwMeter = document.getElementById('hospital_pw_meter');
        const hPwBar = document.getElementById('hospital_pw_bar');
        const hPwTxt = document.getElementById('hospital_pw_strength_text');
        const hPwc = document.getElementById('hospital_password_confirm');
        const hPwcFb = document.getElementById('hospital_password_confirm_feedback');
        const hMatchHint = document.getElementById('hospital_match_hint');
        const hospitalForm = document.getElementById('hospitalForm');
        const hospitalSubmitBtn = document.getElementById('hospital_submit_btn');

        if (hName) {
            hName.addEventListener('keydown', function(e) {
                // Allow control keys (Backspace, Tab, Enter, Arrows, Ctrl+A/C/V/X, etc.)
                if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
                    if (!/^[a-zA-Z\s]$/.test(e.key)) {
                        e.preventDefault();
                    }
                }
            });
            hName.addEventListener('input', () => validateName(hName, hNameFb, true));
            hName.addEventListener('blur', () => validateName(hName, hNameFb, true));
        }
        if (hEmail) {
            hEmail.addEventListener('input', () => validateEmail(hEmail, hEmailFb));
            hEmail.addEventListener('blur', () => validateEmail(hEmail, hEmailFb));
        }
        if (hPhone) {
            hPhone.addEventListener('input', () => validatePhone(hPhone, hPhoneFb));
            hPhone.addEventListener('blur', () => validatePhone(hPhone, hPhoneFb));
        }
        if (hUser) {
            hUser.addEventListener('input', () => validateUsername(hUser, hUserFb));
            hUser.addEventListener('blur', () => validateUsername(hUser, hUserFb));
        }
        if (hPw) {
            hPw.addEventListener('input', () => {
                validatePassword(hPw, hPwFb, hPwMeter, hPwBar, hPwTxt);
                if (hPwc.value) validateConfirmPassword(hPwc, hPw, hPwcFb, hMatchHint);
            });
            hPw.addEventListener('blur', () => validatePassword(hPw, hPwFb, hPwMeter, hPwBar, hPwTxt));
        }
        if (hPwc) {
            hPwc.addEventListener('input', () => validateConfirmPassword(hPwc, hPw, hPwcFb, hMatchHint));
            hPwc.addEventListener('blur', () => validateConfirmPassword(hPwc, hPw, hPwcFb, hMatchHint));
        }

        if (hospitalForm) {
            hospitalForm.addEventListener('submit', function(e) {
                const v1 = validateName(hName, hNameFb, true);
                const v2 = validateEmail(hEmail, hEmailFb);
                const v3 = validatePhone(hPhone, hPhoneFb);
                const v4 = validateUsername(hUser, hUserFb);
                const v5 = validatePassword(hPw, hPwFb, hPwMeter, hPwBar, hPwTxt);
                const v6 = validateConfirmPassword(hPwc, hPw, hPwcFb, hMatchHint);

                if (!v1 || !v2 || !v3 || !v4 || !v5 || !v6) {
                    e.preventDefault();
                    // Focus on the first invalid field
                    const firstInvalid = hospitalForm.querySelector('.is-invalid');
                    if (firstInvalid) {
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstInvalid.focus();
                    }
                    return false;
                }

                // Show spinner on submit button
                if (hospitalSubmitBtn) {
                    hospitalSubmitBtn.disabled = true;
                    hospitalSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Submitting Application...';
                }
            });
        }
    </script>
</body>
</html>
