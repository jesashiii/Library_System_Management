<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../classes/Authentication.php';
require_once '../classes/Reservation.php';
require_once '../classes/Transaction.php';
require_once '../classes/BookManager.php';
require_once '../config/semester_manager.php';

// Check if user is logged in and is student
Authentication::requireRole('student');

$database = new Database();
$db = $database->getConnection();

$reservationManager = new Reservation($db);
$transactionManager = new Transaction($db);
$bookManager = new BookManager($db);
$semesterManager = new SemesterManager();
$current_semester = $semesterManager->getCurrentSemester();

$success_message = "";
$error_message = "";

// Handle form actions
if ($_POST) {
    $action = $_POST['action'] ?? '';

    // Create reservation
    if ($action === 'reserve') {
        $book_id = $_POST['book_id'] ?? 0;

        if (!empty($book_id)) {
            $result = $reservationManager->createReservation($_SESSION['user_id'], $book_id, $current_semester['id']);
            if ($result['success']) {
                $success_message = $result['message'];
            } else {
                $error_message = $result['errors']['general'] ?? "Reservation failed.";
            }
        }
    }

    // Cancel reservation
    if ($action === 'cancel_reservation') {
        $reservation_id = $_POST['reservation_id'] ?? 0;

        if (!empty($reservation_id)) {
            $result = $reservationManager->cancelReservation($reservation_id, $_SESSION['user_id']);
            if ($result['success']) {
                $success_message = "Reservation canceled.";
            } else {
                $error_message = $result['errors']['general'] ?? "Unable to cancel reservation.";
            }
        }
    }
}

// Fetch data
$available_books = $bookManager->getAvailableBooks();
$reservations = Reservation::getActiveReservations($db, $_SESSION['user_id'], $current_semester['id']);
$borrowed_books = Transaction::getActiveBorrows($db, $_SESSION['user_id'], $current_semester['id']);

