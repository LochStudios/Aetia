<?php
// services.php - Services page for Aetia Talant Agency
session_start();
$pageTitle = 'Services | Aetia Talant Agency';
ob_start();
?>
<div class="content">
    <h2 class="title is-2 has-text-info mb-4">Our Services</h2>
    <div class="mb-5" style="max-width:700px;margin-left:auto;margin-right:auto;">
        <p class="has-text-grey-light" style="font-size:1.1rem;">
            We don’t have all the answers, and we’re new to this too—but we’re here to work with you, and with the companies you want to work with. Let’s figure it out together and make something great.
        </p>
        <hr class="my-3 has-background-grey-dark">
        <p class="has-text-grey-light" style="font-size:1.1rem;">
            Things we can help with include:
        </p>
    </div>
    <div class="columns is-multiline is-variable is-4">
        <div class="column is-6-tablet is-4-desktop">
            <div class="aetia-service-card mb-5 has-text-centered">
                <span class="icon is-large aetia-service-icon has-text-link"><i class="fas fa-user-tie fa-2x"></i></span>
                <p class="title is-5 has-text-light">Talent Representation</p>
                <p>We’re still learning what this means, but we’ll figure it out with you and help you get noticed by the right people.</p>
            </div>
        </div>
        <div class="column is-6-tablet is-4-desktop">
            <div class="aetia-service-card mb-5 has-text-centered">
                <span class="icon is-large aetia-service-icon has-text-primary"><i class="fas fa-chart-line fa-2x"></i></span>
                <p class="title is-5 has-text-light">Career Management</p>
                <p>We don’t have all the answers, but we’ll support your journey and help you navigate new opportunities—together.</p>
            </div>
        </div>
        <div class="column is-6-tablet is-4-desktop">
            <div class="aetia-service-card mb-5 has-text-centered">
                <span class="icon is-large aetia-service-icon has-text-info"><i class="fas fa-bullhorn fa-2x"></i></span>
                <p class="title is-5 has-text-light">Event Promotion</p>
                <p>We’re new to this, but we’ll work with you and the companies you want to work with to make your events a success.</p>
            </div>
        </div>
        <div class="column is-6-tablet is-4-desktop">
            <div class="aetia-service-card mb-5 has-text-centered">
                <span class="icon is-large aetia-service-icon has-text-success"><i class="fas fa-lightbulb fa-2x"></i></span>
                <p class="title is-5 has-text-light">Consulting</p>
                <p>We’re not experts, but we’re here to listen, learn, and help however we can—no jargon, just real talk.</p>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include 'layout.php';
?>