<?php
session_start();
require_once '../config/db.php';
require_once '../config/airports.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /dbweb/auth/login.php');
    exit();
}

$ref = $_GET['ref'] ?? '';
if (!$ref) {
    header('Location: /dbweb/user/dashboard.php');
    exit();
}

// Fetch booking + all passenger details
$stmt = $conn->prepare("
    SELECT bk.Book_ID, bk.Book_Confirm, bk.Book_Date, bk.Book_Status, bk.Book_Total, bk.Book_Pay,
           bd.Bokde_Passenger, bd.Bokde_SeatClass, bd.Bokde_Ticket, bd.Bokde_SeatNo,
           f.Flght_No, f.Flght_DepartDate, f.Flght_ArriveDate, f.Flght_Depart, f.Flght_Arrival,
           a.Airln_Name, a.Airln_Code,
           py.Paymt_Method, py.Paymt_Transaction
    FROM Booking bk
    JOIN Bookingdetails bd ON bd.Bokde_BookID  = bk.Book_ID
    JOIN Flight f          ON f.Flght_ID       = bd.Bokde_FlghtID
    JOIN Airliner a        ON a.Airln_ID       = f.Flght_AirlnID
    LEFT JOIN Payment py   ON py.Paymt_BookID  = bk.Book_ID
    WHERE bk.Book_Confirm = ? AND bk.Book_UserID = ?
    ORDER BY bd.Bokde_ID ASC
");
$stmt->bind_param('si', $ref, $_SESSION['user_id']);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($rows)) {
    header('Location: /dbweb/user/dashboard.php');
    exit();
}

// First row holds all booking/flight info; all rows give passenger list
$booking    = $rows[0];
$passengers = $rows;
$fromCity   = airportCity($booking['Flght_Depart']);
$toCity     = airportCity($booking['Flght_Arrival']);

function flightDuration($dep, $arr) {
    $d1 = new DateTime($dep); $d2 = new DateTime($arr);
    $diff = $d1->diff($d2);
    return ($diff->days > 0 ? $diff->days . 'd ' : '') . $diff->h . 'h ' . ($diff->i > 0 ? $diff->i . 'm' : '');
}
function classLabel($c) {
    return match(strtolower($c)) { 'business' => 'Business', 'first' => 'First Class', default => 'Economy' };
}

$title = 'Booking Confirmed';
include '../layout/layout.php';
?>

