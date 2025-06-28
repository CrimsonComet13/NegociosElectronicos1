<?php
require_once(__DIR__ . '/includes/config.php');
require_once(__DIR__ . '/includes/auth.php');

// Destruir la sesión
session_unset();
session_destroy();

// Redirigir al inicio
header("Location: /index.php");
exit();
?>