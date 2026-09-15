<?php
/**
 * Login Portal (Admin, Parent, Hospital)
 * Child Vaccination Management System (VaxCare)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

// If already logged in, redirect to respective dashboard
if (isAdminLoggedIn()) {
    header('Location: ' . BASE_URL . 'admin/index.php');
    exit;
} elseif (isParentLoggedIn()) {
    header('Location: ' . BASE_URL . 'parent/index.php');
    exit;
} elseif (isHospitalLoggedIn()) {
    header('Location: ' . BASE_URL . 'hospital/index.php');
    exit;
}

$error = '';
$dbError = false;
$prefillUsername = trim($_GET['username'] ?? '');

if (!$pdo) {
    $dbError = true;
    $error = 'MySQL Database connection could not be established. Please make sure MySQL is started in XAMPP.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$dbError) {
    $usernameOrEmail = trim($_POST['username'] ?? '');
    $password        = trim($_POST['password'] ?? '');

    if (empty($usernameOrEmail) || empty($password)) {
        $error = 'Please enter both username/email and password.';
    } else {
        $loginRes = loginUser($usernameOrEmail, $password);
        if ($loginRes['success']) {
            if ($loginRes['role'] === 'Admin') {
                header('Location: ' . BASE_URL . 'admin/index.php');
                exit;
            } elseif ($loginRes['role'] === 'Parent') {
                setFlash('success', 'Welcome back, ' . htmlspecialchars($_SESSION['parent_name'] ?? 'Parent') . '! You are logged in to the Parent Portal.');
                header('Location: ' . BASE_URL . 'parent/index.php');
                exit;
            } elseif ($loginRes['role'] === 'Hospital') {
                setFlash('success', 'Welcome back, ' . htmlspecialchars($_SESSION['hospital_name'] ?? 'Hospital') . '! You are logged in to the Hospital Portal.');
                header('Location: ' . BASE_URL . 'hospital/index.php');
                exit;
            }
        } else {
            $error = $loginRes['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | Child Vaccination Management System</title>
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
            padding: 20px;
        }
        .login-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.25);
            max-width: 450px;
            width: 100%;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .login-header {
            background: #1a2035;
            color: #ffffff;
            padding: 35px 30px 25px;
            text-align: center;
        }
        .login-body {
            padding: 32px 30px 28px;
        }
        .brand-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(21, 114, 232, 0.18);
            color: #3b82f6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin: 0 auto 15px;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        .form-control {
            border-radius: 8px;
            padding: 10px 14px;
        }
        .form-control:focus {
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
        .btn-login {
            background: #1572e8;
            border-color: #1572e8;
            padding: 11px;
            font-weight: 700;
            border-radius: 50px;
            transition: all 0.25s ease;
        }
        .btn-login:hover {
            background: #125ec4;
            border-color: #125ec4;
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(21, 114, 232, 0.35);
        }
        .register-callout {
            background: #f8fafc;
            border-radius: 12px;
            border: 1px dashed #cbd5e1;
            padding: 14px;
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-header">
            <div class="brand-icon">
                <i class="fas fa-shield-virus"></i>
            </div>
            <h4 class="fw-bold mb-1">VaxCare Portal</h4>
            <p class="text-white-50 small mb-0">Child Vaccination Management System</p>
        </div>

        <div class="login-body">
            
            <?= displayFlash() ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show small d-flex align-items-center mb-3" role="alert">
                    <i class="fas fa-exclamation-circle me-2 fs-5 flex-shrink-0"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Username or Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                        <input type="text" name="username" class="form-control" placeholder="Enter username or email" value="<?= htmlspecialchars($prefillUsername) ?>" required autofocus autocomplete="username">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                        <input type="password" name="password" id="login_password" class="form-control" placeholder="Enter password" required autocomplete="current-password">
                        <button class="btn btn-outline-secondary btn-toggle-pw" type="button" onclick="togglePw()">
                            <i class="fas fa-eye text-muted" id="pwIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-login w-100 shadow-sm text-white">
                    <i class="fas fa-sign-in-alt me-2"></i> Sign In
                </button>
            </form>

            <!-- Prominent Registration Callout -->
            <div class="register-callout">
                <div class="small text-muted mb-1">Need an account?</div>
                <a href="<?= BASE_URL ?>auth/register.php" class="btn btn-outline-primary btn-sm rounded-pill fw-bold px-3 py-1">
                    <i class="fas fa-user-plus me-1"></i> Open Registration Form
                </a>
            </div>

            <div class="text-center mt-3 pt-2">
                <a href="<?= BASE_URL ?>" class="text-muted small text-decoration-none">
                    <i class="fas fa-arrow-left me-1"></i> Back to Homepage
                </a>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="<?= BASE_URL ?>assets/js/core/bootstrap.min.js"></script>
    <script>
        function togglePw() {
            const pw = document.getElementById('login_password');
            const icon = document.getElementById('pwIcon');
            if (pw.type === 'password') {
                pw.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                pw.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
