<?php
// AdminCustomers.php
// Customer view and management page for NOCTURNE Admin.

require_once 'auth.php';
check_admin_login();
require_once 'db_connect.php';

// Filters & Query Parameters
$search = trim($_GET['search'] ?? '');
$city_filter = trim($_GET['city'] ?? '');

$where_clauses = [];
$params = [];

if ($search !== '') {
    $where_clauses[] = "(c.name LIKE :search_name OR c.email LIKE :search_email OR c.phone LIKE :search_phone OR c.city LIKE :search_city)";
    $params['search_name'] = "%{$search}%";
    $params['search_email'] = "%{$search}%";
    $params['search_phone'] = "%{$search}%";
    $params['search_city'] = "%{$search}%";
}

if ($city_filter !== '') {
    $where_clauses[] = "c.city = :city";
    $params['city'] = $city_filter;
}

$where_sql = '';
if (count($where_clauses) > 0) {
    $where_sql = ' WHERE ' . implode(' AND ', $where_clauses);
}

// Pagination setup
$limit = 8; // Row limit as in template
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

try {
    // 1. Fetch Dynamic Stats
    $total_cust = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    // Count new in last 30 days
    $new_cust = $pdo->query("SELECT COUNT(*) FROM customers WHERE joined_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
    // Count repeats (customers with > 1 order)
    $repeat_cust = $pdo->query("SELECT COUNT(*) FROM (SELECT customer_id FROM orders GROUP BY customer_id HAVING COUNT(id) > 1) AS repeats")->fetchColumn();
    // Count distinct cities
    $total_cities = $pdo->query("SELECT COUNT(DISTINCT city) FROM customers")->fetchColumn();
    
    // Fetch unique cities for filter dropdown
    $cities_list = $pdo->query("SELECT DISTINCT city FROM customers ORDER BY city ASC")->fetchAll(PDO::FETCH_COLUMN);

    // 2. Fetch Total Count for Filters
    $count_sql = "SELECT COUNT(*) FROM customers c" . $where_sql;
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_rows = $stmt->fetchColumn();
    $total_pages = ceil($total_rows / $limit);

    // 3. Fetch Customers List with dynamic order counts and revenue spent
    $sql = "SELECT c.id, c.name, c.email, c.phone, c.city,
            DATE_FORMAT(c.joined_date, '%d %b %Y') AS formatted_join,
            (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS order_count,
            (SELECT IFNULL(SUM(o.total_amount), 0) FROM orders o WHERE o.customer_id = c.id AND o.status != 'Cancelled') AS total_spent
            FROM customers c"
            . $where_sql . "
            ORDER BY c.joined_date DESC, c.id DESC
            LIMIT :limit OFFSET :offset";
            
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $val) {
        $stmt->bindValue(':' . $key, $val);
    }
    $stmt->execute();
    $customers = $stmt->fetchAll();

} catch (\PDOException $e) {
    die("Database query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Customer management on NOCTURNE admin panel">
  <title>Customers | NOCTURNE Admin</title>
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
          <h1>Customer Management</h1>
        </div>
        <form method="get" action="AdminCustomers.php" style="display:flex; align-items:center; gap:16px;">
          <div style="position:relative;">
            <i class="fas fa-search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#aaa;"></i>
            <input type="text" name="search" placeholder="Search customersÃ¢â‚¬Â¦" value="<?php echo htmlspecialchars($search); ?>" style="padding:8px 12px 8px 34px; border:1px solid #ddd; border-radius:8px; font-size:0.9rem; outline:none; width:220px;">
          </div>
          <button type="submit" class="btn-add" style="font-size:0.8rem; padding:8px 14px; border-radius:8px;">Search</button>
        </form>
      </header>

      <main class="admin-content">

        <!-- STATS -->
        <section class="admin-stats">
          <div class="stat-card">
            <div style="width:42px; height:42px; background:#e8f4ff; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:12px;">
              <i class="fas fa-users" style="color:#3b82f6; font-size:1.1rem;"></i>
            </div>
            <h3>Total Customers</h3>
            <div class="value"><?php echo number_format($total_cust); ?></div>
            <div class="trend">Registered accounts</div>
          </div>
          <div class="stat-card">
            <div style="width:42px; height:42px; background:#f0faf0; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:12px;">
              <i class="fas fa-user-plus" style="color:#10b981; font-size:1.1rem;"></i>
            </div>
            <h3>New This Month</h3>
            <div class="value"><?php echo $new_cust; ?></div>
            <div class="trend up"><i class="fas fa-arrow-up"></i> Last 30 days</div>
          </div>
          <div class="stat-card">
            <div style="width:42px; height:42px; background:#fff8e6; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:12px;">
              <i class="fas fa-crown" style="color:#fca311; font-size:1.1rem;"></i>
            </div>
            <h3>Repeat Buyers</h3>
            <div class="value"><?php echo $repeat_cust; ?></div>
            <div class="trend">Loyal customers</div>
          </div>
          <div class="stat-card">
            <div style="width:42px; height:42px; background:#f3e8ff; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:12px;">
              <i class="fas fa-map-marker-alt" style="color:#7c3aed; font-size:1.1rem;"></i>
            </div>
            <h3>Cities</h3>
            <div class="value"><?php echo $total_cities; ?></div>
            <div class="trend">Across Pakistan</div>
          </div>
        </section>

        <!-- FILTERS -->
        <div class="admin-table-container" style="padding:16px 20px; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
          <form method="get" action="AdminCustomers.php" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
            <select name="city" onchange="this.form.submit()" style="padding:8px 12px; border:1px solid #ddd; border-radius:8px; font-size:0.88rem; outline:none; cursor:pointer;">
              <option value="">All Cities</option>
              <?php foreach ($cities_list as $city): ?>
                <option value="<?php echo htmlspecialchars($city); ?>" <?php echo $city_filter === $city ? 'selected' : ''; ?>><?php echo htmlspecialchars($city); ?></option>
              <?php endforeach; ?>
            </select>
          </form>
          <span style="color:#aaa; font-size:0.85rem;">Showing <?php echo count($customers); ?> of <?php echo $total_rows; ?> customers</span>
        </div>

        <!-- CUSTOMERS TABLE -->
        <div class="admin-table-container">
          <table class="admin-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>City</th>
                <th>Orders</th>
                <th>Total Spent</th>
                <th>Joined</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($customers) > 0): $index = $offset + 1; ?>
                <?php foreach ($customers as $cust): ?>
                  <tr>
                    <td><?php echo $index++; ?></td>
                    <td><strong><?php echo htmlspecialchars($cust['name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($cust['email']); ?></td>
                    <td><?php echo htmlspecialchars($cust['phone']); ?></td>
                    <td><?php echo htmlspecialchars($cust['city']); ?></td>
                    <td><span style="font-weight:700;"><?php echo $cust['order_count']; ?></span></td>
                    <td>Rs. <?php echo number_format($cust['total_spent']); ?></td>
                    <td><?php echo htmlspecialchars($cust['formatted_join']); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="8" style="text-align:center; padding:30px; color:#777;">No customers found.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
          <div style="display:flex; justify-content:center; gap:6px; margin-top:20px;">
            <?php if ($page > 1): ?>
              <a href="AdminCustomers.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&city=<?php echo urlencode($city_filter); ?>" style="padding:8px 12px; border:1.5px solid #111; background:#fff; text-decoration:none; color:#111; font-weight:700;"><i class="fas fa-chevron-left"></i></a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
              <a href="AdminCustomers.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&city=<?php echo urlencode($city_filter); ?>" style="padding:8px 14px; border:1.5px solid #111; <?php echo $i === $page ? 'background:#111; color:#fff;' : 'background:#fff; color:#111;'; ?> text-decoration:none; font-weight:700;"><?php echo $i; ?></a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
              <a href="AdminCustomers.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&city=<?php echo urlencode($city_filter); ?>" style="padding:8px 12px; border:1.5px solid #111; background:#fff; text-decoration:none; color:#111; font-weight:700;"><i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

      </main>
    </div>
  </div>

</body>
</html>
