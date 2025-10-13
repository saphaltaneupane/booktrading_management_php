<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

// Initialize session cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Get search query
$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';
$books = [];
$totalResults = 0;

if (!empty($searchQuery)) {
    // Search for books
    $books = searchBooks($searchQuery);
    $totalResults = count($books);
}

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$booksPerPage = 12;
$offset = ($page - 1) * $booksPerPage;
$totalPages = ceil($totalResults / $booksPerPage);

// Slice results for pagination
$paginatedBooks = array_slice($books, $offset, $booksPerPage);

// Get cart count
$cartCount = 0;
if (isLoggedIn()) {
    $cartQuery = "SELECT SUM(quantity) as total FROM cart WHERE user_id = {$_SESSION['user_id']}";
    $cartResult = mysqli_query($conn, $cartQuery);
    $cartData = mysqli_fetch_assoc($cartResult);
    $cartCount = $cartData['total'] ? $cartData['total'] : 0;
} else {
    // Count session cart items
    foreach ($_SESSION['cart'] as $quantity) {
        $cartCount += $quantity;
    }
}

// Handle cart addition
if (isset($_POST['add_to_cart'])) {
    $bookId = $_POST['book_id'];

    if (isLoggedIn()) {
        $userId = $_SESSION['user_id'];

        // Check if book already in cart
        $checkQuery = "SELECT * FROM cart WHERE user_id = $userId AND book_id = $bookId";
        $checkResult = mysqli_query($conn, $checkQuery);

        if (mysqli_num_rows($checkResult) > 0) {
            $updateQuery = "UPDATE cart SET quantity = quantity + 1 WHERE user_id = $userId AND book_id = $bookId";
            mysqli_query($conn, $updateQuery);
        } else {
            $insertQuery = "INSERT INTO cart (user_id, book_id, quantity) VALUES ($userId, $bookId, 1)";
            mysqli_query($conn, $insertQuery);
        }
    } else {
        // Add to session cart
        if (isset($_SESSION['cart'][$bookId])) {
            $_SESSION['cart'][$bookId]++;
        } else {
            $_SESSION['cart'][$bookId] = 1;
        }
    }

    redirect("search.php?q=" . urlencode($searchQuery) . "&page=" . $page);
}

// Handle rating submission
if (isset($_POST['rate_book'])) {
    if (isLoggedIn()) {
        $bookId = $_POST['book_id'];
        $rating = $_POST['rating'];
        $review = mysqli_real_escape_string($conn, $_POST['review']);
        $userId = $_SESSION['user_id'];

        if (canUserRateBook($userId, $bookId) && !hasUserRatedBook($userId, $bookId)) {
            $query = "INSERT INTO ratings (user_id, book_id, rating, review) VALUES ($userId, $bookId, $rating, '$review')";
            if (mysqli_query($conn, $query)) {
                $_SESSION['success_message'] = 'Rating submitted successfully!';
            }
        }
    }
    redirect("search.php?q=" . urlencode($searchQuery) . "&page=" . $page);
}

