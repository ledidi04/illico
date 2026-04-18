<?php
require_once '../config/connexion.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'caissier') {
    header('Location: ../index.php');
    exit;
}

// Statistiques pour le caissier (sa succursale uniquement)
$succursale_id = $_SESSION['succursale_id'];

try {
    // Nombre de comptes dans la succursale
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM comptes WHERE succursale_id = ? AND statut = 'actif'");
    $stmt->execute([$succursale_id]);
    $total_comptes = $stmt->fetchColumn();
    
    // Nombre de clients dans la succursale
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT cl.id) 
        FROM clients cl
        JOIN comptes c ON cl.id = c.titulaire_principal_id
        WHERE c.succursale_id = ?
    ");
    $stmt->execute([$succursale_id]);
    $total_clients = $stmt->fetchColumn();
    
    // Transactions du jour
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as nb_transactions,
            COALESCE(SUM(CASE WHEN type = 'depot' THEN montant ELSE 0 END), 0) as total_depots,
            COALESCE(SUM(CASE WHEN type = 'retrait' THEN montant ELSE 0 END), 0) as total_retraits
        FROM transactions
        WHERE succursale_id = ? AND DATE(date_transaction) = CURDATE()
    ");
    $stmt->execute([$succursale_id]);
    $transactions_jour = $stmt->fetch();
    
    if (!$transactions_jour) {
        $transactions_jour = ['nb_transactions' => 0, 'total_depots' => 0, 'total_retraits' => 0];
    }
    
} catch (PDOException $e) {
    $total_comptes = 0;
    $total_clients = 0;
    $transactions_jour = ['nb_transactions' => 0, 'total_depots' => 0, 'total_retraits' => 0];
}

$currentPage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Caissier - Tableau de bord - S&P illico</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/common.css">
    
</head>
<body>
    <?php require_once '../includes/sidebar.php'; ?>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-building-columns"></i> S&P illico</h2>
            <p>Banque Communautaire</p>
        </div>
        <div class="user-info-side">
            <div class="avatar"><i class="fas fa-user"></i></div>
            <div class="name"><?= htmlspecialchars($_SESSION['nom_complet']) ?></div>
            <div class="role"><i class="fas fa-shield"></i> Caissier</div>
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-item active"><i class="fas fa-gauge"></i> Tableau de bord</a>
            <a href="depot.php" class="nav-item"><i class="fas fa-arrow-down"></i> Dépôt</a>
            <a href="retrait.php" class="nav-item"><i class="fas fa-arrow-up"></i> Retrait</a>
            <a href="verification.php" class="nav-item"><i class="fas fa-search"></i> Vérification</a>
            <div class="nav-divider"></div>
            <a href="../logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>Tableau de bord - Caissier</h1>
                <div class="breadcrumb"><a href="dashboard.php">Accueil</a> / Vue d'ensemble</div>
            </div>
             <div class="top-right">
                <span class="top-succursale"><i class="fas fa-building"></i> <?= htmlspecialchars($_SESSION['succursale_nom']) ?></span>
                <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
            </div>
        </div>
        
        <p style="color:#64748b;margin-bottom:20px;">
            Bienvenue, <strong><?= htmlspecialchars(explode(' ', $_SESSION['nom_complet'])[0]) ?></strong> —
            Caissier | <?= date('d/m/Y H:i') ?>
        </p>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="info">
                    <div class="value"><?= number_format($total_clients) ?></div>
                    <div class="label">Clients</div>
                </div>
                <div class="icon"><i class="fas fa-users"></i></div>
            </div>
            <div class="stat-card">
                <div class="info">
                    <div class="value"><?= number_format($total_comptes) ?></div>
                    <div class="label">Comptes actifs</div>
                </div>
                <div class="icon"><i class="fas fa-credit-card"></i></div>
            </div>
            <div class="stat-card">
                <div class="info">
                    <div class="value"><?= number_format($transactions_jour['nb_transactions']) ?></div>
                    <div class="label">Transactions aujourd'hui</div>
                </div>
                <div class="icon"><i class="fas fa-exchange-alt"></i></div>
            </div>
        </div>
        
        <div class="card">
            <h3><i class="fas fa-calendar-day" style="color:#10b981;"></i> Résumé des opérations du jour</h3>
            <div class="summary-row">
                <div class="summary-item">
                    <div class="summary-value"><?= number_format($transactions_jour['nb_transactions']) ?></div>
                    <div class="summary-label">Transactions</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value" style="color:#16a34a;">+<?= number_format($transactions_jour['total_depots'], 0, ',', ' ') ?> HTG</div>
                    <div class="summary-label">Total dépôts</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value" style="color:#dc2626;">-<?= number_format($transactions_jour['total_retraits'], 0, ',', ' ') ?> HTG</div>
                    <div class="summary-label">Total retraits</div>
                </div>
            </div>
        </div>
        
        <!-- <div class="quick-actions">
            <a href="depot.php" class="action-card">
                <i class="fas fa-arrow-down"></i>
                <h4>Dépôt</h4>
                <p>Créditer un compte</p>
            </a>
            <a href="retrait.php" class="action-card">
                <i class="fas fa-arrow-up"></i>
                <h4>Retrait</h4>
                <p>Débiter un compte</p>
            </a>
            <a href="verification.php" class="action-card">
                <i class="fas fa-search"></i>
                <h4>Vérification</h4>
                <p>Consulter un compte</p>
            </a>
        </div> -->
    </div>
</body>
</html>