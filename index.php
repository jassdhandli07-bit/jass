<?php
session_start();
if(isset($_SESSION['user'])) { header("Location: dashboard.php"); exit(); }

$error = "";
if(isset($_POST['login'])) {
    $user = trim($_POST['username']);
    $pass = trim($_POST['password']);
    if($user === "admin" && $pass === "admin") {
        $_SESSION['user'] = $user;
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Portal - Login</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="login-page">
    <div class="login-box">
        <div class="login-logo">
            <i class="fas fa-hospital-alt"></i>
        </div>
        <h2>Hospital Portal</h2>
        <p class="login-subtitle">Sign in to your account</p>

        <?php if($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-icon-group">
                <i class="fas fa-user"></i>
                <input type="text" name="username" placeholder="Username" required autocomplete="username">
            </div>
            <div class="input-icon-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="Password" required autocomplete="current-password">
            </div>
            <button type="submit" name="login" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
        <p class="login-hint">Default: admin / admin</p>
    </div>
</body>
</html>
