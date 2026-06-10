<?php
    session_start();

    if (!isset($_SESSION['role']) || $_SESSION['role'] != 'dlh') {
        header("Location: ../login.php");
        exit;
    }
?>