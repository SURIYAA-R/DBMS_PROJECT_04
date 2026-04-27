<?php
// ============================================================
//  DATABASE CONFIGURATION - MySQL (XAMPP)
//  AUTO CREATES database + tables if they don't exist
// ============================================================
define('DB_HOST',     'localhost');
define('DB_NAME',     'inventory_db');
define('DB_USER',     'root');
define('DB_PASSWORD', '');   // XAMPP default = no password

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            // Step 1: Connect WITHOUT selecting a database first
            $pdoInit = new PDO(
                "mysql:host=" . DB_HOST . ";charset=utf8mb4",
                DB_USER,
                DB_PASSWORD,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Step 2: Create database if it doesn't exist
            $pdoInit->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            // Step 3: Now connect to the database
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASSWORD,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );

            // Step 4: Create all tables if they don't exist
            createTables($pdo);

        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

function createTables(PDO $pdo): void {

    // --- Suppliers ---
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS suppliers (
            supplier_id  INT AUTO_INCREMENT PRIMARY KEY,
            name         VARCHAR(100) NOT NULL,
            contact      VARCHAR(50),
            address      VARCHAR(255),
            created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");

    // --- Customers ---
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS customers (
            customer_id   INT AUTO_INCREMENT PRIMARY KEY,
            customer_name VARCHAR(100) NOT NULL,
            email         VARCHAR(100) UNIQUE,
            phone         VARCHAR(20),
            created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");

    // --- Products ---
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            product_id   INT AUTO_INCREMENT PRIMARY KEY,
            product_name VARCHAR(100) NOT NULL,
            category     VARCHAR(50),
            price        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            quantity     INT NOT NULL DEFAULT 0,
            supplier_id  INT,
            created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB;
    ");

    // --- Transactions ---
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transactions (
            transaction_id   INT AUTO_INCREMENT PRIMARY KEY,
            product_id       INT NOT NULL,
            customer_id      INT NOT NULL,
            date             DATE NOT NULL,
            quantity         INT NOT NULL DEFAULT 1,
            transaction_type ENUM('Sale','Purchase','Return','Transfer') NOT NULL,
            created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id)  REFERENCES products(product_id)  ON DELETE RESTRICT ON UPDATE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB;
    ");

    // --- Inventory ---
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS inventory (
            inventory_id INT AUTO_INCREMENT PRIMARY KEY,
            product_id   INT NOT NULL UNIQUE,
            stock_level  INT NOT NULL DEFAULT 0,
            updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(product_id)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB;
    ");

    // --- Insert sample data only if tables are empty ---
    $count = $pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
    if ($count == 0) {
        insertSampleData($pdo);
    }
}

function insertSampleData(PDO $pdo): void {

    // Suppliers
    $pdo->exec("
        INSERT INTO suppliers (name, contact, address) VALUES
        ('TechSource Pvt Ltd',  '9876543210', '12, MG Road, Bengaluru'),
        ('QuickSupply Co.',     '9123456780', '45, Anna Salai, Chennai'),
        ('GlobalGoods Inc.',    '9001234567', '78, Nehru Place, New Delhi');
    ");

    // Customers
    $pdo->exec("
        INSERT INTO customers (customer_name, email, phone) VALUES
        ('Arjun Sharma', 'arjun.sharma@gmail.com', '9988776655'),
        ('Priya Nair',   'priya.nair@gmail.com',   '9876501234'),
        ('Ravi Kumar',   'ravi.kumar@yahoo.com',   '9001122334'),
        ('Sneha Reddy',  'sneha.reddy@gmail.com',  '9112233445');
    ");

    // Products
    $pdo->exec("
        INSERT INTO products (product_name, category, price, quantity, supplier_id) VALUES
        ('Laptop Dell XPS 15',  'Electronics', 85000.00, 50,  1),
        ('Wireless Mouse',      'Accessories',  1500.00, 200, 1),
        ('USB-C Hub',           'Accessories',  2500.00, 150, 2),
        ('Office Chair',        'Furniture',   12000.00, 30,  2),
        ('A4 Paper Ream (500)', 'Stationery',    400.00, 500, 3),
        ('Printer HP LaserJet', 'Electronics', 25000.00, 20,  3);
    ");

    // Inventory
    $pdo->exec("
        INSERT INTO inventory (product_id, stock_level) VALUES
        (1,50),(2,200),(3,150),(4,30),(5,500),(6,20);
    ");

    // Transactions
    $pdo->exec("
        INSERT INTO transactions (product_id, customer_id, date, quantity, transaction_type) VALUES
        (1, 1, '2025-01-10', 2,  'Sale'),
        (2, 2, '2025-01-12', 5,  'Sale'),
        (3, 1, '2025-01-15', 3,  'Sale'),
        (4, 3, '2025-02-01', 1,  'Purchase'),
        (5, 4, '2025-02-05', 10, 'Sale'),
        (6, 2, '2025-02-20', 1,  'Sale');
    ");
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function sanitize(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)));
}
