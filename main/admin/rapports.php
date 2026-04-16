<?php
require_once '../config/connexion.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Période sélectionnée
$periode = $_GET['periode'] ?? 'jour';
$date_debut = $_GET['date_debut'] ?? date('Y-m-d');
$date_fin = $_GET['date_fin'] ?? date('Y-m-d');

switch ($periode) {
    case 'jour':
        $date_debut = $date_fin = date('Y-m-d');
        $titre_periode = "Aujourd'hui (" . date('d/m/Y') . ")";
        break;
    case 'semaine':
        $date_debut = date('Y-m-d', strtotime('monday this week'));
        $date_fin = date('Y-m-d', strtotime('sunday this week'));
        $titre_periode = "Semaine du " . date('d/m/Y', strtotime($date_debut)) . " au " . date('d/m/Y', strtotime($date_fin));
        break;
    case 'mois':
        $date_debut = date('Y-m-01');
        $date_fin = date('Y-m-t');
        $titre_periode = date('F Y');
        break;
    case 'annee':
        $date_debut = date('Y-01-01');
        $date_fin = date('Y-12-31');
        $titre_periode = "Année " . date('Y');
        break;
    case 'personnalise':
        $titre_periode = "Du " . date('d/m/Y', strtotime($date_debut)) . " au " . date('d/m/Y', strtotime($date_fin));
        break;
}

