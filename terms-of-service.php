<?php
// terms-of-service.php
session_start();

$pageTitle = 'Terms of Service | Aetia Talent Agency';
ob_start();
?>
<section class="section">
    <div class="container content">
        <h1 class="title">Terms of Service</h1>
        <p><strong>Effective date:</strong> February 15, 2026</p>
        <h2>1. Introduction &amp; Scope</h2>
        <p>These Terms of Service ("Terms") govern your access to and use of the websites, applications, APIs, and other services provided by Aetia Talent Agency ("Aetia", "we", "us", "our"). By accessing or using our Services, you agree to these Terms. If you do not agree, do not use the Services.</p>
        <h2>2. Definitions</h2>
        <p><strong>"Client"</strong> means a person or entity that engages Aetia for talent-related services. <strong>"Talent"</strong> means individuals represented or supported by Aetia. <strong>"User"</strong> means anyone using the Services, including Clients and Talent.</p>
        <h2>3. Services</h2>
        <p>Aetia provides talent representation and management tools including messaging intake and routing, secure document handling, contract management, profile hosting, analytics, and integrations with third‑party platforms. Specific service terms, fees, and deliverables may be set out in separate agreements or order forms between Aetia and a Client or Talent.</p>
        <h2>4. Eligibility</h2>
        <p>You must be at least 18 years old and able to form a binding contract to use the Services, unless otherwise permitted by law or a parent/guardian account is used where legally required.</p>
        <h2>5. Accounts &amp; Access</h2>
        <p>Some features require an account. You are responsible for maintaining the confidentiality of your account credentials and for all activity under your account. You agree to provide accurate information and to keep it updated. Notify us promptly of any unauthorized use.</p>
        <h2>6. Payments &amp; Fees</h2>
        <p>Paid services require payment of fees as described in your plan, invoice, or separate written agreement. We may use third‑party payment processors. All fees are non‑refundable except as expressly set out in a contract or required by law. You are responsible for applicable taxes. We may suspend access for unpaid accounts.</p>
        <h2>7. Use Restrictions &amp; Acceptable Use</h2>
        <p>You agree not to: (a) use the Services for unlawful or harmful purposes; (b) attempt to access other accounts or bypass security; (c) upload content that infringes others' rights; (d) interfere with the operation of the Services; or (e) use automated means to access or scrape the Services without permission. We may investigate and take action for violations.</p>
        <h2>8. Content, Uploads &amp; Licenses</h2>
        <p>Users retain ownership of content they submit. By submitting content you grant Aetia a limited, non‑exclusive, worldwide, royalty‑free license to store, reproduce, display, transmit, and process that content as necessary to provide the Services. You represent and warrant you have the rights to submit the content and that it does not violate these Terms.</p>
        <h2>9. Confidentiality &amp; Sensitive Data</h2>
        <p>Certain information shared through the Services may be confidential. You will not disclose confidential information except as permitted by contract, consent, or law. Do not upload sensitive personal data unless explicitly requested and protected under a separate agreement.</p>
        <h2>10. Third‑Party Services &amp; Integrations</h2>
        <p>We may integrate with third‑party services (e.g., OAuth providers, payment processors). Your use of those third parties is subject to their terms and privacy policies. We are not responsible for their practices or outages. You should review third‑party terms before connecting accounts.</p>
        <h2>11. Intellectual Property</h2>
        <p>All Aetia trademarks, service names, logos, and software are Aetia's property. You may not use our intellectual property without prior written permission. Users remain owners of their pre‑existing IP and grants to Aetia do not transfer ownership unless otherwise agreed in writing.</p>
        <h2>12. Warranties &amp; Disclaimers</h2>
        <p>The Services are provided "as is" and "as available" without warranties of any kind. To the fullest extent permitted by law, Aetia disclaims all warranties, whether express, implied, statutory or otherwise, including warranties of merchantability, fitness for a particular purpose, accuracy, and non‑infringement.</p>
        <h2>13. Limitation of Liability</h2>
        <p>To the maximum extent permitted by law, in no event will Aetia be liable for indirect, incidental, special, consequential, or punitive damages, or for loss of profits, revenue, business, data, or goodwill arising from or related to these Terms or your use of the Services. Our aggregate liability for direct damages will not exceed the fees paid by you to Aetia in the 12 months preceding the claim, or $100 if no fees were paid, unless otherwise agreed in writing.</p>
        <h2>14. Indemnification</h2>
        <p>You agree to indemnify and hold Aetia, its affiliates, and personnel harmless from claims arising out of your breach of these Terms, your violation of law, or your content uploaded to the Services.</p>
        <h2>15. Termination &amp; Suspension</h2>
        <p>We may suspend or terminate accounts for breaches, nonpayment, lawful requests, or other business reasons. Termination does not relieve you of obligations accrued prior to termination. Upon termination we may delete or retain data consistent with our retention policies and legal obligations.</p>
        <h2>16. Governing Law &amp; Dispute Resolution</h2>
        <p>These Terms are governed by the laws of the jurisdiction in which Aetia operates, without regard to conflict of law principles. Disputes may be resolved through negotiation, and if unresolved, in the courts or by binding arbitration as specified in a se
        <h2>17. Export &amp; Compliance</h2>
        <p>You will comply with applicable export laws and not use the Services in violation of those laws.</p>
        <h2>18. Changes to These Terms</h2>
        <p>We may modify these Terms from time to time. If changes are material, we will provide notice by posting an updated Effective Date and, where required, additional notice. Continued use after changes constitutes acceptance.</p>
        <h2>19. Contact</h2>
        <p>For questions about these Terms or to exercise rights, please contact us via our <a href="contact.php">Contact</a> page.</p>
    </div>
</section>
<?php
$content = ob_get_clean();
include 'layout.php';
?>