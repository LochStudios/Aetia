<?php
// index.php - Homepage for Aetia Talant Agency
$pageTitle = 'Home | Aetia Talant Agency';
ob_start();
?>
<section class="hero is-dark is-fullheight-with-navbar">
    <div class="hero-body">
        <div class="container has-text-centered">
            <img src="img/logo.png" alt="Aetia Logo" style="max-width:120px; margin-bottom:1.5rem;filter:brightness(0) invert(1);">
            <h1 class="title is-1 has-text-light">Aetia Talant Agency</h1>
            <h2 class="subtitle is-4 has-text-grey-light">Catalyst for Creative Talent</h2>
            <p class="mb-5 has-text-grey-light">Your trusted partner in talent management and agency services.<br>We connect exceptional talent with outstanding opportunities in the digital and multimedia space.</p>
            <a class="button is-info is-medium mr-2" href="about.php">
                <span class="icon"><i class="fas fa-info-circle"></i></span>
                <span>Learn More About Us</span>
            </a>
            <a class="button is-light is-medium" href="contact.php">
                <span class="icon"><i class="fas fa-envelope"></i></span>
                <span>Contact</span>
            </a>
        </div>
    </div>
</section>
<?php
$content = ob_get_clean();
include 'layout.php';
