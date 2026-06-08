<?php
// customer/Contact.php
$page_title = 'Contact';
require_once 'header.php';

$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = true;
}
?>
    <main class="page-main">
      <div class="page-header">
        <h1>Contact Us</h1>
        <p>We'd love to hear from you. Drop us a message below.</p>
      </div>

      <div class="contact-content">
        <?php if ($success): ?>
          <div style="background-color: #e8f5e9; color: #2e7d32; border: 1.5px solid #2e7d32; padding: 25px; font-weight: bold; border-radius: 0; box-shadow: 6px 6px 0 #000; text-align: left; grid-column: 1 / -1;">
            <i class="fas fa-check-circle" style="margin-right: 8px; font-size:1.2rem;"></i>Thank you for contacting us! Your message has been received. Our support team will get back to you shortly.
          </div>
        <?php else: ?>
          <form class="contact-form" action="Contact.php" method="post">
            <div class="input-group">
              <label for="c-name">Name</label>
              <input type="text" id="c-name" name="name" placeholder="Your Name" required />
            </div>
            <div class="input-group">
              <label for="c-email">Email</label>
              <input type="email" id="c-email" name="email" placeholder="Your Email" required />
            </div>
            <div class="input-group">
              <label for="message">Message</label>
              <textarea id="message" name="message" placeholder="How can we help you?" required></textarea>
            </div>
            <button type="submit" class="contact-btn">Send Message</button>
          </form>
        <?php endif; ?>
        <div class="contact-info">
          <h2>Get in Touch</h2>
          <p>Whether you have a question about our products, shipping, returns, or anything else, our team is ready to answer all your questions.</p>
          <p><strong>Email:</strong> support@nocturne.com</p>
          <p><strong>Phone:</strong> +92-300-1234567</p>
          <p><strong>Address:</strong> DHA Phase 6, Lahore, Pakistan</p>
        </div>

      </div>
    </main>
<?php require_once 'footer.php'; ?>
