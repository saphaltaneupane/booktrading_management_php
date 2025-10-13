<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// Initialize session cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Get cart count
$cartCount = 0;
if (isLoggedIn()) {
    $cartQuery = "SELECT SUM(quantity) as total FROM cart WHERE user_id = {$_SESSION['user_id']}";
    $cartResult = mysqli_query($conn, $cartQuery);
    $cartData = mysqli_fetch_assoc($cartResult);
    $cartCount = $cartData['total'] ? $cartData['total'] : 0;
} else {
    foreach ($_SESSION['cart'] as $quantity) {
        $cartCount += $quantity;
    }
}

// Get user information
$userQuery = "SELECT * FROM users WHERE id = ?";
$userStmt = mysqli_prepare($conn, $userQuery);
mysqli_stmt_bind_param($userStmt, "i", $user_id);
mysqli_stmt_execute($userStmt);
$userResult = mysqli_stmt_get_result($userStmt);
$user = mysqli_fetch_assoc($userResult);

// Get user statistics
$stats = [
    'total_orders' => 0,
    'total_spent' => 0,
    'books_listed' => 0,
    'books_sold' => 0,
    'total_earned' => 0
];

// Get order statistics
$orderStatsQuery = "SELECT COUNT(*) as total_orders, COALESCE(SUM(total_amount), 0) as total_spent 
                    FROM orders WHERE user_id = ? AND payment_status = 'completed'";
$orderStatsStmt = mysqli_prepare($conn, $orderStatsQuery);
mysqli_stmt_bind_param($orderStatsStmt, "i", $user_id);
mysqli_stmt_execute($orderStatsStmt);
$orderStatsResult = mysqli_stmt_get_result($orderStatsStmt);
$orderStats = mysqli_fetch_assoc($orderStatsResult);
$stats['total_orders'] = $orderStats['total_orders'];
$stats['total_spent'] = $orderStats['total_spent'];

// Get selling statistics
$sellingStatsQuery = "SELECT 
                        COUNT(*) as books_listed,
                        COALESCE(SUM(oi.quantity), 0) as books_sold,
                        COALESCE(SUM(oi.quantity * oi.price), 0) as total_earned
                      FROM books b
                      LEFT JOIN order_items oi ON b.id = oi.book_id
                      LEFT JOIN orders o ON oi.order_id = o.id AND o.payment_status = 'completed'
                      WHERE b.added_by = ?";
$sellingStatsStmt = mysqli_prepare($conn, $sellingStatsQuery);
mysqli_stmt_bind_param($sellingStatsStmt, "i", $user_id);
mysqli_stmt_execute($sellingStatsStmt);
$sellingStatsResult = mysqli_stmt_get_result($sellingStatsStmt);
$sellingStats = mysqli_fetch_assoc($sellingStatsResult);
$stats['books_listed'] = $sellingStats['books_listed'];
$stats['books_sold'] = $sellingStats['books_sold'];
$stats['total_earned'] = $sellingStats['total_earned'];

// Get recent activity
$recentOrdersQuery = "SELECT o.*, COUNT(oi.id) as item_count 
                      FROM orders o 
                      LEFT JOIN order_items oi ON o.id = oi.order_id 
                      WHERE o.user_id = ?
                      GROUP BY o.id 
                      ORDER BY o.created_at DESC 
                      LIMIT 5";
$recentOrdersStmt = mysqli_prepare($conn, $recentOrdersQuery);
mysqli_stmt_bind_param($recentOrdersStmt, "i", $user_id);
mysqli_stmt_execute($recentOrdersStmt);
$recentOrdersResult = mysqli_stmt_get_result($recentOrdersStmt);
$recentOrders = [];
while ($order = mysqli_fetch_assoc($recentOrdersResult)) {
    $recentOrders[] = $order;
}

