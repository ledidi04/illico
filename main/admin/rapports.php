<?php
require_once '../config/connexion.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// ── Période sélectionnée ────────────────────────────────────────
$periode    = $_GET['periode']    ?? '';
$date_debut = $_GET['date_debut'] ?? date('Y-m-d');
$date_fin   = $_GET['date_fin']   ?? date('Y-m-d');

$titre_periode = '';

if ($periode) {
    switch ($periode) {
        case 'jour':
            $date_debut = $date_fin = date('Y-m-d');
            $titre_periode = "Aujourd'hui (" . date('d/m/Y') . ")";
            break;
        case 'semaine':
            $date_debut = date('Y-m-d', strtotime('monday this week'));
            $date_fin   = date('Y-m-d', strtotime('sunday this week'));
            $titre_periode = "Semaine du " . date('d/m/Y', strtotime($date_debut))
                           . " au " . date('d/m/Y', strtotime($date_fin));
            break;
        case 'mois':
            $date_debut = date('Y-m-01');
            $date_fin   = date('Y-m-t');
            $titre_periode = date('F Y');
            break;
        case 'annee':
            $date_debut = date('Y-01-01');
            $date_fin   = date('Y-12-31');
            $titre_periode = "Année " . date('Y');
            break;
        case 'personnalise':
            $titre_periode = "Du " . date('d/m/Y', strtotime($date_debut))
                           . " au " . date('d/m/Y', strtotime($date_fin));
            break;
    }
}

$error          = '';
$rapport_genere = false;
$rapport_data   = [];

