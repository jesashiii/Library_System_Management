<?php
require_once '../config/database.php';
require_once '../config/semester_manager.php';

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../login.php');
    exit();
}

$semesterManager = new SemesterManager();
$database = new Database();
$db = $database->getConnection();

$current_semester = $semesterManager->getCurrentSemester();
$message = '';
$error = '';

// Handle clearance operations
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'process_clearance':
            $user_id = $_POST['user_id'] ?? '';
            $semester_id = $_POST['semester_id'] ?? $current_semester['id'];
            
            if (!empty($user_id)) {
                $result = $semesterManager->processTeacherClearance($user_id, $semester_id, $_SESSION['user_id']);
                if ($result['success']) $message = $result['message'];
                else $error = $result['message'];
            }
            break;
        case 'mark_book_lost':
            $transaction_id = $_POST['transaction_id'] ?? '';
            $book_price = $_POST['book_price'] ?? '';
            
            if (!empty($transaction_id) && !empty($book_price)) {
                $result = $semesterManager->markBookAsLost($transaction_id, $book_price);
                if ($result['success']) $message = $result['message'];
                else $error = $result['message'];
            }
            break;
        case 'process_book_payment':
            $transaction_id = $_POST['transaction_id'] ?? '';
            $amount_paid = $_POST['amount_paid'] ?? '';
            
            if (!empty($transaction_id) && !empty($amount_paid)) {
                $result = $semesterManager->processBookPricePayment($transaction_id, $amount_paid);
                if ($result['success']) $message = $result['message'];
                else $error = $result['message'];
            }
            break;
    }
}

// Get pending clearances
$pending_clearances = $semesterManager->getPendingClearances($current_semester['id']);

// Teachers with active books
$query = "SELECT DISTINCT u.id, u.first_name, u.last_name, u.role, COUNT(t.id) as active_books_count
          FROM users u JOIN transactions t ON u.id = t.user_id 
          WHERE u.role='teacher' AND t.semester_id=? AND t.transaction_type='borrow' AND t.status='active'
          GROUP BY u.id, u.first_name, u.last_name, u.role
          ORDER BY u.first_name, u.last_name";
$stmt = $db->prepare($query);
$stmt->execute([$current_semester['id']]);
$teachers_with_books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Students with active books
$query = "SELECT DISTINCT u.id, u.first_name, u.last_name, u.role, COUNT(t.id) as active_books_count
          FROM users u JOIN transactions t ON u.id = t.user_id 
          WHERE u.role='student' AND t.semester_id=? AND t.transaction_type='borrow' AND t.status='active'
          GROUP BY u.id, u.first_name, u.last_name, u.role
          ORDER BY u.first_name, u.last_name";
$stmt = $db->prepare($query);
$stmt->execute([$current_semester['id']]);
$students_with_books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lost books requiring payment
$query = "SELECT t.*, u.first_name, u.last_name, u.role, b.title, b.author, b.price
          FROM transactions t 
          JOIN users u ON t.user_id=u.id 
          JOIN books b ON t.book_id=b.id 
          WHERE t.semester_id=? AND t.status='lost' AND t.book_price_paid_boolean=FALSE
          ORDER BY t.transaction_date DESC";
$stmt = $db->prepare($query);
$stmt->execute([$current_semester['id']]);
$lost_books = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Clearance Management - Smart Library</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#fdfaf6] font-sans">

