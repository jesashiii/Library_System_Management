<?php
session_start();
require_once '../classes/Authentication.php';
require_once '../classes/BookManager.php';

// Check if user is logged in and is a librarian
Authentication::requireRole('librarian');

$auth = new Authentication();
$database = new Database();
$db = $database->getConnection();
$bookManager = new BookManager($db);

// Handle book operations
if ($_POST) {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_book':
            $bookData = [
                'title' => $_POST['title'] ?? '',
                'author' => $_POST['author'] ?? '',
                'isbn' => $_POST['isbn'] ?? '',
                'category' => $_POST['category'] ?? '',
                'price' => $_POST['price'] ?? ''
            ];
            if (!empty($bookData['title']) && !empty($bookData['author'])) {
                $result = $bookManager->addBook($bookData);
                if ($result['success']) $success_message = $result['message'];
                else $error_message = $result['errors']['general'] ?? 'Error adding book.';
            } else $error_message = "Title and Author are required fields.";
            break;

        case 'update_book':
            $book_id = $_POST['book_id'] ?? '';
            $bookData = [
                'title' => $_POST['title'] ?? '',
                'author' => $_POST['author'] ?? '',
                'isbn' => $_POST['isbn'] ?? '',
                'category' => $_POST['category'] ?? '',
                'price' => $_POST['price'] ?? ''
            ];
            if (!empty($book_id) && !empty($bookData['title']) && !empty($bookData['author'])) {
                $result = $bookManager->updateBook($book_id, $bookData);
                if ($result['success']) $success_message = $result['message'];
                else $error_message = $result['errors']['general'] ?? 'Error updating book.';
            } else $error_message = "Book ID, Title and Author are required fields.";
            break;

        case 'archive_book':
        case 'unarchive_book':
            $book_id = $_POST['book_id'] ?? '';
            if (!empty($book_id)) {
                $result = ($action === 'archive_book') ? $bookManager->archiveBook($book_id) : $bookManager->unarchiveBook($book_id);
                if ($result['success']) $success_message = $result['message'];
                else $error_message = $result['errors']['general'] ?? 'Error updating book status.';
            }
            break;
    }
}

// Get all books
$books = $bookManager->getAllBooks();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Librarian Dashboard - Smart Library</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdn.jsdelivr.net/npm/flowbite@2.2.1/dist/flowbite.min.css" rel="stylesheet" />
<style>
:root {
    --primary: #c3a980;
    --primary-dark: #a19071;
    --bg-light: #ffffff;
    --text-dark: #4b4538;
}

body { background-color: var(--bg-light); color: var(--text-dark); font-family: 'Inter', sans-serif; }

nav { background-color: var(--primary-dark); }
nav h1, nav span { color: white; }
nav a { background-color: var(--primary); color: white; transition: 0.2s; }
nav a:hover { background-color: var(--primary-dark); }

.btn-primary { background-color: var(--primary); color: white; transition: 0.2s; }
.btn-primary:hover { background-color: var(--primary-dark); }

.card { border-top: 4px solid var(--primary); background-color: white; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }

.sidebar button {
    height: 3rem; font-size: 0.875rem; transition: all 0.2s; width: 100%;
}
.sidebar button:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }

