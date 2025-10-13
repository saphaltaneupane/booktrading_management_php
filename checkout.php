<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$userId = $_SESSION['user_id'];
$cartItems = getCartItems($userId);
$cartTotal = getCartTotal($userId);

if (empty($cartItems)) {
    redirect('cart.php');
}

$user = getUserById($userId);
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $paymentMethod = $_POST['payment_method'];
    
    // Validation
    if (strlen($phone) != 10 || !is_numeric($phone)) {
        $error = 'Phone number must be exactly 10 digits';
    } else {
        // Create order
        $query = "INSERT INTO orders (user_id, total_amount, payment_method, address, phone) 
                  VALUES ($userId, $cartTotal, '$paymentMethod', '$address', '$phone')";
        
        if (mysqli_query($conn, $query)) {
            $orderId = mysqli_insert_id($conn);
            
            // Add order items
            foreach ($cartItems as $item) {
                $itemQuery = "INSERT INTO order_items (order_id, book_id, quantity, price) 
                              VALUES ($orderId, {$item['book_id']}, {$item['quantity']}, {$item['price']})";
                mysqli_query($conn, $itemQuery);
            }
            
            // Clear cart
            $clearQuery = "DELETE FROM cart WHERE user_id = $userId";
            mysqli_query($conn, $clearQuery);
            
            // Store order info in session
            $_SESSION['order_id'] = $orderId;
            $_SESSION['amount'] = $cartTotal;
            
            // Redirect based on payment method
            if ($paymentMethod == 'khalti') {
                redirect('payment/khalti/payment.php');
            } else {
                // For COD, keep payment status as pending and redirect to thank you page
                foreach ($cartItems as $item) {
                    updateBookQuantity($item['book_id'], $item['quantity']);
                }
                
                // Payment status remains 'pending' for COD orders (admin will confirm payment)
                redirect('payment_thankyou.php?order_id=' . $orderId . '&status=success');
            }
        } else {
            $error = 'Order creation failed. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - BookTrading</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .btn-primary { background-color: #5D5CDE; border-color: #5D5CDE; }
        .checkout-card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h2 class="mb-4">Checkout</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-8">
                <div class="checkout-card p-4 mb-4">
                    <h4 class="mb-3">Shipping Information</h4>
                    <form method="POST">
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Delivery Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($user['address']); ?></textarea>
                        </div>
                        
                        <h4 class="mb-3">Payment Method</h4>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="cod" value="cod" checked>
                                <label class="form-check-label" for="cod">
                                    Cash on Delivery (COD)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="khalti" value="khalti">
                                <label class="form-check-label" for="khalti">
                                    Khalti Digital Wallet
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg">Place Order</button>
                    </form>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="checkout-card p-4">
                    <h4 class="mb-3">Order Summary</h4>
                    <?php foreach ($cartItems as $item): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span><?php echo htmlspecialchars($item['title']); ?> x<?php echo $item['quantity']; ?></span>
                        <span>Rs. <?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                    </div>
                    <?php endforeach; ?>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span>Shipping:</span>
                        <span>Free</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <strong>Total: Rs. <?php echo number_format($cartTotal, 2); ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>