<div class="container py-5" style="max-width:760px;">

    <!-- Success Banner -->
    <div class="text-center mb-4">
        <div style="width:72px;height:72px;background:#e6f9f0;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:36px;color:#1a9e5c;">
            ✓
        </div>
        <h3 class="fw-bold mb-1" style="color:#1a9e5c;">Booking Confirmed!</h3>
        <p class="text-muted" style="font-size:15px;">Your flight has been booked successfully. Have a great trip!</p>
    </div>

    <!-- Booking Reference Card -->
    <div class="trip-card p-4 mb-4 text-center" style="background:linear-gradient(135deg,#003580,#0086FF); color:#fff;">
        <div style="font-size:13px; opacity:.8; text-transform:uppercase; letter-spacing:.6px;">Booking Reference</div>
        <div style="font-size:36px; font-weight:900; letter-spacing:4px; margin:8px 0;">
            <?= htmlspecialchars($booking['Book_Confirm']) ?>
        </div>
        <div style="font-size:13px; opacity:.75;">
            Booked on <?= date('d M Y, H:i', strtotime($booking['Book_Date'])) ?>
            &nbsp;·&nbsp; Payment: <?= htmlspecialchars($booking['Paymt_Method'] ?? '—') ?>
        </div>
    </div>

    <!-- Flight Details -->
    <div class="trip-card p-4 mb-4">
        <h6 class="fw-bold text-muted text-uppercase mb-3" style="font-size:12px; letter-spacing:.6px;">Flight Details</h6>
        <div class="row align-items-center">
            <div class="col-auto text-center" style="min-width:90px;">
                <div style="font-size:20px; font-weight:800; color:#0086FF;"><?= htmlspecialchars($booking['Airln_Code']) ?></div>
                <div style="font-size:11px; color:#aaa;"><?= htmlspecialchars($booking['Airln_Name']) ?></div>
                <div style="font-size:11px; color:#aaa;"><?= htmlspecialchars($booking['Flght_No']) ?></div>
            </div>
            <div class="col">
                <div class="d-flex align-items-center gap-3">
                    <div class="text-center">
                        <div class="fw-bold" style="font-size:26px;"><?= date('H:i', strtotime($booking['Flght_DepartDate'])) ?></div>
                        <div style="font-size:13px; color:#555;"><?= htmlspecialchars($booking['Flght_Depart']) ?></div>
                        <div style="font-size:12px; color:#aaa;"><?= htmlspecialchars($fromCity) ?></div>
                    </div>
                    <div class="flex-grow-1 text-center">
                        <div style="font-size:12px; color:#aaa; margin-bottom:6px;">
                            <?= flightDuration($booking['Flght_DepartDate'], $booking['Flght_ArriveDate']) ?>
                        </div>
                        <div style="height:2px; background:#0086FF; position:relative;">
                            <span style="position:absolute;right:-4px;top:-7px;font-size:16px;color:#0086FF;">✈</span>
                        </div>
                        <div class="mt-1"><span class="badge badge-trip-blue" style="font-size:11px;">Non-stop</span></div>
                    </div>
                    <div class="text-center">
                        <div class="fw-bold" style="font-size:26px;"><?= date('H:i', strtotime($booking['Flght_ArriveDate'])) ?></div>
                        <div style="font-size:13px; color:#555;"><?= htmlspecialchars($booking['Flght_Arrival']) ?></div>
                        <div style="font-size:12px; color:#aaa;"><?= htmlspecialchars($toCity) ?></div>
                    </div>
                </div>
                <div class="mt-3" style="font-size:13px; color:#6B6B6B;">
                    <i class="bi bi-calendar3 me-2"></i><?= date('D, d M Y', strtotime($booking['Flght_DepartDate'])) ?>
                    &nbsp;·&nbsp;
                    <i class="bi bi-seat me-2"></i><?= classLabel($booking['Bokde_SeatClass']) ?>
                    &nbsp;·&nbsp;
                    <?= count($passengers) ?> passenger<?= count($passengers) > 1 ? 's' : '' ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Passenger List -->
    <div class="trip-card p-4 mb-4">
        <h6 class="fw-bold text-muted text-uppercase mb-3" style="font-size:12px; letter-spacing:.6px;">Passengers</h6>
        <div class="table-responsive">
            <table class="table trip-table mb-0">
                <thead>
                    <tr><th>#</th><th>Name</th><th>Class</th><th>Ticket Fare</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($passengers as $i => $p): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($p['Bokde_Passenger']) ?></td>
                        <td><?= classLabel($p['Bokde_SeatClass']) ?></td>
                        <td class="price-text fw-bold">₱<?= number_format($p['Bokde_Ticket'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Total Price -->
    <div class="trip-card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <div class="fw-bold" style="font-size:16px;">Total Amount Paid</div>
                <div class="text-muted" style="font-size:13px;">
                    Status: <span class="badge badge-trip-green px-2 py-1"><?= $booking['Book_Pay'] ?></span>
                    <?php if ($booking['Paymt_Transaction']): ?>
                        &nbsp;·&nbsp; Ref: <?= htmlspecialchars($booking['Paymt_Transaction']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="price-text" style="font-size:32px; font-weight:900;">
                ₱<?= number_format($booking['Book_Total'], 2) ?>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="d-flex gap-3 justify-content-center flex-wrap">
        <a href="/dbweb/user/dashboard.php" class="btn btn-trip px-4 py-2">
            <i class="bi bi-ticket-detailed me-2"></i>View My Bookings
        </a>
        <a href="/dbweb/index.php" class="btn btn-trip-outline px-4 py-2">
            <i class="bi bi-airplane me-2"></i>Book Another Flight
        </a>
        <button onclick="window.print()" class="btn btn-outline-secondary px-4 py-2">
            <i class="bi bi-printer me-2"></i>Print
        </button>
    </div>

</div>

<?php include '../layout/footer.php'; ?>
