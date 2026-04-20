<?php
require_once '../config/connexion.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'secretaire'])) {
    header('Location: ../index.php');
    exit;
}

// ── Paramètres ────────────────────────────────────────────────
$search      = trim($_GET['search']      ?? '');
$filter_type = trim($_GET['filter_type'] ?? '');
$page        = max(1, intval($_GET['page'] ?? 1));
$limit       = 15;
$offset      = ($page - 1) * $limit;

// ── Construction de la requête ────────────────────────────────
$conditions = [];
$params     = [];

if (!empty($search)) {
    $conditions[] = "(c.id_client LIKE ? OR CONCAT(c.prenom,' ',c.nom) LIKE ? OR CONCAT(c.nom,' ',c.prenom) LIKE ? OR c.telephone LIKE ? OR c.email LIKE ?)";
    $term = "%$search%";
    array_push($params, $term, $term, $term, $term, $term);
}
if (!empty($filter_type)) {
    $conditions[] = "c.type_piece = ?";
    $params[] = $filter_type;
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Nombre total
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM clients c $where");
$stmtCount->execute($params);
$total_clients = (int) $stmtCount->fetchColumn();
$total_pages   = max(1, ceil($total_clients / $limit));
$page          = min($page, $total_pages);

// Récupérer les clients avec leur statut
$stmtClients = $pdo->prepare("
    SELECT DISTINCT c.*,
           (SELECT COUNT(*) FROM comptes WHERE titulaire_principal_id = c.id AND statut = 'actif') AS nb_comptes_titulaire,
           (SELECT COUNT(*) FROM compte_cotitulaires cc WHERE cc.client_id = c.id) AS nb_comptes_cotitulaire
    FROM clients c
    $where
    ORDER BY c.created_at DESC
    LIMIT ? OFFSET ?
");
$stmtClients->execute(array_merge($params, [$limit, $offset]));
$clients = $stmtClients->fetchAll();

// ── Statistiques ──────────────────────────────────────────────
$stats = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS aujourdhui,
        SUM(CASE WHEN YEARWEEK(created_at,1) = YEARWEEK(CURDATE(),1) THEN 1 ELSE 0 END) AS cette_semaine,
        SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) AS ce_mois
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
    <link rel="stylesheet" href="../assets/css/common.css">
    <link rel="icon" type="favicon" href="../logo.jpeg">
    <style>
        .badge-titulaire { background: #dbeafe; color: #1e40af; }
        .badge-cotitulaire { background: #f3e8ff; color: #9333ea; }
        .badge-compte { background: #dcfce7; color: #166534; }
        .client-statut { font-size: 11px; padding: 2px 8px; border-radius: 20px; display: inline-block; }
        .btn-action { width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; }
        .btn-action.view { background: #dbeafe; color: #2563eb; }
        .btn-action.add { background: #dcfce7; color: #16a34a; }
        .btn-action.edit { background: #fef3c7; color: #d97706; }
        .btn-action.delete { background: #fee2e2; color: #dc2626; }
        .pagination-btn { padding: 7px 14px; background: white; border: 1px solid #e2e8f0; border-radius: 7px; color: #475569; text-decoration: none; }
        .pagination-btn.active { background: #10b981; color: white; border-color: #10b981; }
        @media print { .sidebar, .top-bar, .no-print, form { display: none !important; } .main-content { margin-left: 0 !important; } }
    </style>
</head>
<body>
    <?php require_once '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>Liste des clients</h1>
                <div class="breadcrumb"><a href="dashboard.php">Accueil</a> / Clients</div>
            </div>
            <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </div>

        <!-- Messages de session -->
        <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success" id="sessionMessage">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['message']) ?>
        </div>
        <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error" id="sessionError">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Statistiques rapides -->
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;">
            <?php
            $miniStats = [
                ['label' => 'Total clients', 'value' => number_format($stats['total']), 'icon' => 'fa-users', 'color' => '#16a34a', 'bg' => '#dcfce7'],
                ['label' => "Aujourd'hui", 'value' => $stats['aujourdhui'], 'icon' => 'fa-calendar-day', 'color' => '#2563eb', 'bg' => '#dbeafe'],
                ['label' => 'Cette semaine', 'value' => $stats['cette_semaine'], 'icon' => 'fa-calendar-week', 'color' => '#d97706', 'bg' => '#fef3c7'],
                ['label' => 'Ce mois', 'value' => $stats['ce_mois'], 'icon' => 'fa-calendar-alt', 'color' => '#9333ea', 'bg' => '#f3e8ff'],
            ];
            foreach ($miniStats as $s):
            ?>
            <div style="background:white;padding:14px 18px;border-radius:12px;display:flex;align-items:center;gap:14px;box-shadow:0 1px 3px rgba(0,0,0,.05);">
                <div style="width:40px;height:40px;border-radius:10px;background:<?= $s['bg'] ?>;color:<?= $s['color'] ?>;display:flex;align-items:center;justify-content:center;">
                    <i class="fas <?= $s['icon'] ?>"></i>
                </div>
                <div>
                    <div style="font-size:20px;font-weight:700;color:#1e293b;"><?= $s['value'] ?></div>
                    <div style="color:#64748b;font-size:13px;"><?= $s['label'] ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Barre de recherche et filtres -->
        <div class="card" style="margin-bottom:20px;">
            <form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
                <div style="flex:1;min-width:220px;">
                    <label style="font-size:13px;color:#64748b;margin-bottom:6px;display:block;">Rechercher</label>
                    <input type="text" name="search" class="form-control" placeholder="NIF/CINU, nom, téléphone ou email..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div>
                    <label style="font-size:13px;color:#64748b;margin-bottom:6px;display:block;">Type de pièce</label>
                    <select name="filter_type" class="form-control" style="min-width:140px;">
                        <option value="">Tous</option>
                        <?php foreach (['NIF', 'CINU', 'PASSEPORT', 'AUTRE'] as $t): ?>
                        <option value="<?= $t ?>" <?= $filter_type === $t ? 'selected' : '' ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex;gap:8px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrer</button>
                    <?php if (!empty($search) || !empty($filter_type)): ?>
                    <a href="liste_clients.php" class="btn btn-secondary"><i class="fas fa-times"></i> Réinitialiser</a>
                    <?php endif; ?>
                </div>
                <div style="margin-left:auto;display:flex;gap:8px;">
                    <a href="ajouter_client.php" class="btn btn-success"><i class="fas fa-plus"></i> Nouveau client</a>
                    <button type="button" class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Imprimer</button>
                </div>
            </form>
        </div>

        <!-- Tableau -->
        <div class="table-card">
            <div class="table-card-header">
                <h3><i class="fas fa-list"></i> <?= number_format($total_clients) ?> client(s) <?= !empty($search) ? '(filtré)' : '' ?></h3>
                <span style="color:#64748b;font-size:13px;">Page <?= $page ?> / <?= $total_pages ?></span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>NIF/CINU</th>
                            <th>Nom complet</th>
                            <th>Type pièce</th>
                            <th>Téléphone</th>
                            <th>Statut</th>
                            <th>Comptes</th>
                            <th>Date création</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): 
                            $totalComptes = $client['nb_comptes_titulaire'] + $client['nb_comptes_cotitulaire'];
                        ?>
                        <tr>
                            <td>
                                <?php if ($client['photo']): ?>
                                <img src="../<?= htmlspecialchars($client['photo']) ?>" style="width:38px;height:38px;border-radius:8px;object-fit:cover;">
                                <?php else: ?>
                                <div style="width:38px;height:38px;border-radius:8px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;">
                                    <i class="fas fa-user" style="color:#94a3b8;"></i>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= htmlspecialchars($client['id_client']) ?></strong></td>
                            <td><?= htmlspecialchars($client['prenom'] . ' ' . $client['nom']) ?></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($client['type_piece'] ?: '—') ?></span></td>
                            <td><?= htmlspecialchars($client['telephone'] ?: '—') ?></td>
                            <td>
                                <?php 
                                $isTitulaire = $client['nb_comptes_titulaire'] > 0;
                                $isCoTitulaire = $client['nb_comptes_cotitulaire'] > 0;
                                ?>
                                <?php if ($isTitulaire): ?>
                                <span class="client-statut badge-titulaire"><i class="fas fa-crown"></i> Titulaire</span>
                                <?php endif; ?>
                                <?php if ($isCoTitulaire): ?>
                                <span class="client-statut badge-cotitulaire"><i class="fas fa-users"></i> Co-titulaire</span>
                                <?php endif; ?>
                                <?php if (!$isTitulaire && !$isCoTitulaire): ?>
                                <span class="client-statut" style="background:#f1f5f9;color:#64748b;"><i class="fas fa-user"></i> Simple</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($totalComptes > 0): ?>
                                <span class="badge badge-compte"><i class="fas fa-credit-card"></i> <?= $totalComptes ?> compte(s)</span>
                                <?php else: ?>
                                <span class="badge" style="background:#f1f5f9;color:#64748b;">Aucun compte</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y', strtotime($client['created_at'])) ?></td>
                            <td>
                                <div style="display:flex;gap:5px;">
                                    <a href="verification.php?search=<?= urlencode($client['id_client']) ?>" class="btn-action view" title="Voir"><i class="fas fa-eye"></i></a>
                                    <a href="creer_compte.php?search_client=<?= urlencode($client['id_client']) ?>" class="btn-action add" title="Ouvrir compte"><i class="fas fa-plus"></i></a>
                                    <a href="modifier_compte.php?client_id=<?= $client['id'] ?>" class="btn-action edit" title="Modifier"><i class="fas fa-edit"></i></a>
                                    <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <a href="supprimer_client.php?id=<?= $client['id'] ?>&redirect=liste_clients.php"
                                       onclick="return confirm('⚠️ ATTENTION : Ce client sera définitivement supprimé.<?= $totalComptes > 0 ? ' Ses ' . $totalComptes . ' compte(s) seront également supprimés.' : '' ?> Confirmer la suppression ?')"
                                       class="btn-action delete" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($clients)): ?>
                        <tr>
                            <td colspan="9" style="text-align:center;padding:40px;">Aucun client trouvé.</span>
                                <a href="ajouter_client.php" class="btn btn-success" style="margin-top:12px;"><i class="fas fa-plus"></i> Ajouter un client</a>
                            </div>
                        </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div style="display:flex;justify-content:center;gap:7px;padding:18px;">
                <?php if ($page > 1): ?>
                <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&filter_type=<?= urlencode($filter_type) ?>" class="pagination-btn"><i class="fas fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&filter_type=<?= urlencode($filter_type) ?>" class="pagination-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&filter_type=<?= urlencode($filter_type) ?>" class="pagination-btn"><i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const msg = document.getElementById('sessionMessage');
            const err = document.getElementById('sessionError');
            if (msg) { msg.style.transition = 'opacity 0.5s'; msg.style.opacity = '0'; setTimeout(() => msg.remove(), 500); }
            if (err) { err.style.transition = 'opacity 0.5s'; err.style.opacity = '0'; setTimeout(() => err.remove(), 500); }
        }, 5000);
    </script>
</body>
</html>