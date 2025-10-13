<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// Get user's listed books with sales information
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Count total books
$count_query = "SELECT COUNT(*) as total FROM books WHERE added_by = ?";
$count_stmt = mysqli_prepare($conn, $count_query);
mysqli_stmt_bind_param($count_stmt, "i", $user_id);
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_books = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_books / $limit);

// FIXED: Updated status logic - check sales first, then availability
$query = "SELECT b.*, 
                 COALESCE(SUM(oi.quantity), 0) as total_sold,
                 COALESCE(SUM(oi.quantity * oi.price), 0) as total_earnings,
                 CASE 
                     WHEN COALESCE(SUM(oi.quantity), 0) >= b.quantity THEN 'sold_out'
                     WHEN COALESCE(SUM(oi.quantity), 0) > 0 THEN 'partially_sold'
                     WHEN b.is_available = 1 THEN 'available'
                     WHEN b.is_available = 0 THEN 'pending_review'
                     WHEN b.is_available = -1 THEN 'rejected'
                     ELSE 'unknown'
                 END as book_status,
                 (b.quantity - COALESCE(SUM(oi.quantity), 0)) as remaining_quantity
          FROM books b
          LEFT JOIN order_items oi ON b.id = oi.book_id
          LEFT JOIN orders o ON oi.order_id = o.id AND o.payment_status = 'completed'
          WHERE b.added_by = ?
          GROUP BY b.id
          ORDER BY b.created_at DESC
          LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "iii", $user_id, $limit, $offset);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Get summary statistics
$stats_query = "SELECT 
                    COUNT(*) as total_books,
                    SUM(CASE WHEN b.is_available = 0 AND (SELECT COALESCE(SUM(oi2.quantity), 0) FROM order_items oi2 JOIN orders o2 ON oi2.order_id = o2.id WHERE oi2.book_id = b.id AND o2.payment_status = 'completed') < b.quantity THEN 1 ELSE 0 END) as pending_books,
                    SUM(CASE WHEN b.is_available = 1 AND (SELECT COALESCE(SUM(oi2.quantity), 0) FROM order_items oi2 JOIN orders o2 ON oi2.order_id = o2.id WHERE oi2.book_id = b.id AND o2.payment_status = 'completed') < b.quantity THEN 1 ELSE 0 END) as approved_books,
                    SUM(CASE WHEN b.is_available = -1 THEN 1 ELSE 0 END) as rejected_books,
                    COALESCE(SUM(oi.quantity), 0) as total_sold,
                    COALESCE(SUM(oi.quantity * oi.price), 0) as total_earnings
                FROM books b
                LEFT JOIN order_items oi ON b.id = oi.book_id
                LEFT JOIN orders o ON oi.order_id = o.id AND o.payment_status = 'completed'
                WHERE b.added_by = ?";

