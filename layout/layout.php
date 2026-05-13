<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'trip.com') ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,600;1,6..72,400;1,6..72,600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --trip-blue:       #0077EE;
            --trip-blue-dark:  #005CC8;
            --trip-orange:     #F06020;
            --trip-orange-drk: #D04E12;
            --trip-navy:       #0A1628;
            --trip-bg:         #F8F9FB;
            --trip-text:       #111827;
            --trip-muted:      #6B7280;
            --trip-border:     rgba(0, 0, 0, 0.07);
            --trip-white:      #FFFFFF;
            --trip-success:    #16A34A;
            --trip-danger:     #DC2626;

            --font-sans:  'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            --font-serif: 'Newsreader', 'Georgia', serif;

            --radius-sm:  6px;
            --radius-md:  10px;
            --radius-lg:  14px;
            --radius-xl:  18px;

            --shadow-sm:  0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md:  0 4px 16px rgba(0,0,0,0.08), 0 1px 4px rgba(0,0,0,0.04);
            --shadow-lg:  0 10px 40px rgba(0,0,0,0.12), 0 2px 8px rgba(0,0,0,0.06);
            --shadow-xl:  0 20px 60px rgba(0,0,0,0.16), 0 4px 16px rgba(0,0,0,0.08);
        }

        * { box-sizing: border-box; }

        body {
            font-family: var(--font-sans);
            background: var(--trip-bg);
            color: var(--trip-text);
            padding-top: 88px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ── FLOATING NAVBAR ── */
        .navbar-trip {
            position: fixed;
            top: 14px;
            left: 50%;
            transform: translateX(-50%);
            width: calc(100% - 28px);
            max-width: 1180px;
            z-index: 1040;
            background: rgba(248, 249, 251, 0.90);
            backdrop-filter: blur(24px) saturate(180%);
            -webkit-backdrop-filter: blur(24px) saturate(180%);
            border-radius: var(--radius-xl);
            min-height: 58px;
            padding: 0 20px;
            border: 1px solid rgba(255,255,255,0.70);
            box-shadow: var(--shadow-md), 0 0 0 1px rgba(0,0,0,0.04);
            transition: box-shadow 0.3s ease, background 0.3s ease;
        }

        .navbar-trip.nav-scrolled {
            background: rgba(255, 255, 255, 0.97);
            box-shadow: var(--shadow-lg), 0 0 0 1px rgba(0,0,0,0.05);
        }

        /* ── BRAND ── */
        .navbar-brand-trip {
            font-size: 20px;
            font-weight: 800;
            color: var(--trip-text);
            text-decoration: none;
            letter-spacing: -0.3px;
            display: inline-flex;
            align-items: baseline;
            gap: 1px;
            line-height: 1;
            flex-shrink: 0;
        }
        .navbar-brand-trip .brand-trip {
            font-family: var(--font-serif);
            font-style: italic;
            font-weight: 600;
            color: var(--trip-text);
        }
        .navbar-brand-trip .brand-dot { color: var(--trip-orange); }
        .navbar-brand-trip .brand-com { color: var(--trip-text); font-size: 18px; font-weight: 700; }

        /* ── NAV LINKS ── */
        .navbar-trip .nav-link {
            color: var(--trip-text);
            font-size: 14px;
            font-weight: 500;
            padding: 7px 14px !important;
            border-radius: var(--radius-md);
            transition: background 0.15s, color 0.15s;
            white-space: nowrap;
        }
        .navbar-trip .nav-link:hover { background: rgba(0,119,238,0.07); color: var(--trip-blue); }

        /* ── MOBILE TOGGLER ── */
        .trip-toggler {
            border: 1.5px solid var(--trip-border);
            border-radius: var(--radius-md);
            padding: 6px 10px;
            background: rgba(255,255,255,0.6);
            color: var(--trip-text);
            font-size: 18px;
            line-height: 1;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s;
        }
        .trip-toggler:hover { background: rgba(0,0,0,0.05); }

        /* Mobile expanded nav */
        @media (max-width: 991.98px) {
            .navbar-trip .navbar-collapse {
                padding: 12px 0 8px;
                border-top: 1px solid var(--trip-border);
                margin-top: 8px;
            }
            .navbar-trip .navbar-collapse .d-flex {
                flex-direction: column;
                align-items: stretch !important;
                gap: 4px !important;
                width: 100%;
            }
            .navbar-trip .nav-link {
                padding: 10px 14px !important;
                width: 100%;
            }
            .navbar-trip .dropdown { width: 100%; }
            .navbar-trip .dropdown .btn { width: 100%; text-align: left; }
            .navbar-trip .dropdown-menu { position: static !important; transform: none !important; box-shadow: none; border: 1px solid var(--trip-border); margin-top: 4px; }
        }

        /* ── BUTTONS ── */
        .btn-trip {
            background: var(--trip-text);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            padding: 9px 22px;
            font-size: 14px;
            font-weight: 600;
            font-family: var(--font-sans);
            transition: background 0.15s, transform 0.1s, box-shadow 0.15s;
            display: inline-block;
            text-decoration: none;
            cursor: pointer;
            line-height: 1.4;
        }
        .btn-trip:hover  { background: #1F2937; color: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.18); }
        .btn-trip:active { transform: scale(0.98); }

        .btn-trip-outline {
            background: transparent;
            color: var(--trip-text);
            border: 1.5px solid rgba(0,0,0,0.15);
            border-radius: var(--radius-sm);
            padding: 8px 20px;
            font-size: 14px;
            font-weight: 600;
            font-family: var(--font-sans);
            transition: all 0.15s;
            display: inline-block;
            text-decoration: none;
            cursor: pointer;
            line-height: 1.4;
        }
        .btn-trip-outline:hover  { background: var(--trip-text); color: #fff; border-color: var(--trip-text); box-shadow: 0 4px 12px rgba(0,0,0,0.14); }
        .btn-trip-outline:active { transform: scale(0.98); }

        .btn-trip-orange {
            background: var(--trip-orange);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            padding: 12px 36px;
            font-size: 15px;
            font-weight: 700;
            font-family: var(--font-sans);
            transition: background 0.15s, transform 0.1s, box-shadow 0.15s;
            cursor: pointer;
            display: inline-block;
            text-decoration: none;
            line-height: 1.4;
        }
        .btn-trip-orange:hover  { background: var(--trip-orange-drk); color: #fff; box-shadow: 0 4px 16px rgba(240,96,32,0.35); }
        .btn-trip-orange:active { transform: scale(0.98); }

        /* ── CARDS ── */
        .trip-card {
            background: var(--trip-white);
            border-radius: var(--radius-lg);
            border: 1px solid var(--trip-border);
            box-shadow: var(--shadow-sm);
        }

        /* ── BADGES ── */
        .badge-trip-blue   { background: #DBEAFE; color: #1D4ED8; font-weight: 600; border-radius: 999px; }
        .badge-trip-green  { background: #DCFCE7; color: #15803D; font-weight: 600; border-radius: 999px; }
        .badge-trip-orange { background: #FFEDD5; color: #C2410C; font-weight: 600; border-radius: 999px; }
        .badge-trip-red    { background: #FEE2E2; color: #B91C1C; font-weight: 600; border-radius: 999px; }

        /* ── FORM CONTROLS ── */
        .form-control, .form-select {
            border-color: rgba(0, 0, 0, 0.12);
            border-radius: var(--radius-md);
            font-family: var(--font-sans);
            font-size: 14px;
            padding: 10px 14px;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--trip-blue);
            box-shadow: 0 0 0 3px rgba(0, 119, 238, 0.12);
        }
        .form-select { padding-right: 36px; }
        .form-control-sm, .form-select-sm {
            padding: 7px 12px;
            font-size: 13px;
        }

        /* ── HOLD-TO-REVEAL PASSWORD ── */
        .pwd-wrap { position: relative; }
        .pwd-wrap .form-control,
        .pwd-wrap .cred-input { padding-right: 44px; }
        .pwd-reveal-btn {
            position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: var(--trip-muted); cursor: pointer;
            padding: 4px; font-size: 16px; line-height: 1;
            user-select: none; -webkit-user-select: none;
            transition: color 0.15s;
        }
        .pwd-reveal-btn:hover { color: var(--trip-text); }

        /* ── TABLE ── */
        .trip-table thead th {
            background: #F8F9FB;
            color: var(--trip-muted);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            border-bottom: 1px solid var(--trip-border);
            font-family: var(--font-sans);
            padding: 12px 16px;
        }
        .trip-table td {
            vertical-align: middle;
            font-size: 14px;
            padding: 14px 16px;
            border-bottom: 1px solid rgba(0,0,0,0.04);
        }
        .trip-table tbody tr:last-child td { border-bottom: none; }
        .trip-table tbody tr:hover td { background: rgba(0,119,238,0.025); }

        /* ── PRICE TEXT ── */
        .price-text { color: var(--trip-orange); font-weight: 700; }

        /* ── TYPOGRAPHY HELPERS ── */
        .section-serif {
            font-family: var(--font-serif);
            font-weight: 600;
            letter-spacing: -0.02em;
            line-height: 1.15;
        }
        .eyebrow-label {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--trip-muted);
            margin-bottom: 12px;
        }

        /* ── ALERT OVERRIDES ── */
        .alert {
            border-radius: var(--radius-lg);
            border: none;
            font-size: 14px;
        }
        .alert-danger  { background: #FEF2F2; color: #991B1B; border-left: 4px solid var(--trip-danger); }
        .alert-success { background: #F0FDF4; color: #166534; border-left: 4px solid var(--trip-success); }
        .alert-warning { background: #FFFBEB; color: #92400E; border-left: 4px solid #F59E0B; }
        .alert-info    { background: #EFF6FF; color: #1E40AF; border-left: 4px solid var(--trip-blue); }

        /* ── DROPDOWN MENU ── */
        .dropdown-menu {
            border-radius: var(--radius-lg);
            border: 1px solid var(--trip-border);
            box-shadow: var(--shadow-lg);
            padding: 6px;
            margin-top: 8px !important;
        }
        .dropdown-item {
            border-radius: var(--radius-md);
            font-size: 14px;
            padding: 8px 12px;
            color: var(--trip-text);
            transition: background 0.12s;
        }
        .dropdown-item:hover { background: var(--trip-bg); color: var(--trip-blue); }
        .dropdown-divider { border-color: var(--trip-border); margin: 4px 0; }

        /* ── MODAL ── */
        .modal-content {
            border-radius: var(--radius-xl);
            border: 1px solid var(--trip-border);
            box-shadow: var(--shadow-xl);
        }
        .modal-header { padding: 24px 28px 16px; }
        .modal-body   { padding: 8px 28px 16px; }
        .modal-footer { padding: 16px 28px 24px; }

        /* ── SCROLLBAR ── */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.18); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(0,0,0,0.28); }
    </style>
</head>
<body>

<!-- ====== ADMIN TOP BAR ====== -->
<?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
<div id="adminBar" style="background: linear-gradient(90deg, #0A1628, #1a3060); color:#fff; font-size:12px; font-weight:600; text-align:center; padding:7px 16px; letter-spacing:.5px; position:fixed; top:0; width:100%; z-index:1050;">
    <i class="bi bi-shield-fill-check me-2" style="color:#60A5FA;"></i>ADMIN PANEL
    <span style="opacity:.5; margin:0 8px;">—</span>
    Changes made here affect all users
</div>
<style>
    body { padding-top: 116px; }
    .navbar-trip { top: 44px; }
</style>
<?php endif; ?>

<!-- ====== NAVBAR ====== -->
<nav class="navbar navbar-expand-lg navbar-trip">
    <div class="container">

        <!-- Brand -->
        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
            <a class="navbar-brand-trip" href="/admin/dashboard.php">
                <span class="brand-trip">trip</span><span class="brand-dot">.</span><span class="brand-com">com</span>
                <span style="font-size:10px; font-weight:700; background:var(--trip-navy); color:#fff; border-radius:4px; padding:2px 7px; margin-left:8px; letter-spacing:.4px; font-style:normal; font-family:var(--font-sans); line-height:1.6;">ADMIN</span>
            </a>
        <?php else: ?>
            <a class="navbar-brand-trip" href="/index.php">
                <span class="brand-trip">trip</span><span class="brand-dot">.</span><span class="brand-com">com</span>
            </a>
        <?php endif; ?>

        <!-- Mobile toggler -->
        <button class="trip-toggler navbar-toggler border-0" type="button"
                data-bs-toggle="collapse" data-bs-target="#navbarContent"
                aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <i class="bi bi-list"></i>
        </button>

        <!-- Nav Items -->
        <div class="collapse navbar-collapse" id="navbarContent">
            <div class="ms-auto d-flex align-items-center gap-2 flex-wrap">

                <?php if (isset($_SESSION['user_id'])): ?>

                    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                        <a href="/admin/dashboard.php" class="nav-link">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                        <a href="/admin/manage_flights.php" class="nav-link">
                            <i class="bi bi-airplane me-1"></i>Flights
                        </a>
                        <a href="/admin/manage_bookings.php" class="nav-link">
                            <i class="bi bi-journal-text me-1"></i>Bookings
                        </a>
                        <div class="dropdown ms-2">
                            <button class="btn dropdown-toggle" data-bs-toggle="dropdown"
                                    style="background: var(--trip-navy); color:#fff; border:none; font-size:14px; font-weight:600; padding:8px 16px; border-radius:var(--radius-sm); font-family:var(--font-sans);">
                                <i class="bi bi-shield-check me-1"></i>
                                <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" style="min-width:210px;">
                                <li class="px-3 pt-2 pb-1">
                                    <div style="font-size:13px; font-weight:700; color:var(--trip-text);"><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></div>
                                    <div style="font-size:12px; color:var(--trip-muted);"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></div>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/admin/dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                                <li><a class="dropdown-item" href="/admin/manage_flights.php"><i class="bi bi-airplane me-2"></i>Manage Flights</a></li>
                                <li><a class="dropdown-item" href="/admin/manage_bookings.php"><i class="bi bi-journal-text me-2"></i>Manage Bookings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" style="color:var(--trip-danger);" href="/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
                            </ul>
                        </div>

                    <?php else: ?>
                        <a href="/flights/search.php" class="nav-link">
                            <i class="bi bi-airplane me-1"></i>Flights
                        </a>
                        <a href="/user/dashboard.php" class="nav-link">
                            <i class="bi bi-ticket-detailed me-1"></i>My Bookings
                        </a>
                        <div class="dropdown ms-2">
                            <button class="btn btn-trip dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle me-1"></i>
                                <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" style="min-width:230px;">
                                <li class="px-3 pt-2 pb-1">
                                    <div style="font-size:13px; font-weight:700; color:var(--trip-text);"><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></div>
                                    <div style="font-size:12px; color:var(--trip-muted);"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></div>
                                    <?php
                                    if (isset($_SESSION['user_id'])) {
                                        global $conn;
                                        $loyaltyRow = $conn->query("SELECT User_Loyalty FROM user WHERE User_ID=" . (int)$_SESSION['user_id'])->fetch_assoc();
                                        $loyaltyPts = $loyaltyRow['User_Loyalty'] ?? 0;
                                    }
                                    ?>
                                    <div style="font-size:12px; color:var(--trip-orange); margin-top:5px; font-weight:600;">
                                        <i class="bi bi-coin me-1"></i><?= number_format($loyaltyPts ?? 0) ?> Trip Coins
                                    </div>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/user/profile.php"><i class="bi bi-person-circle me-2"></i>My Profile</a></li>
                                <li><a class="dropdown-item" href="/user/dashboard.php"><i class="bi bi-ticket-detailed me-2"></i>My Bookings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" style="color:var(--trip-danger);" href="/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
                            </ul>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <a href="/flights/find-booking.php" class="nav-link">
                        <i class="bi bi-search me-1"></i>Find My Bookings
                    </a>
                    <a href="/auth/login.php" class="btn btn-trip-outline ms-1">Sign In</a>
                    <a href="/auth/register.php" class="btn btn-trip ms-1">Register</a>
                <?php endif; ?>

            </div>
        </div>
    </div>
</nav>

<script>
(function () {
    const nav = document.querySelector('.navbar-trip');
    if (!nav) return;
    window.addEventListener('scroll', function () {
        nav.classList.toggle('nav-scrolled', window.scrollY > 20);
    }, { passive: true });
})();

function initHoldReveal(inputEl, btnEl) {
    function show() { inputEl.type = 'text'; }
    function hide() { inputEl.type = 'password'; }
    btnEl.addEventListener('mousedown', show);
    btnEl.addEventListener('touchstart', show, { passive: true });
    btnEl.addEventListener('mouseup', hide);
    btnEl.addEventListener('touchend', hide);
    btnEl.addEventListener('mouseleave', hide);
    btnEl.addEventListener('contextmenu', function(e) { e.preventDefault(); });
}
</script>
