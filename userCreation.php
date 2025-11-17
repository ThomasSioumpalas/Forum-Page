<?php

require_once __DIR__ . '/functions/functions.php';
startSession();
$loggedIn  = isset($_SESSION['user_id']);
$username  = $_SESSION['loggedUserName'] ?? '';
$isAdmin   = ($_SESSION['loggedUserRole'] ?? '') === 'admin';



?>



<html>

<head>
    <meta charset="utf-8">
    <title>Login</title>

    <link rel="stylesheet" href="userCreation.css">
</head>

<body>
    <h2>Login</h2>
    <form action="/servers/userCreationServer.php" method="POST">
        <input type="hidden" name="action" value="login">

        <label> Username
            <input type="text" name="username" required>
        </label>
        <label> Password
            <input type="password" name="password" required>
        </label>
        <label>
            <input type="checkbox" name="remember" value="1"> Keep me logged in
        </label>
        <button type="submit">Login</button>
    </form>

    <hr>
    <h3>Register (quick)</h3>
    <form action="/servers/userCreationServer.php" method="POST">
        <input type="hidden" name="action" value="register">
        <label> Username
            <input type="text" name="username" required>
        </label>
        <label> Password
            <input type="password" name="password" required>
        </label>
        <label> Theme
            <select name="theme">
                <option value="IT">IT</option>
                <option value="Economics">Economics</option>
                <option value="Politics">Politics</option>
            </select>
        </label>
        <button type="submit">Register</button>
    </form>
</body>

</html>