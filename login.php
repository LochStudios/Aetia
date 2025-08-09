<?php
// login.php - Login/Signup page for Aetia Talent Agency
session_start();

require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/services/TwitchOAuth.php';
require_once __DIR__ . '/services/DiscordOAuth.php';

// Redirect if already logged in
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$error_message = '';
$success_message = '';
$isSignupMode = isset($_GET['mode']) && $_GET['mode'] === 'signup';

// Check for logout message
if (isset($_SESSION['logout_message'])) {
    $success_message = $_SESSION['logout_message'];
    unset($_SESSION['logout_message']);
}

// Check for social login messages
if (isset($_SESSION['login_error'])) {
    $error_message = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

if (isset($_SESSION['login_success'])) {
    $success_message = $_SESSION['login_success'];
    unset($_SESSION['login_success']);
}

$userModel = new User();

// Process manual login/signup form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'login') {
            // Manual login
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            
            if (empty($username) || empty($password)) {
                $error_message = 'Please fill in all fields.';
            } else {
                $result = $userModel->authenticateManualUser($username, $password);
                
                if ($result['success']) {
                    $_SESSION['user_logged_in'] = true;
                    $_SESSION['user_id'] = $result['user']['id'];
                    $_SESSION['username'] = $result['user']['username'];
                    $_SESSION['login_time'] = time();
                    $_SESSION['account_type'] = 'manual';
                    
                    // Check if this is the admin user with auto-generated credentials
                    $tempPasswordFile = '/tmp/aetia_admin_initial_password.txt';
                    $isInitialAdminLogin = false;
                    
                    if ($username === 'admin' && file_exists($tempPasswordFile)) {
                        // Verify this is actually the initial admin by checking approved_by field
                        $user = $userModel->getUserById($result['user']['id']);
                        if ($user && $user['approved_by'] === 'Auto-Generated') {
                            $isInitialAdminLogin = true;
                            // Remove the temp password file
                            unlink($tempPasswordFile);
                        }
                    }
                    
                    if ($isInitialAdminLogin) {
                        $success_message = 'Admin login successful! Initial setup complete. Please change your password immediately for security. Redirecting...';
                    } else {
                        $success_message = 'Login successful! Redirecting...';
                    }
                    
                    header('refresh:2;url=index.php');
                } else {
                    $error_message = $result['message'];
                }
            }
        } elseif ($_POST['action'] === 'signup') {
            // Manual signup
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $confirmPassword = trim($_POST['confirm_password'] ?? '');
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            
            if (empty($username) || empty($email) || empty($password)) {
                $error_message = 'Please fill in all required fields.';
            } elseif ($password !== $confirmPassword) {
                $error_message = 'Passwords do not match.';
            } elseif (strlen($password) < 8) {
                $error_message = 'Password must be at least 8 characters long.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error_message = 'Please enter a valid email address.';
            } else {
                $result = $userModel->createManualUser($username, $email, $password, $firstName, $lastName);
                
                if ($result['success']) {
                    $success_message = 'Account created successfully! Your account is pending approval. Aetia Talent Agency will contact you with critical platform information and business terms before you can access your account.';
                    $isSignupMode = false; // Switch to login mode
                } else {
                    $error_message = $result['message'];
                }
            }
        }
    }
}

// Initialize Twitch OAuth
$twitchOAuth = new TwitchOAuth();
$twitchAuthUrl = $twitchOAuth->getAuthorizationUrl();

// Initialize Discord OAuth
$discordAuthUrl = null;
$discordAvailable = true;
try {
    $discordOAuth = new DiscordOAuth();
    $discordAuthUrl = $discordOAuth->getAuthorizationUrl();
} catch (Exception $e) {
    error_log('Discord OAuth initialization failed: ' . $e->getMessage());
    $discordAvailable = false;
}

// Check for initial admin password
$initialAdminPassword = '';
$tempPasswordFile = '/tmp/aetia_admin_initial_password.txt';
if (file_exists($tempPasswordFile)) {
    $initialAdminPassword = trim(file_get_contents($tempPasswordFile));
}

