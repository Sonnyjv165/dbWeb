<!-- ====== FOOTER ====== -->
<footer style="background:#fff; border-top:1px solid rgba(0,0,0,0.06); margin-top:72px;">

    <div class="container py-5">
        <div class="row g-5">

            <!-- Brand column -->
            <div class="col-md-4">
                <div style="font-size:20px; font-weight:800; letter-spacing:-0.3px; margin-bottom:12px; display:flex; align-items:baseline; gap:1px;">
                    <span style="font-family:var(--font-serif); font-style:italic; font-weight:600; color:#111;">trip</span><span style="color:var(--trip-orange);">.</span><span style="color:#111; font-size:18px; font-weight:700;">com</span>
                </div>
                <p style="font-size:13px; color:var(--trip-muted); line-height:1.75; max-width:280px; margin-bottom:16px;">
                    A simulated flight booking platform built for academic demonstration. Book, manage, and explore flights — all in one place.
                </p>
                <div class="d-flex gap-2 flex-wrap">
                    <span style="background:#EFF6FF; color:#1D4ED8; border-radius:999px; font-size:11px; font-weight:600; padding:4px 12px; display:inline-flex; align-items:center; gap:5px;">
                        <i class="bi bi-shield-check"></i>Secure
                    </span>
                    <span style="background:#F0FDF4; color:#15803D; border-radius:999px; font-size:11px; font-weight:600; padding:4px 12px; display:inline-flex; align-items:center; gap:5px;">
                        <i class="bi bi-lightning-charge"></i>Instant
                    </span>
                    <span style="background:#FFEDD5; color:#C2410C; border-radius:999px; font-size:11px; font-weight:600; padding:4px 12px; display:inline-flex; align-items:center; gap:5px;">
                        <i class="bi bi-arrow-counterclockwise"></i>Free Cancel
                    </span>
                </div>
            </div>

            <!-- Quick links -->
            <div class="col-6 col-md-2">
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:var(--trip-text); margin-bottom:16px;">Manage</div>
                <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:10px;">
                    <?php
                    $footerAdmin = [
                        ['Dashboard',       '/admin/dashboard.php'],
                        ['Manage Flights',  '/admin/manage_flights.php'],
                        ['Manage Bookings', '/admin/manage_bookings.php'],
                    ];
                    foreach ($footerAdmin as [$label, $href]):
                    ?>
                    <li>
                        <a href="<?= $href ?>"
                           style="font-size:13px; color:var(--trip-muted); text-decoration:none; transition:color .15s; display:inline-block;"
                           onmouseover="this.style.color='var(--trip-blue)'" onmouseout="this.style.color='var(--trip-muted)'">
                            <?= $label ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:var(--trip-text); margin-bottom:16px;">Flights</div>
                <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:10px;">
                    <?php
                    $footerRoutes = [
                        ['Manila → Cebu',      'MNL','CEB'],
                        ['Manila → Singapore', 'MNL','SIN'],
                        ['Manila → Dubai',     'MNL','DXB'],
                        ['Manila → Tokyo',     'MNL','NRT'],
                        ['Manila → Hong Kong', 'MNL','HKG'],
                        ['Manila → Bangkok',   'MNL','BKK'],
                    ];
                    $fd = date('Y-m-d', strtotime('+1 day'));
                    foreach ($footerRoutes as [$label, $from, $to]):
                    ?>
                    <li>
                        <a href="/flights/search.php?from=<?= $from ?>&to=<?= $to ?>&date=<?= $fd ?>&passengers=1&class=economy&trip_type=oneway"
                           style="font-size:13px; color:var(--trip-muted); text-decoration:none; transition:color .15s; display:inline-block;"
                           onmouseover="this.style.color='var(--trip-blue)'" onmouseout="this.style.color='var(--trip-muted)'">
                            <?= $label ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>

            <!-- Account links -->
            <div class="col-6 col-md-2">
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:var(--trip-text); margin-bottom:16px;">Admin</div>
                <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:10px;">
                    <?php
                    $footerAdminAcct = [
                        ['Admin Dashboard', '/admin/dashboard.php'],
                        ['Sign Out',        '/auth/logout.php'],
                    ];
                    foreach ($footerAdminAcct as [$label, $href]):
                    ?>
                    <li>
                        <a href="<?= $href ?>"
                           style="font-size:13px; color:var(--trip-muted); text-decoration:none; transition:color .15s;"
                           onmouseover="this.style.color='var(--trip-blue)'" onmouseout="this.style.color='var(--trip-muted)'">
                            <?= $label ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:var(--trip-text); margin-bottom:16px;">Account</div>
                <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:10px;">
                    <?php
                    $footerAcct = isset($_SESSION['user_id'])
                        ? [
                            ['My Bookings',   '/user/dashboard.php'],
                            ['My Profile',    '/user/profile.php'],
                            ['Search Flights','/index.php'],
                            ['Sign Out',      '/auth/logout.php'],
                          ]
                        : [
                            ['Sign In',       '/auth/login.php'],
                            ['Register',      '/auth/register.php'],
                            ['Search Flights','/index.php'],
                          ];
                    foreach ($footerAcct as [$label, $href]):
                    ?>
                    <li>
                        <a href="<?= $href ?>"
                           style="font-size:13px; color:var(--trip-muted); text-decoration:none; transition:color .15s;"
                           onmouseover="this.style.color='var(--trip-blue)'" onmouseout="this.style.color='var(--trip-muted)'">
                            <?= $label ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>

            <!-- Promo codes -->
            <div class="col-md-4">
                <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:var(--trip-text); margin-bottom:16px;">Demo Promo Codes</div>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <?php
                    $promos = [
                        ['TRIP10',    '10% off any booking',   '#EFF6FF', '#1D4ED8'],
                        ['SUMMER500', '₱500 off any booking',  '#FFEDD5', '#C2410C'],
                        ['FLYPH20',   '20% off any booking',   '#F0FDF4', '#15803D'],
                    ];
                    foreach ($promos as [$code, $desc, $bg, $color]):
                    ?>
                    <div style="background:<?= $bg ?>; border-radius:10px; padding:11px 16px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="font-size:13px; font-weight:800; color:<?= $color ?>; letter-spacing:1.5px;"><?= $code ?></div>
                            <div style="font-size:11px; color:var(--trip-muted); margin-top:2px;"><?= $desc ?></div>
                        </div>
                        <i class="bi bi-tag-fill" style="color:<?= $color ?>; opacity:.4; font-size:16px;"></i>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>

    <!-- Developer Card -->
    <div style="border-top:1px solid rgba(0,0,0,0.05); padding:48px 0 40px;">
        <div class="container">
            <div style="font-size:11px; font-weight:700; letter-spacing:0.12em; text-transform:uppercase; color:#C0C4CC; margin-bottom:24px; text-align:center;">About the Developer</div>
            <div style="max-width:700px; margin:0 auto; background:var(--trip-bg); border:1px solid rgba(0,0,0,0.06); border-radius:18px; padding:32px; display:flex; gap:28px; align-items:flex-start; flex-wrap:wrap;">

                <!-- Photo -->
                <div style="flex-shrink:0;">
                    <img src="/assets/nash.jpg" alt="Nash T. Riobuya"
                         style="width:84px; height:84px; border-radius:50%; object-fit:cover; border:3px solid #fff; box-shadow:0 4px 16px rgba(0,0,0,0.12);">
                </div>

                <!-- Info -->
                <div style="flex:1; min-width:200px;">
                    <div style="font-family:var(--font-serif); font-size:20px; font-weight:600; letter-spacing:-0.02em; color:#111; margin-bottom:4px;">Nash T. Riobuya</div>
                    <div style="font-size:13px; color:var(--trip-muted); margin-bottom:2px; display:flex; align-items:center; gap:6px;">
                        <i class="bi bi-mortarboard-fill" style="color:var(--trip-blue);"></i>BSIT &mdash; 3A &nbsp;&middot;&nbsp; 3rd Year
                    </div>
                    <div style="font-size:13px; color:var(--trip-muted); margin-bottom:14px; display:flex; align-items:center; gap:6px;">
                        <i class="bi bi-building" style="color:var(--trip-blue);"></i>University of Cebu
                    </div>
                    <div style="font-size:13px; color:#555; line-height:1.65; margin-bottom:18px;">
                        Passionate about making games, web development, and Android application creation.
                    </div>

                    <!-- Quote -->
                    <div style="border-left:3px solid var(--trip-orange); padding-left:14px;">
                        <div style="font-family:var(--font-serif); font-style:italic; font-size:13px; color:#555; line-height:1.75;">
                            "Computer science education cannot make anybody an expert programmer any more than studying brushes and pigment can make somebody an expert painter."
                        </div>
                        <div style="font-size:11px; font-weight:700; color:var(--trip-muted); margin-top:8px; letter-spacing:0.05em;">— Eric S. Raymond</div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Bottom bar -->
    <div style="border-top:1px solid rgba(0,0,0,0.05); padding:16px 0;">
        <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div style="font-size:12px; color:#A8B0BC;">
                &copy; <?= date('Y') ?> <strong style="color:var(--trip-blue);">trip.com</strong> Flight Booking System
                &nbsp;&middot;&nbsp;
                Academic Project &mdash; Not a real booking platform
            </div>
            <div style="font-size:12px; color:#A8B0BC; display:flex; gap:16px; align-items:center;">
                <span style="display:flex; align-items:center; gap:5px;"><i class="bi bi-shield-check text-success"></i>Secure Demo</span>
                <span style="display:flex; align-items:center; gap:5px;"><i class="bi bi-mortarboard" style="color:var(--trip-blue);"></i>Academic Use Only</span>
            </div>
        </div>
    </div>

</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
