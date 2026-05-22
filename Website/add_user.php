<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['role'] != 'admin') {
    header('Location: index.php');
    exit();
}
require 'db.php';

$msg = '';
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $fullname = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $role = $_POST['role'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO User (username, password, full_name, email, phone, user_type) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $password, $fullname, $email, $phone, $role]);
        
        // Log
        $stmt = $pdo->prepare("INSERT INTO SYS_ACT_Log (user_id, actions) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], "Created User: $username, Role: $role"]);

        header('Location: admin_dashboard.php?msg=User Added'); exit;
    } catch (Exception $e) {
        $msg = "Database Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add User - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-10">
    <div class="max-w-lg mx-auto bg-white p-8 rounded shadow">
        <h2 class="text-xl font-bold mb-6">Create New User</h2>
        <?php if($msg): ?><p class="text-red-500 mb-4"><?php echo $msg; ?></p><?php endif; ?>
        <form method="POST">
            <div class="mb-4">
                <label class="block text-sm font-bold mb-2">Username</label>
                <input type="text" name="username" required class="w-full border p-2 rounded">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-bold mb-2">Full Name</label>
                <input type="text" name="full_name" required class="w-full border p-2 rounded">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-bold mb-2">Email</label>
                <input type="email" name="email" class="w-full border p-2 rounded">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-bold mb-2">Phone</label>
                <input type="text" name="phone" class="w-full border p-2 rounded">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-bold mb-2">Password</label>
                <input type="password" name="password" required class="w-full border p-2 rounded">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-bold mb-2">Role</label>
                <select name="role" class="w-full border p-2 rounded">
                <option value="Customer">Customer</option>
                <option value="Staff">Staff</option>
                <option value="Admin">Admin</option>
                </select>
            </div>
            <div class="flex gap-4">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Save User</button>
                <a href="admin_dashboard.php" class="bg-gray-500 text-white px-4 py-2 rounded">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
