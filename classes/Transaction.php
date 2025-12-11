<?php
require_once __DIR__ . '/../config/database.php';

class Transaction {
    private $db;
    private $id;
    private $userId;
    private $bookId;
    private $semesterId;
    private $transactionType;
    private $status;
    private $transactionDate;
    private $dueDate;
    private $returnDate;
    private $penaltyAmount;
    private $penaltyPaid;
    private $bookPricePaid;
    private $bookPricePaidBoolean;

    public function __construct($db = null) {
        if ($db === null) {
            $database = new Database();
            $this->db = $database->getConnection();
        } else {
            $this->db = $db;
        }
    }

    // Getters
    public function getId() { return $this->id; }
    public function getUserId() { return $this->userId; }
    public function getBookId() { return $this->bookId; }
    public function getSemesterId() { return $this->semesterId; }
    public function getTransactionType() { return $this->transactionType; }
    public function getStatus() { return $this->status; }
    public function getTransactionDate() { return $this->transactionDate; }
    public function getDueDate() { return $this->dueDate; }
    public function getReturnDate() { return $this->returnDate; }
    public function getPenaltyAmount() { return $this->penaltyAmount; }
    public function getPenaltyPaid() { return $this->penaltyPaid; }
    public function getBookPricePaid() { return $this->bookPricePaid; }
    public function getBookPricePaidBoolean() { return $this->bookPricePaidBoolean; }

    // Setters
    public function setId($id) { $this->id = $id; }
    public function setUserId($userId) { $this->userId = $userId; }
    public function setBookId($bookId) { $this->bookId = $bookId; }
    public function setSemesterId($semesterId) { $this->semesterId = $semesterId; }
    public function setTransactionType($transactionType) { $this->transactionType = $transactionType; }
    public function setStatus($status) { $this->status = $status; }
    public function setTransactionDate($transactionDate) { $this->transactionDate = $transactionDate; }
    public function setDueDate($dueDate) { $this->dueDate = $dueDate; }
    public function setReturnDate($returnDate) { $this->returnDate = $returnDate; }
    public function setPenaltyAmount($penaltyAmount) { $this->penaltyAmount = $penaltyAmount; }
    public function setPenaltyPaid($penaltyPaid) { $this->penaltyPaid = $penaltyPaid; }
    public function setBookPricePaid($bookPricePaid) { $this->bookPricePaid = $bookPricePaid; }
    public function setBookPricePaidBoolean($bookPricePaidBoolean) { $this->bookPricePaidBoolean = $bookPricePaidBoolean; }

    /**
     * Load transaction data from database by ID
     */
    public function loadById($id) {
        $query = "SELECT * FROM transactions WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$id]);
        
        if ($transaction = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->id = $transaction['id'];
            $this->userId = $transaction['user_id'];
            $this->bookId = $transaction['book_id'];
            $this->semesterId = $transaction['semester_id'];
            $this->transactionType = $transaction['transaction_type'];
            $this->status = $transaction['status'];
            $this->transactionDate = $transaction['transaction_date'];
            $this->dueDate = $transaction['due_date'];
            $this->returnDate = $transaction['return_date'];
            $this->penaltyAmount = $transaction['penalty_amount'];
            $this->penaltyPaid = $transaction['penalty_paid'];
            $this->bookPricePaid = $transaction['book_price_paid'];
            $this->bookPricePaidBoolean = $transaction['book_price_paid_boolean'];
            return true;
        }
        return false;
    }

    /**
     * Save transaction to database (insert or update)
     */
    public function save() {
        if ($this->id) {
            return $this->update();
        } else {
            return $this->insert();
        }
    }

    /**
     * Insert new transaction
     */
    private function insert() {
        try {
            $this->db->beginTransaction();
            
            $query = "INSERT INTO transactions (user_id, book_id, semester_id, transaction_type, status, due_date) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $this->userId,
                $this->bookId,
                $this->semesterId,
                $this->transactionType,
                $this->status,
                $this->dueDate
            ]);
            
            $this->id = $this->db->lastInsertId();
            
            // If this is a borrow transaction, update book status and cancel reservations
            if ($this->transactionType === 'borrow') {
                // Update book status to borrowed
                $query = "UPDATE books SET status = 'borrowed' WHERE id = ?";
                $stmt = $this->db->prepare($query);
                $stmt->execute([$this->bookId]);
                
                // Cancel any active reservations for this book
                $query = "UPDATE reservations SET status = 'cancelled' WHERE book_id = ? AND status = 'active'";
                $stmt = $this->db->prepare($query);
                $stmt->execute([$this->bookId]);
            }
            
            $this->db->commit();
            
