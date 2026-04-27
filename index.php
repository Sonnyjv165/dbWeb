<?php
session_start();
require_once 'config/db.php';
require_once 'config/airports.php';
$title = 'trip.com — Book Cheap Flights';
include 'layout/layout.php';
?>

<!-- ====== HERO ====== -->
<div style="background: linear-gradient(135deg, #003580 0%, #0066cc 60%, #0086FF 100%); padding: 60px 0 80px;">
    <div class="container">

        <div class="text-center text-white mb-4">
            <h1 class="fw-bold" style="font-size: clamp(28px, 4vw, 48px); letter-spacing: -0.5px;">
                Find Your Perfect Flight
            </h1>
            <p style="font-size:17px; opacity:0.85; margin:0;">
                Compare prices and book flights to hundreds of destinations
            </p>
        </div>

        <!-- Search Card -->
        <div class="trip-card p-4 mx-auto" style="max-width:860px;">

            <!-- Trip Type Tabs -->
            <div class="d-flex gap-2 mb-4">
                <button class="btn btn-sm px-4 py-2 fw-semibold trip-tab active-tab" id="tabOneWay"
                        onclick="setTripType('oneway')"
                        style="border-radius:20px; border:1.5px solid #0086FF; background:#0086FF; color:#fff;">
                    One Way
                </button>
                <button class="btn btn-sm px-4 py-2 fw-semibold trip-tab" id="tabRoundTrip"
                        onclick="setTripType('roundtrip')"
                        style="border-radius:20px; border:1.5px solid #ccc; background:#fff; color:#555;">
                    Round Trip
                </button>
            </div>

            <form method="GET" action="/dbweb/flights/search.php">
                <input type="hidden" name="trip_type" id="tripTypeInput" value="oneway">

                <div class="row g-3">

                    <!-- FROM -->
                    <div class="col-md-5">
                        <label class="form-label text-muted fw-semibold" style="font-size:12px; text-transform:uppercase; letter-spacing:0.6px;">From</label>
                        <select name="from" class="form-select" required>
                            <option value="" disabled selected>Select departure city</option>
                            <?php foreach ($AIRPORTS as $code => $ap): ?>
                                <option value="<?= $code ?>">
                                    <?= htmlspecialchars($ap['city']) ?> (<?= $code ?>) — <?= htmlspecialchars($ap['country']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- SWAP BUTTON -->
                    <div class="col-md-2 d-flex align-items-end justify-content-center pb-1">
                        <button type="button" onclick="swapAirports()"
                                style="width:40px;height:40px;border-radius:50%;border:2px solid #0086FF;background:#fff;color:#0086FF;font-size:18px;cursor:pointer;"
                                title="Swap airports">⇄</button>
                    </div>

                    <!-- TO -->
                    <div class="col-md-5">
                        <label class="form-label text-muted fw-semibold" style="font-size:12px; text-transform:uppercase; letter-spacing:0.6px;">To</label>
                        <select name="to" id="toSelect" class="form-select" required>
                            <option value="" disabled selected>Select destination city</option>
                            <?php foreach ($AIRPORTS as $code => $ap): ?>
                                <option value="<?= $code ?>">
                                    <?= htmlspecialchars($ap['city']) ?> (<?= $code ?>) — <?= htmlspecialchars($ap['country']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- DEPARTURE DATE -->
                    <div class="col-md-3">
                        <label class="form-label text-muted fw-semibold" style="font-size:12px; text-transform:uppercase; letter-spacing:0.6px;">Departure</label>
                        <input type="date" name="date" class="form-control" required
                               min="<?= date('Y-m-d') ?>"
                               value="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                    </div>

                    <!-- RETURN DATE -->
                    <div class="col-md-3" id="returnDateCol" style="display:none;">
                        <label class="form-label text-muted fw-semibold" style="font-size:12px; text-transform:uppercase; letter-spacing:0.6px;">Return</label>
                        <input type="date" name="return_date" id="returnDate" class="form-control"
                               min="<?= date('Y-m-d', strtotime('+2 day')) ?>">
                    </div>

                    <!-- PASSENGERS -->
                    <div class="col-md-3">
                        <label class="form-label text-muted fw-semibold" style="font-size:12px; text-transform:uppercase; letter-spacing:0.6px;">Passengers</label>
                        <select name="passengers" class="form-select">
                            <?php for ($i = 1; $i <= 9; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?> Passenger<?= $i > 1 ? 's' : '' ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <!-- CLASS -->
                    <div class="col-md-3">
                        <label class="form-label text-muted fw-semibold" style="font-size:12px; text-transform:uppercase; letter-spacing:0.6px;">Class</label>
                        <select name="class" class="form-select">
                            <option value="economy">Economy</option>
                            <option value="business">Business</option>
                            <option value="first">First Class</option>
                        </select>
                    </div>

                    <!-- SEARCH BUTTON -->
                    <div class="col-12 text-center mt-2">
                        <button type="submit" class="btn-trip-orange px-5">
                            <i class="bi bi-search me-2"></i>Search Flights
                        </button>
                    </div>

                </div>
            </form>
        </div>

    </div>
</div>

<!-- ====== WHY TRIP.COM — Flip Cards ====== -->
<style>
/* ── Scroll fade-up ── */
.fade-up {
    opacity: 0;
    transform: translateY(36px);
    transition: opacity 0.65s ease, transform 0.65s ease;
}
.fade-up.visible {
    opacity: 1;
    transform: translateY(0);
}
.fade-up:nth-child(2) { transition-delay: 0.12s; }
.fade-up:nth-child(3) { transition-delay: 0.24s; }

/* ── Flip card ── */
.flip-card {
    perspective: 1100px;
    height: 240px;
    cursor: default;
}
.flip-card-inner {
    position: relative;
    width: 100%;
    height: 100%;
    transform-style: preserve-3d;
    transition: transform 0.65s cubic-bezier(0.4, 0.2, 0.2, 1);
}
.flip-card:hover .flip-card-inner {
    transform: rotateY(180deg);
}
.flip-card-front,
.flip-card-back {
    position: absolute;
    inset: 0;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    border-radius: 16px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem 1.75rem;
    text-align: center;
}
.flip-card-front {
    background: #ffffff;
    box-shadow: 0 2px 20px rgba(0,0,0,0.07);
    border: 1px solid #f0f0f0;
}
.flip-card-back {
    transform: rotateY(180deg);
    color: #fff;
}
.flip-card-back .back-icon {
    font-size: 28px;
    margin-bottom: 12px;
    opacity: 0.9;
}
.flip-card-back h6 {
    font-size: 15px;
    font-weight: 700;
    margin-bottom: 10px;
}
.flip-card-back p {
    font-size: 13px;
    opacity: 0.92;
    line-height: 1.6;
    margin: 0;
}
.flip-card-back .back-stat {
    margin-top: 14px;
    font-size: 12px;
    opacity: 0.75;
    border-top: 1px solid rgba(255,255,255,0.3);
    padding-top: 10px;
    width: 100%;
}

/* ── Section heading animate ── */
.section-heading {
    opacity: 0;
    transform: translateY(20px);
    transition: opacity 0.5s ease, transform 0.5s ease;
}
.section-heading.visible {
    opacity: 1;
    transform: translateY(0);
}

/* ── Disclaimer ── */
.disclaimer-box {
    background: #fff;
    border: 1.5px solid #e0e7ef;
    border-radius: 16px;
    padding: 28px 32px;
    display: flex;
    align-items: flex-start;
    gap: 18px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    opacity: 0;
    transform: translateY(24px);
    transition: opacity 0.6s ease 0.1s, transform 0.6s ease 0.1s;
}
.disclaimer-box.visible {
    opacity: 1;
    transform: translateY(0);
}
.disclaimer-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, #e8f0fe, #c7d8fc);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 20px;
    color: #0086FF;
}
.disclaimer-text h6 {
    font-size: 14px;
    font-weight: 700;
    color: #003580;
    margin-bottom: 6px;
}
.disclaimer-text p {
    font-size: 13px;
    color: #6B6B6B;
    margin: 0;
    line-height: 1.65;
}
.disclaimer-text .badge-demo {
    display: inline-block;
    background: #fff3e0;
    color: #FF7020;
    border: 1px solid #ffd8b0;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 10px;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
</style>

<div class="container py-5" id="aboutSection">

    <div class="text-center mb-5 section-heading">
        <p class="text-muted text-uppercase fw-semibold mb-1" style="font-size:12px; letter-spacing:1.5px;">Why choose us</p>
        <h4 class="fw-bold" style="color:#1A1A1A;">The trip.com advantage</h4>
        <p class="text-muted mx-auto" style="font-size:14px; max-width:420px;">Hover over each card to discover what makes booking with us different.</p>
    </div>

    <div class="row g-4 mb-5">

        <!-- Card 1 — Routes -->
        <div class="col-md-4 fade-up">
            <div class="flip-card">
                <div class="flip-card-inner">
                    <!-- Front -->
                    <div class="flip-card-front">
                        <div style="font-size:44px; color:#0086FF; line-height:1;" class="mb-3">✈</div>
                        <h6 class="fw-bold mb-2" style="font-size:16px;">Hundreds of Routes</h6>
                        <p class="text-muted mb-0" style="font-size:13px;">Asia · Middle East · Europe · and beyond</p>
                        <div class="mt-3" style="font-size:11px; color:#0086FF; opacity:.7;">Hover to learn more ›</div>
                    </div>
                    <!-- Back -->
                    <div class="flip-card-back" style="background: linear-gradient(135deg, #003580, #0086FF);">
                        <div class="back-icon">🌏</div>
                        <h6>Hundreds of Routes</h6>
                        <p>From short domestic hops between Manila and Cebu to long-haul flights to Dubai, Tokyo, and London — we connect you to the world.</p>
                        <div class="back-stat">12 destinations &nbsp;·&nbsp; 5 airlines &nbsp;·&nbsp; 40+ flights</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2 — Price -->
        <div class="col-md-4 fade-up">
            <div class="flip-card">
                <div class="flip-card-inner">
                    <!-- Front -->
                    <div class="flip-card-front">
                        <div style="font-size:44px; color:#FF7020; line-height:1;" class="mb-3">₱</div>
                        <h6 class="fw-bold mb-2" style="font-size:16px;">Best Price Guarantee</h6>
                        <p class="text-muted mb-0" style="font-size:13px;">Economy from ₱2,100 · Business · First Class</p>
                        <div class="mt-3" style="font-size:11px; color:#FF7020; opacity:.7;">Hover to learn more ›</div>
                    </div>
                    <!-- Back -->
                    <div class="flip-card-back" style="background: linear-gradient(135deg, #c0440a, #FF7020);">
                        <div class="back-icon">💰</div>
                        <h6>Best Price Guarantee</h6>
                        <p>Transparent fares with zero hidden fees. Use promo codes like <strong>TRIP10</strong> or <strong>SUMMER500</strong> to save even more on your next booking.</p>
                        <div class="back-stat">Economy · Business · First Class available</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 3 — Confirmation -->
        <div class="col-md-4 fade-up">
            <div class="flip-card">
                <div class="flip-card-inner">
                    <!-- Front -->
                    <div class="flip-card-front">
                        <div style="font-size:44px; color:#1a9e5c; line-height:1;" class="mb-3">✓</div>
                        <h6 class="fw-bold mb-2" style="font-size:16px;">Instant Confirmation</h6>
                        <p class="text-muted mb-0" style="font-size:13px;">Booking reference · Dashboard · Print ticket</p>
                        <div class="mt-3" style="font-size:11px; color:#1a9e5c; opacity:.7;">Hover to learn more ›</div>
                    </div>
                    <!-- Back -->
                    <div class="flip-card-back" style="background: linear-gradient(135deg, #0d6e3f, #1a9e5c);">
                        <div class="back-icon">📋</div>
                        <h6>Instant Confirmation</h6>
                        <p>Receive your unique booking reference the moment you confirm. View, manage, and cancel your trips anytime from your personal dashboard.</p>
                        <div class="back-stat">Printable ticket &nbsp;·&nbsp; Free cancellation</div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Disclaimer -->
    <div class="disclaimer-box" id="disclaimerBox">
        <div class="disclaimer-icon">ℹ</div>
        <div class="disclaimer-text">
            <span class="badge-demo">Academic Project</span>
            <h6>This website is for demonstration purposes only</h6>
            <p>
                <strong>trip.com Flight Booking System</strong> is an academic project built to simulate a real-world flight booking platform.
                No actual flights are booked, no payments are processed, and no real airline reservations are made.
                All flight data, prices, and booking references shown on this site are entirely fictional and exist solely for educational demonstration.
                Do not use this platform for actual travel planning.
            </p>
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
        btnRound.style.cssText = 'border-radius:20px;border:1.5px solid #0086FF;background:#0086FF;color:#fff;';
        btnOne.style.cssText   = 'border-radius:20px;border:1.5px solid #ccc;background:#fff;color:#555;';
    } else {
        returnCol.style.display = 'none';
        document.getElementById('returnDate').required = false;
        btnOne.style.cssText   = 'border-radius:20px;border:1.5px solid #0086FF;background:#0086FF;color:#fff;';
        btnRound.style.cssText = 'border-radius:20px;border:1.5px solid #ccc;background:#fff;color:#555;';
    }
}

function swapAirports() {
    const from = document.querySelector('select[name="from"]');
    const to   = document.getElementById('toSelect');
    const tmp  = from.value;
    from.value = to.value;
    to.value   = tmp;
}

// Scroll-triggered fade-up for cards and section heading
(function () {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });

    document.querySelectorAll('.fade-up, .section-heading, #disclaimerBox')
        .forEach(el => observer.observe(el));
})();
</script>

<?php include 'layout/footer.php'; ?>
