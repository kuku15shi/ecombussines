<?php
session_start();
unset($_SESSION['affiliate_id'], $_SESSION['affiliate_name']);
header('Location: login.php');
exit;
