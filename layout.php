<?php
// layout.php - Main layout template for Aetia Talant Agency
session_start();

// Handle timezone setting from JavaScript
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_timezone') {
    if (isset($_POST['timezone'])) {
        $_SESSION['user_timezone'] = $_POST['timezone'];
    }
    exit; // Don't render the page for AJAX requests
}

// Get user's timezone (fallback to Australia/Sydney)
function getUserTimezone() {
    // Check session first
    if (isset($_SESSION['user_timezone'])) {
        return $_SESSION['user_timezone'];
    }
    
    // Check cookie as fallback
    if (isset($_COOKIE['user_timezone'])) {
        $_SESSION['user_timezone'] = $_COOKIE['user_timezone'];
        return $_COOKIE['user_timezone'];
    }
    
    // Default to Australia/Sydney
    return 'Australia/Sydney';
}

// Format date/time for user's timezone
function formatDateForUser($dateString, $format = 'M j, Y g:i A') {
    try {
        $userTimezone = getUserTimezone();
        $date = new DateTime($dateString, new DateTimeZone('Australia/Sydney')); // Server timezone
        $date->setTimezone(new DateTimeZone($userTimezone)); // Convert to user's timezone
        return $date->format($format);
    } catch (Exception $e) {
        // Fallback to original formatting if timezone conversion fails
        return date($format, strtotime($dateString));
    }
}

if (!isset($pageTitle)) $pageTitle = 'Aetia Talant Agency';
if (!isset($content)) $content = '';
?>
<!DOCTYPE html>
<html lang="en" style="height:100%;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="icon" type="image/x-icon" href="../img/logo.ico">
    <!-- Bulma CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/custom.css">