<!-- Navigation -->
<nav class="bg-white shadow-md">
    <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-[#a19071]">Clearance Management</h1>
    </div>
</nav>

<div class="max-w-7xl mx-auto py-6 px-4">

    <!-- Messages -->
    <?php if($message): ?>
        <div class="mb-4 bg-[#c3a980]/20 border border-[#c3a980] text-[#a19071] px-4 py-3 rounded">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Current Semester Info -->
    <div class="bg-white border-l-4 border-[#c3a980] rounded p-4 mb-6 shadow">
        <h2 class="font-semibold text-[#a19071] text-lg">Current Semester: <?php echo htmlspecialchars($current_semester['name']); ?></h2>
        <p class="text-sm text-gray-600">Period: <?php echo date('M d, Y', strtotime($current_semester['start_date'])); ?> - <?php echo date('M d, Y', strtotime($current_semester['end_date'])); ?></p>
    </div>

    <!-- Teachers Table -->
    <div class="bg-white shadow rounded-lg mb-6">
        <div class="px-6 py-4 border-b border-[#c3a980]">
            <h2 class="text-lg font-semibold text-[#a19071]">Teachers Requiring Clearance</h2>
            <p class="text-sm text-gray-500">Teachers must return all books before semester clearance</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-[#c3a980]/20">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm font-medium text-[#a19071] uppercase tracking-wider">Teacher</th>
                        <th class="px-6 py-3 text-left text-sm font-medium text-[#a19071] uppercase tracking-wider">Active Books</th>
                        <th class="px-6 py-3 text-left text-sm font-medium text-[#a19071] uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-sm font-medium text-[#a19071] uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if(empty($teachers_with_books)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-gray-500">No teachers with active books</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($teachers_with_books as $teacher): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $teacher['active_books_count']; ?> book(s)</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending Clearance</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="viewTeacherBooks(<?php echo $teacher['id']; ?>)" class="text-[#a19071] hover:text-[#c3a980] font-semibold">View Books</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Students Table -->
    <div class="bg-white shadow rounded-lg mb-6">
        <div class="px-6 py-4 border-b border-[#c3a980]">
            <h2 class="text-lg font-semibold text-[#a19071]">Students with Active Books</h2>
            <p class="text-sm text-gray-500">Students can borrow up to 3 books per semester</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-[#c3a980]/20">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm font-medium text-[#a19071] uppercase tracking-wider">Student</th>
                        <th class="px-6 py-3 text-left text-sm font-medium text-[#a19071] uppercase tracking-wider">Active Books</th>
                        <th class="px-6 py-3 text-left text-sm font-medium text-[#a19071] uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-sm font-medium text-[#a19071] uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if(empty($students_with_books)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-gray-500">No students with active books</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($students_with_books as $student): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $student['active_books_count']; ?>/3 book(s)</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs font-semibold rounded-full <?php echo $student['active_books_count']>=3 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                    <?php echo $student['active_books_count']>=3 ? 'Limit Reached' : 'Within Limit'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="viewStudentBooks(<?php echo $student['id']; ?>)" class="text-[#a19071] hover:text-[#c3a980] font-semibold">View Books</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Lost Books Table -->
    <div class="bg-white shadow rounded-lg mb-6">
        <div class="px-6 py-4 border-b border-[#c3a980]">
            <h2 class="text-lg font-semibold text-[#a19071]">Lost Books Requiring Payment</h2>
            <p class="text-sm text-gray-500">Students must pay book price for lost books</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-[#c3a980]/20">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm font-medium text-[#a19071] uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-sm font-medium text-[#a19071] uppercase tracking-wider">Book</th>
                        <th class="px-6 py-3 text-left text-sm font-medium text-[#a19071] uppercase tracking-wider">Book Price</th>
                        <th class="px-6 py-3 text-left text-sm font-medium text-[#a19071] uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if(empty($lost_books)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-gray-500">No lost books requiring payment</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($lost_books as $book): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800">
                                <?php echo htmlspecialchars($book['first_name'].' '.$book['last_name']); ?>
                                <br><span class="text-xs text-gray-500">(<?php echo ucfirst($book['role']); ?>)</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($book['title']); ?>
                                <br><span class="text-xs text-gray-400">by <?php echo htmlspecialchars($book['author']); ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                ₱<?php echo number_format($book['price'],2); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="openPaymentModal(<?php echo $book['id']; ?>, <?php echo $book['price']; ?>)" class="text-[#a19071] hover:text-[#c3a980] font-semibold">Process Payment</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Payment Modal -->
<div id="paymentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <h3 class="text-lg font-semibold text-[#a19071] mb-4">Process Book Price Payment</h3>
        <form method="POST" id="paymentForm">
            <input type="hidden" name="action" value="process_book_payment">
            <input type="hidden" name="transaction_id" id="payment_transaction_id">
            <div class="mb-4">
                <label for="amount_paid" class="block text-sm font-medium text-gray-700">Amount Paid</label>
                <input type="number" name="amount_paid" id="amount_paid" step="0.01" required 
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-[#c3a980] focus:border-[#a19071] sm:text-sm">
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closePaymentModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">Cancel</button>
                <button type="submit" class="bg-[#a19071] text-white px-4 py-2 rounded hover:bg-[#c3a980]">Process Payment</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPaymentModal(transactionId, bookPrice){
    document.getElementById('payment_transaction_id').value = transactionId;
    document.getElementById('amount_paid').value = bookPrice;
    document.getElementById('paymentModal').classList.remove('hidden');
}
function closePaymentModal(){
    document.getElementById('paymentModal').classList.add('hidden');
}

// View books modal (teacher/student)
function viewTeacherBooks(userId){fetch(`get_user_books.php?user_id=${userId}&type=teacher`).then(r=>r.json()).then(data=>{if(data.success) showBooksModal(data.books,'Teacher');else alert('Error loading books: '+data.message)}).catch(e=>alert('Error: '+e));}
function viewStudentBooks(userId){fetch(`get_user_books.php?user_id=${userId}&type=student`).then(r=>r.json()).then(data=>{if(data.success) showBooksModal(data.books,'Student');else alert('Error loading books: '+data.message)}).catch(e=>alert('Error: '+e));}

function showBooksModal(books,userType){
    let modalContent = `<div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-3/4 shadow-lg rounded-md bg-white">
            <h3 class="text-lg font-semibold text-[#a19071] mb-4">${userType} Active Books</h3>
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-[#c3a980]/20"><tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-[#a19071] uppercase tracking-wider">Book</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-[#a19071] uppercase tracking-wider">Borrowed Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-[#a19071] uppercase tracking-wider">Due Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-[#a19071] uppercase tracking-wider">Status</th>
            </tr></thead><tbody class="bg-white divide-y divide-gray-200">`;
    if(books.length===0){modalContent+=`<tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No active books</td></tr>`;}
    else{books.forEach(book=>{const overdue=new Date(book.due_date)<new Date();modalContent+=`<tr>
        <td class="px-6 py-4 text-sm font-medium text-gray-800">${book.title}<br><span class="text-xs text-gray-400">by ${book.author}</span></td>
        <td class="px-6 py-4 text-sm text-gray-500">${new Date(book.transaction_date).toLocaleDateString()}</td>
        <td class="px-6 py-4 text-sm text-gray-500">${new Date(book.due_date).toLocaleDateString()}${overdue?'<span class="text-red-600 text-xs">(Overdue)</span>':''}</td>
        <td class="px-6 py-4"><span class="px-2 inline-flex text-xs font-semibold rounded-full ${overdue?'bg-red-100 text-red-800':'bg-green-100 text-green-800'}">${overdue?'Overdue':'Active'}</span></td>
    </tr>`})}
    modalContent+=`</tbody></table></div><div class="mt-4 flex justify-end">
        <button onclick="closeBooksModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">Close</button>
    </div></div></div>`;
    document.body.insertAdjacentHTML('beforeend',modalContent);
}
function closeBooksModal(){const modal=document.querySelector('.fixed.inset-0.bg-gray-600');if(modal) modal.remove();}
</script>

</body>
</html>
