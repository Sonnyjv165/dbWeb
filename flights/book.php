<?php
session_start();
require_once '../config/db.php';
require_once '../config/airports.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /dbweb/auth/login.php');
    exit();
}

$flightId  = (int)($_GET['flight_id']  ?? 0);
$passengers = max(1, min(9, (int)($_GET['passengers'] ?? 1)));
$class     = in_array($_GET['class'] ?? '', ['economy','business','first']) ? $_GET['class'] : 'economy';

// Fare multiplier: economy ×1, business ×2.5, first ×4
$multiplier = match($class) { 'business' => 2.5, 'first' => 4.0, default => 1.0 };
$seatClass  = strtoupper($class); // for ENUM: ECONOMY / BUSINESS / FIRST

// Fetch flight
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

$pricePerPax = (float)$flight['Flght_Fare'] * $multiplier;
$totalPrice  = $pricePerPax * $passengers;
$fromCity    = airportCity($flight['Flght_Depart']);
$toCity      = airportCity($flight['Flght_Arrival']);
$error       = '';
$promoMsg    = '';
$discount    = 0.0;
$promoId     = null;

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
        // Check seats still available
        $chk = $conn->prepare("SELECT Flght_SeatAvail FROM Flight WHERE Flght_ID = ? FOR UPDATE");
        $chk->bind_param('i', $flightId);
        $chk->execute();
        $seats = $chk->get_result()->fetch_assoc()['Flght_SeatAvail'];

        if ($seats < $passengers) {
            $error = 'Not enough seats available. Please search again.';
        } else {
            $bookConfirm = 'TR' . strtoupper(bin2hex(random_bytes(4)));
            $conn->begin_transaction();
            try {
                // Insert Booking
                $b = $conn->prepare("
                    INSERT INTO Booking (Book_UserID, Book_Date, Book_Status, Book_Total, Book_Pay, Book_Confirm, Book_PromoID)
                    VALUES (?, NOW(), 'CONFIRMED', ?, 'PAID', ?, ?)
                ");
                $b->bind_param('idsi', $_SESSION['user_id'], $finalTotal, $bookConfirm, $promoId);
                $b->execute();
                $bookId = $conn->insert_id;

                // Insert one Bookingdetails row per passenger
                $bd = $conn->prepare("
                    INSERT INTO Bookingdetails (Bokde_BookID, Bokde_FlghtID, Bokde_Passenger, Bokde_SeatClass, Bokde_Ticket)
                    VALUES (?, ?, ?, ?, ?)
                ");
                for ($i = 1; $i <= $passengers; $i++) {
                    $paxName = trim($_POST["pax_{$i}_name"]);
                    $bd->bind_param('iissd', $bookId, $flightId, $paxName, $seatClass, $pricePerPax);
                    $bd->execute();
                }

                // Insert Payment
                $txn = 'TXN' . strtoupper(bin2hex(random_bytes(5)));
                $py  = $conn->prepare("
                    INSERT INTO Payment (Paymt_BookID, Paymt_Method, Paymt_Date, Paymt_Amt, Paymt_Status, Paymt_Transaction)
                    VALUES (?, ?, NOW(), ?, 'SUCCESS', ?)
                ");
                $py->bind_param('isds', $bookId, $payMethod, $finalTotal, $txn);
                $py->execute();

                // Decrement available seats
                $conn->query("UPDATE Flight SET Flght_SeatAvail = Flght_SeatAvail - $passengers WHERE Flght_ID = $flightId");

                // Increment promo usage
                if ($promoId) {
                    $conn->query("UPDATE Promotion SET Promo_Usage = Promo_Usage + 1 WHERE Promo_ID = $promoId");
                }

                $conn->commit();
                header("Location: /dbweb/flights/confirmation.php?ref=" . urlencode($bookConfirm));
                exit();

            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Booking failed. Please try again.';
            }
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

    <form method="POST">
    <div class="row g-4">

        <!-- LEFT — Passenger + Contact -->
        <div class="col-lg-8">

            <!-- Flight Summary -->
            <div class="trip-card p-4 mb-4">
                <h6 class="fw-bold text-muted text-uppercase mb-3" style="font-size:12px; letter-spacing:.6px;">Selected Flight</h6>
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

                <div class="d-flex justify-content-between mb-2" style="font-size:14px;">
                    <span class="text-muted"><?= htmlspecialchars($fromCity) ?> → <?= htmlspecialchars($toCity) ?></span>
                    <span><?= $passengers ?>×</span>
                </div>
                <div class="d-flex justify-content-between mb-2" style="font-size:14px;">
                    <span class="text-muted"><?= classLabel($class) ?> (per person)</span>
                    <span>₱<?= number_format($pricePerPax, 2) ?></span>
                </div>
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
                    Confirm Booking
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
