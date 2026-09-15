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
                                    <th class="text-center">Details</th>
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
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick='viewBookingDetail(<?= json_encode($b) ?>)' title="View Full Info">
                                                    <i class="fas fa-info-circle"></i> Info
                                                </button>
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

    <!-- Booking Details Modal -->
    <div class="modal fade" id="bookingDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-file-invoice me-2"></i> Parent Booking Information</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Booking Reference</label>
                            <div class="fw-bold fs-5 text-primary font-monospace" id="det_bkg_no"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Current Booking Status</label>
                            <div id="det_status"></div>
                        </div>
                        <div class="col-md-6 border-top pt-2">
                            <label class="text-muted small">Child Name</label>
                            <div class="fw-bold" id="det_child_name"></div>
                            <small class="text-muted" id="det_child_meta"></small>
                        </div>
                        <div class="col-md-6 border-top pt-2">
                            <label class="text-muted small">Parent / Guardian</label>
                            <div class="fw-bold" id="det_parent_name"></div>
                            <small class="text-muted" id="det_parent_contact"></small>
                        </div>
                        <div class="col-md-6 border-top pt-2">
                            <label class="text-muted small">Vaccine Requested</label>
                            <div class="fw-bold" id="det_vaccine"></div>
                        </div>
                        <div class="col-md-6 border-top pt-2">
                            <label class="text-muted small">Hospital Facility</label>
                            <div class="fw-bold" id="det_hospital"></div>
                        </div>
                        <div class="col-md-6 border-top pt-2">
                            <label class="text-muted small">Scheduled Appointment Date</label>
                            <div class="fw-bold" id="det_schedule"></div>
                        </div>
                        <div class="col-md-6 border-top pt-2">
                            <label class="text-muted small">Admin Approval</label>
                            <div id="det_admin_approval" class="fw-bold">-</div>
                        </div>
                        <div class="col-md-6 border-top pt-2">
                            <label class="text-muted small">Vaccination Administered Date</label>
                            <div id="det_vax_date" class="fw-bold text-success">-</div>
                        </div>
                        <div class="col-md-6 border-top pt-2">
                            <label class="text-muted small">Vaccination Record Status</label>
                            <div id="det_record_status">-</div>
                        </div>
                        <div class="col-12 border-top pt-2">
                            <label class="text-muted small">Clinical Remarks / Notes</label>
                            <div id="det_remarks" class="small text-dark">-</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    function viewBookingDetail(b) {
        document.getElementById('det_bkg_no').textContent = '#BK-' + String(b.booking_id).padStart(4, '0');
        document.getElementById('det_status').innerHTML = '<span class="badge bg-secondary">' + b.status + '</span>';
        document.getElementById('det_child_name').textContent = b.child_name;
        document.getElementById('det_child_meta').textContent = 'DOB: ' + b.date_of_birth + ' (' + b.gender + ')';
        document.getElementById('det_parent_name').textContent = b.parent_name;
        document.getElementById('det_parent_contact').textContent = (b.parent_phone || 'N/A') + ' | ' + b.parent_email;
        document.getElementById('det_vaccine').textContent = b.vaccine_name + ' (' + b.age_group + ')';
        document.getElementById('det_hospital').textContent = b.hospital_name + (b.hospital_location ? ' - ' + b.hospital_location : '');
        document.getElementById('det_schedule').textContent = b.appointment_date + (b.appointment_time ? ' at ' + b.appointment_time : '') + ' (Booked on ' + b.booking_date + ')';
        
        var approvalText = '-';
        if (b.status === 'Approved' || b.status === 'Completed') {
            approvalText = 'Approved by Hospital on ' + (b.approval_date || '-');
        } else if (b.status === 'Rejected') {
            approvalText = 'Declined by Hospital on ' + (b.approval_date || '-');
        } else {
            approvalText = 'Awaiting Hospital Schedule';
        }
        document.getElementById('det_admin_approval').textContent = approvalText;
        
        document.getElementById('det_vax_date').textContent = b.vaccination_date || 'Not yet administered';
        document.getElementById('det_record_status').textContent = b.record_status || (b.status === 'Completed' ? 'Vaccinated' : 'Pending');
        document.getElementById('det_remarks').textContent = b.record_remarks || 'None recorded';

        var modal = new bootstrap.Modal(document.getElementById('bookingDetailModal'));
        modal.show();
    }
    </script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
