<!-- ====== FOOTER ====== -->
<footer style="background:#fff; border-top:1px solid #e5e5e5; margin-top:60px;">

    <div class="container py-5">
        <div class="row g-5">

            <!-- Brand column -->
            <div class="col-md-4">
                <div style="font-size:22px; font-weight:800; color:#0086FF; letter-spacing:-0.5px; margin-bottom:10px;">
                    ✈ trip<span style="color:#FF7020;">.</span>com
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
                        ['Dashboard',       '/dbweb/admin/dashboard.php'],
                        ['Manage Flights',  '/dbweb/admin/manage_flights.php'],
                        ['Manage Bookings', '/dbweb/admin/manage_bookings.php'],
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
                        <a href="/dbweb/flights/search.php?from=<?= $from ?>&to=<?= $to ?>&date=<?= $fd ?>&passengers=1&class=economy&trip_type=oneway"
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
                        ['Admin Dashboard', '/dbweb/admin/dashboard.php'],
                        ['Sign Out',        '/dbweb/auth/logout.php'],
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
                            ['My Bookings',   '/dbweb/user/dashboard.php'],
                            ['My Profile',    '/dbweb/user/profile.php'],
                            ['Search Flights','/dbweb/index.php'],
                            ['Sign Out',      '/dbweb/auth/logout.php'],
                          ]
                        : [
                            ['Sign In',       '/dbweb/auth/login.php'],
                            ['Register',      '/dbweb/auth/register.php'],
                            ['Search Flights','/dbweb/index.php'],
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
