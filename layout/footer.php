<!-- ====== FOOTER ====== -->
<footer style="background:#fff; border-top:1px solid #e5e5e5; margin-top:60px;">

    <div class="container py-5">
        <div class="row g-5">

            <!-- Brand column -->
            <div class="col-md-4">
                <div style="font-size:20px; font-weight:800; letter-spacing:-0.3px; margin-bottom:10px; display:flex; align-items:baseline; gap:1px;">
                    <span style="font-family:var(--font-serif); font-style:italic; font-weight:600; color:#111;">trip</span><span style="color:var(--trip-orange, #F06020);">.</span><span style="color:#111; font-size:18px; font-weight:700;">com</span>
                </div>
                <p style="font-size:13px; color:#6B6B6B; line-height:1.7; max-width:280px;">
                    A simulated flight booking platform built for academic demonstration. Book, manage, and explore flights — all in one place.
                </p>
                <div class="d-flex gap-2 mt-3 flex-wrap">
                    <span style="background:#f0f8ff; color:#0086FF; border-radius:20px; font-size:11px; font-weight:600; padding:4px 12px;">
                        <i class="bi bi-shield-check me-1"></i>Secure
                    </span>
                    <span style="background:#e6f9f0; color:#1a9e5c; border-radius:20px; font-size:11px; font-weight:600; padding:4px 12px;">
                        <i class="bi bi-lightning-charge me-1"></i>Instant
                    </span>
                    <span style="background:#fff3ec; color:#FF7020; border-radius:20px; font-size:11px; font-weight:600; padding:4px 12px;">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Free Cancel
                    </span>
                </div>
            </div>

            <!-- Quick links -->
            <div class="col-6 col-md-2">
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:#1A1A1A; margin-bottom:16px;">Manage</div>
                <ul style="list-style:none; padding:0; margin:0;">
                    <?php
                    $footerAdmin = [
                        ['Dashboard',       '/admin/dashboard.php'],
                        ['Manage Flights',  '/admin/manage_flights.php'],
                        ['Manage Bookings', '/admin/manage_bookings.php'],
                    ];
                    foreach ($footerAdmin as [$label, $href]):
                    ?>
                    <li style="margin-bottom:9px;">
                        <a href="<?= $href ?>"
                           style="font-size:13px; color:#6B6B6B; text-decoration:none; transition:color .15s;"
                           onmouseover="this.style.color='#0086FF'" onmouseout="this.style.color='#6B6B6B'">
                            <?= $label ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:#1A1A1A; margin-bottom:16px;">Flights</div>
                <ul style="list-style:none; padding:0; margin:0;">
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
                    <li style="margin-bottom:9px;">
                        <a href="/flights/search.php?from=<?= $from ?>&to=<?= $to ?>&date=<?= $fd ?>&passengers=1&class=economy&trip_type=oneway"
                           style="font-size:13px; color:#6B6B6B; text-decoration:none; transition:color .15s;"
                           onmouseover="this.style.color='#0086FF'" onmouseout="this.style.color='#6B6B6B'">
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
                <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:#1A1A1A; margin-bottom:16px;">Admin</div>
                <ul style="list-style:none; padding:0; margin:0;">
                    <?php
                    $footerAdminAcct = [
                        ['Admin Dashboard', '/admin/dashboard.php'],
                        ['Sign Out',        '/auth/logout.php'],
                    ];
                    foreach ($footerAdminAcct as [$label, $href]):
                    ?>
                    <li style="margin-bottom:9px;">
                        <a href="<?= $href ?>"
                           style="font-size:13px; color:#6B6B6B; text-decoration:none;"
                           onmouseover="this.style.color='#0086FF'" onmouseout="this.style.color='#6B6B6B'">
                            <?= $label ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:#1A1A1A; margin-bottom:16px;">Account</div>
                <ul style="list-style:none; padding:0; margin:0;">
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
                    <li style="margin-bottom:9px;">
                        <a href="<?= $href ?>"
                           style="font-size:13px; color:#6B6B6B; text-decoration:none;"
                           onmouseover="this.style.color='#0086FF'" onmouseout="this.style.color='#6B6B6B'">
                            <?= $label ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>

            <!-- Promo codes -->
            <div class="col-md-4">
                <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:#1A1A1A; margin-bottom:16px;">Demo Promo Codes</div>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <?php
                    $promos = [
                        ['TRIP10',    '10% off any booking',    '#e8f4ff', '#0086FF'],
                        ['SUMMER500', '₱500 off any booking',   '#fff3ec', '#FF7020'],
                        ['FLYPH20',   '20% off any booking',    '#e6f9f0', '#1a9e5c'],
                    ];
                    foreach ($promos as [$code, $desc, $bg, $color]):
                    ?>
                    <div style="background:<?= $bg ?>; border-radius:10px; padding:10px 14px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="font-size:13px; font-weight:800; color:<?= $color ?>; letter-spacing:1px;"><?= $code ?></div>
                            <div style="font-size:11px; color:#6B6B6B;"><?= $desc ?></div>
                        </div>
                        <i class="bi bi-tag-fill" style="color:<?= $color ?>; opacity:.5;"></i>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>

    <!-- Developer Card -->
    <div style="border-top:1px solid #eee; padding:48px 0 40px;">
        <div class="container">
            <div style="font-size:11px; font-weight:700; letter-spacing:0.12em; text-transform:uppercase; color:#aaa; margin-bottom:24px; text-align:center;">About the Developer</div>
            <div style="max-width:680px; margin:0 auto; background:#FAFAF8; border:1px solid rgba(0,0,0,0.07); border-radius:20px; padding:32px; display:flex; gap:28px; align-items:flex-start; flex-wrap:wrap;">

                <!-- Photo -->
                <div style="flex-shrink:0;">
                    <img src="/assets/nash.jpg" alt="Nash T. Riobuya"
                         style="width:88px; height:88px; border-radius:50%; object-fit:cover; border:3px solid #fff; box-shadow:0 2px 12px rgba(0,0,0,0.10);">
                </div>

                <!-- Info -->
                <div style="flex:1; min-width:200px;">
                    <div style="font-family:var(--font-serif); font-size:20px; font-weight:600; letter-spacing:-0.02em; color:#111; margin-bottom:4px;">Nash T. Riobuya</div>
                    <div style="font-size:13px; color:var(--trip-muted); margin-bottom:2px;">
                        <i class="bi bi-mortarboard-fill me-1" style="color:var(--trip-blue);"></i>BSIT &mdash; 3A &nbsp;&middot;&nbsp; 3rd Year
                    </div>
                    <div style="font-size:13px; color:var(--trip-muted); margin-bottom:14px;">
                        <i class="bi bi-building me-1" style="color:var(--trip-blue);"></i>University of Cebu
                    </div>
                    <div style="font-size:13px; color:#555; line-height:1.6; margin-bottom:18px;">
                        Passionate about making games, web development, and Android application creation.
                    </div>

                    <!-- Quote -->
                    <div style="border-left:3px solid var(--trip-orange); padding-left:14px;">
                        <div style="font-family:var(--font-serif); font-style:italic; font-size:13px; color:#444; line-height:1.7;">
                            "Computer science education cannot make anybody an expert programmer any more than studying brushes and pigment can make somebody an expert painter."
                        </div>
                        <div style="font-size:11px; font-weight:700; color:var(--trip-muted); margin-top:6px; letter-spacing:0.05em;">— Eric S. Raymond</div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Bottom bar -->
    <div style="border-top:1px solid #eee; padding:16px 0;">
        <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div style="font-size:12px; color:#aaa;">
                &copy; <?= date('Y') ?> <strong style="color:#0086FF;">trip.com</strong> Flight Booking System &nbsp;·&nbsp;
                Academic Project — Not a real booking platform
            </div>
            <div style="font-size:12px; color:#aaa; display:flex; gap:16px;">
                <span><i class="bi bi-shield-check me-1 text-success"></i>Secure Demo</span>
                <span><i class="bi bi-mortarboard me-1" style="color:#0086FF;"></i>Academic Use Only</span>
            </div>
        </div>
    </div>

</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
