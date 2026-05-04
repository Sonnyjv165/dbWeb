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
            --trip-blue-dark:  #0055CC;
            --trip-orange:     #F06020;
            --trip-orange-drk: #D05010;
            --trip-navy:       #0A1628;
            --trip-bg:         #FAFAF8;
            --trip-text:       #111111;
            --trip-muted:      #787774;
            --trip-border:     rgba(0, 0, 0, 0.08);
            --trip-white:      #FFFFFF;

            --font-sans:  'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            --font-serif: 'Newsreader', 'Georgia', serif;
        }

        * { box-sizing: border-box; }

        body {
            font-family: var(--font-sans);
            background: var(--trip-bg);
            color: var(--trip-text);
            padding-top: 84px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ── FLOATING NAVBAR ── */
        .navbar-trip {
            position: fixed;
            top: 14px;
            left: 50%;
            transform: translateX(-50%);
            width: calc(100% - 32px);
            max-width: 1180px;
            z-index: 1040;
            background: rgba(250, 250, 248, 0.88);
            backdrop-filter: blur(20px) saturate(160%);
            -webkit-backdrop-filter: blur(20px) saturate(160%);
            border-radius: 16px;
            height: 58px;
            padding: 0 20px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow: 0 2px 16px rgba(0, 0, 0, 0.07), 0 1px 0 rgba(255, 255, 255, 0.9) inset;
            transition: box-shadow 0.4s cubic-bezier(0.32, 0.72, 0, 1), background 0.4s cubic-bezier(0.32, 0.72, 0, 1);
        }

        .navbar-trip.nav-scrolled {
            background: rgba(255, 255, 255, 0.97);
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.10), 0 1px 0 rgba(255, 255, 255, 0.9) inset;
        }

        .navbar-brand-trip {
            font-size: 20px;
            font-weight: 800;
            color: var(--trip-text);
            text-decoration: none;
            letter-spacing: -0.3px;
            display: flex;
            align-items: baseline;
            gap: 1px;
            line-height: 1;
        }
        .navbar-brand-trip .brand-trip {
            font-family: var(--font-serif);
            font-style: italic;
            font-weight: 600;
            color: var(--trip-text);
        }
        .navbar-brand-trip .brand-dot { color: var(--trip-orange); }
        .navbar-brand-trip .brand-com { color: var(--trip-text); font-size: 18px; font-weight: 700; }

        .navbar-trip .nav-link {
            color: var(--trip-text);
            font-size: 14px;
            font-weight: 500;
            padding: 6px 14px;
            border-radius: 8px;
            transition: background 0.15s, color 0.15s;
        }
        .navbar-trip .nav-link:hover { background: rgba(0, 119, 238, 0.07); color: var(--trip-blue); }

        /* ── BUTTONS ── */
        .btn-trip {
            background: var(--trip-text);
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 9px 22px;
            font-size: 14px;
            font-weight: 600;
            font-family: var(--font-sans);
            transition: background 0.15s, transform 0.12s;
            display: inline-block;
            text-decoration: none;
            cursor: pointer;
            line-height: 1.4;
        }
        .btn-trip:hover { background: #2a2a2a; color: #fff; }
        .btn-trip:active { transform: scale(0.98); }

        .btn-trip-outline {
            background: transparent;
            color: var(--trip-text);
            border: 1.5px solid rgba(0, 0, 0, 0.18);
            border-radius: 6px;
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
        .btn-trip-outline:hover { background: var(--trip-text); color: #fff; border-color: var(--trip-text); }
        .btn-trip-outline:active { transform: scale(0.98); }

        .btn-trip-orange {
            background: var(--trip-orange);
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 12px 36px;
            font-size: 15px;
            font-weight: 700;
            font-family: var(--font-sans);
            transition: background 0.15s, transform 0.12s;
            cursor: pointer;
            display: inline-block;
            text-decoration: none;
            line-height: 1.4;
        }
        .btn-trip-orange:hover { background: var(--trip-orange-drk); color: #fff; }
        .btn-trip-orange:active { transform: scale(0.98); }

        /* ── CARDS ── */
        .trip-card {
            background: var(--trip-white);
            border-radius: 12px;
            border: 1px solid var(--trip-border);
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
        }

        /* ── BADGES ── */
        .badge-trip-blue   { background: #e6f0fd; color: var(--trip-blue);   font-weight: 600; }
        .badge-trip-green  { background: #e6f4ec; color: #1a9e5c;            font-weight: 600; }
        .badge-trip-orange { background: #fef0e8; color: var(--trip-orange);  font-weight: 600; }
        .badge-trip-red    { background: #fdecea; color: #d93025;             font-weight: 600; }

        /* ── FORM CONTROLS ── */
        .form-control, .form-select {
            border-color: rgba(0, 0, 0, 0.12);
            font-family: var(--font-sans);
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--trip-blue);
            box-shadow: 0 0 0 3px rgba(0, 119, 238, 0.10);
        }

        /* ── TABLE ── */
        .trip-table thead th {
            background: #f8f8f6;
            color: var(--trip-muted);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            border-bottom: 1px solid var(--trip-border);
            font-family: var(--font-sans);
        }
        .trip-table td { vertical-align: middle; font-size: 14px; }

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
    </style>
</head>
<body>

<!-- ====== ADMIN TOP BAR ====== -->
<?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
<div id="adminBar" style="background:#0A1628; color:#fff; font-size:12px; font-weight:600; text-align:center; padding:6px 0; letter-spacing:.5px; position:fixed; top:0; width:100%; z-index:1050;">
    <i class="bi bi-shield-fill-check me-2"></i>ADMIN PANEL &nbsp;&mdash;&nbsp; Changes made here affect all users
</div>
<style>body { padding-top: 112px; } .navbar-trip { top: 44px; }</style>
<?php endif; ?>

<!-- ====== NAVBAR ====== -->
<nav class="navbar navbar-expand-lg navbar-trip">
    <div class="container">

        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
            <a class="navbar-brand-trip" href="/dbweb/admin/dashboard.php">
                <span class="brand-trip">trip</span><span class="brand-dot">.</span><span class="brand-com">com</span>
                <span style="font-size:11px; font-weight:700; background:#111; color:#fff; border-radius:4px; padding:2px 7px; margin-left:8px; letter-spacing:.4px; font-style:normal; font-family:var(--font-sans);">ADMIN</span>
            </a>
        <?php else: ?>
            <a class="navbar-brand-trip" href="/dbweb/index.php">
                <span class="brand-trip">trip</span><span class="brand-dot">.</span><span class="brand-com">com</span>
            </a>
        <?php endif; ?>

        <div class="ms-auto d-flex align-items-center gap-2">

            <?php if (isset($_SESSION['user_id'])): ?>

                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                    <a href="/dbweb/admin/dashboard.php" class="nav-link">
                        <i class="bi bi-speedometer2 me-1"></i>Dashboard
                    </a>
                    <a href="/dbweb/admin/manage_flights.php" class="nav-link">
                        <i class="bi bi-airplane me-1"></i>Flights
                    </a>
                    <a href="/dbweb/admin/manage_bookings.php" class="nav-link">
                        <i class="bi bi-journal-text me-1"></i>Bookings
                    </a>
                    <div class="dropdown ms-2">
                        <button class="btn dropdown-toggle" data-bs-toggle="dropdown"
                                style="background:#111; color:#fff; border:none; font-size:14px; font-weight:600; padding:8px 16px; border-radius:6px; font-family:var(--font-sans);">
                            <i class="bi bi-shield-check me-1"></i>
                            <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                            <li class="px-3 pt-2 pb-1">
                                <div style="font-size:13px; font-weight:600;"><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></div>
                                <div style="font-size:12px; color:#aaa;"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></div>
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li><a class="dropdown-item" href="/dbweb/admin/dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                            <li><a class="dropdown-item" href="/dbweb/admin/manage_flights.php"><i class="bi bi-airplane me-2"></i>Manage Flights</a></li>
                            <li><a class="dropdown-item" href="/dbweb/admin/manage_bookings.php"><i class="bi bi-journal-text me-2"></i>Manage Bookings</a></li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li><a class="dropdown-item text-danger" href="/dbweb/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
                        </ul>
                    </div>

                <?php else: ?>
                    <a href="/dbweb/flights/search.php" class="nav-link">
                        <i class="bi bi-airplane me-1"></i>Flights
                    </a>
                    <a href="/dbweb/user/dashboard.php" class="nav-link">
                        <i class="bi bi-ticket-detailed me-1"></i>My Bookings
                    </a>
                    <div class="dropdown ms-2">
                        <button class="btn btn-trip dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i>
                            <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3" style="min-width:220px;">
                            <li class="px-3 pt-2 pb-1">
                                <div style="font-size:13px; font-weight:600;"><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></div>
                                <div style="font-size:12px; color:#aaa;"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></div>
                                <?php
                                if (isset($_SESSION['user_id'])) {
                                    global $conn;
                                    $loyaltyRow = $conn->query("SELECT User_Loyalty FROM User WHERE User_ID=" . (int)$_SESSION['user_id'])->fetch_assoc();
                                    $loyaltyPts = $loyaltyRow['User_Loyalty'] ?? 0;
                                }
                                ?>
                                <div style="font-size:12px; color:var(--trip-orange); margin-top:4px;">
                                    <i class="bi bi-coin me-1"></i><?= number_format($loyaltyPts ?? 0) ?> Trip Coins
                                </div>
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li><a class="dropdown-item" href="/dbweb/user/profile.php"><i class="bi bi-person-circle me-2"></i>My Profile</a></li>
                            <li><a class="dropdown-item" href="/dbweb/user/dashboard.php"><i class="bi bi-ticket-detailed me-2"></i>My Bookings</a></li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li><a class="dropdown-item text-danger" href="/dbweb/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
                        </ul>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <a href="/dbweb/auth/login.php"    class="btn btn-trip-outline">Sign In</a>
                <a href="/dbweb/auth/register.php" class="btn btn-trip ms-1">Register</a>
            <?php endif; ?>

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
</script>
