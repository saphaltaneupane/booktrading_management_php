<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$userId = $_SESSION['user_id'];

// Initialize session cart for cart count
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Get cart count
$cartCount = 0;
$cartQuery = "SELECT SUM(quantity) as total FROM cart WHERE user_id = $userId";
$cartResult = mysqli_query($conn, $cartQuery);
$cartData = mysqli_fetch_assoc($cartResult);
$cartCount = $cartData['total'] ? $cartData['total'] : 0;

// Get user orders
$query = "SELECT * FROM orders WHERE user_id = $userId ORDER BY created_at DESC";
$orders = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - BookTrading</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }

        .page-header {
            background: linear-gradient(135deg, #5D5CDE 0%, #7C4DFF 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(93, 92, 222, 0.3);
        }

        .page-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 600;
        }

        .page-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }

        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .table {
            margin: 0;
        }

        .table thead th {
            background-color: #5D5CDE;
            color: white;
            border: none;
            font-weight: 600;
            padding: 15px;
        }

        .table tbody td {
            padding: 15px;
            vertical-align: middle;
            border-color: #e9ecef;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending { 
            background-color: #fff3cd; 
            color: #856404; 
        }
        .status-processing { 
            background-color: #d1ecf1; 
            color: #0c5460; 
        }
        .status-shipped { 
            background-color: #cce5ff; 
            color: #004085; 
        }
        .status-delivered { 
            background-color: #d4edda; 
            color: #155724; 
        }
        .status-cancelled { 
            background-color: #f8d7da; 
            color: #721c24; 
        }
        .status-completed { 
            background-color: #d4edda; 
            color: #155724; 
        }
        .status-failed { 
            background-color: #f8d7da; 
            color: #721c24; 
        }

        .payment-method {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .method-cod { 
            background-color: #fff3cd; 
            color: #856404; 
        }
        .method-khalti { 
            background-color: #e2e3f1; 
            color: #5d5cde; 
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
            border-radius: 6px;
        }

        .btn-outline-primary {
            border-color: #5D5CDE;
            color: #5D5CDE;
        }

        .btn-outline-primary:hover {
            background-color: #5D5CDE;
            border-color: #5D5CDE;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .empty-state img {
            width: 100px;
            opacity: 0.5;
            margin-bottom: 20px;
        }

        .empty-state h4 {
            color: #6c757d;
            margin-bottom: 15px;
        }

        .empty-state p {
            color: #6c757d;
            margin-bottom: 25px;
        }

        .btn-primary {
            background-color: #5D5CDE;
            border-color: #5D5CDE;
            padding: 10px 25px;
        }

        .btn-primary:hover {
            background-color: #4a4bc7;
            border-color: #4a4bc7;
        }

        .order-items {
            max-width: 200px;
        }

        .item-pill {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 15px;
            padding: 3px 8px;
            font-size: 0.7rem;
            margin: 2px;
            display: inline-block;
        }

        .text-success {
            font-weight: 600;
        }

        @media (max-width: 992px) {
            .table-responsive {
                font-size: 0.9rem;
            }
            
            .order-items {
                max-width: 150px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'includes/user_sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>📦 My Orders</h1>
            <p>Track your book orders and delivery status</p>
        </div>

        <?php if (mysqli_num_rows($orders) == 0): ?>
            <div class="empty-state">
                <div style="font-size: 4rem; margin-bottom: 20px;">📦</div>
                <h4>No orders found</h4>
                <p>You haven't placed any orders yet. Start exploring our amazing book collection!</p>
                <a href="index.php" class="btn btn-primary">
                    📚 Start Shopping
                </a>
            </div>
        <?php else: ?>
            <div class="table-container">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order Date</th>
                                <th>Items</th>
                                <th>Total Amount</th>
                                <th>Payment Method</th>
                                <th>Payment Status</th>
                                <th>Order Status</th>
                                <th>Delivery Info</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($order = mysqli_fetch_assoc($orders)): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo date('M j, Y', strtotime($order['created_at'])); ?></strong><br>
                                        <small class="text-muted"><?php echo date('g:i A', strtotime($order['created_at'])); ?></small>
                                    </td>
                                    
                                    <td class="order-items">
                                        <?php
                                        $itemsQuery = "SELECT oi.*, b.title FROM order_items oi 
                                                      JOIN books b ON oi.book_id = b.id 
                                                      WHERE oi.order_id = {$order['id']}";
                                        $items = mysqli_query($conn, $itemsQuery);
                                        $itemCount = 0;
                                        while ($item = mysqli_fetch_assoc($items)): 
                                            $itemCount++;
                                            if ($itemCount <= 2): ?>
                                                <div class="item-pill">
                                                    <?php echo htmlspecialchars(substr($item['title'], 0, 20)) . (strlen($item['title']) > 20 ? '...' : ''); ?>
                                                    <span class="badge badge-secondary ms-1"><?php echo $item['quantity']; ?></span>
                                                </div>
                                            <?php endif;
                                        endwhile; 
                                        
                                        if ($itemCount > 2): ?>
                                            <div class="item-pill">
                                                +<?php echo ($itemCount - 2); ?> more
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <span class="text-success">Rs. <?php echo number_format($order['total_amount'], 2); ?></span>
                                    </td>
                                    
                                    <td>
                                        <span class="payment-method method-<?php echo $order['payment_method']; ?>">
                                            <?php echo $order['payment_method'] == 'cod' ? '💵 COD' : '💳 ' . strtoupper($order['payment_method']); ?>
                                        </span>
                                    </td>
                                    
                                    <td>
                                        <span class="status-badge status-<?php echo $order['payment_status']; ?>">
                                            <?php echo ucfirst($order['payment_status']); ?>
                                        </span>
                                    </td>
                                    
                                    <td>
                                        <span class="status-badge status-<?php echo $order['order_status']; ?>">
                                            <?php echo ucfirst($order['order_status']); ?>
                                        </span>
                                    </td>
                                    
                                    <td>
                                        <small>
                                            <strong>📍 Address:</strong><br>
                                            <?php echo htmlspecialchars(substr($order['address'], 0, 30)) . (strlen($order['address']) > 30 ? '...' : ''); ?><br>
                                            <strong>📞 Phone:</strong><br>
                                            <?php echo htmlspecialchars($order['phone']); ?>
                                        </small>
                                    </td>
                                    
                                    <td>
                                        <a href="invoice.php?order_id=<?php echo $order['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm" 
                                           target="_blank">
                                            📄 Invoice
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="assets/js/bootstrap.min.js"></script>
</body>
</html>