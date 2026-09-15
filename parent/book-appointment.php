<?php
/**
 * Book Hospital - Search Facilities & Schedule Appointment
 * Child Vaccination Management System (VMS)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
requireParent();

$currentParent = getCurrentParent();
$parentId = $currentParent['id'];

// Prefill parameters from GET
$prefillChildId   = (int)($_GET['child_id'] ?? 0);
$prefillVaccineId = (int)($_GET['vaccine_id'] ?? 0);
$prefillHospitalId = (int)($_GET['hospital_id'] ?? 0);
$searchQuery      = trim($_GET['search'] ?? '');
$locationFilter   = trim($_GET['location'] ?? '');

// Handle Booking Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $childId         = (int)($_POST['child_id'] ?? 0);
    $hospitalId      = (int)($_POST['hospital_id'] ?? 0);
    $vaccineId       = (int)($_POST['vaccine_id'] ?? 0);
    $appointmentDate = trim($_POST['appointment_date'] ?? '');

    if (!$childId || !$hospitalId || !$vaccineId || empty($appointmentDate)) {
        setFlash('error', 'Please fill in all required fields (Child, Hospital, Vaccine, and Appointment Date).');
    } elseif ($appointmentDate < date('Y-m-d')) {
        setFlash('error', 'Appointment date cannot be in the past. Please choose today or a future date.');
    } else {
        try {
            // Verify child belongs to logged-in parent
            $stmtCh = $pdo->prepare("SELECT child_name FROM children WHERE child_id = ? AND parent_id = ?");
            $stmtCh->execute([$childId, $parentId]);
            $child = $stmtCh->fetch();

            if (!$child) {
                setFlash('error', 'Invalid child selected. Please choose a child from your registered account.');
            } else {
                // Check if an active booking already exists for this child and vaccine
                $stmtCheck = $pdo->prepare("SELECT booking_id FROM bookings WHERE child_id = ? AND vaccine_id = ? AND status IN ('Pending', 'Approved', 'Completed')");
                $stmtCheck->execute([$childId, $vaccineId]);
                if ($stmtCheck->fetch()) {
                    setFlash('warning', 'An active or completed appointment already exists for this child and vaccine. Please review your bookings.');
                } else {
                    // Verify that the vaccine is in stock at the selected hospital
                    $stmtStock = $pdo->prepare("
                        SELECT COALESCE(hv.stock_status, 'Available') AS stock_status
                        FROM vaccines v
                        LEFT JOIN hospital_vaccines hv ON v.vaccine_id = hv.vaccine_id AND hv.hospital_id = ?
                        WHERE v.vaccine_id = ?
                    ");
                    $stmtStock->execute([$hospitalId, $vaccineId]);
                    $chosenStock = $stmtStock->fetchColumn() ?: 'Available';

                    if ($chosenStock === 'Unavailable') {
                        setFlash('error', 'The selected vaccine is currently out of stock at this hospital. Please choose an alternative hospital where this vaccine is available.');
                    } else {
                        $bookingDate = date('Y-m-d');
                        $status = 'Pending';

                        $stmtIns = $pdo->prepare("
                            INSERT INTO bookings (child_id, hospital_id, vaccine_id, admin_id, booking_date, appointment_date, status, approval_date) 
                            VALUES (?, ?, ?, NULL, ?, ?, ?, NULL)
                        ");
                        $stmtIns->execute([$childId, $hospitalId, $vaccineId, $bookingDate, $appointmentDate, $status]);
                        $newBookingId = $pdo->lastInsertId();

                        // Get hospital name
                        $hospName = $pdo->query("SELECT hospital_name FROM hospitals WHERE hospital_id = $hospitalId")->fetchColumn();
                        $vacName  = $pdo->query("SELECT vaccine_name FROM vaccines WHERE vaccine_id = $vaccineId")->fetchColumn();

                        setFlash('success', "Appointment request submitted successfully! Your request for '{$child['child_name']}' ({$vacName}) has been sent to {$hospName}. The hospital will review and assign your appointment date & time.");
                        header('Location: bookings.php');
                        exit;
                    }
                }
            }
        } catch (Exception $e) {
            setFlash('error', 'Error scheduling appointment: ' . $e->getMessage());
        }
    }
}

// Fetch Parent's Children
$children = [];
// Fetch Catalog Vaccines
$vaccines = [];
// Fetch Hospitals (Filtered)
$hospitals = [];
$distinctLocations = [];
$stockMap = [];
$allHospitalsMap = [];

if ($pdo) {
    try {
        $stmtCh = $pdo->prepare("SELECT * FROM children WHERE parent_id = ? ORDER BY child_name ASC");
        $stmtCh->execute([$parentId]);
        $children = $stmtCh->fetchAll();

        // All catalog vaccines
        $vaccines = $pdo->query("SELECT * FROM vaccines ORDER BY vaccine_id ASC")->fetchAll();

        // Distinct Locations for dropdown
        $distinctLocations = $pdo->query("SELECT DISTINCT location FROM hospitals WHERE status = 'Active' AND location IS NOT NULL AND location != '' ORDER BY location ASC")->fetchAll(PDO::FETCH_COLUMN);

        // Hospitals Query
        $hospQuery = "SELECT * FROM hospitals WHERE status = 'Active'";
        $hospParams = [];

        if (!empty($searchQuery)) {
            $hospQuery .= " AND (hospital_name LIKE ? OR location LIKE ? OR address LIKE ?)";
            $searchTerm = '%' . $searchQuery . '%';
            $hospParams[] = $searchTerm;
            $hospParams[] = $searchTerm;
            $hospParams[] = $searchTerm;
        }

        if (!empty($locationFilter)) {
            $hospQuery .= " AND location = ?";
            $hospParams[] = $locationFilter;
        }

        $hospQuery .= " ORDER BY hospital_name ASC";

        $stmtHosp = $pdo->prepare($hospQuery);
        $stmtHosp->execute($hospParams);
        $hospitals = $stmtHosp->fetchAll();

        // Map of all active hospitals for client-side cross-hospital lookup
        $allActiveHosp = $pdo->query("SELECT hospital_id, hospital_name, location, address, phone FROM hospitals WHERE status = 'Active' ORDER BY hospital_name ASC")->fetchAll();
        foreach ($allActiveHosp as $ah) {
            $allHospitalsMap[$ah['hospital_id']] = $ah;
        }

        // Fetch hospital-vaccine stock map
        $stmtStocks = $pdo->query("SELECT hospital_id, vaccine_id, stock_status FROM hospital_vaccines");
        while ($row = $stmtStocks->fetch()) {
            $stockMap[$row['hospital_id']][$row['vaccine_id']] = $row['stock_status'];
        }

    } catch (Exception $e) {
        setFlash('error', 'Database error: ' . $e->getMessage());
    }
}

$pageTitle = 'Book Hospital';
$activePage = 'parent_book';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-panel">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>
    <div class="container">
        <div class="page-inner">

            <!-- Page Header -->
            <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
                <div>
                    <h3 class="fw-bold mb-1">Book Hospital Appointment</h3>
                    <h6 class="op-7 mb-0 text-muted">Search accredited healthcare facilities and schedule vaccination appointments for your children</h6>
                </div>
                <div class="ms-md-auto py-2 py-md-0">
                    <a href="bookings.php" class="btn btn-outline-primary btn-round">
                        <i class="fas fa-calendar-check me-1"></i> View My Bookings
                    </a>
                </div>
            </div>

            <?= displayFlash() ?>

            <?php if (empty($children)): ?>
                <div class="alert alert-warning d-flex align-items-center mb-4">
                    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                    <div class="d-flex justify-content-between align-items-center w-100 flex-wrap gap-2">
                        <div>
                            <strong>No Child Registered:</strong> You need to add at least one child to your account before booking a hospital vaccination schedule.
                        </div>
                        <a href="children.php" class="btn btn-sm btn-warning text-dark fw-bold rounded-pill">
                            <i class="fas fa-baby me-1"></i> Register Child Now &rarr;
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Search & Filter Card -->
            <div class="card card-round shadow-sm mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="book-appointment.php" class="row g-2 align-items-center">
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="Search hospital name, location, or address..." value="<?= htmlspecialchars($searchQuery) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select name="location" class="form-select" onchange="this.form.submit()">
                                <option value="">All Locations / Districts</option>
                                <?php foreach ($distinctLocations as $loc): ?>
                                    <option value="<?= htmlspecialchars($loc) ?>" <?= ($locationFilter === $loc) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($loc) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-fill">
                                <i class="fas fa-filter me-1"></i> Search
                            </button>
                            <a href="book-appointment.php" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Booking Section Grid: Direct Booking Form on Left, Hospital List on Right -->
            <div class="row">
                <!-- Appointment Booking Form -->
                <div class="col-lg-5 mb-4">
                    <div class="card card-round shadow-sm h-100 border-top border-primary border-3">
                        <div class="card-header bg-light">
                            <h5 class="fw-bold mb-0 text-primary">
                                <i class="fas fa-calendar-plus me-2"></i> Schedule Vaccination
                            </h5>
                            <small class="text-muted">Fill details to submit an appointment request</small>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="book-appointment.php">
                                <!-- Child Selection -->
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Select Child <span class="text-danger">*</span></label>
                                    <select name="child_id" id="booking_child_id" class="form-select" required <?= empty($children) ? 'disabled' : '' ?>>
                                        <option value="">-- Select Child --</option>
                                        <?php foreach ($children as $c): ?>
                                            <option value="<?= $c['child_id'] ?>" <?= ($prefillChildId == $c['child_id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($c['child_name']) ?> (<?= calculateAge($c['date_of_birth']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Hospital Selection -->
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Select Hospital Facility <span class="text-danger">*</span></label>
                                    <select name="hospital_id" id="booking_hospital_id" class="form-select" onchange="checkAvailability()" required>
                                        <option value="">-- Choose Healthcare Facility --</option>
                                        <?php foreach ($hospitals as $h): ?>
                                            <option value="<?= $h['hospital_id'] ?>" <?= ($prefillHospitalId == $h['hospital_id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($h['hospital_name']) ?> - <?= htmlspecialchars($h['location']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text small">You can also click "Select Facility" on any card to auto-choose.</div>
                                </div>

                                <!-- Vaccine Selection -->
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Select Vaccine <span class="text-danger">*</span></label>
                                    <select name="vaccine_id" id="booking_vaccine_id" class="form-select" onchange="checkAvailability()" required>
                                        <option value="">-- Choose Vaccine --</option>
                                        <?php foreach ($vaccines as $v): ?>
                                            <option value="<?= $v['vaccine_id'] ?>" <?= ($prefillVaccineId == $v['vaccine_id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($v['vaccine_name']) ?> (<?= htmlspecialchars($v['age_group']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Appointment Date -->
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Preferred Appointment Date <span class="text-danger">*</span></label>
                                    <input type="date" name="appointment_date" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+2 days')) ?>" required>
                                    <div class="form-text small">Selected hospital will review request, approve it, and assign confirmed appointment time.</div>
                                </div>

                                <!-- Real-time Stock Availability & Hospital Cross-Suggestions Container -->
                                <div id="availability_alert_container" class="mb-3"></div>

                                <button type="submit" id="submit_booking_btn" class="btn btn-primary w-100 btn-round py-2 shadow-sm text-white" <?= empty($children) ? 'disabled' : '' ?>>
                                    <i class="fas fa-check-circle me-1"></i> Submit Appointment Request
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Hospital Cards Directory (Right Side) -->
                <div class="col-lg-7 mb-4">
                    <div class="card card-round shadow-sm h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div class="card-title">
                                <i class="fas fa-hospital text-primary me-2"></i> Accredited Vaccination Centers
                            </div>
                            <span class="badge bg-success"><?= count($hospitals) ?> Available Centers</span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($hospitals)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fas fa-hospital-alt fa-3x mb-3 text-muted"></i>
                                    <h6>No accredited hospitals matched your search</h6>
                                    <a href="book-appointment.php" class="btn btn-sm btn-outline-primary rounded-pill mt-2">Clear Search Filters</a>
                                </div>
                            <?php else: ?>
                                <div class="row g-3">
                                    <?php foreach ($hospitals as $hosp): ?>
                                        <div class="col-md-12">
                                            <div class="p-3 border rounded-3 bg-white shadow-sm d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 dashboard-stat-card hospital-card-item" data-hospital-id="<?= $hosp['hospital_id'] ?>" style="background: var(--bg-card, #fff);">
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="avatar-md flex-shrink-0">
                                                        <span class="avatar-title rounded-3 bg-primary text-white fs-4">
                                                            <i class="fas fa-hospital-symbol"></i>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <h6 class="fw-bold mb-1 text-primary">
                                                            <?= htmlspecialchars($hosp['hospital_name']) ?>
                                                        </h6>
                                                        <div class="small text-muted mb-1">
                                                            <i class="fas fa-map-marker-alt text-danger me-1"></i>
                                                            <strong><?= htmlspecialchars($hosp['location']) ?></strong> &bull; <?= htmlspecialchars($hosp['address']) ?>
                                                        </div>
                                                        <div class="small text-muted">
                                                            <?php if (!empty($hosp['phone'])): ?>
                                                                <span class="me-3"><i class="fas fa-phone text-success me-1"></i> <?= htmlspecialchars($hosp['phone']) ?></span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($hosp['email'])): ?>
                                                                <span><i class="fas fa-envelope text-info me-1"></i> <?= htmlspecialchars($hosp['email']) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-shrink-0 text-sm-end w-100 w-sm-auto">
                                                    <span class="badge bg-success mb-2 d-inline-block hospital-vax-status">Accredited</span>
                                                    <button type="button" class="btn btn-sm btn-primary d-block w-100" onclick="chooseHospital(<?= $hosp['hospital_id'] ?>, '<?= htmlspecialchars(addslashes($hosp['hospital_name'])) ?>')">
                                                        <i class="fas fa-calendar-check me-1"></i> Select Facility
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        const stockMap = <?= json_encode($stockMap) ?>;
        const allHospitals = <?= json_encode($allHospitalsMap) ?>;

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function updateHospitalCards(vaccineId) {
            const cardItems = document.querySelectorAll('.hospital-card-item');
            cardItems.forEach(card => {
                const hId = card.getAttribute('data-hospital-id');
                const badge = card.querySelector('.hospital-vax-status');
                if (!badge) return;

                if (!vaccineId) {
                    badge.className = 'badge bg-success mb-2 d-inline-block hospital-vax-status';
                    badge.innerHTML = '<i class="fas fa-check me-1"></i> Accredited';
                } else {
                    const status = (stockMap[hId] && stockMap[hId][vaccineId]) ? stockMap[hId][vaccineId] : 'Available';
                    if (status === 'Unavailable') {
                        badge.className = 'badge bg-danger mb-2 d-inline-block hospital-vax-status';
                        badge.innerHTML = '<i class="fas fa-times-circle me-1"></i> Out of Stock';
                    } else {
                        badge.className = 'badge bg-success mb-2 d-inline-block hospital-vax-status';
                        badge.innerHTML = '<i class="fas fa-check-circle me-1"></i> In Stock';
                    }
                }
            });
        }

        function checkAvailability() {
            const hospitalSelect = document.getElementById('booking_hospital_id');
            const vaccineSelect = document.getElementById('booking_vaccine_id');
            const alertBox = document.getElementById('availability_alert_container');
            const submitBtn = document.getElementById('submit_booking_btn');

            const hospitalId = hospitalSelect ? hospitalSelect.value : '';
            const vaccineId = vaccineSelect ? vaccineSelect.value : '';
            const vaccineText = (vaccineSelect && vaccineSelect.selectedIndex > 0) ? vaccineSelect.options[vaccineSelect.selectedIndex].text : 'Selected vaccine';
            const hospitalText = (hospitalSelect && hospitalSelect.selectedIndex > 0) ? hospitalSelect.options[hospitalSelect.selectedIndex].text : 'Selected hospital';

            updateHospitalCards(vaccineId);

            if (!hospitalId || !vaccineId) {
                if (alertBox) alertBox.innerHTML = '';
                if (submitBtn) submitBtn.disabled = <?= empty($children) ? 'true' : 'false' ?>;
                return;
            }

            const currentStatus = (stockMap[hospitalId] && stockMap[hospitalId][vaccineId]) ? stockMap[hospitalId][vaccineId] : 'Available';

            if (currentStatus === 'Unavailable') {
                if (submitBtn) submitBtn.disabled = true;

                // Find alternative hospitals where this vaccine is available
                const alternatives = [];
                for (const [hId, hosp] of Object.entries(allHospitals)) {
                    if (hId == hospitalId) continue;
                    const hStatus = (stockMap[hId] && stockMap[hId][vaccineId]) ? stockMap[hId][vaccineId] : 'Available';
                    if (hStatus !== 'Unavailable') {
                        alternatives.push(hosp);
                    }
                }

                let altHtml = '';
                if (alternatives.length > 0) {
                    altHtml = `
                        <div class="mt-2 pt-2 border-top border-danger border-opacity-25">
                            <strong class="d-block mb-1 text-dark small"><i class="fas fa-hospital me-1 text-success"></i> Available at these other healthcare facilities:</strong>
                            <div class="d-flex flex-column gap-2 mt-2">
                    `;
                    alternatives.forEach(alt => {
                        altHtml += `
                            <div class="d-flex justify-content-between align-items-center bg-white p-2 rounded border border-success-subtle shadow-sm">
                                <div>
                                    <span class="fw-bold text-dark small">${escapeHtml(alt.hospital_name)}</span>
                                    <span class="badge bg-light text-muted border ms-1" style="font-size: 0.72rem;">${escapeHtml(alt.location || '')}</span>
                                    ${alt.phone ? `<div class="small text-muted" style="font-size: 0.75rem;"><i class="fas fa-phone fa-xs me-1 text-success"></i> ${escapeHtml(alt.phone)}</div>` : ''}
                                </div>
                                <button type="button" class="btn btn-xs btn-outline-success btn-round flex-shrink-0" onclick="chooseHospital(${alt.hospital_id}, '${escapeHtml(alt.hospital_name)}')">
                                    <i class="fas fa-exchange-alt me-1"></i> Switch Hospital
                                </button>
                            </div>
                        `;
                    });
                    altHtml += `</div></div>`;
                } else {
                    altHtml = `
                        <div class="mt-2 small text-muted">
                            <i class="fas fa-info-circle me-1"></i> This vaccine is currently not available at any other registered hospital.
                        </div>
                    `;
                }

                if (alertBox) {
                    alertBox.innerHTML = `
                        <div class="alert alert-danger border-danger shadow-sm mb-0 p-3">
                            <div class="d-flex align-items-start gap-2">
                                <i class="fas fa-exclamation-triangle fa-lg text-danger mt-1 flex-shrink-0"></i>
                                <div class="w-100">
                                    <strong class="text-danger">Vaccine Out of Stock at Selected Hospital!</strong>
                                    <div class="small mt-1 text-dark">
                                        <strong>${escapeHtml(vaccineText)}</strong> is currently <u>unavailable</u> at <strong>${escapeHtml(hospitalText)}</strong>.
                                        Please switch to an available hospital below to proceed with booking.
                                    </div>
                                    ${altHtml}
                                </div>
                            </div>
                        </div>
                    `;
                }
            } else {
                if (submitBtn) submitBtn.disabled = <?= empty($children) ? 'true' : 'false' ?>;
                if (alertBox) {
                    alertBox.innerHTML = `
                        <div class="alert alert-success py-2 px-3 small mb-0 d-flex align-items-center gap-2">
                            <i class="fas fa-check-circle text-success fa-lg flex-shrink-0"></i>
                            <div>
                                <strong>In Stock:</strong> Vaccine is available at this healthcare center.
                            </div>
                        </div>
                    `;
                }
            }
        }

        function chooseHospital(hospitalId, hospitalName) {
            const selectEl = document.getElementById('booking_hospital_id');
            if (selectEl) {
                selectEl.value = hospitalId;
                selectEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                selectEl.classList.add('border-success');
                setTimeout(() => selectEl.classList.remove('border-success'), 2000);
            }
            checkAvailability();
        }

        document.addEventListener('DOMContentLoaded', function() {
            checkAvailability();
        });
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
