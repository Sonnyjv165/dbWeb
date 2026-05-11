<?php
session_start();
require_once '../config/db.php';
require_once '../config/airports.php';

// Redirect logged-in users to their dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: /user/dashboard.php');
    exit();
}

$bookings = [];
$searched = false;
$searchEmail = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_search'])) {
    $searchEmail = strtolower(trim($_POST['search_email']));
    $searched = true;

    if (!filter_var($searchEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $conn->prepare("
            SELECT bk.Book_ID, bk.Book_Confirm, bk.Book_Status, bk.Book_Pay,
                   bk.Book_Total, bk.Book_Date,
                   MIN(f.Flght_Depart)    AS dep_iata,
                   MIN(f.Flght_Arrival)   AS arr_iata,
                   MIN(f.Flght_DepartDate) AS dep_date,
                   COUNT(DISTINCT bd.Bokde_ID) AS pax_count,
                   MIN(bd.Bokde_SeatClass) AS seat_class,
                   MIN(a.Airln_Name) AS airline
            FROM booking bk
            JOIN user u            ON u.User_ID       = bk.Book_UserID
            JOIN bookingdetails bd ON bd.Bokde_BookID = bk.Book_ID
            JOIN flight f          ON f.Flght_ID      = bd.Bokde_FlghtID
            JOIN airliner a        ON a.Airln_ID      = f.Flght_AirlnID
            WHERE LOWER(u.User_Email) = ?
            GROUP BY bk.Book_ID
            ORDER BY bk.Book_Date DESC
        ");
        $stmt->bind_param('s', $searchEmail);
        $stmt->execute();
        $bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

$title = 'Find My Bookings';
include '../layout/layout.php';
?>

<div class="container py-5" style="max-width:760px;">

    <!-- Header -->
    <div class="text-center mb-5">
        <div style="width:56px; height:56px; border-radius:16px; background:#e8f2fd; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; font-size:24px; color:var(--trip-blue);">
            <i class="bi bi-search"></i>
        </div>
        <h3 style="font-family:var(--font-serif); font-size:28px; font-weight:600; letter-spacing:-0.02em; margin-bottom:8px;">Find My Bookings</h3>
        <p class="text-muted" style="font-size:14px; max-width:420px; margin:0 auto;">
            Enter the email address you used when booking to view all your flights.
        </p>
    </div>

    <!-- Search Form -->
    <div class="trip-card p-4 mb-4">
        <form method="POST" class="d-flex gap-3 align-items-end flex-wrap">
            <div style="flex:1; min-width:220px;">
                <label class="form-label fw-semibold" style="font-size:13px;">Email Address</label>
                <input type="email" name="search_email" class="form-control"
                       placeholder="you@email.com" required
                       value="<?= htmlspecialchars($searchEmail) ?>">
            </div>
            <button type="submit" name="do_search" class="btn btn-trip" style="padding:10px 28px; white-space:nowrap;">
                <i class="bi bi-search me-2"></i>Search
            </button>
        </form>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger rounded-3 py-2" style="font-size:14px;">
            <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
        </div>

    <?php elseif ($searched && empty($bookings)): ?>
        <div class="trip-card p-5 text-center">
            <div style="font-size:48px; opacity:.15; color:var(--trip-text); margin-bottom:16px;">
                <i class="bi bi-ticket-detailed"></i>
            </div>
            <p class="fw-semibold mb-1">No bookings found</p>
            <p class="text-muted" style="font-size:14px; max-width:320px; margin:0 auto;">
                We couldn't find any bookings for <strong><?= htmlspecialchars($searchEmail) ?></strong>. Double-check the email and try again.
            </p>
        </div>

    <?php elseif (!empty($bookings)): ?>
        <div class="eyebrow-label mb-3"><?= count($bookings) ?> booking<?= count($bookings) !== 1 ? 's' : '' ?> found for <?= htmlspecialchars($searchEmail) ?></div>

        <?php foreach ($bookings as $bk):
            $statusColors = [
                'CONFIRMED' => ['badge-trip-green', 'bi-check-circle-fill', '#1a9e5c'],
                'CANCELLED' => ['badge-trip-red',   'bi-x-circle-fill',    '#d93025'],
                'PENDING'   => ['badge-trip-orange', 'bi-clock-fill',       '#F06020'],
            ];
            [$badgeClass, $icon, $color] = $statusColors[$bk['Book_Status']] ?? ['badge-trip-orange', 'bi-clock-fill', '#F06020'];
        ?>
        <div class="trip-card p-4 mb-3" style="border-left:3px solid <?= $color ?>;">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <div>
                    <div style="font-size:11px; font-weight:700; letter-spacing:0.10em; text-transform:uppercase; color:var(--trip-muted); margin-bottom:4px;">Booking Reference</div>
                    <div style="font-size:20px; font-weight:800; letter-spacing:3px; color:var(--trip-text);"><?= htmlspecialchars($bk['Book_Confirm']) ?></div>
                </div>
                <span class="badge <?= $badgeClass ?> px-3 py-2" style="font-size:12px;">
                    <i class="bi <?= $icon ?> me-1"></i><?= ucfirst(strtolower($bk['Book_Status'])) ?>
                </span>
            </div>

            <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
                <div>
                    <div style="font-size:22px; font-weight:800; color:var(--trip-text);"><?= $bk['dep_iata'] ?></div>
                    <div style="font-size:12px; color:var(--trip-muted);"><?= htmlspecialchars(airportCity($bk['dep_iata'])) ?></div>
                </div>
                <div style="flex:1; text-align:center; color:var(--trip-muted);">
                    <i class="bi bi-airplane" style="font-size:18px; color:var(--trip-blue);"></i>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:22px; font-weight:800; color:var(--trip-text);"><?= $bk['arr_iata'] ?></div>
                    <div style="font-size:12px; color:var(--trip-muted);"><?= htmlspecialchars(airportCity($bk['arr_iata'])) ?></div>
                </div>
            </div>

            <div class="d-flex gap-3 flex-wrap" style="font-size:13px; color:var(--trip-muted);">
                <span><i class="bi bi-calendar3 me-1"></i><?= date('D, d M Y', strtotime($bk['dep_date'])) ?></span>
                <span><i class="bi bi-person me-1"></i><?= $bk['pax_count'] ?> passenger<?= $bk['pax_count'] != 1 ? 's' : '' ?></span>
                <span><i class="bi bi-gem me-1"></i><?= ucfirst(strtolower($bk['seat_class'])) ?></span>
                <span class="ms-auto price-text fw-bold" style="font-size:15px;">₱<?= number_format($bk['Book_Total'], 2) ?></span>
            </div>

            <hr style="margin:14px 0 12px; border-color:var(--trip-border);">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span style="font-size:12px; color:var(--trip-muted);">
                    <i class="bi bi-clock-history me-1"></i>Booked <?= date('d M Y', strtotime($bk['Book_Date'])) ?>
                </span>
                <a href="/flights/confirmation.php?ref=<?= urlencode($bk['Book_Confirm']) ?>"
                   class="btn btn-trip-outline" style="font-size:13px; padding:6px 18px;">
                    View Details <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>

    <?php endif; ?>

    <!-- Sign-in nudge -->
    <div class="trip-card p-4 mt-4" style="background:#FAFAF8;">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div style="font-size:28px; color:var(--trip-orange);"><i class="bi bi-coin"></i></div>
            <div style="flex:1;">
                <div class="fw-semibold" style="font-size:14px; margin-bottom:2px;">Want to earn Trip Coins and manage bookings easily?</div>
                <div class="text-muted" style="font-size:13px;">Create a free account to access your bookings anytime, earn rewards, and get membership perks.</div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="/auth/register.php" class="btn btn-trip" style="padding:8px 20px; font-size:13px;">Create Account</a>
                <a href="/auth/login.php" class="btn btn-trip-outline" style="padding:7px 16px; font-size:13px;">Sign In</a>
            </div>
        </div>
    </div>

</div>

<?php include '../layout/footer.php'; ?>
