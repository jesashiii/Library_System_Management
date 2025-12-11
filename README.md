# Library_System_Management
Smart Library System — User Manual 

Welcome to the **Smart Library System**, a web-based application designed to help you manage library operations efficiently. Whether you are a Librarian, Staff, Student, or Teacher, this guide will walk you through how to use the system effectively.

Getting Started

When you first open your browser and go to `http://localhost/smart-library/`, you’ll see the login screen. Here, you can enter your credentials depending on your role:

* **Librarian:** `librarian1` / `password`
* **Staff:** `staff1` / `password`
* **Student:** `student1` / `password`
* **Teacher:** `teacher1` / `password`

Once you log in, the system will redirect you to your **role-specific dashboard**, where all your tasks and tools are organized.

Tip: Make sure the database `schema_v3.sql` is imported into phpMyAdmin before first use. If you want demo data, run `test_data_generator.php` from your browser or CLI.


For Librarians

As a Librarian, your main responsibility is to manage the library’s collection. On your dashboard, you can **add new books** by entering the Title and Author. ISBN is optional but must be unique if provided. You can also set the category and price.

If you need to update book details, click **Edit**, modify the information, and save. Archiving a book removes it from circulation but keeps it in the system for record-keeping. Unarchiving restores its availability. Your inventory table clearly shows each book’s status—Available, Borrowed, Reserved, or Archived—so you always know what’s on the shelves.

---

For Staff

Staff handle day-to-day borrowing, returns, penalties, and user clearance:

* **Borrowing Books:** Search for a user and select a book. The system will only allow borrowing if the book is available. Students can borrow up to 3 books per semester for 14 days, while Teachers have 30 days.

* **Returning Books:** Simply select the book in Active Borrows and click Return. This updates both the book’s status and the user’s borrowing history.

* **Managing Penalties:** Staff can see overdue books, calculate penalties, and record payments or waivers. Students are charged ₱10/day with a 3-day grace period, capped at ₱500. Teachers are charged ₱5/day with a 5-day grace period, capped at ₱250.

* **Clearance:** Before a semester ends, ensure users have no outstanding borrows or penalties to clear them for the next term.


For Students and Teachers

Your dashboard is your main control center. Here you can:

* See active borrows, due dates, and fines
* Reserve available books (note: reservations are canceled if staff borrows the book first)
* Track your borrowing history for reference

Searching for books or users is easy—start typing in the search box, and suggestions will appear in real-time.


Searching and Navigation

The system allows quick access to all library resources:

* **Search Users:** by Name, Student ID, or Email
* **Search Books:** by Title, Author, ISBN, or Category
* Real-time suggestions make finding what you need fast and easy



Do you want me to do that?
