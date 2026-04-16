<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php'; // vérifie que rôle = secretaire ou admin

$succursale_id = $_SESSION['succursale_id'];
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération des données
    $type_compte = $_POST['type_compte'];
    $date_creation = $_POST['date_creation'];

    // Titulaire principal
    $nom = $_POST['nom'];
    $prenom = $_POST['prenom'];
    $id_client = $_POST['id_client']; // format XXX-XXX-XXX-X
    $date_naissance = $_POST['date_naissance'];
    $lieu_naissance = $_POST['lieu_naissance'];
    $adresse = $_POST['adresse'];
    $telephone = $_POST['telephone'];

    // Upload photo (optionnel)
    $photo_path = null;
    if (!empty($_FILES['photo']['name'])) {
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $photo_path = 'uploads/' . uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['photo']['tmp_name'], '../' . $photo_path);
    }

    try {
        $pdo->beginTransaction();

        // 1. Insérer ou récupérer le client titulaire
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE id_client = ?");
        $stmt->execute([$id_client]);
        $client_id = $stmt->fetchColumn();

        if (!$client_id) {
            $stmt = $pdo->prepare("INSERT INTO clients (id_client, nom, prenom, date_naissance, lieu_naissance, adresse, telephone, photo)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id_client, $nom, $prenom, $date_naissance, $lieu_naissance, $adresse, $telephone, $photo_path]);
            $client_id = $pdo->lastInsertId();
        } else {
            // Mise à jour des infos si nécessaire (à implémenter)
        }

        // 2. Créer le compte bancaire
        $stmt = $pdo->prepare("INSERT INTO comptes (succursale_id, type_compte, date_creation, titulaire_principal_id)
                               VALUES (?, ?, ?, ?)");
        $stmt->execute([$succursale_id, $type_compte, $date_creation, $client_id]);
        $compte_id = $pdo->lastInsertId();

        // 3. Récupérer l'id_compte généré par trigger
        $stmt = $pdo->prepare("SELECT id_compte FROM comptes WHERE id = ?");
        $stmt->execute([$compte_id]);
        $id_compte = $stmt->fetchColumn();

        // 4. Gestion des co‑titulaires (tableau dynamique)
        if (!empty($_POST['cotitulaires'])) {
            foreach ($_POST['cotitulaires'] as $cot) {
                // Vérifier si client existe, sinon créer
                $stmt = $pdo->prepare("SELECT id FROM clients WHERE id_client = ?");
                $stmt->execute([$cot['id_client']]);
                $cot_id = $stmt->fetchColumn();
                if (!$cot_id) {
                    $stmt = $pdo->prepare("INSERT INTO clients (id_client, nom, prenom) VALUES (?, ?, ?)");
                    $stmt->execute([$cot['id_client'], $cot['nom'], $cot['prenom']]);
                    $cot_id = $pdo->lastInsertId();
                }
                // Lier au compte
                $stmt = $pdo->prepare("INSERT IGNORE INTO compte_cotitulaires (compte_id, client_id) VALUES (?, ?)");
                $stmt->execute([$compte_id, $cot_id]);
            }
        }

        $pdo->commit();
        $success = "Compte $id_compte créé avec succès.";

        // Redirection vers impression (optionnel)
        header("Location: ../commun/impression.php?id_compte=$id_compte");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erreur : " . $e->getMessage();
    }
}

// Affichage du formulaire (HTML avec JavaScript pour ajouter dynamiquement des co‑titulaires)
?>