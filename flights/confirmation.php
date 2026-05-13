<?php
session_start();
require_once '../config/db.php';
require_once '../config/airports.php';

if (($_SESSION['role'] ?? '') === 'admin') {
    header('Location: /admin/dashboard.php');
    exit();
}

$ref         = $_GET['ref']   ?? '';
$coinsEarned = (int)($_GET['coins'] ?? 0);
if (!$ref) {
    header('Location: /index.php');
    exit();
}

// Fetch all booking detail rows; f.Flght_ID added to enable leg grouping
$stmt = $conn->prepare("
    SELECT bk.Book_ID, bk.Book_Confirm, bk.Book_Date, bk.Book_Status, bk.Book_Total, bk.Book_Pay,
           bd.Bokde_ID, bd.Bokde_Passenger, bd.Bokde_SeatClass, bd.Bokde_Ticket, bd.Bokde_SeatNo,
           f.Flght_ID, f.Flght_No, f.Flght_DepartDate, f.Flght_ArriveDate, f.Flght_Depart, f.Flght_Arrival,
           a.Airln_Name, a.Airln_Code,
           py.Paymt_Method, py.Paymt_Transaction
    FROM booking bk
    JOIN bookingdetails bd ON bd.Bokde_BookID  = bk.Book_ID
    JOIN flight f          ON f.Flght_ID       = bd.Bokde_FlghtID
    JOIN airliner a        ON a.Airln_ID       = f.Flght_AirlnID
    LEFT JOIN payment py   ON py.Paymt_BookID  = bk.Book_ID
    WHERE bk.Book_Confirm = ?
    ORDER BY f.Flght_DepartDate ASC, bd.Bokde_ID ASC
");
$stmt->bind_param('s', $ref);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($rows)) {
    header('Location: /index.php');
    exit();
}

$booking     = $rows[0];
$isCancelled = $booking['Book_Status'] === 'CANCELLED';

// Group rows by flight leg (Flght_ID), preserving departure-date order
$legs = [];
foreach ($rows as $row) {
    $fId = $row['Flght_ID'];
    if (!isset($legs[$fId])) {
        $legs[$fId] = ['flight' => $row, 'passengers' => []];
    }
    // Only add unique passengers per leg (one row per passenger per flight)
    $legs[$fId]['passengers'][] = $row;
}
$legList     = array_values($legs);
$isRoundTrip = count($legList) > 1;

// Unique passenger names (for the count display)
$uniquePassengers = array_unique(array_column($rows, 'Bokde_Passenger'));

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

<style>
/* ── Reference card ── */
.ref-card {
    border-radius: var(--radius-lg);
    padding: 32px 28px;
    margin-bottom: 28px;
    text-align: center;
    color: #fff;
    position: relative;
    overflow: hidden;
}
.ref-card::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at top left, rgba(255,255,255,.07) 0%, transparent 60%);
    pointer-events: none;
}
.ref-code {
    font-size: 32px; font-weight: 800; letter-spacing: 5px;
    margin: 10px 0 8px; font-family: var(--font-sans);
}
.ref-meta { font-size: 13px; opacity: .65; }

/* ── Flight leg card ── */
.leg-card {
    background: #fff; border-radius: var(--radius-lg);
    border: 1px solid var(--trip-border); box-shadow: var(--shadow-sm);
    padding: 22px 24px; margin-bottom: 16px;
    border-left: 4px solid var(--trip-blue);
}
.leg-card.ret { border-left-color: var(--trip-orange); }
.leg-card.cancelled { border-left-color: #DC2626; }

.leg-label {
    font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .7px;
    color: var(--trip-muted); margin-bottom: 16px;
    display: flex; align-items: center; gap: 7px;
}

.leg-time { font-size: 28px; font-weight: 800; color: var(--trip-text); letter-spacing: -.5px; line-height: 1; }
.leg-iata { font-size: 13px; font-weight: 700; color: var(--trip-muted); margin-top: 3px; }
.leg-city { font-size: 12px; color: var(--trip-muted); }
.leg-dur  { font-size: 12px; color: var(--trip-muted); text-align: center; margin-bottom: 5px; }
.leg-line {
    height: 2px; background: var(--trip-blue); border-radius: 2px;
    position: relative; margin: 0 8px;
}
.leg-card.ret .leg-line { background: var(--trip-orange); }
.leg-plane {
    position: absolute; right: -5px; top: -9px;
    font-size: 14px; color: var(--trip-blue);
}
.leg-card.ret .leg-plane { color: var(--trip-orange); }

.leg-meta { font-size: 13px; color: var(--trip-muted); margin-top: 14px; display: flex; gap: 16px; flex-wrap: wrap; }

/* ── Pax table ── */
.pax-table-wrap { margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--trip-border); }
.pax-table-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .7px; color: var(--trip-muted); margin-bottom: 10px; }

