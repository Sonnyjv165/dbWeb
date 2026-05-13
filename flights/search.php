<?php
session_start();
require_once '../config/db.php';
require_once '../config/airports.php';
if (($_SESSION['role'] ?? '') === 'admin') {
    header('Location: /admin/dashboard.php');
    exit();
}

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
            FROM flight f
            JOIN airliner a ON f.Flght_AirlnID = a.Airln_ID
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

        if ($tripType === 'roundtrip' && $selectedOutId > 0) {
            $outStmt = $conn->prepare("
                SELECT f.*, a.Airln_Name, a.Airln_Code
                FROM flight f
                JOIN airliner a ON f.Flght_AirlnID = a.Airln_ID
                WHERE f.Flght_ID = ?
                LIMIT 1
            ");
            $outStmt->bind_param('i', $selectedOutId);
            $outStmt->execute();
            $selectedOutFlight = $outStmt->get_result()->fetch_assoc();
        }

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
/* ── Sticky search bar ── */
.search-bar-sticky {
    position: sticky;
    top: 74px;
    z-index: 100;
    background: var(--trip-navy);
    padding: 14px 0;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    box-shadow: 0 4px 24px rgba(0,0,0,0.22);
}
.sbar-label {
    font-size: 10px; font-weight: 700; letter-spacing: 0.10em;
    text-transform: uppercase; color: rgba(255,255,255,0.55);
    margin-bottom: 5px; display: block;
}
.search-bar-sticky .form-control,
.search-bar-sticky .form-select {
    background: rgba(255,255,255,0.10);
    border-color: rgba(255,255,255,0.12);
    color: #fff;
    border-radius: var(--radius-md);
}
.search-bar-sticky .form-control:focus,
.search-bar-sticky .form-select:focus {
    background: rgba(255,255,255,0.16);
    border-color: rgba(255,255,255,0.30);
    box-shadow: 0 0 0 3px rgba(255,255,255,0.08);
    color: #fff;
}
.search-bar-sticky .form-select option { background: #1a3060; color: #fff; }
.search-bar-sticky input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1) opacity(.6); }

