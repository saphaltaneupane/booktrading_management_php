<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    
    $query = "SELECT * FROM users WHERE email = '$email' AND password = '$password'";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['is_admin'] = $user['is_admin'];

        // Merge session cart with database cart after login
        if (!empty($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $bookId => $quantity) {
                $checkQuery = "SELECT * FROM cart WHERE user_id = {$user['id']} AND book_id = $bookId";
                $checkResult = mysqli_query($conn, $checkQuery);

                if (mysqli_num_rows($checkResult) > 0) {
                    $updateQuery = "UPDATE cart SET quantity = quantity + $quantity WHERE user_id = {$user['id']} AND book_id = $bookId";
                    mysqli_query($conn, $updateQuery);
                } else {
                    $insertQuery = "INSERT INTO cart (user_id, book_id, quantity) VALUES ({$user['id']}, $bookId, $quantity)";
                    mysqli_query($conn, $insertQuery);
                }
            }
            // Clear session cart
            $_SESSION['cart'] = [];
        }

        // Redirect to admin dashboard if admin
        if ($user['is_admin'] == 1) {
            redirect('admin/dashboard.php');
        } else {
            redirect('index.php');
        }
    } else {
        $error = 'Invalid email or password';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - BookTrading</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: url('backgroundimage.webp') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
        }
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            z-index: 1;
        }
        .container {
            position: relative;
            z-index: 2;
        }
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #5D5CDE, #4a4bc7);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .btn-primary {
            background-color: #5D5CDE;
            border-color: #5D5CDE;
        }
        .btn-outline-secondary {
            border-color: #6c757d;
            color: #6c757d;
        }
        .btn-outline-secondary:hover {
            background-color: #6c757d;
            border-color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="login-container">
                    <div class="login-header">
                        <h2>📚 BookTrading</h2>
                        <p>Login to your account</p>
                    </div>
                    <div class="p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 mb-3">Login</button>
                        </form>
                        
                        <div class="text-center">
                            <p>Don't have an account? <a href="register.php" class="text-decoration-none">Register here</a></p>
                            <a href="index.php" class="btn btn-outline-secondary">Back to Home</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>