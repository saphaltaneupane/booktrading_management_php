<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

// Initialize session cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Get genre from URL parameter
$selectedGenre = isset($_GET['genre']) ? trim($_GET['genre']) : '';

if (empty($selectedGenre)) {
    redirect('index.php');
}

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$booksPerPage = 12;
$offset = ($page - 1) * $booksPerPage;

// Get total count of books in this genre
$countQuery = "SELECT COUNT(*) as total FROM books 
               WHERE is_available = 1 AND quantity > 0 AND genre = '" . mysqli_real_escape_string($conn, $selectedGenre) . "'";
$countResult = mysqli_query($conn, $countQuery);
$totalBooks = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalBooks / $booksPerPage);

// Get books for current page
$escapedGenre = mysqli_real_escape_string($conn, $selectedGenre);
$query = "SELECT b.*, AVG(r.rating) as avg_rating, COUNT(r.rating) as rating_count
          FROM books b
          LEFT JOIN ratings r ON b.id = r.book_id
          WHERE b.is_available = 1 AND b.quantity > 0 AND b.genre = '$escapedGenre'
          GROUP BY b.id
          ORDER BY b.created_at DESC
          LIMIT $booksPerPage OFFSET $offset";

$result = mysqli_query($conn, $query);
$books = [];

if ($result) {
    while ($book = mysqli_fetch_assoc($result)) {
        $books[] = $book;
    }
}

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

    redirect("category.php?genre=" . urlencode($selectedGenre) . "&page=" . $page);
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
    redirect("category.php?genre=" . urlencode($selectedGenre) . "&page=" . $page);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($selectedGenre); ?> Books - BookTrading</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar-brand {
            font-weight: bold;
            color: #5D5CDE !important;
            font-size: 1.5rem;
        }

        /* SMALLER BOOK CARDS */
        .card {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
            border-radius: 12px;
            overflow: hidden;
            max-width: 260px;
            margin: 0 auto;
        }
        .card:hover {
            transform: translateY(-6px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        }

        /* COMPACT BOOK IMAGE */
        .book-image {
            height: 200px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .card:hover .book-image {
            transform: scale(1.03);
        }

        /* COMPACT CARD BODY */
        .card-body {
            padding: 0.75rem !important;
        }

        /* SMALLER BOOK TITLE */
        .card-title {
            font-size: 0.85rem !important;
            line-height: 1.2 !important;
            font-weight: 600 !important;
            height: 2rem !important;
            overflow: hidden !important;
            display: -webkit-box !important;
            -webkit-line-clamp: 2 !important;
            -webkit-box-orient: vertical !important;
            text-overflow: ellipsis !important;
            word-wrap: break-word !important;
            margin-bottom: 0.4rem !important;
        }

        /* COMPACT TEXT */
        .card-text {
            font-size: 0.75rem !important;
            margin-bottom: 0.3rem !important;
        }

        /* MUCH SMALLER GENRE BADGE */
        .genre-badge {
            background: linear-gradient(135deg, #5D5CDE, #4a4bc7);
            color: white;
            padding: 1px 4px;
            border-radius: 6px;
            font-size: 0.55rem;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 0.2rem !important;
        }

        /* COMPACT BUTTONS */
        .btn-primary {
            background: linear-gradient(135deg, #5D5CDE, #4a4bc7);
            border: none;
            border-radius: 8px;
            padding: 6px 10px;
            font-weight: 500;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #4a4bc7, #3a3ab7);
            transform: translateY(-2px);
        }

        .btn-sm {
            padding: 3px 6px !important;
            font-size: 0.7rem !important;
            border-radius: 6px !important;
        }

        /* COMPACT RATINGS */
        .rating-stars {
            color: #ffc107;
            font-size: 0.85rem;
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

        /* COMPACT PRICE AND STOCK */
        .price-tag {
            color: #27ae60;
            font-weight: 700;
            font-size: 0.95rem;
        }
        .stock-info {
            color: #7f8c8d;
            font-size: 0.75rem;
        }

        .nav-link {
            font-weight: 500;
            color: #2c3e50 !important;
            transition: color 0.3s ease;
        }
        .nav-link:hover {
            color: #5D5CDE !important;
        }

        /* MUCH SMALLER PAGE HEADER */
        .page-header {
            background: linear-gradient(135deg, #5D5CDE, #4a4bc7);
            color: white;
            padding: 30px 0; /* Reduced from 60px to 30px */
            margin-bottom: 30px; /* Reduced from 40px to 30px */
        }

        /* SMALLER HEADING */
        .page-header h1 {
            font-size: 2rem !important; /* Reduced from display-4 */
            margin-bottom: 0.8rem !important; /* Reduced margin */
        }

        /* SMALLER LEAD TEXT */
        .page-header .lead {
            font-size: 1rem !important; /* Reduced from default lead size */
            margin-bottom: 0 !important;
        }

        .breadcrumb {
            background: none;
            padding: 0;
            margin-bottom: 0.8rem; /* Reduced margin */
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
        .no-books-message {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            margin: 40px 0;
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

        /* MINIMAL SPACING FOR MORE BOOK SPACE */
        .mb-1 {
            margin-bottom: 0.15rem !important;
        }
        .mb-2 {
            margin-bottom: 0.25rem !important;
        }
        .mb-3 {
            margin-bottom: 0.4rem !important;
        }

        /* RESPONSIVE ADJUSTMENTS */
        @media (max-width: 768px) {
            .card {
                max-width: 100%;
            }
            .card-title {
                font-size: 0.8rem !important;
                height: 1.8rem !important;
            }
            .book-image {
                height: 180px;
            }
            .genre-badge {
                font-size: 0.5rem;
                padding: 1px 3px;
            }
            
            /* EVEN SMALLER ON MOBILE */
            .page-header {
                padding: 20px 0; /* Even smaller on mobile */
            }
            .page-header h1 {
                font-size: 1.5rem !important;
            }
            .page-header .lead {
                font-size: 0.9rem !important;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation - ENHANCED TO MATCH INDEX.PHP -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                📚 BookTrading
            </a>

            <!-- Search Bar -->
            <div class="mx-auto" style="width: 400px;">
                <form method="GET" action="search.php" class="d-flex">
                    <div class="input-group">
                        <input type="text" name="q" class="form-control" placeholder="Search amazing books..." style="border-radius: 25px 0 0 25px;">
                        <button class="btn btn-primary" type="submit" style="border-radius: 0 25px 25px 0;">
                            🔍
                        </button>
                    </div>
                </form>
            </div>

            <div class="navbar-nav ms-auto d-flex align-items-center">
                <!-- Cart Icon -->
                <a class="nav-link position-relative me-3" href="cart.php">
                    <span class="cart-icon">🛒</span>
                    <?php if ($cartCount > 0): ?>
                        <span class="cart-badge"><?php echo $cartCount; ?></span>
                    <?php endif; ?>
                </a>

                <?php if (isLoggedIn()): ?>
                    <a class="nav-link" href="userdashboard.php">
                        📊 User Dashboard
                    </a>
                    <?php if (isAdmin()): ?>
                        <a class="nav-link" href="admin/dashboard.php">👑 Admin</a>
                    <?php endif; ?>
                    <a class="nav-link" href="logout.php">
                        🚪 Logout
                    </a>
                <?php else: ?>
                    <a class="nav-link" href="login.php">🔑 Login</a>
                    <a class="nav-link" href="register.php">
                        <button class="btn btn-primary btn-sm">📝 Register</button>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- MUCH SMALLER PAGE HEADER -->
    <div class="page-header">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($selectedGenre); ?></li>
                </ol>
            </nav>
            <h1 class="mb-2">
                🏷️ <?php echo htmlspecialchars($selectedGenre); ?>
            </h1>
            <p class="lead">
                Browse all <?php echo $totalBooks; ?> books in the <?php echo htmlspecialchars($selectedGenre); ?> category
            </p>
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

        <!-- Books Grid -->
        <?php if (!empty($books)): ?>
            <div class="row">
                <?php foreach ($books as $book): ?>
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <div class="card h-100">
                            <div class="position-relative">
                                <img src="uploads/<?php echo $book['image'] ? $book['image'] : 'default.jpg'; ?>"
                                     class="card-img-top book-image" alt="<?php echo htmlspecialchars($book['title']); ?>">
                                <?php if (isset($book['condition_type']) && $book['condition_type'] == 'old'): ?>
                                    <span class="position-absolute top-0 end-0 badge bg-warning m-1" style="font-size: 0.6rem;">♻️ Used</span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title">
                                    <?php echo htmlspecialchars($book['title']); ?>
                                </h5>
                                <p class="card-text text-muted">✍️ by <?php echo htmlspecialchars($book['author']); ?></p>
                                <span class="genre-badge align-self-start">🏷️ <?php echo htmlspecialchars($book['genre']); ?></span>

                                <!-- Rating Display -->
                                <div class="mb-2">
                                    <?php
                                    $avgRating = $book['avg_rating'] ? round($book['avg_rating']) : 0;
                                    for ($i = 1; $i <= 5; $i++):
                                    ?>
                                        <span class="rating-stars"><?php echo $i <= $avgRating ? '★' : '☆'; ?></span>
                                    <?php endfor; ?>
                                    <small class="text-muted ms-1" style="font-size: 0.65rem;">
                                        <?php if ($book['avg_rating'] > 0): ?>
                                            (<?php echo round($book['avg_rating'], 1); ?>/5 - <?php echo $book['rating_count']; ?> reviews)
                                        <?php else: ?>
                                            (No ratings yet)
                                        <?php endif; ?>
                                    </small>
                                </div>

                                <div class="price-tag mb-1">💰 Rs. <?php echo number_format($book['price'], 2); ?></div>
                                <p class="stock-info mb-2">
                                    📦 Stock: <?php echo $book['quantity']; ?>
                                </p>

                                <div class="mt-auto">
                                    <form method="POST" class="d-inline w-100">
                                        <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                        <button type="submit" name="add_to_cart" class="btn btn-primary w-100 mb-2">
                                            🛒 Add to Cart
                                        </button>
                                    </form>

                                    <!-- Rating Form for eligible users -->
                                    <?php if (isLoggedIn() && canUserRateBook($_SESSION['user_id'], $book['id']) && !hasUserRatedBook($_SESSION['user_id'], $book['id'])): ?>
                                        <button class="btn btn-outline-warning btn-sm w-100" onclick="openRatingModal(<?php echo $book['id']; ?>, '<?php echo addslashes($book['title']); ?>')">
                                            ⭐ Rate Book
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
                <nav aria-label="Books pagination">
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?genre=<?php echo urlencode($selectedGenre); ?>&page=<?php echo $page - 1; ?>">
                                    Previous
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?genre=<?php echo urlencode($selectedGenre); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?genre=<?php echo urlencode($selectedGenre); ?>&page=<?php echo $page + 1; ?>">
                                    Next
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>

        <?php else: ?>
            <!-- No Books Message -->
            <div class="no-books-message">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📚</div>
                <h3 class="text-muted">No Books Found</h3>
                <p class="text-muted mb-4">
                    There are currently no books available in the "<?php echo htmlspecialchars($selectedGenre); ?>" category.
                </p>
                <a href="index.php" class="btn btn-primary">
                    Back to Home
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Rating Modal -->
    <div class="modal fade" id="ratingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ratingModalTitle">⭐ Rate Book</h5>
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
                            <label class="form-label">📝 Review (Optional)</label>
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

    <!-- Footer -->
    <footer class="bg-white border-top mt-5 py-4" style="color: #2c3e50;">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 mb-2 mb-md-0 d-flex align-items-center">
                    <span class="fw-bold" style="color: #5D5CDE; font-size: 1.2rem;">
                        📚 BookTrading
                    </span>
                    <span class="ms-3" style="color: #2c3e50;">© 2025. All rights reserved.</span>
                </div>
                <div class="col-md-6 text-md-end mt-2 mt-md-0">
                    <a href="privacy_policy.php" class="text-decoration-none me-3" style="color: #2c3e50;">Privacy Policy</a>
                    <a href="terms.php" class="text-decoration-none" style="color: #2c3e50;">Terms</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="assets/js/bootstrap.min.js"></script>
    <script>
        // Rating Modal Functionality
        function openRatingModal(bookId, bookTitle) {
            document.getElementById('ratingBookId').value = bookId;
            document.getElementById('ratingModalTitle').textContent = '⭐ Rate: ' + bookTitle;

            // Reset stars
            const stars = document.querySelectorAll('#starRating .star');
            stars.forEach(star => star.classList.remove('active'));
            document.getElementById('selectedRating').value = '';
            document.getElementById('submitRating').disabled = true;

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('ratingModal'));
            modal.show();
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