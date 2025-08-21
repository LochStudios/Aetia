<?php
// contact.php - Contact page for Aetia Talent Agency
session_start();

require_once __DIR__ . '/models/Contact.php';
require_once __DIR__ . '/includes/FormTokenManager.php';

// Cloudflare Turnstile keys: load from the secure web-config file
$turnstile_site_key = null;
$turnstile_secret_key = null;
$turnstile_explicit = false; // default
$turnstileConfigPath = '/home/aetiacom/web-config/turnstile.php';
if (file_exists($turnstileConfigPath)) {
    $cfg = include $turnstileConfigPath;
    if (is_array($cfg)) {
        $turnstile_site_key = !empty($cfg['site_key']) ? $cfg['site_key'] : null;
        $turnstile_secret_key = !empty($cfg['secret_key']) ? $cfg['secret_key'] : null;
        $turnstile_explicit = !empty($cfg['explicit']);
        $turnstile_execution = !empty($cfg['execution']) ? $cfg['execution'] : 'render';
    }
}

// Explicit rendering toggle (config only — no environment variables)
if (isset($cfg) && is_array($cfg)) {
    $turnstile_explicit = !empty($cfg['explicit']);
    // Execution mode: 'render' (default) or 'execute'
    $turnstile_execution = !empty($cfg['execution']) ? $cfg['execution'] : ($turnstile_execution ?? 'render');
} else {
    // Defaults when no config file is present
    $turnstile_explicit = false;
    $turnstile_execution = $turnstile_execution ?? 'render';
}

