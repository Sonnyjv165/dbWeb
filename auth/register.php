<?php
session_start();
require_once '../config/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /dbweb/index.php');
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
        $check = $conn->prepare("SELECT User_ID FROM User WHERE User_Email = ?");
        $check->bind_param('s', $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = 'Email is already registered.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                INSERT INTO User (User_Name, User_Email, User_Password, User_PhoneNo, User_Loyalty, User_Status, User_Registration)
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

$title = 'Register';
include '../layout/layout.php';
?>

<div class="container py-5" style="max-width:500px;">
    <div class="trip-card p-4 mt-3">

        <div class="text-center mb-4">
            <div style="font-size:40px; color:#0086FF;">✈</div>
            <h4 class="fw-bold mb-0">Create your account</h4>
            <p class="text-muted" style="font-size:14px;">Join trip.com and start booking flights</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger rounded-3 py-2" style="font-size:14px;">
                <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success rounded-3 py-2" style="font-size:14px;">
                <i class="bi bi-check-circle me-2"></i><?= $success ?>
                <a href="/dbweb/auth/login.php" class="fw-bold">Sign in →</a>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size:14px;">Full Name</label>
                <input type="text" name="name" class="form-control" placeholder="Juan Dela Cruz" required
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size:14px;">Email</label>
                <input type="email" name="email" class="form-control" placeholder="you@email.com" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size:14px;">Phone (optional)</label>
                <input type="tel" name="phone" class="form-control" placeholder="+63 9XX XXX XXXX"
                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size:14px;">Password</label>
                <input type="password" name="password" class="form-control"
                       placeholder="Min. 6 characters" required>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold" style="font-size:14px;">Confirm Password</label>
                <input type="password" name="confirm" class="form-control"
                       placeholder="Re-enter password" required>
            </div>
            <button type="submit" name="register" class="btn btn-trip w-100" style="padding:11px;">
                Create Account
            </button>
        </form>

        <hr class="my-3">
        <p class="text-center mb-0" style="font-size:14px;">
            Already have an account?
            <a href="/dbweb/auth/login.php" style="color:#0086FF; font-weight:600;">Sign In</a>
        </p>
    </div>
</div>

<?php include '../layout/footer.php'; ?>
