<?php
header("Content-Type: application/json");
require_once "db_connect.php";

$department = isset($_GET['department']) ? trim($_GET['department']) : '';
$search     = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT book_id, title, author, department, isbn, available_copies, total_copies, cover_image FROM books WHERE 1=1";
$params = [];
$types  = "";

if (!empty($department) && $department !== 'all') {
    $sql .= " AND department = ?";
    $params[] = $department;
    $types .= "s";
}

if (!empty($search)) {
    $sql .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ?)";
    $searchTerm = "%" . $search . "%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

$sql .= " ORDER BY title ASC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$books = [];
while ($row = $result->fetch_assoc()) {
    $books[] = $row;
}

echo json_encode([
    "status" => "success",
    "count"  => count($books),
    "data"   => $books
]);

$stmt->close();
$conn->close();
?>