<?php
/**
 * Child Profile & Immunization Milestone Schedule
 * Child Vaccination Management System (VMS)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
requireParent();

$currentParent = getCurrentParent();
$parentId = $currentParent['id'];
$childId = (int)($_GET['id'] ?? 0);

if (!$childId) {
    setFlash('warning', 'Please select a child to view details.');
    header('Location: children.php');
    exit;
}

// Fetch Child and verify ownership
$child = null;
if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM children WHERE child_id = ? AND parent_id = ?");
        $stmt->execute([$childId, $parentId]);
        $child = $stmt->fetch();
    } catch (Exception $e) {
        setFlash('error', 'Database error: ' . $e->getMessage());
    }
}

if (!$child) {
    setFlash('danger', 'Child profile not found or unauthorized.');
    header('Location: children.php');
    exit;
}

$pageTitle = 'Child Immunization Profile';
$activePage = 'parent_children';

require_once __DIR__ . '/includes/header.php';

// Fetch administered vaccination records
$vaccinationRecords = [];
$completedVaccineIds = [];
// Fetch existing bookings for this child
$existingBookings = [];
$bookedVaccineMap = [];

if ($pdo) {
    try {
        // Administered records
        $stmtRec = $pdo->prepare("
            SELECT vr.*, b.appointment_date, v.vaccine_name, v.age_group, h.hospital_name, h.location as hospital_location
            FROM vaccination_records vr
            JOIN bookings b ON vr.booking_id = b.booking_id
            JOIN vaccines v ON b.vaccine_id = v.vaccine_id
            JOIN hospitals h ON b.hospital_id = h.hospital_id
            WHERE vr.child_id = ?
            ORDER BY vr.vaccination_date DESC, vr.record_id DESC
        ");
        $stmtRec->execute([$childId]);
        $vaccinationRecords = $stmtRec->fetchAll();

        foreach ($vaccinationRecords as $vr) {
            if ($vr['status'] === 'Vaccinated') {
                $completedVaccineIds[] = $vr['vaccine_name'];
            }
        }

        // All Bookings for this child
        $stmtBk = $pdo->prepare("
            SELECT b.*, v.vaccine_name, v.age_group, h.hospital_name, h.location as hospital_location
            FROM bookings b
            JOIN vaccines v ON b.vaccine_id = v.vaccine_id
            JOIN hospitals h ON b.hospital_id = h.hospital_id
            WHERE b.child_id = ?
            ORDER BY b.appointment_date DESC
        ");
        $stmtBk->execute([$childId]);
        $existingBookings = $stmtBk->fetchAll();

        foreach ($existingBookings as $eb) {
            $bookedVaccineMap[$eb['vaccine_id']] = $eb;
        }

        // All Catalog Vaccines for standard WHO milestone tracking
        $catalogVaccines = $pdo->query("SELECT * FROM vaccines ORDER BY vaccine_id ASC")->fetchAll();

    } catch (Exception $e) {
        setFlash('error', 'Error loading immunization history: ' . $e->getMessage());
    }
}

$totalVaccinesCatalog = count($catalogVaccines ?? []);
$completedCount = count($completedVaccineIds);
$completionPct = ($totalVaccinesCatalog > 0) ? round(($completedCount / $totalVaccinesCatalog) * 100) : 0;
?>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-panel">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>
    <div class="container">
        <div class="page-inner">

            <!-- Breadcrumb & Top Bar -->
            <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
                <div>
                    <div class="mb-1">
                        <a href="children.php" class="text-muted text-decoration-none small">
                            <i class="fas fa-arrow-left me-1"></i> Back to Children Directory
                        </a>
                    </div>
                    <h3 class="fw-bold mb-1">
                        <?= htmlspecialchars($child['child_name']) ?>'s Immunization Profile
                    </h3>
                    <h6 class="op-7 mb-0 text-muted">Complete demographic details, WHO immunization schedule, and administered records</h6>
                </div>
                <div class="ms-md-auto py-2 py-md-0 d-flex gap-2 flex-wrap">
                    <a href="book-appointment.php?child_id=<?= $child['child_id'] ?>" class="btn btn-primary btn-round shadow-sm">
                        <i class="fas fa-calendar-plus me-1"></i> Book Hospital for <?= htmlspecialchars($child['child_name']) ?>
                    </a>
                    <a href="reports.php?child_id=<?= $child['child_id'] ?>" class="btn btn-outline-success btn-round">
                        <i class="fas fa-print me-1"></i> Vaccination Report
                    </a>
                </div>
            </div>

            <?= displayFlash() ?>

            <!-- Child Demographics & KPI Row -->
            <div class="row">
                <!-- Demographics Card -->
                <div class="col-lg-4 mb-4">
                    <div class="card card-round shadow-sm h-100">
                        <div class="card-header bg-light d-flex align-items-center gap-3 py-3">
                            <div class="avatar-lg">
                                <span class="avatar-title rounded-circle bg-<?= ($child['gender'] === 'Female') ? 'danger' : 'primary' ?> text-white fw-bold fs-2">
                                    <?= strtoupper(substr($child['child_name'], 0, 1)) ?>
                                </span>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0"><?= htmlspecialchars($child['child_name']) ?></h5>
                                <span class="badge bg-secondary"><?= htmlspecialchars($child['gender']) ?></span>
                                <span class="badge bg-info text-white ms-1"><?= calculateAge($child['date_of_birth']) ?></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush mb-3">
                                <li class="list-group-item d-flex justify-content-between px-0 py-2">
                                    <span class="text-muted small">Date of Birth:</span>
                                    <span class="fw-bold small"><?= formatDate($child['date_of_birth'], 'd M Y') ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between px-0 py-2">
                                    <span class="text-muted small">Parent / Guardian:</span>
                                    <span class="fw-bold small"><?= htmlspecialchars($currentParent['name']) ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between px-0 py-2">
                                    <span class="text-muted small">Parent Phone:</span>
                                    <span class="fw-bold small"><?= htmlspecialchars($currentParent['phone'] ?? '-') ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between px-0 py-2">
                                    <span class="text-muted small">Home Address:</span>
                                    <span class="fw-bold small text-end text-truncate" style="max-width: 170px;">
                                        <?= htmlspecialchars($child['address'] ?? ($currentParent['address'] ?? '-')) ?>
                                    </span>
                                </li>
                            </ul>

                            <div class="mb-3">
                                <span class="small fw-bold text-muted d-block mb-1">Health & Allergy Notes:</span>
                                <div class="p-2 bg-light rounded text-muted small">
                                    <?= !empty($child['notes']) ? htmlspecialchars($child['notes']) : 'No known allergies or medical restrictions recorded.' ?>
                                </div>
                            </div>

                            <div class="p-3 bg-light rounded-3">
                                <div class="d-flex justify-content-between small fw-bold mb-1">
                                    <span>Protection Level:</span>
                                    <span class="text-success"><?= $completedCount ?> / <?= $totalVaccinesCatalog ?> (<?= $completionPct ?>%)</span>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $completionPct ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Status Overview -->
                <div class="col-lg-8 mb-4">
                    <div class="card card-round shadow-sm h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div class="card-title">
                                <i class="fas fa-calendar-check text-primary me-2"></i> Standard WHO Immunization Schedule & Status
                            </div>
                            <span class="badge bg-primary"><?= $totalVaccinesCatalog ?> Recommended Vaccines</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Vaccine Name</th>
                                            <th>Recommended Age</th>
                                            <th>Estimated Due Date</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($catalogVaccines as $vac): ?>
                                            <?php
                                                $dueDate = calculateDueDate($child['date_of_birth'], $vac['age_group']);
                                                $isTaken = in_array($vac['vaccine_name'], $completedVaccineIds);
                                                $existingBk = $bookedVaccineMap[$vac['vaccine_id']] ?? null;
                                                $today = date('Y-m-d');
                                                $isOverdue = (!$isTaken && !$existingBk && $dueDate < $today);
                                            ?>
                                            <tr class="<?= $isTaken ? 'table-success bg-opacity-10' : ($isOverdue ? 'table-warning bg-opacity-10' : '') ?>">
                                                <td>
                                                    <div class="fw-bold text-dark"><?= htmlspecialchars($vac['vaccine_name']) ?></div>
                                                    <small class="text-muted d-block text-truncate" style="max-width: 250px;">
                                                        <?= htmlspecialchars($vac['description']) ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?= htmlspecialchars($vac['age_group']) ?></span>
                                                </td>
                                                <td class="small">
                                                    <?= formatDate($dueDate, 'd M Y') ?>
                                                    <?php if ($isOverdue): ?>
                                                        <span class="badge bg-danger" style="font-size: 0.65rem;">Overdue</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($isTaken): ?>
                                                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Administered</span>
                                                    <?php elseif ($existingBk): ?>
                                                        <?php if ($existingBk['status'] === 'Approved'): ?>
                                                            <span class="badge bg-info text-white"><i class="fas fa-calendar-check me-1"></i> Approved for <?= formatDate($existingBk['appointment_date'], 'd M') ?></span>
                                                        <?php elseif ($existingBk['status'] === 'Pending'): ?>
                                                            <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i> Request Pending</span>
                                                        <?php elseif ($existingBk['status'] === 'Completed'): ?>
                                                            <span class="badge bg-success"><i class="fas fa-check-double me-1"></i> Completed</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary"><?= htmlspecialchars($existingBk['status']) ?></span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-light text-dark border"><i class="fas fa-hourglass-start me-1"></i> Due</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($isTaken): ?>
                                                        <span class="text-success small fw-bold"><i class="fas fa-shield-alt me-1"></i> Protected</span>
                                                    <?php elseif ($existingBk && in_array($existingBk['status'], ['Approved', 'Pending'])): ?>
                                                        <a href="bookings.php" class="btn btn-xs btn-outline-info">
                                                            View Booking
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="book-appointment.php?child_id=<?= $child['child_id'] ?>&vaccine_id=<?= $vac['vaccine_id'] ?>" class="btn btn-xs btn-primary text-white">
                                                            <i class="fas fa-calendar-plus me-1"></i> Book Now
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Administered Vaccination Records History -->
            <div class="card card-round shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="card-title">
                        <i class="fas fa-file-medical text-success me-2"></i> Clinical Vaccination Records & Hospital Notes
                    </div>
                    <?php if (!empty($vaccinationRecords)): ?>
                        <a href="reports.php?child_id=<?= $child['child_id'] ?>" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-print me-1"></i> View Printable Certificate
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($vaccinationRecords)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-notes-medical fa-3x mb-2 text-muted"></i>
                            <h6>No administered vaccination records logged yet</h6>
                            <p class="small mb-0">Once the child attends a hospital appointment and is vaccinated, the clinical facility will update the status here.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Date Administered</th>
                                        <th>Vaccine</th>
                                        <th>Healthcare Facility</th>
                                        <th>Status</th>
                                        <th>Clinical Remarks & Batch #</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vaccinationRecords as $vr): ?>
                                        <tr>
                                            <td class="fw-bold">
                                                <i class="fas fa-calendar-check text-success me-1"></i>
                                                <?= formatDate($vr['vaccination_date'], 'd M Y') ?>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($vr['vaccine_name']) ?></strong>
                                                <span class="badge bg-secondary ms-1"><?= htmlspecialchars($vr['age_group']) ?></span>
                                            </td>
                                            <td>
                                                <div><i class="fas fa-hospital text-muted me-1"></i> <?= htmlspecialchars($vr['hospital_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($vr['hospital_location'] ?? '') ?></small>
                                            </td>
                                            <td>
                                                <?= getStatusBadge($vr['status']) ?>
                                            </td>
                                            <td>
                                                <span class="small text-muted fst-italic">
                                                    <?= !empty($vr['remarks']) ? htmlspecialchars($vr['remarks']) : 'Vaccine successfully administered. No adverse reaction reported.' ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
