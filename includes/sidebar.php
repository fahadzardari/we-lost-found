<?php
if (!isset($_SESSION)) {
  session_start();
}

// Check if user is admin
if (!isAdmin()) {
  redirect('auth/login.php');
}
?>

<!-- Mobile toggle button -->
<button class="fixed top-4 right-4 bg-gray-800 p-2 rounded-md text-white z-50" id="sidebar-toggle">
  <i class="fas fa-bars"></i>
</button>

<!-- Main layout container -->
<div class="flex h-full">
  <!-- Sidebar -->
  <aside
    class="bg-gray-800 text-white w-64 fixed inset-y-0 left-0 transform -translate-x-full transition-transform duration-200 ease-in-out z-30 overflow-y-auto"
    id="sidebar">
    <div class="flex flex-col h-full">
      <!-- Profile section -->
      <div class="flex flex-col items-center space-y-2 py-6">
        <div class="w-16 h-16 rounded-full bg-blue-600 flex items-center justify-center text-2xl font-bold">
          <i class="fas fa-user-shield"></i>
        </div>
        <div class="text-center">
          <h2 class="text-xl font-bold"><?php echo $_SESSION['user_name']; ?></h2>
          <p class="text-gray-400 text-sm">Administrator</p>
        </div>
      </div>

      <!-- Navigation -->
      <nav class="px-4 flex-grow">
        <a href="<?php echo BASE_URL; ?>/dashboard/admin/"
          class="block py-2.5 px-4 rounded transition duration-200 hover:bg-blue-700 hover:text-white mb-1 <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'bg-blue-700' : ''; ?>">
          <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
        </a>
        <a href="<?php echo BASE_URL; ?>/dashboard/admin/manage-users.php"
          class="block py-2.5 px-4 rounded transition duration-200 hover:bg-blue-700 hover:text-white mb-1 <?php echo basename($_SERVER['PHP_SELF']) == 'manage-users.php' ? 'bg-blue-700' : ''; ?>">
          <i class="fas fa-users mr-2"></i> Manage Users
        </a>
        <a href="<?php echo BASE_URL; ?>/dashboard/admin/manage-claims.php"
          class="block py-2.5 px-4 rounded transition duration-200 hover:bg-blue-700 hover:text-white mb-1 <?php echo basename($_SERVER['PHP_SELF']) == 'manage-claims.php' ? 'bg-blue-700' : ''; ?>">
          <i class="fas fa-clipboard-check mr-2"></i> Manage Claims
        </a>
        <a href="<?php echo BASE_URL; ?>/dashboard/admin/manage-reports.php"
          class="block py-2.5 px-4 rounded transition duration-200 hover:bg-blue-700 hover:text-white mb-1 <?php echo basename($_SERVER['PHP_SELF']) == 'manage-reports.php' ? 'bg-blue-700' : ''; ?>">
          <i class="fas fa-flag mr-2"></i> Manage Reports
        </a>
      </nav>

      <!-- Footer links -->
      <div class="mt-auto p-4 border-t border-gray-700">
        <a href="<?php echo BASE_URL; ?>/"
          class="block py-2.5 px-4 rounded transition duration-200 hover:bg-blue-700 hover:text-white mb-1">
          <i class="fas fa-home mr-2"></i> Back to Site
        </a>
        <a href="<?php echo BASE_URL; ?>/auth/logout.php"
          class="block py-2.5 px-4 rounded transition duration-200 hover:bg-blue-700 hover:text-white">
          <i class="fas fa-sign-out-alt mr-2"></i> Logout
        </a>
      </div>
    </div>
  </aside>

  <!-- Main content -->
  <main class="w-full transition-all duration-200 ease-in-out" id="main-content">
    <div class="p-4 md:p-6">
      <!-- Content will be inserted here in each admin page -->
    </div>
  </main>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const mainContent = document.getElementById('main-content');
    const isDesktop = () => window.innerWidth >= 768; // md breakpoint

    // Function to update sidebar state
    function updateSidebarState(collapse) {
      if (collapse) {
        sidebar.classList.add('-translate-x-full');
        sidebar.classList.remove('md:translate-x-0'); // Ensure desktop visibility class is removed
        mainContent.classList.remove('md:ml-64');
        mainContent.classList.add('md:ml-0');
        localStorage.setItem('admin-sidebar-collapsed', 'true');
      } else {
        sidebar.classList.remove('-translate-x-full');
        if (isDesktop()) {
            sidebar.classList.add('md:translate-x-0'); // Add desktop visibility class only if on desktop
        }
        mainContent.classList.add('md:ml-64');
        mainContent.classList.remove('md:ml-0');
        localStorage.setItem('admin-sidebar-collapsed', 'false');
      }
    }

    // Initialize sidebar state based on localStorage and screen size
    function initializeSidebar() {
        const shouldBeCollapsed = localStorage.getItem('admin-sidebar-collapsed') === 'true';
        if (!isDesktop()) {
            // Always start collapsed on mobile
            updateSidebarState(true);
        } else {
            // Respect localStorage on desktop
            updateSidebarState(shouldBeCollapsed);
        }
    }

    initializeSidebar(); // Call initialization function

    // Toggle sidebar on button click
    sidebarToggle.addEventListener('click', function() {
      // Determine the *new* desired state (if currently open, new state is collapsed)
      const shouldCollapse = !sidebar.classList.contains('-translate-x-full');
      updateSidebarState(shouldCollapse);
    });

    // Close sidebar when clicking navigation items on mobile
    if (!isDesktop()) {
      const navLinks = sidebar.querySelectorAll('a');
      navLinks.forEach(link => {
        link.addEventListener('click', function() {
          if (!isDesktop()) {
            updateSidebarState(true); // Collapse sidebar
          }
        });
      });
    }

    // Close sidebar when clicking outside of it on mobile
    document.addEventListener('click', function(event) {
      if (!isDesktop() &&
          !sidebar.contains(event.target) &&
          event.target !== sidebarToggle &&
          !sidebarToggle.contains(event.target)) {
        updateSidebarState(true); // Collapse sidebar
      }
    });

    // Update layout on window resize
    window.addEventListener('resize', function() {
        initializeSidebar(); // Re-initialize based on new screen size and stored preference
    });
  });
</script>