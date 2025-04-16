<?php
require_once 'config.php';
require_once 'functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lost & Found Portal</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
    <header class="bg-blue-600 text-white shadow-md">
        <div class="container mx-auto px-4 py-3">
            <nav class="flex justify-between items-center">
                <a href="<?php echo BASE_URL; ?>" class="text-2xl font-bold">
                    <i class="fas fa-search-location mr-2"></i>Lost & Found
                </a>
                <div class="flex items-center space-x-4">
                    <?php if (isLoggedIn()): ?>
                        <?php if (isAdmin()): ?>
                            <a href="<?php echo BASE_URL; ?>/dashboard/admin/" class="hover:text-gray-200">
                                <i class="fas fa-tachometer-alt mr-1"></i> Admin Dashboard
                            </a>
                        <?php else: ?>
                            <a href="<?php echo BASE_URL; ?>/dashboard/user/" class="hover:text-gray-200">
                                <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo BASE_URL; ?>/auth/logout.php" class="hover:text-gray-200">
                            <i class="fas fa-sign-out-alt mr-1"></i> Logout
                        </a>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>/auth/login.php" class="hover:text-gray-200">
                            <i class="fas fa-sign-in-alt mr-1"></i> Login
                        </a>
                        <a href="<?php echo BASE_URL; ?>/auth/register.php" class="hover:text-gray-200">
                            <i class="fas fa-user-plus mr-1"></i> Register
                        </a>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
    </header>
    <main class="flex-grow">
        <div class="container mx-auto px-4 py-6">