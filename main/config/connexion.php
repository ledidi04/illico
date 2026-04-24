<?php
// Configuration des sessions - DOIT être fait avant session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Mettre à 1 en production avec HTTPS
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.gc_maxlifetime', 1800); // 30 minutes
ini_set('session.use_strict_mode', 1);

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_illico');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configuration de l'application
define('APP_NAME', 'S&P illico');
define('APP_URL', 'http://localhost/illico/main'); // Ajustez selon votre chemin
define('APP_VERSION', '1.4.0');
define('TIMEZONE', 'America/Port-au-Prince');

// Configuration du fuseau horaire
date_default_timezone_set(TIMEZONE);

// Création du dossier logs s'il n'existe pas
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Fonction pour logger les erreurs
function logError($message, $context = []) {
    $logFile = __DIR__ . '/../logs/error.log';
    $logMessage = date('Y-m-d H:i:s') . ' - ' . $message . ' - ' . json_encode($context) . PHP_EOL;
    error_log($logMessage, 3, $logFile);
}

// Connexion à la base de données
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    logError("Erreur de connexion DB: " . $e->getMessage());
    die("Erreur de connexion à la base de données. Veuillez contacter l'administrateur.");
}
?>