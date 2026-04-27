<?php
// ============================================================
//  API HANDLER - api.php  (MySQL version)
// ============================================================
header('Content-Type: application/json');
require_once __DIR__ . '/includes/db.php';

$module = $_GET['module'] ?? '';
$action = $_GET['action'] ?? 'list';
$pdo    = getDB();

// ── DASHBOARD ──────────────────────────────────────────────
if ($module === 'dashboard') {
    $stats = [];

    $stats['total_products']     = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $stats['total_suppliers']    = $pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
    $stats['total_customers']    = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    $stats['total_transactions'] = $pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
    $stats['low_stock_count']    = $pdo->query("SELECT COUNT(*) FROM inventory WHERE stock_level < 30")->fetchColumn();
    $stats['total_revenue']      = $pdo->query("
        SELECT COALESCE(SUM(p.price * t.quantity), 0)
        FROM transactions t
        JOIN products p ON t.product_id = p.product_id
        WHERE t.transaction_type = 'Sale'")->fetchColumn();

    $stats['recent_transactions'] = $pdo->query("
        SELECT t.transaction_id, c.customer_name, p.product_name,
               t.quantity, t.transaction_type, t.date,
               (p.price * t.quantity) AS total
        FROM transactions t
        JOIN products  p ON t.product_id  = p.product_id
        JOIN customers c ON t.customer_id = c.customer_id
        ORDER BY t.created_at DESC LIMIT 5")->fetchAll();

    $stats['category_data'] = $pdo->query("
        SELECT category, COUNT(*) AS count, SUM(price * quantity) AS value
        FROM products
        GROUP BY category
        ORDER BY value DESC")->fetchAll();

    $stats['monthly_sales'] = $pdo->query("
        SELECT DATE_FORMAT(t.date, '%b %Y') AS month,
               SUM(p.price * t.quantity)    AS revenue,
               DATE_FORMAT(t.date, '%Y-%m') AS sort_key
        FROM transactions t
        JOIN products p ON t.product_id = p.product_id
        WHERE t.transaction_type = 'Sale'
        GROUP BY DATE_FORMAT(t.date, '%b %Y'), DATE_FORMAT(t.date, '%Y-%m')
        ORDER BY sort_key DESC
        LIMIT 6")->fetchAll();

    jsonResponse(['success' => true, 'data' => $stats]);
}

// ── SUPPLIERS ──────────────────────────────────────────────
if ($module === 'suppliers') {
    if ($action === 'list') {
        $rows = $pdo->query("
            SELECT s.*, COUNT(p.product_id) AS product_count
            FROM suppliers s
            LEFT JOIN products p ON s.supplier_id = p.supplier_id
            GROUP BY s.supplier_id
            ORDER BY s.supplier_id")->fetchAll();
        jsonResponse(['success' => true, 'data' => $rows]);
    }

    if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $name    = sanitize($_POST['name']    ?? '');
        $contact = sanitize($_POST['contact'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        if (!$name) jsonResponse(['success' => false, 'error' => 'Name is required'], 400);

        $stmt = $pdo->prepare("INSERT INTO suppliers (name, contact, address) VALUES (?, ?, ?)");
        $stmt->execute([$name, $contact, $address]);
        jsonResponse(['success' => true, 'message' => 'Supplier added!', 'id' => $pdo->lastInsertId()]);
    }

    if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['supplier_id'] ?? 0);
        $pdo->prepare("UPDATE suppliers SET name=?, contact=?, address=? WHERE supplier_id=?")
            ->execute([sanitize($_POST['name'] ?? ''), sanitize($_POST['contact'] ?? ''), sanitize($_POST['address'] ?? ''), $id]);
        jsonResponse(['success' => true, 'message' => 'Supplier updated!']);
    }

    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo->prepare("DELETE FROM suppliers WHERE supplier_id=?")->execute([(int)$_POST['supplier_id']]);
        jsonResponse(['success' => true, 'message' => 'Supplier deleted!']);
    }
}

// ── CUSTOMERS ──────────────────────────────────────────────
if ($module === 'customers') {
    if ($action === 'list') {
        $rows = $pdo->query("
            SELECT c.*, COUNT(t.transaction_id) AS transaction_count
            FROM customers c
            LEFT JOIN transactions t ON c.customer_id = t.customer_id
            GROUP BY c.customer_id
            ORDER BY c.customer_id")->fetchAll();
        jsonResponse(['success' => true, 'data' => $rows]);
    }

    if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $name  = sanitize($_POST['customer_name'] ?? '');
        $email = sanitize($_POST['email']         ?? '');
        $phone = sanitize($_POST['phone']         ?? '');
        if (!$name) jsonResponse(['success' => false, 'error' => 'Name is required'], 400);

        $stmt = $pdo->prepare("INSERT INTO customers (customer_name, email, phone) VALUES (?, ?, ?)");
        $stmt->execute([$name, $email, $phone]);
        jsonResponse(['success' => true, 'message' => 'Customer added!', 'id' => $pdo->lastInsertId()]);
    }

    if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['customer_id'] ?? 0);
        $pdo->prepare("UPDATE customers SET customer_name=?, email=?, phone=? WHERE customer_id=?")
            ->execute([sanitize($_POST['customer_name'] ?? ''), sanitize($_POST['email'] ?? ''), sanitize($_POST['phone'] ?? ''), $id]);
        jsonResponse(['success' => true, 'message' => 'Customer updated!']);
    }

    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo->prepare("DELETE FROM customers WHERE customer_id=?")->execute([(int)$_POST['customer_id']]);
        jsonResponse(['success' => true, 'message' => 'Customer deleted!']);
    }
}

