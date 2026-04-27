<?php
session_start();
require_once '../config/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /dbweb/index.php');
    exit();
}

$error = '';

if (isset($_POST['login'])) {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM User WHERE User_Email = ? AND User_Status = 'ACTIVE' LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user['User_Password'])) {
        $_SESSION['user_id']    = $user['User_ID'];
        $_SESSION['user_name']  = $user['User_Name'];
        $_SESSION['user_email'] = $user['User_Email'];
        $_SESSION['role']       = $user['User_Role'];

        header('Location: ' . ($user['User_Role'] === 'admin' ? '/dbweb/admin/dashboard.php' : '/dbweb/index.php'));
        exit();
    } else {
        $error = 'Invalid email or password.';
    }
}

$title = 'Sign In';
include '../layout/layout.php';
?>

<div class="container py-5" style="max-width:440px;">
    <div class="trip-card p-4 mt-3">

        <div class="text-center mb-4">
            <div style="font-size:40px; color:#0086FF;">✈</div>
            <h4 class="fw-bold mb-0">Sign in to trip.com</h4>
            <p class="text-muted" style="font-size:14px;">Access your bookings and manage trips</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger rounded-3 py-2" style="font-size:14px;">
                <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size:14px;">Email</label>
                <input type="email" name="email" class="form-control"
                       placeholder="you@email.com" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold" style="font-size:14px;">Password</label>
                <input type="password" name="password" class="form-control"
                       placeholder="Enter password" required>
            </div>
            <button type="submit" name="login" class="btn btn-trip w-100" style="padding:11px;">
                Sign In
            </button>
        </form>

        <hr class="my-3">
        <p class="text-center mb-0" style="font-size:14px;">
            Don't have an account?
            <a href="/dbweb/auth/register.php" style="color:#0086FF; font-weight:600;">Register</a>
        </p>

        <p class="text-center mt-2 text-muted" style="font-size:12px;">
            Demo: <strong>admin@trip.com</strong> or <strong>user@trip.com</strong> / <strong>password</strong>
        </p>
    </div>
</div>

<?php include '../layout/footer.php'; ?>
