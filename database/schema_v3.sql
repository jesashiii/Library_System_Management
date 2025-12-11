-- Smart Library Database Schema v3 - Enhanced with Semester System
CREATE DATABASE IF NOT EXISTS smart_library;
USE smart_library;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'teacher', 'librarian', 'staff') NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    student_id VARCHAR(20) UNIQUE NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Semesters table
CREATE TABLE semesters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_current BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Books table
CREATE TABLE books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    isbn VARCHAR(20) UNIQUE NULL,
    category VARCHAR(100),
    price DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('available', 'borrowed', 'reserved', 'archived') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Transactions table (borrowing/returning)
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    semester_id INT NOT NULL,
    transaction_type ENUM('borrow', 'return') NOT NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    due_date DATE,
    return_date DATE NULL,
    status ENUM('active', 'completed', 'overdue', 'lost') DEFAULT 'active',
    penalty_amount DECIMAL(10,2) DEFAULT 0.00,
    penalty_paid BOOLEAN DEFAULT FALSE,
    book_price_paid DECIMAL(10,2) DEFAULT 0.00,
    book_price_paid_boolean BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE
);

-- Reservations table
CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    semester_id INT NOT NULL,
    reservation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'fulfilled', 'cancelled') DEFAULT 'active',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE
);

-- Penalty rates table
CREATE TABLE penalty_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_role ENUM('student', 'teacher', 'librarian', 'staff') NOT NULL,
    daily_penalty DECIMAL(10,2) NOT NULL,
    max_penalty DECIMAL(10,2) NOT NULL,
    grace_period_days INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Fines table
CREATE TABLE fines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    transaction_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reason ENUM('overdue', 'damage', 'lost', 'book_price') NOT NULL,
    status ENUM('pending', 'paid', 'waived') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE
);

-- Clearance table
CREATE TABLE clearances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    semester_id INT NOT NULL,
    status ENUM('pending', 'cleared', 'blocked') DEFAULT 'pending',
    cleared_at TIMESTAMP NULL,
    cleared_by INT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
    FOREIGN KEY (cleared_by) REFERENCES users(id) ON DELETE SET NULL
);

-- System settings table
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert sample users for testing
INSERT INTO users (username, password, role, first_name, last_name, email, student_id) VALUES
('librarian1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'librarian', 'John', 'Smith', 'john.smith@library.com', NULL),
('staff1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 'Jane', 'Doe', 'jane.doe@library.com', NULL),
('teacher1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 'Dr. Sarah', 'Johnson', 'sarah.johnson@school.com', NULL),
('student1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'Mike', 'Wilson', 'mike.wilson@student.com', '1349802'),
('student2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'Sarah', 'Brown', 'sarah.brown@student.com', '1349803'),
('student3', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'John', 'Davis', 'john.davis@student.com', '1349804'),
('student4', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'Emily', 'Garcia', 'emily.garcia@student.com', '1349805'),
('student5', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'Michael', 'Johnson', 'michael.johnson@student.com', '1349806');

-- Insert current semester
INSERT INTO semesters (name, start_date, end_date, is_current) VALUES
('First Semester 2025', '2025-01-27', '2025-05-11', TRUE);

-- Insert sample books with prices
INSERT INTO books (title, author, isbn, category, price, status) VALUES
('Introduction to Programming', 'John Doe', '978-1234567890', 'Computer Science', 500.00, 'available'),
('Database Design', 'Jane Smith', '978-1234567891', 'Computer Science', 600.00, 'available'),
('Web Development', 'Bob Johnson', '978-1234567892', 'Computer Science', 550.00, 'available'),
('Data Structures', 'Alice Brown', '978-1234567893', 'Computer Science', 700.00, 'available'),
('Software Engineering', 'Charlie Davis', '978-1234567894', 'Computer Science', 800.00, 'available'),
('Advanced Mathematics', 'Dr. Emily White', '978-1234567895', 'Mathematics', 450.00, 'available'),
('Physics Fundamentals', 'Prof. David Lee', '978-1234567896', 'Physics', 650.00, 'available'),
('History of Science', 'Dr. Maria Garcia', '978-1234567897', 'History', 400.00, 'available'),
('Machine Learning Basics', 'Dr. Alex Chen', '978-1234567898', 'Computer Science', 750.00, 'available'),
('Calculus Advanced', 'Prof. Lisa Wang', '978-1234567899', 'Mathematics', 600.00, 'available'),
('Chemistry Principles', 'Dr. Robert Kim', '978-1234567900', 'Chemistry', 550.00, 'available'),
('English Literature', 'Prof. Maria Rodriguez', '978-1234567901', 'Literature', 400.00, 'available');

-- Insert penalty rates (in Philippine Peso)
INSERT INTO penalty_rates (user_role, daily_penalty, max_penalty, grace_period_days) VALUES
('student', 10.00, 500.00, 3),
('teacher', 5.00, 250.00, 5),
('librarian', 0.00, 0.00, 0),
('staff', 0.00, 0.00, 0);

-- Insert system settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('student_borrow_limit_per_semester', '3', 'Maximum number of books a student can borrow per semester'),
('teacher_borrow_limit', '999', 'Maximum number of books a teacher can borrow'),
('student_borrow_days', '14', 'Number of days students can keep books'),
('teacher_borrow_days', '30', 'Number of days teachers can keep books'),
('system_name', 'Smart Library System', 'Name of the library system'),
('system_version', '3.0', 'Current system version');

-- Insert sample transactions for testing penalties and overdue books
INSERT INTO transactions (user_id, book_id, semester_id, transaction_type, transaction_date, due_date, status, penalty_amount) VALUES
-- Overdue books (borrowed 20 days ago, due 6 days ago)
(4, 1, 1, 'borrow', '2025-01-07 10:00:00', '2025-01-21', 'overdue', 60.00),
(5, 2, 1, 'borrow', '2025-01-08 11:00:00', '2025-01-22', 'overdue', 60.00),
(6, 3, 1, 'borrow', '2025-01-09 12:00:00', '2025-01-23', 'overdue', 60.00),
-- Recently borrowed books (still within due date)
(7, 4, 1, 'borrow', '2025-01-20 09:00:00', '2025-02-03', 'active', 0.00),
(8, 5, 1, 'borrow', '2025-01-21 10:00:00', '2025-02-04', 'active', 0.00);

-- Update book statuses for borrowed books
UPDATE books SET status = 'borrowed' WHERE id IN (1, 2, 3, 4, 5);

-- Insert sample fines for testing
INSERT INTO fines (user_id, transaction_id, amount, reason, status, created_at) VALUES
(4, 1, 60.00, 'overdue', 'pending', '2025-01-28 00:00:00'),
(5, 2, 60.00, 'overdue', 'pending', '2025-01-28 00:00:00'),
(6, 3, 60.00, 'overdue', 'pending', '2025-01-28 00:00:00');

-- Insert sample clearances for testing
INSERT INTO clearances (user_id, semester_id, status, notes) VALUES
(4, 1, 'blocked', 'Has overdue books and unpaid fines'),
(5, 1, 'blocked', 'Has overdue books and unpaid fines'),
(6, 1, 'blocked', 'Has overdue books and unpaid fines'),
(7, 1, 'pending', 'No issues'),
(8, 1, 'pending', 'No issues');

-- Note: Password for all users is 'password' (hashed using password_hash())
