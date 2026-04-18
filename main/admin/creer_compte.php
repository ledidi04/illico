<?php
require_once '../config/connexion.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'secretaire'])) {
    header('Location: ../index.php');
    exit;
}

$message         = '';
$error           = '';
$compte_cree     = null;
$cotitulaires_cree = [];
$client_existant = null;

// ── Types de comptes actifs ───────────────────────────────────
$types_comptes = $pdo->query("SELECT * FROM types_comptes WHERE actif = 1 ORDER BY categorie, nom")->fetchAll();

// ── Recherche client existant via GET ─────────────────────────
if (!empty($_GET['search_client'])) {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id_client = ?");
    $stmt->execute([trim($_GET['search_client'])]);
    $client_existant = $stmt->fetch();
}

// ── Endpoint AJAX : recherche client en JSON ──────────────────
// Appelé par le JS via fetch() pour remplir le formulaire sans rechargement
if (isset($_GET['ajax_search'])) {
    header('Content-Type: application/json');
    $q = trim($_GET['ajax_search'] ?? '');
    if (preg_match('/^\d{3}-\d{3}-\d{3}-\d$/', $q)) {
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE id_client = ?");
        $stmt->execute([$q]);
        $found = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($found ?: ['found' => false]);
    } else {
        echo json_encode(['found' => false]);
    }
    exit;
}

