<?php

require_once '../config/database.php';
require_once '../config/semester_manager.php';

// Check staff login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$semesterManager = new SemesterManager();
$current_semester = $semesterManager->getCurrentSemester();

$success_message = '';
$error_message = '';

// Handle penalty payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay_penalty') {

    $fine_id = $_POST['fine_id'] ?? null;
    $amount_paid = floatval($_POST['amount_paid'] ?? 0);

    if (!$fine_id || $amount_paid <= 0) {
        $error_message = "Invalid payment data.";
    } else {

        try {
            $db->beginTransaction();

            // 1. Verify fine exists and is pending
            $stmt = $db->prepare("SELECT * FROM fines WHERE id = ? AND status = 'pending'");
            $stmt->execute([$fine_id]);
            $fine = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$fine) {
                throw new Exception("Fine not found or already paid.");
            }

            // 2. Update fines table
            $stmt = $db->prepare("
                UPDATE fines 
                SET status = 'paid',
                    amount_paid = ?,
                    paid_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$amount_paid, $fine_id]);

            // 3. Update related transaction
            $stmt = $db->prepare("
                UPDATE transactions 
                SET penalty_paid = 1 
                WHERE id = ?
            ");
            $stmt->execute([$fine['transaction_id']]);

            $db->commit();
            $success_message = "Penalty payment recorded successfully!";

        } catch (Exception $e) {
            $db->rollBack();
            $error_message = "Payment failed: " . $e->getMessage();
        }
    }
}


