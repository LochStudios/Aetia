<?php
// services.php - Services page for Aetia Talant Agency
$pageTitle = 'Services | Aetia Talant Agency';
ob_start();
?>
<div class="content">
    <h2>Our Services</h2>
    <ul>
        <li><strong>Talent Representation:</strong> Connecting talent with top opportunities in various industries.</li>
        <li><strong>Career Management:</strong> Personalized guidance and support for career growth.</li>
        <li><strong>Event Promotion:</strong> Organizing and promoting events for our clients and talent.</li>
        <li><strong>Consulting:</strong> Industry insights and consulting for both talent and businesses.</li>
    </ul>
    <a class="button is-primary" href="contact.php">Contact Us</a>
    <a class="button is-light" href="index.php">Back to Home</a>
</div>
<?php
$content = ob_get_clean();
include 'layout.php';
