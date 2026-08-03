<?php
session_start();
include 'config.php';

if (!isset($_SESSION['userid'])) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset='UTF-8'>
<title>Tender Aproved</title>
<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css'>
</head>
<body>
<h1>Tender Aproved</h1>
<p>Halaman ini dibuat otomatis oleh sistem.</p>
</body>
</html>