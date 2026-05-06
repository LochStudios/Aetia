<?php
// index.php - Homepage for Aetia Talent Agency
require_once __DIR__ . '/includes/session_bootstrap.php';
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
<div class="notification is-success mb-4" data-auto-dismiss="6000">
    <button class="delete" aria-label="dismiss"></button>
    <span class="icon-text">
        <span class="icon"><i class="fas fa-circle-check"></i></span>
        <span><?= htmlspecialchars($loginSuccessMessage) ?></span>
    </span>
</div>
<?php endif; ?>

<?php if ($errorMessage): ?>
<div class="notification is-danger mb-4">
    <button class="delete" aria-label="dismiss"></button>
    <span class="icon-text">
        <span class="icon"><i class="fas fa-triangle-exclamation"></i></span>
        <span><?= htmlspecialchars($errorMessage) ?></span>
    </span>
</div>
<?php endif; ?>

<section class="hero is-medium">
    <div class="hero-body">
        <div class="container has-text-centered">
            <img src="/img/logo.png" alt="Aetia Logo" style="width:auto;max-width:280px;margin:0 auto 2rem;display:block;">
            <h1 class="title is-1 has-text-light">Aetia Talent Agency</h1>
            <h2 class="subtitle is-3 has-text-primary" style="font-weight:700;">Talent, Unfiltered. Opportunity, Unlocked.</h2>
            <p class="mb-5 has-text-grey" style="font-size:1.15rem;max-width:760px;margin-left:auto;margin-right:auto;">
                Founded by creators, for creators. Aetia specializes in professional communications management — assessing every inbound message and routing real opportunities to your secure dashboard so you can focus on creating.
            </p>
            <p class="mb-6 has-text-grey" style="max-width:760px;margin-left:auto;margin-right:auto;">
                Creative, collaborative, and committed to excellence. If you want to grow, connect, and build something that lasts — you're in the right place.
            </p>
            <div class="buttons is-centered">
                <a class="button is-primary is-medium" href="/about.php">
                    <span class="icon"><i class="fas fa-circle-info"></i></span>
                    <span>Learn More About Us</span>
                </a>
                <a class="button is-light is-medium" href="/contact.php">
                    <span class="icon"><i class="fas fa-envelope"></i></span>
                    <span>Get In Touch</span>
                </a>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="columns is-multiline">
            <div class="column is-4">
                <div class="aetia-service-card">
                    <div class="aetia-service-icon"><i class="fas fa-shield-halved"></i></div>
                    <h3 class="title is-4">Communications, handled</h3>
                    <p class="has-text-grey">Every inbound DM, email, and inquiry is triaged for legitimacy, intent, and value before it ever reaches you.</p>
                </div>
            </div>
            <div class="column is-4">
                <div class="aetia-service-card">
                    <div class="aetia-service-icon"><i class="fas fa-handshake"></i></div>
                    <h3 class="title is-4">Real partnerships</h3>
                    <p class="has-text-grey">From contracts to invoicing, Aetia operates as the operational backbone behind your brand and creator partnerships.</p>
                </div>
            </div>
            <div class="column is-4">
                <div class="aetia-service-card">
                    <div class="aetia-service-icon"><i class="fas fa-chart-line"></i></div>
                    <h3 class="title is-4">Built to scale with you</h3>
                    <p class="has-text-grey">A secure dashboard, transparent reporting, and a team that grows alongside your audience and ambitions.</p>
                </div>
            </div>
        </div>
    </div>
</section>
<?php
$content = ob_get_clean();
include 'layout.php';
?>
