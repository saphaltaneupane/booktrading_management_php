<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['book_id'])) {
    echo "Access denied";
    exit();
}

$book_id = intval($_GET['book_id']);
$user_id = $_SESSION['user_id'];

// Verify book belongs to user
$verify_query = "SELECT title FROM books WHERE id = ? AND added_by = ?";
$verify_stmt = mysqli_prepare($conn, $verify_query);
mysqli_stmt_bind_param($verify_stmt, "ii", $book_id, $user_id);
mysqli_stmt_execute($verify_stmt);
$verify_result = mysqli_stmt_get_result($verify_stmt);

if (mysqli_num_rows($verify_result) == 0) {
    echo "Book not found or access denied";
    exit();
}

$book = mysqli_fetch_assoc($verify_result);

// Get sales details
$sales_query = "SELECT o.id as order_id, o.created_at, o.total_amount, 
                       oi.quantity, oi.price, u.name as buyer_name, u.email as buyer_email
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                JOIN users u ON o.user_id = u.id
                WHERE oi.book_id = ? AND o.payment_status = 'completed'
                ORDER BY o.created_at DESC";

$sales_stmt = mysqli_prepare($conn, $sales_query);
mysqli_stmt_bind_param($sales_stmt, "i", $book_id);
mysqli_stmt_execute($sales_stmt);
$sales_result = mysqli_stmt_get_result($sales_stmt);

$total_sold = 0;
$total_earnings = 0;
?>

<div class="container-fluid">
    <h5 class="mb-3">Sales Details for "<?php echo htmlspecialchars($book['title']); ?>"</h5>
    
    <?php if (mysqli_num_rows($sales_result) > 0): ?>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead class="table-light">
                    <tr>
                        <th>Order ID</th>
                        <th>Buyer</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Total</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($sale = mysqli_fetch_assoc($sales_result)): ?>
                        <?php 
                        $total_sold += $sale['quantity'];
                        $sale_total = $sale['quantity'] * $sale['price'];
                        $total_earnings += $sale_total;
                        ?>
                        <tr>
                            <td>#<?php echo $sale['order_id']; ?></td>
                            <td>
                                <?php echo htmlspecialchars($sale['buyer_name']); ?><br>
                                <small class="text-muted"><?php echo htmlspecialchars($sale['buyer_email']); ?></small>
                            </td>
                            <td><?php echo $sale['quantity']; ?></td>
                            <td>Rs. <?php echo number_format($sale['price'], 2); ?></td>
                            <td class="text-success">Rs. <?php echo number_format($sale_total, 2); ?></td>
                            <td>
                                <small><?php echo date('M d, Y g:i A', strtotime($sale['created_at'])); ?></small>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <div class="row mt-3">
            <div class="col-md-6">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h4><?php echo $total_sold; ?></h4>
                        <small>Total Books Sold</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h4>Rs. <?php echo number_format($total_earnings, 2); ?></h4>
                        <small>Total Earnings</small>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="text-center py-4">
            <i class="fas fa-chart-line fa-2x text-muted mb-3"></i>
            <p class="text-muted">No sales recorded yet for this book.</p>
        </div>
    <?php endif; ?>
</div>