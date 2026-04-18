<?php
require_once '../config/connexion.php';
session_start();

// Vérifier l'authentification et le rôle
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Récupération des statistiques globales
try {
    // Statistiques générales avec total des retraits
    $stats = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM utilisateurs WHERE actif = 1) as total_utilisateurs,
            (SELECT COUNT(*) FROM clients) as total_clients,
            (SELECT COUNT(*) FROM comptes WHERE statut = 'actif') as total_comptes_actifs,
            (SELECT COUNT(*) FROM comptes WHERE statut = 'bloque') as total_comptes_bloques,
            (SELECT COALESCE(SUM(solde), 0) FROM comptes WHERE statut = 'actif') as total_depots,
            (SELECT COUNT(*) FROM transactions WHERE DATE(date_transaction) = CURDATE()) as transactions_jour,
            (SELECT COALESCE(SUM(montant), 0) FROM transactions WHERE type = 'depot' AND DATE(date_transaction) = CURDATE()) as depots_jour,
            (SELECT COALESCE(SUM(montant), 0) FROM transactions WHERE type = 'retrait' AND DATE(date_transaction) = CURDATE()) as retraits_jour,
            (SELECT COALESCE(SUM(montant), 0) FROM transactions WHERE type = 'retrait') as total_retraits,
            (SELECT COALESCE(SUM(montant), 0) FROM transactions WHERE type = 'depot') as total_depots_all,
            (SELECT COUNT(*) FROM transactions WHERE type = 'retrait') as nb_retraits,
            (SELECT COUNT(*) FROM transactions WHERE type = 'depot') as nb_depots
    ")->fetch();
    
    // Statistiques par succursale
    $stats_succursales = $pdo->query("
        SELECT 
            s.id,
            s.code,
            s.nom,
            s.adresse,
            s.telephone,
            s.email,
            (SELECT COUNT(*) FROM comptes c WHERE c.succursale_id = s.id AND c.statut = 'actif') as nb_comptes,
            (SELECT COALESCE(SUM(c.solde), 0) FROM comptes c WHERE c.succursale_id = s.id AND c.statut = 'actif') as total_soldes,
            (SELECT COUNT(*) FROM utilisateurs u WHERE u.succursale_id = s.id AND u.actif = 1) as nb_employes,
            (SELECT COALESCE(SUM(t.montant), 0) 
             FROM transactions t 
             JOIN comptes c2 ON t.compte_id = c2.id 
             WHERE c2.succursale_id = s.id AND t.type = 'retrait') as total_retrait,
            (SELECT COALESCE(SUM(t.montant), 0) 
             FROM transactions t 
             JOIN comptes c2 ON t.compte_id = c2.id 
             WHERE c2.succursale_id = s.id AND t.type = 'depot') as total_depot_succ,
            (SELECT COUNT(*) 
             FROM comptes c2 
             WHERE c2.succursale_id = s.id AND c2.statut = 'actif') as nb_comptes_actifs,
            (SELECT COUNT(*) 
             FROM comptes c2 
             WHERE c2.succursale_id = s.id AND c2.statut = 'bloque') as nb_comptes_bloques
        FROM succursales s
        WHERE s.actif = 1
        ORDER BY s.code
    ")->fetchAll();
    
    // Dernières transactions
    $dernieres_transactions = $pdo->query("
        SELECT 
            t.*,
            c.id_compte,
            CONCAT(cl.prenom, ' ', cl.nom) as client_nom,
            u.nom_complet as utilisateur,
            s.code as succursale_code
        FROM transactions t
        JOIN comptes c ON t.compte_id = c.id
        JOIN clients cl ON c.titulaire_principal_id = cl.id
        JOIN utilisateurs u ON t.utilisateur_id = u.id
        JOIN succursales s ON t.succursale_id = s.id
        ORDER BY t.date_transaction DESC
        LIMIT 10
    ")->fetchAll();
    
    // Derniers comptes créés
    $derniers_comptes = $pdo->query("
        SELECT 
            c.id_compte,
            c.solde,
            c.devise,
            c.date_creation,
            CONCAT(cl.prenom, ' ', cl.nom) as titulaire,
            tc.nom as type_compte_nom,
            s.code as succursale_code,
            u.nom_complet as cree_par
        FROM comptes c
        JOIN clients cl ON c.titulaire_principal_id = cl.id
        JOIN types_comptes tc ON c.type_compte_id = tc.id
        JOIN succursales s ON c.succursale_id = s.id
        JOIN utilisateurs u ON c.created_by = u.id
        ORDER BY c.created_at DESC
        LIMIT 5
    ")->fetchAll();
    
    // Données pour le graphique (transactions des 30 derniers jours)
    $graph_data = $pdo->query("
        SELECT 
            DATE(date_transaction) as jour,
            SUM(CASE WHEN type = 'depot' THEN montant ELSE 0 END) as depots,
            SUM(CASE WHEN type = 'retrait' THEN montant ELSE 0 END) as retraits
        FROM transactions
        WHERE date_transaction >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(date_transaction)
        ORDER BY jour ASC
    ")->fetchAll();
    
} catch (PDOException $e) {
    $error = "Erreur lors du chargement des statistiques : " . $e->getMessage();
    $stats = [
        'total_utilisateurs' => 0, 
        'total_clients' => 0, 
        'total_comptes_actifs' => 0, 
        'total_comptes_bloques' => 0, 
        'total_depots' => 0, 
        'transactions_jour' => 0, 
        'depots_jour' => 0, 
        'retraits_jour' => 0,
        'total_retraits' => 0,
        'total_depots_all' => 0,
        'nb_retraits' => 0,
        'nb_depots' => 0
    ];
    $stats_succursales = [];
    $dernieres_transactions = [];
    $derniers_comptes = [];
    $graph_data = [];
}

// Déterminer la page active
$currentPage = 'dashboard';
$pageTitle = 'Tableau de bord - S&P illico';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - S&P illico</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="../assets/css/common.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background: #f1f5f9; min-height: 100vh; }
        
        /* Animation pour les cartes */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .stat-card, .quick-card, .chart-card, .table-card {
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
        }
        
        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
        
        /* Main Content */
        .main-content { margin-left: 280px; padding: 24px 32px; min-height: 100vh; transition: all 0.3s; }
        
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 70px 16px 20px 16px; }
        }
        
        /* Top Bar */
        .top-bar { background: white; padding: 20px 28px; border-radius: 20px; margin-bottom: 28px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; gap: 15px; padding: 16px 20px; }
        }
        
        .page-title h1 { font-size: 24px; color: #0f172a; margin-bottom: 6px; font-weight: 700; }
        @media (max-width: 768px) { .page-title h1 { font-size: 20px; } }
        
        .page-title .breadcrumb { color: #64748b; font-size: 13px; }
        .breadcrumb a { color: #3b82f6; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        
        .top-actions { display: flex; align-items: center; gap: 20px; }
        @media (max-width: 768px) { .top-actions { width: 100%; justify-content: center; } }
        
        .date-display { background: #f1f5f9; padding: 8px 18px; border-radius: 30px; font-size: 13px; color: #475569; }
        .date-display i { margin-right: 8px; color: #3b82f6; }
        
        .logout-btn { background: #ef4444; color: white; padding: 8px 18px; border-radius: 12px; text-decoration: none; font-size: 13px; font-weight: 500; transition: all 0.2s; display: flex; align-items: center; gap: 8px; }
        .logout-btn:hover { background: #dc2626; transform: translateY(-2px); }
        
        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 28px; }
        @media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 640px) { .stats-grid { grid-template-columns: 1fr; } }
        
        .stat-card { background: white; padding: 24px; border-radius: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); transition: all 0.3s; position: relative; overflow: hidden; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px -8px rgba(0,0,0,0.12); }
        
        .stat-card .icon { width: 52px; height: 52px; border-radius: 16px; display: flex; align-items: center; justify-content: center; margin-bottom: 18px; font-size: 24px; }
        .stat-card.primary .icon { background: #dbeafe; color: #2563eb; }
        .stat-card.success .icon { background: #dcfce7; color: #16a34a; }
        .stat-card.warning .icon { background: #fef3c7; color: #d97706; }
        .stat-card.info .icon { background: #e0e7ff; color: #4f46e5; }
        
        .stat-card .value { font-size: 32px; font-weight: 800; color: #0f172a; margin-bottom: 6px; }
        @media (max-width: 480px) { .stat-card .value { font-size: 26px; } }
        
        .stat-card .label { color: #64748b; font-size: 13px; font-weight: 500; margin-bottom: 10px; }
        .stat-card .trend { font-size: 12px; display: flex; align-items: center; gap: 6px; padding-top: 8px; border-top: 1px solid #e2e8f0; }
        .trend.up { color: #16a34a; }
        .trend.down { color: #dc2626; }
        
        /* Quick Stats Row */
        .quick-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 28px; }
        @media (max-width: 768px) { .quick-stats { grid-template-columns: 1fr; } }
        
        .quick-card { background: white; padding: 20px; border-radius: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); transition: all 0.3s; }
        .quick-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px -6px rgba(0,0,0,0.1); }
        .quick-card h4 { color: #64748b; font-size: 13px; font-weight: 600; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 8px; }
        .quick-card .amount { font-size: 28px; font-weight: 800; color: #0f172a; }
        @media (max-width: 480px) { .quick-card .amount { font-size: 22px; } }
        
        /* Charts Section */
        .charts-section { display: grid; grid-template-columns: 1.5fr 1fr; gap: 24px; margin-bottom: 28px; }
        @media (max-width: 1024px) { .charts-section { grid-template-columns: 1fr; } }
        
        .chart-card { background: white; padding: 24px; border-radius: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        .chart-card h3 { color: #0f172a; font-size: 16px; font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .chart-container { height: 320px; position: relative; }
        @media (max-width: 768px) { .chart-container { height: 260px; } }
        
        /* Succursales Grid */
        .succursales-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; max-height: 400px; overflow-y: auto; padding-right: 8px; }
        .succursales-grid::-webkit-scrollbar { width: 5px; }
        .succursales-grid::-webkit-scrollbar-track { background: #e2e8f0; border-radius: 10px; }
        .succursales-grid::-webkit-scrollbar-thumb { background: #3b82f6; border-radius: 10px; }
        
        .succursale-item { background: #f8fafc; padding: 16px; border-radius: 14px; border: 1px solid #e2e8f0; transition: all 0.2s; }
        .succursale-item:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); border-color: #3b82f6; }
        .succursale-item .code { font-weight: 700; color: #0f172a; font-size: 15px; margin-bottom: 4px; display: flex; align-items: center; gap: 8px; }
        .succursale-item .nom { color: #64748b; font-size: 12px; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #e2e8f0; }
        .succursale-item .stats-row { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 8px; }
        .text-primary { color: #3b82f6; font-weight: 600; }
        .text-success { color: #16a34a; font-weight: 600; }
        .text-danger { color: #dc2626; font-weight: 600; }
        
        /* Tables Section */
        .tables-section { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 28px; }
        @media (max-width: 1024px) { .tables-section { grid-template-columns: 1fr; } }
        
        .table-card { background: white; border-radius: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); overflow: hidden; }
        .table-header { padding: 18px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .table-header h3 { color: #0f172a; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .table-header a { color: #3b82f6; text-decoration: none; font-size: 13px; font-weight: 500; transition: all 0.2s; }
        .table-header a:hover { color: #2563eb; text-decoration: underline; }
        
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 500px; }
        th { text-align: left; padding: 14px 16px; background: #f8fafc; color: #475569; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 14px 16px; border-bottom: 1px solid #e2e8f0; color: #1e293b; font-size: 13px; }
        tr:hover { background: #f8fafc; }
        
        /* Badges */
        .badge { padding: 4px 12px; border-radius: 30px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        
        /* Alert */
        .alert { padding: 14px 20px; border-radius: 14px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; animation: fadeInUp 0.3s ease; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        
        /* Loading spinner */
        .loading { display: inline-block; width: 20px; height: 20px; border: 2px solid #e2e8f0; border-top-color: #3b82f6; border-radius: 50%; animation: spin 0.6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        hr { margin: 8px 0; border-color: #e2e8f0; }
    </style>
</head>
<body>
<?php require_once '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1>Tableau de bord</h1>
                <div class="breadcrumb">
                    <a href="dashboard.php">Administration</a> / Vue d'ensemble
                </div>
            </div>
            <div class="top-actions">
                <div class="date-display">
                    <i class="fas fa-calendar-alt"></i>
                    <?= date('l d F Y') ?>
                </div>
                <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="icon"><i class="fas fa-users"></i></div>
                <div class="value"><?= number_format($stats['total_utilisateurs']) ?></div>
                <div class="label">Utilisateurs actifs</div>
                <div class="trend up"><i class="fas fa-user-check"></i> Tous les utilisateurs</div>
            </div>
            <div class="stat-card success">
                <div class="icon"><i class="fas fa-user"></i></div>
                <div class="value"><?= number_format($stats['total_clients']) ?></div>
                <div class="label">Clients enregistrés</div>
                <div class="trend up"><i class="fas fa-chart-line"></i> Base clientèle</div>
            </div>
            <div class="stat-card warning">
                <div class="icon"><i class="fas fa-credit-card"></i></div>
                <div class="value"><?= number_format($stats['total_comptes_actifs']) ?></div>
                <div class="label">Comptes actifs</div>
                <div class="trend up"><i class="fas fa-check-circle"></i> Opérationnels</div>
            </div>
            <div class="stat-card info">
                <div class="icon"><i class="fas fa-chart-line"></i></div>
                <div class="value"><?= number_format($stats['total_retraits'], 0, ',', ' ') ?> HTG</div>
                <div class="label">Total des retraits</div>
                <div class="trend down"><i class="fas fa-arrow-down"></i> <?= $stats['nb_retraits'] ?> opérations</div>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="quick-stats">
            <div class="quick-card">
                <h4><i class="fas fa-calendar-day"></i> Aujourd'hui</h4>
                <div class="amount"><?= number_format($stats['transactions_jour']) ?> transactions</div>
                <div style="display: flex; gap: 20px; margin-top: 14px; flex-wrap: wrap;">
                    <div><span style="color: #16a34a; font-weight: 600;">+<?= number_format($stats['depots_jour'], 0, ',', ' ') ?> HTG</span> <span style="color: #64748b; font-size: 12px;">dépôts</span></div>
                    <div><span style="color: #dc2626; font-weight: 600;">-<?= number_format($stats['retraits_jour'], 0, ',', ' ') ?> HTG</span> <span style="color: #64748b; font-size: 12px;">retraits</span></div>
                </div>
            </div>
            <div class="quick-card">
                <h4><i class="fas fa-arrow-down"></i> Total dépôts</h4>
                <div class="amount"><?= number_format($stats['total_depots_all'], 0, ',', ' ') ?> HTG</div>
                <div style="margin-top: 12px; color: #64748b; font-size: 13px;">
                    <i class="fas fa-chart-simple"></i> <?= $stats['nb_depots'] ?> opérations
                </div>
            </div>
            <div class="quick-card">
                <h4><i class="fas fa-ban"></i> Comptes bloqués</h4>
                <div class="amount"><?= number_format($stats['total_comptes_bloques']) ?></div>
                <div style="margin-top: 12px; color: #64748b; font-size: 13px;">
                    <?php if ($stats['total_comptes_bloques'] > 0): ?>
                        <i class="fas fa-exclamation-triangle" style="color: #f59e0b;"></i> Nécessite attention
                    <?php else: ?>
                        <i class="fas fa-check-circle" style="color: #10b981;"></i> Aucun compte bloqué
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="charts-section">
            <div class="chart-card">
                <h3><i class="fas fa-chart-line" style="color: #3b82f6;"></i> Évolution des transactions (7 derniers jours)</h3>
                <div class="chart-container">
                    <canvas id="transactionsChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h3><i class="fas fa-building" style="color: #3b82f6;"></i> Performance des succursales</h3>
                <div class="succursales-grid">
                    <?php foreach ($stats_succursales as $s): ?>
                    <div class="succursale-item">
                        <div class="code">
                            <i class="fas fa-building"></i> <?= htmlspecialchars($s['code']) ?>
                        </div>
                        <div class="nom"><?= htmlspecialchars($s['nom']) ?></div>
                        <div class="stats-row">
                            <span><i class="fas fa-credit-card"></i> Soldes:</span>
                            <strong class="text-primary"><?= number_format($s['total_soldes'], 0, ',', ' ') ?> HTG</strong>
                        </div>
                        <div class="stats-row">
                            <span><i class="fas fa-chart-line"></i> Comptes actifs:</span>
                            <strong><?= $s['nb_comptes_actifs'] ?></strong>
                        </div>
                        <div class="stats-row">
                            <span><i class="fas fa-ban"></i> Comptes bloqués:</span>
                            <strong class="text-danger"><?= $s['nb_comptes_bloques'] ?></strong>
                        </div>
                        <div class="stats-row">
                            <span><i class="fas fa-arrow-down text-success"></i> Dépôts:</span>
                            <strong><?= number_format($s['total_depot_succ'], 0, ',', ' ') ?> HTG</strong>
                        </div>
                        <div class="stats-row">
                            <span><i class="fas fa-arrow-up text-danger"></i> Retraits:</span>
                            <strong><?= number_format($s['total_retrait'], 0, ',', ' ') ?> HTG</strong>
                        </div>
                        <div class="stats-row">
                            <span><i class="fas fa-users"></i> Employés:</span>
                            <strong><?= $s['nb_employes'] ?></strong>
                        </div>
                        <hr>
                        <div class="stats-row">
                            <span><i class="fas fa-chart-simple"></i> Résultat net:</span>
                            <strong class="<?= ($s['total_depot_succ'] - $s['total_retrait']) >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= number_format($s['total_depot_succ'] - $s['total_retrait'], 0, ',', ' ') ?> HTG
                            </strong>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Tables Section -->
        <div class="tables-section">
            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-clock"></i> Dernières transactions</h3>
                    <a href="transactions.php">Voir tout <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Compte</th>
                                <th>Client</th>
                                <th>Type</th>
                                <th>Montant</th>
                                <th>Succ.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dernieres_transactions as $t): ?>
                            <tr>
                                <td><strong><?= $t['id_compte'] ?></strong></td>
                                <td><?= htmlspecialchars($t['client_nom']) ?></td>
                                <td>
                                    <span class="badge <?= $t['type'] == 'depot' ? 'badge-success' : 'badge-danger' ?>">
                                        <i class="fas fa-<?= $t['type'] == 'depot' ? 'arrow-down' : 'arrow-up' ?>"></i>
                                        <?= ucfirst($t['type']) ?>
                                    </span>
                                </td>
                                <td><strong><?= number_format($t['montant'], 2, ',', ' ') ?> HTG</strong></td>
                                <td><?= $t['succursale_code'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($dernieres_transactions)): ?>
                            <tr><td colspan="5" style="text-align: center; color: #64748b; padding: 40px;">Aucune transaction récente</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-user-plus"></i> Derniers comptes créés</h3>
                    <a href="comptes.php">Voir tout <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>N° Compte</th>
                                <th>Titulaire</th>
                                <th>Type</th>
                                <th>Solde</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($derniers_comptes as $c): ?>
                            <tr>
                                <td><strong><?= $c['id_compte'] ?></strong></td>
                                <td><?= htmlspecialchars($c['titulaire']) ?></td>
                                <td><span class="badge badge-info"><?= htmlspecialchars($c['type_compte_nom']) ?></span></td>
                                <td><strong><?= number_format($c['solde'], 2, ',', ' ') ?> <?= $c['devise'] ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($derniers_comptes)): ?>
                            <tr><td colspan="4" style="text-align: center; color: #64748b; padding: 40px;">Aucun compte récent</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Données pour le graphique
        const graphData = <?= json_encode($graph_data) ?>;
        
        // Préparer les données pour Chart.js
        const labels = graphData.map(d => {
            const date = new Date(d.jour);
            return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
        });
        
        const depots = graphData.map(d => parseFloat(d.depots) || 0);
        const retraits = graphData.map(d => parseFloat(d.retraits) || 0);
        
        // Créer le graphique avec des courbes élégantes
        const ctx = document.getElementById('transactionsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Dépôts',
                        data: depots,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.08)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#10b981',
                        pointBorderColor: 'white',
                        pointBorderWidth: 2,
                        pointHoverBorderWidth: 2
                    },
                    {
                        label: 'Retraits',
                        data: retraits,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.08)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#ef4444',
                        pointBorderColor: 'white',
                        pointBorderWidth: 2,
                        pointHoverBorderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 10,
                            padding: 15,
                            font: { size: 12, family: "'Inter', sans-serif" }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleColor: '#f1f5f9',
                        bodyColor: '#cbd5e1',
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                let value = context.raw;
                                return label + ': ' + new Intl.NumberFormat('fr-HT', { 
                                    style: 'currency', 
                                    currency: 'HTG',
                                    maximumFractionDigits: 0
                                }).format(value);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#e2e8f0',
                            drawBorder: false,
                            drawTicks: true
                        },
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('fr-HT', {
                                    style: 'currency',
                                    currency: 'HTG',
                                    maximumFractionDigits: 0,
                                    notation: 'compact'
                                }).format(value);
                            },
                            font: { size: 11 }
                        }
                    },
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            font: { size: 11 },
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                },
                elements: {
                    line: {
                        borderJoin: 'round'
                    }
                }
            }
        });
        
        // Animation des valeurs des cartes (comptage progressif)
        function animateValue(element, start, end, duration) {
            if (!element) return;
            const range = end - start;
            const increment = range / (duration / 16);
            let current = start;
            const timer = setInterval(() => {
                current += increment;
                if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
                    element.textContent = end.toLocaleString();
                    clearInterval(timer);
                } else {
                    element.textContent = Math.round(current).toLocaleString();
                }
            }, 16);
        }
        
        // Animer les valeurs des cartes
        const values = document.querySelectorAll('.stat-card .value, .quick-card .amount');
        values.forEach(el => {
            const text = el.textContent;
            const number = parseFloat(text.replace(/[^0-9]/g, ''));
            if (!isNaN(number)) {
                const isCurrency = text.includes('HTG');
                el.textContent = '0';
                setTimeout(() => {
                    animateValue(el, 0, number, 800);
                    if (isCurrency) {
                        setTimeout(() => {
                            if (el.textContent !== '0') {
                                el.textContent = el.textContent + ' HTG';
                            }
                        }, 800);
                    }
                }, 500);
            }
        });
    </script>
</body>
</html>