<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}
require 'db.php';

$slot_id = $_GET['slot_id'] ?? null;
if (!$slot_id) { header('Location: customer_dashboard.php'); exit; }

// Fetch slot details
$stmt = $pdo->prepare("SELECT * FROM slots WHERE id = ? AND status = 'available'");
$stmt->execute([$slot_id]);
$slot = $stmt->fetch();

if (!$slot) { header('Location: customer_dashboard.php?err=Slot Unavailable'); exit; }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $plate = $_POST['vehicle_plate'];
    
    // Start transaction
    $pdo->beginTransaction();
    try {
        // 1. Create Session
        $stmt = $pdo->prepare("INSERT INTO sessions (user_id, slot_id, vehicle_plate) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $slot_id, $plate]);
        
        // 2. Update Slot Status
        $stmt = $pdo->prepare("UPDATE slots SET status = 'occupied' WHERE id = ?");
        $stmt->execute([$slot_id]);

        // 3. Log
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, 'Booked Slot', ?)");
        $stmt->execute([$_SESSION['user_id'], "Slot: {$slot['slot_code']}, Plate: $plate"]);
        
        $pdo->commit();
        header('Location: customer_dashboard.php?msg=Parking Started'); exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Booking failed. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book Parking - Smart Parking</title>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        vibrant: {
                            light: '#8b5cf6',
                            DEFAULT: '#6d28d9',
                            dark: '#4c1d95',
                        }
                    }
                }
            }
        }
    </script>
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark')
        } else {
            document.documentElement.classList.remove('dark')
        }
    </script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 transition-colors duration-300 flex items-center justify-center min-h-screen p-4">
    <div class="bg-white dark:bg-gray-800 p-8 rounded-3xl shadow-2xl w-full max-w-md border-b-8 border-indigo-600 relative overflow-hidden">
        <div class="absolute top-0 right-0 p-4">
            <button onclick="toggleTheme()" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-all active:scale-90" title="Toggle Dark/Light Mode">
                <svg id="theme-btn-dark" class="w-5 h-5 hidden dark:block text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707m12.728 12.728L5.123 5.123z"></path></svg>
                <svg id="theme-btn-light" class="w-5 h-5 block dark:hidden text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
            </button>
        </div>

        <div class="mb-8">
            <h2 class="text-3xl font-black text-gray-800 dark:text-white tracking-tight leading-tight">Confirm Parking</h2>
            <div class="mt-4 p-4 bg-indigo-50 dark:bg-indigo-900/30 rounded-2xl border border-indigo-100 dark:border-indigo-800">
                <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">
                    Booking Slot: <span class="font-black text-indigo-600 dark:text-indigo-400 text-lg uppercase"><?php echo $slot['slot_code']; ?></span>
                </p>
                <p class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mt-1"><?php echo ucfirst($slot['type']); ?> AREA</p>
            </div>
        </div>
        
        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-xs font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-3 ml-1">Vehicle Plate Number</label>
                <input type="text" name="vehicle_plate" placeholder="e.g. ABC-1234" required 
                       class="w-full bg-gray-50 dark:bg-gray-900 dark:text-white border-2 border-transparent focus:border-indigo-500 p-4 rounded-2xl focus:outline-none transition-all shadow-inner text-lg font-bold tracking-wider placeholder:text-gray-300 dark:placeholder:text-gray-700">
            </div>
            
            <div class="flex flex-col gap-3 pt-2">
                <button type="submit" class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-black py-4 rounded-2xl shadow-xl hover:shadow-indigo-500/30 transition-all transform hover:scale-[1.02] active:scale-95 text-lg uppercase tracking-widest">
                    Confirm Arrival
                </button>
                <a href="customer_dashboard.php" class="w-full text-center py-4 text-gray-400 dark:text-gray-500 font-bold hover:text-indigo-500 transition-colors uppercase tracking-widest text-sm">
                    Go Back
                </a>
            </div>
        </form>
    </div>

    <script>
        function toggleTheme() {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark')
                localStorage.theme = 'light'
            } else {
                document.documentElement.classList.add('dark')
                localStorage.theme = 'dark'
            }
        }
    </script>
</body>
</html>
