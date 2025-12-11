<?php
session_start();
require_once 'classes/Authentication.php';

// Redirect if already logged in
if (Authentication::isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$auth = new Authentication();
$errors = [];
$success_message = '';

if ($_POST) {
    $result = $auth->register($_POST);
    
    if ($result['success']) {
        $success_message = $result['message'];
        $_POST = []; // clear form
    } else {
        $errors = $result['errors'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register - Smart Library</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdn.jsdelivr.net/npm/flowbite@2.2.1/dist/flowbite.min.css" rel="stylesheet" />
<style>
:root {
    --primary: #c3a980;
    --primary-dark: #a19071;
}
body { background: linear-gradient(to bottom right, #f5f3ef, #e3dfd3); }
.success-animation { animation: fadeIn 0.5s ease-in; }
@keyframes fadeIn { from {opacity:0; transform:translateY(-10px);} to {opacity:1; transform:translateY(0);} }
.loading-spinner { animation: spin 1s linear infinite; }
@keyframes spin { from { transform: rotate(0deg);} to { transform: rotate(360deg);} }
.form-field { transition: all 0.3s ease; }
.form-field:focus { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(195,169,128,0.3); }
</style>
</head>
<body class="min-h-screen flex items-center justify-center">
<div class="max-w-md w-full space-y-8">

    <div class="text-center">
       
        <h2 class="mt-6 text-3xl font-extrabold text-gray-900">Smart Library System</h2>
        <p class="mt-2 text-sm text-gray-600">Create your account to get started</p>
    </div>

    <form class="mt-8 space-y-6" method="POST" id="registerForm" onsubmit="return validateForm()">

        <?php if ($success_message): ?>
            <div class="bg-[var(--primary)]/20 border-l-4 border-[var(--primary)] p-4 mb-4 success-animation">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-[var(--primary-dark)]" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-[var(--primary-dark)]"><?php echo htmlspecialchars($success_message); ?></p>
                        <div class="mt-2">
                            <a href="login.php" class="text-sm font-medium text-[var(--primary-dark)] hover:text-[var(--primary)]">
                                Click here to login →
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($errors['general'])): ?>
            <div class="bg-[var(--primary-dark)]/20 border-l-4 border-[var(--primary-dark)] p-4 mb-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-[var(--primary-dark)]" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-[var(--primary-dark)]"><?php echo htmlspecialchars($errors['general']); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                <input id="first_name" name="first_name" type="text" required 
                       value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                       class="form-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-[var(--primary)] focus:border-[var(--primary)] sm:text-sm <?php echo isset($errors['first_name']) ? 'border-[var(--primary-dark)]' : ''; ?>">
                <?php if (isset($errors['first_name'])): ?>
                    <p class="mt-1 text-sm text-[var(--primary-dark)]"><?php echo htmlspecialchars($errors['first_name']); ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                <input id="last_name" name="last_name" type="text" required 
                       value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                       class="form-field mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-[var(--primary)] focus:border-[var(--primary)] sm:text-sm <?php echo isset($errors['last_name']) ? 'border-[var(--primary-dark)]' : ''; ?>">
                <?php if (isset($errors['last_name'])): ?>
                    <p class="mt-1 text-sm text-[var(--primary-dark)]"><?php echo htmlspecialchars($errors['last_name']); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
            <input id="username" name="username" type="text" required 
                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-[var(--primary)] focus:border-[var(--primary)] sm:text-sm <?php echo isset($errors['username']) ? 'border-[var(--primary-dark)]' : ''; ?>">
            <?php if (isset($errors['username'])): ?>
                <p class="mt-1 text-sm text-[var(--primary-dark)]"><?php echo htmlspecialchars($errors['username']); ?></p>
            <?php endif; ?>
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
            <input id="email" name="email" type="email" required 
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-[var(--primary)] focus:border-[var(--primary)] sm:text-sm <?php echo isset($errors['email']) ? 'border-[var(--primary-dark)]' : ''; ?>">
            <?php if (isset($errors['email'])): ?>
                <p class="mt-1 text-sm text-[var(--primary-dark)]"><?php echo htmlspecialchars($errors['email']); ?></p>
            <?php endif; ?>
        </div>

        <div>
            <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
            <select id="role" name="role" required 
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-[var(--primary)] focus:border-[var(--primary)] sm:text-sm <?php echo isset($errors['role']) ? 'border-[var(--primary-dark)]' : ''; ?>">
                <option value="">Select your role</option>
                <option value="student" <?php echo (($_POST['role'] ?? '') === 'student') ? 'selected' : ''; ?>>Student</option>
                <option value="teacher" <?php echo (($_POST['role'] ?? '') === 'teacher') ? 'selected' : ''; ?>>Teacher</option>
            </select>
            <?php if (isset($errors['role'])): ?>
                <p class="mt-1 text-sm text-[var(--primary-dark)]"><?php echo htmlspecialchars($errors['role']); ?></p>
            <?php endif; ?>
        </div>

        <div id="student_id_field" style="display: none;">
            <label for="student_id" class="block text-sm font-medium text-gray-700">Student ID</label>
            <input id="student_id" name="student_id" type="text" 
                   value="<?php echo htmlspecialchars($_POST['student_id'] ?? ''); ?>"
                   placeholder="e.g., 1349802"
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-[var(--primary)] focus:border-[var(--primary)] sm:text-sm <?php echo isset($errors['student_id']) ? 'border-[var(--primary-dark)]' : ''; ?>">
            <?php if (isset($errors['student_id'])): ?>
                <p class="mt-1 text-sm text-[var(--primary-dark)]"><?php echo htmlspecialchars($errors['student_id']); ?></p>
            <?php endif; ?>
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
            <input id="password" name="password" type="password" required 
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-[var(--primary)] focus:border-[var(--primary)] sm:text-sm <?php echo isset($errors['password']) ? 'border-[var(--primary-dark)]' : ''; ?>">
            <?php if (isset($errors['password'])): ?>
                <p class="mt-1 text-sm text-[var(--primary-dark)]"><?php echo htmlspecialchars($errors['password']); ?></p>
            <?php endif; ?>
        </div>

        <div>
            <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm Password</label>
            <input id="confirm_password" name="confirm_password" type="password" required 
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-[var(--primary)] focus:border-[var(--primary)] sm:text-sm">
        </div>

        <div>
            <button type="submit" id="submit-btn"
                    class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-[var(--primary)] hover:bg-[var(--primary-dark)] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[var(--primary-dark)] transition-all duration-200 transform hover:scale-105">
                <span id="btn-text">Create Account</span>
                <span id="btn-loading" class="hidden">
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-[var(--primary-dark)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Creating Account...
                </span>
            </button>
        </div>

        <div class="text-center">
            <p class="text-sm text-gray-600">Already have an account? 
                <a href="login.php" class="font-medium text-[var(--primary-dark)] hover:text-[var(--primary)]">Log in here</a>
            </p>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/flowbite@2.2.1/dist/flowbite.min.js"></script>
<script>
function validateForm() {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const role = document.getElementById('role').value;
    const studentId = document.getElementById('student_id').value;

    clearErrors();
    let isValid = true;

    if (password.length < 6) { showError('password','Password must be at least 6 characters'); isValid=false; }
    if (password !== confirmPassword) { showError('confirm_password','Passwords do not match'); isValid=false; }
    if (role==='student' && studentId.trim()==='') { showError('student_id','Student ID required'); isValid=false; }

    const email = document.getElementById('email').value;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) { showError('email','Please enter a valid email'); isValid=false; }

    if (isValid) showLoading();
    return isValid;
}

function showError(fieldId,message){
    const field = document.getElementById(fieldId);
    const errorDiv = document.createElement('div');
    errorDiv.className='mt-1 text-sm text-[var(--primary-dark)] flex items-center';
    errorDiv.innerHTML=`<svg class="h-4 w-4 mr-1 text-[var(--primary-dark)]" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
    </svg>${message}`;
    errorDiv.id=`error-${fieldId}`;
    field.parentNode.appendChild(errorDiv);
    field.classList.add('border-[var(--primary-dark)]');
}

function clearErrors(){
    document.querySelectorAll('[id^="error-"]').forEach(el=>el.remove());
    document.querySelectorAll('.border-[var(--primary-dark)]').forEach(el=>el.classList.remove('border-[var(--primary-dark)]'));
}

function showLoading(){
    document.getElementById('btn-text').classList.add('hidden');
    document.getElementById('btn-loading').classList.remove('hidden');
    document.getElementById('submit-btn').disabled=true;
}

document.getElementById('confirm_password').addEventListener('input',function(){
    const password=document.getElementById('password').value;
    this.setCustomValidity(password!==this.value?'Passwords do not match':'');
});

document.getElementById('password').addEventListener('input',function(){
    const confirmPassword=document.getElementById('confirm_password');
    if(confirmPassword.value) confirmPassword.dispatchEvent(new Event('input'));
});

document.getElementById('role').addEventListener('change',function(){
    const f=document.getElementById('student_id_field'); 
    const i=document.getElementById('student_id');
    if(this.value==='student'){ f.style.display='block'; i.required=true; } 
    else { f.style.display='none'; i.required=false; i.value=''; }
});

document.addEventListener('DOMContentLoaded',function(){
    const roleSelect=document.getElementById('role');
    const f=document.getElementById('student_id_field'); const i=document.getElementById('student_id');
    if(roleSelect.value==='student'){ f.style.display='block'; i.required=true; }
});
</script>
</body>
</html>
