<?php
// sitemap.php
header('Content-Type: application/xml; charset=utf-8');

// Inclure la connexion à la base de données
require_once 'config/connexion.php';

// Fonction pour générer l'URL complète
function getFullUrl($path) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $domain = $_SERVER['HTTP_HOST'];
    return $protocol . $domain . '/sp_illico/' . ltrim($path, '/');
}

// Démarrer le XML
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?xml-stylesheet type="text/xsl" href="sitemap.xsl"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// 1. Pages statiques principales
$staticPages = [
    '' => ['priority' => '1.0', 'changefreq' => 'daily'],  // index.php
    'dashboard.php' => ['priority' => '0.9', 'changefreq' => 'daily'],
    'error.php' => ['priority' => '0.3', 'changefreq' => 'monthly'],
];

foreach ($staticPages as $page => $options) {
    $url = getFullUrl($page);
    echo "\t<url>\n";
    echo "\t\t<loc>" . htmlspecialchars($url) . "</loc>\n";
    echo "\t\t<lastmod>" . date('Y-m-d') . "</lastmod>\n";
    echo "\t\t<changefreq>" . $options['changefreq'] . "</changefreq>\n";
    echo "\t\t<priority>" . $options['priority'] . "</priority>\n";
    echo "\t</url>\n";
}

// 2. Pages Admin
$adminPages = [
    'admin/dashboard.php' => ['priority' => '0.8', 'changefreq' => 'daily'],
    'admin/utilisateurs.php' => ['priority' => '0.8', 'changefreq' => 'weekly'],
];

foreach ($adminPages as $page => $options) {
    $url = getFullUrl($page);
    echo "\t<url>\n";
    echo "\t\t<loc>" . htmlspecialchars($url) . "</loc>\n";
    echo "\t\t<lastmod>" . date('Y-m-d') . "</lastmod>\n";
    echo "\t\t<changefreq>" . $options['changefreq'] . "</changefreq>\n";
    echo "\t\t<priority>" . $options['priority'] . "</priority>\n";
    echo "\t</url>\n";
}

// 3. Pages Secrétaire
$secretairePages = [
    'secretaire/dashboard.php' => ['priority' => '0.9', 'changefreq' => 'daily'],
    'secretaire/creer_compte.php' => ['priority' => '0.7', 'changefreq' => 'weekly'],
    'secretaire/ajouter_client.php' => ['priority' => '0.7', 'changefreq' => 'weekly'],
    'secretaire/liste_clients.php' => ['priority' => '0.8', 'changefreq' => 'daily'],
    'secretaire/depot.php' => ['priority' => '0.7', 'changefreq' => 'weekly'],
    'secretaire/retrait.php' => ['priority' => '0.7', 'changefreq' => 'weekly'],
    'secretaire/verification.php' => ['priority' => '0.8', 'changefreq' => 'daily'],
];

foreach ($secretairePages as $page => $options) {
    $url = getFullUrl($page);
    echo "\t<url>\n";
    echo "\t\t<loc>" . htmlspecialchars($url) . "</loc>\n";
    echo "\t\t<lastmod>" . date('Y-m-d') . "</lastmod>\n";
    echo "\t\t<changefreq>" . $options['changefreq'] . "</changefreq>\n";
    echo "\t\t<priority>" . $options['priority'] . "</priority>\n";
    echo "\t</url>\n";
}

// 4. Pages Caissier
$caissierPages = [
    'caissier/dashboard.php' => ['priority' => '0.9', 'changefreq' => 'daily'],
    'caissier/depot.php' => ['priority' => '0.7', 'changefreq' => 'weekly'],
    'caissier/retrait.php' => ['priority' => '0.7', 'changefreq' => 'weekly'],
    'caissier/verification.php' => ['priority' => '0.8', 'changefreq' => 'daily'],
];

foreach ($caissierPages as $page => $options) {
    $url = getFullUrl($page);
    echo "\t<url>\n";
    echo "\t\t<loc>" . htmlspecialchars($url) . "</loc>\n";
    echo "\t\t<lastmod>" . date('Y-m-d') . "</lastmod>\n";
    echo "\t\t<changefreq>" . $options['changefreq'] . "</changefreq>\n";
    echo "\t\t<priority>" . $options['priority'] . "</priority>\n";
    echo "\t</url>\n";
}

// 5. Pages Communes
$communPages = [
    'commun/recherche.php' => ['priority' => '0.6', 'changefreq' => 'daily'],
    'commun/vue_compte.php' => ['priority' => '0.7', 'changefreq' => 'weekly'],
    'commun/vue_client.php' => ['priority' => '0.7', 'changefreq' => 'weekly'],
    'commun/impression.php' => ['priority' => '0.4', 'changefreq' => 'monthly'],
];

foreach ($communPages as $page => $options) {
    $url = getFullUrl($page);
    echo "\t<url>\n";
    echo "\t\t<loc>" . htmlspecialchars($url) . "</loc>\n";
    echo "\t\t<lastmod>" . date('Y-m-d') . "</lastmod>\n";
    echo "\t\t<changefreq>" . $options['changefreq'] . "</changefreq>\n";
    echo "\t\t<priority>" . $options['priority'] . "</priority>\n";
    echo "\t</url>\n";
}

// 6. Pages dynamiques - Clients (si vous avez une table clients)
try {
    $query = "SELECT id, created_at FROM clients ORDER BY id LIMIT 1000";
    $stmt = $pdo->query($query);
    
    while ($client = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $url = getFullUrl('commun/vue_client.php?id=' . $client['id']);
        $lastmod = date('Y-m-d', strtotime($client['created_at']));
        
        echo "\t<url>\n";
        echo "\t\t<loc>" . htmlspecialchars($url) . "</loc>\n";
        echo "\t\t<lastmod>" . $lastmod . "</lastmod>\n";
        echo "\t\t<changefreq>weekly</changefreq>\n";
        echo "\t\t<priority>0.6</priority>\n";
        echo "\t</url>\n";
    }
} catch (PDOException $e) {
    // La table n'existe pas encore, ignorer
}

// 7. Pages dynamiques - Comptes
try {
    $query = "SELECT id, updated_at FROM comptes ORDER BY id LIMIT 1000";
    $stmt = $pdo->query($query);
    
    while ($compte = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $url = getFullUrl('commun/vue_compte.php?id=' . $compte['id']);
        $lastmod = date('Y-m-d', strtotime($compte['updated_at']));
        
        echo "\t<url>\n";
        echo "\t\t<loc>" . htmlspecialchars($url) . "</loc>\n";
        echo "\t\t<lastmod>" . $lastmod . "</lastmod>\n";
        echo "\t\t<changefreq>daily</changefreq>\n";
        echo "\t\t<priority>0.7</priority>\n";
        echo "\t</url>\n";
    }
} catch (PDOException $e) {
    // La table n'existe pas encore, ignorer
}

echo '</urlset>';
?>