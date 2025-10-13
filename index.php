<?php
require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/recommendation.php';

// Initialize session cart if not exists
if (!isset($_SESSION['cart'])) {
$_SESSION['cart'] = [];
}

// FIXED: Use consistent field names - check your actual database schema
$genresQuery = "SELECT DISTINCT genre FROM books WHERE is_available = 1 AND quantity > 0 ORDER BY genre";
$genresResult = mysqli_query($conn, $genresQuery);
$genres = [];
while ($row = mysqli_fetch_assoc($genresResult)) {
$genres[] = $row['genre'];
}

// Get books by genre
$booksByGenre = [];
foreach ($genres as $genre) {
$escapedGenre = mysqli_real_escape_string($conn, $genre);
$query = "SELECT b.*, AVG(r.rating) as avg_rating, COUNT(r.rating) as rating_count
FROM books b
LEFT JOIN ratings r ON b.id = r.book_id
WHERE b.is_available = 1 AND b.quantity > 0 AND b.genre = '$escapedGenre'
GROUP BY b.id
ORDER BY b.created_at DESC
LIMIT 4";
$result = mysqli_query($conn, $query);
if (!$result) {
error_log("MySQL error: " . mysqli_error($conn) . " in query: $query");
$booksByGenre[$genre] = [];
continue;
}
$booksByGenre[$genre] = [];
while ($book = mysqli_fetch_assoc($result)) {
$booksByGenre[$genre][] = $book;
}
}

// Enhanced recommendation logic
if (isLoggedIn()) {
$recommendedBooks = getRecommendedBooks($_SESSION['user_id'], 4);
$sectionTitle = 'Recommended for You';
$sectionDescription = 'Based on your purchase history and preferences';
} else {
$recommendedBooks = [];
}

