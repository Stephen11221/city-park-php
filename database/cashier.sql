CREATE TABLE IF NOT EXISTS table_assignments (
    facility_id INT PRIMARY KEY,
    waiter_id INT NOT NULL,
    assigned_by INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_assignment_table FOREIGN KEY (facility_id) REFERENCES facilities(id) ON DELETE CASCADE,
    CONSTRAINT fk_assignment_waiter FOREIGN KEY (waiter_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_assignment_actor FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_key CHAR(64) NOT NULL UNIQUE,
    cashier_id INT NOT NULL,
    cashier_name VARCHAR(100) NOT NULL,
    facility_id INT NULL,
    table_name VARCHAR(150) NOT NULL,
    waiter_id INT NULL,
    waiter_name VARCHAR(100) NULL,
    currency CHAR(3) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    tax_rate_bps INT NOT NULL DEFAULT 0,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL,
    payment_method ENUM('cash','mpesa','card','bank') NOT NULL,
    payment_reference VARCHAR(100) NULL,
    tendered DECIMAL(12,2) NOT NULL,
    change_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('paid','refunded') NOT NULL DEFAULT 'paid',
    paid_at DATETIME NOT NULL,
    refunded_at DATETIME NULL,
    refunded_by INT NULL,
    refund_reason VARCHAR(500) NULL,
    INDEX sales_paid_at (paid_at),
    CONSTRAINT fk_sale_cashier FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sale_table FOREIGN KEY (facility_id) REFERENCES facilities(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sale_waiter FOREIGN KEY (waiter_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sale_refunder FOREIGN KEY (refunded_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    menu_item_id INT NULL,
    item_name VARCHAR(150) NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    line_total DECIMAL(12,2) NOT NULL,
    CONSTRAINT fk_item_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE RESTRICT,
    CONSTRAINT fk_item_menu FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cashier_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_id INT NOT NULL,
    actor_name VARCHAR(100) NOT NULL,
    action VARCHAR(80) NOT NULL,
    details TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cashier_audit_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NULL,
    title VARCHAR(150) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    expense_date DATE NOT NULL,
    payment_method ENUM('cash','mpesa','card','bank') NOT NULL DEFAULT 'cash',
    reference VARCHAR(100) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_expense_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
