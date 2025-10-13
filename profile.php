<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$userId = $_SESSION['user_id'];
$user = getUserById($userId);
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    
    // Basic validation
    if (strlen($phone) != 10 || !is_numeric($phone)) {
        $error = 'Phone number must be exactly 10 digits';
    } else {
        // Check current password if trying to change password
        if (!empty($newPassword)) {
            if ($currentPassword !== $user['password']) {
                $error = 'Current password is incorrect';
            } else {
                // Update with new password
                $query = "UPDATE users SET name = '$name', phone = '$phone', address = '$address', password = '$newPassword' WHERE id = $userId";
            }
        } else {
            // Update without password change
            $query = "UPDATE users SET name = '$name', phone = '$phone', address = '$address' WHERE id = $userId";
        }
        
        if (!$error && mysqli_query($conn, $query)) {
            $success = 'Profile updated successfully!';
            $user = getUserById($userId); // Refresh user data
        } elseif (!$error) {
            $error = 'Failed to update profile. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - BookTrading</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .btn-primary { background-color: #5D5CDE; border-color: #5D5CDE; }
        .profile-card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
         .main-content {
            margin-left: 250px;
        
            min-height: 100vh;
        }
       .mb-4{
            background: linear-gradient(135deg, #5D5CDE 0%, #7C4DFF 100%) !important;
            color: white;
           padding-left:10px;
         margin-top: 0px;
            padding: 30px;
            
            border-radius: 10px;
            margin-right:20px;
            margin-left:0px;
            margin-bottom: 10px;
            width: 100%;
            height: 100%;
            box-shadow: 0 4px 15px rgba(93, 92, 222, 0.3);
        } box-shadow: 0 4px 15px rgba(93, 92, 222, 0.3);
        
         @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'includes/user_sidebar.php'; ?>

    <div class="main-content">
        <div class="container mt-4">
            <h2 class="mb-4">My Profile</h2>
            
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="profile-card p-4">
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                <small class="text-muted">Email cannot be changed</small>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="text" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($user['phone']); ?>" maxlength="10" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($user['address']); ?></textarea>
                            </div>
                            
                            <hr>
                            <h5>Change Password (Optional)</h5>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password">
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>