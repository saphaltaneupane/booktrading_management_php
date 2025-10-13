<?php
// Session configuration must come before any output
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 3600);
    ini_set('session.gc_maxlifetime', 3600);
    session_start();
}

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Create logs directory if it doesn't exist
if (!file_exists('../../logs')) {
    mkdir('../../logs', 0755, true);
}
ini_set('error_log', '../../logs/khalti_callback.log');

// Force content type
header('Content-Type: text/html; charset=utf-8');

// Get database connection and functions
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Log all incoming data for debugging
error_log("=== Khalti Callback Received ===");
error_log("GET params: " . json_encode($_GET));
error_log("POST params: " . json_encode($_POST));
error_log("Session khalti_payment: " . (isset($_SESSION['khalti_payment']) ? json_encode($_SESSION['khalti_payment']) : "Not set"));

// FIXED: Define the base URL for absolute redirects
$baseUrl = "http://localhost/book";

// Check if pidx parameter exists
if (!isset($_GET['pidx'])) {
    error_log("ERROR: Missing pidx parameter");
    
    // Handle missing pidx parameter
    $orderId = isset($_SESSION['khalti_payment']['order_id']) ? $_SESSION['khalti_payment']['order_id'] : 0;
    
    if ($orderId > 0) {
        unset($_SESSION['khalti_payment']);
        // FIXED: Use absolute URL
        header("Location: $baseUrl/payment_thankyou.php?order_id=$orderId&status=failed&message=" . urlencode("Missing payment verification data"));
        exit;
    } else {
        header("Location: $baseUrl/index.php");
        exit;
    }
}

// Extract the pidx
$pidx = $_GET['pidx'];
error_log("Processing payment with pidx: $pidx");

// Check if we have payment data in session
if (!isset($_SESSION['khalti_payment']) || !isset($_SESSION['khalti_payment']['order_id'])) {
    error_log("No payment data in session. Attempting to find order by pidx.");
    
    // Try to find order by pidx
    $pidxEscaped = mysqli_real_escape_string($conn, $pidx);
    $query = "SELECT id FROM orders WHERE purchase_order_id LIKE '%$pidxEscaped%' OR purchase_order_id = '$pidxEscaped' LIMIT 1";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $orderId = $row['id'];
        error_log("Found order $orderId from database using pidx");
    } else {
        error_log("No order found for pidx: $pidx");
        header("Location: $baseUrl/index.php");
        exit;
    }
} else {
    $orderId = $_SESSION['khalti_payment']['order_id'];
    error_log("Found order_id in session: $orderId");
}

// Verify payment with Khalti
$secretKey = KHALTI_SECRET_KEY;
$verifyUrl = "https://a.khalti.com/api/v2/epayment/lookup/";

// Set up cURL request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $verifyUrl);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['pidx' => $pidx]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Key ' . $secretKey,
    'Content-Type: application/json'
]);

// Execute request
$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

// Close cURL
curl_close($ch);

// Log the verification response
error_log("Khalti Verification Response (HTTP $statusCode): $response");

// Check for cURL errors
if (!empty($curlError)) {
    error_log("cURL Error during verification: $curlError");
    unset($_SESSION['khalti_payment']);
    header("Location: $baseUrl/payment_thankyou.php?order_id=$orderId&status=failed&message=" . urlencode("Connection error: $curlError"));
    exit;
}

if ($statusCode !== 200) {
    error_log("Khalti verification failed with HTTP status: $statusCode");
    unset($_SESSION['khalti_payment']);
    header("Location: $baseUrl/payment_thankyou.php?order_id=$orderId&status=failed&message=" . urlencode("Payment verification failed"));
    exit;
}

$responseData = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error: " . json_last_error_msg());
    unset($_SESSION['khalti_payment']);
    header("Location: $baseUrl/payment_thankyou.php?order_id=$orderId&status=failed&message=" . urlencode("Invalid response format"));
    exit;
}

// Check payment status
if (isset($responseData['status']) && $responseData['status'] === 'Completed') {
    // Payment successful
    $transactionId = isset($responseData['transaction_id']) ? $responseData['transaction_id'] : $pidx;
    error_log("Payment successful. Transaction ID: $transactionId");
    
    // Update order status
    $transactionId = mysqli_real_escape_string($conn, $transactionId);
    $updateQuery = "UPDATE orders SET payment_status = 'completed', transaction_id = '$transactionId' WHERE id = $orderId";
    
    if (mysqli_query($conn, $updateQuery)) {
        error_log("Order status updated successfully for order $orderId");
        
        // Update book inventory after successful payment
        $itemsQuery = "SELECT book_id, quantity FROM order_items WHERE order_id = $orderId";
        $itemsResult = mysqli_query($conn, $itemsQuery);
        
        while ($item = mysqli_fetch_assoc($itemsResult)) {
            $bookId = $item['book_id'];
            $quantity = $item['quantity'];
            
            // Update book inventory - decrease stock
            $updateStockQuery = "UPDATE books SET quantity = quantity - $quantity WHERE id = $bookId AND quantity >= $quantity";
            if (mysqli_query($conn, $updateStockQuery)) {
                error_log("Updated book #$bookId inventory: decreased by $quantity");
            } else {
                error_log("Failed to update book #$bookId inventory: " . mysqli_error($conn));
            }
        }

        // Clear shopping cart after successful payment
        if (isset($_SESSION['cart'])) {
            unset($_SESSION['cart']);
            error_log("Session cart cleared");
        }
        
        // Also clear database cart for logged-in users
        if (isset($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];
            $clearCartQuery = "DELETE FROM cart WHERE user_id = $userId";
            mysqli_query($conn, $clearCartQuery);
            error_log("Database cart cleared for user $userId");
        }
        
        // Clear khalti payment session data
        unset($_SESSION['khalti_payment']);
        
        // FIXED: Use absolute URL
        header("Location: $baseUrl/payment_thankyou.php?order_id=$orderId&status=success");
        exit;
    } else {
        // Database error
        $dbError = mysqli_error($conn);
        error_log("Database error while updating order: $dbError");
        unset($_SESSION['khalti_payment']);
        header("Location: $baseUrl/payment_thankyou.php?order_id=$orderId&status=failed&message=" . urlencode("Database error"));
        exit;
    }
} else {
    // Payment failed or incomplete
    $status = isset($responseData['status']) ? $responseData['status'] : 'Unknown';
    error_log("Payment verification failed. Status: $status");
    error_log("Full response: " . json_encode($responseData));
    
    unset($_SESSION['khalti_payment']);
    header("Location: $baseUrl/payment_thankyou.php?order_id=$orderId&status=failed&message=" . urlencode("Payment status: $status"));
    exit;
}
?>