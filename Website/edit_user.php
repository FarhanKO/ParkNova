<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['role'] != 'admin') {
    header('Location: index.php');
    exit();
}
require 'db.php';

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM User WHERE user_id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) { header('Location: admin_dashboard.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $role = $_POST['role'];
    $status = $_POST['status'];
    
    $stmt = $pdo->prepare("UPDATE User SET full_name = ?, email = ?, phone = ?, user_type = ?, status = ? WHERE user_id = ?");
    $stmt->execute([$fullname, $email, $phone, $role, $status, $id]);

    // Log
    $stmt = $pdo->prepare("INSERT INTO SYS_ACT_Log (user_id, actions) VALUES (?, ?)");
    $stmt->execute([$_SESSION['user_id'], "Updated User Target ID: $id, New Role: $role, Status: $status"]);

    header('Location: admin_dashboard.php?msg=User Updated'); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-10">
    <div class="max-w-lg mx-auto bg-white p-8 rounded shadow">
        <h2 class="text-xl font-bold mb-6">Edit User: <?php echo htmlspecialchars($user['username']); ?></h2>
        <form method="POST">
            <div class="mb-4">
                <label class="block text-sm font-bold mb-2">Full Name</label>
                <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required class="w-full border p-2 rounded">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-bold mb-2">Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="w-full border p-2 rounded">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-bold mb-2">Phone</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" class="w-full border p-2 rounded">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-bold mb-2">Role</label>
                <select name="role" class="w-full border p-2 rounded">
                <option value="Customer" <?php echo strtolower($user['user_type']) == 'customer' ? 'selected' : ''; ?>>Customer</option>
                <option value="Staff" <?php echo strtolower($user['user_type']) == 'staff' ? 'selected' : ''; ?>>Staff</option>
                <option value="Admin" <?php echo strtolower($user['user_type']) == 'admin' ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-bold mb-2">Status</label>
                <select name="status" class="w-full border p-2 rounded">
                    <option value="active" <?php echo $user['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="banned" <?php echo $user['status'] == 'banned' ? 'selected' : ''; ?>>Banned</option>
                </select>
            </div>
            <div class="flex gap-4">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Update User</button>
                <a href="admin_dashboard.php" class="bg-gray-500 text-white px-4 py-2 rounded">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
