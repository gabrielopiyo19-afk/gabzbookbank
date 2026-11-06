<?php
require_once "app_init.php";
start_secure_session();
require_once "db_connect.php";

require_role('admin');

$message = "";
$message_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['reservation_id'])) {
    $reservation_id = intval($_POST['reservation_id']);
    $action = $_POST['action'];

    $conn->begin_transaction();

    try {
        verify_csrf($_POST['csrf_token'] ?? null);
        if (!in_array($action, ['approve', 'cancel', 'returned'], true)) {
            throw new Exception('Invalid reservation action.');
        }
        $res_query = "SELECT r.*, b.book_id FROM reservations r JOIN books b ON r.book_id = b.book_id WHERE r.reservation_id = ? FOR UPDATE";
        $stmt = $conn->prepare($res_query);
        $stmt->bind_param("i", $reservation_id);
        $stmt->execute();
        $res_result = $stmt->get_result();

        if ($res_result->num_rows === 0) {
            throw new Exception("Reservation record not found.");
        }

        $reservation = $res_result->fetch_assoc();
        $book_id = $reservation['book_id'];
        $current_status = $reservation['status'];

        if ($action === 'approve') {
            if ($current_status !== 'pending') {
                throw new Exception("Only pending reservations can be approved.");
            }
            $update_res = "UPDATE reservations SET status = 'approved' WHERE reservation_id = ?";
            $stmt_u = $conn->prepare($update_res);
            $stmt_u->bind_param("i", $reservation_id);
            $stmt_u->execute();
            $stmt_u->close();

            $message = "Reservation #$reservation_id approved successfully!";
            $message_type = "success";

        } elseif ($action === 'cancel') {
            if ($current_status === 'pending' || $current_status === 'approved') {
                $restore_sql = "UPDATE books SET available_copies = LEAST(available_copies + 1, total_copies) WHERE book_id = ?";
                $stmt_b = $conn->prepare($restore_sql);
                $stmt_b->bind_param("i", $book_id);
                $stmt_b->execute();
                $stmt_b->close();
            }

            $update_res = "UPDATE reservations SET status = 'cancelled' WHERE reservation_id = ?";
            $stmt_u = $conn->prepare($update_res);
            $stmt_u->bind_param("i", $reservation_id);
            $stmt_u->execute();
            $stmt_u->close();

            $message = "Reservation #$reservation_id cancelled. Copy returned to stock.";
            $message_type = "warning";

        } elseif ($action === 'returned') {
            if ($current_status !== 'approved') {
                throw new Exception("Only approved active rentals can be marked as returned.");
            }

            // Restore copy to inventory
            $restore_sql = "UPDATE books SET available_copies = LEAST(available_copies + 1, total_copies) WHERE book_id = ?";
            $stmt_b = $conn->prepare($restore_sql);
            $stmt_b->bind_param("i", $book_id);
            $stmt_b->execute();
            $stmt_b->close();

            $update_res = "UPDATE reservations SET status = 'returned' WHERE reservation_id = ?";
            $stmt_u = $conn->prepare($update_res);
            $stmt_u->bind_param("i", $reservation_id);
            $stmt_u->execute();
            $stmt_u->close();

            $message = "Book marked as returned for Reservation #$reservation_id.";
            $message_type = "info";
        }

        $conn->commit();
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

$query = "
    SELECT 
        r.reservation_id,
        r.reservation_date,
        r.status,
        u.full_name AS student_name,
        u.student_id,
        u.email AS student_email,
        b.title AS book_title,
        b.department
    FROM reservations r
    JOIN users u ON r.user_id = u.user_id
    JOIN books b ON r.book_id = b.book_id
    ORDER BY 
        CASE r.status 
            WHEN 'pending' THEN 1 
            WHEN 'approved' THEN 2 
            ELSE 3 
        END,
        r.reservation_date DESC
";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Bank - Admin Reservation Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="admin_reservations.php">
                <i class="bi bi-shield-lock-fill me-2"></i>Book Bank Admin
            </a>
            <div class="d-flex align-items-center">
                <a href="catalog.html" class="btn btn-outline-light btn-sm me-2">View Catalog</a>
                <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container pb-5">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold text-secondary mb-0">Manage Student Reservations</h3>
            <span class="badge bg-primary fs-6"><?= $result ? $result->num_rows : 0 ?> Total Requests</span>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show shadow-sm mb-4" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i><?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th># ID</th>
                                <th>Student Name</th>
                                <th>Book Title</th>
                                <th>Dept</th>
                                <th>Request Date</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="fw-bold">#<?= $row['reservation_id'] ?></td>
                                        <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($row['student_name']) ?></div>
                                        <small class="text-muted">ID: <?= htmlspecialchars($row['student_id']) ?> | <?= htmlspecialchars($row['student_email']) ?></small>
                                                                        </td>
                                        <td><?= htmlspecialchars($row['book_title']) ?></td>
                                        <td><span class="badge bg-secondary text-uppercase"><?= htmlspecialchars($row['department']) ?></span></td>
                                        <td><?= date("M d, Y - h:i A", strtotime($row['reservation_date'])) ?></td>
                                        <td>
                                            <?php
                                            $status = $row['status'];
                                            if ($status === 'pending') {
                                                echo '<span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Pending</span>';
                                            } elseif ($status === 'approved') {
                                                echo '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Approved</span>';
                                            } elseif ($status === 'returned') {
                                                echo '<span class="badge bg-info text-dark"><i class="bi bi-box-arrow-in-left me-1"></i>Returned</span>';
                                            } else {
                                                echo '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Cancelled</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="reservation_id" value="<?= $row['reservation_id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                                
                                                <?php if ($status === 'pending'): ?>
                                                    <button type="submit" name="action" value="approve" class="btn btn-success btn-sm me-1" onclick="return confirm('Approve this reservation?')">
                                                        <i class="bi bi-check-lg"></i> Approve
                                                    </button>
                                                    <button type="submit" name="action" value="cancel" class="btn btn-outline-danger btn-sm" onclick="return confirm('Cancel this reservation?')">
                                                        <i class="bi bi-x-lg"></i> Cancel
                                                    </button>
                                                <?php elseif ($status === 'approved'): ?>
                                                    <button type="submit" name="action" value="returned" class="btn btn-primary btn-sm me-1" onclick="return confirm('Mark this book as returned?')">
                                                        <i class="bi bi-arrow-return-left"></i> Mark Returned
                                                    </button>
                                                    <button type="submit" name="action" value="cancel" class="btn btn-outline-danger btn-sm" onclick="return confirm('Cancel this reservation?')">
                                                        <i class="bi bi-x-lg"></i> Cancel
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted small">No actions</span>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No reservation requests found.</td>
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
