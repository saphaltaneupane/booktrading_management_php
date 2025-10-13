<?php
// Session configuration must happen before session_start() or inclusion of any file
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 3600); // 1 hour
    ini_set('session.gc_maxlifetime', 3600);
  
}

require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../../login.php?redirect=payment/khalti/payment.php');
}

// Handle retry attempts
if (isset($_GET['retry'])) {
    $orderId = (int)$_GET['retry'];
    
    // Get order details to recreate payment
    $query = "SELECT * FROM orders WHERE id = $orderId AND user_id = {$_SESSION['user_id']}";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        $order = mysqli_fetch_assoc($result);
        
        // Only allow retries for pending Khalti payments
        if ($order['payment_method'] === 'khalti' && $order['payment_status'] !== 'completed') {
            // Set session variables for new payment
            $_SESSION['order_id'] = $orderId;
            $_SESSION['amount'] = $order['total_amount'];
        } else {
            redirect('../../payment_thankyou.php?order_id=' . $orderId . '&status=success');
        }
    } else {
        redirect('../../orders.php');
    }
}

// Redirect if no order in session
if (!isset($_SESSION['order_id']) || !isset($_SESSION['amount'])) {
    redirect('../../index.php');
}

$orderId = $_SESSION['order_id'];
$amount = $_SESSION['amount'];

// Get order details
$query = "SELECT o.*, u.name as user_name FROM orders o
          JOIN users u ON o.user_id = u.id
          WHERE o.id = $orderId";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) === 0) {
    redirect('../../index.php');
}

$order = mysqli_fetch_assoc($result);

// Get all items in the order
$query = "SELECT oi.*, b.title, b.author, b.image FROM order_items oi
          JOIN books b ON oi.book_id = b.id
          WHERE oi.order_id = $orderId";
$result = mysqli_query($conn, $query);
$items = [];
while ($row = mysqli_fetch_assoc($result)) {
    $items[] = $row;
}

// Create order name for Khalti
$orderName = count($items) > 1 
    ? $items[0]['title'] . ' and ' . (count($items) - 1) . ' more items'
    : $items[0]['title'];

// Get user details
$user = getUserById($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - BookTrading</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar-brand {
            font-weight: bold;
            color: #5D5CDE !important;
            font-size: 1.5rem;
        }
        .payment-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .payment-header {
            background: linear-gradient(135deg, #5D5CDE, #4a4bc7);
            color: white;
            padding: 2rem;
            text-align: center;
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
        .order-item {
            border-bottom: 1px solid #eee;
            padding: 1rem 0;
        }
        .order-item:last-child {
            border-bottom: none;
        }
        .loading-spinner {
            display: none;
            text-align: center;
            margin: 1rem 0;
        }
        .spinner-border {
            color: #5D5CDE;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="../../index.php">
                <i class="fas fa-book"></i> BookTrading
            </a>
            
            <div class="navbar-nav ms-auto d-flex align-items-center">
                <a class="nav-link" href="../../index.php">Home</a>
                <a class="nav-link" href="../../cart.php">Cart</a>
                <a class="nav-link" href="../../orders.php">Orders</a>
                <a class="nav-link" href="../../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="payment-container">
                    <div class="payment-header">
                        <h2 class="mb-0"><i class="fas fa-credit-card"></i> Complete Payment</h2>
                        <p class="mb-0 mt-2">Secure payment with Khalti</p>
                    </div>
                    
                    <div class="p-4">
                        <div class="row">
                            <div class="col-md-8">
                                <h4 class="mb-3"><i class="fas fa-shopping-bag"></i> Order Summary</h4>
                                <p class="text-muted mb-4">Order #<?php echo $orderId; ?> - <?php echo date('F j, Y', strtotime($order['created_at'])); ?></p>
                                
                                <!-- Order Items -->
                                <div class="mb-4">
                                    <?php foreach ($items as $item): ?>
                                    <div class="order-item">
                                        <div class="row align-items-center">
                                            <div class="col-2">
                                                <img src="../../uploads/<?php echo $item['image'] ? $item['image'] : 'default.jpg'; ?>" 
                                                     class="img-fluid rounded" alt="<?php echo htmlspecialchars($item['title']); ?>"
                                                     style="height: 60px; object-fit: cover;">
                                            </div>
                                            <div class="col-6">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($item['title']); ?></h6>
                                                <small class="text-muted">by <?php echo htmlspecialchars($item['author']); ?></small>
                                            </div>
                                            <div class="col-2 text-center">
                                                <span class="badge bg-secondary">Qty: <?php echo $item['quantity']; ?></span>
                                            </div>
                                            <div class="col-2 text-end">
                                                <strong>Rs. <?php echo number_format($item['price'], 2); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Customer Information -->
                                <h5 class="mb-3"><i class="fas fa-user"></i> Customer Information</h5>
                                <div class="bg-light p-3 rounded mb-4">
                                    <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($user['name']); ?></p>
                                    <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                                    <p class="mb-0"><strong>Phone:</strong> <?php echo htmlspecialchars($user['phone']); ?></p>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="bg-light p-4 rounded">
                                    <h5 class="mb-3"><i class="fas fa-calculator"></i> Payment Details</h5>
                                    
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Subtotal:</span>
                                        <span>Rs. <?php echo number_format($amount, 2); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Shipping:</span>
                                        <span class="text-success">Free</span>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between mb-4">
                                        <strong>Total Amount:</strong>
                                        <strong class="text-primary fs-4">Rs. <?php echo number_format($amount, 2); ?></strong>
                                    </div>
                                    
                                    <!-- Loading indicator -->
                                    <div id="payment-loading" class="loading-spinner">
                                        <div class="spinner-border" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2">Connecting to Khalti...</p>
                                    </div>
                                    
                                    <!-- Payment form -->
                                    <form id="payment-form" action="initiate.php" method="post">
                                        <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                                        <input type="hidden" name="amount" value="<?php echo $amount; ?>">
                                        <input type="hidden" name="customer_name" value="<?php echo htmlspecialchars($user['name']); ?>">
                                        <input type="hidden" name="customer_email" value="<?php echo htmlspecialchars($user['email']); ?>">
                                        <input type="hidden" name="customer_phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                                        
                                        <button type="submit" id="pay-button" class="btn btn-primary w-100 btn-lg mb-3">
                                            <i class="fas fa-shield-alt"></i> Pay with Khalti
                                        </button>
                                    </form>
                                    
                                    <div class="text-center">
                                        <small class="text-muted">
                                            <i class="fas fa-lock"></i> Secure payment powered by Khalti
                                        </small>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div class="text-center">
                                        <a href="../../cart.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-arrow-left"></i> Back to Cart
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/bootstrap.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const payForm = document.getElementById('payment-form');
            const payButton = document.getElementById('pay-button');
            const loadingIndicator = document.getElementById('payment-loading');
            
            payForm.addEventListener('submit', function() {
                // Show loading indicator
                loadingIndicator.style.display = 'block';
                payButton.disabled = true;
                payButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            });
        });
    </script>
</body>
</html>