<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}
require 'db.php';

// Fetch available slots
$stmt = $pdo->query("SELECT * FROM slots WHERE status = 'available'");
$available_slots = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Dashboard - Smart Parking</title>
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
<body class="bg-gray-50 dark:bg-gray-900 transition-colors duration-300">
    <nav class="bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 text-white p-4 flex justify-between items-center shadow-xl">
        <div class="flex items-center gap-4">
            <h1 class="text-xl font-black italic tracking-tighter">PARK<span class="text-yellow-300">PRO</span></h1>
        </div>
        <div class="flex items-center gap-4">
            <button onclick="toggleTheme()" class="p-2 rounded-full hover:bg-white/20 transition-all active:scale-90" title="Toggle Dark/Light Mode">
                <svg id="theme-btn-dark" class="w-6 h-6 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707m12.728 12.728L5.123 5.123z"></path></svg>
                <svg id="theme-btn-light" class="w-6 h-6 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
            </button>
            <span class="hidden md:inline font-medium">Hello, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <a href="profile.php" class="bg-white/20 hover:bg-white/30 backdrop-blur-md px-4 py-1.5 rounded-full text-sm font-bold transition-all transform hover:scale-105">Profile</a>
            <a href="logout.php" class="bg-red-500 hover:bg-red-600 px-4 py-1.5 rounded-full text-sm font-bold shadow-lg transition-all transform hover:scale-105">Logout</a>
        </div>
    </nav>

    <div class="container mx-auto p-6">
        <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
            <h2 class="text-3xl font-black text-gray-800 dark:text-white tracking-tight">Available Slots</h2>
            <div class="flex items-center gap-2 bg-green-100 dark:bg-green-900/30 px-4 py-2 rounded-full border border-green-200 dark:border-green-800">
                <span class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></span>
                <span class="text-sm font-bold text-green-700 dark:text-green-400">Live Updates Enabled</span>
            </div>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-6">
            <?php foreach($available_slots as $slot): ?>
                <div class="group bg-white dark:bg-gray-800 p-6 rounded-3xl shadow-lg border-b-4 border-indigo-500 hover:border-pink-500 transition-all transform hover:-translate-y-2 hover:rotate-1">
                    <div class="w-16 h-16 bg-indigo-100 dark:bg-indigo-900/50 rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform">
                        <span class="text-2xl font-black text-indigo-600 dark:text-indigo-400"><?php echo $slot['slot_code']; ?></span>
                    </div>
                    <p class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-4"><?php echo $slot['type']; ?></p>
                    <a href="booking.php?slot_id=<?php echo $slot['id']; ?>" class="block w-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-bold py-3 rounded-2xl shadow-lg hover:shadow-indigo-500/50 transition-all text-sm">Book Now</a>
                </div>
            <?php endforeach; ?>
            
            <?php if(empty($available_slots)): ?>
                <div class="col-span-full py-20 text-center bg-white dark:bg-gray-800 rounded-3xl shadow-inner border-2 border-dashed border-gray-200 dark:border-gray-700">
                    <p class="text-gray-400 dark:text-gray-500 text-xl font-bold italic">No slots available right now. Check back soon!</p>
                </div>
            <?php endif; ?>
        </div>
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
