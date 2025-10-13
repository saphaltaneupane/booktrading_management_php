<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$success = '';
$error = '';

// Handle user deletion
if (isset($_POST['delete_user'])) {
    $userId = (int)$_POST['user_id'];

    // Delete all related records first to avoid foreign key constraints
    
    // Delete order items for this user's orders
    mysqli_query($conn, "DELETE oi FROM order_items oi 
                        JOIN orders o ON oi.order_id = o.id 
                        WHERE o.user_id = $userId");
    
    // Delete user's orders
    mysqli_query($conn, "DELETE FROM orders WHERE user_id = $userId");
    
    // Delete user's cart items
    mysqli_query($conn, "DELETE FROM cart WHERE user_id = $userId");

    // Delete user
    $query = "DELETE FROM users WHERE id = $userId AND is_admin = 0";
    if (mysqli_query($conn, $query)) {
        $success = 'User deleted successfully!';
    } else {
        $error = 'Failed to delete user.';
    }
}

// Get all non-admin users
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as order_count,
          (SELECT SUM(total_amount) FROM orders WHERE user_id = u.id AND payment_status = 'completed') as total_spent
          FROM users u WHERE is_admin = 0 ORDER BY u.created_at DESC";
$users = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .admin-sidebar { background-color: #343a40; min-height: 100vh; }
        .admin-sidebar .nav-link { color: #adb5bd; }
        .admin-sidebar .nav-link:hover { color: #fff; background-color: #5D5CDE; }
        .content-area { padding: 2rem; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin_sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-10 content-area">
                <h1 class="mb-4">Manage Users</h1>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">All Users</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Address</th>
                                        <th>Orders</th>
                                        <th>Total Spent</th>
                                        <th>Join Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($user = mysqli_fetch_assoc($users)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($user['address'], 0, 50)) . '...'; ?></td>
                                        <td><?php echo $user['order_count']; ?></td>
                                        <td>Rs. <?php echo number_format($user['total_spent'] ? $user['total_spent'] : 0, 2); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?')">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="delete_user" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
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
</body>
</html>