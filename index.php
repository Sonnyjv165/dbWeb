<?php
session_start();
require_once 'config/db.php';
require_once 'config/airports.php';
if (($_SESSION['role'] ?? '') === 'admin') {
    header('Location: /admin/dashboard.php');
    exit();
}
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$title    = 'trip.com — Book Flights';
include 'layout/layout.php';
?>

<style>
/* ── HERO ── */
.hero-section {
    position: relative;
    min-height: 100dvh;
    min-height: 580px;
    display: flex;
    align-items: center;
    margin-top: -88px;
    padding-top: 88px;
    overflow: hidden;
}
.hero-bg {
    position: absolute;
    inset: 0;
    background: url('https://images.unsplash.com/photo-1436491865332-7a61a109cc05?auto=format&fit=crop&w=1920&q=80') center / cover no-repeat;
    z-index: 0;
}
.hero-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        110deg,
        rgba(8, 16, 36, 0.82) 0%,
        rgba(8, 16, 36, 0.60) 50%,
        rgba(8, 16, 36, 0.22) 100%
    );
    z-index: 1;
}
.hero-content {
    position: relative;
    z-index: 2;
    padding: 80px 0 160px;
}
.hero-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255,255,255,0.10);
    border: 1px solid rgba(255,255,255,0.20);
    color: rgba(255,255,255,0.90);
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    padding: 6px 16px 6px 12px;
    margin-bottom: 28px;
    backdrop-filter: blur(10px);
}
.hero-eyebrow .dot {
    width: 7px; height: 7px;
    background: var(--trip-orange);
    border-radius: 50%;
    flex-shrink: 0;
    box-shadow: 0 0 8px rgba(240,96,32,0.7);
}
.hero-h1 {
    font-family: var(--font-serif);
    font-size: clamp(40px, 5.5vw, 66px);
    font-weight: 600;
    color: #fff;
    line-height: 1.06;
    letter-spacing: -0.025em;
    margin-bottom: 20px;
}
.hero-sub {
    font-size: 16px;
    color: rgba(255,255,255,0.68);
    max-width: 360px;
    line-height: 1.70;
    margin-bottom: 36px;
    font-weight: 400;
}

/* ── SEARCH WRAPPER ── */
.search-wrapper {
    position: relative;
    z-index: 10;
    margin-top: -80px;
    margin-bottom: 60px;
}
.search-card {
    background: #fff;
    border-radius: 20px;
    border: 1px solid rgba(0,0,0,0.07);
    box-shadow: 0 8px 48px rgba(0,0,0,0.14), 0 2px 8px rgba(0,0,0,0.06);
    padding: 28px 32px 32px;
}
@media (max-width: 575px) {
    .search-card { padding: 20px 18px 24px; border-radius: 16px; }
}

/* ── TRIP TYPE TABS ── */
.trip-tab {
    border-radius: 999px;
    font-size: 13px;
    font-weight: 600;
    font-family: var(--font-sans);
    padding: 7px 22px;
    cursor: pointer;
    transition: all 0.18s;
    border: 1.5px solid rgba(0,0,0,0.13);
    background: transparent;
    color: var(--trip-muted);
}
.trip-tab.active-tab {
    background: var(--trip-text);
    color: #fff;
    border-color: var(--trip-text);
    box-shadow: 0 2px 8px rgba(0,0,0,0.18);
}

/* ── TRUST STRIP ── */
.trust-strip {
    background: #fff;
    border-bottom: 1px solid rgba(0,0,0,0.06);
    padding: 20px 0;
}
.trust-item {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 4px 0;
}
.trust-icon {
    width: 40px; height: 40px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    font-size: 17px;
}

/* ── DESTINATIONS BENTO ── */
.dest-card-link { text-decoration: none; display: block; }
.dest-card-bg {
    border-radius: 16px;
    position: relative;
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.3s cubic-bezier(0.32,0.72,0,1), box-shadow 0.3s cubic-bezier(0.32,0.72,0,1);
}
.dest-card-bg:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 50px rgba(0,0,0,0.25);
}
.dest-card-bg::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.65) 0%, rgba(0,0,0,0.02) 60%);
    border-radius: 16px;
}
.dest-card-info {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    padding: 18px 20px;
    z-index: 2;
}
.dest-card-city {
    color: #fff;
    font-size: 20px;
    font-weight: 800;
    line-height: 1;
    letter-spacing: -0.3px;
}
.dest-card-country {
    color: rgba(255,255,255,0.70);
    font-size: 11px;
    margin: 4px 0 10px;
}
.dest-card-price {
    display: inline-block;
    background: rgba(255,255,255,0.16);
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 999px;
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.25);
}
.dest-tall  { height: 300px; }
.dest-short { height: 192px; }
@media (max-width: 767px) {
    .dest-tall, .dest-short { height: 200px; }
}

