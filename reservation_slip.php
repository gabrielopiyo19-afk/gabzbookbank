<?php
require_once 'app_init.php';
require_once 'db_connect.php';

require_role('student');
$requestedId = $_GET['id'] ?? null;
$reservationId = null;

if ($requestedId !== null) {
    $reservationId = filter_var($requestedId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$reservationId) {
        http_response_code(400);
        exit('Invalid reservation reference.');
    }
}

$userId = (int) $_SESSION['user_id'];
if ($reservationId) {
    $statement = $conn->prepare(
        'SELECT r.reservation_id, r.reservation_date, r.status, u.full_name, u.student_id,
                b.title, b.author, b.isbn, b.department
         FROM reservations r
         INNER JOIN users u ON u.user_id = r.user_id
         INNER JOIN books b ON b.book_id = r.book_id
         WHERE r.reservation_id = ? AND r.user_id = ?'
    );
    $statement->bind_param('ii', $reservationId, $userId);
} else {
    $statement = $conn->prepare(
        'SELECT r.reservation_id, r.reservation_date, r.status, u.full_name, u.student_id,
                b.title, b.author, b.isbn, b.department
         FROM reservations r
         INNER JOIN users u ON u.user_id = r.user_id
         INNER JOIN books b ON b.book_id = r.book_id
         WHERE r.user_id = ?
         ORDER BY r.reservation_date DESC
         LIMIT 1'
    );
    $statement->bind_param('i', $userId);
}
$statement->execute();
$reservation = $statement->get_result()->fetch_assoc();
$statement->close();
$conn->close();

if (!$reservation) {
    http_response_code(404);
    exit('Reservation slip not found.');
}

$reference = 'BB-' . date('Y', strtotime($reservation['reservation_date'])) . '-' . str_pad((string) $reservation['reservation_id'], 6, '0', STR_PAD_LEFT);
$validUntil = date('d M Y, h:i A', strtotime($reservation['reservation_date'] . ' +48 hours'));
$qrData = rawurlencode('Book Bank reservation ' . $reference);
$isActive = in_array($reservation['status'], ['pending', 'approved'], true);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reservation Slip <?= htmlspecialchars($reference) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #edf2f7; color: #17202a; }
        .slip { max-width: 760px; margin: 3rem auto; border: 10px solid #16202a; background: #fff98a; box-shadow: 0 1rem 2.5rem rgba(0,0,0,.2); }
        .slip-header { border-bottom: 2px solid #1d2935; }
        .brand { background: #b8f1ef; border: 1px solid #4f8a88; display: inline-block; padding: .25rem .8rem; }
        .reference { font-family: monospace; font-size: 1.05rem; letter-spacing: .08em; }
        .barcode { height: 48px; width: 230px; margin: .5rem auto; background: repeating-linear-gradient(90deg, #111 0 2px, transparent 2px 4px, #111 4px 5px, transparent 5px 8px, #111 8px 12px, transparent 12px 14px); border: 1px solid #222; }
        .status { text-transform: uppercase; letter-spacing: .06em; }
        @media print { body { background: #fff; } .slip { margin: 0; box-shadow: none; } .no-print { display: none !important; } }
    </style>
</head>
<body>
    <main class="container pb-5">
        <div class="d-flex justify-content-between align-items-center mt-4 no-print">
            <a href="student_dashboard.php" class="btn btn-outline-secondary">&larr; Dashboard</a>
            <div>
                <button class="btn btn-outline-primary" onclick="window.print()">Print Slip</button>
                <button class="btn btn-primary" onclick="window.print()">Download PDF</button>
            </div>
        </div>

        <section class="slip p-4 p-md-5" aria-label="Digital reservation slip">
            <div class="text-center slip-header pb-3 mb-4">
                <div class="border border-dark d-inline-block px-3 py-2 mb-2">OFFICIAL BOOK RESERVATION SLIP</div>
                <div class="brand fw-semibold">Book Bank Department of <?= htmlspecialchars(strtoupper($reservation['department'])) ?></div>
            </div>

            <div class="row g-3 align-items-start">
                <div class="col-md-8">
                    <p class="mb-1"><strong>Reservation ID:</strong> <span class="reference"><?= htmlspecialchars($reference) ?></span></p>
                    <p class="mb-1"><strong>Student Name:</strong> <?= htmlspecialchars($reservation['full_name']) ?></p>
                    <p class="mb-1"><strong>Student ID:</strong> <?= htmlspecialchars($reservation['student_id']) ?></p>
                    <p class="mb-1"><strong>Book Title:</strong> <?= htmlspecialchars($reservation['title']) ?></p>
                    <p class="mb-1"><strong>Author:</strong> <?= htmlspecialchars($reservation['author']) ?></p>
                    <p class="mb-1"><strong>ISBN:</strong> <?= htmlspecialchars($reservation['isbn']) ?></p>
                    <p class="mb-1"><strong>Pickup Location:</strong> Main Library &ndash; Counter B</p>
                    <p class="mb-0"><strong>Valid Until:</strong> <?= htmlspecialchars($validUntil) ?> (48-hour hold period)</p>
                </div>
                <div class="col-md-4 text-center">
                    <img width="150" height="150" alt="QR reference for <?= htmlspecialchars($reference) ?>" src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&amp;data=<?= $qrData ?>">
                    <div class="small mt-1">Present this code at collection.</div>
                </div>
            </div>

            <hr class="border-dark my-4">
            <div class="text-center">
                <div class="barcode" aria-label="Reservation barcode"></div>
                <div class="reference"><?= htmlspecialchars($reference) ?></div>
                <span class="badge <?= $isActive ? 'text-bg-success' : 'text-bg-secondary' ?> status mt-3"><?= htmlspecialchars($reservation['status']) ?></span>
            </div>
        </section>
        <p class="text-center text-muted small no-print">For “Download PDF”, choose <em>Save as PDF</em> in your browser's print dialog.</p>
    </main>
</body>
</html>
