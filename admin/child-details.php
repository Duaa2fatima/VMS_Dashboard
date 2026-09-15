<?php
/**
 * Child Detailed Vaccination Profile - Admin View
 * Child Vaccination Management System (VMS)
 */
$pageTitle = 'Child Profile & Vaccination Details';
$activePage = 'admin_children';

require_once __DIR__ . '/../includes/header.php';

$childId = (int)($_GET['id'] ?? 0);
if ($childId <= 0) {
    setFlash('error', 'Invalid child ID.');
    header('Location: children.php');
    exit;
}

$child = null;
$bookings = [];
$allVaccines = [];

if ($pdo) {
    try {
        // Child details with parent
        $stmt = $pdo->prepare("SELECT c.*, p.name as parent_name, p.email as parent_email, p.phone as parent_phone, p.address as parent_address
            FROM children c
            JOIN parents p ON c.parent_id = p.parent_id
            WHERE c.child_id = ?");
        $stmt->execute([$childId]);
        $child = $stmt->fetch();

        if (!$child) {
            setFlash('error', 'Child profile not found.');
            header('Location: children.php');
            exit;
        }

        // All bookings and vaccination records for this child
        $stmtBkg = $pdo->prepare("SELECT b.*, v.vaccine_name, v.age_group, v.description as vaccine_desc, 
                                         h.hospital_name, h.phone as hospital_phone, h.location as hospital_location,
                                         vr.record_id, vr.vaccination_date, vr.status as record_status, vr.remarks as record_remarks,
                                         adm.name as approved_by_admin
            FROM bookings b
            JOIN vaccines v ON b.vaccine_id = v.vaccine_id
            JOIN hospitals h ON b.hospital_id = h.hospital_id
            LEFT JOIN vaccination_records vr ON b.booking_id = vr.booking_id
            LEFT JOIN admins adm ON b.admin_id = adm.admin_id
            WHERE b.child_id = ?
            ORDER BY b.appointment_date ASC, b.booking_id ASC");
        $stmtBkg->execute([$childId]);
        $bookings = $stmtBkg->fetchAll();

        // Fetch all vaccines for schedule comparison
        $allVaccines = $pdo->query("SELECT * FROM vaccines ORDER BY vaccine_id ASC")->fetchAll();

    } catch (Exception $e) {
        setFlash('error', 'Error loading record: ' . $e->getMessage());
    }
}

// Map administered vaccine IDs
$administeredVaccines = [];
foreach ($bookings as $b) {
    if ($b['record_status'] === 'Vaccinated' || $b['status'] === 'Completed') {
        $administeredVaccines[$b['vaccine_id']] = $b;
    }
}
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-panel">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-inner">
            
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1"><i class="fas fa-id-card text-primary me-2"></i> Child Profile Details</h3>
                    <ul class="breadcrumbs mb-0 ps-0 list-unstyled d-flex gap-2 text-muted small">
                        <li><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                        <li>/</li>
                        <li><a href="children.php" class="text-decoration-none">Children</a></li>
                        <li>/</li>
                        <li class="active"><?= htmlspecialchars($child['child_name']) ?></li>
                    </ul>
                </div>
                <div>
                    <a href="children.php" class="btn btn-secondary btn-round">
                        <i class="fas fa-arrow-left me-1"></i> Back to Child List
                    </a>
                </div>
            </div>

            <?= displayFlash() ?>

            <!-- Child Info Summary Card -->
            <div class="row">
                <div class="col-lg-4">
                    <div class="card card-profile card-round shadow-sm">
                        <div class="card-header bg-primary text-white text-center py-4 rounded-top">
                            <div class="avatar-lg mx-auto mb-2">
                                <span class="avatar-title rounded-circle bg-white text-primary fw-bold fs-2 shadow">
                                    <?= strtoupper(substr($child['child_name'], 0, 1)) ?>
                                </span>
                            </div>
                            <h4 class="fw-bold mb-1"><?= htmlspecialchars($child['child_name']) ?></h4>
                            <p class="mb-0 text-white-50">DOB: <?= formatDate($child['date_of_birth']) ?> (<?= calculateAge($child['date_of_birth']) ?>)</p>
                        </div>
                        <div class="card-body">
                            <h6 class="fw-bold text-muted text-uppercase mb-3 small">Child Demographics</h6>
                            <ul class="list-group list-group-flush mb-4">
                                <li class="list-group-item d-flex justify-content-between px-0">
                                    <span class="text-muted">Gender</span>
                                    <span class="fw-bold"><?= htmlspecialchars($child['gender']) ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between px-0">
                                    <span class="text-muted">Date of Birth</span>
                                    <span class="fw-bold"><?= formatDate($child['date_of_birth']) ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between px-0">
                                    <span class="text-muted">Child ID</span>
                                    <span class="fw-bold font-monospace">#CH-<?= str_pad($child['child_id'], 4, '0', STR_PAD_LEFT) ?></span>
                                </li>
                                <?php if (!empty($child['address'])): ?>
                                    <li class="list-group-item px-0">
                                        <span class="text-muted d-block small">Child Address:</span>
                                        <span class="text-dark small"><?= htmlspecialchars($child['address']) ?></span>
                                    </li>
                                <?php endif; ?>
                                <?php if (!empty($child['notes'])): ?>
                                    <li class="list-group-item px-0">
                                        <span class="text-muted d-block small">Medical Notes:</span>
                                        <span class="text-dark small"><?= nl2br(htmlspecialchars($child['notes'])) ?></span>
                                    </li>
                                <?php endif; ?>
                            </ul>

                            <h6 class="fw-bold text-muted text-uppercase mb-3 small">Parent / Guardian Information</h6>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between px-0">
                                    <span class="text-muted">Parent Name</span>
                                    <span class="fw-bold"><?= htmlspecialchars($child['parent_name']) ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between px-0">
                                    <span class="text-muted">Contact Phone</span>
                                    <span class="fw-bold"><?= htmlspecialchars($child['parent_phone'] ?: 'N/A') ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between px-0">
                                    <span class="text-muted">Email</span>
                                    <span class="small"><?= htmlspecialchars($child['parent_email']) ?></span>
                                </li>
                                <li class="list-group-item px-0">
                                    <span class="text-muted d-block small">Parent Address:</span>
                                    <span class="small"><?= htmlspecialchars($child['parent_address']) ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Vaccination History & Status -->
                <div class="col-lg-8">
                    
                    <!-- Bookings & Administered Log -->
                    <div class="card card-round shadow-sm mb-4">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <div class="card-title text-dark">
                                <i class="fas fa-syringe text-primary me-2"></i> Vaccination Bookings & Administered History
                            </div>
                            <span class="badge bg-success"><?= count($administeredVaccines) ?> Doses Given</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Booking ID</th>
                                            <th>Vaccine</th>
                                            <th>Scheduled Appt</th>
                                            <th>Hospital</th>
                                            <th>Vaccination Date / Status</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($bookings)): ?>
                                            <?php foreach ($bookings as $b): ?>
                                                <tr>
                                                    <td class="fw-bold text-primary font-monospace">#BK-<?= str_pad($b['booking_id'], 4, '0', STR_PAD_LEFT) ?></td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($b['vaccine_name']) ?></strong>
                                                        <div class="small text-muted"><?= htmlspecialchars($b['age_group']) ?></div>
                                                    </td>
                                                    <td>
                                                        <div><?= formatDate($b['appointment_date']) ?></div>
                                                        <small class="text-muted">Booked: <?= formatDate($b['booking_date']) ?></small>
                                                    </td>
                                                    <td>
                                                        <div class="fw-bold"><?= htmlspecialchars($b['hospital_name']) ?></div>
                                                        <small class="text-muted"><?= htmlspecialchars($b['hospital_location'] ?: '') ?></small>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($b['record_status'])): ?>
                                                            <?= getStatusBadge($b['record_status']) ?>
                                                            <div class="small text-muted mt-1"><?= formatDate($b['vaccination_date']) ?></div>
                                                        <?php else: ?>
                                                            <?= getStatusBadge($b['status']) ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted"><?= htmlspecialchars($b['record_remarks'] ?: ($b['status'] === 'Approved' ? 'Approved by Admin' : '-')) ?></small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4 text-muted">
                                                    No vaccination booking history recorded for this child yet.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Recommended WHO Timeline for this Child -->
                    <div class="card card-round shadow-sm">
                        <div class="card-header bg-light">
                            <div class="card-title text-dark">
                                <i class="fas fa-calendar-check text-info me-2"></i> Standard Immunization Schedule & Due Dates
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Vaccine</th>
                                            <th>Recommended Age Group</th>
                                            <th>Calculated Due Date</th>
                                            <th>Stock Status</th>
                                            <th>Immunization State</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($allVaccines as $v): ?>
                                            <?php 
                                                $dueDate = calculateDueDate($child['date_of_birth'], $v['age_group']);
                                                $isGiven = isset($administeredVaccines[$v['vaccine_id']]);
                                                $isOverdue = (!$isGiven && strtotime($dueDate) < time());
                                            ?>
                                            <tr class="<?= $isGiven ? 'table-success-subtle' : '' ?>">
                                                <td>
                                                    <strong><?= htmlspecialchars($v['vaccine_name']) ?></strong>
                                                    <div class="small text-muted"><?= htmlspecialchars(substr($v['description'] ?? '', 0, 70)) ?></div>
                                                </td>
                                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($v['age_group']) ?></span></td>
                                                <td>
                                                    <span class="fw-bold <?= $isOverdue ? 'text-danger' : 'text-dark' ?>">
                                                        <?= formatDate($dueDate) ?>
                                                    </span>
                                                    <?php if ($isOverdue && !$isGiven): ?>
                                                        <span class="badge bg-danger ms-1">Overdue</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= getStockBadge($v['stock_status']) ?></td>
                                                <td>
                                                    <?php if ($isGiven): ?>
                                                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Administered</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Pending</span>
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

        </div>
    </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
