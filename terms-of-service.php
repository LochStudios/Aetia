<?php
// terms-of-service.php
session_start();

$pageTitle = 'Terms of Service | Aetia Talent Agency';
ob_start();
?>
<section class="section">
    <div class="container content">
        <h1 class="title">Terms of Service</h1>
        <p><strong>Effective date:</strong> December 18, 2025</p>
        <h2>1. Agreement</h2>
        <p>These Terms of Service ("Terms") govern your access to and use of the services, websites, APIs, and software provided by Aetia Talent Agency ("Aetia", "we", "us", "our"). By accessing or using our services you agree to these Terms. If you do not agree, do not use our services.</p>
        <h2>2. Services</h2>
        <p>Aetia provides communications management and talent services including messaging intake and routing, secure document handling, contract management, profile services, and related tools (collectively, the "Services"). We may also offer APIs and integrations for partners and customers.</p>
        <h2>3. Accounts</h2>
        <p>To use certain features you must register for an account. You are responsible for maintaining the confidentiality of your account credentials and for all activity under your account. You agree to provide accurate information and to keep it updated.</p>
        <h2>4. Acceptable Use</h2>
        <p>You will not use the Services to transmit unlawful, defamatory, abusive, obscene, or infringing content. You will not bypass security or attempt to access other users' data. We may suspend or terminate accounts that violate these rules.</p>
        <h2>5. Messaging, File Uploads &amp; Content</h2>
        <p>The Services allow submission and storage of messages, documents, images, and other uploads. You retain ownership of content you submit, but by submitting you grant Aetia a non-exclusive license to store, transmit, display, and otherwise process that content to provide the Services. Do not upload anything you do not have rights to share.</p>
        <h2>6. Contracts &amp; Documents</h2>
        <p>Where the Services facilitate contracts, proposals, or documents, Aetia is not a party to those agreements unless explicitly stated. We do not provide legal advice; users should obtain appropriate professional advice when needed.</p>
        <h2>7. Payments &amp; Billing</h2>
        <p>Certain features may require payment. You agree to pay all charges for paid services. We may use third-party payment processors to take payments (for example, Stripe or PayPal). Payment terms, refunds, and billing disputes are governed by the terms communicated at purchase.</p>
        <h2>8. Third‑Party Services &amp; OAuth</h2>
        <p>We may provide single‑sign‑on and integrations with third‑party providers (e.g., Google, Discord, Twitch). Your use of those services is subject to the third parties' terms and privacy policies. We are not responsible for their practices.</p>
        <h2>9. API Use</h2>
        <p>Access to any Aetia APIs is subject to API documentation and usage limits. You must keep API keys secure and are responsible for activity performed using your keys. We may revoke keys that are abused.</p>
        <h2>10. Intellectual Property</h2>
        <p>All Aetia trademarks, service names, logos, and software are our property. You may not use Aetia's intellectual property without our written permission.</p>
        <h2>11. Privacy</h2>
        <p>Our <a href="privacy-policy.php">Privacy Policy</a> explains how we collect and use information. By using the Services you consent to those practices.</p>
        <h2>12. Disclaimers &amp; Warranties</h2>
        <p>The Services are provided "as is" and we expressly disclaim all warranties to the fullest extent permitted by law. We do not guarantee that the Services will be uninterrupted or error‑free.</p>
        <h2>13. Limitation of Liability</h2>
        <p>To the maximum extent permitted by law, Aetia will not be liable for indirect, incidental, special, consequential, or punitive damages, or for loss of profits, revenue, or data arising from your use of the Services.</p>
        <h2>14. Indemnification</h2>
        <p>You agree to indemnify and hold Aetia harmless from claims, damages, losses, liabilities, costs, and expenses arising from your violation of these Terms or your use of the Services.</p>
        <h2>15. Termination</h2>
        <p>We may suspend or terminate your access for violations of these Terms or for any other reason with notice. Upon termination, your right to use the Services ends and we may delete data in accordance with our retention policies.</p>
        <h2>16. Governing Law</h2>
        <p>These Terms are governed by the laws of the jurisdiction in which Aetia operates, without regard to conflict of laws principles. Disputes may be resolved in courts located in that jurisdiction.</p>
        <h2>17. Changes</h2>
        <p>We may modify these Terms. When we do, we will post the revised Terms and update the "Effective date". Continued use of the Services after changes constitutes acceptance.</p>
        <h2>18. Contact</h2>
        <p>If you have questions about these Terms, please contact us via our <a href="contact.php">Contact</a> page.</p>
    </div>
</section>
<?php
$content = ob_get_clean();
include 'layout.php';
?>