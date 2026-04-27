<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Database Viewer — InvenTrack</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0b0e14;--surface:#111520;--card:#161b27;--border:#1e2535;
  --accent:#00d4aa;--accent2:#7c6aff;--danger:#ff4f6a;--warning:#ffb84d;
  --text:#e8eaf0;--muted:#6b7385;
  --font-head:'Syne',sans-serif;--font-body:'DM Sans',sans-serif;
}
body{font-family:var(--font-body);background:var(--bg);color:var(--text);min-height:100vh;padding:32px}
h1{font-family:var(--font-head);font-size:28px;font-weight:800;color:var(--accent);margin-bottom:6px}
.subtitle{color:var(--muted);font-size:14px;margin-bottom:30px}
.tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px}
.tab{
  padding:9px 20px;border-radius:9px;cursor:pointer;font-size:13px;font-weight:500;
  background:var(--card);border:1px solid var(--border);color:var(--muted);transition:all .2s;
}
.tab:hover{color:var(--text);border-color:var(--accent)}
.tab.active{background:rgba(0,212,170,.15);color:var(--accent);border-color:var(--accent)}
.table-section{display:none}
.table-section.active{display:block}
.table-card{background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:20px}
.table-header{
  display:flex;align-items:center;justify-content:space-between;
  padding:16px 20px;border-bottom:1px solid var(--border);
}
.table-header h3{font-family:var(--font-head);font-size:15px;font-weight:700}
.row-count{font-size:12px;color:var(--muted);background:var(--surface);padding:3px 10px;border-radius:20px;border:1px solid var(--border)}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse}
thead th{
  text-align:left;padding:11px 14px;
  font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:1px;
  color:var(--muted);background:rgba(255,255,255,.02);border-bottom:1px solid var(--border);
  white-space:nowrap;
}
tbody tr{border-bottom:1px solid rgba(255,255,255,.04);transition:background .15s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:rgba(255,255,255,.03)}
tbody td{padding:11px 14px;font-size:13px;white-space:nowrap}
.pk{color:var(--accent);font-weight:600}
.null-val{color:var(--muted);font-style:italic}
.badge{display:inline-flex;align-items:center;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600}
.badge-sale    {background:rgba(0,212,170,.15);color:var(--accent)}
.badge-purchase{background:rgba(124,106,255,.15);color:var(--accent2)}
.badge-return  {background:rgba(255,184,77,.15);color:var(--warning)}
.badge-transfer{background:rgba(107,115,133,.15);color:var(--muted)}
.empty{text-align:center;padding:40px;color:var(--muted);font-size:14px}
.stats-bar{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:28px}
.stat{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:16px 22px;min-width:140px}
.stat-val{font-family:var(--font-head);font-size:26px;font-weight:800;color:var(--accent)}
.stat-lbl{font-size:12px;color:var(--muted);margin-top:2px}
.back-btn{
  display:inline-flex;align-items:center;gap:8px;
  padding:9px 18px;border-radius:9px;background:var(--card);
  border:1px solid var(--border);color:var(--text);
  text-decoration:none;font-size:13px;font-weight:500;
  transition:all .2s;margin-bottom:24px;
}
.back-btn:hover{border-color:var(--accent);color:var(--accent)}
.error{background:rgba(255,79,106,.1);border:1px solid rgba(255,79,106,.3);color:var(--danger);padding:16px 20px;border-radius:12px;margin-bottom:20px}
</style>
</head>
<body>

<?php
require_once __DIR__ . '/includes/db.php';

