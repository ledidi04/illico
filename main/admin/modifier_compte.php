<?php
require_once '../config/connexion.php';
session_start();

// Accès : admin et secrétaire uniquement
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'secretaire'])) {
    header('Location: ../index.php');
    exit;
}

// Fonctions de validation
function validerTelephone($telephone) {
    if (empty($telephone)) return true;
    return preg_match('/^(\+509)?[ -]?(\d{4})[- ]?(\d{4})$/', $telephone);
}

function formaterTelephone($telephone) {
    if (empty($telephone)) return '';
    $chiffres = preg_replace('/\D/', '', $telephone);
    if (strlen($chiffres) === 8) {
        return substr($chiffres, 0, 4) . '-' . substr($chiffres, 4, 4);
    }
    if (strlen($chiffres) === 11 && substr($chiffres, 0, 3) === '509') {
        $chiffres = substr($chiffres, 3);
        return substr($chiffres, 0, 4) . '-' . substr($chiffres, 4, 4);
    }
    return $telephone;
}

function validerEmail($email) {
    if (empty($email)) return true;
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

$message = '';
$error   = '';

// ── Récupérer le compte à modifier ───────────────────────────
$compte      = null;
$cotitulaires = [];

if (!empty($_GET['id'])) {
    $stmt = $pdo->prepare("
        SELECT c.*, tc.nom AS type_compte_nom, tc.code AS type_compte_code,
               cl.id_client, cl.nom, cl.prenom, cl.date_naissance, cl.lieu_naissance,
               cl.adresse, cl.telephone, cl.email, cl.photo, cl.type_piece
        FROM comptes c
        JOIN types_comptes tc ON c.type_compte_id = tc.id
        JOIN clients cl ON c.titulaire_principal_id = cl.id
        WHERE c.id = ?
    ");
    $stmt->execute([(int)$_GET['id']]);
    $compte = $stmt->fetch();

} elseif (!empty($_GET['client_id'])) {
    $stmt = $pdo->prepare("
        SELECT c.*, tc.nom AS type_compte_nom, tc.code AS type_compte_code,
               cl.id_client, cl.nom, cl.prenom, cl.date_naissance, cl.lieu_naissance,
               cl.adresse, cl.telephone, cl.email, cl.photo, cl.type_piece
        FROM comptes c
        JOIN types_comptes tc ON c.type_compte_id = tc.id
        JOIN clients cl ON c.titulaire_principal_id = cl.id
        WHERE cl.id = ?
        ORDER BY c.date_creation DESC
        LIMIT 1
    ");
    $stmt->execute([(int)$_GET['client_id']]);
    $compte = $stmt->fetch();
}

if (!$compte) {
    $error = "Compte introuvable.";
}

// Co-titulaires existants
if ($compte) {
    $stmt = $pdo->prepare("
        SELECT cl.* FROM clients cl
        JOIN compte_cotitulaires cc ON cl.id = cc.client_id
        WHERE cc.compte_id = ?
    ");
    $stmt->execute([$compte['id']]);
    $cotitulaires = $stmt->fetchAll();
}

// Types de comptes
$types_comptes = $pdo->query("SELECT * FROM types_comptes WHERE actif = 1 ORDER BY categorie, nom")->fetchAll();

// ── Traitement POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $compte) {
    try {
        $pdo->beginTransaction();

        $compte_id  = (int) $_POST['compte_id'];
        $client_id  = (int) $_POST['client_id'];

        // ── 1. Mise à jour du client (titulaire) ──────────────
        $nom            = trim($_POST['nom']            ?? '');
        $prenom         = trim($_POST['prenom']         ?? '');
        $date_naissance = $_POST['date_naissance']      ?? null;
        $lieu_naissance = trim($_POST['lieu_naissance'] ?? '');
        $adresse        = trim($_POST['adresse']        ?? '');
        $telephone      = trim($_POST['telephone']      ?? '');
        $email          = trim($_POST['email']          ?? '');
        $type_piece     = $_POST['type_piece']          ?? 'NIF';

        if (empty($nom) || empty($prenom)) {
            throw new Exception("Le nom et le prénom sont obligatoires.");
        }
        if (!validerEmail($email)) {
            throw new Exception("L'adresse email n'est pas valide.");
        }
        if (!empty($telephone) && !validerTelephone($telephone)) {
            throw new Exception("Format de téléphone invalide. Utilisez le format: +509 XXXX-XXXX ou XXXX-XXXX");
        }
        
        // Formater le téléphone
        $telephone = formaterTelephone($telephone);

        // Upload nouvelle photo (optionnel)
        $photo_path = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
                throw new Exception("Format de photo non autorisé. Utilisez JPG, PNG ou WEBP.");
            }
            if ($_FILES['photo']['size'] > 3 * 1024 * 1024) {
                throw new Exception("La photo ne doit pas dépasser 3 Mo.");
            }
            if (!getimagesize($_FILES['photo']['tmp_name'])) {
                throw new Exception("Le fichier n'est pas une image valide.");
            }
            $upload_dir = '../uploads/photos/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $photo_path = 'uploads/photos/' . uniqid('client_', true) . '.' . $ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], '../' . $photo_path);
        }

        if ($photo_path) {
            $stmt = $pdo->prepare("
                UPDATE clients SET nom=?, prenom=?, date_naissance=?, lieu_naissance=?,
                adresse=?, telephone=?, email=?, type_piece=?, photo=?, updated_at=NOW()
                WHERE id=?
            ");
            $stmt->execute([$nom, $prenom, $date_naissance ?: null, $lieu_naissance,
                            $adresse, $telephone, $email, $type_piece, $photo_path, $client_id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE clients SET nom=?, prenom=?, date_naissance=?, lieu_naissance=?,
                adresse=?, telephone=?, email=?, type_piece=?, updated_at=NOW()
                WHERE id=?
            ");
            $stmt->execute([$nom, $prenom, $date_naissance ?: null, $lieu_naissance,
                            $adresse, $telephone, $email, $type_piece, $client_id]);
        }

        // ── 2. Mise à jour du compte ──────────────────────────
        $type_compte_id = intval($_POST['type_compte_id'] ?? 0);
        $devise         = $_POST['devise'] ?? 'HTG';
        $statut         = $_POST['statut'] ?? 'actif';

        // Seul l'admin peut bloquer/clôturer un compte
        if ($_SESSION['role'] !== 'admin') {
            $statut = $compte['statut'];
        }

        if ($type_compte_id <= 0) throw new Exception("Type de compte invalide.");
        if (!in_array($devise, ['HTG','USD','EUR'])) throw new Exception("Devise invalide.");
        if (!in_array($statut, ['actif','bloque','cloture'])) $statut = 'actif';

        $pdo->prepare("
            UPDATE comptes SET type_compte_id=?, devise=?, statut=?, updated_at=NOW()
            WHERE id=?
        ")->execute([$type_compte_id, $devise, $statut, $compte_id]);

        // ── 3. Gestion des co-titulaires ──────────────────────
        // Supprimer les co-titulaires retirés
        $cot_ids_gardes = array_filter(array_map('intval', $_POST['cot_keep_ids'] ?? []));
        if (!empty($cot_ids_gardes)) {
            $placeholders = implode(',', array_fill(0, count($cot_ids_gardes), '?'));
            $pdo->prepare("
                DELETE FROM compte_cotitulaires
                WHERE compte_id = ? AND client_id NOT IN ($placeholders)
            ")->execute(array_merge([$compte_id], $cot_ids_gardes));
        } else {
            $pdo->prepare("DELETE FROM compte_cotitulaires WHERE compte_id = ?")->execute([$compte_id]);
        }

        // Ajouter les nouveaux co-titulaires
        if (!empty($_POST['new_cotitulaires']) && is_array($_POST['new_cotitulaires'])) {
            foreach ($_POST['new_cotitulaires'] as $idx => $cot) {
                $cot_nif    = trim($cot['id_client'] ?? '');
                $cot_nom    = trim($cot['nom']       ?? '');
                $cot_prenom = trim($cot['prenom']    ?? '');

                if (empty($cot_nif) && empty($cot_nom)) continue;

                if (empty($cot_nif) || empty($cot_nom) || empty($cot_prenom)) {
                    throw new Exception("Nouveau co-titulaire #".($idx+1)." : NIF/CINU, nom et prénom requis.");
                }
                if (!preg_match('/^\d{3}-\d{3}-\d{3}-\d$/', $cot_nif)) {
                    throw new Exception("Co-titulaire #".($idx+1)." : format NIF/CINU invalide.");
                }
                if ($cot_nif === $compte['id_client']) {
                    throw new Exception("Le co-titulaire ne peut pas être le même que le titulaire principal.");
                }

                // Valider le téléphone du co-titulaire
                $cot_telephone = !empty($cot['telephone']) ? formaterTelephone(trim($cot['telephone'])) : null;
                if (!empty($cot['telephone']) && !validerTelephone($cot['telephone'])) {
                    throw new Exception("Co-titulaire #".($idx+1)." : format de téléphone invalide.");
                }

                $stmt = $pdo->prepare("SELECT id FROM clients WHERE id_client = ?");
                $stmt->execute([$cot_nif]);
                $cot_id = $stmt->fetchColumn();

                if (!$cot_id) {
                    $pdo->prepare("INSERT INTO clients (id_client, nom, prenom, telephone) VALUES (?, ?, ?, ?)")
                        ->execute([$cot_nif, $cot_nom, $cot_prenom, $cot_telephone]);
                    $cot_id = $pdo->lastInsertId();
                }

                $pdo->prepare("INSERT IGNORE INTO compte_cotitulaires (compte_id, client_id) VALUES (?, ?)")
                    ->execute([$compte_id, $cot_id]);
            }
        }

        $pdo->commit();
        $message = "Modifications enregistrées avec succès.";

        // Log
        $pdo->prepare("INSERT INTO logs_activites (utilisateur_id, action, details, ip_address) VALUES (?, 'modification_compte', ?, ?)")
            ->execute([$_SESSION['user_id'], "Modification compte {$compte['id_compte']}", $_SERVER['REMOTE_ADDR']]);

        // Recharger les données
        $stmt = $pdo->prepare("
            SELECT c.*, tc.nom AS type_compte_nom, tc.code AS type_compte_code,
                   cl.id_client, cl.nom, cl.prenom, cl.date_naissance, cl.lieu_naissance,
                   cl.adresse, cl.telephone, cl.email, cl.photo, cl.type_piece
            FROM comptes c
            JOIN types_comptes tc ON c.type_compte_id = tc.id
            JOIN clients cl ON c.titulaire_principal_id = cl.id
            WHERE c.id = ?
        ");
        $stmt->execute([$compte_id]);
        $compte = $stmt->fetch();

        // Recharger co-titulaires
        $stmt = $pdo->prepare("SELECT cl.* FROM clients cl JOIN compte_cotitulaires cc ON cl.id = cc.client_id WHERE cc.compte_id = ?");
        $stmt->execute([$compte_id]);
        $cotitulaires = $stmt->fetchAll();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

$currentPage = 'modifier_compte';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier compte - S&P illico</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/common.css">
    <link rel="icon" type="favicon" href="../logo.jpeg">
    <style>
        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid #ef4444;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        .error-message i {
            font-size: 18px;
        }
        .field-error {
            border-color: #ef4444 !important;
            background: #fef2f2 !important;
        }
        .field-error:focus {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 3px rgba(239,68,68,0.1) !important;
        }
        .success-message {
            background: #dcfce7;
            color: #166534;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid #16a34a;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
    <?php require_once '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>Modifier un compte</h1>
                <div class="breadcrumb">
                    <a href="dashboard.php">Accueil</a> /
                    <a href="liste_clients.php">Clients</a> /
                    Modifier
                </div>
            </div>
            <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </div>

        <!-- Zone des messages d'erreur/succès -->
        <div id="errorMessage" class="error-message" style="display: none;"></div>
        <div id="successMessage" class="success-message" style="display: none;"></div>

        <?php if ($error && !isset($_POST['ajax'])): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if (!$compte && !$error): ?>
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Aucun compte sélectionné.</div>
        <?php endif; ?>

        <?php if ($compte): ?>
        <form method="post" enctype="multipart/form-data" id="modifForm">
            <input type="hidden" name="compte_id" value="<?= $compte['id'] ?>">
            <input type="hidden" name="client_id" value="<?= $compte['titulaire_principal_id'] ?>">

            <!-- ── Informations du compte ──────────────────── -->
            <div class="card">
                <h3 style="margin-bottom:18px;">
                    <i class="fas fa-credit-card"></i> Compte N° <?= htmlspecialchars($compte['id_compte']) ?>
                </h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Type de compte *</label>
                        <select name="type_compte_id" id="type_compte_id" class="form-control" required>
                            <?php foreach ($types_comptes as $type): ?>
                            <option value="<?= $type['id'] ?>" <?= $type['id'] == $compte['type_compte_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type['nom']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-coins"></i> Devise *</label>
                        <select name="devise" id="devise" class="form-control" required>
                            <?php foreach (['HTG' => 'Gourdes (HTG)', 'USD' => 'Dollars US (USD)', 'EUR' => 'Euros (EUR)'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= $v === $compte['devise'] ? 'selected' : '' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                    <div class="form-group">
                        <label><i class="fas fa-toggle-on"></i> Statut du compte</label>
                        <select name="statut" id="statut" class="form-control">
                            <option value="actif"   <?= $compte['statut'] === 'actif'   ? 'selected' : '' ?>>Actif</option>
                            <option value="bloque"  <?= $compte['statut'] === 'bloque'  ? 'selected' : '' ?>>Bloqué</option>
                            <option value="cloture" <?= $compte['statut'] === 'cloture' ? 'selected' : '' ?>>Clôturé</option>
                        </select>
                    </div>
                    <?php else: ?>
                    <div class="form-group">
                        <label><i class="fas fa-toggle-on"></i> Statut</label>
                        <input type="text" class="form-control" value="<?= ucfirst($compte['statut']) ?>" disabled>
                        <div class="form-hint">Seul l'administrateur peut modifier le statut.</div>
                    </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label><i class="fas fa-calendar"></i> Date de création</label>
                        <input type="text" class="form-control" value="<?= date('d/m/Y', strtotime($compte['date_creation'])) ?>" disabled>
                    </div>
                </div>
            </div>

            <!-- ── Informations du titulaire ──────────────── -->
            <div class="card">
                <h3 style="margin-bottom:18px;"><i class="fas fa-user-circle"></i> Titulaire principal</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> NIF/CINU</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($compte['id_client']) ?>" disabled>
                        <div class="form-hint">Le NIF/CINU ne peut pas être modifié.</div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> Type de pièce</label>
                        <select name="type_piece" id="type_piece" class="form-control">
                            <?php foreach (['NIF', 'CINU', 'PASSEPORT', 'AUTRE'] as $t): ?>
                            <option value="<?= $t ?>" <?= $t === $compte['type_piece'] ? 'selected' : '' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Nom *</label>
                        <input type="text" name="nom" id="nom" class="form-control"
                               value="<?= htmlspecialchars($compte['nom']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Prénom *</label>
                        <input type="text" name="prenom" id="prenom" class="form-control"
                               value="<?= htmlspecialchars($compte['prenom']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-calendar"></i> Date de naissance</label>
                        <input type="date" name="date_naissance" id="date_naissance" class="form-control"
                               value="<?= htmlspecialchars($compte['date_naissance'] ?? '') ?>"
                               max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-map-pin"></i> Lieu de naissance</label>
                        <input type="text" name="lieu_naissance" id="lieu_naissance" class="form-control"
                               value="<?= htmlspecialchars($compte['lieu_naissance'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Téléphone</label>
                        <input type="tel" name="telephone" id="telephone" class="form-control"
                               placeholder="+509 XXXX-XXXX"
                               value="<?= htmlspecialchars($compte['telephone'] ?? '') ?>">
                        <div class="form-hint">Format: +509 XXXX-XXXX ou XXXX-XXXX</div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" name="email" id="email" class="form-control"
                               value="<?= htmlspecialchars($compte['email'] ?? '') ?>">
                    </div>
                    <div class="form-group full-width">
                        <label><i class="fas fa-map"></i> Adresse</label>
                        <textarea name="adresse" id="adresse" class="form-control" rows="2"><?= htmlspecialchars($compte['adresse'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-camera"></i> Nouvelle photo <small>(optionnel)</small></label>
                        <input type="file" name="photo" id="photoInput" class="form-control" accept="image/*">
                        <div class="form-hint">Laissez vide pour conserver l'actuelle. JPG, PNG, WEBP — max 3 Mo</div>
                    </div>
                    <div class="form-group" style="display:flex;align-items:center;justify-content:center;">
                        <div id="photoPreview" style="width:90px;height:90px;border-radius:10px;overflow:hidden;background:#f1f5f9;display:flex;align-items:center;justify-content:center;">
                            <?php if ($compte['photo']): ?>
                            <img src="../<?= htmlspecialchars($compte['photo']) ?>" style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                            <i class="fas fa-user" style="font-size:36px;color:#cbd5e1;"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Co-titulaires existants ─────────────────── -->
            <div class="card">
                <h3 style="margin-bottom:16px;"><i class="fas fa-users"></i> Co-titulaires</h3>

                <?php if (!empty($cotitulaires)): ?>
                <div id="cotitulairesList" style="margin-bottom:16px;">
                    <?php foreach ($cotitulaires as $cot): ?>
                    <div class="cotitulaire-existant" id="cot-existing-<?= $cot['id'] ?>"
                         style="display:flex;align-items:center;gap:12px;padding:12px;background:#f8fafc;border-radius:9px;margin-bottom:8px;border:1px solid #e2e8f0;">
                        <input type="hidden" name="cot_keep_ids[]" value="<?= $cot['id'] ?>" class="cot-keep-input">
                        <i class="fas fa-user" style="color:#3b82f6;"></i>
                        <div style="flex:1;">
                            <strong><?= htmlspecialchars($cot['prenom'] . ' ' . $cot['nom']) ?></strong>
                            <span style="color:#64748b;margin-left:10px;"><?= htmlspecialchars($cot['id_client']) ?></span>
                            <?php if ($cot['telephone']): ?>
                            <span style="color:#64748b;margin-left:10px;"><i class="fas fa-phone fa-xs"></i> <?= htmlspecialchars($cot['telephone']) ?></span>
                            <?php endif; ?>
                        </div>
                        <button type="button" onclick="retirerCotitulaire(<?= $cot['id'] ?>)"
                                class="btn-remove"
                                style="background:#fee2e2;color:#991b1b;border:none;padding:6px 12px;border-radius:6px;cursor:pointer;">
                            <i class="fas fa-trash"></i> Retirer
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p style="color:#64748b;margin-bottom:14px;">Aucun co-titulaire actuellement.</p>
                <?php endif; ?>

                <!-- Nouveaux co-titulaires -->
                <div id="newCotitulaireContainer"></div>
                <button type="button" class="btn btn-outline" onclick="ajouterNouveauCotitulaire()">
                    <i class="fas fa-plus"></i> Ajouter un co-titulaire
                </button>
            </div>

            <div class="form-actions">
                <a href="verification.php?search=<?= urlencode($compte['id_compte']) ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Annuler
                </a>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-save"></i> Enregistrer les modifications
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <script>
        // Éléments du formulaire
        const form = document.getElementById('modifForm');
        const nomInput = document.getElementById('nom');
        const prenomInput = document.getElementById('prenom');
        const telephoneInput = document.getElementById('telephone');
        const emailInput = document.getElementById('email');
        const dateNaissanceInput = document.getElementById('date_naissance');
        const submitBtn = document.getElementById('submitBtn');
        
        // Éléments de messages
        const errorDiv = document.getElementById('errorMessage');
        const successDiv = document.getElementById('successMessage');
        
        // Fonction pour afficher une erreur
        function showError(message, fieldId = null) {
            errorDiv.style.display = 'flex';
            errorDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + message;
            
            // Supprimer les classes d'erreur précédentes
            document.querySelectorAll('.field-error').forEach(el => {
                el.classList.remove('field-error');
            });
            
            // Ajouter la classe d'erreur au champ concerné
            if (fieldId) {
                const field = document.getElementById(fieldId);
                if (field) field.classList.add('field-error');
                field?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            
            // Faire défiler jusqu'à l'erreur
            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Cacher le message après 5 secondes
            setTimeout(() => {
                errorDiv.style.display = 'none';
            }, 5000);
        }
        
        function clearError() {
            errorDiv.style.display = 'none';
            errorDiv.innerHTML = '';
            document.querySelectorAll('.field-error').forEach(el => {
                el.classList.remove('field-error');
            });
        }
        
        function showSuccess(message) {
            successDiv.style.display = 'flex';
            successDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + message;
            successDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            setTimeout(() => {
                successDiv.style.display = 'none';
            }, 5000);
        }
        
        // Validation du téléphone
        function validerTelephone(telephone) {
            if (!telephone) return true;
            return /^(\+509)?[ -]?(\d{4})[- ]?(\d{4})$/.test(telephone);
        }
        
        // Validation email
        function validerEmail(email) {
            if (!email) return true;
            return /^[^\s@]+@([^\s@.,]+\.)+[^\s@.,]{2,}$/.test(email);
        }
        
        // Formatage du téléphone
        function formaterTelephone(telephone) {
            if (!telephone) return '';
            let chiffres = telephone.replace(/\D/g, '');
            if (chiffres.length === 8) {
                return chiffres.substring(0, 4) + '-' + chiffres.substring(4, 8);
            }
            if (chiffres.length === 11 && chiffres.substring(0, 3) === '509') {
                chiffres = chiffres.substring(3);
                return chiffres.substring(0, 4) + '-' + chiffres.substring(4, 8);
            }
            return telephone;
        }
        
        // Validation avant soumission (SANS alert/confirm)
        form?.addEventListener('submit', function(e) {
            clearError();
            
            let hasError = false;
            let errorMessage = '';
            
            // Validation nom
            if (!nomInput.value.trim()) {
                errorMessage = 'Le nom est obligatoire.';
                showError(errorMessage, 'nom');
                hasError = true;
            }
            // Validation prénom
            else if (!prenomInput.value.trim()) {
                errorMessage = 'Le prénom est obligatoire.';
                showError(errorMessage, 'prenom');
                hasError = true;
            }
            // Validation téléphone
            else if (!validerTelephone(telephoneInput.value)) {
                errorMessage = 'Format de téléphone invalide. Utilisez le format: +509 XXXX-XXXX ou XXXX-XXXX';
                showError(errorMessage, 'telephone');
                hasError = true;
            }
            // Validation email
            else if (!validerEmail(emailInput.value)) {
                errorMessage = 'L\'adresse email n\'est pas valide.';
                showError(errorMessage, 'email');
                hasError = true;
            }
            // Validation date de naissance (ne peut pas être dans le futur)
            else if (dateNaissanceInput.value && new Date(dateNaissanceInput.value) > new Date()) {
                errorMessage = 'La date de naissance ne peut pas être dans le futur.';
                showError(errorMessage, 'date_naissance');
                hasError = true;
            }
            
            if (hasError) {
                e.preventDefault();
                return false;
            }
            
            // Formater le téléphone avant soumission
            telephoneInput.value = formaterTelephone(telephoneInput.value);
            
            // Désactiver le bouton pour éviter double soumission
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';
        });
        
        // Nettoyer les erreurs lors de la saisie
        nomInput?.addEventListener('input', function() { clearError(); this.classList.remove('field-error'); });
        prenomInput?.addEventListener('input', function() { clearError(); this.classList.remove('field-error'); });
        telephoneInput?.addEventListener('input', function() { 
            clearError(); 
            this.classList.remove('field-error');
            // Formatage en direct
            let chiffres = this.value.replace(/\D/g, '');
            if (chiffres.length === 8) {
                this.value = chiffres.substring(0, 4) + '-' + chiffres.substring(4, 8);
            }
        });
        emailInput?.addEventListener('input', function() { clearError(); this.classList.remove('field-error'); });
        dateNaissanceInput?.addEventListener('input', function() { clearError(); this.classList.remove('field-error'); });
        
        // Prévisualisation photo
        document.getElementById('photoInput')?.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;
            
            // Vérifier la taille
            if (file.size > 3 * 1024 * 1024) {
                showError('La photo ne doit pas dépasser 3 Mo.', 'photoInput');
                this.value = '';
                return;
            }
            
            // Vérifier le type
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                showError('Format de photo non autorisé. Utilisez JPG, PNG ou WEBP.', 'photoInput');
                this.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = e => {
                const preview = document.getElementById('photoPreview');
                preview.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;">`;
            };
            reader.readAsDataURL(file);
            clearError();
        });
        
        // ── Retirer un co-titulaire existant ─────────────────
        function retirerCotitulaire(id) {
            const div = document.getElementById(`cot-existing-${id}`);
            if (div) {
                div.querySelector('.cot-keep-input').disabled = true;
                div.style.opacity = '0.4';
                div.style.textDecoration = 'line-through';
                const btn = div.querySelector('button');
                btn.textContent = 'Retiré';
                btn.disabled = true;
                btn.style.background = '#cbd5e1';
            }
        }
        
        // ── Ajouter un nouveau co-titulaire ──────────────────
        let newCotCount = 0;
        function ajouterNouveauCotitulaire() {
            newCotCount++;
            const container = document.getElementById('newCotitulaireContainer');
            const div = document.createElement('div');
            div.id = `new-cot-${newCotCount}`;
            div.style.cssText = 'background:#f0fdf4;border-radius:9px;padding:16px;margin-bottom:12px;border:1px solid #bbf7d0;';
            div.innerHTML = `
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <strong>Nouveau co-titulaire #${newCotCount}</strong>
                    <button type="button" onclick="this.closest('div[id]').remove()"
                            style="background:#fee2e2;color:#991b1b;border:none;padding:5px 10px;border-radius:6px;cursor:pointer;">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>NIF/CINU *</label>
                        <input type="text" name="new_cotitulaires[${newCotCount}][id_client]"
                               class="form-control cot-nif" placeholder="XXX-XXX-XXX-X" maxlength="13" required>
                        <div class="form-hint">Format: XXX-XXX-XXX-X</div>
                    </div>
                    <div class="form-group">
                        <label>Nom *</label>
                        <input type="text" name="new_cotitulaires[${newCotCount}][nom]" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Prénom *</label>
                        <input type="text" name="new_cotitulaires[${newCotCount}][prenom]" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="tel" name="new_cotitulaires[${newCotCount}][telephone]" class="form-control" placeholder="+509 XXXX-XXXX">
                    </div>
                </div>
            `;
            container.appendChild(div);
            const nifInput = div.querySelector('.cot-nif');
            nifInput.addEventListener('input', formatNIF);
            
            // Validation du téléphone pour le nouveau co-titulaire
            const telInput = div.querySelector('input[type="tel"]');
            if (telInput) {
                telInput.addEventListener('input', function() {
                    let chiffres = this.value.replace(/\D/g, '');
                    if (chiffres.length === 8) {
                        this.value = chiffres.substring(0, 4) + '-' + chiffres.substring(4, 8);
                    }
                });
            }
        }
        
        // ── Formatage NIF ─────────────────────────────────────
        function formatNIF(e) {
            let digits = this.value.replace(/\D/g, '').substring(0, 10);
            let fmt = '';
            if (digits.length > 0) fmt = digits.substring(0, 3);
            if (digits.length > 3) fmt += '-' + digits.substring(3, 6);
            if (digits.length > 6) fmt += '-' + digits.substring(6, 9);
            if (digits.length > 9) fmt += '-' + digits.substring(9, 10);
            this.value = fmt;
        }
        
        // Appliquer le formatage NIF au champ existant
        document.getElementById('type_piece')?.addEventListener('change', function() {});
    </script>
</body>
</html>