<?php
// contact.php - Contact page for Aetia Talent Agency
session_start();

require_once __DIR__ . '/models/Contact.php';
require_once __DIR__ . '/includes/FormTokenManager.php';

$contactModel = new Contact();
$message = '';
$error = '';
$showForm = true;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form token to prevent CSRF and duplicate submissions
    $formName = 'contact_form';
    $formToken = $_POST['form_token'] ?? '';
    
    if (empty($formToken)) {
        $error = 'Invalid form submission. Please refresh the page and try again.';
    } elseif (!FormTokenManager::validateToken($formName, $formToken)) {
        $error = 'This form has already been submitted or has expired. Please refresh the page and try again.';
    } elseif (FormTokenManager::isRecentSubmission($formName)) {
        $error = 'Please wait a moment before submitting again.';
    } else {
        // Process form submission
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $messageText = trim($_POST['message'] ?? '');
        
        // Get client info for spam prevention
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $result = $contactModel->submitContactForm($name, $email, $subject, $messageText, $ipAddress, $userAgent);
        
        if ($result['success']) {
            $message = $result['message'];
            $showForm = false; // Hide form after successful submission
            FormTokenManager::recordSubmission($formName); // Prevent duplicate submissions
        } else {
            $error = $result['message'];
        }
    }
}

$pageTitle = 'Contact | Aetia Talent Agency';
ob_start();
?>
<div class="card mt-6" style="width:100%;max-width:none;">
    <div class="card-content">
        <h2 class="title is-3 mb-4"><span class="icon has-text-link"><i class="fas fa-envelope"></i></span> Contact Us</h2>
        
        <?php if ($message): ?>
        <div class="notification is-success is-light mb-4">
            <span class="icon-text">
                <span class="icon"><i class="fas fa-check-circle"></i></span>
                <span><?= htmlspecialchars($message) ?></span>
            </span>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="notification is-danger is-light mb-4">
            <span class="icon-text">
                <span class="icon"><i class="fas fa-exclamation-triangle"></i></span>
                <span><?= htmlspecialchars($error) ?></span>
            </span>
        </div>
        <?php endif; ?>
        
        <?php if ($showForm): ?>
        <div class="notification is-info is-light mb-4">
            <span class="icon-text">
                <span class="icon"><i class="fas fa-info-circle"></i></span>
                <span><strong>Get in Touch:</strong> We'd love to hear from you! Whether you have questions about our services, want to discuss a collaboration, or need support, please don't hesitate to reach out.</span>
            </span><br>
            You can also email us directly at <a href="mailto:talent@aetia.com.au">talent@aetia.com.au</a>.
        </div>
        
        <form method="POST" id="contact-form">
            <input type="hidden" name="form_token" value="<?= FormTokenManager::generateToken('contact_form') ?>">
            
            <div class="field">
                <label class="label">Name <span class="has-text-danger">*</span></label>
                <div class="control">
                    <input class="input" type="text" name="name" required maxlength="100" 
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                           placeholder="Your full name">
                </div>
            </div>
            
            <div class="field">
                <label class="label">Email <span class="has-text-danger">*</span></label>
                <div class="control">
                    <input class="input" type="email" name="email" required maxlength="100"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           placeholder="your.email@example.com">
                </div>
            </div>
            
            <div class="field">
                <label class="label">Subject</label>
                <div class="control">
                    <input class="input" type="text" name="subject" maxlength="255"
                           value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>"
                           placeholder="Brief description of your inquiry">
                </div>
            </div>
            
            <div class="field">
                <label class="label">Message <span class="has-text-danger">*</span></label>
                <div class="control">
                    <textarea class="textarea" name="message" required rows="6"
                              placeholder="Please provide details about your inquiry or how we can help you..."><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                </div>
            </div>
            
            <div class="field">
                <div class="control">
                    <button class="button is-link" type="submit">
                        <span class="icon"><i class="fas fa-paper-plane"></i></span>
                        <span>Send Message</span>
                    </button>
                </div>
            </div>
        </form>
        
        <?php else: ?>
        <div class="notification is-success is-light">
            <p><strong>Thank you for your message!</strong></p>
            <p>We have received your inquiry and will get back to you as soon as possible.</p>
            <p>If you need immediate assistance, please email us directly at <a href="mailto:talent@aetia.com.au">talent@aetia.com.au</a>.</p>
        </div>
        
        <div class="buttons">
            <a href="/" class="button is-light">
                <span class="icon"><i class="fas fa-home"></i></span>
                <span>Return to Homepage</span>
            </a>
            <a href="/services.php" class="button is-info">
                <span class="icon"><i class="fas fa-briefcase"></i></span>
                <span>View Our Services</span>
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($showForm): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('contact-form');
    const submitButton = form.querySelector('button[type="submit"]');
    
    form.addEventListener('submit', function(e) {
        // Basic client-side validation
        const name = form.querySelector('input[name="name"]').value.trim();
        const email = form.querySelector('input[name="email"]').value.trim();
        const message = form.querySelector('textarea[name="message"]').value.trim();
        
        if (!name || !email || !message) {
            e.preventDefault();
            alert('Please fill in all required fields.');
            return;
        }
        
        // Disable submit button to prevent double submission
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="icon"><i class="fas fa-spinner fa-pulse"></i></span><span>Sending...</span>';
        
        // Re-enable after a timeout (in case of form validation errors)
        setTimeout(function() {
            submitButton.disabled = false;
            submitButton.innerHTML = '<span class="icon"><i class="fas fa-paper-plane"></i></span><span>Send Message</span>';
        }, 10000);
    });
});
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
include 'layout.php';
?>