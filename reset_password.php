<?php
require_once 'db_connect.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$error = '';
$success = '';

if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    $error = 'This password reset link is invalid.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Use a password of at least 8 characters.';
    } elseif (!hash_equals($password, $confirmPassword)) {
        $error = 'The passwords do not match.';
    } else {
        $stmt = $conn->prepare('SELECT user_id FROM users WHERE reset_token = ? AND reset_token_expires > NOW()');
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $error = 'This password reset link is invalid or has expired.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $update = $conn->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE user_id = ?');
            $update->bind_param('si', $hash, $user['user_id']);
            $update->execute();
            $update->close();
            $success = 'Your password has been reset. You can now sign in.';
        }
    }
}
$conn->close();
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Reset Password - Book Bank</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh"><main class="card shadow-sm border-0 p-4" style="width:100%;max-width:420px"><h1 class="h4 mb-3">Reset password</h1>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><a class="btn btn-primary w-100" href="login.html">Sign in</a><?php elseif (!$error || $_SERVER['REQUEST_METHOD'] === 'POST'): ?><form method="post"><input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>"><label class="form-label" for="password">New password</label><input class="form-control mb-3" id="password" type="password" name="password" minlength="8" required><label class="form-label" for="confirm_password">Confirm password</label><input class="form-control mb-3" id="confirm_password" type="password" name="confirm_password" minlength="8" required><button class="btn btn-primary w-100">Reset password</button></form><?php endif; ?>
</main></body></html>
