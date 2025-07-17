<?php
// contact.php - Contact page for Aetia Talant Agency
$pageTitle = 'Contact | Aetia Talant Agency';
ob_start();
?>
<div class="content">
    <h2>Contact Us</h2>
    <form action="#" method="post">
        <div class="field">
            <label class="label">Name</label>
            <div class="control">
                <input class="input" type="text" name="name" required>
            </div>
        </div>
        <div class="field">
            <label class="label">Email</label>
            <div class="control">
                <input class="input" type="email" name="email" required>
            </div>
        </div>
        <div class="field">
            <label class="label">Message</label>
            <div class="control">
                <textarea class="textarea" name="message" required></textarea>
            </div>
        </div>
        <div class="control">
            <button class="button is-link" type="submit">Send</button>
        </div>
    </form>
    <a class="button is-light" href="index.php">Back to Home</a>
</div>
<?php
$content = ob_get_clean();
include 'layout.php';
