<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['role'] != 'admin') {
    header('Location: index.php');
    exit();
}
require 'db.php';

// Handle Add Slot
if (isset($_POST['add_slot'])) {
    $code = $_POST['slot_code'];
    $type = $_POST['type'];
    $stmt = $pdo->prepare("INSERT INTO slots (slot_code, type) VALUES (?, ?)");
    $stmt->execute([$code, $type]);
    header('Location: manage_slots.php?msg=Slot Created'); exit;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM slots WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header('Location: manage_slots.php?msg=Slot Deleted'); exit;
}

$slots = $pdo->query("SELECT * FROM slots ORDER BY slot_code ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Slots - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <nav class="bg-blue-600 text-white p-4 flex justify-between items-center shadow-lg">
        <h1 class="text-xl font-bold">Slot Management</h1>
        <a href="admin_dashboard.php" class="text-sm border border-white px-3 py-1 rounded hover:bg-white hover:text-blue-600 transition">Back to Dashboard</a>
    </nav>

    <div class="container mx-auto p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Add Form -->
            <div class="bg-white p-6 rounded shadow h-fit">
                <h2 class="text-lg font-bold mb-4">Add New Slot</h2>
                <form method="POST">
                    <div class="mb-4">
                        <label class="block text-sm font-bold mb-2">Slot Code (e.g. A1, B5)</label>
                        <input type="text" name="slot_code" required class="w-full border p-2 rounded">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-bold mb-2">Type</label>
                        <select name="type" class="w-full border p-2 rounded">
                            <option value="standard">Standard</option>
                            <option value="ev">EV Charging</option>
                            <option value="handicapped">Handicapped</option>
                        </select>
                    </div>
                    <button type="submit" name="add_slot" class="w-full bg-blue-600 text-white py-2 rounded font-bold">Create Slot</button>
                </form>
            </div>

            <!-- Slots List -->
            <div class="md:col-span-2 bg-white rounded shadow overflow-hidden">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="p-4">Code</th>
                            <th class="p-4">Type</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($slots as $slot): ?>
                        <tr class="border-b">
                            <td class="p-4 font-bold"><?php echo $slot['slot_code']; ?></td>
                            <td class="p-4"><?php echo ucfirst($slot['type']); ?></td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded text-xs font-bold <?php echo $slot['status'] == 'available' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'; ?>">
                                    <?php echo ucfirst($slot['status']); ?>
                                </span>
                            </td>
                            <td class="p-4 text-center">
                                <a href="?delete=<?php echo $slot['id']; ?>" class="text-red-500 hover:underline" onclick="return confirm('Delete this slot?')">Remove</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
