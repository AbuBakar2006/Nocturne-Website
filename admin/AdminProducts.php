<?php
// AdminProducts.php
// Product CRUD page for NOCTURNE Admin.

require_once 'auth.php';
check_admin_login();
require_once 'db_connect.php';

$success_message = $_GET['success_msg'] ?? '';
$error_message = $_GET['error_msg'] ?? '';

// Image Upload and Resizing Helper
function resize_and_upload_image($file, $target_dir, $max_dim = 600) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return false;
    }
    
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    // Check if GD is available
    if (!function_exists('imagecreatefromjpeg') || !function_exists('imagecreatetruecolor')) {
        // Fallback to standard upload
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'prod_' . time() . '_' . rand(100, 999) . '.' . $ext;
        $target_file = $target_dir . $filename;
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            return '../images/Designs/' . $filename;
        }
        return false;
    }
    
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        return false; // Not a valid image
    }
    
    $mime = $info['mime'];
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowed_mimes)) {
        return false;
    }
    
    $ext = '';
    $src_img = null;
    switch ($mime) {
        case 'image/jpeg':
            $src_img = @imagecreatefromjpeg($file['tmp_name']);
            $ext = '.jpg';
            break;
        case 'image/png':
            $src_img = @imagecreatefrompng($file['tmp_name']);
            $ext = '.png';
            break;
        case 'image/webp':
            $src_img = @imagecreatefromwebp($file['tmp_name']);
            $ext = '.webp';
            break;
    }
    
    if (!$src_img) {
        return false;
    }
    
    $orig_w = imagesx($src_img);
    $orig_h = imagesy($src_img);
    
    $new_w = $orig_w;
    $new_h = $orig_h;
    
    if ($orig_w > $orig_h) {
        if ($orig_w > $max_dim) {
            $new_w = $max_dim;
            $new_h = round($orig_h * ($max_dim / $orig_w));
        }
    } else {
        if ($orig_h > $max_dim) {
            $new_h = $max_dim;
            $new_w = round($orig_w * ($max_dim / $orig_h));
        }
    }
    
    $dst_img = imagecreatetruecolor($new_w, $new_h);
    
    // Handle transparency
    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($dst_img, false);
        imagesavealpha($dst_img, true);
        $transparent = imagecolorallocatealpha($dst_img, 255, 255, 255, 127);
        imagefilledrectangle($dst_img, 0, 0, $new_w, $new_h, $transparent);
    }
    
    imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);
    
    $filename = 'prod_' . time() . '_' . rand(100, 999) . $ext;
    $target_file = $target_dir . $filename;
    
    $saved = false;
    switch ($mime) {
        case 'image/jpeg':
            $saved = imagejpeg($dst_img, $target_file, 85);
            break;
        case 'image/png':
            $saved = imagepng($dst_img, $target_file, 6);
            break;
        case 'image/webp':
            $saved = imagewebp($dst_img, $target_file, 80);
            break;
    }
    
    imagedestroy($src_img);
    imagedestroy($dst_img);
    
    return $saved ? '../images/Designs/' . $filename : false;
}

