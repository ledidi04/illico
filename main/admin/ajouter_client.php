<?php
require_once '../config/connexion.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'secretaire'])) {
    header('Location: ../index.php');
    exit;
}

$message = '';
$error   = '';
$client_cree = null;

// ── Génération d'un id_client suggéré ────────────────────────
// Utilise le nombre total de clients pour garantir un identifiant unique non-prévisible
function genererIdClientSuggestion(PDO $pdo): string {
    $total = (int) $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
    $base  = 100 + $total + 1;
    // Format XXX-XXX-XXX-X : on répartit en 3 blocs + 1 chiffre de contrôle simple
    $a = str_pad((int)($base / 1000), 3, '0', STR_PAD_LEFT);
    $b = str_pad($base % 1000, 3, '0', STR_PAD_LEFT);
    $c = str_pad((($base * 3) % 1000), 3, '0', STR_PAD_LEFT);
    $d = ($base % 9) + 1;
    return "$a-$b-$c-$d";
}

$suggestion_id = genererIdClientSuggestion($pdo);

// ── Traitement POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id_client      = trim($_POST['id_client']      ?? '');
        $type_piece     = $_POST['type_piece']          ?? 'NIF';
        $nom            = trim($_POST['nom']             ?? '');
        $prenom         = trim($_POST['prenom']          ?? '');
        $date_naissance = $_POST['date_naissance']       ?? null;
        $lieu_naissance = trim($_POST['lieu_naissance']  ?? '');
        $adresse        = trim($_POST['adresse']         ?? '');
        $telephone      = trim($_POST['telephone']       ?? '');
        $email          = trim($_POST['email']           ?? '');

        // ── Validations ───────────────────────────────────────
        if (empty($id_client) || empty($nom) || empty($prenom)) {
            throw new Exception("Les champs NIF/CINU, Nom et Prénom sont obligatoires.");
        }
        if (!preg_match('/^\d{3}-\d{3}-\d{3}-\d{1}$/', $id_client)) {
            throw new Exception("Le format du NIF/CINU doit être XXX-XXX-XXX-X (ex: 102-304-509-1).");
        }
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("L'adresse email n'est pas valide.");
        }
        if (!empty($date_naissance)) {
            $dt = DateTime::createFromFormat('Y-m-d', $date_naissance);
            if (!$dt || $dt > new DateTime()) {
                throw new Exception("La date de naissance est invalide ou dans le futur.");
            }
        }

        // Vérifier doublon
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE id_client = ?");
        $stmt->execute([$id_client]);
        if ($stmt->fetch()) {
            throw new Exception("Un client avec ce NIF/CINU existe déjà dans le système.");
        }

        // ── Upload photo (optionnel) ──────────────────────────
        $photo_path = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/photos/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $ext     = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($ext, $allowed)) {
                throw new Exception("Format de photo non autorisé. Utilisez JPG, PNG ou WEBP.");
            }
            if ($_FILES['photo']['size'] > 3 * 1024 * 1024) {
                throw new Exception("La photo ne doit pas dépasser 3 Mo.");
            }
            // Vérifier que c'est vraiment une image
            $imageInfo = getimagesize($_FILES['photo']['tmp_name']);
            if (!$imageInfo) {
                throw new Exception("Le fichier uploadé n'est pas une image valide.");
            }
            $photo_path = 'uploads/photos/' . uniqid('client_', true) . '.' . $ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], '../' . $photo_path);
        }

        // ── Insertion ─────────────────────────────────────────
        $stmt = $pdo->prepare("
            INSERT INTO clients (id_client, type_piece, nom, prenom, date_naissance, lieu_naissance, adresse, telephone, email, photo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $id_client, $type_piece, $nom, $prenom,
            $date_naissance ?: null, $lieu_naissance, $adresse, $telephone, $email, $photo_path
        ]);
        $client_id = $pdo->lastInsertId();

        // Récupérer le client créé pour l'affichage
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
        $stmt->execute([$client_id]);
        $client_cree = $stmt->fetch();

        $message = "Client créé avec succès !";

        // Log
        $pdo->prepare("INSERT INTO logs_activites (utilisateur_id, action, details, ip_address) VALUES (?, 'creation_client', ?, ?)")
            ->execute([$_SESSION['user_id'], "Création client $id_client ($prenom $nom)", $_SERVER['REMOTE_ADDR']]);

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$currentPage = 'ajouter_client';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un client - S&P illico</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/common.css">
</head>
<body>
    <?php require_once '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>Ajouter un client</h1>
                <div class="breadcrumb"><a href="dashboard.php">Accueil</a> / Ajouter client</div>
            </div>
            <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if (!$client_cree): ?>
        <!-- ── Formulaire ───────────────────────────────────── -->
        <div class="card" style="max-width:860px;margin:0 auto;">
            <div class="card-header-icon">
                <i class="fas fa-user-plus" style="color:#10b981;background:#dcfce7;"></i>
                <h2>Nouveau client</h2>
                <p>Créez une fiche client sans ouvrir de compte bancaire</p>
            </div>

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <span>Cette action crée uniquement une fiche client.
                Pour ouvrir un compte bancaire, utilisez <a href="creer_compte.php">Créer un compte</a>.</span>
            </div>

            <form method="post" enctype="multipart/form-data" id="clientForm" novalidate>
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> Type de pièce *</label>
                        <select name="type_piece" class="form-control" required>
                            <option value="NIF">NIF</option>
                            <option value="CINU">CINU</option>
                            <option value="PASSEPORT">Passeport</option>
                            <option value="AUTRE">Autre</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-hashtag"></i> NIF / CINU * <small>(XXX-XXX-XXX-X)</small></label>
                        <input type="text" name="id_client" id="id_client" class="form-control"
                               placeholder="Ex : 102-304-509-1"
                               value="<?= htmlspecialchars($suggestion_id) ?>"
                               maxlength="13" required>
                        <div class="form-hint">Format : 11 chiffres séparés par des tirets</div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Nom *</label>
                        <input type="text" name="nom" class="form-control" placeholder="Nom de famille" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Prénom *</label>
                        <input type="text" name="prenom" class="form-control" placeholder="Prénom" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-calendar"></i> Date de naissance</label>
                        <input type="date" name="date_naissance" class="form-control"
                               max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-map-pin"></i> Lieu de naissance</label>
                        <input type="text" name="lieu_naissance" class="form-control" placeholder="Ville / Commune">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Téléphone</label>
                        <input type="tel" name="telephone" class="form-control" placeholder="+509 XXXX-XXXX">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" name="email" class="form-control" placeholder="exemple@email.com">
                    </div>
                    <div class="form-group full-width">
                        <label><i class="fas fa-map"></i> Adresse</label>
                        <textarea name="adresse" class="form-control" rows="2" placeholder="Adresse complète"></textarea>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-camera"></i> Photo <small>(optionnel)</small></label>
                        <input type="file" name="photo" id="photoInput" class="form-control" accept="image/*">
                        <div class="form-hint">JPG, PNG, WEBP — max 3 Mo</div>
                    </div>
                    <div class="form-group" style="display:flex;align-items:center;justify-content:center;">
                        <div id="photoPreview" style="width:90px;height:90px;border-radius:10px;overflow:hidden;background:#f1f5f9;display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-user" style="font-size:36px;color:#cbd5e1;"></i>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="reset" class="btn btn-secondary"><i class="fas fa-times"></i> Annuler</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Enregistrer le client</button>
                </div>
            </form>
        </div>

        <?php else: ?>
        <!-- ── Résultat ─────────────────────────────────────── -->
        <div class="card" style="max-width:600px;margin:0 auto;text-align:center;">
            <div class="card-header-icon">
                <i class="fas fa-check-circle" style="color:#10b981;background:#dcfce7;"></i>
                <h2>Client enregistré avec succès !</h2>
            </div>

            <div style="background:#f0fdf4;border-radius:12px;padding:22px;margin-bottom:20px;">
                <?php if ($client_cree['photo']): ?>
                <img src="../<?= htmlspecialchars($client_cree['photo']) ?>"
                     style="width:80px;height:80px;border-radius:50%;object-fit:cover;margin-bottom:12px;">
                <?php endif; ?>
                <h3><?= htmlspecialchars($client_cree['prenom'] . ' ' . $client_cree['nom']) ?></h3>
                <p style="margin-top:6px;"><strong>NIF/CINU :</strong> <?= htmlspecialchars($client_cree['id_client']) ?></p>
                <?php if ($client_cree['telephone']): ?>
                <p><i class="fas fa-phone"></i> <?= htmlspecialchars($client_cree['telephone']) ?></p>
                <?php endif; ?>
                <?php if ($client_cree['email']): ?>
                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($client_cree['email']) ?></p>
                <?php endif; ?>
            </div>

            <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
                <a href="creer_compte.php?search_client=<?= urlencode($client_cree['id_client']) ?>" class="btn btn-primary">
                    <i class="fas fa-credit-card"></i> Ouvrir un compte
                </a>
                <a href="ajouter_client.php" class="btn btn-secondary">
                    <i class="fas fa-plus"></i> Nouveau client
                </a>
                <a href="liste_clients.php" class="btn btn-secondary">
                    <i class="fas fa-list"></i> Voir la liste
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // ── Formatage automatique du NIF/CINU ────────────────
        document.getElementById('id_client')?.addEventListener('input', function () {
            let digits = this.value.replace(/\D/g, '').substring(0, 10);
            let fmt = '';
            if (digits.length > 0) fmt  = digits.substring(0, 3);
            if (digits.length > 3) fmt += '-' + digits.substring(3, 6);
            if (digits.length > 6) fmt += '-' + digits.substring(6, 9);
            if (digits.length > 9) fmt += '-' + digits.substring(9, 10);
            this.value = fmt;
        });

        // ── Prévisualisation photo ────────────────────────────
        document.getElementById('photoInput')?.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = e => {
                const preview = document.getElementById('photoPreview');
                preview.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;">`;
            };
            reader.readAsDataURL(file);
        });

        // ── Validation avant soumission ───────────────────────
        document.getElementById('clientForm')?.addEventListener('submit', function (e) {
            const idClient = document.getElementById('id_client').value;
            if (!/^\d{3}-\d{3}-\d{3}-\d$/.test(idClient)) {
                e.preventDefault();
                alert('Le format du NIF/CINU doit être XXX-XXX-XXX-X');
            }
        });
    </script>
</body>
</html>
