<?php
// Role-aware navigation. Links only control what is shown; every page still
// enforces its own access check on the server.
$navUser = function_exists('currentUser') ? currentUser() : null;
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container">
    <a class="navbar-brand" href="<?= $navUser ? 'dashboard.php' : 'login.php' ?>">Peer Tutoring Hub</a>
    <div class="navbar-nav flex-row flex-wrap align-items-center gap-2">
      <?php if ($navUser): ?>
        <a class="nav-link" href="dashboard.php">Dashboard</a>
        <?php if ($navUser['role'] !== 'Tutor'): ?>
          <a class="nav-link" href="requests.php"><?= $navUser['role'] === 'Student' ? 'My Requests' : 'All Requests' ?></a>
        <?php endif; ?>
        <?php if ($navUser['role'] === 'Student'): ?>
          <a class="nav-link" href="request-form.php">Request a Session</a>
        <?php endif; ?>
        <?php if ($navUser['role'] === 'Administrator'): ?>
          <a class="nav-link" href="admin-users.php">Manage Users</a>
        <?php endif; ?>
        <a class="nav-link" href="profile.php">My Profile</a>
        <span class="navbar-text text-white-50 small ms-lg-3">
          <?= escapeHtml($navUser['full_name']) ?> (<?= escapeHtml($navUser['role']) ?>)
        </span>
        <form class="nav-logout" method="post" action="logout.php">
          <?= csrfField() ?>
          <button type="submit" class="btn btn-sm btn-outline-light">Log Out</button>
        </form>
      <?php else: ?>
        <a class="nav-link" href="login.php">Log In</a>
        <a class="nav-link" href="register.php">Register</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
