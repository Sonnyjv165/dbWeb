<?php
session_start();
require_once '../config/db.php';
require_once '../config/airports.php';

if (($_SESSION['role'] ?? '') === 'admin') {
    header('Location: /admin/dashboard.php');
    exit();
}
$isGuest = !isset($_SESSION['user_id']);

$flightId  = (int)($_GET['flight_id']  ?? 0);
$returnId  = (int)($_GET['return_id']  ?? 0);
$passengers = max(1, min(9, (int)($_GET['passengers'] ?? 1)));
$class     = in_array($_GET['class'] ?? '', ['economy','business','first']) ? $_GET['class'] : 'economy';

$multiplier = match($class) { 'business' => 2.5, 'first' => 4.0, default => 1.0 };
$seatClass  = strtoupper($class);

// Fetch outbound flight
$stmt = $conn->prepare("
    SELECT f.*, a.Airln_Name, a.Airln_Code
    FROM flight f
    JOIN airliner a ON f.Flght_AirlnID = a.Airln_ID
    WHERE f.Flght_ID = ? AND f.Flght_Status = 'SCHEDULED'
    LIMIT 1
");
$stmt->bind_param('i', $flightId);
$stmt->execute();
$flight = $stmt->get_result()->fetch_assoc();

if (!$flight) {
    header('Location: /flights/search.php');
    exit();
}

// Fetch return flight (round trip)
$returnFlight = null;
if ($returnId > 0) {
    $stmt2 = $conn->prepare("
        SELECT f.*, a.Airln_Name, a.Airln_Code
        FROM flight f
        JOIN airliner a ON f.Flght_AirlnID = a.Airln_ID
        WHERE f.Flght_ID = ? AND f.Flght_Status = 'SCHEDULED'
        LIMIT 1
    ");
    $stmt2->bind_param('i', $returnId);
    $stmt2->execute();
    $returnFlight = $stmt2->get_result()->fetch_assoc();
}

$isRoundTrip    = ($returnFlight !== null);
$outPricePerPax = (float)$flight['Flght_Fare'] * $multiplier;
$retPricePerPax = $isRoundTrip ? (float)$returnFlight['Flght_Fare'] * $multiplier : 0.0;
$pricePerPax    = $outPricePerPax + $retPricePerPax;
$totalPrice     = $pricePerPax * $passengers;

$fromCity = airportCity($flight['Flght_Depart']);
$toCity   = airportCity($flight['Flght_Arrival']);
$error    = '';
$discount = 0.0;
$promoId  = null;

// Business rule: cannot book a flight that departs within 2 hours
$departIn = strtotime($flight['Flght_DepartDate']) - time();
if ($departIn < 7200) {
    $_SESSION['flash_error'] = 'This flight departs within 2 hours and can no longer be booked online. Please contact the airline directly.';
    header('Location: /flights/search.php');
    exit();
}

// ── Handle Booking Submission ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_booking'])) {

    $payMethod  = trim($_POST['pay_method'] ?? 'Credit Card');
    $promoCode  = strtoupper(trim($_POST['promo_code'] ?? ''));
    $finalTotal = $totalPrice;

    // Validate promo code
    if ($promoCode !== '') {
        $pr = $conn->prepare("
            SELECT * FROM promotion
            WHERE Promo_Code = ?
              AND NOW() BETWEEN Promo_ValidFrom AND Promo_ValidTo
            LIMIT 1
        ");
        $pr->bind_param('s', $promoCode);
        $pr->execute();
        $promo = $pr->get_result()->fetch_assoc();

        if ($promo) {
            $promoId = $promo['Promo_ID'];
            if ($promo['Promo_DiscountType'] === 'PERCENTAGE') {
                $discount = $totalPrice * ($promo['Promo_Value'] / 100);
            } else {
                $discount = min((float)$promo['Promo_Value'], $totalPrice);
            }
            $finalTotal = max(0, $totalPrice - $discount);
        } else {
            $error = 'Promo code is invalid or has expired.';
        }
    }

    // Resolve booking user ID — guest or logged-in
    $bookUserId = $_SESSION['user_id'] ?? null;
    if ($isGuest && !$error) {
        $guestEmail = strtolower(trim($_POST['guest_email'] ?? ''));
        if (!filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address so we can send your booking details.';
        } else {
            $ex = $conn->prepare("SELECT User_ID FROM user WHERE User_Email = ? LIMIT 1");
            $ex->bind_param('s', $guestEmail);
            $ex->execute();
            $exRow = $ex->get_result()->fetch_assoc();
            if ($exRow) {
                $bookUserId = $exRow['User_ID'];
            } else {
                $guestName = trim($_POST['pax_1_name'] ?? 'Guest');
                $gi = $conn->prepare("INSERT INTO user (User_Name, User_Email, User_Password, User_PhoneNo, User_Loyalty, User_Status, User_Role) VALUES (?, ?, '', '', 0, 'ACTIVE', 'user')");
                $gi->bind_param('ss', $guestName, $guestEmail);
                $gi->execute();
                $bookUserId = $conn->insert_id;
            }
        }
    }

    if (!$error) {
        $conn->begin_transaction();
        try {
            // Lock rows and check seat availability on both flights
            $chk = $conn->prepare("SELECT Flght_SeatAvail FROM flight WHERE Flght_ID = ? FOR UPDATE");
            $chk->bind_param('i', $flightId);
            $chk->execute();
            $outSeats = $chk->get_result()->fetch_assoc()['Flght_SeatAvail'];

            if ($outSeats < $passengers) {
                throw new Exception('Not enough seats on the outbound flight.');
            }

            if ($isRoundTrip) {
                $chk2 = $conn->prepare("SELECT Flght_SeatAvail FROM flight WHERE Flght_ID = ? FOR UPDATE");
                $chk2->bind_param('i', $returnId);
                $chk2->execute();
                $retSeats = $chk2->get_result()->fetch_assoc()['Flght_SeatAvail'];

                if ($retSeats < $passengers) {
                    throw new Exception('Not enough seats on the return flight.');
                }
            }

            // Insert Booking
            $bookConfirm = 'TR' . strtoupper(bin2hex(random_bytes(4)));
            $b = $conn->prepare("
                INSERT INTO booking (Book_UserID, Book_Date, Book_Status, Book_Total, Book_Pay, Book_Confirm, Book_PromoID)
                VALUES (?, NOW(), 'CONFIRMED', ?, 'PAID', ?, ?)
            ");
            $b->bind_param('idsi', $bookUserId, $finalTotal, $bookConfirm, $promoId);
            $b->execute();
            $bookId = $conn->insert_id;

            // Insert Bookingdetails: each passenger × each flight leg
            $bd = $conn->prepare("
                INSERT INTO bookingdetails (Bokde_BookID, Bokde_FlghtID, Bokde_Passenger, Bokde_SeatClass, Bokde_Ticket)
                VALUES (?, ?, ?, ?, ?)
            ");

            $legs = [[$flightId, $outPricePerPax]];
            if ($isRoundTrip) {
                $legs[] = [$returnId, $retPricePerPax];
            }

            for ($i = 1; $i <= $passengers; $i++) {
                $paxName = trim($_POST["pax_{$i}_name"]);
                foreach ($legs as [$legFlightId, $legPrice]) {
                    $bd->bind_param('iissd', $bookId, $legFlightId, $paxName, $seatClass, $legPrice);
                    $bd->execute();
                }
            }

            // Insert Payment
            $txn = 'TXN' . strtoupper(bin2hex(random_bytes(5)));
            $py  = $conn->prepare("
                INSERT INTO payment (Paymt_BookID, Paymt_Method, Paymt_Date, Paymt_Amt, Paymt_Status, Paymt_Transaction)
                VALUES (?, ?, NOW(), ?, 'SUCCESS', ?)
            ");
            $py->bind_param('isds', $bookId, $payMethod, $finalTotal, $txn);
            $py->execute();

            // Decrement seats on outbound (and return) flight
            $conn->query("UPDATE flight SET Flght_SeatAvail = Flght_SeatAvail - $passengers WHERE Flght_ID = $flightId");
            if ($isRoundTrip) {
                $conn->query("UPDATE flight SET Flght_SeatAvail = Flght_SeatAvail - $passengers WHERE Flght_ID = $returnId");
            }

            // Increment promo usage
            if ($promoId) {
                $conn->query("UPDATE promotion SET Promo_Usage = Promo_Usage + 1 WHERE Promo_ID = $promoId");
            }

            // Award Trip Coins: only for logged-in members (not guests), not when promo used
            $coinsEarned = 0;
            if (!$isGuest && !$promoId) {
                $coinsEarned = (int)floor($finalTotal / 100);
                if ($coinsEarned > 0) {
                    $conn->query("UPDATE user SET User_Loyalty = User_Loyalty + $coinsEarned WHERE User_ID = $bookUserId");
                }
            }

            $conn->commit();
            header("Location: /flights/confirmation.php?ref=" . urlencode($bookConfirm) . "&coins=" . ($coinsEarned ?? 0));
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage() ?: 'Booking failed. Please try again.';
        }
    }
}

function flightDuration($dep, $arr) {
    $d1 = new DateTime($dep); $d2 = new DateTime($arr);
    $diff = $d1->diff($d2);
    return ($diff->days > 0 ? $diff->days . 'd ' : '') . $diff->h . 'h ' . ($diff->i > 0 ? $diff->i . 'm' : '');
}
function classLabel($c) { return match($c) { 'business' => 'Business', 'first' => 'First Class', default => 'Economy' }; }

$title = 'Book Flight';
include '../layout/layout.php';
?>

<style>
/* ── Page chrome ── */
.book-crumb { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
.book-crumb a { color:var(--trip-blue); font-size:13px; text-decoration:none; font-weight:500; }
.book-crumb a:hover { text-decoration:underline; }
.book-crumb .sep { color:var(--trip-border); font-size:13px; }
.book-crumb .curr { color:var(--trip-muted); font-size:13px; }

/* ── Section card header ── */
.section-label {
    font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.7px;
    color:var(--trip-muted); margin-bottom:14px; display:flex; align-items:center; gap:7px;
}
.section-label i { font-size:14px; }

/* ── Flight summary card ── */
.flight-summary {
    background:#fff; border-radius:var(--radius-lg); border:1px solid var(--trip-border);
    box-shadow:var(--shadow-sm); padding:20px 24px; margin-bottom:12px;
    border-left:4px solid var(--trip-blue);
    transition:box-shadow .15s;
}
.flight-summary.ret { border-left-color:var(--trip-orange); }
.flight-summary:hover { box-shadow:var(--shadow-md); }

.fs-airline-code { font-size:18px; font-weight:800; color:var(--trip-blue); letter-spacing:-.5px; }
.flight-summary.ret .fs-airline-code { color:var(--trip-orange); }
.fs-airline-sub  { font-size:11px; color:var(--trip-muted); margin-top:1px; }
.fs-time { font-size:22px; font-weight:800; color:var(--trip-text); letter-spacing:-.5px; line-height:1; }
.fs-iata { font-size:12px; font-weight:700; color:var(--trip-muted); margin-top:2px; }
.fs-city { font-size:11px; color:var(--trip-muted); }
.fs-line { height:2px; background:var(--trip-blue); position:relative; flex:1; margin:0 8px; }
.flight-summary.ret .fs-line { background:var(--trip-orange); }
.fs-line::after {
    content:''; position:absolute; right:-6px; top:50%; transform:translateY(-50%);
    width:10px; height:10px; background:currentColor; border-radius:50%;
    color:inherit;
}
.fs-line-plane {
    position:absolute; right:-4px; top:-9px; font-size:13px; color:var(--trip-blue);
}
.flight-summary.ret .fs-line-plane { color:var(--trip-orange); }
.fs-dur { font-size:11px; color:var(--trip-muted); text-align:center; margin-bottom:4px; }
.fs-badge {
    display:inline-block; padding:2px 8px; border-radius:999px; font-size:10px; font-weight:700;
    background:#EFF6FF; color:var(--trip-blue); margin-top:3px;
}
.flight-summary.ret .fs-badge { background:#FFF3E5; color:var(--trip-orange); }

/* ── Policy strip ── */
.policy-strip {
    background:#fff; border-radius:var(--radius-lg); border:1px solid var(--trip-border);
    border-left:4px solid var(--trip-orange); padding:16px 20px; margin-bottom:20px;
    box-shadow:var(--shadow-sm);
}
.policy-item { display:flex; gap:10px; align-items:flex-start; }
.policy-icon { font-size:15px; flex-shrink:0; margin-top:1px; }
.policy-title { font-size:13px; font-weight:700; color:var(--trip-text); }
.policy-desc  { font-size:12px; color:var(--trip-muted); line-height:1.5; margin-top:1px; }

/* ── Guest email notice ── */
.guest-info {
    background:#EFF6FF; border-radius:8px; padding:10px 14px; font-size:12px; color:var(--trip-blue);
    display:flex; align-items:center; gap:8px; margin-top:12px;
    border:1px solid rgba(0,119,238,.12);
}

/* ── Passenger card ── */
.pax-card {
    background:#fff; border-radius:var(--radius-lg); border:1px solid var(--trip-border);
    box-shadow:var(--shadow-sm); padding:22px 24px; margin-bottom:12px;
}
.pax-header {
    display:flex; align-items:center; gap:10px; margin-bottom:18px;
    padding-bottom:14px; border-bottom:1px solid var(--trip-border);
}
.pax-num {
    width:28px; height:28px; border-radius:50%; background:var(--trip-blue);
    color:#fff; font-size:12px; font-weight:700;
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.pax-title { font-size:15px; font-weight:700; color:var(--trip-text); }
.pax-sub   { font-size:12px; color:var(--trip-muted); }

/* ── Payment ── */
.payment-card {
    background:#fff; border-radius:var(--radius-lg); border:1px solid var(--trip-border);
    box-shadow:var(--shadow-sm); padding:22px 24px; margin-bottom:12px;
}
.payment-card-title {
    font-family:var(--font-serif); font-size:17px; font-weight:600;
    letter-spacing:-.02em; color:var(--trip-text); margin-bottom:18px;
}
.pay-methods { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:22px; }
.pay-btn {
    display:flex; align-items:center; gap:7px;
    padding:9px 16px; border-radius:var(--radius-md); cursor:pointer;
    border:1.5px solid var(--trip-border);
    background:#fff; color:var(--trip-text);
    font-size:13px; font-weight:600; font-family:var(--font-sans);
    transition:all .18s cubic-bezier(.32,.72,0,1);
}
.pay-btn:hover { border-color:rgba(0,0,0,.22); background:var(--trip-bg); }
.pay-btn.active {
    border-color:var(--trip-blue); background:var(--trip-blue);
    color:#fff; box-shadow:0 2px 12px rgba(0,119,238,.25);
}
.pay-btn .pay-icon { font-size:15px; line-height:1; }

/* ── Credential fields ── */
.cred-section { display:none; }
.cred-section.active { display:block; }
.cred-label {
    font-size:11px; font-weight:700; letter-spacing:.08em;
    text-transform:uppercase; color:var(--trip-muted); margin-bottom:5px; display:block;
}
.cred-input {
    font-family:var(--font-sans); font-size:14px;
    border:1.5px solid rgba(0,0,0,.12); border-radius:var(--radius-md);
    padding:10px 14px; width:100%; background:#fff; color:var(--trip-text);
    transition:border-color .15s; outline:none;
}
.cred-input:focus { border-color:var(--trip-blue); box-shadow:0 0 0 3px rgba(0,119,238,.10); }
.cred-input.mono { font-family:'SF Mono','Menlo',monospace; letter-spacing:.05em; }
.card-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.security-note {
    display:flex; align-items:center; gap:8px; margin-top:14px;
    background:var(--trip-bg); border-radius:var(--radius-md); padding:10px 14px;
    font-size:12px; color:var(--trip-muted); border:1px solid var(--trip-border);
}
.promo-divider {
    margin-top:18px; padding-top:18px; border-top:1px solid var(--trip-border);
}

/* ── Dark price summary ── */
.dark-summary {
    background:var(--trip-navy);
    border-radius:var(--radius-lg);
    padding:24px;
    position:sticky;
    top:82px;
    color:#fff;
    border:1px solid rgba(255,255,255,.06);
    box-shadow:var(--shadow-lg);
}
.dark-summary-title {
    font-family:var(--font-serif); font-size:17px; font-weight:600;
    letter-spacing:-.02em; color:#fff; margin-bottom:18px;
    padding-bottom:14px; border-bottom:1px solid rgba(255,255,255,.08);
    display:flex; align-items:center; gap:8px;
}
.sum-row {
    display:flex; justify-content:space-between; align-items:baseline;
    font-size:13px; margin-bottom:8px;
}
.sum-row .lbl { color:rgba(255,255,255,.50); }
.sum-row .val { color:#fff; font-weight:600; }
.sum-route { font-size:11px; color:rgba(255,255,255,.35); margin-bottom:2px; }
.sum-divider { border-color:rgba(255,255,255,.10); margin:12px 0; }
.sum-total {
    display:flex; justify-content:space-between; align-items:baseline;
    padding:12px 0; border-top:1px solid rgba(255,255,255,.10); margin-top:4px;
}
.sum-total .lbl { font-size:13px; font-weight:700; color:#fff; }
.sum-total .val { font-size:22px; font-weight:800; color:var(--trip-orange); letter-spacing:-.03em; }
.sum-note { font-size:11px; color:rgba(255,255,255,.35); margin-top:8px; margin-bottom:16px; }
.sum-info-bar {
    background:rgba(255,255,255,.07); border-radius:8px;
    padding:10px 14px; font-size:12px; color:rgba(255,255,255,.65);
    margin-bottom:16px; display:flex; align-items:center; gap:8px;
}
.sum-terms { color:rgba(255,255,255,.30); text-align:center; margin-top:12px; font-size:11px; }
</style>

<div class="container py-4" style="max-width:1100px;">

    <!-- Breadcrumb -->
    <nav class="book-crumb mb-3">
        <a href="/index.php"><i class="bi bi-house-fill me-1"></i>Home</a>
        <span class="sep">/</span>
        <a href="javascript:history.back()">Search Results</a>
        <span class="sep">/</span>
        <span class="curr">Booking</span>
    </nav>

    <?php if ($error): ?>
        <div class="alert alert-danger rounded-3 mb-3 d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($isRoundTrip): ?>
    <div class="mb-3 d-flex align-items-center gap-2" style="background:#EFF6FF; border:1px solid #BFDBFE; border-radius:var(--radius-md); padding:12px 16px; font-size:13px; color:#1E40AF;">
        <i class="bi bi-arrow-left-right" style="font-size:16px;"></i>
        <span><strong>Round Trip Booking</strong> — You're booking both outbound and return flights together.</span>
    </div>
    <?php endif; ?>

    <!-- Policy strip -->
    <div class="policy-strip mb-4">
        <div class="row g-3">
            <div class="col-md-4 policy-item">
                <i class="bi bi-person-badge policy-icon" style="color:var(--trip-orange);"></i>
                <div>
                    <div class="policy-title">Name must match ID</div>
                    <div class="policy-desc">Enter full name exactly as it appears on your passport or government-issued ID.</div>
                </div>
            </div>
            <div class="col-md-4 policy-item">
                <i class="bi bi-shield-check policy-icon" style="color:#15803D;"></i>
                <div>
                    <div class="policy-title">Price guaranteed</div>
                    <div class="policy-desc">Your fare is locked the moment payment is confirmed — it will never increase.</div>
                </div>
            </div>
            <div class="col-md-4 policy-item">
                <i class="bi bi-clock-history policy-icon" style="color:var(--trip-blue);"></i>
                <div>
                    <div class="policy-title">Booking cutoff</div>
                    <div class="policy-desc">Flights within 2 hours cannot be booked online. Contact the airline directly.</div>
                </div>
            </div>
        </div>
    </div>

    <form method="POST">
    <div class="row g-4">

        <!-- LEFT -->
        <div class="col-lg-8">

            <!-- Outbound Flight -->
            <div class="section-label"><i class="bi bi-airplane" style="color:var(--trip-blue);"></i><?= $isRoundTrip ? 'Outbound Flight' : 'Selected Flight' ?></div>
            <div class="flight-summary mb-4">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div style="min-width:72px;">
                        <div class="fs-airline-code"><?= htmlspecialchars($flight['Airln_Code']) ?></div>
                        <div class="fs-airline-sub"><?= htmlspecialchars($flight['Airln_Name']) ?><br><?= htmlspecialchars($flight['Flght_No']) ?></div>
                    </div>
                    <div class="d-flex align-items-center flex-grow-1 gap-2">
                        <div class="text-center">
                            <div class="fs-time"><?= date('H:i', strtotime($flight['Flght_DepartDate'])) ?></div>
                            <div class="fs-iata"><?= $flight['Flght_Depart'] ?></div>
                            <div class="fs-city"><?= htmlspecialchars($fromCity) ?></div>
                        </div>
                        <div style="flex:1; min-width:60px; text-align:center;">
                            <div class="fs-dur"><?= flightDuration($flight['Flght_DepartDate'], $flight['Flght_ArriveDate']) ?></div>
                            <div style="height:2px; background:var(--trip-blue); border-radius:2px; position:relative; margin:0 8px;">
                                <i class="bi bi-airplane-fill fs-line-plane"></i>
                            </div>
                            <div class="fs-badge mt-2">Non-stop</div>
                        </div>
                        <div class="text-center">
                            <div class="fs-time"><?= date('H:i', strtotime($flight['Flght_ArriveDate'])) ?></div>
                            <div class="fs-iata"><?= $flight['Flght_Arrival'] ?></div>
                            <div class="fs-city"><?= htmlspecialchars($toCity) ?></div>
                        </div>
                    </div>
                    <div class="text-end" style="min-width:100px;">
                        <div style="font-size:11px; color:var(--trip-muted);"><?= date('D, d M Y', strtotime($flight['Flght_DepartDate'])) ?></div>
                        <span class="badge badge-trip-blue mt-1"><?= classLabel($class) ?></span>
                    </div>
                </div>
            </div>

            <!-- Return Flight -->
            <?php if ($isRoundTrip): ?>
            <div class="section-label"><i class="bi bi-arrow-return-left" style="color:var(--trip-orange);"></i>Return Flight</div>
            <div class="flight-summary ret mb-4">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div style="min-width:72px;">
                        <div class="fs-airline-code"><?= htmlspecialchars($returnFlight['Airln_Code']) ?></div>
                        <div class="fs-airline-sub"><?= htmlspecialchars($returnFlight['Airln_Name']) ?><br><?= htmlspecialchars($returnFlight['Flght_No']) ?></div>
                    </div>
                    <div class="d-flex align-items-center flex-grow-1 gap-2">
                        <div class="text-center">
                            <div class="fs-time"><?= date('H:i', strtotime($returnFlight['Flght_DepartDate'])) ?></div>
                            <div class="fs-iata"><?= $returnFlight['Flght_Depart'] ?></div>
                            <div class="fs-city"><?= htmlspecialchars(airportCity($returnFlight['Flght_Depart'])) ?></div>
                        </div>
                        <div style="flex:1; min-width:60px; text-align:center;">
                            <div class="fs-dur"><?= flightDuration($returnFlight['Flght_DepartDate'], $returnFlight['Flght_ArriveDate']) ?></div>
                            <div style="height:2px; background:var(--trip-orange); border-radius:2px; position:relative; margin:0 8px;">
                                <i class="bi bi-airplane-fill fs-line-plane" style="color:var(--trip-orange);"></i>
                            </div>
                            <div class="fs-badge mt-2">Return · Non-stop</div>
                        </div>
                        <div class="text-center">
                            <div class="fs-time"><?= date('H:i', strtotime($returnFlight['Flght_ArriveDate'])) ?></div>
                            <div class="fs-iata"><?= $returnFlight['Flght_Arrival'] ?></div>
                            <div class="fs-city"><?= htmlspecialchars(airportCity($returnFlight['Flght_Arrival'])) ?></div>
                        </div>
                    </div>
                    <div class="text-end" style="min-width:100px;">
                        <div style="font-size:11px; color:var(--trip-muted);"><?= date('D, d M Y', strtotime($returnFlight['Flght_DepartDate'])) ?></div>
                        <span class="badge badge-trip-orange mt-1"><?= classLabel($class) ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Guest contact email -->
            <?php if ($isGuest): ?>
            <div class="pax-card mb-3">
                <div class="pax-header">
                    <div class="pax-num" style="background:#EFF6FF; color:var(--trip-blue);"><i class="bi bi-envelope-fill" style="font-size:11px;"></i></div>
                    <div>
                        <div class="pax-title">Contact Email</div>
                        <div class="pax-sub">We'll send your booking confirmation here</div>
                    </div>
                </div>
                <div class="mb-0">
                    <label class="form-label" style="font-size:13px; font-weight:600;">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="guest_email" class="form-control"
                           placeholder="you@email.com" required
                           value="<?= htmlspecialchars($_POST['guest_email'] ?? '') ?>">
                </div>
                <div class="guest-info">
                    <i class="bi bi-info-circle-fill" style="flex-shrink:0;"></i>
                    <span>Sign in or create a free account to earn <strong>Trip Coins</strong> and access membership benefits on every booking.</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Passenger Details -->
            <div class="section-label mt-2"><i class="bi bi-people" style="color:var(--trip-blue);"></i>Passenger Details</div>
            <?php for ($i = 1; $i <= $passengers; $i++): ?>
            <div class="pax-card mb-3">
                <div class="pax-header">
                    <div class="pax-num"><?= $i ?></div>
                    <div>
                        <div class="pax-title">Passenger <?= $i ?><?= $passengers > 1 ? " of $passengers" : '' ?></div>
                        <div class="pax-sub">Details must match passport or government-issued ID</div>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" style="font-size:13px; font-weight:600;">Full Name <span class="text-muted fw-normal">(as in passport)</span></label>
                        <input type="text" name="pax_<?= $i ?>_name" class="form-control"
                               placeholder="e.g. Juan Dela Cruz" required
                               value="<?= htmlspecialchars($_POST["pax_{$i}_name"] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" style="font-size:13px; font-weight:600;">Gender</label>
                        <select name="pax_<?= $i ?>_gender" class="form-select" required>
                            <option value="" disabled <?= empty($_POST["pax_{$i}_gender"]) ? 'selected' : '' ?>>Select gender</option>
                            <option value="Male"        <?= ($_POST["pax_{$i}_gender"] ?? '') === 'Male'        ? 'selected' : '' ?>>Male</option>
                            <option value="Female"      <?= ($_POST["pax_{$i}_gender"] ?? '') === 'Female'      ? 'selected' : '' ?>>Female</option>
                            <option value="Unspecified" <?= ($_POST["pax_{$i}_gender"] ?? '') === 'Unspecified' ? 'selected' : '' ?>>Unspecified / X</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" style="font-size:13px; font-weight:600;">Date of Birth</label>
                        <input type="date" name="pax_<?= $i ?>_dob" class="form-control"
                               max="<?= date('Y-m-d', strtotime('-2 years')) ?>"
                               min="<?= date('Y-m-d', strtotime('-120 years')) ?>"
                               required
                               value="<?= htmlspecialchars($_POST["pax_{$i}_dob"] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" style="font-size:13px; font-weight:600;">Nationality</label>
                        <select name="pax_<?= $i ?>_nationality" class="form-select" required>
                            <option value="" disabled <?= empty($_POST["pax_{$i}_nationality"]) ? 'selected' : '' ?>>Select nationality</option>
                            <?php
                            $nationalities = [
                                'Afghan','Albanian','Algerian','American','Andorran','Angolan','Argentine','Armenian',
                                'Australian','Austrian','Azerbaijani','Bahamian','Bahraini','Bangladeshi','Barbadian',
                                'Belarusian','Belgian','Belizean','Beninese','Bhutanese','Bolivian','Bosnian','Botswanan',
                                'Brazilian','British','Bruneian','Bulgarian','Burkinabe','Burundian','Cambodian','Cameroonian',
                                'Canadian','Cape Verdean','Central African','Chadian','Chilean','Chinese','Colombian',
                                'Comorian','Congolese','Croatian','Cuban','Cypriot','Czech','Danish','Djiboutian',
                                'Dominican','Dutch','Ecuadorean','Egyptian','Emirian','Equatorial Guinean','Eritrean',
                                'Estonian','Ethiopian','Fijian','Finnish','French','Gabonese','Gambian','Georgian',
                                'German','Ghanaian','Greek','Grenadian','Guatemalan','Guinean','Guyanese','Haitian',
                                'Honduran','Hungarian','I-Kiribati','Indian','Indonesian','Iranian','Iraqi','Irish',
                                'Israeli','Italian','Ivorian','Jamaican','Japanese','Jordanian','Kazakhstani','Kenyan',
                                'Korean','Kuwaiti','Kyrgyz','Laotian','Latvian','Lebanese','Liberian','Libyan',
                                'Liechtensteiner','Lithuanian','Luxembourger','Macedonian','Malagasy','Malawian',
                                'Malaysian','Maldivian','Malian','Maltese','Marshallese','Mauritanian','Mauritian',
                                'Mexican','Micronesian','Moldovan','Monacan','Mongolian','Montenegrin','Moroccan',
                                'Mozambican','Namibian','Nauruan','Nepalese','New Zealander','Nicaraguan','Nigerian',
                                'Norwegian','Omani','Pakistani','Palauan','Palestinian','Panamanian','Papua New Guinean',
                                'Paraguayan','Peruvian','Filipino','Polish','Portuguese','Qatari','Romanian','Russian',
                                'Rwandan','Saint Lucian','Salvadoran','Samoan','Saudi','Senegalese','Serbian',
                                'Sierra Leonean','Singaporean','Slovak','Slovenian','Somali','South African','South Sudanese',
                                'Spanish','Sri Lankan','Sudanese','Surinamese','Swazi','Swedish','Swiss','Syrian',
                                'Taiwanese','Tajik','Tanzanian','Thai','Timorese','Togolese','Tongan','Trinidadian',
                                'Tunisian','Turkish','Turkmen','Tuvaluan','Ugandan','Ukrainian','Uruguayan','Uzbekistani',
                                'Vanuatuan','Venezuelan','Vietnamese','Yemeni','Zambian','Zimbabwean',
                            ];
                            foreach ($nationalities as $nat):
                                $sel = ($_POST["pax_{$i}_nationality"] ?? '') === $nat ? 'selected' : '';
                            ?>
                            <option value="<?= $nat ?>" <?= $sel ?>><?= $nat ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <?php endfor; ?>

            <!-- Payment -->
            <div class="section-label mt-2"><i class="bi bi-credit-card" style="color:var(--trip-blue);"></i>Payment Details</div>
            <div class="payment-card mb-4">
                <input type="hidden" name="pay_method" id="payMethodInput"
                       value="<?= htmlspecialchars($_POST['pay_method'] ?? 'Credit Card') ?>">

                <div class="pay-methods">
                    <button type="button" class="pay-btn <?= ($_POST['pay_method'] ?? 'Credit Card') === 'Credit Card' ? 'active' : '' ?>"
                            onclick="selectPayMethod('Credit Card', this)">
                        <i class="bi bi-credit-card pay-icon"></i> Credit Card
                    </button>
                    <button type="button" class="pay-btn <?= ($_POST['pay_method'] ?? '') === 'Debit Card' ? 'active' : '' ?>"
                            onclick="selectPayMethod('Debit Card', this)">
                        <i class="bi bi-credit-card-2-front pay-icon"></i> Debit Card
                    </button>
                    <button type="button" class="pay-btn <?= ($_POST['pay_method'] ?? '') === 'GCash' ? 'active' : '' ?>"
                            onclick="selectPayMethod('GCash', this)">
                        <i class="bi bi-phone pay-icon"></i> GCash
                    </button>
                    <button type="button" class="pay-btn <?= ($_POST['pay_method'] ?? '') === 'Maya' ? 'active' : '' ?>"
                            onclick="selectPayMethod('Maya', this)">
                        <i class="bi bi-phone-vibrate pay-icon"></i> Maya
                    </button>
                    <button type="button" class="pay-btn <?= ($_POST['pay_method'] ?? '') === 'Bank Transfer' ? 'active' : '' ?>"
                            onclick="selectPayMethod('Bank Transfer', this)">
                        <i class="bi bi-bank pay-icon"></i> Bank Transfer
                    </button>
                </div>

                <!-- Credit / Debit Card -->
                <div id="creds-card" class="cred-section <?= in_array($_POST['pay_method'] ?? 'Credit Card', ['Credit Card','Debit Card']) ? 'active' : '' ?>">
                    <div class="mb-3">
                        <label class="cred-label">Card Number</label>
                        <input type="text" id="cardNumber" name="card_number" class="cred-input mono"
                               placeholder="0000  0000  0000  0000" maxlength="22"
                               value="<?= htmlspecialchars($_POST['card_number'] ?? '') ?>"
                               autocomplete="cc-number" inputmode="numeric">
                    </div>
                    <div class="mb-3">
                        <label class="cred-label">Cardholder Name</label>
                        <input type="text" name="card_name" class="cred-input"
                               placeholder="Name exactly as on card"
                               value="<?= htmlspecialchars($_POST['card_name'] ?? '') ?>"
                               autocomplete="cc-name" style="text-transform:uppercase;">
                    </div>
                    <div class="card-row mb-2">
                        <div>
                            <label class="cred-label">Expiry Date</label>
                            <input type="text" id="cardExpiry" name="card_expiry" class="cred-input mono"
                                   placeholder="MM / YY" maxlength="7"
                                   value="<?= htmlspecialchars($_POST['card_expiry'] ?? '') ?>"
                                   autocomplete="cc-exp" inputmode="numeric">
                        </div>
                        <div>
                            <label class="cred-label">CVV</label>
                            <div class="pwd-wrap">
                                <input type="password" name="card_cvv" id="cardCvv" class="cred-input mono"
                                       placeholder="&bull;&bull;&bull;" maxlength="4"
                                       value="<?= htmlspecialchars($_POST['card_cvv'] ?? '') ?>"
                                       autocomplete="cc-csc" inputmode="numeric">
                                <button type="button" class="pwd-reveal-btn" id="cardCvvBtn" title="Hold to reveal">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="security-note">
                        <i class="bi bi-lock-fill" style="color:var(--trip-blue);"></i>
                        Your card details are encrypted and never stored. This is a demo — use test values.
                    </div>
                </div>

                <!-- GCash / Maya -->
                <div id="creds-ewallet" class="cred-section <?= in_array($_POST['pay_method'] ?? '', ['GCash','Maya']) ? 'active' : '' ?>">
                    <div class="mb-3">
                        <label class="cred-label" id="ewalletLabel">GCash Mobile Number</label>
                        <div style="position:relative;">
                            <span style="position:absolute; left:14px; top:50%; transform:translateY(-50%); font-size:14px; color:var(--trip-muted); font-weight:600; pointer-events:none;">+63</span>
                            <input type="tel" id="ewalletNumber" name="ewallet_number" class="cred-input"
                                   placeholder="9XX XXX XXXX" maxlength="12"
                                   value="<?= htmlspecialchars($_POST['ewallet_number'] ?? '') ?>"
                                   style="padding-left:46px;" inputmode="numeric">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="cred-label">Account Name / Display Name</label>
                        <input type="text" name="ewallet_name" class="cred-input"
                               placeholder="Name registered to this account"
                               value="<?= htmlspecialchars($_POST['ewallet_name'] ?? '') ?>">
                    </div>
                    <div class="security-note">
                        <i class="bi bi-shield-check" style="color:#15803D;"></i>
                        A payment confirmation will be sent to your registered mobile number.
                    </div>
                </div>

                <!-- Bank Transfer -->
                <div id="creds-bank" class="cred-section <?= ($_POST['pay_method'] ?? '') === 'Bank Transfer' ? 'active' : '' ?>">
                    <div class="mb-3">
                        <label class="cred-label">Bank</label>
                        <select name="bank_name" class="cred-input" style="cursor:pointer;">
                            <?php
                            $banks = ['BDO Unibank','BPI','Metrobank','UnionBank','Land Bank','RCBC','Eastwest Bank','Security Bank','PNB'];
                            $selBank = $_POST['bank_name'] ?? '';
                            foreach ($banks as $b):
                            ?>
                            <option value="<?= $b ?>" <?= $selBank === $b ? 'selected' : '' ?>><?= $b ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="cred-label">Account Number</label>
                        <input type="text" name="bank_account" class="cred-input mono"
                               placeholder="e.g. 1234-5678-9012"
                               value="<?= htmlspecialchars($_POST['bank_account'] ?? '') ?>"
                               inputmode="numeric">
                    </div>
                    <div class="mb-2">
                        <label class="cred-label">Account Name</label>
                        <input type="text" name="bank_account_name" class="cred-input"
                               placeholder="Full name on bank account"
                               value="<?= htmlspecialchars($_POST['bank_account_name'] ?? '') ?>">
                    </div>
                    <div class="security-note">
                        <i class="bi bi-info-circle" style="color:var(--trip-blue);"></i>
                        Transfer the exact amount shown in the price summary. Payment verified within 24 hours.
                    </div>
                </div>

                <!-- Promo Code -->
                <div class="promo-divider">
                    <label class="cred-label">Promo Code <span style="font-weight:400; text-transform:none; letter-spacing:0; color:var(--trip-muted);">(optional)</span></label>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <input type="text" name="promo_code" class="cred-input text-uppercase"
                               placeholder="e.g. TRIP10"
                               value="<?= htmlspecialchars($_POST['promo_code'] ?? '') ?>"
                               style="max-width:200px; letter-spacing:.06em;">
                        <div style="font-size:12px; color:var(--trip-muted);">
                            Try: <strong style="color:var(--trip-text);">TRIP10</strong> &middot;
                            <strong style="color:var(--trip-text);">SUMMER500</strong> &middot;
                            <strong style="color:var(--trip-text);">FLYPH20</strong>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- RIGHT — Price Summary -->
        <div class="col-lg-4">
            <div class="dark-summary">
                <div class="dark-summary-title">
                    <i class="bi bi-receipt" style="color:var(--trip-orange);"></i>
                    Price Summary
                </div>

                <?php if ($isRoundTrip): ?>
                <div class="sum-route"><?= $flight['Flght_Depart'] ?> → <?= $flight['Flght_Arrival'] ?> (Outbound)</div>
                <div class="sum-row mb-3">
                    <span class="lbl"><?= classLabel($class) ?> · per person</span>
                    <span class="val">₱<?= number_format($outPricePerPax, 2) ?></span>
                </div>
                <div class="sum-route"><?= $returnFlight['Flght_Depart'] ?> → <?= $returnFlight['Flght_Arrival'] ?> (Return)</div>
                <div class="sum-row mb-3">
                    <span class="lbl"><?= classLabel($class) ?> · per person</span>
                    <span class="val">₱<?= number_format($retPricePerPax, 2) ?></span>
                </div>
                <div class="sum-row" style="border-top:1px dashed rgba(255,255,255,.10); padding-top:10px; margin-bottom:6px;">
                    <span class="lbl">Per person (both legs)</span>
                    <span class="val">₱<?= number_format($pricePerPax, 2) ?></span>
                </div>
                <div class="sum-row mb-2">
                    <span class="lbl"><?= $passengers ?> passenger<?= $passengers > 1 ? 's' : '' ?></span>
                    <span class="val">×<?= $passengers ?></span>
                </div>
                <?php else: ?>
                <div class="sum-route"><?= htmlspecialchars($fromCity) ?> → <?= htmlspecialchars($toCity) ?></div>
                <div class="sum-row mb-2">
                    <span class="lbl"><?= classLabel($class) ?> · per person</span>
                    <span class="val">₱<?= number_format($pricePerPax, 2) ?></span>
                </div>
                <div class="sum-row mb-2">
                    <span class="lbl">Passengers</span>
                    <span class="val">×<?= $passengers ?></span>
                </div>
                <?php endif; ?>

                <div class="sum-total">
                    <span class="lbl">Total</span>
                    <span class="val">₱<?= number_format($totalPrice, 2) ?></span>
                </div>
                <p class="sum-note">Promo discount (if any) applied on confirm.</p>

                <div class="sum-info-bar">
                    <i class="bi bi-shield-check" style="color:#4ADE80;"></i>
                    Instant confirmation &middot; Free cancellation
                </div>

                <button type="submit" name="confirm_booking" class="btn-trip-orange w-100 py-3" style="font-size:15px; border-radius:var(--radius-md); font-weight:700;">
                    <?= $isRoundTrip ? 'Confirm Round Trip' : 'Confirm Booking' ?>
                </button>

                <p class="sum-terms">By confirming, you agree to our terms of service.</p>
            </div>
        </div>

    </div>
    </form>
</div>

<script>
// ── Payment method switching ────────────────────────────────────
function selectPayMethod(method, btn) {
    // Update hidden input
    document.getElementById('payMethodInput').value = method;

    // Update button styles
    document.querySelectorAll('.pay-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    // Hide all cred sections
    document.querySelectorAll('.cred-section').forEach(s => s.classList.remove('active'));

    // Show appropriate section
    if (method === 'Credit Card' || method === 'Debit Card') {
        document.getElementById('creds-card').classList.add('active');
    } else if (method === 'GCash' || method === 'Maya') {
        document.getElementById('creds-ewallet').classList.add('active');
        document.getElementById('ewalletLabel').textContent = method + ' Mobile Number';
    } else if (method === 'Bank Transfer') {
        document.getElementById('creds-bank').classList.add('active');
    }
}

// ── Card number formatter (XXXX  XXXX  XXXX  XXXX) ────────────
(function () {
    const cardInput = document.getElementById('cardNumber');
    if (!cardInput) return;

    cardInput.addEventListener('input', function (e) {
        let raw = this.value.replace(/\D/g, '').slice(0, 16);
        // Insert double-space every 4 digits for visual clarity
        this.value = raw.replace(/(.{4})/g, '$1  ').trimEnd();
    });

    cardInput.addEventListener('keydown', function (e) {
        // Allow backspace to feel natural even with spaces
        if (e.key === 'Backspace') {
            let pos = this.selectionStart;
            if (pos > 0 && this.value[pos - 1] === ' ') {
                e.preventDefault();
                this.value = this.value.slice(0, pos - 2) + this.value.slice(pos);
                this.selectionStart = this.selectionEnd = pos - 2;
            }
        }
    });
})();

// ── Expiry date formatter (MM / YY) ────────────────────────────
(function () {
    const expiryInput = document.getElementById('cardExpiry');
    if (!expiryInput) return;

    expiryInput.addEventListener('input', function () {
        let raw = this.value.replace(/\D/g, '').slice(0, 4);
        if (raw.length > 2) {
            this.value = raw.slice(0, 2) + ' / ' + raw.slice(2);
        } else {
            this.value = raw;
        }
    });
})();

// ── GCash / Maya phone formatter (9XX XXX XXXX) ───────────────
(function () {
    const phoneInput = document.getElementById('ewalletNumber');
    if (!phoneInput) return;

    phoneInput.addEventListener('input', function () {
        let raw = this.value.replace(/\D/g, '').slice(0, 10);
        if (raw.length > 6) {
            this.value = raw.slice(0, 3) + ' ' + raw.slice(3, 6) + ' ' + raw.slice(6);
        } else if (raw.length > 3) {
            this.value = raw.slice(0, 3) + ' ' + raw.slice(3);
        } else {
            this.value = raw;
        }
    });
})();

// CVV hold-to-reveal
(function() {
    var cvv = document.getElementById('cardCvv');
    var btn = document.getElementById('cardCvvBtn');
    if (cvv && btn) initHoldReveal(cvv, btn);
})();
</script>

<?php include '../layout/footer.php'; ?>
