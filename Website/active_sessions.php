<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}
require 'db.php';

// Handle Checkout
if (isset($_GET['checkout'])) {
    $session_id = $_GET['checkout'];
    
    // Fetch session and slot
    $stmt = $pdo->prepare("SELECT * FROM sessions WHERE id = ?");
    $stmt->execute([$session_id]);
    $session = $stmt->fetch();
    
    if ($session) {
        $pdo->beginTransaction();
        try {
            // 1. Update Session
            $stmt = $pdo->prepare("UPDATE sessions SET end_time = CURRENT_TIMESTAMP, status = 'completed' WHERE id = ?");
            $stmt->execute([$session_id]);
            
            // 2. Clear Slot
            $stmt = $pdo->prepare("UPDATE slots SET status = 'available' WHERE id = ?");
            $stmt->execute([$session['slot_id']]);

            // 3. Log
            $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, 'Checkout', ?)");
            $stmt->execute([$_SESSION['user_id'], "Plate: {$session['vehicle_plate']}, Slot ID: {$session['slot_id']}"]);
            
            $pdo->commit();
            header('Location: active_sessions.php?msg=Session Completed'); exit;
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
}

// Fetch Active Sessions
$sessions = $pdo->query("
    SELECT s.*, u.username, sl.slot_code 
    FROM sessions s 
    JOIN users u ON s.user_id = u.id 
    JOIN slots sl ON s.slot_id = sl.id 
    WHERE s.status = 'active'
    ORDER BY s.start_time DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Active Sessions - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <nav class="bg-blue-600 text-white p-4 flex justify-between items-center shadow-lg">
        <h1 class="text-xl font-bold">Live Parking Map</h1>
        <a href="admin_dashboard.php" class="text-sm border border-white px-3 py-1 rounded hover:bg-white hover:text-blue-600 transition">Back to Dashboard</a>
    </nav>

    <div class="container mx-auto p-6">
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-4 border-b bg-gray-50">
                <h2 class="text-lg font-bold text-gray-800">Currently Parked Vehicles</h2>
            </div>
            
            <table class="w-full text-left font-sans">
                <thead class="bg-gray-100 text-gray-700 text-sm">
                    <tr>
                        <th class="p-4">Slot</th>
                        <th class="p-4">Vehicle Plate</th>
                        <th class="p-4">Owner</th>
                        <th class="p-4">Start Time</th>
                        <th class="p-4 text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($sessions as $session): ?>
                    <tr class="border-b hover:bg-blue-50 transition">
                        <td class="p-4"><span class="bg-blue-600 text-white px-2 py-1 rounded font-bold"><?php echo $session['slot_code']; ?></span></td>
                        <td class="p-4 font-bold text-gray-800"><?php echo htmlspecialchars($session['vehicle_plate']); ?></td>
                        <td class="p-4 text-sm"><?php echo htmlspecialchars($session['username']); ?></td>
                        <td class="p-4 text-sm"><?php echo $session['start_time']; ?></td>
                        <td class="p-4 text-center">
                            <a href="?checkout=<?php echo $session['id']; ?>" class="bg-red-500 text-white px-4 py-1 rounded text-xs font-bold hover:bg-red-600 transition" onclick="return confirm('End this parking session?')">Checkout</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if(empty($sessions)): ?>
                    <tr>
                        <td colspan="5" class="p-8 text-center text-gray-500 italic">No vehicles currently parked.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
