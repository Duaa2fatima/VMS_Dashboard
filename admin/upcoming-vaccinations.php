<?php
/**
 * Upcoming Date of Vaccination - Admin View
 * Child Vaccination Management System (VMS)
 */
$pageTitle = 'Date of Vaccination - Upcoming Schedule';
$activePage = 'admin_upcoming';

require_once __DIR__ . '/../includes/header.php';

$filterRange = $_GET['range'] ?? '30'; // 7, 30, all
$scheduledBookings = [];
$calculatedDueList = [];

if ($pdo) {
    try {
        // 1. Scheduled / Approved appointments from today onwards
        $dateLimitSql = "";
        if ($filterRange === '7') {
            $dateLimitSql = "AND b.appointment_date <= DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY)";
        } elseif ($filterRange === '30') {
            $dateLimitSql = "AND b.appointment_date <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)";
        }

        $stmt = $pdo->query("SELECT b.*, c.child_name, c.date_of_birth, c.gender, 
                                    p.name as parent_name, p.phone as parent_phone, p.email as parent_email, 
                                    v.vaccine_name, v.age_group, 
                                    h.hospital_name, h.location as hospital_location
            FROM bookings b
            JOIN children c ON b.child_id = c.child_id
            JOIN parents p ON c.parent_id = p.parent_id
            JOIN vaccines v ON b.vaccine_id = v.vaccine_id
            JOIN hospitals h ON b.hospital_id = h.hospital_id
            WHERE b.status IN ('Approved', 'Pending') AND b.appointment_date >= CURRENT_DATE $dateLimitSql
            ORDER BY b.appointment_date ASC");
        $scheduledBookings = $stmt->fetchAll();

        // 2. Also calculate upcoming vaccine milestones for registered children
        $children = $pdo->query("SELECT c.*, p.name as parent_name, p.phone as parent_phone FROM children c JOIN parents p ON c.parent_id = p.parent_id")->fetchAll();
        $vaccines = $pdo->query("SELECT * FROM vaccines WHERE stock_status = 'Available'")->fetchAll();
        $givenRows = $pdo->query("SELECT child_id, vaccine_id FROM vaccination_records vr JOIN bookings b ON vr.booking_id = b.booking_id WHERE vr.status = 'Vaccinated'")->fetchAll();
        
        $givenMap = [];
        foreach ($givenRows as $gr) {
            $givenMap[$gr['child_id'] . '_' . $gr['vaccine_id']] = true;
        }

        $today = new DateTime('today');
        $maxDays = ($filterRange === '7') ? 7 : (($filterRange === '30') ? 30 : 180);

        foreach ($children as $c) {
            foreach ($vaccines as $v) {
                $key = $c['child_id'] . '_' . $v['vaccine_id'];
                if (!isset($givenMap[$key])) {
                    $dueStr = calculateDueDate($c['date_of_birth'], $v['age_group']);
                    $dueDate = new DateTime($dueStr);
                    $diffDays = (int)$today->diff($dueDate)->format('%r%a');

                    // If upcoming within filter window (from today up to maxDays)
                    if ($diffDays >= 0 && $diffDays <= $maxDays) {
                        $calculatedDueList[] = [
                            'child_id'     => $c['child_id'],
                            'child_name'   => $c['child_name'],
                            'parent_name'  => $c['parent_name'],
                            'parent_phone' => $c['parent_phone'],
                            'vaccine_name' => $v['vaccine_name'],
                            'age_group'    => $v['age_group'],
                            'due_date'     => $dueStr,
                            'days_left'    => $diffDays
                        ];
                    }
                }
            }
        }

        // Sort calculated dues by date
        usort($calculatedDueList, function($a, $b) {
            return strcmp($a['due_date'], $b['due_date']);
        });

    } catch (Exception $e) {
        setFlash('error', 'Error loading schedule: ' . $e->getMessage());
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
                    <h3 class="fw-bold mb-1"><i class="fas fa-calendar-alt text-primary me-2"></i> Date of Vaccination (Upcoming Dates)</h3>
                    <ul class="breadcrumbs mb-0 ps-0 list-unstyled d-flex gap-2 text-muted small">
                        <li><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                        <li>/</li>
                        <li class="active">Upcoming Vaccination Schedule</li>
                    </ul>
                </div>

                <!-- Range Selector -->
                <div class="btn-group" role="group">
                    <a href="upcoming-vaccinations.php?range=7" class="btn btn-sm <?= $filterRange === '7' ? 'btn-primary' : 'btn-outline-primary' ?>">Next 7 Days</a>
                    <a href="upcoming-vaccinations.php?range=30" class="btn btn-sm <?= $filterRange === '30' ? 'btn-primary' : 'btn-outline-primary' ?>">Next 30 Days</a>
                    <a href="upcoming-vaccinations.php?range=all" class="btn btn-sm <?= $filterRange === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">All Future Dates</a>
                </div>
            </div>

            <?= displayFlash() ?>

            <!-- Section 1: Booked Hospital Appointments -->
            <div class="card card-round shadow-sm mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <div class="card-title text-dark">
                        <i class="fas fa-clock text-warning me-2"></i> Scheduled Hospital Vaccination Appointments (<?= count($scheduledBookings) ?>)
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover datatable-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Appointment Date</th>
                                    <th>Booking Ref</th>
                                    <th>Child Profile</th>
                                    <th>Vaccine & Age Group</th>
                                    <th>Assigned Hospital</th>
                                    <th>Parent Contact</th>
                                    <th>Status</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($scheduledBookings)): ?>
                                    <?php foreach ($scheduledBookings as $b): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-primary"><i class="fas fa-calendar-day me-1"></i> <?= formatDate($b['appointment_date']) ?></div>
                                                <small class="text-muted">Booked: <?= formatDate($b['booking_date']) ?></small>
                                            </td>
                                            <td class="font-monospace fw-bold text-muted">
                                                #BK-<?= str_pad($b['booking_id'], 4, '0', STR_PAD_LEFT) ?>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($b['child_name']) ?></div>
                                                <small class="text-muted">DOB: <?= formatDate($b['date_of_birth']) ?> (<?= htmlspecialchars($b['gender']) ?>)</small>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($b['vaccine_name']) ?></strong>
                                                <div class="small text-muted"><span class="badge bg-light text-dark border"><?= htmlspecialchars($b['age_group']) ?></span></div>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark"><i class="fas fa-hospital me-1 text-muted"></i> <?= htmlspecialchars($b['hospital_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($b['hospital_location'] ?: '') ?></small>
                                            </td>
                                            <td>
                                                <div><?= htmlspecialchars($b['parent_name']) ?></div>
                                                <small class="text-muted"><i class="fas fa-phone-alt me-1"></i> <?= htmlspecialchars($b['parent_phone'] ?: 'N/A') ?></small>
                                            </td>
                                            <td><?= getStatusBadge($b['status']) ?></td>
                                            <td class="text-center">
                                                <a href="child-details.php?id=<?= $b['child_id'] ?>" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Child Profile">
                                                    <i class="fas fa-baby me-1"></i> Profile
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Section 2: Calculated Immunization Schedule Milestones -->
            <div class="card card-round shadow-sm">
                <div class="card-header bg-light">
                    <div class="card-title text-dark">
                        <i class="fas fa-bell text-info me-2"></i> Upcoming Due Vaccination Milestones Across All Registered Children
                    </div>
                    <small class="text-muted">Calculated automatically according to child date of birth and recommended vaccine age groups</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover datatable-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Calculated Due Date</th>
                                    <th>Child Name</th>
                                    <th>Recommended Vaccine</th>
                                    <th>Target Age Group</th>
                                    <th>Parent Contact</th>
                                    <th>Timeline</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($calculatedDueList)): ?>
                                    <?php foreach ($calculatedDueList as $due): ?>
                                        <tr>
                                            <td>
                                                <span class="fw-bold text-dark"><?= formatDate($due['due_date']) ?></span>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-primary"><?= htmlspecialchars($due['child_name']) ?></div>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($due['vaccine_name']) ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($due['age_group']) ?></span>
                                            </td>
                                            <td>
                                                <div><?= htmlspecialchars($due['parent_name']) ?></div>
                                                <small class="text-muted"><i class="fas fa-phone-alt me-1"></i> <?= htmlspecialchars($due['parent_phone'] ?: 'N/A') ?></small>
                                            </td>
                                            <td>
                                                <?php if ($due['days_left'] == 0): ?>
                                                    <span class="badge bg-danger">Due Today</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info text-white">In <?= $due['days_left'] ?> days</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <a href="child-details.php?id=<?= $due['child_id'] ?>" class="btn btn-sm btn-light border">
                                                    View Child
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
