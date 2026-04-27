-- ============================================================
--   INVENTORY MANAGEMENT SYSTEM - MySQL Schema
--   Import this in phpMyAdmin
-- ============================================================

CREATE DATABASE IF NOT EXISTS inventory_db;
USE inventory_db;

-- Drop tables in reverse dependency order
DROP TABLE IF EXISTS inventory;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS customers;

-- ============================================================
-- TABLE: Supplier
-- ============================================================
CREATE TABLE suppliers (
    supplier_id   INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    contact       VARCHAR(50),
    address       VARCHAR(255),
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABLE: Customer
-- ============================================================
CREATE TABLE customers (
    customer_id   INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL,
    email         VARCHAR(100) UNIQUE,
    phone         VARCHAR(20),
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABLE: Product
-- ============================================================
CREATE TABLE products (
    product_id    INT AUTO_INCREMENT PRIMARY KEY,
    product_name  VARCHAR(100) NOT NULL,
    category      VARCHAR(50),
    price         DECIMAL(10,2) NOT NULL CHECK (price >= 0),
    quantity      INT NOT NULL DEFAULT 0,
    supplier_id   INT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id)
        ON DELETE SET NULL ON UPDATE CASCADE
);

-- ============================================================
-- TABLE: Transactions
-- ============================================================
CREATE TABLE transactions (
    transaction_id   INT AUTO_INCREMENT PRIMARY KEY,
    product_id       INT NOT NULL,
    customer_id      INT NOT NULL,
    date             DATE NOT NULL DEFAULT (CURRENT_DATE),
    quantity         INT NOT NULL CHECK (quantity > 0),
    transaction_type ENUM('Sale','Purchase','Return','Transfer') NOT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id)  REFERENCES products(product_id)  ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- ============================================================
-- TABLE: Inventory
-- ============================================================
CREATE TABLE inventory (
    inventory_id  INT AUTO_INCREMENT PRIMARY KEY,
    product_id    INT NOT NULL UNIQUE,
    stock_level   INT NOT NULL DEFAULT 0,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- ============================================================
-- SAMPLE DATA
-- ============================================================
INSERT INTO suppliers (name, contact, address) VALUES
('TechSource Pvt Ltd',  '9876543210', '12, MG Road, Bengaluru'),
('QuickSupply Co.',     '9123456780', '45, Anna Salai, Chennai'),
('GlobalGoods Inc.',    '9001234567', '78, Nehru Place, New Delhi');

INSERT INTO customers (customer_name, email, phone) VALUES
('Arjun Sharma',  'arjun.sharma@gmail.com',  '9988776655'),
('Priya Nair',    'priya.nair@gmail.com',    '9876501234'),
('Ravi Kumar',    'ravi.kumar@yahoo.com',    '9001122334'),
('Sneha Reddy',   'sneha.reddy@gmail.com',   '9112233445');

INSERT INTO products (product_name, category, price, quantity, supplier_id) VALUES
('Laptop Dell XPS 15',   'Electronics',  85000.00, 50,  1),
('Wireless Mouse',       'Accessories',   1500.00, 200, 1),
('USB-C Hub',            'Accessories',   2500.00, 150, 2),
('Office Chair',         'Furniture',    12000.00, 30,  2),
('A4 Paper Ream (500)',  'Stationery',     400.00, 500, 3),
('Printer HP LaserJet',  'Electronics',  25000.00, 20,  3);

INSERT INTO inventory (product_id, stock_level) VALUES
(1,50),(2,200),(3,150),(4,30),(5,500),(6,20);

INSERT INTO transactions (product_id, customer_id, date, quantity, transaction_type) VALUES
(1, 1, '2025-01-10', 2,  'Sale'),
(2, 2, '2025-01-12', 5,  'Sale'),
(3, 1, '2025-01-15', 3,  'Sale'),
(4, 3, '2025-02-01', 1,  'Purchase'),
(5, 4, '2025-02-05', 10, 'Sale'),
(6, 2, '2025-02-20', 1,  'Sale');