// If no content-based recommendations available, show 4+ star books
if (empty($recommendedBooks)) {
$fallbackQuery = "SELECT b.*, AVG(r.rating) as avg_rating, COUNT(r.rating) as rating_count
FROM books b
LEFT JOIN ratings r ON b.id = r.book_id
WHERE b.is_available = 1 AND b.quantity > 0
GROUP BY b.id
HAVING AVG(r.rating) >= 4 AND COUNT(r.rating) > 0
ORDER BY AVG(r.rating) DESC, COUNT(r.rating) DESC
LIMIT 4";
$fallbackResult = mysqli_query($conn, $fallbackQuery);
if ($fallbackResult) {
while ($book = mysqli_fetch_assoc($fallbackResult)) {
$recommendedBooks[] = $book;
}
}

if (isLoggedIn()) {
$sectionTitle = 'Top Rated Books (4+ ⭐)';
$sectionDescription = 'High quality books while we learn your preferences';
} else {
$sectionTitle = 'Top Rated Books (4+ ⭐)';
$sectionDescription = 'Highly rated books with 4+ stars from our community';
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

redirect('index.php');
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
redirect('index.php');
}

// Handle AJAX request for reviews (WITHOUT description)
if (isset($_GET['action']) && $_GET['action'] === 'get_reviews' && isset($_GET['book_id'])) {
$bookId = (int)$_GET['book_id'];

// Get reviews only
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

// Handle AJAX request for book description
if (isset($_GET['action']) && $_GET['action'] === 'get_description' && isset($_GET['book_id'])) {
$bookId = (int)$_GET['book_id'];

// Get book details
$bookQuery = "SELECT title, author, description, genre, price FROM books WHERE id = $bookId";
$bookResult = mysqli_query($conn, $bookQuery);
$bookDetails = mysqli_fetch_assoc($bookResult);

header('Content-Type: application/json');
echo json_encode($bookDetails);
exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BookTrading - Online Book Store</title>
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
max-width: 280px;
margin: 0 auto;
}
.card:hover {
transform: translateY(-6px);
box-shadow: 0 6px 20px rgba(0,0,0,0.12);
}

/* SMALLER BOOK IMAGE */
.book-image {
height: 220px;
object-fit: cover;
transition: transform 0.3s ease;
}
.card:hover .book-image {
transform: scale(1.03);
}

/* COMPACT BOOK TITLE */
.card-title {
font-size: 0.9rem !important;
line-height: 1.2 !important;
font-weight: 600 !important;
height: 2.2rem !important;
overflow: hidden !important;
display: -webkit-box !important;
-webkit-line-clamp: 2 !important;
-webkit-box-orient: vertical !important;
text-overflow: ellipsis !important;
word-wrap: break-word !important;
margin-bottom: 0.5rem !important;
}

/* COMPACT AUTHOR INFO */
.card-text {
font-size: 0.8rem !important;
margin-bottom: 0.5rem !important;
}

.btn-primary {
background: linear-gradient(135deg, #5D5CDE, #4a4bc7);
border: none;
border-radius: 8px;
padding: 6px 12px;
font-weight: 500;
font-size: 0.85rem;
transition: all 0.3s ease;
}
.btn-primary:hover {
background: linear-gradient(135deg, #4a4bc7, #3a3ab7);
transform: translateY(-2px);
}

/* COMPACT BUTTONS */
.btn-sm {
padding: 4px 8px !important;
font-size: 0.75rem !important;
border-radius: 6px !important;
}

.rating-stars {
color: #ffc107;
font-size: 0.9rem;
}
.hero-section {
background: url('indeximage.jpg') no-repeat center center;
background-size: cover;
color: white;
padding: 100px 0;
position: relative;
overflow: hidden;
}
.hero-section::before {
content: '';
position: absolute;
top: 0;
left: 0;
right: 0;
bottom: 0;
background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" fill="white" opacity="0.1"><polygon points="0,100 1000,0 1000,100"/></svg>');
background-size: cover;
}
.genre-badge {
background: linear-gradient(135deg, #5D5CDE, #4a4bc7);
color: white;
padding: 3px 8px;
border-radius: 12px;
font-size: 0.7rem;
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
.genre-section {
margin-bottom: 4rem;
}
.genre-title {
color: #2c3e50;
font-weight: 700;
margin-bottom: 2rem;
position: relative;
padding-bottom: 10px;
}
.genre-title::after {
content: '';
position: absolute;
bottom: 0;
left: 0;
width: 60px;
height: 3px;
background: linear-gradient(135deg, #5D5CDE, #4a4bc7);
border-radius: 2px;
}
.price-tag {
color: #27ae60;
font-weight: 700;
font-size: 1rem;
}
.stock-info {
color: #7f8c8d;
font-size: 0.8rem;
}
.nav-link {
font-weight: 500;
color: #2c3e50 !important;
transition: color 0.3s ease;
}
.nav-link:hover {
color: #5D5CDE !important;
}
.section-divider {
height: 2px;
background: linear-gradient(90deg, transparent, #5D5CDE, transparent);
margin: 3rem 0;
border-radius: 1px;
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

/* Recommendation Section Styling */
.recommendation-section {
background: linear-gradient(135deg, #f8f9fa, #e9ecef);
border-radius: 20px;
padding: 2rem;
margin-bottom: 3rem;
border: 2px solid #5D5CDE;
position: relative;
}

.recommendation-title {
color: #5D5CDE;
font-weight: 700;
margin-bottom: 1rem;
position: relative;
padding-bottom: 10px;
}

.recommendation-title::after {
content: '';
position: absolute;
bottom: 0;
left: 50%;
transform: translateX(-50%);
width: 80px;
height: 3px;
background: linear-gradient(135deg, #5D5CDE, #4a4bc7);
border-radius: 2px;
}

.quality-badge {
position: absolute;
top: -10px;
right: 20px;
background: linear-gradient(135deg, #27ae60, #2ecc71);
color: white;
padding: 5px 15px;
border-radius: 20px;
font-size: 0.8rem;
font-weight: 600;
box-shadow: 0 2px 10px rgba(39, 174, 96, 0.3);
}

.recommendation-badge {
position: absolute;
top: 10px;
left: 10px;
background: linear-gradient(135deg, #5D5CDE, #4a4bc7);
color: white;
padding: 4px 8px;
border-radius: 15px;
font-size: 0.7rem;
font-weight: 600;
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

/* DESCRIPTION MODAL STYLING */
.description-modal .book-description {
background: linear-gradient(135deg, #f8f9fa, #ffffff);
padding: 25px;
border-radius: 15px;
border-left: 5px solid #5D5CDE;
box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.description-modal .book-details-header {
color: #5D5CDE;
font-weight: 700;
margin-bottom: 20px;
font-size: 1.3rem;
display: flex;
align-items: center;
gap: 10px;
}

.description-modal .book-description-text {
color: #2c3e50;
line-height: 1.8;
font-size: 1rem;
margin-bottom: 20px;
text-align: justify;
max-height: 300px;
overflow-y: auto;
padding-right: 15px;
}

.description-modal .book-meta {
display: grid;
grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
gap: 20px;
margin-top: 20px;
padding-top: 20px;
border-top: 2px solid #e9ecef;
}

.description-modal .book-meta-item {
color: #6c757d;
font-size: 1rem;
display: flex;
align-items: center;
gap: 10px;
background: #f8f9fa;
padding: 10px;
border-radius: 8px;
}

.description-modal .book-meta-item strong {
color: #2c3e50;
}

/* Enhanced Footer Styles */
footer .hover-primary {
transition: color 0.3s ease;
}

footer .hover-primary:hover {
color: #5D5CDE !important;
}

footer .badge {
font-size: 0.75rem;
font-weight: 500;
}

footer .list-unstyled li {
transition: all 0.3s ease;
}

footer .list-unstyled li:hover {
transform: translateX(5px);
}

footer .btn:hover {
transform: translateY(-2px);
box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

footer .form-control:focus {
border-color: #5D5CDE;
box-shadow: 0 0 0 0.2rem rgba(93, 92, 222, 0.25);
}

footer .social-icons a {
width: 40px;
height: 40px;
display: inline-flex;
align-items: center;
justify-content: center;
border-radius: 50%;
background: rgba(255,255,255,0.1);
transition: all 0.3s ease;
}

footer .social-icons a:hover {
background: #5D5CDE;
transform: translateY(-3px);
}

/* Footer Styles */
footer {
background-color: #fff;
color: #212529;
}

footer a {
color: #212529;
}

footer a:hover {
color: #5D5CDE;
}

footer .social-icons a {
color: #6c757d;
}

footer .social-icons a:hover {
color: #5D5CDE;
}

/* RESPONSIVE ADJUSTMENTS */
@media (max-width: 768px) {
.card {
max-width: 100%;
}
.card-title {
font-size: 0.85rem !important;
height: 2rem !important;
}
.book-image {
height: 200px;
}
.description-modal .book-meta {
grid-template-columns: 1fr;
gap: 10px;
}
}
</style>
</head>
<body>
<!-- Navigation -->
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

<!-- Hero Section -->
<div class="hero-section position-relative">
<div class="container text-center position-relative" style="z-index: 2;">
<h1 class="display-3 mb-4 fw-bold">Welcome to BookTrading</h1>
<p class="lead fs-4 mb-4">Buy, Sell, and Trade Books Online</p>
<p class="fs-5">Discover amazing books and connect with fellow readers</p>
</div>
</div>

<div class="container mt-5">
<!-- Success Message -->
<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show m-3" role="alert">
<?php echo $_SESSION['success_message']; ?>
<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<!-- Recommended Books Section -->
<?php if (!empty($recommendedBooks)): ?>
<section class="recommendation-section position-relative">
<div class="quality-badge">
⭐ 4+ Stars Only
</div>

<div class="text-center mb-4">
<h2 class="recommendation-title text-center">
⭐ <?php echo $sectionTitle; ?>
</h2>
<p class="text-muted"><?php echo $sectionDescription; ?></p>
<small class="text-success fw-bold">
🛡️ Quality Guaranteed: Only books with 4+ star ratings are shown!
</small>
</div>

<div class="row">
<?php foreach ($recommendedBooks as $book): ?>
<div class="col-lg-3 col-md-4 col-sm-6 mb-4">
<div class="card h-100 position-relative">
<div class="position-relative">
<img src="uploads/<?php echo $book['image'] ? $book['image'] : 'default.jpg'; ?>"
class="card-img-top book-image" alt="<?php echo htmlspecialchars($book['title']); ?>">
<?php if (isset($book['condition_type']) && $book['condition_type'] == 'old'): ?>
<span class="position-absolute top-0 end-0 badge bg-warning m-2">♻️ Used</span>
<?php endif; ?>
</div>
<div class="card-body d-flex flex-column">
<h5 class="card-title">
<?php echo htmlspecialchars($book['title']); ?>
</h5>
<p class="card-text text-muted">✍️ by <?php echo htmlspecialchars($book['author']); ?></p>
<span class="genre-badge mb-2 align-self-start">🏷️ <?php echo htmlspecialchars($book['genre']); ?></span>

<!-- Rating Display -->
<div class="mb-2">
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

<div class="price-tag mb-2">💰 Rs. <?php echo number_format($book['price'], 2); ?></div>
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

<!-- View Description Button -->
<button class="btn btn-outline-secondary btn-sm w-100 mb-2" onclick="viewDescription(<?php echo $book['id']; ?>, '<?php echo addslashes($book['title']); ?>')">
📖 View Description
</button>

<!-- View Reviews Button -->
<?php if ($ratingCount > 0): ?>
<button class="btn btn-outline-info btn-sm w-100 mb-2" onclick="viewReviews(<?php echo $book['id']; ?>, '<?php echo addslashes($book['title']); ?>')">
💬 View Reviews (<?php echo $ratingCount; ?>)
</button>
<?php endif; ?>

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
</section>

<div class="section-divider"></div>
<?php else: ?>
<!-- Show message if no recommendations -->
<section class="recommendation-section position-relative text-center">
<h2 class="recommendation-title">
ℹ️ No Recommendations Available
</h2>
<p class="text-muted">
<?php if (isLoggedIn()): ?>
Start purchasing books to get personalized recommendations based on your preferences!
<?php else: ?>
🔑 Login for personalized recommendations or check back later for 4+ star rated books!
<?php endif; ?>
</p>
</section>
<div class="section-divider"></div>
<?php endif; ?>

<!-- Books by Genre Sections -->
<?php foreach ($genres as $genre): ?>
<?php if (!empty($booksByGenre[$genre])): ?>
<section class="genre-section">
<div class="d-flex justify-content-between align-items-center mb-4">
<h2 class="genre-title">
🏷️ <?php echo htmlspecialchars($genre); ?>
</h2>
<a href="category.php?genre=<?php echo urlencode($genre); ?>" class="btn btn-outline-primary">
👀 View All ➡️
</a>
</div>

<div class="row">
<?php foreach ($booksByGenre[$genre] as $book): ?>
<div class="col-lg-3 col-md-4 col-sm-6 mb-4">
<div class="card h-100">
<div class="position-relative">
<img src="uploads/<?php echo $book['image'] ? $book['image'] : 'default.jpg'; ?>"
class="card-img-top book-image" alt="<?php echo htmlspecialchars($book['title']); ?>">
<?php if (isset($book['condition_type']) && $book['condition_type'] == 'old'): ?>
<span class="position-absolute top-0 end-0 badge bg-warning m-2">♻️ Used</span>
<?php endif; ?>
</div>
<div class="card-body d-flex flex-column">
<h5 class="card-title">
<?php echo htmlspecialchars($book['title']); ?>
</h5>
<p class="card-text text-muted">✍️ by <?php echo htmlspecialchars($book['author']); ?></p>
<span class="genre-badge mb-2 align-self-start">🏷️ <?php echo htmlspecialchars($book['genre']); ?></span>

<!-- Rating Display -->
<div class="mb-2">
<?php
$avgRating = $book['avg_rating'] ? round($book['avg_rating']) : 0;
for ($i = 1; $i <= 5; $i++):
?>
<span class="rating-stars"><?php echo $i <= $avgRating ? '★' : '☆'; ?></span>
<?php endfor; ?>
<small class="text-muted ms-1">
<?php if ($book['avg_rating'] > 0): ?>
(<?php echo round($book['avg_rating'], 1); ?>/5 - <?php echo $book['rating_count']; ?> reviews)
<?php else: ?>
(No ratings yet)
<?php endif; ?>
</small>
</div>

<div class="price-tag mb-2">💰 Rs. <?php echo number_format($book['price'], 2); ?></div>
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

<!-- View Description Button -->
<button class="btn btn-outline-secondary btn-sm w-100 mb-2" onclick="viewDescription(<?php echo $book['id']; ?>, '<?php echo addslashes($book['title']); ?>')">
📖 View Description
</button>

<!-- View Reviews Button -->
<?php if ($book['rating_count'] > 0): ?>
<button class="btn btn-outline-info btn-sm w-100 mb-2" onclick="viewReviews(<?php echo $book['id']; ?>, '<?php echo addslashes($book['title']); ?>')">
💬 View Reviews (<?php echo $book['rating_count']; ?>)
</button>
<?php endif; ?>

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
</section>

<?php if ($genre !== end($genres)): ?>
<div class="section-divider"></div>
<?php endif; ?>
<?php endif; ?>
<?php endforeach; ?>
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
<small class="text-muted">👆 Click on stars to rate</small>
</div>

<div class="mb-3">
<label class="form-label">📝 Review (Optional)</label>
<textarea name="review" class="form-control" rows="3" placeholder="Share your thoughts about this book..."></textarea>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Cancel</button>
<button type="submit" name="rate_book" class="btn btn-primary" id="submitRating" disabled>✅ Submit Rating</button>
</div>
</form>
</div>
</div>
</div>

<!-- Description Modal -->
<div class="modal fade description-modal" id="descriptionModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="descriptionModalTitle">📖 Book Description</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
<div id="descriptionContent">
<div class="text-center">
<div class="spinner-border text-primary" role="status">
<span class="visually-hidden">Loading...</span>
</div>
<p class="mt-2">⏳ Loading book description...</p>
</div>
</div>
</div>
</div>
</div>
</div>

<!-- Reviews Modal -->
<div class="modal fade" id="reviewsModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="reviewsModalTitle">💬 Reviews & Ratings</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
<div id="reviewsContent">
<div class="text-center">
<div class="spinner-border text-primary" role="status">
<span class="visually-hidden">Loading...</span>
</div>
<p class="mt-2">⏳ Loading reviews...</p>
</div>
</div>
</div>
</div>
</div>
</div>

<!-- Simple Footer -->
<footer class="mt-5 bg-white border-top" style="color: #2c3e50;">
    <div class="container py-4">
        <div class="row align-items-center">
            <div class="col-md-6 mb-2 mb-md-0 d-flex align-items-center">
                <span class="fw-bold" style="color: #5D5CDE; font-size: 1.2rem;">
                    📚 BookTrading
                </span>
                <span class="ms-3" style="color: #2c3e50;">© 2025. All rights reserved.</span>
            </div>
            <div class="col-md-6 text-md-end mt-2 mt-md-0">
                <a href="privacy_policy.php" class="text-decoration-none me-3" style="color: #2c3e50;">🔒 Privacy Policy</a>
                <a href="terms.php" class="text-decoration-none" style="color: #2c3e50;">📋 Terms</a>
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

// NEW: Description Modal Functionality
function viewDescription(bookId, bookTitle) {
document.getElementById('descriptionModalTitle').textContent = '📖 Description: ' + bookTitle;

// Show loading
document.getElementById('descriptionContent').innerHTML = `
<div class="text-center">
<div class="spinner-border text-primary" role="status">
<span class="visually-hidden">Loading...</span>
</div>
<p class="mt-2">⏳ Loading book description...</p>
</div>
`;

// Show modal
const modal = new bootstrap.Modal(document.getElementById('descriptionModal'));
modal.show();

// Fetch book description
fetch(`index.php?action=get_description&book_id=${bookId}`)
.then(response => response.json())
.then(bookDetails => {
displayDescription(bookDetails);
})
.catch(error => {
document.getElementById('descriptionContent').innerHTML = `
<div class="alert alert-danger">
⚠️ Error loading book description. Please try again.
</div>
`;
});
}

// NEW: Display book description
function displayDescription(bookDetails) {
if (!bookDetails) {
document.getElementById('descriptionContent').innerHTML = `
<div class="alert alert-warning">
📭 No description available for this book.
</div>
`;
return;
}

let descriptionHTML = `
<div class="book-description">
<div class="book-details-header">
📖 About This Book
</div>
`;

if (bookDetails.description && bookDetails.description.trim()) {
descriptionHTML += `<div class="book-description-text">${escapeHtml(bookDetails.description)}</div>`;
} else {
descriptionHTML += `<div class="book-description-text text-muted fst-italic">No description available for this book.</div>`;
}

descriptionHTML += `
<div class="book-meta">
<div class="book-meta-item">
<strong>✍️ Author:</strong> ${escapeHtml(bookDetails.author)}
</div>
<div class="book-meta-item">
<strong>🏷️ Genre:</strong> ${escapeHtml(bookDetails.genre)}
</div>
<div class="book-meta-item">
<strong>💰 Price:</strong> Rs. ${parseFloat(bookDetails.price).toLocaleString()}
</div>
</div>
</div>
`;

document.getElementById('descriptionContent').innerHTML = descriptionHTML;
}

// Reviews Modal Functionality (WITHOUT description)
function viewReviews(bookId, bookTitle) {
document.getElementById('reviewsModalTitle').textContent = '💬 Reviews for: ' + bookTitle;

// Show loading
document.getElementById('reviewsContent').innerHTML = `
<div class="text-center">
<div class="spinner-border text-primary" role="status">
<span class="visually-hidden">Loading...</span>
</div>
<p class="mt-2">⏳ Loading reviews...</p>
</div>
`;

// Show modal
const modal = new bootstrap.Modal(document.getElementById('reviewsModal'));
modal.show();

// Fetch reviews only
fetch(`index.php?action=get_reviews&book_id=${bookId}`)
.then(response => response.json())
.then(reviews => {
displayReviews(reviews);
})
.catch(error => {
document.getElementById('reviewsContent').innerHTML = `
<div class="alert alert-danger">
⚠️ Error loading reviews. Please try again.
</div>
`;
});
}

// Display reviews (simplified - no description)
function displayReviews(reviews) {
if (reviews.length === 0) {
document.getElementById('reviewsContent').innerHTML = `
<div class="text-center py-4">
<div style="font-size: 3rem; margin-bottom: 1rem;">💬</div>
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
<h4>⭐ ${averageRating.toFixed(1)} / 5</h4>
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
<div class="reviewer-name">👤 ${escapeHtml(review.reviewer_name)}</div>
<div class="review-rating">${stars}</div>
</div>
<div class="review-date">📅 ${reviewDate}</div>
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