<?php
require_once '../config/database.php';
require_once '../config/user_manager.php';

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['librarian', 'staff'])) {
    header('Location: ../login.php');
    exit();
}

$userManager = new UserManager();
$page = $_GET['page'] ?? 1;
$search = $_GET['search'] ?? '';
$action = $_POST['action'] ?? '';
$message = '';
$error = '';

// Handle user actions
if ($_POST) {
    switch ($action) {
        case 'create_user':
            $result = $userManager->registerUser($_POST);
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = implode(', ', $result['errors']);
            }
            break;
            
        case 'update_user':
            $userId = $_POST['user_id'] ?? '';
            if ($userId) {
                $result = $userManager->updateUser($userId, $_POST);
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = implode(', ', $result['errors']);
                }
            }
            break;
            
        case 'delete_user':
            $userId = $_POST['user_id'] ?? '';
            if ($userId) {
                $result = $userManager->deleteUser($userId);
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = implode(', ', $result['errors']);
                }
            }
            break;
            
    }
}

// Get users data
$usersData = $userManager->getUsers($page, 10, $search);
$users = $usersData['users'];
$totalPages = $usersData['total_pages'];

// Get user statistics
$stats = $userManager->getUserStatistics();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Smart Library</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/flowbite@2.2.1/dist/flowbite.min.css" rel="stylesheet" />
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-gray-800">User Management</h1>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <?php if ($message): ?>
            <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Users</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo $stats['total_users']; ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>


            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" />
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Students</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo $stats['students']; ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Teachers</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo $stats['teachers']; ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Add User -->
        <div class="bg-white shadow rounded-lg p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-medium text-gray-900">Users</h2>
                <button onclick="openCreateModal()" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
                    Add New User
                </button>
            </div>
            
            <!-- Search -->
            <form method="GET" class="mb-4">
                <div class="flex">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search users..." 
                           class="flex-1 px-3 py-2 border border-gray-300 rounded-l-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    <button type="submit" class="bg-gray-500 text-white px-4 py-2 rounded-r-md hover:bg-gray-600">
                        Search
                    </button>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="bg-white shadow rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    @<?php echo htmlspecialchars($user['username']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($user['email']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                <button onclick="openEditModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['first_name']); ?>', '<?php echo htmlspecialchars($user['last_name']); ?>', '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['email']); ?>', '<?php echo $user['role']; ?>', <?php echo $user['is_active'] ? 'true' : 'false'; ?>)" 
                                        class="text-indigo-600 hover:text-indigo-900">Edit</button>
                                
                                <button onclick="deleteUser(<?php echo $user['id']; ?>)" 
                                        class="text-red-600 hover:text-red-900">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                <div class="flex-1 flex justify-between sm:hidden">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" 
                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Previous
                        </a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" 
                           class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing page <span class="font-medium"><?php echo $page; ?></span> of <span class="font-medium"><?php echo $totalPages; ?></span>
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo $i == $page ? 'bg-indigo-50 border-indigo-500 text-indigo-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </nav>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create User Modal -->
    <div id="createModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Add New User</h3>
                <form method="POST" id="createForm">
                    <input type="hidden" name="action" value="create_user">
                    <div class="mb-4">
                        <label for="create_first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                        <input type="text" name="first_name" id="create_first_name" required 
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                    <div class="mb-4">
                        <label for="create_last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                        <input type="text" name="last_name" id="create_last_name" required 
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                    <div class="mb-4">
                        <label for="create_username" class="block text-sm font-medium text-gray-700">Username</label>
                        <input type="text" name="username" id="create_username" required 
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                    <div class="mb-4">
                        <label for="create_email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" id="create_email" required 
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                    <div class="mb-4">
                        <label for="create_role" class="block text-sm font-medium text-gray-700">Role</label>
                        <select name="role" id="create_role" required 
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="">Select Role</option>
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                            <option value="librarian">Librarian</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="create_password" class="block text-sm font-medium text-gray-700">Password</label>
                        <input type="password" name="password" id="create_password" required 
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeCreateModal()" 
                                class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">Cancel</button>
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Edit User</h3>
                <form method="POST" id="editForm">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="mb-4">
                        <label for="edit_first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                        <input type="text" name="first_name" id="edit_first_name" required 
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                    <div class="mb-4">
                        <label for="edit_last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                        <input type="text" name="last_name" id="edit_last_name" required 
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                    <div class="mb-4">
                        <label for="edit_username" class="block text-sm font-medium text-gray-700">Username</label>
                        <input type="text" name="username" id="edit_username" required 
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                    <div class="mb-4">
                        <label for="edit_email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" id="edit_email" required 
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                    <div class="mb-4">
                        <label for="edit_role" class="block text-sm font-medium text-gray-700">Role</label>
                        <select name="role" id="edit_role" required 
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                            <option value="librarian">Librarian</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="edit_password" class="block text-sm font-medium text-gray-700">New Password (leave blank to keep current)</label>
                        <input type="password" name="password" id="edit_password" 
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeEditModal()" 
                                class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">Cancel</button>
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Delete User</h3>
                <p class="text-sm text-gray-500 mb-4">Are you sure you want to delete this user? This action cannot be undone.</p>
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeDeleteModal()" 
                                class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">Cancel</button>
                        <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openCreateModal() {
            document.getElementById('createModal').classList.remove('hidden');
        }

        function closeCreateModal() {
            document.getElementById('createModal').classList.add('hidden');
        }

        function openEditModal(id, firstName, lastName, username, email, role, isActive) {
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_first_name').value = firstName;
            document.getElementById('edit_last_name').value = lastName;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_role').value = role;
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        function deleteUser(id) {
            document.getElementById('delete_user_id').value = id;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }
    </script>
</body>
</html>
