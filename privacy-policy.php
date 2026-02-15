<?php
// privacy-policy.php
session_start();

$pageTitle = 'Privacy Policy | Aetia Talent Agency';
ob_start();
?>
<section class="section">
    <div class="container content">
        <h1 class="title">Privacy Policy</h1>
        <p><strong>Effective date:</strong> February 15, 2026</p>
        <h2>Overview</h2>
        <p>This Privacy Policy explains how Aetia Talent Agency ("Aetia", "we", "us", "our") collects, uses, shares, and protects personal information when you use our websites, services, and APIs. It also explains your choices and legal rights regarding your personal information.</p>
        <h2>Information We Collect</h2>
        <ul>
            <li><strong>Account information:</strong> name, email, username, contact details, profile and industry-related information.</li>
            <li><strong>Communications &amp; uploads:</strong> messages, documents, images, and files you or others submit.</li>
            <li><strong>Payment &amp; billing data:</strong> transactional data and payment method details collected and processed by our third‑party payment processors.</li>
            <li><strong>Usage &amp; device data:</strong> IP addresses, device identifiers, browser and operating system, logs, cookies, and other telemetry.</li>
            <li><strong>Third‑party data:</strong> profile information returned by OAuth providers (e.g., Google, Discord, Twitch) when you connect accounts.</li>
            <li><strong>Communications with us:</strong> support requests, feedback, and other correspondence.</li>
        </ul>
        <h2>How We Use Information</h2>
        <p>We process personal data to provide and improve our Services, authenticate users, manage accounts, process payments, communicate about our Services, detect and prevent fraud and abuse, comply with legal obligations, and perform analytics and product development. Where required by law, we rely on your consent or other lawful bases such as contract performance, legitimate interests, or legal obligations.</p>
        <h2>Sharing &amp; Disclosure</h2>
        <p>We may share information with service providers who perform services on our behalf (hosting, payments, email delivery, analytics), with Clients or Talent where needed to provide the Services, with law enforcement or regulators when required by law, and to enforce our rights. We do not sell personal data for money. We may share aggregated or de‑identified data that does not reasonably identify an individual.</p>
        <h2>Cookies &amp; Tracking</h2>
        <p>We and our partners use cookies and similar technologies to provide functionality, remember preferences, enable security features, and analyze usage. You can manage cookie preferences through your browser, though blocking cookies may affect functionality. For targeted advertising or analytics provided by third parties, review those providers' privacy notices for opt‑out options.</p>
        <h2>Legal Bases &amp; International Transfers</h2>
        <p>When processing personal data of individuals in jurisdictions such as the European Economic Area, we rely on lawful bases like consent, contract performance, or legitimate interests. We may transfer data to countries outside your jurisdiction; when we do, we implement appropriate safeguards such as Standard Contractual Clauses or rely on adequacy decisions where applicable.</p>
        <h2>Your Rights</h2>
        <p>Depending on your jurisdiction, you may have rights including access, correction, deletion, restriction, portability, and objection to certain processing. California residents may have additional rights under the CCPA/CPRA. To exercise your rights, contact us via our <a href="contact.php">Contact</a> page. We will respond in accordance with applicable law and may need to verify your identity.</p>
        <h2>Security &amp; Data Retention</h2>
        <p>We implement administrative, technical, and physical safeguards to protect personal information. While we strive to protect your data, no system is completely secure. We retain personal data as necessary to provide the Services, comply with legal obligations, resolve disputes, and enforce agreements. Retention periods vary by data type and purpose.</p>
        <h2>Third‑Party Services, Integrations &amp; OAuth</h2>
        <p>When you connect third‑party services (e.g., Google, Discord, Twitch), we receive and store information you authorize. Those services are governed by their own privacy policies and terms. Review them before connecting. We are not responsible for their practices.</p>
        <h2>Children</h2>
        <p>Our Services are not directed to children under 13. We do not knowingly collect personal data from children under applicable age thresholds. If you believe we have collected such data, contact us and we will take steps to remove it.</p>
        <h2>Data Protection Officer &amp; Contact</h2>
        <p>For privacy inquiries or to exercise your data rights, please contact us via our <a href="contact.php">Contact</a> page. We will respond in accordance with applicable law.</p>
        <h2>Changes to this Policy</h2>
        <p>We may update this Privacy Policy. When we do, we will post the revised Policy with an updated effective date. Continued use after changes indicates acceptance.</p>
    </div>
</section>
<?php
$content = ob_get_clean();
include 'layout.php';
?>