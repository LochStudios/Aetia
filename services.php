<?php
// services.php - Services page for Aetia Talent Agency
require_once __DIR__ . '/includes/session_bootstrap.php';
session_start();
$pageTitle = 'Services | Aetia Talent Agency';
ob_start();
?>
<section class="section" style="padding-top:0;">
    <div class="has-text-centered mb-6">
        <h1 class="title is-1 has-text-light">Our Services</h1>
        <p class="subtitle is-4 has-text-grey">Built around how creators actually work.</p>
        <p class="mx-auto has-text-grey" style="max-width:720px;font-size:1.05rem;">
            We specialize in professional communications management — every incoming message is assessed, prioritized, and routed to your secure dashboard so you can decide what's worth your time.
        </p>
    </div>

    <div class="columns is-multiline">
        <div class="column is-4">
            <div class="aetia-service-card has-text-centered">
                <div class="aetia-service-icon mx-auto" style="margin-left:auto;margin-right:auto;"><i class="fas fa-envelope-open-text"></i></div>
                <h3 class="title is-4">Message Assessment</h3>
                <p class="has-text-grey">We meticulously evaluate every incoming communication to identify real opportunities and weed out noise — only what matters reaches you.</p>
            </div>
        </div>
        <div class="column is-4">
            <div class="aetia-service-card has-text-centered">
                <div class="aetia-service-icon mx-auto"><i class="fas fa-gauge-high"></i></div>
                <h3 class="title is-4">Custom Dashboard</h3>
                <p class="has-text-grey">An intuitive secure dashboard to review routed messages, manage responses, and stay in full control of your communications.</p>
            </div>
        </div>
        <div class="column is-4">
            <div class="aetia-service-card has-text-centered">
                <div class="aetia-service-icon mx-auto"><i class="fas fa-route"></i></div>
                <h3 class="title is-4">Communication Routing</h3>
                <p class="has-text-grey">Pertinent messages flow to the right channel, with consistent professional voice and timely engagement on your behalf.</p>
            </div>
        </div>
        <div class="column is-4">
            <div class="aetia-service-card has-text-centered">
                <div class="aetia-service-icon mx-auto"><i class="fas fa-shield-halved"></i></div>
                <h3 class="title is-4">Secure Management</h3>
                <p class="has-text-grey">Communications handled with rigorous security and discretion — protecting your brand, your data, and your relationships.</p>
            </div>
        </div>
        <div class="column is-4">
            <div class="aetia-service-card has-text-centered">
                <div class="aetia-service-icon mx-auto"><i class="fas fa-file-signature"></i></div>
                <h3 class="title is-4">Contracts &amp; Billing</h3>
                <p class="has-text-grey">Generate, send and sign agreements; produce clean invoices and keep the paper trail tidy — all in one place.</p>
            </div>
        </div>
        <div class="column is-4">
            <div class="aetia-service-card has-text-centered">
                <div class="aetia-service-icon mx-auto"><i class="fas fa-people-group"></i></div>
                <h3 class="title is-4">Long-Term Partnership</h3>
                <p class="has-text-grey">We grow as you grow. Aetia's not a tool you outgrow — it's a team that scales with your audience and ambitions.</p>
            </div>
        </div>
    </div>
</section>
<?php
$content = ob_get_clean();
include 'layout.php';
?>
