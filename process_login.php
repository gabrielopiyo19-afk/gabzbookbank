<?php
require_once "app_init.php";
start_secure_session();

require_once "db_connect.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.html");
    exit;
}

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? '';

if (empty($email) || empty($password) || !in_array($role, ['student', 'admin'], true)) {
    $_SESSION['login_error'] = "Please fill in all required fields.";
    header("Location: login.html?error=empty_fields");
    exit;
}

try {
    $sql = "SELECT user_id, full_name, student_id, email, password, role, department FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password']) && $user['role'] === $role) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['student_id'] = $user['student_id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['department'] = $user['department'];

            unset($_SESSION['login_error']);

            if ($user['role'] === 'admin') {
                header("Location: admin_reservations.php");
                exit;
            } else {
                header("Location: student_dashboard.php");
                exit;
            }

        } else {
            $_SESSION['login_error'] = "Invalid email or password.";
            header("Location: login.html?error=invalid_credentials");
            exit;
        }
    } else {
        $_SESSION['login_error'] = "No user account found with that email.";
        header("Location: login.html?error=user_not_found");
        exit;
    }

} catch (Exception $e) {
    $_SESSION['login_error'] = "An unexpected error occurred. Please try again.";
    header("Location: login.html?error=server_error");
    exit;
} finally {
    if (isset($stmt) && $stmt) {
        $stmt->close();
    }
    $conn->close();
}
?>
