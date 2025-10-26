<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - MCP Server</title>
    <link rel="stylesheet" href="/assets/css/login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🔐 Admin Panel</h1>
            <p>MCP PHP Server Management</p>
        </div>

        <div class="default-info">
            <strong>Domyślne dane:</strong><br>
            Login: admin<br>
            Hasło: admin123
        </div>

        <div class="error" id="error"></div>
        <div class="success" id="success"></div>

        <form id="loginForm">
            <div class="form-group">
                <label for="username">Użytkownik:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Hasło:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" id="loginBtn">Zaloguj się</button>
            <div class="loading" id="loading">Logowanie...</div>
        </form>

        <div class="footer">
            <p>Secure Admin Access Required</p>
        </div>
    </div>

    <script src="/assets/js/login.js"></script>
</body>
</html>