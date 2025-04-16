<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Destroy all session data
session_start();
session_unset();
session_destroy();

// Redirect to home page
redirect('index.php');
?>