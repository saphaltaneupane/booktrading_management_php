<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$success = '';
$error = '';

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_category'])) {
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    
    if (!empty($name)) {
        $checkQuery = "SELECT id FROM categories WHERE name = '$name'";
        $checkResult = mysqli_query($conn, $checkQuery);
        
        if (mysqli_num_rows($checkResult) > 0) {
            $error = "Category '$name' already exists!";
        } else {
            $query = "INSERT INTO categories (name, description) VALUES ('$name', '$description')";
            if (mysqli_query($conn, $query)) {
                $success = "Category '$name' added successfully!";
            } else {
                $error = "Failed to add category. Please try again.";
            }
        }
    } else {
        $error = "Category name is required!";
    }
}

// Handle Delete Category
if (isset($_GET['delete']) && $_GET['delete'] > 0) {
    $categoryId = (int)$_GET['delete'];
    
    // Check if category is used in books
    $checkBooks = "SELECT COUNT(*) as count FROM books WHERE genre = (SELECT name FROM categories WHERE id = $categoryId)";
    $bookResult = mysqli_query($conn, $checkBooks);
    $bookCount = mysqli_fetch_assoc($bookResult)['count'];
    
    if ($bookCount > 0) {
        $error = "Cannot delete category. It is being used by $bookCount book(s).";
    } else {
        $deleteQuery = "DELETE FROM categories WHERE id = $categoryId";
        if (mysqli_query($conn, $deleteQuery)) {
            $success = "Category deleted successfully!";
        } else {
            $error = "Failed to delete category.";
        }
    }
}

// Handle Toggle Status
if (isset($_GET['toggle']) && $_GET['toggle'] > 0) {
    $categoryId = (int)$_GET['toggle'];
    $toggleQuery = "UPDATE categories SET is_active = NOT is_active WHERE id = $categoryId";
    if (mysqli_query($conn, $toggleQuery)) {
        $success = "Category status updated successfully!";
    } else {
        $error = "Failed to update category status.";
    }
}

// Get all categories
$query = "SELECT * FROM categories ORDER BY name";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Admin</title>
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
                <h1 class="mb-4">Manage Categories</h1>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Add Category Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Add New Category</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Category Name *</label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <input type="text" class="form-control" id="description" name="description" 
                                               placeholder="Brief description of this category">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="mb-3">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="submit" name="add_category" class="btn btn-primary w-100">
                                            Add Category
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Categories List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Existing Categories</h5>
                    </div>
                    <div class="card-body">
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <!-- <th>ID</th> -->
                                            <th>Category Name</th>
                                            <th>Description</th>
                                            <th>Status</th>
                                            
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($category = mysqli_fetch_assoc($result)): ?>
                                            <?php
                                            // Count books in this category
                                            $countQuery = "SELECT COUNT(*) as count FROM books WHERE genre = '" . mysqli_real_escape_string($conn, $category['name']) . "'";
                                            $countResult = mysqli_query($conn, $countQuery);
                                            $bookCount = mysqli_fetch_assoc($countResult)['count'];
                                            ?>
                                            <tr>
                                                <!-- <td><?php echo $category['id']; ?></td> -->
                                                <td>
                                                    <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($category['description']); ?></td>
                                                <td>
                                                    <?php if ($category['is_active']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                              
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y', strtotime($category['created_at'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary" 
                                                                onclick="editCategory(<?php echo $category['id']; ?>, '<?php echo addslashes($category['name']); ?>', '<?php echo addslashes($category['description']); ?>')"
                                                                title="Edit">
                                                            ✏️
                                                        </button>
                                                        <?php if ($bookCount == 0): ?>
                                                            <a href="?delete=<?php echo $category['id']; ?>" 
                                                               class="btn btn-outline-danger"
                                                               onclick="return confirm('Are you sure you want to delete this category?')"
                                                               title="Delete">
                                                                🗑️
                                                            </a>
                                                        <?php else: ?>
                                                            <button class="btn btn-outline-secondary" disabled title="Cannot delete - has books">
                                                                🔒
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <p class="text-muted">No categories found. Add your first category above.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editCategoryForm" method="POST">
                    <div class="modal-body">
                        <input type="hidden" id="edit_category_id" name="edit_category_id">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Category Name</label>
                            <input type="text" class="form-control" id="edit_name" name="edit_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <input type="text" class="form-control" id="edit_description" name="edit_description">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_category" class="btn btn-primary">Update Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        function editCategory(id, name, description) {
            document.getElementById('edit_category_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_description').value = description;
            new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
        }
    </script>
</body>
</html>

<?php
// Handle Edit Category
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_category'])) {
    $categoryId = (int)$_POST['edit_category_id'];
    $name = mysqli_real_escape_string($conn, trim($_POST['edit_name']));
    $description = mysqli_real_escape_string($conn, trim($_POST['edit_description']));
    
    if (!empty($name)) {
        $checkQuery = "SELECT id FROM categories WHERE name = '$name' AND id != $categoryId";
        $checkResult = mysqli_query($conn, $checkQuery);
        
        if (mysqli_num_rows($checkResult) > 0) {
            echo "<script>alert('Category name already exists!'); window.location.href = 'manage_categories.php';</script>";
        } else {
            $updateQuery = "UPDATE categories SET name = '$name', description = '$description' WHERE id = $categoryId";
            if (mysqli_query($conn, $updateQuery)) {
                echo "<script>alert('Category updated successfully!'); window.location.href = 'manage_categories.php';</script>";
            } else {
                echo "<script>alert('Failed to update category!'); window.location.href = 'manage_categories.php';</script>";
            }
        }
    }
}
?>