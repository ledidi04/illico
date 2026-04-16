<?php
require_once '../config/connexion.php';
session_start();

// Vérifier l'authentification et le rôle
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'secretaire'])) {
    header('Location: ../index.php');
    exit;
}

// Paramètres de recherche et pagination
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Construction de la requête
$whereClause = "";
$params = [];

if (!empty($search)) {
    $whereClause = " WHERE id_client LIKE ? OR CONCAT(nom, ' ', prenom) LIKE ? OR telephone LIKE ? OR email LIKE ?";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

// Récupérer le nombre total de clients
$countSql = "SELECT COUNT(*) as total FROM clients" . $whereClause;
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total_clients = $stmt->fetch()['total'];
$total_pages = ceil($total_clients / $limit);

// Récupérer les clients
$sql = "SELECT c.*, 
        (SELECT COUNT(*) FROM comptes WHERE titulaire_principal_id = c.id) as nb_comptes
        FROM clients c" . $whereClause . " ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$allParams = array_merge($params, [$limit, $offset]);
$stmt->execute($allParams);
$clients = $stmt->fetchAll();

// Statistiques
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as aujourdhui,
        SUM(CASE WHEN YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1) THEN 1 ELSE 0 END) as cette_semaine,
        SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as ce_mois
    FROM clients
")->fetch();

