<?php
// Session settings must come before session initialization
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 3600);
    ini_set('session.gc_maxlifetime', 3600);
    session_start();
}

require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Setup error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Create logs directory if it doesn't exist
if (!file_exists('../../logs')) {
    mkdir('../../logs', 0755, true);
}
ini_set('error_log', '../../logs/khalti_initiate.log');

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../../login.php');
    exit;
}

// Get order data from POST
$orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;

// Validate input
if ($orderId <= 0 || $amount <= 0) {
    echo "<div class='alert alert-danger'>Invalid order information. <a href='../../index.php'>Return to homepage</a>.</div>";
    exit;
}

// Validate amount (should be reasonable)
if ($amount < 10 || $amount > 100000) {
    echo "<div class='alert alert-danger'>Invalid amount. <a href='../../index.php'>Return to homepage</a>.</div>";
    exit;
}

// Get user data
$userId = $_SESSION['user_id'];
$userQuery = "SELECT * FROM users WHERE id = $userId";
$userResult = mysqli_query($conn, $userQuery);

if (!$userResult || mysqli_num_rows($userResult) === 0) {
    echo "<div class='alert alert-danger'>Could not find user information. <a href='../../index.php'>Return to homepage</a>.</div>";
    exit;
}

$user = mysqli_fetch_assoc($userResult);

// Validate user phone (Khalti requires valid phone)
if (strlen($user['phone']) != 10 || !is_numeric($user['phone'])) {
    echo "<div class='alert alert-danger'>Invalid phone number. Please update your profile with a valid 10-digit phone number. <a href='../../profile.php'>Update Profile</a></div>";
    exit;
}

// Create unique purchase order ID
$purchaseOrderId = 'BOOK-' . time() . '-' . $orderId . '-' . rand(1000, 9999);

// Update order with this purchase order ID
$purchaseOrderId = mysqli_real_escape_string($conn, $purchaseOrderId);
$updateQuery = "UPDATE orders SET purchase_order_id = '$purchaseOrderId' WHERE id = $orderId";
if (!mysqli_query($conn, $updateQuery)) {
    echo "<div class='alert alert-danger'>Database error: " . mysqli_error($conn) . " <a href='../../index.php'>Return to homepage</a>.</div>";
    exit;
}

// Get order information for the payment name
$itemsQuery = "SELECT b.title FROM order_items oi JOIN books b ON oi.book_id = b.id WHERE oi.order_id = $orderId LIMIT 1";
$itemsResult = mysqli_query($conn, $itemsQuery);

$orderName = "BookTrading Order #$orderId";
if ($itemsResult && mysqli_num_rows($itemsResult) > 0) {
    $item = mysqli_fetch_assoc($itemsResult);
    $orderName = substr($item['title'], 0, 50); // Limit title length
    
    // Get total items count
    $countQuery = "SELECT COUNT(*) as total FROM order_items WHERE order_id = $orderId";
    $countResult = mysqli_query($conn, $countQuery);
    if ($countResult && $row = mysqli_fetch_assoc($countResult)) {
        if ($row['total'] > 1) {
            $orderName .= " (+" . ($row['total'] - 1) . " more)";
        }
    }
}

// Convert amount to paisa (Khalti requires amount in paisa)
$amountInPaisa = (int)($amount * 100);

// IMPORTANT: Use ngrok or a public URL for testing, localhost won't work with Khalti
// For testing, you can use ngrok: ngrok http 80
// Then replace localhost with your ngrok URL
$baseUrl = "http://localhost/book"; // Change this to your ngrok URL when testing

$returnUrl = $baseUrl . "/payment/khalti/callback.php";
$websiteUrl = $baseUrl;

// Log the request details
error_log("=== Khalti Payment Initiation ===");
error_log("Order ID: $orderId");
error_log("Amount: Rs. $amount (Paisa: $amountInPaisa)");
error_log("Return URL: $returnUrl");
error_log("Purchase Order ID: $purchaseOrderId");
error_log("Customer: {$user['name']} ({$user['email']})");

// Prepare payload for Khalti API
$payload = [
    "return_url" => $returnUrl,
    "website_url" => $websiteUrl,
    "amount" => $amountInPaisa,
    "purchase_order_id" => $purchaseOrderId,
    "purchase_order_name" => $orderName,
    "customer_info" => [
        "name" => $user['name'],
        "email" => $user['email'],
        "phone" => $user['phone']
    ]
];

// Log the full payload
error_log("Khalti Payload: " . json_encode($payload, JSON_PRETTY_PRINT));

// Validate Khalti keys
if (empty(KHALTI_SECRET_KEY) || empty(KHALTI_PUBLIC_KEY)) {
    echo "<div class='alert alert-danger'>Payment gateway configuration error. Please contact administrator.</div>";
    error_log("ERROR: Khalti keys not configured properly");
    exit;
}

// Initialize cURL with better error handling
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://a.khalti.com/api/v2/epayment/initiate/");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Key ' . KHALTI_SECRET_KEY,
    'Content-Type: application/json'
]);

// Execute the request
$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

// Close cURL
curl_close($ch);

// Log the response
error_log("Khalti Response Code: $statusCode");
error_log("Khalti Response: $response");

// Check for cURL errors
if (!empty($curlError)) {
    error_log("cURL Error: $curlError");
    echo "<div class='alert alert-danger'>Connection error: $curlError <a href='../../index.php'>Return to homepage</a>.</div>";
    exit;
}

// Process the response
if ($statusCode === 200) {
    $responseData = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        echo "<div class='alert alert-danger'>Invalid response format from payment gateway.</div>";
        exit;
    }
    
    if (!isset($responseData['payment_url']) || !isset($responseData['pidx'])) {
        error_log("Missing required fields in response: " . print_r($responseData, true));
        echo "<div class='alert alert-danger'>Invalid response from payment gateway.</div>";
        exit;
    }
    
    // Store payment details in session
    $_SESSION['khalti_payment'] = [
        'pidx' => $responseData['pidx'],
        'order_id' => $orderId,
        'purchase_order_id' => $purchaseOrderId,
        'initiated_at' => time(),
        'amount' => $amount
    ];
    
    error_log("Payment initiated successfully. Redirecting to: " . $responseData['payment_url']);
    
    // Redirect to Khalti payment page
    header("Location: " . $responseData['payment_url']);
    exit;
} else {
    // Payment initiation failed
    $responseData = json_decode($response, true);
    $errorMessage = 'Unknown error';
    
    if (is_array($responseData)) {
        if (isset($responseData['detail'])) {
            $errorMessage = $responseData['detail'];
        } elseif (isset($responseData['message'])) {
            $errorMessage = $responseData['message'];
        } elseif (isset($responseData['error'])) {
            $errorMessage = $responseData['error'];
        }
    }
    
    error_log("Payment initiation failed: HTTP $statusCode - $errorMessage");
    error_log("Full error response: $response");
    
    echo "<div class='alert alert-danger'>Payment initiation failed: $errorMessage <a href='../../index.php'>Return to homepage</a>.</div>";
    exit;
}
?>