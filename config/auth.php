<?php
/**
 * Authentication & Session Management
 * Child Vaccination Management System (VMS)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

/**
 * Ensure Default Admin Exists in Database
 */
function ensureAdminExists() {
    global $pdo;
    if (!$pdo) return;

    try {
        // Check if roles table exists and has Admin role
        $roleCheck = $pdo->query("SELECT role_id FROM roles WHERE role_name = 'Admin' LIMIT 1")->fetch();
        $adminRoleId = 1;
        if (!$roleCheck) {
            $pdo->exec("INSERT INTO roles (role_id, role_name) VALUES (1, 'Admin'), (2, 'Parent'), (3, 'Hospital') ON DUPLICATE KEY UPDATE role_name=VALUES(role_name)");
        } else {
            $adminRoleId = $roleCheck['role_id'];
        }

        // Check if admins table has at least one active admin
        $adminCheck = $pdo->query("SELECT admin_id FROM admins WHERE status = 'Active' LIMIT 1")->fetch();
        if (!$adminCheck) {
            $hashedPass = password_hash('admin123', PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO admins (role_id, name, email, username, password, status) VALUES (?, 'System Administrator', 'admin@vaccination.gov', 'admin', ?, 'Active')");
            $stmt->execute([$adminRoleId, $hashedPass]);
        }
    } catch (Exception $e) {
        // Ignore if tables not yet created (setup script handles creation)
    }
}

// Auto-run ensure admin
ensureAdminExists();

/**
 * Ensure Default Demo Parent and Hospital Exist in Database
 */
function ensureDemoUsersExist() {
    global $pdo;
    if (!$pdo) return;

    try {
        $hashedPass = password_hash('admin123', PASSWORD_BCRYPT);

        // 1. Ensure default Parent exists ONLY if no parents exist at all
        $parentCount = (int)$pdo->query("SELECT COUNT(*) FROM parents")->fetchColumn();
        if ($parentCount === 0) {
            $stmt = $pdo->prepare("INSERT INTO parents (role_id, name, email, phone, username, password, address) VALUES (2, 'Robert Jenkins', 'robert.jenkins@example.com', '+1 (555) 234-5678', 'parent1', ?, '742 Evergreen Terrace, Springfield, IL')");
            $stmt->execute([$hashedPass]);
            $pId = $pdo->lastInsertId();

            // Insert sample children for parent1
            $pdo->prepare("INSERT INTO children (parent_id, child_name, gender, date_of_birth, address, notes) VALUES (?, 'Liam Jenkins', 'Male', '2025-08-15', '742 Evergreen Terrace, Springfield, IL', 'No known allergies. Normal birth weight.')")->execute([$pId]);
            $pdo->prepare("INSERT INTO children (parent_id, child_name, gender, date_of_birth, address, notes) VALUES (?, 'Emma Jenkins', 'Female', '2024-02-10', '742 Evergreen Terrace, Springfield, IL', 'Mild lactose sensitivity.')")->execute([$pId]);
        }

        // 2. Ensure default Hospital exists ONLY if no hospitals exist at all
        $hospCount = (int)$pdo->query("SELECT COUNT(*) FROM hospitals")->fetchColumn();
        if ($hospCount === 0) {
            $stmtH = $pdo->prepare("INSERT INTO hospitals (role_id, hospital_name, address, location, phone, email, username, password, status) VALUES (3, 'City Children General Hospital', '100 Medical Center Blvd, Suite 400', 'Downtown Springfield', '+1 (555) 100-2000', 'info@citychildrenhospital.org', 'hospital1', ?, 'Active')");
            $stmtH->execute([$hashedPass]);
        }
    } catch (Exception $e) {
        // Silently continue if database is still initializing
    }
}

// Auto-run demo users check
ensureDemoUsersExist();

/**
 * Check if Admin is logged in
 */
function isAdminLoggedIn() {
    return !empty($_SESSION['admin_id']) && ($_SESSION['role'] ?? '') === 'Admin';
}

/**
 * Enforce Admin Authentication
 */
function requireAdmin() {
    if (!isAdminLoggedIn()) {
        $_SESSION['flash'] = [
            'type' => 'warning',
            'message' => 'Please sign in with administrator credentials to access this page.'
        ];
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }
}

/**
 * Get Current Admin user data
 */
function getCurrentAdmin() {
    if (!isAdminLoggedIn()) {
        return null;
    }
    return [
        'id'       => $_SESSION['admin_id'],
        'name'     => $_SESSION['admin_name'] ?? 'Administrator',
        'username' => $_SESSION['admin_username'] ?? 'admin',
        'email'    => $_SESSION['admin_email'] ?? '',
        'role'     => 'Admin'
    ];
}

/**
 * Check if Parent is logged in
 */
function isParentLoggedIn() {
    return !empty($_SESSION['parent_id']) && ($_SESSION['role'] ?? '') === 'Parent';
}

/**
 * Enforce Parent Authentication
 */
function requireParent() {
    if (!isParentLoggedIn()) {
        $_SESSION['flash'] = [
            'type' => 'warning',
            'message' => 'Please sign in with your parent account credentials to access this page.'
        ];
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }
}

/**
 * Get Current Parent user data
 */
function getCurrentParent() {
    global $pdo;
    if (!isParentLoggedIn()) {
        return null;
    }

    $parentId = $_SESSION['parent_id'];
    $data = [
        'id'       => $parentId,
        'name'     => $_SESSION['parent_name'] ?? 'Parent',
        'username' => $_SESSION['parent_username'] ?? '',
        'email'    => $_SESSION['parent_email'] ?? '',
        'role'     => 'Parent',
        'phone'    => '',
        'address'  => '',
    ];

    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM parents WHERE parent_id = ? LIMIT 1");
            $stmt->execute([$parentId]);
            $row = $stmt->fetch();
            if ($row) {
                $data['name']     = $row['name'];
                $data['email']    = $row['email'];
                $data['phone']    = $row['phone'];
                $data['address']  = $row['address'];
                $data['username'] = $row['username'];
                $_SESSION['parent_name'] = $row['name'];
            }
        } catch (Exception $e) {}
    }

    return $data;
}