            return ['success' => true, 'transaction_id' => $this->id, 'message' => 'Transaction created successfully!'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'errors' => ['general' => 'Transaction creation failed: ' . $e->getMessage()]];
        }
    }

    /**
     * Update existing transaction
     */
    private function update() {
        try {
            $this->db->beginTransaction();
            
            $query = "UPDATE transactions SET status = ?, return_date = ?, penalty_amount = ?, penalty_paid = ?, book_price_paid = ?, book_price_paid_boolean = ? WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $this->status,
                $this->returnDate,
                $this->penaltyAmount,
                $this->penaltyPaid,
                $this->bookPricePaid,
                $this->bookPricePaidBoolean,
                $this->id
            ]);
            
            // If completing a transaction, update book status to available
            if ($this->status === 'completed') {
                $query = "UPDATE books SET status = 'available' WHERE id = ?";
                $stmt = $this->db->prepare($query);
                $stmt->execute([$this->bookId]);
            }
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Transaction updated successfully!'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'errors' => ['general' => 'Transaction update failed: ' . $e->getMessage()]];
        }
    }

    /**
     * Process book borrowing
     */
    public function borrowBook($userId, $bookId, $semesterId, $userRole) {
        try {
            // Set due date based on user role
            $dueDate = $userRole === 'student' ? 
                date('Y-m-d', strtotime('+14 days')) : 
                date('Y-m-d', strtotime('+30 days'));
            
            // Check if book is available
            $query = "SELECT status FROM books WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$bookId]);
            $book = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$book || $book['status'] !== 'available') {
                return ['success' => false, 'errors' => ['general' => 'Book is no longer available']];
            }
            
            // Create transaction record
            $this->userId = $userId;
            $this->bookId = $bookId;
            $this->semesterId = $semesterId;
            $this->transactionType = 'borrow';
            $this->status = 'active';
            $this->dueDate = $dueDate;
            
            $result = $this->insert();
            return $result;
            
        } catch (Exception $e) {
            return ['success' => false, 'errors' => ['general' => 'Error processing borrowing: ' . $e->getMessage()]];
        }
    }

    /**
     * Process book return
     */
    public function returnBook() {
        try {
            // Update transaction status
            $this->status = 'completed';
            $this->returnDate = date('Y-m-d H:i:s');
            
            $result = $this->update();
            
            if ($result['success']) {
                // Create return transaction record
                $returnTransaction = new Transaction($this->db);
                $returnTransaction->setUserId($this->userId);
                $returnTransaction->setBookId($this->bookId);
                $returnTransaction->setSemesterId($this->semesterId);
                $returnTransaction->setTransactionType('return');
                $returnTransaction->setStatus('completed');
                
                $returnTransaction->insert();
                
                return $result;
            } else {
                return $result;
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'errors' => ['general' => 'Error processing return: ' . $e->getMessage()]];
        }
    }

    /**
     * Check if transaction is overdue
     */
    public function isOverdue() {
        if ($this->status !== 'active' || !$this->dueDate) {
            return false;
        }
        
        return strtotime($this->dueDate) < time();
    }

    /**
     * Get days overdue
     */
    public function getDaysOverdue() {
        if (!$this->isOverdue()) {
            return 0;
        }
        
        $dueDate = new DateTime($this->dueDate);
        $currentDate = new DateTime();
        
        return $currentDate->diff($dueDate)->days;
    }

    /**
     * Get active borrows for user
     */
    public static function getActiveBorrows($db, $userId, $semesterId = null) {
        $query = "SELECT t.*, b.title, b.author, b.price 
                  FROM transactions t 
                  JOIN books b ON t.book_id = b.id 
                  WHERE t.user_id = ? AND t.transaction_type = 'borrow' AND t.status = 'active'";
        
        $params = [$userId];
        
        if ($semesterId) {
            $query .= " AND t.semester_id = ?";
            $params[] = $semesterId;
        }
        
        $query .= " ORDER BY t.transaction_date DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all active borrows for current semester
     */
    public static function getAllActiveBorrows($db, $semesterId) {
        $query = "SELECT t.*, u.first_name, u.last_name, u.role, u.student_id, b.title, b.author, b.price, s.name as semester_name
                  FROM transactions t 
                  JOIN users u ON t.user_id = u.id 
                  JOIN books b ON t.book_id = b.id 
                  JOIN semesters s ON t.semester_id = s.id
                  WHERE t.transaction_type = 'borrow' AND t.status = 'active' AND t.semester_id = ?
                  ORDER BY t.transaction_date DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$semesterId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get overdue transactions
     */
    public static function getOverdueTransactions($db) {
        $query = "SELECT t.*, u.first_name, u.last_name, u.role, b.title, b.author
                  FROM transactions t 
                  JOIN users u ON t.user_id = u.id 
                  JOIN books b ON t.book_id = b.id
                  WHERE t.transaction_type = 'borrow' 
                  AND t.status = 'active'
                  AND t.due_date < CURDATE()
                  ORDER BY t.due_date ASC";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get transaction as array
     */
    public function toArray() {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'book_id' => $this->bookId,
            'semester_id' => $this->semesterId,
            'transaction_type' => $this->transactionType,
            'status' => $this->status,
            'transaction_date' => $this->transactionDate,
            'due_date' => $this->dueDate,
            'return_date' => $this->returnDate,
            'penalty_amount' => $this->penaltyAmount,
            'penalty_paid' => $this->penaltyPaid,
            'book_price_paid' => $this->bookPricePaid,
            'book_price_paid_boolean' => $this->bookPricePaidBoolean
        ];
    }
}
?>
