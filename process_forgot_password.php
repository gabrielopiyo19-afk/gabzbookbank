<?php
header("Content-Type: application/json");
require_once "db_connect.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email     = trim($_POST['email'] ?? '');
    $studentId = trim($_POST['student_id'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || empty($studentId)) {
        echo json_encode([
            "status" => "error", 
            "message" => "Please enter both your university email and student ID."
        ]);
        exit();
    }

    $stmt = $conn->prepare("SELECT user_id, full_name FROM users WHERE email = ? AND student_id = ?");
    $stmt->bind_param("ss", $email, $studentId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        $token = bin2hex(random_bytes(32));
        $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

        $updateStmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE user_id = ?");
        $updateStmt->bind_param("ssi", $token, $expires, $user['user_id']);
        
        if ($updateStmt->execute()) {
            // Replace reset_url with email delivery before a public deployment.
            echo json_encode([
                "status" => "success",
                "message" => "Verification successful. Use the reset link below.",
                "reset_url" => "reset_password.php?token=" . urlencode($token)
            ]);
        } else {
            echo json_encode([
                "status" => "error", 
                "message" => "Failed to generate reset request. Please try again."
            ]);
        }
        $updateStmt->close();
    } else {
        echo json_encode([
            "status" => "error", 
            "message" => "No account found matching that email and Student ID combination."
        ]);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode([
        "status" => "error", 
        "message" => "Invalid request method."
    ]);
}
?>
