<?php
session_start();
require_once '../classes/Authentication.php';
require_once '../classes/Transaction.php';
require_once '../classes/BookManager.php';
require_once '../classes/User.php';
require_once '../config/semester_manager.php';

// Check if user is logged in and is staff
Authentication::requireRole('staff');

$auth = new Authentication();
$database = new Database();
$db = $database->getConnection();
$transactionManager = new Transaction($db);
$bookManager = new BookManager($db);
$userManager = new User($db);
$semesterManager = new SemesterManager();
$current_semester = $semesterManager->getCurrentSemester();

// Handle transactions
if ($_POST) {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'borrow':
            $user_id = $_POST['user_id'] ?? '';
            $book_id = $_POST['book_id'] ?? '';
            if (empty($user_id) || empty($book_id)) {
                $error_message = "Please select both a user and a book to borrow.";
            } else {
                if (!$userManager->loadById($user_id)) {
                    $error_message = "User not found.";
                } elseif (!$userManager->getIsActive()) {
                    $error_message = "User account is inactive.";
                } else {
                    $book = $bookManager->getBookById($book_id);
                    if (!$book) $error_message = "Book not found.";
                    elseif ($book['status'] !== 'available') $error_message = "Book is not available.";
                    else {
                        $result = $transactionManager->borrowBook($user_id, $book_id, $current_semester['id'], $userManager->getRole());
                        if ($result['success']) $success_message = "Book '{$book['title']}' borrowed successfully!";
                        else $error_message = $result['errors']['general'] ?? "Error borrowing book.";
                    }
                }
            }
            break;

        case 'return':
            $transaction_id = $_POST['transaction_id'] ?? '';
            if (!empty($transaction_id) && $transactionManager->loadById($transaction_id)) {
                $result = $transactionManager->returnBook();
                if ($result['success']) $success_message = "Book returned successfully!";
                else $error_message = $result['errors']['general'] ?? "Error returning book.";
            } else {
                $error_message = "Transaction not found.";
            }
            break;
    }
}

