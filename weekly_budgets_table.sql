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

ALTER TABLE transactions
ADD weekly_budget_id INT NULL,
ADD FOREIGN KEY (weekly_budget_id) REFERENCES weekly_budgets(id) ON DELETE SET NULL;