// ── Requêtes base de données (uniquement si une période est choisie) ─
if ($periode) {
    try {
        // Résumé transactions
        $tx = $pdo->prepare("
            SELECT
                COUNT(*) AS nb_transactions,
                COALESCE(SUM(CASE WHEN type='depot'   THEN montant ELSE 0 END), 0) AS depots,
                COALESCE(SUM(CASE WHEN type='retrait' THEN montant ELSE 0 END), 0) AS retraits,
                COALESCE(SUM(montant), 0) AS volume_total
            FROM transactions
            WHERE DATE(date_transaction) BETWEEN ? AND ?
        ");
        $tx->execute([$date_debut, $date_fin]);
        $tx_data = $tx->fetch();

        // Liste détaillée
        $transactions = $pdo->prepare("
            SELECT t.*, c.id_compte, c.devise,
                   CONCAT(cl.nom,' ',cl.prenom)      AS client,
                   u.username                         AS operateur,
                   u.nom_complet                      AS operateur_nom,
                   s.code                             AS succursale,
                   s.nom                              AS succursale_nom
            FROM transactions t
            JOIN comptes     c  ON t.compte_id        = c.id
            JOIN clients     cl ON c.titulaire_principal_id = cl.id
            JOIN utilisateurs u ON t.utilisateur_id   = u.id
            JOIN succursales  s ON t.succursale_id    = s.id
            WHERE DATE(t.date_transaction) BETWEEN ? AND ?
            ORDER BY t.date_transaction DESC
        ");
        $transactions->execute([$date_debut, $date_fin]);
        $liste_transactions = $transactions->fetchAll();

        // Nouveaux clients
        $clients = $pdo->prepare("SELECT COUNT(*) AS nb FROM clients WHERE DATE(created_at) BETWEEN ? AND ?");
        $clients->execute([$date_debut, $date_fin]);
        $nb_clients = $clients->fetch()['nb'];

        // Nouveaux comptes
        $comptes = $pdo->prepare("SELECT COUNT(*) AS nb, COALESCE(SUM(solde),0) AS total FROM comptes WHERE DATE(created_at) BETWEEN ? AND ?");
        $comptes->execute([$date_debut, $date_fin]);
        $comptes_data = $comptes->fetch();

        // Totaux généraux
        $total_clients       = $pdo->query("SELECT COUNT(*) AS t FROM clients")->fetch()['t'];
        $total_personnel     = $pdo->query("SELECT COUNT(*) AS t FROM utilisateurs WHERE actif=1")->fetch()['t'];
        $total_comptes       = $pdo->query("SELECT COUNT(*) AS t FROM comptes WHERE statut='actif'")->fetch()['t'];
        $total_depots_global = $pdo->query("SELECT COALESCE(SUM(solde),0) AS t FROM comptes WHERE statut='actif'")->fetch()['t'];

        // Stats par succursale
        $stats_succursales = $pdo->query("
            SELECT s.code, s.nom, COUNT(DISTINCT c.id) AS nb_comptes,
                   COALESCE(SUM(c.solde),0) AS total_depots
            FROM succursales s
            LEFT JOIN comptes c ON s.id=c.succursale_id AND c.statut='actif'
            GROUP BY s.id ORDER BY s.code
        ")->fetchAll();

        $rapport_genere = true;
        $rapport_data = [
            'titre'               => $titre_periode,
            'date_debut'          => $date_debut,
            'date_fin'            => $date_fin,
            'nb_transactions'     => $tx_data['nb_transactions'],
            'volume_total'        => $tx_data['volume_total'],
            'depots'              => $tx_data['depots'],
            'retraits'            => $tx_data['retraits'],
            'nb_clients'          => $nb_clients,
            'nb_comptes'          => $comptes_data['nb'],
            'total_clients'       => $total_clients,
            'total_personnel'     => $total_personnel,
            'total_comptes'       => $total_comptes,
            'total_depots_global' => $total_depots_global,
            'transactions'        => $liste_transactions,
            'stats_succursales'   => $stats_succursales,
            'date_generation'     => date('d/m/Y'),
            'heure_generation'    => date('H:i:s'),
            'operateur'           => $_SESSION['nom_complet'],
            'operateur_username'  => $_SESSION['username'],
            'succursale'          => $_SESSION['succursale_nom'] ?? 'Succursale',
        ];

    } catch (PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

$currentPage = 'rapports';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports — S&P illico</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/common.css">
    <link rel="icon" type="image/png" href="../logo.jpeg">
    <style>
        /* ── Cartes résumé ── */
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
            box-shadow: 0 1px 3px rgba(0,0,0,.05);
        }
        .resume-card .value { font-size: 26px; font-weight: 700; color: #1e293b; }
        .resume-card .label { color: #64748b; font-size: 13px; margin-top: 8px; }
        .resume-card.depot   { background: linear-gradient(135deg,#dcfce7,#f0fdf4); border-left: 4px solid #16a34a; }
        .resume-card.retrait { background: linear-gradient(135deg,#fee2e2,#fef2f2); border-left: 4px solid #dc2626; }

        /* ── Tableau ── */
        .table-card { background: white; border-radius: 16px; overflow: hidden; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.05); }
        .table-header { padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .table-container { overflow-x: auto; padding: 0 20px 20px; }
        table  { width: 100%; border-collapse: collapse; }
        th     { text-align: left; padding: 12px 0; color: #64748b; font-size: 12px; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
        td     { padding: 12px 0; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
        .badge-success { background:#dcfce7; color:#166534; padding:4px 10px; border-radius:20px; font-size:11px; display:inline-block; }
        .badge-danger  { background:#fee2e2; color:#991b1b; padding:4px 10px; border-radius:20px; font-size:11px; display:inline-block; }

        /* ── Bouton PDF ── */
        .btn-pdf {
            background: #dc2626; color: white; padding: 12px 24px;
            border-radius: 10px; text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px;
            font-weight: 500; transition: all .2s;
        }
        .btn-pdf:hover { background: #b91c1c; transform: translateY(-1px); }

        /* ── Sélecteur de période ── */
        .filtres-card  { background: white; padding: 28px; border-radius: 20px; margin-bottom: 24px; box-shadow: 0 1px 4px rgba(0,0,0,.06); }
        .filtres-card h2 { font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 6px; }
        .filtres-card p  { color: #64748b; font-size: 14px; margin-bottom: 24px; }

        .periode-buttons { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }

        .periode-btn {
            padding: 11px 22px;
            background: #f1f5f9;
            border: 2px solid transparent;
            border-radius: 10px;
            cursor: pointer;
            color: #475569;
            font-weight: 600;
            font-size: 14px;
            transition: all .2s;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }
        .periode-btn:hover   { background: #e2e8f0; color: #1e293b; }
        .periode-btn.active  { background: #3b82f6; color: white; border-color: #3b82f6; }

        /* ── Champs dates personnalisées ── */
        .custom-dates {
            display: none;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            background: #f8fafc;
            padding: 18px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }
        .custom-dates.visible { display: flex; }
        .custom-dates label   { font-size: 13px; font-weight: 600; color: #475569; }
        .custom-dates input[type="date"] {
            padding: 9px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            color: #1e293b;
            background: white;
            outline: none;
            transition: border .2s;
        }
        .custom-dates input[type="date"]:focus { border-color: #3b82f6; }
        .custom-dates span { color: #94a3b8; }

        /* ── Bouton principal Générer ── */
        .btn-generer {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 13px 32px;
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all .25s;
            box-shadow: 0 4px 14px rgba(30,58,138,.35);
        }
        .btn-generer:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(30,58,138,.45); }
        .btn-generer:disabled { opacity: .5; cursor: not-allowed; transform: none; }

        /* ── En-tête rapport ── */
        .rapport-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .rapport-header h2 { color: #1e293b; margin-bottom: 4px; font-size: 20px; }

        /* ── Totaux généraux ── */
        .totaux-generaux { background: #f8fafc; border-radius: 12px; padding: 20px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        .totaux-generaux h3 { margin-bottom: 16px; color: #1e293b; font-size: 15px; }
        .totaux-inner { display: grid; grid-template-columns: repeat(4,1fr); gap: 20px; }
        .totaux-inner .ti { text-align: center; }
        .totaux-inner .tv { font-size: 22px; font-weight: 700; }
        .totaux-inner .tl { color: #64748b; font-size: 12px; margin-top: 4px; }

        /* ── Alerte ── */
        .alert-error { background:#fee2e2; color:#991b1b; padding:14px 18px; border-radius:10px; margin-bottom:20px; display:flex; align-items:center; gap:10px; }

        @media print {
            .sidebar, .top-bar, .filtres-card, .btn, .logout-btn, .no-print, .btn-pdf { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 10px !important; }
            body { background: white !important; }
        }
        @media (max-width: 768px) {
            .resume-grid { grid-template-columns: 1fr 1fr; }
            .totaux-inner { grid-template-columns: 1fr 1fr; }
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
        <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- ════════════════════════════════════════════════════
             FORMULAIRE DE SÉLECTION (toujours visible en haut)
        ════════════════════════════════════════════════════ -->
        <div class="filtres-card no-print">
            <h2><i class="fas fa-filter" style="color:#3b82f6;"></i> Sélectionner la période</h2>
            <p>Choisissez une période puis cliquez sur <strong>Générer le rapport</strong>.</p>

            <!-- Boutons de période -->
            <div class="periode-buttons">
                <button type="button" class="periode-btn <?= $periode === 'jour'        ? 'active' : '' ?>" data-periode="jour">
                    <i class="fas fa-calendar-day"></i> Aujourd'hui
                </button>
                <button type="button" class="periode-btn <?= $periode === 'semaine'     ? 'active' : '' ?>" data-periode="semaine">
                    <i class="fas fa-calendar-week"></i> Cette semaine
                </button>
                <button type="button" class="periode-btn <?= $periode === 'mois'        ? 'active' : '' ?>" data-periode="mois">
                    <i class="fas fa-calendar-alt"></i> Ce mois
                </button>
                <button type="button" class="periode-btn <?= $periode === 'annee'       ? 'active' : '' ?>" data-periode="annee">
                    <i class="fas fa-calendar"></i> Cette année
                </button>
                <button type="button" class="periode-btn <?= $periode === 'personnalise' ? 'active' : '' ?>" data-periode="personnalise">
                    <i class="fas fa-sliders-h"></i> Personnalisé
                </button>
            </div>

            <!-- Champs dates (affichés seulement si "personnalisé") -->
            <div class="custom-dates <?= $periode === 'personnalise' ? 'visible' : '' ?>" id="customDates">
                <label for="dateDebut">Du</label>
                <input type="date" id="dateDebut" value="<?= htmlspecialchars($date_debut) ?>" max="<?= date('Y-m-d') ?>">
                <span>au</span>
                <label for="dateFin">Au</label>
                <input type="date" id="dateFin"   value="<?= htmlspecialchars($date_fin) ?>"   max="<?= date('Y-m-d') ?>">
            </div>

            <!-- Bouton Générer -->
            <button type="button" class="btn-generer" id="btnGenerer" <?= !$periode ? 'disabled' : '' ?>>
                <i class="fas fa-chart-bar"></i>
                Générer le rapport
            </button>

            <!-- Champ caché pour savoir quelle période est sélectionnée -->
            <input type="hidden" id="selectedPeriode" value="<?= htmlspecialchars($periode) ?>">
        </div>

        <?php if ($rapport_genere): ?>
        <!-- ════════════════════════════════════════════════════
             AFFICHAGE DU RAPPORT
        ════════════════════════════════════════════════════ -->

        <!-- En-tête du rapport -->
        <div class="rapport-header">
            <div>
                <h2><i class="fas fa-file-chart-bar" style="color:#3b82f6;"></i>
                    Rapport&nbsp;: <?= htmlspecialchars($rapport_data['titre']) ?>
                </h2>
                <p style="color:#64748b;font-size:13px;">
                    Généré le <?= $rapport_data['date_generation'] ?> à <?= $rapport_data['heure_generation'] ?>
                    par <strong><?= htmlspecialchars($rapport_data['operateur']) ?></strong>
                </p>
            </div>
            <div style="display:flex;gap:10px;" class="no-print">
                <a href="../generate_pdf.php?action=rapport&periode=<?= urlencode($periode) ?>&date_debut=<?= urlencode($date_debut) ?>&date_fin=<?= urlencode($date_fin) ?>"
                   target="_blank" class="btn-pdf">
                    <i class="fas fa-file-pdf"></i> Télécharger PDF
                </a>
            </div>
        </div>

        <!-- KPI -->
        <div class="resume-grid">
            <div class="resume-card">
                <div class="value"><?= number_format($rapport_data['nb_transactions']) ?></div>
                <div class="label"><i class="fas fa-exchange-alt"></i> Transactions</div>
            </div>
            <div class="resume-card">
                <div class="value"><?= number_format($rapport_data['volume_total'], 0, ',', ' ') ?>&nbsp;HTG</div>
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

        <!-- Dépôts / Retraits -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">
            <div class="resume-card depot">
                <div class="value" style="color:#166534;">
                    <i class="fas fa-arrow-down"></i>
                    <?= number_format($rapport_data['depots'], 0, ',', ' ') ?>&nbsp;HTG
                </div>
                <div class="label">Total des dépôts sur la période</div>
            </div>
            <div class="resume-card retrait">
                <div class="value" style="color:#991b1b;">
                    <i class="fas fa-arrow-up"></i>
                    <?= number_format($rapport_data['retraits'], 0, ',', ' ') ?>&nbsp;HTG
                </div>
                <div class="label">Total des retraits sur la période</div>
            </div>
        </div>

        <!-- Totaux généraux -->
        <div class="totaux-generaux">
            <h3><i class="fas fa-database" style="color:#3b82f6;"></i> Totaux généraux (ensemble des comptes)</h3>
            <div class="totaux-inner">
                <div class="ti"><div class="tv" style="color:#3b82f6;"><?= number_format($rapport_data['total_clients']) ?></div><div class="tl"><i class="fas fa-users"></i> Total clients</div></div>
                <div class="ti"><div class="tv" style="color:#10b981;"><?= number_format($rapport_data['total_personnel']) ?></div><div class="tl"><i class="fas fa-user-tie"></i> Personnel actif</div></div>
                <div class="ti"><div class="tv" style="color:#f59e0b;"><?= number_format($rapport_data['total_comptes']) ?></div><div class="tl"><i class="fas fa-credit-card"></i> Comptes actifs</div></div>
                <div class="ti"><div class="tv" style="color:#8b5cf6;"><?= number_format($rapport_data['total_depots_global'], 0, ',', ' ') ?>&nbsp;HTG</div><div class="tl"><i class="fas fa-money-bill"></i> Total dépôts</div></div>
            </div>
        </div>

        <!-- Détail transactions -->
        <div class="table-card">
            <div class="table-header">
                <h3 style="font-size:15px;"><i class="fas fa-list"></i> Détail des transactions</h3>
                <span style="background:#e2e8f0;color:#475569;padding:4px 12px;border-radius:20px;font-size:13px;">
                    <?= count($rapport_data['transactions']) ?> transactions
                </span>
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
                        <?php if (empty($rapport_data['transactions'])): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;padding:40px;color:#64748b;">
                                <i class="fas fa-inbox" style="font-size:36px;display:block;margin-bottom:10px;opacity:.4;"></i>
                                Aucune transaction sur cette période
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($rapport_data['transactions'] as $t): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($t['date_transaction'])) ?></td>
                                <td><strong><?= htmlspecialchars($t['id_compte']) ?></strong></td>
                                <td><?= htmlspecialchars($t['client']) ?></td>
                                <td>
                                    <span class="badge-<?= $t['type'] === 'depot' ? 'success' : 'danger' ?>">
                                        <i class="fas fa-arrow-<?= $t['type'] === 'depot' ? 'down' : 'up' ?>"></i>
                                        <?= ucfirst($t['type']) ?>
                                    </span>
                                </td>
                                <td><strong><?= number_format($t['montant'], 2, ',', ' ') ?> <?= $t['devise'] ?></strong></td>
                                <td><?= htmlspecialchars($t['operateur']) ?></td>
                                <td><?= htmlspecialchars($t['succursale']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Actions bas de page -->
        <div style="display:flex;gap:15px;justify-content:center;margin-top:10px;" class="no-print">
            <a href="dashboard.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Retour au tableau de bord
            </a>
        </div>

        <?php elseif ($periode && !$error): ?>
        <!-- Impossible normalement, mais sécurité -->
        <div style="text-align:center;padding:60px;color:#64748b;">
            <i class="fas fa-exclamation-triangle" style="font-size:36px;margin-bottom:12px;color:#f59e0b;display:block;"></i>
            Impossible de générer le rapport. Vérifiez la période sélectionnée.
        </div>

        <?php else: ?>
        <!-- État initial : aucune période encore choisie -->
        <div style="text-align:center;padding:60px;color:#94a3b8;">
            <i class="fas fa-chart-bar" style="font-size:48px;display:block;margin-bottom:16px;opacity:.3;"></i>
            <p style="font-size:15px;">Sélectionnez une période ci-dessus et cliquez sur <strong style="color:#3b82f6;">Générer le rapport</strong>.</p>
        </div>
        <?php endif; ?>

    </div><!-- /main-content -->

    <script>
    (function () {
        const btns            = document.querySelectorAll('.periode-btn');
        const customDates     = document.getElementById('customDates');
        const dateDebut       = document.getElementById('dateDebut');
        const dateFin         = document.getElementById('dateFin');
        const btnGenerer      = document.getElementById('btnGenerer');
        const selectedPeriode = document.getElementById('selectedPeriode');

        // ── Sélection d'une période ──────────────────────────────
        btns.forEach(btn => {
            btn.addEventListener('click', () => {
                // Mettre à jour le bouton actif
                btns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                const p = btn.dataset.periode;
                selectedPeriode.value = p;

                // Afficher / masquer les champs personnalisés
                if (p === 'personnalise') {
                    customDates.classList.add('visible');
                } else {
                    customDates.classList.remove('visible');
                }

                // Activer le bouton Générer
                btnGenerer.disabled = false;
            });
        });

        // ── Validation dates personnalisées ────────────────────
        function validateDates() {
            const d = dateDebut.value;
            const f = dateFin.value;
            if (d && f && d > f) {
                dateFin.setCustomValidity('La date de fin doit être après la date de début.');
                dateFin.reportValidity();
                return false;
            }
            dateFin.setCustomValidity('');
            return true;
        }

        dateDebut.addEventListener('change', () => {
            // La date de fin ne peut pas être avant la date de début
            dateFin.min = dateDebut.value;
            validateDates();
        });

        dateFin.addEventListener('change', validateDates);

        // ── Génération du rapport ───────────────────────────────
        btnGenerer.addEventListener('click', () => {
            const p = selectedPeriode.value;

            if (!p) {
                alert('Veuillez sélectionner une période.');
                return;
            }

            if (p === 'personnalise') {
                if (!dateDebut.value || !dateFin.value) {
                    alert('Veuillez renseigner les deux dates pour la période personnalisée.');
                    dateDebut.focus();
                    return;
                }
                if (!validateDates()) return;
            }

            // Construire l'URL et naviguer
            let url = '?periode=' + encodeURIComponent(p);

            if (p === 'personnalise') {
                url += '&date_debut=' + encodeURIComponent(dateDebut.value);
                url += '&date_fin='   + encodeURIComponent(dateFin.value);
            }

            // Feedback visuel
            btnGenerer.disabled = true;
            btnGenerer.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Chargement…';

            window.location.href = url;
        });

        // ── Permettre de régénérer via Entrée dans les champs dates ──
        [dateDebut, dateFin].forEach(input => {
            input.addEventListener('keydown', e => {
                if (e.key === 'Enter') btnGenerer.click();
            });
        });
    })();
    </script>
</body>
</html>