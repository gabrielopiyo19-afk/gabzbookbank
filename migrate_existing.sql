USE book_bank_db;

-- First allow both the legacy and current application status values.
ALTER TABLE reservations
    MODIFY status ENUM('pending', 'approved', 'rejected', 'completed', 'cancelled', 'returned') NOT NULL DEFAULT 'pending';

-- Preserve legacy outcomes before restricting the status column.
UPDATE reservations
SET status = CASE
    WHEN status = 'completed' THEN 'returned'
    WHEN status IN ('pending', 'approved') THEN status
    ELSE 'cancelled'
END;

ALTER TABLE reservations
    MODIFY status ENUM('pending', 'approved', 'cancelled', 'returned') NOT NULL DEFAULT 'pending',
    ADD INDEX idx_reservation_user_status (user_id, status),
    ADD INDEX idx_reservation_book_status (book_id, status);

ALTER TABLE books
    MODIFY total_copies INT UNSIGNED NOT NULL DEFAULT 0,
    MODIFY available_copies INT UNSIGNED NOT NULL DEFAULT 0;
