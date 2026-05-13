<?php
session_start();
require_once '../config/db.php';
require_once '../config/airports.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /auth/login.php');
    exit();
}

// Cancel booking
if (isset($_GET['cancel'])) {
    $bId = (int)$_GET['cancel'];

    $get = $conn->prepare("
        SELECT bk.Book_ID, bk.Book_Status, bd.Bokde_FlghtID, COUNT(bd.Bokde_ID) AS pax_count
        FROM booking bk
        JOIN bookingdetails bd ON bd.Bokde_BookID = bk.Book_ID
        WHERE bk.Book_ID = ? AND bk.Book_Status = 'CONFIRMED'
        GROUP BY bk.Book_ID, bk.Book_Status, bd.Bokde_FlghtID
    ");
    $get->bind_param('i', $bId);
    $get->execute();
    $legRows = $get->get_result()->fetch_all(MYSQLI_ASSOC);

    if (!empty($legRows)) {
        $conn->begin_transaction();
        try {
            $conn->query("UPDATE booking SET Book_Status='CANCELLED', Book_Pay='REFUNDED' WHERE Book_ID=$bId");
            // Restore seats for every leg (handles both one-way and round-trip)
            foreach ($legRows as $leg) {
                $conn->query("UPDATE flight SET Flght_SeatAvail = Flght_SeatAvail + {$leg['pax_count']} WHERE Flght_ID = {$leg['Bokde_FlghtID']}");
            }
            $conn->query("UPDATE payment SET Paymt_Status='REFUNDED' WHERE Paymt_BookID=$bId");
            $conn->commit();
            $_SESSION['flash'] = ['type'=>'warning','msg'=>'Booking cancelled.'];
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash'] = ['type'=>'danger','msg'=>'Failed to cancel booking.'];
        }
    }
    header('Location: /admin/manage_bookings.php');
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
    FROM booking bk
    JOIN user u            ON bk.Book_UserID   = u.User_ID
    JOIN bookingdetails bd ON bd.Bokde_BookID  = bk.Book_ID
    JOIN flight f          ON f.Flght_ID       = bd.Bokde_FlghtID
    JOIN airliner a        ON a.Airln_ID       = f.Flght_AirlnID
    $whereClause
    GROUP BY bk.Book_ID
    ORDER BY bk.Book_Date DESC
")->fetch_all(MYSQLI_ASSOC);

function classLabel($c) { return match(strtolower($c)) { 'business' => 'Business', 'first' => 'First Class', default => 'Economy' }; }

$title = 'Manage Bookings';
include '../layout/layout.php';
?>

<style>
.admin-page-header {
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: 12px; margin-bottom: 20px;
}
.admin-page-title { font-family: var(--font-serif); font-size: 22px; font-weight: 600; letter-spacing: -.02em; color: var(--trip-text); margin: 0; }
.admin-back { font-size: 13px; color: var(--trip-blue); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; margin-top: 2px; }
.admin-back:hover { text-decoration: underline; }
.filter-pill {
    padding: 6px 16px; border-radius: 999px; font-size: 13px; font-weight: 600;
    text-decoration: none; transition: all .15s;
}
</style>

<div class="container py-4">

    <div class="admin-page-header">
        <div>
            <h4 class="admin-page-title">Manage Bookings</h4>
            <a href="/admin/dashboard.php" class="admin-back"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php
            $filterMeta = ['all'=>'All','confirmed'=>'Confirmed','cancelled'=>'Cancelled'];
            foreach ($filterMeta as $val => $lbl):
                $isActive = $statusFilter === $val;
            ?>
                <a href="?status=<?= $val ?>"
                   class="filter-pill <?= $isActive ? 'btn-trip' : 'btn-trip-outline' ?>">
                    <?= $lbl ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Flash -->
    <?php if (!empty($_SESSION['flash'])): $fl = $_SESSION['flash']; unset($_SESSION['flash']); ?>
        <div class="alert alert-<?= $fl['type'] ?> rounded-3 mb-3 d-flex align-items-center gap-2" style="font-size:14px;">
            <i class="bi bi-<?= $fl['type'] === 'success' ? 'check-circle' : ($fl['type'] === 'danger' ? 'exclamation-circle' : 'exclamation-triangle') ?>"></i>
            <?= htmlspecialchars($fl['msg']) ?>
        </div>
    <?php endif; ?>

    <div class="trip-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div style="font-size:15px; font-weight:700; color:var(--trip-text);">
                <?= ucfirst($statusFilter === 'all' ? 'All' : $statusFilter) ?> Bookings
            </div>
            <div style="font-size:12px; color:var(--trip-muted);"><?= count($bookings) ?> record<?= count($bookings) !== 1 ? 's' : '' ?></div>
        </div>

        <?php if (empty($bookings)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox" style="font-size:40px; opacity:.15; display:block; margin-bottom:14px;"></i>
                No bookings found for this filter.
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table trip-table mb-0">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Passenger</th>
                        <th>Flight</th>
                        <th>Route</th>
                        <th>Departure</th>
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
                        <td>
                            <div style="font-size:13px; font-weight:800; letter-spacing:1.5px; color:var(--trip-navy);"><?= htmlspecialchars($b['Book_Confirm']) ?></div>
                            <div style="font-size:11px; color:var(--trip-muted);"><?= date('d M Y', strtotime($b['Book_Date'])) ?></div>
                        </td>
                        <td>
                            <div style="font-size:13px; font-weight:600; color:var(--trip-text);"><?= htmlspecialchars($b['User_Name']) ?></div>
                            <div style="font-size:11px; color:var(--trip-muted);"><?= htmlspecialchars($b['User_Email']) ?></div>
                        </td>
                        <td style="font-size:13px;">
                            <span style="color:var(--trip-blue); font-weight:800;"><?= $b['Airln_Code'] ?></span>
                            <span style="color:var(--trip-muted); font-size:11px; margin-left:3px;"><?= $b['Flght_No'] ?></span>
                        </td>
                        <td style="font-size:13px; font-weight:600;"><?= $b['Flght_Depart'] ?> <span style="color:var(--trip-muted); font-weight:400;">→</span> <?= $b['Flght_Arrival'] ?></td>
                        <td style="font-size:12px; color:var(--trip-muted);"><?= date('d M Y H:i', strtotime($b['Flght_DepartDate'])) ?></td>
                        <td style="font-size:12px;"><?= classLabel($b['Bokde_SeatClass']) ?></td>
                        <td style="font-size:13px; font-weight:600;"><?= $b['pax_count'] ?></td>
                        <td class="price-text fw-bold" style="font-size:13px;">₱<?= number_format($b['Book_Total'], 2) ?></td>
                        <td>
                            <?php $sc = match($b['Book_Status']) {
                                'CONFIRMED'=>'badge-trip-green','CANCELLED'=>'badge-trip-red',default=>'badge-trip-orange'
                            }; ?>
                            <span class="badge <?= $sc ?>" style="font-size:11px; padding:4px 9px;"><?= ucfirst(strtolower($b['Book_Status'])) ?></span>
                        </td>
                        <td>
                            <?php if ($b['Book_Status'] === 'CONFIRMED'): ?>
                                <a href="?cancel=<?= $b['Book_ID'] ?>&status=<?= $statusFilter ?>"
                                   onclick="return confirm('Cancel booking <?= htmlspecialchars($b['Book_Confirm'], ENT_QUOTES) ?>?')"
                                   class="btn btn-sm btn-outline-danger" style="font-size:12px; padding:4px 10px;">Cancel</a>
                            <?php else: ?>
                                <span style="font-size:12px; color:var(--trip-muted);">—</span>
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
