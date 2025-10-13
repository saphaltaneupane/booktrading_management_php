<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $author = mysqli_real_escape_string($conn, $_POST['author']);
    $genre = mysqli_real_escape_string($conn, $_POST['genre']);
    $price = (float)$_POST['price'];
    $quantity = (int)$_POST['quantity'];
    $condition = $_POST['condition'];
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $adminId = $_SESSION['user_id'];
    
    // Validation for price and quantity
    if ($price <= 0) {
        $error = 'Price must be greater than 0.';
    } elseif ($quantity <= 0) {
        $error = 'Quantity must be greater than 0.';
    }
    
    // Handle image upload
    $imageName = '';
    if (!$error && isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $uploadDir = '../uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $imageName = time() . '_' . $_FILES['image']['name'];
        $uploadPath = $uploadDir . $imageName;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
            // Image uploaded successfully
        } else {
            $error = 'Failed to upload image';
        }
    }
    
    if (!$error) {
        $query = "INSERT INTO books (title, author, genre, price, quantity, condition_type, description, image, added_by, is_available) 
                  VALUES ('$title', '$author', '$genre', $price, $quantity, '$condition', '$description', '$imageName', $adminId, 1)";
        
        if (mysqli_query($conn, $query)) {
            $success = 'Book added successfully!';
        } else {
            $error = 'Failed to add book. Please try again.';
        }
    }
}

// Get active categories from database
$categoriesQuery = "SELECT name FROM categories WHERE is_active = 1 ORDER BY name";
$categoriesResult = mysqli_query($conn, $categoriesQuery);
$genres = [];
while ($row = mysqli_fetch_assoc($categoriesResult)) {
    $genres[] = $row['name'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Book - Admin</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .admin-sidebar { background-color: #343a40; min-height: 100vh; }
        .admin-sidebar .nav-link { color: #adb5bd; }
        .admin-sidebar .nav-link:hover { color: #fff; background-color: #5D5CDE; }
        .admin-sidebar .nav-link.active { background-color: #5D5CDE; color: #fff; }
        .content-area { padding: 2rem; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin_sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-10 content-area">
                <h1 class="mb-4">Add New Book</h1>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Book Title</label>
                                        <input type="text" class="form-control" id="title" name="title" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="author" class="form-label">Author</label>
                                        <input type="text" class="form-control" id="author" name="author" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="genre" class="form-label">Category/Genre</label>
                                        <select class="form-select" id="genre" name="genre" required>
                                            <option value="">Select Category</option>
                                            <?php foreach ($genres as $genre): ?>
                                                <option value="<?php echo htmlspecialchars($genre); ?>"><?php echo htmlspecialchars($genre); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="price" class="form-label">Price (Rs.)</label>
                                        <input type="number" class="form-control" id="price" name="price"  min="1" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="quantity" class="form-label">Quantity</label>
                                        <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="condition" class="form-label">Condition</label>
                                        <select class="form-select" id="condition" name="condition" required>
                                           
                                            <option value="new">New</option>
                                           
                                          
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="image" class="form-label">Book Image</label>
                                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4"></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Add Book</button>
                            <a href="manage_books.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>