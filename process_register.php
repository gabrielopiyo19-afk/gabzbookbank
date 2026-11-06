<?php
header("Content-Type: application/json");
require_once "db_connect.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullName   = trim($_POST['full_name'] ?? '');
    $studentId  = trim($_POST['student_id'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $role       = $_POST['role'] ?? 'student';
    $adminCode  = $_POST['admin_code'] ?? '';

    $allowedDepartments = ['cse', 'ee', 'me'];
    if (empty($fullName) || empty($studentId) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8 || !in_array($department, $allowedDepartments, true)) {
        echo json_encode(["status" => "error", "message" => "Enter valid details and a password of at least 8 characters."]);
        exit();
    }

    if (!in_array($role, ['student', 'admin'], true)) {
        echo json_encode(["status" => "error", "message" => "Invalid account type."]);
        exit();
    }

    if ($role === 'admin') {
        $localConfig = __DIR__ . '/config.local.php';
        $config = is_file($localConfig) ? require $localConfig : [];
        $expectedCode = $config['admin_registration_code'] ?? getenv('BOOK_BANK_ADMIN_REGISTRATION_CODE') ?: '';

        if ($expectedCode === '' || !is_string($adminCode) || !hash_equals($expectedCode, $adminCode)) {
            echo json_encode(["status" => "error", "message" => "The administrator setup code is invalid."]);
            exit();
        }
    }

    $checkQuery = $conn->prepare("SELECT user_id FROM users WHERE email = ? OR student_id = ?");
    $checkQuery->bind_param("ss", $email, $studentId);
    $checkQuery->execute();
    $result = $checkQuery->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Email or Student ID is already registered!"]);
        exit();
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $conn->prepare("INSERT INTO users (full_name, student_id, department, email, password, role) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $fullName, $studentId, $department, $email, $hashedPassword, $role);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Registration successful! Redirecting to login..."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to create account. Try again."]);
    }

    $stmt->close();
    $conn->close();
}
?>
