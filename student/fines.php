<?php
require_once '../config/database.php';
require_once '../config/penalty_manager.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$penalty_manager = new PenaltyManager();

// Get user's outstanding fines
$outstanding_fines = $penalty_manager->getUserOutstandingFines($_SESSION['user_id']);

// Calculate total outstanding amount
$total_outstanding = array_sum(array_column($outstanding_fines, 'amount'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Fines - Smart Library</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
    :root {
        --primary-color: #c3a980;
        --secondary-color: #a19071;
    }
    .btn-primary { background-color: var(--primary-color); color: white; }
    .btn-primary:hover { background-color: var(--secondary-color); }
    .badge-red { background-color: #c3a980; color: white; }
    .badge-yellow { background-color: #a19071; color: white; }
</style>
</head>
<body class="bg-gray-100 font-sans">

<!-- Navigation -->
<nav class="bg-white shadow-lg">
    <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between">
        <h1 class="text-xl font-semibold text-gray-800">My Fines</h1>
    </div>
</nav>

<div class="max-w-7xl mx-auto py-6 px-4">

    <!-- Summary Card -->
    <div class="bg-white shadow rounded-lg p-6 mb-6 flex justify-between items-center">
        <div>
            <h2 class="text-lg font-medium text-gray-900">Outstanding Fines</h2>
            <p class="text-sm text-gray-500">Total amount due</p>
        </div>
        <div class="text-right">
            <div class="text-3xl font-bold text-red-600">₱<?php echo number_format($total_outstanding, 2); ?></div>
            <div class="text-sm text-gray-500"><?php echo count($outstanding_fines); ?> fine(s)</div>
        </div>
    </div>

    <!-- Fines Table -->
    <div class="bg-white shadow rounded-lg overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Book</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($outstanding_fines)): ?>
                <tr>
                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                        <div class="flex flex-col items-center">
                            <svg class="h-12 w-12 text-green-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="text-lg font-medium text-gray-900">No outstanding fines!</p>
                            <p class="text-sm text-gray-500">You're all caught up with your library obligations.</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($outstanding_fines as $fine): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            <?php echo htmlspecialchars($fine['title']); ?><br>
                            <span class="text-xs text-gray-400">by <?php echo htmlspecialchars($fine['author']); ?></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo date('M d, Y', strtotime($fine['due_date'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span class="px-2 py-1 rounded-full text-xs badge-red">
                                <?php echo ucfirst($fine['reason']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            ₱<?php echo number_format($fine['amount'], 2); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span class="px-2 py-1 rounded-full text-xs badge-yellow">
                                <?php echo ucfirst($fine['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
   

</body>
</html>
