<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$userId = $_SESSION['user_id'];

// Handle quantity update
if (isset($_POST['update_quantity'])) {
    $cartId = $_POST['cart_id'];
    $quantity = $_POST['quantity'];
    
    if ($quantity > 0) {
        $query = "UPDATE cart SET quantity = $quantity WHERE id = $cartId AND user_id = $userId";
        mysqli_query($conn, $query);
    }
    redirect('cart.php');
}

// Handle remove from cart
if (isset($_POST['remove_item'])) {
    $cartId = $_POST['cart_id'];
    $query = "DELETE FROM cart WHERE id = $cartId AND user_id = $userId";
    mysqli_query($conn, $query);
    redirect('cart.php');
}

if (!isLoggedIn()) {
    // Show session cart for non-logged users
    $cartItems = [];
    if (!empty($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $bookId => $quantity) {
            $bookQuery = "SELECT * FROM books WHERE id = $bookId";
            $bookResult = mysqli_query($conn, $bookQuery);
            if ($book = mysqli_fetch_assoc($bookResult)) {
                $book['cart_quantity'] = $quantity;
                $cartItems[] = $book;
            }
        }
    }
} else {
    // Use existing database cart logic
    $cartItems = getCartItems($userId);
}

$cartTotal = getCartTotal($userId);

// Get cart count for navigation
$cartCount = 0;
if (isLoggedIn()) {
    $cartQuery = "SELECT SUM(quantity) as total FROM cart WHERE user_id = {$_SESSION['user_id']}";
    $cartResult = mysqli_query($conn, $cartQuery);
    $cartData = mysqli_fetch_assoc($cartResult);
    $cartCount = $cartData['total'] ? $cartData['total'] : 0;
} else {
    // Count session cart items
    if (isset($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $quantity) {
            $cartCount += $quantity;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - BookTrading</title>
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
        .cart-item { 
            background: white; 
            border-radius: 10px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
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
        .nav-link {
            font-weight: 500;
            color: #2c3e50 !important;
            transition: color 0.3s ease;
        }
        .nav-link:hover {
            color: #5D5CDE !important;
        }
        .price-tag {
            color: #27ae60;
            font-weight: 700;
            font-size: 1.2rem;
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

    <div class="container mt-4">
        <h2 class="mb-4">🛒 Shopping Cart</h2>
        
        <?php if (empty($cartItems)): ?>
            <div class="alert alert-info text-center">
                <div style="font-size: 3rem; margin-bottom: 1rem;">🛒</div>
                <h4>Your cart is empty</h4>
                <p>Add some books to your cart to get started!</p>
                <a href="index.php" class="btn btn-primary">🛍️ Continue Shopping</a>
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col-md-8">
                    <?php foreach ($cartItems as $item): ?>
                    <div class="cart-item p-4 mb-3">
                        <div class="row align-items-center">
                            <div class="col-md-2">
                                <img src="uploads/<?php echo $item['image'] ? $item['image'] : 'default.jpg'; ?>" 
                                     class="img-fluid rounded" alt="<?php echo htmlspecialchars($item['title']); ?>">
                            </div>
                            <div class="col-md-4">
                                <h5>📚 <?php echo htmlspecialchars($item['title']); ?></h5>
                                <p class="text-muted">✍️ by <?php echo htmlspecialchars($item['author']); ?></p>
                                <p class="price-tag">💰 Rs. <?php echo number_format($item['price'], 2); ?></p>
                            </div>
                            <div class="col-md-3">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                    <div class="input-group">
                                        <input type="number" name="quantity" class="form-control" 
                                               value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['stock']; ?>">
                                        <button type="submit" name="update_quantity" class="btn btn-outline-primary">🔄 Update</button>
                                    </div>
                                </form>
                                <small class="text-muted">📦 Stock: <?php echo $item['stock']; ?></small>
                            </div>
                            <div class="col-md-2">
                                <p class="price-tag">💰 Rs. <?php echo number_format($item['price'] * $item['quantity'], 2); ?></p>
                            </div>
                            <div class="col-md-1">
                                <form method="POST">
                                    <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" name="remove_item" class="btn btn-danger btn-sm">🗑️ Remove</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="col-md-4">
                    <div class="cart-item p-4">
                        <h4>📋 Order Summary</h4>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span>Subtotal:</span>
                            <span>💰 Rs. <?php echo number_format($cartTotal, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>📦 Shipping:</span>
                            <span>🆓 Free</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <strong>Total: 💰 Rs. <?php echo number_format($cartTotal, 2); ?></strong>
                        </div>
                        <hr>
                        <a href="checkout.php" class="btn btn-primary w-100">✅ Proceed to Checkout</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

   

    <script src="assets/js/bootstrap.min.js"></script>
</body>
</html>