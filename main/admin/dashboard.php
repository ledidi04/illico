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
    // Statistiques générales
    $stats = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM utilisateurs WHERE actif = 1) as total_utilisateurs,
            (SELECT COUNT(*) FROM clients) as total_clients,
            (SELECT COUNT(*) FROM comptes WHERE statut = 'actif') as total_comptes_actifs,
            (SELECT COUNT(*) FROM comptes WHERE statut = 'bloque') as total_comptes_bloques,
            (SELECT COALESCE(SUM(solde), 0) FROM comptes WHERE statut = 'actif') as total_depots,
            (SELECT COUNT(*) FROM transactions WHERE DATE(date_transaction) = CURDATE()) as transactions_jour,
            (SELECT COALESCE(SUM(montant), 0) FROM transactions WHERE type = 'depot' AND DATE(date_transaction) = CURDATE()) as depots_jour,
            (SELECT COALESCE(SUM(montant), 0) FROM transactions WHERE type = 'retrait' AND DATE(date_transaction) = CURDATE()) as retraits_jour
    ")->fetch();
    
    // Statistiques par succursale
    $stats_succursales = $pdo->query("
        SELECT 
            s.code,
            s.nom,
            COUNT(DISTINCT c.id) as nb_comptes,
            COALESCE(SUM(c.solde), 0) as total_depots,
            COUNT(DISTINCT u.id) as nb_employes
        FROM succursales s
        LEFT JOIN comptes c ON s.id = c.succursale_id AND c.statut = 'actif'
        LEFT JOIN utilisateurs u ON s.id = u.succursale_id AND u.actif = 1
        GROUP BY s.id
        ORDER BY s.code
    ")->fetchAll();
    
    // Dernières transactions
    $dernieres_transactions = $pdo->query("
        SELECT 
            t.*,
            c.id_compte,
            CONCAT(cl.nom, ' ', cl.prenom) as client_nom,
            u.username as utilisateur,
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
            c.*,
            CONCAT(cl.nom, ' ', cl.prenom) as titulaire,
            tc.nom as type_compte_nom,
            s.code as succursale_code,
            u.username as cree_par
        FROM comptes c
        JOIN clients cl ON c.titulaire_principal_id = cl.id
        JOIN types_comptes tc ON c.type_compte_id = tc.id
        JOIN succursales s ON c.succursale_id = s.id
        JOIN utilisateurs u ON c.created_by = u.id
        ORDER BY c.created_at DESC
        LIMIT 5
    ")->fetchAll();
    
    // Données pour le graphique (transactions des 7 derniers jours)
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
    $stats = ['total_utilisateurs' => 0, 'total_clients' => 0, 'total_comptes_actifs' => 0, 'total_comptes_bloques' => 0, 'total_depots' => 0, 'transactions_jour' => 0, 'depots_jour' => 0, 'retraits_jour' => 0];
    $stats_succursales = [];
    $dernieres_transactions = [];
    $derniers_comptes = [];
    $graph_data = [];
}

// Récupérer les succursales pour l'affichage
$succursales = $pdo->query("SELECT * FROM succursales ORDER BY code")->fetchAll();

// Déterminer la page active
$currentPage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - S&P illico</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', 'Inter', sans-serif; background: #f1f5f9; display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 280px; background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%); color: white; padding: 24px 0; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 0 20px 24px; border-bottom: 1px solid #334155; margin-bottom: 24px; }
        .sidebar-header h2 { color: #3b82f6; font-size: 22px; display: flex; align-items: center; gap: 10px; }
        .sidebar-header h2 i { font-size: 28px; }
        .sidebar-header p { color: #94a3b8; font-size: 13px; margin-top: 8px; }
        .user-info-side { padding: 16px 20px; background: #1e293b; margin: 0 16px 20px; border-radius: 12px; }
        .user-info-side .avatar { width: 48px; height: 48px; background: #3b82f6; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; }
        .user-info-side .avatar i { font-size: 24px; }
        .user-info-side .name { font-weight: 600; margin-bottom: 4px; }
        .user-info-side .role { color: #3b82f6; font-size: 13px; }
        .user-info-side .succursale { color: #94a3b8; font-size: 12px; margin-top: 8px; }
        .nav-menu { padding: 0 12px; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #cbd5e1; text-decoration: none; border-radius: 10px; margin-bottom: 4px; transition: all 0.2s; }
        .nav-item i { width: 24px; font-size: 18px; }
        .nav-item:hover { background: #334155; color: white; }
        .nav-item.active { background: #3b82f6; color: white; }
        .nav-divider { height: 1px; background: #334155; margin: 16px 0; }
        
        /* Main Content */
        .main-content { margin-left: 280px; flex: 1; padding: 24px; }
        
        /* Top Bar */
        .top-bar { background: white; padding: 16px 24px; border-radius: 16px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .page-title h1 { font-size: 24px; color: #1e293b; margin-bottom: 4px; }
        .page-title .breadcrumb { color: #64748b; font-size: 14px; }
        .breadcrumb a { color: #3b82f6; text-decoration: none; }
        .top-actions { display: flex; align-items: center; gap: 20px; }
        .date-display { background: #f1f5f9; padding: 8px 16px; border-radius: 30px; font-size: 14px; color: #475569; }
        .date-display i { margin-right: 8px; color: #3b82f6; }
        .user-profile-top { display: flex; align-items: center; gap: 12px; }
        .user-profile-top img { width: 40px; height: 40px; border-radius: 50%; }
        .logout-btn { background: #ef4444; color: white; padding: 10px 18px; border-radius: 10px; text-decoration: none; font-size: 14px; transition: all 0.2s; }
        .logout-btn:hover { background: #dc2626; }
        
        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 24px; }
        .stat-card { background: white; padding: 24px; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.07); }
        .stat-card .icon { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; }
        .stat-card.primary .icon { background: #dbeafe; color: #2563eb; }
        .stat-card.success .icon { background: #dcfce7; color: #16a34a; }
        .stat-card.warning .icon { background: #fef3c7; color: #d97706; }
        .stat-card.danger .icon { background: #fee2e2; color: #dc2626; }
        .stat-card .value { font-size: 32px; font-weight: 700; color: #1e293b; margin-bottom: 4px; }
        .stat-card .label { color: #64748b; font-size: 14px; }
        .stat-card .trend { margin-top: 12px; font-size: 13px; display: flex; align-items: center; gap: 6px; }
        .trend.up { color: #16a34a; }
        .trend.down { color: #dc2626; }
        
        /* Quick Stats Row */
        .quick-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 24px; }
        .quick-card { background: white; padding: 20px; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .quick-card h4 { color: #64748b; font-size: 13px; font-weight: 500; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        .quick-card .amount { font-size: 24px; font-weight: 700; color: #1e293b; }
        
        /* Charts Section */
        .charts-section { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 24px; }
        .chart-card { background: white; padding: 20px; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .chart-card h3 { color: #1e293b; font-size: 16px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .chart-container { height: 250px; position: relative; }
        
        /* Tables Section */
        .tables-section { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
        .table-card { background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
        .table-header { padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .table-header h3 { color: #1e293b; font-size: 16px; }
        .table-header a { color: #3b82f6; text-decoration: none; font-size: 13px; }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px 16px; background: #f8fafc; color: #475569; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 12px 16px; border-bottom: 1px solid #e2e8f0; color: #1e293b; font-size: 13px; }
        tr:hover { background: #f8fafc; }
        
        /* Badges */
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        
        /* Succursales Grid */
        .succursales-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-top: 16px; }
        .succursale-item { background: #f8fafc; padding: 16px; border-radius: 12px; }
        .succursale-item .code { font-weight: 700; color: #1e293b; margin-bottom: 4px; }
        .succursale-item .nom { color: #64748b; font-size: 13px; margin-bottom: 12px; }
        .succursale-item .stats-row { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px; }
        
        /* Alert */
        .alert { padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-building-columns"></i> S&P illico</h2>
            <p>Banque Communautaire</p>
        </div>
        <div class="user-info-side">
            <div class="avatar">
                <i class="fas fa-user-cog"></i>
            </div>
            <div class="name"><?= htmlspecialchars($_SESSION['nom_complet']) ?></div>
            <div class="role"><i class="fas fa-shield"></i> Administrateur</div>
            <div class="succursale"><i class="fas fa-building"></i> <?= htmlspecialchars($_SESSION['succursale_nom']) ?></div>
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
    
    <!-- Main Content -->
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
        
        <!-- Message d'erreur éventuel -->
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
                <div class="trend up"><i class="fas fa-arrow-up"></i> +12% ce mois</div>
            </div>
            <div class="stat-card success">
                <div class="icon"><i class="fas fa-user"></i></div>
                <div class="value"><?= number_format($stats['total_clients']) ?></div>
                <div class="label">Clients enregistrés</div>
                <div class="trend up"><i class="fas fa-arrow-up"></i> +8% ce mois</div>
            </div>
            <div class="stat-card warning">
                <div class="icon"><i class="fas fa-credit-card"></i></div>
                <div class="value"><?= number_format($stats['total_comptes_actifs']) ?></div>
                <div class="label">Comptes actifs</div>
                <div class="trend up"><i class="fas fa-arrow-up"></i> +5% ce mois</div>
            </div>
            <div class="stat-card danger">
                <div class="icon"><i class="fas fa-money-bill"></i></div>
                <div class="value"><?= number_format($stats['total_depots'], 0, ',', ' ') ?> HTG</div>
                <div class="label">Total des dépôts</div>
                <div class="trend up"><i class="fas fa-arrow-up"></i> +15% ce mois</div>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="quick-stats">
            <div class="quick-card">
                <h4><i class="fas fa-calendar-day"></i> Aujourd'hui</h4>
                <div class="amount"><?= number_format($stats['transactions_jour']) ?> transactions</div>
                <div style="display: flex; gap: 20px; margin-top: 12px;">
                    <div><span style="color: #16a34a;">+<?= number_format($stats['depots_jour'], 0, ',', ' ') ?> HTG</span> dépôts</div>
                    <div><span style="color: #dc2626;">-<?= number_format($stats['retraits_jour'], 0, ',', ' ') ?> HTG</span> retraits</div>
                </div>
            </div>
            <div class="quick-card">
                <h4><i class="fas fa-ban"></i> Comptes bloqués</h4>
                <div class="amount"><?= number_format($stats['total_comptes_bloques']) ?></div>
                <div style="margin-top: 12px; color: #64748b; font-size: 13px;">
                    <?= $stats['total_comptes_bloques'] > 0 ? 'Nécessite attention' : 'Aucun compte bloqué' ?>
                </div>
            </div>
            <div class="quick-card">
                <h4><i class="fas fa-building"></i> Succursales</h4>
                <div class="amount"><?= count($succursales) ?></div>
                <div style="margin-top: 12px; color: #64748b; font-size: 13px;">
                    Interconnectées et opérationnelles
                </div>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="charts-section">
            <div class="chart-card">
                <h3><i class="fas fa-chart-line" style="color: #3b82f6;"></i> Transactions (7 derniers jours)</h3>
                <div class="chart-container">
                    <canvas id="transactionsChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h3><i class="fas fa-building" style="color: #3b82f6;"></i> Répartition par succursale</h3>
                <div class="succursales-grid">
                    <?php foreach ($stats_succursales as $s): ?>
                    <div class="succursale-item">
                        <div class="code"><?= htmlspecialchars($s['code']) ?></div>
                        <div class="nom"><?= htmlspecialchars($s['nom']) ?></div>
                        <div class="stats-row">
                            <span>Comptes:</span>
                            <strong><?= $s['nb_comptes'] ?></strong>
                        </div>
                        <div class="stats-row">
                            <span>Dépôts:</span>
                            <strong><?= number_format($s['total_depots'], 0, ',', ' ') ?> HTG</strong>
                        </div>
                        <div class="stats-row">
                            <span>Employés:</span>
                            <strong><?= $s['nb_employes'] ?></strong>
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
                    <a href="#">Voir tout <i class="fas fa-arrow-right"></i></a>
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
                            <tr><td colspan="5" style="text-align: center; color: #64748b;">Aucune transaction</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-user-plus"></i> Derniers comptes créés</h3>
                    <a href="#">Voir tout <i class="fas fa-arrow-right"></i></a>
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
                                <td><strong><?= number_format($c['solde'], 2, ',', ' ') ?> HTG</strong></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($derniers_comptes)): ?>
                            <tr><td colspan="4" style="text-align: center; color: #64748b;">Aucun compte</td></tr>
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
            return date.toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric' });
        });
        
        const depots = graphData.map(d => parseFloat(d.depots) || 0);
        const retraits = graphData.map(d => parseFloat(d.retraits) || 0);
        
        // Créer le graphique
        const ctx = document.getElementById('transactionsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Dépôts',
                        data: depots,
                        borderColor: '#16a34a',
                        backgroundColor: 'rgba(22, 163, 74, 0.1)',
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Retraits',
                        data: retraits,
                        borderColor: '#dc2626',
                        backgroundColor: 'rgba(220, 38, 38, 0.1)',
                        tension: 0.3,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 10
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + 
                                       new Intl.NumberFormat('fr-HT', { 
                                           style: 'currency', 
                                           currency: 'HTG',
                                           maximumFractionDigits: 0
                                       }).format(context.raw);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('fr-HT', {
                                    style: 'currency',
                                    currency: 'HTG',
                                    maximumFractionDigits: 0
                                }).format(value);
                            }
                        }
                    }
                }
            }
        });
        
        // Animation des cartes statistiques
        document.querySelectorAll('.stat-card').forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'all 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    </script>
</body>
</html>