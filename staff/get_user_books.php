<?php

require_once '../config/database.php';
require_once '../config/semester_manager.php';

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_GET['user_id'] ?? '';
$type = $_GET['type'] ?? '';

if (empty($user_id) || empty($type)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$semesterManager = new SemesterManager();
$current_semester = $semesterManager->getCurrentSemester();

try {
    // Get user's active books for current semester
    $query = "SELECT t.*, b.title, b.author, b.price 
              FROM transactions t 
              JOIN books b ON t.book_id = b.id 
              WHERE t.user_id = ? AND t.semester_id = ? AND t.transaction_type = 'borrow' AND t.status = 'active' 
              ORDER BY t.transaction_date DESC";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id, $current_semester['id']]);
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'books' => $books]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
