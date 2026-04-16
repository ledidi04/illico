<?php
// Ce fichier est inclus APRÈS que la session soit démarrée
// et APRÈS l'inclusion de config/connexion.php

// Vérification de l'authentification
function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}

// Vérification du rôle
function checkRole($allowedRoles) {
    checkAuth();
    
    if (!is_array($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    
    if (!in_array($_SESSION['role'], $allowedRoles)) {
        header('HTTP/1.0 403 Forbidden');
        die('Accès non autorisé');
    }
}

// Vérification du timeout de session (30 minutes)
function checkSessionTimeout() {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_unset();
        session_destroy();
        header('Location: ' . APP_URL . '/index.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

// Récupération des informations utilisateur
function getCurrentUser() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, s.nom as succursale_nom, s.code as succursale_code
            FROM utilisateurs u
            JOIN succursales s ON u.succursale_id = s.id
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        logError("Erreur getCurrentUser: " . $e->getMessage());
        return null;
    }
}

// Protection CSRF
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>