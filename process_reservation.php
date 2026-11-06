<?php
header("Content-Type: application/json");

require_once "app_init.php";
require_once "db_connect.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request method."
    ]);
    exit;
}

require_role('student', true);
$user_id = (int) $_SESSION['user_id'];

$book_id = isset($_POST['book_id']) ? intval($_POST['book_id']) : 0;

if ($book_id <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid book selection."
    ]);
    exit;
}

$conn->begin_transaction();

try {
    verify_csrf($_POST['csrf_token'] ?? null);
    $check_sql = "SELECT available_copies, title FROM books WHERE book_id = ? FOR UPDATE";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Book not found in database.");
    }

    $book = $result->fetch_assoc();

    if ($book['available_copies'] <= 0) {
        throw new Exception("Sorry, this book is currently out of stock.");
    }

    $existing_sql = "SELECT reservation_id FROM reservations WHERE user_id = ? AND book_id = ? AND status IN ('pending', 'approved')";
    $stmt_exist = $conn->prepare($existing_sql);
    $stmt_exist->bind_param("ii", $user_id, $book_id);
    $stmt_exist->execute();
    if ($stmt_exist->get_result()->num_rows > 0) {
        throw new Exception("You already have a pending reservation for this textbook.");
    }
    $stmt_exist->close();

    $limit_sql = "SELECT COUNT(*) AS active_count FROM reservations WHERE user_id = ? AND status IN ('pending', 'approved')";
    $stmt_limit = $conn->prepare($limit_sql);
    $stmt_limit->bind_param("i", $user_id);
    $stmt_limit->execute();
    $activeCount = (int) $stmt_limit->get_result()->fetch_assoc()['active_count'];
    $stmt_limit->close();
    if ($activeCount >= 2) {
        throw new Exception("You may only have two active reservations at a time.");
    }

    $update_sql = "UPDATE books SET available_copies = available_copies - 1 WHERE book_id = ? AND available_copies > 0";
    $stmt_update = $conn->prepare($update_sql);
    $stmt_update->bind_param("i", $book_id);
    $stmt_update->execute();
    if ($stmt_update->affected_rows !== 1) {
        throw new Exception("Sorry, this book is currently out of stock.");
    }
    $stmt_update->close();

    $reservation_date = date("Y-m-d H:i:s");
    $status = "pending";

    $insert_sql = "INSERT INTO reservations (user_id, book_id, reservation_date, status) VALUES (?, ?, ?, ?)";
    $stmt_insert = $conn->prepare($insert_sql);
    $stmt_insert->bind_param("iiss", $user_id, $book_id, $reservation_date, $status);
    $stmt_insert->execute();
    $reservation_id = $conn->insert_id;
    $stmt_insert->close();

    $conn->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Successfully reserved '" . $book['title'] . "'. Your digital reservation slip is ready.",
        "reservation_id" => $reservation_id,
        "slip_url" => "reservation_slip.php?id=" . $reservation_id
    ]);

} catch (Exception $e) {
    $conn->rollback();

    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
} finally {
    if (isset($stmt) && $stmt) {
        $stmt->close();
    }
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>
