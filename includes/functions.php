<?php
// Ensure no output is sent before this point
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check if logged in user is admin
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

// Function to redirect
function redirect($url) {
    // Ensure no output is sent before calling header()
    if (headers_sent()) {
        echo "<script>window.location.href='$url';</script>";
        exit();
    }
    header("Location: $url");
    exit();
}

// Function to display error message
function displayError($message) {
    return "<div class='alert alert-danger'>$message</div>";
}

// Function to display success message
function displaySuccess($message) {
    return "<div class='alert alert-success'>$message</div>";
}

// Function to sanitize input
function sanitize($data) {
    global $conn;
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($data)));
}

// Function to validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Function to validate phone number (10 digits)
function isValidPhone($phone) {
    return preg_match('/^[0-9]{10}$/', $phone);
}

/**
 * Get all available books (status must be 'available')
 */
function getAvailableBooks() {
    global $conn;
    
    $query = "SELECT b.*, u.name as added_by_name 
              FROM books b 
              LEFT JOIN users u ON b.added_by = u.id 
              WHERE b.is_available = 1
              ORDER BY b.created_at DESC";
    
    $result = mysqli_query($conn, $query);
    $books = [];
    
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $books[] = $row;
        }
    }
    
    return $books;
}

/**
 * Get all books regardless of status (for admin)
 */
function getAllBooks() {
    global $conn;
    
    $query = "SELECT b.*, u.name as added_by_name 
              FROM books b 
              LEFT JOIN users u ON b.added_by = u.id 
              ORDER BY b.created_at DESC";
    
    $result = mysqli_query($conn, $query);
    $books = [];
    
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $books[] = $row;
        }
    }
    
    return $books;
}

/**
 * Get book by ID
 */
function getBookById($id) {
    global $conn;
    
    $id = (int)$id; // Ensure it's an integer
    
    $query = "SELECT b.*, u.name as added_by_name 
              FROM books b 
              LEFT JOIN users u ON b.added_by = u.id 
              WHERE b.id = $id";
    
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return null;
}

/**
 * FIXED: Get ONLY books with rating >= 4.0 (consistent with recommendation.php)
 * @param int $limit The maximum number of recommendations to return.
 * @param array $excludeIds Book IDs to exclude
 * @return array Books with 4+ star ratings ONLY
 */
function getTopRatedBooks($limit = 5, $excludeIds = []) {
    global $conn;

    $excludeClause = '';
    if (!empty($excludeIds)) {
        $excludeClause = 'AND b.id NOT IN (' . implode(',', array_map('intval', $excludeIds)) . ')';
    }

    // STRICT: Query to fetch ONLY books with average rating >= 4.0
    $query = "SELECT b.*, 
              COALESCE(AVG(r.rating), 0) as avg_rating, 
              COUNT(r.id) as rating_count 
              FROM books b 
              JOIN ratings r ON b.id = r.book_id 
              WHERE b.quantity > 0 AND b.is_available = 1
              $excludeClause
              GROUP BY b.id
              HAVING AVG(r.rating) >= 4.0 AND COUNT(r.id) >= 1
              ORDER BY AVG(r.rating) DESC, COUNT(r.id) DESC
              LIMIT $limit";
    
    $result = mysqli_query($conn, $query);
    $books = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            // Double check the rating to be absolutely sure
            if ($row['avg_rating'] >= 4.0) {
                $books[] = $row;
            }
        }
    }
    
    // NO FALLBACK - Only return 4+ star books
    error_log("getTopRatedBooks: Found " . count($books) . " books with 4+ stars");
    return $books;
}

function getBooksByGenre($genre, $limit = 4) {
    global $conn;
    $query = "SELECT b.*, AVG(r.rating) as avg_rating, COUNT(r.rating) as rating_count 
              FROM books b 
              LEFT JOIN ratings r ON b.id = r.book_id 
              WHERE b.genre = '$genre' AND b.is_available = 1 AND b.quantity > 0 
              GROUP BY b.id 
              ORDER BY avg_rating DESC 
              LIMIT $limit";
    $result = mysqli_query($conn, $query);
    $books = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $books[] = $row;
    }
    return $books;
}

// Function to get user by ID
function getUserById($userId) {
    global $conn;
    $query = "SELECT * FROM users WHERE id = '$userId'";
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_assoc($result);
}

