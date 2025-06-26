<?php
// Función para verificar si el usuario está logueado
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Función para verificar si el usuario es administrador
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Función para verificar si el usuario es colaborador
function isCollaborator() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'colaborador';
}

// Función para verificar si el usuario es cliente
function isClient() {
  if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'cliente';
}

// Redirigir si no está logueado
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /login.php");
        exit();
    }
}

// Redirigir según el rol
function redirectByRole() {
    if (isAdmin()) {
        header("Location: /admin/dashboard.php");
    } elseif (isCollaborator()) {
        header("Location: /colaborador/dashboard.php");
    } elseif (isClient()) {
        header("Location: /cliente/dashboard.php");
    }
    exit();
}
?>