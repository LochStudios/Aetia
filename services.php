<?php
// services.php - Services page for Aetia Talent Agency
session_start();
$pageTitle = 'Services | Aetia Talent Agency';
ob_start();
?>
<div class="content">
    <h2 class="title is-2 has-text-info mb-4">Our Services</h2>
    <div class="mb-5" style="max-width:700px;margin-left:auto;margin-right:auto;">
        <p class="has-text-grey-light" style="font-size:1.1rem;">
            At Aetia Talent Agency, we specialize in professional communications management, ensuring that every incoming message is handled with precision and care. Our dedicated team assesses all communications, routing relevant inquiries to our secure custom dashboard for your review and response.
        </p>
        <hr class="my-3 has-background-grey-dark">
        <p class="has-text-grey-light" style="font-size:1.1rem;">
            Our core services include:
        </p>
    </div>
    <div class="columns is-multiline is-variable is-4">
        <div class="column is-6-tablet is-4-desktop">
            <div class="aetia-service-card mb-5 has-text-centered">
                <span class="icon is-large aetia-service-icon has-text-link"><i class="fas fa-envelope fa-2x"></i></span>
                <p class="title is-5 has-text-light">Message Assessment</p>
                <p>We meticulously evaluate all incoming communications to identify opportunities and prioritize responses that align with your goals.</p>
            </div>
        </div>
        <div class="column is-6-tablet is-4-desktop">
            <div class="aetia-service-card mb-5 has-text-centered">
                <span class="icon is-large aetia-service-icon has-text-primary"><i class="fas fa-tachometer-alt fa-2x"></i></span>
                <p class="title is-5 has-text-light">Custom Dashboard</p>
                <p>Access our intuitive dashboard to review routed messages, manage responses, and maintain control over your communications.</p>
            </div>
        </div>
        <div class="column is-6-tablet is-4-desktop">
            <div class="aetia-service-card mb-5 has-text-centered">
                <span class="icon is-large aetia-service-icon has-text-info"><i class="fas fa-comments fa-2x"></i></span>
                <p class="title is-5 has-text-light">Communication Routing</p>
                <p>Efficiently route pertinent messages to the appropriate channels, ensuring timely and relevant engagement with your audience.</p>
            </div>
        </div>
        <div class="column is-6-tablet is-4-desktop">
            <div class="aetia-service-card mb-5 has-text-centered">
                <span class="icon is-large aetia-service-icon has-text-success"><i class="fas fa-shield-alt fa-2x"></i></span>
                <p class="title is-5 has-text-light">Secure Management</p>
                <p>Handle all communications with the highest standards of security and professionalism, protecting your brand and relationships.</p>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include 'layout.php';
?>