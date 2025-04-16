<?php
if (!isset($_SESSION)) {
  session_start();
}

// Check if user is admin
if (!isAdmin()) {
  redirect('auth/login.php');
}
?>

<div
  class="bg-gray-800 text-white w-64  fixed md:sticky top-0 py-7 px-2 transform -translate-x-full md:translate-x-0 transition duration-200 ease-in-out z-30"
  id="sidebar">
  <div class="flex flex-col items-center space-y-2">
    <div class="w-16 h-16 rounded-full bg-blue-600 flex items-center justify-center text-2xl font-bold">
      <i class="fas fa-user-shield"></i>
    </div>
    <div>
      <h2 class="text-xl font-bold"><?php echo $_SESSION['user_name']; ?></h2>
      <p class="text-gray-400 text-sm">Administrator</p>
    </div>
  </div>

  <nav class="mt-6">
    <a href="<?php echo BASE_URL; ?>/dashboard/admin/"
      class="block py-2.5 px-4 rounded transition duration-200 hover:bg-blue-700 hover:text-white <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'bg-blue-700' : ''; ?>">
      <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
    </a>
    <a href="<?php echo BASE_URL; ?>/dashboard/admin/manage-users.php"
      class="block py-2.5 px-4 rounded transition duration-200 hover:bg-blue-700 hover:text-white <?php echo basename($_SERVER['PHP_SELF']) == 'manage-users.php' ? 'bg-blue-700' : ''; ?>">
      <i class="fas fa-users mr-2"></i> Manage Users
    </a>
    <a href="<?php echo BASE_URL; ?>/dashboard/admin/manage-claims.php"
      class="block py-2.5 px-4 rounded transition duration-200 hover:bg-blue-700 hover:text-white <?php echo basename($_SERVER['PHP_SELF']) == 'manage-claims.php' ? 'bg-blue-700' : ''; ?>">
      <i class="fas fa-clipboard-check mr-2"></i> Manage Claims
    </a>
    <a href="<?php echo BASE_URL; ?>/dashboard/admin/manage-reports.php"
      class="block py-2.5 px-4 rounded transition duration-200 hover:bg-blue-700 hover:text-white <?php echo basename($_SERVER['PHP_SELF']) == 'manage-reports.php' ? 'bg-blue-700' : ''; ?>">
      <i class="fas fa-flag mr-2"></i> Manage Reports
    </a>
    <a href="<?php echo BASE_URL; ?>/dashboard/admin/matches.php"
      class="block py-2.5 px-4 rounded transition duration-200 hover:bg-blue-700 hover:text-white <?php echo basename($_SERVER['PHP_SELF']) == 'matches.php' ? 'bg-blue-700' : ''; ?>">
      <i class="fas fa-exchange-alt mr-2"></i> Matches
    </a>
    <a href="<?php echo BASE_URL; ?>/dashboard/admin/statistics.php"
      class="block py-2.5 px-4 rounded transition duration-200 hover:bg-blue-700 hover:text-white <?php echo basename($_SERVER['PHP_SELF']) == 'statistics.php' ? 'bg-blue-700' : ''; ?>">
      <i class="fas fa-chart-bar mr-2"></i> Statistics
    </a>
  </nav>

  <div class="mt-auto pt-8">
    <a href="<?php echo BASE_URL; ?>/"
      class="block py-2.5 px-4 rounded transition duration-200 hover:bg-blue-700 hover:text-white">
      <i class="fas fa-home mr-2"></i> Back to Site
    </a>
    <a href="<?php echo BASE_URL; ?>/auth/logout.php"
      class="block py-2.5 px-4 rounded transition duration-200 hover:bg-blue-700 hover:text-white">
      <i class="fas fa-sign-out-alt mr-2"></i> Logout
    </a>
  </div>

  <!-- Mobile toggle button -->
  <button class="md:hidden fixed top-4 left-4 bg-gray-800 p-2 rounded-md text-gray-400 z-40" id="sidebar-toggle">
    <i class="fas fa-bars"></i>
  </button>
</div>

<script>
  // Mobile sidebar toggle
  document.getElementById('sidebar-toggle').addEventListener('click', function () {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('-translate-x-full');
  });
</script>