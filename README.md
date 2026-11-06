# Book Bank

PHP/MySQL textbook reservation application for XAMPP.

## Setup

1. Import `schema.sql` into MySQL.
2. Create the least-privilege `book_bank_app` account using the commented commands in `schema.sql`.
3. Set `BOOK_BANK_DB_HOST`, `BOOK_BANK_DB_NAME`, `BOOK_BANK_DB_USER`, and `BOOK_BANK_DB_PASSWORD` in the web-server environment. Do not use the MySQL `root` account in deployed environments.
4. Serve this directory through Apache/XAMPP and open `login.html`.
5. Create an administrator by registering a user and then changing its `role` to `admin` directly in MySQL.

To register an administrator from the app, select **Administrator** on the registration form and enter the `admin_registration_code` configured in `config.local.php`. Change that code before sharing the system.

## Security controls

- Role-protected student and administrator pages and reservation actions.
- HTTP-only, SameSite session cookies; session ID renewal on sign-in; dedicated logout endpoint.
- CSRF validation for reservation, cancellation, and administrator actions.
- Password hashes with a minimum 8-character password policy.
- Transactional inventory updates and a two-active-reservation limit.
- One-hour, single-use password-reset tokens.
- Student-only digital reservation slips with a printable/save-as-PDF view and collection QR reference.
- ISBN-based book-cover fallback through Open Library for catalog records without a saved cover image.

The local password-reset screen returns a reset link to the verified requester for development only. Replace that response with a mail-provider delivery call before making the system public.
