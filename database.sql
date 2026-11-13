-- Task Tracker Database Setup
-- Level 1 + 2 + 3

CREATE DATABASE IF NOT EXISTS task_tracker;
USE task_tracker;

-- Users table
DROP TABLE IF EXISTS tasks;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tasks table (linked to users)
CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('Pending', 'In Progress', 'Completed') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Pre-created test users
-- Login: admin / admin123 (role: admin - can view all users' tasks)
-- Login: testuser / test123 (role: user - can only manage own tasks)
INSERT INTO users (username, password, role) VALUES
('admin', '$2y$12$dwROl8/8..j0zpxOI/0IjeKYQHp70UHnF8vqajpRrTMfSinlY3EX2', 'admin'),
('testuser', '$2y$12$BVRLoJtFa5DULnDBtSA2MONey1ccvBqwuQLzjmD/Df/Yf.4etGQ.e', 'user');

-- Sample tasks for testuser (user_id = 2)
INSERT INTO tasks (user_id, title, description, status) VALUES
(2, 'Sample Task 1', 'This is a sample task.', 'Pending'),
(2, 'Sample Task 2', 'Another task for testing.', 'In Progress');