try {
    $pdo = getDB();

    // Fetch all tables
    $suppliers    = $pdo->query("SELECT * FROM suppliers    ORDER BY supplier_id")->fetchAll();
    $customers    = $pdo->query("SELECT * FROM customers    ORDER BY customer_id")->fetchAll();
    $products     = $pdo->query("
        SELECT p.*, s.name AS supplier_name FROM products p
        LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
        ORDER BY p.product_id")->fetchAll();
    $transactions = $pdo->query("
        SELECT t.*, p.product_name, c.customer_name,
               (p.price * t.quantity) AS total
        FROM transactions t
        JOIN products p  ON t.product_id  = p.product_id
        JOIN customers c ON t.customer_id = c.customer_id
        ORDER BY t.transaction_id DESC")->fetchAll();
    $inventory    = $pdo->query("
        SELECT i.*, p.product_name, p.category,
               CASE WHEN i.stock_level=0 THEN 'Out of Stock'
                    WHEN i.stock_level<30 THEN 'Low Stock'
                    ELSE 'In Stock' END AS status
        FROM inventory i
        JOIN products p ON i.product_id = p.product_id
        ORDER BY i.stock_level ASC")->fetchAll();

    $error = null;
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<a href="index.html" class="back-btn">← Back to Dashboard</a>

<h1>⬡ Database Viewer</h1>
<p class="subtitle">Live view of all records stored in <strong>inventory_db</strong></p>

<?php if ($error): ?>
<div class="error">❌ <?= htmlspecialchars($error) ?></div>
<?php else: ?>

<!-- Stats -->
<div class="stats-bar">
    <div class="stat"><div class="stat-val"><?= count($suppliers) ?></div><div class="stat-lbl">Suppliers</div></div>
    <div class="stat"><div class="stat-val"><?= count($customers) ?></div><div class="stat-lbl">Customers</div></div>
    <div class="stat"><div class="stat-val"><?= count($products) ?></div><div class="stat-lbl">Products</div></div>
    <div class="stat"><div class="stat-val"><?= count($transactions) ?></div><div class="stat-lbl">Transactions</div></div>
    <div class="stat"><div class="stat-val"><?= count($inventory) ?></div><div class="stat-lbl">Inventory Records</div></div>
</div>

<!-- Tabs -->
<div class="tabs">
    <div class="tab active"     onclick="showTab('suppliers')">Suppliers</div>
    <div class="tab"            onclick="showTab('customers')">Customers</div>
    <div class="tab"            onclick="showTab('products')">Products</div>
    <div class="tab"            onclick="showTab('transactions')">Transactions</div>
    <div class="tab"            onclick="showTab('inventory')">Inventory</div>
</div>

<!-- SUPPLIERS -->
<div class="table-section active" id="tab-suppliers">
<div class="table-card">
    <div class="table-header">
        <h3>suppliers</h3>
        <span class="row-count"><?= count($suppliers) ?> rows</span>
    </div>
    <div class="table-wrap">
    <?php if(empty($suppliers)): ?>
        <div class="empty">No records found</div>
    <?php else: ?>
    <table>
        <thead><tr><th>supplier_id</th><th>name</th><th>contact</th><th>address</th><th>created_at</th></tr></thead>
        <tbody>
        <?php foreach($suppliers as $r): ?>
        <tr>
            <td class="pk">#<?= $r['supplier_id'] ?></td>
            <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
            <td><?= htmlspecialchars($r['contact'] ?? '') ?: '<span class="null-val">NULL</span>' ?></td>
            <td><?= htmlspecialchars($r['address'] ?? '') ?: '<span class="null-val">NULL</span>' ?></td>
            <td><?= $r['created_at'] ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    </div>
</div>
</div>

<!-- CUSTOMERS -->
<div class="table-section" id="tab-customers">
<div class="table-card">
    <div class="table-header">
        <h3>customers</h3>
        <span class="row-count"><?= count($customers) ?> rows</span>
    </div>
    <div class="table-wrap">
    <?php if(empty($customers)): ?>
        <div class="empty">No records found</div>
    <?php else: ?>
    <table>
        <thead><tr><th>customer_id</th><th>customer_name</th><th>email</th><th>phone</th><th>created_at</th></tr></thead>
        <tbody>
        <?php foreach($customers as $r): ?>
        <tr>
            <td class="pk">#<?= $r['customer_id'] ?></td>
            <td><strong><?= htmlspecialchars($r['customer_name']) ?></strong></td>
            <td><?= htmlspecialchars($r['email'] ?? '') ?: '<span class="null-val">NULL</span>' ?></td>
            <td><?= htmlspecialchars($r['phone'] ?? '') ?: '<span class="null-val">NULL</span>' ?></td>
            <td><?= $r['created_at'] ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    </div>
</div>
</div>

<!-- PRODUCTS -->
<div class="table-section" id="tab-products">
<div class="table-card">
    <div class="table-header">
        <h3>products</h3>
        <span class="row-count"><?= count($products) ?> rows</span>
    </div>
    <div class="table-wrap">
    <?php if(empty($products)): ?>
        <div class="empty">No records found</div>
    <?php else: ?>
    <table>
        <thead><tr><th>product_id</th><th>product_name</th><th>category</th><th>price</th><th>quantity</th><th>supplier</th><th>created_at</th></tr></thead>
        <tbody>
        <?php foreach($products as $r): ?>
        <tr>
            <td class="pk">#<?= $r['product_id'] ?></td>
            <td><strong><?= htmlspecialchars($r['product_name']) ?></strong></td>
            <td><?= htmlspecialchars($r['category'] ?? '') ?: '<span class="null-val">NULL</span>' ?></td>
            <td>₹<?= number_format($r['price'], 2) ?></td>
            <td><?= $r['quantity'] ?></td>
            <td><?= htmlspecialchars($r['supplier_name'] ?? '') ?: '<span class="null-val">NULL</span>' ?></td>
            <td><?= $r['created_at'] ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    </div>
</div>
</div>

<!-- TRANSACTIONS -->
<div class="table-section" id="tab-transactions">
<div class="table-card">
    <div class="table-header">
        <h3>transactions</h3>
        <span class="row-count"><?= count($transactions) ?> rows</span>
    </div>
    <div class="table-wrap">
    <?php if(empty($transactions)): ?>
        <div class="empty">No records found</div>
    <?php else: ?>
    <table>
        <thead><tr><th>transaction_id</th><th>date</th><th>customer</th><th>product</th><th>quantity</th><th>type</th><th>total</th></tr></thead>
        <tbody>
        <?php foreach($transactions as $r): ?>
        <tr>
            <td class="pk">#<?= $r['transaction_id'] ?></td>
            <td><?= $r['date'] ?></td>
            <td><?= htmlspecialchars($r['customer_name']) ?></td>
            <td><?= htmlspecialchars($r['product_name']) ?></td>
            <td><?= $r['quantity'] ?></td>
            <td><span class="badge badge-<?= strtolower($r['transaction_type']) ?>"><?= $r['transaction_type'] ?></span></td>
            <td>₹<?= number_format($r['total'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    </div>
</div>
</div>

<!-- INVENTORY -->
<div class="table-section" id="tab-inventory">
<div class="table-card">
    <div class="table-header">
        <h3>inventory</h3>
        <span class="row-count"><?= count($inventory) ?> rows</span>
    </div>
    <div class="table-wrap">
    <?php if(empty($inventory)): ?>
        <div class="empty">No records found</div>
    <?php else: ?>
    <table>
        <thead><tr><th>inventory_id</th><th>product</th><th>category</th><th>stock_level</th><th>status</th><th>updated_at</th></tr></thead>
        <tbody>
        <?php foreach($inventory as $r):
            $st = $r['status'];
            $cls = $st==='In Stock' ? 'sale' : ($st==='Low Stock' ? 'return' : 'transfer');
        ?>
        <tr>
            <td class="pk">#<?= $r['inventory_id'] ?></td>
            <td><strong><?= htmlspecialchars($r['product_name']) ?></strong></td>
            <td><?= htmlspecialchars($r['category'] ?? '') ?></td>
            <td><?= $r['stock_level'] ?> units</td>
            <td><span class="badge badge-<?= $cls ?>"><?= $st ?></span></td>
            <td><?= $r['updated_at'] ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    </div>
</div>
</div>

<?php endif; ?>

<script>
function showTab(name) {
    document.querySelectorAll('.table-section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    event.target.classList.add('active');
}
</script>
</body>
</html>
