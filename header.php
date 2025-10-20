<?php
// Proveri da li je korisnik ulogovan (samo ako sesija već nije pokrenuta)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) !== 'login.php' && basename($_SERVER['PHP_SELF']) !== 'register.php') {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="sr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>TransportApp</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.css">
  <style>
    :root {
      --primary-color: #10a37f;
      --secondary-color: #f7f7f8;
      --text-primary: #2d3748;
      --text-secondary: #6b7280;
      --border-color: #e5e7eb;
      --hover-bg: #f8f9fa;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background-color: #ffffff;
      color: var(--text-primary);
      line-height: 1.6;
    }

    .navbar {
      background: white !important;
      border-bottom: 1px solid var(--border-color);
      padding: 0.5rem 0;
      box-shadow: none;
    }
    
    .navbar-nav {
      flex-wrap: nowrap;
    }
    
    .navbar-collapse {
      justify-content: space-between;
    }

    .navbar-brand {
      font-weight: 600;
      font-size: 1.25rem;
      color: var(--text-primary) !important;
      text-decoration: none;
      display: flex;
      align-items: center;
    }

    .navbar-brand i {
      color: var(--primary-color);
      margin-right: 0.5rem;
    }

    .navbar-toggler {
      border: none;
      padding: 0.25rem 0.5rem;
      background: none;
    }

    .navbar-toggler:focus {
      box-shadow: none;
    }

    .navbar-toggler-icon {
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%2845, 55, 72, 0.75%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
    }

    .nav-link {
      color: var(--text-secondary) !important;
      font-weight: 500;
      padding: 0.5rem 0.5rem !important;
      margin: 0;
      border-radius: 0.375rem;
      transition: all 0.2s ease;
      text-decoration: none;
      font-size: 0.8125rem;
      white-space: nowrap;
    }

    .nav-link:hover {
      color: var(--text-primary) !important;
      background-color: var(--hover-bg);
    }

    .nav-link.active {
      color: var(--primary-color) !important;
      background-color: rgba(16, 163, 127, 0.1);
    }

    .nav-link i {
      width: 0.875rem;
      text-align: center;
      margin-right: 0.375rem;
      font-size: 0.8125rem;
    }

    .navbar-nav.ms-auto .nav-link.text-danger {
      color: #ef4444 !important;
    }

    .navbar-nav.ms-auto .nav-link.text-danger:hover {
      background-color: rgba(239, 68, 68, 0.1);
    }

    .container-main {
      margin-top: 1rem;
      margin-bottom: 2rem;
    }

    .dropdown-menu {
      border: 1px solid var(--border-color);
      border-radius: 0.5rem;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      padding: 0.5rem 0;
      margin-top: 0.25rem;
    }

    .dropdown-item {
      padding: 0.5rem 1rem;
      color: var(--text-secondary);
      font-size: 0.875rem;
      transition: all 0.2s ease;
    }

    .dropdown-item:hover {
      background-color: var(--hover-bg);
      color: var(--text-primary);
    }

    /* Responsive */
    @media (max-width: 991px) {
      .navbar-nav {
        padding-top: 1rem;
        border-top: 1px solid var(--border-color);
        margin-top: 1rem;
      }

      .nav-link {
        margin: 0.125rem 0;
        padding: 0.75rem 1rem !important;
      }
    }

    /* Clean animations */
    .navbar-nav {
      animation: fadeIn 0.3s ease-out;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* Global container restrictions for content readability */
    .content-container {
      max-width: 1200px;
      margin-left: auto;
      margin-right: auto;
    }

    /* Content wrapper for pages */
    .container-main {
      margin-top: 80px;
      margin-bottom: 2rem;
      padding: 0;
    }

    /* Specific content area */
    .container-main > * {
      max-width: 1200px;
      margin-left: auto;
      margin-right: auto;
      padding-left: 15px;
      padding-right: 15px;
    }

    /* Full width exceptions */
    .container-main > .container,
    .container-main > .container-fluid {
      max-width: none;
      margin-left: 0;
      margin-right: 0;
      padding-left: 0;
      padding-right: 0;
    }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg fixed-top">
  <div class="container-fluid" style="max-width: none; padding: 0 0.5rem;">
    <a class="navbar-brand" href="index.php">
      <i class="fas fa-truck"></i>
      TransportApp
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php
          $cur = basename($_SERVER['PHP_SELF']);
          $items = [
            'index.php'              => ['Dashboard', 'fas fa-home'],
            'add_tour.php'           => ['Nova tura', 'fas fa-plus'],
            'tours.php'              => ['Ture', 'fas fa-list'],
            'import_assign_tours.php' => ['Planiranje i import', 'fas fa-upload'],
            'send_tours.php'         => ['Slanje', 'fas fa-paper-plane'],
            'admin_tovarni_listovi.php' => ['Tovarni listovi', 'fas fa-clipboard-list'],
            'payroll.php'            => ['Plate vozači', 'fas fa-money-bill-wave'],
            'maintenance.php'        => ['Održavanje', 'fas fa-wrench'],
            'report_finance.php'     => ['Izveštaji', 'fas fa-chart-bar'],
            'map_vehicle.php'        => ['Mapa', 'fas fa-map'],
            'settings.php'           => ['Podešavanja', 'fas fa-cog']
          ];
          foreach($items as $file => $data):
            [$label, $icon] = $data;
        ?>
        <li class="nav-item">
          <a class="nav-link <?= $cur == $file ? 'active' : '' ?>" href="<?= $file ?>">
            <i class="<?= $icon ?>"></i>
            <?= $label ?>
          </a>
        </li>
        <?php endforeach; ?>
        <li class="nav-item">
          <a class="nav-link" href="quotes.php">
            <i class="fas fa-file-invoice"></i> Ponude
          </a>
        </li>
      </ul>

      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <a class="nav-link" href="notifications.php">
            <i class="fas fa-bell"></i>
            Obaveštenja
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="vehicles.php">
            <i class="fas fa-truck"></i>
            Vozila
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-danger" href="logout.php">
            <i class="fas fa-sign-out-alt"></i>
            Odjavi se
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="container-main">

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
</body>
</html>