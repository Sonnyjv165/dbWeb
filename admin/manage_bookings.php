<?php
session_start();
require_once '../config/db.php';
require_once '../config/airports.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /dbweb/auth/login.php');
    exit();
}

// Cancel booking
if (isset($_GET['cancel'])) {
    $bId = (int)$_GET['cancel'];

    $get = $conn->prepare("
        SELECT bk.Book_ID, bk.Book_Status, bd.Bokde_FlghtID, COUNT(bd.Bokde_ID) AS pax_count
        FROM Booking bk
        JOIN Bookingdetails bd ON bd.Bokde_BookID = bk.Book_ID
        WHERE bk.Book_ID = ? AND bk.Book_Status = 'CONFIRMED'
        GROUP BY bk.Book_ID, bk.Book_Status, bd.Bokde_FlghtID
    ");
    $get->bind_param('i', $bId);
    $get->execute();
    $bRow = $get->get_result()->fetch_assoc();

    if ($bRow) {
        $conn->begin_transaction();
        try {
            $conn->query("UPDATE Booking SET Book_Status='CANCELLED', Book_Pay='REFUNDED' WHERE Book_ID=$bId");
            $conn->query("UPDATE Flight SET Flght_SeatAvail = Flght_SeatAvail + {$bRow['pax_count']} WHERE Flght_ID = {$bRow['Bokde_FlghtID']}");
            $conn->query("UPDATE Payment SET Paymt_Status='REFUNDED' WHERE Paymt_BookID=$bId");
            $conn->commit();
            $_SESSION['flash'] = ['type'=>'warning','msg'=>'Booking cancelled.'];
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash'] = ['type'=>'danger','msg'=>'Failed to cancel booking.'];
        }
    }
    header('Location: /dbweb/admin/manage_bookings.php');
    exit();
}

// Filter
$statusFilter = $_GET['status'] ?? 'all';
$whereClause  = $statusFilter !== 'all' ? "WHERE bk.Book_Status = '" . $conn->real_escape_string(strtoupper($statusFilter)) . "'" : '';

$bookings = $conn->query("
    SELECT bk.Book_ID, bk.Book_Confirm, bk.Book_Status, bk.Book_Total, bk.Book_Pay, bk.Book_Date,
           u.User_Name, u.User_Email,
           MIN(f.Flght_No)         AS Flght_No,
           MIN(f.Flght_DepartDate) AS Flght_DepartDate,
           MIN(f.Flght_Depart)     AS Flght_Depart,
           MIN(f.Flght_Arrival)    AS Flght_Arrival,
           MIN(a.Airln_Code)       AS Airln_Code,
           MIN(bd.Bokde_SeatClass) AS Bokde_SeatClass,
           COUNT(bd.Bokde_ID)      AS pax_count
    FROM Booking bk
    JOIN User u            ON bk.Book_UserID   = u.User_ID
    JOIN Bookingdetails bd ON bd.Bokde_BookID  = bk.Book_ID
    JOIN Flight f          ON f.Flght_ID       = bd.Bokde_FlghtID
    JOIN Airliner a        ON a.Airln_ID       = f.Flght_AirlnID
    $whereClause
    GROUP BY bk.Book_ID
    ORDER BY bk.Book_Date DESC
")->fetch_all(MYSQLI_ASSOC);

function classLabel($c) { return match(strtolower($c)) { 'business' => 'Business', 'first' => 'First Class', default => 'Economy' }; }

$title = 'Manage Bookings';
include '../layout/layout.php';
?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0">Manage Bookings</h4>
            <a href="/dbweb/admin/dashboard.php" style="font-size:13px; color:#0086FF;">← Back to Dashboard</a>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php foreach (['all'=>'All','confirmed'=>'Confirmed','cancelled'=>'Cancelled'] as $val => $lbl): ?>
                <a href="?status=<?= $val ?>"
                   class="btn btn-sm <?= $statusFilter === $val ? 'btn-trip' : 'btn-trip-outline' ?>"
                   style="font-size:13px;"><?= $lbl ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Flash -->
    <?php if (!empty($_SESSION['flash'])): $fl = $_SESSION['flash']; unset($_SESSION['flash']); ?>
        <div class="alert alert-<?= $fl['type'] ?> rounded-3 mb-3" style="font-size:14px;"><?= htmlspecialchars($fl['msg']) ?></div>
    <?php endif; ?>

    <div class="trip-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span style="font-size:13px; color:#6B6B6B;"><?= count($bookings) ?> booking<?= count($bookings) != 1 ? 's' : '' ?> found</span>
        </div>

        <?php if (empty($bookings)): ?>
            <p class="text-muted text-center py-4" style="font-size:14px;">No bookings found.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table trip-table mb-0">
                <thead>
                    <tr>
                        <th>Ref</th>
                        <th>Passenger</th>
                        <th>Email</th>
                        <th>Flight</th>
                        <th>Route</th>
                        <th>Date</th>
                        <th>Class</th>
                        <th>Pax</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                    <tr>
                        <td class="fw-bold" style="font-size:12px; letter-spacing:1px; color:#003580;">
                            <?= htmlspecialchars($b['Book_Confirm']) ?>
                        </td>
                        <td style="font-size:13px;"><?= htmlspecialchars($b['User_Name']) ?></td>
                        <td style="font-size:12px; color:#aaa;"><?= htmlspecialchars($b['User_Email']) ?></td>
                        <td style="font-size:12px;">
                            <span style="color:#0086FF; font-weight:700;"><?= $b['Airln_Code'] ?></span> <?= $b['Flght_No'] ?>
                        </td>
                        <td style="font-size:12px;">
                            <?= $b['Flght_Depart'] ?> → <?= $b['Flght_Arrival'] ?>
                        </td>
                        <td style="font-size:12px; color:#555;"><?= date('d M Y H:i', strtotime($b['Flght_DepartDate'])) ?></td>
                        <td style="font-size:12px;"><?= classLabel($b['Bokde_SeatClass']) ?></td>
                        <td style="font-size:13px;"><?= $b['pax_count'] ?></td>
                        <td class="price-text fw-bold" style="font-size:13px;">₱<?= number_format($b['Book_Total'], 2) ?></td>
                        <td>
                            <?php $sc = match($b['Book_Status']) {
                                'CONFIRMED'=>'badge-trip-green','CANCELLED'=>'badge-trip-red',default=>'badge-trip-orange'
                            }; ?>
                            <span class="badge <?= $sc ?> px-2 py-1" style="font-size:11px;"><?= ucfirst(strtolower($b['Book_Status'])) ?></span>
                        </td>
                        <td>
                            <?php if ($b['Book_Status'] === 'CONFIRMED'): ?>
                                <a href="?cancel=<?= $b['Book_ID'] ?>&status=<?= $statusFilter ?>"
                                   onclick="return confirm('Cancel booking <?= htmlspecialchars($b['Book_Confirm'], ENT_QUOTES) ?>?')"
                                   class="btn btn-sm btn-outline-danger" style="font-size:12px;">Cancel</a>
                            <?php else: ?>
                                <span style="font-size:12px; color:#aaa;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../layout/footer.php'; ?>
