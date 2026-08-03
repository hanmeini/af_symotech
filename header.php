<?php
// header.php
if (!isset($_SESSION)) session_start();
?>
<header style="position:fixed;top:0;left:0;right:0;padding:10px 20px;background:rgba(0,0,0,0.25);backdrop-filter: blur(4px);z-index:100;">
  <img src="logo.png" alt="logo" style="height:44px;vertical-align:middle">
  <span style="margin-left:12px;font-weight:bold;color:#fff">PT ARNI FAMILY — <?=htmlspecialchars($_SESSION['location'] ?? '')?></span>
  <a href="logout.php" style="float:right;color:#ffdddd;text-decoration:none">Logout</a>
</header>
<div style="height:72px"></div>
