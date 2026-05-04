<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['role'] != 'admin') {
    header('Location: index.php');
    exit();
}
require 'db.php';

// Fetch Team Members (Staff and Admin)
$team_stmt = $pdo->prepare("SELECT user_id as id, username, full_name, user_type as role, status FROM User WHERE user_type IN ('Admin', 'Staff', 'Manager') ORDER BY user_type ASC, username ASC");
$team_stmt->execute();
$team = $team_stmt->fetchAll();

// Fetch Logs
$logs = $pdo->query("
    SELECT l.*, l.actions as action, l.timestamp as created_at, u.username, u.user_type as role 
    FROM SYS_ACT_Log l 
    JOIN User u ON l.user_id = u.user_id 
    ORDER BY l.timestamp DESC 
    LIMIT 50
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Monitoring - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-blue-600 text-white p-4 flex justify-between items-center shadow-lg">
        <h1 class="text-xl font-bold">System Monitoring</h1>
        <a href="admin_dashboard.php" class="text-sm border border-white px-3 py-1 rounded hover:bg-white hover:text-blue-600 transition">Back to Dashboard</a>
    </nav>

    <div class="container mx-auto p-6 space-y-8">
        <!-- Team Status Section -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-4 border-b bg-gray-50">
                <h2 class="text-lg font-bold text-gray-800">Staff & Team Status</h2>
                <p class="text-sm text-gray-500">Overview of the currently registered team members.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4">
                <?php foreach($team as $member): ?>
                    <div class="border rounded-xl p-4 flex items-center gap-4 bg-gray-50">
                        <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold">
                            <?php echo strtoupper(substr($member['username'], 0, 1)); ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold text-gray-900 truncate"><?php echo htmlspecialchars($member['full_name'] ?: $member['username']); ?></h3>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] uppercase font-black px-1.5 py-0.5 rounded <?php echo $member['role'] == 'admin' ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-600'; ?>">
                                    <?php echo $member['role']; ?>
                                </span>
                                <span class="w-2 h-2 rounded-full <?php echo $member['status'] == 'active' ? 'bg-green-500' : 'bg-gray-400'; ?>"></span>
                                <span class="text-[10px] text-gray-500 font-bold uppercase"><?php echo $member['status']; ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Activity Logs Section -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-4 border-b bg-gray-50">
                <h2 class="text-lg font-bold text-gray-800">Global Activity Logs</h2>
                <p class="text-sm text-gray-500">Real-time monitoring of user actions.</p>
            </div>
            
            <div class="divide-y max-h-[500px] overflow-y-auto">
                <?php foreach($logs as $log): ?>
                <div class="p-4 hover:bg-gray-50 flex items-start gap-4">
                    <div class="mt-1">
                        <span class="bg-gray-100 text-gray-600 text-[10px] uppercase font-bold px-1.5 py-0.5 rounded"><?php echo $log['role']; ?></span>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm">
                            <span class="font-bold text-gray-900"><?php echo htmlspecialchars($log['username']); ?></span> 
                            <span class="text-gray-600"><?php echo htmlspecialchars($log['action']); ?></span>
                        </p>
                    </div>
                    <div class="text-xs text-gray-400 whitespace-nowrap">
                        <?php echo date('M d, H:i', strtotime($log['created_at'])); ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if(empty($logs)): ?>
                    <div class="p-10 text-center text-gray-500 italic">No activity recorded yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
