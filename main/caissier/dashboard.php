<?php
require_once '../config/connexion.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'caissier') {
    header('Location: ../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Caissier - S&P illico</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: #1e293b; color: white; padding: 24px 0; }
        .sidebar h2 { padding: 0 24px 24px; color: #3b82f6; }
        .sidebar a { display: block; padding: 12px 24px; color: #cbd5e1; text-decoration: none; }
        .sidebar a:hover, .sidebar a.active { background: #334155; color: white; }
        .sidebar a i { margin-right: 12px; width: 20px; }
        .main { flex: 1; padding: 24px; }
        .topbar { background: white; padding: 20px 24px; border-radius: 12px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .logout { background: #ef4444; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; }
        .card { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 24px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2><i class="fas fa-building-columns"></i> S&P illico</h2>
        <a href="#" class="active"><i class="fas fa-gauge"></i> Tableau de bord</a>
        <a href="depot.php"><i class="fas fa-arrow-down"></i> Dépôt</a>
        <a href="retrait.php"><i class="fas fa-arrow-up"></i> Retrait</a>
        <a href="verification.php"><i class="fas fa-search"></i> Vérification</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
    </div>
    <div class="main">
        <div class="topbar">
            <h1>Espace Caissier</h1>
            <div>
                <span style="margin-right: 20px;">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['nom_complet']) ?>
                </span>
                <a href="../logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
            </div>
        </div>
        <div class="card">
            <h3>Bienvenue, <?= htmlspecialchars($_SESSION['nom_complet']) ?> !</h3>
            <p>Utilisez le menu pour effectuer des dépôts, retraits et vérifications de comptes.</p>
        </div>
    </div>
</body>
</html>