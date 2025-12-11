<?php
require_once 'database.php';

class PenaltyManager {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Calculate penalty for overdue books
     */
    public function calculatePenalty($transaction_id) {
        $query = "SELECT t.*, u.role, p.daily_penalty, p.max_penalty, p.grace_period_days 
                  FROM transactions t 
                  JOIN users u ON t.user_id = u.id 
                  JOIN penalty_rates p ON u.role = p.user_role 
                  WHERE t.id = ? AND t.transaction_type = 'borrow' AND t.status = 'active'";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$transaction_id]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$transaction) {
            return 0;
        }
        
        $due_date = new DateTime($transaction['due_date']);
        $current_date = new DateTime();
        $grace_period = $transaction['grace_period_days'];
        
        // Add grace period to due date
        $due_date->add(new DateInterval('P' . $grace_period . 'D'));
        
        if ($current_date <= $due_date) {
            return 0; // No penalty within grace period
        }
        
        $days_overdue = $current_date->diff($due_date)->days;
        $daily_penalty = $transaction['daily_penalty'];
        $max_penalty = $transaction['max_penalty'];
        
        $penalty = $days_overdue * $daily_penalty;
        
        // Cap at maximum penalty
        if ($penalty > $max_penalty) {
            $penalty = $max_penalty;
        }
        
        return round($penalty, 2);
    }
    
    /**
     * Update overdue status for all transactions
     */
    public function updateOverdueStatus() {
        $query = "UPDATE transactions t 
                  JOIN users u ON t.user_id = u.id 
                  JOIN penalty_rates p ON u.role = p.user_role 
                  SET t.status = 'overdue' 
                  WHERE t.transaction_type = 'borrow' 
                  AND t.status = 'active' 
                  AND DATE_ADD(t.due_date, INTERVAL p.grace_period_days DAY) < CURDATE()";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->rowCount();
    }
    
    /**
     * Get all overdue transactions with penalties
     */
    public function getOverdueTransactions() {
        $query = "SELECT t.*, u.first_name, u.last_name, u.role, b.title, b.author,
                         p.daily_penalty, p.max_penalty, p.grace_period_days
                  FROM transactions t 
                  JOIN users u ON t.user_id = u.id 
                  JOIN books b ON t.book_id = b.id
                  JOIN penalty_rates p ON u.role = p.user_role 
                  WHERE t.transaction_type = 'borrow' 
                  AND t.status = 'overdue'
                  ORDER BY t.due_date ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate penalties for each transaction
        foreach ($transactions as &$transaction) {
            $transaction['calculated_penalty'] = $this->calculatePenalty($transaction['id']);
            $transaction['days_overdue'] = $this->getDaysOverdue($transaction['due_date'], $transaction['grace_period_days']);
        }
        
        return $transactions;
    }
    
    /**
     * Get days overdue for a transaction
     */
    private function getDaysOverdue($due_date, $grace_period) {
        $due_date_obj = new DateTime($due_date);
        $current_date = new DateTime();
        
        // Add grace period to due date
        $due_date_obj->add(new DateInterval('P' . $grace_period . 'D'));
        
        if ($current_date <= $due_date_obj) {
            return 0;
        }
        
        return $current_date->diff($due_date_obj)->days;
    }
    
    /**
     * Process penalty payment
     */
    public function processPenaltyPayment($transaction_id, $amount_paid) {
        $this->db->beginTransaction();
        
        try {
            // Get transaction details
            $query = "SELECT * FROM transactions WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$transaction_id]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$transaction) {
                throw new Exception("Transaction not found");
            }
            
            // Calculate current penalty
            $penalty_amount = $this->calculatePenalty($transaction_id);
            
            if ($amount_paid < $penalty_amount) {
                throw new Exception("Insufficient payment amount");
            }
            
            // Update transaction penalty status
            $query = "UPDATE transactions SET penalty_paid = TRUE, penalty_amount = ? WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$penalty_amount, $transaction_id]);
            
            // Create fine record
            $query = "INSERT INTO fines (user_id, transaction_id, amount, reason, status, paid_at) 
                      VALUES (?, ?, ?, 'overdue', 'paid', NOW())";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$transaction['user_id'], $transaction_id, $penalty_amount]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Get user's outstanding fines
     */
    public function getUserOutstandingFines($user_id) {
        $query = "SELECT f.*, t.due_date, b.title, b.author
                  FROM fines f
                  JOIN transactions t ON f.transaction_id = t.id
                  JOIN books b ON t.book_id = b.id
                  WHERE f.user_id = ? AND f.status = 'pending'
                  ORDER BY f.created_at DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$user_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get penalty statistics
     */
    public function getPenaltyStatistics() {
        $query = "SELECT 
                    COUNT(*) as total_overdue,
                    SUM(CASE WHEN f.status = 'pending' THEN f.amount ELSE 0 END) as total_outstanding,
                    SUM(CASE WHEN f.status = 'paid' THEN f.amount ELSE 0 END) as total_collected,
                    AVG(CASE WHEN f.status = 'pending' THEN f.amount ELSE NULL END) as avg_outstanding
                  FROM transactions t
                  LEFT JOIN fines f ON t.id = f.transaction_id
                  WHERE t.status = 'overdue'";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
