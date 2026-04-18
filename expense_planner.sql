CREATE DATABASE IF NOT EXISTS expense_planner;
USE expense_planner;

DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS weekly_budgets;
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

CREATE TABLE weekly_budgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    week_start DATE NOT NULL,
    week_end DATE NOT NULL,
    weekly_limit DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NULL,
    budget_id INT NULL,
    weekly_budget_id INT NULL,
    title VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('income', 'expense') NOT NULL,
    transaction_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE SET NULL,
    FOREIGN KEY (weekly_budget_id) REFERENCES weekly_budgets(id) ON DELETE SET NULL
);

INSERT INTO users (id, full_name, email, password) VALUES
(1, 'Bright Office Services', 'admin@budgetplanner.com', '$2y$10$mGOurOYbScDgdWDp3jxwYOJoSslKKIPMOTFmncyK26A.belxSriP.'),
(2, 'Green Mart Traders', 'demo@budgetplanner.com', '$2y$10$uZWts.DxcEsp1wj1YWHIt.mjnDYPW5hAmEu87TjIBXBocHAXtS5HC');

INSERT INTO categories (id, user_id, name) VALUES
(1, 1, 'Sales Income'),
(2, 1, 'Office Supplies'),
(3, 1, 'Travel'),
(4, 1, 'Utilities'),
(5, 1, 'Maintenance'),
(6, 2, 'Sales Income'),
(7, 2, 'Stock Purchase'),
(8, 2, 'Transport'),
(9, 2, 'Electricity');

INSERT INTO budgets (id, user_id, title, monthly_limit) VALUES
(1, 1, 'No Budget', 999999.00),
(2, 1, 'Office Supplies Budget', 10000.00),
(3, 1, 'Travel Budget', 15000.00),
(4, 1, 'Utilities Budget', 8000.00),
(5, 2, 'No Budget', 999999.00),
(6, 2, 'Stock Purchase Budget', 40000.00),
(7, 2, 'Transport Budget', 12000.00),
(8, 2, 'Electricity Budget', 7000.00);

INSERT INTO weekly_budgets (id, user_id, title, week_start, week_end, weekly_limit) VALUES
(1, 1, 'Week 1 Office Spending', '2026-04-01', '2026-04-07', 6000.00),
(2, 1, 'Week 2 Office Spending', '2026-04-08', '2026-04-14', 7000.00),
(3, 2, 'Week 1 Shop Spending', '2026-04-01', '2026-04-07', 12000.00);

INSERT INTO transactions (user_id, category_id, budget_id, weekly_budget_id, title, amount, type, transaction_date) VALUES
(1, 1, NULL, NULL, 'Client Payment Received', 65000.00, 'income', '2026-04-02'),
(1, 2, 2, 1, 'Printer Paper and Files', 2500.00, 'expense', '2026-04-03'),
(1, 3, 3, 1, 'Staff Travel Reimbursement', 4200.00, 'expense', '2026-04-05'),
(1, 4, 4, 2, 'Internet Bill', 1800.00, 'expense', '2026-04-08'),
(1, 5, 2, 2, 'Office Chair Repair', 1200.00, 'expense', '2026-04-11'),
(2, 6, NULL, NULL, 'Daily Sales Collection', 85000.00, 'income', '2026-04-02'),
(2, 7, 6, 3, 'New Stock Purchase', 18500.00, 'expense', '2026-04-03'),
(2, 8, 7, 3, 'Goods Transport Charge', 3500.00, 'expense', '2026-04-04'),
(2, 9, 8, NULL, 'Electricity Bill', 2600.00, 'expense', '2026-04-09');
