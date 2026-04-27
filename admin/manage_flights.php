<?php
ob_start();
session_start();
require_once '../config/db.php';
require_once '../config/airports.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /dbweb/auth/login.php');
    exit();
}

// Load airlines for selects
$airlinerOpts = $conn->query("SELECT * FROM Airliner WHERE Airln_Status='ACTIVE' ORDER BY Airln_Name")->fetch_all(MYSQLI_ASSOC);

// ── ADD FLIGHT ────────────────────────────────────────────────
if (isset($_POST['add_flight'])) {
    $airlnId = (int)$_POST['airln_id'];
    $flghtNo = strtoupper(trim($_POST['flght_no']));
    $depart  = strtoupper(trim($_POST['flght_depart']));
    $arrival = strtoupper(trim($_POST['flght_arrival']));
    $depDate = $_POST['depart_date'] . ' ' . $_POST['depart_time'];
    $arrDate = $_POST['arrive_date'] . ' ' . $_POST['arrive_time'];
    $fare    = (float)$_POST['flght_fare'];
    $seats   = (int)$_POST['total_seats'];

    $st = $conn->prepare("
        INSERT INTO Flight (Flght_AirlnID, Flght_No, Flght_Depart, Flght_Arrival, Flght_DepartDate, Flght_ArriveDate, Flght_SeatAvail, Flght_Fare, Flght_TotalSeats)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $st->bind_param('isssssdii', $airlnId, $flghtNo, $depart, $arrival, $depDate, $arrDate, $fare, $seats, $seats);
    if ($st->execute()) {
        $_SESSION['flash'] = ['type'=>'success','msg'=>"Flight $flghtNo added successfully."];
    } else {
        $_SESSION['flash'] = ['type'=>'danger','msg'=>'Failed to add flight: ' . $conn->error];
    }
    header('Location: /dbweb/admin/manage_flights.php');
    exit();
}

// ── CANCEL FLIGHT ─────────────────────────────────────────────
if (isset($_GET['cancel'])) {
    $fid = (int)$_GET['cancel'];
    $st  = $conn->prepare("UPDATE Flight SET Flght_Status='CANCELLED' WHERE Flght_ID=?");
    $st->bind_param('i', $fid);
    $st->execute();
    $_SESSION['flash'] = ['type'=>'warning','msg'=>'Flight cancelled.'];
    header('Location: /dbweb/admin/manage_flights.php');
    exit();
}

// ── RESTORE FLIGHT ────────────────────────────────────────────
if (isset($_GET['restore'])) {
    $fid = (int)$_GET['restore'];
    $st  = $conn->prepare("UPDATE Flight SET Flght_Status='SCHEDULED' WHERE Flght_ID=?");
    $st->bind_param('i', $fid);
    $st->execute();
    $_SESSION['flash'] = ['type'=>'success','msg'=>'Flight restored to scheduled.'];
    header('Location: /dbweb/admin/manage_flights.php');
    exit();
}

// Fetch all flights
$flights = $conn->query("
    SELECT f.*, a.Airln_Name, a.Airln_Code
    FROM Flight f
    JOIN Airliner a ON f.Flght_AirlnID = a.Airln_ID
    ORDER BY f.Flght_DepartDate DESC
")->fetch_all(MYSQLI_ASSOC);

$title = 'Manage Flights';
include '../layout/layout.php';
?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0">Manage Flights</h4>
            <a href="/dbweb/admin/dashboard.php" style="font-size:13px; color:#0086FF;">← Back to Dashboard</a>
        </div>
        <button class="btn btn-trip" data-bs-toggle="modal" data-bs-target="#addFlightModal">
            <i class="bi bi-plus-circle me-2"></i>Add Flight
        </button>
    </div>

    <!-- Flash -->
    <?php if (!empty($_SESSION['flash'])): $f = $_SESSION['flash']; unset($_SESSION['flash']); ?>
        <div class="alert alert-<?= $f['type'] ?> rounded-3 mb-3" style="font-size:14px;"><?= htmlspecialchars($f['msg']) ?></div>
    <?php endif; ?>

    <!-- Flights Table -->
    <div class="trip-card p-4">
        <div class="table-responsive">
            <table class="table trip-table mb-0">
                <thead>
                    <tr>
                        <th>Flight</th>
                        <th>Route</th>
                        <th>Departure</th>
                        <th>Arrival</th>
                        <th>Base Fare</th>
                        <th>Seats Left</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($flights as $fl): ?>
                    <tr>
                        <td>
                            <div class="fw-bold" style="color:#0086FF;"><?= htmlspecialchars($fl['Airln_Code']) ?></div>
                            <div style="font-size:12px; color:#aaa;"><?= htmlspecialchars($fl['Flght_No']) ?></div>
                        </td>
                        <td style="font-size:13px;">
                            <?= htmlspecialchars(airportCity($fl['Flght_Depart'])) ?> (<?= $fl['Flght_Depart'] ?>) →
                            <br><?= htmlspecialchars(airportCity($fl['Flght_Arrival'])) ?> (<?= $fl['Flght_Arrival'] ?>)
                        </td>
                        <td style="font-size:13px;"><?= date('d M Y H:i', strtotime($fl['Flght_DepartDate'])) ?></td>
                        <td style="font-size:13px;"><?= date('d M Y H:i', strtotime($fl['Flght_ArriveDate'])) ?></td>
                        <td class="price-text fw-bold">₱<?= number_format($fl['Flght_Fare'], 0) ?></td>
                        <td style="font-size:13px;"><?= $fl['Flght_SeatAvail'] ?> / <?= $fl['Flght_TotalSeats'] ?></td>
                        <td>
                            <?php $sc = match($fl['Flght_Status']) {
                                'SCHEDULED'=>'badge-trip-green','CANCELLED'=>'badge-trip-red',default=>'badge-trip-orange'
                            }; ?>
                            <span class="badge <?= $sc ?> px-2 py-1" style="font-size:11px;"><?= ucfirst(strtolower($fl['Flght_Status'])) ?></span>
                        </td>
                        <td>
                            <?php if ($fl['Flght_Status'] === 'SCHEDULED'): ?>
                                <a href="?cancel=<?= $fl['Flght_ID'] ?>"
                                   onclick="return confirm('Cancel this flight?')"
                                   class="btn btn-sm btn-outline-danger" style="font-size:12px;">Cancel</a>
                            <?php else: ?>
                                <a href="?restore=<?= $fl['Flght_ID'] ?>"
                                   class="btn btn-sm btn-outline-success" style="font-size:12px;">Restore</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ====== ADD FLIGHT MODAL ====== -->
<div class="modal fade" id="addFlightModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Add New Flight</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
            <div class="modal-body pt-3">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" style="font-size:13px;">Flight Number</label>
                        <input type="text" name="flght_no" class="form-control text-uppercase" placeholder="e.g. PR501" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" style="font-size:13px;">Airline</label>
                        <select name="airln_id" class="form-select" required>
                            <option value="" disabled selected>Select airline</option>
                            <?php foreach ($airlinerOpts as $al): ?>
                                <option value="<?= $al['Airln_ID'] ?>"><?= htmlspecialchars($al['Airln_Name']) ?> (<?= $al['Airln_Code'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" style="font-size:13px;">Total Seats</label>
                        <input type="number" name="total_seats" class="form-control" value="180" min="1" max="600" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold" style="font-size:13px;">From (IATA Code)</label>
                        <select name="flght_depart" class="form-select" required>
                            <option value="" disabled selected>Select origin</option>
                            <?php foreach ($AIRPORTS as $code => $ap): ?>
                                <option value="<?= $code ?>"><?= htmlspecialchars($ap['city']) ?> (<?= $code ?>) — <?= $ap['country'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" style="font-size:13px;">To (IATA Code)</label>
                        <select name="flght_arrival" class="form-select" required>
                            <option value="" disabled selected>Select destination</option>
                            <?php foreach ($AIRPORTS as $code => $ap): ?>
                                <option value="<?= $code ?>"><?= htmlspecialchars($ap['city']) ?> (<?= $code ?>) — <?= $ap['country'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold" style="font-size:13px;">Departure Date</label>
                        <input type="date" name="depart_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold" style="font-size:13px;">Departure Time</label>
                        <input type="time" name="depart_time" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold" style="font-size:13px;">Arrival Date</label>
                        <input type="date" name="arrive_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold" style="font-size:13px;">Arrival Time</label>
                        <input type="time" name="arrive_time" class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold" style="font-size:13px;">Base Fare (₱) <span class="text-muted fw-normal">Economy price</span></label>
                        <input type="number" name="flght_fare" class="form-control" step="0.01" min="0" placeholder="0.00" required>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="p-3 rounded-3 w-100" style="background:#f0f8ff; font-size:13px; color:#0086FF;">
                            <i class="bi bi-info-circle me-1"></i>
                            Business = ×2.5 &nbsp;|&nbsp; First Class = ×4
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-trip-outline" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="add_flight" class="btn btn-trip px-4">Add Flight</button>
            </div>
            </form>
        </div>
    </div>
</div>

<?php include '../layout/footer.php'; ?>
