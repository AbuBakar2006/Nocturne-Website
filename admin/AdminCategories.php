<?php
// AdminCategories.php
// Categories CRUD page for NOCTURNE Admin.

require_once 'auth.php';
check_admin_login();
require_once 'db_connect.php';

$success_message = '';
$error_message = '';

// 1. Handle Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_category') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($name)) {
        $error_message = "Category Name is required.";
    } else {
        try {
            // Check uniqueness
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ?");
            $stmt->execute([$name]);
            if ($stmt->fetchColumn() > 0) {
                $error_message = "A category with the name '{$name}' already exists.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
                $stmt->execute([$name, $description]);
                $success_message = "Category '{$name}' created successfully.";
            }
        } catch (\PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// 2. Handle Edit Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_category') {
    $category_id = (int)($_POST['category_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if ($category_id <= 0 || empty($name)) {
        $error_message = "Category Name is required.";
    } else {
        try {
            // Check uniqueness except current
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ? AND id != ?");
            $stmt->execute([$name, $category_id]);
            if ($stmt->fetchColumn() > 0) {
                $error_message = "Another category with the name '{$name}' already exists.";
            } else {
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $description, $category_id]);
                $success_message = "Category '{$name}' updated successfully.";
            }
        } catch (\PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// 3. Handle Delete Category
if (isset($_GET['delete'])) {
    $category_id = (int)$_GET['delete'];
    try {
        // Fetch category name
        $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $stmt->execute([$category_id]);
        $cat_name = $stmt->fetchColumn();
        
        if ($cat_name) {
            // Check if products exist in category
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
            $stmt->execute([$category_id]);
            $prod_count = $stmt->fetchColumn();
            
            if ($prod_count > 0) {
                $error_message = "Cannot delete category '{$cat_name}' because it contains {$prod_count} products. Please re-assign those products first.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$category_id]);
                $success_message = "Category '{$cat_name}' deleted successfully.";
            }
        }
    } catch (\PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Fetch categories list with product counts
try {
    $sql = "SELECT c.*, COUNT(p.id) AS product_count 
            FROM categories c 
            LEFT JOIN products p ON c.id = p.category_id 
            GROUP BY c.id 
            ORDER BY c.name ASC";
    $categories = $pdo->query($sql)->fetchAll();
    
    // Stats metrics
    $total_categories = count($categories);
    $active_associations = $pdo->query("SELECT COUNT(*) FROM products WHERE category_id IS NOT NULL")->fetchColumn();
} catch (\PDOException $e) {
    die("Database query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Manage product categories on NOCTURNE admin panel">
  <title>Categories | NOCTURNE Admin</title>
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
      max-width: 500px;
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
    .form-group input, .form-group textarea {
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
      min-height: 100px;
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
          <h1>Category Management</h1>
        </div>
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

        <!-- SUMMARY CARDS -->
        <section class="admin-stats" style="grid-template-columns: repeat(2, 1fr); margin-bottom: 28px;">
          <div class="stat-card">
            <div style="width:42px; height:42px; background:#fff8e6; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:12px;">
              <i class="fas fa-tags" style="color:#fca311; font-size:1.1rem;"></i>
            </div>
            <h3>Total Categories</h3>
            <div class="value"><?php echo $total_categories; ?></div>
            <div class="trend">For sorting products</div>
          </div>
          <div class="stat-card">
            <div style="width:42px; height:42px; background:#e8f4ff; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:12px;">
              <i class="fas fa-box" style="color:#3b82f6; font-size:1.1rem;"></i>
            </div>
            <h3>Categorized Products</h3>
            <div class="value"><?php echo $active_associations; ?></div>
            <div class="trend">Products assigned</div>
          </div>
        </section>

        <!-- ADD BUTTON -->
        <div style="display:flex; justify-content:flex-end; align-items:center; margin-bottom:20px;">
          <button onclick="openAddModal()" class="btn-add">
            <i class="fas fa-plus" style="margin-right:6px;"></i>Add Category
          </button>
        </div>

        <!-- CATEGORIES TABLE -->
        <div class="admin-table-container">
          <table class="admin-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Category Name</th>
                <th>Description</th>
                <th>Product Count</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($categories) > 0): $index = 1; ?>
                <?php foreach ($categories as $cat): ?>
                  <tr>
                    <td><?php echo $index++; ?></td>
                    <td><strong><?php echo htmlspecialchars($cat['name']); ?></strong></td>
                    <td style="white-space: normal; color: #666; font-size:0.85rem; max-width: 350px;">
                      <?php echo htmlspecialchars($cat['description'] ?: 'No description provided.'); ?>
                    </td>
                    <td><span style="font-weight:700;"><?php echo htmlspecialchars($cat['product_count']); ?></span> products</td>
                    <td>
                      <div class="action-btns">
                        <button onclick='openEditModal(<?php echo json_encode($cat, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)' class="btn-edit" style="background:none; border:none; cursor:pointer; font-family:inherit;"><i class="fas fa-edit"></i> Edit</button>
                        <a href="AdminCategories.php?delete=<?php echo $cat['id']; ?>" onclick="return confirm('Are you sure you want to delete this category?')" class="btn-delete"><i class="fas fa-trash"></i> Delete</a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5" style="text-align:center; padding: 30px; color:#777;">No categories found. Click 'Add Category' to get started.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      </main>
    </div>
  </div>

  <!-- ADD CATEGORY MODAL -->
  <div id="addModal" class="modal">
    <div class="modal-content">
      <span class="close-btn" onclick="closeAddModal()">&times;</span>
      <h2 style="margin-top:0; border-bottom: 2px solid #111; padding-bottom:10px;">Add Category</h2>
      <form method="post" action="AdminCategories.php">
        <input type="hidden" name="action" value="add_category">
        
        <div class="form-group">
          <label>Category Name*</label>
          <input type="text" name="name" required placeholder="e.g. Graphic Sweaters">
        </div>
        
        <div class="form-group">
          <label>Description</label>
          <textarea name="description" placeholder="Write description details here..."></textarea>
        </div>
        
        <div style="display:flex; justify-content: flex-end; gap:12px; margin-top:20px;">
          <button type="button" onclick="closeAddModal()" class="btn-add" style="background:#888;">Cancel</button>
          <button type="submit" class="btn-add">Save Category</button>
        </div>
      </form>
    </div>
  </div>

  <!-- EDIT CATEGORY MODAL -->
  <div id="editModal" class="modal">
    <div class="modal-content">
      <span class="close-btn" onclick="closeEditModal()">&times;</span>
      <h2 style="margin-top:0; border-bottom: 2px solid #111; padding-bottom:10px;">Edit Category</h2>
      <form method="post" action="AdminCategories.php">
        <input type="hidden" name="action" value="edit_category">
        <input type="hidden" name="category_id" id="edit-id">
        
        <div class="form-group">
          <label>Category Name*</label>
          <input type="text" name="name" id="edit-name" required>
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
    function openAddModal() {
        document.getElementById('addModal').classList.add('show');
    }
    function closeAddModal() {
        document.getElementById('addModal').classList.remove('show');
    }
    
    function openEditModal(category) {
        document.getElementById('edit-id').value = category.id;
        document.getElementById('edit-name').value = category.name;
        document.getElementById('edit-description').value = category.description;
        document.getElementById('editModal').classList.add('show');
    }
    function closeEditModal() {
        document.getElementById('editModal').classList.remove('show');
    }
    
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
  </script>

</body>
</html>
