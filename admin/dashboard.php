<?php
session_start();
require_once '../config/db.php';
require_once '../config/airports.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /auth/login.php');
    exit();
}

// Stats
$totalFlights  = $conn->query("SELECT COUNT(*) c FROM flight WHERE Flght_Status='SCHEDULED'")->fetch_assoc()['c'];
$totalBookings = $conn->query("SELECT COUNT(*) c FROM booking WHERE Book_Status='CONFIRMED'")->fetch_assoc()['c'];
$totalRevenue  = $conn->query("SELECT COALESCE(SUM(Book_Total),0) r FROM booking WHERE Book_Status='CONFIRMED'")->fetch_assoc()['r'];
$totalUsers    = $conn->query("SELECT COUNT(*) c FROM user WHERE User_Role='user'")->fetch_assoc()['c'];

// Recent bookings
$recent = $conn->query("
    SELECT bk.Book_Confirm, bk.Book_Status, bk.Book_Total, bk.Book_Date,
           u.User_Name,
           MIN(f.Flght_Depart)  AS Flght_Depart,
           MIN(f.Flght_Arrival) AS Flght_Arrival
    FROM booking bk
    JOIN user u            ON bk.Book_UserID   = u.User_ID
    JOIN bookingdetails bd ON bd.Bokde_BookID  = bk.Book_ID
    JOIN flight f          ON f.Flght_ID       = bd.Bokde_FlghtID
    GROUP BY bk.Book_ID
    ORDER BY bk.Book_Date DESC
    LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

$title = 'Admin Dashboard';
include '../layout/layout.php';
?>

<style>
.admin-hero {
    background: linear-gradient(135deg, var(--trip-navy) 0%, #1a3a6a 100%);
    border-radius: var(--radius-lg); padding: 26px 32px; margin-bottom: 24px; color: #fff;
    position: relative; overflow: hidden;
}
.admin-hero::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(ellipse at top right, rgba(255,255,255,.06) 0%, transparent 60%);
    pointer-events: none;
}
.admin-hero h4 { font-family: var(--font-serif); font-size: 22px; font-weight: 600; letter-spacing: -.02em; color: #fff; margin-bottom: 4px; }
.admin-hero p  { font-size: 14px; color: rgba(255,255,255,.6); margin: 0; }

.stat-card {
    background: #fff; border-radius: var(--radius-lg); border: 1px solid var(--trip-border);
    box-shadow: var(--shadow-sm); padding: 22px 24px;
    display: flex; align-items: center; gap: 16px;
    transition: box-shadow .15s;
}
.stat-card:hover { box-shadow: var(--shadow-md); }
.stat-icon {
    width: 48px; height: 48px; border-radius: 12px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 20px;
}
.stat-val { font-size: 26px; font-weight: 900; letter-spacing: -.03em; line-height: 1; }
.stat-lbl { font-size: 12px; color: var(--trip-muted); margin-top: 3px; }
</style>

<div class="container py-4">

    <!-- Hero -->
    <div class="admin-hero">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4>Admin Dashboard</h4>
                <p>Overview of the trip.com flight system</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="/admin/manage_flights.php"  class="btn btn-trip"><i class="bi bi-airplane me-2"></i>Manage Flights</a>
                <a href="/admin/manage_bookings.php" class="btn btn-trip-outline"><i class="bi bi-ticket-detailed me-2"></i>Manage Bookings</a>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#EFF6FF; color:var(--trip-blue);"><i class="bi bi-airplane-fill"></i></div>
                <div>
                    <div class="stat-val" style="color:var(--trip-blue);"><?= number_format($totalFlights) ?></div>
                    <div class="stat-lbl">Scheduled Flights</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#F0FDF4; color:#16A34A;"><i class="bi bi-ticket-detailed-fill"></i></div>
                <div>
                    <div class="stat-val" style="color:#16A34A;"><?= number_format($totalBookings) ?></div>
                    <div class="stat-lbl">Confirmed Bookings</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#FFF3E5; color:var(--trip-orange);"><i class="bi bi-currency-exchange"></i></div>
                <div>
                    <div class="stat-val" style="color:var(--trip-orange); font-size:20px;">₱<?= number_format($totalRevenue, 0) ?></div>
                    <div class="stat-lbl">Total Revenue</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#F5F5F5; color:#6B6B6B;"><i class="bi bi-people-fill"></i></div>
                <div>
                    <div class="stat-val" style="color:#6B6B6B;"><?= number_format($totalUsers) ?></div>
                    <div class="stat-lbl">Registered Users</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Bookings -->
    <div class="trip-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <div style="font-size:15px; font-weight:700; color:var(--trip-text);">Recent Bookings</div>
                <div style="font-size:12px; color:var(--trip-muted);">Latest 8 transactions</div>
            </div>
            <a href="/admin/manage_bookings.php" class="btn btn-sm btn-trip-outline" style="font-size:13px;">
                View All <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>

        <?php if (empty($recent)): ?>
            <div class="text-center py-4 text-muted">
                <i class="bi bi-inbox" style="font-size:36px; opacity:.2; display:block; margin-bottom:12px;"></i>
                No bookings yet.
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table trip-table mb-0">
                <thead>
                    <tr>
                        <th>Reference</th>
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
                        <td class="fw-bold" style="letter-spacing:1.5px; font-size:13px; color:var(--trip-text);"><?= htmlspecialchars($r['Book_Confirm']) ?></td>
                        <td><?= htmlspecialchars($r['User_Name']) ?></td>
                        <td style="font-size:13px;"><?= htmlspecialchars(airportCity($r['Flght_Depart'])) ?> → <?= htmlspecialchars(airportCity($r['Flght_Arrival'])) ?></td>
                        <td class="price-text fw-bold">₱<?= number_format($r['Book_Total'], 2) ?></td>
                        <td>
                            <?php $sc = match($r['Book_Status']) { 'CONFIRMED'=>'badge-trip-green','CANCELLED'=>'badge-trip-red',default=>'badge-trip-orange' }; ?>
                            <span class="badge <?= $sc ?>" style="font-size:11px; padding:4px 9px;"><?= ucfirst(strtolower($r['Book_Status'])) ?></span>
                        </td>
                        <td style="font-size:12px; color:var(--trip-muted);"><?= date('d M Y', strtotime($r['Book_Date'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php include '../layout/footer.php'; ?>
