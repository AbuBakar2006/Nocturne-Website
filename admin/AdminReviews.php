<?php
// AdminReviews.php
// Review management page for NOCTURNE Admin.

require_once 'auth.php';
check_admin_login();
require_once 'db_connect.php';

$success_message = '';
$error_message = '';

// 1. Handle Submit/Edit Reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reply_review') {
    $review_id = (int)($_POST['review_id'] ?? 0);
    $reply_text = trim($_POST['reply_text'] ?? '');
    
    if ($review_id <= 0 || empty($reply_text)) {
        $error_message = "Reply text cannot be empty.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE reviews SET admin_reply = :reply, status = 'Approved' WHERE id = :id");
            $stmt->execute(['reply' => $reply_text, 'id' => $review_id]);
            $success_message = "Reply added/updated successfully.";
        } catch (\PDOException $e) {
            $error_message = "Failed to submit reply: " . $e->getMessage();
        }
    }
}

// 2. Handle Delete Review/Reply
if (isset($_GET['delete'])) {
    $review_id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
        $stmt->execute([$review_id]);
        $success_message = "Review deleted successfully.";
    } catch (\PDOException $e) {
        $error_message = "Failed to delete review: " . $e->getMessage();
    }
}

// Filters & Query Parameters
$search = trim($_GET['search'] ?? '');
$rating_filter = trim($_GET['rating'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$product_filter = trim($_GET['product'] ?? '');

$where_clauses = [];
$params = [];

if ($search !== '') {
    $where_clauses[] = "(r.customer_name LIKE :search_name OR r.review_text LIKE :search_text)";
    $params['search_name'] = "%{$search}%";
    $params['search_text'] = "%{$search}%";
}

if ($rating_filter !== '') {
    $where_clauses[] = "r.rating = :rating";
    $params['rating'] = (int)$rating_filter;
}

if ($status_filter !== '') {
    if ($status_filter === 'Replied') {
        $where_clauses[] = "r.admin_reply IS NOT NULL AND r.admin_reply != ''";
    } elseif ($status_filter === 'Pending Reply') {
        $where_clauses[] = "r.admin_reply IS NULL OR r.admin_reply = ''";
    }
}

if ($product_filter !== '') {
    $where_clauses[] = "p.name = :product_name";
    $params['product_name'] = $product_filter;
}

$where_sql = '';
if (count($where_clauses) > 0) {
    $where_sql = ' WHERE ' . implode(' AND ', $where_clauses);
}

// Pagination setup
$limit = 6;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

try {
    // 1. Fetch Dynamic Stats
    $total_reviews = $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn() ?: 0;
    $avg_rating = round($pdo->query("SELECT AVG(rating) FROM reviews")->fetchColumn() ?: 0.0, 1);
    $replied_reviews = $pdo->query("SELECT COUNT(*) FROM reviews WHERE admin_reply IS NOT NULL AND admin_reply != ''")->fetchColumn() ?: 0;
    $pending_reviews = $total_reviews - $replied_reviews;
    
    // Fetch unique products for filtering dropdown
    $products_list = $pdo->query("SELECT DISTINCT name FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);

    // 2. Fetch Rating Breakdown counts
    $rating_counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    $raw_counts = $pdo->query("SELECT rating, COUNT(*) as count FROM reviews GROUP BY rating")->fetchAll();
    foreach ($raw_counts as $row) {
        $rating_counts[(int)$row['rating']] = (int)$row['count'];
    }

    // 3. Sentiment calculations (5,4 = positive; 3 = neutral; 2,1 = negative)
    $pos_cnt = $rating_counts[5] + $rating_counts[4];
    $neu_cnt = $rating_counts[3];
    $neg_cnt = $rating_counts[2] + $rating_counts[1];
    
    $divisor = $total_reviews > 0 ? $total_reviews : 1;
    $pos_pct = round(($pos_cnt / $divisor) * 100);
    $neu_pct = round(($neu_cnt / $divisor) * 100);
    $neg_pct = round(($neg_cnt / $divisor) * 100);

    // conic gradient values for sentiment
    $lim1 = $pos_pct;
    $lim2 = $lim1 + $neu_pct;

    // 4. Fetch Total Rows for pagination
    $count_sql = "SELECT COUNT(*) FROM reviews r LEFT JOIN products p ON r.product_id = p.id" . $where_sql;
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_rows = $stmt->fetchColumn();
    $total_pages = ceil($total_rows / $limit);

    // 5. Fetch reviews list
    $sql = "SELECT r.*, p.name AS product_name, DATE_FORMAT(r.created_at, '%d %b %Y') AS formatted_date 
            FROM reviews r 
            LEFT JOIN products p ON r.product_id = p.id" 
            . $where_sql . " 
            ORDER BY r.created_at DESC, r.id DESC 
            LIMIT :limit OFFSET :offset";
            
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $val) {
        $stmt->bindValue(':' . $key, $val);
    }
    $stmt->execute();
    $reviews = $stmt->fetchAll();

} catch (\PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Manage feedback and reviews on NOCTURNE admin panel">
  <title>Reviews | NOCTURNE Admin</title>
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
          <h1>Feedback &amp; Reviews</h1>
        </div>
        <form method="get" action="AdminReviews.php" style="display:flex; align-items:center; gap:16px;">
          <div style="position:relative;">
            <i class="fas fa-search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#aaa;"></i>
            <input type="text" name="search" placeholder="Search reviewsÃ¢â‚¬Â¦" value="<?php echo htmlspecialchars($search); ?>" style="padding:8px 12px 8px 34px; border:1px solid #ddd; border-radius:8px; font-size:0.9rem; outline:none; width:220px;">
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

        <!-- REVIEW STATS -->
        <section class="admin-stats">
          <div class="stat-card">
            <div style="width:42px; height:42px; background:#fff8e6; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:12px;">
              <i class="fas fa-star" style="color:#fca311; font-size:1.1rem;"></i>
            </div>
            <h3>Average Rating</h3>
            <div class="value"><?php echo $avg_rating; ?> <span style="font-size:1rem; color:#aaa;">/ 5</span></div>
            <div class="trend">Store satisfaction</div>
          </div>
          <div class="stat-card">
            <div style="width:42px; height:42px; background:#e8f4ff; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:12px;">
              <i class="fas fa-comments" style="color:#3b82f6; font-size:1.1rem;"></i>
            </div>
            <h3>Total Reviews</h3>
            <div class="value"><?php echo $total_reviews; ?></div>
            <div class="trend">Submitted reviews</div>
          </div>
          <div class="stat-card">
            <div style="width:42px; height:42px; background:#f0faf0; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:12px;">
              <i class="fas fa-check-circle" style="color:#10b981; font-size:1.1rem;"></i>
            </div>
            <h3>Replied</h3>
            <div class="value"><?php echo $replied_reviews; ?></div>
            <div class="trend">Responded to</div>
          </div>
          <div class="stat-card">
            <div style="width:42px; height:42px; background:#fff0f0; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:12px;">
              <i class="fas fa-clock" style="color:#ef4444; font-size:1.1rem;"></i>
            </div>
            <h3>Pending Reply</h3>
            <div class="value"><?php echo $pending_reviews; ?></div>
            <div class="trend" style="color:#ef4444;">Awaiting response</div>
          </div>
        </section>

        <!-- FILTERS -->
        <div class="admin-table-container" style="padding:16px 20px; margin-bottom:24px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
          <form method="get" action="AdminReviews.php" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
            
            <select name="rating" onchange="this.form.submit()" style="padding:8px 12px; border:1px solid #ddd; border-radius:8px; font-size:0.88rem; outline:none; cursor:pointer;">
              <option value="">All Ratings</option>
              <option value="5" <?php echo $rating_filter === '5' ? 'selected' : ''; ?>>5 Stars</option>
              <option value="4" <?php echo $rating_filter === '4' ? 'selected' : ''; ?>>4 Stars</option>
              <option value="3" <?php echo $rating_filter === '3' ? 'selected' : ''; ?>>3 Stars</option>
              <option value="2" <?php echo $rating_filter === '2' ? 'selected' : ''; ?>>2 Stars</option>
              <option value="1" <?php echo $rating_filter === '1' ? 'selected' : ''; ?>>1 Star</option>
            </select>
            
            <select name="status" onchange="this.form.submit()" style="padding:8px 12px; border:1px solid #ddd; border-radius:8px; font-size:0.88rem; outline:none; cursor:pointer;">
              <option value="">All Statuses</option>
              <option value="Replied" <?php echo $status_filter === 'Replied' ? 'selected' : ''; ?>>Replied</option>
              <option value="Pending Reply" <?php echo $status_filter === 'Pending Reply' ? 'selected' : ''; ?>>Pending Reply</option>
            </select>
            
            <select name="product" onchange="this.form.submit()" style="padding:8px 12px; border:1px solid #ddd; border-radius:8px; font-size:0.88rem; outline:none; cursor:pointer;">
              <option value="">All Products</option>
              <?php foreach ($products_list as $prod): ?>
                <option value="<?php echo htmlspecialchars($prod); ?>" <?php echo $product_filter === $prod ? 'selected' : ''; ?>><?php echo htmlspecialchars($prod); ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($search !== '' || $rating_filter !== '' || $status_filter !== '' || $product_filter !== ''): ?>
              <a href="AdminReviews.php" style="padding: 8px 12px; border: 1px solid #ef4444; border-radius: 8px; font-size: 0.88rem; outline: none; cursor: pointer; color: #fff; background: #ef4444; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-weight: 700; margin-left: 10px; transition: all 0.2s ease;">
                <i class="fas fa-times-circle"></i> Clear Filters
              </a>
            <?php endif; ?>
          </form>
          <span style="color:#aaa; font-size:0.85rem;">Showing <?php echo count($reviews); ?> of <?php echo $total_rows; ?> reviews</span>
        </div>

        <!-- RATING BREAKDOWN + SENTIMENT -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px;">

          <!-- Rating Breakdown -->
          <?php
            $max_count = max(array_values($rating_counts)) ?: 1;
          ?>
          <div class="admin-table-container" style="padding:24px;">
            <h3 style="font-size:1rem; font-weight:700; margin:0 0 20px;">Rating Breakdown</h3>
            <?php for ($i = 5; $i >= 1; $i--): 
              $width_pct = round(($rating_counts[$i] / $max_count) * 100);
            ?>
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
              <span style="font-size:0.85rem; font-weight:600; width:50px;"><?php echo $i; ?> <i class="fas fa-star" style="color:#fca311; font-size:0.7rem;"></i></span>
              <div style="flex:1; height:10px; background:#f0f0f0; overflow:hidden; border: 1px solid #eee;">
                <div style="height:100%; width:<?php echo $width_pct; ?>%; background:#fca311;"></div>
              </div>
              <span style="font-size:0.78rem; color:#aaa; width:30px; text-align:right;"><?php echo $rating_counts[$i]; ?></span>
            </div>
            <?php endfor; ?>
          </div>

          <!-- Customer Sentiment Donut -->
          <div class="admin-table-container" style="padding:24px;">
            <h3 style="font-size:1rem; font-weight:700; margin:0 0 20px;">Customer Sentiment</h3>
            <div style="display:flex; align-items:center; gap:24px; flex-wrap:wrap;">
              <div style="width:120px; height:120px; border-radius:50%; background:conic-gradient(#10b981 0% <?php echo $lim1; ?>%, #f59e0b <?php echo $lim1; ?>% <?php echo $lim2; ?>%, #ef4444 <?php echo $lim2; ?>% 100%); display:flex; align-items:center; justify-content:center; flex-shrink:0; border: 1.5px solid #111;">
                <div style="width:76px; height:76px; background:#fff; border-radius:50%; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                  <strong style="font-size:1rem;"><?php echo $pos_pct; ?>%</strong>
                  <span style="font-size:0.6rem; color:#aaa;">Positive</span>
                </div>
              </div>
              <div style="display:flex; flex-direction:column; gap:8px; font-size:0.82rem;">
                <div><span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#10b981; margin-right:8px;"></span>Positive (<?php echo $pos_pct; ?>%)</div>
                <div><span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#f59e0b; margin-right:8px;"></span>Neutral (<?php echo $neu_pct; ?>%)</div>
                <div><span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#ef4444; margin-right:8px;"></span>Negative (<?php echo $neg_pct; ?>%)</div>
              </div>
            </div>
          </div>
        </div>

        <!-- REVIEWS LIST -->
        <h3 style="font-size:1rem; font-weight:700; margin:0 0 16px; text-transform:uppercase; letter-spacing:1px;">All Reviews</h3>

        <?php if (count($reviews) > 0): ?>
          <?php foreach ($reviews as $rev): 
            // Generate initials
            $names = explode(' ', $rev['customer_name']);
            $rev_initials = '';
            foreach ($names as $n) {
                $rev_initials .= strtoupper(substr($n, 0, 1));
            }
            $rev_initials = substr($rev_initials, 0, 2);
          ?>
            <div class="admin-table-container" style="padding:20px; margin-bottom:16px;">
              <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:12px;">
                <div style="display:flex; align-items:center; gap:12px;">
                  <div style="width:38px; height:38px; background:#111; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fca311; font-weight:700; font-size:0.8rem; border:1px solid #fca311;">
                    <?php echo htmlspecialchars($rev_initials); ?>
                  </div>
                  <div>
                    <strong><?php echo htmlspecialchars($rev['customer_name']); ?></strong>
                    <span style="font-size:0.75rem; color:#aaa; margin-left:8px;"><?php echo htmlspecialchars($rev['customer_email']); ?></span>
                    <br>
                    <span style="font-size:0.82rem; color:#666; font-weight:600;"><i class="fas fa-box" style="margin-right:4px;"></i><?php echo htmlspecialchars($rev['product_name'] ?: '[Deleted Product]'); ?></span>
                  </div>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                  <span style="color:#fca311;">
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                      <i class="<?php echo $s <= $rev['rating'] ? 'fas' : 'far'; ?> fa-star" style="font-size: 0.85rem;"></i>
                    <?php endfor; ?>
                  </span>
                  <span style="font-size:0.75rem; color:#aaa;"><?php echo htmlspecialchars($rev['formatted_date']); ?></span>
                </div>
              </div>
              
              <p style="color:#444; font-size:0.9rem; line-height:1.6; margin-bottom:12px; white-space: pre-wrap;"><?php echo htmlspecialchars($rev['review_text']); ?></p>
              
              <?php if (!empty($rev['admin_reply'])): ?>
                <div style="background:#f0fdf4; border-left:3px solid #10b981; padding:12px; margin-bottom:12px;">
                  <p style="font-size:0.78rem; color:#10b981; font-weight:700; margin-bottom:4px;"><i class="fas fa-reply" style="margin-right:4px;"></i>Admin Reply</p>
                  <p style="font-size:0.85rem; color:#444;"><?php echo htmlspecialchars($rev['admin_reply']); ?></p>
                </div>
              <?php endif; ?>
              
              <div class="action-btns" style="justify-content:flex-start; gap: 15px;">
                <button onclick="showReplyForm(<?php echo $rev['id']; ?>, '<?php echo htmlspecialchars(addslashes($rev['admin_reply'] ?? '')); ?>')" class="btn-edit" style="background:none; border:none; cursor:pointer; font-family:inherit; padding:0; display:inline-flex; align-items:center; gap:6px;">
                  <i class="fas fa-reply"></i> <?php echo empty($rev['admin_reply']) ? 'Reply' : 'Edit Reply'; ?>
                </button>
                <a href="AdminReviews.php?delete=<?php echo $rev['id']; ?>" onclick="return confirm('Are you sure you want to delete this review?')" class="btn-delete"><i class="fas fa-trash"></i> Delete</a>
              </div>
              
              <!-- Toggleable Reply Form -->
              <div id="reply-container-<?php echo $rev['id']; ?>" style="display:none; margin-top:14px; padding-top:14px; border-top:1px dashed #ddd;">
                <form method="post" action="AdminReviews.php">
                  <input type="hidden" name="action" value="reply_review">
                  <input type="hidden" name="review_id" value="<?php echo $rev['id']; ?>">
                  <div class="form-group" style="margin-bottom:10px;">
                    <label style="font-size:0.8rem; font-weight:700;">Your Response:</label>
                    <textarea name="reply_text" id="reply-text-<?php echo $rev['id']; ?>" required placeholder="Write response to customer..." style="width:100%; padding:10px; border:1.5px solid #111; border-radius:4px; font-family:inherit; min-height:80px; background:#fcfbf0;"></textarea>
                  </div>
                  <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn-add" style="padding:6px 16px; font-size:0.8rem;">Submit</button>
                    <button type="button" onclick="hideReplyForm(<?php echo $rev['id']; ?>)" class="btn-add" style="padding:6px 16px; font-size:0.8rem; background:#888;">Cancel</button>
                  </div>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="admin-table-container" style="padding:40px; text-align:center; color:#777;">
            No reviews found matching the filters.
          </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
          <div style="display:flex; justify-content:center; gap:6px; margin-top:20px;">
            <?php if ($page > 1): ?>
              <a href="AdminReviews.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&rating=<?php echo urlencode($rating_filter); ?>&status=<?php echo urlencode($status_filter); ?>&product=<?php echo urlencode($product_filter); ?>" style="padding:8px 12px; border:1.5px solid #111; background:#fff; text-decoration:none; color:#111; font-weight:700;"><i class="fas fa-chevron-left"></i></a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
              <a href="AdminReviews.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&rating=<?php echo urlencode($rating_filter); ?>&status=<?php echo urlencode($status_filter); ?>&product=<?php echo urlencode($product_filter); ?>" style="padding:8px 14px; border:1.5px solid #111; <?php echo $i === $page ? 'background:#111; color:#fff;' : 'background:#fff; color:#111;'; ?> text-decoration:none; font-weight:700;"><?php echo $i; ?></a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
              <a href="AdminReviews.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&rating=<?php echo urlencode($rating_filter); ?>&status=<?php echo urlencode($status_filter); ?>&product=<?php echo urlencode($product_filter); ?>" style="padding:8px 12px; border:1.5px solid #111; background:#fff; text-decoration:none; color:#111; font-weight:700;"><i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

      </main>
    </div>
  </div>

  <script>
    function showReplyForm(reviewId, currentReply) {
        document.getElementById('reply-text-' + reviewId).value = currentReply;
        document.getElementById('reply-container-' + reviewId).style.display = 'block';
    }
    function hideReplyForm(reviewId) {
        document.getElementById('reply-container-' + reviewId).style.display = 'none';
    }
  </script>

</body>
</html>
