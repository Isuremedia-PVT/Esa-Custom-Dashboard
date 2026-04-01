<?php
// Ensure page title is set, fallback to 'Cocoon Baby'
$page_title = $page_title ?? 'Cocoon Baby';
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
  <!-- Charset & Viewport -->
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />

  <!-- Title & Description -->
  <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?> | Cocoon Baby</title>
  <meta name="description" content="Cocoon Baby - Patient Management System" />
  <link rel="canonical" href="https://www.cocoonbaby.com.au/" />

  <!-- Favicon -->
  <link rel="icon" href="./public/img/favicon.png" type="image/png" />
  <link rel="apple-touch-icon" sizes="180x180" href="./public/img/favicon.png" />

  <!-- CSS Files -->
  <link rel="stylesheet" href="./public/css/style.css?v=<?php echo time(); ?>" />
  <link rel="stylesheet" href="./public/css/main.css?v=<?php echo time(); ?>" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

  <!-- SEO: Don't index internal pages -->
  <meta name="robots" content="noindex, nofollow">
</head>
<body>