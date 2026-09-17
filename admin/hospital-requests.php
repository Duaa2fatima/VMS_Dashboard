<?php
/**
 * Hospital Registration Requests - Admin Approval Workflow
 * Child Vaccination Management System (VMS)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
requireAdmin();

$pageTitle = 'Request from Hospitals (Approve/Reject)';
$activePage = 'admin_hospital_requests';

// Handle Approve / Reject Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action'] ?? '';
    $hospitalId = (int)($_POST['hospital_id'] ?? 0);

    if ($hospitalId > 0 && in_array($action, ['approve', 'reject', 'delete'])) {
        try {
            // Fetch hospital details
            $stmtH = $pdo->prepare("SELECT * FROM hospitals WHERE hospital_id = ?");
            $stmtH->execute([$hospitalId]);
            $hosp = $stmtH->fetch();

            if ($hosp) {
                if ($action === 'approve') {
                    $stmtUpdate = $pdo->prepare("UPDATE hospitals SET status = 'Active' WHERE hospital_id = ?");
                    $stmtUpdate->execute([$hospitalId]);
                    setFlash('success', "Hospital '{$hosp['hospital_name']}' has been APPROVED and activated successfully. Facility can now log in.");
                } elseif ($action === 'reject') {
                    $stmtUpdate = $pdo->prepare("UPDATE hospitals SET status = 'Rejected' WHERE hospital_id = ?");
                    $stmtUpdate->execute([$hospitalId]);
                    setFlash('info', "Hospital '{$hosp['hospital_name']}' registration request has been REJECTED.");
                } elseif ($action === 'delete') {
                    $pdo->beginTransaction();
                    $pdo->prepare("DELETE FROM hospital_vaccines WHERE hospital_id = ?")->execute([$hospitalId]);
                    $pdo->prepare("DELETE vr FROM vaccination_records vr INNER JOIN bookings b ON vr.booking_id = b.booking_id WHERE b.hospital_id = ?")->execute([$hospitalId]);
                    $pdo->prepare("DELETE FROM bookings WHERE hospital_id = ?")->execute([$hospitalId]);
                    $stmtDel = $pdo->prepare("DELETE FROM hospitals WHERE hospital_id = ?");
                    $stmtDel->execute([$hospitalId]);
                    $pdo->commit();
                    setFlash('success', "Hospital request for '{$hosp['hospital_name']}' has been removed.");
                }
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('error', 'Action failed: ' . $e->getMessage());
        }
    }
    $viewParam = !empty($_GET['view']) ? '?view=' . urlencode($_GET['view']) : '';
    header('Location: hospital-requests.php' . $viewParam);
    exit;
}

// View filter: pending by default, or all
$viewMode = $_GET['view'] ?? 'pending';
$requests = [];

if ($pdo) {
    try {
        $statusSql = ($viewMode === 'pending') ? "WHERE h.status = 'Pending'" : "WHERE 1=1";
        $stmt = $pdo->query("SELECT h.*,
            (SELECT COUNT(*) FROM bookings b WHERE b.hospital_id = h.hospital_id) as total_bookings
            FROM hospitals h
            $statusSql
            ORDER BY CASE WHEN h.status = 'Pending' THEN 0 ELSE 1 END, h.hospital_id DESC");
        $requests = $stmt->fetchAll();
    } catch (Exception $e) {
        setFlash('error', 'Error loading hospital requests: ' . $e->getMessage());
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
                    <h3 class="fw-bold mb-1"><i class="fas fa-hospital-user text-danger me-2"></i> Request from Hospitals</h3>
                    <ul class="breadcrumbs mb-0 ps-0 list-unstyled d-flex gap-2 text-muted small">
                        <li><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                        <li>/</li>
                        <li class="active">Hospital Registration Requests</li>
                    </ul>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="btn-group" role="group">
                        <a href="hospital-requests.php?view=pending" class="btn btn-sm <?= $viewMode === 'pending' ? 'btn-danger fw-bold' : 'btn-outline-danger' ?>">
                            <i class="fas fa-clock me-1"></i> Pending Queue (<?= $pendingHospitalsCount ?>)
                        </a>
                        <a href="hospital-requests.php?view=all" class="btn btn-sm <?= $viewMode === 'all' ? 'btn-primary fw-bold' : 'btn-outline-primary' ?>">
                            <i class="fas fa-list me-1"></i> All Hospital Records
                        </a>
                    </div>
                    <a href="hospitals.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-hospital me-1"></i> Hospital Directory
                    </a>
                </div>
            </div>

            <?= displayFlash() ?>

            <!-- Hospital Requests Table Card -->
            <div class="card card-round shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <div class="card-title text-dark">
                        <i class="fas fa-clipboard-check me-2 text-primary"></i>
                        <?= $viewMode === 'pending' ? 'Pending Hospital Registration Applications Requiring Admin Decision' : 'All Hospital Registration Applications & Status' ?>
                    </div>
                    <span class="badge bg-secondary"><?= count($requests) ?> Facilities</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover datatable-custom align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Ref #</th>
                                    <th>Hospital / Clinic Profile</th>
                                    <th>Location / District</th>
                                    <th>Official Contact</th>
                                    <th>Physical Address</th>
                                    <th>Status</th>
                                    <th class="text-center">Action / Decision</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($requests)): ?>
                                    <?php foreach ($requests as $h): ?>
                                        <tr>
                                            <td class="fw-bold font-monospace text-muted">
                                                #HSP-<?= str_pad($h['hospital_id'], 4, '0', STR_PAD_LEFT) ?>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle me-2 bg-primary text-white">
                                                        <i class="fas fa-hospital-alt"></i>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold text-dark"><?= htmlspecialchars($h['hospital_name']) ?></div>
                                                        <small class="text-muted">Username: <code><?= htmlspecialchars($h['username']) ?></code></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($h['location'] ?: 'Unspecified') ?></div>
                                            </td>
                                            <td>
                                                <div><i class="fas fa-envelope text-muted me-1 small"></i> <?= htmlspecialchars($h['email'] ?: 'N/A') ?></div>
                                                <small class="text-muted"><i class="fas fa-phone-alt text-muted me-1 small"></i> <?= htmlspecialchars($h['phone'] ?: 'N/A') ?></small>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?= htmlspecialchars($h['address'] ?: 'Not provided') ?></small>
                                            </td>
                                            <td>
                                                <?= getStatusBadge($h['status']) ?>
                                            </td>
                                            <td class="text-center text-nowrap">
                                                <?php if ($h['status'] === 'Pending'): ?>
                                                    <form method="POST" action="hospital-requests.php<?= !empty($_GET['view']) ? '?view=' . urlencode($_GET['view']) : '' ?>" class="d-inline">
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="hidden" name="hospital_id" value="<?= $h['hospital_id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-success fw-bold me-1 px-3 shadow-sm" title="Approve & Activate Hospital">
                                                            <i class="fas fa-check me-1"></i> Approve
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="hospital-requests.php<?= !empty($_GET['view']) ? '?view=' . urlencode($_GET['view']) : '' ?>" class="d-inline" onsubmit="return confirm('Are you sure you want to REJECT this hospital registration request?');">
                                                        <input type="hidden" name="action" value="reject">
                                                        <input type="hidden" name="hospital_id" value="<?= $h['hospital_id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger fw-bold me-1 px-2" title="Reject Hospital Request">
                                                            <i class="fas fa-ban me-1"></i> Reject
                                                        </button>
                                                    </form>
                                                <?php elseif ($h['status'] === 'Rejected'): ?>
                                                    <form method="POST" action="hospital-requests.php<?= !empty($_GET['view']) ? '?view=' . urlencode($_GET['view']) : '' ?>" class="d-inline">
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="hidden" name="hospital_id" value="<?= $h['hospital_id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-success fw-bold me-1" title="Re-Approve Hospital">
                                                            <i class="fas fa-redo me-1"></i> Re-Approve
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 small">
                                                        <i class="fas fa-check-circle me-1"></i> Active Facility
                                                    </span>
                                                <?php endif; ?>

                                                <form method="POST" action="hospital-requests.php<?= !empty($_GET['view']) ? '?view=' . urlencode($_GET['view']) : '' ?>" class="d-inline" onsubmit="return confirm('Delete this hospital request record permanently?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="hospital_id" value="<?= $h['hospital_id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Delete Request Record">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5">
                                            <div class="text-muted">
                                                <i class="fas fa-check-circle fa-3x text-success mb-3 d-block"></i>
                                                <h5><?= $viewMode === 'pending' ? 'No Pending Hospital Registration Requests' : 'No Hospital Requests Found' ?></h5>
                                                <p class="small mb-0">
                                                    <?= $viewMode === 'pending' ? 'All hospital applications have been processed and reviewed.' : 'Registered healthcare facilities will appear here.' ?>
                                                </p>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