try {
    // Transactions période
    $tx = $pdo->prepare("
        SELECT 
            COUNT(*) as nb_transactions,
            COALESCE(SUM(CASE WHEN type = 'depot' THEN montant ELSE 0 END), 0) as depots,
            COALESCE(SUM(CASE WHEN type = 'retrait' THEN montant ELSE 0 END), 0) as retraits,
            COALESCE(SUM(montant), 0) as volume_total
        FROM transactions 
        WHERE DATE(date_transaction) BETWEEN ? AND ?
    ");
    $tx->execute([$date_debut, $date_fin]);
    $tx_data = $tx->fetch();
    
    // Liste des transactions détaillées
    $transactions = $pdo->prepare("
        SELECT t.*, c.id_compte, CONCAT(cl.nom, ' ', cl.prenom) as client, u.username as operateur, s.code as succursale
        FROM transactions t
        JOIN comptes c ON t.compte_id = c.id
        JOIN clients cl ON c.titulaire_principal_id = cl.id
        JOIN utilisateurs u ON t.utilisateur_id = u.id
        JOIN succursales s ON t.succursale_id = s.id
        WHERE DATE(t.date_transaction) BETWEEN ? AND ?
        ORDER BY t.date_transaction DESC
    ");
    $transactions->execute([$date_debut, $date_fin]);
    $liste_transactions = $transactions->fetchAll();
    
    // Nouveaux clients période
    $clients = $pdo->prepare("
        SELECT COUNT(*) as nb_clients FROM clients WHERE DATE(created_at) BETWEEN ? AND ?
    ");
    $clients->execute([$date_debut, $date_fin]);
    $nb_clients = $clients->fetch()['nb_clients'];
    
    // Nouveaux comptes période
    $comptes = $pdo->prepare("
        SELECT COUNT(*) as nb_comptes, COALESCE(SUM(solde), 0) as total_solde 
        FROM comptes WHERE DATE(created_at) BETWEEN ? AND ?
    ");
    $comptes->execute([$date_debut, $date_fin]);
    $comptes_data = $comptes->fetch();
    
    // Totaux généraux
    $total_clients = $pdo->query("SELECT COUNT(*) as total FROM clients")->fetch()['total'];
    $total_personnel = $pdo->query("SELECT COUNT(*) as total FROM utilisateurs WHERE actif = 1")->fetch()['total'];
    $total_comptes = $pdo->query("SELECT COUNT(*) as total FROM comptes WHERE statut = 'actif'")->fetch()['total'];
    
} catch (PDOException $e) {
    $error = "Erreur : " . $e->getMessage();
}

$currentPage = 'rapports';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports - S&P illico</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        .nav-menu { padding: 0 12px; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #cbd5e1; text-decoration: none; border-radius: 10px; margin-bottom: 4px; }
        .nav-item i { width: 24px; }
        .nav-item:hover { background: #334155; color: white; }
        .nav-item.active { background: #3b82f6; color: white; }
        .nav-divider { height: 1px; background: #334155; margin: 16px 0; }
        
        .main-content { margin-left: 280px; flex: 1; padding: 24px; }
        
        .top-bar { background: white; padding: 16px 24px; border-radius: 16px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .page-title h1 { font-size: 24px; color: #1e293b; }
        .breadcrumb { color: #64748b; font-size: 14px; }
        .breadcrumb a { color: #3b82f6; text-decoration: none; }
        .btn { padding: 10px 20px; border-radius: 10px; font-size: 14px; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-success { background: #10b981; color: white; }
        .logout-btn { background: #ef4444; color: white; padding: 10px 18px; border-radius: 10px; text-decoration: none; }
        
        .filtres-card { background: white; padding: 20px; border-radius: 16px; margin-bottom: 24px; }
        .periode-buttons { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 15px; }
        .periode-btn { padding: 8px 16px; background: #f1f5f9; border: none; border-radius: 8px; cursor: pointer; color: #475569; }
        .periode-btn.active { background: #3b82f6; color: white; }
        
        .resume-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 24px; }
        .resume-card { background: white; padding: 20px; border-radius: 16px; text-align: center; }
        .resume-card .value { font-size: 28px; font-weight: 700; color: #1e293b; }
        .resume-card .label { color: #64748b; font-size: 13px; margin-top: 8px; }
        
        .table-card { background: white; border-radius: 16px; overflow: hidden; margin-bottom: 24px; }
        .table-header { padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .table-container { overflow-x: auto; padding: 0 20px 20px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px 0; color: #64748b; font-size: 12px; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
        td { padding: 12px 0; border-bottom: 1px solid #e2e8f0; }
        
        .badge-success { background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 20px; font-size: 11px; }
        .badge-danger { background: #fee2e2; color: #991b1b; padding: 4px 10px; border-radius: 20px; font-size: 11px; }
        
        @media print {
            .sidebar, .top-bar, .filtres-card, .btn, .logout-btn, .no-print { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 10px !important; }
            body { background: white !important; }
            .resume-card, .table-card { box-shadow: none !important; border: 1px solid #ddd !important; }
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
            <div class="avatar"><i class="fas fa-user-cog"></i></div>
            <div class="name"><?= htmlspecialchars($_SESSION['nom_complet']) ?></div>
        </div>
            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-item "><i class="fas fa-gauge"></i> Tableau de bord</a>
                <a href="utilisateurs.php" class="nav-item"><i class="fas fa-users-gear"></i> Utilisateurs</a>
                <a href="#" class="nav-item"><i class="fas fa-users"></i> Clients</a>
                <a href="compte.php" class="nav-item"><i class="fas fa-credit-card"></i> Comptes</a>
                <div class="nav-divider"></div>
                <a href="statistiques.php" class="nav-item"><i class="fas fa-chart-pie"></i> Statistiques</a>
                <a href="rapports.php" class="nav-item active"><i class="fas fa-file-pdf"></i> Rapports</a>
                
                <div class="nav-divider"></div>
            
                <a href="../logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
            </nav>
    </div>
    
    <div class="main-content" id="printableArea">
        <div class="top-bar no-print">
            <div class="page-title">
                <h1>Rapports</h1>
                <div class="breadcrumb"><a href="dashboard.php">Administration</a> / Rapports</div>
            </div>
            <div style="display: flex; gap: 10px;">
                <button class="btn btn-success" onclick="window.print()"><i class="fas fa-print"></i> Imprimer</button>
                <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
            </div>
        </div>
        
        <!-- En-tête pour impression -->
        <div style="display: none;" class="print-only">
            <h1 style="text-align: center; margin-bottom: 10px;">S&P illico - Rapport</h1>
            <p style="text-align: center; margin-bottom: 20px;"><?= $titre_periode ?></p>
        </div>
        
        <!-- Filtres -->
        <div class="filtres-card no-print">
            <form method="get">
                <div class="periode-buttons">
                    <button type="submit" name="periode" value="jour" class="periode-btn <?= $periode == 'jour' ? 'active' : '' ?>"><i class="fas fa-calendar-day"></i> Jour</button>
                    <button type="submit" name="periode" value="semaine" class="periode-btn <?= $periode == 'semaine' ? 'active' : '' ?>"><i class="fas fa-calendar-week"></i> Semaine</button>
                    <button type="submit" name="periode" value="mois" class="periode-btn <?= $periode == 'mois' ? 'active' : '' ?>"><i class="fas fa-calendar-alt"></i> Mois</button>
                    <button type="submit" name="periode" value="annee" class="periode-btn <?= $periode == 'annee' ? 'active' : '' ?>"><i class="fas fa-calendar"></i> Année</button>
                    <button type="submit" name="periode" value="personnalise" class="periode-btn <?= $periode == 'personnalise' ? 'active' : '' ?>"><i class="fas fa-calendar-range"></i> Personnalisé</button>
                </div>
                <?php if ($periode == 'personnalise'): ?>
                <div style="display: flex; gap: 15px; margin-top: 15px;">
                    <input type="date" name="date_debut" value="<?= $date_debut ?>" class="form-control" style="padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px;">
                    <input type="date" name="date_fin" value="<?= $date_fin ?>" class="form-control" style="padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px;">
                    <button type="submit" class="btn btn-primary">Appliquer</button>
                </div>
                <?php endif; ?>
            </form>
        </div>
        
        <h2 style="margin-bottom: 20px;"><?= $titre_periode ?></h2>
        
        <!-- Résumé -->
        <div class="resume-grid">
            <div class="resume-card">
                <div class="value"><?= number_format($tx_data['nb_transactions']) ?></div>
                <div class="label">Transactions</div>
            </div>
            <div class="resume-card">
                <div class="value"><?= number_format($tx_data['volume_total'], 0, ',', ' ') ?> HTG</div>
                <div class="label">Volume total</div>
            </div>
            <div class="resume-card">
                <div class="value"><?= number_format($nb_clients) ?></div>
                <div class="label">Nouveaux clients</div>
            </div>
            <div class="resume-card">
                <div class="value"><?= number_format($comptes_data['nb_comptes']) ?></div>
                <div class="label">Nouveaux comptes</div>
            </div>
        </div>
        
        <!-- Totaux généraux -->
        <div class="resume-grid" style="grid-template-columns: repeat(3, 1fr);">
            <div class="resume-card">
                <div class="value"><?= number_format($total_clients) ?></div>
                <div class="label">Total clients</div>
            </div>
            <div class="resume-card">
                <div class="value"><?= number_format($total_personnel) ?></div>
                <div class="label">Personnel actif</div>
            </div>
            <div class="resume-card">
                <div class="value"><?= number_format($total_comptes) ?></div>
                <div class="label">Comptes actifs</div>
            </div>
        </div>
        
        <!-- Détail dépôts/retraits -->
        <div class="resume-grid" style="grid-template-columns: repeat(2, 1fr);">
            <div class="resume-card" style="background: #dcfce7;">
                <div class="value" style="color: #166534;"><?= number_format($tx_data['depots'], 0, ',', ' ') ?> HTG</div>
                <div class="label"><i class="fas fa-arrow-down"></i> Total dépôts</div>
            </div>
            <div class="resume-card" style="background: #fee2e2;">
                <div class="value" style="color: #991b1b;"><?= number_format($tx_data['retraits'], 0, ',', ' ') ?> HTG</div>
                <div class="label"><i class="fas fa-arrow-up"></i> Total retraits</div>
            </div>
        </div>
        
        <!-- Liste des transactions -->
        <div class="table-card">
            <div class="table-header">
                <h3>Détail des transactions</h3>
                <span><?= count($liste_transactions) ?> transactions</span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Compte</th>
                            <th>Client</th>
                            <th>Type</th>
                            <th>Montant</th>
                            <th>Opérateur</th>
                            <th>Succursale</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($liste_transactions as $t): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($t['date_transaction'])) ?></td>
                            <td><strong><?= $t['id_compte'] ?></strong></td>
                            <td><?= htmlspecialchars($t['client']) ?></td>
                            <td><span class="badge-<?= $t['type'] == 'depot' ? 'success' : 'danger' ?>"><?= ucfirst($t['type']) ?></span></td>
                            <td><strong><?= number_format($t['montant'], 2, ',', ' ') ?> HTG</strong></td>
                            <td><?= htmlspecialchars($t['operateur']) ?></td>
                            <td><?= $t['succursale'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($liste_transactions)): ?>
                        <tr><td colspan="7" style="text-align: center; padding: 30px; color: #64748b;">Aucune transaction sur cette période</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pied de page pour impression -->
        <div style="display: none;" class="print-only">
            <p style="margin-top: 30px; text-align: right; font-size: 12px;">
                Rapport généré le <?= date('d/m/Y à H:i') ?> par <?= htmlspecialchars($_SESSION['nom_complet']) ?>
            </p>
        </div>
    </div>
</body>
</html>