$pageTitle = ($isSignupMode ? 'Sign Up' : 'Login') . ' | Aetia Talent Agency';
ob_start();
?>
<div class="columns is-centered">
    <div class="column is-6-tablet is-5-desktop is-4-widescreen">
        <div class="card login-card">
            <div class="card-content">
                <h2 class="title is-4 has-text-centered mb-5">
                    <span class="icon has-text-primary"><i class="fas fa-<?= $isSignupMode ? 'user-plus' : 'sign-in-alt' ?>"></i></span>
                    <?= $isSignupMode ? 'Create Account' : 'Login' ?>
                </h2>
                
                <?php if ($isSignupMode): ?>
                <div class="notification is-info is-light mb-4">
                    <div class="content">
                        <p><strong>Notice:</strong> All new accounts require approval from Aetia Talent Agency.</p>
                        <p>After creating your account, our team will contact you to discuss:</p>
                        <ul>
                            <li>Platform terms and conditions</li>
                            <li>Commission structure and revenue sharing</li>
                            <li>Business partnership agreements</li>
                        </ul>
                        <p>You will be able to access your account once approved.</p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($initialAdminPassword && !$isSignupMode): ?>
                <div class="notification is-warning is-light mb-4">
                    <div class="content">
                        <p><strong><i class="fas fa-shield-alt"></i> Initial Admin Setup</strong></p>
                        <p>A system administrator account has been created for first-time setup:</p>
                        <div class="box has-background-dark has-text-light">
                            <p><strong>Username:</strong> <code style="background:#333;color:#fff;padding:2px 6px;">admin</code></p>
                            <p><strong>Password:</strong> <code style="background:#333;color:#fff;padding:2px 6px;"><?= htmlspecialchars($initialAdminPassword) ?></code></p>
                        </div>
                        <p class="is-size-7 has-text-grey">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Please login with these credentials and change the password immediately. 
                            This notice will disappear after first login.
                        </p>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Social Login Section -->
                <div class="mb-5">
                    <h3 class="subtitle is-6 has-text-centered mb-3">Login with Social Media</h3>
                    <div class="buttons is-centered">
                        <a href="<?= htmlspecialchars($twitchAuthUrl) ?>" class="button is-link is-fullwidth mb-2 has-text-white">
                            <span class="icon"><i class="fab fa-twitch"></i></span>
                            <span>Continue with Twitch</span>
                        </a>
                        <a href="<?= htmlspecialchars($discordAuthUrl) ?>" class="button is-primary is-fullwidth mb-2 has-text-white" style="background-color: #5865F2;">
                            <span class="icon"><i class="fab fa-discord"></i></span>
                            <span>Continue with Discord</span>
                        </a>
                        <button class="button is-danger is-fullwidth mb-2 has-text-white" disabled>
                            <span class="icon"><i class="fab fa-youtube"></i></span>
                            <span>Continue with YouTube (Coming Soon)</span>
                        </button>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <!-- Manual Login/Signup Section -->
                <div class="has-text-centered mb-3">
                    <h3 class="subtitle is-6">Or use manual <?= $isSignupMode ? 'signup' : 'login' ?></h3>
                </div>
                
                <?php if ($error_message): ?>
                <div class="notification is-danger is-light">
                    <span class="icon-text">
                        <span class="icon"><i class="fas fa-exclamation-triangle"></i></span>
                        <span><?= htmlspecialchars($error_message) ?></span>
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                <div class="notification is-success is-light">
                    <span class="icon-text">
                        <span class="icon"><i class="fas fa-check-circle"></i></span>
                        <span><?= htmlspecialchars($success_message) ?></span>
                    </span>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="login.php<?= $isSignupMode ? '?mode=signup' : '' ?>">
                    <input type="hidden" name="action" value="<?= $isSignupMode ? 'signup' : 'login' ?>">
                    
                    <?php if ($isSignupMode): ?>
                    <!-- Signup Fields -->
                    <div class="columns">
                        <div class="column">
                            <div class="field">
                                <label class="label">First Name</label>
                                <div class="control has-icons-left">
                                    <input class="input" type="text" name="first_name" placeholder="First name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                                    <span class="icon is-small is-left">
                                        <i class="fas fa-user"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="column">
                            <div class="field">
                                <label class="label">Last Name</label>
                                <div class="control has-icons-left">
                                    <input class="input" type="text" name="last_name" placeholder="Last name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                                    <span class="icon is-small is-left">
                                        <i class="fas fa-user"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="field">
                        <label class="label">Email *</label>
                        <div class="control has-icons-left">
                            <input class="input" type="email" name="email" placeholder="Enter email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            <span class="icon is-small is-left">
                                <i class="fas fa-envelope"></i>
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="field">
                        <label class="label"><?= $isSignupMode ? 'Username *' : 'Username or Email' ?></label>
                        <div class="control has-icons-left">
                            <input class="input" type="text" name="username" placeholder="<?= $isSignupMode ? 'Choose username' : 'Enter username or email' ?>" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                            <span class="icon is-small is-left">
                                <i class="fas fa-user"></i>
                            </span>
                        </div>
                    </div>
                    
                    <div class="field">
                        <label class="label">Password *</label>
                        <div class="control has-icons-left">
                            <input class="input" type="password" name="password" placeholder="<?= $isSignupMode ? 'Create password (min 8 chars)' : 'Enter password' ?>" required>
                            <span class="icon is-small is-left">
                                <i class="fas fa-lock"></i>
                            </span>
                        </div>
                        <?php if (!$isSignupMode): ?>
                        <p class="help">
                            <a href="forgot-password.php" class="has-text-primary is-size-7">
                                <span class="icon is-small"><i class="fas fa-key"></i></span>
                                Forgot your password?
                            </a>
                        </p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($isSignupMode): ?>
                    <div class="field">
                        <label class="label">Confirm Password *</label>
                        <div class="control has-icons-left">
                            <input class="input" type="password" name="confirm_password" placeholder="Confirm password" required>
                            <span class="icon is-small is-left">
                                <i class="fas fa-lock"></i>
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="field">
                        <div class="control">
                            <button class="button is-primary is-fullwidth" type="submit">
                                <span class="icon"><i class="fas fa-<?= $isSignupMode ? 'user-plus' : 'sign-in-alt' ?>"></i></span>
                                <span><?= $isSignupMode ? 'Create Account' : 'Login' ?></span>
                            </button>
                        </div>
                    </div>
                </form>
                
                <div class="has-text-centered mt-4">
                    <p>
                        <?php if ($isSignupMode): ?>
                            Already have an account? 
                            <a href="login.php" class="has-text-primary">Login here</a>
                        <?php else: ?>
                            Don't have an account? 
                            <a href="login.php?mode=signup" class="has-text-primary">Sign up here</a>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include 'layout.php';
?>
