<?php
require 'db.php';

echo "<h1>Login Debug Info</h1>";

// Check if admin user exists
$stmt = $pdo->prepare("SELECT id, username, role, password FROM users WHERE username = 'admin'");
$stmt->execute();
$user = $stmt->fetch();

if ($user) {
    echo "<pre>";
    echo "✓ Admin user found!\n";
    echo "ID: " . $user['id'] . "\n";
    echo "Username: " . $user['username'] . "\n";
    echo "Role: " . $user['role'] . "\n";
    echo "Role type check (== 'admin'): " . ($user['role'] == 'admin' ? 'TRUE ✓' : 'FALSE ✗') . "\n";
    echo "Password Hash: " . $user['password'] . "\n";
    
    // Test password verification
    $test_password = 'admin';
    $verify_result = password_verify($test_password, $user['password']);
    echo "Password 'admin' verification: " . ($verify_result ? 'PASS ✓' : 'FAIL ✗') . "\n";
    echo "</pre>";
} else {
    echo "<p style='color:red;'>✗ Admin user NOT found in database!</p>";
}

// List all users
echo "<h2>All Users in Database:</h2>";
$stmt = $pdo->query("SELECT id, username, role FROM users");
$users = $stmt->fetchAll();

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Username</th><th>Role</th></tr>";
foreach ($users as $u) {
    echo "<tr><td>" . $u['id'] . "</td><td>" . $u['username'] . "</td><td>" . $u['role'] . "</td></tr>";
}
echo "</table>";
?>
