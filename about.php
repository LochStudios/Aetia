<?php
// about.php - About page for Aetia Talant Agency
$pageTitle = 'About | Aetia Talant Agency';
ob_start();
?>
<div class="content">
    <h2>About Us</h2>
    <div class="box" style="margin-bottom:1.5rem;">
        <h3 class="title is-5" style="margin-bottom:0.5rem;">What does <strong>Aetia</strong> mean?</h3>
        <p><em>Pronounced:</em> <strong>AY-tee-uh</strong></p>
        <p>The meaning of <strong>Aetia</strong> stems from its subtle connection to Greek roots:</p>
        <p>It suggests being the underlying cause or catalyst for creative talent, implying an ethereal force that sparks and elevates careers within the digital and multimedia space.</p>
    </div>
    <p>Aetia Talant Agency is dedicated to discovering, nurturing, and representing top talent across various industries. Our experienced team ensures both clients and talent receive the best possible service.</p>
    <a class="button is-primary" href="contact.php">Contact Us</a>
    <a class="button is-light" href="index.php">Back to Home</a>
</div>
<?php
$content = ob_get_clean();
include 'layout.php';
