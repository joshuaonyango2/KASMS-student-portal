-- File: kasms_db.sql
CREATE DATABASE IF NOT EXISTS kasms_db;
USE kasms_db;

-- Users table (created first since it’s referenced by other tables)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'instructor', 'hod', 'finance') NOT NULL,
    student_id VARCHAR(20), -- Links to students table
    INDEX idx_username (username)
);

-- Students table
CREATE TABLE students (
    student_id VARCHAR(20) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    department VARCHAR(50) NOT NULL,
    year_of_study INT NOT NULL,
    INDEX idx_student_id (student_id)
);

-- Units table
CREATE TABLE units (
    unit_code VARCHAR(20) PRIMARY KEY,
    unit_name VARCHAR(100) NOT NULL,
    instructor_id INT,
    FOREIGN KEY (instructor_id) REFERENCES users(id),
    INDEX idx_instructor_id (instructor_id)
);

-- Enrollments table
CREATE TABLE enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20),
    unit_code VARCHAR(20),
    semester VARCHAR(20),
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (unit_code) REFERENCES units(unit_code),
    INDEX idx_student_unit (student_id, unit_code)
);

-- Marks table
CREATE TABLE marks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20),
    unit_code VARCHAR(20),
    cat1 INT DEFAULT NULL,
    cat2 INT DEFAULT NULL,
    assignment INT DEFAULT NULL,
    final_exam INT DEFAULT NULL,
    final_grade VARCHAR(2) DEFAULT NULL,
    version INT DEFAULT 0, -- For optimistic locking
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (unit_code) REFERENCES units(unit_code),
    INDEX idx_student_unit_mark (student_id, unit_code)
);

-- Fees table
CREATE TABLE fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20),
    semester VARCHAR(20),
    amount_owed DECIMAL(10, 2) DEFAULT 0.00,
    amount_paid DECIMAL(10, 2) DEFAULT 0.00,
    version INT DEFAULT 0, -- For optimistic locking
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    INDEX idx_student_semester (student_id, semester)
);

-- Exam card table
CREATE TABLE exam_card (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20),
    semester VARCHAR(20),
    eligible BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    INDEX idx_student_semester_card (student_id, semester)
);

-- Sessions table (created last since it references users)
CREATE TABLE sessions (
    id VARCHAR(255) NOT NULL PRIMARY KEY,
    user_id INT,
    data TEXT,
    last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert initial data
INSERT INTO users (username, password, role, student_id) VALUES
('student1', '$2y$10$12jK/9xO8zPqR5tYvL3mLu8e7w9xV6yB1cD2eF3gH4iJ5kL6mN7oP', 'student', 'S001'),
('instructor1', '$2y$10$12jK/9xO8zPqR5tYvL3mLu8e7w9xV6yB1cD2eF3gH4iJ5kL6mN7oP', 'instructor', NULL),
('hod1', '$2y$10$12jK/9xO8zPqR5tYvL3mLu8e7w9xV6yB1cD2eF3gH4iJ5kL6mN7oP', 'hod', NULL),
('finance1', '$2y$10$12jK/9xO8zPqR5tYvL3mLu8e7w9xV6yB1cD2eF3gH4iJ5kL6mN7oP', 'finance', NULL);

INSERT INTO students (student_id, name, department, year_of_study) VALUES
('S001', 'John Doe', 'Computer Science', 2);

INSERT INTO units (unit_code, unit_name, instructor_id) VALUES
('CS101', 'Introduction to Programming', 2);

INSERT INTO enrollments (student_id, unit_code, semester) VALUES
('S001', 'CS101', '2025-S1');

INSERT INTO fees (student_id, semester, amount_owed, amount_paid) VALUES
('S001', '2025-S1', 5000.00, 2000.00);

INSERT INTO exam_card (student_id, semester, eligible) VALUES
('S001', '2025-S1', FALSE);