// 1. Handle Product ADD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {
    $name = trim($_POST['name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $price = (float)($_POST['price'] ?? 0.00);
    $inventory_qty = (int)($_POST['inventory_qty'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    
    if (empty($name) || $category_id <= 0 || $price < 0 || $inventory_qty < 0) {
        $error_message = "Please fill in all required fields with valid values.";
    } else {
        $uploaded_images = [];
        if (isset($_FILES['product_images']) && is_array($_FILES['product_images']['name'])) {
            for ($i = 0; $i < count($_FILES['product_images']['name']); $i++) {
                if ($_FILES['product_images']['error'][$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['product_images']['name'][$i],
                        'tmp_name' => $_FILES['product_images']['tmp_name'][$i],
                        'error' => $_FILES['product_images']['error'][$i],
                        'size' => $_FILES['product_images']['size'][$i],
                        'type' => $_FILES['product_images']['type'][$i],
                    ];
                    $uploaded_path = resize_and_upload_image($file, '../images/Designs/');
                    if ($uploaded_path) {
                        $uploaded_images[$i] = $uploaded_path;
                    }
                }
            }
        }
        
        $final_images = [null, null, null, null];
        $order = $_POST['images_order'] ?? [];
        for ($i = 0; $i < 4; $i++) {
            if (isset($order[$i])) {
                $val = $order[$i];
                if (strpos($val, 'existing:') === 0) {
                    $final_images[$i] = str_replace('existing:', '', $val);
                } elseif (strpos($val, 'new:') === 0) {
                    $new_idx = (int)str_replace('new:', '', $val);
                    if (isset($uploaded_images[$new_idx])) {
                        $final_images[$i] = $uploaded_images[$new_idx];
                    }
                }
            }
        }
        
        $image_path = $final_images[0] ?: '../images/Designs/1.webp';
        $image_path2 = $final_images[1];
        $image_path3 = $final_images[2];
        $image_path4 = $final_images[3];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO products (category_id, name, description, price, image_path, image_path2, image_path3, image_path4, inventory_qty) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$category_id, $name, $description, $price, $image_path, $image_path2, $image_path3, $image_path4, $inventory_qty]);
            $success_message = "Product '{$name}' created successfully.";
        } catch (\PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
        header('Content-Type: application/json');
        if (!empty($error_message)) {
            echo json_encode(['status' => 'error', 'message' => $error_message]);
        } else {
            echo json_encode(['status' => 'success', 'message' => $success_message]);
        }
        exit;
    }
}

// 2. Handle Product EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_product') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $price = (float)($_POST['price'] ?? 0.00);
    $inventory_qty = (int)($_POST['inventory_qty'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    
    if ($product_id <= 0 || empty($name) || $category_id <= 0 || $price < 0 || $inventory_qty < 0) {
        $error_message = "Please fill in all required fields with valid values.";
    } else {
        $uploaded_images = [];
        if (isset($_FILES['product_images']) && is_array($_FILES['product_images']['name'])) {
            for ($i = 0; $i < count($_FILES['product_images']['name']); $i++) {
                if ($_FILES['product_images']['error'][$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['product_images']['name'][$i],
                        'tmp_name' => $_FILES['product_images']['tmp_name'][$i],
                        'error' => $_FILES['product_images']['error'][$i],
                        'size' => $_FILES['product_images']['size'][$i],
                        'type' => $_FILES['product_images']['type'][$i],
                    ];
                    $uploaded_path = resize_and_upload_image($file, '../images/Designs/');
                    if ($uploaded_path) {
                        $uploaded_images[$i] = $uploaded_path;
                    }
                }
            }
        }
        
        $final_images = [null, null, null, null];
        $order = $_POST['images_order'] ?? [];
        for ($i = 0; $i < 4; $i++) {
            if (isset($order[$i])) {
                $val = $order[$i];
                if (strpos($val, 'existing:') === 0) {
                    $final_images[$i] = str_replace('existing:', '', $val);
                } elseif (strpos($val, 'new:') === 0) {
                    $new_idx = (int)str_replace('new:', '', $val);
                    if (isset($uploaded_images[$new_idx])) {
                        $final_images[$i] = $uploaded_images[$new_idx];
                    }
                }
            }
        }
        
        $image_path = $final_images[0] ?: '../images/Designs/1.webp';
        $image_path2 = $final_images[1];
        $image_path3 = $final_images[2];
        $image_path4 = $final_images[3];
        
        try {
            $stmt = $pdo->prepare("UPDATE products SET category_id = ?, name = ?, description = ?, price = ?, image_path = ?, image_path2 = ?, image_path3 = ?, image_path4 = ?, inventory_qty = ? WHERE id = ?");
            $stmt->execute([$category_id, $name, $description, $price, $image_path, $image_path2, $image_path3, $image_path4, $inventory_qty, $product_id]);
            $success_message = "Product '{$name}' updated successfully.";
        } catch (\PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
        header('Content-Type: application/json');
        if (!empty($error_message)) {
            echo json_encode(['status' => 'error', 'message' => $error_message]);
        } else {
            echo json_encode(['status' => 'success', 'message' => $success_message]);
        }
        exit;
    }
}

// 3. Handle Product DELETE
if (isset($_GET['delete'])) {
    $product_id = (int)$_GET['delete'];
    try {
        // Fetch product name for success alert
        $stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $prod_name = $stmt->fetchColumn();
        
        if ($prod_name) {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $success_message = "Product '{$prod_name}' deleted successfully.";
        }
    } catch (\PDOException $e) {
        $error_message = "Could not delete product (it might be linked to past order records).";
    }
}

// Fetch categories for forms and filters
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Product Filters & Queries
$search = trim($_GET['search'] ?? '');
$cat_filter = (int)($_GET['category_id'] ?? 0);
$stock_filter = trim($_GET['stock'] ?? '');

$where_clauses = [];
$params = [];

if ($search !== '') {
    $where_clauses[] = "p.name LIKE :search";
    $params['search'] = "%{$search}%";
}

if ($cat_filter > 0) {
    $where_clauses[] = "p.category_id = :category_id";
    $params['category_id'] = $cat_filter;
}

if ($stock_filter !== '') {
    if ($stock_filter === 'instock') {
        $where_clauses[] = "p.inventory_qty > 10";
    } elseif ($stock_filter === 'low') {
        $where_clauses[] = "p.inventory_qty > 0 AND p.inventory_qty <= 10";
    } elseif ($stock_filter === 'out') {
        $where_clauses[] = "p.inventory_qty = 0";
    }
}

$where_sql = '';
if (count($where_clauses) > 0) {
    $where_sql = ' WHERE ' . implode(' AND ', $where_clauses);
}

// Pagination setup
$limit = 5;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

try {
    // Total count for filters
    $count_sql = "SELECT COUNT(*) FROM products p" . $where_sql;
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_rows = $stmt->fetchColumn();
    $total_pages = ceil($total_rows / $limit);

    // Fetch filtered products
    $sql = "SELECT p.*, c.name AS category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id" 
            . $where_sql . " 
            ORDER BY p.id DESC 
            LIMIT :limit OFFSET :offset";
            
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $val) {
        $stmt->bindValue(':' . $key, $val);
    }
    $stmt->execute();
    $products = $stmt->fetchAll();

    // Inventory status metrics counts
    $in_stock_cnt = $pdo->query("SELECT COUNT(*) FROM products WHERE inventory_qty > 10")->fetchColumn();
    $low_stock_cnt = $pdo->query("SELECT COUNT(*) FROM products WHERE inventory_qty > 0 AND inventory_qty <= 10")->fetchColumn();
    $out_of_stock_cnt = $pdo->query("SELECT COUNT(*) FROM products WHERE inventory_qty = 0")->fetchColumn();
    $total_units_cnt = $pdo->query("SELECT SUM(inventory_qty) FROM products")->fetchColumn() ?: 0;

} catch (\PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Manage products on NOCTURNE admin panel">
  <title>Products | NOCTURNE Admin</title>
  <link href="../font-awesome/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <style>
    /* Modal styles */
    .modal {
      display: none;
      position: fixed;
      z-index: 2000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto;
      background-color: rgba(0,0,0,0.6);
      align-items: center;
      justify-content: center;
    }
    .modal.show {
      display: flex;
    }
    .modal-content {
      background-color: #fff;
      padding: 30px;
      border: 2px solid #111;
      box-shadow: 10px 10px 0px #000;
      width: 90%;
      max-width: 550px;
      position: relative;
    }
    .close-btn {
      position: absolute;
      top: 15px;
      right: 20px;
      font-size: 1.5rem;
      cursor: pointer;
      color: #111;
      font-weight: bold;
    }
    .close-btn:hover {
      color: #fca311;
    }
    .form-group {
      margin-bottom: 16px;
      text-align: left;
    }
    .form-group label {
      display: block;
      font-weight: 700;
      margin-bottom: 6px;
      font-size: 0.9rem;
    }
    .form-group input, .form-group select, .form-group textarea {
      width: 100%;
      padding: 10px;
      border: 1px solid #111;
      border-radius: 4px;
      font-family: inherit;
      font-size: 0.9rem;
      background: #fcfbf0;
    }
    .form-group textarea {
      resize: vertical;
      min-height: 80px;
    }
    /* Upload Zone & Image Card Styles */
    .image-upload-zone {
      border: 2px dashed #111;
      background: #fcfbf0;
      padding: 24px;
      text-align: center;
      cursor: pointer;
      border-radius: 4px;
      transition: all 0.3s ease;
      box-shadow: 8px 8px 0px #000;
      border: 1.5px solid #111;
    }
    .image-upload-zone:hover {
      background: #fff8e6;
      border-color: #fca311;
    }
    .image-card {
      position: relative;
      width: 100px;
      height: 130px;
      border: 1.5px solid #111;
      box-shadow: 4px 4px 0px #000;
      background: #fff;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: space-between;
      padding: 4px;
    }
    .image-card img {
      width: 100%;
      height: 75px;
      object-fit: cover;
      border-bottom: 1.5px solid #111;
    }
    .image-card .badge {
      font-size: 0.55rem;
      font-weight: 800;
      padding: 2px 6px;
      border-radius: 20px;
      position: absolute;
      top: 4px;
      left: 4px;
      border: 1px solid #111;
      box-shadow: 1px 1px 0px #000;
    }
    .badge-primary {
      background: #fca311;
      color: #000;
    }
    .badge-secondary {
      background: #eee;
      color: #444;
    }
  </style>
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
          <h1>Product Management</h1>
        </div>
        <form method="get" action="AdminProducts.php" style="display:flex; align-items:center; gap:16px;">
          <div style="position:relative;">
            <i class="fas fa-search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#aaa;"></i>
            <input type="text" name="search" placeholder="Search productsÃ¢â‚¬Â¦" value="<?php echo htmlspecialchars($search); ?>" style="padding:8px 12px 8px 34px; border:1px solid #ddd; border-radius:8px; font-size:0.9rem; outline:none; width:200px;">
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

        <!-- INVENTORY SUMMARY -->
        <section class="admin-stats">
          <div class="stat-card">
            <div style="width:42px; height:42px; background:#f0faf0; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:12px;">
              <i class="fas fa-check-circle" style="color:#10b981; font-size:1.1rem;"></i>
            </div>
            <h3>In Stock</h3>
            <div class="value"><?php echo $in_stock_cnt; ?></div>
            <div class="trend">Active products</div>
          </div>
          <div class="stat-card">
            <div style="width:42px; height:42px; background:#fff8e6; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:12px;">
              <i class="fas fa-exclamation-triangle" style="color:#fca311; font-size:1.1rem;"></i>
            </div>
            <h3>Low Stock</h3>
            <div class="value"><?php echo $low_stock_cnt; ?></div>
            <div class="trend" style="color:#fca311;">Restock soon</div>
          </div>
          <div class="stat-card">
            <div style="width:42px; height:42px; background:#fff0f0; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:12px;">
              <i class="fas fa-times-circle" style="color:#ef4444; font-size:1.1rem;"></i>
            </div>
            <h3>Out of Stock</h3>
            <div class="value"><?php echo $out_of_stock_cnt; ?></div>
            <div class="trend" style="color:#ef4444;">Needs restocking</div>
          </div>
          <div class="stat-card">
            <div style="width:42px; height:42px; background:#e8f4ff; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:12px;">
              <i class="fas fa-cubes" style="color:#3b82f6; font-size:1.1rem;"></i>
            </div>
            <h3>Total Units</h3>
            <div class="value"><?php echo $total_units_cnt; ?></div>
            <div class="trend">Across all items</div>
          </div>
        </section>

        <!-- FILTERS + ADD BUTTON -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
          <form method="get" action="AdminProducts.php" style="display:flex; gap:10px; flex-wrap:wrap;">
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
            
            <select name="category_id" onchange="this.form.submit()" style="padding:8px 12px; border:1px solid #ddd; border-radius:8px; font-size:0.88rem; outline:none; cursor:pointer;">
              <option value="">All Categories</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo $cat_filter === (int)$cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
              <?php endforeach; ?>
            </select>
            
            <select name="stock" onchange="this.form.submit()" style="padding:8px 12px; border:1px solid #ddd; border-radius:8px; font-size:0.88rem; outline:none; cursor:pointer;">
              <option value="">All Stock</option>
              <option value="instock" <?php echo $stock_filter === 'instock' ? 'selected' : ''; ?>>In Stock</option>
              <option value="low" <?php echo $stock_filter === 'low' ? 'selected' : ''; ?>>Low Stock (<=10)</option>
              <option value="out" <?php echo $stock_filter === 'out' ? 'selected' : ''; ?>>Out of Stock (0)</option>
            </select>
          </form>
          
          <button onclick="openAddModal()" class="btn-add">
            <i class="fas fa-plus" style="margin-right:6px;"></i>Add Product
          </button>
        </div>

        <!-- PRODUCTS TABLE -->
        <div class="admin-table-container">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Image</th>
                <th>Product Name</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($products) > 0): ?>
                <?php foreach ($products as $prod): 
                  // Status & stock indicators
                  $badge_class = 'status-active';
                  $badge_label = 'In Stock';
                  if ($prod['inventory_qty'] == 0) {
                      $badge_class = 'status-cancelled';
                      $badge_label = 'Out of Stock';
                  } elseif ($prod['inventory_qty'] <= 10) {
                      $badge_class = 'status-pending';
                      $badge_label = 'Low Stock';
                  }
                  
                  $stock_color = '#111';
                  if ($prod['inventory_qty'] == 0) {
                      $stock_color = '#c62828';
                  } elseif ($prod['inventory_qty'] <= 10) {
                      $stock_color = '#f59e0b';
                  }
                ?>
                  <tr>
                    <td><img src="<?php echo htmlspecialchars($prod['image_path']); ?>" style="width:50px; height:50px; border-radius:8px; object-fit:cover; border: 1.5px solid #111;" alt="<?php echo htmlspecialchars($prod['name']); ?>"></td>
                    <td><strong><?php echo htmlspecialchars($prod['name']); ?></strong><br><span style="font-size:0.78rem; color:#aaa;"><?php echo htmlspecialchars(substr($prod['description'], 0, 30)) . (strlen($prod['description']) > 30 ? '...' : ''); ?></span></td>
                    <td><?php echo htmlspecialchars($prod['category_name'] ?: 'None'); ?></td>
                    <td>Rs. <?php echo number_format($prod['price']); ?></td>
                    <td><span style="font-weight:600; color: <?php echo $stock_color; ?>;"><?php echo htmlspecialchars($prod['inventory_qty']); ?></span></td>
                    <td><span class="status-badge <?php echo $badge_class; ?>"><?php echo $badge_label; ?></span></td>
                    <td>
                      <div class="action-btns">
                        <button onclick='openEditModal(<?php echo json_encode($prod, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)' class="btn-edit" style="background:none; border:none; cursor:pointer; font-family:inherit;"><i class="fas fa-edit"></i> Edit</button>
                        <a href="AdminProducts.php?delete=<?php echo $prod['id']; ?>" onclick="return confirm('Are you sure you want to delete this product? This action cannot be undone.')" class="btn-delete"><i class="fas fa-trash"></i> Delete</a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7" style="text-align:center; padding: 30px; color:#777;">No products found matching filters.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
          <div style="display:flex; justify-content:center; gap:6px; margin-top:20px;">
            <?php if ($page > 1): ?>
              <a href="AdminProducts.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category_id=<?php echo $cat_filter; ?>&stock=<?php echo urlencode($stock_filter); ?>" style="padding:8px 12px; border:1.5px solid #111; background:#fff; text-decoration:none; color:#111; font-weight:700;"><i class="fas fa-chevron-left"></i></a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
              <a href="AdminProducts.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category_id=<?php echo $cat_filter; ?>&stock=<?php echo urlencode($stock_filter); ?>" style="padding:8px 14px; border:1.5px solid #111; <?php echo $i === $page ? 'background:#111; color:#fff;' : 'background:#fff; color:#111;'; ?> text-decoration:none; font-weight:700;"><?php echo $i; ?></a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
              <a href="AdminProducts.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category_id=<?php echo $cat_filter; ?>&stock=<?php echo urlencode($stock_filter); ?>" style="padding:8px 12px; border:1.5px solid #111; background:#fff; text-decoration:none; color:#111; font-weight:700;"><i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

      </main>
    </div>
  </div>

  <!-- ADD PRODUCT MODAL -->
  <div id="addModal" class="modal">
    <div class="modal-content">
      <span class="close-btn" onclick="closeAddModal()">&times;</span>
      <h2 style="margin-top:0; border-bottom: 2px solid #111; padding-bottom:10px;">Add New Product</h2>
      <form id="add-product-form" method="post" action="AdminProducts.php" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add_product">
        
        <div class="form-group">
          <label>Product Name*</label>
          <input type="text" name="name" required placeholder="e.g. Classic Hooded Sweatshirt">
        </div>
        
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
          <div class="form-group">
            <label>Category*</label>
            <select name="category_id" required>
              <option value="">Select Category</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Price (Rs.)*</label>
            <input type="number" step="1" name="price" min="0" required placeholder="e.g. 2999">
          </div>
        </div>
        
        <div class="form-group">
          <label>Inventory Stock Qty*</label>
          <input type="number" name="inventory_qty" min="0" required placeholder="e.g. 25">
        </div>
        
        <div class="form-group">
          <label>Product Images (Up to 4 images, drag or browse, first image is primary)</label>
          <div class="image-upload-zone" id="add-upload-zone">
              <i class="fas fa-cloud-upload-alt" style="font-size: 1.8rem; color: #fca311; margin-bottom: 8px;"></i>
              <p style="font-weight: 700; margin: 0; font-size: 0.9rem; color: #111;">Drag & Drop Images Here</p>
              <p style="color: #777; font-size: 0.75rem; margin: 4px 0 0 0;">or click to browse</p>
              <input type="file" id="add-file-input" multiple accept="image/*" style="display: none;">
          </div>
          <div class="image-preview-container" id="add-preview-container" style="display: flex; gap: 16px; margin-top: 18px; flex-wrap: wrap;"></div>
        </div>
        
        <div class="form-group">
          <label>Description</label>
          <textarea name="description" placeholder="Write description details here..."></textarea>
        </div>
        
        <div style="display:flex; justify-content: flex-end; gap:12px; margin-top:20px;">
          <button type="button" onclick="closeAddModal()" class="btn-add" style="background:#888;">Cancel</button>
          <button type="submit" class="btn-add">Save Product</button>
        </div>
      </form>
    </div>
  </div>

  <!-- EDIT PRODUCT MODAL -->
  <div id="editModal" class="modal">
    <div class="modal-content">
      <span class="close-btn" onclick="closeEditModal()">&times;</span>
      <h2 style="margin-top:0; border-bottom: 2px solid #111; padding-bottom:10px;">Edit Product</h2>
      <form id="edit-product-form" method="post" action="AdminProducts.php" enctype="multipart/form-data">
        <input type="hidden" name="action" value="edit_product">
        <input type="hidden" name="product_id" id="edit-id">
        
        <div class="form-group">
          <label>Product Name*</label>
          <input type="text" name="name" id="edit-name" required>
        </div>
        
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
          <div class="form-group">
            <label>Category*</label>
            <select name="category_id" id="edit-category" required>
              <option value="">Select Category</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Price (Rs.)*</label>
            <input type="number" step="1" name="price" id="edit-price" min="0" required>
          </div>
        </div>
        
        <div class="form-group">
          <label>Inventory Stock Qty*</label>
          <input type="number" name="inventory_qty" id="edit-stock" min="0" required>
        </div>
        
        <div class="form-group">
          <label>Product Images (Up to 4 images, drag or move to reorder, first image is primary)</label>
          <div class="image-upload-zone" id="edit-upload-zone">
              <i class="fas fa-cloud-upload-alt" style="font-size: 1.8rem; color: #fca311; margin-bottom: 8px;"></i>
              <p style="font-weight: 700; margin: 0; font-size: 0.9rem; color: #111;">Drag & Drop Images Here</p>
              <p style="color: #777; font-size: 0.75rem; margin: 4px 0 0 0;">or click to browse</p>
              <input type="file" id="edit-file-input" multiple accept="image/*" style="display: none;">
          </div>
          <div class="image-preview-container" id="edit-preview-container" style="display: flex; gap: 16px; margin-top: 18px; flex-wrap: wrap;"></div>
        </div>
        
        <div class="form-group">
          <label>Description</label>
          <textarea name="description" id="edit-description"></textarea>
        </div>
        
        <div style="display:flex; justify-content: flex-end; gap:12px; margin-top:20px;">
          <button type="button" onclick="closeEditModal()" class="btn-add" style="background:#888;">Cancel</button>
          <button type="submit" class="btn-add">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    let addImages = [];
    let editImages = [];

    function renderImages(containerId, images, listVarName) {
        const container = document.getElementById(containerId);
        container.innerHTML = '';
        
        images.forEach((img, index) => {
            const card = document.createElement('div');
            card.className = 'image-card';
            
            const src = img.type === 'existing' ? img.path : img.previewUrl;
            
            const imgEl = document.createElement('img');
            imgEl.src = src;
            card.appendChild(imgEl);
            
            // Badge
            const badge = document.createElement('span');
            badge.className = index === 0 ? 'badge badge-primary' : 'badge badge-secondary';
            badge.textContent = index === 0 ? 'Primary' : 'Alt ' + index;
            card.appendChild(badge);
            
            // Controls
            const controls = document.createElement('div');
            controls.style.display = 'flex';
            controls.style.justifyContent = 'space-between';
            controls.style.width = '100%';
            controls.style.padding = '2px 4px';
            
            // Move Left
            const moveLeftBtn = document.createElement('button');
            moveLeftBtn.type = 'button';
            moveLeftBtn.innerHTML = '<i class="fas fa-arrow-left"></i>';
            moveLeftBtn.style.background = 'none';
            moveLeftBtn.style.border = 'none';
            moveLeftBtn.style.cursor = 'pointer';
            moveLeftBtn.style.color = '#111';
            if (index === 0) moveLeftBtn.style.visibility = 'hidden';
            moveLeftBtn.onclick = () => {
                const temp = images[index];
                images[index] = images[index - 1];
                images[index - 1] = temp;
                renderImages(containerId, images, listVarName);
            };
            controls.appendChild(moveLeftBtn);
            
            // Delete
            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
            deleteBtn.style.background = 'none';
            deleteBtn.style.border = 'none';
            deleteBtn.style.cursor = 'pointer';
            deleteBtn.style.color = '#111';
            deleteBtn.onclick = () => {
                images.splice(index, 1);
                renderImages(containerId, images, listVarName);
            };
            controls.appendChild(deleteBtn);
            
            // Move Right
            const moveRightBtn = document.createElement('button');
            moveRightBtn.type = 'button';
            moveRightBtn.innerHTML = '<i class="fas fa-arrow-right"></i>';
            moveRightBtn.style.background = 'none';
            moveRightBtn.style.border = 'none';
            moveRightBtn.style.cursor = 'pointer';
            moveRightBtn.style.color = '#111';
            if (index === images.length - 1) moveRightBtn.style.visibility = 'hidden';
            moveRightBtn.onclick = () => {
                const temp = images[index];
                images[index] = images[index + 1];
                images[index + 1] = temp;
                renderImages(containerId, images, listVarName);
            };
            controls.appendChild(moveRightBtn);
            
            card.appendChild(controls);
            container.appendChild(card);
        });
    }

    function setupUploadZone(zoneId, fileInputId, images, containerId, listVarName) {
        const zone = document.getElementById(zoneId);
        const input = document.getElementById(fileInputId);
        
        zone.onclick = () => input.click();
        
        zone.ondragover = (e) => {
            e.preventDefault();
            zone.style.background = '#fff8e6';
            zone.style.borderColor = '#fca311';
        };
        zone.ondragleave = () => {
            zone.style.background = '#fcfbf0';
            zone.style.borderColor = '#111';
        };
        zone.ondrop = (e) => {
            e.preventDefault();
            zone.style.background = '#fcfbf0';
            zone.style.borderColor = '#111';
            handleFiles(e.dataTransfer.files);
        };
        
        input.onchange = () => {
            handleFiles(input.files);
        };
        
        function handleFiles(files) {
            for (let file of files) {
                if (images.length >= 4) {
                    alert("You can only upload up to 4 images.");
                    break;
                }
                if (!file.type.startsWith('image/')) continue;
                
                const reader = new FileReader();
                reader.onload = (e) => {
                    images.push({
                        type: 'new',
                        file: file,
                        previewUrl: e.target.result
                    });
                    renderImages(containerId, images, listVarName);
                };
                reader.readAsDataURL(file);
            }
            input.value = ''; // clear input
        }
    }

    function setupFormSubmit(formId, imagesArray, actionName) {
        const form = document.getElementById(formId);
        form.onsubmit = function(e) {
            e.preventDefault();
            
            if (imagesArray.length === 0) {
                alert("Please add at least one product image.");
                return;
            }
            
            const formData = new FormData(form);
            // Clean out default file inputs
            formData.delete('product_image');
            formData.delete('product_image2');
            formData.delete('product_image3');
            formData.delete('product_image4');
            
            // Append images in order
            imagesArray.forEach((img, index) => {
                if (img.type === 'new') {
                    formData.append('product_images[]', img.file);
                    formData.append('images_order[]', 'new:' + index);
                } else {
                    formData.append('images_order[]', 'existing:' + img.path);
                }
            });
            
            // Append ajax marker
            formData.append('ajax', '1');
            
            // Submit via fetch
            fetch('AdminProducts.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    window.location.href = 'AdminProducts.php?success_msg=' + encodeURIComponent(data.message);
                } else {
                    window.location.href = 'AdminProducts.php?error_msg=' + encodeURIComponent(data.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert("An error occurred while saving the product.");
            });
        };
    }

    function openAddModal() {
        addImages.length = 0;
        renderImages('add-preview-container', addImages, 'addImages');
        document.getElementById('addModal').classList.add('show');
    }
    
    function closeAddModal() {
        document.getElementById('addModal').classList.remove('show');
    }
    
    function openEditModal(product) {
        document.getElementById('edit-id').value = product.id;
        document.getElementById('edit-name').value = product.name;
        document.getElementById('edit-category').value = product.category_id;
        document.getElementById('edit-price').value = Math.round(product.price);
        document.getElementById('edit-stock').value = product.inventory_qty;
        document.getElementById('edit-description').value = product.description;
        
        editImages.length = 0;
        if (product.image_path) {
            editImages.push({ type: 'existing', path: product.image_path });
        }
        if (product.image_path2) {
            editImages.push({ type: 'existing', path: product.image_path2 });
        }
        if (product.image_path3) {
            editImages.push({ type: 'existing', path: product.image_path3 });
        }
        if (product.image_path4) {
            editImages.push({ type: 'existing', path: product.image_path4 });
        }
        
        renderImages('edit-preview-container', editImages, 'editImages');
        document.getElementById('editModal').classList.add('show');
    }
    
    function closeEditModal() {
        document.getElementById('editModal').classList.remove('show');
    }
    
    // Close modals on clicking outside content
    window.onclick = function(event) {
        var addM = document.getElementById('addModal');
        var editM = document.getElementById('editModal');
        if (event.target == addM) {
            closeAddModal();
        }
        if (event.target == editM) {
            closeEditModal();
        }
    }

    window.addEventListener('DOMContentLoaded', () => {
        setupUploadZone('add-upload-zone', 'add-file-input', addImages, 'add-preview-container', 'addImages');
        setupUploadZone('edit-upload-zone', 'edit-file-input', editImages, 'edit-preview-container', 'editImages');
        
        setupFormSubmit('add-product-form', addImages, 'add_product');
        setupFormSubmit('edit-product-form', editImages, 'edit_product');
    });
  </script>

</body>
</html>
