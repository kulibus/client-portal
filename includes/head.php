<?php
if (!isset($base_url)) {
    $base_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
}
?>
<!-- includes/head.php - Metadatos y enlaces CSS del documento HTML -->

<!-- Configuración de codificación de caracteres UTF-8 -->
<meta charset="UTF-8">

<!-- Configuración de viewport para diseño responsivo -->
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- Título dinámico de la página - usa variable $titulo_pagina o valor por defecto -->
<title><?= $titulo_pagina ?? 'El Garage' ?></title>

<!-- Bootstrap 5.3.0 - Framework CSS para diseño responsivo -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Estilos personalizados del proyecto -->
<link rel="stylesheet" href="<?= $base_url ?>css/styles.css">

<!-- Favicon del sitio web -->
<link rel="icon" type="image/x-icon" href="<?= $base_url ?>favicon.ico">