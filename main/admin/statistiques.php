<?php
require_once '../config/connexion.php';
session_start();

// Vérifier l'authentification et le rôle
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

try {
    // ========== STATISTIQUES TRANSACTIONS ==========
    
    // Transactions du jour
    $tx_jour = $pdo->query("
        SELECT 
            COUNT(*) as nb_transactions,
            COALESCE(SUM(CASE WHEN type = 'depot' THEN montant ELSE 0 END), 0) as total_depots,
            COALESCE(SUM(CASE WHEN type = 'retrait' THEN montant ELSE 0 END), 0) as total_retraits
        FROM transactions 
        WHERE DATE(date_transaction) = CURDATE()
    ")->fetch();
    
    // Transactions de la semaine
    $tx_semaine = $pdo->query("
        SELECT 
            COUNT(*) as nb_transactions,
            COALESCE(SUM(CASE WHEN type = 'depot' THEN montant ELSE 0 END), 0) as total_depots,
            COALESCE(SUM(CASE WHEN type = 'retrait' THEN montant ELSE 0 END), 0) as total_retraits
        FROM transactions 
        WHERE YEARWEEK(date_transaction, 1) = YEARWEEK(CURDATE(), 1)
    ")->fetch();
    
    // Transactions du mois
    $tx_mois = $pdo->query("
        SELECT 
            COUNT(*) as nb_transactions,
            COALESCE(SUM(CASE WHEN type = 'depot' THEN montant ELSE 0 END), 0) as total_depots,
            COALESCE(SUM(CASE WHEN type = 'retrait' THEN montant ELSE 0 END), 0) as total_retraits
        FROM transactions 
        WHERE MONTH(date_transaction) = MONTH(CURDATE()) 
          AND YEAR(date_transaction) = YEAR(CURDATE())
    ")->fetch();
    
    // Transactions de l'année
    $tx_annee = $pdo->query("
        SELECT 
            COUNT(*) as nb_transactions,
            COALESCE(SUM(CASE WHEN type = 'depot' THEN montant ELSE 0 END), 0) as total_depots,
            COALESCE(SUM(CASE WHEN type = 'retrait' THEN montant ELSE 0 END), 0) as total_retraits
        FROM transactions 
        WHERE YEAR(date_transaction) = YEAR(CURDATE())
    ")->fetch();
    
    // ========== STATISTIQUES CLIENTS ==========
    $total_clients = $pdo->query("SELECT COUNT(*) as total FROM clients")->fetch()['total'];
    $clients_jour = $pdo->query("SELECT COUNT(*) as total FROM clients WHERE DATE(created_at) = CURDATE()")->fetch()['total'];
    $clients_semaine = $pdo->query("SELECT COUNT(*) as total FROM clients WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)")->fetch()['total'];
    $clients_mois = $pdo->query("SELECT COUNT(*) as total FROM clients WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetch()['total'];
    $clients_annee = $pdo->query("SELECT COUNT(*) as total FROM clients WHERE YEAR(created_at) = YEAR(CURDATE())")->fetch()['total'];
    
    // ========== STATISTIQUES PERSONNEL ==========
    $personnel = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
            SUM(CASE WHEN role = 'secretaire' THEN 1 ELSE 0 END) as secretaires,
            SUM(CASE WHEN role = 'caissier' THEN 1 ELSE 0 END) as caissiers,
            SUM(CASE WHEN actif = 1 THEN 1 ELSE 0 END) as actifs
        FROM utilisateurs
    ")->fetch();
    
    // Personnel par succursale
    $personnel_succursale = $pdo->query("
        SELECT s.code, s.nom, COUNT(u.id) as total
        FROM succursales s
        LEFT JOIN utilisateurs u ON s.id = u.succursale_id
        GROUP BY s.id
    ")->fetchAll();
    
    // ========== STATISTIQUES COMPTES ==========
    $comptes_stats = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN statut = 'actif' THEN 1 ELSE 0 END) as actifs,
            SUM(CASE WHEN statut = 'bloque' THEN 1 ELSE 0 END) as bloques,
            COALESCE(SUM(solde), 0) as total_solde
        FROM comptes
    ")->fetch();
    
    // Comptes par type
    $comptes_types = $pdo->query("
        SELECT tc.nom, COUNT(c.id) as nb_comptes, COALESCE(SUM(c.solde), 0) as total_solde
        FROM types_comptes tc
        LEFT JOIN comptes c ON tc.id = c.type_compte_id AND c.statut = 'actif'
        GROUP BY tc.id
    ")->fetchAll();
    
    // Données graphique mensuel
    $graph_mensuel = $pdo->query("
        SELECT 
            DATE_FORMAT(date_transaction, '%b') as mois,
            COUNT(*) as nb_transactions
        FROM transactions
        WHERE date_transaction >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(date_transaction, '%Y-%m')
        ORDER BY MIN(date_transaction)
    ")->fetchAll();
    
} catch (PDOException $e) {
    $error = "Erreur : " . $e->getMessage();
}

$currentPage = 'statistiques';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques - S&P illico</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', 'Inter', sans-serif; background: #f1f5f9; display: flex; min-height: 100vh; }
        
        .sidebar { width: 280px; background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%); color: white; padding: 24px 0; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 0 20px 24px; border-bottom: 1px solid #334155; margin-bottom: 24px; }
        .sidebar-header h2 { color: #3b82f6; font-size: 22px; display: flex; align-items: center; gap: 10px; }
        .sidebar-header p { color: #94a3b8; font-size: 13px; margin-top: 8px; }
        .user-info-side { padding: 16px 20px; background: #1e293b; margin: 0 16px 20px; border-radius: 12px; }
        .user-info-side .avatar { width: 48px; height: 48px; background: #3b82f6; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; }
        .user-info-side .avatar i { font-size: 24px; }
        .user-info-side .name { font-weight: 600; margin-bottom: 4px; }
        .user-info-side .role { color: #3b82f6; font-size: 13px; }
        .nav-menu { padding: 0 12px; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #cbd5e1; text-decoration: none; border-radius: 10px; margin-bottom: 4px; transition: all 0.2s; }
        .nav-item i { width: 24px; }
        .nav-item:hover { background: #334155; color: white; }
        .nav-item.active { background: #3b82f6; color: white; }
        .nav-divider { height: 1px; background: #334155; margin: 16px 0; }
        
        .main-content { margin-left: 280px; flex: 1; padding: 24px; }
        
        .top-bar { background: white; padding: 16px 24px; border-radius: 16px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .page-title h1 { font-size: 24px; color: #1e293b; }
        .breadcrumb { color: #64748b; font-size: 14px; }
        .breadcrumb a { color: #3b82f6; text-decoration: none; }
        .logout-btn { background: #ef4444; color: white; padding: 10px 18px; border-radius: 10px; text-decoration: none; }
        
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 24px; }
        .kpi-card { background: white; padding: 24px; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .kpi-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; }
        .kpi-icon.blue { background: #dbeafe; color: #2563eb; }
        .kpi-icon.green { background: #dcfce7; color: #16a34a; }
        .kpi-icon.orange { background: #fef3c7; color: #d97706; }
        .kpi-icon.purple { background: #f3e8ff; color: #9333ea; }
        .kpi-value { font-size: 32px; font-weight: 700; color: #1e293b; margin-bottom: 4px; }
        .kpi-label { color: #64748b; font-size: 14px; }
        .kpi-trend { margin-top: 12px; font-size: 13px; }
        .trend-up { color: #16a34a; }
        .trend-down { color: #dc2626; }
        
        .stats-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
        .stats-card { background: white; padding: 20px; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .stats-card h3 { color: #1e293b; font-size: 16px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .stats-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #e2e8f0; }
        .stats-item:last-child { border-bottom: none; }
        .stats-label { color: #64748b; }
        .stats-value { font-weight: 600; color: #1e293b; }
        
        .chart-card { background: white; padding: 20px; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .chart-container { height: 300px; }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-building-columns"></i> S&P illico</h2>
            <p>Banque Communautaire</p>
        </div>
        <div class="user-info-side">
            <div class="avatar"><i class="fas fa-user-cog"></i></div>
            <div class="name"><?= htmlspecialchars($_SESSION['nom_complet']) ?></div>
            <div class="role"><i class="fas fa-shield"></i> Administrateur</div>
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-item active"><i class="fas fa-gauge"></i> Tableau de bord</a>
            <a href="utilisateurs.php" class="nav-item"><i class="fas fa-users-gear"></i> Utilisateurs</a>
            <a href="#" class="nav-item"><i class="fas fa-users"></i> Clients</a>
            <a href="#" class="nav-item"><i class="fas fa-credit-card"></i> Comptes</a>
            <div class="nav-divider"></div>
            <a href="statistiques.php" class="nav-item"><i class="fas fa-chart-pie"></i> Statistiques</a>
            <a href="rapports.php" class="nav-item"><i class="fas fa-file-pdf"></i> Rapports</a>
            
            <div class="nav-divider"></div>
          
            <a href="../logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>Statistiques</h1>
                <div class="breadcrumb"><a href="dashboard.php">Administration</a> / Statistiques</div>
            </div>
            <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </div>
        
        <!-- KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-icon blue"><i class="fas fa-exchange-alt"></i></div>
                <div class="kpi-value"><?= number_format($tx_jour['nb_transactions']) ?></div>
                <div class="kpi-label">Transactions aujourd'hui</div>
                <div class="kpi-trend trend-up"><i class="fas fa-arrow-up"></i> +12% vs hier</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon green"><i class="fas fa-users"></i></div>
                <div class="kpi-value"><?= number_format($total_clients) ?></div>
                <div class="kpi-label">Total clients</div>
                <div class="kpi-trend trend-up"><i class="fas fa-arrow-up"></i> +<?= $clients_mois ?> ce mois</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon orange"><i class="fas fa-user-tie"></i></div>
                <div class="kpi-value"><?= number_format($personnel['total']) ?></div>
                <div class="kpi-label">Personnel total</div>
                <div class="kpi-trend"><?= $personnel['actifs'] ?> actifs</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon purple"><i class="fas fa-credit-card"></i></div>
                <div class="kpi-value"><?= number_format($comptes_stats['actifs']) ?></div>
                <div class="kpi-label">Comptes actifs</div>
                <div class="kpi-trend"><?= number_format($comptes_stats['total_solde'], 0, ',', ' ') ?> HTG</div>
            </div>
        </div>
        
        <!-- Statistiques détaillées -->
        <div class="stats-row">
            <!-- Transactions -->
            <div class="stats-card">
                <h3><i class="fas fa-chart-bar" style="color:#3b82f6;"></i> Volume des transactions</h3>
                <div class="stats-item">
                    <span class="stats-label">Aujourd'hui</span>
                    <span class="stats-value"><?= number_format($tx_jour['nb_transactions']) ?> (<?= number_format($tx_jour['total_depots'] + $tx_jour['total_retraits'], 0, ',', ' ') ?> HTG)</span>
                </div>
                <div class="stats-item">
                    <span class="stats-label">Cette semaine</span>
                    <span class="stats-value"><?= number_format($tx_semaine['nb_transactions']) ?> (<?= number_format($tx_semaine['total_depots'] + $tx_semaine['total_retraits'], 0, ',', ' ') ?> HTG)</span>
                </div>
                <div class="stats-item">
                    <span class="stats-label">Ce mois</span>
                    <span class="stats-value"><?= number_format($tx_mois['nb_transactions']) ?> (<?= number_format($tx_mois['total_depots'] + $tx_mois['total_retraits'], 0, ',', ' ') ?> HTG)</span>
                </div>
                <div class="stats-item">
                    <span class="stats-label">Cette année</span>
                    <span class="stats-value"><?= number_format($tx_annee['nb_transactions']) ?> (<?= number_format($tx_annee['total_depots'] + $tx_annee['total_retraits'], 0, ',', ' ') ?> HTG)</span>
                </div>
                <div class="stats-item">
                    <span class="stats-label"><i class="fas fa-arrow-down" style="color:#16a34a;"></i> Dépôts (année)</span>
                    <span class="stats-value" style="color:#16a34a;"><?= number_format($tx_annee['total_depots'], 0, ',', ' ') ?> HTG</span>
                </div>
                <div class="stats-item">
                    <span class="stats-label"><i class="fas fa-arrow-up" style="color:#dc2626;"></i> Retraits (année)</span>
                    <span class="stats-value" style="color:#dc2626;"><?= number_format($tx_annee['total_retraits'], 0, ',', ' ') ?> HTG</span>
                </div>
            </div>
            
            <!-- Clients & Personnel -->
            <div class="stats-card">
                <h3><i class="fas fa-users" style="color:#3b82f6;"></i> Clients & Personnel</h3>
                <div class="stats-item">
                    <span class="stats-label">Total clients</span>
                    <span class="stats-value"><?= number_format($total_clients) ?></span>
                </div>
                <div class="stats-item">
                    <span class="stats-label">Nouveaux clients (jour)</span>
                    <span class="stats-value">+<?= $clients_jour ?></span>
                </div>
                <div class="stats-item">
                    <span class="stats-label">Nouveaux clients (semaine)</span>
                    <span class="stats-value">+<?= $clients_semaine ?></span>
                </div>
                <div class="stats-item">
                    <span class="stats-label">Nouveaux clients (mois)</span>
                    <span class="stats-value">+<?= $clients_mois ?></span>
                </div>
                <div class="stats-item">
                    <span class="stats-label">Nouveaux clients (année)</span>
                    <span class="stats-value">+<?= $clients_annee ?></span>
                </div>
                <div class="stats-item">
                    <span class="stats-label">Personnel total</span>
                    <span class="stats-value"><?= $personnel['total'] ?></span>
                </div>
                <div class="stats-item">
                    <span class="stats-label"><i class="fas fa-crown"></i> Admins</span>
                    <span class="stats-value"><?= $personnel['admins'] ?></span>
                </div>
                <div class="stats-item">
                    <span class="stats-label"><i class="fas fa-user-tie"></i> Secrétaires</span>
                    <span class="stats-value"><?= $personnel['secretaires'] ?></span>
                </div>
                <div class="stats-item">
                    <span class="stats-label"><i class="fas fa-cash-register"></i> Caissiers</span>
                    <span class="stats-value"><?= $personnel['caissiers'] ?></span>
                </div>
            </div>
        </div>
        
        <!-- Comptes par type -->
        <div class="stats-card" style="margin-bottom: 24px;">
            <h3><i class="fas fa-credit-card" style="color:#3b82f6;"></i> Comptes par type</h3>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                <?php foreach ($comptes_types as $type): ?>
                <div style="text-align: center; padding: 20px; background: #f8fafc; border-radius: 12px;">
                    <div style="font-size: 24px; font-weight: 700; color: #1e293b;"><?= number_format($type['nb_comptes']) ?></div>
                    <div style="color: #64748b; margin: 8px 0;"><?= htmlspecialchars($type['nom']) ?></div>
                    <div style="font-size: 13px; color: #3b82f6;"><?= number_format($type['total_solde'], 0, ',', ' ') ?> HTG</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Graphique -->
        <div class="chart-card">
            <h3 style="margin-bottom: 20px;"><i class="fas fa-chart-line" style="color:#3b82f6;"></i> Évolution des transactions (6 derniers mois)</h3>
            <div class="chart-container">
                <canvas id="transactionsChart"></canvas>
            </div>
        </div>
    </div>
    
    <script>
        const graphData = <?= json_encode($graph_mensuel) ?>;
        
        new Chart(document.getElementById('transactionsChart'), {
            type: 'line',
            data: {
                labels: graphData.map(d => d.mois),
                datasets: [{
                    label: 'Nombre de transactions',
                    data: graphData.map(d => d.nb_transactions),
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
    </script>
</body>
</html>