-- Bonus Allocation Table
CREATE TABLE IF NOT EXISTS bonuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    bonus_amount DECIMAL(10,2) NOT NULL,
    bonus_reason TEXT NOT NULL,
    pay_period_start DATE NOT NULL,
    pay_period_end DATE NOT NULL,
    allocated_by INT NOT NULL,
    allocated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    processed_by INT DEFAULT NULL,
    processed_at TIMESTAMP NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    FOREIGN KEY (allocated_by) REFERENCES users(user_id),
    FOREIGN KEY (processed_by) REFERENCES users(user_id),
    INDEX idx_employee_id (employee_id),
    INDEX idx_allocated_by (allocated_by),
    INDEX idx_status (status),
    INDEX idx_pay_period (pay_period_start, pay_period_end)
); 