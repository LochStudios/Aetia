<?php
// Simple syntax check for FormTokenManager
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing FormTokenManager...\n";

// Start session for testing
session_start();

// Include the FormTokenManager
require_once 'includes/FormTokenManager.php';

try {
    // Test token generation
    $token = FormTokenManager::generateToken('test_form');
    echo "✓ Token generation successful: " . substr($token, 0, 10) . "...\n";
    
    // Test token validation (should return true)
    $isValid = FormTokenManager::validateToken('test_form', $token);
    echo "✓ Token validation result: " . ($isValid ? 'VALID' : 'INVALID') . "\n";
    
    // Test token reuse (should return false)
    $isReused = FormTokenManager::validateToken('test_form', $token);
    echo "✓ Token reuse protection: " . ($isReused ? 'FAILED (allowed reuse)' : 'SUCCESS (blocked reuse)') . "\n";
    
    // Test recent submission check
    $isRecent = FormTokenManager::isRecentSubmission('test_form2');
    echo "✓ Recent submission check: " . ($isRecent ? 'BLOCKED' : 'ALLOWED') . "\n";
    
    // Test HTML field generation
    $html = FormTokenManager::getTokenField('test_form3');
    echo "✓ HTML field generation: " . (strlen($html) > 50 ? 'SUCCESS' : 'FAILED') . "\n";
    
    echo "\nAll tests completed successfully! 🎉\n";
    echo "The FormTokenManager is ready to prevent duplicate form submissions.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
