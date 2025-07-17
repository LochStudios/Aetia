<?php
// layout.php - Main layout template for Aetia Talant Agency
if (!isset($pageTitle)) $pageTitle = 'Aetia Talant Agency';
if (!isset($content)) $content = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.0/css/bulma.min.css">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar is-primary" role="navigation" aria-label="main navigation">
      <div class="navbar-brand">
        <a class="navbar-item" href="index.php">
          <strong>Aetia Talant Agency</strong>
        </a>
        <a role="button" class="navbar-burger" aria-label="menu" aria-expanded="false" data-target="navbarBasic">
          <span aria-hidden="true"></span>
          <span aria-hidden="true"></span>
          <span aria-hidden="true"></span>
        </a>
      </div>
      <div id="navbarBasic" class="navbar-menu">
        <div class="navbar-start">
          <a class="navbar-item" href="index.php">Home</a>
          <a class="navbar-item" href="about.php">About</a>
          <a class="navbar-item" href="services.php">Services</a>
          <a class="navbar-item" href="contact.php">Contact</a>
        </div>
      </div>
    </nav>
    <section class="section">
        <div class="container">
            <?= $content ?>
        </div>
    </section>
    <script src="js/navbar.js"></script>
</body>
</html>
