<?php
// pricing.php - Pricing page for Aetia Talent Agency
require_once __DIR__ . '/includes/session_bootstrap.php';
session_start();
$pageTitle = 'Pricing | Aetia Talent Agency';
ob_start();
?>
<section class="section" style="padding-top:0;">
    <div class="has-text-centered mb-6">
        <h1 class="title is-1 has-text-primary">Pricing</h1>
        <p class="subtitle is-4 has-text-grey">Communication Management Service</p>
        <p class="has-text-grey-light mx-auto" style="max-width:680px;">Transparent thread-based billing. No retainers, no surprises — you only pay for active conversations.</p>
    </div>

    <div class="box mb-5">
        <h2 class="title is-4 has-text-primary">Service Overview</h2>
        <p class="has-text-grey">Our Communication Management service handles message processing from external sources, organizing them into threads for efficient management and timely response.</p>
    </div>

    <div class="box mb-5">
        <h2 class="title is-4 has-text-primary">Pricing Structure</h2>
        <p class="is-size-5 has-text-weight-semibold mb-4">All prices are in USD ($).</p>

        <div class="columns">
            <div class="column is-half">
                <div class="notification is-info">
                    <h3 class="title is-5">Standard Processing</h3>
                    <p class="has-text-weight-bold mb-2" style="font-size:1.5rem;color:var(--aetia-info);">$1 <span class="is-size-7 has-text-grey">/ message thread</span></p>
                    <ul>
                        <li>Processed during normal hours: 12:00 PM – 1:00 PM AEST</li>
                        <li>Each thread (including replies under the same subject) is billed once</li>
                        <li>External source messages only</li>
                    </ul>
                </div>
            </div>
            <div class="column is-half">
                <div class="notification is-warning">
                    <h3 class="title is-5">Out-of-Hours Processing</h3>
                    <p class="has-text-weight-bold mb-2" style="font-size:1.5rem;color:var(--aetia-warning);">$2 <span class="is-size-7 has-text-grey">/ message thread</span></p>
                    <ul>
                        <li>Processed outside normal hours (12:00 PM – 1:00 PM AEST)</li>
                        <li>Includes a $1 surcharge per thread</li>
                        <li>Internally-triggered message checks count when applicable</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="notification is-success mt-4">
            <h3 class="title is-5">Billing Notes</h3>
            <ul>
                <li><strong>Thread-based billing:</strong> only the first message in a conversation thread is charged</li>
                <li>Replies and follow-up messages in the same thread are not additionally charged</li>
                <li>Out-of-hours processing applies to anything outside the 12:00 PM – 1:00 PM AEST window</li>
                <li>Internally-initiated checks (when anticipating external responses) follow out-of-hours rates if outside normal processing time</li>
            </ul>
        </div>
    </div>

    <div class="box mb-5">
        <h2 class="title is-4 has-text-primary">SMS Notifications</h2>
        <div class="notification is-info">
            <h3 class="title is-5">$0.30 per SMS message</h3>
            <ul>
                <li>Charged per SMS message sent</li>
                <li>US phone numbers only at present</li>
                <li>More countries coming soon</li>
            </ul>
        </div>
    </div>

    <div class="has-text-centered mt-6">
        <p class="is-size-5 mb-4">Ready to get started with Communication Management?</p>
        <a href="/contact.php" class="button is-primary is-large">
            <span class="icon"><i class="fas fa-envelope"></i></span>
            <span>Contact Us for Details</span>
        </a>
    </div>
</section>
<?php
$content = ob_get_clean();
include 'layout.php';
?>