// Function to get all users (for admin)
function getAllUsers() {
    global $conn;
    $query = "SELECT * FROM users WHERE is_admin = 0";
    $result = mysqli_query($conn, $query);
    $users = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
    
    return $users;
}

/**
 * Update book quantity after confirmed purchase.
 * 
 * @param int $bookId The ID of the book.
 * @param int $orderQuantity The quantity being ordered.
 * @return bool Success status.
 */
function updateBookQuantity($bookId, $orderQuantity = 1) {
    global $conn;
    
    // Log the function call for debugging
    error_log("Updating book quantity: Book ID=$bookId, Quantity=$orderQuantity");
    
    // Get current book information
    $query = "SELECT quantity, is_available FROM books WHERE id = $bookId";
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        error_log("Query error in updateBookQuantity: " . mysqli_error($conn));
        return false;
    }
    
    if (mysqli_num_rows($result) === 0) {
        error_log("Book ID $bookId not found");
        return false;
    }
    
    $book = mysqli_fetch_assoc($result);
    $currentQuantity = (int)$book['quantity'];
    
    // Calculate new quantity
    $newQuantity = $currentQuantity - $orderQuantity;
    
    // Prevent negative quantity
    if ($newQuantity < 0) {
        $newQuantity = 0;
    }
    
    // Determine new availability
    $newAvailability = $newQuantity > 0 ? 1 : 0;
    
    // Update book quantity and availability
    $updateQuery = "UPDATE books SET quantity = $newQuantity, is_available = $newAvailability WHERE id = $bookId";
    $updateResult = mysqli_query($conn, $updateQuery);
    
    if (!$updateResult) {
        error_log("Update error in updateBookQuantity: " . mysqli_error($conn));
    }
    
    return $updateResult;
}

/**
 * Decrement the quantity of books in an order.
 * If quantity reaches 0 or less, update book availability.
 * 
 * @param int $orderId The ID of the order.
 */
function decrementBookQuantity($orderId) {
    global $conn;
    $query = "SELECT book_id, quantity FROM order_items WHERE order_id = $orderId";
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $bookId = $row['book_id'];
        $orderQuantity = $row['quantity'] ?? 1;
        
        // Get current quantity
        $qtyQuery = "SELECT quantity FROM books WHERE id = $bookId";
        $qtyResult = mysqli_query($conn, $qtyQuery);
        $book = mysqli_fetch_assoc($qtyResult);
        
        if ($book) {
            $currentQuantity = (int)$book['quantity'];
            
            // Calculate new quantity
            $newQuantity = $currentQuantity - $orderQuantity;
            if ($newQuantity < 0) {
                $newQuantity = 0;
            }
            
            // Update quantity and availability if needed
            if ($newQuantity == 0) {
                $updateQuery = "UPDATE books SET quantity = 0, is_available = 0 WHERE id = $bookId";
            } else {
                $updateQuery = "UPDATE books SET quantity = $newQuantity WHERE id = $bookId";
            }
            
            mysqli_query($conn, $updateQuery);
        }
    }
}

/**
 * Function to get book rating
 */
function getBookRating($bookId) {
    global $conn;
    $query = "SELECT AVG(rating) as avg_rating FROM ratings WHERE book_id = '$bookId'";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return round($row['avg_rating'], 1);
}

// Function to search books
function searchBooks($keyword) {
    global $conn;
    $keyword = sanitize($keyword);
    $query = "SELECT b.*, AVG(r.rating) as avg_rating, COUNT(r.rating) as rating_count
              FROM books b
              LEFT JOIN ratings r ON b.id = r.book_id
              WHERE b.is_available = 1 AND (b.title LIKE '%$keyword%' OR b.author LIKE '%$keyword%' OR b.genre LIKE '%$keyword%')
              GROUP BY b.id
              ORDER BY b.created_at DESC";
    $result = mysqli_query($conn, $query);
    $books = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $books[] = $row;
    }

    return $books;
}

// Function to get user orders
function getUserOrders($userId) {
    global $conn;
    $query = "SELECT * FROM orders WHERE user_id = '$userId' ORDER BY created_at DESC";
    $result = mysqli_query($conn, $query);
    $orders = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $orders[] = $row;
    }
    
    return $orders;
}

// Function to get order items
function getOrderItems($orderId) {
    global $conn;
    $query = "SELECT oi.*, b.title, b.author FROM order_items oi 
              JOIN books b ON oi.book_id = b.id 
              WHERE oi.order_id = '$orderId'";
    $result = mysqli_query($conn, $query);
    $items = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    
    return $items;
}

