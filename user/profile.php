<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /dbweb/auth/login.php');
    exit();
}
if (($_SESSION['role'] ?? '') === 'admin') {
    header('Location: /dbweb/admin/dashboard.php');
    exit();
}

$userId  = $_SESSION['user_id'];
$success = '';
$error   = '';

// ── Handle profile update ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['update_profile'])) {
        $name        = trim($_POST['name'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $nationality = trim($_POST['nationality'] ?? '');
        $dob         = $_POST['dob'] ?? '';

        if ($name === '') {
            $error = 'Full name cannot be empty.';
        } else {
            $st = $conn->prepare("
                UPDATE User SET User_Name=?, User_PhoneNo=?, User_Nationality=?, User_DOB=?
                WHERE User_ID=?
            ");
            $dobVal = $dob ?: null;
            $st->bind_param('ssssi', $name, $phone, $nationality, $dobVal, $userId);
            $st->execute();
            $_SESSION['user_name'] = $name;
            $success = 'Profile updated successfully.';
        }
    }

    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'] ?? '';
        $newPw   = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $row = $conn->query("SELECT User_Password FROM User WHERE User_ID=$userId")->fetch_assoc();

        if (!password_verify($current, $row['User_Password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($newPw) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($newPw !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $hash = password_hash($newPw, PASSWORD_DEFAULT);
            $conn->prepare("UPDATE User SET User_Password=? WHERE User_ID=?")->execute([$hash, $userId]);
            $success = 'Password changed successfully.';
        }
    }
}

// ── Fetch user ────────────────────────────────────────────────
$user = $conn->query("SELECT * FROM User WHERE User_ID=$userId")->fetch_assoc();

// ── Booking stats ─────────────────────────────────────────────
$stats = $conn->query("
    SELECT
        COUNT(*)                                           AS total_bookings,
        SUM(CASE WHEN Book_Status='CONFIRMED' THEN 1 END) AS confirmed,
        SUM(CASE WHEN Book_Status='CANCELLED' THEN 1 END) AS cancelled,
        SUM(CASE WHEN Book_Status='CONFIRMED' THEN Book_Total ELSE 0 END) AS total_spent
    FROM Booking WHERE Book_UserID=$userId
")->fetch_assoc();

// ── Membership tier ───────────────────────────────────────────
function membershipTier(int $coins): array {
    if ($coins >= 20000) return ['Black Diamond', '#1a1a1a', '#FFD700',  'bi-gem'];
    if ($coins >= 10000) return ['Diamond+',      '#003580', '#fff',     'bi-gem'];
    if ($coins >= 5000)  return ['Diamond',       '#0086FF', '#fff',     'bi-gem'];
    if ($coins >= 2000)  return ['Platinum',      '#607D8B', '#fff',     'bi-award'];
    if ($coins >= 500)   return ['Gold',          '#B8860B', '#fff',     'bi-star-fill'];
    return                      ['Silver',        '#757575', '#fff',     'bi-star-half'];
}
[$tierName, $tierBg, $tierFg, $tierIcon] = membershipTier((int)$user['User_Loyalty']);

// Coins needed to reach next tier
$tiers   = [500, 2000, 5000, 10000, 20000];
$coins   = (int)$user['User_Loyalty'];
$nextThreshold = null;
foreach ($tiers as $t) { if ($coins < $t) { $nextThreshold = $t; break; } }
$progressPct = 100;
if ($nextThreshold !== null) {
    $prevThreshold = 0;
    foreach ($tiers as $t) { if ($t < $nextThreshold) $prevThreshold = $t; }
    $progressPct = min(100, (int)(($coins - $prevThreshold) / ($nextThreshold - $prevThreshold) * 100));
}

// Avatar initials + color
$words    = array_filter(explode(' ', $user['User_Name']));
$initials = strtoupper(substr($words[0] ?? 'U', 0, 1) . substr($words[1] ?? '', 0, 1));
$avatarColors = ['#0086FF','#FF7020','#1a9e5c','#7B2FBE','#C0392B','#00897B','#C8820A'];
$avatarBg = $avatarColors[ord($initials[0]) % count($avatarColors)];

$title = 'My Profile';
include '../layout/layout.php';
?>

<style>
.profile-avatar {
    width: 88px; height: 88px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 32px; font-weight: 800; color: #fff;
    flex-shrink: 0;
    box-shadow: 0 4px 16px rgba(0,0,0,0.18);
}
.tier-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 14px; border-radius: 20px;
    font-size: 12px; font-weight: 700; letter-spacing: .4px;
}
.stat-card {
    background: #fff; border-radius: 12px; padding: 20px 24px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    text-align: center;
}
.stat-card .stat-val { font-size: 26px; font-weight: 800; color: #1A1A1A; }
.stat-card .stat-lbl { font-size: 12px; color: #aaa; margin-top: 2px; }
.progress-bar-trip {
    background: linear-gradient(90deg, #0086FF, #00c6ff);
    height: 8px; border-radius: 4px;
    transition: width 0.6s ease;
}
.section-label {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .8px; color: #aaa; margin-bottom: 16px;
}

/* ── Tier flip cards ── */
.tier-flip        { perspective: 900px; height: 168px; cursor: default; }
.tier-flip-inner  {
    position: relative; width: 100%; height: 100%;
    transform-style: preserve-3d;
    transition: transform 0.55s cubic-bezier(0.4, 0.2, 0.2, 1);
}
.tier-flip:hover .tier-flip-inner { transform: rotateY(180deg); }
.tier-flip-front,
.tier-flip-back   {
    position: absolute; inset: 0;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    border-radius: 14px;
    padding: 16px;
    display: flex; flex-direction: column;
}
.tier-flip-front  {
    background: #f8f9fa;
    justify-content: space-between;
}
.tier-flip-front.is-active {
    border: 2px solid var(--tier-color);
    background: #fff;
    box-shadow: 0 4px 16px rgba(0,0,0,0.10);
}
.tier-flip-back   {
    transform: rotateY(180deg);
    color: #fff;
    justify-content: flex-start;
    gap: 0;
}
.tier-flip-back ul {
    list-style: none; padding: 0; margin: 8px 0 0;
}
.tier-flip-back ul li {
    font-size: 11.5px; line-height: 1.45; margin-bottom: 5px;
    display: flex; align-items: flex-start; gap: 6px;
}
.tier-flip-back ul li::before {
    content: '✓';
    font-weight: 800; font-size: 10px;
    background: rgba(255,255,255,0.25);
    border-radius: 50%; width: 16px; height: 16px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; margin-top: 1px;
}
.tier-hint {
    font-size: 10px; opacity: .55; letter-spacing: .3px;
    display: flex; align-items: center; gap: 4px;
}
</style>

<div class="container py-4" style="max-width:840px;">

    <!-- Flash messages -->
    <?php if ($success): ?>
        <div class="alert alert-success rounded-3 mb-3" style="font-size:14px;">
            <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger rounded-3 mb-3" style="font-size:14px;">
            <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- ── Profile Header ── -->
    <div class="trip-card p-4 mb-4" style="background:linear-gradient(135deg,#003580 0%,#0086FF 100%); color:#fff;">
        <div class="d-flex align-items-center gap-4 flex-wrap">
            <div class="profile-avatar" style="background:<?= $avatarBg ?>;">
                <?= $initials ?>
            </div>
            <div class="flex-grow-1">
                <h4 class="fw-bold mb-1" style="color:#fff;"><?= htmlspecialchars($user['User_Name']) ?></h4>
                <div style="font-size:14px; opacity:.85; margin-bottom:10px;">
                    <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($user['User_Email']) ?>
                    <?php if ($user['User_PhoneNo']): ?>
                        &nbsp;·&nbsp;<i class="bi bi-telephone me-1"></i><?= htmlspecialchars($user['User_PhoneNo']) ?>
                    <?php endif; ?>
                </div>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <span class="tier-badge" style="background:<?= $tierBg ?>; color:<?= $tierFg ?>;">
                        <i class="bi <?= $tierIcon ?>"></i><?= $tierName ?>
                    </span>
                    <span style="font-size:13px; opacity:.8;">
                        <i class="bi bi-coin me-1"></i><?= number_format($coins) ?> Trip Coins
                    </span>
                    <span style="font-size:12px; opacity:.65;">
                        Member since <?= date('M Y', strtotime($user['User_Registration'])) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Tier progress bar -->
        <div class="mt-4">
            <?php if ($nextThreshold !== null): ?>
                <div class="d-flex justify-content-between mb-1" style="font-size:12px; opacity:.75;">
                    <span><?= $tierName ?></span>
                    <span><?= number_format($coins) ?> / <?= number_format($nextThreshold) ?> coins to next tier</span>
                </div>
                <div style="background:rgba(255,255,255,0.2); border-radius:4px; height:8px;">
                    <div class="progress-bar-trip" style="width:<?= $progressPct ?>%;"></div>
                </div>
            <?php else: ?>
                <div class="d-flex justify-content-between mb-1" style="font-size:12px; opacity:.75;">
                    <span>Black Diamond — Maximum tier reached</span>
                    <span><?= number_format($coins) ?> coins</span>
                </div>
                <div style="background:rgba(255,255,255,0.2); border-radius:4px; height:8px;">
                    <div class="progress-bar-trip" style="width:100%; background:linear-gradient(90deg,#FFD700,#FFA500);"></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Stats Row ── -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-val"><?= (int)($stats['total_bookings'] ?? 0) ?></div>
                <div class="stat-lbl">Total Bookings</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-val" style="color:#1a9e5c;"><?= (int)($stats['confirmed'] ?? 0) ?></div>
                <div class="stat-lbl">Confirmed</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-val" style="color:#d93025;"><?= (int)($stats['cancelled'] ?? 0) ?></div>
                <div class="stat-lbl">Cancelled</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-val price-text" style="font-size:18px;">₱<?= number_format((float)($stats['total_spent'] ?? 0), 0) ?></div>
                <div class="stat-lbl">Total Spent</div>
            </div>
        </div>
    </div>

    <!-- ── Membership Tiers Reference ── -->
    <div class="trip-card p-4 mb-4">
        <div class="section-label">Membership Tiers <span style="font-weight:400; text-transform:none; letter-spacing:0; font-size:11px; color:#bbb;">— hover a card to see benefits</span></div>
        <div class="row g-3">
            <?php
            $allTiers = [
                [
                    'name'  => 'Silver',
                    'bg'    => 'linear-gradient(135deg,#757575,#9E9E9E)',
                    'color' => '#9E9E9E',
                    'fg'    => '#fff',
                    'from'  => 0,
                    'to'    => 499,
                    'icon'  => 'bi-star-half',
                    'perks' => [
                        'Full access to all flights',
                        'Email customer support',
                        'Earn 1 coin per ₱100 spent',
                        'Standard seat selection',
                    ],
                ],
                [
                    'name'  => 'Gold',
                    'bg'    => 'linear-gradient(135deg,#B8860B,#DAA520)',
                    'color' => '#B8860B',
                    'fg'    => '#fff',
                    'from'  => 500,
                    'to'    => 1999,
                    'icon'  => 'bi-star-fill',
                    'perks' => [
                        '5% discount voucher (1× per year)',
                        'Priority email support',
                        'Earn 1.0× Trip Coins',
                        'Early access to promotions',
                    ],
                ],
                [
                    'name'  => 'Platinum',
                    'bg'    => 'linear-gradient(135deg,#455A64,#607D8B)',
                    'color' => '#607D8B',
                    'fg'    => '#fff',
                    'from'  => 2000,
                    'to'    => 4999,
                    'icon'  => 'bi-award',
                    'perks' => [
                        '10% discount vouchers (2× per year)',
                        'Free seat selection on all flights',
                        'Dedicated customer support line',
                        'Earn 1.2× Trip Coins',
                    ],
                ],
                [
                    'name'  => 'Diamond',
                    'bg'    => 'linear-gradient(135deg,#0052a3,#0086FF)',
                    'color' => '#0086FF',
                    'fg'    => '#fff',
                    'from'  => 5000,
                    'to'    => 9999,
                    'icon'  => 'bi-gem',
                    'perks' => [
                        '15% discount vouchers (3× per year)',
                        'Free date change (1× per booking)',
                        'Priority boarding on all flights',
                        'Earn 1.5× Trip Coins',
                    ],
                ],
                [
                    'name'  => 'Diamond+',
                    'bg'    => 'linear-gradient(135deg,#001f4d,#003580)',
                    'color' => '#003580',
                    'fg'    => '#fff',
                    'from'  => 10000,
                    'to'    => 19999,
                    'icon'  => 'bi-gem',
                    'perks' => [
                        '20% discount on all bookings',
                        'Earn 2× Trip Coins',
                        'Free cancellation upgrade',
                        'Premium concierge service',
                    ],
                ],
                [
                    'name'  => 'Black Diamond',
                    'bg'    => 'linear-gradient(135deg,#000,#1a1a1a)',
                    'color' => '#1a1a1a',
                    'fg'    => '#FFD700',
                    'from'  => 20000,
                    'to'    => null,
                    'icon'  => 'bi-gem',
                    'perks' => [
                        '25% off all bookings',
                        'Unlimited flight changes',
                        'Earn 3× Trip Coins',
                        'Personal travel consultant',
                        'Complimentary class upgrades',
                    ],
                ],
            ];
            foreach ($allTiers as $tier):
                $active = $tierName === $tier['name'];
            ?>
            <div class="col-6 col-md-4">
                <div class="tier-flip" style="--tier-color:<?= $tier['color'] ?>;">
                    <div class="tier-flip-inner">

                        <!-- Front -->
                        <div class="tier-flip-front <?= $active ? 'is-active' : '' ?>">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi <?= $tier['icon'] ?>" style="color:<?= $tier['color'] ?>; font-size:20px;"></i>
                                <div>
                                    <div style="font-size:13px; font-weight:700; color:#1A1A1A; line-height:1.2;">
                                        <?= $tier['name'] ?>
                                        <?php if ($active): ?>
                                            <span style="font-size:10px; background:<?= $tier['color'] ?>; color:#fff; border-radius:10px; padding:1px 7px; margin-left:4px; vertical-align:middle;">You</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size:11px; color:#aaa;">
                                        <?= number_format($tier['from']) ?><?= $tier['to'] ? '–' . number_format($tier['to']) : '+' ?> coins
                                    </div>
                                </div>
                            </div>
                            <div class="tier-hint">
                                <i class="bi bi-arrow-repeat"></i> Hover for benefits
                            </div>
                        </div>

                        <!-- Back -->
                        <div class="tier-flip-back" style="background:<?= $tier['bg'] ?>;">
                            <div style="font-size:12px; font-weight:800; color:<?= $tier['fg'] ?>; letter-spacing:.3px; opacity:.9;">
                                <i class="bi <?= $tier['icon'] ?> me-1"></i><?= $tier['name'] ?>
                            </div>
                            <ul>
                                <?php foreach ($tier['perks'] as $perk): ?>
                                    <li style="color:<?= $tier['fg'] ?>; opacity:.95;"><?= $perk ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── Edit Profile ── -->
    <div class="trip-card p-4 mb-4">
        <div class="section-label">Edit Profile</div>
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold" style="font-size:13px;">Full Name</label>
                    <input type="text" name="name" class="form-control"
                           value="<?= htmlspecialchars($user['User_Name']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" style="font-size:13px;">Phone Number</label>
                    <input type="text" name="phone" class="form-control"
                           value="<?= htmlspecialchars($user['User_PhoneNo'] ?? '') ?>"
                           placeholder="+63 9XX XXX XXXX">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" style="font-size:13px;">Nationality</label>
                    <input type="text" name="nationality" class="form-control"
                           value="<?= htmlspecialchars($user['User_Nationality'] ?? '') ?>"
                           placeholder="e.g. Filipino">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" style="font-size:13px;">Date of Birth</label>
                    <input type="date" name="dob" class="form-control"
                           value="<?= htmlspecialchars($user['User_DOB'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <div class="p-3 rounded-3" style="background:#fff8f0; font-size:13px; color:#FF7020; border:1px solid #ffe5cc;">
                        <i class="bi bi-info-circle me-2"></i>
                        Your <strong>Full Name</strong> must exactly match the name on your government-issued ID or passport, as it is used for all flight bookings.
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" name="update_profile" class="btn btn-trip px-4">
                    <i class="bi bi-check2 me-1"></i>Save Changes
                </button>
            </div>
        </form>
    </div>

    <!-- ── Change Password ── -->
    <div class="trip-card p-4 mb-4">
        <div class="section-label">Change Password</div>
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold" style="font-size:13px;">Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold" style="font-size:13px;">New Password</label>
                    <input type="password" name="new_password" class="form-control"
                           minlength="6" placeholder="Min. 6 characters" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold" style="font-size:13px;">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" name="change_password" class="btn btn-outline-danger px-4">
                    <i class="bi bi-shield-lock me-1"></i>Change Password
                </button>
            </div>
        </form>
    </div>

    <!-- ── Policy Summary ── -->
    <div class="trip-card p-4 mb-4" style="border-left:4px solid #0086FF;">
        <div class="section-label">Booking Policies</div>
        <div class="row g-3" style="font-size:13px; color:#555;">
            <div class="col-md-6">
                <div class="d-flex gap-2">
                    <i class="bi bi-person-badge text-primary mt-1" style="flex-shrink:0;"></i>
                    <div><strong>Name Match Required</strong><br>Passenger names must exactly match your government-issued ID or passport.</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="d-flex gap-2">
                    <i class="bi bi-coin" style="color:#FF7020; flex-shrink:0; margin-top:2px;"></i>
                    <div><strong>Trip Coins</strong><br>Earn 1 coin per ₱100 spent. Coins are credited to your account after booking. Not awarded on promotional discounts.</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="d-flex gap-2">
                    <i class="bi bi-arrow-counterclockwise text-success mt-1" style="flex-shrink:0;"></i>
                    <div><strong>Free Cancellation</strong><br>Cancel confirmed bookings before the departure date for a full refund, subject to airline fare rules.</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="d-flex gap-2">
                    <i class="bi bi-shield-check text-primary mt-1" style="flex-shrink:0;"></i>
                    <div><strong>Price Guarantee</strong><br>Your booking price is locked once payment is confirmed — it will never change after that point.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center">
        <a href="/dbweb/user/dashboard.php" class="btn btn-trip px-4">
            <i class="bi bi-ticket-detailed me-2"></i>View My Bookings
        </a>
    </div>

</div>

<?php include '../layout/footer.php'; ?>
