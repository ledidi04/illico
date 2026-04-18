<?php
require_once '../config/connexion.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['caissier','secretaire','admin'])) {
    header('Location: ../index.php');
    exit;
}

$comptes      = [];
$compte       = null;
$client_seul  = null;
$cotitulaires = [];
$transactions = [];
$error        = '';
$search       = trim($_GET['search']    ?? '');
$compte_sel   = trim($_GET['compte_id'] ?? '');

if (!empty($search)) {
    try {
        $stmt = $pdo->prepare("
            SELECT c.id, c.id_compte, c.solde, c.devise, c.statut, c.date_creation,
                   tc.nom AS type_compte, tc.taux_interet, tc.solde_minimum,
                   s.code AS succursale_code, s.nom AS succursale_nom,
                   CONCAT(cl.prenom,' ',cl.nom) AS titulaire,
                   cl.id_client, cl.telephone, cl.email, cl.adresse,
                   cl.date_naissance, cl.lieu_naissance, cl.photo,
                   cl.id AS client_id_pk, cl.nom AS client_nom, cl.prenom AS client_prenom
            FROM comptes c
            JOIN types_comptes tc ON c.type_compte_id=tc.id
            JOIN succursales s    ON c.succursale_id=s.id
            JOIN clients cl       ON c.titulaire_principal_id=cl.id
            WHERE c.id_compte=? OR cl.id_client LIKE ? OR cl.telephone LIKE ? OR CONCAT(cl.prenom,' ',cl.nom) LIKE ?
            ORDER BY c.date_creation DESC LIMIT 20
        ");
        $like = "%$search%";
        $stmt->execute([$search, $search, $like, $like]);
        $comptes = $stmt->fetchAll();

        if (empty($comptes)) {
            $stmt = $pdo->prepare("SELECT * FROM clients WHERE id_client LIKE ? OR telephone LIKE ? OR CONCAT(prenom,' ',nom) LIKE ? OR CONCAT(nom,' ',prenom) LIKE ? LIMIT 5");
            $stmt->execute([$search,$like,$like,$like]);
            $css = $stmt->fetchAll();
            if (!empty($css)) $client_seul = $css[0];
            else $error = "Aucun client ni compte trouvé pour « ".htmlspecialchars($search)." ».";
        } elseif (count($comptes) === 1 || !empty($compte_sel)) {
            $id_show = !empty($compte_sel) ? $compte_sel : $comptes[0]['id'];
            foreach ($comptes as $c) { if ($c['id']==$id_show) { $compte=$c; break; } }
        }

        if ($compte) {
            $stCot = $pdo->prepare("SELECT cl.* FROM clients cl JOIN compte_cotitulaires cc ON cl.id=cc.client_id WHERE cc.compte_id=?");
            $stCot->execute([$compte['id']]);
            $cotitulaires = $stCot->fetchAll();

            $stTx = $pdo->prepare("
                SELECT t.*, u.nom_complet AS operateur, s.code AS succ_code
                FROM transactions t JOIN utilisateurs u ON t.utilisateur_id=u.id
                JOIN succursales s ON t.succursale_id=s.id
                WHERE t.compte_id=? ORDER BY t.date_transaction DESC LIMIT 10
            ");
            $stTx->execute([$compte['id']]);
            $transactions = $stTx->fetchAll();
        }
    } catch (PDOException $e) {
        $error = "Erreur : ".$e->getMessage();
    }
}

$currentPage = 'verification';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification - S&P illico</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/common.css">
    <style>
        .info-grid { display:grid;grid-template-columns:repeat(2,1fr);gap:14px; }
        .info-item { padding:12px;background:#f8fafc;border-radius:9px; }
        .info-label { color:#64748b;font-size:12px;margin-bottom:4px; }
        .info-value { color:#1e293b;font-size:14px;font-weight:500; }
        .badge { padding:4px 10px;border-radius:20px;font-size:11px;font-weight:500; }
        .badge-success { background:#dcfce7;color:#166534; }
        .badge-danger  { background:#fee2e2;color:#991b1b; }
        /* Boutons PDF */
        .pdf-btn-group { display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin:16px 0; }
        .pdf-btn { display:flex;align-items:center;justify-content:center;gap:8px;padding:11px 16px;border-radius:10px;text-decoration:none;font-weight:600;font-size:13px;transition:filter .15s; }
        .pdf-btn:hover { filter:brightness(.92); }
        .pdf-btn-depot  { background:#16a34a;color:white; }
        .pdf-btn-client { background:#1e3a8a;color:white; }
        .pdf-btn-compte { background:#6366f1;color:white; }
        .pdf-btn-gray   { background:#64748b;color:white; }
    </style>
</head>
<body>
    <?php require_once '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>Vérification de compte</h1>
                <div class="breadcrumb"><a href="dashboard.php">Accueil</a> / Vérification</div>
            </div>
            <div class="top-right">
                <span class="top-succursale"><i class="fas fa-building"></i> <?= htmlspecialchars($_SESSION['succursale_nom']) ?></span>
                <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
            </div>
        </div>

        <!-- Recherche -->
        <div class="card" style="margin-bottom:20px;">
            <form method="get" style="display:flex;gap:12px;">
                <input type="text" name="search" class="form-control"
                       placeholder="N° de compte, NIF, CINU, téléphone ou nom..."
                       value="<?= htmlspecialchars($search) ?>" style="flex:1;font-size:15px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Rechercher</button>
                <?php if (!empty($search)): ?>
                <a href="verification.php" class="btn btn-secondary"><i class="fas fa-times"></i> Effacer</a>
                <?php endif; ?>
            </form>
            <p style="margin-top:10px;color:#64748b;font-size:13px;">
                <i class="fas fa-info-circle"></i>
                N° de compte (5 chiffres), NIF/CINU, téléphone ou nom.
            </p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Plusieurs comptes → sélection -->
        <?php if (!empty($comptes) && count($comptes) > 1 && !$compte): ?>
        <div class="card">
            <h3 style="margin-bottom:16px;"><i class="fas fa-list" style="color:#3b82f6;"></i> <?= count($comptes) ?> compte(s) trouvé(s)</h3>
            <table style="width:100%;border-collapse:collapse;">
                <thead><tr>
                    <?php foreach (['N° Compte','Titulaire','NIF/CINU','Type','Solde','Statut','Action'] as $h): ?>
                    <th style="padding:10px 0;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:11px;text-transform:uppercase;"><?= $h ?></th>
                    <?php endforeach; ?>
                </tr></thead>
                <tbody>
                    <?php foreach ($comptes as $c): ?>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:10px 0;"><strong><?= htmlspecialchars($c['id_compte']) ?></strong></td>
                        <td style="padding:10px 0;"><?= htmlspecialchars($c['titulaire']) ?></td>
                        <td style="padding:10px 0;"><?= htmlspecialchars($c['id_client']) ?></td>
                        <td style="padding:10px 0;"><?= htmlspecialchars($c['type_compte']) ?></td>
                        <td style="padding:10px 0;"><?= number_format($c['solde'],2,',',' ') ?> <?= $c['devise'] ?></td>
                        <td style="padding:10px 0;"><span class="badge <?= $c['statut']==='actif'?'badge-success':'badge-danger' ?>"><?= ucfirst($c['statut']) ?></span></td>
                        <td style="padding:10px 0;"><a href="?search=<?= urlencode($search) ?>&compte_id=<?= $c['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> Voir</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Client sans compte -->
        <?php if ($client_seul && !$compte): ?>
        <div class="card">
            <div style="display:flex;align-items:center;gap:14px;margin-bottom:18px;">
                <div style="width:50px;height:50px;border-radius:50%;background:#fef3c7;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-user" style="color:#d97706;font-size:20px;"></i>
                </div>
                <div>
                    <h3><?= htmlspecialchars($client_seul['prenom'].' '.$client_seul['nom']) ?></h3>
                    <p style="color:#64748b;font-size:13px;">Client enregistré — aucun compte bancaire ouvert</p>
                </div>
            </div>
            <div class="info-grid" style="margin-bottom:18px;">
                <div class="info-item"><div class="info-label">NIF/CINU</div><div class="info-value"><?= htmlspecialchars($client_seul['id_client']) ?></div></div>
                <div class="info-item"><div class="info-label">Type de pièce</div><div class="info-value"><?= htmlspecialchars($client_seul['type_piece']?:'—') ?></div></div>
                <div class="info-item"><div class="info-label">Téléphone</div><div class="info-value"><?= htmlspecialchars($client_seul['telephone']?:'—') ?></div></div>
                <div class="info-item"><div class="info-label">Email</div><div class="info-value"><?= htmlspecialchars($client_seul['email']?:'—') ?></div></div>
                <div class="info-item"><div class="info-label">Date de naissance</div><div class="info-value"><?= $client_seul['date_naissance']?date('d/m/Y',strtotime($client_seul['date_naissance'])):'—' ?></div></div>
                <div class="info-item"><div class="info-label">Lieu de naissance</div><div class="info-value"><?= htmlspecialchars($client_seul['lieu_naissance']?:'—') ?></div></div>
                <div class="info-item" style="grid-column:span 2;"><div class="info-label">Adresse</div><div class="info-value"><?= htmlspecialchars($client_seul['adresse']?:'—') ?></div></div>
            </div>

            <!-- PDF fiche client seul -->
            <div class="pdf-btn-group" style="grid-template-columns:1fr 1fr;">
                <a href="../generate_pdf.php?action=fiche_client_seul&id_client=<?= urlencode($client_seul['id_client']) ?>"
                   target="_blank" class="pdf-btn pdf-btn-client">
                    <i class="fas fa-file-pdf"></i> Fiche client PDF
                </a>
                <?php if (in_array($_SESSION['role'],['admin','secretaire'])): ?>
                <a href="creer_compte.php?search_client=<?= urlencode($client_seul['id_client']) ?>" class="pdf-btn pdf-btn-depot">
                    <i class="fas fa-plus"></i> Ouvrir un compte
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Détail d'un compte -->
        <?php if ($compte): ?>
        <div>
            <!-- En-tête bleu -->
            <div style="background:linear-gradient(135deg,#1e3a8a 0%,#3b82f6 100%);color:white;padding:26px;border-radius:14px 14px 0 0;">
                <div style="font-size:26px;font-weight:700;margin-bottom:5px;">Compte N° <?= htmlspecialchars($compte['id_compte']) ?></div>
                <div style="font-size:16px;margin-bottom:10px;">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($compte['titulaire']) ?>
                    <span style="margin-left:16px;font-size:13px;opacity:.85;"><i class="fas fa-id-card"></i> <?= htmlspecialchars($compte['id_client']) ?></span>
                </div>
                <div style="font-size:28px;font-weight:700;"><?= number_format($compte['solde'],2,',',' ') ?> <?= $compte['devise'] ?></div>
                <div style="margin-top:10px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <span class="badge <?= $compte['statut']==='actif'?'badge-success':'badge-danger' ?>" style="font-size:13px;padding:5px 14px;">
                        <i class="fas fa-<?= $compte['statut']==='actif'?'check-circle':'ban' ?>"></i>
                        <?= $compte['statut']==='actif'?'Compte actif':'Compte bloqué' ?>
                    </span>
                    <?php if (in_array($_SESSION['role'],['admin','secretaire'])): ?>
                    <a href="modifier_compte.php?id=<?= $compte['id'] ?>" style="background:rgba(255,255,255,.2);color:white;padding:7px 14px;border-radius:8px;text-decoration:none;font-size:13px;">
                        <i class="fas fa-edit"></i> Modifier
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <div style="background:white;border-radius:0 0 14px 14px;padding:24px;box-shadow:0 4px 6px rgba(0,0,0,.05);">

                <!-- ══ BOUTONS PDF ══════════════════════════════ -->
                <div style="background:#f8fafc;border-radius:12px;padding:16px;margin-bottom:22px;border:1px solid #e2e8f0;">
                    <p style="font-size:12px;color:#64748b;margin-bottom:10px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">
                        <i class="fas fa-file-pdf" style="color:#dc2626;"></i> Télécharger les documents PDF
                    </p>
                    <div class="pdf-btn-group">
                        <a href="../generate_pdf.php?action=fiche_client&id_compte=<?= urlencode($compte['id_compte']) ?>"
                           target="_blank" class="pdf-btn pdf-btn-client">
                            <i class="fas fa-id-card"></i> Fiche client (avec photo)
                        </a>
                        <a href="../generate_pdf.php?action=fiche_compte&id_compte=<?= urlencode($compte['id_compte']) ?>"
                           target="_blank" class="pdf-btn pdf-btn-compte">
                            <i class="fas fa-file-alt"></i> Relevé de compte
                        </a>
                    </div>
                    <?php if (!empty($cotitulaires)): ?>
                    <div class="pdf-btn-group" style="margin-top:8px;">
                        <?php foreach ($cotitulaires as $cot): ?>
                        <a href="../generate_pdf.php?action=fiche_client&id_compte=<?= urlencode($compte['id_compte']) ?>&id_client_recherche=<?= urlencode($cot['id_client']) ?>"
                           target="_blank" class="pdf-btn pdf-btn-gray" style="font-size:12px;">
                            <i class="fas fa-user-friends"></i> Co-titulaire : <?= htmlspecialchars($cot['prenom'].' '.$cot['nom']) ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <p style="font-size:11px;color:#94a3b8;margin-top:8px;margin-bottom:0;">
                        La fiche client inclut l'espace photo et les informations personnelles.
                        Le relevé de compte inclut le solde et les 10 dernières opérations.
                    </p>
                </div>

                <!-- Infos compte -->
                <div class="info-grid" style="margin-bottom:22px;">
                    <div class="info-item"><div class="info-label"><i class="fas fa-credit-card"></i> Type</div><div class="info-value"><?= htmlspecialchars($compte['type_compte']) ?></div></div>
                    <div class="info-item"><div class="info-label"><i class="fas fa-calendar"></i> Création</div><div class="info-value"><?= date('d/m/Y',strtotime($compte['date_creation'])) ?></div></div>
                    <div class="info-item"><div class="info-label"><i class="fas fa-building"></i> Succursale</div><div class="info-value"><?= htmlspecialchars($compte['succursale_code'].' — '.$compte['succursale_nom']) ?></div></div>
                    <div class="info-item"><div class="info-label"><i class="fas fa-percent"></i> Taux</div><div class="info-value"><?= $compte['taux_interet'] ?>%</div></div>
                </div>

                <!-- Titulaire -->
                <h4 style="margin-bottom:12px;color:#1e293b;"><i class="fas fa-user-circle"></i> Titulaire principal</h4>
                <div class="info-grid" style="margin-bottom:20px;">
                    <?php if ($compte['photo']): ?>
                    <div class="info-item" style="grid-column:span 2;display:flex;align-items:center;gap:12px;">
                        <img src="../<?= htmlspecialchars($compte['photo']) ?>" style="width:60px;height:60px;border-radius:10px;object-fit:cover;">
                        <div><strong><?= htmlspecialchars($compte['titulaire']) ?></strong><br><span style="color:#64748b;"><?= htmlspecialchars($compte['id_client']) ?></span></div>
                    </div>
                    <?php endif; ?>
                    <div class="info-item"><div class="info-label">Téléphone</div><div class="info-value"><?= htmlspecialchars($compte['telephone']?:'—') ?></div></div>
                    <div class="info-item"><div class="info-label">Email</div><div class="info-value"><?= htmlspecialchars($compte['email']?:'—') ?></div></div>
                    <div class="info-item"><div class="info-label">Date de naissance</div><div class="info-value"><?= $compte['date_naissance']?date('d/m/Y',strtotime($compte['date_naissance'])):'—' ?></div></div>
                    <div class="info-item"><div class="info-label">Lieu de naissance</div><div class="info-value"><?= htmlspecialchars($compte['lieu_naissance']?:'—') ?></div></div>
                    <div class="info-item" style="grid-column:span 2;"><div class="info-label">Adresse</div><div class="info-value"><?= htmlspecialchars($compte['adresse']?:'—') ?></div></div>
                </div>

                <!-- Co-titulaires -->
                <?php if (!empty($cotitulaires)): ?>
                <h4 style="margin-bottom:10px;color:#1e293b;"><i class="fas fa-users"></i> Co-titulaires (<?= count($cotitulaires) ?>)</h4>
                <div style="margin-bottom:20px;">
                    <?php foreach ($cotitulaires as $cot): ?>
                    <div style="display:flex;align-items:center;gap:10px;padding:9px;background:#f8fafc;border-radius:8px;margin-bottom:7px;">
                        <i class="fas fa-user" style="color:#3b82f6;"></i>
                        <strong><?= htmlspecialchars($cot['prenom'].' '.$cot['nom']) ?></strong>
                        <span style="color:#64748b;"><?= htmlspecialchars($cot['id_client']) ?></span>
                        <?php if ($cot['telephone']): ?><span style="color:#64748b;"><i class="fas fa-phone fa-xs"></i> <?= htmlspecialchars($cot['telephone']) ?></span><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Actions rapides -->
                <?php if ($compte['statut']==='actif'): ?>
                <div style="display:flex;gap:10px;margin-bottom:20px;">
                    <a href="depot.php?id_compte=<?= urlencode($compte['id_compte']) ?>" class="btn btn-success btn-sm"><i class="fas fa-arrow-down"></i> Dépôt</a>
                    <a href="retrait.php?id_compte=<?= urlencode($compte['id_compte']) ?>" class="btn btn-danger btn-sm"><i class="fas fa-arrow-up"></i> Retrait</a>
                </div>
                <?php endif; ?>

                <!-- Transactions -->
                <h4 style="margin-bottom:10px;color:#1e293b;"><i class="fas fa-history"></i> 10 dernières transactions</h4>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr><?php foreach (['Date','Type','Montant','Solde après','Opérateur','Description'] as $h): ?>
                            <th style="text-align:left;padding:10px 0;color:#64748b;font-size:11px;text-transform:uppercase;border-bottom:1px solid #e2e8f0;"><?= $h ?></th>
                            <?php endforeach; ?></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $t): ?>
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:9px 0;"><?= date('d/m/Y H:i',strtotime($t['date_transaction'])) ?></td>
                                <td style="padding:9px 0;"><span class="badge <?= $t['type']==='depot'?'badge-success':'badge-danger' ?>"><i class="fas fa-<?= $t['type']==='depot'?'arrow-down':'arrow-up' ?>"></i> <?= ucfirst($t['type']) ?></span></td>
                                <td style="padding:9px 0;font-weight:600;color:<?= $t['type']==='depot'?'#16a34a':'#dc2626' ?>;">
                                    <?= $t['type']==='depot'?'+':'-' ?> <?= number_format($t['montant'],2,',',' ') ?> HTG
                                </td>
                                <td style="padding:9px 0;"><?= number_format($t['solde_apres'],2,',',' ') ?> HTG</td>
                                <td style="padding:9px 0;"><?= htmlspecialchars($t['operateur']) ?> <small style="color:#94a3b8;">(<?= $t['succ_code'] ?>)</small></td>
                                <td style="padding:9px 0;"><?= htmlspecialchars($t['description']?:'—') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($transactions)): ?>
                            <tr><td colspan="6" style="text-align:center;color:#64748b;padding:20px;">Aucune transaction</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php elseif (empty($search)): ?>
        <div style="text-align:center;padding:60px;background:white;border-radius:14px;">
            <i class="fas fa-search" style="font-size:48px;color:#cbd5e1;margin-bottom:16px;display:block;"></i>
            <h3 style="color:#1e293b;margin-bottom:8px;">Recherchez un client ou un compte</h3>
            <p style="color:#64748b;">Numéro de compte, NIF, CINU, téléphone ou nom.</p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