// Handle clearance update
if ($_POST && ($_POST['action'] ?? '') === 'update_clearance') {
    $user_id = $_POST['user_id'] ?? '';
    $clearance_status = $_POST['clearance_status'] ?? '';
    $notes = $_POST['notes'] ?? '';

    if (!empty($user_id) && !empty($clearance_status)) {
        try {
            $stmt = $db->prepare("UPDATE clearances SET status=?, notes=?, cleared_by=? 
                                  WHERE user_id=? AND semester_id=?");
            $stmt->execute([$clearance_status, $notes, $_SESSION['user_id'], $user_id, $current_semester['id']]);
            $success_message = "Clearance status updated successfully!";
        } catch (Exception $e) {
            $error_message = "Error updating clearance: " . $e->getMessage();
        }
    }
}

// Fetch fines
$stmt = $db->prepare("SELECT f.*, u.first_name, u.last_name, u.student_id, u.role, 
                             t.due_date, t.transaction_date, b.title as book_title, b.author
                      FROM fines f
                      JOIN users u ON f.user_id=u.id
                      JOIN transactions t ON f.transaction_id=t.id
                      JOIN books b ON t.book_id=b.id
                      ORDER BY f.created_at DESC");
$stmt->execute();
$fines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch clearances
$stmt = $db->prepare("SELECT c.*, u.first_name, u.last_name, u.student_id, u.role
                      FROM clearances c
                      JOIN users u ON c.user_id=u.id
                      WHERE c.semester_id=?
                      ORDER BY c.created_at DESC");
$stmt->execute([$current_semester['id']]);
$clearances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$total_fines = array_sum(array_column($fines, 'amount'));
$paid_fines = array_sum(array_column(array_filter($fines, fn($f)=>$f['status']==='paid'), 'amount'));
$pending_fines = $total_fines - $paid_fines;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Penalty & Clearance Management - Smart Library</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
    :root {
        --primary-color: #c3a980;
        --secondary-color: #a19071;
    }
    .btn-primary { background-color: var(--primary-color); color: white; }
    .btn-primary:hover { background-color: var(--secondary-color); }
    .badge-paid { background-color: #c3a980; color: white; }
    .badge-pending { background-color: #a19071; color: white; }
</style>
</head>
<body class="bg-gray-100 font-sans">

<!-- Navigation -->
<nav class="bg-white shadow-lg">
    <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between">
        <h1 class="text-xl font-semibold text-gray-800">Smart Library - Penalty & Clearance Management</h1>
    </div>
</nav>

<div class="max-w-7xl mx-auto py-6 px-4">

    <!-- Messages -->
    <?php if ($success_message): ?>
        <div class="mb-4 px-4 py-3 rounded bg-green-100 text-green-800 border border-green-400"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="mb-4 px-4 py-3 rounded bg-red-100 text-red-800 border border-red-400"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-white shadow rounded-lg p-5 text-center">
            <h3 class="text-sm font-medium text-gray-500">Total Fines</h3>
            <p class="text-2xl font-bold text-gray-900">₱<?php echo number_format($total_fines,2); ?></p>
        </div>
        <div class="bg-white shadow rounded-lg p-5 text-center">
            <h3 class="text-sm font-medium text-gray-500">Paid Fines</h3>
            <p class="text-2xl font-bold text-gray-900">₱<?php echo number_format($paid_fines,2); ?></p>
        </div>
        <div class="bg-white shadow rounded-lg p-5 text-center">
            <h3 class="text-sm font-medium text-gray-500">Pending Fines</h3>
            <p class="text-2xl font-bold text-gray-900">₱<?php echo number_format($pending_fines,2); ?></p>
        </div>
    </div>

    <!-- Fines Table -->
    <div class="bg-white shadow rounded-lg mb-6 overflow-x-auto">
        <table class="min-  w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Book</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach($fines as $fine): ?>
                <tr>
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">
                        <?php echo htmlspecialchars($fine['first_name'].' '.$fine['last_name']); ?>
                        <br><span class="text-xs text-gray-500"><?php echo ucfirst($fine['role']); ?></span>
                        <?php if ($fine['student_id']): ?>
                            <br><span class="text-xs text-blue-600">ID: <?php echo htmlspecialchars($fine['student_id']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">
                        <?php echo htmlspecialchars($fine['book_title']); ?><br>
                        <span class="text-xs text-gray-400">by <?php echo htmlspecialchars($fine['author']); ?></span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">₱<?php echo number_format($fine['amount'],2); ?></td>
                    <td class="px-6 py-4 text-sm text-gray-500"><?php echo ucfirst($fine['reason']); ?></td>
                    <td class="px-6 py-4 text-sm">
                        <span class="px-2 py-1 rounded-full text-xs <?php echo $fine['status']==='paid'?'badge-paid':'badge-pending'; ?>">
                            <?php echo ucfirst($fine['status']); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm font-medium">
                        <?php if($fine['status']==='pending'): ?>
                        <button onclick="openPaymentModal(<?php echo $fine['id']; ?>,<?php echo $fine['amount']; ?>)" class="btn-primary px-3 py-1 rounded text-white text-sm">Pay</button>
                        <?php else: ?>
                        <span class="text-gray-400">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Clearance Table -->
    <div class="bg-white shadow rounded-lg overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach($clearances as $clearance): ?>
                <tr>
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">
                        <?php echo htmlspecialchars($clearance['first_name'].' '.$clearance['last_name']); ?><br>
                        <span class="text-xs text-gray-500"><?php echo ucfirst($clearance['role']); ?></span>
                        <?php if($clearance['student_id']): ?>
                            <br><span class="text-xs text-blue-600">ID: <?php echo htmlspecialchars($clearance['student_id']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <span class="px-2 py-1 rounded-full text-xs <?php echo $clearance['status']==='cleared'?'badge-paid':'badge-pending'; ?>">
                            <?php echo ucfirst($clearance['status']); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($clearance['notes']); ?></td>
                    <td class="px-6 py-4 text-sm font-medium">
                        <button onclick="openClearanceModal(<?php echo $clearance['user_id']; ?>,'<?php echo $clearance['status']; ?>','<?php echo htmlspecialchars($clearance['notes']); ?>')" class="btn-primary px-3 py-1 rounded text-white text-sm">Update</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center">
    <div class="bg-white rounded-md shadow-lg w-96 p-6">
        <h3 class="text-lg font-semibold mb-4">Process Penalty Payment</h3>
        <form method="POST">
            <input type="hidden" name="action" value="pay_penalty">
            <input type="hidden" name="fine_id" id="payment_fine_id">
            <label class="block text-sm font-medium mb-1">Amount to Pay</label>
            <input type="number" name="amount_paid" id="payment_amount" step="0.01" min="0" required class="w-full border rounded px-3 py-2 mb-4">
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closePaymentModal()" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
                <button type="submit" class="px-4 py-2 btn-primary rounded">Process</button>
            </div>
        </form>
    </div>
</div>

<!-- Clearance Modal -->
<div id="clearanceModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center">
    <div class="bg-white rounded-md shadow-lg w-96 p-6">
        <h3 class="text-lg font-semibold mb-4">Update Clearance Status</h3>
        <form method="POST">
            <input type="hidden" name="action" value="update_clearance">
            <input type="hidden" name="user_id" id="clearance_user_id">
            <label class="block text-sm font-medium mb-1">Status</label>
            <select name="clearance_status" id="clearance_status" class="w-full border rounded px-3 py-2 mb-4" required>
                <option value="pending">Pending</option>
                <option value="cleared">Cleared</option>
                <option value="blocked">Blocked</option>
            </select>
            <label class="block text-sm font-medium mb-1">Notes</label>
            <textarea name="notes" id="clearance_notes" rows="3" class="w-full border rounded px-3 py-2 mb-4"></textarea>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeClearanceModal()" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
                <button type="submit" class="px-4 py-2 btn-primary rounded">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPaymentModal(id, amount){
    document.getElementById('payment_fine_id').value = id;
    document.getElementById('payment_amount').value = amount;
    document.getElementById('paymentModal').classList.remove('hidden');
}
function closePaymentModal(){ document.getElementById('paymentModal').classList.add('hidden'); }

function openClearanceModal(id,status,notes){
    document.getElementById('clearance_user_id').value = id;
    document.getElementById('clearance_status').value = status;
    document.getElementById('clearance_notes').value = notes;
    document.getElementById('clearanceModal').classList.remove('hidden');
}
function closeClearanceModal(){ document.getElementById('clearanceModal').classList.add('hidden'); }

// Close modals on outside click
window.onclick = function(e){
    if(e.target.id==='paymentModal') closePaymentModal();
    if(e.target.id==='clearanceModal') closeClearanceModal();
}
</script>
</body>
</html>
