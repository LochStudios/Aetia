<?php
// config/cleanup-stripe-database.php - Remove Stripe database tables and data

require_once __DIR__ . '/database.php';

try {
    $database = new Database();
    $mysqli = $database->getConnection();
    
    if (!$mysqli) {
        throw new Exception('Database connection failed');
    }
    
    echo "Cleaning up Stripe database tables...\n";
    
    // Drop Stripe tables in reverse order of dependencies
    $tables = [
        'stripe_payment_attempts',
        'stripe_webhook_events', 
        'stripe_invoices',
        'stripe_customers'
    ];
    
    foreach ($tables as $table) {
        $query = "DROP TABLE IF EXISTS `{$table}`";
        if ($mysqli->query($query)) {
            echo "✓ Dropped table: {$table}\n";
        } else {
            echo "✗ Error dropping table {$table}: " . $mysqli->error . "\n";
        }
    }
    
    echo "\nStripe database cleanup completed!\n";
    echo "All Stripe-related tables have been removed.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
