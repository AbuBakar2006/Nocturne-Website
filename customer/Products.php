<?php
// customer/Products.php
$page_title = 'Products';

// Read query parameters
$search_query = trim($_GET['search'] ?? '');
$category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;

require_once 'header.php';

// Pagination setup (disabled to show all products on one page)
$limit = 999999;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $limit;

try {
    // 1. Fetch categories for filters bar
    $cat_stmt = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
    $categories_list = $cat_stmt->fetchAll();

    // 2. Determine filter clauses
    $where_clauses = [];
    $params = [];

    if ($search_query !== '') {
        $where_clauses[] = "(name LIKE :search OR description LIKE :search)";
        $params['search'] = "%{$search_query}%";
    }

    if ($category_id > 0) {
        $where_clauses[] = "category_id = :category";
        $params['category'] = $category_id;
    }

    $where_sql = '';
    if (count($where_clauses) > 0) {
        $where_sql = ' WHERE ' . implode(' AND ', $where_clauses);
    }

    // 3. Count total matching products for pagination
    $count_sql = "SELECT COUNT(*) FROM products" . $where_sql;
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_products = $count_stmt->fetchColumn();
    $total_pages = ceil($total_products / $limit);

    // 4. Fetch products for current page
    $sql = "SELECT * FROM products" . $where_sql . " ORDER BY id DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $val) {
        $stmt->bindValue(':' . $key, $val);
    }
    $stmt->execute();
    $products = $stmt->fetchAll();

    // Get active category name for display header
    $active_category_name = 'All Products';
    if ($category_id > 0) {
        foreach ($categories_list as $cat) {
            if (intval($cat['id']) === $category_id) {
                $active_category_name = $cat['name'];
                break;
            }
        }
    }
} catch (\PDOException $e) {
    die("Database query error: " . $e->getMessage());
}
?>

    <main class="page-main">
      <div class="page-header" style="background-color:#111; color:#fff; padding:60px 20px; text-align:center;">
        <h1><?php echo htmlspecialchars($active_category_name); ?></h1>
        <?php if ($search_query !== ''): ?>
          <p>Search results for "<strong><?php echo htmlspecialchars($search_query); ?></strong>" (<?php echo $total_products; ?> items found)</p>
        <?php else: ?>
          <p>Explore our entire collection of premium apparel.</p>
        <?php endif; ?>
      </div>

      <!-- Categories Filter Tabs -->
      <div class="category-tabs" style="display:flex; justify-content:center; gap:15px; margin: 40px auto 20px; flex-wrap:wrap; padding: 0 20px;">
        <a href="Products.php<?php echo $search_query !== '' ? '?search=' . urlencode($search_query) : ''; ?>" 
           style="padding:10px 20px; border:1.5px solid #111; background:<?php echo $category_id === 0 ? '#111; color:#fff;' : '#fff; color:#111;'; ?> font-weight:bold; border-radius:0; box-shadow: 4px 4px 0 #000; text-decoration:none; transition:all 0.2s;">
           All Items
        </a>
        <?php foreach ($categories_list as $cat): ?>
          <a href="Products.php?category=<?php echo $cat['id']; ?><?php echo $search_query !== '' ? '&search=' . urlencode($search_query) : ''; ?>" 
             style="padding:10px 20px; border:1.5px solid #111; background:<?php echo $category_id === intval($cat['id']) ? '#111; color:#fff;' : '#fff; color:#111;'; ?> font-weight:bold; border-radius:0; box-shadow: 4px 4px 0 #000; text-decoration:none; transition:all 0.2s;">
             <?php echo htmlspecialchars($cat['name']); ?>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- Products Grid -->
      <div class="products-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 40px; padding: 40px 20px; max-width: 1300px; margin: 0 auto;">
        <?php if (count($products) > 0): ?>
          <?php foreach ($products as $prod): ?>
            <div class="product-card" style="width: 100%; height: auto; display: flex; flex-direction: column; background: #fff; border-radius: 0; border: 1.5px solid #111; box-shadow: 6px 6px 0px #000; transition: transform 0.2s; overflow: hidden; padding-bottom: 20px;">
              <a href="ProductDetails.php?id=<?php echo $prod['id']; ?>">
                <img src="<?php echo htmlspecialchars($prod['image_path']); ?>" alt="<?php echo htmlspecialchars($prod['name']); ?>" style="width:100%; height:320px; object-fit:cover; display:block;" />
              </a>
              <div class="product-info" style="padding: 20px; text-align: left; flex-grow: 1; display:flex; flex-direction:column; justify-content:space-between; gap:10px;">
                <div>
                  <a href="ProductDetails.php?id=<?php echo $prod['id']; ?>" style="text-decoration:none; color:inherit; display:block;">
                    <h3 style="font-size:1.3rem; font-weight:bold; margin-bottom:5px; color:#111;"><?php echo htmlspecialchars($prod['name']); ?></h3>
                    <p style="font-size:0.9rem; color:#666; line-height:1.4; margin-bottom:10px; height: 40px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                      <?php echo htmlspecialchars($prod['description'] ?? ''); ?>
                    </p>
                  </a>
                  <div style="font-size:0.85rem; font-weight:bold; color:#666; margin-top:5px;">
                    Stock: <?php echo $prod['inventory_qty'] > 0 ? htmlspecialchars($prod['inventory_qty']) : '<span style="color:coral;">Out of Stock</span>'; ?>
                  </div>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; border-top: 1px solid #eee; padding-top:15px; margin-top:5px;">
                  <span style="font-size:1.2rem; font-weight:800; color:#111;">Rs. <?php echo number_format($prod['price']); ?></span>
                  <a href="ProductDetails.php?id=<?php echo $prod['id']; ?>" class="login-btn" style="text-decoration:none; font-size:0.85rem; padding:8px 14px; margin:0; border-radius:0;">View Details</a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div style="grid-column: 1 / -1; text-align: center; padding: 60px 20px; border: 1.5px dashed #ccc; background:#fff; font-size:1.1rem; color:#777;">
            <i class="fas fa-search-minus" style="font-size: 2.5rem; margin-bottom: 15px; display:block; color:#aaa;"></i>
            No products found matching your selection.
          </div>
        <?php endif; ?>
      </div>

      <!-- Pagination Navigation -->
      <?php if ($total_pages > 1): ?>
        <div class="pagination" style="display:flex; justify-content:center; align-items:center; gap:8px; margin: 40px auto 80px;">
          <?php if ($current_page > 1): ?>
            <a href="Products.php?page=<?php echo $current_page - 1; ?><?php echo $category_id > 0 ? '&category=' . $category_id : ''; ?><?php echo $search_query !== '' ? '&search=' . urlencode($search_query) : ''; ?>" 
               style="padding:10px 14px; border:1.5px solid #111; background:#fff; color:#111; text-decoration:none; font-weight:bold; box-shadow: 3px 3px 0 #000;">
               <i class="fas fa-chevron-left"></i>
            </a>
          <?php endif; ?>

          <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="Products.php?page=<?php echo $i; ?><?php echo $category_id > 0 ? '&category=' . $category_id : ''; ?><?php echo $search_query !== '' ? '&search=' . urlencode($search_query) : ''; ?>" 
               style="padding:10px 16px; border:1.5px solid #111; background:<?php echo $i === $current_page ? '#111; color:#fff;' : '#fff; color:#111;'; ?> text-decoration:none; font-weight:bold; box-shadow: 3px 3px 0 #000;">
               <?php echo $i; ?>
            </a>
          <?php endfor; ?>

          <?php if ($current_page < $total_pages): ?>
            <a href="Products.php?page=<?php echo $current_page + 1; ?><?php echo $category_id > 0 ? '&category=' . $category_id : ''; ?><?php echo $search_query !== '' ? '&search=' . urlencode($search_query) : ''; ?>" 
               style="padding:10px 14px; border:1.5px solid #111; background:#fff; color:#111; text-decoration:none; font-weight:bold; box-shadow: 3px 3px 0 #000;">
               <i class="fas fa-chevron-right"></i>
            </a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </main>

<?php require_once 'footer.php'; ?>
