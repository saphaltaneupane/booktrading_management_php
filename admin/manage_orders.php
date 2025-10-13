<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$success = '';
$error = '';

// Handle order status update
if (isset($_POST['update_order_status'])) {
    $orderId = (int)$_POST['order_id'];
    $newStatus = $_POST['order_status'];
    
    // Get current order details before updating
    $currentOrderQuery = "SELECT order_status, payment_method FROM orders WHERE id = $orderId";
    $currentOrderResult = mysqli_query($conn, $currentOrderQuery);
    $currentOrder = mysqli_fetch_assoc($currentOrderResult);
    
    $query = "UPDATE orders SET order_status = '$newStatus' WHERE id = $orderId";
    if (mysqli_query($conn, $query)) {
        // If order is being cancelled and it's a COD order, restore book quantities
        if ($newStatus == 'cancelled' && $currentOrder['payment_method'] == 'cod' && $currentOrder['order_status'] != 'cancelled') {
            // Get order items to restore quantities
            $itemsQuery = "SELECT book_id, quantity FROM order_items WHERE order_id = $orderId";
            $itemsResult = mysqli_query($conn, $itemsQuery);
            
            while ($item = mysqli_fetch_assoc($itemsResult)) {
                $bookId = $item['book_id'];
                $quantity = $item['quantity'];
                
                // Restore book quantity
                $restoreQuery = "UPDATE books SET quantity = quantity + $quantity WHERE id = $bookId";
                mysqli_query($conn, $restoreQuery);
            }
            
            $success = 'Order cancelled successfully! Book quantities have been restored.';
        } else {
            $success = 'Order status updated successfully!';
        }
    } else {
        $error = 'Failed to update order status.';
    }
}

// Handle payment status update (only for COD orders)
if (isset($_POST['update_payment_status'])) {
    $orderId = (int)$_POST['order_id'];
    $newPaymentStatus = $_POST['payment_status'];
    
    // Verify it's a COD order before allowing payment status change
    $checkQuery = "SELECT payment_method FROM orders WHERE id = $orderId";
    $checkResult = mysqli_query($conn, $checkQuery);
    $order = mysqli_fetch_assoc($checkResult);
    
    if ($order && $order['payment_method'] == 'cod') {
        $query = "UPDATE orders SET payment_status = '$newPaymentStatus' WHERE id = $orderId";
        if (mysqli_query($conn, $query)) {
            $success = 'Payment status updated successfully!';
        } else {
            $error = 'Failed to update payment status.';
        }
    } else {
        $error = 'Payment status can only be updated for COD orders.';
    }
}

// Get all orders
$query = "SELECT o.*, u.name as user_name, u.email as user_email FROM orders o 
          JOIN users u ON o.user_id = u.id 
          ORDER BY o.created_at DESC";
$orders = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Admin</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .admin-sidebar { background-color: #343a40; min-height: 100vh; }
        .admin-sidebar .nav-link { color: #adb5bd; }
        .admin-sidebar .nav-link:hover { color: #fff; background-color: #5D5CDE; }
        .admin-sidebar .nav-link.active { color: #fff; background-color: #5D5CDE; }
        .content-area { padding: 2rem; }
        .status-dropdown { min-width: 120px; }
        .payment-cod { background-color: #fff3cd; }
        .payment-khalti { background-color: #d1ecf1; }
        .table th { background-color: #f8f9fa; font-weight: 600; }
        .order-row:hover { background-color: #f5f5f5; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin_sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-10 content-area">
                <h1 class="mb-4">Manage Orders</h1>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0">All Orders</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Items</th>
                                        <th>Amount</th>
                                        <th>Payment Method</th>
                                        <th>Payment Status</th>
                                        <th>Order Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($order = mysqli_fetch_assoc($orders)): ?>
                                    <tr class="order-row">
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($order['user_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($order['user_email']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $itemsQuery = "SELECT oi.quantity, b.title FROM order_items oi 
                                                          JOIN books b ON oi.book_id = b.id WHERE oi.order_id = {$order['id']}";
                                            $items = mysqli_query($conn, $itemsQuery);
                                            while ($item = mysqli_fetch_assoc($items)) {
                                                echo htmlspecialchars($item['title']) . ' (' . $item['quantity'] . ')<br>';
                                            }
                                            ?>
                                        </td>
                                        <td><strong>Rs. <?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                        <td>
                                            <span class="badge bg-<?php echo $order['payment_method'] == 'cod' ? 'warning' : 'info'; ?> text-dark">
                                                <?php echo strtoupper($order['payment_method']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($order['payment_method'] == 'cod'): ?>
                                                <!-- Editable payment status for COD orders -->
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                    <select name="payment_status" class="form-select form-select-sm status-dropdown" onchange="this.form.submit()">
                                                        <option value="pending" <?php echo $order['payment_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="completed" <?php echo $order['payment_status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                       
                                                    </select>
                                                    <input type="hidden" name="update_payment_status" value="1">
                                                </form>
                                            <?php else: ?>
                                                <!-- Read-only payment status for Khalti orders -->
                                                <span class="badge bg-<?php echo $order['payment_status'] == 'completed' ? 'success' : ($order['payment_status'] == 'failed' ? 'danger' : 'warning'); ?>">
                                                    <?php echo ucfirst($order['payment_status']); ?>
                                                </span>
                                                <br><small class="text-muted">Auto-managed</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <!-- Order status dropdown (editable for all orders) -->
                                            <?php
                                            // Check if order contains old books
                                            $oldBookCheck = "SELECT COUNT(*) as old_count FROM order_items oi 
                                                           JOIN books b ON oi.book_id = b.id 
                                                           WHERE oi.order_id = {$order['id']} AND b.condition_type IN ('old', 'used')";
                                            $oldBookResult = mysqli_query($conn, $oldBookCheck);
                                            $oldBookData = mysqli_fetch_assoc($oldBookResult);
                                            $hasOldBooks = $oldBookData['old_count'] > 0;
                                            ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <select name="order_status" class="form-select form-select-sm status-dropdown" onchange="this.form.submit()">
                                                    <option value="pending" <?php echo $order['order_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="processing" <?php echo $order['order_status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                                    <option value="shipped" <?php echo $order['order_status'] == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                                    <option value="delivered" <?php echo $order['order_status'] == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                    <?php if (!$hasOldBooks && $order['payment_method'] != 'khalti'): ?>
                                                        <option value="cancelled" <?php echo $order['order_status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                    <?php elseif ($order['order_status'] == 'cancelled'): ?>
                                                        <option value="cancelled" selected disabled>Cancelled</option>
                                                    <?php endif; ?>
                                                </select>
                                                <input type="hidden" name="update_order_status" value="1">
                                            </form>
                                            <?php if ($hasOldBooks && $order['order_status'] != 'cancelled'): ?>
                                                <br><small class="text-muted">Old books cannot be cancelled</small>
                                            <?php elseif ($order['payment_method'] == 'khalti' && $order['order_status'] != 'cancelled'): ?>
                                                <br><small class="text-muted">Khalti orders cannot be cancelled</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo date('M j, Y', strtotime($order['created_at'])); ?></strong><br>
                                                <small class="text-muted"><?php echo date('g:i A', strtotime($order['created_at'])); ?></small>
                                            </div>
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

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('show')) {
                    alert.classList.remove('show');
                }
            });
        }, 5000);
    </script>
</body>
</html>