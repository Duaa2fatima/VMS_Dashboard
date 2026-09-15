<?php
/**
 * Hospital Dashboard - Clinical Overview & Today's Schedule
 * Child Vaccination Management System (VMS)
 */
$pageTitle = 'Hospital Dashboard';
$activePage = 'hospital_dashboard';

require_once __DIR__ . '/includes/header.php';

$hospitalId = $currentHospital['id'];

// Dashboard Metrics & Queues
$todayAppointments = [];
$upcomingApprovedAppointments = [];
$recentVaccinations = [];
$totalAdministeredCount = 0;
$totalPatientsCount = 0;
$pendingRequestsCount = 0;

if ($pdo) {
    try {
        // Pending booking requests requiring hospital approval & schedule
        $stmtPend = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE hospital_id = ? AND status = 'Pending'");
        $stmtPend->execute([$hospitalId]);
        $pendingRequestsCount = (int)$stmtPend->fetchColumn();

        // 1. Today's Appointments for this hospital
        $stmtToday = $pdo->prepare("
            SELECT b.*, c.child_name, c.date_of_birth, c.gender, c.notes as child_notes,
                   v.vaccine_name, v.age_group,
                   p.name as parent_name, p.phone as parent_phone
            FROM bookings b
            JOIN children c ON b.child_id = c.child_id
            JOIN vaccines v ON b.vaccine_id = v.vaccine_id
            JOIN parents p ON c.parent_id = p.parent_id
            WHERE b.hospital_id = ? AND b.appointment_date = CURRENT_DATE AND b.status IN ('Approved', 'Completed')
            ORDER BY b.status ASC, b.booking_id ASC
        ");
        $stmtToday->execute([$hospitalId]);
        $todayAppointments = $stmtToday->fetchAll();

        // 2. Upcoming Approved Appointments (Received from admin)
        $stmtUp = $pdo->prepare("
            SELECT b.*, c.child_name, c.date_of_birth, c.gender,
                   v.vaccine_name, v.age_group,
                   p.name as parent_name, p.phone as parent_phone
            FROM bookings b
            JOIN children c ON b.child_id = c.child_id
            JOIN vaccines v ON b.vaccine_id = v.vaccine_id
            JOIN parents p ON c.parent_id = p.parent_id
            WHERE b.hospital_id = ? AND b.status = 'Approved' AND b.appointment_date >= CURRENT_DATE
            ORDER BY b.appointment_date ASC
            LIMIT 6
        ");
        $stmtUp->execute([$hospitalId]);
        $upcomingApprovedAppointments = $stmtUp->fetchAll();

        // 3. Count total completed vaccinations given by this hospital
        $stmtTot = $pdo->prepare("
            SELECT COUNT(*) 
            FROM vaccination_records vr
            JOIN bookings b ON vr.booking_id = b.booking_id
            WHERE b.hospital_id = ? AND vr.status = 'Vaccinated'
        ");
        $stmtTot->execute([$hospitalId]);
        $totalAdministeredCount = (int)$stmtTot->fetchColumn();

        // 4. Count unique patients served
        $stmtPat = $pdo->prepare("
            SELECT COUNT(DISTINCT b.child_id)
            FROM bookings b
            WHERE b.hospital_id = ? AND b.status = 'Completed'
        ");
        $stmtPat->execute([$hospitalId]);
        $totalPatientsCount = (int)$stmtPat->fetchColumn();

        // 5. Recent Vaccinations Logged
        $stmtRec = $pdo->prepare("
            SELECT vr.*, c.child_name, c.date_of_birth, v.vaccine_name, p.name as parent_name
            FROM vaccination_records vr
            JOIN bookings b ON vr.booking_id = b.booking_id
            JOIN children c ON vr.child_id = c.child_id
            JOIN vaccines v ON b.vaccine_id = v.vaccine_id
            JOIN parents p ON c.parent_id = p.parent_id
            WHERE b.hospital_id = ?
            ORDER BY vr.vaccination_date DESC, vr.record_id DESC
            LIMIT 5
        ");
        $stmtRec->execute([$hospitalId]);
        $recentVaccinations = $stmtRec->fetchAll();

    } catch (Exception $e) {
        setFlash('error', 'Error loading hospital dashboard: ' . $e->getMessage());
    }
}
?>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-panel">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>
    <div class="container">
        <div class="page-inner">

            <!-- Facility Header -->
            <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h3 class="fw-bold mb-0"><?= htmlspecialchars($currentHospital['hospital_name']) ?></h3>
                        <span class="badge bg-success">Accredited Facility</span>
                    </div>
                    <div class="op-7 text-muted small">
                        <i class="fas fa-map-marker-alt text-danger me-1"></i> <?= htmlspecialchars($currentHospital['location'] ?? 'Location') ?> &bull;
                        <?= htmlspecialchars($currentHospital['address'] ?? '') ?> &bull;
                        <i class="fas fa-phone text-primary me-1"></i> <?= htmlspecialchars($currentHospital['phone'] ?? '-') ?>
                    </div>
                </div>
                <div class="ms-md-auto py-2 py-md-0 d-flex gap-2 flex-wrap">
                    <a href="appointments.php" class="btn btn-primary btn-round shadow-sm">
                        <i class="fas fa-calendar-check me-1"></i> Received Appointments
                    </a>
                    <a href="records.php" class="btn btn-outline-success btn-round">
                        <i class="fas fa-file-medical me-1"></i> Vaccination Records
                    </a>
                </div>
            </div>

            <?= displayFlash() ?>

            <?php if ($pendingRequestsCount > 0): ?>
                <div class="alert alert-warning d-flex align-items-center justify-content-between p-3 mb-4 rounded-3 shadow-sm border-warning">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-clock fa-2x me-3 text-warning"></i>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">You have <?= $pendingRequestsCount ?> parent booking request(s) awaiting your schedule & approval</h6>
                            <span class="small text-muted">Review child details and assign confirmed appointment date & time.</span>
                        </div>
                    </div>
                    <a href="appointments.php?filter=Pending" class="btn btn-sm btn-warning text-dark fw-bold rounded-pill px-3">
                        <i class="fas fa-calendar-check me-1"></i> Review & Schedule Now
                    </a>
                </div>
            <?php endif; ?>

            <!-- KPI Metric Cards -->
            <div class="row">
                <div class="col-sm-6 col-md-3">
                    <div class="card card-stats card-round dashboard-stat-card" onclick="location.href='appointments.php?filter=Today'">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-icon">
                                    <div class="icon-big text-center icon-danger bubble-shadow-small">
                                        <i class="fas fa-calendar-day"></i>
                                    </div>
                                </div>
                                <div class="col col-stats ms-3 ms-sm-0">
                                    <div class="numbers">
                                        <p class="card-category">Today's Schedule</p>
                                        <h4 class="card-title"><?= count($todayAppointments) ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-md-3">
                    <div class="card card-stats card-round dashboard-stat-card border-warning border-2" onclick="location.href='appointments.php?filter=Pending'" style="cursor: pointer;">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-icon">
                                    <div class="icon-big text-center icon-warning bubble-shadow-small">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                </div>
                                <div class="col col-stats ms-3 ms-sm-0">
                                    <div class="numbers">
                                        <p class="card-category text-warning fw-bold">Pending Requests</p>
                                        <h4 class="card-title text-warning fw-bold"><?= $pendingRequestsCount ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-md-3">
                    <div class="card card-stats card-round dashboard-stat-card" onclick="location.href='records.php'">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-icon">
                                    <div class="icon-big text-center icon-success bubble-shadow-small">
                                        <i class="fas fa-syringe"></i>
                                    </div>
                                </div>
                                <div class="col col-stats ms-3 ms-sm-0">
                                    <div class="numbers">
                                        <p class="card-category">Doses Given</p>
                                        <h4 class="card-title"><?= $totalAdministeredCount ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-md-3">
                    <div class="card card-stats card-round dashboard-stat-card" onclick="location.href='records.php'">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-icon">
                                    <div class="icon-big text-center icon-primary bubble-shadow-small">
                                        <i class="fas fa-user-friends"></i>
                                    </div>
                                </div>
                                <div class="col col-stats ms-3 ms-sm-0">
                                    <div class="numbers">
                                        <p class="card-category">Patients Served</p>
                                        <h4 class="card-title"><?= $totalPatientsCount ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- TODAY'S PATIENT QUEUE (Prompt Requirement)                                -->
            <!-- "Hospital will receive the appointment once its booked from admin side.   -->
            <!-- If vaccination is completed they will update the status to Vaccinated or  -->
            <!-- not."                                                                     -->
            <!-- ========================================================================= -->
            <div class="card card-round shadow-sm mb-4 border-top border-primary border-3">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-0 text-primary">
                            <i class="fas fa-clock me-2"></i> Today's Patient Schedule (<?= formatDate(date('Y-m-d'), 'l, d M Y') ?>)
                        </h5>
                        <small class="text-muted">Direct appointment queue for infants arriving today at your healthcare facility</small>
                    </div>
                    <a href="appointments.php?filter=Today" class="btn btn-sm btn-outline-primary rounded-pill">
                        View All
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($todayAppointments)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-calendar-check fa-3x mb-2 text-success"></i>
                            <h6 class="fw-bold">No appointments scheduled for today</h6>
                            <p class="small mb-0">Upcoming appointments received from admin will appear here on their scheduled date.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Ref ID</th>
                                        <th>Child Details</th>
                                        <th>Parent Contact</th>
                                        <th>Vaccine to Administer</th>
                                        <th>Booking Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($todayAppointments as $app): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-dark">#BKG-<?= str_pad($app['booking_id'], 3, '0', STR_PAD_LEFT) ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="avatar-xs">
                                                        <span class="avatar-title rounded-circle bg-<?= ($app['gender'] === 'Female') ? 'danger' : 'primary' ?> text-white font-weight-bold">
                                                            <?= strtoupper(substr($app['child_name'], 0, 1)) ?>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <strong><?= htmlspecialchars($app['child_name']) ?></strong>
                                                        <div class="small text-muted"><?= calculateAge($app['date_of_birth']) ?> (<?= htmlspecialchars($app['gender']) ?>)</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div><?= htmlspecialchars($app['parent_name']) ?></div>
                                                <div class="small text-muted"><i class="fas fa-phone me-1"></i> <?= htmlspecialchars($app['parent_phone'] ?? '-') ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-primary"><?= htmlspecialchars($app['vaccine_name']) ?></div>
                                                <small class="badge bg-secondary"><?= htmlspecialchars($app['age_group']) ?></small>
                                            </td>
                                            <td>
                                                <?= getStatusBadge($app['status']) ?>
                                                <?php if (!empty($app['appointment_time'])): ?>
                                                    <div class="small text-primary fw-bold mt-1"><i class="far fa-clock me-1"></i><?= formatTime($app['appointment_time']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="appointments.php?open_status=<?= $app['booking_id'] ?>" class="btn btn-sm btn-success rounded-pill text-white px-3 shadow-sm">
                                                    <i class="fas fa-syringe me-1"></i> Update Status
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Upcoming Received Appointments & Recent Records Row -->
            <div class="row">
                <!-- Upcoming Appointments Received from Admin -->
                <div class="col-lg-7 mb-4">
                    <div class="card card-round shadow-sm h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div class="card-title">
                                <i class="fas fa-calendar-alt text-info me-2"></i> Upcoming Received Appointments
                            </div>
                            <a href="appointments.php" class="btn btn-sm btn-outline-info rounded-pill">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($upcomingApprovedAppointments)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-calendar-times fa-2x mb-2 text-muted"></i>
                                    <p class="small mb-0">No upcoming approved appointments received yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>Date</th>
                                                <th>Child</th>
                                                <th>Vaccine</th>
                                                <th>Parent</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($upcomingApprovedAppointments as $up): ?>
                                                <tr>
                                                    <td class="fw-bold small text-primary">
                                                        <?= formatDate($up['appointment_date'], 'd M Y') ?>
                                                    </td>
                                                    <td>
                                                        <strong class="text-dark"><?= htmlspecialchars($up['child_name']) ?></strong>
                                                        <div class="small text-muted"><?= calculateAge($up['date_of_birth']) ?></div>
                                                    </td>
                                                    <td>
                                                        <span class="small fw-bold"><?= htmlspecialchars($up['vaccine_name']) ?></span>
                                                    </td>
                                                    <td class="small">
                                                        <?= htmlspecialchars($up['parent_name']) ?>
                                                    </td>
                                                    <td>
                                                        <a href="appointments.php?open_status=<?= $up['booking_id'] ?>" class="btn btn-xs btn-outline-primary">
                                                            <i class="fas fa-syringe me-1"></i> Update
                                                        </a>
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

                <!-- Recent Clinical Records Logged -->
                <div class="col-lg-5 mb-4">
                    <div class="card card-round shadow-sm h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div class="card-title">
                                <i class="fas fa-file-medical text-success me-2"></i> Recent Clinical Logs
                            </div>
                            <a href="records.php" class="btn btn-sm btn-outline-success rounded-pill">Log</a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($recentVaccinations)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-notes-medical fa-2x mb-2 text-muted"></i>
                                    <p class="small mb-0">No vaccination records logged yet.</p>
                                </div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($recentVaccinations as $rv): ?>
                                        <li class="list-group-item p-3">
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <strong class="text-dark"><?= htmlspecialchars($rv['child_name']) ?></strong>
                                                <?= getStatusBadge($rv['status']) ?>
                                            </div>
                                            <div class="small text-primary fw-bold mb-1">
                                                <i class="fas fa-syringe me-1"></i> <?= htmlspecialchars($rv['vaccine_name']) ?>
                                            </div>
                                            <div class="d-flex justify-content-between text-muted small">
                                                <span><i class="fas fa-calendar me-1"></i> <?= formatDate($rv['vaccination_date'], 'd M Y') ?></span>
                                                <span class="fst-italic text-truncate" style="max-width: 140px;"><?= htmlspecialchars($rv['remarks'] ?? '') ?></span>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Action Shortcuts -->
            <div class="row">
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card text-center p-3 shadow-sm dashboard-shortcut-card h-100" onclick="location.href='appointments.php'">
                        <i class="fas fa-calendar-check fa-2x text-primary mb-2 shortcut-icon"></i>
                        <h6 class="fw-bold mb-1">Patient Appointments</h6>
                        <p class="text-muted small mb-2">View received appointments from admin</p>
                        <a href="appointments.php" class="btn btn-sm btn-light border btn-shortcut">Manage Appointments</a>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card text-center p-3 shadow-sm dashboard-shortcut-card h-100" onclick="location.href='appointments.php?filter=Approved'">
                        <i class="fas fa-syringe fa-2x text-success mb-2 shortcut-icon"></i>
                        <h6 class="fw-bold mb-1">Update Vaccine Status</h6>
                        <p class="text-muted small mb-2">Mark status as Vaccinated or Not</p>
                        <a href="appointments.php?filter=Approved" class="btn btn-sm btn-light border btn-shortcut">Update Status</a>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card text-center p-3 shadow-sm dashboard-shortcut-card h-100" onclick="location.href='records.php'">
                        <i class="fas fa-file-medical-alt fa-2x text-info mb-2 shortcut-icon"></i>
                        <h6 class="fw-bold mb-1">Vaccination Records</h6>
                        <p class="text-muted small mb-2">Clinical records log & CSV export</p>
                        <a href="records.php" class="btn btn-sm btn-light border btn-shortcut">View Records</a>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card text-center p-3 shadow-sm dashboard-shortcut-card h-100" onclick="location.href='profile.php'">
                        <i class="fas fa-hospital fa-2x text-warning mb-2 shortcut-icon"></i>
                        <h6 class="fw-bold mb-1">Facility Profile</h6>
                        <p class="text-muted small mb-2">Manage facility name, location & contact</p>
                        <a href="profile.php" class="btn btn-sm btn-light border btn-shortcut">Edit Facility</a>
                    </div>
                </div>
            </div>

        </div>
    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