// Get recent listed books
$recentBooksQuery = "SELECT * FROM books WHERE added_by = ? ORDER BY created_at DESC LIMIT 5";
$recentBooksStmt = mysqli_prepare($conn, $recentBooksQuery);
mysqli_stmt_bind_param($recentBooksStmt, "i", $user_id);
mysqli_stmt_execute($recentBooksStmt);
$recentBooksResult = mysqli_stmt_get_result($recentBooksStmt);
$recentBooks = [];
while ($book = mysqli_fetch_assoc($recentBooksResult)) {
    $recentBooks[] = $book;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - BookTrading</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-left: 5px solid;
            margin-bottom: 1rem;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stat-card.orders { border-left-color: #3498db; }
        .stat-card.spent { border-left-color: #e74c3c; }
        .stat-card.listed { border-left-color: #f39c12; }
        .stat-card.sold { border-left-color: #27ae60; }
        .stat-card.earned { border-left-color: #9b59b6; }
        
        .dashboard-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .section-title {
            color: #2d3748;
            font-weight: 700;
            margin-bottom: 1.5rem;
            position: relative;
            padding-bottom: 10px;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background: linear-gradient(135deg, #5D5CDE, #4a4bc7);
            border-radius: 2px;
        }
        
        .recent-item {
            padding: 1rem;
            border-left: 3px solid #5D5CDE;
            margin-bottom: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
        }
        
        .welcome-message {
            background: linear-gradient(135deg, #5D5CDE, #4a4bc7);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
       .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }
        .content-header {
            background: linear-gradient(135deg, #5D5CDE 0%, #7C4DFF 100%) !important;
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(93, 92, 222, 0.3);
        }
        .content-header  h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 600;
            color: #fff !important;
        }

        .content-header  p {
            margin: 10px 0 0 0;
            opacity: 0.9;
              color: white !important;
        }
    </style>
</head>
<body>
    <!-- Mobile Toggle Button -->
    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <?php include 'includes/user_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Content Header -->
        <div class="content-header">
            <h1>Dashboard</h1>
            <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($user['name']); ?>! Here's your book trading summary.</p>
        </div>

        <!-- Content Body -->
        <div class="content-body">
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stat-card orders">
                        <div class="h3 text-primary mb-1"><?php echo $stats['total_orders']; ?></div>
                        <small class="text-muted">Orders Placed</small>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stat-card spent">
                        <div class="h3 text-danger mb-1">Rs. <?php echo number_format($stats['total_spent'], 0); ?></div>
                        <small class="text-muted">Total Spent</small>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stat-card listed">
                        <div class="h3 text-warning mb-1"><?php echo $stats['books_listed']; ?></div>
                        <small class="text-muted">Books Listed</small>
                    </div>
                </div>
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="stat-card sold">
                        <div class="h3 text-success mb-1"><?php echo $stats['books_sold']; ?></div>
                        <small class="text-muted">Books Sold</small>
                    </div>
                </div>
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="stat-card earned">
                        <div class="h3" style="color: #9b59b6;" mb-1>Rs. <?php echo number_format($stats['total_earned'], 0); ?></div>
                        <small class="text-muted">Total Earned</small>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="row">
                <div class="col-md-6">
                    <div class="dashboard-card">
                        <h3 class="section-title">Recent Orders</h3>
                        <?php if (!empty($recentOrders)): ?>
                            <?php foreach ($recentOrders as $order): ?>
                                <div class="recent-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>Order #<?php echo $order['id']; ?></strong>
                                            <small class="text-muted d-block"><?php echo $order['item_count']; ?> item(s) • Rs. <?php echo number_format($order['total_amount'], 2); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <?php if ($order['order_status'] == 'cancelled'): ?>
                                                <span class="status-badge bg-danger text-white">Cancelled</span>
                                            <?php else: ?>
                                                <span class="status-badge bg-<?php echo $order['payment_status'] == 'completed' ? 'success' : 'warning'; ?> text-white">
                                                    <?php echo ucfirst($order['payment_status']); ?>
                                                </span>
                                            <?php endif; ?>
                                            <small class="text-muted d-block"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center mt-3">
                                <a href="orders.php" class="btn btn-outline-primary">View All Orders</a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-shopping-bag fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No orders yet</p>
                                <a href="index.php" class="btn btn-primary">Start Shopping</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="dashboard-card">
                        <h3 class="section-title">Recent Listed Books</h3>
                        <?php if (!empty($recentBooks)): ?>
                            <?php foreach ($recentBooks as $book): ?>
                                <div class="recent-item">
                                    <div>
                                        <strong><?php echo htmlspecialchars($book['title']); ?></strong>
                                        <small class="text-muted d-block">by <?php echo htmlspecialchars($book['author']); ?> • Rs. <?php echo number_format($book['price'], 2); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center mt-3">
                                <a href="my_listed_books.php" class="btn btn-outline-primary">View All Listed Books</a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-book fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No books listed yet</p>
                                <a href="add_book.php" class="btn btn-primary">List Your First Book</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.min.js"></script>
</body>
</html>