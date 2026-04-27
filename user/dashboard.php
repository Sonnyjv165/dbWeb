<?php
session_start();
require_once '../config/db.php';
require_once '../config/airports.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /dbweb/auth/login.php');
    exit();
}

$userId = $_SESSION['user_id'];

// Cancel booking
if (isset($_POST['cancel_booking'])) {
    $bId = (int)$_POST['booking_id'];

    // Verify ownership and get flight IDs to restore seats
    $get = $conn->prepare("
        SELECT bk.Book_ID, bk.Book_Status, bd.Bokde_FlghtID, COUNT(bd.Bokde_ID) AS pax_count
        FROM Booking bk
        JOIN Bookingdetails bd ON bd.Bokde_BookID = bk.Book_ID
        WHERE bk.Book_ID = ? AND bk.Book_UserID = ?
        GROUP BY bk.Book_ID, bk.Book_Status, bd.Bokde_FlghtID
    ");
    $get->bind_param('ii', $bId, $userId);
    $get->execute();
    $bRow = $get->get_result()->fetch_assoc();

    if ($bRow && $bRow['Book_Status'] === 'CONFIRMED') {
        $conn->begin_transaction();
        try {
            $conn->query("UPDATE Booking SET Book_Status='CANCELLED', Book_Pay='REFUNDED' WHERE Book_ID=$bId");
            $conn->query("UPDATE Flight SET Flght_SeatAvail = Flght_SeatAvail + {$bRow['pax_count']} WHERE Flght_ID = {$bRow['Bokde_FlghtID']}");
            $conn->query("UPDATE Payment SET Paymt_Status='REFUNDED' WHERE Paymt_BookID=$bId");
            $conn->commit();
            $_SESSION['flash_success'] = 'Booking cancelled successfully.';
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_error'] = 'Cancellation failed. Please try again.';
        }
    }
    header('Location: /dbweb/user/dashboard.php');
    exit();
}

// Fetch user's bookings (one row per booking; aggregate passenger count)
$stmt = $conn->prepare("
    SELECT bk.Book_ID, bk.Book_Confirm, bk.Book_Date, bk.Book_Status, bk.Book_Total, bk.Book_Pay,
           MIN(f.Flght_No)         AS Flght_No,
           MIN(f.Flght_DepartDate) AS Flght_DepartDate,
           MIN(f.Flght_ArriveDate) AS Flght_ArriveDate,
           MIN(f.Flght_Depart)     AS Flght_Depart,
           MIN(f.Flght_Arrival)    AS Flght_Arrival,
           MIN(a.Airln_Name)       AS Airln_Name,
           MIN(a.Airln_Code)       AS Airln_Code,
           MIN(bd.Bokde_SeatClass) AS Bokde_SeatClass,
           COUNT(bd.Bokde_ID)      AS pax_count
    FROM Booking bk
    JOIN Bookingdetails bd ON bd.Bokde_BookID  = bk.Book_ID
    JOIN Flight f          ON f.Flght_ID       = bd.Bokde_FlghtID
    JOIN Airliner a        ON a.Airln_ID       = f.Flght_AirlnID
    WHERE bk.Book_UserID = ?
    GROUP BY bk.Book_ID
    ORDER BY bk.Book_Date DESC
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$myBookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function classLabel($c) { return match(strtolower($c)) { 'business' => 'Business', 'first' => 'First Class', default => 'Economy' }; }

$title = 'My Bookings';
include '../layout/layout.php';
?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0">My Bookings</h4>
            <p class="text-muted mb-0" style="font-size:14px;">
                Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?>
            </p>
        </div>
        <a href="/dbweb/index.php" class="btn btn-trip-orange px-4">
            <i class="bi bi-airplane me-2"></i>Book a Flight
        </a>
    </div>

    <!-- Flash messages -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success rounded-3 mb-3" style="font-size:14px;">
            <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_SESSION['flash_success']) ?>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger rounded-3 mb-3" style="font-size:14px;">
            <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($_SESSION['flash_error']) ?>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if (empty($myBookings)): ?>
        <div class="trip-card p-5 text-center text-muted">
            <div style="font-size:60px; opacity:.2;">✈</div>
            <h5 class="mt-3">No bookings yet</h5>
            <p style="font-size:14px;">Search and book your first flight to get started.</p>
            <a href="/dbweb/index.php" class="btn btn-trip mt-2">Search Flights</a>
        </div>
    <?php else: ?>
        <?php foreach ($myBookings as $b):
            $statusClass = match($b['Book_Status']) {
                'CONFIRMED' => 'badge-trip-green',
                'CANCELLED' => 'badge-trip-red',
                default     => 'badge-trip-orange',
            };
            $isPast = strtotime($b['Flght_DepartDate']) < time();
            $fromCity = airportCity($b['Flght_Depart']);
            $toCity   = airportCity($b['Flght_Arrival']);
        ?>
        <div class="trip-card mb-3 p-0 overflow-hidden">
            <div class="row g-0">
                <div class="col-auto" style="width:6px; background:<?= $b['Book_Status'] === 'CONFIRMED' ? '#1a9e5c' : ($b['Book_Status'] === 'CANCELLED' ? '#d93025' : '#FF7020') ?>;"></div>
                <div class="col p-4">
                    <div class="row align-items-center g-3">

                        <div class="col-md-4">
                            <div class="d-flex align-items-center gap-3">
                                <div style="font-size:24px; font-weight:800; color:#0086FF;">
                                    <?= htmlspecialchars($b['Airln_Code']) ?>
                                </div>
                                <div>
                                    <div class="fw-bold" style="font-size:16px;">
                                        <?= htmlspecialchars($fromCity) ?> → <?= htmlspecialchars($toCity) ?>
                                    </div>
                                    <div class="text-muted" style="font-size:12px;">
                                        <?= $b['Flght_Depart'] ?> → <?= $b['Flght_Arrival'] ?>
                                        &nbsp;·&nbsp;<?= htmlspecialchars($b['Flght_No']) ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div style="font-size:14px;">
                                <i class="bi bi-calendar3 me-1 text-muted"></i>
                                <?= date('D, d M Y', strtotime($b['Flght_DepartDate'])) ?>
                            </div>
                            <div style="font-size:13px;" class="text-muted mt-1">
                                <?= date('H:i', strtotime($b['Flght_DepartDate'])) ?> →
                                <?= date('H:i', strtotime($b['Flght_ArriveDate'])) ?>
                                &nbsp;·&nbsp;<?= classLabel($b['Bokde_SeatClass']) ?>
                                &nbsp;·&nbsp;<?= $b['pax_count'] ?> pax
                            </div>
                        </div>

                        <div class="col-md-2">
                            <div style="font-size:12px; color:#aaa; text-transform:uppercase; letter-spacing:.5px;">Ref</div>
                            <div class="fw-bold" style="font-size:15px; letter-spacing:1px;">
                                <?= htmlspecialchars($b['Book_Confirm']) ?>
                            </div>
                            <span class="badge <?= $statusClass ?> mt-1 px-2 py-1" style="font-size:11px;">
                                <?= ucfirst(strtolower($b['Book_Status'])) ?>
                            </span>
                        </div>

                        <div class="col-md-3 text-end">
                            <div class="price-text fw-bold" style="font-size:20px;">
                                ₱<?= number_format($b['Book_Total'], 2) ?>
                            </div>
                            <div class="d-flex gap-2 justify-content-end mt-2 flex-wrap">
                                <a href="/dbweb/flights/confirmation.php?ref=<?= urlencode($b['Book_Confirm']) ?>"
                                   class="btn btn-sm btn-trip" style="font-size:12px;">View</a>
                                <?php if ($b['Book_Status'] === 'CONFIRMED' && !$isPast): ?>
                                    <form method="POST" onsubmit="return confirm('Cancel this booking?')">
                                        <input type="hidden" name="booking_id" value="<?= $b['Book_ID'] ?>">
                                        <button type="submit" name="cancel_booking"
                                                class="btn btn-sm btn-outline-danger" style="font-size:12px;">
                                            Cancel
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<?php include '../layout/footer.php'; ?>
