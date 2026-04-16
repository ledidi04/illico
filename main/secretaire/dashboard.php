<?php
require_once '../config/connexion.php';
session_start();

// Vérifier l'authentification et le rôle
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretaire') {
    header('Location: ../index.php');
    exit;
}

// Récupération des statistiques
try {
    // Statistiques générales
    $total_clients = $pdo->query("SELECT COUNT(*) as total FROM clients")->fetch()['total'];
    
    $total_comptes = $pdo->query("
        SELECT COUNT(*) as total FROM comptes WHERE statut = 'actif'
    ")->fetch()['total'];
    
    $total_depots = $pdo->query("
        SELECT COALESCE(SUM(solde), 0) as total FROM comptes WHERE statut = 'actif'
    ")->fetch()['total'];
    
    // Comptes créés aujourd'hui
    $comptes_jour = $pdo->query("
        SELECT COUNT(*) as total FROM comptes WHERE DATE(created_at) = CURDATE()
    ")->fetch()['total'];
    
    // Clients créés ce mois
    $clients_mois = $pdo->query("
        SELECT COUNT(*) as total FROM clients 
        WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
    ")->fetch()['total'];
    
    // Derniers comptes créés
    $derniers_comptes = $pdo->query("
        SELECT c.id_compte, c.date_creation, c.solde, c.statut,
               tc.nom as type_compte_nom,
               CONCAT(cl.nom, ' ', cl.prenom) as titulaire
        FROM comptes c
        JOIN types_comptes tc ON c.type_compte_id = tc.id
        JOIN clients cl ON c.titulaire_principal_id = cl.id
        ORDER BY c.created_at DESC
        LIMIT 5
    ")->fetchAll();
    
    // Derniers clients créés
    $derniers_clients = $pdo->query("
        SELECT id_client, nom, prenom, telephone, created_at
        FROM clients
        ORDER BY created_at DESC
        LIMIT 5
    ")->fetchAll();
    
    // Transactions du jour
    $transactions_jour = $pdo->query("
        SELECT 
            COUNT(*) as nb_transactions,
            COALESCE(SUM(CASE WHEN type = 'depot' THEN montant ELSE 0 END), 0) as depots,
            COALESCE(SUM(CASE WHEN type = 'retrait' THEN montant ELSE 0 END), 0) as retraits
        FROM transactions 
        WHERE DATE(date_transaction) = CURDATE()
    ")->fetch();
    
    // Comptes par type
    $comptes_par_type = $pdo->query("
        SELECT tc.nom, COUNT(c.id) as nb_comptes
        FROM types_comptes tc
        LEFT JOIN comptes c ON tc.id = c.type_compte_id AND c.statut = 'actif'
        GROUP BY tc.id
    ")->fetchAll();
    
} catch (PDOException $e) {
    $error = "Erreur lors du chargement des statistiques.";
    $total_clients = $total_comptes = $total_depots = $comptes_jour = $clients_mois = 0;
    $derniers_comptes = $derniers_clients = [];
    $transactions_jour = ['nb_transactions' => 0, 'depots' => 0, 'retraits' => 0];
    $comptes_par_type = [];
}

$currentPage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - S&P illico</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        .user-info-side .avatar { width: 48px; height: 48px; background: #10b981; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; }
        .user-info-side .avatar i { font-size: 24px; }
        .user-info-side .name { font-weight: 600; margin-bottom: 4px; }
        .user-info-side .role { color: #10b981; font-size: 13px; }
        .user-info-side .succursale { color: #94a3b8; font-size: 12px; margin-top: 8px; }
        .nav-menu { padding: 0 12px; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #cbd5e1; text-decoration: none; border-radius: 10px; margin-bottom: 4px; transition: all 0.2s; }
        .nav-item i { width: 24px; font-size: 18px; }
        .nav-item:hover { background: #334155; color: white; }
        .nav-item.active { background: #10b981; color: white; }
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
        .date-display i { margin-right: 8px; color: #10b981; }
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
        .stat-card.info .icon { background: #e0f2fe; color: #0284c7; }
        .stat-card .value { font-size: 32px; font-weight: 700; color: #1e293b; margin-bottom: 4px; }
        .stat-card .label { color: #64748b; font-size: 14px; }
        .stat-card .sub { margin-top: 12px; font-size: 13px; color: #64748b; }
        
        /* Quick Actions */
        .quick-actions { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 24px; }
        .action-card { background: white; padding: 20px; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); text-align: center; text-decoration: none; transition: all 0.2s; }
        .action-card:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .action-card i { font-size: 28px; color: #10b981; margin-bottom: 12px; }
        .action-card h4 { color: #1e293b; font-size: 15px; margin-bottom: 4px; }
        .action-card p { color: #64748b; font-size: 12px; }
        
        /* Tables Section */
        .tables-section { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
        .table-card { background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
        .table-header { padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .table-header h3 { color: #1e293b; font-size: 16px; }
        .table-header a { color: #10b981; text-decoration: none; font-size: 13px; }
        .table-container { overflow-x: auto; padding: 0 20px 20px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px 0; color: #64748b; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; }
        td { padding: 12px 0; border-bottom: 1px solid #e2e8f0; color: #1e293b; font-size: 13px; }
        tr:last-child td { border-bottom: none; }
        
        /* Badges */
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        
        /* Résumé transactions */
        .transactions-summary { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .summary-row { display: flex; justify-content: space-between; align-items: center; }
        .summary-item { text-align: center; flex: 1; }
        .summary-value { font-size: 24px; font-weight: 700; color: #1e293b; }
        .summary-label { color: #64748b; font-size: 13px; }
        
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
                <i class="fas fa-user-tie"></i>
            </div>
            <div class="name"><?= htmlspecialchars($_SESSION['nom_complet']) ?></div>
            <div class="role"><i class="fas fa-shield"></i> Secrétaire</div>
            <div class="succursale"><i class="fas fa-building"></i> <?= htmlspecialchars($_SESSION['succursale_nom'] ?? 'Succursale') ?></div>
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-item active"><i class="fas fa-gauge"></i> Tableau de bord</a>
            <a href="creer_compte.php" class="nav-item"><i class="fas fa-user-plus"></i> Créer compte</a>
            <a href="liste_clients.php" class="nav-item"><i class="fas fa-users"></i> Liste clients</a>
            <a href="ajouter_client.php" class="nav-item"><i class="fas fa-user"></i> Ajouter client</a>
            <div class="nav-divider"></div>
            <a href="depot.php" class="nav-item"><i class="fas fa-arrow-down"></i> Dépôt</a>
            <a href="retrait.php" class="nav-item"><i class="fas fa-arrow-up"></i> Retrait</a>
            <a href="verification.php" class="nav-item"><i class="fas fa-search"></i> Vérification</a>
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
                    <a href="dashboard.php">Accueil</a> / Vue d'ensemble
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
        
        <!-- Message de bienvenue -->
        <div style="margin-bottom: 24px;">
            <h2 style="color: #1e293b; font-size: 20px;">
                <i class="fas fa-hand-wave" style="color: #10b981;"></i> 
                Bienvenue, <?= htmlspecialchars(explode(' ', $_SESSION['nom_complet'])[0]) ?> !
            </h2>
            <p style="color: #64748b;">Voici un aperçu de votre activité aujourd'hui.</p>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="icon"><i class="fas fa-users"></i></div>
                <div class="value"><?= number_format($total_clients) ?></div>
                <div class="label">Total clients</div>
                <div class="sub"><i class="fas fa-user-plus" style="color: #10b981;"></i> +<?= $clients_mois ?> ce mois</div>
            </div>
            <div class="stat-card success">
                <div class="icon"><i class="fas fa-credit-card"></i></div>
                <div class="value"><?= number_format($total_comptes) ?></div>
                <div class="label">Comptes actifs</div>
                <div class="sub"><i class="fas fa-plus-circle" style="color: #10b981;"></i> +<?= $comptes_jour ?> aujourd'hui</div>
            </div>
            <div class="stat-card warning">
                <div class="icon"><i class="fas fa-money-bill"></i></div>
                <div class="value"><?= number_format($total_depots, 0, ',', ' ') ?> HTG</div>
                <div class="label">Total des dépôts</div>
                <div class="sub">Tous comptes confondus</div>
            </div>
            <div class="stat-card info">
                <div class="icon"><i class="fas fa-exchange-alt"></i></div>
                <div class="value"><?= number_format($transactions_jour['nb_transactions']) ?></div>
                <div class="label">Transactions aujourd'hui</div>
                <div class="sub"><?= date('d/m/Y') ?></div>
            </div>
        </div>
        
        <!-- Actions rapides -->
        <div class="quick-actions">
            <a href="creer_compte.php" class="action-card">
                <i class="fas fa-user-plus"></i>
                <h4>Créer un compte</h4>
                <p>Ouvrir un nouveau compte client</p>
            </a>
            <a href="ajouter_client.php" class="action-card">
                <i class="fas fa-user"></i>
                <h4>Ajouter un client</h4>
                <p>Enregistrer un nouveau client</p>
            </a>
            <a href="depot.php" class="action-card">
                <i class="fas fa-arrow-down"></i>
                <h4>Effectuer un dépôt</h4>
                <p>Créditer un compte</p>
            </a>
            <a href="verification.php" class="action-card">
                <i class="fas fa-search"></i>
                <h4>Vérifier un compte</h4>
                <p>Consulter les informations</p>
            </a>
        </div>
        
        <!-- Résumé des transactions du jour -->
        <div class="transactions-summary" style="margin-bottom: 24px;">
            <h3 style="margin-bottom: 15px; color: #1e293b;"><i class="fas fa-calendar-day" style="color: #10b981;"></i> Résumé du jour</h3>
            <div class="summary-row">
                <div class="summary-item">
                    <div class="summary-value"><?= number_format($transactions_jour['nb_transactions']) ?></div>
                    <div class="summary-label">Transactions</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value" style="color: #16a34a;">+<?= number_format($transactions_jour['depots'], 0, ',', ' ') ?> HTG</div>
                    <div class="summary-label">Total dépôts</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value" style="color: #dc2626;">-<?= number_format($transactions_jour['retraits'], 0, ',', ' ') ?> HTG</div>
                    <div class="summary-label">Total retraits</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value" style="color: #3b82f6;"><?= number_format($transactions_jour['depots'] - $transactions_jour['retraits'], 0, ',', ' ') ?> HTG</div>
                    <div class="summary-label">Solde net</div>
                </div>
            </div>
        </div>
        
        <!-- Tables Section -->
        <div class="tables-section">
            <!-- Derniers comptes créés -->
            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-credit-card" style="color: #10b981;"></i> Derniers comptes créés</h3>
                    <a href="liste_clients.php">Voir tout <i class="fas fa-arrow-right"></i></a>
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
                                <td><strong><?= htmlspecialchars($c['id_compte']) ?></strong></td>
                                <td><?= htmlspecialchars($c['titulaire']) ?></td>
                                <td><span class="badge badge-info"><?= htmlspecialchars($c['type_compte_nom']) ?></span></td>
                                <td><strong><?= number_format($c['solde'], 2, ',', ' ') ?> HTG</strong></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($derniers_comptes)): ?>
                            <tr><td colspan="4" style="text-align: center; color: #64748b; padding: 30px;">Aucun compte créé</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Derniers clients -->
            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-user" style="color: #10b981;"></i> Derniers clients enregistrés</h3>
                    <a href="liste_clients.php">Voir tout <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>NIF/CINU</th>
                                <th>Nom</th>
                                <th>Téléphone</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($derniers_clients as $cl): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($cl['id_client']) ?></strong></td>
                                <td><?= htmlspecialchars($cl['prenom'] . ' ' . $cl['nom']) ?></td>
                                <td><?= htmlspecialchars($cl['telephone'] ?: '-') ?></td>
                                <td><?= date('d/m/Y', strtotime($cl['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($derniers_clients)): ?>
                            <tr><td colspan="4" style="text-align: center; color: #64748b; padding: 30px;">Aucun client enregistré</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Répartition des comptes par type -->
        <div class="table-card" style="margin-bottom: 24px;">
            <div class="table-header">
                <h3><i class="fas fa-chart-pie" style="color: #10b981;"></i> Répartition des comptes par type</h3>
            </div>
            <div class="table-container">
                <div style="display: flex; gap: 30px; justify-content: space-around; padding: 20px 0;">
                    <?php foreach ($comptes_par_type as $type): ?>
                    <div style="text-align: center;">
                        <div style="font-size: 28px; font-weight: 700; color: #1e293b;"><?= $type['nb_comptes'] ?></div>
                        <div style="color: #64748b; font-size: 14px;"><?= htmlspecialchars($type['nom']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
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
        
        // Animation des actions rapides
        document.querySelectorAll('.action-card').forEach((card, index) => {
            card.style.opacity = '0';
            setTimeout(() => {
                card.style.transition = 'all 0.3s ease';
                card.style.opacity = '1';
            }, 300 + index * 100);
        });
    </script>
</body>
</html>