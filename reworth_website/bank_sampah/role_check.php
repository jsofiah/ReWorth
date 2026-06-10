<?php
    session_start();

    if (!isset($_SESSION['role']) || $_SESSION['role'] != 'bank sampah') {
        header("Location: ../login.php");
        exit;
    }
?>