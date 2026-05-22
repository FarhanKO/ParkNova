<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}
require 'db.php';

$user_id = $_SESSION['user_id'];
$msg = '';
$error = '';

// Fetch current data
$stmt = $pdo->prepare("SELECT * FROM User WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    
    try {
        $stmt = $pdo->prepare("UPDATE User SET full_name = ?, email = ?, phone = ? WHERE user_id = ?");
        $stmt->execute([$fullname, $email, $phone, $user_id]);
        
        // Log activity
        $log_stmt = $pdo->prepare("INSERT INTO SYS_ACT_Log (user_id, actions) VALUES (?, 'Updated profile')");
        $log_stmt->execute([$user_id]);
        
        $msg = "Profile updated successfully!";
        // Refresh local user data
        $user['full_name'] = $fullname;
        $user['email'] = $email;
        $user['phone'] = $phone;
    } catch (Exception $e) {
        $error = "Update failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile - Smart Parking</title>
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
<body class="bg-gray-50 dark:bg-gray-900 transition-colors duration-300 min-h-screen">
    <nav class="bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 text-white p-4 flex justify-between items-center shadow-xl">
        <h1 class="text-xl font-black italic tracking-tighter">PARK<span class="text-yellow-300">PRO</span></h1>
        <div class="flex items-center gap-4">
            <button onclick="toggleTheme()" class="p-2 rounded-full hover:bg-white/20 transition-all active:scale-90" title="Toggle Dark/Light Mode">
                <svg id="theme-btn-dark" class="w-6 h-6 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707m12.728 12.728L5.123 5.123z"></path></svg>
                <svg id="theme-btn-light" class="w-6 h-6 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
            </button>
            <a href="<?php echo $_SESSION['role'] == 'admin' ? 'admin_dashboard.php' : 'customer_dashboard.php'; ?>" 
               class="bg-white/20 hover:bg-white/30 backdrop-blur-md px-4 py-1.5 rounded-full text-sm font-bold transition-all transform hover:scale-105">
               Dashboard
            </a>
        </div>
    </nav>

    <div class="container mx-auto p-6 max-w-2xl">
        <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-500">
            <div class="bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-600 p-8 text-white relative">
                <div class="flex items-center gap-6">
                    <div class="w-24 h-24 rounded-2xl bg-white/20 backdrop-blur-lg flex items-center justify-center text-4xl font-black shadow-inner ring-4 ring-white/10">
                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                    </div>
                    <div>
                        <h2 class="text-3xl font-black tracking-tight"><?php echo htmlspecialchars($user['username']); ?></h2>
                        <p class="opacity-80 font-medium">Member since <?php echo date('M Y', strtotime($user['created_at'])); ?></p>
                        <span class="inline-block mt-3 px-3 py-1 bg-yellow-400 text-indigo-900 rounded-lg text-[10px] uppercase font-black tracking-widest shadow-lg"><?php echo $user['user_type']; ?></span>
                    </div>
                </div>
            </div>

            <div class="p-8">
                <?php if($msg): ?>
                    <div class="bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 p-4 rounded-2xl mb-6 flex items-center gap-3 border border-green-200 dark:border-green-800 animate-bounce">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                        <span class="font-bold"><?php echo $msg; ?></span>
                    </div>
                <?php endif; ?>

                <?php if($error): ?>
                    <div class="bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 p-4 rounded-2xl mb-6 border border-red-200 dark:border-red-800 font-bold">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
                    <div>
                        <label class="block text-xs font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2 ml-1">Full Name</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" 
                               class="w-full p-4 bg-gray-50 dark:bg-gray-900 border-2 border-transparent focus:border-indigo-500 dark:text-white rounded-2xl focus:outline-none transition-all shadow-inner">
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2 ml-1">Email Address</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" 
                                   class="w-full p-4 bg-gray-50 dark:bg-gray-900 border-2 border-transparent focus:border-indigo-500 dark:text-white rounded-2xl focus:outline-none transition-all shadow-inner">
                        </div>
                        <div>
                            <label class="block text-xs font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2 ml-1">Phone Number</label>
                            <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" 
                                   class="w-full p-4 bg-gray-50 dark:bg-gray-900 border-2 border-transparent focus:border-indigo-500 dark:text-white rounded-2xl focus:outline-none transition-all shadow-inner">
                        </div>
                    </div>

                    <div class="pt-6">
                        <button type="submit" class="w-full bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 text-white font-black py-4 rounded-2xl shadow-xl hover:shadow-indigo-500/30 transition-all transform hover:scale-[1.02] active:scale-95 text-lg uppercase tracking-widest">
                            Update Profile
                        </button>
                    </div>
                </form>
            </div>
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
