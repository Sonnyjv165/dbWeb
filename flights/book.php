<?php
session_start();
require_once '../config/db.php';
require_once '../config/airports.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /dbweb/auth/login.php');
    exit();
}
if (($_SESSION['role'] ?? '') === 'admin') {
    header('Location: /dbweb/admin/dashboard.php');
    exit();
}

$flightId  = (int)($_GET['flight_id']  ?? 0);
$returnId  = (int)($_GET['return_id']  ?? 0);
$passengers = max(1, min(9, (int)($_GET['passengers'] ?? 1)));
$class     = in_array($_GET['class'] ?? '', ['economy','business','first']) ? $_GET['class'] : 'economy';

$multiplier = match($class) { 'business' => 2.5, 'first' => 4.0, default => 1.0 };
$seatClass  = strtoupper($class);

// Fetch outbound flight
$stmt = $conn->prepare("
    SELECT f.*, a.Airln_Name, a.Airln_Code
    FROM Flight f
    JOIN Airliner a ON f.Flght_AirlnID = a.Airln_ID
    WHERE f.Flght_ID = ? AND f.Flght_Status = 'SCHEDULED'
    LIMIT 1
");
$stmt->bind_param('i', $flightId);
$stmt->execute();
$flight = $stmt->get_result()->fetch_assoc();

if (!$flight) {
    header('Location: /dbweb/flights/search.php');
    exit();
}

// Fetch return flight (round trip)
$returnFlight = null;
if ($returnId > 0) {
    $stmt2 = $conn->prepare("
        SELECT f.*, a.Airln_Name, a.Airln_Code
        FROM Flight f
        JOIN Airliner a ON f.Flght_AirlnID = a.Airln_ID
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
    header('Location: /dbweb/flights/search.php');
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
            SELECT * FROM Promotion
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

    if (!$error) {
        $conn->begin_transaction();
        try {
            // Lock rows and check seat availability on both flights
            $chk = $conn->prepare("SELECT Flght_SeatAvail FROM Flight WHERE Flght_ID = ? FOR UPDATE");
            $chk->bind_param('i', $flightId);
            $chk->execute();
            $outSeats = $chk->get_result()->fetch_assoc()['Flght_SeatAvail'];

            if ($outSeats < $passengers) {
                throw new Exception('Not enough seats on the outbound flight.');
            }

            if ($isRoundTrip) {
                $chk2 = $conn->prepare("SELECT Flght_SeatAvail FROM Flight WHERE Flght_ID = ? FOR UPDATE");
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
                INSERT INTO Booking (Book_UserID, Book_Date, Book_Status, Book_Total, Book_Pay, Book_Confirm, Book_PromoID)
                VALUES (?, NOW(), 'CONFIRMED', ?, 'PAID', ?, ?)
            ");
            $b->bind_param('idsi', $_SESSION['user_id'], $finalTotal, $bookConfirm, $promoId);
            $b->execute();
            $bookId = $conn->insert_id;

            // Insert Bookingdetails: each passenger × each flight leg
            $bd = $conn->prepare("
                INSERT INTO Bookingdetails (Bokde_BookID, Bokde_FlghtID, Bokde_Passenger, Bokde_SeatClass, Bokde_Ticket)
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
                INSERT INTO Payment (Paymt_BookID, Paymt_Method, Paymt_Date, Paymt_Amt, Paymt_Status, Paymt_Transaction)
                VALUES (?, ?, NOW(), ?, 'SUCCESS', ?)
            ");
            $py->bind_param('isds', $bookId, $payMethod, $finalTotal, $txn);
            $py->execute();

            // Decrement seats on outbound (and return) flight
            $conn->query("UPDATE Flight SET Flght_SeatAvail = Flght_SeatAvail - $passengers WHERE Flght_ID = $flightId");
            if ($isRoundTrip) {
                $conn->query("UPDATE Flight SET Flght_SeatAvail = Flght_SeatAvail - $passengers WHERE Flght_ID = $returnId");
            }

            // Increment promo usage
            if ($promoId) {
                $conn->query("UPDATE Promotion SET Promo_Usage = Promo_Usage + 1 WHERE Promo_ID = $promoId");
            }

            // Award Trip Coins: 1 per ₱100 spent — not awarded when a promo discount was applied
            if (!$promoId) {
                $coinsEarned = (int)floor($finalTotal / 100);
                if ($coinsEarned > 0) {
                    $conn->query("UPDATE User SET User_Loyalty = User_Loyalty + $coinsEarned WHERE User_ID = {$_SESSION['user_id']}");
                }
            }

            $conn->commit();
            header("Location: /dbweb/flights/confirmation.php?ref=" . urlencode($bookConfirm) . "&coins=" . ($coinsEarned ?? 0));
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

<div class="container py-4">

    <nav style="font-size:13px;" class="mb-3">
        <a href="/dbweb/index.php" style="color:#0086FF;">Home</a> ›
        <a href="javascript:history.back()" style="color:#0086FF;">Search Results</a> ›
        <span class="text-muted">Booking</span>
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
                <i class="bi bi-clock-history" style="color:#0086FF; font-size:16px; flex-shrink:0; margin-top:1px;"></i>
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
                    <?= $isRoundTrip ? '✈ Outbound Flight' : '✈ Selected Flight' ?>
                </h6>
                <div class="d-flex align-items-center gap-4 flex-wrap">
                    <div>
                        <div style="font-size:20px; font-weight:800; color:#0086FF;"><?= htmlspecialchars($flight['Airln_Code']) ?></div>
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
                            <div style="height:2px; background:#0086FF; position:relative;">
                                <span style="position:absolute;right:-4px;top:-7px;font-size:14px;color:#0086FF;">✈</span>
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
                    ↩ Return Flight
                </h6>
                <div class="d-flex align-items-center gap-4 flex-wrap">
                    <div>
                        <div style="font-size:20px; font-weight:800; color:#0086FF;"><?= htmlspecialchars($returnFlight['Airln_Code']) ?></div>
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
                                <span style="position:absolute;right:-4px;top:-7px;font-size:14px;color:#FF7020;">✈</span>
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

            <!-- Passenger Details -->
            <?php for ($i = 1; $i <= $passengers; $i++): ?>
            <div class="trip-card p-4 mb-3">
                <h6 class="fw-bold mb-3">
                    <i class="bi bi-person-circle me-2" style="color:#0086FF;"></i>
                    Passenger <?= $i ?><?= $passengers > 1 ? " of $passengers" : '' ?>
                </h6>
                <div class="mb-0">
                    <label class="form-label fw-semibold" style="font-size:13px;">Full Name <span class="text-muted fw-normal">(as in passport)</span></label>
                    <input type="text" name="pax_<?= $i ?>_name" class="form-control"
                           placeholder="e.g. Juan Dela Cruz" required>
                </div>
            </div>
            <?php endfor; ?>

            <!-- Payment -->
            <div class="trip-card p-4 mb-4">
                <h6 class="fw-bold mb-3">Payment Method</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <select name="pay_method" class="form-select">
                            <option value="Credit Card">Credit Card</option>
                            <option value="Debit Card">Debit Card</option>
                            <option value="GCash">GCash</option>
                            <option value="Maya">Maya</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text" style="font-size:13px;">Promo Code</span>
                            <input type="text" name="promo_code" class="form-control text-uppercase"
                                   placeholder="Optional" style="font-size:13px;"
                                   value="<?= htmlspecialchars($_POST['promo_code'] ?? '') ?>">
                        </div>
                        <div class="form-text" style="font-size:12px;">Try: TRIP10, SUMMER500, FLYPH20</div>
                    </div>
                </div>
            </div>

        </div>

        <!-- RIGHT — Price Summary -->
        <div class="col-lg-4">
            <div class="trip-card p-4 sticky-top" style="top:80px;">
                <h6 class="fw-bold mb-3">Price Summary</h6>

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

                <div class="mb-3 p-3 rounded-3" style="background:#f0f8ff; font-size:13px; color:#0086FF;">
                    <i class="bi bi-shield-check me-2"></i>Instant confirmation · Free cancellation
                </div>

                <button type="submit" name="confirm_booking" class="btn-trip-orange w-100 py-3" style="font-size:16px; border-radius:8px;">
                    <?= $isRoundTrip ? 'Confirm Round Trip' : 'Confirm Booking' ?>
                </button>

                <p class="text-muted text-center mt-3 mb-0" style="font-size:12px;">
                    By confirming, you agree to our terms of service.
                </p>
            </div>
        </div>

    </div>
    </form>
</div>

<?php include '../layout/footer.php'; ?>
