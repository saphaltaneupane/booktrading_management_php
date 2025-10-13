<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// Get statistics
$statsQuery = "SELECT 
    (SELECT COUNT(*) FROM users WHERE is_admin = 0) as total_users,
    (SELECT COUNT(*) FROM books) as total_books,
    (SELECT COUNT(*) FROM orders) as total_orders,
    (SELECT SUM(total_amount) FROM orders WHERE payment_status = 'completed') as total_revenue,
    (SELECT COUNT(*) FROM books WHERE quantity = 0) as out_of_stock";
$statsResult = mysqli_query($conn, $statsQuery);
$stats = mysqli_fetch_assoc($statsResult);

// Recent orders
$recentOrdersQuery = "SELECT o.*, u.name as user_name FROM orders o 
                      JOIN users u ON o.user_id = u.id 
                      ORDER BY o.created_at DESC LIMIT 5";
$recentOrders = mysqli_query($conn, $recentOrdersQuery);

// Low stock books
$lowStockQuery = "SELECT * FROM books WHERE quantity <= 5 AND quantity > 0 ORDER BY quantity ASC LIMIT 5";
$lowStock = mysqli_query($conn, $lowStockQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - BookTrading</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .admin-sidebar { background-color: #343a40; min-height: 100vh; }
        .admin-sidebar .nav-link { color: #adb5bd; }
        .admin-sidebar .nav-link:hover, .admin-sidebar .nav-link.active { color: #fff; background-color: #5D5CDE; }
        .stat-card { background: linear-gradient(135deg, #5D5CDE, #4a4bc7); color: white; border-radius: 10px; }
        .content-area { padding: 2rem; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin_sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-10 content-area">
                <h1 class="mb-4">Dashboard</h1>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card p-3">
                            <h3><?php echo $stats['total_users']; ?></h3>
                            <p class="mb-0">Total Users</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card p-3">
                            <h3><?php echo $stats['total_books']; ?></h3>
                            <p class="mb-0">Total Books</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card p-3">
                            <h3><?php echo $stats['total_orders']; ?></h3>
                            <p class="mb-0">Total Orders</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card p-3">
                            <h3>Rs. <?php echo number_format($stats['total_revenue'], 0); ?></h3>
                            <p class="mb-0">Total Revenue</p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Recent Orders -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5>Recent Orders</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Customer</th>
                                                <th>Amount</th>
                                                <th>Payment</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($order = mysqli_fetch_assoc($recentOrders)): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($order['user_name']); ?></td>
                                                <td>Rs. <?php echo number_format($order['total_amount'], 2); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $order['payment_status'] == 'completed' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($order['payment_status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo ucfirst($order['order_status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j', strtotime($order['created_at'])); ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Low Stock Alert -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5>Low Stock Alert</h5>
                            </div>
                            <div class="card-body">
                                <?php if (mysqli_num_rows($lowStock) > 0): ?>
                                    <?php while ($book = mysqli_fetch_assoc($lowStock)): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small><?php echo htmlspecialchars($book['title']); ?></small>
                                        <span class="badge bg-warning"><?php echo $book['quantity']; ?> left</span>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-muted">All books are well stocked!</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5>Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="add_book.php" class="btn btn-primary btn-sm">Add New Book</a>
                                    <a href="manage_orders.php" class="btn btn-outline-primary btn-sm">Manage Orders</a>
                                    <a href="sales_report.php" class="btn btn-outline-success btn-sm">View Sales Report</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>