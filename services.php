<?php
// services.php - Services page for Aetia Talant Agency
$pageTitle = 'Services | Aetia Talant Agency';
ob_start();
?>
<div class="content">
    <h2>Our Services</h2>
    <div class="columns is-multiline">
        <div class="column is-6-tablet is-4-desktop">
            <div class="aetia-service-card mb-5 has-text-centered">
                <span class="icon is-large aetia-service-icon has-text-link"><i class="fas fa-user-tie fa-2x"></i></span>
                <p class="title is-5 has-text-light">Talent Representation</p>
                <p>Connecting talent with top opportunities in various industries.</p>
            </div>
        </div>
        <div class="column is-6-tablet is-4-desktop">
            <div class="aetia-service-card mb-5 has-text-centered">
                <span class="icon is-large aetia-service-icon has-text-primary"><i class="fas fa-chart-line fa-2x"></i></span>
                <p class="title is-5 has-text-light">Career Management</p>
                <p>Personalized guidance and support for career growth.</p>
            </div>
        </div>
        <div class="column is-6-tablet is-4-desktop">
            <div class="aetia-service-card mb-5 has-text-centered">
                <span class="icon is-large aetia-service-icon has-text-info"><i class="fas fa-bullhorn fa-2x"></i></span>
                <p class="title is-5 has-text-light">Event Promotion</p>
                <p>Organizing and promoting events for our clients and talent.</p>
            </div>
        </div>
        <div class="column is-6-tablet is-4-desktop">
            <div class="aetia-service-card mb-5 has-text-centered">
                <span class="icon is-large aetia-service-icon has-text-success"><i class="fas fa-lightbulb fa-2x"></i></span>
                <p class="title is-5 has-text-light">Consulting</p>
                <p>Industry insights and consulting for both talent and businesses.</p>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include 'layout.php';
