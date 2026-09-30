-- City Park Management schema. Run scripts/setup-database.php to select/create the configured database.

CREATE TABLE IF NOT EXISTS `users` (
  job_title VARCHAR(100) NULL,
  hired_on DATE NULL,
  terminated_on DATE NULL,
  termination_reason TEXT NULL,
  employment_status ENUM('employed','dismissed') NOT NULL DEFAULT 'employed',
  daily_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  monthly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `demo_key` varchar(80) DEFAULT NULL,
  UNIQUE KEY `users_demo_key_unique` (`demo_key`),
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff','customer','cashier') DEFAULT 'customer',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `parks` (
  `demo_key` varchar(80) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  UNIQUE KEY `parks_demo_key_unique` (`demo_key`),
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `park_name` varchar(150) NOT NULL,
  `location` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `opening_time` time DEFAULT NULL,
  `closing_time` time DEFAULT NULL,
  `status` enum('open','closed','maintenance') DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `facilities` (
  `kind` enum('facility','table') NOT NULL DEFAULT 'facility',
  `demo_key` varchar(80) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  UNIQUE KEY `facilities_demo_key_unique` (`demo_key`),
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `park_id` int(11) NOT NULL,
  `facility_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `capacity` int(11) DEFAULT 0,
  `price` decimal(10,2) DEFAULT 0.00,
  `status` enum('available','unavailable','maintenance') DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_facility_park` (`park_id`),
  CONSTRAINT `fk_facility_park` FOREIGN KEY (`park_id`) REFERENCES `parks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `bookings` (
  `demo_key` varchar(80) DEFAULT NULL,
  UNIQUE KEY `bookings_demo_key_unique` (`demo_key`),
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `facility_id` int(11) NOT NULL,
  `booking_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `number_of_people` int(11) DEFAULT 1,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending','approved','rejected','cancelled','completed') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_booking_user` (`user_id`),
  KEY `fk_booking_facility` (`facility_id`),
  CONSTRAINT `fk_booking_facility` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_booking_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `payments` (
  `demo_key` varchar(80) DEFAULT NULL,
  UNIQUE KEY `payments_demo_key_unique` (`demo_key`),
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `transaction_reference` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','mpesa','card','bank') DEFAULT 'mpesa',
  `payment_status` enum('pending','paid','failed','refunded') DEFAULT 'pending',
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction_reference` (`transaction_reference`),
  KEY `fk_payment_booking` (`booking_id`),
  CONSTRAINT `fk_payment_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `maintenance` (
  `demo_key` varchar(80) DEFAULT NULL,
  UNIQUE KEY `maintenance_demo_key_unique` (`demo_key`),
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `facility_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `reported_by` int(11) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('reported','in_progress','completed') DEFAULT 'reported',
  `reported_date` date NOT NULL,
  `completed_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_maintenance_facility` (`facility_id`),
  KEY `fk_maintenance_reporter` (`reported_by`),
  KEY `fk_maintenance_staff` (`assigned_to`),
  CONSTRAINT `fk_maintenance_facility` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_maintenance_reporter` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_maintenance_staff` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `demo_key` varchar(80) DEFAULT NULL,
  UNIQUE KEY `activity_logs_demo_key_unique` (`demo_key`),
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_activity_user` (`user_id`),
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS menu_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_name VARCHAR(150) NOT NULL,
  category ENUM('meals','snacks','drinks','desserts') NOT NULL DEFAULT 'meals',
  description TEXT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  image_path VARCHAR(255) NULL,
  status ENUM('available','unavailable') NOT NULL DEFAULT 'available',
  demo_key VARCHAR(80) NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
