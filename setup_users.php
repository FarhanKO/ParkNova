<?php
require 'db.php';

// Default credentials for testing
$users = [
    ['username' => 'admin', 'password' => 'admin', 'full_name' => 'Admin User', 'role' => 'Admin'],
    ['username' => 'manager', 'password' => 'manager', 'full_name' => 'Manager User', 'role' => 'Manager'],
    ['username' => 'staff', 'password' => 'staff', 'full_name' => 'Staff User', 'role' => 'Staff'],
    ['username' => 'cust', 'password' => 'cust', 'full_name' => 'Customer User', 'role' => 'Customer'],
];

try {
    foreach ($users as $user) {
        // Check if user already exists
        $stmt = $pdo->prepare("SELECT user_id FROM User WHERE username = ?");
        $stmt->execute([$user['username']]);
        
        if ($stmt->fetch()) {
            echo "User '{$user['username']}' already exists. Skipping...\n";
        } else {
            // Insert new user with hashed password
            $hashed_password = password_hash($user['password'], PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO User (username, password, full_name, user_type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user['username'], $hashed_password, $user['full_name'], $user['role']]);
            echo "✓ User '{$user['username']}' created successfully (password: {$user['password']})\n";
        }
    }
    echo "\nAll users have been set up!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