/* ── FLIP CARDS ── */
.flip-card { perspective: 1100px; height: 240px; cursor: default; }
.flip-card-inner {
    position: relative; width: 100%; height: 100%;
    transform-style: preserve-3d;
    transition: transform 0.65s cubic-bezier(0.4, 0.2, 0.2, 1);
}
.flip-card:hover .flip-card-inner { transform: rotateY(180deg); }
.flip-card-front, .flip-card-back {
    position: absolute; inset: 0;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    border-radius: 16px;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    padding: 2rem 1.75rem; text-align: center;
}
.flip-card-front {
    background: #fff;
    border: 1px solid rgba(0,0,0,0.07);
    box-shadow: 0 1px 6px rgba(0,0,0,0.05);
}
.flip-card-front .card-icon {
    width: 54px; height: 54px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; margin-bottom: 16px;
}
.flip-card-back { transform: rotateY(180deg); color: #fff; }
.flip-card-back h6 { font-size: 15px; font-weight: 700; margin-bottom: 10px; }
.flip-card-back p { font-size: 13px; opacity: .92; line-height: 1.65; margin: 0; }
.flip-card-back .back-stat {
    margin-top: 14px; font-size: 12px; opacity: .70;
    border-top: 1px solid rgba(255,255,255,0.20);
    padding-top: 10px; width: 100%;
}
.flip-card-hint {
    margin-top: 10px; font-size: 11px;
    color: var(--trip-muted); letter-spacing: 0.02em;
}

/* ── DISCLAIMER ── */
.disclaimer-box {
    background: #fff;
    border: 1px solid rgba(0,0,0,0.07);
    border-radius: 16px;
    padding: 24px 28px;
    display: flex;
    align-items: flex-start;
    gap: 18px;
}
.disclaimer-icon {
    width: 42px; height: 42px; border-radius: 12px;
    background: #EFF6FF;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: 18px; color: var(--trip-blue);
}

/* ── SCROLL FADE-IN ── */
.fade-in {
    opacity: 0;
    transform: translateY(20px);
    transition: opacity 0.6s cubic-bezier(0.16,1,0.3,1), transform 0.6s cubic-bezier(0.16,1,0.3,1);
}
.fade-in.visible { opacity: 1; transform: translateY(0); }
.fade-in:nth-child(2) { transition-delay: 0.08s; }
.fade-in:nth-child(3) { transition-delay: 0.16s; }
.fade-in:nth-child(4) { transition-delay: 0.08s; }
.fade-in:nth-child(5) { transition-delay: 0.16s; }
.fade-in:nth-child(6) { transition-delay: 0.24s; }
.fade-in-slow {
    opacity: 0;
    transform: translateY(16px);
    transition: opacity 0.7s cubic-bezier(0.16,1,0.3,1), transform 0.7s cubic-bezier(0.16,1,0.3,1);
}
.fade-in-slow.visible { opacity: 1; transform: translateY(0); }

/* ── SWAP BUTTON ── */
.swap-btn {
    width: 40px; height: 40px; border-radius: 50%;
    border: 1.5px solid rgba(0,0,0,0.12);
    background: #fff; color: var(--trip-text);
    font-size: 15px; cursor: pointer;
    transition: all 0.18s;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.swap-btn:hover {
    background: var(--trip-blue);
    color: #fff;
    border-color: var(--trip-blue);
    transform: rotate(180deg);
}
</style>

<!-- ====== HERO ====== -->
<div class="hero-section">
    <div class="hero-bg"></div>
    <div class="hero-overlay"></div>
    <div class="container hero-content">
        <div style="max-width: 500px;">
            <div class="hero-eyebrow">
                <span class="dot"></span>
                Flight Booking Platform
            </div>
            <h1 class="hero-h1">
                Fly anywhere,<br>anytime.
            </h1>
            <p class="hero-sub">
                Search routes across Asia, the Middle East, and beyond. Transparent fares, instant confirmation.
            </p>
            <?php if (!isset($_SESSION['user_id'])): ?>
            <div class="d-flex gap-3 flex-wrap">
                <a href="/auth/register.php"
                   style="background:#fff; color:#111; border-radius:8px; padding:12px 26px; font-size:14px; font-weight:700; text-decoration:none; transition:opacity 0.15s; display:inline-block; box-shadow:0 2px 12px rgba(0,0,0,0.18);">
                    Create Free Account
                </a>
                <a href="/auth/login.php"
                   style="background:rgba(255,255,255,0.12); color:#fff; border:1.5px solid rgba(255,255,255,0.30); border-radius:8px; padding:11px 24px; font-size:14px; font-weight:600; text-decoration:none; backdrop-filter:blur(8px); display:inline-block; transition:background 0.15s;">
                    Sign In
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ====== SEARCH CARD ====== -->
<div class="search-wrapper">
    <div class="container">
        <div class="search-card" style="max-width:900px; margin:0 auto;">

            <div class="d-flex align-items-center gap-3 mb-4">
                <button class="trip-tab active-tab" id="tabOneWay" onclick="setTripType('oneway')">One Way</button>
                <button class="trip-tab" id="tabRoundTrip" onclick="setTripType('roundtrip')">Round Trip</button>
            </div>

            <form method="GET" action="/flights/search.php">
                <input type="hidden" name="trip_type" id="tripTypeInput" value="oneway">

                <div class="row g-3">

                    <div class="col-md-5">
                        <label class="form-label eyebrow-label">From</label>
                        <select name="from" class="form-select" required>
                            <option value="" disabled selected>Select departure city</option>
                            <?php foreach ($AIRPORTS as $code => $ap): ?>
                                <option value="<?= $code ?>">
                                    <?= htmlspecialchars($ap['city']) ?> (<?= $code ?>) — <?= htmlspecialchars($ap['country']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2 d-flex align-items-end justify-content-center pb-1">
                        <button type="button" class="swap-btn" onclick="swapAirports()" title="Swap airports">
                            <i class="bi bi-arrow-left-right"></i>
                        </button>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label eyebrow-label">To</label>
                        <select name="to" id="toSelect" class="form-select" required>
                            <option value="" disabled selected>Select destination city</option>
                            <?php foreach ($AIRPORTS as $code => $ap): ?>
                                <option value="<?= $code ?>">
                                    <?= htmlspecialchars($ap['city']) ?> (<?= $code ?>) — <?= htmlspecialchars($ap['country']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label eyebrow-label">Departure</label>
                        <input type="date" name="date" class="form-control" required
                               min="<?= date('Y-m-d') ?>"
                               value="<?= $tomorrow ?>">
                    </div>

                    <div class="col-md-3" id="returnDateCol" style="display:none;">
                        <label class="form-label eyebrow-label">Return</label>
                        <input type="date" name="return_date" id="returnDate" class="form-control"
                               min="<?= date('Y-m-d', strtotime('+2 day')) ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label eyebrow-label">Passengers</label>
                        <select name="passengers" class="form-select">
                            <?php for ($i = 1; $i <= 9; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?> Passenger<?= $i > 1 ? 's' : '' ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label eyebrow-label">Class</label>
                        <select name="class" class="form-select">
                            <option value="economy">Economy</option>
                            <option value="business">Business</option>
                            <option value="first">First Class</option>
                        </select>
                    </div>

                    <div class="col-12 mt-2 d-flex align-items-center gap-3 flex-wrap">
                        <button type="submit" class="btn-trip-orange px-5">
                            <i class="bi bi-search me-2"></i>Search Flights
                        </button>
                        <span style="font-size:12px; color:var(--trip-muted);">
                            <i class="bi bi-shield-check me-1 text-success"></i>No booking fees
                        </span>
                    </div>

                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====== TRUST STRIP ====== -->
<div class="trust-strip">
    <div class="container">
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <div class="trust-item">
                    <div class="trust-icon" style="background:#F0FDF4;"><i class="bi bi-shield-check" style="color:#15803D;"></i></div>
                    <div>
                        <div style="font-size:13px; font-weight:700; line-height:1.3;">Best Price Guarantee</div>
                        <div style="font-size:11px; color:var(--trip-muted);">No hidden fees, ever</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="trust-item">
                    <div class="trust-icon" style="background:#FFEDD5;"><i class="bi bi-lightning-charge-fill" style="color:var(--trip-orange);"></i></div>
                    <div>
                        <div style="font-size:13px; font-weight:700; line-height:1.3;">Instant Confirmation</div>
                        <div style="font-size:11px; color:var(--trip-muted);">Booking ref in seconds</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="trust-item">
                    <div class="trust-icon" style="background:#EFF6FF;"><i class="bi bi-arrow-counterclockwise" style="color:var(--trip-blue);"></i></div>
                    <div>
                        <div style="font-size:13px; font-weight:700; line-height:1.3;">Free Cancellation</div>
                        <div style="font-size:11px; color:var(--trip-muted);">Flexible booking policy</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="trust-item">
                    <div class="trust-icon" style="background:#F5F3FF;"><i class="bi bi-headset" style="color:#7C3AED;"></i></div>
                    <div>
                        <div style="font-size:13px; font-weight:700; line-height:1.3;">24/7 Support</div>
                        <div style="font-size:11px; color:var(--trip-muted);">Always here to help</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ====== POPULAR DESTINATIONS ====== -->
<div class="container py-5">

    <div class="fade-in-slow mb-4">
        <span class="eyebrow-label">Explore the world</span>
        <h2 class="section-serif" style="font-size: clamp(26px, 3vw, 36px); margin-bottom:6px;">
            Popular Destinations
        </h2>
        <p style="color:var(--trip-muted); font-size:14px; margin:0;">Click any destination to search available flights.</p>
    </div>

    <!-- Bento grid -->
    <div class="row g-3" id="destGrid">

        <!-- Cebu — large left -->
        <div class="col-md-8 fade-in">
            <a href="/flights/search.php?from=MNL&to=CEB&date=<?= $tomorrow ?>&passengers=1&class=economy&trip_type=oneway" class="dest-card-link">
                <div class="dest-card-bg dest-tall" style="background: url('/assets/destinations/Cebu.jpg') center/cover no-repeat; background-color:#1a6ec7;">
                    <div class="dest-card-info">
                        <div class="dest-card-city">Cebu</div>
                        <div class="dest-card-country">Philippines &middot; 1h 20m</div>
                        <span class="dest-card-price">from &#8369;2,100</span>
                    </div>
                </div>
            </a>
        </div>

        <!-- Singapore -->
        <div class="col-md-4 fade-in">
            <a href="/flights/search.php?from=MNL&to=SIN&date=<?= $tomorrow ?>&passengers=1&class=economy&trip_type=oneway" class="dest-card-link">
                <div class="dest-card-bg dest-short" style="background: url('/assets/destinations/singapore.webp') center/cover no-repeat; background-color:#00897B;">
                    <div class="dest-card-info">
                        <div class="dest-card-city">Singapore</div>
                        <div class="dest-card-country">Singapore &middot; 3h 30m</div>
                        <span class="dest-card-price">from &#8369;8,500</span>
                    </div>
                </div>
            </a>
        </div>

        <!-- Hong Kong -->
        <div class="col-md-4 fade-in">
            <a href="/flights/search.php?from=MNL&to=HKG&date=<?= $tomorrow ?>&passengers=1&class=economy&trip_type=oneway" class="dest-card-link">
                <div class="dest-card-bg dest-short" style="background: url('/assets/destinations/Hongkong.jpg') center/cover no-repeat; background-color:#7B2FBE;">
                    <div class="dest-card-info">
                        <div class="dest-card-city">Hong Kong</div>
                        <div class="dest-card-country">HK SAR &middot; 2h 50m</div>
                        <span class="dest-card-price">from &#8369;9,500</span>
                    </div>
                </div>
            </a>
        </div>

        <!-- Bangkok -->
        <div class="col-md-4 fade-in">
            <a href="/flights/search.php?from=MNL&to=BKK&date=<?= $tomorrow ?>&passengers=1&class=economy&trip_type=oneway" class="dest-card-link">
                <div class="dest-card-bg dest-short" style="background: url('/assets/destinations/bangkok.jpg') center/cover no-repeat; background-color:#00796B;">
                    <div class="dest-card-info">
                        <div class="dest-card-city">Bangkok</div>
                        <div class="dest-card-country">Thailand &middot; 3h 10m</div>
                        <span class="dest-card-price">from &#8369;11,000</span>
                    </div>
                </div>
            </a>
        </div>

        <!-- Dubai — large right -->
        <div class="col-md-8 fade-in">
            <a href="/flights/search.php?from=MNL&to=DXB&date=<?= $tomorrow ?>&passengers=1&class=economy&trip_type=oneway" class="dest-card-link">
                <div class="dest-card-bg dest-tall" style="background: url('/assets/destinations/dubai.jpg') center/cover no-repeat; background-color:#C8820A;">
                    <div class="dest-card-info">
                        <div class="dest-card-city">Dubai</div>
                        <div class="dest-card-country">UAE &middot; 9h 30m</div>
                        <span class="dest-card-price">from &#8369;18,000</span>
                    </div>
                </div>
            </a>
        </div>

        <!-- Tokyo -->
        <div class="col-md-4 fade-in">
            <a href="/flights/search.php?from=MNL&to=NRT&date=<?= $tomorrow ?>&passengers=1&class=economy&trip_type=oneway" class="dest-card-link">
                <div class="dest-card-bg dest-short" style="background: url('/assets/destinations/Tokyo.jpg') center/cover no-repeat; background-color:#C0392B;">
                    <div class="dest-card-info">
                        <div class="dest-card-city">Tokyo</div>
                        <div class="dest-card-country">Japan &middot; 4h 10m</div>
                        <span class="dest-card-price">from &#8369;22,000</span>
                    </div>
                </div>
            </a>
        </div>

    </div>
</div>

<!-- ====== WHY TRIP.COM ====== -->
<div style="background:#F1F5F9; padding:72px 0;">
<div class="container">

    <div class="text-center mb-5 fade-in-slow">
        <span class="eyebrow-label">Why choose us</span>
        <h2 class="section-serif" style="font-size: clamp(24px, 3vw, 34px); margin-bottom:8px;">
            The trip.com advantage
        </h2>
        <p style="color:var(--trip-muted); font-size:14px; max-width:380px; margin:0 auto;">
            Hover each card to learn more about what makes booking with us different.
        </p>
    </div>

    <div class="row g-4 mb-5">

        <!-- Card 1 — Routes -->
        <div class="col-md-4 fade-in">
            <div class="flip-card">
                <div class="flip-card-inner">
                    <div class="flip-card-front">
                        <div class="card-icon" style="background:#EFF6FF;">
                            <i class="bi bi-airplane" style="color:var(--trip-blue); font-size:22px;"></i>
                        </div>
                        <h6 class="fw-bold mb-2" style="font-size:16px;">Hundreds of Routes</h6>
                        <p style="color:var(--trip-muted); font-size:13px; margin:0;">Asia &middot; Middle East &middot; Europe &middot; and beyond</p>
                        <div class="flip-card-hint">Hover to learn more</div>
                    </div>
                    <div class="flip-card-back" style="background:linear-gradient(135deg,#0A1628,#1a52aa);">
                        <i class="bi bi-globe-asia-australia" style="font-size:28px; margin-bottom:12px; opacity:.9;"></i>
                        <h6>Hundreds of Routes</h6>
                        <p>From short domestic hops between Manila and Cebu to long-haul flights to Dubai, Tokyo, and London — we connect you to the world.</p>
                        <div class="back-stat">12 destinations &nbsp;&middot;&nbsp; 5 airlines &nbsp;&middot;&nbsp; 300+ flights</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2 — Price -->
        <div class="col-md-4 fade-in">
            <div class="flip-card">
                <div class="flip-card-inner">
                    <div class="flip-card-front">
                        <div class="card-icon" style="background:#FFEDD5;">
                            <i class="bi bi-tag" style="color:var(--trip-orange); font-size:22px;"></i>
                        </div>
                        <h6 class="fw-bold mb-2" style="font-size:16px;">Best Price Guarantee</h6>
                        <p style="color:var(--trip-muted); font-size:13px; margin:0;">Economy from &#8369;2,100 &middot; Business &middot; First Class</p>
                        <div class="flip-card-hint">Hover to learn more</div>
                    </div>
                    <div class="flip-card-back" style="background:linear-gradient(135deg,#92280a,var(--trip-orange));">
                        <i class="bi bi-currency-exchange" style="font-size:28px; margin-bottom:12px; opacity:.9;"></i>
                        <h6>Best Price Guarantee</h6>
                        <p>Transparent fares with zero hidden fees. Use promo codes like <strong>TRIP10</strong> or <strong>SUMMER500</strong> to save even more on your next booking.</p>
                        <div class="back-stat">Economy &nbsp;&middot;&nbsp; Business &nbsp;&middot;&nbsp; First Class available</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 3 — Confirmation -->
        <div class="col-md-4 fade-in">
            <div class="flip-card">
                <div class="flip-card-inner">
                    <div class="flip-card-front">
                        <div class="card-icon" style="background:#F0FDF4;">
                            <i class="bi bi-check2-circle" style="color:#15803D; font-size:22px;"></i>
                        </div>
                        <h6 class="fw-bold mb-2" style="font-size:16px;">Instant Confirmation</h6>
                        <p style="color:var(--trip-muted); font-size:13px; margin:0;">Booking reference &middot; Dashboard &middot; Print ticket</p>
                        <div class="flip-card-hint">Hover to learn more</div>
                    </div>
                    <div class="flip-card-back" style="background:linear-gradient(135deg,#0a4023,#16a34a);">
                        <i class="bi bi-receipt" style="font-size:28px; margin-bottom:12px; opacity:.9;"></i>
                        <h6>Instant Confirmation</h6>
                        <p>Receive your unique booking reference the moment you confirm. View, manage, and cancel your trips anytime from your personal dashboard.</p>
                        <div class="back-stat">Printable ticket &nbsp;&middot;&nbsp; Free cancellation</div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Disclaimer -->
    <div class="disclaimer-box fade-in-slow" id="disclaimerBox">
        <div class="disclaimer-icon">
            <i class="bi bi-info-circle"></i>
        </div>
        <div>
            <span style="display:inline-block; background:#FFEDD5; color:var(--trip-orange); border:1px solid rgba(240,96,32,0.18); border-radius:999px; font-size:11px; font-weight:700; padding:3px 12px; margin-bottom:8px; letter-spacing:0.05em; text-transform:uppercase;">Academic Project</span>
            <h6 style="font-size:14px; font-weight:700; color:var(--trip-navy); margin-bottom:6px;">This website is for demonstration purposes only</h6>
            <p style="font-size:13px; color:var(--trip-muted); margin:0; line-height:1.70;">
                <strong style="color:var(--trip-text);">trip.com Flight Booking System</strong> is an academic project built to simulate a real-world flight booking platform.
                No actual flights are booked, no payments are processed, and no real airline reservations are made.
                All flight data, prices, and booking references shown on this site are entirely fictional and exist solely for educational demonstration.
            </p>
        </div>
    </div>

</div>
</div>

<script>
function setTripType(type) {
    document.getElementById('tripTypeInput').value = type;
    const returnCol = document.getElementById('returnDateCol');
    const btnOne    = document.getElementById('tabOneWay');
    const btnRound  = document.getElementById('tabRoundTrip');

    if (type === 'roundtrip') {
        returnCol.style.display = 'block';
        document.getElementById('returnDate').required = true;
        btnRound.classList.add('active-tab');
        btnOne.classList.remove('active-tab');
    } else {
        returnCol.style.display = 'none';
        document.getElementById('returnDate').required = false;
        btnOne.classList.add('active-tab');
        btnRound.classList.remove('active-tab');
    }
}

function swapAirports() {
    const from = document.querySelector('select[name="from"]');
    const to   = document.getElementById('toSelect');
    if (!from || !to) return;
    const tmp  = from.value;
    from.value = to.value;
    to.value   = tmp;
}

(function () {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.10 });

    document.querySelectorAll('.fade-in, .fade-in-slow, #disclaimerBox')
        .forEach(el => observer.observe(el));
})();
</script>

<?php include 'layout/footer.php'; ?>