// ── PRODUCTS ───────────────────────────────────────────────
if ($module === 'products') {
    if ($action === 'list') {
        $rows = $pdo->query("
            SELECT p.*, s.name AS supplier_name,
                   COALESCE(i.stock_level, 0) AS stock_level
            FROM products p
            LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
            LEFT JOIN inventory i ON p.product_id  = i.product_id
            ORDER BY p.product_id")->fetchAll();
        jsonResponse(['success' => true, 'data' => $rows]);
    }

    if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $name     = sanitize($_POST['product_name'] ?? '');
        $category = sanitize($_POST['category']     ?? '');
        $price    = (float)($_POST['price']         ?? 0);
        $quantity = (int)($_POST['quantity']        ?? 0);
        $suppId   = (int)($_POST['supplier_id']     ?? 0) ?: null;
        if (!$name) jsonResponse(['success' => false, 'error' => 'Product name required'], 400);

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO products (product_name, category, price, quantity, supplier_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $category, $price, $quantity, $suppId]);
        $pid = $pdo->lastInsertId();

        $pdo->prepare("INSERT INTO inventory (product_id, stock_level) VALUES (?, ?)")->execute([$pid, $quantity]);
        $pdo->commit();
        jsonResponse(['success' => true, 'message' => 'Product added!', 'id' => $pid]);
    }

    if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id       = (int)($_POST['product_id']      ?? 0);
        $name     = sanitize($_POST['product_name'] ?? '');
        $category = sanitize($_POST['category']     ?? '');
        $price    = (float)($_POST['price']         ?? 0);
        $quantity = (int)($_POST['quantity']        ?? 0);
        $suppId   = (int)($_POST['supplier_id']     ?? 0) ?: null;

        $pdo->prepare("UPDATE products SET product_name=?, category=?, price=?, quantity=?, supplier_id=? WHERE product_id=?")
            ->execute([$name, $category, $price, $quantity, $suppId, $id]);
        $pdo->prepare("UPDATE inventory SET stock_level=? WHERE product_id=?")
            ->execute([$quantity, $id]);
        jsonResponse(['success' => true, 'message' => 'Product updated!']);
    }

    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo->prepare("DELETE FROM products WHERE product_id=?")->execute([(int)$_POST['product_id']]);
        jsonResponse(['success' => true, 'message' => 'Product deleted!']);
    }
}

// ── TRANSACTIONS ───────────────────────────────────────────
if ($module === 'transactions') {
    if ($action === 'list') {
        $rows = $pdo->query("
            SELECT t.*, p.product_name, p.price, c.customer_name,
                   (p.price * t.quantity) AS total_amount
            FROM transactions t
            JOIN products  p ON t.product_id  = p.product_id
            JOIN customers c ON t.customer_id = c.customer_id
            ORDER BY t.created_at DESC")->fetchAll();
        jsonResponse(['success' => true, 'data' => $rows]);
    }

    if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $pid  = (int)($_POST['product_id']         ?? 0);
        $cid  = (int)($_POST['customer_id']        ?? 0);
        $qty  = (int)($_POST['quantity']           ?? 0);
        $type = sanitize($_POST['transaction_type'] ?? '');
        $date = sanitize($_POST['date']             ?? date('Y-m-d'));

        if (!$pid || !$cid || $qty <= 0 || !$type)
            jsonResponse(['success' => false, 'error' => 'All fields are required'], 400);

        // Stock check for Sales
        if ($type === 'Sale') {
            $stmt = $pdo->prepare("SELECT stock_level FROM inventory WHERE product_id = ?");
            $stmt->execute([$pid]);
            $level = $stmt->fetchColumn();
            if ($level === false || $level < $qty)
                jsonResponse(['success' => false, 'error' => "Insufficient stock. Available: " . ($level ?: 0)], 400);
        }

        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO transactions (product_id, customer_id, date, quantity, transaction_type) VALUES (?, ?, ?, ?, ?)")
            ->execute([$pid, $cid, $date, $qty, $type]);

        if ($type === 'Sale') {
            $pdo->prepare("UPDATE inventory SET stock_level = stock_level - ? WHERE product_id = ?")->execute([$qty, $pid]);
        } elseif (in_array($type, ['Purchase', 'Return'])) {
            $pdo->prepare("UPDATE inventory SET stock_level = stock_level + ? WHERE product_id = ?")->execute([$qty, $pid]);
        }
        $pdo->commit();
        jsonResponse(['success' => true, 'message' => 'Transaction recorded!']);
    }

    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo->prepare("DELETE FROM transactions WHERE transaction_id=?")->execute([(int)$_POST['transaction_id']]);
        jsonResponse(['success' => true, 'message' => 'Transaction deleted!']);
    }
}

// ── INVENTORY ──────────────────────────────────────────────
if ($module === 'inventory') {
    if ($action === 'list') {
        $rows = $pdo->query("
            SELECT i.*, p.product_name, p.category, p.price,
                   s.name AS supplier_name,
                   CASE
                     WHEN i.stock_level = 0  THEN 'Out of Stock'
                     WHEN i.stock_level < 30 THEN 'Low Stock'
                     ELSE 'In Stock'
                   END AS stock_status
            FROM inventory i
            JOIN products  p ON i.product_id  = p.product_id
            LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
            ORDER BY i.stock_level ASC")->fetchAll();
        jsonResponse(['success' => true, 'data' => $rows]);
    }
}

jsonResponse(['success' => false, 'error' => 'Invalid request'], 400);
