<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// Get date range from query parameters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Sales statistics
$salesQuery = "SELECT 
    COUNT(*) as total_orders,
    SUM(total_amount) as total_revenue,
    AVG(total_amount) as avg_order_value,
    SUM(CASE WHEN payment_status = 'completed' THEN 1 ELSE 0 END) as completed_orders
    FROM orders 
    WHERE DATE(created_at) BETWEEN '$startDate' AND '$endDate'";
$salesResult = mysqli_query($conn, $salesQuery);
$salesStats = mysqli_fetch_assoc($salesResult);

// Top selling books
$topBooksQuery = "SELECT b.title, b.author, SUM(oi.quantity) as total_sold, SUM(oi.quantity * oi.price) as revenue
                  FROM order_items oi 
                  JOIN books b ON oi.book_id = b.id 
                  JOIN orders o ON oi.order_id = o.id 
                  WHERE DATE(o.created_at) BETWEEN '$startDate' AND '$endDate' AND o.payment_status = 'completed'
                  GROUP BY oi.book_id 
                  ORDER BY total_sold DESC 
                  LIMIT 10";
$topBooks = mysqli_query($conn, $topBooksQuery);

// Sales by payment method
$paymentQuery = "SELECT payment_method, COUNT(*) as count, SUM(total_amount) as revenue 
                 FROM orders 
                 WHERE DATE(created_at) BETWEEN '$startDate' AND '$endDate' AND payment_status = 'completed'
                 GROUP BY payment_method";
$paymentStats = mysqli_query($conn, $paymentQuery);

// Daily sales chart data
$dailyQuery = "SELECT DATE(created_at) as sale_date, COUNT(*) as orders, SUM(total_amount) as revenue 
               FROM orders 
               WHERE DATE(created_at) BETWEEN '$startDate' AND '$endDate' AND payment_status = 'completed'
               GROUP BY DATE(created_at) 
               ORDER BY sale_date";
$dailySales = mysqli_query($conn, $dailyQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report - Admin</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .admin-sidebar { background-color: #343a40; min-height: 100vh; }
        .admin-sidebar .nav-link { color: #adb5bd; }
        .admin-sidebar .nav-link:hover { color: #fff; background-color: #5D5CDE; }
        .content-area { padding: 2rem; }
        .stat-card { background: linear-gradient(135deg, #5D5CDE, #4a4bc7); color: white; border-radius: 10px; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin_sidebar.php'; ?>
            <!-- Main Content -->
            <div class="col-md-10 content-area">
                <h1 class="mb-4">Sales Report</h1>

                <!-- Date Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary d-block">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Sales Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card p-3">
                            <h3><?php echo $salesStats['total_orders']; ?></h3>
                            <p class="mb-0">Total Orders</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card p-3">
                            <h3><?php echo $salesStats['completed_orders']; ?></h3>
                            <p class="mb-0">Completed Orders</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card p-3">
                            <h3>Rs. <?php echo number_format($salesStats['total_revenue'], 0); ?></h3>
                            <p class="mb-0">Total Revenue</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card p-3">
                            <h3>Rs. <?php echo number_format($salesStats['avg_order_value'], 0); ?></h3>
                            <p class="mb-0">Average Order</p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Top Selling Books -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Top Selling Books</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Book</th>
                                                <th>Sold</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($book = mysqli_fetch_assoc($topBooks)): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($book['title']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($book['author']); ?></small>
                                                </td>
                                                <td><?php echo $book['total_sold']; ?></td>
                                                <td>Rs. <?php echo number_format($book['revenue'], 2); ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Methods -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Sales by Payment Method</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Payment Method</th>
                                                <th>Orders</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($payment = mysqli_fetch_assoc($paymentStats)): ?>
                                            <tr>
                                                <td><?php echo strtoupper($payment['payment_method']); ?></td>
                                                <td><?php echo $payment['count']; ?></td>
                                                <td>Rs. <?php echo number_format($payment['revenue'], 2); ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Daily Sales -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5>Daily Sales</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Orders</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($daily = mysqli_fetch_assoc($dailySales)): ?>
                                            <tr>
                                                <td><?php echo date('M j', strtotime($daily['sale_date'])); ?></td>
                                                <td><?php echo $daily['orders']; ?></td>
                                                <td>Rs. <?php echo number_format($daily['revenue'], 2); ?></td>
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
        </div>
    </div>
</body>
</html>