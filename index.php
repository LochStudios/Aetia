<?php
// index.php - Homepage for Aetia Talent Agency
session_start();

$pageTitle = 'Home | Aetia Talent Agency';
ob_start();

// Check for login success message
$loginSuccessMessage = '';
if (isset($_SESSION['login_success'])) {
    $loginSuccessMessage = $_SESSION['login_success'];
    unset($_SESSION['login_success']);
}

// Check for error message
$errorMessage = '';
if (isset($_SESSION['error_message'])) {
    $errorMessage = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
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

<?php if ($errorMessage): ?>
<div class="notification is-danger is-light mb-4">
    <button class="delete"></button>
    <span class="icon-text">
        <span class="icon"><i class="fas fa-exclamation-triangle"></i></span>
        <span><?= htmlspecialchars($errorMessage) ?></span>
    </span>
</div>
<?php endif; ?>

<section class="hero is-dark" style="min-height:60vh;display:flex;align-items:center;">
    <div class="hero-body py-4">
        <div class="container has-text-centered">
            <img src="img/logo.png" alt="Aetia Logo" style="width:480px; height:270px; object-fit:cover; object-position:center 30%; margin-bottom:2rem; filter:brightness(0) invert(1);">
            <h1 class="title is-1 has-text-light">Aetia Talent Agency</h1>
            <h2 class="subtitle is-3 has-text-info" style="font-weight:700;">Talent, Unfiltered. Opportunity, Unlocked.</h2>
            <p class="mb-5 has-text-grey-light" style="font-size:1.25rem;max-width:700px;margin-left:auto;margin-right:auto;">
                Founded by creators, for creators. At Aetia Talent Agency, we specialize in professional communications management, ensuring seamless message assessment and routing to empower your creative journey.<br>
                We’re a dedicated agency: creative, collaborative, and committed to excellence. Our expert team handles all incoming communications with precision, routing relevant opportunities to our secure dashboard for your review and response. If you want to grow, connect, and make something impactful, you’re in the right place.
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