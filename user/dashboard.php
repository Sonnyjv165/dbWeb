<?php
session_start();
require_once '../config/db.php';
require_once '../config/airports.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit();
}
if (($_SESSION['role'] ?? '') === 'admin') {
    header('Location: /admin/dashboard.php');
    exit();
}

$userId = $_SESSION['user_id'];

// Cancel booking
if (isset($_POST['cancel_booking'])) {
    $bId = (int)$_POST['booking_id'];

    // Verify ownership; get one row per flight leg so all seats are restored on cancel
    $get = $conn->prepare("
        SELECT bk.Book_ID, bk.Book_Status, bd.Bokde_FlghtID,
               COUNT(DISTINCT bd.Bokde_Passenger) AS pax_count
        FROM booking bk
        JOIN bookingdetails bd ON bd.Bokde_BookID = bk.Book_ID
        WHERE bk.Book_ID = ? AND bk.Book_UserID = ?
        GROUP BY bk.Book_ID, bk.Book_Status, bd.Bokde_FlghtID
    ");
    $get->bind_param('ii', $bId, $userId);
    $get->execute();
    $legRows = $get->get_result()->fetch_all(MYSQLI_ASSOC);

    if (!empty($legRows) && $legRows[0]['Book_Status'] === 'CONFIRMED') {
        $conn->begin_transaction();
        try {
            $conn->query("UPDATE booking SET Book_Status='CANCELLED', Book_Pay='REFUNDED' WHERE Book_ID=$bId");
            // Restore seats for every flight leg (handles both one-way and round trip)
            foreach ($legRows as $leg) {
                $conn->query("UPDATE flight SET Flght_SeatAvail = Flght_SeatAvail + {$leg['pax_count']} WHERE Flght_ID = {$leg['Bokde_FlghtID']}");
            }
            $conn->query("UPDATE payment SET Paymt_Status='REFUNDED' WHERE Paymt_BookID=$bId");
            $conn->commit();
            $_SESSION['flash_success'] = 'Booking cancelled successfully.';
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_error'] = 'Cancellation failed. Please try again.';
        }
    }
    header('Location: /user/dashboard.php');
    exit();
}

// Fetch user's bookings — one row per booking, showing outbound (earliest) leg route
$stmt = $conn->prepare("
    SELECT bk.Book_ID, bk.Book_Confirm, bk.Book_Date, bk.Book_Status, bk.Book_Total, bk.Book_Pay,
           fl.Flght_No, fl.Flght_DepartDate, fl.Flght_ArriveDate, fl.Flght_Depart, fl.Flght_Arrival,
           al.Airln_Name, al.Airln_Code,
           MIN(bd.Bokde_SeatClass)         AS Bokde_SeatClass,
           COUNT(DISTINCT bd.Bokde_Passenger) AS pax_count,
           COUNT(DISTINCT bd.Bokde_FlghtID)   AS leg_count
    FROM booking bk
    JOIN bookingdetails bd ON bd.Bokde_BookID = bk.Book_ID
    JOIN flight fl ON fl.Flght_ID = (
        SELECT bd2.Bokde_FlghtID
        FROM bookingdetails bd2
        JOIN flight f2 ON f2.Flght_ID = bd2.Bokde_FlghtID
        WHERE bd2.Bokde_BookID = bk.Book_ID
        ORDER BY f2.Flght_DepartDate ASC
        LIMIT 1
    )
    JOIN airliner al ON al.Airln_ID = fl.Flght_AirlnID
    WHERE bk.Book_UserID = ?
    GROUP BY bk.Book_ID, fl.Flght_ID
    ORDER BY bk.Book_Date DESC
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$myBookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function classLabel($c) { return match(strtolower($c)) { 'business' => 'Business', 'first' => 'First Class', default => 'Economy' }; }

// Compute counts for filter tabs
$tabCounts = ['all' => 0, 'upcoming' => 0, 'completed' => 0, 'cancelled' => 0];
foreach ($myBookings as $b) {
    $tabCounts['all']++;
    if ($b['Book_Status'] === 'CANCELLED') {
        $tabCounts['cancelled']++;
    } elseif (strtotime($b['Flght_DepartDate']) < time()) {
        $tabCounts['completed']++;
    } else {
        $tabCounts['upcoming']++;
    }
}

$title = 'My Bookings';
include '../layout/layout.php';
?>

<style>
/* ── Scroll-reveal ── */
.reveal {
    opacity: 0;
    transform: translateY(24px);
    transition: opacity 0.40s ease, transform 0.40s ease;
    transition-delay: var(--reveal-delay, 0s);
}
.reveal.is-visible { opacity: 1; transform: translateY(0); }
.reveal:not(.is-visible) { transition-duration: 0.20s; transition-delay: 0s; }

.reveal-instant { animation: revealSlideUp 0.48s ease both; }
@keyframes revealSlideUp {
    from { opacity: 0; transform: translateY(18px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ── Hero header card ── */
.dash-hero {
    background: linear-gradient(135deg, var(--trip-navy) 0%, #1a3a6a 100%);
    border-radius: var(--radius-lg);
    padding: 28px 32px;
    margin-bottom: 24px;
    color: #fff;
    position: relative; overflow: hidden;
}
.dash-hero::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at top right, rgba(255,255,255,.06) 0%, transparent 60%);
    pointer-events: none;
}
.dash-hero h4 { font-family: var(--font-serif); font-size: 22px; font-weight: 600; letter-spacing: -.02em; color: #fff; margin-bottom: 4px; }
.dash-hero .coins-pill {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(255,255,255,.10); border: 1px solid rgba(255,255,255,.15);
    border-radius: 999px; padding: 4px 14px; font-size: 13px; font-weight: 600; color: #fff;
}
.dash-hero .tier-link {
    font-size: 12px; color: rgba(255,255,255,.65); text-decoration: none;
    display: inline-flex; align-items: center; gap: 4px;
}
.dash-hero .tier-link:hover { color: #fff; }

/* ── Filter tabs ── */
.tab-pill {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 18px; border-radius: 24px; font-size: 13px; font-weight: 600;
    border: 1.5px solid var(--trip-border); background: #fff; color: var(--trip-muted);
    cursor: pointer; transition: all .15s;
}
.tab-pill.active { background: var(--trip-blue); color: #fff; border-color: var(--trip-blue); box-shadow: 0 2px 10px rgba(0,119,238,.25); }
.tab-pill .tab-count {
    background: rgba(255,255,255,.25); border-radius: 20px;
    padding: 0 7px; font-size: 11px;
}
.tab-pill:not(.active) .tab-count { background: #F0F0F0; color: #999; }

/* ── Booking card ── */
.booking-card {
    background: #fff; border-radius: var(--radius-lg);
    border: 1px solid var(--trip-border); box-shadow: var(--shadow-sm);
    overflow: hidden; margin-bottom: 12px;
    transition: box-shadow .15s, transform .15s;
}
.booking-card:hover { box-shadow: var(--shadow-md); transform: translateY(-1px); }
.bk-accent { width: 5px; flex-shrink: 0; }
.bk-body { padding: 18px 22px; }
.bk-ref-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--trip-muted); }
.bk-ref-code  { font-size: 16px; font-weight: 800; letter-spacing: 2px; color: var(--trip-text); line-height: 1.2; }
.bk-route     { font-size: 18px; font-weight: 800; color: var(--trip-text); letter-spacing: -.3px; }
.bk-route-sub { font-size: 12px; color: var(--trip-muted); margin-top: 1px; }
.bk-meta      { font-size: 13px; color: var(--trip-muted); display: flex; gap: 14px; flex-wrap: wrap; margin-top: 8px; }
.bk-price     { font-size: 20px; font-weight: 800; color: var(--trip-orange); letter-spacing: -.03em; }
</style>

<div class="container py-4">

    <?php
    $loyaltyRow = $conn->query("SELECT User_Loyalty FROM user WHERE User_ID=$userId")->fetch_assoc();
    $userCoins  = (int)($loyaltyRow['User_Loyalty'] ?? 0);
    $tierNames  = [[20000,'Black Diamond'],[10000,'Diamond+'],[5000,'Diamond'],[2000,'Platinum'],[500,'Gold'],[0,'Silver']];
    $tierIcons  = ['Black Diamond'=>'bi-gem','Diamond+'=>'bi-gem','Diamond'=>'bi-gem','Platinum'=>'bi-award','Gold'=>'bi-star-fill','Silver'=>'bi-star-half'];
    $userTier   = 'Silver';
    foreach ($tierNames as [$t,$n]) { if ($userCoins >= $t) { $userTier = $n; break; } }
    ?>
    <div class="dash-hero reveal-instant">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4>Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?></h4>
                <div class="d-flex align-items-center gap-3 flex-wrap mt-2">
                    <span class="coins-pill">
                        <i class="bi bi-coin" style="color:var(--trip-orange);"></i>
                        <?= number_format($userCoins) ?> Trip Coins
                    </span>
                    <a href="/user/profile.php" class="tier-link">
                        <i class="bi <?= $tierIcons[$userTier] ?>" style="color:var(--trip-orange);"></i>
                        <?= $userTier ?> Member ›
                    </a>
                </div>
            </div>
            <a href="/index.php" class="btn btn-trip-orange px-4">
                <i class="bi bi-airplane me-2"></i>Book a Flight
            </a>
        </div>
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

    <?php if (!empty($myBookings)): ?>
    <!-- Filter Tabs -->
    <div class="d-flex gap-2 mb-4 flex-wrap reveal-instant" style="animation-delay:.08s;" id="bookingTabs">
        <button class="tab-pill active" onclick="filterBookings('all',this)">
            All <span class="tab-count"><?= $tabCounts['all'] ?></span>
        </button>
        <button class="tab-pill" onclick="filterBookings('upcoming',this)">
            <i class="bi bi-airplane-fill" style="font-size:11px;"></i>
            Upcoming <span class="tab-count"><?= $tabCounts['upcoming'] ?></span>
        </button>
        <button class="tab-pill" onclick="filterBookings('completed',this)">
            <i class="bi bi-check-circle-fill" style="font-size:11px;"></i>
            Completed <span class="tab-count"><?= $tabCounts['completed'] ?></span>
        </button>
        <button class="tab-pill" onclick="filterBookings('cancelled',this)">
            <i class="bi bi-x-circle-fill" style="font-size:11px;"></i>
            Cancelled <span class="tab-count"><?= $tabCounts['cancelled'] ?></span>
        </button>
    </div>
    <?php endif; ?>

    <?php if (empty($myBookings)): ?>
        <div class="trip-card p-5 text-center text-muted reveal" style="--reveal-delay:.05s;">
            <div style="font-size:56px; opacity:.15; color:var(--trip-text);"><i class="bi bi-airplane"></i></div>
            <h5 class="mt-3">No bookings yet</h5>
            <p style="font-size:14px;">Search and book your first flight to get started.</p>
            <a href="/index.php" class="btn btn-trip mt-2">Search Flights</a>
        </div>
    <?php else: ?>
        <?php $bi = 0; foreach ($myBookings as $b):
            $bi++;
            $statusClass = match($b['Book_Status']) {
                'CONFIRMED' => 'badge-trip-green',
                'CANCELLED' => 'badge-trip-red',
                default     => 'badge-trip-orange',
            };
            $accentColor = match($b['Book_Status']) {
                'CONFIRMED' => '#16A34A',
                'CANCELLED' => '#DC2626',
                default     => 'var(--trip-orange)',
            };
            $isPast = strtotime($b['Flght_DepartDate']) < time();
            $fromCity = airportCity($b['Flght_Depart']);
            $toCity   = airportCity($b['Flght_Arrival']);
            $bookingCategory = $b['Book_Status'] === 'CANCELLED' ? 'cancelled' : ($isPast ? 'completed' : 'upcoming');
            $stagger = round(min(($bi - 1) * 0.07, 0.28), 2);
        ?>
        <div class="booking-card reveal"
             data-category="<?= $bookingCategory ?>"
             style="--reveal-delay:<?= $stagger ?>s;">
            <div class="d-flex">
                <div class="bk-accent" style="background:<?= $accentColor ?>;"></div>
                <div class="bk-body flex-grow-1">
                    <div class="row align-items-center g-3">

                        <!-- Route -->
                        <div class="col-md-4">
                            <div class="d-flex align-items-center gap-3">
                                <div style="font-size:20px; font-weight:800; color:var(--trip-blue); min-width:44px;">
                                    <?= htmlspecialchars($b['Airln_Code']) ?>
                                </div>
                                <div>
                                    <div class="bk-route">
                                        <?= htmlspecialchars($fromCity) ?> → <?= htmlspecialchars($toCity) ?>
                                        <?php if (($b['leg_count'] ?? 1) > 1): ?>
                                            <span class="badge badge-trip-orange ms-1" style="font-size:10px; vertical-align:middle; padding:2px 7px;">RT</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="bk-route-sub">
                                        <?= $b['Flght_Depart'] ?> → <?= $b['Flght_Arrival'] ?>
                                        &nbsp;·&nbsp;<?= htmlspecialchars($b['Flght_No']) ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Date/time -->
                        <div class="col-md-3">
                            <div style="font-size:14px; font-weight:600; color:var(--trip-text);">
                                <i class="bi bi-calendar3 me-1" style="color:var(--trip-muted);"></i>
                                <?= date('D, d M Y', strtotime($b['Flght_DepartDate'])) ?>
                            </div>
                            <div class="bk-meta" style="margin-top:4px;">
                                <span><?= date('H:i', strtotime($b['Flght_DepartDate'])) ?> → <?= date('H:i', strtotime($b['Flght_ArriveDate'])) ?></span>
                                <span><?= classLabel($b['Bokde_SeatClass']) ?></span>
                                <span><?= $b['pax_count'] ?> pax</span>
                            </div>
                        </div>

                        <!-- Ref + status -->
                        <div class="col-md-2">
                            <div class="bk-ref-label">Ref</div>
                            <div class="bk-ref-code"><?= htmlspecialchars($b['Book_Confirm']) ?></div>
                            <span class="badge <?= $statusClass ?> mt-1" style="font-size:11px; padding:3px 8px;">
                                <?= ucfirst(strtolower($b['Book_Status'])) ?>
                            </span>
                        </div>

                        <!-- Price + actions -->
                        <div class="col-md-3 text-end">
                            <div class="bk-price">₱<?= number_format($b['Book_Total'], 2) ?></div>
                            <div class="d-flex gap-2 justify-content-end mt-2 flex-wrap">
                                <a href="/flights/confirmation.php?ref=<?= urlencode($b['Book_Confirm']) ?>"
                                   class="btn btn-sm btn-trip" style="font-size:12px; padding:5px 14px;">View</a>
                                <?php if ($b['Book_Status'] === 'CONFIRMED' && !$isPast): ?>
                                    <form method="POST" onsubmit="return confirm('Cancel this booking?')">
                                        <input type="hidden" name="booking_id" value="<?= $b['Book_ID'] ?>">
                                        <button type="submit" name="cancel_booking"
                                                class="btn btn-sm btn-outline-danger" style="font-size:12px; padding:5px 14px;">
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

<script>
// ── Scroll-reveal observer ──────────────────────────────────────
(function () {
    const obs = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
            } else {
                entry.target.classList.remove('is-visible');
            }
        });
    }, { threshold: 0.08 });

    document.querySelectorAll('.reveal').forEach(el => obs.observe(el));
})();

// ── Filter tabs ─────────────────────────────────────────────────
function filterBookings(category, btn) {
    document.querySelectorAll('#bookingTabs .tab-pill').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    document.querySelectorAll('.booking-card').forEach(card => {
        const show = category === 'all' || card.dataset.category === category;
        if (!show) {
            card.style.display = 'none';
            card.classList.remove('is-visible'); // reset so it re-animates when shown again
        } else {
            card.style.display = '';
            // IntersectionObserver will fire is-visible if card is in viewport
        }
    });

    // Show empty state if nothing visible
    const visible = document.querySelectorAll('.booking-card:not([style*="display: none"]):not([style*="display:none"])').length;
    let empty = document.getElementById('emptyFilter');
    if (!empty) {
        empty = document.createElement('div');
        empty.id = 'emptyFilter';
        empty.className = 'trip-card p-4 text-center text-muted reveal is-visible';
        empty.style.fontSize = '14px';
        empty.innerHTML = '<i class="bi bi-inbox" style="font-size:32px; opacity:.3; display:block; margin-bottom:8px;"></i>No bookings in this category.';
        document.querySelector('.booking-card')?.parentNode?.appendChild(empty);
    }
    empty.style.display = visible === 0 ? '' : 'none';
}
</script>

<?php include '../layout/footer.php'; ?>
