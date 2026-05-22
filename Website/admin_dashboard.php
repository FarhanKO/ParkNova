<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['role'] != 'admin') {
    header('Location: index.php');
    exit();
}
require 'db.php';

// Handle Delete
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM User WHERE user_id = ? AND user_type != 'Admin'");
    $stmt->execute([$_GET['delete']]);
    header('Location: admin_dashboard.php?msg=User Deleted');
    exit;
}

// Fetch Users
$stmt = $pdo->query("SELECT * FROM User ORDER BY created_at DESC");
$users = $stmt->fetchAll();

// Fetch Slots Summary
$stmt = $pdo->query("SELECT occupancy as status, COUNT(*) as count FROM Slot GROUP BY occupancy");
$slot_stats = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Smart Parking</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <nav class="bg-blue-600 text-white p-4 flex justify-between items-center shadow-lg">
        <h1 class="text-xl font-bold">Admin Portal</h1>
        <div class="flex items-center gap-4">
            <span>Welcome, Admin</span>
            <a href="profile.php" class="bg-blue-500 hover:bg-blue-700 px-3 py-1 rounded text-sm transition">Profile</a>
            <a href="logout.php" class="bg-red-500 hover:bg-red-600 px-3 py-1 rounded text-sm transition">Logout</a>
        </div>
    </nav>

    <div class="container mx-auto p-6">
        <!-- Quick Navigation -->
        <div class="flex gap-4 mb-8">
            <a href="manage_slots.php" class="bg-white border border-blue-600 text-blue-600 px-4 py-2 rounded font-bold hover:bg-blue-600 hover:text-white transition">Manage Slots</a>
            <a href="active_sessions.php" class="bg-white border border-blue-600 text-blue-600 px-4 py-2 rounded font-bold hover:bg-blue-600 hover:text-white transition">Live Parking Map</a>
            <a href="monitoring.php" class="bg-white border border-blue-600 text-blue-600 px-4 py-2 rounded font-bold hover:bg-blue-600 hover:text-white transition">Team Monitoring</a>
        </div>

        <!-- Stats Summary -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <?php foreach($slot_stats as $stat): ?>
                <div class="bg-white p-4 rounded-lg shadow border-l-4 border-blue-500">
                    <p class="text-gray-500 text-sm uppercase font-bold"><?php echo $stat['status'] ? 'Occupied' : 'Available'; ?> Slots</p>
                    <p class="text-2xl font-bold"><?php echo $stat['count']; ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- User Management Section -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-4 border-b bg-gray-50 flex justify-between items-center">
                <h2 class="text-lg font-bold text-gray-800">User Management</h2>
                <a href="add_user.php" class="bg-green-600 text-white px-4 py-2 rounded text-sm hover:bg-green-700 transition">Add New User</a>
            </div>
            
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-100 text-gray-700 text-sm uppercase">
                    <tr>
                        <th class="p-4">Username</th>
                        <th class="p-4">Full Name</th>
                        <th class="p-4">Role</th>
                        <th class="p-4">Status</th>
                        <th class="p-4 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600">
                    <?php foreach($users as $user): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="p-4 font-medium"><?php echo htmlspecialchars($user['username']); ?></td>
                        <td class="p-4"><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td class="p-4 font-semibold text-blue-600"><?php echo ucfirst($user['user_type']); ?></td>
                        <td class="p-4">
                            <span class="px-2 py-1 rounded-full text-xs font-bold <?php echo $user['status'] == 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </td>
                        <td class="p-4 text-center">
                            <a href="edit_user.php?id=<?php echo $user['user_id']; ?>" class="text-blue-600 hover:underline mr-3">Edit</a>
                            <?php if(strtolower($user['user_type']) != 'admin'): ?>
                                <a href="?delete=<?php echo $user['user_id']; ?>" class="text-red-600 hover:underline" onclick="return confirm('Are you sure?')">Delete</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