/** Verify Cloudflare Turnstile response token server-side. */
function verifyTurnstileResponse($token, $secret, $remoteIp = null, $idempotencyKey = null) {
    if (empty($secret) || empty($token)) {
        return null;
    }
    // Cluster-wide single-use enforcement: check DB for used token hash if available
    try {
        // Try to load Database class and check turnstile_verifications table
        if (class_exists('Database')) {
            $db = new Database();
            $mysqli = $db->getConnection();
            $tokenHash = hash('sha256', $token);
            $checkStmt = $mysqli->prepare("SELECT id, success, created_at FROM turnstile_verifications WHERE token_hash = ? LIMIT 1");
            if ($checkStmt) {
                $checkStmt->bind_param('s', $tokenHash);
                $checkStmt->execute();
                $res = $checkStmt->get_result();
                if ($res && $row = $res->fetch_assoc()) {
                    // Token already verified before - treat as replay
                    error_log('Turnstile token already verified (replay): ' . $tokenHash);
                    return ['success' => false, 'error' => 'replayed_token'];
                }
                $checkStmt->close();
            }
        }
    } catch (Exception $e) {
        // If DB access fails, just continue with siteverify; we keep server tolerant
        error_log('Turnstile DB check failed: ' . $e->getMessage());
    }
    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $payload = [
        'secret' => $secret,
        'response' => $token
    ];
    if (!empty($remoteIp)) {
        $payload['remoteip'] = $remoteIp;
    }
    if (!empty($idempotencyKey)) {
        $payload['idempotency_key'] = $idempotencyKey;
    }
    $postData = http_build_query($payload);
    // Prefer cURL for POST
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            error_log('Turnstile verification cURL error: ' . $err);
            return ['success' => false, 'error' => 'curl_error'];
        }
    } else {
        $opts = ['http' => [
            'method'  => 'POST',
            'header'  => 'Content-type: application/x-www-form-urlencoded',
            'content' => $postData,
            'timeout' => 5
        ]];
        $context  = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            error_log('Turnstile verification failed: unable to contact siteverify endpoint');
            return ['success' => false, 'error' => 'network_error'];
        }
    }
    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return ['success' => false, 'error' => 'invalid_response'];
    }

    // Persist verification result if DB available
    try {
            if (class_exists('Database')) {
                $db = new Database();
                $mysqli = $db->getConnection();
                $tokenHash = hash('sha256', $token);
                $responseJson = $mysqli->real_escape_string(json_encode($decoded));
                $successInt = !empty($decoded['success']) ? 1 : 0;
                $idempKey = $idempotencyKey;
                $action = !empty($decoded['action']) ? $decoded['action'] : null;
                $cdata = !empty($decoded['cdata']) ? $decoded['cdata'] : null;
                $ephemeral = null;
                if (!empty($decoded['metadata']) && is_array($decoded['metadata']) && !empty($decoded['metadata']['ephemeral_id'])) {
                    $ephemeral = $decoded['metadata']['ephemeral_id'];
                }
                $hostname = !empty($decoded['hostname']) ? $decoded['hostname'] : null;
                $challengeTs = !empty($decoded['challenge_ts']) ? date('Y-m-d H:i:s', strtotime($decoded['challenge_ts'])) : null;
                $errorCodesJson = null;
                if (!empty($decoded['error-codes']) || !empty($decoded['error_codes']) || !empty($decoded['error-codes'])) {
                    $ec = !empty($decoded['error-codes']) ? $decoded['error-codes'] : ($decoded['error_codes'] ?? null);
                    $errorCodesJson = $mysqli->real_escape_string(json_encode($ec));
                }

                // Insert record (uniq on token_hash prevents duplicates)
                $insertSql = "INSERT INTO turnstile_verifications (token_hash, idempotency_key, remoteip, success, response_json, action, cdata, ephemeral_id, hostname, challenge_ts, error_codes) VALUES ('{$tokenHash}', ?, ?, {$successInt}, '{$responseJson}', ?, ?, ?, ?, ?, ?)";
                $stmt = $mysqli->prepare($insertSql);
                if ($stmt) {
                    $stmt->bind_param('ssssssssss', $idempKey, $remoteIp, $action, $cdata, $ephemeral, $hostname, $challengeTs, $errorCodesJson);
                    $stmt->execute();
                    $stmt->close();
                }
            }
    } catch (Exception $e) {
        error_log('Turnstile DB insert failed: ' . $e->getMessage());
    }
    return $decoded;
}

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
        // Get client info for spam prevention (respect Cloudflare and proxy headers)
        $ipAddress = null;
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ipAddress = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // X-Forwarded-For may contain a comma-separated list; take the first
            $xff = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ipAddress = trim($xff[0]);
        } else {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        }
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    // Cloudflare Turnstile response (if widget present)
    $turnstileToken = $_POST['cf-turnstile-response'] ?? '';
    // Optional idempotency key for siteverify retries (client may provide)
    $turnstileIdempotency = $_POST['turnstile_idempotency_key'] ?? null;
        // If Turnstile secret is configured, require verification success
        if (!empty($turnstile_site_key) && !empty($turnstile_secret_key)) {
            if (empty($turnstileToken)) {
                $error = 'Please complete the security check.';
            } else {
                $turnstileResult = verifyTurnstileResponse($turnstileToken, $turnstile_secret_key, $ipAddress, $turnstileIdempotency);
                if (!is_array($turnstileResult)) {
                    $error = 'Security verification failed. Please try again.';
                } elseif (empty($turnstileResult['success'])) {
                    // Normalize error codes from different possible keys
                    $codes = [];
                    if (!empty($turnstileResult['error-codes']) && is_array($turnstileResult['error-codes'])) {
                        $codes = $turnstileResult['error-codes'];
                    } elseif (!empty($turnstileResult['error_codes']) && is_array($turnstileResult['error_codes'])) {
                        $codes = $turnstileResult['error_codes'];
                    } elseif (!empty($turnstileResult['"error-codes"']) && is_array($turnstileResult['"error-codes"'])) {
                        $codes = $turnstileResult['"error-codes"'];
                    }
                    // Log for diagnostics
                    if (!empty($codes)) {
                        error_log('Turnstile siteverify error-codes: ' . json_encode($codes));
                    }
                    // Map common error codes to user-friendly messages
                    $userMessage = 'Security verification failed. Please try again.';
                    if (in_array('missing-input-response', $codes, true)) {
                        $userMessage = 'Please complete the security check.';
                    } elseif (in_array('invalid-input-response', $codes, true)) {
                        $userMessage = 'Security token invalid or expired. Please try again.';
                    } elseif (in_array('timeout-or-duplicate', $codes, true)) {
                        $userMessage = 'Security token already used. Please try again.';
                    } elseif (in_array('missing-input-secret', $codes, true) || in_array('invalid-input-secret', $codes, true)) {
                        // Configuration issue - log full details for admin
                        error_log('Turnstile secret configuration error: ' . json_encode($codes));
                        $userMessage = 'Security verification configuration error. Contact the site administrator.';
                    } elseif (in_array('internal-error', $codes, true)) {
                        $userMessage = 'Security verification service error. Please try again.';
                    }
                    $error = $userMessage;
                } else {
                    // Enforce token expiry (5 minutes) and single-use per session
                    $now = time();
                    $challengeTs = $turnstileResult['challenge_ts'] ?? null;
                    if ($challengeTs) {
                        $ts = strtotime($challengeTs);
                        if ($ts === false || ($now - $ts) > 300) {
                            error_log('Turnstile token expired: challenge_ts=' . $challengeTs);
                            $error = 'Security verification expired. Please try again.';
                        }
                    }
                    // Check session-stored used tokens to prevent replay
                    if (empty($error)) {
                        if (!isset($_SESSION['turnstile_tokens']) || !is_array($_SESSION['turnstile_tokens'])) {
                            $_SESSION['turnstile_tokens'] = [];
                        }
                        // Cleanup tokens older than 5 minutes
                        foreach ($_SESSION['turnstile_tokens'] as $tkn => $tstamp) {
                            if (($now - $tstamp) > 300) {
                                unset($_SESSION['turnstile_tokens'][$tkn]);
                            }
                        }
                        if (isset($_SESSION['turnstile_tokens'][$turnstileToken])) {
                            error_log('Turnstile token replay detected: ' . $turnstileToken);
                            $error = 'Security token already used. Please try again.';
                        } else {
                            // Mark token as used in this session
                            $_SESSION['turnstile_tokens'][$turnstileToken] = $now;
                        }
                    }
                    // If still ok, check hostname matches (if present)
                    if (empty($error) && !empty($turnstileResult['hostname'])) {
                        $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
                        if (stripos($turnstileResult['hostname'], $currentHost) === false && stripos($currentHost, $turnstileResult['hostname']) === false) {
                            error_log('Turnstile hostname mismatch: token_hostname=' . $turnstileResult['hostname'] . ' current_host=' . $currentHost);
                            $error = 'Security verification failed (hostname mismatch). Please try again.';
                        }
                    }
                }
            }
        } else {
            // If site key present but secret missing, include the widget but don't enforce server verification.
            if (!empty($turnstile_site_key) && empty($turnstile_secret_key)) {
                error_log('Turnstile site key present but secret key missing; server-side verification skipped.');
            }
        }
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
            <?php if (!empty($turnstile_site_key)): ?>
            <div class="field">
                <div class="control">
                    <!-- Cloudflare Turnstile widget -->
                    <?php if (!empty($turnstile_explicit)): ?>
                        <div id="turnstile-container" class="cf-turnstile"></div>
                    <?php else: ?>
                        <div class="cf-turnstile"
                             data-sitekey="<?= htmlspecialchars($turnstile_site_key) ?>"
                             data-theme="auto"
                             data-size="flexible"
                             data-action="contact"
                             data-callback="onTurnstileSuccess"
                             data-error-callback="onTurnstileError"
                             data-expired-callback="onTurnstileExpired"
                        ></div>
                    <?php endif; ?>
                    <!-- Hidden token field for explicit mode (explicit rendering will set this) -->
                    <?php if (!empty($turnstile_explicit)): ?>
                        <input type="hidden" name="cf-turnstile-response" id="cf-turnstile-response" value="">
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="field">
                <div class="control">
                    <button id="contact-submit" class="button is-link" type="submit" <?php if (!empty($turnstile_site_key)) echo 'disabled'; ?> >
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
// Turnstile callbacks (only declared when widget is present)
function onTurnstileSuccess(token) {
    try {
        var submit = document.getElementById('contact-submit');
        if (submit) submit.disabled = false;
        console.log('Turnstile success');
    } catch (e) {console.error(e)}
}
function onTurnstileError(err) {
    try {
        var submit = document.getElementById('contact-submit');
        if (submit) submit.disabled = true;
        console.error('Turnstile error', err);
    } catch (e) {console.error(e)}
}
function onTurnstileExpired() {
    try {
        var submit = document.getElementById('contact-submit');
        if (submit) submit.disabled = true;
        console.warn('Turnstile token expired');
    } catch (e) {console.error(e)}
}
</script>
<?php endif; ?>

