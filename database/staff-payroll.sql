CREATE TABLE IF NOT EXISTS staff_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    pay_basis ENUM('daily','monthly') NOT NULL,
    period_start DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('allocated','paid','cancelled') NOT NULL DEFAULT 'allocated',
    paid_on DATE NULL,
    reference VARCHAR(100) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY staff_pay_period_unique (staff_id, pay_basis, period_start),
    CONSTRAINT fk_staff_payment_user FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100) NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(20) NULL,
    address VARCHAR(255) NULL,
    supplies VARCHAR(255) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
