<?php
require_once 'db.php';
require_once 'auth.php';

// If already logged in, go to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Validation
    if ($username === '') {
        $error = 'Username is required.';
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $error = 'Username must be between 3 and 50 characters.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username can only contain letters, numbers and underscores.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = 'Username already taken. Choose a different one.';
        } else {
            // Create user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'user')");
            $stmt->execute([$username, $hashedPassword]);
            header("Location: login.php?msg=Registration successful! Please login.");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Task Tracker</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f4f4f4; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .register-box { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        .register-box h1 { text-align: center; margin-bottom: 5px; color: #333; }
        .register-box p.subtitle { text-align: center; color: #777; margin-bottom: 20px; font-size: 14px; }
        .register-box label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px; }
        .register-box input[type="text"],
        .register-box input[type="password"] { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
        .register-box button { width: 100%; padding: 10px; background: #28a745; color: #fff; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; }
        .register-box button:hover { background: #1e7e34; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 14px; }
        .links { text-align: center; margin-top: 15px; font-size: 14px; }
        .links a { color: #007bff; text-decoration: none; }
    </style>
</head>
<body>
<div class="register-box">
    <h1>📋 Task Tracker</h1>
    <p class="subtitle">Create a new account</p>

    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="register.php">
        <label>Username</label>
        <input type="text" name="username" placeholder="Choose a username" value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
        <label>Password</label>
        <input type="password" name="password" placeholder="At least 6 characters" required>
        <label>Confirm Password</label>
        <input type="password" name="confirm_password" placeholder="Re-enter password" required>
        <button type="submit">Register</button>
    </form>
    <div class="links">
        Already have an account? <a href="login.php">Login here</a>
    </div>
</div>
</body>
</html>
