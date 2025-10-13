<?php
// Get current page name to set active navigation
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Sidebar -->
<div class="col-md-2 admin-sidebar p-0">
    <div class="p-3">
        <h4 class="text-white">📚 Admin Panel</h4>
    </div>
    <nav class="nav flex-column">
        <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">Dashboard</a>
        <a class="nav-link <?php echo ($current_page == 'add_book.php') ? 'active' : ''; ?>" href="add_book.php">Add Book</a>
        <a class="nav-link <?php echo ($current_page == 'manage_books.php') ? 'active' : ''; ?>" href="manage_books.php">Manage Books</a>
        <a class="nav-link <?php echo ($current_page == 'manage_categories.php') ? 'active' : ''; ?>" href="manage_categories.php">Manage Categories</a>
        <a class="nav-link <?php echo ($current_page == 'manage_users.php') ? 'active' : ''; ?>" href="manage_users.php">Manage Users</a>
        <a class="nav-link <?php echo ($current_page == 'manage_orders.php') ? 'active' : ''; ?>" href="manage_orders.php">Manage Orders</a>
        <a class="nav-link <?php echo ($current_page == 'sales_report.php') ? 'active' : ''; ?>" href="sales_report.php">Sales Report</a>
        <a class="nav-link" href="logout.php">Logout</a>
    </nav>
</div>