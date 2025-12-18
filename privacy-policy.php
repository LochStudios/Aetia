<?php
// privacy-policy.php
session_start();

$pageTitle = 'Privacy Policy | Aetia Talent Agency';
ob_start();
?>
<section class="section">
    <div class="container content">
        <h1 class="title">Privacy Policy</h1>
        <p><strong>Effective date:</strong> December 18, 2025</p>
        <h2>Overview</h2>
        <p>This Privacy Policy explains how Aetia Talent Agency ("Aetia", "we", "us", "our") collects, uses, shares, and protects personal information when you use our websites, services, and APIs.</p>
        <h2>Information We Collect</h2>
        <ul>
            <li><strong>Account information:</strong> name, email, username, profile information.</li>
            <li><strong>Messages and uploads:</strong> messages you submit, documents, images, attachments, and other files.</li>
            <li><strong>Payment & billing data:</strong> transactional data and payment method details processed by third‑party payment processors.</li>
            <li><strong>Usage data:</strong> logs, IP addresses, device and browser information, activity within the Services.</li>
            <li><strong>Third‑party data:</strong> information from OAuth providers (e.g., Google, Discord, Twitch) if you connect those accounts.</li>
        </ul>
        <h2>How We Use Information</h2>
        <p>We use personal data to provide and improve the Services, authenticate users, process payments, communicate with you, detect and prevent fraud, comply with legal obligations, and for analytics and product development.</p>
        <h2>Sharing &amp; Disclosure</h2>
        <p>We may share information with service providers who perform services on our behalf (hosting, payments, email delivery, analytics), with law enforcement or to comply with legal processes, and to protect our rights. We don’t sell personal data for money.</p>
        <h2>Cookies &amp; Tracking</h2>
        <p>We use cookies and similar technologies to remember preferences, enable features, and analyze usage. We may also use third‑party analytics. You can manage cookie settings in your browser, but some features may require cookies.</p>
        <h2>File Uploads &amp; Public Content</h2>
        <p>Files and messages you upload are stored to provide the Services. If you make content public or share it with others, that content may be visible to others and we are not responsible for content you choose to share publicly.</p>
        <h2>Data Retention</h2>
        <p>We retain personal data as necessary to provide the Services, comply with legal obligations, resolve disputes, and enforce our agreements. Retention periods vary based on the type of data and business needs.</p>
        <h2>Security</h2>
        <p>We implement administrative, technical, and physical safeguards designed to protect personal information. However, no system is completely secure; we cannot guarantee absolute security.</p>
        <h2>Your Rights</h2>
        <p>Depending on your jurisdiction, you may have rights including access, correction, deletion, portability, and objection to certain processing. To exercise these rights, contact us via our <a href="contact.php">Contact</a> page. We will respond in accordance with applicable law.</p>
        <h2>International Transfers</h2>
        <p>We may transfer and store information in servers located outside your country. Where required, we implement safeguards to protect personal data when transferred internationally.</p>
        <h2>Children</h2>
        <p>Our Services are not directed to children under 13 and we do not knowingly collect personal data from children. If you believe we have collected data from a child, contact us to request deletion.</p>
        <h2>Changes to this Policy</h2>
        <p>We may update this Privacy Policy. We will post changes here with an updated effective date. Continued use after changes indicates acceptance.</p>
        <h2>Contact</h2>
        <p>For questions, data requests, or privacy concerns, please reach us through our <a href="contact.php">Contact</a> page.</p>
    </div>
</section>
<?php
$content = ob_get_clean();
include 'layout.php';
?>