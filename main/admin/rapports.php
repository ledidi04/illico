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

$error = '';
$rapport_genere = false;
$rapport_data = [];

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
        SELECT t.*, c.id_compte, c.devise, CONCAT(cl.nom, ' ', cl.prenom) as client, 
               u.username as operateur, u.nom_complet as operateur_nom,
               s.code as succursale, s.nom as succursale_nom
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
    $total_depots_global = $pdo->query("SELECT COALESCE(SUM(solde), 0) as total FROM comptes WHERE statut = 'actif'")->fetch()['total'];
    
    // Statistiques par succursale
    $stats_succursales = $pdo->query("
        SELECT s.code, s.nom, COUNT(DISTINCT c.id) as nb_comptes,
               COALESCE(SUM(c.solde), 0) as total_depots
        FROM succursales s
        LEFT JOIN comptes c ON s.id = c.succursale_id AND c.statut = 'actif'
        GROUP BY s.id
        ORDER BY s.code
    ")->fetchAll();
    
    // Si le formulaire a été soumis, on active l'affichage du rapport
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['periode'])) {
        $rapport_genere = true;
        $rapport_data = [
            'titre' => $titre_periode,
            'date_debut' => $date_debut,
            'date_fin' => $date_fin,
            'nb_transactions' => $tx_data['nb_transactions'],
            'volume_total' => $tx_data['volume_total'],
            'depots' => $tx_data['depots'],
            'retraits' => $tx_data['retraits'],
            'nb_clients' => $nb_clients,
            'nb_comptes' => $comptes_data['nb_comptes'],
            'total_clients' => $total_clients,
            'total_personnel' => $total_personnel,
            'total_comptes' => $total_comptes,
            'total_depots_global' => $total_depots_global,
            'transactions' => $liste_transactions,
            'stats_succursales' => $stats_succursales,
            'date_generation' => date('d/m/Y'),
            'heure_generation' => date('H:i:s'),
            'operateur' => $_SESSION['nom_complet'],
            'operateur_username' => $_SESSION['username'],
            'succursale' => $_SESSION['succursale_nom'] ?? 'Succursale',
        ];
    }
    
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
    <link rel="stylesheet" href="../assets/css/common.css">
    <link rel="icon" type="image/png" href="../logo.jpeg">
    <style>
        .rapport-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .resume-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .resume-card {
            background: white;
            padding: 20px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .resume-card .value {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
        }
        .resume-card .label {
            color: #64748b;
            font-size: 13px;
            margin-top: 8px;
        }
        .resume-card.depot {
            background: linear-gradient(135deg, #dcfce7 0%, #f0fdf4 100%);
            border-left: 4px solid #16a34a;
        }
        .resume-card.retrait {
            background: linear-gradient(135deg, #fee2e2 0%, #fef2f2 100%);
            border-left: 4px solid #dc2626;
        }
        .table-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .table-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .table-container {
            overflow-x: auto;
            padding: 0 20px 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            text-align: left;
            padding: 12px 0;
            color: #64748b;
            font-size: 12px;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
        }
        td {
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
        }
        .badge-success {
            background: #dcfce7;
            color: #166534;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            display: inline-block;
        }
        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            display: inline-block;
        }
        .btn-pdf {
            background: #dc2626;
            color: white;
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-pdf:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }
        .filtres-card {
            background: white;
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 24px;
        }
        .periode-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .periode-btn {
            padding: 10px 20px;
            background: #f1f5f9;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            color: #475569;
            font-weight: 500;
            transition: all 0.2s;
        }
        .periode-btn:hover {
            background: #e2e8f0;
        }
        .periode-btn.active {
            background: #3b82f6;
            color: white;
        }
        .totaux-generaux {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        
        @media print {
            .sidebar, .top-bar, .filtres-card, .btn, .logout-btn, .no-print, .btn-pdf { 
                display: none !important; 
            }
            .main-content { 
                margin-left: 0 !important; 
                padding: 10px !important; 
            }
            body { 
                background: white !important; 
            }
        }
    </style>
</head>
<body>
    <?php require_once '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>Rapports</h1>
                <div class="breadcrumb"><a href="dashboard.php">Administration</a> / Rapports</div>
            </div>
            <div class="top-right">
                <span class="top-succursale"><i class="fas fa-building"></i> <?= htmlspecialchars($_SESSION['succursale_nom']) ?></span>
                <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
            </div>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!$rapport_genere): ?>
        <!-- ══ FORMULAIRE DE SÉLECTION ═══════════════════════════ -->
        <div class="card" style="max-width:700px;margin:0 auto;">
            <div class="card-header-icon">
                <i class="fas fa-file-alt" style="color:#3b82f6;background:#dbeafe;"></i>
                <h2>Générer un rapport</h2>
                <p style="color:#64748b;">Sélectionnez la période souhaitée</p>
            </div>

            <div class="filtres-card" style="box-shadow:none;padding:0;">
                <form method="get">
                    <div class="periode-buttons">
                        <button type="submit" name="periode" value="jour" class="periode-btn <?= $periode == 'jour' ? 'active' : '' ?>">
                            <i class="fas fa-calendar-day"></i> Jour
                        </button>
                        <button type="submit" name="periode" value="semaine" class="periode-btn <?= $periode == 'semaine' ? 'active' : '' ?>">
                            <i class="fas fa-calendar-week"></i> Semaine
                        </button>
                        <button type="submit" name="periode" value="mois" class="periode-btn <?= $periode == 'mois' ? 'active' : '' ?>">
                            <i class="fas fa-calendar-alt"></i> Mois
                        </button>
                        <button type="submit" name="periode" value="annee" class="periode-btn <?= $periode == 'annee' ? 'active' : '' ?>">
                            <i class="fas fa-calendar"></i> Année
                        </button>
                        <button type="submit" name="periode" value="personnalise" class="periode-btn <?= $periode == 'personnalise' ? 'active' : '' ?>">
                            <i class="fas fa-calendar-range"></i> Personnalisé
                        </button>
                    </div>
                    
                    <?php if ($periode == 'personnalise'): ?>
                    <div style="display: flex; gap: 15px; margin-top: 20px; justify-content:center;">
                        <input type="date" name="date_debut" value="<?= $date_debut ?>" class="form-control" style="width:auto;">
                        <span style="align-self:center;">au</span>
                        <input type="date" name="date_fin" value="<?= $date_fin ?>" class="form-control" style="width:auto;">
                    </div>
                    <?php endif; ?>
                    
                    <div style="text-align:center;margin-top:25px;">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-chart-bar"></i> Générer le rapport
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php else: ?>
        <!-- ══ AFFICHAGE DU RAPPORT ═════════════════════════════ -->
        
        <!-- En-tête avec titre et bouton PDF -->
        <div class="rapport-header">
            <div>
                <h2 style="color:#1e293b;margin-bottom:5px;">
                    <i class="fas fa-file-alt" style="color:#3b82f6;"></i> 
                    Rapport : <?= htmlspecialchars($rapport_data['titre']) ?>
                </h2>
                <p style="color:#64748b;font-size:14px;">
                    Généré le <?= $rapport_data['date_generation'] ?> à <?= $rapport_data['heure_generation'] ?> 
                    par <?= htmlspecialchars($rapport_data['operateur']) ?>
                </p>
            </div>
            <div style="display:flex;gap:10px;">
                <!-- ══ UTILISATION DE generate_pdf.php POUR L'IMPRESSION PDF ══ -->
                <a href="../generate_pdf.php?action=rapport&periode=<?= urlencode($periode) ?>&date_debut=<?= urlencode($date_debut) ?>&date_fin=<?= urlencode($date_fin) ?>" 
                   target="_blank" class="btn-pdf no-print">
                    <i class="fas fa-file-pdf"></i> Télécharger PDF
                </a>
                
            </div>
        </div>
        
        <!-- Résumé KPI -->
        <div class="resume-grid">
            <div class="resume-card">
                <div class="value"><?= number_format($rapport_data['nb_transactions']) ?></div>
                <div class="label"><i class="fas fa-exchange-alt"></i> Transactions</div>
            </div>
            <div class="resume-card">
                <div class="value"><?= number_format($rapport_data['volume_total'], 0, ',', ' ') ?> HTG</div>
                <div class="label"><i class="fas fa-chart-pie"></i> Volume total</div>
            </div>
            <div class="resume-card">
                <div class="value"><?= number_format($rapport_data['nb_clients']) ?></div>
                <div class="label"><i class="fas fa-user-plus"></i> Nouveaux clients</div>
            </div>
            <div class="resume-card">
                <div class="value"><?= number_format($rapport_data['nb_comptes']) ?></div>
                <div class="label"><i class="fas fa-credit-card"></i> Nouveaux comptes</div>
            </div>
        </div>
        
        <!-- Dépôts vs Retraits -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">
            <div class="resume-card depot">
                <div class="value" style="color:#166534;">
                    <i class="fas fa-arrow-down"></i> <?= number_format($rapport_data['depots'], 0, ',', ' ') ?> HTG
                </div>
                <div class="label">Total des dépôts sur la période</div>
            </div>
            <div class="resume-card retrait">
                <div class="value" style="color:#991b1b;">
                    <i class="fas fa-arrow-up"></i> <?= number_format($rapport_data['retraits'], 0, ',', ' ') ?> HTG
                </div>
                <div class="label">Total des retraits sur la période</div>
            </div>
        </div>
        
        <!-- Totaux généraux -->
        <div class="totaux-generaux">
            <h3 style="margin-bottom:15px;color:#1e293b;"><i class="fas fa-database"></i> Totaux généraux</h3>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:20px;">
                <?php 
                $totaux = [
                    ['Total clients', $rapport_data['total_clients'], 'fa-users', '#3b82f6'],
                    ['Personnel actif', $rapport_data['total_personnel'], 'fa-user-tie', '#10b981'],
                    ['Comptes actifs', $rapport_data['total_comptes'], 'fa-credit-card', '#f59e0b'],
                    ['Total dépôts', number_format($rapport_data['total_depots_global'], 0, ',', ' ') . ' HTG', 'fa-money-bill', '#8b5cf6'],
                ];
                foreach ($totaux as [$label, $valeur, $icone, $couleur]): ?>
                <div style="text-align:center;">
                    <div style="font-size:24px;font-weight:700;color:<?= $couleur ?>;"><?= $valeur ?></div>
                    <div style="color:#64748b;font-size:13px;"><i class="fas <?= $icone ?>"></i> <?= $label ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Liste des transactions -->
        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> Détail des transactions</h3>
                <span class="badge" style="background:#e2e8f0;color:#475569;"><?= count($rapport_data['transactions']) ?> transactions</span>
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
                            <th>Succ.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rapport_data['transactions'] as $t): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($t['date_transaction'])) ?></td>
                            <td><strong><?= $t['id_compte'] ?></strong></td>
                            <td><?= htmlspecialchars($t['client']) ?></td>
                            <td>
                                <span class="badge-<?= $t['type'] == 'depot' ? 'success' : 'danger' ?>">
                                    <i class="fas fa-<?= $t['type'] == 'depot' ? 'arrow-down' : 'arrow-up' ?>"></i>
                                    <?= ucfirst($t['type']) ?>
                                </span>
                            </td>
                            <td><strong><?= number_format($t['montant'], 2, ',', ' ') ?> <?= $t['devise'] ?></strong></td>
                            <td><?= htmlspecialchars($t['operateur']) ?></td>
                            <td><?= $t['succursale'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($rapport_data['transactions'])): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: #64748b;">
                                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px; opacity: 0.5;"></i>
                                <p>Aucune transaction sur cette période</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Boutons d'action -->
        <div style="display:flex;gap:15px;justify-content:center;margin-top:20px;" class="no-print">
            <a href="rapports.php" class="btn btn-secondary">
                <i class="fas fa-plus"></i> Nouveau rapport
            </a>
            <a href="dashboard.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Retour au tableau de bord
            </a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>