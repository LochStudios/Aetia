<?php
// index.php - Homepage for Aetia Talant Agency
session_start();

$pageTitle = 'Home | Aetia Talant Agency';
ob_start();

// Check for login success message
$loginSuccessMessage = '';
if (isset($_SESSION['login_success'])) {
    $loginSuccessMessage = $_SESSION['login_success'];
    unset($_SESSION['login_success']);
}
?>

<?php if ($loginSuccessMessage): ?>
<div class="notification is-success is-light mb-4">
    <button class="delete"></button>
    <span class="icon-text">
        <span class="icon"><i class="fas fa-check-circle"></i></span>
        <span><?= htmlspecialchars($loginSuccessMessage) ?></span>
    </span>
</div>
<?php endif; ?>

<section class="hero is-dark" style="min-height:60vh;display:flex;align-items:center;">
    <div class="hero-body py-4">
        <div class="container has-text-centered">
            <img src="img/logo.png" alt="Aetia Logo" style="width:480px; height:270px; object-fit:cover; object-position:center 30%; margin-bottom:2rem; filter:brightness(0) invert(1);">
            <h1 class="title is-1 has-text-light">Aetia Talant Agency</h1>
            <h2 class="subtitle is-3 has-text-info" style="font-weight:700;">Talent, Unfiltered. Opportunity, Unlocked.</h2>
            <p class="mb-5 has-text-grey-light" style="font-size:1.25rem;max-width:700px;margin-left:auto;margin-right:auto;">
                Founded by creators, for creators. At Aetia Talant Agency, we break the awkwardness—empowering you to be boldly original, not just another face in the crowd.<br>
                We’re a new kind of agency: creative, collaborative, and real. Honestly? We don’t have all the answers—but we’re figuring it out together, with you. If you want to grow, experiment, and make something different, you’re in the right place.
            </p>
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
?>