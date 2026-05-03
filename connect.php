<?php
// connect.php - Database Connection
session_start();

$host = 'localhost';
$dbname = 'db_ligaraypacina';
$username = 'root';
$password = '';

$connection = new mysqli($host, $username, $password, $dbname);

if ($connection->connect_error) {
    die('<div style="padding:20px;background:#fee;color:#c00;font-family:sans-serif;">
        <strong>Database Connection Failed:</strong> ' . $connection->connect_error . '<br>
        Please make sure MySQL is running and the database <em>db_ligaraypacina</em> exists.<br>
        Import <strong>database.sql</strong> first.
    </div>');
}

$connection->set_charset("utf8mb4");

// Auth helper
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header('Location: login.php');
        exit;
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function currentUser() {
    return $_SESSION ?? [];
}
?>
