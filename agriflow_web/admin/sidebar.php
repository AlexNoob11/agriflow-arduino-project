<div class="mobile-nav">
    <a href="#" class="nav-logo">AGRIFLOW</a>
    <button id="menuBtn">☰</button>
</div>

<nav class="sidebar" id="sidebar">
    <div>
        <a href="#" class="nav-logo">AGRIFLOW ADMIN</a>
        <ul class="nav-links">
            <li><a href="admin-dashboard.php" class="active"><span>📊</span> Dashboard</a></li>
            <li><a href="manage-users.php"><span>👥</span> User Management</a></li>
            <li><a href="threshold-settings.php"><span>⚙️</span> Threshold Settings</a></li>
            <li><a href="data-visualization.php"><span>📈</span> Data Visualization</a></li>
            <li><a href="profile.php"><span>👤</span> Profile Settings</a></li>
            <li><a href="system-settings.php"><span>🛠️</span> System Settings</a></li>
        </ul>
    </div>
    <a href="../index.php" class="logout-btn" onclick="return confirmLogout(event)">🚪 Log Out</a>
</nav>

<script>
    const menuBtn = document.getElementById('menuBtn');
    const sidebar = document.getElementById('sidebar');
    
    menuBtn.addEventListener('click', () => {
        sidebar.classList.toggle('active');
    });
    function confirmLogout(e) {
        if (!confirm("Are you sure you want to log out from the Agriflow Cloud?")) {
            e.preventDefault(); // Stops the link from opening
            return false;
        }
    }

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 992 && !sidebar.contains(e.target) && !menuBtn.contains(e.target)) {
            sidebar.classList.remove('active');
        }
    });
</script>