<?php if (!empty($turnstile_site_key)): ?>
<!-- Cloudflare Turnstile client script -->
<?php if (!empty($turnstile_explicit)): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" defer></script>
<?php else: ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
<?php endif; ?>

<?php if (!empty($turnstile_site_key) && !empty($turnstile_explicit)): ?>
<script>
// Programmatic render for explicit Turnstile
window.addEventListener('load', function() {
    try {
        if (typeof turnstile !== 'undefined') {
            // Render into the first element with class cf-turnstile inside the form
            var container = document.querySelector('#contact-form .cf-turnstile');
            if (container) {
                window._turnstileWidgetId = turnstile.render(container, {
                    sitekey: '<?= htmlspecialchars($turnstile_site_key) ?>',
                    theme: 'auto',
                    size: 'flexible',
                    action: 'contact',
                    execution: '<?= htmlspecialchars($turnstile_execution ?? 'render') ?>',
                    callback: function(token) {
                        // place token into hidden input
                        var h = document.getElementById('cf-turnstile-response');
                        if (h) h.value = token;
                        onTurnstileSuccess(token);
                    },
                    'error-callback': onTurnstileError,
                    'expired-callback': onTurnstileExpired
                });
            }
        }
    } catch (e) { console.error('Turnstile explicit render error', e); }
});
</script>
<?php endif; ?>

<?php if (!empty($turnstile_site_key) && !empty($turnstile_explicit) && (!empty($turnstile_execution) && $turnstile_execution === 'execute')): ?>
<script>
// If execution mode is 'execute', override form submit to run the challenge first
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('contact-form');
    var submit = document.getElementById('contact-submit');
    if (!form || !submit) return;
    form.addEventListener('submit', function(e) {
        // If token already present, allow submit
        var tokenField = document.getElementById('cf-turnstile-response');
        if (tokenField && tokenField.value) {
            return; // allow submit
        }
        // Prevent submit and execute the challenge
        e.preventDefault();
        submit.disabled = true;
        try {
            if (typeof turnstile !== 'undefined') {
                // execute the widget (by container id)
                turnstile.execute('#turnstile-container');
            } else {
                // fallback: re-enable submit after timeout
                setTimeout(function(){ submit.disabled = false; }, 3000);
            }
        } catch (err) {
            console.error('Turnstile execute error', err);
            submit.disabled = false;
        }
    });
});
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
include 'layout.php';
?>