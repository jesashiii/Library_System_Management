<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/User.php';

class Authentication {
    private $db;
    private $user;

    public function __construct($db = null) {
        if ($db === null) {
            $database = new Database();
            $this->db = $database->getConnection();
        } else {
            $this->db = $db;
        }
        $this->user = new User($this->db);
    }

    /**
     * Authenticate user login (supports both username and email)
     */
    public function login($usernameOrEmail, $password) {
        if (empty($usernameOrEmail) || empty($password)) {
            return ['success' => false, 'message' => 'Please fill in all fields'];
        }

        // Try to load user by username first, then by email
        $userLoaded = false;
        if ($this->user->loadByUsername($usernameOrEmail)) {
            $userLoaded = true;
        } elseif ($this->user->loadByEmail($usernameOrEmail)) {
            $userLoaded = true;
        }

        if ($userLoaded) {
            // Check if user is active
            if (!$this->user->getIsActive()) {
                return ['success' => false, 'message' => 'Your account is inactive. Please contact administrator.'];
            }

            // Verify password
            if ($this->user->verifyPassword($password)) {
                return [
                    'success' => true,
                    'user' => $this->user->toArray(),
                    'message' => 'Login successful'
                ];
            } else {
                return ['success' => false, 'message' => 'Invalid username/email or password'];
            }
        } else {
            return ['success' => false, 'message' => 'Invalid username/email or password'];
        }
    }

    /**
     * Register new user
     */
    public function register($userData) {
        // Create new user object
        $user = new User($this->db);
        
        // Set user properties
        $user->setUsername($userData['username']);
        $user->setPassword($userData['password']);
        $user->setRole($userData['role']);
        $user->setFirstName($userData['first_name']);
        $user->setLastName($userData['last_name']);
        $user->setEmail($userData['email']);
        $user->setStudentId($userData['student_id'] ?? null);
        $user->setIsActive(true);

        // Validate user data
        $errors = $user->validate();
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Save user
        return $user->save();
    }

    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    /**
     * Get current user from session
     */
    public static function getCurrentUser() {
        if (self::isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'role' => $_SESSION['role'],
                'first_name' => $_SESSION['first_name'],
                'last_name' => $_SESSION['last_name']
            ];
        }
        return null;
    }

    /**
     * Set user session
     */
    public static function setSession($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
    }

    /**
     * Destroy user session
     */
    public static function logout() {
        session_destroy();
        return ['success' => true, 'message' => 'Logged out successfully'];
    }

    /**
     * Check if user has specific role
     */
    public static function hasRole($role) {
        return self::isLoggedIn() && $_SESSION['role'] === $role;
    }

    /**
     * Check if user has any of the specified roles
     */
    public static function hasAnyRole($roles) {
        return self::isLoggedIn() && in_array($_SESSION['role'], $roles);
    }

    /**
     * Require authentication
     */
    public static function requireAuth() {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit();
        }
    }

    /**
     * Require specific role
     */
    public static function requireRole($role) {
        self::requireAuth();
        if (!self::hasRole($role)) {
            header('Location: ../login.php');
            exit();
        }
    }

    /**
     * Require any of the specified roles
     */
    public static function requireAnyRole($roles) {
        self::requireAuth();
        if (!self::hasAnyRole($roles)) {
            header('Location: ../login.php');
            exit();
        }
    }

    /**
     * Get redirect URL based on user role
     */
    public static function getRedirectUrl($role) {
        switch ($role) {
            case 'librarian':
                return 'librarian/dashboard.php';
            case 'staff':
                return 'staff/dashboard.php';
            case 'teacher':
                return 'teacher/dashboard.php';
            case 'student':
                return 'student/dashboard.php';
            default:
                return 'login.php';
        }
    }

    /**
     * Redirect user to appropriate dashboard
     */
    public static function redirectToDashboard($role) {
        $url = self::getRedirectUrl($role);
        header("Location: $url");
        exit();
    }

    /**
     * Validate session and redirect if needed
     */
    public static function validateSession() {
        if (!self::isLoggedIn()) {
            return false;
        }

        // Check if session data is complete
        $required = ['user_id', 'username', 'role', 'first_name', 'last_name'];
        foreach ($required as $field) {
            if (!isset($_SESSION[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get user permissions based on role
     */
    public static function getPermissions($role) {
        $permissions = [
            'student' => [
                'view_books' => true,
                'reserve_books' => true,
                'view_own_transactions' => true,
                'view_own_fines' => true
            ],
            'teacher' => [
                'view_books' => true,
                'reserve_books' => true,
                'view_own_transactions' => true,
                'view_own_fines' => true,
                'unlimited_borrowing' => true
            ],
            'staff' => [
                'view_books' => true,
                'borrow_books' => true,
                'return_books' => true,
                'manage_transactions' => true,
                'manage_penalties' => true,
                'manage_clearance' => true,
                'view_all_transactions' => true
            ],
            'librarian' => [
                'view_books' => true,
                'add_books' => true,
                'edit_books' => true,
                'archive_books' => true,
                'manage_users' => true,
                'view_all_transactions' => true,
                'view_reports' => true
            ]
        ];

        return $permissions[$role] ?? [];
    }

    /**
     * Check if user has specific permission
     */
    public static function hasPermission($permission) {
        if (!self::isLoggedIn()) {
            return false;
        }

        $permissions = self::getPermissions($_SESSION['role']);
        return isset($permissions[$permission]) && $permissions[$permission];
    }

    /**
     * Require specific permission
     */
    public static function requirePermission($permission) {
        self::requireAuth();
        if (!self::hasPermission($permission)) {
            header('Location: ../login.php');
            exit();
        }
    }
}
?>
