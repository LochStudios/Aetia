<?php
// about.php - About page for Aetia Talant Agency
$pageTitle = 'About | Aetia Talant Agency';
ob_start();
?>
<div class="content">
    <div class="columns is-vcentered is-multiline">
        <div class="column is-5">
            <div class="card aetia-about-card">
                <div class="card-content has-text-light">
                    <h3 class="title is-5 mb-2 has-text-info">What does <strong>Aetia</strong> mean?</h3>
                    <p><span class="icon has-text-warning"><i class="fas fa-bolt"></i></span> <em>Pronounced:</em> <strong>AY-tee-uh</strong></p>
                    <hr class="has-background-grey-dark">
                    <p class="mt-2">The meaning of <strong>Aetia</strong> stems from its subtle connection to Greek roots:</p>
                    <p>It suggests being the underlying cause or catalyst for creative talent, implying an ethereal force that sparks and elevates careers within the digital and multimedia space.</p>
                </div>
            </div>
        </div>
        <div class="column is-7">
            <div class="box aetia-about-box has-background-dark has-text-light">
                <h2 class="title is-3 has-text-info">About Us</h2>
                <p class="mb-4">Aetia Talant Agency was founded by creators, for creators. We’re not here to pretend we have all the answers—we’re here to learn, experiment, and grow with you. Our approach is collaborative, honest, and always a little bit different. If you want to break the awkwardness and do things your way, you’re in the right place.</p>
                <a class="button is-primary is-medium mr-2" href="contact.php">
                    <span class="icon"><i class="fas fa-envelope"></i></span>
                    <span>Contact Us</span>
                </a>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include 'layout.php';
?>