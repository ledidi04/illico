<?php
require_once '../config/connexion.php';
session_start();

// Vérifier l'authentification et le rôle (admin ou secretaire)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'secretaire'])) {
    header('Location: ../index.php');
    exit;
}

$message = '';
$error = '';
$compte_cree = null;
$cotitulaires_cree = [];
$client_existant = null;

// Récupérer les types de comptes
$types_comptes = $pdo->query("
    SELECT * FROM types_comptes 
    WHERE actif = 1 
    ORDER BY code
")->fetchAll();

// Vérifier si un client existe déjà (recherche par NIF/CINU)
if (isset($_GET['search_client']) && !empty($_GET['search_client'])) {
    $search = $_GET['search_client'];
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id_client = ?");
    $stmt->execute([$search]);
    $client_existant = $stmt->fetch();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // ========== 1. GESTION DU TITULAIRE PRINCIPAL ==========
        $type_piece = $_POST['type_piece'] ?? 'NIF';
        $id_client = trim($_POST['id_client'] ?? '');
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $date_naissance = $_POST['date_naissance'] ?? null;
        $lieu_naissance = trim($_POST['lieu_naissance'] ?? '');
        $adresse = trim($_POST['adresse'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        // Validation
        if (empty($id_client) || empty($nom) || empty($prenom)) {
            throw new Exception("Les champs NIF/CINU, Nom et Prénom sont obligatoires.");
        }
        
        // Format du NIF/CINU
        if (!preg_match('/^\d{3}-\d{3}-\d{3}-\d{1}$/', $id_client)) {
            throw new Exception("Le format du NIF/CINU doit être XXX-XXX-XXX-X (ex: 102-304-509-1)");
        }
        
        // Vérifier si le client existe déjà
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE id_client = ?");
        $stmt->execute([$id_client]);
        $client_id = $stmt->fetchColumn();
        
        // Upload photo (optionnel)
        $photo_path = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/photos/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $photo_path = 'uploads/photos/' . uniqid('client_') . '.' . $ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], '../' . $photo_path);
        }
        
        if (!$client_id) {
            // Créer le nouveau client
            $stmt = $pdo->prepare("
                INSERT INTO clients (id_client, nom, prenom, date_naissance, lieu_naissance, adresse, telephone, email, photo, type_piece)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$id_client, $nom, $prenom, $date_naissance, $lieu_naissance, $adresse, $telephone, $email, $photo_path, $type_piece]);
            $client_id = $pdo->lastInsertId();
        } else {
            // Mettre à jour les informations du client existant
            if ($photo_path) {
                $stmt = $pdo->prepare("
                    UPDATE clients SET nom = ?, prenom = ?, date_naissance = ?, lieu_naissance = ?, 
                    adresse = ?, telephone = ?, email = ?, photo = ?, type_piece = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$nom, $prenom, $date_naissance, $lieu_naissance, $adresse, $telephone, $email, $photo_path, $type_piece, $client_id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE clients SET nom = ?, prenom = ?, date_naissance = ?, lieu_naissance = ?, 
                    adresse = ?, telephone = ?, email = ?, type_piece = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$nom, $prenom, $date_naissance, $lieu_naissance, $adresse, $telephone, $email, $type_piece, $client_id]);
            }
        }
        
        // ========== 2. CRÉATION DU COMPTE ==========
        $type_compte_id = intval($_POST['type_compte_id'] ?? 0);
        $devise = $_POST['devise'] ?? 'HTG';
        $date_creation = $_POST['date_creation'] ?? date('Y-m-d');
        
        if ($type_compte_id <= 0) {
            throw new Exception("Veuillez sélectionner un type de compte.");
        }
        
        // Insérer le compte
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
        
        // Récupérer l'ID du compte généré
        $stmt = $pdo->prepare("SELECT id_compte FROM comptes WHERE id = ?");
        $stmt->execute([$compte_id]);
        $id_compte_genere = $stmt->fetchColumn();
        
        // ========== 3. GESTION DES CO-TITULAIRES ==========
        $cotitulaires_ids = [];
        
        if (isset($_POST['cotitulaires']) && is_array($_POST['cotitulaires'])) {
            foreach ($_POST['cotitulaires'] as $index => $cotitulaire) {
                if (empty($cotitulaire['id_client']) || empty($cotitulaire['nom']) || empty($cotitulaire['prenom'])) {
                    continue;
                }
                
                $cot_id_client = trim($cotitulaire['id_client']);
                
                // Vérifier si le co-titulaire existe déjà
                $stmt = $pdo->prepare("SELECT id FROM clients WHERE id_client = ?");
                $stmt->execute([$cot_id_client]);
                $cot_client_id = $stmt->fetchColumn();
                
                // Upload photo co-titulaire
                $cot_photo = null;
                if (isset($_FILES['cotitulaires']['name'][$index]['photo']) && $_FILES['cotitulaires']['error'][$index]['photo'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($_FILES['cotitulaires']['name'][$index]['photo'], PATHINFO_EXTENSION);
                    $cot_photo = 'uploads/photos/' . uniqid('cot_') . '.' . $ext;
                    move_uploaded_file($_FILES['cotitulaires']['tmp_name'][$index]['photo'], '../' . $cot_photo);
                }
                
                if (!$cot_client_id) {
                    // Créer le co-titulaire
                    $stmt = $pdo->prepare("
                        INSERT INTO clients (id_client, nom, prenom, date_naissance, lieu_naissance, adresse, telephone, email, photo)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $cot_id_client,
                        trim($cotitulaire['nom']),
                        trim($cotitulaire['prenom']),
                        $cotitulaire['date_naissance'] ?? null,
                        trim($cotitulaire['lieu_naissance'] ?? ''),
                        trim($cotitulaire['adresse'] ?? ''),
                        trim($cotitulaire['telephone'] ?? ''),
                        trim($cotitulaire['email'] ?? ''),
                        $cot_photo
                    ]);
                    $cot_client_id = $pdo->lastInsertId();
                }
                
                // Lier au compte
                $stmt = $pdo->prepare("
                    INSERT IGNORE INTO compte_cotitulaires (compte_id, client_id)
                    VALUES (?, ?)
                ");
                $stmt->execute([$compte_id, $cot_client_id]);
                
                // Récupérer les infos pour l'impression
                $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
                $stmt->execute([$cot_client_id]);
                $cotitulaires_cree[] = $stmt->fetch();
            }
        }
        
        $pdo->commit();
        
        // Récupérer les infos complètes du compte créé
        $stmt = $pdo->prepare("
            SELECT c.*, tc.nom as type_compte_nom, tc.code as type_compte_code,
                   s.code as succursale_code, s.nom as succursale_nom,
                   cl.*
            FROM comptes c
            JOIN types_comptes tc ON c.type_compte_id = tc.id
            JOIN succursales s ON c.succursale_id = s.id
            JOIN clients cl ON c.titulaire_principal_id = cl.id
            WHERE c.id = ?
        ");
        $stmt->execute([$compte_id]);
        $compte_cree = $stmt->fetch();
        
        $message = "Compte N° " . $id_compte_genere . " créé avec succès !";
        
        // Log de l'action
        $logStmt = $pdo->prepare("
            INSERT INTO logs_activites (utilisateur_id, action, details, ip_address)
            VALUES (?, 'creation_compte', ?, ?)
        ");
        $logStmt->execute([$_SESSION['user_id'], "Création du compte $id_compte_genere", $_SERVER['REMOTE_ADDR']]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erreur : " . $e->getMessage();
    }
}

$currentPage = 'creer_compte';

// Récupérer le dernier ID client pour suggestion
$stmt = $pdo->query("SELECT id_client FROM clients ORDER BY id DESC LIMIT 1");
$dernier_id = $stmt->fetchColumn();
if ($dernier_id) {
    // Suggérer le prochain ID
    preg_match('/^(\d{3})-(\d{3})-(\d{3})-(\d)$/', $dernier_id, $matches);
    if ($matches) {
        $suggestion_id = sprintf('%03d-%03d-%03d-%d', $matches[1], $matches[2], intval($matches[3]) + 1, rand(1, 9));
    } else {
        $suggestion_id = '102-304-509-1';
    }
} else {
    $suggestion_id = '102-304-509-1';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Création de compte - S&P illico</title>
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
        .user-info-side .name { font-weight: 600; }
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
        .logout-btn { background: #ef4444; color: white; padding: 10px 18px; border-radius: 10px; text-decoration: none; }
        
        .card { background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 30px; margin-bottom: 24px; }
        .card-header { margin-bottom: 25px; }
        .card-header h2 { color: #1e293b; font-size: 20px; display: flex; align-items: center; gap: 10px; }
        
        .alert { padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
        .alert-success { background: #dcfce7; color: #166534; border-left: 4px solid #16a34a; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group.full-width { grid-column: span 2; }
        .form-group label { display: block; margin-bottom: 8px; color: #475569; font-size: 14px; font-weight: 500; }
        .form-group label i { margin-right: 8px; color: #3b82f6; width: 18px; }
        .form-control { width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px; }
        .form-control:focus { outline: none; border-color: #3b82f6; }
        select.form-control { background: white; }
        
        .section-title { font-size: 18px; color: #1e293b; margin: 30px 0 20px; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0; display: flex; align-items: center; gap: 10px; }
        
        .btn { padding: 12px 24px; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-success { background: #10b981; color: white; }
        .btn-secondary { background: #64748b; color: white; }
        .btn-outline { background: transparent; border: 2px solid #3b82f6; color: #3b82f6; }
        
        .cotitulaire-card { background: #f8fafc; border-radius: 12px; padding: 20px; margin-bottom: 20px; border: 1px solid #e2e8f0; position: relative; }
        .cotitulaire-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .cotitulaire-header h4 { color: #1e293b; }
        .btn-remove { background: #ef4444; color: white; padding: 6px 12px; border-radius: 6px; border: none; cursor: pointer; font-size: 12px; }
        
        .recherche-client { background: #f0f9ff; padding: 20px; border-radius: 12px; margin-bottom: 25px; }
        .client-info { background: #dcfce7; padding: 15px; border-radius: 10px; margin-top: 15px; }
        
        .resultat-impression { background: white; border-radius: 16px; padding: 30px; }
        .fiche-impression { font-family: 'Courier New', monospace; }
        .fiche-header { text-align: center; margin-bottom: 20px; }
        .fiche-title { font-size: 24px; font-weight: bold; }
        .fiche-subtitle { color: #64748b; }
        
        .btn-imprimer { background: #8b5cf6; color: white; margin-right: 10px; }
        .btn-imprimer:hover { background: #7c3aed; }
        
        @media print {
            .sidebar, .top-bar, .btn, form, .no-print { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
            body { background: white !important; }
            .print-only { display: block !important; }
        }
        .print-only { display: none; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-building-columns"></i> S&P illico</h2>
            <p>Banque Communautaire</p>
        </div>
        <div class="user-info-side">
            <div class="avatar"><i class="fas fa-user-tie"></i></div>
            <div class="name"><?= htmlspecialchars($_SESSION['nom_complet']) ?></div>
            <div class="role"><i class="fas fa-shield"></i> <?= ucfirst($_SESSION['role']) ?></div>
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-item "><i class="fas fa-gauge"></i> Tableau de bord</a>
            <a href="utilisateurs.php" class="nav-item"><i class="fas fa-users-gear"></i> Utilisateurs</a>
            <a href="#" class="nav-item"><i class="fas fa-users"></i> Clients</a>
            <a href="compte.php" class="nav-item active"><i class="fas fa-credit-card"></i> Comptes</a>
            <div class="nav-divider"></div>
            <a href="statistiques.php" class="nav-item"><i class="fas fa-chart-pie"></i> Statistiques</a>
            <a href="rapports.php" class="nav-item"><i class="fas fa-file-pdf"></i> Rapports</a>
            
            <div class="nav-divider"></div>
          
            <a href="../logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="top-bar no-print">
            <div class="page-title">
                <h1>Création de compte client</h1>
                <div class="breadcrumb"><a href="dashboard.php">Accueil</a> / Créer compte</div>
            </div>
            <div>
                <span style="margin-right: 15px; color: #64748b;">
                    <i class="fas fa-building"></i> <?= htmlspecialchars($_SESSION['succursale_nom']) ?>
                </span>
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
        <!-- Formulaire de création -->
        <form method="post" enctype="multipart/form-data" id="formCreation" class="no-print">
            <!-- Recherche client existant -->
            <div class="recherche-client">
                <h4 style="margin-bottom: 15px;"><i class="fas fa-search"></i> Vérifier si le client existe déjà</h4>
                <div style="display: flex; gap: 10px;">
                    <input type="text" id="search_client" class="form-control" placeholder="NIF/CINU (XXX-XXX-XXX-X)" style="flex: 1;">
                    <button type="button" class="btn btn-outline" onclick="searchClient()">
                        <i class="fas fa-search"></i> Vérifier
                    </button>
                </div>
                <div id="clientInfo" style="display: none;" class="client-info"></div>
            </div>
            
            <!-- Informations générales du compte -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-credit-card"></i> Informations du compte</h2>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Type de compte *</label>
                        <select name="type_compte_id" class="form-control" required>
                            <option value="">Sélectionner...</option>
                            <?php foreach ($types_comptes as $type): ?>
                            <option value="<?= $type['id'] ?>">
                                <?= htmlspecialchars($type['nom']) ?> 
                                (<?= $type['taux_interet'] > 0 ? $type['taux_interet'].'%' : 'Sans intérêt' ?>)
                            </option>
                            <?php endforeach; ?>
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
                        <input type="date" name="date_creation" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
            </div>
            
            <!-- Titulaire principal -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-user-circle"></i> Titulaire principal</h2>
                </div>
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
                        <label><i class="fas fa-id-card"></i> NIF/CINU * (XXX-XXX-XXX-X)</label>
                        <input type="text" name="id_client" id="id_client" class="form-control" 
                               placeholder="Ex: 102-304-509-1" value="<?= htmlspecialchars($client_existant['id_client'] ?? $suggestion_id) ?>" required>
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
                               value="<?= htmlspecialchars($client_existant['date_naissance'] ?? '') ?>">
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
                        <input type="file" name="photo" class="form-control" accept="image/*" id="photoInput">
                        <small style="color: #64748b;">Formats acceptés: JPG, PNG, GIF (max 5MB)</small>
                    </div>
                    <div class="form-group">
                        <div id="photoPreview" style="width: 100px; height: 100px; border-radius: 10px; overflow: hidden; background: #f1f5f9; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-user" style="font-size: 40px; color: #cbd5e1;"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Co-titulaires -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-users"></i> Co-titulaires (optionnel)</h2>
                </div>
                
                <div id="cotitulairesContainer"></div>
                
                <button type="button" class="btn btn-outline" onclick="ajouterCotitulaire()">
                    <i class="fas fa-plus"></i> Ajouter un co-titulaire
                </button>
            </div>
            
            <div style="display: flex; gap: 15px; justify-content: flex-end;">
                <button type="reset" class="btn btn-secondary"><i class="fas fa-times"></i> Annuler</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Créer le compte</button>
            </div>
        </form>
        
        <?php else: ?>
        <!-- Résultat après création -->
        <div class="resultat-impression" id="zoneImpression">
            <!-- Fiche pour le titulaire principal -->
            <div class="fiche-impression" id="ficheTitulaire">
                <div class="fiche-header">
                    <div class="fiche-title">S&P illico - Banque Communautaire</div>
                    <div class="fiche-subtitle">Fiche d'inscription - Titulaire principal</div>
                    <div style="margin-top: 10px;"><?= date('d/m/Y H:i') ?></div>
                </div>
                
                <div style="border: 2px solid #1e3a8a; padding: 20px; border-radius: 10px;">
                    <h3 style="color: #1e3a8a; margin-bottom: 20px;">Compte N° <?= $compte_cree['id_compte'] ?></h3>
                    
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Type de compte:</strong></td>
                            <td style="padding: 8px; border-bottom: 1px solid #ddd;"><?= htmlspecialchars($compte_cree['type_compte_nom']) ?> (<?= $compte_cree['devise'] ?>)</td></tr>
                        <tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Date de création:</strong></td>
                            <td style="padding: 8px; border-bottom: 1px solid #ddd;"><?= date('d/m/Y', strtotime($compte_cree['date_creation'])) ?></td></tr>
                        <tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Succursale:</strong></td>
                            <td style="padding: 8px; border-bottom: 1px solid #ddd;"><?= htmlspecialchars($compte_cree['succursale_code'] . ' - ' . $compte_cree['succursale_nom']) ?></td></tr>
                    </table>
                    
                    <h4 style="margin: 20px 0 15px; color: #1e3a8a;">Titulaire principal</h4>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr><td style="padding: 8px; border-bottom: 1px solid #ddd; width: 40%;"><strong>NIF/CINU:</strong></td>
                            <td style="padding: 8px; border-bottom: 1px solid #ddd;"><?= htmlspecialchars($compte_cree['id_client']) ?></td></tr>
                        <tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Nom complet:</strong></td>
                            <td style="padding: 8px; border-bottom: 1px solid #ddd;"><?= htmlspecialchars($compte_cree['prenom'] . ' ' . $compte_cree['nom']) ?></td></tr>
                        <tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Date de naissance:</strong></td>
                            <td style="padding: 8px; border-bottom: 1px solid #ddd;"><?= $compte_cree['date_naissance'] ? date('d/m/Y', strtotime($compte_cree['date_naissance'])) : '-' ?></td></tr>
                        <tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Lieu de naissance:</strong></td>
                            <td style="padding: 8px; border-bottom: 1px solid #ddd;"><?= htmlspecialchars($compte_cree['lieu_naissance'] ?: '-') ?></td></tr>
                        <tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Téléphone:</strong></td>
                            <td style="padding: 8px; border-bottom: 1px solid #ddd;"><?= htmlspecialchars($compte_cree['telephone'] ?: '-') ?></td></tr>
                        <tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Adresse:</strong></td>
                            <td style="padding: 8px; border-bottom: 1px solid #ddd;"><?= htmlspecialchars($compte_cree['adresse'] ?: '-') ?></td></tr>
                    </table>
                    
                    <div style="margin-top: 30px; display: flex; justify-content: space-between;">
                        <div>Signature du client: _________________</div>
                        <div>Signature de l'agent: _________________</div>
                    </div>
                </div>
            </div>
            
            <!-- Fiches pour chaque co-titulaire -->
            <?php foreach ($cotitulaires_cree as $index => $cot): ?>
            <div class="fiche-impression" id="ficheCotitulaire<?= $index ?>" style="margin-top: 40px; page-break-before: always;">
                <div class="fiche-header">
                    <div class="fiche-title">S&P illico - Banque Communautaire</div>
                    <div class="fiche-subtitle">Fiche d'inscription - Co-titulaire #<?= $index + 1 ?></div>
                    <div style="margin-top: 10px;"><?= date('d/m/Y H:i') ?></div>
                </div>
                
                <div style="border: 2px solid #1e3a8a; padding: 20px; border-radius: 10px;">
                    <h3 style="color: #1e3a8a; margin-bottom: 20px;">Compte N° <?= $compte_cree['id_compte'] ?> (Co-titulaire)</h3>
                    
                    <h4 style="margin: 20px 0 15px; color: #1e3a8a;">Informations du co-titulaire</h4>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr><td style="padding: 8px; border-bottom: 1px solid #ddd; width: 40%;"><strong>NIF/CINU:</strong></td>
                            <td style="padding: 8px; border-bottom: 1px solid #ddd;"><?= htmlspecialchars($cot['id_client']) ?></td></tr>
                        <tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Nom complet:</strong></td>
                            <td style="padding: 8px; border-bottom: 1px solid #ddd;"><?= htmlspecialchars($cot['prenom'] . ' ' . $cot['nom']) ?></td></tr>
                        <tr><td style="padding: 8px; border-bottom: 1px solid #ddd;"><strong>Téléphone:</strong></td>
                            <td style="padding: 8px; border-bottom: 1px solid #ddd;"><?= htmlspecialchars($cot['telephone'] ?: '-') ?></td></tr>
                    </table>
                    
                    <div style="margin-top: 30px;">
                        <p><strong>Titulaire principal:</strong> <?= htmlspecialchars($compte_cree['prenom'] . ' ' . $compte_cree['nom']) ?></p>
                    </div>
                    
                    <div style="margin-top: 30px; display: flex; justify-content: space-between;">
                        <div>Signature du co-titulaire: _________________</div>
                        <div>Signature de l'agent: _________________</div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: center;" class="no-print">
                <button class="btn btn-imprimer" onclick="imprimerTitulaire()">
                    <i class="fas fa-print"></i> Imprimer fiche titulaire
                </button>
                <?php if (!empty($cotitulaires_cree)): ?>
                <button class="btn btn-imprimer" onclick="imprimerTous()">
                    <i class="fas fa-print"></i> Imprimer toutes les fiches
                </button>
                <?php endif; ?>
                <a href="creer_compte.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nouveau compte
                </a>
                <a href="verification.php?search=<?= $compte_cree['id_compte'] ?>" class="btn btn-secondary">
                    <i class="fas fa-search"></i> Voir le compte
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        let cotitulaireCount = 0;
        
        function ajouterCotitulaire() {
            cotitulaireCount++;
            const container = document.getElementById('cotitulairesContainer');
            
            const card = document.createElement('div');
            card.className = 'cotitulaire-card';
            card.id = `cotitulaire-${cotitulaireCount}`;
            card.innerHTML = `
                <div class="cotitulaire-header">
                    <h4><i class="fas fa-user"></i> Co-titulaire #${cotitulaireCount}</h4>
                    <button type="button" class="btn-remove" onclick="supprimerCotitulaire(${cotitulaireCount})">
                        <i class="fas fa-trash"></i> Supprimer
                    </button>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>NIF/CINU *</label>
                        <input type="text" name="cotitulaires[${cotitulaireCount}][id_client]" class="form-control" placeholder="XXX-XXX-XXX-X" required>
                    </div>
                    <div class="form-group">
                        <label>Nom *</label>
                        <input type="text" name="cotitulaires[${cotitulaireCount}][nom]" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Prénom *</label>
                        <input type="text" name="cotitulaires[${cotitulaireCount}][prenom]" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="tel" name="cotitulaires[${cotitulaireCount}][telephone]" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Photo</label>
                        <input type="file" name="cotitulaires[${cotitulaireCount}][photo]" class="form-control" accept="image/*">
                    </div>
                </div>
            `;
            container.appendChild(card);
        }
        
        function supprimerCotitulaire(id) {
            document.getElementById(`cotitulaire-${id}`).remove();
        }
        
        function searchClient() {
            const idClient = document.getElementById('search_client').value;
            if (!idClient) {
                alert('Veuillez saisir un NIF/CINU');
                return;
            }
            
            fetch(`creer_compte.php?search_client=${encodeURIComponent(idClient)}`)
                .then(response => response.text())
                .then(html => {
                    // Extraire les informations du client
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const clientInfo = doc.querySelector('.client-info');
                    
                    if (clientInfo) {
                        // Remplir automatiquement le formulaire
                        // Cette partie nécessite d'extraire les données via AJAX
                        alert('Client trouvé ! Les champs seront pré-remplis.');
                        location.href = `creer_compte.php?search_client=${encodeURIComponent(idClient)}`;
                    } else {
                        alert('Aucun client trouvé avec ce NIF/CINU');
                    }
                });
        }
        
        // Prévisualisation de la photo
        document.getElementById('photoInput')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const preview = document.getElementById('photoPreview');
                    preview.innerHTML = `<img src="${event.target.result}" style="width: 100%; height: 100%; object-fit: cover;">`;
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Validation du format NIF/CINU
        document.getElementById('id_client')?.addEventListener('input', function(e) {
            let value = this.value.replace(/[^\d-]/g, '');
            if (value.length === 3 || value.length === 7) {
                value += '-';
            }
            if (value.length === 11) {
                value += '-';
            }
            this.value = value.substring(0, 14);
        });
        
        // Fonctions d'impression
        function imprimerTitulaire() {
            const printContent = document.getElementById('ficheTitulaire').innerHTML;
            const originalContent = document.body.innerHTML;
            
            document.body.innerHTML = `
                <div style="padding: 20px;">
                    ${printContent}
                </div>
            `;
            window.print();
            window.location.reload();
        }
        
        function imprimerTous() {
            window.print();
        }
        
        // Validation du formulaire
        document.getElementById('formCreation')?.addEventListener('submit', function(e) {
            const idClient = document.getElementById('id_client').value;
            const pattern = /^\d{3}-\d{3}-\d{3}-\d{1}$/;
            
            if (!pattern.test(idClient)) {
                e.preventDefault();
                alert('Le format du NIF/CINU doit être XXX-XXX-XXX-X');
                return false;
            }
            
            if (confirm('Confirmer la création du compte ?')) {
                return true;
            }
            e.preventDefault();
            return false;
        });
    </script>
</body>
</html>