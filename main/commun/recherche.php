<?php
session_start();
require_once '../config/database.php';

$recherche = $_GET['q'] ?? '';
$resultats = [];

if (strlen($recherche) >= 3) {
    // Recherche par id_compte, NIF/CINU (id_client) ou nom
    $stmt = $pdo->prepare("
        SELECT c.id_compte, c.type_compte, c.solde, cl.nom, cl.prenom, cl.id_client
        FROM comptes c
        JOIN clients cl ON c.titulaire_principal_id = cl.id
        WHERE c.id_compte = ? OR cl.id_client = ? OR CONCAT(cl.nom, ' ', cl.prenom) LIKE ?
    ");
    $stmt->execute([$recherche, $recherche, "%$recherche%"]);
    $resultats = $stmt->fetchAll();
}
?>
<!-- Affichage sous forme de tableau avec lien vers vue détaillée -->