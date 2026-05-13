<?php
session_start();
require_once '../config/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit();
}

$error   = '';
$success = '';

if (isset($_POST['register'])) {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm'];

    if ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $check = $conn->prepare("SELECT User_ID FROM user WHERE User_Email = ?");
        $check->bind_param('s', $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = 'Email is already registered.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                INSERT INTO user (User_Name, User_Email, User_Password, User_PhoneNo, User_Loyalty, User_Status, User_Registration)
                VALUES (?, ?, ?, ?, 0, 'ACTIVE', NOW())
            ");
            $stmt->bind_param('ssss', $name, $email, $hashed, $phone);
            if ($stmt->execute()) {
                $success = 'Account created! You can now sign in.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}

$title = 'Create Account';
include '../layout/layout.php';
?>

<style>
.auth-wrap {
    min-height: calc(100dvh - 88px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 16px;
}
.auth-card {
    background: #fff;
    border-radius: 20px;
    border: 1px solid rgba(0,0,0,0.07);
    box-shadow: 0 8px 40px rgba(0,0,0,0.10), 0 2px 8px rgba(0,0,0,0.05);
    padding: 44px 40px;
    width: 100%;
    max-width: 500px;
}
@media (max-width: 540px) {
    .auth-card { padding: 32px 20px; border-radius: 16px; }
}
.auth-icon {
    width: 56px; height: 56px;
    border-radius: 16px;
    background: #EFF6FF;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 24px;
    font-size: 24px;
    color: var(--trip-blue);
}
.auth-title {
    font-family: var(--font-serif);
    font-size: 26px;
    font-weight: 600;
    letter-spacing: -0.02em;
    color: var(--trip-text);
    text-align: center;
    margin-bottom: 6px;
}
.auth-sub {
    font-size: 14px;
    color: var(--trip-muted);
    text-align: center;
    margin-bottom: 32px;
}
.form-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--trip-text);
    margin-bottom: 6px;
}
.auth-divider {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 24px 0;
    color: var(--trip-muted);
    font-size: 12px;
    font-weight: 500;
}
.auth-divider::before, .auth-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: rgba(0,0,0,0.08);
}
.phone-prefix {
    position: absolute; left: 14px; top: 50%;
    transform: translateY(-50%);
    font-size: 14px; color: var(--trip-text);
    font-weight: 600; pointer-events: none;
    z-index: 2; user-select: none;
}
</style>

<div class="auth-wrap">
    <div class="auth-card">

        <div class="auth-icon">
            <i class="bi bi-person-plus-fill"></i>
        </div>
        <h4 class="auth-title">Create your account</h4>
        <p class="auth-sub">Join trip.com and start booking flights</p>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 mb-3">
                <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success py-2 mb-3">
                <i class="bi bi-check-circle me-2"></i><?= $success ?>
                <a href="/auth/login.php" style="color:var(--trip-success); font-weight:700;">Sign in now &rarr;</a>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" name="name" class="form-control" placeholder="Your full name" required
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Email address</label>
                <input type="email" name="email" class="form-control" placeholder="you@email.com" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" style="display:flex; align-items:center; gap:6px;">
                    Phone Number <span style="font-size:11px; font-weight:400; color:var(--trip-muted);">(optional)</span>
                </label>
                <div style="position:relative;">
                    <span class="phone-prefix">+63</span>
                    <input type="tel" id="regPhoneDisplay" class="form-control"
                           placeholder="9XX XXX XXXX" maxlength="13" inputmode="numeric"
                           style="padding-left:50px;"
                           value="<?= htmlspecialchars(preg_replace('/^\+63\s*/', '', $_POST['phone'] ?? '')) ?>">
                    <input type="hidden" name="phone" id="regPhoneHidden"
                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <div class="pwd-wrap">
                    <input type="password" name="password" id="regPwd" class="form-control"
                           placeholder="At least 6 characters" required>
                    <button type="button" class="pwd-reveal-btn" id="regPwdBtn" title="Hold to reveal">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">Confirm Password</label>
                <div class="pwd-wrap">
                    <input type="password" name="confirm" id="regConfirm" class="form-control"
                           placeholder="Re-enter password" required>
                    <button type="button" class="pwd-reveal-btn" id="regConfirmBtn" title="Hold to reveal">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            <button type="submit" name="register" class="btn-trip w-100" style="padding:12px; font-size:15px; text-align:center; border-radius:var(--radius-md);">
                Create Account
            </button>
        </form>

        <div class="auth-divider">or</div>

        <p class="text-center mb-0" style="font-size:14px; color:var(--trip-muted);">
            Already have an account?
            <a href="/auth/login.php" style="color:var(--trip-blue); font-weight:600; text-decoration:none;">Sign in</a>
        </p>

    </div>
</div>

<script>
initHoldReveal(document.getElementById('regPwd'), document.getElementById('regPwdBtn'));
initHoldReveal(document.getElementById('regConfirm'), document.getElementById('regConfirmBtn'));

(function () {
    var display = document.getElementById('regPhoneDisplay');
    var hidden  = document.getElementById('regPhoneHidden');
    if (!display) return;

    function fmt(val) {
        var d = val.replace(/\D/g, '').slice(0, 10);
        if (d.length > 6) return d.slice(0,3) + ' ' + d.slice(3,6) + ' ' + d.slice(6);
        if (d.length > 3) return d.slice(0,3) + ' ' + d.slice(3);
        return d;
    }
    display.addEventListener('input', function () {
        this.value = fmt(this.value);
        hidden.value = this.value ? '+63 ' + this.value : '';
    });
    display.closest('form').addEventListener('submit', function () {
        hidden.value = display.value ? '+63 ' + display.value : '';
    });
})();
</script>
<?php include '../layout/footer.php'; ?>
