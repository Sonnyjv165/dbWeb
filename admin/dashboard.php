<?php
session_start();
require_once '../config/db.php';
require_once '../config/airports.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /dbweb/auth/login.php');
    exit();
}

// Stats
$totalFlights  = $conn->query("SELECT COUNT(*) c FROM Flight WHERE Flght_Status='SCHEDULED'")->fetch_assoc()['c'];
$totalBookings = $conn->query("SELECT COUNT(*) c FROM Booking WHERE Book_Status='CONFIRMED'")->fetch_assoc()['c'];
$totalRevenue  = $conn->query("SELECT COALESCE(SUM(Book_Total),0) r FROM Booking WHERE Book_Status='CONFIRMED'")->fetch_assoc()['r'];
$totalUsers    = $conn->query("SELECT COUNT(*) c FROM User WHERE User_Role='user'")->fetch_assoc()['c'];

// Recent bookings
$recent = $conn->query("
    SELECT bk.Book_Confirm, bk.Book_Status, bk.Book_Total, bk.Book_Date,
           u.User_Name,
           MIN(f.Flght_Depart)  AS Flght_Depart,
           MIN(f.Flght_Arrival) AS Flght_Arrival
    FROM Booking bk
    JOIN User u            ON bk.Book_UserID   = u.User_ID
    JOIN Bookingdetails bd ON bd.Bokde_BookID  = bk.Book_ID
    JOIN Flight f          ON f.Flght_ID       = bd.Bokde_FlghtID
    GROUP BY bk.Book_ID
    ORDER BY bk.Book_Date DESC
    LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

$title = 'Admin Dashboard';
include '../layout/layout.php';
?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0">Admin Dashboard</h4>
            <p class="text-muted mb-0" style="font-size:14px;">Overview of the trip.com flight system</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="/dbweb/admin/manage_flights.php"  class="btn btn-trip">Manage Flights</a>
            <a href="/dbweb/admin/manage_bookings.php" class="btn btn-trip-outline">Manage Bookings</a>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="trip-card p-4 text-center">
                <div style="font-size:32px; color:#0086FF; font-weight:900;"><?= number_format($totalFlights) ?></div>
                <div class="text-muted" style="font-size:14px; margin-top:4px;">Scheduled Flights</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="trip-card p-4 text-center">
                <div style="font-size:32px; color:#1a9e5c; font-weight:900;"><?= number_format($totalBookings) ?></div>
                <div class="text-muted" style="font-size:14px; margin-top:4px;">Confirmed Bookings</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="trip-card p-4 text-center">
                <div class="price-text" style="font-size:28px; font-weight:900;">₱<?= number_format($totalRevenue, 0) ?></div>
                <div class="text-muted" style="font-size:14px; margin-top:4px;">Total Revenue</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="trip-card p-4 text-center">
                <div style="font-size:32px; color:#6B6B6B; font-weight:900;"><?= number_format($totalUsers) ?></div>
                <div class="text-muted" style="font-size:14px; margin-top:4px;">Registered Users</div>
            </div>
        </div>
    </div>

    <!-- Recent Bookings -->
    <div class="trip-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0">Recent Bookings</h6>
            <a href="/dbweb/admin/manage_bookings.php" style="font-size:13px; color:#0086FF;">View all →</a>
        </div>

        <?php if (empty($recent)): ?>
            <p class="text-muted text-center py-3" style="font-size:14px;">No bookings yet.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table trip-table mb-0">
                <thead>
                    <tr>
                        <th>Ref</th>
                        <th>Passenger</th>
                        <th>Route</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $r): ?>
                    <tr>
                        <td class="fw-bold" style="letter-spacing:1px; font-size:13px;"><?= htmlspecialchars($r['Book_Confirm']) ?></td>
                        <td><?= htmlspecialchars($r['User_Name']) ?></td>
                        <td><?= htmlspecialchars(airportCity($r['Flght_Depart'])) ?> → <?= htmlspecialchars(airportCity($r['Flght_Arrival'])) ?></td>
                        <td class="price-text fw-bold">₱<?= number_format($r['Book_Total'], 2) ?></td>
                        <td>
                            <?php $sc = match($r['Book_Status']) { 'CONFIRMED'=>'badge-trip-green','CANCELLED'=>'badge-trip-red',default=>'badge-trip-orange' }; ?>
                            <span class="badge <?= $sc ?> px-2 py-1" style="font-size:11px;"><?= ucfirst(strtolower($r['Book_Status'])) ?></span>
                        </td>
                        <td style="font-size:12px; color:#aaa;"><?= date('d M Y', strtotime($r['Book_Date'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php include '../layout/footer.php'; ?>
