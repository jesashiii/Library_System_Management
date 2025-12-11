<?php
require_once 'database.php';

class UserManager {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Register a new user
     */
    public function registerUser($data) {
        $errors = $this->validateUserData($data);
        
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        // Check if username or email already exists
        if ($this->usernameExists($data['username'])) {
            return ['success' => false, 'errors' => ['username' => 'Username already exists']];
        }
        
        if ($this->emailExists($data['email'])) {
            return ['success' => false, 'errors' => ['email' => 'Email already exists']];
        }
        
        try {
            $this->db->beginTransaction();
            
            // Hash password
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            
            // Insert user
            $query = "INSERT INTO users (username, password, role, first_name, last_name, email, student_id, is_active) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $data['username'],
                $hashedPassword,
                $data['role'],
                $data['first_name'],
                $data['last_name'],
                $data['email'],
                $data['student_id'] ?? null,
                $data['is_active'] ?? true
            ]);
            
            $userId = $this->db->lastInsertId();
            
            $this->db->commit();
            
            return [
                'success' => true, 
                'user_id' => $userId,
                'message' => 'User registered successfully!'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'errors' => ['general' => 'Registration failed: ' . $e->getMessage()]];
        }
    }
    
    /**
     * Validate user registration data
     */
    private function validateUserData($data) {
        $errors = [];
        
        // Required fields
        $required = ['username', 'password', 'role', 'first_name', 'last_name', 'email'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }
        
        // Student ID validation for students
        if (!empty($data['role']) && $data['role'] === 'student') {
            if (empty($data['student_id'])) {
                $errors['student_id'] = 'Student ID is required for students';
            } elseif ($this->studentIdExists($data['student_id'])) {
                $errors['student_id'] = 'Student ID already exists';
            }
        }
        
        // Username validation
        if (!empty($data['username'])) {
            if (strlen($data['username']) < 3) {
                $errors['username'] = 'Username must be at least 3 characters long';
            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
                $errors['username'] = 'Username can only contain letters, numbers, and underscores';
            }
        }
        
        // Password validation
        if (!empty($data['password'])) {
            if (strlen($data['password']) < 6) {
                $errors['password'] = 'Password must be at least 6 characters long';
            }
        }
        
        // Email validation
        if (!empty($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Please enter a valid email address';
            }
        }
        
        // Name validation
        if (!empty($data['first_name']) && strlen($data['first_name']) < 2) {
            $errors['first_name'] = 'First name must be at least 2 characters long';
        }
        
        if (!empty($data['last_name']) && strlen($data['last_name']) < 2) {
            $errors['last_name'] = 'Last name must be at least 2 characters long';
        }
        
        // Role validation
        if (!empty($data['role'])) {
            $validRoles = ['student', 'teacher', 'librarian', 'staff'];
            if (!in_array($data['role'], $validRoles)) {
                $errors['role'] = 'Invalid role selected';
            }
        }
        
        return $errors;
    }
    
    /**
     * Check if username exists
     */
    private function usernameExists($username) {
        $query = "SELECT id FROM users WHERE username = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$username]);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Check if email exists
     */
    private function emailExists($email) {
        $query = "SELECT id FROM users WHERE email = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$email]);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Check if student ID exists
     */
    private function studentIdExists($studentId) {
        $query = "SELECT id FROM users WHERE student_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$studentId]);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Get all users with pagination
     */
    public function getUsers($page = 1, $limit = 10, $search = '') {
        $offset = ($page - 1) * $limit;
        
        $whereClause = '';
        $params = [];
        
        if (!empty($search)) {
            $whereClause = "WHERE first_name LIKE ? OR last_name LIKE ? OR username LIKE ? OR email LIKE ? OR student_id LIKE ?";
            $searchTerm = "%{$search}%";
            $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
        }
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM users {$whereClause}";
        $stmt = $this->db->prepare($countQuery);
        $stmt->execute($params);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get users
        $query = "SELECT id, username, role, first_name, last_name, email, student_id, is_active, created_at 
                  FROM users {$whereClause} 
                  ORDER BY created_at DESC 
                  LIMIT {$limit} OFFSET {$offset}";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ];
    }
    
    /**
     * Get user by ID
     */
    public function getUserById($id) {
        $query = "SELECT id, username, role, first_name, last_name, email, student_id, is_active, created_at 
                  FROM users WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update user
     */
    public function updateUser($id, $data) {
        $errors = $this->validateUserData($data, $id);
        
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        try {
            $this->db->beginTransaction();
            
            $updateFields = [];
            $params = [];
            
            // Update basic fields
            $fields = ['username', 'first_name', 'last_name', 'email', 'role', 'is_active', 'student_id'];
            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "{$field} = ?";
                    $params[] = $data[$field];
                }
            }
            
            // Update password if provided
            if (!empty($data['password'])) {
                $updateFields[] = "password = ?";
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            
            if (empty($updateFields)) {
                return ['success' => false, 'errors' => ['general' => 'No fields to update']];
            }
            
            $params[] = $id;
            
            $query = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'User updated successfully!'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'errors' => ['general' => 'Update failed: ' . $e->getMessage()]];
        }
    }
    
    /**
     * Delete user
     */
    public function deleteUser($id) {
        try {
            $this->db->beginTransaction();
            
            // Check if user has active transactions
            $query = "SELECT COUNT(*) as count FROM transactions WHERE user_id = ? AND status = 'active'";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$id]);
            $activeTransactions = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($activeTransactions > 0) {
                return ['success' => false, 'errors' => ['general' => 'Cannot delete user with active transactions']];
            }
            
            // Delete user
            $query = "DELETE FROM users WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$id]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'User deleted successfully!'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'errors' => ['general' => 'Delete failed: ' . $e->getMessage()]];
        }
    }
    
    /**
     * Toggle user active status
     */
    public function toggleUserStatus($id) {
        try {
            $query = "UPDATE users SET is_active = NOT is_active WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$id]);
            
            return ['success' => true, 'message' => 'User status updated successfully!'];
            
        } catch (Exception $e) {
            return ['success' => false, 'errors' => ['general' => 'Status update failed: ' . $e->getMessage()]];
        }
    }
    
    /**
     * Get user statistics
     */
    public function getUserStatistics() {
        $query = "SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
                    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_users,
                    SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as students,
                    SUM(CASE WHEN role = 'teacher' THEN 1 ELSE 0 END) as teachers,
                    SUM(CASE WHEN role = 'librarian' THEN 1 ELSE 0 END) as librarians,
                    SUM(CASE WHEN role = 'staff' THEN 1 ELSE 0 END) as staff
                  FROM users";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
