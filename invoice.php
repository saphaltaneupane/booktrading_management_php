<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$userId = $_SESSION['user_id'];

// Get order details
$query = "SELECT o.*, u.name as user_name, u.email, u.phone as user_phone 
          FROM orders o 
          JOIN users u ON o.user_id = u.id 
          WHERE o.id = $orderId AND o.user_id = $userId";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    redirect('orders.php');
}

$order = mysqli_fetch_assoc($result);

// Get order items
$itemsQuery = "SELECT oi.*, b.title, b.author FROM order_items oi 
               JOIN books b ON oi.book_id = b.id 
               WHERE oi.order_id = $orderId";
$items = mysqli_query($conn, $itemsQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $orderId; ?> - BookTrading</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .invoice-container { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .invoice-header { background: linear-gradient(135deg, #5D5CDE, #4a4bc7); color: white; }
        @media print {
            body { background: white; }
            .no-print { display: none; }
            .invoice-container { box-shadow: none; }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm no-print">
        <div class="container">
            <a class="navbar-brand" href="index.php">📚 BookTrading</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="orders.php">← Back to Orders</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="invoice-container">
            <!-- Invoice Header -->
            <div class="invoice-header p-4 rounded-top">
                <div class="row">
                    <div class="col-md-6">
                        <h2>📚 BookTrading</h2>
                        <p class="mb-0">Online Book Store</p>
                    </div>
                    <div class="col-md-6 text-end">
                        <h3>INVOICE</h3>
                        <p class="mb-0">Invoice #<?php echo $orderId; ?></p>
                        <p class="mb-0">Date: <?php echo date('F j, Y', strtotime($order['created_at'])); ?></p>
                    </div>
                </div>
            </div>

            <!-- Invoice Body -->
            <div class="p-4">
                <!-- Billing Information -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5>Bill To:</h5>
                        <p>
                            <strong><?php echo htmlspecialchars($order['user_name']); ?></strong><br>
                            <?php echo htmlspecialchars($order['email']); ?><br>
                            <?php echo htmlspecialchars($order['phone']); ?><br>
                            <?php echo nl2br(htmlspecialchars($order['address'])); ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h5>Order Details:</h5>
                        <p>
                            <strong>Order ID:</strong> #<?php echo $order['id']; ?><br>
                            <strong>Payment Method:</strong> <?php echo strtoupper($order['payment_method']); ?><br>
                            <strong>Payment Status:</strong> <?php echo ucfirst($order['payment_status']); ?><br>
                            <strong>Order Status:</strong> <?php echo ucfirst($order['order_status']); ?><br>
                            <?php if ($order['transaction_id']): ?>
                                <strong>Transaction ID:</strong> <?php echo htmlspecialchars($order['transaction_id']); ?><br>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <!-- Order Items Table -->
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Item</th>
                                <th>Author</th>
                                <th class="text-center">Quantity</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $subtotal = 0;
                            while ($item = mysqli_fetch_assoc($items)): 
                                $itemTotal = $item['quantity'] * $item['price'];
                                $subtotal += $itemTotal;
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['title']); ?></td>
                                    <td><?php echo htmlspecialchars($item['author']); ?></td>
                                    <td class="text-center"><?php echo $item['quantity']; ?></td>
                                    <td class="text-end">Rs. <?php echo number_format($item['price'], 2); ?></td>
                                    <td class="text-end">Rs. <?php echo number_format($itemTotal, 2); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-end">Subtotal:</th>
                                <th class="text-end">Rs. <?php echo number_format($subtotal, 2); ?></th>
                            </tr>
                            <tr>
                                <th colspan="4" class="text-end">Shipping:</th>
                                <th class="text-end">Free</th>
                            </tr>
                            <tr class="table-primary">
                                <th colspan="4" class="text-end">Total Amount:</th>
                                <th class="text-end">Rs. <?php echo number_format($order['total_amount'], 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Footer -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <h6>Terms & Conditions:</h6>
                            <ul class="mb-0">
                                <li>All sales are final unless the product is damaged or defective.</li>
                                <li>Returns are accepted within 7 days of delivery for unused items.</li>
                                <li>Customer is responsible for return shipping costs unless item is defective.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <p class="text-muted">Thank you for shopping with BookTrading!</p>
                </div>

                <!-- Print Button -->
                <div class="text-center no-print">
                    <button onclick="window.print()" class="btn btn-primary">Print Invoice</button>
                    <a href="orders.php" class="btn btn-outline-secondary ms-2">Back to Orders</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>