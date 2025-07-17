<?php
// about.php - About page for Aetia Talant Agency
$pageTitle = 'About | Aetia Talant Agency';
ob_start();
?>
<div class="content">
    <h2>About Us</h2>
    <p>Aetia Talant Agency is dedicated to discovering, nurturing, and representing top talent across various industries. Our experienced team ensures both clients and talent receive the best possible service.</p>
    <a class="button is-primary" href="contact.php">Contact Us</a>
    <a class="button is-light" href="index.php">Back to Home</a>
</div>
<?php
$content = ob_get_clean();
include 'layout.php';
