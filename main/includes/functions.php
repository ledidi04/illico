<?php
require_once __DIR__ . '/../config/connexion.php';

// Validation des entrées
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Validation du format NIF/CINU
function validateIdClient($id) {
    return preg_match('/^\d{3}-\d{3}-\d{3}-\d{1}$/', $id);
}

// Génération d'un ID client optionnel
function generateOptionalId() {
    global $pdo;
    do {
        $id = sprintf('%03d-%03d-%03d-%d', 
            rand(1, 999), rand(1, 999), rand(1, 999), rand(1, 9));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE id_client = ?");
        $stmt->execute([$id]);
    } while ($stmt->fetchColumn() > 0);
    return $id;
}

// Formatage de la monnaie
function formatMoney($amount) {
    return number_format($amount, 2, '.', ',') . ' HTG';
}

// Formatage de date
function formatDate($date, $format = 'd/m/Y') {
    return date($format, strtotime($date));
}

// Génération d'un mot de passe aléatoire
function generateRandomPassword($length = 10) {
    return bin2hex(random_bytes($length / 2));
}

// Upload de photo
function uploadPhoto($file, $type = 'client') {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'Type de fichier non autorisé'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'Fichier trop volumineux'];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid($type . '_') . '.' . $extension;
    $uploadPath = __DIR__ . '/../uploads/photos/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return ['success' => true, 'filename' => $filename];
    }
    
    return ['success' => false, 'message' => 'Erreur lors de l\'upload'];
}

// Vérification des permissions
function hasPermission($requiredRole) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $roleHierarchy = [
        'admin' => 3,
        'secretaire' => 2,
        'caissier' => 1
    ];
    
    $userRole = $_SESSION['role'] ?? '';
    return isset($roleHierarchy[$userRole]) && 
           $roleHierarchy[$userRole] >= $roleHierarchy[$requiredRole];
}

// Récupération des statistiques du dashboard
function getDashboardStats($succursale_id = null) {
    global $pdo;
    
    $stats = [];
    
    // Nombre total de comptes
    $sql = "SELECT COUNT(*) FROM comptes WHERE statut = 'actif'";
    $params = [];
    if ($succursale_id) {
        $sql .= " AND succursale_id = ?";
        $params[] = $succursale_id;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stats['total_comptes'] = $stmt->fetchColumn();
    
    // Total des dépôts
    $sql = "SELECT COALESCE(SUM(solde), 0) FROM comptes WHERE statut = 'actif'";
    $params = [];
    if ($succursale_id) {
        $sql .= " AND succursale_id = ?";
        $params[] = $succursale_id;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stats['total_depots'] = $stmt->fetchColumn();
    
    // Transactions du jour
    $sql = "SELECT COUNT(*) FROM transactions WHERE DATE(date_transaction) = CURDATE()";
    $params = [];
    if ($succursale_id) {
        $sql .= " AND succursale_id = ?";
        $params[] = $succursale_id;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stats['transactions_jour'] = $stmt->fetchColumn();
    
    return $stats;
}

// Journalisation des actions
function logAction($action, $details = []) {
    global $pdo;
    
    $utilisateur_id = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    
    $stmt = $pdo->prepare("
        INSERT INTO logs_activites (utilisateur_id, action, details, ip_address)
        VALUES (?, ?, ?, ?)
    ");
    
    return $stmt->execute([$utilisateur_id, $action, json_encode($details), $ip]);
}

// Recherche de compte par différents critères
function searchAccount($search) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT c.id_compte, c.solde, c.date_creation, c.statut,
               tc.nom as type_compte,
               cl.nom, cl.prenom, cl.id_client, cl.telephone,
               s.nom as succursale
        FROM comptes c
        JOIN types_comptes tc ON c.type_compte_id = tc.id
        JOIN clients cl ON c.titulaire_principal_id = cl.id
        JOIN succursales s ON c.succursale_id = s.id
        WHERE c.id_compte = ? 
           OR cl.id_client = ? 
           OR CONCAT(cl.nom, ' ', cl.prenom) LIKE ?
           OR cl.telephone LIKE ?
        LIMIT 20
    ");
    
    $searchTerm = "%$search%";
    $stmt->execute([$search, $search, $searchTerm, $searchTerm]);
    return $stmt->fetchAll();
}
?>