<?php
// AdminOrders.php
// Orders management page for NOCTURNE Admin.

require_once 'auth.php';
check_admin_login();
require_once 'db_connect.php';

$success_message = '';
$error_message = '';

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $new_status = $_POST['status'] ?? '';
    
    $valid_statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
    if (in_array($new_status, $valid_statuses)) {
        try {
            $stmt = $pdo->prepare("UPDATE orders SET status = :status WHERE id = :id");
            $stmt->execute(['status' => $new_status, 'id' => $order_id]);
            $success_message = "Order #ORD-{$order_id} status updated to '{$new_status}' successfully.";
        } catch (\PDOException $e) {
            $error_message = "Failed to update status: " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid status selected.";
    }
}

// Filters & Query Logic
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$date = trim($_GET['date'] ?? '');
$sort = trim($_GET['sort'] ?? 'newest');

$where_clauses = [];
$params = [];

if ($search !== '') {
    $where_clauses[] = "(c.name LIKE :search_name OR o.id = :search_id)";
    $params['search_name'] = "%{$search}%";
    
    // Check if user entered ORD-xxxx
    $search_id = $search;
    if (preg_match('/ORD-(\d+)/i', $search, $matches)) {
        $search_id = $matches[1];
    }
    $params['search_id'] = is_numeric($search_id) ? (int)$search_id : -1;
}

if ($status !== '') {
    $where_clauses[] = "o.status = :status";
    $params['status'] = $status;
}

if ($date !== '') {
    $where_clauses[] = "o.order_date = :date";
    $params['date'] = $date;
}

$where_sql = '';
if (count($where_clauses) > 0) {
    $where_sql = ' WHERE ' . implode(' AND ', $where_clauses);
}

// Sorting logic
$order_sql = 'ORDER BY o.order_date DESC, o.id DESC';
if ($sort === 'oldest') {
    $order_sql = 'ORDER BY o.order_date ASC, o.id ASC';
} elseif ($sort === 'high') {
    $order_sql = 'ORDER BY o.total_amount DESC';
} elseif ($sort === 'low') {
    $order_sql = 'ORDER BY o.total_amount ASC';
}

// Pagination setup
$limit = 5; // Rows per page
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

