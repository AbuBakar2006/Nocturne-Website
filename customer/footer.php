<?php
// customer/footer.php
?>
    <br class="Line" />
    <footer class="site-footer">
      <div class="footer-column">
        <h3>Quick Links</h3>
        <a href="Homepage.php">Home</a>
        <a href="Products.php">Products</a>
        <a href="AboutUs.php">About Us</a>
        <a href="Contact.php">Contact</a>
      </div>
      <div class="footer-column">
        <h3>Customer Service</h3>
        <a href="FutureUpdate.php">Terms &amp; Services</a>
        <a href="FutureUpdate.php">Delivery / Exchange / Return Policy</a>
        <a href="FutureUpdate.php">Privacy Policy</a>
      </div>
      <div class="footer-column">
        <h3>Newsletter</h3>
        <p>
          Subscribe to get special offers, free giveaways, and
          once-in-a-lifetime deals.
        </p>
        <form class="newsletter-form" action="#" method="post" onsubmit="event.preventDefault(); alert('Thank you for subscribing to our newsletter!');">
          <input type="email" placeholder="Enter your email" class="newsletter-input" required />
          <button type="submit" class="newsletter-btn">Subscribe</button>
        </form>
      </div>
    </footer>
  </body>
</html>
