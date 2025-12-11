<?php
require_once 'database.php';

class SemesterManager {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Get current semester
     */
    public function getCurrentSemester() {
        $query = "SELECT * FROM semesters WHERE is_current = TRUE LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all semesters
     */
    public function getAllSemesters() {
        $query = "SELECT * FROM semesters ORDER BY start_date DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create new semester
     */
    public function createSemester($name, $start_date, $end_date) {
        try {
            $this->db->beginTransaction();
            
            // Set all other semesters as not current
            $query = "UPDATE semesters SET is_current = FALSE";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            // Create new semester as current
            $query = "INSERT INTO semesters (name, start_date, end_date, is_current) VALUES (?, ?, ?, TRUE)";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$name, $start_date, $end_date]);
            
            $this->db->commit();
            return ['success' => true, 'message' => 'Semester created successfully!'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => 'Error creating semester: ' . $e->getMessage()];
        }
    }
    
    /**
     * Set semester as current
     */
    public function setCurrentSemester($semester_id) {
        try {
            $this->db->beginTransaction();
            
            // Set all semesters as not current
            $query = "UPDATE semesters SET is_current = FALSE";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            // Set selected semester as current
            $query = "UPDATE semesters SET is_current = TRUE WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$semester_id]);
            
            $this->db->commit();
            return ['success' => true, 'message' => 'Current semester updated successfully!'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => 'Error updating semester: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get student's borrowing count for current semester
     */
    public function getStudentBorrowingCount($user_id, $semester_id = null) {
        if (!$semester_id) {
            $current_semester = $this->getCurrentSemester();
            $semester_id = $current_semester['id'];
        }
        
        $query = "SELECT COUNT(*) as count FROM transactions 
                  WHERE user_id = ? AND semester_id = ? AND transaction_type = 'borrow' AND status = 'active'";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$user_id, $semester_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'];
    }
    
    /**
     * Check if student can borrow more books
     */
    public function canStudentBorrow($user_id, $semester_id = null) {
        $current_semester = $this->getCurrentSemester();
        $semester_id = $semester_id ?: $current_semester['id'];
        
        $borrowing_count = $this->getStudentBorrowingCount($user_id, $semester_id);
        $limit = 3; // Students can borrow up to 3 books per semester
        
        return $borrowing_count < $limit;
    }
    
    /**
     * Get teacher's active books for semester
     */
    public function getTeacherActiveBooks($user_id, $semester_id = null) {
        if (!$semester_id) {
            $current_semester = $this->getCurrentSemester();
            $semester_id = $current_semester['id'];
        }
        
        $query = "SELECT t.*, b.title, b.author, b.price 
                  FROM transactions t 
                  JOIN books b ON t.book_id = b.id 
                  WHERE t.user_id = ? AND t.semester_id = ? AND t.transaction_type = 'borrow' AND t.status = 'active'";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$user_id, $semester_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check if teacher has returned all books for semester
     */
    public function isTeacherCleared($user_id, $semester_id = null) {
        if (!$semester_id) {
            $current_semester = $this->getCurrentSemester();
            $semester_id = $current_semester['id'];
        }
        
        $active_books = $this->getTeacherActiveBooks($user_id, $semester_id);
        return empty($active_books);
    }
    
    /**
     * Process semester clearance for teacher
     */
    public function processTeacherClearance($user_id, $semester_id, $cleared_by) {
        try {
            $this->db->beginTransaction();
            
            // Check if teacher has active books
            if (!$this->isTeacherCleared($user_id, $semester_id)) {
                return ['success' => false, 'message' => 'Teacher still has active books. Must return all books first.'];
            }
            
            // Create clearance record
            $query = "INSERT INTO clearances (user_id, semester_id, status, cleared_at, cleared_by) 
                      VALUES (?, ?, 'cleared', NOW(), ?)";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$user_id, $semester_id, $cleared_by]);
            
            $this->db->commit();
            return ['success' => true, 'message' => 'Teacher cleared successfully!'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => 'Error processing clearance: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get clearance status for user
     */
    public function getClearanceStatus($user_id, $semester_id = null) {
        if (!$semester_id) {
            $current_semester = $this->getCurrentSemester();
            $semester_id = $current_semester['id'];
        }
        
        $query = "SELECT * FROM clearances WHERE user_id = ? AND semester_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$user_id, $semester_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all pending clearances
     */
    public function getPendingClearances($semester_id = null) {
        if (!$semester_id) {
            $current_semester = $this->getCurrentSemester();
            $semester_id = $current_semester['id'];
        }
        
        $query = "SELECT c.*, u.first_name, u.last_name, u.role 
                  FROM clearances c 
                  JOIN users u ON c.user_id = u.id 
                  WHERE c.semester_id = ? AND c.status = 'pending' 
                  ORDER BY c.created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$semester_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Mark book as lost and require payment
     */
    public function markBookAsLost($transaction_id, $book_price) {
        try {
            $this->db->beginTransaction();
            
            // Update transaction status
            $query = "UPDATE transactions SET status = 'lost', book_price_paid_boolean = FALSE WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$transaction_id]);
            
            // Create fine record for book price
            $query = "INSERT INTO fines (user_id, transaction_id, amount, reason, status) 
                      SELECT user_id, ?, ?, 'book_price', 'pending' FROM transactions WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$transaction_id, $book_price, $transaction_id]);
            
            $this->db->commit();
            return ['success' => true, 'message' => 'Book marked as lost. Payment required.'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => 'Error marking book as lost: ' . $e->getMessage()];
        }
    }
    
    /**
     * Process book price payment
     */
    public function processBookPricePayment($transaction_id, $amount_paid) {
        try {
            $this->db->beginTransaction();
            
            // Get transaction details
            $query = "SELECT * FROM transactions WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$transaction_id]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$transaction) {
                throw new Exception("Transaction not found");
            }
            
            // Get book price
            $query = "SELECT price FROM books WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$transaction['book_id']]);
            $book = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($amount_paid < $book['price']) {
                throw new Exception("Insufficient payment amount");
            }
            
            // Update transaction
            $query = "UPDATE transactions SET book_price_paid = ?, book_price_paid_boolean = TRUE WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$book['price'], $transaction_id]);
            
            // Update fine status
            $query = "UPDATE fines SET status = 'paid', paid_at = NOW() 
                      WHERE transaction_id = ? AND reason = 'book_price'";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$transaction_id]);
            
            $this->db->commit();
            return ['success' => true, 'message' => 'Book price payment processed successfully!'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => 'Error processing payment: ' . $e->getMessage()];
        }
    }
}
?>
