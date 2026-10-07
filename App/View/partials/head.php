<?php
/**
 * En-tête HTML — partiel inclus par layout.php.
 * Variables attendues : $title (string), $cssUrl (string), $identityDefault (string).
 */
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars((string)($title ?? 'RouterOS Generator V2'), ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="<?= htmlspecialchars((string)($cssUrl ?? 'assets/css/style.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<div class="app">
  <header class="hero">
    <h1>Générateur MikroTik RouterOS V2 <span class="small">v<?= htmlspecialchars((string)($appVersion ?? '2.2.0'), ENT_QUOTES, 'UTF-8') ?></span></h1>
    <p>Backend PHP séparé, validation serveur et moteur de templates.</p>
  </header>
