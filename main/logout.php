<?php
// Démarrer la session
session_start();

// Détruire toutes les variables de session
$_SESSION = array();

// Supprimer le cookie de session si existant
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Détruire la session
session_destroy();

// Supprimer les cookies "remember"
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}
if (isset($_COOKIE['remember_login'])) {
    setcookie('remember_login', '', time() - 3600, '/');
}

// Rediriger vers la page de connexion avec un message
header('Location: index.php?logout=1');
exit;
?>