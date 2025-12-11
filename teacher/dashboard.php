<?php
session_start();
require_once '../classes/Authentication.php';
require_once '../classes/Reservation.php';
require_once '../classes/Transaction.php';
require_once '../classes/BookManager.php';
require_once '../config/semester_manager.php';

// Check role
Authentication::requireRole('teacher');

$database = new Database();
$db = $database->getConnection();
$reservationManager = new Reservation($db);
$transactionManager = new Transaction($db);
$bookManager = new BookManager($db);
$semesterManager = new SemesterManager();
$current_semester = $semesterManager->getCurrentSemester();

// Handle Actions
if ($_POST) {
    $action = $_POST['action'] ?? '';

    if ($action === 'reserve') {
        $book_id = $_POST['book_id'] ?? '';

        if ($book_id) {
            $result = $reservationManager->createReservation($_SESSION['user_id'], $book_id, $current_semester['id']);
            $success_message = $result['success'] ? $result['message'] : ($result['errors']['general'] ?? "Reservation failed.");
        }
    }

    if ($action === 'cancel_reservation') {
        $reservation_id = $_POST['reservation_id'] ?? '';
        $cancelled = $reservationManager->cancelReservation($reservation_id);
        $success_message = $cancelled ? "Reservation cancelled successfully." : null;
        $error_message = $cancelled ? null : "Failed to cancel reservation.";
    }
}

// Refresh Lists
$available_books = $bookManager->getAvailableBooks();
$reservations = Reservation::getActiveReservations($db, $_SESSION['user_id'], $current_semester['id']);
$borrowed_books = Transaction::getActiveBorrows($db, $_SESSION['user_id'], $current_semester['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Smart Library</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>

        :root {
    --primary: #c3a980;
    --primary-dark: #a19071;
    --bg-light: #ffffff;
    --text-dark: #4b4538;
}
        .section { display: none; }
        .theme-btn {
            background-color: #a19071;
            color: white;
        }
        .theme-btn:hover {
            background-color: #c3a980;
        }
        .theme-border {
            border-color: #c3a980;
        }
        .theme-title {
            color: #a19071;
        }
    </style>
</head>

<body class="bg-gray-100">

<!-- TOP NAV -->
<header class="w-full bg-white shadow-lg p-5 flex justify-between items-center border-b theme-border">
    <h1 class="text-2xl font-semibold theme-title"><span class="text-gray-700 font-medium">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</span></h1>

    <div class="flex items-center gap-5">
        <a href="../logout.php" class="px-4 py-2 rounded theme-btn">Logout</a>
    </div>
</header>

<div class="flex">

    <!-- SIDEBAR -->
    <aside class="w-72 bg-white shadow-xl h-screen border-r theme-border p-6 space-y-4">

        <button onclick="showSection('borrowed')" class="w-full px-4 py-3 text-left font-semibold rounded-lg shadow-md theme-btn">
            My Borrowed Books
        </button>

        <button onclick="showSection('reservations')" class="w-full px-4 py-3 text-left font-semibold rounded-lg shadow-md theme-btn">
            My Reservations
        </button>

        <button onclick="showSection('available')" class="w-full px-4 py-3 text-left font-semibold rounded-lg shadow-md theme-btn">
            Available Books
        </button>

    </aside>


    <!-- MAIN CONTENT -->
    <main class="flex-1 p-10 space-y-6">

        <!-- Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>


        <!-- 📚 Borrowed Books -->
        <div id="borrowed" class="section">
            <div class="bg-white p-8 shadow-lg rounded-lg border theme-border">
                <h2 class="text-2xl font-semibold theme-title mb-4">My Borrowed Books</h2>

                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3">Book</th>
                            <th class="px-6 py-3">Borrowed</th>
                            <th class="px-6 py-3">Due Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($borrowed_books)): ?>
                            <tr><td colspan="3" class="text-center py-5 text-gray-500">No borrowed books</td></tr>
                        <?php else: foreach ($borrowed_books as $book): ?>
                            <tr class="border-b">
                                <td class="px-6 py-4"><?php echo htmlspecialchars($book['title']); ?></td>
                                <td class="px-6 py-4"><?php echo date('M d, Y', strtotime($book['transaction_date'])); ?></td>
                                <td class="px-6 py-4"><?php echo date('M d, Y', strtotime($book['due_date'])); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>


        <!-- 📝 Reservations -->
        <div id="reservations" class="section">
            <div class="bg-white p-8 shadow-lg rounded-lg border theme-border">
                <h2 class="text-2xl font-semibold theme-title mb-4">My Reservations</h2>

                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3">Book</th>
                            <th class="px-6 py-3">Reserved</th>
                            <th class="px-6 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reservations)): ?>
                            <tr><td colspan="3" class="text-center py-5 text-gray-500">No active reservations</td></tr>
                        <?php else: foreach ($reservations as $res): ?>
                            <tr class="border-b">
                                <td class="px-6 py-4"><?= htmlspecialchars($res['title']) ?></td>
                                <td class="px-6 py-4"><?= date('M d, Y', strtotime($res['reservation_date'])) ?></td>
                                <td class="px-6 py-4">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="cancel_reservation">
                                        <input type="hidden" name="reservation_id" value="<?= $res['id'] ?>">
                                        <button class="text-red-600 hover:text-red-800">Cancel</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>


        <!-- 📘 Available Books -->
        <div id="available" class="section">
            <div class="bg-white p-8 shadow-lg rounded-lg border theme-border">
                <h2 class="text-2xl font-semibold theme-title mb-4">Available Books</h2>

                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3">Title</th>
                            <th class="px-6 py-3">Author</th>
                            <th class="px-6 py-3">ISBN</th>
                            <th class="px-6 py-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($available_books as $book): ?>
                            <tr class="border-b">
                                <td class="px-6 py-4"><?= htmlspecialchars($book['title']) ?></td>
                                <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($book['author']) ?></td>
                                <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($book['isbn']) ?></td>
                                <td class="px-6 py-4 text-center">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="reserve">
                                        <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                                        <button class="theme-btn px-3 py-1 rounded">Reserve</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            </div>
        </div>

    </main>
</div>

<script>
function showSection(id) {
    document.querySelectorAll('.section').forEach(sec => sec.style.display = 'none');
    document.getElementById(id).style.display = 'block';
}
</script>

</body>
</html>