/* ── Total card ── */
.total-card {
    background: var(--trip-navy); color: #fff;
    border-radius: var(--radius-lg); padding: 22px 24px; margin-bottom: 16px;
}
.total-card.cancelled { background: #1a0404; }
.total-card-title { font-size: 13px; color: rgba(255,255,255,.5); margin-bottom: 4px; }
.total-card-amount { font-size: 36px; font-weight: 900; color: var(--trip-orange); letter-spacing: -.04em; }
.total-card-sub { font-size: 12px; color: rgba(255,255,255,.4); margin-top: 4px; }

/* ── Info grid ── */
.info-grid { display: flex; flex-direction: column; gap: 10px; }
.info-item { display: flex; gap: 10px; align-items: flex-start; font-size: 13px; color: var(--trip-muted); }
.info-item i { flex-shrink: 0; margin-top: 2px; font-size: 14px; }
.info-item strong { color: var(--trip-text); }
</style>

<div class="container py-5" style="max-width:800px;">

    <!-- Status Banner -->
    <div class="text-center mb-4">
        <?php if ($isCancelled): ?>
            <div style="width:72px;height:72px;background:#FEF2F2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:32px;color:#DC2626;">
                <i class="bi bi-x-circle-fill"></i>
            </div>
            <h3 style="font-family:var(--font-serif); font-weight:600; letter-spacing:-0.02em; color:#DC2626; margin-bottom:6px;">Booking Cancelled</h3>
            <p class="text-muted" style="font-size:15px; max-width:460px; margin:0 auto;">
                This booking has been cancelled. A refund has been initiated and may take a few business days.
            </p>
        <?php else: ?>
            <div style="width:72px;height:72px;background:#F0FDF4;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:32px;color:#16A34A;">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <h3 style="font-family:var(--font-serif); font-weight:600; letter-spacing:-0.02em; color:#16A34A; margin-bottom:6px;">Booking Confirmed</h3>
            <p class="text-muted" style="font-size:15px; max-width:460px; margin:0 auto;">
                <?= $isRoundTrip ? 'Your round trip has been booked successfully. Have a great journey!' : 'Your flight has been booked. Have a great trip!' ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- Reference Card -->
    <div class="ref-card" style="background:<?= $isCancelled ? 'linear-gradient(135deg,#2d1212,#5c2020)' : 'linear-gradient(135deg,var(--trip-navy),#1a3a6a)' ?>;">
        <div style="font-size:11px; font-weight:700; opacity:.55; text-transform:uppercase; letter-spacing:.12em;">Booking Reference</div>
        <div class="ref-code"><?= htmlspecialchars($booking['Book_Confirm']) ?></div>
        <?php if ($isCancelled): ?>
        <div style="display:inline-block; background:rgba(220,38,38,.35); border:1px solid rgba(220,38,38,.4); border-radius:999px; padding:3px 18px; font-size:11px; font-weight:700; letter-spacing:1.5px; margin-bottom:10px;">
            CANCELLED
        </div><br>
        <?php endif; ?>
        <div class="ref-meta">
            Booked <?= date('d M Y, H:i', strtotime($booking['Book_Date'])) ?>
            &nbsp;·&nbsp;
            <?= $isRoundTrip ? 'Round Trip' : 'One Way' ?>
            &nbsp;·&nbsp;
            <?= htmlspecialchars($booking['Paymt_Method'] ?? '—') ?>
        </div>
    </div>

    <!-- Flight Legs -->
    <?php foreach ($legList as $legIndex => $leg):
        $f        = $leg['flight'];
        $legPax   = $leg['passengers'];
        $fromCity = airportCity($f['Flght_Depart']);
        $toCity   = airportCity($f['Flght_Arrival']);
        $isRet    = $isRoundTrip && $legIndex > 0;
        $legClass = $isCancelled ? 'cancelled' : ($isRet ? 'ret' : '');
        $accentColor = $isCancelled ? '#DC2626' : ($isRet ? 'var(--trip-orange)' : 'var(--trip-blue)');
    ?>
    <div class="leg-card <?= $legClass ?>">
        <div class="leg-label">
            <?php if ($isRoundTrip): ?>
                <i class="bi bi-<?= $isRet ? 'arrow-return-left' : 'airplane' ?>" style="color:<?= $accentColor ?>;"></i>
                <?= $isRet ? 'Return Flight' : 'Outbound Flight' ?>
            <?php else: ?>
                <i class="bi bi-airplane" style="color:<?= $accentColor ?>;"></i>
                Flight Details
            <?php endif; ?>
        </div>

        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div style="min-width:72px;">
                <div style="font-size:18px; font-weight:800; color:<?= $accentColor ?>; letter-spacing:-.5px;"><?= htmlspecialchars($f['Airln_Code']) ?></div>
                <div style="font-size:11px; color:var(--trip-muted); margin-top:2px;"><?= htmlspecialchars($f['Airln_Name']) ?><br><?= htmlspecialchars($f['Flght_No']) ?></div>
            </div>
            <div class="d-flex align-items-center flex-grow-1 gap-2">
                <div class="text-center">
                    <div class="leg-time"><?= date('H:i', strtotime($f['Flght_DepartDate'])) ?></div>
                    <div class="leg-iata"><?= htmlspecialchars($f['Flght_Depart']) ?></div>
                    <div class="leg-city"><?= htmlspecialchars($fromCity) ?></div>
                </div>
                <div style="flex:1; min-width:60px; text-align:center;">
                    <div class="leg-dur"><?= flightDuration($f['Flght_DepartDate'], $f['Flght_ArriveDate']) ?></div>
                    <div class="leg-line">
                        <i class="bi bi-airplane-fill leg-plane" style="color:<?= $accentColor ?>;"></i>
                    </div>
                    <div style="margin-top:6px; display:inline-block; background:<?= $isRet ? '#FFF3E5' : '#EFF6FF' ?>; color:<?= $accentColor ?>; border-radius:999px; font-size:10px; font-weight:700; padding:2px 8px;">Non-stop</div>
                </div>
                <div class="text-center">
                    <div class="leg-time"><?= date('H:i', strtotime($f['Flght_ArriveDate'])) ?></div>
                    <div class="leg-iata"><?= htmlspecialchars($f['Flght_Arrival']) ?></div>
                    <div class="leg-city"><?= htmlspecialchars($toCity) ?></div>
                </div>
            </div>
        </div>

        <div class="leg-meta">
            <span><i class="bi bi-calendar3 me-1"></i><?= date('D, d M Y', strtotime($f['Flght_DepartDate'])) ?></span>
            <span><i class="bi bi-gem me-1"></i><?= classLabel($f['Bokde_SeatClass']) ?></span>
            <span><i class="bi bi-people me-1"></i><?= count($legPax) ?> passenger<?= count($legPax) > 1 ? 's' : '' ?></span>
        </div>

        <div class="pax-table-wrap">
            <div class="pax-table-label">Passengers</div>
            <div class="table-responsive">
                <table class="table trip-table mb-0">
                    <thead>
                        <tr><th>#</th><th>Name</th><th>Class</th><th class="text-end">Ticket Fare</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($legPax as $pi => $p): ?>
                        <tr>
                            <td><?= $pi + 1 ?></td>
                            <td><?= htmlspecialchars($p['Bokde_Passenger']) ?></td>
                            <td><?= classLabel($p['Bokde_SeatClass']) ?></td>
                            <td class="text-end price-text fw-bold">₱<?= number_format($p['Bokde_Ticket'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Total -->
    <div class="total-card <?= $isCancelled ? 'cancelled' : '' ?> mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <div class="total-card-title"><?= $isCancelled ? 'Total Amount (Refunded)' : 'Total Amount Paid' ?></div>
                <div class="total-card-amount">₱<?= number_format($booking['Book_Total'], 2) ?></div>
                <div class="total-card-sub">
                    <?php
                    $payBadge = match($booking['Book_Pay']) {
                        'REFUNDED' => 'badge-trip-red',
                        'PAID'     => 'badge-trip-green',
                        default    => 'badge-trip-orange',
                    };
                    ?>
                    <span class="badge <?= $payBadge ?>"><?= htmlspecialchars($booking['Book_Pay']) ?></span>
                    <?php if ($booking['Paymt_Transaction']): ?>
                    &nbsp;· <?= htmlspecialchars($booking['Paymt_Transaction']) ?>
                    <?php endif; ?>
                    <?php if ($isRoundTrip): ?>
                    &nbsp;· Outbound + Return · <?= count($uniquePassengers) ?> pax
                    <?php endif; ?>
                </div>
            </div>
            <div style="width:56px; height:56px; border-radius:14px; background:rgba(255,255,255,.07); display:flex; align-items:center; justify-content:center; font-size:22px; color:var(--trip-orange);">
                <i class="bi bi-receipt"></i>
            </div>
        </div>
    </div>

    <!-- Trip Coins -->
    <?php if ($coinsEarned > 0): ?>
    <div class="mb-4 d-flex align-items-center gap-3" style="background:linear-gradient(135deg,#fff8f4,#fff3ec); border:1px solid rgba(240,96,32,.15); border-left:4px solid var(--trip-orange); border-radius:var(--radius-lg); padding:20px 22px;">
        <div style="width:48px; height:48px; border-radius:12px; background:rgba(240,96,32,.10); display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:22px; color:var(--trip-orange);">
            <i class="bi bi-coin"></i>
        </div>
        <div>
            <div style="font-family:var(--font-serif); font-size:16px; font-weight:600; color:var(--trip-orange); margin-bottom:4px;">+<?= $coinsEarned ?> Trip Coins Earned</div>
            <div style="font-size:13px; color:var(--trip-muted);">
                Credited after your trip. Check your balance on your <a href="/user/profile.php" style="color:var(--trip-blue); font-weight:500;">profile page</a>.
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Reminders -->
    <?php if ($isCancelled): ?>
    <div class="trip-card p-4 mb-4" style="border-left:4px solid #DC2626;">
        <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.7px; color:#DC2626; margin-bottom:14px;">Cancellation Details</div>
        <div class="info-grid">
            <div class="info-item"><i class="bi bi-arrow-counterclockwise" style="color:#DC2626;"></i><div>Your refund has been initiated. It may take <strong>3–7 business days</strong> to reflect in your original payment method.</div></div>
            <div class="info-item"><i class="bi bi-hash" style="color:var(--trip-blue);"></i><div>Quote reference <strong><?= htmlspecialchars($booking['Book_Confirm']) ?></strong> when following up with customer support.</div></div>
            <div class="info-item"><i class="bi bi-airplane" style="color:var(--trip-blue);"></i><div>Looking to rebook? Search for available flights and we'll have you on your way again.</div></div>
        </div>
    </div>
    <?php else: ?>
    <div class="trip-card p-4 mb-4">
        <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.7px; color:var(--trip-muted); margin-bottom:14px;">Important Reminders</div>
        <div class="row g-3">
            <div class="col-md-6 info-item"><i class="bi bi-hash" style="color:var(--trip-blue);"></i><div>Quote reference <strong><?= htmlspecialchars($booking['Book_Confirm']) ?></strong> in all communications with customer support.</div></div>
            <div class="col-md-6 info-item"><i class="bi bi-person-badge" style="color:var(--trip-orange);"></i><div>Passenger names must match their <strong>government-issued ID or passport</strong> exactly.</div></div>
            <div class="col-md-6 info-item"><i class="bi bi-sort-numeric-down" style="color:#16A34A;"></i><div>Tickets must be used <strong>in the sequence</strong> listed. Skipping a segment may void subsequent flights.</div></div>
            <div class="col-md-6 info-item"><i class="bi bi-arrow-counterclockwise" style="color:var(--trip-blue);"></i><div>Cancellations are subject to airline fare rules. Refunds may take several business days.</div></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="d-flex gap-3 justify-content-center flex-wrap">
        <a href="/user/dashboard.php" class="btn btn-trip px-4 py-2">
            <i class="bi bi-ticket-detailed me-2"></i>View My Bookings
        </a>
        <a href="/index.php" class="btn btn-trip-outline px-4 py-2">
            <i class="bi bi-airplane me-2"></i>Book Another Flight
        </a>
        <button onclick="window.print()" class="btn btn-outline-secondary px-4 py-2">
            <i class="bi bi-printer me-2"></i>Print
        </button>
    </div>

</div>

<?php include '../layout/footer.php'; ?>
