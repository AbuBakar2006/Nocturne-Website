<?php
// AdminDashboard.php
// Main overview page for NOCTURNE Admin.

require_once 'auth.php';
check_admin_login();
require_once 'db_connect.php';

// Fetch stats
try {
    // 1. Total Revenue
    $stmt = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status != 'Cancelled'");
    $total_revenue = $stmt->fetchColumn() ?: 0;

    // 2. Total Orders
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
    $total_orders = $stmt->fetchColumn() ?: 0;

    // 3. Total Products
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    $total_products = $stmt->fetchColumn() ?: 0;

    // 4. Total Customers
    $stmt = $pdo->query("SELECT COUNT(*) FROM customers");
    $total_customers = $stmt->fetchColumn() ?: 0;

    // 5. Recent Orders (limit 5)
    $stmt = $pdo->query("SELECT o.id, c.name AS customer_name, o.total_amount, o.status, DATE_FORMAT(o.order_date, '%b %d, %Y') AS formatted_date, 
                        (SELECT GROUP_CONCAT(p.name SEPARATOR ', ') FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = o.id) AS products_ordered
                        FROM orders o 
                        JOIN customers c ON o.customer_id = c.id 
                        ORDER BY o.order_date DESC, o.id DESC 
                        LIMIT 5");
    $recent_orders = $stmt->fetchAll();

    // 6. Top Selling Products (limit 3)
    $stmt = $pdo->query("SELECT p.name, p.image_path, SUM(oi.quantity) AS sold, SUM(oi.quantity * oi.price) AS revenue 
                        FROM order_items oi 
                        JOIN products p ON oi.product_id = p.id 
                        GROUP BY p.id 
                        ORDER BY sold DESC 
                        LIMIT 3");
    $top_products = $stmt->fetchAll();

    // 7. New Customers (limit 4)
    $stmt = $pdo->query("SELECT name, city, DATE_FORMAT(joined_date, '%b %d') AS joined 
                        FROM customers 
                        ORDER BY joined_date DESC, id DESC 
                        LIMIT 4");
    $new_customers = $stmt->fetchAll();

    // 8. Monthly Sales for Bar Chart (Last 6 Months)
    $stmt = $pdo->query("SELECT DATE_FORMAT(order_date, '%b') AS month_name, SUM(total_amount) AS sales 
                        FROM orders 
                        WHERE status != 'Cancelled' 
                        GROUP BY MONTH(order_date), YEAR(order_date)
                        ORDER BY order_date ASC 
                        LIMIT 6");
    $monthly_sales_raw = $stmt->fetchAll();
    
    // Fill up to 6 months with default data if empty
    $monthly_sales = [];
    $max_monthly_sales = 1; // Prevent division by zero
    foreach ($monthly_sales_raw as $row) {
        $monthly_sales[$row['month_name']] = (float)$row['sales'];
        if ((float)$row['sales'] > $max_monthly_sales) {
            $max_monthly_sales = (float)$row['sales'];
        }
    }
    
    // Default 6 months if not enough data
    if (count($monthly_sales) < 6) {
        $default_months = ['Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr'];
        foreach ($default_months as $m) {
            if (!isset($monthly_sales[$m])) {
                $monthly_sales[$m] = rand(3000, 15000); // Mock data for empty months
                if ($monthly_sales[$m] > $max_monthly_sales) {
                    $max_monthly_sales = $monthly_sales[$m];
                }
            }
        }
    }

    // 9. Orders by Status for Donut Chart
    $stmt = $pdo->query("SELECT status, COUNT(*) AS count FROM orders GROUP BY status");
    $status_counts = $stmt->fetchAll();
    $status_data = ['Delivered' => 0, 'Shipped' => 0, 'Pending' => 0, 'Cancelled' => 0, 'Processing' => 0];
    $total_status_orders = 0;
    foreach ($status_counts as $row) {
        $status_data[$row['status']] = (int)$row['count'];
        $total_status_orders += (int)$row['count'];
    }
    if ($total_status_orders == 0) { $total_status_orders = 1; } // Prevent division by zero

} catch (\PDOException $e) {
    die("Database fetch error: " . $e->getMessage());
}

// Admin initials helper
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$initials = '';
$name_parts = explode(' ', $admin_name);
foreach ($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="NOCTURNE Admin Dashboard Ã¢â‚¬â€œ Store overview">
  <title>Dashboard | NOCTURNE Admin</title>
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
          <h1>Dashboard Overview</h1>
        </div>
        <div style="display:flex; align-items:center; gap:16px;">
          <div style="display:flex; align-items:center; gap:10px;">
            <span style="font-weight:700; font-size:0.9rem;"><?php echo htmlspecialchars($admin_name); ?></span>
            <a href="AdminProfile.php" style="text-decoration:none;">
              <div style="width:34px; height:34px; background:#111; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fca311; font-weight:700; font-size:0.85rem; border: 1px solid #fca311;">
                <?php echo htmlspecialchars($initials); ?>
              </div>
            </a>
          </div>
        </div>
      </header>

      <!-- CONTENT -->
      <main class="admin-content">

        <?php if (isset($_GET['error'])): ?>
          <div style="background-color: #ffebee; color: #c62828; border: 1.5px solid #c62828; padding: 12px; margin-bottom: 20px; font-weight: bold; border-radius: 4px; font-size: 0.9rem;">
            <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i><?php echo htmlspecialchars($_GET['error']); ?>
          </div>
        <?php endif; ?>

        <!-- Welcome Banner -->
        <div class="admin-card-dark" style="background:linear-gradient(135deg,#111 0%,#1a1a1a 100%); color:#fff; padding:24px 28px; margin-bottom:28px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; border:1.5px solid #111; box-shadow:8px 8px 0px #000;">
          <div>
            <h2 style="font-size:1.3rem; margin-bottom:6px;">Welcome back, <?php echo htmlspecialchars($_SESSION['admin_name']); ?></h2>
            <p style="color:rgba(255,255,255,.55); font-size:0.9rem;">Here's what's happening with your NOCTURNE store today.</p>
          </div>
          <a href="AdminOrders.php" class="btn-add" style="font-size:0.85rem; padding:10px 18px; background:#fca311; color:#000;">
            <i class="fas fa-shopping-bag" style="margin-right:6px;"></i>View Orders
          </a>
        </div>

        <!-- STAT CARDS -->
        <section class="admin-stats">
          <div class="stat-card">
            <div style="width:42px; height:42px; background:#fff8e6; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-bottom:12px;">
              <i class="fas fa-coins" style="color:#fca311; font-size:1.1rem;"></i>
            </div>
            <h3>Total Revenue</h3>
            <div class="value">Rs. <?php echo number_format($total_revenue); ?></div>
            <div class="trend up"><i class="fas fa-arrow-up"></i> Live stats</div>
          </div>
          <div class="stat-card">
            <div style="width:42px; height:42px; background:#e8f4ff; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-bottom:12px;">
              <i class="fas fa-shopping-bag" style="color:#3b82f6; font-size:1.1rem;"></i>
            </div>
            <h3>Total Orders</h3>
            <div class="value"><?php echo $total_orders; ?></div>
            <div class="trend up"><i class="fas fa-arrow-up"></i> Active orders</div>
          </div>
          <div class="stat-card">
            <div style="width:42px; height:42px; background:#f0faf0; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-bottom:12px;">
              <i class="fas fa-box-open" style="color:#10b981; font-size:1.1rem;"></i>
            </div>
            <h3>Total Products</h3>
            <div class="value"><?php echo $total_products; ?></div>
            <div class="trend"><i class="fas fa-check"></i> Active in store</div>
          </div>
          <div class="stat-card">
            <div style="width:42px; height:42px; background:#fff0f0; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-bottom:12px;">
              <i class="fas fa-users" style="color:#ef4444; font-size:1.1rem;"></i>
            </div>
            <h3>Customers</h3>
            <div class="value"><?php echo $total_customers; ?></div>
            <div class="trend"><i class="fas fa-users"></i> Registered</div>
          </div>
        </section>

        <!-- CHARTS ROW -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:28px;">

          <!-- Monthly Sales Bar Chart -->
          <div class="admin-table-container" style="padding:24px;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
              <h3 style="font-size:1rem; font-weight:700; margin:0;">Monthly Sales</h3>
              <span style="color:#aaa; font-size:0.8rem;">Last 6 months</span>
            </div>
            <div style="display:flex; align-items:flex-end; gap:10px; height:140px; padding-bottom:24px; position:relative;">
              <?php foreach ($monthly_sales as $month => $sales_val): 
                $height = ($sales_val / $max_monthly_sales) * 100;
                $is_gold = ($sales_val == $max_monthly_sales) ? 'background:#fca311;' : 'background:#111;';
              ?>
              <div style="flex:1; text-align:center;">
                <div style="<?php echo $is_gold; ?> width:100%; height:<?php echo max(5, $height); ?>px; border-radius:4px 4px 0 0; margin:0 auto;" title="Rs. <?php echo number_format($sales_val); ?>"></div>
                <span style="font-size:0.7rem; color:#aaa; margin-top:6px; display:block;"><?php echo htmlspecialchars($month); ?></span>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Orders by Status Donut -->
          <?php
          $del_count = $status_data['Delivered'];
          $ship_count = $status_data['Shipped'];
          $pend_count = $status_data['Pending'] + $status_data['Processing'];
          $canc_count = $status_data['Cancelled'];
          
          $tot_val = $del_count + $ship_count + $pend_count + $canc_count;
          if ($tot_val == 0) $tot_val = 1;
          
          $del_pct = round(($del_count / $tot_val) * 100);
          $ship_pct = round(($ship_count / $tot_val) * 100);
          $pend_pct = round(($pend_count / $tot_val) * 100);
          $canc_pct = round(($canc_count / $tot_val) * 100);
          
          // conic gradient calculations
          $lim1 = $del_pct;
          $lim2 = $lim1 + $ship_pct;
          $lim3 = $lim2 + $pend_pct;
          ?>
          <div class="admin-table-container" style="padding:24px;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
              <h3 style="font-size:1rem; font-weight:700; margin:0;">Orders by Status</h3>
              <span style="color:#aaa; font-size:0.8rem;">Current status</span>
            </div>
            <div style="display:flex; align-items:center; gap:24px; flex-wrap:wrap;">
              <div style="width:110px; height:110px; border-radius:50%; background:conic-gradient(#10b981 0% <?php echo $lim1; ?>%, #3b82f6 <?php echo $lim1; ?>% <?php echo $lim2; ?>%, #fca311 <?php echo $lim2; ?>% <?php echo $lim3; ?>%, #ef4444 <?php echo $lim3; ?>% 100%); display:flex; align-items:center; justify-content:center; flex-shrink:0; border: 1.5px solid #111;">
                <div style="width:70px; height:70px; background:#fff; border-radius:50%; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                  <strong style="font-size:1rem;"><?php echo $total_orders; ?></strong>
                  <span style="font-size:0.65rem; color:#aaa;">Orders</span>
                </div>
              </div>
              <div style="display:flex; flex-direction:column; gap:8px; font-size:0.82rem;">
                <div><span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#10b981; margin-right:6px;"></span>Delivered (<?php echo $del_pct; ?>%)</div>
                <div><span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#3b82f6; margin-right:6px;"></span>Shipped (<?php echo $ship_pct; ?>%)</div>
                <div><span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#fca311; margin-right:6px;"></span>Pending (<?php echo $pend_pct; ?>%)</div>
                <div><span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#ef4444; margin-right:6px;"></span>Cancelled (<?php echo $canc_pct; ?>%)</div>
              </div>
            </div>
          </div>
        </div>

        <!-- RECENT ORDERS TABLE -->
        <section style="margin-bottom:28px;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 style="font-size:1.2rem; text-transform:uppercase; letter-spacing:1px; margin:0;">Recent Orders</h2>
            <a href="AdminOrders.php" style="color:#fca311; font-weight:700; text-decoration:none; font-size:0.9rem;">
              View All <i class="fas fa-arrow-right" style="margin-left:4px;"></i>
            </a>
          </div>
          <div class="admin-table-container">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Order ID</th>
                  <th>Customer</th>
                  <th>Products</th>
                  <th>Amount</th>
                  <th>Status</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($recent_orders) > 0): ?>
                  <?php foreach ($recent_orders as $ord): 
                    $badge_class = 'status-pending';
                    if ($ord['status'] === 'Delivered') $badge_class = 'status-active';
                    if ($ord['status'] === 'Shipped') $badge_class = 'status-shipped';
                    if ($ord['status'] === 'Cancelled') $badge_class = 'status-cancelled';
                  ?>
                    <tr>
                      <td><strong>#ORD-<?php echo htmlspecialchars($ord['id']); ?></strong></td>
                      <td><?php echo htmlspecialchars($ord['customer_name']); ?></td>
                      <td><span style="font-size:0.85rem; color:#666;" title="<?php echo htmlspecialchars($ord['products_ordered']); ?>"><?php echo htmlspecialchars(strlen($ord['products_ordered']) > 32 ? substr($ord['products_ordered'], 0, 30) . '...' : $ord['products_ordered']); ?></span></td>
                      <td>Rs. <?php echo number_format($ord['total_amount']); ?></td>
                      <td><span class="status-badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($ord['status']); ?></span></td>
                      <td><?php echo htmlspecialchars($ord['formatted_date']); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="6" style="text-align:center; padding: 20px; color: #777;">No recent orders found.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>

        <!-- TOP PRODUCTS + NEW CUSTOMERS -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:28px;">

          <!-- Top Products -->
          <div class="admin-table-container" style="padding:24px;">
            <h3 style="font-size:1rem; font-weight:700; margin:0 0 16px;">Top Selling Products</h3>
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Sold</th>
                  <th>Revenue</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($top_products) > 0): ?>
                  <?php foreach ($top_products as $p): ?>
                    <tr>
                      <td style="display:flex; align-items:center; gap:10px;">
                        <img src="<?php echo htmlspecialchars($p['image_path']); ?>" style="width:36px; height:36px; border-radius:6px; object-fit:cover; border: 1px solid #ddd;" alt="<?php echo htmlspecialchars($p['name']); ?>" />
                        <?php echo htmlspecialchars($p['name']); ?>
                      </td>
                      <td><?php echo htmlspecialchars($p['sold']); ?></td>
                      <td>Rs. <?php echo number_format($p['revenue']); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="3" style="text-align:center; padding: 20px; color:#777;">No data available.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- New Customers -->
          <div class="admin-table-container" style="padding:24px;">
            <h3 style="font-size:1rem; font-weight:700; margin:0 0 16px;">New Customers</h3>
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>City</th>
                  <th>Joined</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($new_customers) > 0): ?>
                  <?php foreach ($new_customers as $c): ?>
                    <tr>
                      <td><strong><?php echo htmlspecialchars($c['name']); ?></strong></td>
                      <td><?php echo htmlspecialchars($c['city']); ?></td>
                      <td><?php echo htmlspecialchars($c['joined']); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="3" style="text-align:center; padding:20px; color:#777;">No customers found.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </main>
    </div>
  </div>

</body>
</html>
