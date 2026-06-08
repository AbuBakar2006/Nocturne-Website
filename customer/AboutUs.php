<?php
// customer/AboutUs.php
$page_title = 'About Us';
require_once 'header.php';
?>
    <main class="page-main">
      <div class="page-header">
        <h1>About Us</h1>
        <p>Discover our story and what drives us.</p>
      </div>

      <div class="about-content">
        <div class="about-text">
          <section class="about-section">
            <h2>Our Mission</h2>
            <p>
              Founded with a passion for quality and design, we aim to provide
              premium wardrobe essentials that elevate your everyday style. We
              believe in simplicity, elegance, and products that stand the test of
              time.
            </p>
          </section>
          
          <section class="about-section">
            <h2>Our Story</h2>
            <p>
              What started as a small project in a home studio has grown into a 
              globally recognized brand. We spent years researching materials 
              and refining our production process to ensure that every garment 
              leaving our warehouse meets the highest standards of excellence.
            </p>
          </section>

          <section class="about-section">
            <h2>Our Values</h2>
            <p>
              Sustainability, integrity, and innovation are at the heart of 
              everything we do. We work relentlessly to source the best 
              materials, ensuring that every piece we offer not only looks 
              exceptional but also feels incredibly comfortable.
            </p>
          </section>
        </div>
        <div class="about-image">
          <img src="../images/Designs/1.webp"/>
        </div>
        <div class="Follow">
          <h2>Follow Us</h2>
          <p>
            Stay connected with us on social media for the latest updates,
            trends, and exclusive offers.
          </p>
          <div class="social-links" style="display:flex; gap:15px; align-items: center;">
            <a href="FutureUpdate.php"><i class="fab fa-instagram" style="font-size: 30px; color: #ffffffff;"></i></a>
            <a href="FutureUpdate.php"><i class="fab fa-facebook-f" style="font-size: 30px; color: #ffffffff;"></i></a>
            <a href="FutureUpdate.php"><i class="fab fa-x-twitter" style="font-size: 30px; color: #ffffffff;"></i></a>
            <a href="FutureUpdate.php"><i class="fab fa-youtube" style="font-size: 30px; color: #ffffffff;"></i></a>
          </div>
        </div>
      </div>
    </main>
<?php require_once 'footer.php'; ?>