</head>
<body style="min-height:100vh;display:flex;flex-direction:column;">
    <!-- Navigation Bar -->
    <nav class="navbar is-primary" role="navigation" aria-label="main navigation">
      <div class="navbar-brand">
        <a class="navbar-item" href="index.php">
          <img src="../img/logo.png" alt="Aetia Talant Agency Logo" style="max-height: 3rem;">
        </a>
        <a role="button" class="navbar-burger" aria-label="menu" aria-expanded="false" data-target="navbarBasic">
          <span aria-hidden="true"></span>
          <span aria-hidden="true"></span>
          <span aria-hidden="true"></span>
        </a>
      </div>
      <div id="navbarBasic" class="navbar-menu">
        <div class="navbar-start">
          <a class="navbar-item" href="../index.php">Home</a>
          <a class="navbar-item" href="../about.php">About</a>
          <a class="navbar-item" href="../services.php">Services</a>
          <a class="navbar-item" href="../contact.php">Contact</a>
          <?php if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true): ?>
          <hr class="navbar-divider">
          <a class="navbar-item" href="../messages.php">
            <span class="icon"><i class="fas fa-envelope"></i></span>
            <span>Messages</span>
          </a>
          <?php endif; ?>
        </div>
        <div class="navbar-end">
          <div class="navbar-item">
            <div class="buttons">
              <?php if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true): ?>
                <?php 
                // Check if user is admin for navigation
                $showAdminLink = false;
                if (isset($_SESSION['user_id'])) {
                    require_once __DIR__ . '/models/User.php';
                    $userModel = new User();
                    $showAdminLink = $userModel->isUserAdmin($_SESSION['user_id']);
                }
                ?>
                <div class="dropdown is-hoverable">
                  <div class="dropdown-trigger">
                    <button class="button is-light is-small" aria-haspopup="true" aria-controls="dropdown-menu">
                      <?php if (isset($_SESSION['social_data']['profile_image_url'])): ?>
                        <img src="<?= htmlspecialchars($_SESSION['social_data']['profile_image_url']) ?>" alt="Profile" style="width:20px;height:20px;border-radius:50%;margin-right:0.5rem;">
                      <?php else: ?>
                        <span class="icon"><i class="fas fa-user"></i></span>
                      <?php endif; ?>
                      <span><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
                      <span class="icon is-small">
                        <i class="fas fa-angle-down" aria-hidden="true"></i>
                      </span>
                    </button>
                  </div>
                  <div class="dropdown-menu" id="dropdown-menu" role="menu">
                    <div class="dropdown-content">
                      <div class="dropdown-item is-static">
                        <p class="is-size-7 has-text-grey">
                          Logged in via <?= ucfirst($_SESSION['account_type'] ?? 'manual') ?>
                        </p>
                      </div>
                      <hr class="dropdown-divider">
                      <a href="../profile.php" class="dropdown-item">
                        <span class="icon"><i class="fas fa-user-cog"></i></span>
                        <span>Profile Settings</span>
                      </a>
                      <a href="../messages.php" class="dropdown-item">
                        <span class="icon"><i class="fas fa-envelope"></i></span>
                        <span>Messages</span>
                      </a>
                      <?php if ($showAdminLink): ?>
                      <a href="../admin/pending-users.php" class="dropdown-item">
                        <span class="icon"><i class="fas fa-users-cog"></i></span>
                        <span>Admin Panel</span>
                      </a>
                      <?php endif; ?>
                      <hr class="dropdown-divider">
                      <a href="../logout.php" class="dropdown-item">
                        <span class="icon"><i class="fas fa-sign-out-alt"></i></span>
                        <span>Logout</span>
                      </a>
                    </div>
                  </div>
                </div>
              <?php else: ?>
                <a class="button is-primary is-small" href="../login.php">
                  <span class="icon"><i class="fas fa-sign-in-alt"></i></span>
                  <span>Login</span>
                </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </nav>
    <section class="section" style="flex:1 0 auto;">
        <div class="container">
            <?= $content ?>
        </div>
    </section>
    <footer class="footer has-background-dark has-text-light mt-6" style="padding-top:2rem;padding-bottom:2rem;flex-shrink:0;">
      <div class="content has-text-centered">
        <p>
          <img src="../img/logo.png" alt="Aetia Logo" style="max-height:2rem;vertical-align:middle;filter:brightness(0) invert(1);"> <strong class="has-text-light">Aetia Talant Agency</strong><br>
          <span class="icon-text">
            <span class="icon has-text-info"><i class="fas fa-envelope"></i></span>
            <span><a href="mailto:talant@aetia.com.au" class="has-text-info">talant@aetia.com.au</a></span>
          </span>
        </p>
        <p class="is-size-7 has-text-grey-light">&copy; <?= date('Y') ?> Aetia Talant Agency. All rights reserved.</p>
        <hr class="my-2" style="background:rgba(255,255,255,0.08);height:1px;border:none;">
        <p class="is-size-7 has-text-grey-light mb-0">
          Aetia Talant Agency is registered as a subsidiary under LochStudios (ABN: 20 447 022 747).
        </p>
      </div>
    </footer>
    <script src="../js/navbar.js"></script>
    <script>
        // Handle notification dismissal
        document.addEventListener('DOMContentLoaded', function() {
            const deleteButtons = document.querySelectorAll('.notification .delete');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    this.parentElement.style.display = 'none';
                });
            });
            
            // Detect and store user's timezone
            detectUserTimezone();
        });
        
        function detectUserTimezone() {
            try {
                // Get user's timezone using Intl API
                const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
                
                // Store in session via AJAX if different from current
                const currentTimezone = getCookie('user_timezone');
                if (userTimezone !== currentTimezone) {
                    setCookie('user_timezone', userTimezone, 30); // Store for 30 days
                    
                    // Also send to server for session storage
                    fetch('<?= $_SERVER['PHP_SELF'] ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=set_timezone&timezone=' + encodeURIComponent(userTimezone)
                    }).catch(e => console.log('Timezone sync failed:', e));
                }
            } catch (e) {
                console.log('Timezone detection failed:', e);
            }
        }
        
        function setCookie(name, value, days) {
            const expires = new Date();
            expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
            document.cookie = name + '=' + value + ';expires=' + expires.toUTCString() + ';path=/';
        }
        
        function getCookie(name) {
            const nameEQ = name + "=";
            const ca = document.cookie.split(';');
            for(let i = 0; i < ca.length; i++) {
                let c = ca[i];
                while (c.charAt(0) === ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
            }
            return null;
        }
    </script>
    <?php if (isset($scripts) && !empty($scripts)): ?>
    <?= $scripts ?>
    <?php endif; ?>
</body>
</html>
