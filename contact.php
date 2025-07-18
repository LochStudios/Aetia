<?php
// contact.php - Contact page for Aetia Talant Agency
$pageTitle = 'Contact | Aetia Talant Agency';
ob_start();
?>
<div class="columns is-centered">
  <div class="column is-7-tablet is-6-desktop">
    <div class="card mt-6">
      <div class="card-content">
        <h2 class="title is-3 mb-4"><span class="icon has-text-link"><i class="fas fa-envelope"></i></span> Contact Us</h2>
        <div class="notification is-info is-light mb-4">
          <span class="icon-text">
            <span class="icon"><i class="fas fa-info-circle"></i></span>
            <span><strong>Notice:</strong> Our online contact form is currently undergoing scheduled maintenance to ensure the highest level of service and security for our clients.</span>
          </span><br>
          For all inquiries, please email us directly at <a href="mailto:talant@aetia.com.au">talant@aetia.com.au</a>.<br>
          We appreciate your understanding and look forward to assisting you.
        </div>
        <form>
          <div class="field">
            <label class="label">Name</label>
            <div class="control">
              <input class="input" type="text" name="name" disabled>
            </div>
          </div>
          <div class="field">
            <label class="label">Email</label>
            <div class="control">
              <input class="input" type="email" name="email" disabled>
            </div>
          </div>
          <div class="field">
            <label class="label">Message</label>
            <div class="control">
              <textarea class="textarea" name="message" disabled></textarea>
            </div>
          </div>
          <div class="control">
            <button class="button is-link" type="submit" disabled>Send</button>
          </div>
        </form>
        <a class="button is-light mt-4" href="index.php">
          <span class="icon"><i class="fas fa-home"></i></span>
          <span>Back to Home</span>
        </a>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include 'layout.php';
