<?php
// Start session first
if (session_status() === PHP_SESSION_NONE) {
   
}

require_once 'config/db.php';
require_once 'includes/functions.php';

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : 'failed';
$message = isset($_GET['message']) ? $_GET['message'] : '';

if ($orderId > 0) {
    $query = "SELECT * FROM orders WHERE id = $orderId";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $order = mysqli_fetch_assoc($result);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status - BookTrading</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #5D5CDE, #4a4bc7);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .status-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
            padding: 3rem;
            text-align: center;
        }
        .success-icon {
            color: #28a745;
            font-size: 5rem;
            margin-bottom: 1rem;
        }
        .error-icon {
            color: #dc3545;
            font-size: 5rem;
            margin-bottom: 1rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #5D5CDE, #4a4bc7);
            border: none;
            border-radius: 10px;
            padding: 12px 24px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #4a4bc7, #3a3ab7);
            transform: translateY(-2px);
        }
        .btn-outline-primary {
            color: #5D5CDE;
            border-color: #5D5CDE;
        }
        .btn-outline-primary:hover {
            background-color: #5D5CDE;
            border-color: #5D5CDE;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="status-card">
                    <?php if ($status == 'success'): ?>
                        <div class="success-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h2 class="text-success mb-3">Order Successful!</h2>
                        <p class="lead mb-4">Thank you for your order. Your payment has been processed successfully and your books are being prepared for delivery.</p>
                        <?php if (isset($order)): ?>
                            <div class="alert alert-info text-start">
                                <strong><i class="fas fa-receipt"></i> Order Details:</strong><br>
                                <strong>Order ID:</strong> #<?php echo $orderId; ?><br>
                                <strong>Amount:</strong> Rs. <?php echo number_format($order['total_amount'], 2); ?><br>
                                <strong>Payment Method:</strong> <?php echo strtoupper($order['payment_method']); ?><br>
                                <strong>Date:</strong> <?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?><br>
                                <?php if (!empty($order['transaction_id'])): ?>
                                <strong>Transaction ID:</strong> <?php echo htmlspecialchars($order['transaction_id']); ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="error-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <h2 class="text-danger mb-3">Payment Failed!</h2>
                        <p class="lead mb-4">Sorry, there was an issue processing your payment. Please try again or contact support.</p>
                        <?php if ($message): ?>
                            <div class="alert alert-warning">
                                <strong><i class="fas fa-exclamation-triangle"></i> Error Details:</strong><br>
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <div class="mt-4">
                        <a href="index.php" class="btn btn-primary btn-lg me-3">
                            <i class="fas fa-home"></i> Go to Homepage
                        </a>
                        <?php if (isLoggedIn()): ?>
                            <a href="orders.php" class="btn btn-outline-primary btn-lg">
                                <i class="fas fa-list"></i> View My Orders
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($status == 'success'): ?>
                    <div class="mt-4 pt-3 border-top">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i> 
                            Your order has been confirmed and books inventory has been updated. You can track your order status in the "My Orders" section.
                        </small>
                    </div>
                    <?php else: ?>
                    <div class="mt-4 pt-3 border-top">
                        <small class="text-muted">
                            <i class="fas fa-question-circle"></i> 
                            If you continue having issues, please contact our support team or try using Cash on Delivery option.
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Auto redirect to homepage after 30 seconds for failed payments
        <?php if ($status !== 'success'): ?>
        setTimeout(function() {
            if (confirm('Would you like to return to the homepage to try again?')) {
                window.location.href = 'index.php';
            }
        }, 30000);
        <?php endif; ?>
    </script>
</body>
</html>