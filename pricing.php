<?php
// pricing.php - Pricing page for Aetia Talent Agency
session_start();

$pageTitle = 'Pricing | Aetia Talent Agency';
ob_start();
?>
<div class="container">
    <div class="has-text-centered mb-6">
        <h1 class="title is-2 has-text-primary">Pricing</h1>
        <p class="subtitle is-5 has-text-grey">Communication Management Service</p>
    </div>

    <div class="content">
        <div class="box">
            <h2 class="title is-4 has-text-primary">Service Overview</h2>
            <p>Our Communication Management service handles message processing from external sources, organizing them into threads for efficient management and response.</p>
        </div>

        <div class="box">
            <h2 class="title is-4 has-text-primary">Pricing Structure</h2>
            <p class="is-size-5 has-text-weight-bold mb-4">All prices are in USD ($)</p>

            <div class="columns">
                <div class="column is-half">
                    <div class="notification is-info is-light">
                        <h3 class="title is-5">Standard Processing</h3>
                        <p><strong>$1 per message thread</strong></p>
                        <ul>
                            <li>Messages processed during normal hours: 12:00 PM - 1:00 PM AEST (Australian Eastern Standard Time)</li>
                            <li>Each thread (including replies and subsequent messages under the same subject) is billed once</li>
                            <li>External source messages only</li>
                        </ul>
                    </div>
                </div>
                <div class="column is-half">
                    <div class="notification is-warning is-light">
                        <h3 class="title is-5">Out-of-Hours Processing</h3>
                        <p><strong>$2 per message thread</strong></p>
                        <ul>
                            <li>Messages processed outside normal hours (12:00 PM - 1:00 PM AEST)</li>
                            <li>Additional $1 surcharge per thread</li>
                            <li>Includes internally triggered message checks when expecting new external messages</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="notification is-success is-light mt-4">
                <h3 class="title is-5">Billing Notes</h3>
                <ul>
                    <li>Thread-based billing: Only the first message in a conversation thread is charged</li>
                    <li>Replies and follow-up messages in the same thread are not additionally charged</li>
                    <li>Out-of-hours processing applies to messages received or processed outside the 12:00 PM - 1:00 PM AEST window</li>
                    <li>Internally initiated message checks (when anticipating external responses) are subject to out-of-hours rates if outside normal processing time</li>
                </ul>
            </div>
        <div class="box">
            <h2 class="title is-4 has-text-primary">SMS Notifications</h2>
            <div class="notification is-link is-light">
                <h3 class="title is-5">Pricing: $0.30 per SMS Message</h3>
                <ul>
                    <li>Charged per SMS message sent</li>
                    <li>US Phone Numbers Only</li>
                    <li>More countries coming soon</li>
                </ul>
            </div>
        </div>
            <p class="is-size-5 mb-4">Ready to get started with Communication Management?</p>
            <a href="contact.php" class="button is-primary is-large">
                <span class="icon"><i class="fas fa-envelope"></i></span>
                <span>Contact Us for Details</span>
            </a>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include 'layout.php';
?>