<?php
require_once __DIR__ . '/../config/database.php';

class Book {
    private $db;
    private $id;
    private $title;
    private $author;
    private $isbn;
    private $category;
    private $price;
    private $status;
    private $createdAt;

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
    public function getTitle() { return $this->title; }
    public function getAuthor() { return $this->author; }
    public function getIsbn() { return $this->isbn; }
    public function getCategory() { return $this->category; }
    public function getPrice() { return $this->price; }
    public function getStatus() { return $this->status; }
    public function getCreatedAt() { return $this->createdAt; }

    // Setters
    public function setId($id) { $this->id = $id; }
    public function setTitle($title) { $this->title = $title; }
    public function setAuthor($author) { $this->author = $author; }
    public function setIsbn($isbn) { $this->isbn = $isbn; }
    public function setCategory($category) { $this->category = $category; }
    public function setPrice($price) { $this->price = $price; }
    public function setStatus($status) { $this->status = $status; }

    /**
     * Load book data from database by ID
     */
    public function loadById($id) {
        $query = "SELECT id, title, author, isbn, category, price, status, created_at 
                  FROM books WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$id]);
        
        if ($book = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->id = $book['id'];
            $this->title = $book['title'];
            $this->author = $book['author'];
            $this->isbn = $book['isbn'];
            $this->category = $book['category'];
            $this->price = $book['price'];
            $this->status = $book['status'];
            $this->createdAt = $book['created_at'];
            return true;
        }
        return false;
    }

    /**
     * Save book to database (insert or update)
     */
    public function save() {
        if ($this->id) {
            return $this->update();
        } else {
            return $this->insert();
        }
    }

    /**
     * Insert new book
     */
    private function insert() {
        try {
            $this->db->beginTransaction();
            
            $query = "INSERT INTO books (title, author, isbn, category, price, status) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $this->title,
                $this->author,
                $this->isbn,
                $this->category,
                $this->price,
                $this->status ?? 'available'
            ]);
            
            $this->id = $this->db->lastInsertId();
            $this->db->commit();
            
            return ['success' => true, 'book_id' => $this->id, 'message' => 'Book added successfully!'];
            
        } catch (PDOException $e) {
            $this->db->rollback();
            if ($e->getCode() == 23000) {
                return ['success' => false, 'errors' => ['general' => 'A book with this ISBN already exists. Please use a different ISBN or leave it blank.']];
            } else {
                return ['success' => false, 'errors' => ['general' => 'Error adding book: ' . $e->getMessage()]];
            }
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'errors' => ['general' => 'Error adding book: ' . $e->getMessage()]];
        }
    }

    /**
     * Update existing book
     */
    private function update() {
        try {
            $this->db->beginTransaction();
            
            $query = "UPDATE books SET title = ?, author = ?, isbn = ?, category = ?, price = ?, status = ? WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $this->title,
                $this->author,
                $this->isbn,
                $this->category,
                $this->price,
                $this->status,
                $this->id
            ]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Book updated successfully!'];
            
        } catch (PDOException $e) {
            $this->db->rollback();
            if ($e->getCode() == 23000) {
                return ['success' => false, 'errors' => ['general' => 'A book with this ISBN already exists. Please use a different ISBN or leave it blank.']];
            } else {
                return ['success' => false, 'errors' => ['general' => 'Error updating book: ' . $e->getMessage()]];
            }
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'errors' => ['general' => 'Error updating book: ' . $e->getMessage()]];
        }
    }

    /**
     * Delete book (archive it)
     */
    public function archive() {
        try {
            $this->status = 'archived';
            $query = "UPDATE books SET status = 'archived' WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$this->id]);
            
            return ['success' => true, 'message' => 'Book archived successfully!'];
            
        } catch (Exception $e) {
            return ['success' => false, 'errors' => ['general' => 'Error archiving book: ' . $e->getMessage()]];
        }
    }

    /**
     * Unarchive book
     */
    public function unarchive() {
        try {
            $this->status = 'available';
            $query = "UPDATE books SET status = 'available' WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$this->id]);
            
            return ['success' => true, 'message' => 'Book unarchived successfully!'];
            
        } catch (Exception $e) {
            return ['success' => false, 'errors' => ['general' => 'Error unarchiving book: ' . $e->getMessage()]];
        }
    }

    /**
     * Check if book is available for borrowing
     */
    public function isAvailable() {
        return $this->status === 'available';
    }

    /**
     * Check if book is borrowed
     */
    public function isBorrowed() {
        return $this->status === 'borrowed';
    }

    /**
     * Check if book is reserved
     */
    public function isReserved() {
        return $this->status === 'reserved';
    }

    /**
     * Check if book is archived
     */
    public function isArchived() {
        return $this->status === 'archived';
    }

    /**
     * Get all books with optional filters
     */
    public static function getAll($db, $status = null, $orderBy = 'created_at DESC') {
        $query = "SELECT * FROM books";
        $params = [];
        
        if ($status) {
            $query .= " WHERE status = ?";
            $params[] = $status;
        }
        
        $query .= " ORDER BY " . $orderBy;
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get available books
     */
    public static function getAvailable($db) {
        return self::getAll($db, 'available', 'title');
    }

    /**
     * Search books
     */
    public static function search($db, $searchTerm, $status = null) {
        $query = "SELECT * FROM books WHERE (title LIKE ? OR author LIKE ? OR isbn LIKE ? OR category LIKE ?)";
        $params = ["%{$searchTerm}%", "%{$searchTerm}%", "%{$searchTerm}%", "%{$searchTerm}%"];
        
        if ($status) {
            $query .= " AND status = ?";
            $params[] = $status;
        }
        
        $query .= " ORDER BY title";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Validate book data
     */
    public function validate() {
        $errors = [];
        
        // Required fields
        if (empty($this->title)) {
            $errors['title'] = 'Title is required';
        }
        
        if (empty($this->author)) {
            $errors['author'] = 'Author is required';
        }
        
        // Price validation
        if ($this->price !== null && (!is_numeric($this->price) || $this->price < 0)) {
            $errors['price'] = 'Price must be a positive number';
        }
        
        // Status validation
        if (!empty($this->status)) {
            $validStatuses = ['available', 'borrowed', 'reserved', 'archived'];
            if (!in_array($this->status, $validStatuses)) {
                $errors['status'] = 'Invalid status';
            }
        }
        
        return $errors;
    }

    /**
     * Get book as array
     */
    public function toArray() {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'isbn' => $this->isbn,
            'category' => $this->category,
            'price' => $this->price,
            'status' => $this->status,
            'created_at' => $this->createdAt
        ];
    }

    /**
     * Get formatted price
     */
    public function getFormattedPrice() {
        return $this->price !== null ? '₱' . number_format((float)$this->price, 2) : '—';
    }

    /**
     * Get status badge class for UI
     */
    public function getStatusBadgeClass() {
        switch ($this->status) {
            case 'available':
                return 'bg-green-100 text-green-800';
            case 'borrowed':
                return 'bg-yellow-100 text-yellow-800';
            case 'reserved':
                return 'bg-blue-100 text-blue-800';
            case 'archived':
                return 'bg-gray-200 text-gray-700';
            default:
                return 'bg-blue-100 text-blue-800';
        }
    }
}
?>
