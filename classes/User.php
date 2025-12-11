<?php
require_once __DIR__ . '/../config/database.php';

class User {
    private $db;
    private $id;
    private $username;
    private $password;
    private $role;
    private $firstName;
    private $lastName;
    private $email;
    private $studentId;
    private $isActive;
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
    public function getUsername() { return $this->username; }
    public function getRole() { return $this->role; }
    public function getFirstName() { return $this->firstName; }
    public function getLastName() { return $this->lastName; }
    public function getEmail() { return $this->email; }
    public function getStudentId() { return $this->studentId; }
    public function getIsActive() { return $this->isActive; }
    public function getCreatedAt() { return $this->createdAt; }

    // Setters
    public function setId($id) { $this->id = $id; }
    public function setUsername($username) { $this->username = $username; }
    public function setPassword($password) { $this->password = $password; }
    public function setRole($role) { $this->role = $role; }
    public function setFirstName($firstName) { $this->firstName = $firstName; }
    public function setLastName($lastName) { $this->lastName = $lastName; }
    public function setEmail($email) { $this->email = $email; }
    public function setStudentId($studentId) { $this->studentId = $studentId; }
    public function setIsActive($isActive) { $this->isActive = $isActive; }

    /**
     * Load user data from database by ID
     */
    public function loadById($id) {
        $query = "SELECT id, username, password, role, first_name, last_name, email, student_id, is_active, created_at 
                  FROM users WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$id]);
        
        if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->id = $user['id'];
            $this->username = $user['username'];
            $this->password = $user['password'];
            $this->role = $user['role'];
            $this->firstName = $user['first_name'];
            $this->lastName = $user['last_name'];
            $this->email = $user['email'];
            $this->studentId = $user['student_id'];
            $this->isActive = $user['is_active'];
            $this->createdAt = $user['created_at'];
            return true;
        }
        return false;
    }

    /**
     * Load user data from database by username
     */
    public function loadByUsername($username) {
        $query = "SELECT id, username, password, role, first_name, last_name, email, student_id, is_active, created_at 
                  FROM users WHERE username = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$username]);
        
        if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->id = $user['id'];
            $this->username = $user['username'];
            $this->password = $user['password'];
            $this->role = $user['role'];
            $this->firstName = $user['first_name'];
            $this->lastName = $user['last_name'];
            $this->email = $user['email'];
            $this->studentId = $user['student_id'];
            $this->isActive = $user['is_active'];
            $this->createdAt = $user['created_at'];
            return true;
        }
        return false;
    }

    /**
     * Load user data from database by email
     */
    public function loadByEmail($email) {
        $query = "SELECT id, username, password, role, first_name, last_name, email, student_id, is_active, created_at 
                  FROM users WHERE email = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$email]);
        
        if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->id = $user['id'];
            $this->username = $user['username'];
            $this->password = $user['password'];
            $this->role = $user['role'];
            $this->firstName = $user['first_name'];
            $this->lastName = $user['last_name'];
            $this->email = $user['email'];
            $this->studentId = $user['student_id'];
            $this->isActive = $user['is_active'];
            $this->createdAt = $user['created_at'];
            return true;
        }
        return false;
    }

    /**
     * Save user to database (insert or update)
     */
    public function save() {
        if ($this->id) {
            return $this->update();
        } else {
            return $this->insert();
        }
    }

    /**
     * Insert new user
     */
    private function insert() {
        try {
            $this->db->beginTransaction();
            
            $hashedPassword = password_hash($this->password, PASSWORD_DEFAULT);
            
            $query = "INSERT INTO users (username, password, role, first_name, last_name, email, student_id, is_active) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $this->username,
                $hashedPassword,
                $this->role,
                $this->firstName,
                $this->lastName,
                $this->email,
                $this->studentId,
                $this->isActive ?? true
            ]);
            
            $this->id = $this->db->lastInsertId();
            $this->db->commit();
            
            return ['success' => true, 'user_id' => $this->id, 'message' => 'User created successfully!'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'errors' => ['general' => 'User creation failed: ' . $e->getMessage()]];
        }
    }

    /**
     * Update existing user
     */
    private function update() {
        try {
            $this->db->beginTransaction();
            
            $updateFields = [];
            $params = [];
            
            $fields = ['username', 'first_name', 'last_name', 'email', 'role', 'is_active', 'student_id'];
            foreach ($fields as $field) {
                $property = lcfirst(str_replace('_', '', ucwords($field, '_')));
                if (isset($this->$property)) {
                    $updateFields[] = "{$field} = ?";
                    $params[] = $this->$property;
                }
            }
            
            // Update password if provided
            if (!empty($this->password)) {
                $updateFields[] = "password = ?";
                $params[] = password_hash($this->password, PASSWORD_DEFAULT);
            }
            
            if (empty($updateFields)) {
                return ['success' => false, 'errors' => ['general' => 'No fields to update']];
            }
            
            $params[] = $this->id;
            
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
    public function delete() {
        try {
            $this->db->beginTransaction();
            
            // Check if user has active transactions
            $query = "SELECT COUNT(*) as count FROM transactions WHERE user_id = ? AND status = 'active'";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$this->id]);
            $activeTransactions = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($activeTransactions > 0) {
                return ['success' => false, 'errors' => ['general' => 'Cannot delete user with active transactions']];
            }
            
            // Delete user
            $query = "DELETE FROM users WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$this->id]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'User deleted successfully!'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'errors' => ['general' => 'Delete failed: ' . $e->getMessage()]];
        }
    }

    /**
     * Verify password
     */
    public function verifyPassword($password) {
        return password_verify($password, $this->password);
    }

    /**
     * Check if username exists
     */
    public function usernameExists($username, $excludeId = null) {
        $query = "SELECT id FROM users WHERE username = ?";
        $params = [$username];
        
        if ($excludeId) {
            $query .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch() !== false;
    }

    /**
     * Check if email exists
     */
    public function emailExists($email, $excludeId = null) {
        $query = "SELECT id FROM users WHERE email = ?";
        $params = [$email];
        
        if ($excludeId) {
            $query .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch() !== false;
    }

    /**
     * Check if student ID exists
     */
    public function studentIdExists($studentId, $excludeId = null) {
        $query = "SELECT id FROM users WHERE student_id = ?";
        $params = [$studentId];
        
        if ($excludeId) {
            $query .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch() !== false;
    }

    /**
     * Get full name
     */
    public function getFullName() {
        return $this->firstName . ' ' . $this->lastName;
    }

    /**
     * Validate user data
     */
    public function validate() {
        $errors = [];
        
        // Required fields
        $required = ['username', 'role', 'firstName', 'lastName', 'email'];
        foreach ($required as $field) {
            if (empty($this->$field)) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }
        
        // Student ID validation for students
        if ($this->role === 'student') {
            if (empty($this->studentId)) {
                $errors['studentId'] = 'Student ID is required for students';
            } elseif ($this->studentIdExists($this->studentId, $this->id)) {
                $errors['studentId'] = 'Student ID already exists';
            }
        }
        
        // Username validation
        if (!empty($this->username)) {
            if (strlen($this->username) < 3) {
                $errors['username'] = 'Username must be at least 3 characters long';
            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $this->username)) {
                $errors['username'] = 'Username can only contain letters, numbers, and underscores';
            } elseif ($this->usernameExists($this->username, $this->id)) {
                $errors['username'] = 'Username already exists';
            }
        }
        
        // Password validation (only for new users or when password is being changed)
        if (empty($this->id) || !empty($this->password)) {
            if (strlen($this->password) < 6) {
                $errors['password'] = 'Password must be at least 6 characters long';
            }
        }
        
        // Email validation
        if (!empty($this->email)) {
            if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Please enter a valid email address';
            } elseif ($this->emailExists($this->email, $this->id)) {
                $errors['email'] = 'Email already exists';
            }
        }
        
        // Name validation
        if (!empty($this->firstName) && strlen($this->firstName) < 2) {
            $errors['firstName'] = 'First name must be at least 2 characters long';
        }
        
        if (!empty($this->lastName) && strlen($this->lastName) < 2) {
            $errors['lastName'] = 'Last name must be at least 2 characters long';
        }
        
        // Role validation
        if (!empty($this->role)) {
            $validRoles = ['student', 'teacher', 'librarian', 'staff'];
            if (!in_array($this->role, $validRoles)) {
                $errors['role'] = 'Invalid role selected';
            }
        }
        
        return $errors;
    }

    /**
     * Get user as array
     */
    public function toArray() {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'role' => $this->role,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'student_id' => $this->studentId,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt
        ];
    }
}
?>
