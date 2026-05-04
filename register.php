<?php
require 'db.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $fullname = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO User (username, password, user_type, full_name, email, phone) VALUES (?, ?, 'Customer', ?, ?, ?)");
        $stmt->execute([$username, $password, $fullname, $email, $phone]);
        header('Location: index.php?msg=Registration Successful'); exit;
    } catch (Exception $e) {
        $msg = "Registration Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - Smart Parking</title>
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
<body class="bg-gray-50 dark:bg-gray-900 transition-colors duration-300 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 p-8 rounded-3xl shadow-2xl w-full max-w-md border-t-8 border-indigo-600 relative">
        <div class="absolute top-0 right-0 p-4">
            <button onclick="toggleTheme()" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-all active:scale-90" title="Toggle Dark/Light Mode">
                <svg id="theme-btn-dark" class="w-5 h-5 hidden dark:block text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707m12.728 12.728L5.123 5.123z"></path></svg>
                <svg id="theme-btn-light" class="w-5 h-5 block dark:hidden text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
            </button>
        </div>

        <div class="text-center mb-8">
            <h2 class="text-3xl font-black text-gray-800 dark:text-white tracking-tight leading-tight">Join <span class="text-indigo-600">ParkPro</span></h2>
            <p class="text-gray-400 dark:text-gray-500 font-bold uppercase text-[10px] tracking-widest mt-2">Create your personal account</p>
        </div>

        <?php if($msg): ?>
            <div class="bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 p-4 rounded-xl mb-4 text-sm font-bold border border-red-200 dark:border-red-800"><?php echo $msg; ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1 ml-1">Username</label>
                <input type="text" name="username" required class="w-full bg-gray-50 dark:bg-gray-900 dark:text-white border-2 border-transparent focus:border-indigo-500 p-3 rounded-2xl focus:outline-none transition-all shadow-inner font-bold">
            </div>
            <div>
                <label class="block text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1 ml-1">Full Name</label>
                <input type="text" name="full_name" required class="w-full bg-gray-50 dark:bg-gray-900 dark:text-white border-2 border-transparent focus:border-indigo-500 p-3 rounded-2xl focus:outline-none transition-all shadow-inner font-bold">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1 ml-1">Email</label>
                    <input type="email" name="email" class="w-full bg-gray-50 dark:bg-gray-900 dark:text-white border-2 border-transparent focus:border-indigo-500 p-3 rounded-2xl focus:outline-none transition-all shadow-inner font-bold">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1 ml-1">Phone</label>
                    <input type="text" name="phone" class="w-full bg-gray-50 dark:bg-gray-900 dark:text-white border-2 border-transparent focus:border-indigo-500 p-3 rounded-2xl focus:outline-none transition-all shadow-inner font-bold">
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1 ml-1">Password</label>
                <input type="password" name="password" required class="w-full bg-gray-50 dark:bg-gray-900 dark:text-white border-2 border-transparent focus:border-indigo-500 p-3 rounded-2xl focus:outline-none transition-all shadow-inner font-bold">
            </div>
            
            <div class="pt-4">
                <button type="submit" class="w-full bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 text-white font-black py-4 rounded-2xl shadow-xl hover:shadow-indigo-500/30 transition-all transform hover:scale-[1.02] active:scale-95 text-sm uppercase tracking-widest">
                    Register Now
                </button>
            </div>
        </form>
        
        <p class="mt-8 text-center text-xs font-bold text-gray-400 dark:text-gray-500">
            Already have an account? <a href="index.php" class="text-indigo-600 dark:text-indigo-400 hover:underline">Sign In</a>
        </p>
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
