<?php
require_once __DIR__ . '/../config/database.php';

class Reservation {
    private $db;
    private $id;
    private $userId;
    private $bookId;
    private $semesterId;
    private $status;
    private $reservationDate;

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
    public function getStatus() { return $this->status; }
    public function getReservationDate() { return $this->reservationDate; }

    // Setters
    public function setId($id) { $this->id = $id; }
    public function setUserId($userId) { $this->userId = $userId; }
    public function setBookId($bookId) { $this->bookId = $bookId; }
    public function setSemesterId($semesterId) { $this->semesterId = $semesterId; }
    public function setStatus($status) { $this->status = $status; }
    public function setReservationDate($reservationDate) { $this->reservationDate = $reservationDate; }

    /**
     * Load reservation data from database by ID
     */
    public function loadById($id) {
        $query = "SELECT * FROM reservations WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$id]);
        
        if ($reservation = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->id = $reservation['id'];
            $this->userId = $reservation['user_id'];
            $this->bookId = $reservation['book_id'];
            $this->semesterId = $reservation['semester_id'];
            $this->status = $reservation['status'];
            $this->reservationDate = $reservation['reservation_date'];
            return true;
        }
        return false;
    }

    /**
     * Save reservation to database (insert or update)
     */
    public function save() {
        if ($this->id) {
            return $this->update();
        } else {
            return $this->insert();
        }
    }

    /**
     * Insert new reservation
     */
    private function insert() {
        try {
            $this->db->beginTransaction();
            
            $query = "INSERT INTO reservations (user_id, book_id, semester_id, status) 
                      VALUES (?, ?, ?, ?)";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $this->userId,
                $this->bookId,
                $this->semesterId,
                $this->status ?? 'active'
            ]);
            
            $this->id = $this->db->lastInsertId();
            
            // Update book status to reserved
            $query = "UPDATE books SET status = 'reserved' WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$this->bookId]);
            
            $this->db->commit();
            
            return ['success' => true, 'reservation_id' => $this->id, 'message' => 'Reservation created successfully!'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'errors' => ['general' => 'Reservation creation failed: ' . $e->getMessage()]];
        }
    }

    /**
     * Update existing reservation
     */
    private function update() {
        try {
            $this->db->beginTransaction();
            
            $query = "UPDATE reservations SET status = ? WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$this->status, $this->id]);
            
            // If cancelling reservation, update book status back to available
            if ($this->status === 'cancelled') {
                $query = "UPDATE books SET status = 'available' WHERE id = ?";
                $stmt = $this->db->prepare($query);
                $stmt->execute([$this->bookId]);
            }
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Reservation updated successfully!'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'errors' => ['general' => 'Reservation update failed: ' . $e->getMessage()]];
        }
    }

    /**
     * Create new reservation
     */
    public function createReservation($userId, $bookId, $semesterId) {
        try {
            // Check if book is available
            $query = "SELECT status FROM books WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$bookId]);
            $book = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$book || $book['status'] !== 'available') {
                return ['success' => false, 'errors' => ['general' => 'Book is not available for reservation']];
            }
            
            // Check if user already has an active reservation for this book
            $query = "SELECT id FROM reservations WHERE user_id = ? AND book_id = ? AND status = 'active'";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$userId, $bookId]);
            
            if ($stmt->fetch()) {
                return ['success' => false, 'errors' => ['general' => 'You already have an active reservation for this book']];
            }
            
            // Create reservation
            $this->userId = $userId;
            $this->bookId = $bookId;
            $this->semesterId = $semesterId;
            $this->status = 'active';
            
            $result = $this->insert();
            return $result;
            
        } catch (Exception $e) {
            return ['success' => false, 'errors' => ['general' => 'Error creating reservation: ' . $e->getMessage()]];
        }
    }

    /**
     * Cancel reservation
     */
    public function cancelReservation() {
        try {
            // Update reservation status
            $this->status = 'cancelled';
            $result = $this->update();
            return $result;
            
        } catch (Exception $e) {
            return ['success' => false, 'errors' => ['general' => 'Error cancelling reservation: ' . $e->getMessage()]];
        }
    }

    /**
     * Get active reservations for user
     */
    public static function getActiveReservations($db, $userId, $semesterId = null) {
        $query = "SELECT r.*, b.title, b.author 
                  FROM reservations r 
                  JOIN books b ON r.book_id = b.id 
                  WHERE r.user_id = ? AND r.status = 'active'";
        
        $params = [$userId];
        
        if ($semesterId) {
            $query .= " AND r.semester_id = ?";
            $params[] = $semesterId;
        }
        
        $query .= " ORDER BY r.reservation_date DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all active reservations
     */
    public static function getAllActiveReservations($db) {
        $query = "SELECT r.*, u.first_name, u.last_name, u.role, b.title, b.author
                  FROM reservations r 
                  JOIN users u ON r.user_id = u.id 
                  JOIN books b ON r.book_id = b.id
                  WHERE r.status = 'active'
                  ORDER BY r.reservation_date DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if user has active reservation for book
     */
    public static function hasActiveReservation($db, $userId, $bookId) {
        $query = "SELECT id FROM reservations WHERE user_id = ? AND book_id = ? AND status = 'active'";
        $stmt = $db->prepare($query);
        $stmt->execute([$userId, $bookId]);
        
        return $stmt->fetch() !== false;
    }

    /**
     * Cancel all active reservations for a book (when book is borrowed)
     */
    public static function cancelAllForBook($db, $bookId) {
        try {
            $query = "UPDATE reservations SET status = 'cancelled' WHERE book_id = ? AND status = 'active'";
            $stmt = $db->prepare($query);
            $stmt->execute([$bookId]);
            
            return $stmt->rowCount();
            
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get reservation as array
     */
    public function toArray() {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'book_id' => $this->bookId,
            'semester_id' => $this->semesterId,
            'status' => $this->status,
            'reservation_date' => $this->reservationDate
        ];
    }

    /**
     * Validate reservation data
     */
    public function validate() {
        $errors = [];
        
        // Required fields
        if (empty($this->userId)) {
            $errors['userId'] = 'User ID is required';
        }
        
        if (empty($this->bookId)) {
            $errors['bookId'] = 'Book ID is required';
        }
        
        if (empty($this->semesterId)) {
            $errors['semesterId'] = 'Semester ID is required';
        }
        
        // Status validation
        if (!empty($this->status)) {
            $validStatuses = ['active', 'cancelled', 'fulfilled'];
            if (!in_array($this->status, $validStatuses)) {
                $errors['status'] = 'Invalid status';
            }
        }
        
        return $errors;
    }
}
?>