$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "i", $user_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Listed Books - BookTrading</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }

        .page-header {
            background: linear-gradient(135deg, #5D5CDE 0%, #7C4DFF 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(93, 92, 222, 0.3);
        }

        .page-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 600;
        }

        .page-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }

        .stats-container {
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            height: 100px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .stat-card h3 {
            margin: 0 0 5px 0;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .stat-card small {
            color: #6c757d;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .earnings-highlight {
            background: #5D5CDE;
            color: white;
        }

        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .table {
            margin: 0;
        }

        .table thead th {
            background-color: #5D5CDE;
            color: white;
            border: none;
            font-weight: 600;
            padding: 15px;
            text-transform: uppercase;
            font-size: 0.9rem;
        }

        .table tbody td {
            padding: 15px;
            vertical-align: middle;
            border-color: #e9ecef;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .book-image {
            width: 65px;
            height: 85px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending { 
            background-color: #fff3cd; 
            color: #856404; 
        }
        .status-available { 
            background-color: #d4edda; 
            color: #155724; 
        }
        .status-sold-out { 
            background-color: #6c757d; 
            color: white; 
        }
        .status-partially-sold { 
            background-color: #d1ecf1; 
            color: #0c5460; 
        }
        .status-rejected { 
            background-color: #f8d7da; 
            color: #721c24; 
        }

        .btn-primary {
            background-color: #5D5CDE;
            border-color: #5D5CDE;
            padding: 10px 25px;
            border-radius: 8px;
        }

        .btn-primary:hover {
            background-color: #4a4bc7;
            border-color: #4a4bc7;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .empty-state h5 {
            color: #6c757d;
            margin-bottom: 15px;
        }

        .empty-state p {
            color: #6c757d;
            margin-bottom: 25px;
        }

        .pagination .page-link {
            border: 1px solid #dee2e6;
            color: #5D5CDE;
            margin: 0 2px;
            border-radius: 5px;
        }

        .pagination .page-item.active .page-link {
            background-color: #5D5CDE;
            border-color: #5D5CDE;
        }

        .pagination .page-link:hover {
            background-color: #f8f9fa;
            color: #5D5CDE;
        }

        .text-primary { color: #5D5CDE !important; }
        .text-warning { color: #ffc107 !important; }
        .text-success { color: #198754 !important; }
        .text-info { color: #0dcaf0 !important; }
        .text-danger { color: #dc3545 !important; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'includes/user_sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>📚 My Listed Books</h1>
            <p>Track your books and sales performance</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-container">
            <div class="row g-3">
                <div class="col-md-2">
                    <div class="stat-card">
                        <div class="h3 text-primary"><?php echo $stats['total_books']; ?></div>
                        <small>Total Listed</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-card">
                        <div class="h3 text-warning"><?php echo $stats['pending_books']; ?></div>
                        <small>Pending Review</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-card">
                        <div class="h3 text-success"><?php echo $stats['approved_books']; ?></div>
                        <small>Live & Available</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-card">
                        <div class="h3 text-info"><?php echo $stats['total_sold']; ?></div>
                        <small>Books Sold</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-card">
                        <div class="h3 text-danger"><?php echo $stats['rejected_books']; ?></div>
                        <small>Rejected</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-card earnings-highlight">
                        <div class="h3">Rs. <?php echo number_format($stats['total_earnings'], 2); ?></div>
                        <small>Total Earnings</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Books Table -->
        <?php if (mysqli_num_rows($result) > 0): ?>
            <div class="table-container">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Book</th>
                                <th>Details</th>
                                <th>Status</th>
                                <th>Sales</th>
                                <th>Stock</th>
                                <th>Earnings</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($book = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($book['image'] && file_exists('uploads/' . $book['image'])): ?>
                                                <img src="uploads/<?php echo htmlspecialchars($book['image']); ?>" 
                                                     alt="Book Image" class="book-image me-3">
                                            <?php else: ?>
                                                <div class="book-image bg-light d-flex align-items-center justify-content-center me-3">
                                                    <span style="font-size: 1.5rem;">📖</span>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <strong><?php echo htmlspecialchars($book['title']); ?></strong>
                                                <br>
                                                <small class="text-muted">by <?php echo htmlspecialchars($book['author']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <span class="badge bg-light text-dark"><?php echo htmlspecialchars($book['genre']); ?></span><br>
                                            <strong class="text-success">Rs. <?php echo number_format($book['price'], 2); ?></strong><br>
                                            <span class="text-muted"><?php echo ucfirst($book['condition_type']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        $status_text = '';
                                        $status_icon = '';
                                        
                                        switch($book['book_status']) {
                                            case 'pending_review':
                                                $status_class = 'status-pending';
                                                $status_text = 'Pending Review';
                                                $status_icon = '⏳';
                                                break;
                                            case 'available':
                                                $status_class = 'status-available';
                                                $status_text = 'Live & Available';
                                                $status_icon = '✅';
                                                break;
                                            case 'partially_sold':
                                                $status_class = 'status-partially-sold';
                                                $status_text = 'Partially Sold';
                                                $status_icon = '📊';
                                                break;
                                            case 'sold_out':
                                                $status_class = 'status-sold-out';
                                                $status_text = 'Sold Out';
                                                $status_icon = '🎉';
                                                break;
                                            case 'rejected':
                                                $status_class = 'status-rejected';
                                                $status_text = 'Rejected';
                                                $status_icon = '❌';
                                                break;
                                            default:
                                                $status_class = 'bg-secondary';
                                                $status_text = 'Unknown';
                                                $status_icon = '❓';
                                        }
                                        ?>
                                        <span class="badge status-badge <?php echo $status_class; ?>">
                                            <?php echo $status_icon; ?> <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($book['total_sold'] > 0): ?>
                                            <span class="text-success fw-bold"><?php echo $book['total_sold']; ?> sold</span>
                                        <?php else: ?>
                                            <span class="text-muted">No sales yet</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="<?php echo $book['remaining_quantity'] > 0 ? 'text-info' : 'text-danger'; ?>">
                                            <?php echo max(0, $book['remaining_quantity']); ?> / <?php echo $book['quantity']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($book['total_earnings'] > 0): ?>
                                            <span class="text-success fw-bold">Rs. <?php echo number_format($book['total_earnings'], 2); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Rs. 0.00</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Books pagination" class="mt-4 p-3">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo ($page - 1); ?>">Previous</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo ($page + 1); ?>">Next</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
            
        <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 4rem; margin-bottom: 20px;">📚</div>
                <h5>No books listed yet</h5>
                <p>Start selling your old books and track your earnings!</p>
                <a href="add_book.php" class="btn btn-primary">
                    ➕ List Your First Book
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>