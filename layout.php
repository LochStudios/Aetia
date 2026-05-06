<?php
// layout.php - Main layout template for Aetia Talent Agency
// Custom dark theme — Bulma removed. FontAwesome v7 + SweetAlert2 only.

// Set UTF-8 content type header
header('Content-Type: text/html; charset=UTF-8');

// Include timezone utilities
require_once __DIR__ . '/includes/timezone.php';

// Handle timezone setting from JavaScript
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_timezone') {
    if (isset($_POST['timezone'])) {
        $_SESSION['user_timezone'] = $_POST['timezone'];
    }
    exit; // Don't render the page for AJAX requests
}

if (!isset($pageTitle)) $pageTitle = 'Aetia Talent Agency';
if (!isset($content)) $content = '';

// Cache-busting via file modification time (so users always pick up the latest theme).
$aetia_css_path = __DIR__ . '/css/custom.css';
$aetia_js_path  = __DIR__ . '/js/aetia-ui.js';
$aetia_css_v = file_exists($aetia_css_path) ? filemtime($aetia_css_path) : time();
$aetia_js_v  = file_exists($aetia_js_path)  ? filemtime($aetia_js_path)  : time();

// Pull any flash messages from session for SweetAlert2 toast display
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
$flashInfo    = $_SESSION['flash_info'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['flash_info']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark" style="height:100%;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#07090f">
    <meta name="color-scheme" content="dark">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="icon" type="image/x-icon" href="/img/logo.ico">
    <!-- Font Awesome v7 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom Theme -->
    <link rel="stylesheet" href="/css/custom.css?v=<?= $aetia_css_v ?>">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar" role="navigation" aria-label="main navigation">
        <div class="navbar-brand">
            <a class="navbar-item" href="/index.php" aria-label="Aetia home">
                <img src="/img/logo.png" alt="Aetia Talent Agency" style="max-height: 2.4rem;">
            </a>
            <button type="button" class="navbar-burger" aria-label="menu" aria-expanded="false" data-target="navbarMain">
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
            </button>
        </div>
        <div id="navbarMain" class="navbar-menu">
            <div class="navbar-start">
                <a class="navbar-item" href="/index.php"><i class="fas fa-home"></i> Home</a>
                <a class="navbar-item" href="/about.php"><i class="fas fa-circle-info"></i> About</a>
                <a class="navbar-item" href="/services.php"><i class="fas fa-stars"></i> Services</a>
                <a class="navbar-item" href="/pricing.php"><i class="fas fa-tag"></i> Pricing</a>
                <a class="navbar-item" href="/contact.php"><i class="fas fa-envelope"></i> Contact</a>
                <?php if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true): ?>
                <span class="navbar-divider" aria-hidden="true"></span>
                <a class="navbar-item" href="/messages.php"><i class="fas fa-comments"></i> Messages</a>
                <a class="navbar-item" href="/documents.php"><i class="fas fa-file-lines"></i> Documents</a>
                <a class="navbar-item" href="/billing.php"><i class="fas fa-file-invoice-dollar"></i> Billing</a>
                <a class="navbar-item" href="/contracts.php"><i class="fas fa-file-signature"></i> Contracts</a>
                <?php endif; ?>
            </div>
            <div class="navbar-end">
                <?php if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true):
                    $showAdminLink = false;
                    if (isset($_SESSION['user_id'])) {
                        require_once __DIR__ . '/models/User.php';
                        $userModel = new User();
                        $showAdminLink = $userModel->isUserAdmin($_SESSION['user_id']);
                    }
                ?>
                <div class="dropdown is-right is-hoverable">
                    <div class="dropdown-trigger">
                        <button type="button" class="button is-light is-small" aria-haspopup="true" aria-controls="user-dropdown-menu">
                            <?php if (!empty($_SESSION['social_data']['profile_image_url'])): ?>
                                <img src="<?= htmlspecialchars($_SESSION['social_data']['profile_image_url']) ?>" alt="" style="width:20px;height:20px;border-radius:50%;">
                            <?php else: ?>
                                <span class="icon"><i class="fas fa-user"></i></span>
                            <?php endif; ?>
                            <span><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
                            <span class="icon is-small"><i class="fas fa-chevron-down"></i></span>
                        </button>
                    </div>
                    <div class="dropdown-menu" id="user-dropdown-menu" role="menu">
                        <div class="dropdown-content">
                            <div class="dropdown-item is-static">
                                <span class="is-size-7 has-text-grey">Logged in via <?= htmlspecialchars(ucfirst($_SESSION['account_type'] ?? 'manual')) ?></span>
                            </div>
                            <hr class="dropdown-divider">
                            <a href="/profile.php" class="dropdown-item"><i class="fas fa-user-gear"></i> Profile Settings</a>
                            <a href="/logout.php" class="dropdown-item"><i class="fas fa-right-from-bracket"></i> Logout</a>
                            <?php if ($showAdminLink): ?>
                            <hr class="dropdown-divider">
                            <a href="/admin/" class="dropdown-item"><i class="fas fa-users-gear"></i> Admin Panel</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <a class="button is-primary is-small" href="/login.php"><i class="fas fa-right-to-bracket"></i> Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main content -->
    <main class="section" style="flex:1 0 auto;">
        <div class="container">
            <?= $content ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="content has-text-centered">
            <p>
                <img src="/img/logo.png" alt="Aetia Logo" style="max-height:1.8rem;vertical-align:middle;">
                <strong class="has-text-light">Aetia Talent Agency</strong><br>
                <span class="icon-text mt-2">
                    <span class="icon has-text-primary"><i class="fas fa-envelope"></i></span>
                    <a href="mailto:talent@aetia.com.au">talent@aetia.com.au</a>
                </span>
            </p>
            <p class="is-size-7 has-text-grey">&copy; <?= date('Y') ?> Aetia Talent Agency. All rights reserved.</p>
            <hr>
            <p class="is-size-7 has-text-grey mb-0">
                Aetia Talent Agency is registered as a subsidiary under LochStudios (ABN: 20 447 022 747).
            </p>
            <p class="is-size-7 has-text-grey mt-2">
                <a href="/terms-of-service.php">Terms of Service</a>
                &nbsp;&middot;&nbsp;
                <a href="/privacy-policy.php">Privacy Policy</a>
            </p>
        </div>
    </footer>

    <!-- Aetia UI helpers (SweetAlert wrappers, navbar burger, dropdown, etc.) -->
    <script src="/js/aetia-ui.js?v=<?= $aetia_js_v ?>"></script>
    <script>
        // Detect and persist timezone on first visit
        (function () {
            try {
                var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
                var cur = (function (n) {
                    var a = document.cookie.split(';');
                    for (var i = 0; i < a.length; i++) {
                        var c = a[i].trim();
                        if (c.indexOf(n + '=') === 0) return c.substring(n.length + 1);
                    }
                    return null;
                })('user_timezone');
                if (tz && tz !== cur) {
                    var d = new Date();
                    d.setTime(d.getTime() + 30 * 24 * 3600 * 1000);
                    document.cookie = 'user_timezone=' + tz + ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax';
                    fetch(<?= json_encode($_SERVER['PHP_SELF']) ?>, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=set_timezone&timezone=' + encodeURIComponent(tz),
                        credentials: 'same-origin'
                    }).catch(function () { /* swallow */ });
                }
            } catch (e) { /* noop */ }
        })();
    </script>
    <?php if ($flashSuccess): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            window.Aetia && Aetia.toast(<?= json_encode($flashSuccess) ?>, 'success');
        });
    </script>
    <?php endif; ?>
    <?php if ($flashError): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            window.Aetia && Aetia.error('Error', <?= json_encode($flashError) ?>);
        });
    </script>
    <?php endif; ?>
    <?php if ($flashInfo): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            window.Aetia && Aetia.toast(<?= json_encode($flashInfo) ?>, 'info');
        });
    </script>
    <?php endif; ?>
    <?php if (isset($scripts) && !empty($scripts)): ?>
    <?= $scripts ?>
    <?php endif; ?>
</body>
</html>