// Borrow limit
$borrow_count = $semesterManager->getStudentBorrowingCount($_SESSION['user_id'], $current_semester['id']);
$can_borrow = $semesterManager->canStudentBorrow($_SESSION['user_id'], $current_semester['id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Dashboard - Smart Library</title>
<script src="https://cdn.tailwindcss.com"></script>

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

.btn-section { background-color: var(--primary); color: white; padding: 0.5rem 1rem; border-radius: 0.375rem; transition: 0.2s; }
.btn-section:hover { background-color: var(--primary-dark); }

.card { border-top: 4px solid var(--primary); background-color: white; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }

.table-header { background-color: var(--primary); color: white; }
.section { display: none; margin-bottom: 1.5rem; }
</style>
</head>

<body>

<!-- Navigation -->
<nav class="shadow-lg">
    <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
        <h1 class="text-xl font-semibold"> <span>Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</span></h1>

        <div class="flex items-center space-x-4">
            <a href="../logout.php" class="px-4 py-2 rounded hover:bg-red-700">Logout</a>
        </div>
    </div>
</nav>

<div class="max-w-7xl mx-auto py-6 px-4">

<?php if(isset($success_message) && $success_message): ?>
<div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
    <?php echo htmlspecialchars($success_message); ?>
</div>
<?php endif; ?>

<?php if(isset($error_message) && $error_message): ?>
<div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
    <?php echo htmlspecialchars($error_message); ?>
</div>
<?php endif; ?>

<!-- Semester Card -->
<div class="card p-4 mb-6">
    <h3 class="text-sm font-medium text-gray-700">Current Semester:  
        <?php echo htmlspecialchars($current_semester['name']); ?>
    </h3>
    <p class="mt-1 text-sm text-gray-500">
        Period: <?php echo date('M d, Y', strtotime($current_semester['start_date'])); ?> -
        <?php echo date('M d, Y', strtotime($current_semester['end_date'])); ?>
    </p>
</div>

<!-- Borrowing Status -->
<div class="card p-4 mb-6">
    <h3 class="text-sm font-medium text-gray-700">Borrowing Status (This Semester)</h3>
    <p class="mt-2 text-gray-600">Books borrowed: <?php echo $borrow_count; ?>/3</p>

    <?php if (!$can_borrow): ?>
        <p class="text-red-600 font-medium">You have reached the maximum borrowing limit.</p>
    <?php else: ?>
        <p class="text-green-600 font-medium">You can borrow <?php echo (3 - $borrow_count); ?> more book(s).</p>
    <?php endif; ?>
</div>

<!-- Quick Action Buttons -->
<div class="flex flex-wrap gap-4 mb-6">
    <button onclick="showSection('fines')" class="btn-section">My Fines</button>
    <button onclick="showSection('borrowed')" class="btn-section">My Borrowed Books</button>
    <button onclick="showSection('reservations')" class="btn-section">My Reservations</button>
    <button onclick="showSection('available')" class="btn-section">Available Books</button>
</div>

<!-- FINES SECTION -->
<div id="fines" class="section card p-6">
    <h2 class="text-lg font-medium mb-4">My Fines</h2>
    <?php include 'fines.php'; ?>
</div>

<!-- BORROWED BOOKS (FIXED FULL SECTION) -->
<div id="borrowed" class="section card p-6 overflow-x-auto">
    <h2 class="text-lg font-medium mb-4">Borrowed Books</h2>

    <table class="min-w-full divide-y divide-gray-200">
        <thead class="table-header">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium tracking-wider">Book</th>
                <th class="px-6 py-3 text-left text-xs font-medium tracking-wider">Borrowed Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium tracking-wider">Due Date</th>
            </tr>
        </thead>

        <tbody class="bg-white divide-y divide-gray-200">
            <?php if (empty($borrowed_books)): ?>
                <tr>
                    <td colspan="3" class="px-6 py-4 text-center text-gray-500">
                        No borrowed books
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($borrowed_books as $book): ?>
                <tr>
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">
                        <?php echo htmlspecialchars($book['title']); ?><br>
                        <span class="text-xs text-gray-400">by <?php echo htmlspecialchars($book['author']); ?></span>
                    </td>

                    <td class="px-6 py-4 text-sm text-gray-500">
                        <?php echo date('M d, Y', strtotime($book['transaction_date'])); ?>
                    </td>

                    <td class="px-6 py-4 text-sm text-gray-500">
                        <?php echo date('M d, Y', strtotime($book['due_date'])); ?>
                        <?php if (strtotime($book['due_date']) < time()): ?>
                            <span class="text-red-600 text-xs">(Overdue)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- RESERVATIONS SECTION -->
<div id="reservations" class="section card p-6 overflow-x-auto">
    <h2 class="text-lg font-medium mb-4">My Reservations</h2>

    <table class="min-w-full divide-y divide-gray-200">
        <thead class="table-header">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium">Book</th>
                <th class="px-6 py-3 text-left text-xs font-medium">Reserved Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium">Actions</th>
            </tr>
        </thead>

        <tbody class="bg-white divide-y divide-gray-200">
        <?php if (empty($reservations)): ?>
            <tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">No active reservations</td></tr>
        <?php else: ?>
        <?php foreach ($reservations as $res): ?>
            <tr>
                <td class="px-6 py-4 text-sm font-medium text-gray-900">
                    <?php echo htmlspecialchars($res['title']); ?><br>
                    <span class="text-xs text-gray-400">by <?php echo htmlspecialchars($res['author']); ?></span>
                </td>

                <td class="px-6 py-4 text-sm text-gray-500">
                    <?php echo date('M d, Y', strtotime($res['reservation_date'])); ?>
                </td>

                <td class="px-6 py-4 text-sm font-medium">
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="cancel_reservation">
                        <input type="hidden" name="reservation_id" value="<?php echo $res['id']; ?>">
                        <button type="submit" class="text-red-600 hover:text-red-900">Cancel</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- AVAILABLE BOOKS SECTION -->
<div id="available" class="section card p-6 overflow-x-auto">
    <h2 class="text-lg font-medium mb-4">Available Books</h2>

    <table class="min-w-full divide-y divide-gray-200">
        <thead class="table-header">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium">Title</th>
                <th class="px-6 py-3 text-left text-xs font-medium">Author</th>
                <th class="px-6 py-3 text-left text-xs font-medium">ISBN</th>
                <th class="px-6 py-3 text-left text-xs font-medium">Actions</th>
            </tr>
        </thead>

        <tbody class="bg-white divide-y divide-gray-200">
        <?php foreach ($available_books as $book): ?>
        <tr>
            <td class="px-6 py-4 text-sm font-medium text-gray-900">
                <?php echo htmlspecialchars($book['title']); ?>
            </td>
            <td class="px-6 py-4 text-sm text-gray-500">
                <?php echo htmlspecialchars($book['author']); ?>
            </td>
            <td class="px-6 py-4 text-sm text-gray-500">
                <?php echo htmlspecialchars($book['isbn']); ?>
            </td>
            <td class="px-6 py-4 text-sm font-medium">
                <form method="POST" class="inline">
                    <input type="hidden" name="action" value="reserve">
                    <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                    <button type="submit" class="text-indigo-600 hover:text-indigo-900">Reserve</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

</div>

<script>
function showSection(sectionId) {
    document.querySelectorAll('.section').forEach(sec => sec.style.display='none');
    document.getElementById(sectionId).style.display = 'block';
}
</script>

</body>
</html>
