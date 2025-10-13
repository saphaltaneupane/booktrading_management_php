<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

// Initialize session cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Get cart count
$cartCount = 0;
if (isLoggedIn()) {
    $cartQuery = "SELECT SUM(quantity) as total FROM cart WHERE user_id = {$_SESSION['user_id']}";
    $cartResult = mysqli_query($conn, $cartQuery);
    $cartData = mysqli_fetch_assoc($cartResult);
    $cartCount = $cartData['total'] ? $cartData['total'] : 0;
} else {
    // Count session cart items
    foreach ($_SESSION['cart'] as $quantity) {
        $cartCount += $quantity;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - BookTrading</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar-brand {
            font-weight: bold;
            color: #5D5CDE !important;
            font-size: 1.5rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #5D5CDE, #4a4bc7);
            border: none;
            border-radius: 10px;
            padding: 8px 16px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #4a4bc7, #3a3ab7);
            transform: translateY(-2px);
        }
        .cart-icon {
            position: relative;
            color: #5D5CDE;
            font-size: 1.4rem;
        }
        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .nav-link {
            font-weight: 500;
            color: #2c3e50 !important;
            transition: color 0.3s ease;
        }
        .nav-link:hover {
            color: #5D5CDE !important;
        }
        .hero-section {
            background: linear-gradient(135deg, #5D5CDE, #4a4bc7, #6c5ce7);
            color: white;
            padding: 80px 0 60px 0;
            position: relative;
            overflow: hidden;
        }
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" fill="white" opacity="0.1"><polygon points="0,100 1000,0 1000,100"/></svg>');
            background-size: cover;
        }
        .content-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .section-title {
            color: #5D5CDE;
            font-weight: 700;
            margin-bottom: 1.5rem;
            position: relative;
            padding-bottom: 10px;
        }
        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background: linear-gradient(135deg, #5D5CDE, #4a4bc7);
            border-radius: 2px;
        }
        .policy-content h4 {
            color: #2c3e50;
            font-weight: 600;
            margin-top: 2rem;
            margin-bottom: 1rem;
        }
        .policy-content h5 {
            color: #34495e;
            font-weight: 500;
            margin-top: 1.5rem;
            margin-bottom: 0.8rem;
        }
        .policy-content p, .policy-content li {
            color: #555;
            line-height: 1.8;
            margin-bottom: 1rem;
        }
        .policy-content ul {
            padding-left: 2rem;
        }
        .highlight-box {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-left: 4px solid #5D5CDE;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1.5rem 0;
        }
        .contact-info {
            background: linear-gradient(135deg, #5D5CDE, #4a4bc7);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                📚 BookTrading
            </a>

            <!-- Search Bar -->
            <div class="mx-auto" style="width: 400px;">
                <form method="GET" action="search.php" class="d-flex">
                    <div class="input-group">
                        <input type="text" name="q" class="form-control" placeholder="Search amazing books..." style="border-radius: 25px 0 0 25px;">
                        <button class="btn btn-primary" type="submit" style="border-radius: 0 25px 25px 0;">
                            🔍
                        </button>
                    </div>
                </form>
            </div>

            <div class="navbar-nav ms-auto d-flex align-items-center">
                <!-- Cart Icon -->
                <a class="nav-link position-relative me-3" href="cart.php">
                    <span class="cart-icon">🛒</span>
                    <?php if ($cartCount > 0): ?>
                        <span class="cart-badge"><?php echo $cartCount; ?></span>
                    <?php endif; ?>
                </a>

                <?php if (isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>
                        <a class="nav-link" href="admin/dashboard.php">👑 Admin</a>
                    <?php endif; ?>
                    <a class="nav-link" href="logout.php">
                        🚪 Logout
                    </a>
                <?php else: ?>
                    <a class="nav-link" href="login.php">🔑 Login</a>
                    <a class="nav-link" href="register.php">
                        <button class="btn btn-primary btn-sm">📝 Register</button>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section position-relative">
        <div class="container text-center position-relative" style="z-index: 2;">
            <h1 class="display-4 mb-3 fw-bold">
                🛡️ Privacy Policy
            </h1>
            <p class="lead fs-5 mb-0">How we protect and handle your personal information</p>
        </div>
    </div>

    <div class="container mt-4 mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Introduction -->
                <div class="content-section">
                    <h2 class="section-title">📋 Introduction</h2>
                    <div class="policy-content">
                        <p>
                            Welcome to BookTrading! We are committed to protecting your privacy and ensuring the security of your personal information. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you visit our website and use our services.
                        </p>
                        <p>
                            By using BookTrading, you agree to the collection and use of information in accordance with this policy. If you do not agree with our policies and practices, please do not use our services.
                        </p>
                    </div>
                </div>

                <!-- Information We Collect -->
                <div class="content-section">
                    <h2 class="section-title">📊 Information We Collect</h2>
                    <div class="policy-content">
                        <h4>📝 Personal Information</h4>
                        <p>We may collect the following personal information when you register or use our services:</p>
                        <ul>
                            <li><strong>Account Information:</strong> Name, email address, phone number, and shipping address</li>
                            <li><strong>Profile Information:</strong> Profile picture, preferences, and reading interests</li>
                            <li><strong>Transaction Information:</strong> Purchase history, payment details, and order information</li>
                            <li><strong>Communication Data:</strong> Messages, reviews, ratings, and customer support inquiries</li>
                        </ul>

                        <h4>🔄 Automatically Collected Information</h4>
                        <ul>
                            <li><strong>Usage Data:</strong> Pages visited, time spent on site, and browsing behavior</li>
                            <li><strong>Device Information:</strong> IP address, browser type, operating system, and device identifiers</li>
                            <li><strong>Cookies and Tracking:</strong> Information collected through cookies and similar technologies</li>
                        </ul>
                    </div>
                </div>

                <!-- How We Use Your Information -->
                <div class="content-section">
                    <h2 class="section-title">⚙️ How We Use Your Information</h2>
                    <div class="policy-content">
                        <p>We use the collected information for the following purposes:</p>
                        <ul>
                            <li><strong>Service Provision:</strong> To provide, maintain, and improve our book trading platform</li>
                            <li><strong>Order Processing:</strong> To process transactions, manage orders, and handle payments</li>
                            <li><strong>Communication:</strong> To send order updates, notifications, and respond to inquiries</li>
                            <li><strong>Personalization:</strong> To provide personalized recommendations and enhance user experience</li>
                            <li><strong>Security:</strong> To detect, prevent, and address fraud, abuse, and security issues</li>
                            <li><strong>Legal Compliance:</strong> To comply with applicable laws and regulations</li>
                            <li><strong>Marketing:</strong> To send promotional materials (with your consent)</li>
                        </ul>
                    </div>
                </div>

                <!-- Information Sharing -->
                <div class="content-section">
                    <h2 class="section-title">🤝 Information Sharing and Disclosure</h2>
                    <div class="policy-content">
                        <p>We may share your information in the following circumstances:</p>
                        
                        <h4>✅ With Your Consent</h4>
                        <p>We may share information when you explicitly consent to such sharing.</p>

                        <h4>🔧 Service Providers</h4>
                        <p>We may share information with third-party service providers who assist us in:</p>
                        <ul>
                            <li>💰 Payment processing</li>
                            <li>📦 Shipping and delivery</li>
                            <li>🎧 Customer support</li>
                            <li>📈 Analytics and marketing</li>
                        </ul>

                        <h4>⚖️ Legal Requirements</h4>
                        <p>We may disclose information if required by law or in response to valid legal processes.</p>

                        <h4>🏢 Business Transfers</h4>
                        <p>In the event of a merger, acquisition, or sale of assets, your information may be transferred to the new entity.</p>
                    </div>
                </div>

                <!-- Data Security -->
                <div class="content-section">
                    <h2 class="section-title">🔒 Data Security</h2>
                    <div class="policy-content">
                        <p>We implement appropriate technical and organizational measures to protect your personal information:</p>
                        <ul>
                            <li><strong>🔐 Encryption:</strong> We use SSL/TLS encryption for data transmission</li>
                            <li><strong>🚪 Access Controls:</strong> Limited access to personal data on a need-to-know basis</li>
                            <li><strong>🔄 Regular Updates:</strong> We regularly update our security measures and systems</li>
                            <li><strong>👁️ Monitoring:</strong> Continuous monitoring for security threats and vulnerabilities</li>
                        </ul>
                        
                        <div class="highlight-box">
                            <p class="mb-0">
                                ℹ️ <strong>Note:</strong> While we strive to protect your information, no method of transmission over the internet is 100% secure. We cannot guarantee absolute security.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Your Rights -->
                <div class="content-section">
                    <h2 class="section-title">👤 Your Rights and Choices</h2>
                    <div class="policy-content">
                        <p>You have the following rights regarding your personal information:</p>
                        <ul>
                            <li><strong>👁️ Access:</strong> Request access to your personal data</li>
                            <li><strong>✏️ Correction:</strong> Request correction of inaccurate information</li>
                            <li><strong>🗑️ Deletion:</strong> Request deletion of your personal data</li>
                            <li><strong>📤 Portability:</strong> Request a copy of your data in a portable format</li>
                            <li><strong>✋ Opt-out:</strong> Unsubscribe from marketing communications</li>
                            <li><strong>🚫 Object:</strong> Object to certain processing of your data</li>
                        </ul>
                        
                        <p>To exercise these rights, please contact us using the information provided below.</p>
                    </div>
                </div>

                <!-- Cookies -->
                <div class="content-section">
                    <h2 class="section-title">🍪 Cookies and Tracking Technologies</h2>
                    <div class="policy-content">
                        <p>We use cookies and similar tracking technologies to enhance your experience:</p>
                        
                        <h4>🏷️ Types of Cookies We Use</h4>
                        <ul>
                            <li><strong>⚡ Essential Cookies:</strong> Required for basic website functionality</li>
                            <li><strong>📊 Performance Cookies:</strong> Help us understand how visitors use our site</li>
                            <li><strong>⚙️ Functional Cookies:</strong> Remember your preferences and settings</li>
                            <li><strong>📢 Marketing Cookies:</strong> Used to deliver relevant advertisements</li>
                        </ul>

                        <p>You can control cookie settings through your browser preferences. However, disabling cookies may affect website functionality.</p>
                    </div>
                </div>

                <!-- Children's Privacy -->
                <div class="content-section">
                    <h2 class="section-title">👶 Children's Privacy</h2>
                    <div class="policy-content">
                        <p>
                            Our services are not intended for children under 13 years of age. We do not knowingly collect personal information from children under 13. If we become aware that we have collected personal information from a child under 13, we will take steps to delete such information.
                        </p>
                    </div>
                </div>

                <!-- Changes to Privacy Policy -->
                <div class="content-section">
                    <h2 class="section-title">🔄 Changes to This Privacy Policy</h2>
                    <div class="policy-content">
                        <p>
                            We may update this Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page. You are advised to review this Privacy Policy periodically for any changes.
                        </p>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="contact-info">
                    <h3>📞 Contact Us</h3>
                    <p class="mb-3">If you have any questions about this Privacy Policy, please contact us:</p>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>📧 Email:</strong> privacy@booktrading.com</p>
                            <p><strong>📞 Phone:</strong> +977-123-456-7890</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>📍 Address:</strong> BookTrading Privacy Team</p>
                            <p>Kathmandu, Nepal</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="mt-5 bg-white border-top" style="color: #2c3e50;">
        <div class="container py-4">
            <div class="row align-items-center">
                <div class="col-md-6 mb-2 mb-md-0 d-flex align-items-center">
                    <span class="fw-bold" style="color: #5D5CDE; font-size: 1.2rem;">
                        📚 BookTrading
                    </span>
                    <span class="ms-3" style="color: #2c3e50;">&copy; 2025. All rights reserved.</span>
                </div>
                <div class="col-md-6 text-md-end mt-2 mt-md-0">
                    <a href="privacy_policy.php" class="text-decoration-none me-3" style="color: #2c3e50;">🔒 Privacy Policy</a>
                    <a href="terms.php" class="text-decoration-none" style="color: #2c3e50;">📋 Terms</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="assets/js/bootstrap.min.js"></script>
</body>
</html>