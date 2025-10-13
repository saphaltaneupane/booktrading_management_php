<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $author = mysqli_real_escape_string($conn, $_POST['author']);
    $genre = mysqli_real_escape_string($conn, $_POST['genre']);
    $price = (float)$_POST['price'];
    $condition = $_POST['condition'];
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $userId = $_SESSION['user_id'];

    // Validate price
    if ($price <= 0) {
        $error = 'Price must be greater than 0.';
    }

    // Validate that only 'old' or 'used' condition is allowed for users
    if (!$error && !in_array($condition, ['old', 'used'])) {
        $error = 'Users can only add used/old books for sale.';
    }

    // Handle image upload
    $imageName = '';
    if (!$error && isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $uploadDir = 'uploads/';
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
        // Set is_available = 0 for pending review
        $query = "INSERT INTO books (title, author, genre, price, condition_type, description, image, added_by, is_available) 
                  VALUES ('$title', '$author', '$genre', $price, '$condition', '$description', '$imageName', $userId, 0)";

        if (mysqli_query($conn, $query)) {
            $success = 'Book added successfully! It will be available after admin approval.';
        } else {
            $error = 'Failed to add book. Please try again.';
        }
    }
}

// Get active categories from database
$categoriesQuery = "SELECT name FROM categories WHERE is_active = 1 ORDER BY name";
$categoriesResult = mysqli_query($conn, $categoriesQuery);
$genres = [];
if ($categoriesResult) {
    while ($row = mysqli_fetch_assoc($categoriesResult)) {
        $genres[] = $row['name'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Book - BookTrading</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body { background-color: #f8f9fa; }
        .btn-primary { background-color: #5D5CDE; border-color: #5D5CDE; }
        .add-book-card { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
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
        }
         @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }
        
    </style>
</head>
<body>
    <?php include 'includes/user_sidebar.php'; ?>

    <div class="main-content">
        <div class="container mt-4">
            <h2 class="mb-4">📖 Sell Your Old Book</h2>
            
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="add-book-card p-4">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <?php echo $success; ?>
                                <hr>
                                <div class="d-flex gap-2">
                                    <a href="my_listed_books.php" class="btn btn-sm btn-outline-primary">
                                        📊 Track My Listed Books
                                    </a>
                                    <a href="add_book.php" class="btn btn-sm btn-primary">
                                        ➕ Add Another Book
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Book Title *</label>
                                        <input type="text" class="form-control" id="title" name="title" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="author" class="form-label">Author *</label>
                                        <input type="text" class="form-control" id="author" name="author" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="genre" class="form-label">Category/Genre *</label>
                                        <select class="form-select" id="genre" name="genre" required>
                                            <option value="">Select Category</option>
                                            <?php foreach ($genres as $genre): ?>
                                                <option value="<?php echo htmlspecialchars($genre); ?>"><?php echo htmlspecialchars($genre); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="price" class="form-label">Selling Price (Rs.) *</label>
                                        <input type="number" class="form-control" id="price" name="price" step="0.01" min="1" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="condition" class="form-label">Book Condition *</label>
                                <select class="form-select" id="condition" name="condition" required>
                                    <option value="">Select Condition</option>
                               
                                    <option value="old">Old/Used</option>
                                </select>
                                <small class="text-muted">Users can only sell used/old books. New books are added by admin only.</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="image" class="form-label">Book Image</label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                <small class="text-muted">Upload a clear image of your book (recommended for better sales)</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Book Description *</label>
                                <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">📤 Submit for Review</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Ensure only 'old' or 'used' condition for users
        document.getElementById('condition').addEventListener('change', function() {
            if (!['old', 'used'].includes(this.value)) {
                alert('Users can only add used/old books for sale.');
                this.value = '';
            }
        });

        // Price validation
        document.getElementById('price').addEventListener('input', function() {
            if (parseFloat(this.value) <= 0) {
                this.setCustomValidity('Price must be greater than 0');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>