$currentPage = 'liste_clients';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des clients - S&P illico</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', 'Inter', sans-serif; background: #f1f5f9; display: flex; min-height: 100vh; }
        
        .sidebar { width: 280px; background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%); color: white; padding: 24px 0; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 0 20px 24px; border-bottom: 1px solid #334155; margin-bottom: 24px; }
        .sidebar-header h2 { color: #3b82f6; font-size: 22px; display: flex; align-items: center; gap: 10px; }
        .sidebar-header p { color: #94a3b8; font-size: 13px; margin-top: 8px; }
        .user-info-side { padding: 16px 20px; background: #1e293b; margin: 0 16px 20px; border-radius: 12px; }
        .user-info-side .avatar { width: 48px; height: 48px; background: #10b981; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; }
        .user-info-side .avatar i { font-size: 24px; }
        .user-info-side .name { font-weight: 600; margin-bottom: 4px; }
        .user-info-side .role { color: #10b981; font-size: 13px; }
        .nav-menu { padding: 0 12px; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #cbd5e1; text-decoration: none; border-radius: 10px; margin-bottom: 4px; }
        .nav-item i { width: 24px; }
        .nav-item:hover { background: #334155; color: white; }
        .nav-item.active { background: #10b981; color: white; }
        .nav-divider { height: 1px; background: #334155; margin: 16px 0; }
        
        .main-content { margin-left: 280px; flex: 1; padding: 24px; }
        
        .top-bar { background: white; padding: 16px 24px; border-radius: 16px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .page-title h1 { font-size: 24px; color: #1e293b; }
        .breadcrumb { color: #64748b; font-size: 14px; }
        .breadcrumb a { color: #3b82f6; text-decoration: none; }
        .logout-btn { background: #ef4444; color: white; padding: 10px 18px; border-radius: 10px; text-decoration: none; }
        
        .stats-mini { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 24px; }
        .stat-mini { background: white; padding: 15px 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; }
        .stat-mini .icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
        .stat-mini .icon.green { background: #dcfce7; color: #16a34a; }
        .stat-mini .icon.blue { background: #dbeafe; color: #2563eb; }
        .stat-mini .icon.orange { background: #fef3c7; color: #d97706; }
        .stat-mini .icon.purple { background: #f3e8ff; color: #9333ea; }
        .stat-mini .value { font-size: 22px; font-weight: 700; color: #1e293b; }
        .stat-mini .label { color: #64748b; font-size: 13px; }
        
        .search-bar { background: white; padding: 20px; border-radius: 16px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; gap: 15px; align-items: center; }
        .search-input { flex: 1; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px; }
        .search-input:focus { outline: none; border-color: #10b981; }
        .search-btn { padding: 12px 24px; background: #10b981; color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 600; }
        .btn-add { padding: 12px 24px; background: #3b82f6; color: white; border-radius: 10px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }
        
        .table-card { background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
        .table-header { padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .table-container { overflow-x: auto; padding: 0 20px 20px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 14px 0; color: #64748b; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; }
        td { padding: 14px 0; border-bottom: 1px solid #e2e8f0; color: #1e293b; font-size: 14px; }
        tr:hover { background: #f8fafc; }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 500; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        
        .action-buttons { display: flex; gap: 6px; }
        .action-btn { width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.2s; }
        .action-btn.view { background: #dbeafe; color: #2563eb; }
        .action-btn.view:hover { background: #2563eb; color: white; }
        .action-btn.add { background: #dcfce7; color: #16a34a; }
        .action-btn.add:hover { background: #16a34a; color: white; }
        .action-btn.edit { background: #fef3c7; color: #d97706; }
        .action-btn.edit:hover { background: #d97706; color: white; }
        
        .pagination { display: flex; justify-content: center; gap: 8px; padding: 20px; }
        .page-link { padding: 8px 14px; background: white; border: 1px solid #e2e8f0; border-radius: 8px; color: #475569; text-decoration: none; }
        .page-link.active { background: #10b981; color: white; border-color: #10b981; }
        .page-link:hover { background: #f1f5f9; }
        
        .client-photo { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; background: #f1f5f9; }
        
        .no-results { text-align: center; padding: 40px; color: #64748b; }
        
        @media print {
            .sidebar, .top-bar, .stats-mini, .search-bar, .action-buttons, .btn-add, .logout-btn { display: none !important; }
            .main-content { margin-left: 0 !important; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-building-columns"></i> S&P illico</h2>
            <p>Banque Communautaire</p>
        </div>
        <div class="user-info-side">
            <div class="avatar"><i class="fas fa-user-tie"></i></div>
            <div class="name"><?= htmlspecialchars($_SESSION['nom_complet']) ?></div>
            <div class="role"><i class="fas fa-shield"></i> <?= ucfirst($_SESSION['role']) ?></div>
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-item"><i class="fas fa-gauge"></i> Tableau de bord</a>
            <a href="creer_compte.php" class="nav-item"><i class="fas fa-user-plus"></i> Créer compte</a>
            <a href="liste_clients.php" class="nav-item active"><i class="fas fa-users"></i> Liste clients</a>
            <a href="ajouter_client.php" class="nav-item"><i class="fas fa-user"></i> Ajouter client</a>
            <div class="nav-divider"></div>
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
                <h1>Liste des clients</h1>
                <div class="breadcrumb"><a href="dashboard.php">Accueil</a> / Clients</div>
            </div>
            <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </div>
        
        <!-- Mini statistiques -->
        <div class="stats-mini">
            <div class="stat-mini">
                <div class="icon green"><i class="fas fa-users"></i></div>
                <div><div class="value"><?= number_format($stats['total']) ?></div><div class="label">Total clients</div></div>
            </div>
            <div class="stat-mini">
                <div class="icon blue"><i class="fas fa-calendar-day"></i></div>
                <div><div class="value"><?= $stats['aujourdhui'] ?></div><div class="label">Aujourd'hui</div></div>
            </div>
            <div class="stat-mini">
                <div class="icon orange"><i class="fas fa-calendar-week"></i></div>
                <div><div class="value"><?= $stats['cette_semaine'] ?></div><div class="label">Cette semaine</div></div>
            </div>
            <div class="stat-mini">
                <div class="icon purple"><i class="fas fa-calendar-alt"></i></div>
                <div><div class="value"><?= $stats['ce_mois'] ?></div><div class="label">Ce mois</div></div>
            </div>
        </div>
        
        <!-- Barre de recherche -->
        <div class="search-bar">
            <form method="get" style="display: flex; gap: 15px; flex: 1;">
                <input type="text" name="search" class="search-input" placeholder="Rechercher par NIF/CINU, nom, téléphone ou email..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="search-btn"><i class="fas fa-search"></i> Rechercher</button>
                <?php if (!empty($search)): ?>
                <a href="liste_clients.php" class="search-btn" style="background: #64748b;"><i class="fas fa-times"></i> Réinitialiser</a>
                <?php endif; ?>
            </form>
            <a href="ajouter_client.php" class="btn-add"><i class="fas fa-plus"></i> Nouveau client</a>
            <button class="btn-add" style="background: #64748b;" onclick="window.print()"><i class="fas fa-print"></i> Imprimer</button>
        </div>
        
        <!-- Tableau des clients -->
        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> <?= number_format($total_clients) ?> client(s) trouvé(s)</h3>
                <span>Page <?= $page ?> / <?= $total_pages ?: 1 ?></span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>NIF/CINU</th>
                            <th>Nom complet</th>
                            <th>Téléphone</th>
                            <th>Email</th>
                            <th>Comptes</th>
                            <th>Date création</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                        <tr>
                            <td>
                                <?php if ($client['photo']): ?>
                                <img src="../<?= htmlspecialchars($client['photo']) ?>" class="client-photo" alt="Photo">
                                <?php else: ?>
                                <div class="client-photo" style="display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-user" style="color: #94a3b8;"></i>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= htmlspecialchars($client['id_client']) ?></strong></td>
                            <td><?= htmlspecialchars($client['prenom'] . ' ' . $client['nom']) ?></td>
                            <td><?= htmlspecialchars($client['telephone'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($client['email'] ?: '-') ?></td>
                            <td>
                                <?php if ($client['nb_comptes'] > 0): ?>
                                <span class="badge badge-success"><i class="fas fa-credit-card"></i> <?= $client['nb_comptes'] ?> compte(s)</span>
                                <?php else: ?>
                                <span class="badge badge-info">Aucun compte</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y', strtotime($client['created_at'])) ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="verification.php?search=<?= urlencode($client['id_client']) ?>" class="action-btn view" title="Voir détails">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="creer_compte.php?search_client=<?= urlencode($client['id_client']) ?>" class="action-btn add" title="Ouvrir un compte">
                                        <i class="fas fa-plus"></i>
                                    </a>
                                    <a href="modifier_client.php?id=<?= $client['id'] ?>" class="action-btn edit" title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($clients)): ?>
                        <tr>
                            <td colspan="8" class="no-results">
                                <i class="fas fa-search" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                                <p>Aucun client trouvé.</p>
                                <a href="ajouter_client.php" class="btn-add" style="margin-top: 15px;">Ajouter un client</a>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>" class="page-link"><i class="fas fa-chevron-left"></i></a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>" class="page-link"><i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>