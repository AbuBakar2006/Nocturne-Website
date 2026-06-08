<?php
// customer/ProductDetails.php
require_once '../admin/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

try {
    // 1. Fetch product details
    $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ? LIMIT 1");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product) {
        header("Location: Products.php");
        exit;
    }

    // Handle review submission
    $review_success = '';
    $review_error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
        if (!isset($_SESSION['customer_logged_in'])) {
            $review_error = "You must be logged in to submit a review.";
        } else {
            $rating = intval($_POST['rating'] ?? 5);
            $review_text = trim($_POST['review_text'] ?? '');
            if ($rating >= 1 && $rating <= 5 && !empty($review_text)) {
                $ins_stmt = $pdo->prepare("INSERT INTO reviews (product_id, customer_name, customer_email, rating, review_text, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
                $ins_stmt->execute([
                    $product_id,
                    $_SESSION['customer_name'],
                    $_SESSION['customer_email'],
                    $rating,
                    $review_text
                ]);
                $review_success = "Your review has been submitted and is pending administrator approval.";
            } else {
                $review_error = "Please provide rating and review text.";
            }
        }
    }

    // 2. Fetch approved reviews
    $reviews_stmt = $pdo->prepare("SELECT *, DATE_FORMAT(created_at, '%M %d, %Y') AS formatted_date FROM reviews WHERE product_id = ? AND status = 'Approved' ORDER BY id DESC");
    $reviews_stmt->execute([$product_id]);
    $reviews = $reviews_stmt->fetchAll();

    // 3. Fetch related products (same category, up to 3)
    $related_stmt = $pdo->prepare("SELECT * FROM products WHERE category_id = ? AND id != ? LIMIT 3");
    $related_stmt->execute([$product['category_id'], $product_id]);
    $related_products = $related_stmt->fetchAll();
    // Fallback if no related products in the same category
    if (count($related_products) < 3) {
        $needed = 3 - count($related_products);
        $fallback_stmt = $pdo->prepare("SELECT * FROM products WHERE id != ? AND category_id != ? LIMIT " . intval($needed));
        $fallback_stmt->execute([$product_id, $product['category_id']]);
        $fallback_products = $fallback_stmt->fetchAll();
        $related_products = array_merge($related_products, $fallback_products);
    }
} catch (\PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

$page_title = $product['name'];
require_once 'header.php';

// Prepare image paths for slider
$slider_images = [];
if (!empty($product['image_path'])) $slider_images[] = $product['image_path'];
if (!empty($product['image_path2'])) $slider_images[] = $product['image_path2'];
if (!empty($product['image_path3'])) $slider_images[] = $product['image_path3'];
if (!empty($product['image_path4'])) $slider_images[] = $product['image_path4'];

$num_images = count($slider_images);
?>

    <main class="page-main">
      <div style="max-width: 1200px; margin: 40px auto; padding: 0 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: start;" class="product-detail-grid-container">
        
        <!-- Image Section -->
        <div class="product-image-section" style="position: sticky; top: 100px;">
          
          <?php if ($num_images > 1): ?>
            <!-- Dynamic CSS Slider using original stylesheet classes -->
            <?php 
              // We match the slider structure and input naming of style.css based on number of images
              $slider_prefix = ($num_images == 4) ? 's1' : 's3';
              $slider_name = ($num_images == 4) ? 'slider-1' : 'slider-3';
            ?>
            <div class="slider-wrapper">
              <?php for ($i = 1; $i <= $num_images; $i++): ?>
                <input
                  type="radio"
                  name="<?php echo $slider_name; ?>"
                  id="<?php echo $slider_prefix . '-' . $i; ?>"
                  class="slider-radio"
                  <?php echo ($i === 1) ? 'checked' : ''; ?>
                />
              <?php endfor; ?>

              <div class="slides" style="width: <?php echo $num_images * 100; ?>%;">
                <?php foreach ($slider_images as $img): ?>
                  <div class="slide" style="width: <?php echo 100 / $num_images; ?>%;">
                    <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" />
                  </div>
                <?php endforeach; ?>
              </div>

              <!-- Dot indicators -->
              <div class="slider-dots">
                <?php for ($i = 1; $i <= $num_images; $i++): ?>
                  <label for="<?php echo $slider_prefix . '-' . $i; ?>" class="dot"></label>
                <?php endfor; ?>
              </div>
            </div>

            <!-- Thumbnail strip -->
            <div class="thumbnail-strip" style="margin-top: 15px;">
              <?php for ($i = 1; $i <= $num_images; $i++): ?>
                <label for="<?php echo $slider_prefix . '-' . $i; ?>">
                  <img src="<?php echo htmlspecialchars($slider_images[$i - 1]); ?>" alt="Thumb <?php echo $i; ?>" />
                </label>
              <?php endfor; ?>
            </div>

          <?php else: ?>
            <!-- Static Single Image -->
            <div class="slider-wrapper">
              <img src="<?php echo htmlspecialchars($slider_images[0]); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width:100%; display:block; aspect-ratio: 1; object-fit:cover;" />
            </div>
          <?php endif; ?>
        </div>

        <!-- Info & Add to Cart Section -->
        <div class="product-info-detail">
          <span style="font-size:0.9rem; text-transform:uppercase; font-weight:bold; letter-spacing:1px; color:#fca311; display:block; margin-bottom:5px;">
            Category: <?php echo htmlspecialchars($product['category_name'] ?? 'General'); ?>
          </span>
          <h1><?php echo htmlspecialchars($product['name']); ?></h1>
          <div class="product-price">Rs. <?php echo number_format($product['price']); ?></div>
          
          <p class="product-desc">
            <?php echo htmlspecialchars($product['description'] ?? 'No description available for this item.'); ?>
          </p>

          <!-- Add to Cart Form -->
          <form action="cart_action.php" method="post" id="add-to-cart-form" onsubmit="return validateAddToCart();">
            <input type="hidden" name="action" value="add" />
            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>" />
            <input type="hidden" name="size" id="selected-size" value="" required />

            <div class="product-options">
              <h3>Select Size*</h3>
              <div class="size-selector">
                <button type="button" class="size-btn" onclick="selectSize('S', this)">S</button>
                <button type="button" class="size-btn" onclick="selectSize('M', this)">M</button>
                <button type="button" class="size-btn" onclick="selectSize('L', this)">L</button>
                <button type="button" class="size-btn" onclick="selectSize('XL', this)">XL</button>
              </div>
              <span id="size-warning" style="color:coral; font-size:0.85rem; display:none; margin-top:5px; font-weight:bold;">Please select a size first.</span>
            </div>

            <div class="product-options">
              <h3>Quantity</h3>
              <div class="cart-item-qty" style="margin-top: 10px;">
                <input type="number" id="qty-input" name="quantity" value="1" min="1" max="<?php echo max(1, $product['inventory_qty']); ?>" />
              </div>
              <span style="font-size:0.85rem; color:#666; margin-top: 8px; display: block; font-weight:bold;">
                (<?php echo ($product['inventory_qty'] > 0) ? $product['inventory_qty'] . ' in stock' : '<span style="color:coral;">Out of stock</span>'; ?>)
              </span>
            </div>

            <?php if ($product['inventory_qty'] > 0): ?>
              <button type="submit" class="add-to-cart-btn">Add to Cart</button>
            <?php else: ?>
              <button type="button" class="add-to-cart-btn" style="background:#eee; color:#999; cursor:not-allowed;" disabled>Temporarily Out of Stock</button>
            <?php endif; ?>
          </form>

          <!-- Size Chart -->
          <div class="size-chart-section">
            <h3 class="size-chart-title">📐 Size Chart</h3>
            <div class="size-chart-scroll">
              <table class="size-chart-table">
                <thead>
                  <tr>
                    <th>Size</th>
                    <th>Chest (in)</th>
                    <th>Waist (in)</th>
                    <th>Arm Length (in)</th>
                    <th>Body Length (in)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>S</td>
                    <td>36–38</td>
                    <td>30–32</td>
                    <td>25</td>
                    <td>27</td>
                  </tr>
                  <tr>
                    <td>M</td>
                    <td>38–40</td>
                    <td>32–34</td>
                    <td>25.5</td>
                    <td>28</td>
                  </tr>
                  <tr>
                    <td>L</td>
                    <td>40–42</td>
                    <td>34–36</td>
                    <td>26</td>
                    <td>29</td>
                  </tr>
                  <tr>
                    <td>XL</td>
                    <td>42–44</td>
                    <td>36–38</td>
                    <td>26.5</td>
                    <td>30</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

        </div>
      </div>

      <!-- Reviews Section -->
      <section style="max-width: 1200px; margin: 60px auto; padding: 0 20px; border-top:1px solid #ddd; padding-top:40px;">
        <h2 style="font-size:2rem; font-weight:bold; margin-bottom:30px;">Customer Reviews</h2>
        
        <div style="display:grid; grid-template-columns: 3fr 2fr; gap:40px; align-items: start;" class="reviews-grid-container">
          
          <!-- Reviews List -->
          <div>
            <?php if (count($reviews) > 0): ?>
              <div style="display:flex; flex-direction:column; gap:20px;">
                <?php foreach ($reviews as $rev): ?>
                  <div style="background:#fff; border: 1.5px solid #111; box-shadow: 4px 4px 0px #000; padding:20px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                      <div>
                        <strong style="font-size:1.05rem;"><?php echo htmlspecialchars($rev['customer_name']); ?></strong>
                        <span style="color:#777; font-size:0.85rem; margin-left:10px;"><?php echo htmlspecialchars($rev['formatted_date']); ?></span>
                      </div>
                      <div style="color:#fca311;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                          <i class="<?php echo ($i <= intval($rev['rating'])) ? 'fas' : 'far'; ?> fa-star"></i>
                        <?php endfor; ?>
                      </div>
                    </div>
                    <p style="color:#444; line-height:1.5; font-size:0.95rem; font-style:italic;">"<?php echo htmlspecialchars($rev['review_text']); ?>"</p>
                    
                    <?php if (!empty($rev['admin_reply'])): ?>
                      <div style="margin-top:15px; padding-top:15px; border-top:1px dashed #ddd; padding-left:15px; border-left:2px solid #111; background:#fcfbf0;">
                        <strong style="font-size:0.9rem; color:#111;"><i class="fas fa-reply" style="transform:rotate(180deg); margin-right:5px;"></i>Admin Reply:</strong>
                        <p style="font-size:0.9rem; color:#555; margin-top:5px;"><?php echo htmlspecialchars($rev['admin_reply']); ?></p>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div style="padding:40px; text-align:center; border:1px dashed #ccc; background:#fff; color:#777;">
                No reviews yet for this product. Be the first to share your thoughts!
              </div>
            <?php endif; ?>
          </div>

          <!-- Submit Review Form -->
          <div style="background:#fff; border: 1.5px solid #111; box-shadow: 4px 4px 0px #000; padding:25px;">
            <h3 style="font-size:1.3rem; font-weight:bold; margin-bottom:15px;">Write a Review</h3>
            
            <?php if (!empty($review_success)): ?>
              <div style="background-color: #e8f5e9; color: #2e7d32; border: 1.5px solid #2e7d32; padding: 12px; margin-bottom: 20px; font-weight: bold; border-radius: 4px; font-size: 0.9rem;">
                <i class="fas fa-check-circle" style="margin-right: 8px;"></i><?php echo $review_success; ?>
              </div>
            <?php endif; ?>
            
            <?php if (!empty($review_error)): ?>
              <div style="background-color: #ffebee; color: #c62828; border: 1.5px solid #c62828; padding: 12px; margin-bottom: 20px; font-weight: bold; border-radius: 4px; font-size: 0.9rem;">
                <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i><?php echo $review_error; ?>
              </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true): ?>
              <form action="ProductDetails.php?id=<?php echo $product_id; ?>" method="post" class="login-form" style="gap:15px;">
                <div class="input-group">
                  <label for="rev-rating">Rating (Stars)</label>
                  <select id="rev-rating" name="rating" required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:8px; font-size:0.95rem; background:#fcfbf0; outline:none;">
                    <option value="5">⭐⭐⭐⭐⭐ (5/5)</option>
                    <option value="4">⭐⭐⭐⭐ (4/5)</option>
                    <option value="3">⭐⭐⭐ (3/5)</option>
                    <option value="2">⭐⭐ (2/5)</option>
                    <option value="1">⭐ (1/5)</option>
                  </select>
                </div>
                <div class="input-group">
                  <label for="rev-text">Your Review</label>
                  <textarea id="rev-text" name="review_text" placeholder="Share your experience with this apparel..." required style="width:100%; padding:12px; border:1px solid #ccc; border-radius:8px; font-size:0.95rem; background:#fcfbf0; min-height:100px; resize:vertical; outline:none;"></textarea>
                </div>
                <button type="submit" name="submit_review" class="login-btn" style="margin-top:5px; border-radius:0;">Submit Review</button>
              </form>
            <?php else: ?>
              <p style="font-size:0.9rem; color:#666; line-height:1.5;">
                You must be signed in to leave a review. <a href="Login.php" style="color:#111; font-weight:bold; text-decoration:underline;">Click here to sign in.</a>
              </p>
            <?php endif; ?>
          </div>

        </div>
      </section>

      <!-- Related Products Section -->
      <section style="max-width: 1500px; margin: 60px auto 100px; padding: 0 20px; border-top:1px solid #ddd; padding-top:40px;">
        <h2 style="font-size:2rem; font-weight:bold; margin-bottom:30px; text-align:center;">Related Products</h2>
        
        <div class="products-grid">
          <?php foreach ($related_products as $rel): ?>
            <div class="product-card">
              <a href="ProductDetails.php?id=<?php echo $rel['id']; ?>">
                <img src="<?php echo htmlspecialchars($rel['image_path']); ?>" alt="<?php echo htmlspecialchars($rel['name']); ?>" />
              </a>
              <div class="product-info">
                <a href="ProductDetails.php?id=<?php echo $rel['id']; ?>" style="text-decoration:none; color:inherit; display:block;">
                  <h3><?php echo htmlspecialchars($rel['name']); ?></h3>
                </a>
                <p>Rs. <?php echo number_format($rel['price']); ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

    </main>

    <script>
      // Size selection script
      function selectSize(size, btn) {
          document.getElementById('selected-size').value = size;
          document.getElementById('size-warning').style.display = 'none';
          document.querySelectorAll('.size-btn').forEach(b => b.classList.remove('active'));
          btn.classList.add('active');
      }

      // Quantity change script
      function changeQty(amt) {
          const qtyInput = document.getElementById('qty-input');
          let val = parseInt(qtyInput.value) || 1;
          val += amt;
          const min = parseInt(qtyInput.min) || 1;
          const max = parseInt(qtyInput.max) || 999;
          if (val >= min && val <= max) {
              qtyInput.value = val;
          }
      }

      // Form validation script
      function validateAddToCart() {
          const selectedSize = document.getElementById('selected-size').value;
          if (!selectedSize) {
              document.getElementById('size-warning').style.display = 'block';
              return false;
          }
          return true;
      }
    </script>

<?php require_once 'footer.php'; ?>
