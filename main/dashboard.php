<?php
require_once 'config/connexion.php';
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Rediriger selon le rôle
$role = $_SESSION['role'] ?? '';

switch ($role) {
    case 'admin':
        header('Location: admin/dashboard.php');
        break;
    case 'secretaire':
        header('Location: secretaire/dashboard.php');
        break;
    case 'caissier':
        header('Location: caissier/dashboard.php');
        break;
    default:
        session_destroy();
        header('Location: index.php?error=invalid_role');
}
exit;
?>