CREATE DATABASE IF NOT EXISTS expense_planner;
USE expense_planner;

DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS budgets;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE budgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    monthly_limit DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NULL,
    budget_id INT NULL,
    title VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('income', 'expense') NOT NULL,
    transaction_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE SET NULL
);

INSERT INTO users (id, full_name, email, password) VALUES
(1, 'Admin User', 'admin@expenseplanner.com', '$2y$10$mGOurOYbScDgdWDp3jxwYOJoSslKKIPMOTFmncyK26A.belxSriP.'),
(2, 'Student Demo', 'student@expenseplanner.com', '$2y$10$uZWts.DxcEsp1wj1YWHIt.mjnDYPW5hAmEu87TjIBXBocHAXtS5HC');

INSERT INTO categories (user_id, name) VALUES
(1, 'Groceries'),
(1, 'Transport'),
(1, 'Salary'),
(1, 'Bills'),
(2, 'Food'),
(2, 'Travel'),
(2, 'Pocket Money');

INSERT INTO budgets (id, user_id, title, monthly_limit) VALUES
(1, 1, 'Home Budget', 2000.00),
(2, 1, 'Travel Budget', 900.00),
(3, 1, 'Bills Budget', 1500.00),
(4, 1, 'No Budget', 999999.00),
(5, 2, 'College Expenses', 3000.00),
(6, 2, 'Personal Spending', 1200.00),
(7, 2, 'No Budget', 999999.00);

INSERT INTO transactions (user_id, category_id, budget_id, title, amount, type, transaction_date) VALUES
(1, 3, NULL, 'Monthly Salary', 5600.00, 'income', '2026-03-30'),
(1, 1, 1, 'Whole Foods Market', 138.20, 'expense', '2026-04-01'),
(1, 2, 2, 'Uber Ride', 24.70, 'expense', '2026-03-29'),
(1, 4, 3, 'Electricity Bill', 450.00, 'expense', '2026-03-27'),
(1, 4, 3, 'Internet Bill', 900.00, 'expense', '2026-03-25'),
(2, 7, NULL, 'Pocket Money Received', 5000.00, 'income', '2026-04-01'),
(2, 5, 5, 'Canteen Lunch', 120.00, 'expense', '2026-04-01'),
(2, 6, 6, 'Bus Pass', 300.00, 'expense', '2026-03-30');
