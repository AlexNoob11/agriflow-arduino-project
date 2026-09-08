<div class="mobile-nav">
    <a href="#" class="nav-logo">AGRIFLOW</a>
    <button id="menuBtn">☰</button>
</div>

<nav class="sidebar" id="sidebar">
    <div>
        <a href="dashboard.php" class="nav-logo"> AGRIFLOW </a>
        
        <!-- NEW: Notification Toast (Hidden by default) -->
        <div id="drySoilAlert" style="display: none; background: #ffebee; border-left: 4px solid #f44336; padding: 12px; margin: 10px; border-radius: 8px; animation: pulse 2s infinite;">
            <strong style="color: #c62828; font-size: 0.8rem; display: block;">⚠️ CRITICAL ALERT</strong>
            <span style="color: #555; font-size: 0.75rem;">Soil is extremely dry! System check required.</span>
        </div>

        <ul class="nav-links">
            <li><a href="dashboard.php" class="active"><span>📊</span> Dashboard</a></li>
            <li>
                <a href="sensor_monitoring.php">
                    <span>📡</span> Sensor Monitoring
                    <!-- NEW: Mini indicator dot -->
                    <span id="statusDot" style="height: 8px; width: 8px; background: #4caf50; border-radius: 50%; display: inline-block; margin-left: 10px;"></span>
                </a>
            </li>
            <li><a href="threshold_settings.php"><span>⚙️</span> Threshold Settings</a></li>
            <li><a href="motor_control.php"><span>⚡</span> Motor Control</a></li>
            <li><a href="pump_logs.php"><span>📋</span> System Logs</a></li>
            <li><a href="data_visualtion.php"><span>📈</span> Data Visualization</a></li>
            <li><a href="profile.php"><span>👤</span> Profile Settings</a></li>
            <li><a href="system_settings.php"><span>🛠️</span> System Settings</a></li>
        </ul>
    </div>
    <a href="Index.php" class="logout-btn" onclick="return confirmLogout(event)">🚪 Log Out</a>
</nav>

<style>
    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(244, 67, 54, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(244, 67, 54, 0); }
        100% { box-shadow: 0 0 0 0 rgba(244, 67, 54, 0); }
    }
</style>

<script>
    const menuBtn = document.getElementById('menuBtn');
    const sidebar = document.getElementById('sidebar');
    const dryAlert = document.getElementById('drySoilAlert');
    const statusDot = document.getElementById('statusDot');
    
    menuBtn.addEventListener('click', () => {
        sidebar.classList.toggle('active');
    });

    function confirmLogout(e) {
        if (!confirm("Are you sure you want to log out from the Agriflow Cloud?")) {
            e.preventDefault();
            return false;
        }
    }

    // --- NEW: Real-time Notification Logic ---
    function checkSoilStatus() {
        // Fetching the latest moisture from your existing monitoring data
        fetch('get_latest_sensor.php') 
            .then(response => response.json())
            .then(data => {
                const moisture = parseFloat(data.moisture);
                
                // If moisture is below 30% (Adjust this based on your needs)
                if (moisture < 30) {
                    dryAlert.style.display = 'block';
                    statusDot.style.backgroundColor = '#f44336'; // Change dot to red
                } else {
                    dryAlert.style.display = 'none';
                    statusDot.style.backgroundColor = '#4caf50'; // Back to green
                }
            })
            .catch(err => console.log("Notification Error:", err));
    }

    // Run check every 10 seconds
    setInterval(checkSoilStatus, 10000);
    checkSoilStatus(); // Run once on load

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 992 && !sidebar.contains(e.target) && !menuBtn.contains(e.target)) {
            sidebar.classList.remove('active');
        }
    });
</script>