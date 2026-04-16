<?php
require_once '../config/database.php';
require_once '../lib/fpdf/fpdf.php';

$id_compte = $_GET['id_compte'] ?? '';
$stmt = $pdo->prepare("
    SELECT c.*, cl.nom, cl.prenom, cl.id_client, cl.adresse, cl.telephone
    FROM comptes c
    JOIN clients cl ON c.titulaire_principal_id = cl.id
    WHERE c.id_compte = ?
");
$stmt->execute([$id_compte]);
$compte = $stmt->fetch();

if (!$compte) die("Compte introuvable");

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,"S&P illico - Releve de Compte",0,1,'C');
$pdf->Ln(10);
$pdf->SetFont('Arial','',12);
$pdf->Cell(50,10,"Numero de compte : ".$compte['id_compte']);
$pdf->Ln(7);
$pdf->Cell(50,10,"Titulaire : ".$compte['prenom']." ".$compte['nom']);
$pdf->Ln(7);
$pdf->Cell(50,10,"Type : ".$compte['type_compte']);
$pdf->Ln(7);
$pdf->Cell(50,10,"Date creation : ".$compte['date_creation']);
$pdf->Ln(7);
$pdf->Cell(50,10,"Solde initial : 0.00 HTG");
$pdf->Output('D', "Compte_$id_compte.pdf");