table thead { background-color: var(--primary); }
table thead th { color: white; }
table tbody tr:hover { background-color: #fdf8f2; transition: 0.2s; }

.status-available { background-color: #c3a98033; color: var(--primary-dark); padding: 2px 6px; border-radius: 6px; font-weight: 500; }
.status-borrowed { background-color: #a1907133; color: var(--primary-dark); padding: 2px 6px; border-radius: 6px; font-weight: 500; }
.status-archived { background-color: #e5e2dd; color: #6b6458; padding: 2px 6px; border-radius: 6px; font-weight: 500; }

#editModal { backdrop-filter: blur(2px); }
</style>
</head>
<body>

<!-- Navigation -->
<nav class="shadow-lg">
  <div class="max-w-7xl mx-auto px-4">
    <div class="flex justify-between h-16 items-center">
        <h1 class="text-xl font-semibold"><span>Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</span></h1>
        <div class="flex items-center space-x-4">
            <a href="../logout.php" class="px-4 py-2 rounded hover:bg-red-700">Logout</a>
        </div>
    </div>
  </div>
</nav>

<!-- Main Content -->
<div class="max-w-7xl mx-auto py-6 px-4">
<?php if(isset($success_message)): ?>
<div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
    <?php echo htmlspecialchars($success_message); ?>
</div>
<?php endif; ?>
<?php if(isset($error_message)): ?>
<div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
    <?php echo htmlspecialchars($error_message); ?>
</div>
<?php endif; ?>

<div class="flex space-x-6">
    <!-- Sidebar -->
    <div class="w-60 flex-shrink-0 sidebar">
        <div class="bg-white shadow rounded-lg p-4 space-y-4">
            <button onclick="showSection('addbook')" class="btn-primary rounded">Add Book</button>
            <button onclick="showSection('inventory')" class="btn-primary rounded">Book Inventory</button>
        </div>
    </div>

    <!-- Sections -->
    <div class="flex-1 space-y-6">
        <!-- Add Book -->
        <div id="addbook" class="section" style="display:none;">
            <div class="card p-6">
                <h2 class="text-lg font-semibold mb-4">Add New Book</h2>
                <form method="POST" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <input type="hidden" name="action" value="add_book">
                    <input type="text" name="title" placeholder="Title" required class="border-gray-300 rounded-md shadow-sm p-2">
                    <input type="text" name="author" placeholder="Author" required class="border-gray-300 rounded-md shadow-sm p-2">
                    <input type="text" name="isbn" placeholder="ISBN" class="border-gray-300 rounded-md shadow-sm p-2">
                    <input type="text" name="category" placeholder="Category" class="border-gray-300 rounded-md shadow-sm p-2">
                    <input type="number" name="price" placeholder="Price" step="0.01" min="0" class="border-gray-300 rounded-md shadow-sm p-2">
                    <div class="sm:col-span-3">
                        <button type="submit" class="btn-primary px-4 py-2 rounded w-full">Add Book</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Book Inventory -->
        <div id="inventory" class="section" style="display:none;">
            <div class="card overflow-x-auto">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold">Book Inventory</h2>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Author</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">ISBN</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($books as $book): ?>
                        <tr>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($book['title']); ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($book['author']); ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($book['isbn']); ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($book['category']); ?></td>
                            <td class="px-6 py-4"><?php echo $book['price'] !== null ? '₱'.number_format((float)$book['price'],2) : '—'; ?></td>
                            <td class="px-6 py-4">
                                <span class="<?php 
                                    if($book['status']==='available') echo 'status-available';
                                    elseif($book['status']==='borrowed') echo 'status-borrowed';
                                    elseif($book['status']==='archived') echo 'status-archived';
                                ?>"><?php echo ucfirst($book['status']); ?></span>
                            </td>
                            <td class="px-6 py-4 space-x-2">
                                <button onclick="editBook(<?php echo $book['id']; ?>,'<?php echo htmlspecialchars($book['title']); ?>','<?php echo htmlspecialchars($book['author']); ?>','<?php echo htmlspecialchars($book['isbn']); ?>','<?php echo htmlspecialchars($book['category']); ?>','<?php echo htmlspecialchars($book['price']); ?>')" class="text-indigo-600 hover:text-indigo-900">Edit</button>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="<?php echo $book['status']==='archived'?'unarchive_book':'archive_book'; ?>">
                                    <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                    <button type="submit" class="<?php echo $book['status']==='archived'?'text-green-600 hover:text-green-900':'text-red-600 hover:text-red-900'; ?>">
                                        <?php echo $book['status']==='archived'?'Unarchive':'Archive'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden">
    <div class="relative top-20 mx-auto p-6 w-96 shadow-lg rounded-lg bg-white">
        <h3 class="text-lg font-semibold mb-4">Edit Book</h3>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="update_book">
            <input type="hidden" name="book_id" id="edit_book_id">
            <input type="text" name="title" id="edit_title" placeholder="Title" required class="w-full p-2 mb-3 border rounded-md">
            <input type="text" name="author" id="edit_author" placeholder="Author" required class="w-full p-2 mb-3 border rounded-md">
            <input type="text" name="isbn" id="edit_isbn" placeholder="ISBN" class="w-full p-2 mb-3 border rounded-md">
            <input type="text" name="category" id="edit_category" placeholder="Category" class="w-full p-2 mb-3 border rounded-md">
            <input type="number" name="price" id="edit_price" placeholder="Price" step="0.01" min="0" class="w-full p-2 mb-3 border rounded-md">
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 rounded bg-gray-300 hover:bg-gray-400">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function editBook(id,title,author,isbn,category,price){
    document.getElementById('edit_book_id').value=id;
    document.getElementById('edit_title').value=title;
    document.getElementById('edit_author').value=author;
    document.getElementById('edit_isbn').value=isbn;
    document.getElementById('edit_category').value=category||'';
    document.getElementById('edit_price').value=price||'';
    document.getElementById('editModal').classList.remove('hidden');
}
function closeEditModal(){document.getElementById('editModal').classList.add('hidden');}
function showSection(sectionId){
    document.querySelectorAll(".section").forEach(sec=>sec.style.display="none");
    document.getElementById(sectionId).style.display="block";
}
</script>
</body>
</html>
