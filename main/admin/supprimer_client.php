<?php
require_once '../config/connexion.php';
session_start();

// Vérifier l'authentification et le rôle (admin uniquement)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$id_client = $_GET['id'] ?? 0;
$redirect = $_GET['redirect'] ?? 'liste_clients.php';

if ($id_client) {
    try {
        // Vérifier si le client a des comptes (titulaire principal)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM comptes WHERE titulaire_principal_id = ?");
        $stmt->execute([$id_client]);
        $nb_comptes_titulaire = $stmt->fetchColumn();
        
        // Vérifier si le client est co-titulaire
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM compte_cotitulaires WHERE client_id = ?");
        $stmt->execute([$id_client]);
        $nb_comptes_cotitulaire = $stmt->fetchColumn();
        
        $total_comptes = $nb_comptes_titulaire + $nb_comptes_cotitulaire;
        
        if ($total_comptes > 0) {
            $pdo->beginTransaction();
            
            // 1. Supprimer les relations de co-titularité
            $pdo->prepare("DELETE FROM compte_cotitulaires WHERE client_id = ?")->execute([$id_client]);
            
            // 2. Récupérer les comptes dont le client est titulaire principal
            $stmt = $pdo->prepare("SELECT id FROM comptes WHERE titulaire_principal_id = ?");
            $stmt->execute([$id_client]);
            $comptes = $stmt->fetchAll();
            
            // 3. Pour chaque compte, supprimer les transactions et les relations
            foreach ($comptes as $compte) {
                $pdo->prepare("DELETE FROM transactions WHERE compte_id = ?")->execute([$compte['id']]);
                $pdo->prepare("DELETE FROM compte_cotitulaires WHERE compte_id = ?")->execute([$compte['id']]);
                $pdo->prepare("DELETE FROM comptes WHERE id = ?")->execute([$compte['id']]);
            }
            
            // 4. Supprimer le client
            $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$id_client]);
            
            $pdo->commit();
            $_SESSION['message'] = "Client et ses $total_comptes compte(s) supprimés avec succès.";
            
            $pdo->prepare("INSERT INTO logs_activites (utilisateur_id, action, details, ip_address) VALUES (?, 'suppression_client_avec_comptes', ?, ?)")
                ->execute([$_SESSION['user_id'], "Suppression client ID $id_client avec $total_comptes comptes", $_SERVER['REMOTE_ADDR']]);
        } else {
            $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$id_client]);
            $_SESSION['message'] = "Client supprimé avec succès.";
            
            $pdo->prepare("INSERT INTO logs_activites (utilisateur_id, action, details, ip_address) VALUES (?, 'suppression_client', ?, ?)")
                ->execute([$_SESSION['user_id'], "Suppression client ID $id_client", $_SERVER['REMOTE_ADDR']]);
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur lors de la suppression : " . $e->getMessage();
    }
}

header("Location: $redirect");
exit;
?>