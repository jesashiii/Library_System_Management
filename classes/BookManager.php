<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Book.php';

class BookManager {
    private $db;
    
    public function __construct($db = null) {
        if ($db === null) {
            $database = new Database();
            $this->db = $database->getConnection();
        } else {
            $this->db = $db;
        }
    }
    
    /**
     * Add new book
     */
    public function addBook($bookData) {
        $book = new Book($this->db);
        
        $book->setTitle($bookData['title']);
        $book->setAuthor($bookData['author']);
        $book->setIsbn($bookData['isbn'] ?? null);
        $book->setCategory($bookData['category'] ?? null);
        $book->setPrice($bookData['price'] ?? 0.00);
        $book->setStatus('available');
        
        // Validate book data
        $errors = $book->validate();
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        return $book->save();
    }
    
    /**
     * Update existing book
     */
    public function updateBook($id, $bookData) {
        $book = new Book($this->db);
        
        if (!$book->loadById($id)) {
            return ['success' => false, 'errors' => ['general' => 'Book not found']];
        }
        
        $book->setTitle($bookData['title']);
        $book->setAuthor($bookData['author']);
        $book->setIsbn($bookData['isbn'] ?? null);
        $book->setCategory($bookData['category'] ?? null);
        $book->setPrice($bookData['price'] ?? 0.00);
        
        // Validate book data
        $errors = $book->validate();
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        return $book->save();
    }
    
    /**
     * Archive book
     */
    public function archiveBook($id) {
        $book = new Book($this->db);
        
        if (!$book->loadById($id)) {
            return ['success' => false, 'errors' => ['general' => 'Book not found']];
        }
        
        return $book->archive();
    }
    
    /**
     * Unarchive book
     */
    public function unarchiveBook($id) {
        $book = new Book($this->db);
        
        if (!$book->loadById($id)) {
            return ['success' => false, 'errors' => ['general' => 'Book not found']];
        }
        
        return $book->unarchive();
    }
    
    /**
     * Get all books
     */
    public function getAllBooks($status = null) {
        return Book::getAll($this->db, $status);
    }
    
    /**
     * Get available books
     */
    public function getAvailableBooks() {
        return Book::getAvailable($this->db);
    }
    
    /**
     * Search books
     */
    public function searchBooks($searchTerm, $status = null) {
        return Book::search($this->db, $searchTerm, $status);
    }
    
    /**
     * Get book by ID
     */
    public function getBookById($id) {
        $book = new Book($this->db);
        if ($book->loadById($id)) {
            return $book->toArray();
        }
        return null;
    }
    
    /**
     * Get book statistics
     */
    public function getBookStatistics() {
        $query = "SELECT 
                    COUNT(*) as total_books,
                    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_books,
                    SUM(CASE WHEN status = 'borrowed' THEN 1 ELSE 0 END) as borrowed_books,
                    SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) as reserved_books,
                    SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived_books
                  FROM books";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get books by category
     */
    public function getBooksByCategory($category) {
        $query = "SELECT * FROM books WHERE category = ? AND status != 'archived' ORDER BY title";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$category]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all categories
     */
    public function getAllCategories() {
        $query = "SELECT DISTINCT category FROM books WHERE category IS NOT NULL AND status != 'archived' ORDER BY category";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Get recently added books
     */
    public function getRecentlyAddedBooks($limit = 10) {
        $query = "SELECT * FROM books WHERE status != 'archived' ORDER BY created_at DESC LIMIT ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get popular books (most borrowed)
     */
    public function getPopularBooks($limit = 10) {
        $query = "SELECT b.*, COUNT(t.id) as borrow_count
                  FROM books b
                  LEFT JOIN transactions t ON b.id = t.book_id AND t.transaction_type = 'borrow'
                  WHERE b.status != 'archived'
                  GROUP BY b.id
                  ORDER BY borrow_count DESC, b.title
                  LIMIT ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check if book exists
     */
    public function bookExists($id) {
        $query = "SELECT id FROM books WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$id]);
        
        return $stmt->fetch() !== false;
    }
    
    /**
     * Check if ISBN exists
     */
    public function isbnExists($isbn, $excludeId = null) {
        if (empty($isbn)) {
            return false;
        }
        
        $query = "SELECT id FROM books WHERE isbn = ?";
        $params = [$isbn];
        
        if ($excludeId) {
            $query .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetch() !== false;
    }
    
    /**
     * Get books with pagination
     */
    public function getBooksWithPagination($page = 1, $limit = 10, $status = null, $search = '') {
        $offset = ($page - 1) * $limit;
        
        $whereClause = '';
        $params = [];
        
        if ($status) {
            $whereClause = "WHERE status = ?";
            $params[] = $status;
        }
        
        if (!empty($search)) {
            if ($whereClause) {
                $whereClause .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ? OR category LIKE ?)";
            } else {
                $whereClause = "WHERE (title LIKE ? OR author LIKE ? OR isbn LIKE ? OR category LIKE ?)";
            }
            $searchTerm = "%{$search}%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM books {$whereClause}";
        $stmt = $this->db->prepare($countQuery);
        $stmt->execute($params);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get books
        $query = "SELECT * FROM books {$whereClause} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'books' => $books,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ];
    }
}
?>
