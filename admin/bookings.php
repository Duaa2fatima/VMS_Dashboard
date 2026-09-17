<?php
/**
 * Master Booking Details - Admin View
 * Child Vaccination Management System (VMS)
 */
$pageTitle = 'Master Booking Details';
$activePage = 'admin_bookings';

require_once __DIR__ . '/../includes/header.php';

$filterStatus = $_GET['status'] ?? 'all';
$bookings = [];

if ($pdo) {
    try {
        $whereSql = "";
        $params = [];
        if ($filterStatus !== 'all') {
            $whereSql = "WHERE b.status = ?";
            $params[] = $filterStatus;
        }

        $sql = "SELECT b.*, c.child_name, c.date_of_birth, c.gender, c.notes as child_notes,
                       p.name as parent_name, p.phone as parent_phone, p.email as parent_email, p.address as parent_address,
                       v.vaccine_name, v.age_group, v.description as vaccine_desc,
                       h.hospital_name, h.phone as hospital_phone, h.location as hospital_location, h.email as hospital_email,
                       vr.record_id, vr.vaccination_date, vr.status as record_status, vr.remarks as record_remarks,
                       adm.name as approved_by_admin
                FROM bookings b
                JOIN children c ON b.child_id = c.child_id
                JOIN parents p ON c.parent_id = p.parent_id
                JOIN vaccines v ON b.vaccine_id = v.vaccine_id
                JOIN hospitals h ON b.hospital_id = h.hospital_id
                LEFT JOIN vaccination_records vr ON b.booking_id = vr.booking_id
                LEFT JOIN admins adm ON b.admin_id = adm.admin_id
                $whereSql
                ORDER BY b.booking_id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $bookings = $stmt->fetchAll();

    } catch (Exception $e) {
        setFlash('error', 'Error loading bookings: ' . $e->getMessage());
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
                    <h3 class="fw-bold mb-1"><i class="fas fa-book-medical text-primary me-2"></i> Booking Details</h3>
                    <ul class="breadcrumbs mb-0 ps-0 list-unstyled d-flex gap-2 text-muted small">
                        <li><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                        <li>/</li>
                        <li class="active">All Parent Booking Details</li>
                    </ul>
                </div>

                <!-- Status Filter Badges -->
                <div class="btn-group" role="group">
                    <a href="bookings.php?status=all" class="btn btn-sm <?= $filterStatus === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">All (<?= count($bookings) ?>)</a>
                    <a href="bookings.php?status=Pending" class="btn btn-sm <?= $filterStatus === 'Pending' ? 'btn-warning text-dark' : 'btn-outline-warning' ?>">Pending</a>
                    <a href="bookings.php?status=Approved" class="btn btn-sm <?= $filterStatus === 'Approved' ? 'btn-info text-white' : 'btn-outline-info' ?>">Approved</a>
                    <a href="bookings.php?status=Completed" class="btn btn-sm <?= $filterStatus === 'Completed' ? 'btn-success' : 'btn-outline-success' ?>">Completed</a>
                    <a href="bookings.php?status=Rejected" class="btn btn-sm <?= $filterStatus === 'Rejected' ? 'btn-danger' : 'btn-outline-danger' ?>">Rejected</a>
                </div>
            </div>

            <?= displayFlash() ?>

            <!-- Bookings Table Card -->
            <div class="card card-round shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <div class="card-title text-dark">Parent Vaccination Bookings Directory</div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover datatable-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Booking Ref</th>
                                    <th>Appt Date</th>
                                    <th>Child Profile</th>
                                    <th>Vaccine & Age Group</th>
                                    <th>Hospital Assigned</th>
                                    <th>Parent Contact</th>
                                    <th>Status</th>
                                    <th class="text-center">Child Info</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($bookings)): ?>
                                    <?php foreach ($bookings as $b): ?>
                                        <tr>
                                            <td class="fw-bold text-primary font-monospace">
                                                #BK-<?= str_pad($b['booking_id'], 4, '0', STR_PAD_LEFT) ?>
                                                <div class="small text-muted font-sans-serif">Booked: <?= formatDate($b['booking_date']) ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= formatDate($b['appointment_date']) ?></div>
                                                <?php if (!empty($b['appointment_time'])): ?>
                                                    <div class="small text-primary fw-bold"><i class="far fa-clock me-1"></i><?= formatTime($b['appointment_time']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="child-details.php?id=<?= $b['child_id'] ?>" class="fw-bold text-dark text-decoration-none">
                                                    <?= htmlspecialchars($b['child_name']) ?>
                                                </a>
                                                <div class="small text-muted"><?= htmlspecialchars($b['gender']) ?> &bull; <?= calculateAge($b['date_of_birth']) ?></div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($b['vaccine_name']) ?></span>
                                                <div class="small text-muted"><?= htmlspecialchars($b['age_group']) ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($b['hospital_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($b['hospital_location'] ?: '') ?></small>
                                            </td>
                                            <td>
                                                <div><?= htmlspecialchars($b['parent_name']) ?></div>
                                                <small class="text-muted"><i class="fas fa-phone-alt me-1"></i> <?= htmlspecialchars($b['parent_phone'] ?: 'N/A') ?></small>
                                            </td>
                                            <td><?= getStatusBadge($b['status']) ?></td>
                                            <td class="text-center">
                                                <a href="child-details.php?id=<?= $b['child_id'] ?>" class="btn btn-sm btn-outline-info btn-round" title="Open Child Profile Details">
                                                    <i class="fas fa-baby me-1"></i> Child Info
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
