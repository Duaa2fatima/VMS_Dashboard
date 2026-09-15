<?php
/**
 * Database Web Installer & Diagnostic Tool
 * Vaccination Management System (VMS)
 */
require_once __DIR__ . '/../config/db.php';

$message = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['auto'])) {
    try {
        $rootDsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4;port=" . DB_PORT;
        $rootPdo = new PDO($rootDsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        $schemaFile = __DIR__ . '/schema.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception("Schema file not found at " . $schemaFile);
        }

        $sql = file_get_contents($schemaFile);
        $rootPdo->exec($sql);

        $status = 'success';
        $message = "Database 'child_vaccination' and all 8 tables created and populated with sample data successfully!";
    } catch (Exception $e) {
        $status = 'error';
        $message = "Installation Error: " . $e->getMessage();
    }
}

// Verify current status
$tablesFound = [];
$counts = [];
try {
    $checkPdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4;port=" . DB_PORT, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    $tables = ['roles', 'admins', 'parents', 'hospitals', 'children', 'vaccines', 'hospital_vaccines', 'bookings', 'vaccination_records'];
    foreach ($tables as $t) {
        $stmt = $checkPdo->query("SHOW TABLES LIKE '$t'");
        if ($stmt->fetch()) {
            $tablesFound[] = $t;
            $cnt = $checkPdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
            $counts[$t] = $cnt;
        }
    }
} catch (Exception $e) {
    // Database might not be installed yet
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup & Verification | VaxCare Admin</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background: #f4f6f9; font-family: 'Public Sans', sans-serif; padding: 40px 15px; }
        .setup-card { max-width: 760px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); overflow: hidden; }
        .setup-header { background: #1a2035; color: #fff; padding: 30px; }
    </style>
</head>
<body>

    <div class="setup-card">
        <div class="setup-header text-center">
            <i class="fas fa-shield-virus fa-3x text-primary mb-2"></i>
            <h3 class="fw-bold mb-1">Vaccination Management System</h3>
            <p class="text-white-50 mb-0">Database Setup & Table Verifier (<code>child_vaccination</code>)</p>
        </div>

        <div class="p-4">
            <?php if ($status === 'success'): ?>
                <div class="alert alert-success d-flex align-items-center mb-4">
                    <i class="fas fa-check-circle fa-2x me-3"></i>
                    <div>
                        <strong>Success!</strong> <?= htmlspecialchars($message) ?>
                    </div>
                </div>
            <?php elseif ($status === 'error'): ?>
                <div class="alert alert-danger d-flex align-items-center mb-4">
                    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                    <div>
                        <strong>Installation Error:</strong> <?= htmlspecialchars($message) ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mb-4">
                <h5 class="fw-bold"><i class="fas fa-database text-primary me-2"></i> Current Database Status</h5>
                <p class="text-muted small">Target Database: <code><?= DB_NAME ?></code> on <code><?= DB_HOST ?>:<?= DB_PORT ?></code></p>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Table Name</th>
                                <th>Status</th>
                                <th>Record Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $requiredTables = ['roles', 'admins', 'parents', 'hospitals', 'children', 'vaccines', 'hospital_vaccines', 'bookings', 'vaccination_records'];
                            foreach ($requiredTables as $tbl): 
                                $exists = in_array($tbl, $tablesFound);
                            ?>
                                <tr>
                                    <td class="font-monospace"><strong><?= $tbl ?></strong></td>
                                    <td>
                                        <?php if ($exists): ?>
                                            <span class="badge bg-success"><i class="fas fa-check"></i> Ready</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><i class="fas fa-times"></i> Missing</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $exists ? ($counts[$tbl] ?? 0) . ' records' : '-' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card bg-light p-3 mb-4 border-0">
                <h6 class="fw-bold mb-2"><i class="fas fa-key text-warning me-1"></i> Default Admin Credentials:</h6>
                <div class="row">
                    <div class="col-sm-6">
                        <small class="text-muted">Username / Login:</small>
                        <div class="fw-bold font-monospace">admin</div>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted">Password:</small>
                        <div class="fw-bold font-monospace">admin123</div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <form method="POST" action="setup.php" onsubmit="return confirm('This will recreate the child_vaccination database and reload sample records. Continue?');">
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-sync-alt me-1"></i> (Re)Install Database & Seed Data
                    </button>
                </form>

                <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-primary px-4">
                    <i class="fas fa-sign-in-alt me-1"></i> Proceed to Admin Login
                </a>
            </div>
        </div>
    </div>

</body>
</html>
