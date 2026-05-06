<?php
// about.php - About page for Aetia Talent Agency
require_once __DIR__ . '/includes/session_bootstrap.php';
session_start();
$pageTitle = 'About | Aetia Talent Agency';
ob_start();
?>
<section class="section" style="padding-top:0;">
    <div class="columns is-vcentered is-multiline">
        <div class="column is-5">
            <div class="aetia-about-card">
                <h3 class="title is-4 has-text-primary mb-3">What does <strong>Aetia</strong> mean?</h3>
                <p>
                    <span class="icon has-text-warning"><i class="fas fa-bolt"></i></span>
                    <em>Pronounced:</em> <strong>AY-tee-uh</strong>
                </p>
                <hr>
                <p>The meaning of <strong>Aetia</strong> stems from its subtle connection to Greek roots:</p>
                <p>It suggests being the underlying cause or catalyst for creative talent — an ethereal force that sparks and elevates careers within the digital and multimedia space.</p>
            </div>
        </div>
        <div class="column is-7">
            <div class="aetia-about-box">
                <h2 class="title is-2 has-text-primary">About Us</h2>
                <p class="mb-4">Aetia Talent Agency was founded by creators, for creators. We're not here to pretend we have all the answers — we're here to learn, experiment, and grow with you. Our approach is collaborative, honest, and always a little bit different. If you want to break the awkwardness and do things your way, you're in the right place.</p>
                <p class="mb-5 has-text-grey">We handle the busywork — vetting inbound messages, drafting professional replies, managing contracts and invoicing — so you can spend your time on the work that actually moves your career forward.</p>
                <a class="button is-primary is-medium" href="/contact.php">
                    <span class="icon"><i class="fas fa-envelope"></i></span>
                    <span>Contact Us</span>
                </a>
            </div>
        </div>
    </div>
</section>
<?php
$content = ob_get_clean();
include 'layout.php';
?>
