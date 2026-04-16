<?php
require_once '../config/connexion.php';
session_start();

// Vérifier l'authentification et le rôle
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$message = '';
$error = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // AJOUTER UN UTILISATEUR
    if ($action === 'add') {
        $succursale_id = $_POST['succursale_id'] ?? '';
        $nom_complet = trim($_POST['nom_complet'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        
        if (empty($nom_complet) || empty($username) || empty($password) || empty($role) || empty($succursale_id)) {
            $error = "Tous les champs obligatoires doivent être remplis.";
        } else {
            try {
                // Vérifier si le username existe déjà
                $check = $pdo->prepare("SELECT id FROM utilisateurs WHERE username = ?");
                $check->execute([$username]);
                if ($check->fetch()) {
                    $error = "Ce nom d'utilisateur existe déjà.";
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO utilisateurs (succursale_id, nom_complet, username, password, role, email, telephone, actif)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([$succursale_id, $nom_complet, $username, $password, $role, $email, $telephone]);
                    $message = "Utilisateur ajouté avec succès.";
                }
            } catch (PDOException $e) {
                $error = "Erreur lors de l'ajout : " . $e->getMessage();
            }
        }
    }
    
    // MODIFIER UN UTILISATEUR
    elseif ($action === 'edit') {
        $id = $_POST['id'] ?? '';
        $succursale_id = $_POST['succursale_id'] ?? '';
        $nom_complet = trim($_POST['nom_complet'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $role = $_POST['role'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        
        if (empty($id) || empty($nom_complet) || empty($username) || empty($role)) {
            $error = "Tous les champs obligatoires doivent être remplis.";
        } else {
            try {
                // Vérifier si le username existe déjà pour un autre utilisateur
                $check = $pdo->prepare("SELECT id FROM utilisateurs WHERE username = ? AND id != ?");
                $check->execute([$username, $id]);
                if ($check->fetch()) {
                    $error = "Ce nom d'utilisateur existe déjà.";
                } else {
                    $sql = "UPDATE utilisateurs SET succursale_id = ?, nom_complet = ?, username = ?, role = ?, email = ?, telephone = ? WHERE id = ?";
                    $params = [$succursale_id, $nom_complet, $username, $role, $email, $telephone, $id];
                    
                    // Si un nouveau mot de passe est fourni
                    if (!empty($_POST['password'])) {
                        $sql = "UPDATE utilisateurs SET succursale_id = ?, nom_complet = ?, username = ?, password = ?, role = ?, email = ?, telephone = ? WHERE id = ?";
                        $params = [$succursale_id, $nom_complet, $username, $_POST['password'], $role, $email, $telephone, $id];
                    }
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $message = "Utilisateur modifié avec succès.";
                }
            } catch (PDOException $e) {
                $error = "Erreur lors de la modification : " . $e->getMessage();
            }
        }
    }
    
    // ACTIVER/DÉSACTIVER UN UTILISATEUR
    elseif ($action === 'toggle') {
        $id = $_POST['id'] ?? '';
        $actif = $_POST['actif'] ?? 0;
        
        try {
            $stmt = $pdo->prepare("UPDATE utilisateurs SET actif = ? WHERE id = ?");
            $stmt->execute([$actif, $id]);
            $message = $actif ? "Utilisateur activé." : "Utilisateur désactivé.";
        } catch (PDOException $e) {
            $error = "Erreur : " . $e->getMessage();
        }
    }
    
    // RÉINITIALISER LE MOT DE PASSE
    elseif ($action === 'reset_password') {
        $id = $_POST['id'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        
        if (empty($new_password)) {
            $error = "Le nouveau mot de passe est requis.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE utilisateurs SET password = ? WHERE id = ?");
                $stmt->execute([$new_password, $id]);
                $message = "Mot de passe réinitialisé avec succès.";
            } catch (PDOException $e) {
                $error = "Erreur : " . $e->getMessage();
            }
        }
    }
    
    // SUPPRIMER UN UTILISATEUR
    elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        
        // Empêcher la suppression de son propre compte
        if ($id == $_SESSION['user_id']) {
            $error = "Vous ne pouvez pas supprimer votre propre compte.";
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Utilisateur supprimé avec succès.";
            } catch (PDOException $e) {
                $error = "Erreur lors de la suppression : " . $e->getMessage();
            }
        }
    }
}

// Récupérer tous les utilisateurs
$users = $pdo->query("
    SELECT u.*, s.code as succursale_code, s.nom as succursale_nom 
    FROM utilisateurs u 
    JOIN succursales s ON u.succursale_id = s.id 
    ORDER BY u.role, u.nom_complet
")->fetchAll();

// Récupérer les succursales pour le formulaire
$succursales = $pdo->query("SELECT * FROM succursales ORDER BY code")->fetchAll();

// Statistiques
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
        SUM(CASE WHEN role = 'secretaire' THEN 1 ELSE 0 END) as secretaires,
        SUM(CASE WHEN role = 'caissier' THEN 1 ELSE 0 END) as caissiers,
        SUM(CASE WHEN actif = 1 THEN 1 ELSE 0 END) as actifs,
        SUM(CASE WHEN actif = 0 THEN 1 ELSE 0 END) as inactifs
    FROM utilisateurs
")->fetch();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs - S&P illico</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', 'Inter', sans-serif; background: #f1f5f9; display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 280px; background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%); color: white; padding: 24px 0; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 0 20px 24px; border-bottom: 1px solid #334155; margin-bottom: 24px; }
        .sidebar-header h2 { color: #3b82f6; font-size: 22px; display: flex; align-items: center; gap: 10px; }
        .sidebar-header h2 i { font-size: 28px; }
        .sidebar-header p { color: #94a3b8; font-size: 13px; margin-top: 8px; }
        .user-info-side { padding: 16px 20px; background: #1e293b; margin: 0 16px 20px; border-radius: 12px; }
        .user-info-side .name { font-weight: 600; margin-bottom: 4px; }
        .user-info-side .role { color: #3b82f6; font-size: 13px; }
        .user-info-side .succursale { color: #94a3b8; font-size: 12px; margin-top: 8px; }
        .nav-menu { padding: 0 12px; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #cbd5e1; text-decoration: none; border-radius: 10px; margin-bottom: 4px; transition: all 0.2s; }
        .nav-item i { width: 24px; font-size: 18px; }
        .nav-item:hover { background: #334155; color: white; }
        .nav-item.active { background: #3b82f6; color: white; }
        .nav-divider { height: 1px; background: #334155; margin: 16px 0; }
        
        /* Main Content */
        .main-content { margin-left: 280px; flex: 1; padding: 24px; }
        
        /* Top Bar */
        .top-bar { background: white; padding: 16px 24px; border-radius: 16px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .page-title h1 { font-size: 24px; color: #1e293b; margin-bottom: 4px; }
        .page-title .breadcrumb { color: #64748b; font-size: 14px; }
        .breadcrumb a { color: #3b82f6; text-decoration: none; }
        .top-actions { display: flex; align-items: center; gap: 20px; }
        .search-box { display: flex; align-items: center; background: #f1f5f9; padding: 8px 16px; border-radius: 30px; }
        .search-box i { color: #64748b; margin-right: 10px; }
        .search-box input { border: none; background: transparent; outline: none; font-size: 14px; width: 200px; }
        .user-profile-top { display: flex; align-items: center; gap: 12px; }
        .user-profile-top img { width: 40px; height: 40px; border-radius: 50%; }
        .logout-btn { background: #ef4444; color: white; padding: 10px 18px; border-radius: 10px; text-decoration: none; font-size: 14px; transition: all 0.2s; }
        .logout-btn:hover { background: #dc2626; }
        
        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: white; padding: 20px; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .stat-card .label { color: #64748b; font-size: 13px; margin-bottom: 8px; }
        .stat-card .value { font-size: 28px; font-weight: 700; color: #1e293b; }
        .stat-card .icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; }
        .stat-card.admin .icon { background: #dbeafe; color: #2563eb; }
        .stat-card.secretaire .icon { background: #dcfce7; color: #16a34a; }
        .stat-card.caissier .icon { background: #fef3c7; color: #d97706; }
        
        /* Alerts */
        .alert { padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; animation: slideIn 0.3s; }
        .alert-success { background: #dcfce7; color: #166534; border-left: 4px solid #16a34a; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        
        /* Content Card */
        .content-card { background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
        .card-header { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .card-header h3 { color: #1e293b; font-size: 18px; }
        .btn { padding: 10px 20px; border-radius: 10px; font-size: 14px; font-weight: 500; cursor: pointer; border: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-success { background: #10b981; color: white; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-secondary { background: #64748b; color: white; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        
        /* Table */
        .table-container { padding: 0; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 14px 16px; background: #f8fafc; color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; }
        td { padding: 16px; border-bottom: 1px solid #e2e8f0; color: #1e293b; font-size: 14px; }
        tr:hover { background: #f8fafc; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; display: inline-block; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-admin { background: #dbeafe; color: #1e40af; }
        .badge-secretaire { background: #dcfce7; color: #166534; }
        .badge-caissier { background: #fef3c7; color: #92400e; }
        .action-buttons { display: flex; gap: 6px; flex-wrap: wrap; }
        .action-btn { width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; border: none; cursor: pointer; transition: all 0.2s; font-size: 14px; }
        .action-btn.edit { background: #dbeafe; color: #2563eb; }
        .action-btn.edit:hover { background: #2563eb; color: white; }
        .action-btn.delete { background: #fee2e2; color: #dc2626; }
        .action-btn.delete:hover { background: #dc2626; color: white; }
        .action-btn.reset { background: #fef3c7; color: #d97706; }
        .action-btn.reset:hover { background: #d97706; color: white; }
        .action-btn.toggle { background: #e2e8f0; color: #475569; }
        .action-btn.toggle:hover { background: #475569; color: white; }
        
        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: white; border-radius: 20px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto; animation: modalIn 0.3s; }
        @keyframes modalIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
        .modal-header { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { color: #1e293b; font-size: 18px; }
        .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b; }
        .modal-body { padding: 24px; }
        .modal-footer { padding: 16px 24px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px; }
        
        /* Form */
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #475569; font-size: 14px; font-weight: 500; }
        .form-group label i { margin-right: 8px; color: #3b82f6; width: 16px; }
        .form-control { width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px; transition: all 0.2s; }
        .form-control:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        select.form-control { background: white; cursor: pointer; }
        
        .password-strength { margin-top: 8px; height: 4px; background: #e2e8f0; border-radius: 2px; }
        .password-strength-bar { height: 100%; border-radius: 2px; transition: width 0.3s; }
        .strength-weak { background: #ef4444; width: 25%; }
        .strength-medium { background: #f59e0b; width: 50%; }
        .strength-strong { background: #10b981; width: 100%; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-building-columns"></i> S&P illico</h2>
            <p>Banque Communautaire</p>
        </div>
        <div class="user-info-side">
            <div class="name"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['nom_complet']) ?></div>
            <div class="role"><i class="fas fa-shield"></i> Administrateur</div>
            <div class="succursale"><i class="fas fa-building"></i> <?= htmlspecialchars($_SESSION['succursale_nom']) ?></div>
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-item "><i class="fas fa-gauge"></i> Tableau de bord</a>
            <a href="utilisateurs.php" class="nav-item active"><i class="fas fa-users-gear"></i> Utilisateurs</a>
            <a href="#" class="nav-item"><i class="fas fa-users"></i> Clients</a>
            <a href="compte.php" class="nav-item"><i class="fas fa-credit-card"></i> Comptes</a>
            <div class="nav-divider"></div>
            <a href="statistiques.php" class="nav-item"><i class="fas fa-chart-pie"></i> Statistiques</a>
            <a href="rapports.php" class="nav-item"><i class="fas fa-file-pdf"></i> Rapports</a>
            
            <div class="nav-divider"></div>
          
            <a href="../logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1>Gestion des Utilisateurs</h1>
                <div class="breadcrumb">
                    <a href="dashboard.php">Administration</a> / Utilisateurs
                </div>
            </div>
            <div class="top-actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Rechercher..." id="searchInput">
                </div>
                <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
        <div class="alert alert-success" id="alertMessage">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-error" id="alertError">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="value"><?= $stats['total'] ?></div>
                <div class="label">Total utilisateurs</div>
            </div>
            <div class="stat-card admin">
                <div class="icon"><i class="fas fa-crown"></i></div>
                <div class="value"><?= $stats['admins'] ?></div>
                <div class="label">Administrateurs</div>
            </div>
            <div class="stat-card secretaire">
                <div class="icon"><i class="fas fa-user-tie"></i></div>
                <div class="value"><?= $stats['secretaires'] ?></div>
                <div class="label">Secrétaires</div>
            </div>
            <div class="stat-card caissier">
                <div class="icon"><i class="fas fa-cash-register"></i></div>
                <div class="value"><?= $stats['caissiers'] ?></div>
                <div class="label">Caissiers</div>
            </div>
            <div class="stat-card">
                <div class="value"><?= $stats['actifs'] ?></div>
                <div class="label">Actifs</div>
            </div>
            <div class="stat-card">
                <div class="value"><?= $stats['inactifs'] ?></div>
                <div class="label">Inactifs</div>
            </div>
        </div>
        
        <!-- Content Card -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Liste des utilisateurs</h3>
                <button class="btn btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Nouvel utilisateur
                </button>
            </div>
            <div class="table-container">
                <table id="usersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Utilisateur</th>
                            <th>Nom complet</th>
                            <th>Rôle</th>
                            <th>Succursale</th>
                            <th>Contact</th>
                            <th>Statut</th>
                            <th>Dernière connexion</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>#<?= $user['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($user['username']) ?></strong><br>
                                <small style="color: #64748b;"><?= htmlspecialchars($user['email'] ?: '—') ?></small>
                            </td>
                            <td><?= htmlspecialchars($user['nom_complet']) ?></td>
                            <td>
                                <?php
                                $roleBadge = [
                                    'admin' => 'badge-admin',
                                    'secretaire' => 'badge-secretaire',
                                    'caissier' => 'badge-caissier'
                                ];
                                $roleIcon = [
                                    'admin' => 'crown',
                                    'secretaire' => 'user-tie',
                                    'caissier' => 'cash-register'
                                ];
                                ?>
                                <span class="badge <?= $roleBadge[$user['role']] ?>">
                                    <i class="fas fa-<?= $roleIcon[$user['role']] ?>"></i>
                                    <?= ucfirst($user['role']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($user['succursale_code']) ?></td>
                            <td><?= htmlspecialchars($user['telephone'] ?: '—') ?></td>
                            <td>
                                <span class="badge <?= $user['actif'] ? 'badge-success' : 'badge-danger' ?>">
                                    <i class="fas fa-<?= $user['actif'] ? 'check-circle' : 'times-circle' ?>"></i>
                                    <?= $user['actif'] ? 'Actif' : 'Inactif' ?>
                                </span>
                            </td>
                            <td>
                                <?= $user['derniere_connexion'] ? date('d/m/Y H:i', strtotime($user['derniere_connexion'])) : 'Jamais' ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn edit" onclick='openEditModal(<?= json_encode($user) ?>)' title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn reset" onclick='openResetModal(<?= $user["id"] ?>, "<?= htmlspecialchars($user["username"]) ?>")' title="Réinitialiser mot de passe">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    <button class="action-btn toggle" onclick='toggleUser(<?= $user["id"] ?>, <?= $user["actif"] ?>)' title="<?= $user['actif'] ? 'Désactiver' : 'Activer' ?>">
                                        <i class="fas fa-<?= $user['actif'] ? 'ban' : 'check' ?>"></i>
                                    </button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <button class="action-btn delete" onclick='deleteUser(<?= $user["id"] ?>, "<?= htmlspecialchars($user["username"]) ?>")' title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal Ajout -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <form method="post" id="addForm">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h3><i class="fas fa-user-plus"></i> Nouvel utilisateur</h3>
                    <button type="button" class="modal-close" onclick="closeModal('addModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label><i class="fas fa-building"></i> Succursale *</label>
                        <select name="succursale_id" class="form-control" required>
                            <option value="">Sélectionner...</option>
                            <?php foreach ($succursales as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['code'] . ' - ' . $s['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Nom complet *</label>
                        <input type="text" name="nom_complet" class="form-control" placeholder="Ex: Pierre Antoine" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-at"></i> Nom d'utilisateur *</label>
                        <input type="text" name="username" class="form-control" placeholder="Ex: Pierre.Antoine" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Mot de passe *</label>
                        <input type="text" name="password" class="form-control" id="addPassword" placeholder="Mot de passe" required>
                        <div class="password-strength">
                            <div class="password-strength-bar" id="passwordStrengthBar"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Rôle *</label>
                        <select name="role" class="form-control" required>
                            <option value="">Sélectionner...</option>
                            <option value="admin">Administrateur</option>
                            <option value="secretaire">Secrétaire</option>
                            <option value="caissier">Caissier</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" name="email" class="form-control" placeholder="Pierre.Antoine@spillico.ht">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Téléphone</label>
                        <input type="text" name="telephone" class="form-control" placeholder="+509 XXXX-XXXX">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Modification -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <form method="post" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                <div class="modal-header">
                    <h3><i class="fas fa-edit"></i> Modifier l'utilisateur</h3>
                    <button type="button" class="modal-close" onclick="closeModal('editModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label><i class="fas fa-building"></i> Succursale *</label>
                        <select name="succursale_id" id="editSuccursale" class="form-control" required>
                            <?php foreach ($succursales as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['code'] . ' - ' . $s['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Nom complet *</label>
                        <input type="text" name="nom_complet" id="editNom" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-at"></i> Nom d'utilisateur *</label>
                        <input type="text" name="username" id="editUsername" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Nouveau mot de passe (laisser vide pour ne pas changer)</label>
                        <input type="text" name="password" id="editPassword" class="form-control" placeholder="Nouveau mot de passe">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Rôle *</label>
                        <select name="role" id="editRole" class="form-control" required>
                            <option value="admin">Administrateur</option>
                            <option value="secretaire">Secrétaire</option>
                            <option value="caissier">Caissier</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" name="email" id="editEmail" class="form-control">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Téléphone</label>
                        <input type="text" name="telephone" id="editTelephone" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Mettre à jour</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Réinitialisation mot de passe -->
    <div class="modal" id="resetModal">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="id" id="resetId">
                <div class="modal-header">
                    <h3><i class="fas fa-key"></i> Réinitialiser le mot de passe</h3>
                    <button type="button" class="modal-close" onclick="closeModal('resetModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Réinitialisation du mot de passe pour : <strong id="resetUsername"></strong></p>
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Nouveau mot de passe *</label>
                        <input type="text" name="new_password" class="form-control" placeholder="Nouveau mot de passe" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('resetModal')">Annuler</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-key"></i> Réinitialiser</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Formulaires cachés pour actions -->
    <form method="post" id="toggleForm" style="display: none;">
        <input type="hidden" name="action" value="toggle">
        <input type="hidden" name="id" id="toggleId">
        <input type="hidden" name="actif" id="toggleActif">
    </form>
    
    <form method="post" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
    </form>
    
    <script>
        // Ouvrir modal ajout
        function openAddModal() {
            document.getElementById('addModal').classList.add('show');
            document.getElementById('addForm').reset();
        }
        
        // Ouvrir modal modification
        function openEditModal(user) {
            document.getElementById('editId').value = user.id;
            document.getElementById('editSuccursale').value = user.succursale_id;
            document.getElementById('editNom').value = user.nom_complet;
            document.getElementById('editUsername').value = user.username;
            document.getElementById('editRole').value = user.role;
            document.getElementById('editEmail').value = user.email || '';
            document.getElementById('editTelephone').value = user.telephone || '';
            document.getElementById('editPassword').value = '';
            document.getElementById('editModal').classList.add('show');
        }
        
        // Ouvrir modal reset password
        function openResetModal(id, username) {
            document.getElementById('resetId').value = id;
            document.getElementById('resetUsername').textContent = username;
            document.getElementById('resetModal').classList.add('show');
        }
        
        // Fermer modal
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }
        
        // Activer/Désactiver utilisateur
        function toggleUser(id, currentStatus) {
            if (confirm('Voulez-vous ' + (currentStatus ? 'désactiver' : 'activer') + ' cet utilisateur ?')) {
                document.getElementById('toggleId').value = id;
                document.getElementById('toggleActif').value = currentStatus ? 0 : 1;
                document.getElementById('toggleForm').submit();
            }
        }
        
        // Supprimer utilisateur
        function deleteUser(id, username) {
            if (confirm('Êtes-vous sûr de vouloir supprimer l\'utilisateur "' + username + '" ? Cette action est irréversible.')) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
        
        // Fermer modal en cliquant à l'extérieur
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                }
            });
        });
        
        // Recherche dans le tableau
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#usersTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        // Force du mot de passe
        document.getElementById('addPassword').addEventListener('input', function() {
            const password = this.value;
            const bar = document.getElementById('passwordStrengthBar');
            
            bar.className = 'password-strength-bar';
            if (password.length >= 8) {
                bar.classList.add('strength-strong');
            } else if (password.length >= 5) {
                bar.classList.add('strength-medium');
            } else if (password.length > 0) {
                bar.classList.add('strength-weak');
            }
        });
        
        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
        
        // Afficher modal si erreur après soumission
        <?php if ($error && strpos($error, 'ajout') !== false): ?>
        document.getElementById('addModal').classList.add('show');
        <?php endif; ?>
    </script>
</body>
</html>