<?php
session_start();
require_once '../config/db.php';
require_once '../config/airports.php';

// ── Gather search params ──────────────────────────────────────
$fromCode   = strtoupper(trim($_GET['from']       ?? ''));
$toCode     = strtoupper(trim($_GET['to']         ?? ''));
$date       = $_GET['date']                        ?? '';
$returnDate = $_GET['return_date']                 ?? '';
$passengers = max(1, (int)($_GET['passengers']     ?? 1));
$class      = in_array($_GET['class'] ?? '', ['economy','business','first']) ? $_GET['class'] : 'economy';
$tripType   = ($_GET['trip_type'] ?? 'oneway') === 'roundtrip' ? 'roundtrip' : 'oneway';

// Fare multiplier per class (base Flght_Fare is economy)
$multiplier = match($class) { 'business' => 2.5, 'first' => 4.0, default => 1.0 };

$flights       = [];
$returnFlights = [];
$error         = '';
$fromCity      = airportCity($fromCode);
$toCity        = airportCity($toCode);

if ($fromCode && $toCode && $date) {
    if ($fromCode === $toCode) {
        $error = 'Departure and destination cannot be the same.';
    } else {
        $sql = "
            SELECT f.*, a.Airln_Name, a.Airln_Code
            FROM Flight f
            JOIN Airliner a ON f.Flght_AirlnID = a.Airln_ID
            WHERE f.Flght_Depart   = ?
              AND f.Flght_Arrival  = ?
              AND DATE(f.Flght_DepartDate) = ?
              AND f.Flght_Status   = 'SCHEDULED'
              AND f.Flght_SeatAvail >= ?
            ORDER BY f.Flght_Fare ASC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssi', $fromCode, $toCode, $date, $passengers);
        $stmt->execute();
        $flights = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if ($tripType === 'roundtrip' && $returnDate) {
            $stmt2 = $conn->prepare($sql);
            $stmt2->bind_param('sssi', $toCode, $fromCode, $returnDate, $passengers);
            $stmt2->execute();
            $returnFlights = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    }
}

function flightDuration($dep, $arr) {
    $d1   = new DateTime($dep);
    $d2   = new DateTime($arr);
    $diff = $d1->diff($d2);
    return ($diff->days > 0 ? $diff->days . 'd ' : '') . $diff->h . 'h ' . ($diff->i > 0 ? $diff->i . 'm' : '');
}

function classLabel($c) {
    return match($c) { 'business' => 'Business', 'first' => 'First Class', default => 'Economy' };
}

$title = 'Flight Results';
include '../layout/layout.php';
?>

<!-- ====== COMPACT SEARCH BAR ====== -->
<div style="background:linear-gradient(135deg,#003580,#0086FF); padding:20px 0;">
    <div class="container">
        <form method="GET" action="/dbweb/flights/search.php" class="row g-2 align-items-end">
            <input type="hidden" name="trip_type" value="<?= $tripType ?>">

            <div class="col-md-2">
                <label class="form-label text-white fw-semibold" style="font-size:11px; text-transform:uppercase; letter-spacing:.5px;">From</label>
                <select name="from" class="form-select form-select-sm" required>
                    <?php foreach ($AIRPORTS as $code => $ap): ?>
                        <option value="<?= $code ?>" <?= $code === $fromCode ? 'selected' : '' ?>>
                            <?= $ap['city'] ?> (<?= $code ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label text-white fw-semibold" style="font-size:11px; text-transform:uppercase; letter-spacing:.5px;">To</label>
                <select name="to" class="form-select form-select-sm" required>
                    <?php foreach ($AIRPORTS as $code => $ap): ?>
                        <option value="<?= $code ?>" <?= $code === $toCode ? 'selected' : '' ?>>
                            <?= $ap['city'] ?> (<?= $code ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label text-white fw-semibold" style="font-size:11px; text-transform:uppercase; letter-spacing:.5px;">Departure</label>
                <input type="date" name="date" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($date) ?>" required min="<?= date('Y-m-d') ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label text-white fw-semibold" style="font-size:11px; text-transform:uppercase; letter-spacing:.5px;">Passengers</label>
                <select name="passengers" class="form-select form-select-sm">
                    <?php for ($i = 1; $i <= 9; $i++): ?>
                        <option value="<?= $i ?>" <?= $i == $passengers ? 'selected' : '' ?>><?= $i ?> Pax</option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label text-white fw-semibold" style="font-size:11px; text-transform:uppercase; letter-spacing:.5px;">Class</label>
                <select name="class" class="form-select form-select-sm">
                    <?php foreach (['economy'=>'Economy','business'=>'Business','first'=>'First'] as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= $val === $class ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-trip-orange w-100 py-2" style="font-size:14px;">
                    <i class="bi bi-search me-1"></i>Search
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ====== RESULTS ====== -->
<div class="container py-4">

    <?php if ($error): ?>
        <div class="alert alert-warning rounded-3"><?= htmlspecialchars($error) ?></div>

    <?php elseif ($fromCode && $toCode && $date): ?>

        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h5 class="fw-bold mb-0"><?= htmlspecialchars($fromCity) ?> → <?= htmlspecialchars($toCity) ?></h5>
                <p class="text-muted mb-0" style="font-size:13px;">
                    <?= date('D, d M Y', strtotime($date)) ?> &middot;
                    <?= $passengers ?> passenger<?= $passengers > 1 ? 's' : '' ?> &middot;
                    <?= classLabel($class) ?>
                    <?php if ($tripType === 'roundtrip'): ?>
                        &middot; <span class="badge badge-trip-blue px-2 py-1">Round Trip</span>
                    <?php endif; ?>
                </p>
            </div>
            <span class="text-muted" style="font-size:13px;"><?= count($flights) ?> flight<?= count($flights) != 1 ? 's' : '' ?> found</span>
        </div>

        <?php if (empty($flights)): ?>
            <div class="trip-card p-5 text-center text-muted">
                <div style="font-size:48px; opacity:.3;">✈</div>
                <h6 class="mt-3">No flights found for this route and date.</h6>
                <p style="font-size:14px;">Try a different date or adjust your search.</p>
            </div>
        <?php else: ?>
            <?php foreach ($flights as $f):
                $duration    = flightDuration($f['Flght_DepartDate'], $f['Flght_ArriveDate']);
                $pricePerPax = (float)$f['Flght_Fare'] * $multiplier;
                $totalPrice  = $pricePerPax * $passengers;
                $bookUrl = '/dbweb/flights/book.php?' . http_build_query([
                    'flight_id'  => $f['Flght_ID'],
                    'passengers' => $passengers,
                    'class'      => $class,
                ]);
            ?>
            <div class="trip-card mb-3 p-0 overflow-hidden">
                <div class="row g-0 align-items-center">

                    <div class="col-md-2 text-center py-3 px-3" style="border-right:1px solid #f0f0f0;">
                        <div style="font-size:22px; color:#0086FF; font-weight:800;"><?= htmlspecialchars($f['Airln_Code']) ?></div>
                        <div style="font-size:12px; color:#6B6B6B; font-weight:500;"><?= htmlspecialchars($f['Airln_Name']) ?></div>
                        <div style="font-size:11px; color:#aaa;" class="mt-1"><?= htmlspecialchars($f['Flght_No']) ?></div>
                    </div>

                    <div class="col-md-6 py-3 px-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="text-center">
                                <div class="fw-bold" style="font-size:24px; letter-spacing:-0.5px;">
                                    <?= date('H:i', strtotime($f['Flght_DepartDate'])) ?>
                                </div>
                                <div style="font-size:12px; color:#6B6B6B;"><?= htmlspecialchars($f['Flght_Depart']) ?></div>
                            </div>
                            <div class="flex-grow-1 text-center">
                                <div style="font-size:12px; color:#6B6B6B; margin-bottom:4px;"><?= $duration ?></div>
                                <div style="height:2px; background:#0086FF; position:relative;">
                                    <span style="position:absolute;right:-4px;top:-7px;font-size:16px;color:#0086FF;">✈</span>
                                </div>
                                <div class="mt-1"><span class="badge badge-trip-blue px-2" style="font-size:11px;">Non-stop</span></div>
                            </div>
                            <div class="text-center">
                                <div class="fw-bold" style="font-size:24px; letter-spacing:-0.5px;">
                                    <?= date('H:i', strtotime($f['Flght_ArriveDate'])) ?>
                                </div>
                                <div style="font-size:12px; color:#6B6B6B;"><?= htmlspecialchars($f['Flght_Arrival']) ?></div>
                            </div>
                        </div>
                        <div class="mt-2" style="font-size:12px; color:#aaa;">
                            <?= htmlspecialchars($fromCity) ?> → <?= htmlspecialchars($toCity) ?>
                            &nbsp;·&nbsp; <?= $f['Flght_SeatAvail'] ?> seats left
                        </div>
                    </div>

                    <div class="col-md-4 py-3 px-4 text-end" style="border-left:1px solid #f0f0f0;">
                        <div style="font-size:11px; color:#aaa; text-transform:uppercase; letter-spacing:.4px;">
                            <?= classLabel($class) ?> · per person
                        </div>
                        <div class="price-text" style="font-size:28px; font-weight:800; letter-spacing:-0.5px;">
                            ₱<?= number_format($pricePerPax, 2) ?>
                        </div>
                        <?php if ($passengers > 1): ?>
                            <div style="font-size:12px; color:#aaa;">Total: ₱<?= number_format($totalPrice, 2) ?></div>
                        <?php endif; ?>
                        <a href="<?= $bookUrl ?>" class="btn btn-trip-orange mt-2 px-4 py-2" style="font-size:14px;">
                            Book Now →
                        </a>
                    </div>

                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Return Flights -->
        <?php if ($tripType === 'roundtrip' && $returnDate): ?>
            <div class="mt-5 mb-3">
                <h5 class="fw-bold mb-0">
                    <?= htmlspecialchars($toCity) ?> → <?= htmlspecialchars($fromCity) ?>
                    <span class="badge badge-trip-blue ms-2 px-2 py-1" style="font-size:13px;">Return</span>
                </h5>
                <p class="text-muted" style="font-size:13px;">
                    <?= date('D, d M Y', strtotime($returnDate)) ?> &middot; <?= $passengers ?> passenger<?= $passengers > 1 ? 's' : '' ?>
                </p>
            </div>

            <?php if (empty($returnFlights)): ?>
                <div class="trip-card p-4 text-center text-muted" style="font-size:14px;">
                    No return flights found for <?= date('d M Y', strtotime($returnDate)) ?>.
                </div>
            <?php else: ?>
                <?php foreach ($returnFlights as $f):
                    $duration    = flightDuration($f['Flght_DepartDate'], $f['Flght_ArriveDate']);
                    $pricePerPax = (float)$f['Flght_Fare'] * $multiplier;
                    $bookUrl = '/dbweb/flights/book.php?' . http_build_query([
                        'flight_id'  => $f['Flght_ID'],
                        'passengers' => $passengers,
                        'class'      => $class,
                    ]);
                ?>
                <div class="trip-card mb-3 p-0 overflow-hidden" style="border-left:4px solid #1a9e5c;">
                    <div class="row g-0 align-items-center">
                        <div class="col-md-2 text-center py-3 px-3" style="border-right:1px solid #f0f0f0;">
                            <div style="font-size:22px; color:#0086FF; font-weight:800;"><?= $f['Airln_Code'] ?></div>
                            <div style="font-size:12px; color:#6B6B6B;"><?= htmlspecialchars($f['Airln_Name']) ?></div>
                            <div style="font-size:11px; color:#aaa;" class="mt-1"><?= $f['Flght_No'] ?></div>
                        </div>
                        <div class="col-md-6 py-3 px-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="text-center">
                                    <div class="fw-bold" style="font-size:24px;"><?= date('H:i', strtotime($f['Flght_DepartDate'])) ?></div>
                                    <div style="font-size:12px; color:#6B6B6B;"><?= $f['Flght_Depart'] ?></div>
                                </div>
                                <div class="flex-grow-1 text-center">
                                    <div style="font-size:12px; color:#6B6B6B; margin-bottom:4px;"><?= $duration ?></div>
                                    <div style="height:2px; background:#0086FF; position:relative;">
                                        <span style="position:absolute;right:-4px;top:-7px;font-size:16px;color:#0086FF;">✈</span>
                                    </div>
                                    <div class="mt-1"><span class="badge badge-trip-green px-2" style="font-size:11px;">Return · Non-stop</span></div>
                                </div>
                                <div class="text-center">
                                    <div class="fw-bold" style="font-size:24px;"><?= date('H:i', strtotime($f['Flght_ArriveDate'])) ?></div>
                                    <div style="font-size:12px; color:#6B6B6B;"><?= $f['Flght_Arrival'] ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 py-3 px-4 text-end" style="border-left:1px solid #f0f0f0;">
                            <div style="font-size:11px; color:#aaa; text-transform:uppercase;">Return · <?= classLabel($class) ?></div>
                            <div class="price-text" style="font-size:28px; font-weight:800;">₱<?= number_format($pricePerPax, 2) ?></div>
                            <a href="<?= $bookUrl ?>" class="btn btn-trip-orange mt-2 px-4 py-2" style="font-size:14px;">Book Return →</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>

    <?php else: ?>
        <div class="text-center py-5 text-muted">
            <div style="font-size:60px; opacity:.2;">✈</div>
            <h5 class="mt-3">Search for available flights above.</h5>
        </div>
    <?php endif; ?>

</div>

<?php include '../layout/footer.php'; ?>
