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
$client_cree = null;

// Générer une suggestion d'ID client
$stmt = $pdo->query("SELECT id_client FROM clients ORDER BY id DESC LIMIT 1");
$dernier_id = $stmt->fetchColumn();
if ($dernier_id) {
    preg_match('/^(\d{3})-(\d{3})-(\d{3})-(\d)$/', $dernier_id, $matches);
    if ($matches) {
        $suggestion_id = sprintf('%03d-%03d-%03d-%d', $matches[1], $matches[2], intval($matches[3]) + 1, rand(1, 9));
    } else {
        $suggestion_id = '102-304-509-1';
    }
} else {
    $suggestion_id = '102-304-509-1';
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id_client = trim($_POST['id_client'] ?? '');
        $type_piece = $_POST['type_piece'] ?? 'NIF';
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
        if ($stmt->fetch()) {
            throw new Exception("Un client avec ce NIF/CINU existe déjà.");
        }
        
        // Upload photo (optionnel)
        $photo_path = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/photos/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array(strtolower($ext), $allowed)) {
                throw new Exception("Format de photo non autorisé. Utilisez JPG, PNG ou GIF.");
            }
            
            if ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
                throw new Exception("La photo ne doit pas dépasser 5MB.");
            }
            
            $photo_path = 'uploads/photos/' . uniqid('client_') . '.' . $ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], '../' . $photo_path);
        }
        
        // Créer le client
        $stmt = $pdo->prepare("
            INSERT INTO clients (id_client, nom, prenom, date_naissance, lieu_naissance, adresse, telephone, email, photo, type_piece)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$id_client, $nom, $prenom, $date_naissance, $lieu_naissance, $adresse, $telephone, $email, $photo_path, $type_piece]);
        $client_id = $pdo->lastInsertId();
        
        // Récupérer les infos du client créé
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
        $stmt->execute([$client_id]);
        $client_cree = $stmt->fetch();
        
        $message = "Client créé avec succès !";
        
        // Log de l'action
        $logStmt = $pdo->prepare("
            INSERT INTO logs_activites (utilisateur_id, action, details, ip_address)
            VALUES (?, 'creation_client', ?, ?)
        ");
        $logStmt->execute([$_SESSION['user_id'], "Création du client $id_client", $_SERVER['REMOTE_ADDR']]);
        
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', 'Inter', sans-serif; background: #f1f5f9; display: flex; min-height: 100vh; }
        
        .sidebar { width: 280px; background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%); color: white; padding: 24px 0; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 0 20px 24px; border-bottom: 1px solid #334155; margin-bottom: 24px; }
        .sidebar-header h2 { color: #3b82f6; font-size: 22px; display: flex; align-items: center; gap: 10px; }
        .sidebar-header p { color: #94a3b8; font-size: 13px; margin-top: 8px; }
        .user-info-side { padding: 16px 20px; background: #1e293b; margin: 0 16px 20px; border-radius: 12px; }
        .user-info-side .avatar { width: 48px; height: 48px; background: #10b981; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; }
        .user-info-side .avatar i { font-size: 24px; }
        .user-info-side .name { font-weight: 600; margin-bottom: 4px; }
        .user-info-side .role { color: #10b981; font-size: 13px; }
        .nav-menu { padding: 0 12px; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #cbd5e1; text-decoration: none; border-radius: 10px; margin-bottom: 4px; }
        .nav-item i { width: 24px; }
        .nav-item:hover { background: #334155; color: white; }
        .nav-item.active { background: #10b981; color: white; }
        .nav-divider { height: 1px; background: #334155; margin: 16px 0; }
        
        .main-content { margin-left: 280px; flex: 1; padding: 24px; }
        
        .top-bar { background: white; padding: 16px 24px; border-radius: 16px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .page-title h1 { font-size: 24px; color: #1e293b; }
        .breadcrumb { color: #64748b; font-size: 14px; }
        .breadcrumb a { color: #3b82f6; text-decoration: none; }
        .logout-btn { background: #ef4444; color: white; padding: 10px 18px; border-radius: 10px; text-decoration: none; }
        
        .card { background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 30px; max-width: 900px; margin: 0 auto; }
        .card-header { margin-bottom: 25px; text-align: center; }
        .card-header i { font-size: 48px; color: #10b981; background: #dcfce7; padding: 16px; border-radius: 50%; margin-bottom: 16px; }
        .card-header h2 { color: #1e293b; font-size: 24px; }
        .card-header p { color: #64748b; margin-top: 8px; }
        
        .alert { padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
        .alert-success { background: #dcfce7; color: #166534; border-left: 4px solid #16a34a; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group.full-width { grid-column: span 2; }
        .form-group label { display: block; margin-bottom: 8px; color: #475569; font-size: 14px; font-weight: 500; }
        .form-group label i { margin-right: 8px; color: #10b981; width: 18px; }
        .form-control { width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px; }
        .form-control:focus { outline: none; border-color: #10b981; }
        select.form-control { background: white; }
        
        .btn { padding: 14px 28px; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 10px; }
        .btn-primary { background: #10b981; color: white; }
        .btn-primary:hover { background: #059669; }
        .btn-secondary { background: #64748b; color: white; }
        
        .form-actions { display: flex; gap: 15px; justify-content: flex-end; margin-top: 30px; }
        
        .result-card { background: #f0fdf4; border-radius: 12px; padding: 25px; text-align: center; margin-top: 20px; }
        .result-card i { font-size: 48px; color: #10b981; margin-bottom: 15px; }
        .result-card h3 { color: #1e293b; margin-bottom: 10px; }
        
        .info-note { background: #f0f9ff; padding: 15px; border-radius: 10px; margin-bottom: 25px; border-left: 4px solid #3b82f6; }
        .info-note i { color: #3b82f6; margin-right: 10px; }
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
            <a href="dashboard.php" class="nav-item"><i class="fas fa-gauge"></i> Tableau de bord</a>
            <a href="creer_compte.php" class="nav-item"><i class="fas fa-user-plus"></i> Créer compte</a>
            <a href="liste_clients.php" class="nav-item"><i class="fas fa-users"></i> Liste clients</a>
            <a href="ajouter_client.php" class="nav-item active"><i class="fas fa-user"></i> Ajouter client</a>
            <div class="nav-divider"></div>
            <a href="depot.php" class="nav-item"><i class="fas fa-arrow-down"></i> Dépôt</a>
            <a href="retrait.php" class="nav-item"><i class="fas fa-arrow-up"></i> Retrait</a>
            <a href="verification.php" class="nav-item"><i class="fas fa-search"></i> Vérification</a>
            <div class="nav-divider"></div>
            <a href="../logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </nav>
    </div>
    
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
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user-plus"></i>
                <h2>Nouveau client</h2>
                <p>Enregistrez un client sans créer de compte bancaire</p>
            </div>
            
            <div class="info-note">
                <i class="fas fa-info-circle"></i>
                <strong>Note :</strong> Cette action crée uniquement une fiche client. Pour ouvrir un compte bancaire, utilisez 
                <a href="creer_compte.php" style="color: #3b82f6;">Créer un compte</a>.
            </div>
            
            <form method="post" enctype="multipart/form-data" id="clientForm">
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
                        <label><i class="fas fa-id-card"></i> NIF/CINU * (XXX-XXX-XXX-X)</label>
                        <input type="text" name="id_client" id="id_client" class="form-control" 
                               placeholder="Ex: 102-304-509-1" value="<?= htmlspecialchars($suggestion_id) ?>" required>
                        <small style="color: #64748b;">Format: 11 chiffres avec tirets</small>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Nom *</label>
                        <input type="text" name="nom" class="form-control" placeholder="Nom" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Prénom *</label>
                        <input type="text" name="prenom" class="form-control" placeholder="Prénom" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-calendar"></i> Date de naissance</label>
                        <input type="date" name="date_naissance" class="form-control">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-map-pin"></i> Lieu de naissance</label>
                        <input type="text" name="lieu_naissance" class="form-control" placeholder="Ville">
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
                    <div class="form-group full-width">
                        <label><i class="fas fa-camera"></i> Photo (optionnel)</label>
                        <input type="file" name="photo" class="form-control" accept="image/*" id="photoInput">
                        <small style="color: #64748b;">Formats acceptés: JPG, PNG, GIF (max 5MB)</small>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="reset" class="btn btn-secondary"><i class="fas fa-times"></i> Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer le client</button>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-header">
                <i class="fas fa-check-circle" style="color: #10b981;"></i>
                <h2>Client enregistré avec succès !</h2>
            </div>
            
            <div class="result-card">
                <i class="fas fa-user-check"></i>
                <h3><?= htmlspecialchars($client_cree['prenom'] . ' ' . $client_cree['nom']) ?></h3>
                <p style="margin-bottom: 15px;"><strong>NIF/CINU :</strong> <?= htmlspecialchars($client_cree['id_client']) ?></p>
                
                <?php if ($client_cree['telephone']): ?>
                <p style="margin-bottom: 5px;"><i class="fas fa-phone"></i> <?= htmlspecialchars($client_cree['telephone']) ?></p>
                <?php endif; ?>
                <?php if ($client_cree['email']): ?>
                <p style="margin-bottom: 5px;"><i class="fas fa-envelope"></i> <?= htmlspecialchars($client_cree['email']) ?></p>
                <?php endif; ?>
                
                <div style="display: flex; gap: 15px; justify-content: center; margin-top: 25px;">
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
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Formatage du NIF/CINU
        document.getElementById('id_client')?.addEventListener('input', function(e) {
            let value = this.value.replace(/[^\d]/g, '');
            let formatted = '';
            
            if (value.length > 0) {
                formatted = value.substring(0, 3);
                if (value.length > 3) formatted += '-' + value.substring(3, 6);
                if (value.length > 6) formatted += '-' + value.substring(6, 9);
                if (value.length > 9) formatted += '-' + value.substring(9, 10);
            }
            
            this.value = formatted;
        });
        
        // Validation du formulaire
        document.getElementById('clientForm')?.addEventListener('submit', function(e) {
            const idClient = document.getElementById('id_client').value;
            const pattern = /^\d{3}-\d{3}-\d{3}-\d{1}$/;
            
            if (!pattern.test(idClient)) {
                e.preventDefault();
                alert('Le format du NIF/CINU doit être XXX-XXX-XXX-X');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>