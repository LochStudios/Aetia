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
    <link rel="icon" type="image/x-icon" href="img/logo.ico">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.min.css">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar is-primary" role="navigation" aria-label="main navigation">
      <div class="navbar-brand">
        <a class="navbar-item" href="index.php">
          <img src="img/logo.png" alt="Aetia Talant Agency Logo" style="max-height: 3rem;">
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
    <footer class="footer has-background-light mt-6">
      <div class="content has-text-centered">
        <p>
          <img src="img/logo.png" alt="Aetia Logo" style="max-height:2rem;vertical-align:middle;"> <strong>Aetia Talant Agency</strong><br>
          <span class="icon-text">
            <span class="icon"><i class="fas fa-envelope"></i></span>
            <span><a href="mailto:talant@aetia.com.au">talant@aetia.com.au</a></span>
          </span>
        </p>
        <p class="is-size-7">&copy; <?= date('Y') ?> Aetia Talant Agency. All rights reserved.</p>
      </div>
    </footer>
    <script src="js/navbar.js"></script>
    <!-- Font Awesome for icons -->
    <script src="https://kit.fontawesome.com/7b8e1e2e2a.js" crossorigin="anonymous"></script>
</body>
</html>