// ── Traitement POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // ── 1. Titulaire principal ────────────────────────────
        $id_client      = trim($_POST['id_client']      ?? '');
        $type_piece     = $_POST['type_piece']          ?? 'NIF';
        $nom            = trim($_POST['nom']             ?? '');
        $prenom         = trim($_POST['prenom']          ?? '');
        $date_naissance = $_POST['date_naissance']       ?? null;
        $lieu_naissance = trim($_POST['lieu_naissance']  ?? '');
        $adresse        = trim($_POST['adresse']         ?? '');
        $telephone      = trim($_POST['telephone']       ?? '');
        $email          = trim($_POST['email']           ?? '');

        if (empty($id_client) || empty($nom) || empty($prenom)) {
            throw new Exception("NIF/CINU, Nom et Prénom du titulaire sont obligatoires.");
        }
        if (!preg_match('/^\d{3}-\d{3}-\d{3}-\d{1}$/', $id_client)) {
            throw new Exception("Format NIF/CINU invalide. Attendu : XXX-XXX-XXX-X");
        }
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("L'adresse email du titulaire n'est pas valide.");
        }

        // Upload photo titulaire
        $photo_path = uploadPhoto('photo', '../uploads/photos/', 'client_');

        // Créer ou mettre à jour le titulaire
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE id_client = ?");
        $stmt->execute([$id_client]);
        $client_id = $stmt->fetchColumn();

        if (!$client_id) {
            $stmt = $pdo->prepare("
                INSERT INTO clients (id_client, type_piece, nom, prenom, date_naissance, lieu_naissance, adresse, telephone, email, photo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$id_client, $type_piece, $nom, $prenom, $date_naissance ?: null,
                            $lieu_naissance, $adresse, $telephone, $email, $photo_path]);
            $client_id = $pdo->lastInsertId();
        } else {
            // Mise à jour — on ne remplace la photo que si une nouvelle est fournie
            if ($photo_path) {
                $stmt = $pdo->prepare("
                    UPDATE clients SET type_piece=?, nom=?, prenom=?, date_naissance=?,
                    lieu_naissance=?, adresse=?, telephone=?, email=?, photo=?, updated_at=NOW()
                    WHERE id=?
                ");
                $stmt->execute([$type_piece, $nom, $prenom, $date_naissance ?: null,
                                $lieu_naissance, $adresse, $telephone, $email, $photo_path, $client_id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE clients SET type_piece=?, nom=?, prenom=?, date_naissance=?,
                    lieu_naissance=?, adresse=?, telephone=?, email=?, updated_at=NOW()
                    WHERE id=?
                ");
                $stmt->execute([$type_piece, $nom, $prenom, $date_naissance ?: null,
                                $lieu_naissance, $adresse, $telephone, $email, $client_id]);
            }
        }

        // ── 2. Compte ─────────────────────────────────────────
        $type_compte_id = intval($_POST['type_compte_id'] ?? 0);
        $devise         = $_POST['devise']         ?? 'HTG';
        $date_creation  = $_POST['date_creation']  ?? date('Y-m-d');

        if ($type_compte_id <= 0) {
            throw new Exception("Veuillez sélectionner un type de compte.");
        }
        if (!in_array($devise, ['HTG', 'USD', 'EUR'])) {
            throw new Exception("Devise invalide.");
        }

        $stmt = $pdo->prepare("
            INSERT INTO comptes (succursale_id, type_compte_id, date_creation, solde, titulaire_principal_id, created_by, statut, devise)
            VALUES (?, ?, ?, 0.00, ?, ?, 'actif', ?)
        ");
        $stmt->execute([
            $_SESSION['succursale_id'],
            $type_compte_id,
            $date_creation,
            $client_id,
            $_SESSION['user_id'],
            $devise
        ]);
        $compte_id = $pdo->lastInsertId();

        // Récupérer l'id_compte généré par le trigger
        $id_compte_genere = $pdo->query("SELECT id_compte FROM comptes WHERE id = $compte_id")->fetchColumn();

        // ── 3. Co-titulaires ──────────────────────────────────
        if (!empty($_POST['cotitulaires']) && is_array($_POST['cotitulaires'])) {
            foreach ($_POST['cotitulaires'] as $index => $cot) {
                $cot_id = trim($cot['id_client'] ?? '');
                $cot_nom    = trim($cot['nom']    ?? '');
                $cot_prenom = trim($cot['prenom'] ?? '');

                // Ignorer les lignes vides
                if (empty($cot_id) && empty($cot_nom) && empty($cot_prenom)) continue;

                if (empty($cot_id) || empty($cot_nom) || empty($cot_prenom)) {
                    throw new Exception("Co-titulaire #" . ($index + 1) . " : NIF/CINU, nom et prénom sont obligatoires.");
                }
                if (!preg_match('/^\d{3}-\d{3}-\d{3}-\d{1}$/', $cot_id)) {
                    throw new Exception("Co-titulaire #" . ($index + 1) . " : format NIF/CINU invalide.");
                }
                if ($cot_id === $id_client) {
                    throw new Exception("Co-titulaire #" . ($index + 1) . " : ne peut pas être identique au titulaire principal.");
                }

                // Photo du co-titulaire (gestion du tableau $_FILES imbriqué)
                $cot_photo = null;
                if (
                    isset($_FILES['cotitulaires']['error'][$index]['photo'])
                    && $_FILES['cotitulaires']['error'][$index]['photo'] === UPLOAD_ERR_OK
                ) {
                    $tmp  = $_FILES['cotitulaires']['tmp_name'][$index]['photo'];
                    $name = $_FILES['cotitulaires']['name'][$index]['photo'];
                    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png','webp']) && getimagesize($tmp)) {
                        $cot_photo = 'uploads/photos/' . uniqid('cot_', true) . '.' . $ext;
                        move_uploaded_file($tmp, '../' . $cot_photo);
                    }
                }

                // Créer ou récupérer le co-titulaire
                $stmt = $pdo->prepare("SELECT id FROM clients WHERE id_client = ?");
                $stmt->execute([$cot_id]);
                $cot_client_id = $stmt->fetchColumn();

                if (!$cot_client_id) {
                    $stmt = $pdo->prepare("
                        INSERT INTO clients (id_client, nom, prenom, date_naissance, lieu_naissance, adresse, telephone, email, photo)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $cot_id, $cot_nom, $cot_prenom,
                        $cot['date_naissance'] ?? null,
                        trim($cot['lieu_naissance'] ?? ''),
                        trim($cot['adresse']        ?? ''),
                        trim($cot['telephone']      ?? ''),
                        trim($cot['email']          ?? ''),
                        $cot_photo
                    ]);
                    $cot_client_id = $pdo->lastInsertId();
                }

                // Lier au compte (INSERT IGNORE évite les doublons)
                $pdo->prepare("INSERT IGNORE INTO compte_cotitulaires (compte_id, client_id) VALUES (?, ?)")
                    ->execute([$compte_id, $cot_client_id]);

                $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
                $stmt->execute([$cot_client_id]);
                $cotitulaires_cree[] = $stmt->fetch();
            }
        }

        $pdo->commit();

        // Récupérer les infos complètes du compte créé
        $stmt = $pdo->prepare("
            SELECT c.*, tc.nom AS type_compte_nom, tc.code AS type_compte_code,
                   s.code AS succursale_code, s.nom AS succursale_nom,
                   cl.id_client, cl.nom, cl.prenom, cl.telephone, cl.email,
                   cl.adresse, cl.date_naissance, cl.lieu_naissance
            FROM comptes c
            JOIN types_comptes tc ON c.type_compte_id = tc.id
            JOIN succursales s    ON c.succursale_id  = s.id
            JOIN clients cl       ON c.titulaire_principal_id = cl.id
            WHERE c.id = ?
        ");
        $stmt->execute([$compte_id]);
        $compte_cree = $stmt->fetch();

        $message = "Compte N° $id_compte_genere créé avec succès !";

        $pdo->prepare("INSERT INTO logs_activites (utilisateur_id, action, details, ip_address) VALUES (?, 'creation_compte', ?, ?)")
            ->execute([$_SESSION['user_id'], "Compte $id_compte_genere créé pour $id_client", $_SERVER['REMOTE_ADDR']]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// ── Suggestion id_client ──────────────────────────────────────
function genererIdClientSuggestion(PDO $pdo): string {
    $total = (int) $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
    $base  = 100 + $total + 1;
    $a = str_pad((int)($base / 1000), 3, '0', STR_PAD_LEFT);
    $b = str_pad($base % 1000, 3, '0', STR_PAD_LEFT);
    $c = str_pad((($base * 3) % 1000), 3, '0', STR_PAD_LEFT);
    $d = ($base % 9) + 1;
    return "$a-$b-$c-$d";
}

// ── Upload helper ─────────────────────────────────────────────
function uploadPhoto(string $fieldName, string $uploadDir, string $prefix): ?string {
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) return null;
    $ext = strtolower(pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'])) return null;
    if ($_FILES[$fieldName]['size'] > 3 * 1024 * 1024) return null;
    if (!getimagesize($_FILES[$fieldName]['tmp_name'])) return null;
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $path = 'uploads/photos/' . uniqid($prefix, true) . '.' . $ext;
    move_uploaded_file($_FILES[$fieldName]['tmp_name'], '../' . $path);
    return $path;
}

$suggestion_id = genererIdClientSuggestion($pdo);
$currentPage = 'creer_compte';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Création de compte - S&P illico</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/common.css">
</head>
<body>
    <?php require_once '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar no-print">
            <div class="page-title">
                <h1>Création de compte client</h1>
                <div class="breadcrumb"><a href="dashboard.php">Accueil</a> / Créer compte</div>
            </div>
            <div class="top-right">
                <!-- <span class="top-succursale"><i class="fas fa-building"></i> <?= htmlspecialchars($_SESSION['succursale_nom']) ?></span> -->
                <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
            </div>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error no-print"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($message): ?>
        <div class="alert alert-success no-print"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if (!$compte_cree): ?>
        <!-- ── Formulaire ───────────────────────────────────── -->
        <form method="post" enctype="multipart/form-data" id="formCreation" class="no-print">

            <!-- Recherche client existant -->
            <div class="card">
                <h3 style="margin-bottom:12px;"><i class="fas fa-search"></i> Vérifier si le client existe déjà</h3>
                <div style="display:flex;gap:10px;">
                    <input type="text" id="searchClientInput" class="form-control"
                           placeholder="NIF/CINU (XXX-XXX-XXX-X)" style="flex:1;"
                           value="<?= htmlspecialchars($_GET['search_client'] ?? '') ?>">
                    <button type="button" class="btn btn-outline" onclick="rechercherClient()">
                        <i class="fas fa-search"></i> Vérifier
                    </button>
                </div>
                <div id="clientInfo" style="display:none;margin-top:12px;" class="alert alert-success"></div>
                <div id="clientNotFound" style="display:none;margin-top:12px;" class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> Aucun client trouvé. Remplissez les informations ci-dessous.
                </div>
            </div>

            <!-- Informations du compte -->
            <div class="card">
                <h3 style="margin-bottom:18px;"><i class="fas fa-credit-card"></i> Informations du compte</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Type de compte *</label>
                        <select name="type_compte_id" class="form-control" required>
                            <option value="">Sélectionner...</option>
                            <?php
                            $currentCat = '';
                            foreach ($types_comptes as $type):
                                if ($type['categorie'] !== $currentCat):
                                    if ($currentCat !== '') echo '</optgroup>';
                                    $currentCat = $type['categorie'];
                                    echo '<optgroup label="' . ucfirst($currentCat) . '">';
                                endif;
                            ?>
                            <option value="<?= $type['id'] ?>">
                                <?= htmlspecialchars($type['nom']) ?>
                                (<?= $type['taux_interet'] > 0 ? $type['taux_interet'] . '% int.' : 'Sans intérêt' ?>
                                — Min: <?= number_format($type['solde_minimum'], 0, ',', ' ') ?> <?= $type['devise_defaut'] ?>)
                            </option>
                            <?php endforeach; if ($currentCat) echo '</optgroup>'; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-coins"></i> Devise *</label>
                        <select name="devise" class="form-control" required>
                            <option value="HTG">Gourdes (HTG)</option>
                            <option value="USD">Dollars US (USD)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-calendar"></i> Date de création *</label>
                        <input type="date" name="date_creation" class="form-control"
                               value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
            </div>

            <!-- Titulaire principal -->
            <div class="card">
                <h3 style="margin-bottom:18px;"><i class="fas fa-user-circle"></i> Titulaire principal</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> Type de pièce</label>
                        <select name="type_piece" class="form-control">
                            <option value="NIF">NIF</option>
                            <option value="CINU">CINU</option>
                            <option value="PASSEPORT">Passeport</option>
                            <option value="AUTRE">Autre</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-hashtag"></i> NIF / CINU * <small>(XXX-XXX-XXX-X)</small></label>
                        <input type="text" name="id_client" id="id_client" class="form-control"
                               placeholder="Ex: 102-304-509-1"
                               value="<?= htmlspecialchars($client_existant['id_client'] ?? $suggestion_id) ?>"
                               maxlength="13" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Nom *</label>
                        <input type="text" name="nom" id="nom" class="form-control"
                               value="<?= htmlspecialchars($client_existant['nom'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Prénom *</label>
                        <input type="text" name="prenom" id="prenom" class="form-control"
                               value="<?= htmlspecialchars($client_existant['prenom'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-calendar"></i> Date de naissance</label>
                        <input type="date" name="date_naissance" id="date_naissance" class="form-control"
                               value="<?= htmlspecialchars($client_existant['date_naissance'] ?? '') ?>"
                               max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-map-pin"></i> Lieu de naissance</label>
                        <input type="text" name="lieu_naissance" id="lieu_naissance" class="form-control"
                               value="<?= htmlspecialchars($client_existant['lieu_naissance'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Téléphone</label>
                        <input type="tel" name="telephone" id="telephone" class="form-control"
                               value="<?= htmlspecialchars($client_existant['telephone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" name="email" id="email" class="form-control"
                               value="<?= htmlspecialchars($client_existant['email'] ?? '') ?>">
                    </div>
                    <div class="form-group full-width">
                        <label><i class="fas fa-map"></i> Adresse</label>
                        <textarea name="adresse" id="adresse" class="form-control" rows="2"><?= htmlspecialchars($client_existant['adresse'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-camera"></i> Photo (optionnel)</label>
                        <input type="file" name="photo" id="photoInput" class="form-control" accept="image/*">
                        <div class="form-hint">JPG, PNG, WEBP — max 3 Mo</div>
                    </div>
                    <div class="form-group" style="display:flex;align-items:center;justify-content:center;">
                        <div id="photoPreview" style="width:90px;height:90px;border-radius:10px;overflow:hidden;background:#f1f5f9;display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-user" style="font-size:36px;color:#cbd5e1;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Co-titulaires -->
            <div class="card">
                <h3 style="margin-bottom:18px;"><i class="fas fa-users"></i> Co-titulaires <small style="color:#64748b;">(optionnel)</small></h3>
                <div id="cotitulairesContainer"></div>
                <button type="button" class="btn btn-outline" onclick="ajouterCotitulaire()">
                    <i class="fas fa-plus"></i> Ajouter un co-titulaire
                </button>
            </div>

            <div class="form-actions no-print">
                <button type="reset" class="btn btn-secondary"><i class="fas fa-times"></i> Annuler</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Créer le compte</button>
            </div>
        </form>

        <?php else: ?>
        <!-- ── Résultat / Impression ────────────────────────── -->
        <div id="zoneImpression">
            <!-- Fiche titulaire -->
            <div class="fiche-impression" id="ficheTitulaire">
                <div style="text-align:center;margin-bottom:20px;">
                    <h2>S&P illico — Banque Communautaire</h2>
                    <p>Fiche d'inscription — Titulaire principal</p>
                    <small><?= date('d/m/Y H:i') ?></small>
                </div>
                <div style="border:2px solid #1e3a8a;padding:20px;border-radius:10px;">
                    <h3 style="color:#1e3a8a;margin-bottom:15px;">Compte N° <?= htmlspecialchars($compte_cree['id_compte']) ?></h3>
                    <table style="width:100%;border-collapse:collapse;">
                        <tr><td style="padding:7px;border-bottom:1px solid #ddd;width:40%;"><strong>Type de compte :</strong></td>
                            <td style="padding:7px;border-bottom:1px solid #ddd;"><?= htmlspecialchars($compte_cree['type_compte_nom']) ?> (<?= $compte_cree['devise'] ?>)</td></tr>
                        <tr><td style="padding:7px;border-bottom:1px solid #ddd;"><strong>Date de création :</strong></td>
                            <td style="padding:7px;border-bottom:1px solid #ddd;"><?= date('d/m/Y', strtotime($compte_cree['date_creation'])) ?></td></tr>
                        <tr><td style="padding:7px;border-bottom:1px solid #ddd;"><strong>Succursale :</strong></td>
                            <td style="padding:7px;border-bottom:1px solid #ddd;"><?= htmlspecialchars($compte_cree['succursale_code'] . ' — ' . $compte_cree['succursale_nom']) ?></td></tr>
                        <tr><td style="padding:7px;border-bottom:1px solid #ddd;"><strong>NIF/CINU :</strong></td>
                            <td style="padding:7px;border-bottom:1px solid #ddd;"><?= htmlspecialchars($compte_cree['id_client']) ?></td></tr>
                        <tr><td style="padding:7px;border-bottom:1px solid #ddd;"><strong>Nom complet :</strong></td>
                            <td style="padding:7px;border-bottom:1px solid #ddd;"><?= htmlspecialchars($compte_cree['prenom'] . ' ' . $compte_cree['nom']) ?></td></tr>
                        <tr><td style="padding:7px;border-bottom:1px solid #ddd;"><strong>Téléphone :</strong></td>
                            <td style="padding:7px;border-bottom:1px solid #ddd;"><?= htmlspecialchars($compte_cree['telephone'] ?: '-') ?></td></tr>
                        <tr><td style="padding:7px;"><strong>Adresse :</strong></td>
                            <td style="padding:7px;"><?= htmlspecialchars($compte_cree['adresse'] ?: '-') ?></td></tr>
                    </table>
                    <div style="margin-top:30px;display:flex;justify-content:space-between;">
                        <span>Signature du client : _________________</span>
                        <span>Signature de l'agent : _________________</span>
                    </div>
                </div>
            </div>

            <!-- Fiches co-titulaires -->
            <?php foreach ($cotitulaires_cree as $idx => $cot): ?>
            <div class="fiche-impression" style="margin-top:40px;page-break-before:always;">
                <div style="text-align:center;margin-bottom:20px;">
                    <h2>S&P illico — Banque Communautaire</h2>
                    <p>Fiche d'inscription — Co-titulaire #<?= $idx + 1 ?></p>
                    <small><?= date('d/m/Y H:i') ?></small>
                </div>
                <div style="border:2px solid #1e3a8a;padding:20px;border-radius:10px;">
                    <h3 style="color:#1e3a8a;margin-bottom:15px;">Compte N° <?= htmlspecialchars($compte_cree['id_compte']) ?> (Co-titulaire)</h3>
                    <table style="width:100%;border-collapse:collapse;">
                        <tr><td style="padding:7px;border-bottom:1px solid #ddd;width:40%;"><strong>NIF/CINU :</strong></td>
                            <td style="padding:7px;border-bottom:1px solid #ddd;"><?= htmlspecialchars($cot['id_client']) ?></td></tr>
                        <tr><td style="padding:7px;border-bottom:1px solid #ddd;"><strong>Nom complet :</strong></td>
                            <td style="padding:7px;border-bottom:1px solid #ddd;"><?= htmlspecialchars($cot['prenom'] . ' ' . $cot['nom']) ?></td></tr>
                        <tr><td style="padding:7px;"><strong>Titulaire principal :</strong></td>
                            <td style="padding:7px;"><?= htmlspecialchars($compte_cree['prenom'] . ' ' . $compte_cree['nom']) ?></td></tr>
                    </table>
                    <div style="margin-top:30px;display:flex;justify-content:space-between;">
                        <span>Signature du co-titulaire : _________________</span>
                        <span>Signature de l'agent : _________________</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <div style="display:flex;gap:12px;justify-content:center;margin-top:30px;" class="no-print">
                <button class="btn btn-purple" onclick="imprimerTitulaire()">
                    <i class="fas fa-print"></i> Imprimer fiche titulaire
                </button>
                <?php if (!empty($cotitulaires_cree)): ?>
                <button class="btn btn-purple" onclick="window.print()">
                    <i class="fas fa-print"></i> Imprimer toutes les fiches
                </button>
                <?php endif; ?>
                <a href="creer_compte.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nouveau compte</a>
                <a href="verification.php?search=<?= urlencode($compte_cree['id_compte']) ?>" class="btn btn-secondary">
                    <i class="fas fa-search"></i> Voir le compte
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        let cotitulaireCount = 0;

        // ── Ajout d'un co-titulaire ───────────────────────────
        function ajouterCotitulaire() {
            cotitulaireCount++;
            const container = document.getElementById('cotitulairesContainer');
            const card = document.createElement('div');
            card.className = 'cotitulaire-card';
            card.id = `cotitulaire-${cotitulaireCount}`;
            card.style.cssText = 'background:#f8fafc;border-radius:12px;padding:18px;margin-bottom:16px;border:1px solid #e2e8f0;';
            card.innerHTML = `
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <h4><i class="fas fa-user"></i> Co-titulaire #${cotitulaireCount}</h4>
                    <button type="button" onclick="supprimerCotitulaire(${cotitulaireCount})"
                            style="background:#ef4444;color:white;border:none;padding:6px 12px;border-radius:6px;cursor:pointer;">
                        <i class="fas fa-trash"></i> Supprimer
                    </button>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>NIF/CINU *</label>
                        <input type="text" name="cotitulaires[${cotitulaireCount}][id_client]"
                               class="form-control cot-nif" placeholder="XXX-XXX-XXX-X" maxlength="13">
                    </div>
                    <div class="form-group">
                        <label>Nom *</label>
                        <input type="text" name="cotitulaires[${cotitulaireCount}][nom]" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Prénom *</label>
                        <input type="text" name="cotitulaires[${cotitulaireCount}][prenom]" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="tel" name="cotitulaires[${cotitulaireCount}][telephone]" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Date de naissance</label>
                        <input type="date" name="cotitulaires[${cotitulaireCount}][date_naissance]" class="form-control" max="${new Date().toISOString().split('T')[0]}">
                    </div>
                    <div class="form-group">
                        <label>Adresse</label>
                        <input type="text" name="cotitulaires[${cotitulaireCount}][adresse]" class="form-control">
                    </div>
                </div>
            `;
            container.appendChild(card);
            // Formatage NIF sur le nouveau champ
            card.querySelector('.cot-nif').addEventListener('input', formatNIF);
        }

        function supprimerCotitulaire(id) {
            document.getElementById(`cotitulaire-${id}`)?.remove();
        }

        // ── Recherche client via AJAX ─────────────────────────
        async function rechercherClient() {
            const nif = document.getElementById('searchClientInput').value.trim();
            const infoBox = document.getElementById('clientInfo');
            const notFound = document.getElementById('clientNotFound');
            infoBox.style.display = 'none';
            notFound.style.display = 'none';

            if (!nif) { alert('Veuillez saisir un NIF/CINU.'); return; }

            try {
                const res = await fetch(`creer_compte.php?ajax_search=${encodeURIComponent(nif)}`);
                const data = await res.json();

                if (data && data.id) {
                    // Remplir le formulaire avec les données trouvées
                    setField('id_client',      data.id_client);
                    setField('nom',            data.nom);
                    setField('prenom',         data.prenom);
                    setField('date_naissance', data.date_naissance ?? '');
                    setField('lieu_naissance', data.lieu_naissance ?? '');
                    setField('telephone',      data.telephone ?? '');
                    setField('email',          data.email ?? '');
                    setField('adresse',        data.adresse ?? '');
                    infoBox.innerHTML = `<i class="fas fa-check-circle"></i> Client trouvé : <strong>${data.prenom} ${data.nom}</strong> — formulaire pré-rempli.`;
                    infoBox.style.display = 'flex';
                } else {
                    notFound.style.display = 'flex';
                }
            } catch(e) {
                alert('Erreur lors de la recherche.');
            }
        }

        function setField(id, value) {
            const el = document.getElementById(id);
            if (el) el.value = value || '';
        }

        // ── Formatage NIF/CINU ────────────────────────────────
        function formatNIF(e) {
            let digits = this.value.replace(/\D/g, '').substring(0, 10);
            let fmt = '';
            if (digits.length > 0) fmt  = digits.substring(0, 3);
            if (digits.length > 3) fmt += '-' + digits.substring(3, 6);
            if (digits.length > 6) fmt += '-' + digits.substring(6, 9);
            if (digits.length > 9) fmt += '-' + digits.substring(9, 10);
            this.value = fmt;
        }
        document.getElementById('id_client')?.addEventListener('input', formatNIF);

        // ── Prévisualisation photo ────────────────────────────
        document.getElementById('photoInput')?.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = e => {
                document.getElementById('photoPreview').innerHTML =
                    `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;">`;
            };
            reader.readAsDataURL(file);
        });

        // ── Validation formulaire ─────────────────────────────
        document.getElementById('formCreation')?.addEventListener('submit', function (e) {
            const nif = document.getElementById('id_client').value;
            if (!/^\d{3}-\d{3}-\d{3}-\d$/.test(nif)) {
                e.preventDefault();
                alert('Format NIF/CINU invalide. Attendu : XXX-XXX-XXX-X');
                return;
            }
            if (!confirm('Confirmer la création du compte ?')) {
                e.preventDefault();
            }
        });

        // ── Impression titulaire uniquement ───────────────────
        function imprimerTitulaire() {
            const content = document.getElementById('ficheTitulaire').innerHTML;
            const w = window.open('', '_blank');
            w.document.write(`<html><head><title>Fiche titulaire</title></head><body style="padding:20px;font-family:sans-serif;">${content}</body></html>`);
            w.document.close();
            w.print();
        }
    </script>

    <style>
        @media print {
            .sidebar, .top-bar, .no-print { display: none !important; }
            .main-content { margin-left: 0 !important; }
            body { background: white !important; }
        }
    </style>
</body>
</html>
