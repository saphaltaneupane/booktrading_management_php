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
    <title>Terms of Service - BookTrading</title>
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
        .terms-content h4 {
            color: #2c3e50;
            font-weight: 600;
            margin-top: 2rem;
            margin-bottom: 1rem;
        }
        .terms-content h5 {
            color: #34495e;
            font-weight: 500;
            margin-top: 1.5rem;
            margin-bottom: 0.8rem;
        }
        .terms-content p, .terms-content li {
            color: #555;
            line-height: 1.8;
            margin-bottom: 1rem;
        }
        .terms-content ul, .terms-content ol {
            padding-left: 2rem;
        }
        .highlight-box {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-left: 4px solid #5D5CDE;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1.5rem 0;
        }
        .warning-box {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border-left: 4px solid #ffc107;
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
                📋 Terms of Service
            </h1>
            <p class="lead fs-5 mb-0">Terms and conditions for using BookTrading platform</p>
        </div>
    </div>

    <div class="container mt-4 mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Introduction -->
                <div class="content-section">
                    <h2 class="section-title">📖 Introduction</h2>
                    <div class="terms-content">
                        <p>
                            Welcome to BookTrading! These Terms of Service ("Terms") govern your use of the BookTrading website and services. By accessing or using our platform, you agree to be bound by these Terms.
                        </p>
                        <p>
                            If you do not agree to these Terms, please do not use our services. We reserve the right to modify these Terms at any time, and such modifications will be effective immediately upon posting.
                        </p>
                    </div>
                </div>

                <!-- Acceptance of Terms -->
                <div class="content-section">
                    <h2 class="section-title">✅ Acceptance of Terms</h2>
                    <div class="terms-content">
                        <p>By using BookTrading, you confirm that:</p>
                        <ul>
                            <li>You are at least 18 years old or have parental consent</li>
                            <li>You have the legal capacity to enter into these Terms</li>
                            <li>You will use our services in compliance with all applicable laws</li>
                            <li>All information you provide is accurate and truthful</li>
                        </ul>
                    </div>
                </div>

                <!-- Account Registration -->
                <div class="content-section">
                    <h2 class="section-title">👤 Account Registration and Security</h2>
                    <div class="terms-content">
                        <h4>📝 Account Creation</h4>
                        <p>To access certain features, you must create an account. You agree to:</p>
                        <ul>
                            <li>Provide accurate and complete information</li>
                            <li>Maintain and update your account information</li>
                            <li>Keep your login credentials secure</li>
                            <li>Not share your account with others</li>
                        </ul>

                        <h4>🔒 Account Responsibility</h4>
                        <p>You are responsible for all activities that occur under your account. Notify us immediately of any unauthorized use of your account.</p>
                    </div>
                </div>

                <!-- Platform Services -->
                <div class="content-section">
                    <h2 class="section-title">🌐 Platform Services</h2>
                    <div class="terms-content">
                        <h4>📚 BookTrading Services Include:</h4>
                        <ul>
                            <li><strong>🛒 Book Marketplace:</strong> Platform for buying and selling books</li>
                            <li><strong>👤 User Accounts:</strong> Personal profiles and transaction history</li>
                            <li><strong>⭐ Rating System:</strong> Reviews and ratings for books and users</li>
                            <li><strong>🔍 Search and Discovery:</strong> Tools to find relevant books</li>
                            <li><strong>💬 Communication:</strong> Messaging between buyers and sellers</li>
                        </ul>

                        <h4>⚡ Service Availability</h4>
                        <p>We strive to maintain service availability but do not guarantee uninterrupted access. Services may be temporarily unavailable for maintenance or updates.</p>
                    </div>
                </div>

                <!-- User Responsibilities -->
                <div class="content-section">
                    <h2 class="section-title">⚖️ User Responsibilities and Conduct</h2>
                    <div class="terms-content">
                        <h4>✅ Acceptable Use</h4>
                        <p>You agree to use BookTrading responsibly and in accordance with these Terms. You will NOT:</p>
                        <ul>
                            <li>🚫 Violate any laws or regulations</li>
                            <li>🚫 Infringe on intellectual property rights</li>
                            <li>🚫 Post false, misleading, or deceptive content</li>
                            <li>🚫 Engage in fraudulent or harmful activities</li>
                            <li>🚫 Harass, threaten, or abuse other users</li>
                            <li>🚫 Attempt to hack or compromise platform security</li>
                            <li>🚫 Spam or send unsolicited communications</li>
                        </ul>

                        <h4>📝 Content Standards</h4>
                        <p>All content you post must be:</p>
                        <ul>
                            <li>✅ Accurate and truthful</li>
                            <li>✅ Respectful and appropriate</li>
                            <li>✅ Compliant with copyright laws</li>
                            <li>✅ Free from offensive or harmful material</li>
                        </ul>
                    </div>
                </div>

                <!-- Buying and Selling -->
                <div class="content-section">
                    <h2 class="section-title">🤝 Buying and Selling Books</h2>
                    <div class="terms-content">
                        <h4>🏪 Seller Responsibilities</h4>
                        <ul>
                            <li>📖 Provide accurate book descriptions and conditions</li>
                            <li>💰 Set fair and reasonable prices</li>
                            <li>💬 Respond promptly to buyer inquiries</li>
                            <li>📦 Ship items within specified timeframes</li>
                            <li>📦 Package items securely to prevent damage</li>
                        </ul>

                        <h4>🛒 Buyer Responsibilities</h4>
                        <ul>
                            <li>👁️ Read item descriptions carefully before purchasing</li>
                            <li>💰 Pay for items promptly</li>
                            <li>📍 Provide accurate shipping information</li>
                            <li>💬 Communicate respectfully with sellers</li>
                        </ul>

                        <h4>🔄 Transaction Process</h4>
                        <ol>
                            <li>🛒 Buyer places order and makes payment</li>
                            <li>✅ Seller receives notification and confirms order</li>
                            <li>📦 Seller ships item with tracking information</li>
                            <li>⭐ Buyer receives item and can leave feedback</li>
                        </ol>
                    </div>
                </div>

                <!-- Payment and Fees -->
                <div class="content-section">
                    <h2 class="section-title">💰 Payment and Fees</h2>
                    <div class="terms-content">
                        <h4>💳 Payment Methods</h4>
                        <p>We accept the following payment methods:</p>
                        <ul>
                            <li>💵 Cash on Delivery (COD)</li>
                            <li>📱 Digital payment systems (where available)</li>
                        </ul>

                        <h4>💲 Platform Fees</h4>
                        <p>BookTrading may charge fees for certain services. Any applicable fees will be clearly disclosed before you incur them.</p>

                        <h4>🔄 Refunds and Returns</h4>
                        <p>Refund and return policies are determined by individual sellers. We encourage fair practices but are not responsible for seller policies.</p>
                    </div>
                </div>

                <!-- Intellectual Property -->
                <div class="content-section">
                    <h2 class="section-title">🎨 Intellectual Property</h2>
                    <div class="terms-content">
                        <h4>🌐 Platform Content</h4>
                        <p>BookTrading owns all rights to the platform design, features, and proprietary content. You may not reproduce, distribute, or create derivative works without permission.</p>

                        <h4>👤 User Content</h4>
                        <p>You retain ownership of content you post but grant BookTrading a license to use, display, and distribute your content in connection with our services.</p>

                        <h4>📄 Copyright Compliance</h4>
                        <p>We respect intellectual property rights. If you believe your copyright has been infringed, please contact us with details.</p>
                    </div>
                </div>

                <!-- Privacy and Data -->
                <div class="content-section">
                    <h2 class="section-title">🔒 Privacy and Data Protection</h2>
                    <div class="terms-content">
                        <p>Your privacy is important to us. Our Privacy Policy explains how we collect, use, and protect your information. By using our services, you also agree to our Privacy Policy.</p>
                        <p>Key privacy points:</p>
                        <ul>
                            <li>📊 We collect only necessary information</li>
                            <li>🔐 We use secure methods to protect your data</li>
                            <li>🚫 We do not sell your personal information</li>
                            <li>⚙️ You can control your privacy settings</li>
                        </ul>
                    </div>
                </div>

                <!-- Disclaimers -->
                <div class="content-section">
                    <h2 class="section-title">⚠️ Disclaimers and Limitations</h2>
                    <div class="terms-content">
                        <div class="warning-box">
                            <h5>⚠️ Important Disclaimer</h5>
                            <p class="mb-0">BookTrading is a platform that connects buyers and sellers. We do not guarantee the quality, safety, or legality of items listed, the truth or accuracy of listings, or the ability of sellers to sell items or buyers to pay for items.</p>
                        </div>

                        <h4>⚡ Service Limitations</h4>
                        <ul>
                            <li>Services are provided "as is" without warranties</li>
                            <li>We do not guarantee continuous service availability</li>
                            <li>We are not responsible for user-generated content</li>
                            <li>Platform features may change without notice</li>
                        </ul>

                        <h4>🛡️ Liability Limitations</h4>
                        <p>To the maximum extent permitted by law, BookTrading shall not be liable for any indirect, incidental, special, or consequential damages arising from your use of our services.</p>
                    </div>
                </div>

                <!-- Termination -->
                <div class="content-section">
                    <h2 class="section-title">🚪 Account Termination</h2>
                    <div class="terms-content">
                        <h4>👤 Termination by User</h4>
                        <p>You may deactivate your account at any time by contacting customer support. Some information may be retained for legal or business purposes.</p>

                        <h4>🔨 Termination by BookTrading</h4>
                        <p>We may suspend or terminate accounts that violate these Terms, including:</p>
                        <ul>
                            <li>🔄 Repeated policy violations</li>
                            <li>🚫 Fraudulent activities</li>
                            <li>⚠️ Abuse of platform or other users</li>
                            <li>⚖️ Legal requirements</li>
                        </ul>

                        <h4>📝 Effect of Termination</h4>
                        <p>Upon termination, your access to services will cease, but these Terms will continue to apply to prior use of services.</p>
                    </div>
                </div>

                <!-- Governing Law -->
                <div class="content-section">
                    <h2 class="section-title">⚖️ Governing Law and Disputes</h2>
                    <div class="terms-content">
                        <h4>📍 Applicable Law</h4>
                        <p>These Terms are governed by the laws of Nepal. Any disputes will be resolved in the courts of Nepal.</p>

                        <h4>🤝 Dispute Resolution</h4>
                        <p>We encourage resolving disputes through direct communication. If that fails, disputes may be resolved through:</p>
                        <ol>
                            <li>🎧 Customer support mediation</li>
                            <li>⚖️ Formal legal proceedings if necessary</li>
                        </ol>
                    </div>
                </div>

                <!-- Changes to Terms -->
                <div class="content-section">
                    <h2 class="section-title">🔄 Changes to Terms</h2>
                    <div class="terms-content">
                        <p>We may update these Terms periodically. Changes will be effective immediately upon posting. Continued use of our services after changes constitutes acceptance of the new Terms.</p>
                        <p>We recommend reviewing these Terms regularly to stay informed of any updates.</p>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="contact-info">
                    <h3>📞 Contact Us</h3>
                    <p class="mb-3">If you have questions about these Terms of Service, please contact us:</p>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>📧 Email:</strong> support@booktrading.com</p>
                            <p><strong>📞 Phone:</strong> +977-123-456-7890</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>📍 Address:</strong> BookTrading Legal Team</p>
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