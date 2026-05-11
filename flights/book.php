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
.dark-summary {
    background: #111111;
    border-radius: 14px;
    padding: 24px;
    position: sticky;
    top: 80px;
    color: #fff;
    border: 1px solid rgba(255,255,255,0.06);
}
.dark-summary h6   { color: #fff; font-family: var(--font-serif); font-size: 18px; font-weight: 600; letter-spacing: -0.02em; margin-bottom: 20px; }
.dark-summary .text-muted { color: rgba(255,255,255,0.50) !important; }
.dark-summary hr   { border-color: rgba(255,255,255,0.10); }
.dark-summary .price-text { color: var(--trip-orange); }
.dark-summary .summary-row { display:flex; justify-content:space-between; align-items:baseline; font-size:13px; margin-bottom:8px; }
.dark-summary .summary-row.total { font-size:15px; font-weight:700; border-top:1px solid rgba(255,255,255,0.10); padding-top:14px; margin-top:4px; }
.dark-summary .summary-row span:first-child { color: rgba(255,255,255,0.55); }
.dark-summary .summary-row span:last-child  { color: #fff; font-weight:600; }
.dark-summary .summary-row.total span       { color: #fff; }
.dark-summary .info-bar {
    background: rgba(255,255,255,0.07);
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 13px;
    color: rgba(255,255,255,0.72);
    margin-bottom: 16px;
}
.book-crumb a { color: var(--trip-blue); font-size: 13px; text-decoration: none; }
.book-crumb a:hover { text-decoration: underline; }
.book-crumb span { color: var(--trip-muted); font-size: 13px; }

/* ── Payment method buttons ── */
.pay-methods { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 24px; }
.pay-btn {
    display: flex; align-items: center; gap: 9px;
    padding: 10px 18px; border-radius: 10px; cursor: pointer;
    border: 1.5px solid var(--trip-border);
    background: #fff; color: var(--trip-text);
    font-size: 13px; font-weight: 600; font-family: var(--font-sans);
    transition: all 0.18s cubic-bezier(0.32,0.72,0,1);
}
.pay-btn:hover { border-color: rgba(0,0,0,0.22); background: var(--trip-bg); }
.pay-btn.active {
    border-color: var(--trip-text);
    background: var(--trip-text);
    color: #fff;
    box-shadow: 0 2px 12px rgba(0,0,0,0.15);
}
.pay-btn .pay-icon { font-size: 16px; line-height: 1; }

/* ── Credential fields ── */
.cred-section { display: none; }
.cred-section.active { display: block; }
.cred-label {
    font-size: 11px; font-weight: 700; letter-spacing: 0.08em;
    text-transform: uppercase; color: var(--trip-muted); margin-bottom: 5px; display: block;
}
.cred-input {
    font-family: var(--font-sans); font-size: 14px;
    border: 1.5px solid rgba(0,0,0,0.12); border-radius: 8px;
    padding: 10px 14px; width: 100%; background: #fff; color: var(--trip-text);
    transition: border-color 0.15s;
    outline: none;
}
.cred-input:focus { border-color: var(--trip-blue); box-shadow: 0 0 0 3px rgba(0,119,238,0.10); }
.cred-input.mono { font-family: 'SF Mono', 'Menlo', monospace; letter-spacing: 0.05em; }
.card-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.security-note {
    display: flex; align-items: center; gap: 8px; margin-top: 16px;
    background: var(--trip-bg); border-radius: 8px; padding: 10px 14px;
    font-size: 12px; color: var(--trip-muted); border: 1px solid var(--trip-border);
}
</style>

<div class="container py-4">

    <nav class="book-crumb mb-3">
        <a href="/index.php">Home</a> ›
        <a href="javascript:history.back()">Search Results</a> ›
        <span>Booking</span>
    </nav>

    <?php if ($error): ?>
        <div class="alert alert-danger rounded-3 mb-3"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($isRoundTrip): ?>
    <div class="alert alert-info rounded-3 mb-3 d-flex align-items-center gap-2" style="font-size:13px; background:#eff8ff; border-color:#b3d9ff; color:#004a8f;">
        <i class="bi bi-arrow-left-right" style="font-size:18px;"></i>
        <strong>Round Trip Booking</strong> — You're booking both outbound and return flights together.
    </div>
    <?php endif; ?>

    <!-- Policy notices -->
    <div class="trip-card p-3 mb-4" style="border-left:4px solid #FF7020;">
        <div class="row g-2" style="font-size:13px; color:#555;">
            <div class="col-md-4 d-flex gap-2 align-items-start">
                <i class="bi bi-person-badge" style="color:#FF7020; font-size:16px; flex-shrink:0; margin-top:1px;"></i>
                <div><strong style="color:#1A1A1A;">Name must match ID</strong><br>Enter full name exactly as it appears on your passport or government-issued ID.</div>
            </div>
            <div class="col-md-4 d-flex gap-2 align-items-start">
                <i class="bi bi-shield-check" style="color:#1a9e5c; font-size:16px; flex-shrink:0; margin-top:1px;"></i>
                <div><strong style="color:#1A1A1A;">Price guaranteed</strong><br>Your fare is locked the moment payment is confirmed — it will never increase after that.</div>
            </div>
            <div class="col-md-4 d-flex gap-2 align-items-start">
                <i class="bi bi-clock-history" style="color:#0077EE; font-size:16px; flex-shrink:0; margin-top:1px;"></i>
                <div><strong style="color:#1A1A1A;">Booking cutoff</strong><br>Flights departing within 2 hours cannot be booked online. Contact the airline directly.</div>
            </div>
        </div>
    </div>

    <form method="POST">
    <div class="row g-4">

        <!-- LEFT — Flight summaries + Passenger + Payment -->
        <div class="col-lg-8">

            <!-- Outbound Flight Summary -->
            <div class="trip-card p-4 mb-3">
                <h6 class="fw-bold text-muted text-uppercase mb-3" style="font-size:12px; letter-spacing:.6px;">
                    <i class="bi bi-airplane me-1"></i><?= $isRoundTrip ? 'Outbound Flight' : 'Selected Flight' ?>
                </h6>
                <div class="d-flex align-items-center gap-4 flex-wrap">
                    <div>
                        <div style="font-size:20px; font-weight:800; color:#0077EE;"><?= htmlspecialchars($flight['Airln_Code']) ?></div>
                        <div style="font-size:12px; color:#6B6B6B;"><?= htmlspecialchars($flight['Airln_Name']) ?> · <?= htmlspecialchars($flight['Flght_No']) ?></div>
                    </div>
                    <div class="d-flex align-items-center gap-3 flex-grow-1">
                        <div class="text-center">
                            <div class="fw-bold" style="font-size:22px;"><?= date('H:i', strtotime($flight['Flght_DepartDate'])) ?></div>
                            <div style="font-size:12px; color:#aaa;"><?= $flight['Flght_Depart'] ?></div>
                            <div style="font-size:11px; color:#aaa;"><?= htmlspecialchars($fromCity) ?></div>
                        </div>
                        <div class="flex-grow-1 text-center">
                            <div style="font-size:12px; color:#aaa; margin-bottom:4px;"><?= flightDuration($flight['Flght_DepartDate'], $flight['Flght_ArriveDate']) ?></div>
                            <div style="height:2px; background:#0077EE; position:relative;">
                                <span style="position:absolute;right:-4px;top:-9px;font-size:14px;color:#0077EE;"><i class="bi bi-airplane-fill"></i></span>
                            </div>
                            <div class="mt-1"><span class="badge badge-trip-blue" style="font-size:10px;">Non-stop</span></div>
                        </div>
                        <div class="text-center">
                            <div class="fw-bold" style="font-size:22px;"><?= date('H:i', strtotime($flight['Flght_ArriveDate'])) ?></div>
                            <div style="font-size:12px; color:#aaa;"><?= $flight['Flght_Arrival'] ?></div>
                            <div style="font-size:11px; color:#aaa;"><?= htmlspecialchars($toCity) ?></div>
                        </div>
                    </div>
                    <div class="text-end">
                        <div style="font-size:11px; color:#aaa;"><?= date('D, d M Y', strtotime($flight['Flght_DepartDate'])) ?></div>
                        <span class="badge badge-trip-blue px-2 py-1"><?= classLabel($class) ?></span>
                    </div>
                </div>
            </div>

            <!-- Return Flight Summary (round trip only) -->
            <?php if ($isRoundTrip): ?>
            <div class="trip-card p-4 mb-3" style="border-left:4px solid #FF7020;">
                <h6 class="fw-bold text-muted text-uppercase mb-3" style="font-size:12px; letter-spacing:.6px;">
                    <i class="bi bi-arrow-return-left me-1"></i>Return Flight
                </h6>
                <div class="d-flex align-items-center gap-4 flex-wrap">
                    <div>
                        <div style="font-size:20px; font-weight:800; color:#0077EE;"><?= htmlspecialchars($returnFlight['Airln_Code']) ?></div>
                        <div style="font-size:12px; color:#6B6B6B;"><?= htmlspecialchars($returnFlight['Airln_Name']) ?> · <?= htmlspecialchars($returnFlight['Flght_No']) ?></div>
                    </div>
                    <div class="d-flex align-items-center gap-3 flex-grow-1">
                        <div class="text-center">
                            <div class="fw-bold" style="font-size:22px;"><?= date('H:i', strtotime($returnFlight['Flght_DepartDate'])) ?></div>
                            <div style="font-size:12px; color:#aaa;"><?= $returnFlight['Flght_Depart'] ?></div>
                            <div style="font-size:11px; color:#aaa;"><?= htmlspecialchars(airportCity($returnFlight['Flght_Depart'])) ?></div>
                        </div>
                        <div class="flex-grow-1 text-center">
                            <div style="font-size:12px; color:#aaa; margin-bottom:4px;"><?= flightDuration($returnFlight['Flght_DepartDate'], $returnFlight['Flght_ArriveDate']) ?></div>
                            <div style="height:2px; background:#FF7020; position:relative;">
                                <span style="position:absolute;right:-4px;top:-9px;font-size:14px;color:#FF7020;"><i class="bi bi-airplane-fill"></i></span>
                            </div>
                            <div class="mt-1"><span class="badge badge-trip-orange" style="font-size:10px;">Return · Non-stop</span></div>
                        </div>
                        <div class="text-center">
                            <div class="fw-bold" style="font-size:22px;"><?= date('H:i', strtotime($returnFlight['Flght_ArriveDate'])) ?></div>
                            <div style="font-size:12px; color:#aaa;"><?= $returnFlight['Flght_Arrival'] ?></div>
                            <div style="font-size:11px; color:#aaa;"><?= htmlspecialchars(airportCity($returnFlight['Flght_Arrival'])) ?></div>
                        </div>
                    </div>
                    <div class="text-end">
                        <div style="font-size:11px; color:#aaa;"><?= date('D, d M Y', strtotime($returnFlight['Flght_DepartDate'])) ?></div>
                        <span class="badge badge-trip-orange px-2 py-1"><?= classLabel($class) ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Guest contact email -->
            <?php if ($isGuest): ?>
            <div class="trip-card p-4 mb-3">
                <h6 class="fw-bold mb-1">
                    <i class="bi bi-envelope me-2" style="color:#0077EE;"></i>Contact Email
                </h6>
                <p class="text-muted mb-3" style="font-size:13px;">We'll send your booking confirmation here. Use this email to retrieve your booking later.</p>
                <div class="mb-0">
                    <label class="form-label fw-semibold" style="font-size:13px;">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="guest_email" class="form-control"
                           placeholder="you@email.com" required
                           value="<?= htmlspecialchars($_POST['guest_email'] ?? '') ?>">
                </div>
                <div style="margin-top:14px; background:#f0f8ff; border-radius:8px; padding:10px 14px; font-size:12px; color:#0077EE; display:flex; align-items:center; gap:8px;">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>Sign in or create a free account to earn <strong>Trip Coins</strong> and access membership benefits on every booking.</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Passenger Details -->
            <?php for ($i = 1; $i <= $passengers; $i++): ?>
            <div class="trip-card p-4 mb-3">
                <h6 class="fw-bold mb-4">
                    <i class="bi bi-person-circle me-2" style="color:#0077EE;"></i>
                    Passenger <?= $i ?><?= $passengers > 1 ? " of $passengers" : '' ?>
                    <span class="text-muted fw-normal" style="font-size:12px; margin-left:6px;">Details must match passport</span>
                </h6>

                <div class="row g-3">
                    <!-- Full Name -->
                    <div class="col-12">
                        <label class="form-label fw-semibold" style="font-size:13px;">Full Name <span class="text-muted fw-normal">(as in passport)</span></label>
                        <input type="text" name="pax_<?= $i ?>_name" class="form-control"
                               placeholder="e.g. Juan Dela Cruz" required
                               value="<?= htmlspecialchars($_POST["pax_{$i}_name"] ?? '') ?>">
                    </div>

                    <!-- Gender -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" style="font-size:13px;">Gender <span class="text-muted fw-normal">(on passport)</span></label>
                        <select name="pax_<?= $i ?>_gender" class="form-select" required>
                            <option value="" disabled <?= empty($_POST["pax_{$i}_gender"]) ? 'selected' : '' ?>>Select gender</option>
                            <option value="Male"   <?= ($_POST["pax_{$i}_gender"] ?? '') === 'Male'   ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= ($_POST["pax_{$i}_gender"] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                            <option value="Unspecified" <?= ($_POST["pax_{$i}_gender"] ?? '') === 'Unspecified' ? 'selected' : '' ?>>Unspecified / X</option>
                        </select>
                    </div>

                    <!-- Date of Birth -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" style="font-size:13px;">Date of Birth</label>
                        <input type="date" name="pax_<?= $i ?>_dob" class="form-control"
                               max="<?= date('Y-m-d', strtotime('-2 years')) ?>"
                               min="<?= date('Y-m-d', strtotime('-120 years')) ?>"
                               required
                               value="<?= htmlspecialchars($_POST["pax_{$i}_dob"] ?? '') ?>">
                    </div>

                    <!-- Nationality -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" style="font-size:13px;">Nationality</label>
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
            <div class="trip-card p-4 mb-4">
                <h6 style="font-family:var(--font-serif); font-weight:600; font-size:16px; letter-spacing:-0.02em; margin-bottom:18px;">
                    Payment Details
                </h6>

                <!-- Hidden field sent with form POST -->
                <input type="hidden" name="pay_method" id="payMethodInput"
                       value="<?= htmlspecialchars($_POST['pay_method'] ?? 'Credit Card') ?>">

                <!-- Method selector -->
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

                <!-- ── Credit / Debit Card fields ── -->
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

                <!-- ── GCash / Maya fields ── -->
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
                        <i class="bi bi-shield-check" style="color:#1a9e5c;"></i>
                        A payment confirmation will be sent to your registered mobile number.
                    </div>
                </div>

                <!-- ── Bank Transfer fields ── -->
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

                <!-- Promo Code (always visible) -->
                <div style="margin-top:20px; padding-top:20px; border-top:1px solid var(--trip-border);">
                    <label class="cred-label">Promo Code <span style="font-weight:400; text-transform:none; letter-spacing:0; color:var(--trip-muted);">(optional)</span></label>
                    <div class="d-flex gap-2">
                        <input type="text" name="promo_code" class="cred-input text-uppercase"
                               placeholder="e.g. TRIP10"
                               value="<?= htmlspecialchars($_POST['promo_code'] ?? '') ?>"
                               style="max-width:220px; letter-spacing:0.05em;">
                        <div style="font-size:12px; color:var(--trip-muted); line-height:1.4; display:flex; align-items:center;">
                            Try: <strong style="color:var(--trip-text); margin-left:4px;">TRIP10 &nbsp;&middot;&nbsp; SUMMER500 &nbsp;&middot;&nbsp; FLYPH20</strong>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- RIGHT — Price Summary -->
        <div class="col-lg-4">
            <div class="dark-summary">
                <h6>Price Summary</h6>

                <?php if ($isRoundTrip): ?>
                <div class="d-flex justify-content-between mb-1" style="font-size:13px;">
                    <span class="text-muted">Outbound (<?= $flight['Flght_Depart'] ?> → <?= $flight['Flght_Arrival'] ?>)</span>
                </div>
                <div class="d-flex justify-content-between mb-2" style="font-size:14px;">
                    <span class="text-muted"><?= classLabel($class) ?> · per person</span>
                    <span>₱<?= number_format($outPricePerPax, 2) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-1" style="font-size:13px;">
                    <span class="text-muted">Return (<?= $returnFlight['Flght_Depart'] ?> → <?= $returnFlight['Flght_Arrival'] ?>)</span>
                </div>
                <div class="d-flex justify-content-between mb-2" style="font-size:14px;">
                    <span class="text-muted"><?= classLabel($class) ?> · per person</span>
                    <span>₱<?= number_format($retPricePerPax, 2) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2" style="font-size:13px; border-top:1px dashed #ddd; padding-top:8px;">
                    <span class="text-muted">Per person (both legs)</span>
                    <span class="fw-semibold">₱<?= number_format($pricePerPax, 2) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2" style="font-size:14px;">
                    <span class="text-muted"><?= $passengers ?> passenger<?= $passengers > 1 ? 's' : '' ?></span>
                    <span>×<?= $passengers ?></span>
                </div>
                <?php else: ?>
                <div class="d-flex justify-content-between mb-2" style="font-size:14px;">
                    <span class="text-muted"><?= htmlspecialchars($fromCity) ?> → <?= htmlspecialchars($toCity) ?></span>
                    <span><?= $passengers ?>×</span>
                </div>
                <div class="d-flex justify-content-between mb-2" style="font-size:14px;">
                    <span class="text-muted"><?= classLabel($class) ?> (per person)</span>
                    <span>₱<?= number_format($pricePerPax, 2) ?></span>
                </div>
                <?php endif; ?>

                <hr>
                <div class="d-flex justify-content-between fw-bold mb-4">
                    <span>Total</span>
                    <span class="price-text" style="font-size:20px;">₱<?= number_format($totalPrice, 2) ?></span>
                </div>
                <p class="text-muted" style="font-size:12px; margin-top:-12px;">
                    Promo discount (if any) applied on confirm.
                </p>

                <div class="info-bar">
                    <i class="bi bi-shield-check me-2"></i>Instant confirmation &middot; Free cancellation
                </div>

                <button type="submit" name="confirm_booking" class="btn-trip-orange w-100 py-3" style="font-size:15px; border-radius:8px; font-weight:700;">
                    <?= $isRoundTrip ? 'Confirm Round Trip' : 'Confirm Booking' ?>
                </button>

                <p style="color:rgba(255,255,255,0.38); text-align:center; margin-top:12px; margin-bottom:0; font-size:12px;">
                    By confirming, you agree to our terms of service.
                </p>
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
