<?php
// layout.php - Main layout template for Aetia Talant Agency
if (!isset($pageTitle)) $pageTitle = 'Aetia Talant Agency';
if (!isset($content)) $content = '';
?>
<!DOCTYPE html>
<html lang="en" style="height:100%;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="icon" type="image/x-icon" href="img/logo.ico">
    <!-- Bulma CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/custom.css">
</head>
<body style="min-height:100vh;display:flex;flex-direction:column;">
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
    <section class="section" style="flex:1 0 auto;">
        <div class="container">
            <?= $content ?>
        </div>
    </section>
    <footer class="footer has-background-dark has-text-light mt-6" style="padding-top:2rem;padding-bottom:2rem;flex-shrink:0;">
      <div class="content has-text-centered">
        <p>
          <img src="img/logo.png" alt="Aetia Logo" style="max-height:2rem;vertical-align:middle;filter:brightness(0) invert(1);"> <strong class="has-text-light">Aetia Talant Agency</strong><br>
          <span class="icon-text">
            <span class="icon has-text-info"><i class="fas fa-envelope"></i></span>
            <span><a href="mailto:talant@aetia.com.au" class="has-text-info">talant@aetia.com.au</a></span>
          </span>
        </p>
        <p class="is-size-7 has-text-grey-light">&copy; <?= date('Y') ?> Aetia Talant Agency. All rights reserved.</p>
        <hr class="my-2" style="background:rgba(255,255,255,0.08);height:1px;border:none;">
        <p class="is-size-7 has-text-grey-light mb-0">
          Aetia Talant Agency is registered as a subsidiary under LochStudios (ABN: 20 447 022 747).
        </p>
      </div>
    </footer>
    <script src="js/navbar.js"></script>
</body>
</html>