try {
    // Get stats for top cards
    $total_orders_count = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $pending_orders_count = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Pending'")->fetchColumn();
    $delivered_orders_count = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Delivered'")->fetchColumn();
    $total_sales_revenue = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status != 'Cancelled'")->fetchColumn() ?: 0;

    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) FROM orders o JOIN customers c ON o.customer_id = c.id" . $where_sql;
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_rows = $stmt->fetchColumn();
    $total_pages = ceil($total_rows / $limit);

    // Fetch orders
    $sql = "SELECT o.id, c.name AS customer_name, o.total_amount, o.status, DATE_FORMAT(o.order_date, '%b %d, %Y') AS formatted_date,
            (SELECT GROUP_CONCAT(CONCAT(p.name, ' (x', oi.quantity, ')') SEPARATOR ', ') 
             FROM order_items oi 
             JOIN products p ON oi.product_id = p.id 
             WHERE oi.order_id = o.id) AS product_details
            FROM orders o
            JOIN customers c ON o.customer_id = c.id" 
            . $where_sql . " " 
            . $order_sql . " 
            LIMIT :limit OFFSET :offset";
            
    $stmt = $pdo->prepare($sql);
    // Bind limit/offset as integers
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $val) {
        $stmt->bindValue(':' . $key, $val);
    }
    $stmt->execute();
    $orders = $stmt->fetchAll();

} catch (\PDOException $e) {
    die("Database query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Manage orders on NOCTURNE admin panel">
  <title>Orders | NOCTURNE Admin</title>
  <link href="../font-awesome/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
</head>
<body class="admin-body">

  <div class="admin-wrapper">
    <!-- SIDEBAR -->
    <?php include 'sidebar.php'; ?>

    <!-- MAIN -->
    <div class="main">

      <!-- TOPBAR -->
      <header class="admin-header">
        <div style="display:flex; align-items:center; gap:14px;">
          <h1>Order Management</h1>
        </div>
        <form method="get" action="AdminOrders.php" style="display:flex; align-items:center; gap:16px;">
          <div style="position:relative;">
            <i class="fas fa-search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#aaa;"></i>
            <input type="text" name="search" placeholder="Search customer or IDÃ¢â‚¬Â¦" value="<?php echo htmlspecialchars($search); ?>" style="padding:8px 12px 8px 34px; border:1px solid #ddd; border-radius:8px; font-size:0.9rem; outline:none; width:220px;">
          </div>
          <button type="submit" class="btn-add" style="font-size:0.8rem; padding:8px 14px; border-radius:8px;">Search</button>
        </form>
      </header>

      <main class="admin-content">

        <?php if (!empty($success_message)): ?>
          <div style="background-color: #e8f5e9; color: #2e7d32; border: 1.5px solid #2e7d32; padding: 12px; margin-bottom: 20px; font-weight: bold; border-radius: 4px; font-size: 0.9rem;">
            <i class="fas fa-check-circle" style="margin-right: 8px;"></i><?php echo htmlspecialchars($success_message); ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
          <div style="background-color: #ffebee; color: #c62828; border: 1.5px solid #c62828; padding: 12px; margin-bottom: 20px; font-weight: bold; border-radius: 4px; font-size: 0.9rem;">
            <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i><?php echo htmlspecialchars($error_message); ?>
          </div>
        <?php endif; ?>

        <!-- STATS -->
        <section class="admin-stats">
          <div class="stat-card">
            <div style="width:42px; height:42px; background:#e8f4ff; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:12px;">
              <i class="fas fa-shopping-bag" style="color:#3b82f6; font-size:1.1rem;"></i>
            </div>
            <h3>Total Orders</h3>
            <div class="value"><?php echo number_format($total_orders_count); ?></div>
            <div class="trend">All-time orders</div>
          </div>
          <div class="stat-card">
            <div style="width:42px; height:42px; background:#fff8e6; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:12px;">
              <i class="fas fa-clock" style="color:#fca311; font-size:1.1rem;"></i>
            </div>
            <h3>Pending Orders</h3>
            <div class="value"><?php echo number_format($pending_orders_count); ?></div>
            <div class="trend">Awaiting processing</div>
          </div>
          <div class="stat-card">
            <div style="width:42px; height:42px; background:#f0faf0; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:12px;">
              <i class="fas fa-check-circle" style="color:#10b981; font-size:1.1rem;"></i>
            </div>
            <h3>Delivered</h3>
            <div class="value"><?php echo number_format($delivered_orders_count); ?></div>
            <div class="trend">Completed orders</div>
          </div>
          <div class="stat-card">
            <div style="width:42px; height:42px; background:#f3e8ff; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:12px;">
              <i class="fas fa-coins" style="color:#7c3aed; font-size:1.1rem;"></i>
            </div>
            <h3>Total Revenue</h3>
            <div class="value">Rs. <?php echo number_format($total_sales_revenue); ?></div>
            <div class="trend">Excludes cancelled</div>
          </div>
        </section>

        <!-- FILTERS -->

        <div class="admin-table-container" style="padding:16px 20px; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
          <form method="get" action="AdminOrders.php" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
            
            <select name="status" onchange="this.form.submit()" style="padding:8px 12px; border:1px solid #ddd; border-radius:8px; font-size:0.88rem; outline:none; cursor:pointer;">
              <option value="">All Statuses</option>
              <option value="Pending" <?php echo $status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
              <option value="Processing" <?php echo $status === 'Processing' ? 'selected' : ''; ?>>Processing</option>
              <option value="Shipped" <?php echo $status === 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
              <option value="Delivered" <?php echo $status === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
              <option value="Cancelled" <?php echo $status === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
            
            <select name="sort" onchange="this.form.submit()" style="padding:8px 12px; border:1px solid #ddd; border-radius:8px; font-size:0.88rem; outline:none; cursor:pointer;">
              <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
              <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
              <option value="high" <?php echo $sort === 'high' ? 'selected' : ''; ?>>Amount: High Ã¢â€ â€™ Low</option>
              <option value="low" <?php echo $sort === 'low' ? 'selected' : ''; ?>>Amount: Low Ã¢â€ â€™ High</option>
            </select>
            
            <input type="date" name="date" onchange="this.form.submit()" value="<?php echo htmlspecialchars($date); ?>" style="padding:8px 12px; border:1px solid #ddd; border-radius:8px; font-size:0.88rem; outline:none; cursor:pointer;">
            <?php if ($search !== '' || $status !== '' || $date !== ''): ?>
              <a href="AdminOrders.php" style="padding: 8px 12px; border: 1px solid #ef4444; border-radius: 8px; font-size: 0.88rem; outline: none; cursor: pointer; color: #fff; background: #ef4444; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-weight: 700; margin-left: 10px; transition: all 0.2s ease;">
                <i class="fas fa-times-circle"></i> Clear Filters
              </a>
            <?php endif; ?>
          </form>
          <span style="color:#aaa; font-size:0.85rem;">Showing <?php echo count($orders); ?> of <?php echo $total_rows; ?> orders</span>
        </div>

        <!-- ORDERS TABLE -->
        <div class="admin-table-container">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Products</th>
                <th>Amount</th>
                <th>Date</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($orders) > 0): ?>
                <?php foreach ($orders as $ord): 
                  // Status colors mapping
                  $bg_color = '#fff3e0'; $text_color = '#ef6c00';
                  if ($ord['status'] === 'Delivered') { $bg_color = '#e8f5e9'; $text_color = '#2e7d32'; }
                  elseif ($ord['status'] === 'Shipped') { $bg_color = '#e3f2fd'; $text_color = '#1565c0'; }
                  elseif ($ord['status'] === 'Cancelled') { $bg_color = '#ffebee'; $text_color = '#c62828'; }
                ?>
                  <tr>
                    <td><strong>#ORD-<?php echo htmlspecialchars($ord['id']); ?></strong></td>
                    <td><?php echo htmlspecialchars($ord['customer_name']); ?></td>
                    <td><span style="font-size:0.85rem; color:#666;" title="<?php echo htmlspecialchars($ord['product_details']); ?>"><?php echo htmlspecialchars(strlen($ord['product_details']) > 45 ? substr($ord['product_details'], 0, 42) . '...' : $ord['product_details']); ?></span></td>
                    <td>Rs. <?php echo number_format($ord['total_amount']); ?></td>
                    <td><?php echo htmlspecialchars($ord['formatted_date']); ?></td>
                    <td>
                      <!-- Inline Status Change form -->
                      <form method="post" action="AdminOrders.php" style="margin:0;">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="order_id" value="<?php echo $ord['id']; ?>">
                        <select name="status" onchange="this.form.submit()" style="padding:6px 12px; border-radius:50px; font-size:0.8rem; font-weight:700; border: 1.5px solid #111; background:<?php echo $bg_color; ?>; color:<?php echo $text_color; ?>; cursor:pointer; text-transform: uppercase;">
                          <option value="Pending" <?php echo $ord['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                          <option value="Processing" <?php echo $ord['status'] === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                          <option value="Shipped" <?php echo $ord['status'] === 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
                          <option value="Delivered" <?php echo $ord['status'] === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                          <option value="Cancelled" <?php echo $ord['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                      </form>
                    </td>
                    <td>
                      <div class="action-btns">
                        <a href="OrderDetails.php?id=<?php echo $ord['id']; ?>" class="btn-edit" style="color:#111 !important;" title="View Details"><i class="fas fa-eye"></i> View</a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7" style="text-align:center; padding: 30px; color:#777;">No orders found matching filters.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
          <div style="display:flex; justify-content:center; gap:6px; margin-top:20px;">
            <?php if ($page > 1): ?>
              <a href="AdminOrders.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&sort=<?php echo urlencode($sort); ?>&date=<?php echo urlencode($date); ?>" style="padding:8px 12px; border:1.5px solid #111; background:#fff; text-decoration:none; color:#111; font-weight:700;"><i class="fas fa-chevron-left"></i></a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
              <a href="AdminOrders.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&sort=<?php echo urlencode($sort); ?>&date=<?php echo urlencode($date); ?>" style="padding:8px 14px; border:1.5px solid #111; <?php echo $i === $page ? 'background:#111; color:#fff;' : 'background:#fff; color:#111;'; ?> text-decoration:none; font-weight:700;"><?php echo $i; ?></a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
              <a href="AdminOrders.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&sort=<?php echo urlencode($sort); ?>&date=<?php echo urlencode($date); ?>" style="padding:8px 12px; border:1.5px solid #111; background:#fff; text-decoration:none; color:#111; font-weight:700;"><i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

      </main>
    </div>
  </div>

</body>
</html>
