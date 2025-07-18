<?php
// services.php - Services page for Aetia Talant Agency
$pageTitle = 'Services | Aetia Talant Agency';
ob_start();
?>
<div class="content">
    <h2>Our Services</h2>
    <div class="columns is-multiline">
        <div class="column is-6-tablet is-4-desktop">
            <div class="card has-shadow mb-5">
                <div class="card-content has-text-centered">
                    <span class="icon is-large has-text-link mb-2"><i class="fas fa-user-tie fa-2x"></i></span>
                    <p class="title is-5">Talent Representation</p>
                    <p>Connecting talent with top opportunities in various industries.</p>
                </div>
            </div>
        </div>
        <div class="column is-6-tablet is-4-desktop">
            <div class="card has-shadow mb-5">
                <div class="card-content has-text-centered">
                    <span class="icon is-large has-text-primary mb-2"><i class="fas fa-chart-line fa-2x"></i></span>
                    <p class="title is-5">Career Management</p>
                    <p>Personalized guidance and support for career growth.</p>
                </div>
            </div>
        </div>
        <div class="column is-6-tablet is-4-desktop">
            <div class="card has-shadow mb-5">
                <div class="card-content has-text-centered">
                    <span class="icon is-large has-text-info mb-2"><i class="fas fa-bullhorn fa-2x"></i></span>
                    <p class="title is-5">Event Promotion</p>
                    <p>Organizing and promoting events for our clients and talent.</p>
                </div>
            </div>
        </div>
        <div class="column is-6-tablet is-4-desktop">
            <div class="card has-shadow mb-5">
                <div class="card-content has-text-centered">
                    <span class="icon is-large has-text-success mb-2"><i class="fas fa-lightbulb fa-2x"></i></span>
                    <p class="title is-5">Consulting</p>
                    <p>Industry insights and consulting for both talent and businesses.</p>
                </div>
            </div>
        </div>
    </div>
    <a class="button is-primary" href="contact.php">Contact Us</a>
    <a class="button is-light" href="index.php">Back to Home</a>
</div>
<?php
$content = ob_get_clean();
include 'layout.php';
