<?php
require_once '../config/connexion.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

function validerMotDePasse($password) {
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password);
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
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
        } elseif (!validerMotDePasse($password)) {
            $error = "Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule et un chiffre.";
        } else {
            try {
                $check = $pdo->prepare("SELECT id FROM utilisateurs WHERE username = ?");
                $check->execute([$username]);
                if ($check->fetch()) {
                    $error = "Ce nom d'utilisateur existe déjà.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO utilisateurs (succursale_id, nom_complet, username, password, role, email, telephone, actif) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                    $stmt->execute([$succursale_id, $nom_complet, $username, $password, $role, $email, $telephone]);
                    $message = "Utilisateur ajouté avec succès.";
                }
            } catch (PDOException $e) {
                $error = "Erreur lors de l'ajout : " . $e->getMessage();
            }
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'] ?? '';
        $succursale_id = $_POST['succursale_id'] ?? '';
        $nom_complet = trim($_POST['nom_complet'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $role = $_POST['role'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $new_password = $_POST['password'] ?? '';
        
        if (empty($id) || empty($nom_complet) || empty($username) || empty($role)) {
            $error = "Tous les champs obligatoires doivent être remplis.";
        } elseif (!empty($new_password) && !validerMotDePasse($new_password)) {
            $error = "Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule et un chiffre.";
        } else {
            try {
                $check = $pdo->prepare("SELECT id FROM utilisateurs WHERE username = ? AND id != ?");
                $check->execute([$username, $id]);
                if ($check->fetch()) {
                    $error = "Ce nom d'utilisateur existe déjà.";
                } else {
                    if (!empty($new_password)) {
                        $stmt = $pdo->prepare("UPDATE utilisateurs SET succursale_id=?, nom_complet=?, username=?, password=?, role=?, email=?, telephone=? WHERE id=?");
                        $stmt->execute([$succursale_id, $nom_complet, $username, $new_password, $role, $email, $telephone, $id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE utilisateurs SET succursale_id=?, nom_complet=?, username=?, role=?, email=?, telephone=? WHERE id=?");
                        $stmt->execute([$succursale_id, $nom_complet, $username, $role, $email, $telephone, $id]);
                    }
                    $message = "Utilisateur modifié avec succès.";
                }
            } catch (PDOException $e) {
                $error = "Erreur lors de la modification : " . $e->getMessage();
            }
        }
    } elseif ($action === 'toggle') {
        $id = $_POST['id'] ?? '';
        $actif = $_POST['actif'] ?? 0;
        try {
            $stmt = $pdo->prepare("UPDATE utilisateurs SET actif = ? WHERE id = ?");
            $stmt->execute([$actif, $id]);
            $message = $actif ? "Utilisateur activé." : "Utilisateur désactivé.";
        } catch (PDOException $e) {
            $error = "Erreur : " . $e->getMessage();
        }
    } elseif ($action === 'reset_password') {
        $id = $_POST['id'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        if (empty($new_password)) {
            $error = "Le nouveau mot de passe est requis.";
        } elseif (!validerMotDePasse($new_password)) {
            $error = "Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule et un chiffre.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE utilisateurs SET password = ? WHERE id = ?");
                $stmt->execute([$new_password, $id]);
                $message = "Mot de passe réinitialisé avec succès.";
            } catch (PDOException $e) {
                $error = "Erreur : " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
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

$users = $pdo->query("SELECT u.*, s.code as succursale_code, s.nom as succursale_nom FROM utilisateurs u JOIN succursales s ON u.succursale_id = s.id ORDER BY u.role, u.nom_complet")->fetchAll();
$succursales = $pdo->query("SELECT * FROM succursales ORDER BY code")->fetchAll();
$stats = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins, SUM(CASE WHEN role = 'secretaire' THEN 1 ELSE 0 END) as secretaires, SUM(CASE WHEN role = 'caissier' THEN 1 ELSE 0 END) as caissiers, SUM(CASE WHEN actif = 1 THEN 1 ELSE 0 END) as actifs, SUM(CASE WHEN actif = 0 THEN 1 ELSE 0 END) as inactifs FROM utilisateurs")->fetch();

$currentPage = 'gestion_utilisateurs';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs - S&P illico</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/common.css">
    <style>
        .stats-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: white; padding: 20px; border-radius: 16px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .stat-card .value { font-size: 28px; font-weight: 700; color: #1e293b; }
        .stat-card .label { color: #64748b; font-size: 13px; margin-top: 5px; }
        .badge-admin { background: #dbeafe; color: #1e40af; }
        .badge-secretaire { background: #dcfce7; color: #166534; }
        .badge-caissier { background: #fef3c7; color: #92400e; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; display: inline-flex; align-items: center; gap: 5px; }
        .action-buttons { display: flex; gap: 6px; flex-wrap: wrap; }
        .action-btn { width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; border: none; cursor: pointer; }
        .action-btn.edit { background: #dbeafe; color: #2563eb; }
        .action-btn.reset { background: #fef3c7; color: #d97706; }
        .action-btn.toggle { background: #e2e8f0; color: #475569; }
        .action-btn.delete { background: #fee2e2; color: #dc2626; }
        .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: white; border-radius: 20px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
        .modal-header { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 24px; }
        .modal-footer { padding: 16px 24px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px; }
        .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b; }
        .password-strength { margin-top: 8px; height: 4px; background: #e2e8f0; border-radius: 2px; }
        .password-strength-bar { height: 100%; border-radius: 2px; transition: width 0.3s; }
        .strength-weak { background: #ef4444; width: 25%; }
        .strength-medium { background: #f59e0b; width: 50%; }
        .strength-strong { background: #10b981; width: 100%; }
        .password-hint { font-size: 11px; color: #64748b; margin-top: 5px; }
    </style>
</head>
<body>
    <?php require_once '../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title"><h1>Gestion des Utilisateurs</h1><div class="breadcrumb"><a href="dashboard.php">Administration</a> / Utilisateurs</div></div>
            <div class="top-actions"><div class="search-box"><i class="fas fa-search"></i><input type="text" placeholder="Rechercher..." id="searchInput"></div><a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></div>
        </div>
        <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card"><div class="value"><?= $stats['total'] ?></div><div class="label">Total</div></div>
            <div class="stat-card"><div class="value"><?= $stats['admins'] ?></div><div class="label">Administrateurs</div></div>
            <div class="stat-card"><div class="value"><?= $stats['secretaires'] ?></div><div class="label">Secrétaires</div></div>
            <div class="stat-card"><div class="value"><?= $stats['caissiers'] ?></div><div class="label">Caissiers</div></div>
            <div class="stat-card"><div class="value"><?= $stats['actifs'] ?></div><div class="label">Actifs</div></div>
            <div class="stat-card"><div class="value"><?= $stats['inactifs'] ?></div><div class="label">Inactifs</div></div>
        </div>
        
        <div class="content-card"><div class="card-header"><h3>Liste des utilisateurs</h3><button class="btn btn-primary" onclick="openAddModal()"><i class="fas fa-plus"></i> Nouvel utilisateur</button></div>
        <div class="table-container"><table id="usersTable"><thead><tr><th>ID</th><th>Utilisateur</th><th>Nom complet</th><th>Rôle</th><th>Succursale</th><th>Contact</th><th>Statut</th><th>Actions</th></tr></thead>
        <tbody><?php foreach ($users as $user): ?><tr><td>#<?= $user['id'] ?></td><td><strong><?= htmlspecialchars($user['username']) ?></strong><br><small><?= htmlspecialchars($user['email'] ?: '—') ?></small></td><td><?= htmlspecialchars($user['nom_complet']) ?></td>
        <td><span class="badge badge-<?= $user['role'] ?>"><i class="fas fa-<?= $user['role'] === 'admin' ? 'crown' : ($user['role'] === 'secretaire' ? 'user-tie' : 'cash-register') ?>"></i> <?= ucfirst($user['role']) ?></span></td>
        <td><?= htmlspecialchars($user['succursale_code']) ?></td><td><?= htmlspecialchars($user['telephone'] ?: '—') ?></td>
        <td><span class="badge <?= $user['actif'] ? 'badge-success' : 'badge-danger' ?>"><i class="fas fa-<?= $user['actif'] ? 'check-circle' : 'times-circle' ?>"></i> <?= $user['actif'] ? 'Actif' : 'Inactif' ?></span></td>
        <td><div class="action-buttons"><button class="action-btn edit" onclick='openEditModal(<?= json_encode($user) ?>)'><i class="fas fa-edit"></i></button><button class="action-btn reset" onclick='openResetModal(<?= $user["id"] ?>, "<?= htmlspecialchars($user["username"]) ?>")'><i class="fas fa-key"></i></button><button class="action-btn toggle" onclick='toggleUser(<?= $user["id"] ?>, <?= $user["actif"] ?>)'><i class="fas fa-<?= $user['actif'] ? 'ban' : 'check' ?>"></i></button><?php if ($user['id'] != $_SESSION['user_id']): ?><button class="action-btn delete" onclick='deleteUser(<?= $user["id"] ?>, "<?= htmlspecialchars($user["username"]) ?>")'><i class="fas fa-trash"></i></button><?php endif; ?></div></td></tr><?php endforeach; ?></tbody></table></div></div>
    </div>
    
    <div class="modal" id="addModal"><div class="modal-content"><form method="post" id="addForm"><input type="hidden" name="action" value="add"><div class="modal-header"><h3>Nouvel utilisateur</h3><button type="button" class="modal-close" onclick="closeModal('addModal')">&times;</button></div>
    <div class="modal-body"><div class="form-group"><label>Succursale *</label><select name="succursale_id" class="form-control" required><?php foreach ($succursales as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['code'] . ' - ' . $s['nom']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Nom complet *</label><input type="text" name="nom_complet" class="form-control" required></div>
    <div class="form-group"><label>Nom d'utilisateur *</label><input type="text" name="username" class="form-control" required></div>
    <div class="form-group"><label>Mot de passe *</label><input type="text" name="password" id="addPassword" class="form-control" required><div class="password-strength"><div class="password-strength-bar" id="passwordStrengthBar"></div></div><div class="password-hint"><i class="fas fa-info-circle"></i> 8+ caractères, 1 majuscule, 1 minuscule, 1 chiffre</div></div>
    <div class="form-group"><label>Rôle *</label><select name="role" class="form-control" required><option value="">Sélectionner...</option><option value="admin">Administrateur</option><option value="secretaire">Secrétaire</option><option value="caissier">Caissier</option></select></div>
    <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control"></div>
    <div class="form-group"><label>Téléphone</label><input type="text" name="telephone" class="form-control" placeholder="+509 XXXX-XXXX"></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Annuler</button><button type="submit" class="btn btn-primary">Enregistrer</button></div></form></div></div>
    
    <div class="modal" id="editModal"><div class="modal-content"><form method="post" id="editForm"><input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="editId"><div class="modal-header"><h3>Modifier l'utilisateur</h3><button type="button" class="modal-close" onclick="closeModal('editModal')">&times;</button></div>
    <div class="modal-body"><div class="form-group"><label>Succursale *</label><select name="succursale_id" id="editSuccursale" class="form-control" required><?php foreach ($succursales as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['code'] . ' - ' . $s['nom']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Nom complet *</label><input type="text" name="nom_complet" id="editNom" class="form-control" required></div>
    <div class="form-group"><label>Nom d'utilisateur *</label><input type="text" name="username" id="editUsername" class="form-control" required></div>
    <div class="form-group"><label>Nouveau mot de passe</label><input type="text" name="password" id="editPassword" class="form-control" placeholder="Laisser vide pour ne pas changer"><div class="password-hint"><i class="fas fa-info-circle"></i> 8+ caractères, 1 majuscule, 1 minuscule, 1 chiffre</div></div>
    <div class="form-group"><label>Rôle *</label><select name="role" id="editRole" class="form-control" required><option value="admin">Administrateur</option><option value="secretaire">Secrétaire</option><option value="caissier">Caissier</option></select></div>
    <div class="form-group"><label>Email</label><input type="email" name="email" id="editEmail" class="form-control"></div>
    <div class="form-group"><label>Téléphone</label><input type="text" name="telephone" id="editTelephone" class="form-control" placeholder="+509 XXXX-XXXX"></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Annuler</button><button type="submit" class="btn btn-primary">Mettre à jour</button></div></form></div></div>
    
    <div class="modal" id="resetModal"><div class="modal-content"><form method="post"><input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" id="resetId"><div class="modal-header"><h3>Réinitialiser le mot de passe</h3><button type="button" class="modal-close" onclick="closeModal('resetModal')">&times;</button></div>
    <div class="modal-body"><p>Pour : <strong id="resetUsername"></strong></p><div class="form-group"><label>Nouveau mot de passe *</label><input type="text" name="new_password" id="resetPassword" class="form-control" required><div class="password-strength"><div class="password-strength-bar" id="resetStrengthBar"></div></div><div class="password-hint"><i class="fas fa-info-circle"></i> 8+ caractères, 1 majuscule, 1 minuscule, 1 chiffre</div></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('resetModal')">Annuler</button><button type="submit" class="btn btn-warning">Réinitialiser</button></div></form></div></div>
    
    <form method="post" id="toggleForm" style="display:none;"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" id="toggleId"><input type="hidden" name="actif" id="toggleActif"></form>
    <form method="post" id="deleteForm" style="display:none;"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" id="deleteId"></form>
    
    <script>
        function checkPasswordStrength(password, barId) {
            const bar = document.getElementById(barId);
            if (!bar) return;
            if (password.length >= 8 && /[A-Z]/.test(password) && /[a-z]/.test(password) && /\d/.test(password)) {
                bar.className = 'password-strength-bar strength-strong';
            } else if (password.length >= 5) {
                bar.className = 'password-strength-bar strength-medium';
            } else if (password.length > 0) {
                bar.className = 'password-strength-bar strength-weak';
            } else {
                bar.className = 'password-strength-bar';
            }
        }
        document.getElementById('addPassword')?.addEventListener('input', function() { checkPasswordStrength(this.value, 'passwordStrengthBar'); });
        document.getElementById('resetPassword')?.addEventListener('input', function() { checkPasswordStrength(this.value, 'resetStrengthBar'); });
        
        function openAddModal() { document.getElementById('addModal').classList.add('show'); document.getElementById('addForm').reset(); }
        function openEditModal(user) { document.getElementById('editId').value = user.id; document.getElementById('editSuccursale').value = user.succursale_id; document.getElementById('editNom').value = user.nom_complet; document.getElementById('editUsername').value = user.username; document.getElementById('editRole').value = user.role; document.getElementById('editEmail').value = user.email || ''; document.getElementById('editTelephone').value = user.telephone || ''; document.getElementById('editPassword').value = ''; document.getElementById('editModal').classList.add('show'); }
        function openResetModal(id, username) { document.getElementById('resetId').value = id; document.getElementById('resetUsername').textContent = username; document.getElementById('resetPassword').value = ''; document.getElementById('resetStrengthBar').className = 'password-strength-bar'; document.getElementById('resetModal').classList.add('show'); }
        function closeModal(modalId) { document.getElementById(modalId).classList.remove('show'); }
        function toggleUser(id, currentStatus) { if (confirm('Voulez-vous ' + (currentStatus ? 'désactiver' : 'activer') + ' cet utilisateur ?')) { document.getElementById('toggleId').value = id; document.getElementById('toggleActif').value = currentStatus ? 0 : 1; document.getElementById('toggleForm').submit(); } }
        function deleteUser(id, username) { if (confirm('Supprimer "' + username + '" ? Action irréversible.')) { document.getElementById('deleteId').value = id; document.getElementById('deleteForm').submit(); } }
        document.querySelectorAll('.modal').forEach(modal => { modal.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('show'); }); });
        document.getElementById('searchInput').addEventListener('keyup', function() { const term = this.value.toLowerCase(); document.querySelectorAll('#usersTable tbody tr').forEach(row => { row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none'; }); });
        setTimeout(() => { document.querySelectorAll('.alert').forEach(alert => { alert.style.transition = 'opacity 0.5s'; alert.style.opacity = '0'; setTimeout(() => alert.remove(), 500); }); }, 5000);
    </script>
</body>
</html>