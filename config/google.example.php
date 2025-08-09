<?php
// Google OAuth Configuration - Copy this to google.php and update with your values

return [
    'client_id' => 'your-google-client-id.apps.googleusercontent.com',
    'client_secret' => 'your-google-client-secret',
    'redirect_uri' => 'https://yourdomain.com/auth/google-callback.php', // For sign-in
    'link_redirect_uri' => 'https://yourdomain.com/auth/google-link-callback.php', // For account linking
    'scopes' => ['openid', 'email'],
];
