<?php
// contact.php - Contact page for Aetia Talant Agency
session_start();
$pageTitle = 'Contact | Aetia Talant Agency';
ob_start();
?>
<div class="card mt-6" style="width:100%;max-width:none;">
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
    </div>
</div>
<?php
$content = ob_get_clean();
include 'layout.php';
?>