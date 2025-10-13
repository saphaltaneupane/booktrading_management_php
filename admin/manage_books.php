<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$success = '';
$error = '';

// Handle book actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['toggle_availability'])) {
        $bookId = (int)$_POST['book_id'];
        $currentStatus = (int)$_POST['current_status'];
        $newStatus = $currentStatus ? 0 : 1;
        
        $query = "UPDATE books SET is_available = $newStatus WHERE id = $bookId";
        if (mysqli_query($conn, $query)) {
            $success = 'Book availability updated successfully!';
        } else {
            $error = 'Failed to update book availability.';
        }
    }
    
    if (isset($_POST['update_quantity'])) {
        $bookId = (int)$_POST['book_id'];
        $quantity = (int)$_POST['quantity'];
        
        // Only allow quantity updates for admin-added books
        $checkQuery = "SELECT added_by FROM books WHERE id = $bookId";
        $checkResult = mysqli_query($conn, $checkQuery);
        $book = mysqli_fetch_assoc($checkResult);
        
        if ($book['added_by'] == $_SESSION['user_id'] || $book['added_by'] == 1) {
            $query = "UPDATE books SET quantity = $quantity WHERE id = $bookId";
            if (mysqli_query($conn, $query)) {
                $success = 'Book quantity updated successfully!';
            } else {
                $error = 'Failed to update book quantity.';
            }
        } else {
            $error = 'You cannot change quantity of user-added books.';
        }
    }
    
    if (isset($_POST['delete_book'])) {
        $bookId = (int)$_POST['book_id'];
        
        // Admin can delete any book, regardless of quantity or order status
        // Start transaction for safe deletion
        mysqli_begin_transaction($conn);
        
        try {
            // Function to safely delete from table if it exists
            function safeDelete($conn, $tableName, $bookId) {
                // Check if table exists
                $checkTable = "SHOW TABLES LIKE '$tableName'";
                $tableExists = mysqli_query($conn, $checkTable);
                
                if ($tableExists && mysqli_num_rows($tableExists) > 0) {
                    $deleteQuery = "DELETE FROM $tableName WHERE book_id = $bookId";
                    mysqli_query($conn, $deleteQuery);
                }
            }
            
            // Delete from all possible tables that might reference this book
            // Order of deletion is important to avoid foreign key constraint errors
            
            // Delete from order_items first (most likely to exist)
            safeDelete($conn, 'order_items', $bookId);
            
            // Delete from cart (most likely to exist)
            safeDelete($conn, 'cart', $bookId);
            
            // Delete from other tables if they exist
            safeDelete($conn, 'reviews', $bookId);
            safeDelete($conn, 'wishlist', $bookId);
            safeDelete($conn, 'favorites', $bookId);
            safeDelete($conn, 'ratings', $bookId);
            safeDelete($conn, 'book_categories', $bookId);
            
            // Finally, delete the book itself
            $deleteBookQuery = "DELETE FROM books WHERE id = $bookId";
            if (mysqli_query($conn, $deleteBookQuery)) {
                // Commit transaction if all deletions successful
                mysqli_commit($conn);
                $success = 'Book deleted successfully! (Removed from all related tables)';
            } else {
                // Rollback if book deletion fails
                mysqli_rollback($conn);
                $error = 'Failed to delete book: ' . mysqli_error($conn);
            }
        } catch (Exception $e) {
            // Rollback transaction on any error
            mysqli_rollback($conn);
            $error = 'Failed to delete book: ' . $e->getMessage();
        }
    }
}

// Get all books
$query = "SELECT b.*, u.name as added_by_name FROM books b 
          LEFT JOIN users u ON b.added_by = u.id 
          ORDER BY b.created_at DESC";
$books = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Books - Admin</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .admin-sidebar { background-color: #343a40; min-height: 100vh; }
        .admin-sidebar .nav-link { color: #adb5bd; }
        .admin-sidebar .nav-link:hover { color: #fff; background-color: #5D5CDE; }
        .content-area { padding: 2rem; }
        .book-image { width: 50px; height: 70px; object-fit: cover; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin_sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-10 content-area">
                <h1 class="mb-4">Manage Books</h1>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">All Books</h5>
                        <a href="add_book.php" class="btn btn-primary btn-sm">Add New Book</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Author</th>
                                        <th>Genre</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Condition</th>
                                        <th>Added By</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($book = mysqli_fetch_assoc($books)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($book['title']); ?></td>
                                        <td><?php echo htmlspecialchars($book['author']); ?></td>
                                        <td><?php echo htmlspecialchars($book['genre']); ?></td>
                                        <td>Rs. <?php echo number_format($book['price'], 2); ?></td>
                                        <td>
                                            <?php if ($book['added_by'] == $_SESSION['user_id'] || $book['added_by'] == 1): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                                    <input type="number" name="quantity" value="<?php echo $book['quantity']; ?>" 
                                                           class="form-control form-control-sm d-inline" style="width: 70px;" min="0">
                                                    <button type="submit" name="update_quantity" class="btn btn-sm btn-outline-primary">Update</button>
                                                </form>
                                            <?php else: ?>
                                                <?php echo $book['quantity']; ?> <small class="text-muted">(User book)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo ucfirst($book['condition_type']); ?></td>
                                        <td><?php echo $book['added_by_name'] ? htmlspecialchars($book['added_by_name']) : 'Admin'; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $book['is_available'] ? 'success' : 'danger'; ?>">
                                                <?php echo $book['is_available'] ? 'Available' : 'Hidden'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <!-- Toggle Availability - Hide if condition is old and quantity is 0 -->
                                            <?php if (!($book['condition_type'] == 'old' && $book['quantity'] == 0)): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                                    <input type="hidden" name="current_status" value="<?php echo $book['is_available']; ?>">
                                                    <button type="submit" name="toggle_availability" 
                                                            class="btn btn-sm btn-outline-<?php echo $book['is_available'] ? 'warning' : 'success'; ?>" 
                                                            title="<?php echo $book['is_available'] ? 'Hide' : 'Show'; ?>">
                                                        <?php echo $book['is_available'] ? 'Hide' : 'Show'; ?>
                                                    </button>
                                                </form>
                                                <span style="margin-right: 8px;"></span>
                                            <?php endif; ?>
                                            
                                            <!-- Delete Book -->
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this book? This will permanently remove it from all related tables.')">
                                                <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>"><br>
                                                <button type="submit" name="delete_book" class="btn btn-sm btn-outline-danger" title="Delete">
                                                    Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>