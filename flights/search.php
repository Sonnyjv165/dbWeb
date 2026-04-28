<?php
session_start();
require_once '../config/db.php';
require_once '../config/airports.php';
if (($_SESSION['role'] ?? '') === 'admin') {
    header('Location: /dbweb/admin/dashboard.php');
    exit();
}

// ── Gather search params ──────────────────────────────────────
$fromCode      = strtoupper(trim($_GET['from']        ?? ''));
$toCode        = strtoupper(trim($_GET['to']          ?? ''));
$date          = $_GET['date']                         ?? '';
$returnDate    = $_GET['return_date']                  ?? '';
$passengers    = max(1, (int)($_GET['passengers']      ?? 1));
$class         = in_array($_GET['class'] ?? '', ['economy','business','first']) ? $_GET['class'] : 'economy';
$tripType      = ($_GET['trip_type'] ?? 'oneway') === 'roundtrip' ? 'roundtrip' : 'oneway';
$selectedOutId = (int)($_GET['out'] ?? 0);

$multiplier = match($class) { 'business' => 2.5, 'first' => 4.0, default => 1.0 };

$flights           = [];
$returnFlights     = [];
$selectedOutFlight = null;
$error             = '';
$fromCity          = airportCity($fromCode);
$toCity            = airportCity($toCode);

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

        // Round trip: fetch selected outbound flight details
        if ($tripType === 'roundtrip' && $selectedOutId > 0) {
            $outStmt = $conn->prepare("
                SELECT f.*, a.Airln_Name, a.Airln_Code
                FROM Flight f
                JOIN Airliner a ON f.Flght_AirlnID = a.Airln_ID
                WHERE f.Flght_ID = ?
                LIMIT 1
            ");
            $outStmt->bind_param('i', $selectedOutId);
            $outStmt->execute();
            $selectedOutFlight = $outStmt->get_result()->fetch_assoc();
        }

        // Fetch return flights
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

// Base params to preserve search state across step links
$baseParams = http_build_query([
    'from'        => $fromCode,
    'to'          => $toCode,
    'date'        => $date,
    'return_date' => $returnDate,
    'passengers'  => $passengers,
    'class'       => $class,
    'trip_type'   => $tripType,
]);

$title = 'Flight Results';
include '../layout/layout.php';
?>

<style>
/* ── Sort bar ── */
.sort-bar { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:14px; }
.sort-btn {
    padding:5px 16px; font-size:12px; font-weight:600; border-radius:20px; cursor:pointer;
    border:1.5px solid #ddd; background:#fff; color:#6B6B6B; transition:all 0.15s;
}
.sort-btn.active, .sort-btn:hover { background:#0086FF; color:#fff; border-color:#0086FF; }
.seats-warning { color:#d93025; font-size:12px; font-weight:600; }
.seats-ok      { color:#aaa; font-size:12px; }

/* ── Step pills ── */
.step-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 18px;
    border-radius: 24px;
    font-size: 13px;
    font-weight: 600;
}
.step-pill.active   { background: #e6f9f0; color: #1a9e5c; border: 1.5px solid #1a9e5c; }
.step-pill.current  { background: #fff4ed; color: #FF7020; border: 1.5px solid #FF7020; }
.step-pill.inactive { background: #f5f5f5; color: #aaa;    border: 1.5px solid #ddd; }
.locked-card { border: 2px solid #1a9e5c; border-radius: 12px; background: #f9fffe; }
</style>

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

            <div class="<?= $tripType === 'roundtrip' ? 'col-md-2' : 'col-md-2' ?>">
                <label class="form-label text-white fw-semibold" style="font-size:11px; text-transform:uppercase; letter-spacing:.5px;">Depart</label>
                <input type="date" name="date" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($date) ?>" required min="<?= date('Y-m-d') ?>">
            </div>

            <?php if ($tripType === 'roundtrip'): ?>
            <div class="col-md-2">
                <label class="form-label text-white fw-semibold" style="font-size:11px; text-transform:uppercase; letter-spacing:.5px;">Return</label>
                <input type="date" name="return_date" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($returnDate) ?>" min="<?= htmlspecialchars($date ?: date('Y-m-d')) ?>">
            </div>
            <div class="col-md-1">
                <label class="form-label text-white fw-semibold" style="font-size:11px; text-transform:uppercase; letter-spacing:.5px;">Pax</label>
                <select name="passengers" class="form-select form-select-sm">
                    <?php for ($i = 1; $i <= 9; $i++): ?>
                        <option value="<?= $i ?>" <?= $i == $passengers ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label text-white fw-semibold" style="font-size:11px; text-transform:uppercase; letter-spacing:.5px;">Class</label>
                <select name="class" class="form-select form-select-sm">
                    <?php foreach (['economy'=>'Eco','business'=>'Biz','first'=>'First'] as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= $val === $class ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-trip-orange w-100 py-2" style="font-size:14px;">
                    <i class="bi bi-search me-1"></i>Search
                </button>
            </div>
            <?php else: ?>
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
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- ====== RESULTS ====== -->
<div class="container py-4">

    <?php if ($error): ?>
        <div class="alert alert-warning rounded-3"><?= htmlspecialchars($error) ?></div>

    <?php elseif ($fromCode && $toCode && $date): ?>

    <?php
    // ── ROUND TRIP STEP 2: Outbound selected, now pick return ──
    if ($tripType === 'roundtrip' && $selectedOutId > 0 && $selectedOutFlight):
        $outPricePerPax = (float)$selectedOutFlight['Flght_Fare'] * $multiplier;
    ?>

        <!-- Step indicator -->
        <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
            <span class="step-pill active"><i class="bi bi-check-circle-fill"></i> Step 1: Outbound Selected</span>
            <i class="bi bi-arrow-right text-muted"></i>
            <span class="step-pill current"><i class="bi bi-airplane-fill"></i> Step 2: Choose Return Flight</span>
        </div>

        <!-- Locked outbound card -->
        <div class="locked-card p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold text-success mb-0">
                    <i class="bi bi-check-circle-fill me-2"></i>Outbound:
                    <?= htmlspecialchars($fromCity) ?> → <?= htmlspecialchars($toCity) ?>
                </h6>
                <a href="/dbweb/flights/search.php?<?= $baseParams ?>"
                   class="btn btn-sm btn-outline-secondary" style="font-size:12px;">
                    <i class="bi bi-pencil me-1"></i>Change
                </a>
            </div>
            <div class="row g-0 align-items-center">
                <div class="col-auto text-center pe-4" style="border-right:1px solid #e0f0e8; min-width:90px;">
                    <div style="font-size:20px; color:#0086FF; font-weight:800;"><?= htmlspecialchars($selectedOutFlight['Airln_Code']) ?></div>
                    <div style="font-size:11px; color:#6B6B6B;"><?= htmlspecialchars($selectedOutFlight['Airln_Name']) ?></div>
                    <div style="font-size:11px; color:#aaa;" class="mt-1"><?= htmlspecialchars($selectedOutFlight['Flght_No']) ?></div>
                </div>
                <div class="col px-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="text-center">
                            <div class="fw-bold" style="font-size:22px;"><?= date('H:i', strtotime($selectedOutFlight['Flght_DepartDate'])) ?></div>
                            <div style="font-size:12px; color:#6B6B6B;"><?= htmlspecialchars($selectedOutFlight['Flght_Depart']) ?></div>
                        </div>
                        <div class="flex-grow-1 text-center">
                            <div style="font-size:12px; color:#aaa; margin-bottom:4px;"><?= flightDuration($selectedOutFlight['Flght_DepartDate'], $selectedOutFlight['Flght_ArriveDate']) ?></div>
                            <div style="height:2px; background:#1a9e5c; position:relative;">
                                <span style="position:absolute;right:-4px;top:-7px;font-size:14px;color:#1a9e5c;">✈</span>
                            </div>
                            <div class="mt-1"><span class="badge badge-trip-green" style="font-size:10px;">Non-stop</span></div>
                        </div>
                        <div class="text-center">
                            <div class="fw-bold" style="font-size:22px;"><?= date('H:i', strtotime($selectedOutFlight['Flght_ArriveDate'])) ?></div>
                            <div style="font-size:12px; color:#6B6B6B;"><?= htmlspecialchars($selectedOutFlight['Flght_Arrival']) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-auto ps-4 text-end" style="border-left:1px solid #e0f0e8;">
                    <div style="font-size:11px; color:#aaa;"><?= classLabel($class) ?> · per person</div>
                    <div class="price-text fw-bold" style="font-size:22px; color:#1a9e5c;">₱<?= number_format($outPricePerPax, 2) ?></div>
                    <div style="font-size:11px; color:#aaa;"><?= date('D, d M Y', strtotime($selectedOutFlight['Flght_DepartDate'])) ?></div>
                </div>
            </div>
        </div>

        <!-- Return flights -->
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h5 class="fw-bold mb-0">
                    <?= htmlspecialchars($toCity) ?> → <?= htmlspecialchars($fromCity) ?>
                    <span class="badge badge-trip-orange ms-2 px-2 py-1" style="font-size:13px;">Return</span>
                </h5>
                <p class="text-muted mb-0" style="font-size:13px;">
                    <?= $returnDate ? date('D, d M Y', strtotime($returnDate)) : '—' ?>
                    &middot; <?= $passengers ?> passenger<?= $passengers > 1 ? 's' : '' ?>
                    &middot; <?= classLabel($class) ?>
                </p>
            </div>
            <span class="text-muted" style="font-size:13px;"><?= count($returnFlights) ?> flight<?= count($returnFlights) != 1 ? 's' : '' ?> found</span>
        </div>

        <?php if (empty($returnFlights)): ?>
            <div class="trip-card p-5 text-center text-muted">
                <div style="font-size:48px; opacity:.3;">✈</div>
                <h6 class="mt-3">No return flights found for this date.</h6>
                <p style="font-size:14px;">Try changing the return date in the search bar above.</p>
            </div>
        <?php else: ?>
            <div class="sort-bar">
                <span style="font-size:12px; color:#aaa; font-weight:600;">Sort:</span>
                <button class="sort-btn active" onclick="sortFlights('cheapest','rt2-list',this)">Cheapest</button>
                <button class="sort-btn" onclick="sortFlights('earliest','rt2-list',this)">Earliest</button>
                <button class="sort-btn" onclick="sortFlights('latest','rt2-list',this)">Latest</button>
            </div>
            <div id="rt2-list">
            <?php foreach ($returnFlights as $f):
                $retPricePerPax = (float)$f['Flght_Fare'] * $multiplier;
                $combinedTotal  = ($outPricePerPax + $retPricePerPax) * $passengers;
                $bookUrl = '/dbweb/flights/book.php?' . http_build_query([
                    'flight_id'  => $selectedOutId,
                    'return_id'  => $f['Flght_ID'],
                    'passengers' => $passengers,
                    'class'      => $class,
                ]);
            ?>
            <div class="trip-card mb-3 p-0 overflow-hidden" style="border-left:4px solid #FF7020;">
                <div class="row g-0 align-items-center">
                    <div class="col-md-2 text-center py-3 px-3" style="border-right:1px solid #f0f0f0;">
                        <div style="font-size:22px; color:#0086FF; font-weight:800;"><?= htmlspecialchars($f['Airln_Code']) ?></div>
                        <div style="font-size:12px; color:#6B6B6B;"><?= htmlspecialchars($f['Airln_Name']) ?></div>
                        <div style="font-size:11px; color:#aaa;" class="mt-1"><?= htmlspecialchars($f['Flght_No']) ?></div>
                    </div>
                    <div class="col-md-6 py-3 px-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="text-center">
                                <div class="fw-bold" style="font-size:24px;"><?= date('H:i', strtotime($f['Flght_DepartDate'])) ?></div>
                                <div style="font-size:12px; color:#6B6B6B;"><?= htmlspecialchars($f['Flght_Depart']) ?></div>
                            </div>
                            <div class="flex-grow-1 text-center">
                                <div style="font-size:12px; color:#6B6B6B; margin-bottom:4px;"><?= flightDuration($f['Flght_DepartDate'], $f['Flght_ArriveDate']) ?></div>
                                <div style="height:2px; background:#0086FF; position:relative;">
                                    <span style="position:absolute;right:-4px;top:-7px;font-size:16px;color:#0086FF;">✈</span>
                                </div>
                                <div class="mt-1"><span class="badge badge-trip-orange px-2" style="font-size:11px;">Return · Non-stop</span></div>
                            </div>
                            <div class="text-center">
                                <div class="fw-bold" style="font-size:24px;"><?= date('H:i', strtotime($f['Flght_ArriveDate'])) ?></div>
                                <div style="font-size:12px; color:#6B6B6B;"><?= htmlspecialchars($f['Flght_Arrival']) ?></div>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span style="font-size:12px; color:#aaa;">
                                <?= htmlspecialchars($toCity) ?> → <?= htmlspecialchars($fromCity) ?>
                            </span>
                            &nbsp;·&nbsp;
                            <?php if ($f['Flght_SeatAvail'] <= 7): ?>
                                <span class="seats-warning"><i class="bi bi-exclamation-circle me-1"></i>Only <?= $f['Flght_SeatAvail'] ?> seats left!</span>
                            <?php else: ?>
                                <span class="seats-ok"><?= $f['Flght_SeatAvail'] ?> seats left</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 py-3 px-4 text-end" style="border-left:1px solid #f0f0f0;">
                        <div style="font-size:11px; color:#aaa; text-transform:uppercase;">Return · <?= classLabel($class) ?></div>
                        <div class="price-text" style="font-size:24px; font-weight:800;">₱<?= number_format($retPricePerPax, 2) ?></div>
                        <div style="font-size:12px; color:#aaa;">per person (return only)</div>
                        <div class="mt-1 fw-bold" style="font-size:13px; color:#FF7020;">
                            Combined: ₱<?= number_format($combinedTotal, 2) ?>
                            <span style="font-weight:400; color:#aaa;"><?= $passengers > 1 ? "($passengers pax)" : '' ?></span>
                        </div>
                        <a href="<?= $bookUrl ?>" class="btn btn-trip-orange mt-2 px-4 py-2" style="font-size:14px;">
                            <i class="bi bi-check2-circle me-1"></i>Book Round Trip →
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            </div><!-- #rt2-list -->
        <?php endif; ?>

    <?php
    // ── ROUND TRIP STEP 1: Select outbound flight first ──
    elseif ($tripType === 'roundtrip'):
    ?>

        <!-- Step indicator -->
        <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
            <span class="step-pill current"><i class="bi bi-airplane-fill"></i> Step 1: Choose Outbound Flight</span>
            <i class="bi bi-arrow-right text-muted"></i>
            <span class="step-pill inactive"><i class="bi bi-airplane"></i> Step 2: Choose Return Flight</span>
        </div>

        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h5 class="fw-bold mb-0">
                    <?= htmlspecialchars($fromCity) ?> → <?= htmlspecialchars($toCity) ?>
                    <span class="badge badge-trip-blue ms-2 px-2 py-1" style="font-size:13px;">Outbound</span>
                </h5>
                <p class="text-muted mb-0" style="font-size:13px;">
                    <?= date('D, d M Y', strtotime($date)) ?> &middot;
                    <?= $passengers ?> passenger<?= $passengers > 1 ? 's' : '' ?> &middot;
                    <?= classLabel($class) ?> &middot;
                    <span class="badge badge-trip-blue px-2 py-1">Round Trip</span>
                </p>
            </div>
            <span class="text-muted" style="font-size:13px;"><?= count($flights) ?> flight<?= count($flights) != 1 ? 's' : '' ?> found</span>
        </div>

        <?php if (empty($flights)): ?>
            <div class="trip-card p-5 text-center text-muted">
                <div style="font-size:48px; opacity:.3;">✈</div>
                <h6 class="mt-3">No outbound flights found for this route and date.</h6>
                <p style="font-size:14px;">Try a different date or adjust your search.</p>
            </div>
        <?php else: ?>
            <div class="sort-bar">
                <span style="font-size:12px; color:#aaa; font-weight:600;">Sort:</span>
                <button class="sort-btn active" onclick="sortFlights('cheapest','rt1-list',this)">Cheapest</button>
                <button class="sort-btn" onclick="sortFlights('earliest','rt1-list',this)">Earliest</button>
                <button class="sort-btn" onclick="sortFlights('latest','rt1-list',this)">Latest</button>
            </div>
            <div id="rt1-list">
            <?php foreach ($flights as $f):
                $pricePerPax = (float)$f['Flght_Fare'] * $multiplier;
                $lowSeats    = $f['Flght_SeatAvail'] <= 7;
                $selectUrl = '/dbweb/flights/search.php?' . $baseParams . '&out=' . $f['Flght_ID'];
            ?>
            <div class="trip-card mb-3 p-0 overflow-hidden"
                 data-price="<?= $pricePerPax ?>"
                 data-time="<?= strtotime($f['Flght_DepartDate']) ?>">
                <div class="row g-0 align-items-center">
                    <div class="col-md-2 text-center py-3 px-3" style="border-right:1px solid #f0f0f0;">
                        <div style="font-size:22px; color:#0086FF; font-weight:800;"><?= htmlspecialchars($f['Airln_Code']) ?></div>
                        <div style="font-size:12px; color:#6B6B6B;"><?= htmlspecialchars($f['Airln_Name']) ?></div>
                        <div style="font-size:11px; color:#aaa;" class="mt-1"><?= htmlspecialchars($f['Flght_No']) ?></div>
                    </div>
                    <div class="col-md-6 py-3 px-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="text-center">
                                <div class="fw-bold" style="font-size:24px;"><?= date('H:i', strtotime($f['Flght_DepartDate'])) ?></div>
                                <div style="font-size:12px; color:#6B6B6B;"><?= htmlspecialchars($f['Flght_Depart']) ?></div>
                            </div>
                            <div class="flex-grow-1 text-center">
                                <div style="font-size:12px; color:#6B6B6B; margin-bottom:4px;"><?= flightDuration($f['Flght_DepartDate'], $f['Flght_ArriveDate']) ?></div>
                                <div style="height:2px; background:#0086FF; position:relative;">
                                    <span style="position:absolute;right:-4px;top:-7px;font-size:16px;color:#0086FF;">✈</span>
                                </div>
                                <div class="mt-1"><span class="badge badge-trip-blue px-2" style="font-size:11px;">Non-stop</span></div>
                            </div>
                            <div class="text-center">
                                <div class="fw-bold" style="font-size:24px;"><?= date('H:i', strtotime($f['Flght_ArriveDate'])) ?></div>
                                <div style="font-size:12px; color:#6B6B6B;"><?= htmlspecialchars($f['Flght_Arrival']) ?></div>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span style="font-size:12px; color:#aaa;">
                                <?= htmlspecialchars($fromCity) ?> → <?= htmlspecialchars($toCity) ?>
                            </span>
                            &nbsp;·&nbsp;
                            <?php if ($f['Flght_SeatAvail'] <= 7): ?>
                                <span class="seats-warning"><i class="bi bi-exclamation-circle me-1"></i>Only <?= $f['Flght_SeatAvail'] ?> seats left!</span>
                            <?php else: ?>
                                <span class="seats-ok"><?= $f['Flght_SeatAvail'] ?> seats left</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 py-3 px-4 text-end" style="border-left:1px solid #f0f0f0;">
                        <div style="font-size:11px; color:#aaa; text-transform:uppercase;"><?= classLabel($class) ?> · per person</div>
                        <div class="price-text" style="font-size:28px; font-weight:800;">₱<?= number_format($pricePerPax, 2) ?></div>
                        <div style="font-size:12px; color:#aaa;">+ return fare in next step</div>
                        <a href="<?= $selectUrl ?>" class="btn btn-trip mt-2 px-4 py-2" style="font-size:14px;">
                            Select Outbound →
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            </div><!-- #rt1-list -->
        <?php endif; ?>

    <?php
    // ── ONE WAY: Direct booking ──
    else:
    ?>

        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h5 class="fw-bold mb-0"><?= htmlspecialchars($fromCity) ?> → <?= htmlspecialchars($toCity) ?></h5>
                <p class="text-muted mb-0" style="font-size:13px;">
                    <?= date('D, d M Y', strtotime($date)) ?> &middot;
                    <?= $passengers ?> passenger<?= $passengers > 1 ? 's' : '' ?> &middot;
                    <?= classLabel($class) ?>
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
            <div class="sort-bar">
                <span style="font-size:12px; color:#aaa; font-weight:600;">Sort:</span>
                <button class="sort-btn active" onclick="sortFlights('cheapest','ow-list',this)">Cheapest</button>
                <button class="sort-btn" onclick="sortFlights('earliest','ow-list',this)">Earliest</button>
                <button class="sort-btn" onclick="sortFlights('latest','ow-list',this)">Latest</button>
            </div>
            <div id="ow-list">
            <?php foreach ($flights as $f):
                $duration    = flightDuration($f['Flght_DepartDate'], $f['Flght_ArriveDate']);
                $pricePerPax = (float)$f['Flght_Fare'] * $multiplier;
                $totalPrice  = $pricePerPax * $passengers;
                $lowSeats    = $f['Flght_SeatAvail'] <= 7;
                $bookUrl = '/dbweb/flights/book.php?' . http_build_query([
                    'flight_id'  => $f['Flght_ID'],
                    'passengers' => $passengers,
                    'class'      => $class,
                ]);
            ?>
            <div class="trip-card mb-3 p-0 overflow-hidden"
                 data-price="<?= $pricePerPax ?>"
                 data-time="<?= strtotime($f['Flght_DepartDate']) ?>">
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
                        <div class="mt-2">
                            <span style="font-size:12px; color:#aaa;">
                                <?= htmlspecialchars($fromCity) ?> → <?= htmlspecialchars($toCity) ?>
                            </span>
                            &nbsp;·&nbsp;
                            <?php if ($lowSeats): ?>
                                <span class="seats-warning"><i class="bi bi-exclamation-circle me-1"></i>Only <?= $f['Flght_SeatAvail'] ?> seats left!</span>
                            <?php else: ?>
                                <span class="seats-ok"><?= $f['Flght_SeatAvail'] ?> seats left</span>
                            <?php endif; ?>
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
            </div><!-- #ow-list -->
        <?php endif; ?>

    <?php endif; ?>

    <?php else: ?>
        <div class="text-center py-5 text-muted">
            <div style="font-size:60px; opacity:.2;">✈</div>
            <h5 class="mt-3">Search for available flights above.</h5>
        </div>
    <?php endif; ?>

</div>

<script>
function sortFlights(mode, listId, btn) {
    // Update active button style within the same sort bar
    btn.closest('.sort-bar').querySelectorAll('.sort-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const list  = document.getElementById(listId);
    if (!list) return;
    const cards = Array.from(list.querySelectorAll('.trip-card'));

    cards.sort((a, b) => {
        if (mode === 'cheapest') return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
        if (mode === 'earliest') return parseInt(a.dataset.time)    - parseInt(b.dataset.time);
        if (mode === 'latest')   return parseInt(b.dataset.time)    - parseInt(a.dataset.time);
        return 0;
    });
    cards.forEach(c => list.appendChild(c));
}
</script>

<?php include '../layout/footer.php'; ?>