// Get users and books
$query = "SELECT id, username, first_name, last_name, role, student_id FROM users ORDER BY role, first_name";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$available_books = $bookManager->getAvailableBooks();
$active_borrows = Transaction::getAllActiveBorrows($db, $current_semester['id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Dashboard - Smart Library</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdn.jsdelivr.net/npm/flowbite@2.2.1/dist/flowbite.min.css" rel="stylesheet" />
<style>
:root {
    --primary: #c3a980;
    --primary-dark: #a19071;
    --bg-light: #ffffff;
    --text-dark: #4b4538;
}

body { background-color: var(--bg-light); color: var(--text-dark); font-family: 'Inter', sans-serif; }
nav { background-color: var(--primary-dark); }
nav h1, nav span { color: white; }
nav a { background-color: var(--primary); color: white; transition: 0.2s; }
nav a:hover { background-color: var(--primary-dark); }

.btn-primary { background-color: var(--primary); color: white; transition: 0.2s; }
.btn-primary:hover { background-color: var(--primary-dark); }

.card { border-top: 4px solid var(--primary); background-color: white; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }

.sidebar button { height: 3rem; font-size: 0.875rem; transition: all 0.2s; width: 100%; }
.sidebar button:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }

table thead { background-color: var(--primary); }
table thead th { color: white; }
table tbody tr:hover { background-color: #fdf8f2; transition: 0.2s; }

.section { display: none; } /* hide all sections initially */
</style>
</head>
<body>

<!-- Navigation -->
<nav class="shadow-lg">
  <div class="max-w-7xl mx-auto px-4">
    <div class="flex justify-between h-16 items-center">
        <h1 class="text-xl font-semibold"><span>Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</span></h1>
        <div class="flex items-center space-x-4">
            <a href="../logout.php" class="px-4 py-2 rounded hover:bg-red-700">Logout</a>
        </div>
    </div>
  </div>
</nav>

<div class="max-w-7xl mx-auto py-6 px-4">
<?php if(isset($success_message)): ?>
<div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
    <?php echo htmlspecialchars($success_message); ?>
</div>
<?php endif; ?>
<?php if(isset($error_message)): ?>
<div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
    <?php echo htmlspecialchars($error_message); ?>
</div>
<?php endif; ?>

<!-- Sidebar Buttons -->
<div class="mb-6 flex flex-col space-y-3 w-60 sidebar">
    <button class="btn-primary rounded toggle-section" data-target="clearance">Manage Clearance</button>
    <button class="btn-primary rounded toggle-section" data-target="users">Manage Users</button>
    <button class="btn-primary rounded toggle-section" data-target="penalties">Manage Penalties</button>
    <button class="btn-primary rounded toggle-section" data-target="borrow">Borrow Book</button>
    <button class="btn-primary rounded toggle-section" data-target="active_borrows">Active Borrows</button>
</div>

<!-- Current Semester Info -->
<div class="card p-4 mb-6">
    <h3 class="text-sm font-medium text-gray-700">Current Semester: <?php echo htmlspecialchars($current_semester['name']); ?></h3>
    <p class="mt-1 text-sm text-gray-500">Period: <?php echo date('M d, Y', strtotime($current_semester['start_date'])); ?> - <?php echo date('M d, Y', strtotime($current_semester['end_date'])); ?></p>
</div>

<!-- Sections -->
<div id="clearance" class="section card p-6 mb-6">
    <?php include 'clearance_management.php'; ?>
</div>

<div id="users" class="section card p-6 mb-6">
    <?php include '../admin/user_management.php'; ?>
</div>

<div id="penalties" class="section card p-6 mb-6">
    <?php include 'penalty_management.php'; ?>
</div>

<div id="borrow" class="section card p-6 mb-6">
    <h2 class="text-lg font-semibold mb-4">Borrow Book</h2>
    <form method="POST" class="grid grid-cols-1 gap-4 sm:grid-cols-3" onsubmit="return validateBorrowForm()">
        <input type="hidden" name="action" value="borrow">
        <div>
            <label for="user_id" class="block text-sm font-medium text-gray-700">Select User</label>
            <select name="user_id" id="user_id" required class="mt-1 block w-full border-gray-300 rounded-md">
                <option value="">Select User</option>
                <?php foreach ($users as $user): ?>
                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="book_id" class="block text-sm font-medium text-gray-700">Select Book</label>
            <select name="book_id" id="book_id" required class="mt-1 block w-full border-gray-300 rounded-md">
                <option value="">Select Book</option>
                <?php foreach ($available_books as $book): ?>
                <option value="<?php echo $book['id']; ?>"><?php echo htmlspecialchars($book['title']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="btn-primary w-full">Borrow Book</button>
        </div>
    </form>
</div>

<div id="active_borrows" class="section card p-6 mb-6">
    <h2 class="text-lg font-semibold mb-4">Active Borrows</h2>
    <table class="min-w-full divide-y divide-gray-200">
        <thead>
            <tr class="bg-[var(--primary)] text-white">
                <th class="px-6 py-3 text-left">User</th>
                <th class="px-6 py-3 text-left">Book</th>
                <th class="px-6 py-3 text-left">Borrowed Date</th>
                <th class="px-6 py-3 text-left">Due Date</th>
                <th class="px-6 py-3 text-left">Action</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($active_borrows as $borrow): ?>
            <tr>
                <td class="px-6 py-4"><?php echo htmlspecialchars($borrow['first_name'].' '.$borrow['last_name']); ?></td>
                <td class="px-6 py-4"><?php echo htmlspecialchars($borrow['title']); ?></td>
                <td class="px-6 py-4"><?php echo date('M d, Y', strtotime($borrow['transaction_date'])); ?></td>
                <td class="px-6 py-4"><?php echo date('M d, Y', strtotime($borrow['due_date'])); ?></td>
                <td class="px-6 py-4">
                    <form method="POST">
                        <input type="hidden" name="action" value="return">
                        <input type="hidden" name="transaction_id" value="<?php echo $borrow['id']; ?>">
                        <button type="submit" class="text-green-600 hover:text-green-900">Return</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
// Toggle sections
document.querySelectorAll('.toggle-section').forEach(btn => {
    btn.addEventListener('click', () => {
        const targetId = btn.dataset.target;
        document.querySelectorAll('.section').forEach(sec => sec.style.display = 'none');
        document.getElementById(targetId).style.display = 'block';
    });
});

// Borrow form validation
function validateBorrowForm() {
    const userId = document.getElementById('user_id').value;
    const bookId = document.getElementById('book_id').value;
    if (!userId || !bookId) {
        alert('Please select a user and a book.');
        return false;
    }
    return true;
}
</script>

</body>
</html>
