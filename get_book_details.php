<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    echo "Access denied";
    exit();
}

$book_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

$query = "SELECT * FROM books WHERE id = ? AND added_by = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $book_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$book = mysqli_fetch_assoc($result);

if (!$book) {
    echo "Book not found";
    exit();
}

// Get status text
$status_text = '';
$status_class = '';
switch($book['is_available']) {
    case 0:
        $status_text = 'Pending Admin Review';
        $status_class = 'text-warning';
        break;
    case 1:
        $status_text = 'Approved & Available for Sale';
        $status_class = 'text-success';
        break;
    case -1:
        $status_text = 'Rejected by Admin';
        $status_class = 'text-danger';
        break;
}
?>

<div class="row">
    <div class="col-md-4">
        <?php if ($book['image'] && file_exists('uploads/' . $book['image'])): ?>
            <img src="uploads/<?php echo htmlspecialchars($book['image']); ?>" 
                 alt="Book Image" class="img-fluid rounded">
        <?php else: ?>
            <div class="bg-light rounded p-5 text-center">
                <i class="fas fa-book fa-3x text-muted"></i>
                <p class="mt-2 text-muted">No image available</p>
            </div>
        <?php endif; ?>
    </div>
    <div class="col-md-8">
        <h4><?php echo htmlspecialchars($book['title']); ?></h4>
        <p><strong>Author:</strong> <?php echo htmlspecialchars($book['author']); ?></p>
        <p><strong>Genre:</strong> <?php echo htmlspecialchars($book['genre']); ?></p>
        <p><strong>Price:</strong> <span class="text-success">Rs. <?php echo number_format($book['price'], 2); ?></span></p>
        <p><strong>Condition:</strong> <?php echo ucfirst($book['condition_type']); ?></p>
        <p><strong>Quantity:</strong> <?php echo $book['quantity']; ?></p>
        <p><strong>Status:</strong> <span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span></p>
        <p><strong>Listed:</strong> <?php echo date('F d, Y g:i A', strtotime($book['created_at'])); ?></p>
        
        <?php if ($book['reviewed_at']): ?>
            <p><strong>Reviewed:</strong> <?php echo date('F d, Y g:i A', strtotime($book['reviewed_at'])); ?></p>
        <?php endif; ?>
        
        <?php if ($book['rejection_reason']): ?>
            <div class="alert alert-danger">
                <strong>Rejection Reason:</strong><br>
                <?php echo nl2br(htmlspecialchars($book['rejection_reason'])); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($book['description'])): ?>
            <div class="mt-3">
                <strong>Description:</strong>
                <div class="bg-light p-3 rounded mt-2">
                    <?php echo nl2br(htmlspecialchars($book['description'])); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>