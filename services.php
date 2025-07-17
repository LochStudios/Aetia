<?php
// services.php - Services page for Aetia Talant Agency
$pageTitle = 'Services | Aetia Talant Agency';
ob_start();
?>
<div class="content">
    <h2>Our Services</h2>
    <div class="columns is-multiline">
        <div class="column is-6-tablet is-4-desktop">
            <div class="card">
                <div class="card-content">
                    <p class="title is-5">Talent Representation</p>
                    <p>Connecting talent with top opportunities in various industries.</p>
                </div>
            </div>
        </div>
        <div class="column is-6-tablet is-4-desktop">
            <div class="card">
                <div class="card-content">
                    <p class="title is-5">Career Management</p>
                    <p>Personalized guidance and support for career growth.</p>
                </div>
            </div>
        </div>
        <div class="column is-6-tablet is-4-desktop">
            <div class="card">
                <div class="card-content">
                    <p class="title is-5">Event Promotion</p>
                    <p>Organizing and promoting events for our clients and talent.</p>
                </div>
            </div>
        </div>
        <div class="column is-6-tablet is-4-desktop">
            <div class="card">
                <div class="card-content">
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
