# Smart Library Management System - Entity Relationship Diagram (ERD)

## 📊 Database Schema Overview

The Smart Library Management System uses a normalized relational database design with the following core entities and relationships.

## 🗂️ Entity Descriptions

### **1. Users Table**
- **Primary Key**: `id` (INT, AUTO_INCREMENT)
- **Purpose**: Stores all system users (Students, Teachers, Staff, Librarians)
- **Key Attributes**: username, password, role, first_name, last_name, email
- **Status Fields**: is_active, clearance_status, created_at

### **2. Books Table**
- **Primary Key**: `id` (INT, AUTO_INCREMENT)
- **Purpose**: Library book inventory management
- **Key Attributes**: title, author, isbn, price, status
- **Status**: available, borrowed, archived

### **3. Transactions Table**
- **Primary Key**: `id` (INT, AUTO_INCREMENT)
- **Purpose**: Records all borrowing and returning activities
- **Key Attributes**: user_id, book_id, semester_id, transaction_type, status
- **Types**: borrow, return
- **Status**: active, completed, overdue

### **4. Semesters Table**
- **Primary Key**: `id` (INT, AUTO_INCREMENT)
- **Purpose**: Academic year and semester management
- **Key Attributes**: name, start_date, end_date, is_current
- **Purpose**: Track academic periods for borrowing limits and clearance

### **5. Reservations Table**
- **Primary Key**: `id` (INT, AUTO_INCREMENT)
- **Purpose**: Book reservation system
- **Key Attributes**: user_id, book_id, semester_id, status
- **Status**: pending, confirmed, cancelled

## 🔗 Relationships

### **One-to-Many Relationships**
1. **Users → Transactions**: One user can have many transactions
2. **Books → Transactions**: One book can be involved in many transactions
3. **Semesters → Transactions**: One semester can have many transactions
4. **Users → Reservations**: One user can have many reservations
5. **Books → Reservations**: One book can have many reservations
6. **Semesters → Reservations**: One semester can have many reservations

### **Many-to-Many Relationships**
- **Users ↔ Books**: Through transactions and reservations
- **Users ↔ Semesters**: Through transactions and reservations

## 📋 Detailed Table Structures

### **Users Table**
```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('student', 'teacher', 'staff', 'librarian') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    clearance_status ENUM('clear', 'pending', 'blocked') DEFAULT 'clear',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### **Books Table**
```sql
CREATE TABLE books (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    isbn VARCHAR(20) UNIQUE NULL,
    price DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('available', 'borrowed', 'archived') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### **Transactions Table**
```sql
CREATE TABLE transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    semester_id INT NOT NULL,
    transaction_type ENUM('borrow', 'return') NOT NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    due_date DATE,
    return_date TIMESTAMP NULL,
    status ENUM('active', 'completed', 'overdue') DEFAULT 'active',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE
);
```

### **Semesters Table**
```sql
CREATE TABLE semesters (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_current BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### **Reservations Table**
```sql
CREATE TABLE reservations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    semester_id INT NOT NULL,
    reservation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE
);
```

## 🎯 Business Rules

### **Borrowing Rules**
1. **Students**: Maximum 3 books per semester
2. **Teachers**: Unlimited borrowing
3. **Due Dates**: Students (14 days), Teachers (30 days)
4. **Penalties**: Students (₱10/day), Teachers (₱5/day)

### **Clearance Rules**
1. **Students**: Must return all books and pay fines
2. **Teachers**: Must return all books and pay fines
3. **Semester End**: All users must be cleared

### **Reservation Rules**
1. **Priority**: First-come, first-served
2. **Duration**: 7 days to claim reserved book
3. **Limit**: One reservation per book per user

## 📊 Data Flow

### **Borrowing Process**
1. User requests book
2. System checks borrowing limits
3. Transaction created with due date
4. Book status updated to 'borrowed'
5. User receives confirmation

### **Returning Process**
1. User returns book
2. System calculates penalties (if overdue)
3. Transaction marked as 'completed'
4. Book status updated to 'available'
5. Return transaction created

### **Penalty Calculation**
1. System checks due dates daily
2. Overdue transactions marked as 'overdue'
3. Penalties calculated based on role
4. Users notified of outstanding fines

## 🔍 Indexes and Performance

### **Primary Indexes**
- All primary keys (id fields)
- Foreign key columns (user_id, book_id, semester_id)
- Unique constraints (username, email, isbn)

### **Performance Indexes**
- Status columns for filtering
- Date columns for sorting
- Role-based queries

## 🛡️ Data Integrity

### **Foreign Key Constraints**
- All foreign keys have CASCADE DELETE
- Referential integrity maintained
- Orphaned records prevented

### **Data Validation**
- Email format validation
- ISBN format validation
- Date range validation
- Status enum validation

## 📈 Scalability Considerations

### **Current Design**
- Supports up to 10,000 users
- Handles 100,000+ books
- Manages multiple semesters
- Tracks all transactions

### **Future Enhancements**
- Partitioning for large datasets
- Archiving old transactions
- Caching frequently accessed data
- Database replication for high availability

## 🔧 Maintenance

### **Regular Tasks**
- Clean up old transactions
- Archive completed semesters
- Update user clearance status
- Generate system reports

### **Monitoring**
- Database performance metrics
- Query execution times
- Storage usage
- Connection counts

---

**Entity Relationship Diagram Documentation v1.0**  
*Smart Library Management System*
