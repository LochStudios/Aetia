<?php
// index.php - Homepage for Aetia Talant Agency
$pageTitle = 'Home | Aetia Talant Agency';
ob_start();
?>
<div class="content">
    <h2>Welcome to Aetia Talant Agency</h2>
    <p>Your trusted partner in talent management and agency services. We connect exceptional talent with outstanding opportunities.</p>
    <a class="button is-link" href="about.php">Learn More About Us</a>
</div>
<?php
$content = ob_get_clean();
include 'layout.php';
