CREATE DATABASE IF NOT EXISTS library_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE library_db;

-- ---------------------------------------------------------------
-- Users Table (Admins & Members)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100)  NOT NULL,
    email        VARCHAR(150)  NOT NULL UNIQUE,
    password     VARCHAR(255)  NOT NULL,           -- bcrypt hash
    role         ENUM('admin','member') NOT NULL DEFAULT 'member',
    is_active    TINYINT(1)    NOT NULL DEFAULT 1,
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Books Table
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS books (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(200)  NOT NULL,
    author       VARCHAR(150)  NOT NULL,
    isbn         VARCHAR(20)   NOT NULL UNIQUE,
    genre        VARCHAR(80)   NOT NULL,
    quantity     INT UNSIGNED  NOT NULL DEFAULT 1,
    available    INT UNSIGNED  NOT NULL DEFAULT 1,
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Borrowing Records
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS borrowings (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED  NOT NULL,
    book_id      INT UNSIGNED  NOT NULL,
    borrowed_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    due_date     DATETIME      NOT NULL,
    returned_at  DATETIME      NULL DEFAULT NULL,
    status       ENUM('borrowed','returned','overdue') NOT NULL DEFAULT 'borrowed',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Audit / Activity Log
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED  NULL,
    action       VARCHAR(100)  NOT NULL,
    detail       TEXT          NULL,
    ip_address   VARCHAR(45)   NOT NULL,
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Login Attempts (brute-force protection)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email        VARCHAR(150)  NOT NULL,
    ip_address   VARCHAR(45)   NOT NULL,
    attempted_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    success      TINYINT(1)    NOT NULL DEFAULT 0,
    INDEX idx_email_ip (email, ip_address),
    INDEX idx_attempted_at (attempted_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Seed Data
-- ---------------------------------------------------------------
-- Default admin: admin@library.com / Admin@1234
INSERT INTO users (name, email, password, role) VALUES
('Admin User', 'admin@library.com', '$2y$10$Fi4A4gwAS45HxDNC9NVCyeq6vUtxbRXopyqZeUH/e3hOAAYjsvOeu', 'admin');

-- Sample books
INSERT INTO books (title, author, isbn, genre, quantity, available) VALUES
('Clean Code',                    'Robert C. Martin',  '9780132350884', 'Technology',  3, 3),
('The Pragmatic Programmer',      'David Thomas',      '9780135957059', 'Technology',  2, 2),
('Introduction to Algorithms',    'Thomas H. Cormen',  '9780262046305', 'Technology',  2, 2),
('To Kill a Mockingbird',         'Harper Lee',        '9780061743528', 'Fiction',     4, 4),
('1984',                          'George Orwell',     '9780451524935', 'Fiction',     3, 3),
('Sapiens',                       'Yuval Noah Harari', '9780062316097', 'Non-Fiction', 2, 2),
('The Great Gatsby',              'F. Scott Fitzgerald','9780743273565','Fiction',     3, 3),
('Design Patterns',               'Gang of Four',      '9780201633610', 'Technology',  2, 2);