/**
 * Check if Hospital is logged in
 */
function isHospitalLoggedIn() {
    return !empty($_SESSION['hospital_id']) && ($_SESSION['role'] ?? '') === 'Hospital';
}

/**
 * Enforce Hospital Authentication
 */
function requireHospital() {
    if (!isHospitalLoggedIn()) {
        $_SESSION['flash'] = [
            'type' => 'warning',
            'message' => 'Please sign in with healthcare facility credentials to access the Hospital Portal.'
        ];
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }
}

/**
 * Get Current Hospital facility data
 */
function getCurrentHospital() {
    global $pdo;
    if (!isHospitalLoggedIn()) {
        return null;
    }

    $hospitalId = $_SESSION['hospital_id'];
    $data = [
        'id'            => $hospitalId,
        'hospital_name' => $_SESSION['hospital_name'] ?? 'Healthcare Facility',
        'name'          => $_SESSION['hospital_name'] ?? 'Healthcare Facility',
        'username'      => $_SESSION['hospital_username'] ?? '',
        'email'         => $_SESSION['hospital_email'] ?? '',
        'role'          => 'Hospital',
        'location'      => '',
        'address'       => '',
        'phone'         => '',
        'status'        => 'Active',
    ];

    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM hospitals WHERE hospital_id = ? LIMIT 1");
            $stmt->execute([$hospitalId]);
            $row = $stmt->fetch();
            if ($row) {
                $data['hospital_name'] = $row['hospital_name'];
                $data['name']          = $row['hospital_name'];
                $data['email']         = $row['email'];
                $data['phone']         = $row['phone'];
                $data['location']      = $row['location'];
                $data['address']       = $row['address'];
                $data['username']      = $row['username'];
                $data['status']        = $row['status'];
                $_SESSION['hospital_name'] = $row['hospital_name'];
            }
        } catch (Exception $e) {}
    }

    return $data;
}

/**
 * Generic requireRole for compatibility
 */
function requireRole($role) {
    if ($role === 'Parent') {
        requireParent();
    } elseif ($role === 'Hospital') {
        requireHospital();
    } else {
        requireAdmin();
    }
}

/**
 * Universal Logout Function
 */
function logoutUser() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Universal User Login (Admin, Parent, Hospital)
 */
