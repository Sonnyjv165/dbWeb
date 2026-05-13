<?php
session_start();
require_once '../config/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit();
}

$error = '';

if (isset($_POST['login'])) {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM user WHERE User_Email = ? AND User_Status = 'ACTIVE' LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user['User_Password'])) {
        $_SESSION['user_id']    = $user['User_ID'];
        $_SESSION['user_name']  = $user['User_Name'];
        $_SESSION['user_email'] = $user['User_Email'];
        $_SESSION['role']       = $user['User_Role'];

        header('Location: ' . ($user['User_Role'] === 'admin' ? '/admin/dashboard.php' : '/index.php'));
        exit();
    } else {
        $error = 'Invalid email or password.';
    }
}

$title = 'Sign In';
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
    max-width: 440px;
}
@media (max-width: 480px) {
    .auth-card { padding: 32px 24px; border-radius: 16px; }
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
.demo-box {
    background: var(--trip-bg);
    border: 1px solid rgba(0,0,0,0.07);
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 12px;
    color: var(--trip-muted);
    text-align: center;
    margin-top: 16px;
}
</style>

<div class="auth-wrap">
    <div class="auth-card">

        <div class="auth-icon">
            <i class="bi bi-airplane-fill"></i>
        </div>
        <h4 class="auth-title">Sign in to trip.com</h4>
        <p class="auth-sub">Access your bookings and manage trips</p>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 mb-3">
                <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Email address</label>
                <input type="email" name="email" class="form-control"
                       placeholder="you@email.com" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="mb-4">
                <label class="form-label">Password</label>
                <div class="pwd-wrap">
                    <input type="password" name="password" id="loginPwd" class="form-control"
                           placeholder="Enter your password" required>
                    <button type="button" class="pwd-reveal-btn" id="loginPwdBtn" title="Hold to reveal">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            <button type="submit" name="login" class="btn-trip w-100" style="padding:12px; font-size:15px; text-align:center; border-radius:var(--radius-md);">
                Sign In
            </button>
        </form>

        <div class="auth-divider">or</div>

        <p class="text-center mb-0" style="font-size:14px; color:var(--trip-muted);">
            Don't have an account?
            <a href="/auth/register.php" style="color:var(--trip-blue); font-weight:600; text-decoration:none;">Create account</a>
        </p>

        <div class="demo-box">
            <i class="bi bi-info-circle me-1"></i>
            Demo: <strong style="color:var(--trip-text);">admin@trip.com</strong> or <strong style="color:var(--trip-text);">user@trip.com</strong> &nbsp;/&nbsp; <strong style="color:var(--trip-text);">password</strong>
        </div>

    </div>
</div>

<script>
initHoldReveal(document.getElementById('loginPwd'), document.getElementById('loginPwdBtn'));
</script>
<?php include '../layout/footer.php'; ?>
