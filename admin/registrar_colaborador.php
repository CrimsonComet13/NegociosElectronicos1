<?php
require_once(__DIR__ . '/../includes/config.php');
require_once(__DIR__ . '/../includes/auth.php');

if (!isAdmin()) {
    header("Location: /login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    // Redirigir al formulario de registro con parámetros claros
    header("Location: gestion_colaboradores.php?registrar=1");
    exit();
}

// Redirección por defecto
header("Location: gestion_colaboradores.php");
exit();
?>