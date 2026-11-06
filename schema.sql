CREATE DATABASE IF NOT EXISTS book_bank_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE book_bank_db;

CREATE TABLE IF NOT EXISTS users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    student_id VARCHAR(60) NOT NULL UNIQUE,
    department ENUM('cse', 'ee', 'me') NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'admin') NOT NULL DEFAULT 'student',
    reset_token CHAR(64) NULL UNIQUE,
    reset_token_expires DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS books (
    book_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    department ENUM('cse', 'ee', 'me') NOT NULL,
    isbn VARCHAR(32) NOT NULL UNIQUE,
    available_copies INT UNSIGNED NOT NULL DEFAULT 0,
    total_copies INT UNSIGNED NOT NULL DEFAULT 0,
    cover_image VARCHAR(2048) NULL,
    CONSTRAINT chk_book_inventory CHECK (available_copies <= total_copies)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reservations (
    reservation_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    book_id INT UNSIGNED NOT NULL,
    reservation_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'cancelled', 'returned') NOT NULL DEFAULT 'pending',
    CONSTRAINT fk_reservation_user FOREIGN KEY (user_id) REFERENCES users(user_id),
    CONSTRAINT fk_reservation_book FOREIGN KEY (book_id) REFERENCES books(book_id),
    INDEX idx_reservation_user_status (user_id, status),
    INDEX idx_reservation_book_status (book_id, status)
) ENGINE=InnoDB;

-- Create a least-privilege account, then configure its password via BOOK_BANK_DB_PASSWORD.
-- CREATE USER 'book_bank_app'@'localhost' IDENTIFIED BY 'replace-with-a-strong-password';
-- GRANT SELECT, INSERT, UPDATE ON book_bank_db.* TO 'book_bank_app'@'localhost';
-- FLUSH PRIVILEGES;