// Handle AJAX request for reviews
if (isset($_GET['action']) && $_GET['action'] === 'get_reviews' && isset($_GET['book_id'])) {
    $bookId = (int)$_GET['book_id'];
    $reviewsQuery = "SELECT r.*, u.name as reviewer_name 
                    FROM ratings r 
                    JOIN users u ON r.user_id = u.id 
                    WHERE r.book_id = $bookId 
                    ORDER BY r.created_at DESC";
    $reviewsResult = mysqli_query($conn, $reviewsQuery);
    $reviews = [];
    while ($review = mysqli_fetch_assoc($reviewsResult)) {
        $reviews[] = $review;
    }
    header('Content-Type: application/json');
    echo json_encode($reviews);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo !empty($searchQuery) ? 'Search: ' . htmlspecialchars($searchQuery) : 'Search Books'; ?> - BookTrading</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar-brand {
            font-weight: bold;
            color: #5D5CDE !important;
            font-size: 1.5rem;
        }
        .card {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-radius: 15px;
            overflow: hidden;
        }
        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .book-image {
            height: 280px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .card:hover .book-image {
            transform: scale(1.05);
        }
        .btn-primary {
            background: linear-gradient(135deg, #5D5CDE, #4a4bc7);
            border: none;
            border-radius: 10px;
            padding: 8px 16px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #4a4bc7, #3a3ab7);
            transform: translateY(-2px);
        }
        .rating-stars {
            color: #ffc107;
            font-size: 1.1rem;
        }
        .genre-badge {
            background: linear-gradient(135deg, #5D5CDE, #4a4bc7);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .cart-icon {
            position: relative;
            color: #5D5CDE;
            font-size: 1.4rem;
        }
        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .price-tag {
            color: #27ae60;
            font-weight: 700;
            font-size: 1.2rem;
        }
        .stock-info {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        .nav-link {
            font-weight: 500;
            color: #2c3e50 !important;
            transition: color 0.3s ease;
        }
        .nav-link:hover {
            color: #5D5CDE !important;
        }
        .search-header {
            background: linear-gradient(135deg, #5D5CDE, #4a4bc7);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
        }
        .search-stats {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .breadcrumb {
            background: none;
            padding: 0;
        }
        .breadcrumb-item a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
        }
        .breadcrumb-item.active {
            color: white;
        }
        .pagination {
            justify-content: center;
            margin-top: 40px;
        }
        .page-link {
            color: #5D5CDE;
            border: 1px solid #dee2e6;
            margin: 0 2px;
            border-radius: 8px;
        }
        .page-link:hover {
            color: #4a4bc7;
            background-color: #f8f9fa;
        }
        .page-item.active .page-link {
            background-color: #5D5CDE;
            border-color: #5D5CDE;
        }
        .no-results {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            margin: 40px 0;
        }
        .search-suggestions {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        /* Interactive Star Rating */
        .star-rating {
            display: flex;
            gap: 5px;
            margin-bottom: 15px;
        }
        .star-rating .star {
            font-size: 24px;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
        }
        .star-rating .star:hover,
        .star-rating .star.active {
            color: #ffc107;
        }
        .star-rating .star:hover ~ .star {
            color: #ddd;
        }

        /* Reviews Modal Styling */
        .review-item {
            border-left: 4px solid #5D5CDE;
            padding: 15px;
            margin-bottom: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .reviewer-name {
            font-weight: 600;
            color: #2c3e50;
        }
        .review-date {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        .review-rating {
            color: #ffc107;
            margin-bottom: 8px;
        }
        .review-text {
            color: #2c3e50;
            line-height: 1.6;
        }
        .reviews-summary {
            background: linear-gradient(135deg, #5D5CDE, #4a4bc7);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        .rating-breakdown {
            margin: 15px 0;
        }
        .rating-bar {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }
        .rating-label {
            width: 60px;
            font-size: 0.9rem;
        }
        .progress {
            flex: 1;
            height: 8px;
            margin: 0 10px;
        }
        .rating-count {
            width: 30px;
            font-size: 0.9rem;
            text-align: right;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-book"></i> BookTrading
            </a>

            <!-- Search Bar -->
            <div class="mx-auto" style="width: 400px;">
                <form method="GET" action="search.php" class="d-flex">
                    <div class="input-group">
                        <input type="text" name="q" class="form-control" placeholder="Search amazing books..." value="<?php echo htmlspecialchars($searchQuery); ?>" style="border-radius: 25px 0 0 25px;">
                        <button class="btn btn-primary" type="submit" style="border-radius: 0 25px 25px 0;">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>

            <div class="navbar-nav ms-auto d-flex align-items-center">
                <!-- Cart Icon -->
                <a class="nav-link position-relative me-3" href="cart.php">
                    <i class="fas fa-shopping-cart cart-icon"></i>
                    <?php if ($cartCount > 0): ?>
                        <span class="cart-badge"><?php echo $cartCount; ?></span>
                    <?php endif; ?>
                </a>

                <?php if (isLoggedIn()): ?>
                    <a class="nav-link" href="add_book.php">Sell Book</a>
                    <a class="nav-link" href="orders.php">Orders</a>
                    <a class="nav-link" href="profile.php">Profile</a>
                    <?php if (isAdmin()): ?>
                        <a class="nav-link" href="admin/dashboard.php">Admin</a>
                    <?php endif; ?>
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                <?php else: ?>
                    <a class="nav-link" href="login.php">Login</a>
                    <a class="nav-link" href="register.php">
                        <button class="btn btn-primary btn-sm">Register</button>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Search Header -->
    <div class="search-header">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Search Results</li>
                </ol>
            </nav>
            <h1 class="display-4 mb-3">
                <i class="fas fa-search"></i> Search Results
            </h1>
            <?php if (!empty($searchQuery)): ?>
                <p class="lead">
                    Showing results for: <strong>"<?php echo htmlspecialchars($searchQuery); ?>"</strong>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <!-- Success Message -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (empty($searchQuery)): ?>
            <!-- Search Prompt -->
            <div class="no-results">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h3 class="text-muted">Start Your Search</h3>
                <p class="text-muted mb-4">Enter keywords to search for books by title, author, or genre.</p>
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-home"></i> Browse All Books
                </a>
            </div>
        <?php elseif ($totalResults > 0): ?>
            <!-- Search Statistics -->
            <div class="search-stats">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-0">
                            <i class="fas fa-book text-primary"></i>
                            Found <?php echo $totalResults; ?> book<?php echo $totalResults > 1 ? 's' : ''; ?>
                        </h5>
                        <small class="text-muted">for "<?php echo htmlspecialchars($searchQuery); ?>"</small>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <?php if ($totalPages > 1): ?>
                            <small class="text-muted">
                                Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Search Results -->
            <div class="row">
                <?php foreach ($paginatedBooks as $book): ?>
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <div class="card h-100">
                            <div class="position-relative">
                                <img src="uploads/<?php echo $book['image'] ? $book['image'] : 'default.jpg'; ?>"
                                     class="card-img-top book-image" alt="<?php echo htmlspecialchars($book['title']); ?>">
                                <?php if (isset($book['condition_type']) && $book['condition_type'] == 'old'): ?>
                                    <span class="position-absolute top-0 end-0 badge bg-warning m-2">Used</span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title mb-2" style="height: 3rem; overflow: hidden;">
                                    <?php echo htmlspecialchars($book['title']); ?>
                                </h5>
                                <p class="card-text text-muted mb-2">by <?php echo htmlspecialchars($book['author']); ?></p>
                                <span class="genre-badge mb-3 align-self-start"><?php echo htmlspecialchars($book['genre']); ?></span>

                                <!-- Rating Display -->
                                <div class="mb-3">
                                    <?php
                                    $avgRating = isset($book['avg_rating']) && $book['avg_rating'] ? round($book['avg_rating']) : 0;
                                    $ratingCount = isset($book['rating_count']) ? $book['rating_count'] : 0;
                                    for ($i = 1; $i <= 5; $i++):
                                    ?>
                                        <span class="rating-stars"><?php echo $i <= $avgRating ? '★' : '☆'; ?></span>
                                    <?php endfor; ?>
                                    <small class="text-muted ms-1">
                                        <?php if (isset($book['avg_rating']) && $book['avg_rating'] > 0): ?>
                                            (<?php echo round($book['avg_rating'], 1); ?>/5 - <?php echo $ratingCount; ?> reviews)
                                        <?php else: ?>
                                            (No ratings yet)
                                        <?php endif; ?>
                                    </small>
                                </div>

                                <div class="price-tag mb-2">Rs. <?php echo number_format($book['price'], 2); ?></div>
                                <p class="stock-info mb-3">
                                    <i class="fas fa-box"></i> Stock: <?php echo $book['quantity']; ?>
                                </p>

                                <div class="mt-auto">
                                    <form method="POST" class="d-inline w-100">
                                        <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                        <button type="submit" name="add_to_cart" class="btn btn-primary w-100 mb-2">
                                            <i class="fas fa-cart-plus"></i> Add to Cart
                                        </button>
                                    </form>

                                    <!-- View Reviews Button -->
                                    <?php if ($ratingCount > 0): ?>
                                        <button class="btn btn-outline-info btn-sm w-100 mb-2" onclick="viewReviews(<?php echo $book['id']; ?>, '<?php echo addslashes($book['title']); ?>')">
                                            <i class="fas fa-comments"></i> View Reviews (<?php echo $ratingCount; ?>)
                                        </button>
                                    <?php endif; ?>

                                    <!-- Rating Form for eligible users -->
                                    <?php if (isLoggedIn() && canUserRateBook($_SESSION['user_id'], $book['id']) && !hasUserRatedBook($_SESSION['user_id'], $book['id'])): ?>
                                        <button class="btn btn-outline-warning btn-sm w-100" onclick="openRatingModal(<?php echo $book['id']; ?>, '<?php echo addslashes($book['title']); ?>')">
                                            <i class="fas fa-star"></i> Rate Book
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Search results pagination">
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?q=<?php echo urlencode($searchQuery); ?>&page=<?php echo $page - 1; ?>">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?q=<?php echo urlencode($searchQuery); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?q=<?php echo urlencode($searchQuery); ?>&page=<?php echo $page + 1; ?>">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>

        <?php else: ?>
            <!-- No Results -->
            <div class="no-results">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h3 class="text-muted">No Books Found</h3>
                <p class="text-muted mb-4">
                    Sorry, we couldn't find any books matching "<strong><?php echo htmlspecialchars($searchQuery); ?></strong>".
                </p>
                
                <div class="search-suggestions">
                    <h6><i class="fas fa-lightbulb"></i> Search Tips:</h6>
                    <ul class="text-start">
                        <li>Check your spelling</li>
                        <li>Try different keywords</li>
                        <li>Use broader search terms</li>
                        <li>Search by author name or book genre</li>
                    </ul>
                </div>
                
                <div class="mt-4">
                    <a href="index.php" class="btn btn-primary me-2">
                        <i class="fas fa-home"></i> Browse All Books
                    </a>
                    <button class="btn btn-outline-primary" onclick="document.querySelector('input[name=q]').focus()">
                        <i class="fas fa-search"></i> Try Another Search
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Rating Modal -->
    <div class="modal fade" id="ratingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ratingModalTitle">Rate Book</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="ratingForm">
                    <div class="modal-body">
                        <input type="hidden" name="book_id" id="ratingBookId">
                        <input type="hidden" name="rating" id="selectedRating">

                        <div class="mb-3">
                            <label class="form-label">Rating (1-5 stars)</label>
                            <div class="star-rating" id="starRating">
                                <span class="star" data-rating="1">★</span>
                                <span class="star" data-rating="2">★</span>
                                <span class="star" data-rating="3">★</span>
                                <span class="star" data-rating="4">★</span>
                                <span class="star" data-rating="5">★</span>
                            </div>
                            <small class="text-muted">Click on stars to rate</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Review (Optional)</label>
                            <textarea name="review" class="form-control" rows="3" placeholder="Share your thoughts about this book..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="rate_book" class="btn btn-primary" id="submitRating" disabled>Submit Rating</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reviews Modal -->
    <div class="modal fade" id="reviewsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reviewsModalTitle">Reviews & Ratings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="reviewsContent">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading reviews...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white mt-5 py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-book"></i> BookTrading</h5>
                    <p class="text-muted">Your one-stop destination for buying, selling, and trading books online.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>&copy; 2024 BookTrading. All rights reserved.</p>
                    <div>
                        <a href="#" class="text-white me-3"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.min.js"></script>
    <script>
        // Rating Modal Functionality
        function openRatingModal(bookId, bookTitle) {
            document.getElementById('ratingBookId').value = bookId;
            document.getElementById('ratingModalTitle').textContent = 'Rate: ' + bookTitle;

            // Reset stars
            const stars = document.querySelectorAll('#starRating .star');
            stars.forEach(star => star.classList.remove('active'));
            document.getElementById('selectedRating').value = '';
            document.getElementById('submitRating').disabled = true;

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('ratingModal'));
            modal.show();
        }

        // Reviews Modal Functionality
        function viewReviews(bookId, bookTitle) {
            document.getElementById('reviewsModalTitle').textContent = 'Reviews for: ' + bookTitle;

            // Show loading
            document.getElementById('reviewsContent').innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading reviews...</p>
                </div>
            `;

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('reviewsModal'));
            modal.show();

            // Fetch reviews
            fetch(`search.php?action=get_reviews&book_id=${bookId}`)
                .then(response => response.json())
                .then(reviews => {
                    displayReviews(reviews);
                })
                .catch(error => {
                    document.getElementById('reviewsContent').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            Error loading reviews. Please try again.
                        </div>
                    `;
                });
        }

        function displayReviews(reviews) {
            if (reviews.length === 0) {
                document.getElementById('reviewsContent').innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Reviews Yet</h5>
                        <p class="text-muted">Be the first to share your thoughts about this book!</p>
                    </div>
                `;
                return;
            }

            // Calculate rating statistics
            const totalReviews = reviews.length;
            const averageRating = reviews.reduce((sum, review) => sum + parseInt(review.rating), 0) / totalReviews;
            const ratingCounts = [0, 0, 0, 0, 0];
            reviews.forEach(review => {
                ratingCounts[parseInt(review.rating) - 1]++;
            });

            let reviewsHTML = `
                <div class="reviews-summary">
                    <h4><i class="fas fa-star"></i> ${averageRating.toFixed(1)} / 5</h4>
                    <p class="mb-0">Based on ${totalReviews} review${totalReviews > 1 ? 's' : ''}</p>
                </div>

                <div class="rating-breakdown">
            `;

            // Rating breakdown
            for (let i = 5; i >= 1; i--) {
                const count = ratingCounts[i - 1];
                const percentage = totalReviews > 0 ? (count / totalReviews) * 100 : 0;
                reviewsHTML += `
                    <div class="rating-bar">
                        <span class="rating-label">${i} star${i > 1 ? 's' : ''}</span>
                        <div class="progress">
                            <div class="progress-bar bg-warning" style="width: ${percentage}%"></div>
                        </div>
                        <span class="rating-count">${count}</span>
                    </div>
                `;
            }

            reviewsHTML += `</div><hr>`;

            // Individual reviews
            reviews.forEach(review => {
                const reviewDate = new Date(review.created_at).toLocaleDateString();
                const stars = '★'.repeat(parseInt(review.rating)) + '☆'.repeat(5 - parseInt(review.rating));

                reviewsHTML += `
                    <div class="review-item">
                        <div class="review-header">
                            <div>
                                <div class="reviewer-name">${escapeHtml(review.reviewer_name)}</div>
                                <div class="review-rating">${stars}</div>
                            </div>
                            <div class="review-date">${reviewDate}</div>
                        </div>
                        ${review.review ? `<div class="review-text">${escapeHtml(review.review)}</div>` : '<div class="text-muted fst-italic">No written review</div>'}
                    </div>
                `;
            });

            document.getElementById('reviewsContent').innerHTML = reviewsHTML;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Star rating functionality
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('#starRating .star');
            const selectedRatingInput = document.getElementById('selectedRating');
            const submitButton = document.getElementById('submitRating');

            stars.forEach((star, index) => {
                star.addEventListener('click', function() {
                    const rating = this.getAttribute('data-rating');
                    selectedRatingInput.value = rating;

                    // Update star display
                    stars.forEach((s, i) => {
                        if (i < rating) {
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                        }
                    });

                    // Enable submit button
                    submitButton.disabled = false;
                });

                star.addEventListener('mouseenter', function() {
                    const rating = this.getAttribute('data-rating');
                    stars.forEach((s, i) => {
                        if (i < rating) {
                            s.style.color = '#ffc107';
                        } else {
                            s.style.color = '#ddd';
                        }
                    });
                });
            });

            // Reset hover effect on mouse leave
            document.getElementById('starRating').addEventListener('mouseleave', function() {
                const currentRating = selectedRatingInput.value;
                stars.forEach((s, i) => {
                    if (i < currentRating) {
                        s.style.color = '#ffc107';
                    } else {
                        s.style.color = '#ddd';
                    }
                });
            });
        });

        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('show')) {
                    alert.classList.remove('show');
                    setTimeout(() => alert.remove(), 150);
                }
            });
        }, 5000);
    </script>
</body>
</html>