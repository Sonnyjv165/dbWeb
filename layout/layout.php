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
            padding-top: 64px;
        }

        /* ── NAVBAR ── */
        .navbar-trip {
            background: var(--trip-white);
            height: 64px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.09);
            padding: 0;
        }
        .navbar-trip .navbar-brand {
            font-size: 22px;
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
            border-radius: 4px;
            transition: background 0.15s;
        }
        .navbar-trip .nav-link:hover { background: #f0f4ff; color: var(--trip-blue); }

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

<!-- ====== NAVBAR ====== -->
<nav class="navbar navbar-expand-lg navbar-trip fixed-top">
    <div class="container">

        <a class="navbar-brand" href="/dbweb/index.php">
            ✈ trip<span>.</span>com
        </a>

        <div class="ms-auto d-flex align-items-center gap-2">
            <?php if (isset($_SESSION['user_id'])): ?>

                <a href="/dbweb/flights/search.php" class="nav-link">
                    <i class="bi bi-airplane me-1"></i>Flights
                </a>

                <a href="/dbweb/user/dashboard.php" class="nav-link">
                    <i class="bi bi-ticket-detailed me-1"></i>My Bookings
                </a>

                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                    <a href="/dbweb/admin/dashboard.php" class="nav-link">
                        <i class="bi bi-shield-check me-1"></i>Admin
                    </a>
                <?php endif; ?>

                <div class="dropdown ms-2">
                    <button class="btn btn-trip dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                        <li class="px-3 py-2 text-muted" style="font-size:12px;">
                            <?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="dropdown-item" href="/dbweb/user/dashboard.php">
                            <i class="bi bi-ticket-detailed me-2"></i>My Bookings
                        </a></li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="dropdown-item text-danger" href="/dbweb/auth/logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i>Sign Out
                        </a></li>
                    </ul>
                </div>

            <?php else: ?>
                <a href="/dbweb/auth/login.php"    class="btn btn-trip-outline">Sign In</a>
                <a href="/dbweb/auth/register.php" class="btn btn-trip ms-1">Register</a>
            <?php endif; ?>
        </div>

    </div>
</nav>
