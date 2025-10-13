<!-- Mobile Toggle Button -->
<button class="mobile-toggle" onclick="toggleSidebar()">
    ☰
</button>

<div class="sidebar">
    <div class="sidebar-header">
        <h4>📚 User Panel</h4>
    </div>
    
    <nav class="sidebar-nav">
        <a href="userdashboard.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'userdashboard.php' ? 'active' : ''; ?>">
            <span class="sidebar-icon">📊</span>
            <span>Dashboard</span>
        </a>
        
        <a href="index.php" class="sidebar-link">
            <span class="sidebar-icon">🏠</span>
            <span>Home</span>
        </a>
        
        <a href="add_book.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'add_book.php' ? 'active' : ''; ?>">
            <span class="sidebar-icon">➕</span>
            <span>Sell Books</span>
        </a>
        
        <a href="my_listed_books.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'my_listed_books.php' ? 'active' : ''; ?>">
            <span class="sidebar-icon">📖</span>
            <span>My Listed Books</span>
        </a>
        
        <a href="orders.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>">
            <span class="sidebar-icon">📦</span>
            <span>My Orders</span>
        </a>
        
        <a href="cart.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'cart.php' ? 'active' : ''; ?>">
            <span class="sidebar-icon">🛒</span>
            <span>Cart</span>
            <?php if (isset($cartCount) && $cartCount > 0): ?>
                <span class="sidebar-badge"><?php echo $cartCount; ?></span>
            <?php endif; ?>
        </a>
        
        <a href="profile.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
            <span class="sidebar-icon">👤</span>
            <span>Profile</span>
        </a>
        
        <div class="sidebar-divider"></div>
        
        <a href="logout.php" class="sidebar-link">
            <span class="sidebar-icon">🚪</span>
            <span>Logout</span>
        </a>
    </nav>
</div>

<style>
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 250px;
    height: 100vh;
    background: linear-gradient(180deg, #4a5568, #2d3748);
    color: white;
    z-index: 1000;
    overflow-y: auto;
}

.sidebar-header {
    padding: 2rem 1.5rem;
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    background: linear-gradient(135deg, #5D5CDE, #4a4bc7);
}

.sidebar-header h4 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 600;
}

.sidebar-nav {
    padding: 1rem 0;
}

.sidebar-link {
    display: flex;
    align-items: center;
    padding: 1rem 1.5rem;
    color: #cbd5e0;
    text-decoration: none;
    transition: all 0.3s ease;
    border: none;
    background: none;
    width: 100%;
}

.sidebar-link:hover {
    background: rgba(255,255,255,0.1);
    color: white;
    text-decoration: none;
}

.sidebar-link.active {
    background: linear-gradient(135deg, #5D5CDE, #4a4bc7);
    color: white;
    border-right: 3px solid white;
}

.sidebar-icon {
    width: 20px;
    margin-right: 1rem;
    font-size: 1.1rem;
    text-align: center;
}

.sidebar-link span:not(.sidebar-icon):not(.sidebar-badge) {
    font-weight: 500;
}

.sidebar-badge {
    background: #ff4757;
    color: white;
    font-size: 0.7rem;
    padding: 0.2rem 0.5rem;
    border-radius: 50px;
    margin-left: auto;
    font-weight: bold;
}

.sidebar-divider {
    height: 1px;
    background: rgba(255,255,255,0.1);
    margin: 1rem 1.5rem;
}

.main-content {
    margin-left: 250px;
    min-height: 100vh;
    background-color: #f8f9fa;
}

.content-header {
    background: white;
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #e2e8f0;
    margin-bottom: 2rem;
}

.content-header h1 {
    margin: 0;
    color: #2d3748;
    font-size: 2rem;
    font-weight: 700;
}

.content-body {
    padding: 0 2rem 2rem;
}

/* Responsive */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
    
    .main-content {
        margin-left: 0;
    }
    
    .mobile-toggle {
        display: block;
        position: fixed;
        top: 1rem;
        left: 1rem;
        z-index: 1001;
        background: #5D5CDE;
        color: white;
        border: none;
        padding: 0.5rem;
        border-radius: 5px;
        font-size: 1.2rem;
    }
}

@media (min-width: 769px) {
    .mobile-toggle {
        display: none;
    }
}
</style>

<script>
// Mobile sidebar toggle
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.classList.toggle('show');
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    if (window.innerWidth <= 768) {
        const sidebar = document.querySelector('.sidebar');
        const toggle = document.querySelector('.mobile-toggle');
        
        if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
            sidebar.classList.remove('show');
        }
    }
});
</script>