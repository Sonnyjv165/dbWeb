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
    <title><?= htmlspecialchars($title ?? 'Trip.com') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --trip-blue:       #0086FF;
            --trip-blue-dark:  #005FCC;
            --trip-orange:     #FF7020;
            --trip-orange-drk: #e0601a;
            --trip-navy:       #003580;
            --trip-bg:         #F5F5F5;
            --trip-text:       #1A1A1A;
            --trip-muted:      #6B6B6B;
            --trip-border:     #E5E5E5;
            --trip-white:      #FFFFFF;
        }

        * { box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            background: var(--trip-bg);
            color: var(--trip-text);
            padding-top: 84px;
        }

        /* ── FLOATING NAVBAR ── */
        .navbar-trip {
            /* Floating position — detached from edge */
            position: fixed;
            top: 14px;
            left: 50%;
            transform: translateX(-50%);
            width: calc(100% - 32px);
            max-width: 1180px;
            z-index: 1040;

            /* Frosted glass */
            background: rgba(255, 255, 255, 0.82);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);

            /* Shape */
            border-radius: 18px;
            height: 58px;
            padding: 0 20px;

            /* Depth */
            border: 1px solid rgba(0, 134, 255, 0.12);
            box-shadow:
                0 4px 16px rgba(0, 53, 128, 0.10),
                0 1px 0   rgba(255, 255, 255, 0.9) inset;

            transition: box-shadow 0.35s ease, background 0.35s ease, top 0.35s ease;
        }

        /* Enhance shadow when user scrolls down */
        .navbar-trip.nav-scrolled {
            background: rgba(255, 255, 255, 0.96);
            box-shadow:
                0 8px 32px rgba(0, 53, 128, 0.16),
                0 2px 8px  rgba(0, 53, 128, 0.08),
                0 1px 0    rgba(255, 255, 255, 0.9) inset;
        }

        .navbar-trip .navbar-brand {
            font-size: 21px;
            font-weight: 800;
            color: var(--trip-blue);
            letter-spacing: -0.5px;
        }
        .navbar-trip .navbar-brand span { color: var(--trip-orange); }
        .navbar-trip .nav-link {
            color: var(--trip-text);
            font-size: 14px;
            font-weight: 500;
            padding: 6px 14px;
            border-radius: 8px;
            transition: background 0.15s, color 0.15s;
        }
        .navbar-trip .nav-link:hover { background: #edf3ff; color: var(--trip-blue); }

        /* ── BUTTONS ── */
        .btn-trip {
            background: var(--trip-blue);
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 9px 22px;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.15s;
        }
        .btn-trip:hover { background: var(--trip-blue-dark); color: #fff; }

        .btn-trip-outline {
            background: transparent;
            color: var(--trip-blue);
            border: 1.5px solid var(--trip-blue);
            border-radius: 6px;
            padding: 8px 20px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.15s;
        }
        .btn-trip-outline:hover { background: var(--trip-blue); color: #fff; }

        .btn-trip-orange {
            background: var(--trip-orange);
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 12px 36px;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.3px;
            transition: background 0.15s;
        }
        .btn-trip-orange:hover { background: var(--trip-orange-drk); color: #fff; }

        /* ── CARDS ── */
        .trip-card {
            background: var(--trip-white);
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            border: none;
        }

        /* ── BADGES ── */
        .badge-trip-blue   { background: #e8f4ff; color: var(--trip-blue);   font-weight: 600; }
        .badge-trip-green  { background: #e6f9f0; color: #1a9e5c;            font-weight: 600; }
        .badge-trip-orange { background: #fff3ec; color: var(--trip-orange);  font-weight: 600; }
        .badge-trip-red    { background: #fff0f0; color: #d93025;             font-weight: 600; }

        /* ── FORM CONTROLS ── */
        .form-control:focus, .form-select:focus {
            border-color: var(--trip-blue);
            box-shadow: 0 0 0 3px rgba(0,134,255,0.12);
        }

        /* ── TABLE ── */
        .trip-table thead th {
            background: #f8faff;
            color: var(--trip-muted);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            border-bottom: 2px solid var(--trip-border);
        }
        .trip-table td { vertical-align: middle; font-size: 14px; }

        /* ── PRICE TEXT ── */
        .price-text { color: var(--trip-orange); font-weight: 700; }
    </style>
</head>
<body>

<!-- ====== ADMIN TOP BAR (admin only) ====== -->
<?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
<div id="adminBar" style="background:linear-gradient(90deg,#003580,#0052a3); color:#fff; font-size:12px; font-weight:600; text-align:center; padding:6px 0; letter-spacing:.5px; position:fixed; top:0; width:100%; z-index:1050;">
    <i class="bi bi-shield-fill-check me-2"></i>ADMIN PANEL &nbsp;—&nbsp; Changes made here affect all users
</div>
<style>body { padding-top: 112px; } .navbar-trip { top: 44px; }</style>
<?php endif; ?>

<!-- ====== NAVBAR ====== -->
<nav class="navbar navbar-expand-lg navbar-trip">
    <div class="container">

        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
            <a class="navbar-brand" href="/dbweb/admin/dashboard.php">
                ✈ trip<span>.</span>com
                <span style="font-size:11px; font-weight:700; background:#003580; color:#fff; border-radius:4px; padding:2px 7px; margin-left:6px; vertical-align:middle; letter-spacing:.4px;">ADMIN</span>
            </a>
        <?php else: ?>
            <a class="navbar-brand" href="/dbweb/index.php">
                ✈ trip<span>.</span>com
            </a>
        <?php endif; ?>

        <div class="ms-auto d-flex align-items-center gap-2">

            <?php if (isset($_SESSION['user_id'])): ?>

                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                    <!-- ── ADMIN NAV ── -->
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
                                style="background:#003580; color:#fff; border:none; font-size:14px; font-weight:600; padding:8px 16px; border-radius:6px;">
                            <i class="bi bi-shield-check me-1"></i>
                            <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                            <li class="px-3 pt-2 pb-1">
                                <div style="font-size:13px; font-weight:600;"><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></div>
                                <div style="font-size:12px; color:#aaa;"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></div>
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li><a class="dropdown-item" href="/dbweb/admin/dashboard.php">
                                <i class="bi bi-speedometer2 me-2"></i>Dashboard
                            </a></li>
                            <li><a class="dropdown-item" href="/dbweb/admin/manage_flights.php">
                                <i class="bi bi-airplane me-2"></i>Manage Flights
                            </a></li>
                            <li><a class="dropdown-item" href="/dbweb/admin/manage_bookings.php">
                                <i class="bi bi-journal-text me-2"></i>Manage Bookings
                            </a></li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li><a class="dropdown-item text-danger" href="/dbweb/auth/logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>Sign Out
                            </a></li>
                        </ul>
                    </div>

                <?php else: ?>
                    <!-- ── USER NAV ── -->
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
                                // Show Trip Coins balance in dropdown
                                if (isset($_SESSION['user_id'])) {
                                    global $conn;
                                    $loyaltyRow = $conn->query("SELECT User_Loyalty FROM User WHERE User_ID=" . (int)$_SESSION['user_id'])->fetch_assoc();
                                    $loyaltyPts = $loyaltyRow['User_Loyalty'] ?? 0;
                                }
                                ?>
                                <div style="font-size:12px; color:#FF7020; margin-top:4px;">
                                    <i class="bi bi-coin me-1"></i><?= number_format($loyaltyPts ?? 0) ?> Trip Coins
                                </div>
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li><a class="dropdown-item" href="/dbweb/user/profile.php">
                                <i class="bi bi-person-circle me-2"></i>My Profile
                            </a></li>
                            <li><a class="dropdown-item" href="/dbweb/user/dashboard.php">
                                <i class="bi bi-ticket-detailed me-2"></i>My Bookings
                            </a></li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li><a class="dropdown-item text-danger" href="/dbweb/auth/logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>Sign Out
                            </a></li>
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