/**
 * Update book availability when the order is completed.
 * 
 * @param int $orderId The ID of the order.
 */
function updateBookAvailability($orderId) {
    global $conn;
    $query = "SELECT book_id FROM order_items WHERE order_id = $orderId";
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $bookId = $row['book_id'];
        // Check quantity before updating availability
        $qtyQuery = "SELECT quantity FROM books WHERE id = $bookId";
        $qtyResult = mysqli_query($conn, $qtyQuery);
        $book = mysqli_fetch_assoc($qtyResult);
        $quantity = $book ? (int)$book['quantity'] : 0;
        if ($quantity == 0) {
            $updateQuery = "UPDATE books SET is_available = 0 WHERE id = $bookId";
            mysqli_query($conn, $updateQuery);
        }
    }
}

/**
 * Check if a user has purchased a book with completed status.
 * 
 * @param int $userId The ID of the user.
 * @param int $bookId The ID of the book.
 * @return bool True if the user has purchased the book with completed status, false otherwise.
 */
function hasCompletedPurchase($userId, $bookId) {
    global $conn;
    
    $query = "SELECT o.id FROM orders o 
              JOIN order_items oi ON o.id = oi.order_id 
              WHERE o.user_id = $userId 
              AND oi.book_id = $bookId 
              AND o.payment_status = 'completed' 
              AND o.status = 'completed'";
    
    $result = mysqli_query($conn, $query);
    
    return mysqli_num_rows($result) > 0;
}

function getCartItems($userId) {
    global $conn;
    $query = "SELECT c.*, b.title, b.author, b.price, b.image, b.quantity as stock 
              FROM cart c 
              JOIN books b ON c.book_id = b.id 
              WHERE c.user_id = $userId AND b.is_available = 1";
    $result = mysqli_query($conn, $query);
    $items = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    return $items;
}

function getCartTotal($userId) {
    global $conn;
    $query = "SELECT SUM(c.quantity * b.price) as total 
              FROM cart c 
              JOIN books b ON c.book_id = b.id 
              WHERE c.user_id = $userId AND b.is_available = 1";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['total'] ? $row['total'] : 0;
}

function canUserRateBook($userId, $bookId) {
    global $conn;
    $query = "SELECT COUNT(*) as count FROM orders o 
              JOIN order_items oi ON o.id = oi.order_id 
              WHERE o.user_id = $userId AND oi.book_id = $bookId 
              AND o.payment_status = 'completed' AND o.order_status = 'delivered'";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['count'] > 0;
}

function hasUserRatedBook($userId, $bookId) {
    global $conn;
    $query = "SELECT COUNT(*) as count FROM ratings WHERE user_id = $userId AND book_id = $bookId";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['count'] > 0;
}

/**
 * Delete a user by ID
 * 
 * @param int $userId The ID of the user to delete
 * @return bool True if successful, false otherwise
 */
function deleteUser($userId) {
    global $conn;
    
    // Convert to integer for security
    $userId = (int)$userId;
    
    // Begin transaction to ensure data integrity
    mysqli_begin_transaction($conn);
    
    try {
        // First, delete any related records
        // Delete user's orders and order items
        $orderQuery = "SELECT id FROM orders WHERE user_id = $userId";
        $orderResult = mysqli_query($conn, $orderQuery);
        
        if ($orderResult) {
            while ($order = mysqli_fetch_assoc($orderResult)) {
                $orderId = $order['id'];
                // Delete order items
                mysqli_query($conn, "DELETE FROM order_items WHERE order_id = $orderId");
            }
        }
        
        // Delete user's orders
        mysqli_query($conn, "DELETE FROM orders WHERE user_id = $userId");
        
        // Delete user's ratings/reviews
        mysqli_query($conn, "DELETE FROM ratings WHERE user_id = $userId");
        
        // Delete the user
        $deleteQuery = "DELETE FROM users WHERE id = $userId";
        $result = mysqli_query($conn, $deleteQuery);
        
        if ($result && mysqli_affected_rows($conn) > 0) {
            // Commit transaction
            mysqli_commit($conn);
            return true;
        } else {
            // Rollback if user deletion failed
            mysqli_rollback($conn);
            return false;
        }
    } catch (Exception $e) {
        // Rollback on any error
        mysqli_rollback($conn);
        return false;
    }
}
?>