<?php
session_start();
include_once __DIR__ . '/../../config/config.php';

// Cek apakah user sudah login
if (!isLoggedIn()) {
    header("Location: {$base_url}/auth/login.php");
    exit;
}

if ($_SESSION['role'] !== 'owner') {
    header("Location: {$base_url}/auth/role_tidak_cocok.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<!-- [Head] start -->

<head>
    <title>irveena</title>
    <!-- [Meta] -->
    <meta charset="utf-8" />
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta
        name="description"
        content="Mantis is made using Bootstrap 5 design framework. Download the free admin template & use it for your project." />
    <meta
        name="keywords"
        content="Mantis, Dashboard UI Kit, Bootstrap 5, Admin Template, Admin Dashboard, CRM, CMS, Bootstrap Admin Template" />
    <meta name="author" content="CodedThemes" />

    <!-- [Favicon] icon -->
    <!-- <link rel="icon" href="<?= $base_url ?>assets/images/favicon.svg" type="image/x-icon" /> -->
    <link rel="icon" type="image/x-icon" href="<?= $base_url ?>/assets/img/Logo.png" />


    <!-- [Google Font] Family -->
    <link
        rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap"
        id="main-font-link" />
    <!-- [Tabler Icons] https://tablericons.com -->
    <link rel="stylesheet" href="<?= $base_url ?>/assets/fonts/tabler-icons.min.css" />
    <!-- [Feather Icons] https://feathericons.com -->
    <link rel="stylesheet" href="<?= $base_url ?>/assets/fonts/feather.css" />
    <!-- [Font Awesome Icons] https://fontawesome.com/icons -->
    <link rel="stylesheet" href="<?= $base_url ?>/assets/fonts/fontawesome.css" />
    <!-- [Material Icons] https://fonts.google.com/icons -->
    <link rel="stylesheet" href="<?= $base_url ?>/assets/fonts/material.css" />
    <!-- [Template CSS Files] -->
    <link
        rel="stylesheet"
        href="<?= $base_url ?>/assets/css/style.css"
        id="main-style-link" />
    <link rel="stylesheet" href="<?= $base_url ?>/assets/css/style-preset.css" />
</head>
<!-- [Head] end -->