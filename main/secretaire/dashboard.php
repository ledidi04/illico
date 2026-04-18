<?php
require_once '../config/connexion.php';
session_start();

// CORRECTION : accepter tous les rôles
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'secretaire', 'caissier'])) {
    header('Location: ../index.php');
    exit;
}

$role = $_SESSION['role'];

// ── Statistiques ─────────────────────────────────────────────
try {
    // Récupération des stats globales depuis la base de données
    $total_clients = (int) $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
    $total_comptes_actifs = (int) $pdo->query("SELECT COUNT(*) FROM comptes WHERE statut = 'actif'")->fetchColumn();
    $solde_global = (float) $pdo->query("SELECT COALESCE(SUM(solde), 0) FROM comptes WHERE statut = 'actif'")->fetchColumn();

    // Comptes créés aujourd'hui
    $comptes_jour = (int) $pdo->query("
        SELECT COUNT(*) FROM comptes WHERE DATE(created_at) = CURDATE()
    ")->fetchColumn();

    // Clients créés ce mois
    $clients_mois = (int) $pdo->query("
        SELECT COUNT(*) FROM clients
        WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
    ")->fetchColumn();

    // Derniers comptes (filtrés par succursale pour caissier/secrétaire, globaux pour admin)
    if ($role === 'admin') {
        $derniers_comptes = $pdo->query("
            SELECT c.id_compte, c.date_creation, c.solde, c.statut, c.devise,
                   tc.nom AS type_compte_nom,
                   CONCAT(cl.prenom, ' ', cl.nom) AS titulaire
            FROM comptes c
            JOIN types_comptes tc ON c.type_compte_id = tc.id
            JOIN clients cl ON c.titulaire_principal_id = cl.id
            ORDER BY c.created_at DESC
            LIMIT 5
        ")->fetchAll();
    } else {
        $stmt = $pdo->prepare("
            SELECT c.id_compte, c.date_creation, c.solde, c.statut, c.devise,
                   tc.nom AS type_compte_nom,
                   CONCAT(cl.prenom, ' ', cl.nom) AS titulaire
            FROM comptes c
            JOIN types_comptes tc ON c.type_compte_id = tc.id
            JOIN clients cl ON c.titulaire_principal_id = cl.id
            WHERE c.succursale_id = ?
            ORDER BY c.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$_SESSION['succursale_id']]);
        $derniers_comptes = $stmt->fetchAll();
    }

    // Derniers clients
    $derniers_clients = $pdo->query("
        SELECT id_client, nom, prenom, telephone, created_at
        FROM clients
        ORDER BY created_at DESC
        LIMIT 5
    ")->fetchAll();

    // Transactions du jour
    if ($role === 'admin') {
        $transactions_jour = $pdo->query("
            SELECT
                COUNT(*) AS nb_transactions,
                COALESCE(SUM(CASE WHEN type = 'depot' THEN montant ELSE 0 END), 0) AS depots,
                COALESCE(SUM(CASE WHEN type = 'retrait' THEN montant ELSE 0 END), 0) AS retraits
            FROM transactions
            WHERE DATE(date_transaction) = CURDATE()
        ")->fetch();
    } else {
        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) AS nb_transactions,
                COALESCE(SUM(CASE WHEN type = 'depot' THEN montant ELSE 0 END), 0) AS depots,
                COALESCE(SUM(CASE WHEN type = 'retrait' THEN montant ELSE 0 END), 0) AS retraits
            FROM transactions
            WHERE succursale_id = ? AND DATE(date_transaction) = CURDATE()
        ");
        $stmt->execute([$_SESSION['succursale_id']]);
        $transactions_jour = $stmt->fetch();
    }

    // S'assurer que $transactions_jour est un tableau
    if (!$transactions_jour) {
        $transactions_jour = ['nb_transactions' => 0, 'depots' => 0, 'retraits' => 0];
    }

    // Répartition par type de compte
    $comptes_par_type = $pdo->query("
        SELECT tc.nom, COUNT(c.id) AS nb_comptes
        FROM types_comptes tc
        LEFT JOIN comptes c ON tc.id = c.type_compte_id AND c.statut = 'actif'
        WHERE tc.actif = 1
        GROUP BY tc.id, tc.nom
        ORDER BY nb_comptes DESC
    ")->fetchAll();

    if (empty($comptes_par_type)) {
        $comptes_par_type = [];
    }

} catch (PDOException $e) {
    $error = "Erreur lors du chargement des statistiques.";
    $total_clients = 0;
    $total_comptes_actifs = 0;
    $solde_global = 0;
    $comptes_jour = 0;
    $clients_mois = 0;
    $derniers_comptes = [];
    $derniers_clients = [];
    $transactions_jour = ['nb_transactions' => 0, 'depots' => 0, 'retraits' => 0];
    $comptes_par_type = [];
}

// Libellés du rôle pour l'affichage
$roleLabel = ['admin' => 'Administrateur', 'secretaire' => 'Secrétaire', 'caissier' => 'Caissier'];
$currentPage = 'dashboard';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - S&P illico</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/common.css">
</head>
<body>
    <?php require_once '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>Tableau de bord</h1>
                <div class="breadcrumb"><a href="dashboard.php">Accueil</a> / Vue d'ensemble</div>
            </div>
            <div class="top-right">
                <span style="color:#64748b;font-size:13px;">
                    <i class="fas fa-calendar-alt" style="color:#10b981;"></i>
                    <?= date('d/m/Y') ?>
                </span>
                <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
            </div>
        </div>

        <?php if (isset($error)): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <p style="color:#64748b;margin-bottom:20px;">
            Bienvenue, <strong><?= htmlspecialchars(explode(' ', $_SESSION['nom_complet'] ?? 'Utilisateur')[0]) ?></strong> —
            <?= $roleLabel[$role] ?? $role ?> | <?= date('l d F Y') ?>
        </p>

        <!-- Cartes statistiques -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="icon"><i class="fas fa-users"></i></div>
                <div class="value"><?= number_format($total_clients) ?></div>
                <div class="label">Total clients</div>
                <div class="sub"><i class="fas fa-user-plus" style="color:#10b981;"></i> +<?= $clients_mois ?> ce mois</div>
            </div>
            <div class="stat-card success">
                <div class="icon"><i class="fas fa-credit-card"></i></div>
                <div class="value"><?= number_format($total_comptes_actifs) ?></div>
                <div class="label">Comptes actifs</div>
                <div class="sub"><i class="fas fa-plus-circle" style="color:#10b981;"></i> +<?= $comptes_jour ?> aujourd'hui</div>
            </div>
            <div class="stat-card warning">
                <div class="icon"><i class="fas fa-money-bill"></i></div>
                <div class="value"><?= number_format($solde_global, 0, ',', ' ') ?></div>
                <div class="label">Solde global (HTG)</div>
                <div class="sub">Comptes actifs</div>
            </div>
            <div class="stat-card info">
                <div class="icon"><i class="fas fa-exchange-alt"></i></div>
                <div class="value"><?= number_format($transactions_jour['nb_transactions']) ?></div>
                <div class="label">Transactions aujourd'hui</div>
                <div class="sub"><?= date('d/m/Y') ?></div>
            </div>
        </div>

        <!-- Résumé des transactions du jour -->
        <div class="card" style="margin-bottom:22px;">
            <h3 style="margin-bottom:15px;color:#1e293b;">
                <i class="fas fa-calendar-day" style="color:#10b981;"></i> Résumé du jour
            </h3>
            <div class="summary-row">
                <div class="summary-item">
                    <div class="summary-value"><?= number_format($transactions_jour['nb_transactions']) ?></div>
                    <div class="summary-label">Transactions</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value" style="color:#16a34a;">
                        +<?= number_format($transactions_jour['depots'], 0, ',', ' ') ?> HTG
                    </div>
                    <div class="summary-label">Total dépôts</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value" style="color:#dc2626;">
                        -<?= number_format($transactions_jour['retraits'], 0, ',', ' ') ?> HTG
                    </div>
                    <div class="summary-label">Total retraits</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value" style="color:#3b82f6;">
                        <?= number_format($transactions_jour['depots'] - $transactions_jour['retraits'], 0, ',', ' ') ?> HTG
                    </div>
                    <div class="summary-label">Solde net</div>
                </div>
            </div>
        </div>

        <!-- Tables -->
        <div class="tables-section">
            <!-- Derniers comptes -->
            <div class="table-card">
                <div class="table-card-header">
                    <h3><i class="fas fa-credit-card" style="color:#10b981;"></i> Derniers comptes créés</h3>
                    <?php if (in_array($role, ['admin', 'secretaire'])): ?>
                    <a href="liste_clients.php">Voir tout <i class="fas fa-arrow-right"></i></a>
                    <?php endif; ?>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>N° Compte</th>
                                <th>Titulaire</th>
                                <th>Type</th>
                                <th>Solde</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($derniers_comptes as $c): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($c['id_compte']) ?></strong></td>
                                <td><?= htmlspecialchars($c['titulaire']) ?></td>
                                <td><span class="badge badge-info"><?= htmlspecialchars($c['type_compte_nom']) ?></span></td>
                                <td><strong><?= number_format($c['solde'], 2, ',', ' ') ?> <?= $c['devise'] ?></strong></td>
                                <td>
                                    <span class="badge <?= $c['statut'] === 'actif' ? 'badge-success' : 'badge-danger' ?>">
                                        <?= ucfirst($c['statut']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($derniers_comptes)): ?>
                            <tr><td colspan="5" style="text-align:center;color:#64748b;padding:20px;">Aucun compte</td>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Derniers clients (visible admin + secrétaire) -->
            <?php if (in_array($role, ['admin', 'secretaire'])): ?>
            <div class="table-card">
                <div class="table-card-header">
                    <h3><i class="fas fa-user" style="color:#10b981;"></i> Derniers clients enregistrés</h3>
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
                            <tr><td colspan="4" style="text-align:center;color:#64748b;padding:20px;">Aucun client</td>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Répartition par type -->
        <div class="table-card">
            <div class="table-card-header">
                <h3><i class="fas fa-chart-pie" style="color:#10b981;"></i> Répartition des comptes par type</h3>
            </div>
            <div style="display:flex;gap:30px;justify-content:space-around;padding:20px;flex-wrap:wrap;">
                <?php if (!empty($comptes_par_type)): ?>
                    <?php foreach ($comptes_par_type as $type): ?>
                    <div style="text-align:center;">
                        <div style="font-size:26px;font-weight:700;color:#1e293b;"><?= $type['nb_comptes'] ?></div>
                        <div style="color:#64748b;font-size:13px;"><?= htmlspecialchars($type['nom']) ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center;color:#64748b;">Aucun type de compte</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Animation d'entrée des cartes statistiques
        document.querySelectorAll('.stat-card').forEach((card, i) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(16px)';
            setTimeout(() => {
                card.style.transition = 'all .4s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, i * 80);
        });
    </script>
</body>
</html>