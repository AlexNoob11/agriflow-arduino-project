<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agriflow | IoT Crop-Driven Hydration</title>
    <style>
        :root {
            --bg: #f8faf8;
            --text: #1a1c1a;
            --secondary: #5c635c;
            --accent: #2e7d32;
            --admin-accent: #1565c0; /* Blue for Admin distinction */
            --white: #ffffff;
            --border: #e0e6e0;
        }

        * { box-sizing: border-box; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }

        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        nav {
            padding: 1.5rem 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid var(--border);
        }

        .logo {
            font-weight: 800;
            font-size: 1.3rem;
            letter-spacing: -1px;
            color: var(--accent);
            text-decoration: none;
        }

        main {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 120px 24px 60px;
        }

        .status-pill {
            background: #e8f5e9;
            color: var(--accent);
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dot { height: 8px; width: 8px; background-color: var(--accent); border-radius: 50%; display: inline-block; animation: pulse 2s infinite; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.3; } 100% { opacity: 1; } }

        h1 {
            font-size: clamp(2.5rem, 6vw, 4.5rem);
            line-height: 1.05;
            margin: 0 0 1.5rem 0;
            max-width: 900px;
            letter-spacing: -2px;
        }

        p {
            font-size: 1.25rem;
            color: var(--secondary);
            max-width: 600px;
            margin-bottom: 3.5rem;
            line-height: 1.6;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: none;
        }

        .btn-nav-login { color: var(--text); background: transparent; }
        .btn-nav-register { background: var(--text); color: white; }
        .btn-dashboard {
            background: var(--accent);
            color: white;
            padding: 18px 48px;
            font-size: 1.1rem;
            box-shadow: 0 10px 20px rgba(46, 125, 50, 0.2);
        }

        .overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.4);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            backdrop-filter: blur(6px);
        }

        .auth-card {
            background: white;
            padding: 40px;
            border-radius: 24px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.12);
            position: relative;
        }

        .role-selector {
            display: flex;
            background: #f0f2f0;
            padding: 4px;
            border-radius: 12px;
            margin-bottom: 24px;
        }

        .role-btn {
            flex: 1;
            padding: 10px;
            border: none;
            background: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            color: var(--secondary);
        }

        .role-btn.active {
            background: white;
            color: var(--text);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .input-group { margin-bottom: 20px; text-align: left; }
        .input-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 8px; color: var(--secondary); }
        input {
            width: 100%;
            padding: 14px;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 1rem;
        }
        input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 4px rgba(46, 125, 50, 0.1); }

        .submit-btn {
            width: 100%;
            background: var(--accent);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
        }

        .admin-mode .submit-btn { background: var(--admin-accent); }
        .admin-mode input:focus { border-color: var(--admin-accent); box-shadow: 0 0 0 4px rgba(21, 101, 192, 0.1); }

        .close-btn { position: absolute; top: 20px; right: 20px; background: none; border: none; color: #ccc; cursor: pointer; font-size: 1.5rem; }
        
        footer { padding: 40px; text-align: center; color: var(--secondary); font-size: 0.85rem; border-top: 1px solid var(--border); }
    </style>
</head>
<body>

    <nav>
        <a href="#" class="logo">AGRIFLOW.</a>
        <div style="display: flex; gap: 8px;">
            <button class="btn btn-nav-login" onclick="openAuth('login')">Log In</button>
            <button class="btn btn-nav-register" onclick="openAuth('register')">Register</button>
        </div>
    </nav>

    <main>
        <div class="status-pill">
            <span class="dot"></span> System Ready: Multi-User Cloud Access
        </div>
        <h1>Effortless Hydration.<br>Driven by Data.</h1>
        <p>Advanced IoT monitoring for both farm owners and field operators. Precise NPK and moisture control at your fingertips.</p>
        <button class="btn btn-dashboard" onclick="openAuth('login')">Access Dashboard</button>
    </main>

    <!-- Auth Modal -->
    <div class="overlay" id="authOverlay">
        <div class="auth-card" id="authCard">
            <button class="close-btn" onclick="closeAuth()">&times;</button>
            
            <h2 id="authTitle">Welcome Back</h2>
            <p id="authSubtitle" style="color: var(--secondary); margin-bottom: 20px;">Choose your account type.</p>

            <!-- Role Toggle -->
            <div class="role-selector">
                <button type="button" class="role-btn active" id="userTab" onclick="setRole('user')">User / Farmer</button>
                <button type="button" class="role-btn" id="adminTab" onclick="setRole('admin')">Administrator</button>
            </div>
            
            <form action="auth.php" method="POST">
                <!-- Hidden inputs for logic -->
                <input type="hidden" name="action" id="authAction" value="login">
                <input type="hidden" name="role" id="authRole" value="user">
                
                <div class="input-group" id="nameField" style="display: none;">
                    <label>Full Name</label>
                    <input type="text" name="fullname" placeholder="John Doe">
                </div>
                
                <div class="input-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="name@domain.com" required>
                </div>
                
                <div class="input-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>
                
                <button type="submit" class="submit-btn" id="submitBtn">Sign In</button>
            </form>
        </div>
    </div>

    <footer>
        &copy; 2026 Agriflow IoT Ecosystem. Secure Multi-Role Portal.
    </footer>

    <script>
        const authOverlay = document.getElementById('authOverlay');
        const authCard = document.getElementById('authCard');
        const authTitle = document.getElementById('authTitle');
        const authSubtitle = document.getElementById('authSubtitle');
        const nameField = document.getElementById('nameField');
        const submitBtn = document.getElementById('submitBtn');
        const authAction = document.getElementById('authAction');
        const authRole = document.getElementById('authRole');
        
        const userTab = document.getElementById('userTab');
        const adminTab = document.getElementById('adminTab');

        function openAuth(mode) {
            authOverlay.style.display = 'flex';
            authAction.value = mode;
            
            if (mode === 'register') {
                authTitle.innerText = "Create Account";
                authSubtitle.innerText = "Join the Agriflow network.";
                nameField.style.display = 'block';
                submitBtn.innerText = "Create Account";
            } else {
                authTitle.innerText = "Welcome Back";
                authSubtitle.innerText = "Access your agricultural insights.";
                nameField.style.display = 'none';
                submitBtn.innerText = "Sign In";
            }
            
            setRole('user');
        }

        function setRole(role) {
            authRole.value = role;
            if (role === 'admin') {
                adminTab.classList.add('active');
                userTab.classList.remove('active');
                authCard.classList.add('admin-mode');
                authSubtitle.innerText = "Administrator Cloud Access Only.";
            } else {
                userTab.classList.add('active');
                adminTab.classList.remove('active');
                authCard.classList.remove('admin-mode');
                authSubtitle.innerText = "Standard operator dashboard access.";
            }
        }

        function closeAuth() {
            authOverlay.style.display = 'none';
        }

        // Close on outside click
        window.onclick = (e) => { if (e.target == authOverlay) closeAuth(); }
    </script>
</body>
</html>
