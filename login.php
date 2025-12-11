<?php
session_start();
require_once 'classes/Authentication.php';

$error_message = '';

if ($_POST) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $auth = new Authentication();
    $result = $auth->login($username, $password);
    
    if ($result['success']) {
        Authentication::setSession($result['user']);
        Authentication::redirectToDashboard($result['user']['role']);
    } else {
        $error_message = $result['message'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Smart Library - Login</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
    :root {
        --primary: #c3a980;
        --primary-dark: #a19071;
        --text-dark: #4a4033;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: #fdfaf5;
    }

    .logo-section {
        background: var(--primary);
        color: white;
    }

    .logo-circle {
        background-color: var(--primary-dark);
    }

    .btn-primary {
        background-color: var(--primary);
        color: white;
    }
    .btn-primary:hover {
        background-color: var(--primary-dark);
    }

    .badge-red {
        background-color: var(--primary);
        color: white;
    }
</style>
</head>
<body class="min-h-screen flex">

    <!-- Left: Logo & Info -->
    <div class="hidden md:flex w-1/2 logo-section flex-col justify-center items-center p-10">
        <div class="flex items-center space-x-6">
            
            <h1 class="text-6xl font-bold text-white">Smart Library</h1>
        </div>
        <p class="mt-6 text-white text-lg text-center max-w-xs">Access your library account, check fines, and manage your borrowed books anytime, anywhere.</p>
    </div>

    <!-- Right: Login Form -->
    <div class="flex w-full md:w-1/2 justify-center items-center p-10">
        <div class="bg-white p-10 rounded-2xl shadow-lg w-full max-w-md">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Login to your account</h2>
            
            <?php if ($error_message): ?>
            <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-4">
                <p class="text-red-700 text-sm"><?php echo htmlspecialchars($error_message); ?></p>
            </div>
            <?php endif; ?>

            <form method="POST" id="loginForm" onsubmit="return validateLoginForm()" class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="username">Username or Email</label>
                    <input type="text" name="username" id="username" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-[var(--primary)] focus:border-[var(--primary)]">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="password">Password</label>
                    <input type="password" name="password" id="password" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-[var(--primary)] focus:border-[var(--primary)]">
                </div>

                <button type="submit" class="w-full py-3 rounded-md btn-primary font-medium hover:scale-105 transition-all">
                    Log In
                </button>

                <p class="text-xs text-gray-500 mt-3">Don't have an account? 
                    <a href="register.php" class="text-[var(--primary-dark)] hover:text-[var(--primary)] font-medium">Register here</a>
                </p>
            </form>
            </div>
        </div>
    </div>

<script>
function validateLoginForm() {
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value.trim();
    if (!username || !password) {
        alert('Please fill in all fields.');
        return false;
    }
    return true;
}
</script>

</body>
</html>
