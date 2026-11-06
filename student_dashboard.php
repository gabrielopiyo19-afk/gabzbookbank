<?php
require_once "app_init.php";
start_secure_session();

// Include database connection
require_once "db_connect.php";

require_role('student');

$user_id = $_SESSION['user_id'];
$message = "";
$message_type = "";

// 2. Handle Student Request Cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_reservation') {
    $reservation_id = intval($_POST['reservation_id']);

    $conn->begin_transaction();

    try {
        verify_csrf($_POST['csrf_token'] ?? null);
        // Fetch reservation details and verify ownership
        $query = "SELECT r.*, b.book_id FROM reservations r JOIN books b ON r.book_id = b.book_id WHERE r.reservation_id = ? AND r.user_id = ? FOR UPDATE";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $reservation_id, $user_id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) {
            throw new Exception("Reservation record not found or unauthorized action.");
        }

        $row = $res->fetch_assoc();
        $book_id = $row['book_id'];
        $status = $row['status'];

        if ($status !== 'pending' && $status !== 'approved') {
            throw new Exception("Only pending or approved active requests can be cancelled.");
        }

        // Increment copy count back in catalog
        $restore_sql = "UPDATE books SET available_copies = LEAST(available_copies + 1, total_copies) WHERE book_id = ?";
        $stmt_b = $conn->prepare($restore_sql);
        $stmt_b->bind_param("i", $book_id);
        $stmt_b->execute();
        $stmt_b->close();

        // Update reservation status to cancelled
        $update_sql = "UPDATE reservations SET status = 'cancelled' WHERE reservation_id = ?";
        $stmt_u = $conn->prepare($update_sql);
        $stmt_u->bind_param("i", $reservation_id);
        $stmt_u->execute();
        $stmt_u->close();

        $conn->commit();
        $message = "Reservation #$reservation_id cancelled successfully. Stock updated.";
        $message_type = "success";

    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error: " . $e->getMessage();
        $message_type = "danger";
    } finally {
        if (isset($stmt) && $stmt) {
            $stmt->close();
        }
    }
}

// 3. Fetch User Profile Info from DB
$user_stmt = $conn->prepare("SELECT full_name, student_id, email, department FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

// 4. Fetch Student's Reservations
$res_stmt = $conn->prepare("
    SELECT 
        r.reservation_id,
        r.reservation_date,
        r.status,
        b.title AS book_title,
        b.author,
        b.department AS book_department
    FROM reservations r
    JOIN books b ON r.book_id = b.book_id
    WHERE r.user_id = ?
    ORDER BY r.reservation_date DESC
");
$res_stmt->bind_param("i", $user_id);
$res_stmt->execute();
$reservations = $res_stmt->get_result();
$res_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Bank - Student Dashboard</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="student_dashboard.php">
                <i class="bi bi-book-half me-2"></i>Book Bank Portal
            </a>
            <div class="d-flex align-items-center">
                <a href="catalog.html" class="btn btn-light btn-sm me-2">
                    <i class="bi bi-search me-1"></i> Browse Catalog
                </a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container pb-5">

        <!-- Student Profile Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm bg-white rounded-3">
                    <div class="card-body p-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                        <div>
                            <h4 class="fw-bold text-primary mb-1">
                                Welcome, <?= htmlspecialchars($user_data['full_name'] ?? 'Student') ?>!
                            </h4>
                            <p class="text-muted mb-0">
                                Student ID: <strong><?= htmlspecialchars($user_data['student_id'] ?? 'N/A') ?></strong> | 
                                Department: <span class="badge bg-secondary text-uppercase"><?= htmlspecialchars($user_data['department'] ?? 'General') ?></span> |
                                Email: <span><?= htmlspecialchars($user_data['email'] ?? '') ?></span>
                            </p>
                        </div>
                        <div>
                            <a href="catalog.html" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-1"></i> Reserve New Book
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show shadow-sm mb-4" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i><?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-journal-bookmark me-2 text-primary"></i>My Textbook Reservations
                </h5>
                <span class="badge bg-primary fs-6"><?= $reservations ? $reservations->num_rows : 0 ?> Total Requests</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th># Reg ID</th>
                                <th>Book Details</th>
                                <th>Department</th>
                                <th>Date Requested</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($reservations && $reservations->num_rows > 0): ?>
                                <?php while ($row = $reservations->fetch_assoc()): ?>
                                    <tr>
                                        <td class="fw-bold">#<?= $row['reservation_id'] ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($row['book_title']) ?></div>
                                            <small class="text-muted">Author: <?= htmlspecialchars($row['author']) ?></small>
                                        </td>
                                        <td><span class="badge bg-outline-dark text-uppercase border"><?= htmlspecialchars($row['book_department']) ?></span></td>
                                        <td><?= date("M d, Y - h:i A", strtotime($row['reservation_date'])) ?></td>
                                        <td>
                                            <?php
                                            $st = $row['status'];
                                            if ($st === 'pending') {
                                                echo '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Pending Approval</span>';
                                            } elseif ($st === 'approved') {
                                                echo '<span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i>Ready for Pickup / Active</span>';
                                            } elseif ($st === 'returned') {
                                                echo '<span class="badge bg-secondary"><i class="bi bi-box-arrow-in-left me-1"></i>Returned</span>';
                                            } else {
                                                echo '<span class="badge bg-danger"><i class="bi bi-x-circle-fill me-1"></i>Cancelled</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="reservation_slip.php?id=<?= (int) $row['reservation_id'] ?>" class="btn btn-outline-primary btn-sm me-1">
                                                <i class="bi bi-receipt me-1"></i>View Slip
                                            </a>
                                            <?php if ($st === 'pending'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="reservation_id" value="<?= $row['reservation_id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                                    <button type="submit" name="action" value="cancel_reservation" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to cancel this reservation request?')">
                                                        <i class="bi bi-x-circle me-1"></i>Cancel Request
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted small">No actions available</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                                        You haven't reserved any textbooks yet.<br>
                                        <a href="catalog.html" class="btn btn-outline-primary btn-sm mt-3">Browse Book Catalog</a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $conn->close(); ?>
