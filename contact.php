<?php
// contact.php - Contact page for Aetia Talant Agency
$pageTitle = 'Contact | Aetia Talant Agency';
ob_start();
?>
<div class="content">
    <h2>Contact Us</h2>
    <form>
        <div class="notification is-info">
            <strong>Notice:</strong> Our online contact form is currently undergoing scheduled maintenance to ensure the highest level of service and security for our clients.<br>
            For all inquiries, please email us directly at <a href="mailto:talant@aetia.com.au">talant@aetia.com.au</a>.<br>
            We appreciate your understanding and look forward to assisting you.
        </div>
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
    <a class="button is-light" href="index.php">Back to Home</a>
</div>
<?php
$content = ob_get_clean();
include 'layout.php';