/* ── Sort bar ── */
.sort-bar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
.sort-btn {
    padding: 5px 18px; font-size: 12px; font-weight: 600;
    border-radius: 999px; cursor: pointer;
    border: 1.5px solid rgba(0,0,0,0.12);
    background: #fff; color: var(--trip-muted);
    font-family: var(--font-sans);
    transition: all 0.18s;
}
.sort-btn.active, .sort-btn:hover { background: var(--trip-text); color: #fff; border-color: var(--trip-text); }

/* ── Step pills ── */
.step-pill {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 8px 18px; border-radius: 999px;
    font-size: 13px; font-weight: 600;
}
.step-pill.active   { background: #DCFCE7; color: #15803D; border: 1.5px solid rgba(21,128,61,0.30); }
.step-pill.current  { background: #FFEDD5; color: var(--trip-orange); border: 1.5px solid rgba(240,96,32,0.30); }
.step-pill.inactive { background: var(--trip-bg); color: var(--trip-muted); border: 1.5px solid var(--trip-border); }

/* ── Flight cards ── */
.flight-card {
    background: #fff;
    border-radius: var(--radius-lg);
    border: 1px solid rgba(0,0,0,0.07);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    margin-bottom: 12px;
    transition: box-shadow 0.2s, transform 0.2s;
}
.flight-card:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-1px);
}
.flight-card-out { border-left: 3px solid var(--trip-blue); }
.flight-card-ret { border-left: 3px solid var(--trip-orange); }
.flight-card-locked { border: 1.5px solid rgba(21,128,61,0.40); background: #FAFFFE; border-radius: var(--radius-lg); }

.airline-badge {
    font-size: 20px; font-weight: 800; color: var(--trip-blue);
    font-family: var(--font-sans); letter-spacing: -0.5px;
}
.time-display {
    font-size: 24px; font-weight: 800; letter-spacing: -0.5px; color: var(--trip-text);
}
.route-code {
    font-size: 12px; color: var(--trip-muted); margin-top: 2px;
}
.flight-line {
    height: 2px; background: var(--trip-blue); position: relative;
    flex: 1;
}
.flight-line::after {
    content: '';
    position: absolute; right: -5px; top: -5px;
    width: 10px; height: 10px;
    background: var(--trip-blue);
    border-radius: 50%;
}
.flight-line-orange { background: var(--trip-orange); }
.flight-line-orange::after { background: var(--trip-orange); }
.flight-line-green { background: #16a34a; }
.flight-line-green::after { background: #16a34a; }

.seats-warning { color: var(--trip-danger); font-size: 12px; font-weight: 600; }
.seats-ok      { color: var(--trip-muted); font-size: 12px; }

.result-heading {
    font-family: var(--font-serif);
    font-weight: 600;
    letter-spacing: -0.02em;
    margin-bottom: 2px;
}

/* ── Empty state ── */
.empty-state {
    text-align: center;
    padding: 60px 24px;
    background: #fff;
    border-radius: var(--radius-lg);
    border: 1px solid rgba(0,0,0,0.07);
}
.empty-icon {
    font-size: 52px;
    opacity: .16;
    color: var(--trip-text);
    margin-bottom: 16px;
    display: block;
}
</style>

<!-- ====== STICKY SEARCH BAR ====== -->
<div class="search-bar-sticky">
    <div class="container">
        <form method="GET" action="/flights/search.php" class="row g-2 align-items-end">
            <input type="hidden" name="trip_type" value="<?= $tripType ?>">

            <div class="col-md-2">
                <label class="sbar-label">From</label>
                <select name="from" class="form-select form-select-sm" required>
                    <?php foreach ($AIRPORTS as $code => $ap): ?>
                        <option value="<?= $code ?>" <?= $code === $fromCode ? 'selected' : '' ?>>
                            <?= $ap['city'] ?> (<?= $code ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="sbar-label">To</label>
                <select name="to" class="form-select form-select-sm" required>
                    <?php foreach ($AIRPORTS as $code => $ap): ?>
                        <option value="<?= $code ?>" <?= $code === $toCode ? 'selected' : '' ?>>
                            <?= $ap['city'] ?> (<?= $code ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="sbar-label">Depart</label>
                <input type="date" name="date" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($date) ?>" required min="<?= date('Y-m-d') ?>">
            </div>

            <?php if ($tripType === 'roundtrip'): ?>
            <div class="col-md-2">
                <label class="sbar-label">Return</label>
                <input type="date" name="return_date" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($returnDate) ?>" min="<?= htmlspecialchars($date ?: date('Y-m-d')) ?>">
            </div>
            <div class="col-md-1">
                <label class="sbar-label">Pax</label>
                <select name="passengers" class="form-select form-select-sm">
                    <?php for ($i = 1; $i <= 9; $i++): ?>
                        <option value="<?= $i ?>" <?= $i == $passengers ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="sbar-label">Class</label>
                <select name="class" class="form-select form-select-sm">
                    <?php foreach (['economy'=>'Eco','business'=>'Biz','first'=>'First'] as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= $val === $class ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-trip-orange w-100 py-2" style="font-size:14px; border-radius:var(--radius-md);">
                    <i class="bi bi-search me-1"></i>Search
                </button>
            </div>
            <?php else: ?>
            <div class="col-md-2">
                <label class="sbar-label">Passengers</label>
                <select name="passengers" class="form-select form-select-sm">
                    <?php for ($i = 1; $i <= 9; $i++): ?>
                        <option value="<?= $i ?>" <?= $i == $passengers ? 'selected' : '' ?>><?= $i ?> Pax</option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="sbar-label">Class</label>
                <select name="class" class="form-select form-select-sm">
                    <?php foreach (['economy'=>'Economy','business'=>'Business','first'=>'First'] as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= $val === $class ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-trip-orange w-100 py-2" style="font-size:14px; border-radius:var(--radius-md);">
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
        <div class="alert alert-warning"><?= htmlspecialchars($error) ?></div>

    <?php elseif ($fromCode && $toCode && $date): ?>

    <?php
    // ── ROUND TRIP STEP 2 ──
    if ($tripType === 'roundtrip' && $selectedOutId > 0 && $selectedOutFlight):
        $outPricePerPax = (float)$selectedOutFlight['Flght_Fare'] * $multiplier;
    ?>

        <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
            <span class="step-pill active"><i class="bi bi-check-circle-fill"></i> Step 1: Outbound Selected</span>
            <i class="bi bi-arrow-right text-muted"></i>
            <span class="step-pill current"><i class="bi bi-airplane-fill"></i> Step 2: Choose Return Flight</span>
        </div>

        <!-- Locked outbound -->
        <div class="flight-card-locked p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h6 class="fw-bold text-success mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill"></i>
                    Outbound: <?= htmlspecialchars($fromCity) ?> &rarr; <?= htmlspecialchars($toCity) ?>
                </h6>
                <a href="/flights/search.php?<?= $baseParams ?>" class="btn btn-sm btn-trip-outline" style="font-size:12px;">
                    <i class="bi bi-pencil me-1"></i>Change
                </a>
            </div>
            <div class="row g-0 align-items-center">
                <div class="col-auto text-center pe-4" style="border-right:1px solid #d1fae5; min-width:90px;">
                    <div class="airline-badge" style="color:#15803D;"><?= htmlspecialchars($selectedOutFlight['Airln_Code']) ?></div>
                    <div style="font-size:11px; color:var(--trip-muted);"><?= htmlspecialchars($selectedOutFlight['Airln_Name']) ?></div>
                    <div style="font-size:11px; color:#aaa; margin-top:2px;"><?= htmlspecialchars($selectedOutFlight['Flght_No']) ?></div>
                </div>
                <div class="col px-4">
                    <div class="d-flex align-items-center gap-4">
                        <div class="text-center">
                            <div class="time-display"><?= date('H:i', strtotime($selectedOutFlight['Flght_DepartDate'])) ?></div>
                            <div class="route-code"><?= htmlspecialchars($selectedOutFlight['Flght_Depart']) ?></div>
                        </div>
                        <div class="flex-grow-1 text-center">
                            <div style="font-size:12px; color:var(--trip-muted); margin-bottom:6px;"><?= flightDuration($selectedOutFlight['Flght_DepartDate'], $selectedOutFlight['Flght_ArriveDate']) ?></div>
                            <div style="display:flex; align-items:center; gap:4px;">
                                <div class="flight-line flight-line-green"></div>
                                <i class="bi bi-airplane-fill" style="color:#16a34a; font-size:14px;"></i>
                            </div>
                            <div class="mt-1"><span class="badge badge-trip-green" style="font-size:10px;">Non-stop</span></div>
                        </div>
                        <div class="text-center">
                            <div class="time-display"><?= date('H:i', strtotime($selectedOutFlight['Flght_ArriveDate'])) ?></div>
                            <div class="route-code"><?= htmlspecialchars($selectedOutFlight['Flght_Arrival']) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-auto ps-4 text-end" style="border-left:1px solid #d1fae5;">
                    <div style="font-size:11px; color:var(--trip-muted);"><?= classLabel($class) ?> · per person</div>
                    <div class="fw-bold" style="font-size:22px; color:#15803D;">&#8369;<?= number_format($outPricePerPax, 2) ?></div>
                    <div style="font-size:11px; color:var(--trip-muted);"><?= date('D, d M Y', strtotime($selectedOutFlight['Flght_DepartDate'])) ?></div>
                </div>
            </div>
        </div>

        <!-- Return flights header -->
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div>
                <h5 class="result-heading mb-0">
                    <?= htmlspecialchars($toCity) ?> &rarr; <?= htmlspecialchars($fromCity) ?>
                    <span class="badge badge-trip-orange ms-2 px-2 py-1" style="font-size:12px;">Return</span>
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
            <div class="empty-state">
                <i class="bi bi-airplane empty-icon"></i>
                <h6 class="fw-bold">No return flights found for this date.</h6>
                <p class="text-muted" style="font-size:14px;">Try changing the return date in the search bar above.</p>
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
                $bookUrl = '/flights/book.php?' . http_build_query([
                    'flight_id'  => $selectedOutId,
                    'return_id'  => $f['Flght_ID'],
                    'passengers' => $passengers,
                    'class'      => $class,
                ]);
            ?>
            <div class="flight-card flight-card-ret"
                 data-price="<?= $retPricePerPax ?>"
                 data-time="<?= strtotime($f['Flght_DepartDate']) ?>">
                <div class="row g-0 align-items-center">
                    <div class="col-md-2 text-center py-3 px-3" style="border-right:1px solid #f5f5f5;">
                        <div class="airline-badge" style="color:var(--trip-orange);"><?= htmlspecialchars($f['Airln_Code']) ?></div>
                        <div style="font-size:12px; color:var(--trip-muted);"><?= htmlspecialchars($f['Airln_Name']) ?></div>
                        <div style="font-size:11px; color:#aaa; margin-top:2px;"><?= htmlspecialchars($f['Flght_No']) ?></div>
                    </div>
                    <div class="col-md-6 py-3 px-4">
                        <div class="d-flex align-items-center gap-4">
                            <div class="text-center">
                                <div class="time-display"><?= date('H:i', strtotime($f['Flght_DepartDate'])) ?></div>
                                <div class="route-code"><?= htmlspecialchars($f['Flght_Depart']) ?></div>
                            </div>
                            <div class="flex-grow-1 text-center">
                                <div style="font-size:12px; color:var(--trip-muted); margin-bottom:6px;"><?= flightDuration($f['Flght_DepartDate'], $f['Flght_ArriveDate']) ?></div>
                                <div style="display:flex; align-items:center; gap:4px;">
                                    <div class="flight-line flight-line-orange"></div>
                                    <i class="bi bi-airplane-fill" style="color:var(--trip-orange); font-size:14px;"></i>
                                </div>
                                <div class="mt-1"><span class="badge badge-trip-orange px-2" style="font-size:10px;">Return · Non-stop</span></div>
                            </div>
                            <div class="text-center">
                                <div class="time-display"><?= date('H:i', strtotime($f['Flght_ArriveDate'])) ?></div>
                                <div class="route-code"><?= htmlspecialchars($f['Flght_Arrival']) ?></div>
                            </div>
                        </div>
                        <div class="mt-2 d-flex align-items-center gap-2 flex-wrap" style="font-size:12px; color:var(--trip-muted);">
                            <span><?= htmlspecialchars($toCity) ?> &rarr; <?= htmlspecialchars($fromCity) ?></span>
                            <span>&middot;</span>
                            <?php if ($f['Flght_SeatAvail'] <= 7): ?>
                                <span class="seats-warning"><i class="bi bi-exclamation-circle me-1"></i>Only <?= $f['Flght_SeatAvail'] ?> seats left!</span>
                            <?php else: ?>
                                <span class="seats-ok"><?= $f['Flght_SeatAvail'] ?> seats available</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 py-3 px-4 text-end" style="border-left:1px solid #f5f5f5;">
                        <div style="font-size:11px; color:var(--trip-muted); text-transform:uppercase; letter-spacing:.3px;">Return · <?= classLabel($class) ?></div>
                        <div class="price-text" style="font-size:24px; font-weight:800;">&#8369;<?= number_format($retPricePerPax, 2) ?></div>
                        <div style="font-size:12px; color:var(--trip-muted);">per person (return only)</div>
                        <div class="mt-1 fw-bold" style="font-size:13px; color:var(--trip-orange);">
                            Combined: &#8369;<?= number_format($combinedTotal, 2) ?>
                            <?php if ($passengers > 1): ?><span style="font-weight:400; color:var(--trip-muted);">(<?= $passengers ?> pax)</span><?php endif; ?>
                        </div>
                        <a href="<?= $bookUrl ?>" class="btn-trip-orange mt-3 px-4 py-2 d-inline-block" style="font-size:14px; border-radius:var(--radius-md);">
                            <i class="bi bi-check2-circle me-1"></i>Book Round Trip &rarr;
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php
    // ── ROUND TRIP STEP 1 ──
    elseif ($tripType === 'roundtrip'):
    ?>

        <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
            <span class="step-pill current"><i class="bi bi-airplane-fill"></i> Step 1: Choose Outbound Flight</span>
            <i class="bi bi-arrow-right text-muted"></i>
            <span class="step-pill inactive"><i class="bi bi-airplane"></i> Step 2: Choose Return Flight</span>
        </div>

        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div>
                <h5 class="result-heading mb-0">
                    <?= htmlspecialchars($fromCity) ?> &rarr; <?= htmlspecialchars($toCity) ?>
                    <span class="badge badge-trip-blue ms-2 px-2 py-1" style="font-size:12px;">Outbound</span>
                </h5>
                <p class="text-muted mb-0" style="font-size:13px;">
                    <?= date('D, d M Y', strtotime($date)) ?> &middot;
                    <?= $passengers ?> passenger<?= $passengers > 1 ? 's' : '' ?> &middot;
                    <?= classLabel($class) ?> &middot;
                    <span class="badge badge-trip-blue px-2 py-1" style="font-size:11px;">Round Trip</span>
                </p>
            </div>
            <span class="text-muted" style="font-size:13px;"><?= count($flights) ?> flight<?= count($flights) != 1 ? 's' : '' ?> found</span>
        </div>

        <?php if (empty($flights)): ?>
            <div class="empty-state">
                <i class="bi bi-airplane empty-icon"></i>
                <h6 class="fw-bold">No outbound flights found for this route and date.</h6>
                <p class="text-muted" style="font-size:14px;">Try a different date or adjust your search above.</p>
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
                $selectUrl = '/flights/search.php?' . $baseParams . '&out=' . $f['Flght_ID'];
            ?>
            <div class="flight-card flight-card-out"
                 data-price="<?= $pricePerPax ?>"
                 data-time="<?= strtotime($f['Flght_DepartDate']) ?>">
                <div class="row g-0 align-items-center">
                    <div class="col-md-2 text-center py-3 px-3" style="border-right:1px solid #f5f5f5;">
                        <div class="airline-badge"><?= htmlspecialchars($f['Airln_Code']) ?></div>
                        <div style="font-size:12px; color:var(--trip-muted);"><?= htmlspecialchars($f['Airln_Name']) ?></div>
                        <div style="font-size:11px; color:#aaa; margin-top:2px;"><?= htmlspecialchars($f['Flght_No']) ?></div>
                    </div>
                    <div class="col-md-6 py-3 px-4">
                        <div class="d-flex align-items-center gap-4">
                            <div class="text-center">
                                <div class="time-display"><?= date('H:i', strtotime($f['Flght_DepartDate'])) ?></div>
                                <div class="route-code"><?= htmlspecialchars($f['Flght_Depart']) ?></div>
                            </div>
                            <div class="flex-grow-1 text-center">
                                <div style="font-size:12px; color:var(--trip-muted); margin-bottom:6px;"><?= flightDuration($f['Flght_DepartDate'], $f['Flght_ArriveDate']) ?></div>
                                <div style="display:flex; align-items:center; gap:4px;">
                                    <div class="flight-line"></div>
                                    <i class="bi bi-airplane-fill" style="color:var(--trip-blue); font-size:14px;"></i>
                                </div>
                                <div class="mt-1"><span class="badge badge-trip-blue px-2" style="font-size:10px;">Non-stop</span></div>
                            </div>
                            <div class="text-center">
                                <div class="time-display"><?= date('H:i', strtotime($f['Flght_ArriveDate'])) ?></div>
                                <div class="route-code"><?= htmlspecialchars($f['Flght_Arrival']) ?></div>
                            </div>
                        </div>
                        <div class="mt-2 d-flex align-items-center gap-2 flex-wrap" style="font-size:12px; color:var(--trip-muted);">
                            <span><?= htmlspecialchars($fromCity) ?> &rarr; <?= htmlspecialchars($toCity) ?></span>
                            <span>&middot;</span>
                            <?php if ($f['Flght_SeatAvail'] <= 7): ?>
                                <span class="seats-warning"><i class="bi bi-exclamation-circle me-1"></i>Only <?= $f['Flght_SeatAvail'] ?> seats left!</span>
                            <?php else: ?>
                                <span class="seats-ok"><?= $f['Flght_SeatAvail'] ?> seats available</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 py-3 px-4 text-end" style="border-left:1px solid #f5f5f5;">
                        <div style="font-size:11px; color:var(--trip-muted); text-transform:uppercase; letter-spacing:.3px;"><?= classLabel($class) ?> · per person</div>
                        <div class="price-text" style="font-size:28px; font-weight:800;">&#8369;<?= number_format($pricePerPax, 2) ?></div>
                        <div style="font-size:12px; color:var(--trip-muted);">+ return fare in next step</div>
                        <a href="<?= $selectUrl ?>" class="btn-trip mt-3 px-4 py-2 d-inline-block" style="font-size:14px; border-radius:var(--radius-md);">
                            Select Outbound &rarr;
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php
    // ── ONE WAY ──
    else:
    ?>

        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div>
                <h5 class="result-heading mb-0"><?= htmlspecialchars($fromCity) ?> &rarr; <?= htmlspecialchars($toCity) ?></h5>
                <p class="text-muted mb-0" style="font-size:13px;">
                    <?= date('D, d M Y', strtotime($date)) ?> &middot;
                    <?= $passengers ?> passenger<?= $passengers > 1 ? 's' : '' ?> &middot;
                    <?= classLabel($class) ?>
                </p>
            </div>
            <span class="text-muted" style="font-size:13px;"><?= count($flights) ?> flight<?= count($flights) != 1 ? 's' : '' ?> found</span>
        </div>

        <?php if (empty($flights)): ?>
            <div class="empty-state">
                <i class="bi bi-airplane empty-icon"></i>
                <h6 class="fw-bold">No flights found for this route and date.</h6>
                <p class="text-muted" style="font-size:14px;">Try a different date or route using the search bar above.</p>
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
                $bookUrl = '/flights/book.php?' . http_build_query([
                    'flight_id'  => $f['Flght_ID'],
                    'passengers' => $passengers,
                    'class'      => $class,
                ]);
            ?>
            <div class="flight-card flight-card-out"
                 data-price="<?= $pricePerPax ?>"
                 data-time="<?= strtotime($f['Flght_DepartDate']) ?>">
                <div class="row g-0 align-items-center">
                    <div class="col-md-2 text-center py-3 px-3" style="border-right:1px solid #f5f5f5;">
                        <div class="airline-badge"><?= htmlspecialchars($f['Airln_Code']) ?></div>
                        <div style="font-size:12px; color:var(--trip-muted); font-weight:500;"><?= htmlspecialchars($f['Airln_Name']) ?></div>
                        <div style="font-size:11px; color:#aaa; margin-top:2px;"><?= htmlspecialchars($f['Flght_No']) ?></div>
                    </div>
                    <div class="col-md-6 py-3 px-4">
                        <div class="d-flex align-items-center gap-4">
                            <div class="text-center">
                                <div class="time-display"><?= date('H:i', strtotime($f['Flght_DepartDate'])) ?></div>
                                <div class="route-code"><?= htmlspecialchars($f['Flght_Depart']) ?></div>
                            </div>
                            <div class="flex-grow-1 text-center">
                                <div style="font-size:12px; color:var(--trip-muted); margin-bottom:6px;"><?= $duration ?></div>
                                <div style="display:flex; align-items:center; gap:4px;">
                                    <div class="flight-line"></div>
                                    <i class="bi bi-airplane-fill" style="color:var(--trip-blue); font-size:14px;"></i>
                                </div>
                                <div class="mt-1"><span class="badge badge-trip-blue px-2" style="font-size:10px;">Non-stop</span></div>
                            </div>
                            <div class="text-center">
                                <div class="time-display"><?= date('H:i', strtotime($f['Flght_ArriveDate'])) ?></div>
                                <div class="route-code"><?= htmlspecialchars($f['Flght_Arrival']) ?></div>
                            </div>
                        </div>
                        <div class="mt-2 d-flex align-items-center gap-2 flex-wrap" style="font-size:12px; color:var(--trip-muted);">
                            <span><?= htmlspecialchars($fromCity) ?> &rarr; <?= htmlspecialchars($toCity) ?></span>
                            <span>&middot;</span>
                            <?php if ($lowSeats): ?>
                                <span class="seats-warning"><i class="bi bi-exclamation-circle me-1"></i>Only <?= $f['Flght_SeatAvail'] ?> seats left!</span>
                            <?php else: ?>
                                <span class="seats-ok"><?= $f['Flght_SeatAvail'] ?> seats available</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 py-3 px-4 text-end" style="border-left:1px solid #f5f5f5;">
                        <div style="font-size:11px; color:var(--trip-muted); text-transform:uppercase; letter-spacing:.3px;">
                            <?= classLabel($class) ?> · per person
                        </div>
                        <div class="price-text" style="font-size:28px; font-weight:800;">
                            &#8369;<?= number_format($pricePerPax, 2) ?>
                        </div>
                        <?php if ($passengers > 1): ?>
                            <div style="font-size:12px; color:var(--trip-muted);">Total: &#8369;<?= number_format($totalPrice, 2) ?></div>
                        <?php endif; ?>
                        <a href="<?= $bookUrl ?>" class="btn-trip-orange mt-3 px-4 py-2 d-inline-block" style="font-size:14px; border-radius:var(--radius-md);">
                            Book Now &rarr;
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <?php else: ?>
        <div class="empty-state" style="margin-top:24px;">
            <i class="bi bi-airplane empty-icon"></i>
            <h5 class="fw-bold">Search for available flights above.</h5>
            <p class="text-muted" style="font-size:14px;">Enter your route and date to see available options.</p>
        </div>
    <?php endif; ?>

</div>

<script>
function sortFlights(mode, listId, btn) {
    btn.closest('.sort-bar').querySelectorAll('.sort-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const list  = document.getElementById(listId);
    if (!list) return;
    const cards = Array.from(list.querySelectorAll('.flight-card'));

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
