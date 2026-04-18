<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../config/connexion.php';
session_start();

$response = ['found' => false, 'error' => ''];

// Vérifier l'authentification
if (!isset($_SESSION['user_id'])) {
    $response['error'] = 'Non authentifié';
    echo json_encode($response);
    exit;
}

// Récupérer l'ID du compte
$id_compte = $_GET['id_compte'] ?? '';

if (!preg_match('/^\d{5}$/', $id_compte)) {
    $response['error'] = 'Format de compte invalide (5 chiffres requis)';
    echo json_encode($response);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT c.id_compte, c.solde, c.devise, c.statut,
               CONCAT(cl.prenom, ' ', cl.nom) as titulaire
        FROM comptes c
        JOIN clients cl ON c.titulaire_principal_id = cl.id
        WHERE c.id_compte = ? AND c.statut = 'actif'
    ");
    $stmt->execute([$id_compte]);
    $compte = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($compte) {
        $response = [
            'found' => true,
            'id_compte' => $compte['id_compte'],
            'titulaire' => $compte['titulaire'],
            'solde' => number_format($compte['solde'], 2, ',', ' '),
            'devise' => $compte['devise']
        ];
    } else {
        $response['error'] = 'Compte non trouvé ou inactif';
    }
} catch (PDOException $e) {
    $response['error'] = 'Erreur base de données: ' . $e->getMessage();
}

echo json_encode($response);
?>