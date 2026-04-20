<?php
require_once '../config/connexion.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['caissier','secretaire','admin'])) {
    header('Location: ../index.php');
    exit;
}

$message       = '';
$error         = '';
$compte_info   = null;
$compte_apercu = null;

if (!empty($_GET['id_compte'])) {
    $stmt = $pdo->prepare("
        SELECT c.id_compte, c.solde, c.devise, c.statut,
               tc.nom AS type_compte, tc.solde_minimum,
               CONCAT(cl.prenom,' ',cl.nom) AS titulaire
        FROM comptes c JOIN types_comptes tc ON c.type_compte_id=tc.id
        JOIN clients cl ON c.titulaire_principal_id=cl.id
        WHERE c.id_compte=? AND c.statut='actif'");
    $stmt->execute([trim($_GET['id_compte'])]);
    $compte_apercu = $stmt->fetch() ?: null;
    if (!$compte_apercu) $error = "Compte introuvable ou inactif.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_compte   = trim($_POST['id_compte']   ?? '');
    $montant     = floatval($_POST['montant']  ?? 0);
    $description = trim($_POST['description'] ?? 'Retrait en espèces');

    if (empty($id_compte))                       $error = "Veuillez saisir un numéro de compte.";
    elseif (!preg_match('/^\d{5}$/',$id_compte)) $error = "Le numéro de compte doit contenir 5 chiffres.";
    elseif ($montant <= 0)                       $error = "Le montant doit être supérieur à 0.";
    elseif ($montant > 9999999.99)               $error = "Montant dépasse la limite autorisée.";
    elseif ($montant >= 100000 && $_SESSION['role'] !== 'admin')
        $error = "Les retraits ≥ 100 000 HTG sont réservés à l'administrateur.";
    else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                SELECT c.*, tc.nom AS type_compte, tc.solde_minimum,
                       CONCAT(cl.prenom,' ',cl.nom) AS titulaire, cl.id_client, cl.telephone
                FROM comptes c JOIN types_comptes tc ON c.type_compte_id=tc.id
                JOIN clients cl ON c.titulaire_principal_id=cl.id
                WHERE c.id_compte=? AND c.statut='actif' FOR UPDATE");
            $stmt->execute([$id_compte]);
            $compte = $stmt->fetch();
            if (!$compte) throw new Exception("Compte introuvable ou inactif.");

            $ancien_solde        = $compte['solde'];
            $solde_apres_retrait = round($ancien_solde - $montant, 2);

            if ($ancien_solde < $montant)
                throw new Exception("Solde insuffisant. Disponible : ".number_format($ancien_solde,2,',',' ')." ".$compte['devise']);
            if ($solde_apres_retrait < $compte['solde_minimum'])
                throw new Exception("Ce retrait passerait sous le minimum requis de ".number_format($compte['solde_minimum'],2,',',' ')." ".$compte['devise'].". Disponible au retrait : ".number_format($ancien_solde-$compte['solde_minimum'],2,',',' ')." ".$compte['devise']);

            $pdo->prepare("UPDATE comptes SET solde=?, updated_at=NOW() WHERE id=?")->execute([$solde_apres_retrait,$compte['id']]);
            $pdo->prepare("INSERT INTO transactions (compte_id,utilisateur_id,succursale_id,type,montant,solde_avant,solde_apres,description) VALUES (?,?,?,'retrait',?,?,?,?)")
                ->execute([$compte['id'],$_SESSION['user_id'],$_SESSION['succursale_id'],$montant,$ancien_solde,$solde_apres_retrait,$description]);
            $pdo->commit();

            $compte_info = [
                'id_compte'     => $compte['id_compte'],
                'titulaire'     => $compte['titulaire'],
                'id_client'     => $compte['id_client'],
                'type_compte'   => $compte['type_compte'],
                'devise'        => $compte['devise'],
                'ancien_solde'  => $ancien_solde,
                'nouveau_solde' => $solde_apres_retrait,
                'montant'       => $montant,
                'description'   => $description,
                'date'          => date('d/m/Y'),
                'heure'         => date('H:i:s'),
                'operateur'     => $_SESSION['nom_complet'],
                'succursale'    => $_SESSION['succursale_nom'],
            ];
            $message = "Retrait de ".number_format($montant,2,',',' ')." HTG effectué avec succès.";
            $pdo->prepare("INSERT INTO logs_activites (utilisateur_id,action,details,ip_address) VALUES (?,'retrait',?,?)")
                ->execute([$_SESSION['user_id'],"Retrait de $montant HTG sur compte $id_compte",$_SERVER['REMOTE_ADDR']]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

$currentPage = 'retrait';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retrait - S&P illico</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/common.css">
    <link rel="icon" type="favicon" href="../logo.jpeg">
</head>
<body>
    <?php require_once '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>Retrait</h1>
                <div class="breadcrumb"><a href="dashboard.php">Accueil</a> / Retrait</div>
            </div>
            <div class="top-right">
                <span class="top-succursale"><i class="fas fa-building"></i> <?= htmlspecialchars($_SESSION['succursale_nom']) ?></span>
                <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
            </div>
        </div>

        <div class="card" style="max-width:580px;margin:0 auto;">
            <div class="card-header-icon">
                <i class="fas fa-arrow-up" style="color:#dc2626;background:#fee2e2;"></i>
                <h2>Effectuer un retrait</h2>
                <p style="color:#64748b;">Débitez un compte client</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (!$compte_info): ?>

            <div class="alert alert-warning">
                <i class="fas fa-info-circle"></i>
                Le solde après retrait doit rester ≥ au solde minimum requis.
            </div>

            <?php if ($compte_apercu): ?>
            <div class="alert alert-info" style="margin-bottom:16px;">
                <div>
                    <strong>Compte <?= htmlspecialchars($compte_apercu['id_compte']) ?></strong>
                    — <?= htmlspecialchars($compte_apercu['titulaire']) ?><br>
                    <?= htmlspecialchars($compte_apercu['type_compte']) ?> |
                    Solde : <strong><?= number_format($compte_apercu['solde'],2,',',' ') ?> <?= $compte_apercu['devise'] ?></strong>
                    (min requis : <?= number_format($compte_apercu['solde_minimum'],2,',',' ') ?> <?= $compte_apercu['devise'] ?>)
                </div>
            </div>
            <?php endif; ?>

            <div id="valErr" style="display:none;" class="alert alert-error"></div>

            <form method="post" id="retraitForm">
                <div class="form-group">
                    <label><i class="fas fa-credit-card"></i> Numéro de compte *</label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" id="id_compte" name="id_compte" class="form-control"
                               placeholder="Ex: 00001" value="<?= htmlspecialchars($_GET['id_compte']??'') ?>"
                               maxlength="5" required autofocus>
                        <button type="button" class="btn btn-outline btn-sm" onclick="voirCompte()" title="Voir solde">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="form-hint"><i class="fas fa-info-circle"></i> Format : 5 chiffres</div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-money-bill"></i> Montant à retirer (HTG) *</label>
                    <input type="number" id="montant" name="montant" class="form-control"
                           placeholder="0.00" step="0.01" min="0.01" max="9999999.99" required>
                    <?php if ($_SESSION['role'] !== 'admin'): ?>
                    <div class="form-hint" style="color:#f59e0b;">
                        <i class="fas fa-lock"></i> Retraits ≥ 100 000 HTG réservés à l'administrateur.
                    </div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-pen"></i> Description <small>(optionnel)</small></label>
                    <input type="text" name="description" class="form-control" value="Retrait en espèces" maxlength="255">
                </div>
                <button type="submit" class="btn btn-danger btn-block">
                    <i class="fas fa-check-circle"></i> Valider le retrait
                </button>
            </form>

            <?php else: ?>
            <!-- ══ RÉSUMÉ + TÉLÉCHARGEMENT PDF ══════════════════ -->
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                Retrait de <strong><?= number_format($compte_info['montant'],2,',',' ') ?> <?= $compte_info['devise'] ?></strong> effectué avec succès.
            </div>

            <!-- Résumé visuel -->
            <div style="background:#f8fafc;border-radius:12px;padding:20px;margin-bottom:20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                    <div>
                        <div style="font-size:13px;color:#64748b;">Compte</div>
                        <div style="font-size:18px;font-weight:700;color:#1e293b;"><?= htmlspecialchars($compte_info['id_compte']) ?></div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:13px;color:#64748b;">Nouveau solde</div>
                        <div style="font-size:22px;font-weight:800;color:#dc2626;"><?= number_format($compte_info['nouveau_solde'],2,',',' ') ?> <?= $compte_info['devise'] ?></div>
                    </div>
                </div>

                <?php foreach ([
                    ['Titulaire',       $compte_info['titulaire']],
                    ['Type de compte',  $compte_info['type_compte']],
                    ['Ancien solde',    number_format($compte_info['ancien_solde'],2,',',' ').' '.$compte_info['devise']],
                    ['Montant retiré',  '- '.number_format($compte_info['montant'],2,',',' ').' '.$compte_info['devise']],
                    ['Date',            $compte_info['date'].' à '.$compte_info['heure']],
                    ['Opérateur',       $compte_info['operateur']],
                ] as [$l,$v]): ?>
                <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid #e2e8f0;">
                    <span style="color:#64748b;font-size:13px;"><?= $l ?></span>
                    <span style="font-weight:600;font-size:13px;<?= $l==='Montant retiré'?'color:#dc2626;':'' ?>">
                        <?= htmlspecialchars($v) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Boutons PDF -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:18px;">

                <a href="../generate_pdf.php?action=retrait&id_compte=<?= urlencode($compte_info['id_compte']) ?>"
                target="_blank"
                class="btn btn-danger"
                style="justify-content:center; text-decoration:none;">
                    <i class="fas fa-file-pdf"></i> Reçu de retrait PDF
                </a>

                <a href="retrait.php"
                class="btn btn-secondary"
                style="justify-content:center; text-decoration:none;">
                    <i class="fas fa-plus"></i> Nouveau retrait
                </a>

            </div>


            <?php endif; ?>
        </div>
    </div>

    <script>
        const role = '<?= $_SESSION['role'] ?>';
        document.getElementById('retraitForm')?.addEventListener('submit', function(e) {
            const err = document.getElementById('valErr');
            const id  = document.getElementById('id_compte').value.trim();
            const mt  = parseFloat(document.getElementById('montant').value);
            err.style.display='none';
            if (!/^\d{5}$/.test(id))            { e.preventDefault(); err.innerHTML='<i class="fas fa-exclamation-triangle"></i> Numéro de compte invalide.'; err.style.display='flex'; return; }
            if (isNaN(mt)||mt<=0)                { e.preventDefault(); err.innerHTML='<i class="fas fa-exclamation-triangle"></i> Montant invalide.'; err.style.display='flex'; return; }
            if (mt>=100000&&role!=='admin')       { e.preventDefault(); err.innerHTML='<i class="fas fa-lock"></i> Retraits ≥ 100 000 HTG réservés à l\'administrateur.'; err.style.display='flex'; return; }
        });
        document.getElementById('id_compte')?.addEventListener('input',function(){this.value=this.value.replace(/\D/g,'').substring(0,5);});
        function voirCompte(){const id=document.getElementById('id_compte').value.trim();if(!/^\d{5}$/.test(id)){alert('5 chiffres requis.');return;}window.location.href=`retrait.php?id_compte=${id}`;}
    </script>
</body>
</html>
