<?php
// customer/Homepage.php
$page_title = 'Home';
require_once 'header.php';

try {
    // Fetch top 7 best-selling products for Trending showcase
    $trending_stmt = $pdo->query("
        SELECT p.*, COALESCE(SUM(oi.quantity), 0) AS total_sold
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        GROUP BY p.id
        ORDER BY total_sold ASC, p.id ASC
        LIMIT 7
    ");
    $trending_products = $trending_stmt->fetchAll();

    // Fetch all categories
    $categories_stmt = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
    $categories = $categories_stmt->fetchAll();
} catch (\PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

// Category image map to keep design matching the original HTML
$category_images = [
    'Long Sleeves' => '../images/Designs/Long Sleeve/Full-Sleeves_f3f0b379-d092-406c-af18-5e9e06bc652c.webp',
    'Short Sleeves' => '../images/Designs/Half Sleeve/T-Shirt-Front-Temp3.webp',
    'Hoodies' => '../images/Designs/Hoodies/H1.webp',
    'Sweatshirts' => '../images/Designs/Hoodies/H3.webp'
];
?>

    <main>
      <!-- Hero/Promo Banner -->
      <div class="banner">
        <h1 style="color: #fff; text-shadow: 0 2px 4px rgba(0,0,0,0.5);">Flash Sale: 30% Off</h1>
        <p style="color: #eee; font-size: 1.1rem;">Use promo code <strong style="color: #fca311; text-decoration: underline;">FLASHSALE</strong> at checkout. Upgrade your wardrobe today.</p>
      </div>

      <!-- Trending Section -->
      <div class="trending-header">
        <h2 style="font-size: 7vh; color: #333">Trending</h2>
      </div>

      <div class="showcase">
        <div class="Trending">
          <?php foreach ($trending_products as $product): ?>
            <div class="product-card">
              <a href="ProductDetails.php?id=<?php echo $product['id']; ?>">
                <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" />
              </a>
              <div class="product-info">
                <a href="ProductDetails.php?id=<?php echo $product['id']; ?>" style="text-decoration:none; color:inherit; display:block;">
                  <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                </a>
                <p>Rs. <?php echo number_format($product['price']); ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Categories Section -->
      <div class="category-header">
        <h2>Shop By Category</h2>
      </div>
      <div class="category-grid">
        <?php foreach ($categories as $cat): 
          $cat_name = $cat['name'];
          $cat_img = isset($category_images[$cat_name]) ? $category_images[$cat_name] : '../images/Designs/1.webp';
        ?>
          <div class="category-card">
            <a href="Products.php?category=<?php echo $cat['id']; ?>">
              <img src="<?php echo htmlspecialchars($cat_img); ?>" alt="<?php echo htmlspecialchars($cat_name); ?>" />
            </a>
            <div class="category-info">
              <h3><?php echo htmlspecialchars($cat_name); ?></h3>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </main>

<?php require_once 'footer.php'; ?>
