<?php
// setup_db.php
// A browser-runnable script to initialize and seed the database on XAMPP.

$host = 'localhost';
$user = 'root';
$pass = ''; // Default XAMPP password is empty
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // 1. Create Database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `nocturne_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $pdo->exec("USE `nocturne_db`");
    
    echo "<h3>Database initialized successfully. Creating tables...</h3>";

    // 2. Create tables
    // Admins table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `admins` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `email` VARCHAR(100) NOT NULL UNIQUE,
        `mobile` VARCHAR(20) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `role` VARCHAR(20) NOT NULL DEFAULT 'Admin',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // Categories table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `categories` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `description` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // Products table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `products` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `category_id` INT,
        `name` VARCHAR(150) NOT NULL,
        `description` TEXT,
        `price` DECIMAL(10,2) NOT NULL,
        `image_path` VARCHAR(255) NOT NULL,
        `image_path2` VARCHAR(255) NULL,
        `image_path3` VARCHAR(255) NULL,
        `image_path4` VARCHAR(255) NULL,
        `inventory_qty` INT NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB;");

    // Customers table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `customers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(100) NOT NULL UNIQUE,
        `phone` VARCHAR(20) NOT NULL,
        `city` VARCHAR(50) NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `reset_token` VARCHAR(100) NULL,
        `joined_date` DATE NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // Orders table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `orders` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `customer_id` INT,
        `order_date` DATE NOT NULL,
        `status` ENUM('Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled') NOT NULL DEFAULT 'Pending',
        `total_amount` DECIMAL(10,2) NOT NULL,
        `shipping_address` TEXT NOT NULL,
        `payment_method` VARCHAR(50) NOT NULL DEFAULT 'Cash on Delivery',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB;");

    // Order Items table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `order_items` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `order_id` INT NOT NULL,
        `product_id` INT,
        `quantity` INT NOT NULL DEFAULT 1,
        `price` DECIMAL(10,2) NOT NULL,
        `size` VARCHAR(10) NOT NULL DEFAULT 'M',
        FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB;");

    // Reviews table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `reviews` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `product_id` INT NOT NULL,
        `customer_name` VARCHAR(100) NOT NULL,
        `customer_email` VARCHAR(100) NOT NULL,
        `rating` INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
        `review_text` TEXT,
        `admin_reply` TEXT NULL,
        `status` ENUM('Approved', 'Pending', 'Rejected') NOT NULL DEFAULT 'Pending',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB;");

    echo "Tables created successfully. Seeding initial data...<br>";

    // 3. Seed Admins
    // Default password: admin123
    $adminCount = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    if ($adminCount == 0) {
        $hashed_pass = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admins (name, username, email, mobile, password, role) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['Admin User', 'admin', 'admin@nocturne.com', '03001234567', $hashed_pass, 'SuperAdmin']);
        echo " - Admin user seeded (admin@nocturne.com / admin123)<br>";
    }

    // 4. Seed Categories
    $categoryCount = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    if ($categoryCount == 0) {
        $categories = [
            ['Short Sleeves', 'Oversized and regular short sleeve crewnecks and shirts.'],
            ['Hoodies', 'Heavyweight fleece hooded sweatshirts.'],
            ['Long Sleeves', 'Premium cotton long sleeve shirts.'],
            ['Sweatshirts', 'Urban crewneck sweaters.']
        ];
        $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
        foreach ($categories as $cat) {
            $stmt->execute($cat);
        }
        echo " - Categories seeded (Short Sleeves, Hoodies, Long Sleeves, Sweatshirts)<br>";
    }

    // 5. Seed Products
    $productCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    if ($productCount == 0) {
        // Get category IDs
        $catIds = [];
        $cats = $pdo->query("SELECT id, name FROM categories")->fetchAll();
        foreach ($cats as $c) {
            $catIds[$c['name']] = $c['id'];
        }

        $products = [
            [$catIds['Short Sleeves'], 'Classic T-Shirt', 'Short Sleeve Classic Fit cotton crewneck.', 2599.00, '../images/Designs/1.webp', '../images/Designs/S1.png', '../images/Designs/S1.png', '../images/Designs/S1.png', 24],
            [$catIds['Hoodies'], 'Classic Hoodie', 'Heavyweight Fleece double-lined hood.', 3799.00, '../images/Designs/H2.webp', '../images/Designs/S2.jpg', '../images/Designs/S2.jpg', '../images/Designs/S2.jpg', 15],
            [$catIds['Long Sleeves'], 'Premium Graphic Shirt', 'Long Sleeve graphic design with ribbed cuffs.', 2599.00, '../images/Designs/F1.webp', '../images/Designs/S3.jpg', '../images/Designs/S3.jpg', NULL, 0],
            [$catIds['Short Sleeves'], 'Urban Anime T-Shirt', 'Oversized Fit streetwear weight cotton.', 2599.00, '../images/Designs/4.webp', '../images/Designs/S4.jpg', '../images/Designs/S4.jpg', NULL, 56],
            [$catIds['Hoodies'], 'Limited Edition Hoodie', 'Drop Collection exclusive heavyweight hoodie.', 4599.00, '../images/Designs/5.webp', '../images/Designs/S5.png', '../images/Designs/S5.png', NULL, 8]
        ];

        $stmt = $pdo->prepare("INSERT INTO products (category_id, name, description, price, image_path, image_path2, image_path3, image_path4, inventory_qty) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($products as $prod) {
            $stmt->execute($prod);
        }
        echo " - Products seeded<br>";
    }

    // 6. Seed Customers
    $customerCount = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    if ($customerCount == 0) {
        $customers = [
            ['Ahmed Khan', 'ahmed.khan@gmail.com', '0300-1234567', 'Lahore', '2024-01-10'],
            ['Sara Malik', 'sara.malik@hotmail.com', '0321-9876543', 'Karachi', '2024-01-15'],
            ['Usman Ali', 'usman.ali@yahoo.com', '0333-4567890', 'Islamabad', '2024-01-20'],
            ['Fatima Noor', 'fatima.noor@gmail.com', '0345-6789012', 'Faisalabad', '2024-01-25'],
            ['Bilal Ahmed', 'bilal.ahmed@gmail.com', '0312-3456789', 'Rawalpindi', '2024-02-01'],
            ['Hamza Raza', 'hamza.raza@outlook.com', '0301-7654321', 'Multan', '2024-02-10'],
            ['Ayesha Siddiqui', 'ayesha.s@gmail.com', '0322-9988776', 'Peshawar', '2024-02-15'],
            ['Muhammad Hassan', 'm.hassan@gmail.com', '0311-2233445', 'Lahore', '2024-02-25'],
            ['Zain Ali', 'zain@nocturne.com', '0300-1112222', 'Lahore', '2023-09-01'],
            ['Nimra Khan', 'nimra@nocturne.com', '0321-3334444', 'Karachi', '2023-09-05'],
            ['Mustafa Kamal', 'mustafa@nocturne.com', '0333-5556666', 'Islamabad', '2023-09-10'],
            ['Saad Javed', 'saad@nocturne.com', '0345-7778888', 'Peshawar', '2023-09-15'],
            ['Rida Fatima', 'rida@nocturne.com', '0312-9990000', 'Islamabad', '2023-09-20']
        ];
        $hashed_pass = password_hash('customer123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO customers (name, email, phone, city, password, joined_date) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($customers as $cust) {
            $stmt->execute([$cust[0], $cust[1], $cust[2], $cust[3], $hashed_pass, $cust[4]]);
        }
        echo " - Customers seeded<br>";
    }

    // 7. Seed Orders
    $orderCount = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    if ($orderCount == 0) {
        $custIds = [];
        $custs = $pdo->query("SELECT id, name FROM customers")->fetchAll();
        foreach ($custs as $cu) {
            $custIds[$cu['name']] = $cu['id'];
        }

        $orders = [
            [$custIds['Zain Ali'], '2023-10-12', 'Shipped', 3799.00, 'House No 12, Street 5, Lahore', 'Visa ending in **** 4242'],
            [$custIds['Nimra Khan'], '2023-10-11', 'Delivered', 2599.00, 'Flat 4B, Ocean Towers, Karachi', 'MasterCard ending in **** 8888'],
            [$custIds['Mustafa Kamal'], '2023-10-11', 'Pending', 2599.00, 'Apartment 502, G-11 Sector, Islamabad', 'Cash on Delivery'],
            [$custIds['Saad Javed'], '2023-10-10', 'Delivered', 2599.00, 'Street 2, DHA Phase 6, Karachi', 'Cash on Delivery'],
            [$custIds['Rida Fatima'], '2023-10-09', 'Cancelled', 3799.00, 'House 114, F-8 Sector, Islamabad', 'Bank Transfer']
        ];

        $stmt = $pdo->prepare("INSERT INTO orders (customer_id, order_date, status, total_amount, shipping_address, payment_method) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($orders as $ord) {
            $stmt->execute($ord);
        }
        echo " - Orders seeded<br>";

        // Seed Order Items
        $ordIds = $pdo->query("SELECT id FROM orders ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
        $prodIds = $pdo->query("SELECT id, name FROM products")->fetchAll();
        $prodIdMap = [];
        foreach ($prodIds as $p) {
            $prodIdMap[$p['name']] = $p['id'];
        }

        $orderItems = [
            [$ordIds[0], $prodIdMap['Classic Hoodie'], 1, 3799.00, 'L'],
            [$ordIds[1], $prodIdMap['Urban Anime T-Shirt'], 1, 2599.00, 'M'],
            [$ordIds[2], $prodIdMap['Classic T-Shirt'], 1, 2599.00, 'M'],
            [$ordIds[3], $prodIdMap['Premium Graphic Shirt'], 1, 2599.00, 'S'],
            [$ordIds[4], $prodIdMap['Classic Hoodie'], 1, 3799.00, 'XL']
        ];

        $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, size) VALUES (?, ?, ?, ?, ?)");
        foreach ($orderItems as $item) {
            $stmtItem->execute($item);
        }
        echo " - Order items seeded<br>";
    }

    // 8. Seed Reviews
    $reviewCount = $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
    if ($reviewCount == 0) {
        $prodIds = $pdo->query("SELECT id, name FROM products")->fetchAll();
        $prodIdMap = [];
        foreach ($prodIds as $p) {
            $prodIdMap[$p['name']] = $p['id'];
        }

        $reviews = [
            [
                $prodIdMap['Classic T-Shirt'], 'Ahmed Khan', 'ahmed.khan@gmail.com', 5,
                "Absolutely love this t-shirt! The print quality is incredible and the fabric is super soft. Fits perfectly and the design really stands out. Will definitely be ordering more!",
                "Thank you for your wonderful review, Ahmed! We're thrilled you love the quality. Enjoy your NOCTURNE gear!",
                'Approved', '2024-04-10 14:00:00'
            ],
            [
                $prodIdMap['Classic Hoodie'], 'Sara Malik', 'sara.malik@hotmail.com', 4,
                "Great hoodie with smooth heavyweight fleece. Packaging was excellent and delivery was on time. Only reason for 4 stars is the sizing runs slightly large, but overall a fantastic purchase.",
                "Thank you Sara! We appreciate the honest feedback. We'll update our size guide to help future customers.",
                'Approved', '2024-04-09 11:30:00'
            ],
            [
                $prodIdMap['Urban Anime T-Shirt'], 'Fatima Noor', 'fatima.noor@gmail.com', 5,
                "This is my 3rd purchase from NOCTURNE and they never disappoint! The oversized fit is perfect for the streetwear look. The anime graphic is detailed and doesn't fade after washing. Highly recommended!",
                NULL,
                'Pending', '2024-04-07 16:45:00'
            ],
            [
                $prodIdMap['Premium Graphic Shirt'], 'Usman Ali', 'usman.ali@yahoo.com', 3,
                "Decent shirt for the price. The graphic print is nice but I expected the fabric to be thicker. Delivery took a bit longer than expected. Overall satisfactory.",
                NULL,
                'Pending', '2024-04-08 09:15:00'
            ],
            [
                $prodIdMap['Limited Edition Hoodie'], 'Muhammad Hassan', 'm.hassan@gmail.com', 5,
                "The Limited Edition Hoodie is a masterpiece! The premium drop collection material feels luxurious. Perfect for both casual hangouts and layered fits. Worth every rupee.",
                "Hassan, we're glad you love the Limited Edition! It's truly one of our finest drops. Thank you for being a valued NOCTURNE customer.",
                'Approved', '2024-04-05 18:20:00'
            ],
            [
                $prodIdMap['Classic T-Shirt'], 'Hamza Raza', 'hamza.raza@outlook.com', 4,
                "Purchased this as a gift for my brother and he absolutely loves it. The print quality is top-notch and the cotton fabric feels soft. Great customer service during the purchase!",
                NULL,
                'Pending', '2024-04-03 10:10:00'
            ]
        ];

        $stmt = $pdo->prepare("INSERT INTO reviews (product_id, customer_name, customer_email, rating, review_text, admin_reply, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($reviews as $rev) {
            $stmt->execute($rev);
        }
        echo " - Reviews seeded<br>";
    }

    echo "<h2>Seeding complete! Database setup is ready.</h2>";
    echo "<p><a href='AdminLogin.php'>Go to Admin Login Page</a></p>";

} catch (\PDOException $e) {
    die("Setup failed: " . $e->getMessage());
}
?>
