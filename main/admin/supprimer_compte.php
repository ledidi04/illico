<?php
require_once '../config/connexion.php';
session_start();

// Vérifier l'authentification et le rôle (admin uniquement)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$id_compte = $_GET['id'] ?? 0;
$redirect = $_GET['redirect'] ?? 'liste_clients.php';

if ($id_compte) {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT id_compte FROM comptes WHERE id = ?");
        $stmt->execute([$id_compte]);
        $compte = $stmt->fetch();
        
        if ($compte) {
            $pdo->prepare("DELETE FROM compte_cotitulaires WHERE compte_id = ?")->execute([$id_compte]);
            $pdo->prepare("DELETE FROM transactions WHERE compte_id = ?")->execute([$id_compte]);
            $pdo->prepare("DELETE FROM comptes WHERE id = ?")->execute([$id_compte]);
            
            $_SESSION['message'] = "Compte N° " . $compte['id_compte'] . " supprimé avec succès.";
            
            $pdo->prepare("INSERT INTO logs_activites (utilisateur_id, action, details, ip_address) VALUES (?, 'suppression_compte', ?, ?)")
                ->execute([$_SESSION['user_id'], "Suppression compte {$compte['id_compte']}", $_SERVER['REMOTE_ADDR']]);
        } else {
            $_SESSION['error'] = "Compte introuvable.";
        }
        
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Erreur lors de la suppression : " . $e->getMessage();
    }
}

header("Location: $redirect");
exit;
?>