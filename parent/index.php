<?php
/**
 * Parent Dashboard
 * Child Vaccination Management System (VMS)
 */
$pageTitle = 'Parent Dashboard';
$activePage = 'parent_dashboard';

require_once __DIR__ . '/includes/header.php';

$parentId = $currentParent['id'];

// Dashboard Data
$totalChildren = 0;
$upcomingAppointments = [];
$pendingBookings = [];
$recentBookings = [];
$completedVaccinationsCount = 0;
$childrenList = [];
$dueMilestones = [];

if ($pdo) {
    try {
        // 1. Children of this parent
        $stmtCh = $pdo->prepare("SELECT * FROM children WHERE parent_id = ? ORDER BY child_id DESC");
        $stmtCh->execute([$parentId]);
        $childrenList = $stmtCh->fetchAll();
        $totalChildren = count($childrenList);

        // 2. Count completed vaccinations received by this parent's children
        $stmtComp = $pdo->prepare("
            SELECT COUNT(*) 
            FROM vaccination_records vr
            JOIN children c ON vr.child_id = c.child_id
            WHERE c.parent_id = ? AND vr.status = 'Vaccinated'
        ");
        $stmtComp->execute([$parentId]);
        $completedVaccinationsCount = (int)$stmtComp->fetchColumn();

        // 3. Upcoming Approved Appointments (received notification alert)
        $stmtUp = $pdo->prepare("
            SELECT b.*, c.child_name, c.date_of_birth, v.vaccine_name, v.age_group, h.hospital_name, h.location, h.phone as hospital_phone
            FROM bookings b
            JOIN children c ON b.child_id = c.child_id
            JOIN vaccines v ON b.vaccine_id = v.vaccine_id
            JOIN hospitals h ON b.hospital_id = h.hospital_id
            WHERE c.parent_id = ? AND b.status = 'Approved' AND b.appointment_date >= CURRENT_DATE
            ORDER BY b.appointment_date ASC
            LIMIT 5
        ");
        $stmtUp->execute([$parentId]);
        $upcomingAppointments = $stmtUp->fetchAll();

        // 4. Pending Booking Requests
        $stmtPend = $pdo->prepare("
            SELECT b.*, c.child_name, v.vaccine_name, h.hospital_name, h.location
            FROM bookings b
            JOIN children c ON b.child_id = c.child_id
            JOIN vaccines v ON b.vaccine_id = v.vaccine_id
            JOIN hospitals h ON b.hospital_id = h.hospital_id
            WHERE c.parent_id = ? AND b.status = 'Pending'
            ORDER BY b.booking_date DESC, b.booking_id DESC
        ");
        $stmtPend->execute([$parentId]);
        $pendingBookings = $stmtPend->fetchAll();

        // 5. Recent Bookings (All statuses)
        $stmtRec = $pdo->prepare("
            SELECT b.*, c.child_name, v.vaccine_name, h.hospital_name, h.location
            FROM bookings b
            JOIN children c ON b.child_id = c.child_id
            JOIN vaccines v ON b.vaccine_id = v.vaccine_id
            JOIN hospitals h ON b.hospital_id = h.hospital_id
            WHERE c.parent_id = ?
            ORDER BY b.booking_date DESC, b.booking_id DESC
            LIMIT 5
        ");
        $stmtRec->execute([$parentId]);
        $recentBookings = $stmtRec->fetchAll();

        // 6. Calculate upcoming WHO milestones for each child that haven't been booked/vaccinated
        $allVaccines = $pdo->query("SELECT * FROM vaccines WHERE stock_status = 'Available' ORDER BY vaccine_id ASC")->fetchAll();
        
        foreach ($childrenList as $child) {
            // Get already booked or vaccinated vaccine IDs for this child
            $stmtTaken = $pdo->prepare("SELECT vaccine_id FROM bookings WHERE child_id = ? AND status IN ('Approved', 'Completed', 'Pending')");
            $stmtTaken->execute([$child['child_id']]);
            $takenVaccineIds = $stmtTaken->fetchAll(PDO::FETCH_COLUMN);

            foreach ($allVaccines as $v) {
                if (!in_array($v['vaccine_id'], $takenVaccineIds)) {
                    $calcDate = calculateDueDate($child['date_of_birth'], $v['age_group']);
                    $today = date('Y-m-d');
                    $diffDays = (strtotime($calcDate) - strtotime($today)) / 86400;

                    // If due within next 60 days or overdue
                    if ($diffDays <= 60) {
                        $dueMilestones[] = [
                            'child_id'     => $child['child_id'],
                            'child_name'   => $child['child_name'],
                            'vaccine_id'   => $v['vaccine_id'],
                            'vaccine_name' => $v['vaccine_name'],
                            'age_group'    => $v['age_group'],
                            'due_date'     => $calcDate,
                            'days_diff'    => (int)$diffDays,
                            'is_overdue'   => $diffDays < 0
                        ];
                    }
                }
            }
        }

        // Sort milestones by due date
        usort($dueMilestones, fn($a, $b) => strcmp($a['due_date'], $b['due_date']));

    } catch (Exception $e) {
        setFlash('error', 'Error loading dashboard data: ' . $e->getMessage());
    }
}
?>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-panel">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>
    <div class="container">
        <div class="page-inner">

            <!-- Welcome Header -->
            <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
                <div>
                    <h3 class="fw-bold mb-1">
                        Welcome back, <?= htmlspecialchars($currentParent['name'] ?? 'Parent') ?>! 👋
                    </h3>
                    <h6 class="op-7 mb-0 text-muted">Manage your child's immunization schedule, hospital bookings, and vaccination certificates</h6>
                </div>
                <div class="ms-md-auto py-2 py-md-0 d-flex gap-2 flex-wrap">
                    <a href="book-appointment.php" class="btn btn-primary btn-round shadow-sm">
                        <i class="fas fa-calendar-plus me-1"></i> Book Hospital
                    </a>
                    <a href="children.php" class="btn btn-outline-primary btn-round">
                        <i class="fas fa-baby me-1"></i> Add / View Child
                    </a>
                    <a href="reports.php" class="btn btn-outline-success btn-round">
                        <i class="fas fa-file-invoice me-1"></i> Reports
                    </a>
                </div>
            </div>

            <?= displayFlash() ?>

            <!-- ========================================================================= -->
            <!-- VACCINATION NOTIFICATION & UPCOMING ALERTS (Prompt Requirement)           -->
            <!-- "Can get notified through in Dashboard of their respective accounts       -->
            <!-- about upcoming vaccinations."                                             -->
            <!-- ========================================================================= -->
            <?php if (!empty($upcomingAppointments) || !empty($dueMilestones)): ?>
                <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, rgba(21, 114, 232, 0.08) 0%, rgba(16, 185, 129, 0.08) 100%); border-left: 5px solid #1572e8 !important;">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-sm">
                                    <span class="avatar-title rounded-circle bg-primary text-white">
                                        <i class="fas fa-bell"></i>
                                    </span>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-0 text-primary">Upcoming Vaccination Notifications</h5>
                                    <small class="text-muted">Important dates and immunization reminders for your children</small>
                                </div>
                            </div>
                            <a href="schedule.php" class="btn btn-xs btn-outline-primary rounded-pill px-3">
                                View Full Schedule <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>

                        <div class="row g-3">
                            <!-- Scheduled Hospital Appointments -->
                            <?php foreach ($upcomingAppointments as $up): ?>
                                <?php
                                    $today = new DateTime('today');
                                    $appDate = new DateTime($up['appointment_date']);
                                    $diff = $today->diff($appDate);
                                    $daysRemaining = (int)$diff->format("%r%a");
                                    $badgeClass = ($daysRemaining === 0) ? 'bg-danger' : (($daysRemaining <= 3) ? 'bg-warning text-dark' : 'bg-success');
                                    $timeLabel = ($daysRemaining === 0) ? 'TODAY!' : (($daysRemaining === 1) ? 'Tomorrow' : 'In ' . $daysRemaining . ' days');
                                ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="p-3 bg-white rounded-3 border shadow-sm h-100" style="background: var(--bg-card, #fff);">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <span class="badge <?= $badgeClass ?> fw-bold px-2 py-1">
                                                <i class="fas fa-clock me-1"></i> <?= $timeLabel ?>
                                            </span>
                                            <span class="badge bg-info text-white">Approved</span>
                                        </div>
                                        <h6 class="fw-bold mb-1 text-truncate">
                                            <i class="fas fa-syringe text-primary me-1"></i> <?= htmlspecialchars($up['vaccine_name']) ?>
                                        </h6>
                                        <p class="small text-muted mb-2">
                                            Child: <strong class="text-dark"><?= htmlspecialchars($up['child_name']) ?></strong>
                                        </p>
                                        <div class="small text-muted border-top pt-2">
                                            <div><i class="fas fa-hospital me-1 text-secondary"></i> <?= htmlspecialchars($up['hospital_name']) ?></div>
                                            <div><i class="fas fa-calendar-alt me-1 text-secondary"></i> <strong><?= formatDate($up['appointment_date'], 'l, d M Y') ?></strong></div>
                                            <?php if (!empty($up['appointment_time'])): ?>
                                                <div class="text-primary fw-bold"><i class="fas fa-clock me-1 text-primary"></i> <?= formatTime($up['appointment_time']) ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($up['location'])): ?>
                                                <div class="text-truncate"><i class="fas fa-map-marker-alt me-1 text-secondary"></i> <?= htmlspecialchars($up['location']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <!-- Due Milestone Vaccinations (WHO Schedule) -->
                            <?php $shownMilestones = 0; foreach ($dueMilestones as $dm): if ($shownMilestones >= 3) break; $shownMilestones++; ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="p-3 bg-white rounded-3 border shadow-sm h-100 border-start border-3 <?= $dm['is_overdue'] ? 'border-danger' : 'border-warning' ?>" style="background: var(--bg-card, #fff);">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <?php if ($dm['is_overdue']): ?>
                                                <span class="badge bg-danger">Overdue by <?= abs($dm['days_diff']) ?>d</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Due in <?= $dm['days_diff'] ?>d</span>
                                            <?php endif; ?>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($dm['age_group']) ?></span>
                                        </div>
                                        <h6 class="fw-bold mb-1 text-truncate">
                                            <i class="fas fa-shield-alt text-warning me-1"></i> <?= htmlspecialchars($dm['vaccine_name']) ?>
                                        </h6>
                                        <p class="small text-muted mb-2">
                                            Child: <strong class="text-dark"><?= htmlspecialchars($dm['child_name']) ?></strong>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center border-top pt-2">
                                            <span class="small text-muted">Est. <?= formatDate($dm['due_date'], 'd M Y') ?></span>
                                            <a href="book-appointment.php?child_id=<?= $dm['child_id'] ?>&vaccine_id=<?= $dm['vaccine_id'] ?>" class="btn btn-xs btn-primary rounded-pill">
                                                <i class="fas fa-calendar-check me-1"></i> Book Now
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-success d-flex align-items-center mb-4 shadow-sm">
                    <i class="fas fa-check-circle fa-2x me-3 text-success"></i>
                    <div>
                        <h6 class="fw-bold mb-1">Vaccinations Up-to-Date</h6>
                        <span class="small">No immediate pending vaccination dates for your registered children. You can book upcoming appointments at any time.</span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- KPI Metric Cards -->
            <div class="row">
                <div class="col-sm-6 col-md-3">
                    <div class="card card-stats card-round dashboard-stat-card" onclick="location.href='children.php'">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-icon">
                                    <div class="icon-big text-center icon-primary bubble-shadow-small">
                                        <i class="fas fa-baby"></i>
                                    </div>
                                </div>
                                <div class="col col-stats ms-3 ms-sm-0">
                                    <div class="numbers">
                                        <p class="card-category">Registered Children</p>
                                        <h4 class="card-title"><?= $totalChildren ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-md-3">
                    <div class="card card-stats card-round dashboard-stat-card" onclick="location.href='schedule.php'">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-icon">
                                    <div class="icon-big text-center icon-warning bubble-shadow-small">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                </div>
                                <div class="col col-stats ms-3 ms-sm-0">
                                    <div class="numbers">
                                        <p class="card-category">Upcoming Dates</p>
                                        <h4 class="card-title"><?= count($upcomingAppointments) ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-md-3">
                    <div class="card card-stats card-round dashboard-stat-card" onclick="location.href='bookings.php?filter=Pending'">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-icon">
                                    <div class="icon-big text-center icon-danger bubble-shadow-small">
                                        <i class="fas fa-hourglass-half"></i>
                                    </div>
                                </div>
                                <div class="col col-stats ms-3 ms-sm-0">
                                    <div class="numbers">
                                        <p class="card-category">Pending Requests</p>
                                        <h4 class="card-title"><?= count($pendingBookings) ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-md-3">
                    <div class="card card-stats card-round dashboard-stat-card" onclick="location.href='reports.php'">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-icon">
                                    <div class="icon-big text-center icon-success bubble-shadow-small">
                                        <i class="fas fa-syringe"></i>
                                    </div>
                                </div>
                                <div class="col col-stats ms-3 ms-sm-0">
                                    <div class="numbers">
                                        <p class="card-category">Vaccines Received</p>
                                        <h4 class="card-title"><?= $completedVaccinationsCount ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Children Quick Section & Recent Bookings -->
            <div class="row">
                <!-- My Children Cards -->
                <div class="col-md-7">
                    <div class="card card-round">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div class="card-title">
                                <i class="fas fa-baby text-primary me-2"></i> My Children
                            </div>
                            <a href="children.php" class="btn btn-sm btn-outline-primary rounded-pill">
                                <i class="fas fa-plus me-1"></i> Add / Maintain Child
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($childrenList)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-baby fa-3x mb-3 text-muted"></i>
                                    <h6>No children registered yet</h6>
                                    <p class="small mb-3">Add your child profile to start tracking vaccination dates and scheduling hospital visits.</p>
                                    <a href="children.php" class="btn btn-primary btn-sm rounded-pill">
                                        <i class="fas fa-plus me-1"></i> Register Your First Child
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($childrenList as $c): ?>
                                        <?php
                                            // Vaccines count for this child
                                            $stmtChVax = $pdo->prepare("SELECT COUNT(*) FROM vaccination_records WHERE child_id = ? AND status = 'Vaccinated'");
                                            $stmtChVax->execute([$c['child_id']]);
                                            $vaxDone = (int)$stmtChVax->fetchColumn();
                                            $pct = min(100, round(($vaxDone / 12) * 100)); // 12 standard catalog vaccines
                                        ?>
                                        <div class="list-group-item p-3 border-0 mb-2 rounded-3" style="background: rgba(21, 114, 232, 0.04);">
                                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="avatar-md">
                                                        <span class="avatar-title rounded-circle bg-<?= ($c['gender'] === 'Female') ? 'danger' : 'primary' ?> text-white fw-bold fs-5">
                                                            <?= strtoupper(substr($c['child_name'], 0, 1)) ?>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <h6 class="fw-bold mb-0">
                                                            <a href="child-details.php?id=<?= $c['child_id'] ?>" class="text-decoration-none">
                                                                <?= htmlspecialchars($c['child_name']) ?>
                                                            </a>
                                                        </h6>
                                                        <span class="small text-muted">
                                                            <?= htmlspecialchars($c['gender']) ?> &bull; Born <?= formatDate($c['date_of_birth']) ?> (<?= calculateAge($c['date_of_birth']) ?>)
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="text-end me-2 d-none d-sm-block">
                                                        <span class="small fw-bold text-success"><?= $vaxDone ?> / 12 Doses</span>
                                                        <div class="progress" style="width: 90px; height: 6px;">
                                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= $pct ?>%"></div>
                                                        </div>
                                                    </div>
                                                    <a href="child-details.php?id=<?= $c['child_id'] ?>" class="btn btn-xs btn-outline-primary" title="View Profile & Vaccines">
                                                        <i class="fas fa-eye"></i> Profile
                                                    </a>
                                                    <a href="book-appointment.php?child_id=<?= $c['child_id'] ?>" class="btn btn-xs btn-primary text-white" title="Book Vaccine">
                                                        <i class="fas fa-calendar-plus"></i> Book
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Bookings Queue -->
                <div class="col-md-5">
                    <div class="card card-round">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div class="card-title">
                                <i class="fas fa-history text-info me-2"></i> Recent Bookings
                            </div>
                            <a href="bookings.php" class="btn btn-sm btn-outline-info rounded-pill">
                                View All
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($recentBookings)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-calendar-times fa-2x mb-2 text-muted"></i>
                                    <p class="small mb-0">No booking requests submitted yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>Child & Vaccine</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentBookings as $rb): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-bold small"><?= htmlspecialchars($rb['child_name']) ?></div>
                                                        <span class="text-muted small text-truncate d-block" style="max-width: 140px;"><?= htmlspecialchars($rb['vaccine_name']) ?></span>
                                                    </td>
                                                    <td class="small">
                                                        <div><?= formatDate($rb['appointment_date'], 'd M Y') ?></div>
                                                        <?php if (!empty($rb['appointment_time'])): ?>
                                                            <div class="text-primary fw-bold" style="font-size: 0.72rem;"><i class="far fa-clock me-1"></i><?= formatTime($rb['appointment_time']) ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?= getStatusBadge($rb['status']) ?>
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

            <!-- Quick Action Shortcuts (Kaiadmin Admin-Style) -->
            <div class="row mt-2">
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card text-center p-3 shadow-sm dashboard-shortcut-card h-100" onclick="location.href='children.php'">
                        <i class="fas fa-baby fa-2x text-primary mb-2 shortcut-icon"></i>
                        <h6 class="fw-bold mb-1">Details of Child</h6>
                        <p class="text-muted small mb-2">Update and maintain child details & immunization</p>
                        <a href="children.php" class="btn btn-sm btn-light border btn-shortcut">Manage Children</a>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card text-center p-3 shadow-sm dashboard-shortcut-card h-100" onclick="location.href='schedule.php'">
                        <i class="fas fa-calendar-alt fa-2x text-warning mb-2 shortcut-icon"></i>
                        <h6 class="fw-bold mb-1">Vaccination Dates</h6>
                        <p class="text-muted small mb-2">Get notified about upcoming due dates</p>
                        <a href="schedule.php" class="btn btn-sm btn-light border btn-shortcut">View Schedule</a>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card text-center p-3 shadow-sm dashboard-shortcut-card h-100" onclick="location.href='book-appointment.php'">
                        <i class="fas fa-hospital fa-2x text-info mb-2 shortcut-icon"></i>
                        <h6 class="fw-bold mb-1">Book Hospital</h6>
                        <p class="text-muted small mb-2">Search accredited hospitals & schedule slots</p>
                        <a href="book-appointment.php" class="btn btn-sm btn-light border btn-shortcut">Search & Book</a>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card text-center p-3 shadow-sm dashboard-shortcut-card h-100" onclick="location.href='reports.php'">
                        <i class="fas fa-file-medical-alt fa-2x text-success mb-2 shortcut-icon"></i>
                        <h6 class="fw-bold mb-1">Vaccination Reports</h6>
                        <p class="text-muted small mb-2">Previous vaccination records of infants</p>
                        <a href="reports.php" class="btn btn-sm btn-light border btn-shortcut">View Reports</a>
                    </div>
                </div>
            </div>

        </div>
    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
