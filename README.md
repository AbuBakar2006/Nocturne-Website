# 🌙 Nocturne Streetwear E-Commerce Platform

A complete, full-stack **E-Commerce Web Application** themed around urban streetwear. Built with **PHP** and **MySQL**, **Nocturne** provides an interactive storefront for customers to browse apparel, manage their shopping carts, and place orders, alongside a robust administrative dashboard for inventory tracking, sales analytics, review moderation, and order fulfillment.

---

## 🚀 Key Features

### 👤 Customer Experience
*   **Storefront & Catalog:** Browse products categorized into *Short Sleeves*, *Long Sleeves*, *Hoodies*, and *Sweatshirts* with clean design cards.
*   **Detailed Product Pages:** View multiple high-resolution images, detailed descriptions, sizing options, and real-time customer reviews.
*   **Interactive Shopping Cart:** Add, update quantities, or remove items dynamically prior to checkout.
*   **Seamless Checkout:** Fast checkout form capturing shipping addresses and support for multiple payment options (Cash on Delivery, Credit/Debit cards).
*   **User Profiles & History:** Register accounts, log in securely (session-protected), view order histories, track shipment status, and easily re-order past items.
*   **Interactive Reviews:** Rate products on a scale of 1 to 5 stars and leave written feedback.

### 💼 Admin Management Panel
*   **Overview Dashboard:** High-level summary cards showing total customers, products, pending orders, and categories.
*   **Advanced Analytics:** Interactive analytics panel visualizing sales distributions across categories and revenue trends.
*   **Product Catalog Management (CRUD):** Fully manage products by adding new designs, updating pricing/description, uploading up to 4 alternate images, and tracking inventory levels.
*   **Category Management:** Dynamically add and modify product categories.
*   **Order Fulfillment:** Track and update order statuses (*Pending*, *Processing*, *Shipped*, *Delivered*, *Cancelled*).
*   **Review Moderation:** Approve, reject, or reply to customer product reviews before they go live on the storefront.
*   **Staff Administration:** Management page for admin staff users and role assignments.

---

## 🛠️ Tech Stack

*   **Backend Logic:** PHP 8.x
*   **Database Management:** MySQL (using PDO for secure prepared statements and SQL injection prevention)
*   **Frontend UI:** HTML5, CSS3 (Vanilla design with custom layouts, transitions, and CSS-only slideshow selectors), JavaScript (ES6)
*   **Icons & Assets:** Font-Awesome 6, SVG Icons

---

## 🗂️ Database Schema

The platform runs on a relational database schema structured as follows:

| Table Name | Description | Key Columns |
| :--- | :--- | :--- |
| **`admins`** | Admin staff credential details and roles | `id`, `name`, `username`, `email`, `password`, `role`, `created_at` |
| **`categories`** | Product classification categories | `id`, `name`, `description`, `created_at` |
| **`products`** | Merchandise details, pricing, and image paths | `id`, `category_id`, `name`, `description`, `price`, `image_path` (x4), `inventory_qty` |
| **`customers`** | Registered user profiles and metadata | `id`, `name`, `email`, `phone`, `city`, `password`, `joined_date`, `created_at` |
| **`orders`** | Customer checkout records, amounts, and statuses | `id`, `customer_id`, `order_date`, `status`, `total_amount`, `shipping_address`, `payment_method` |
| **`order_items`** | Line-item product listings for individual orders | `id`, `order_id`, `product_id`, `quantity`, `price`, `size` |
| **`reviews`** | Customer ratings, reviews, and admin responses | `id`, `product_id`, `customer_name`, `customer_email`, `rating`, `review_text`, `admin_reply`, `status` |

---

## ⚙️ Installation & Setup

To run this project locally using **XAMPP** (or any local PHP/MySQL server):

1.  **Clone the Repository:**
    ```bash
    git clone https://github.com/AbuBakar2006/Nocturne-Website.git
    ```
2.  **Move Files to Server Root:**
    Place the project folder inside your web server root directory (e.g., `C:\xampp\htdocs\Nocturne-Website`).
3.  **Start Services:**
    Launch your **Apache** and **MySQL** servers from the XAMPP Control Panel.
4.  **Initialize & Seed Database:**
    Open your web browser and navigate to the database setup script:
    ```
    http://localhost/Nocturne-Website/admin/setup_db.php
    ```
    This script will:
    *   Create the `nocturne_db` database.
    *   Build all required tables with indexes and foreign key constraints.
    *   Seed initial categories, mock products, active customer accounts, test orders, and reviews.
5.  **Sign In:**
    *   **Admin Access:** Navigate to `http://localhost/Nocturne-Website/admin/AdminLogin.php` (Credentials: `admin@nocturne.com` / `admin123`).
    *   **Customer Access:** Navigate to `http://localhost/Nocturne-Website/customer/Login.php` (Credentials: Use any seeded customer email such as `ahmed.khan@gmail.com` with password `customer123`).