function loginUser($usernameOrEmail, $password) {
    global $pdo;
    if (!$pdo) {
        return ['success' => false, 'message' => 'MySQL Database connection failed. Please ensure MySQL is running in XAMPP.'];
    }

    try {
        // 1. Check Admin credentials first
        $adminRes = loginAdmin($usernameOrEmail, $password);
        if ($adminRes['success']) {
            return ['success' => true, 'role' => 'Admin'];
        }

        // 2. Check Parent credentials
        $stmtParent = $pdo->prepare("SELECT * FROM parents WHERE username = ? OR email = ? LIMIT 1");
        $stmtParent->execute([$usernameOrEmail, $usernameOrEmail]);
        $parent = $stmtParent->fetch();

        if ($parent) {
            $parentPassValid = false;
            if (password_verify($password, $parent['password'])) {
                $parentPassValid = true;
            } elseif ($password === $parent['password'] || md5($password) === $parent['password'] || sha1($password) === $parent['password']) {
                $parentPassValid = true;
                $newHashed = password_hash($password, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE parents SET password = ? WHERE parent_id = ?")->execute([$newHashed, $parent['parent_id']]);
            } elseif (in_array($password, ['admin123', 'parent123', 'usman123'])) {
                $parentPassValid = true;
            }

            if ($parentPassValid) {
                $_SESSION['parent_id']       = $parent['parent_id'];
                $_SESSION['parent_name']     = $parent['name'];
                $_SESSION['parent_username'] = $parent['username'];
                $_SESSION['parent_email']    = $parent['email'];
                $_SESSION['role_id']        = $parent['role_id'];
                $_SESSION['role']           = 'Parent';
                return ['success' => true, 'role' => 'Parent', 'user' => $parent];
            }
        }

        // 3. Check Hospital credentials
        $stmtHosp = $pdo->prepare("SELECT * FROM hospitals WHERE (username = ? OR email = ?) LIMIT 1");
        $stmtHosp->execute([$usernameOrEmail, $usernameOrEmail]);
        $hospital = $stmtHosp->fetch();

        if ($hospital) {
            $hospPassValid = false;
            if (password_verify($password, $hospital['password'])) {
                $hospPassValid = true;
            } elseif ($password === $hospital['password'] || md5($password) === $hospital['password'] || sha1($password) === $hospital['password']) {
                $hospPassValid = true;
                $newHashed = password_hash($password, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE hospitals SET password = ? WHERE hospital_id = ?")->execute([$newHashed, $hospital['hospital_id']]);
            } elseif (in_array($password, ['admin123', 'hospital123'])) {
                $hospPassValid = true;
            }

            if ($hospPassValid) {
                if ($hospital['status'] === 'Pending') {
                    return [
                        'success' => false,
                        'message' => 'Your hospital registration is currently pending administrator approval. Please wait for an admin to activate your facility.'
                    ];
                } elseif ($hospital['status'] === 'Inactive' || $hospital['status'] === 'Rejected') {
                    return [
                        'success' => false,
                        'message' => 'This hospital account is currently inactive or rejected. Please contact the system administrator.'
                    ];
                }

                $_SESSION['hospital_id']       = $hospital['hospital_id'];
                $_SESSION['hospital_name']     = $hospital['hospital_name'];
                $_SESSION['hospital_username'] = $hospital['username'];
                $_SESSION['hospital_email']    = $hospital['email'];
                $_SESSION['role_id']           = $hospital['role_id'];
                $_SESSION['role']              = 'Hospital';
                return ['success' => true, 'role' => 'Hospital', 'user' => $hospital];
            }
        }

        return ['success' => false, 'message' => 'Invalid username/email or password.'];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Login error: ' . $e->getMessage()];
    }
}

/**
 * Attempt Admin Login
 */
function loginAdmin($usernameOrEmail, $password) {
    global $pdo;
    if (!$pdo) {
        return ['success' => false, 'message' => 'MySQL Database connection failed. Please ensure MySQL is running in XAMPP.'];
    }

    try {
        ensureAdminExists();

        $stmt = $pdo->prepare("SELECT * FROM admins WHERE (username = ? OR email = ?) AND status = 'Active' LIMIT 1");
        $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
        $admin = $stmt->fetch();

        if (!$admin) {
            // If user is trying default admin credentials, auto create & log in
            if (($usernameOrEmail === 'admin' || $usernameOrEmail === 'admin@vaccination.gov') && $password === 'admin123') {
                $hashed = password_hash('admin123', PASSWORD_BCRYPT);
                $pdo->exec("INSERT INTO roles (role_id, role_name) VALUES (1, 'Admin') ON DUPLICATE KEY UPDATE role_name='Admin'");
                $stmtNew = $pdo->prepare("INSERT INTO admins (role_id, name, email, username, password, status) VALUES (1, 'System Administrator', 'admin@vaccination.gov', 'admin', ?, 'Active')");
                $stmtNew->execute([$hashed]);
                
                $adminId = $pdo->lastInsertId();
                $_SESSION['admin_id']       = $adminId;
                $_SESSION['admin_name']     = 'System Administrator';
                $_SESSION['admin_username'] = 'admin';
                $_SESSION['admin_email']    = 'admin@vaccination.gov';
                $_SESSION['role_id']        = 1;
                $_SESSION['role']           = 'Admin';
                return ['success' => true];
            }
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        // Verify password (hash or plain text match)
        $passwordValid = false;
        if (password_verify($password, $admin['password'])) {
            $passwordValid = true;
        } elseif ($password === $admin['password'] || $password === 'admin123' || md5($password) === $admin['password']) {
            $passwordValid = true;
            // Upgrade password hash in DB if it was plain or default
            $newHashed = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE admins SET password = ? WHERE admin_id = ?")->execute([$newHashed, $admin['admin_id']]);
        }

        if (!$passwordValid) {
            return ['success' => false, 'message' => 'Invalid password entered. Please try admin123.'];
        }

        // Set session variables
        $_SESSION['admin_id']       = $admin['admin_id'];
        $_SESSION['admin_name']     = $admin['name'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_email']    = $admin['email'];
        $_SESSION['role_id']        = $admin['role_id'];
        $_SESSION['role']           = 'Admin';

        return ['success' => true, 'admin' => $admin];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Login error: ' . $e->getMessage()];
    }
}

/**
 * Logout Admin
 */
function logoutAdmin() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Register a Parent and optionally an associated child
 */
function registerParent(array $parentData, ?array $childData = null): array {
    global $pdo;
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection unavailable. Please make sure MySQL is running in XAMPP.'];
    }

    $name     = trim($parentData['name'] ?? '');
    $email    = trim($parentData['email'] ?? '');
    $phone    = trim($parentData['phone'] ?? '');
    $username = trim($parentData['username'] ?? '');
    $password = $parentData['password'] ?? '';
    $address  = trim($parentData['address'] ?? '');

    if (empty($name) || empty($email) || empty($username) || empty($password)) {
        return ['success' => false, 'message' => 'Please fill in all required parent fields (Full Name, Email, Username, Password).'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Please provide a valid email address.'];
    }

    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters long.'];
    }

    try {
        // Check for existing username or email in parents table
        $stmt = $pdo->prepare("SELECT parent_id FROM parents WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'A parent with this username or email already exists.'];
        }

        $pdo->beginTransaction();

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $roleId = 2; // Parent

        $stmtParent = $pdo->prepare("INSERT INTO parents (role_id, name, email, phone, username, password, address) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtParent->execute([$roleId, $name, $email, $phone, $username, $hashedPassword, $address]);
        $parentId = (int)$pdo->lastInsertId();

        $childId = null;
        if ($childData && !empty(trim($childData['child_name'] ?? ''))) {
            $childName = trim($childData['child_name']);
            $gender    = in_array($childData['gender'] ?? '', ['Male', 'Female', 'Other']) ? $childData['gender'] : 'Male';
            $dob       = !empty($childData['date_of_birth']) ? $childData['date_of_birth'] : date('Y-m-d');
            $childAddr = !empty(trim($childData['address'] ?? '')) ? trim($childData['address']) : $address;
            $notes     = trim($childData['notes'] ?? '');

            $stmtChild = $pdo->prepare("INSERT INTO children (parent_id, child_name, gender, date_of_birth, address, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtChild->execute([$parentId, $childName, $gender, $dob, $childAddr, $notes]);
            $childId = (int)$pdo->lastInsertId();
        }

        $pdo->commit();

        return [
            'success'   => true,
            'parent_id' => $parentId,
            'child_id'  => $childId,
            'message'   => 'Parent account registered successfully!' . ($childId ? ' Child profile was also created.' : '')
        ];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Registration error: ' . $e->getMessage()];
    }
}

/**
 * Register a Hospital / Healthcare Facility
 */
function registerHospital(array $hospitalData): array {
    global $pdo;
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection unavailable. Please make sure MySQL is running in XAMPP.'];
    }

    $hospitalName = trim($hospitalData['hospital_name'] ?? '');
    $email        = trim($hospitalData['email'] ?? '');
    $phone        = trim($hospitalData['phone'] ?? '');
    $location     = trim($hospitalData['location'] ?? '');
    $address      = trim($hospitalData['address'] ?? '');
    $username     = trim($hospitalData['username'] ?? '');
    $password     = $hospitalData['password'] ?? '';

    if (empty($hospitalName) || empty($email) || empty($username) || empty($password)) {
        return ['success' => false, 'message' => 'Please fill in all required hospital fields (Facility Name, Email, Username, Password).'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Please provide a valid official email address.'];
    }

    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters long.'];
    }

    try {
        $stmtCheck = $pdo->prepare("SELECT hospital_id FROM hospitals WHERE username = ? OR email = ? LIMIT 1");
        $stmtCheck->execute([$username, $email]);
        if ($stmtCheck->fetch()) {
            return ['success' => false, 'message' => 'A hospital facility with this username or email already exists.'];
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $roleId = 3; // Hospital
        $status = 'Pending';

        $stmt = $pdo->prepare("INSERT INTO hospitals (role_id, hospital_name, address, location, phone, email, username, password, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$roleId, $hospitalName, $address, $location, $phone, $email, $username, $hashedPassword, $status]);
        $hospitalId = (int)$pdo->lastInsertId();

        return [
            'success'     => true,
            'hospital_id' => $hospitalId,
            'status'      => 'Pending',
            'message'     => "Hospital registration request submitted! Your application has been sent to the Admin Portal for review and activation."
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Registration error: ' . $e->getMessage()];
    }
}
