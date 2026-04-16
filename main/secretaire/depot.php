<?php
require_once '../config/connexion.php';
session_start();

// Vérifier l'authentification et le rôle (caissier ou secretaire)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['caissier', 'secretaire', 'admin'])) {
    header('Location: ../index.php');
    exit;
}

$message = '';
$error = '';
$compte_info = null;

// Traitement du dépôt
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_compte = trim($_POST['id_compte'] ?? '');
    $montant = floatval($_POST['montant'] ?? 0);
    $description = trim($_POST['description'] ?? 'Dépôt en espèces');
    
    if (empty($id_compte)) {
        $error = "Veuillez saisir un numéro de compte.";
    } elseif ($montant <= 0) {
        $error = "Le montant doit être supérieur à 0.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Vérifier le compte
            $stmt = $pdo->prepare("
                SELECT c.*, tc.nom as type_compte, 
                       CONCAT(cl.nom, ' ', cl.prenom) as titulaire,
                       cl.id_client, cl.telephone
                FROM comptes c
                JOIN types_comptes tc ON c.type_compte_id = tc.id
                JOIN clients cl ON c.titulaire_principal_id = cl.id
                WHERE c.id_compte = ? AND c.statut = 'actif'
                FOR UPDATE
            ");
            $stmt->execute([$id_compte]);
            $compte = $stmt->fetch();
            
            if (!$compte) {
                throw new Exception("Compte introuvable ou inactif.");
            }
            
            // Calculer le nouveau solde
            $ancien_solde = $compte['solde'];
            $nouveau_solde = $ancien_solde + $montant;
            
            // Mettre à jour le solde
            $stmt = $pdo->prepare("UPDATE comptes SET solde = ? WHERE id = ?");
            $stmt->execute([$nouveau_solde, $compte['id']]);
            
            // Enregistrer la transaction
            $stmt = $pdo->prepare("
                INSERT INTO transactions (compte_id, utilisateur_id, succursale_id, type, montant, solde_avant, solde_apres, description)
                VALUES (?, ?, ?, 'depot', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $compte['id'],
                $_SESSION['user_id'],
                $_SESSION['succursale_id'],
                $montant,
                $ancien_solde,
                $nouveau_solde,
                $description
            ]);
            
            $pdo->commit();
            
            $message = "Dépôt de " . number_format($montant, 2, ',', ' ') . " HTG effectué avec succès.";
            $compte_info = [
                'id_compte' => $compte['id_compte'],
                'titulaire' => $compte['titulaire'],
                'type_compte' => $compte['type_compte'],
                'ancien_solde' => $ancien_solde,
                'nouveau_solde' => $nouveau_solde,
                'montant' => $montant
            ];
            
            // Log de l'action
            $logStmt = $pdo->prepare("
                INSERT INTO logs_activites (utilisateur_id, action, details, ip_address)
                VALUES (?, 'depot', ?, ?)
            ");
            $logStmt->execute([$_SESSION['user_id'], "Dépôt de $montant HTG sur compte $id_compte", $_SERVER['REMOTE_ADDR']]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Erreur : " . $e->getMessage();
        }
    }
}

$currentPage = 'depot';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dépôt - S&P illico</title>
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
        .user-info-side .role { color: #3b82f6; font-size: 13px; }
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
        
        .card { background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 30px; max-width: 600px; margin: 0 auto; }
        .card-header { text-align: center; margin-bottom: 30px; }
        .card-header i { font-size: 48px; color: #16a34a; background: #dcfce7; padding: 16px; border-radius: 50%; margin-bottom: 16px; }
        .card-header h2 { color: #1e293b; margin-bottom: 8px; }
        
        .alert { padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
        .alert-success { background: #dcfce7; color: #166534; border-left: 4px solid #16a34a; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #475569; font-size: 14px; font-weight: 500; }
        .form-group label i { margin-right: 8px; color: #3b82f6; }
        .form-control { width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 16px; }
        .form-control:focus { outline: none; border-color: #3b82f6; }
        
        .btn { padding: 14px 24px; border-radius: 12px; font-size: 16px; font-weight: 600; cursor: pointer; border: none; width: 100%; }
        .btn-primary { background: #16a34a; color: white; }
        .btn-primary:hover { background: #15803d; }
        .btn-secondary { background: #64748b; color: white; }
        
        .result-card { background: #f8fafc; border-radius: 12px; padding: 20px; margin-top: 20px; }
        .result-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e2e8f0; }
        .result-row:last-child { border-bottom: none; }
        .result-label { color: #64748b; }
        .result-value { font-weight: 600; color: #1e293b; }
        .result-value.highlight { color: #16a34a; font-size: 18px; }
        
        .recherche-rapide { margin-bottom: 20px; }
        .recherche-rapide input { width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 14px; }
        
        .btn-imprimer { background: #3b82f6; color: white; padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-building-columns"></i> S&P illico</h2>
            <p>Banque Communautaire</p>
        </div>
        <div class="user-info-side">
            <div class="avatar"><i class="fas fa-user"></i></div>
            <div class="name"><?= htmlspecialchars($_SESSION['nom_complet']) ?></div>
            <div class="role"><i class="fas fa-shield"></i> <?= ucfirst($_SESSION['role']) ?></div>
        </div>
        <nav class="nav-menu">
            <a href="<?= $_SESSION['role'] == 'secretaire' ? 'dashboard.php' : '../admin/dashboard.php' ?>" class="nav-item">
                <i class="fas fa-gauge"></i> Tableau de bord
            </a>
            <a href="depot.php" class="nav-item active"><i class="fas fa-arrow-down"></i> Dépôt</a>
            <a href="retrait.php" class="nav-item"><i class="fas fa-arrow-up"></i> Retrait</a>
            <a href="verification.php" class="nav-item"><i class="fas fa-search"></i> Vérification</a>
            <div class="nav-divider"></div>
            <a href="../logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>Dépôt</h1>
                <div class="breadcrumb"><a href="dashboard.php">Accueil</a> / Dépôt</div>
            </div>
            <div>
                <span style="margin-right: 15px; color: #64748b;">
                    <i class="fas fa-building"></i> <?= htmlspecialchars($_SESSION['succursale_nom']) ?>
                </span>
                <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-arrow-down"></i>
                <h2>Effectuer un dépôt</h2>
                <p style="color: #64748b;">Saisissez les informations du dépôt</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            
            <?php if (!$compte_info): ?>
            <form method="post" id="depotForm">
                <div class="form-group">
                    <label for="id_compte"><i class="fas fa-credit-card"></i> Numéro de compte *</label>
                    <input type="text" id="id_compte" name="id_compte" class="form-control" 
                           placeholder="Ex: 00001" required autofocus maxlength="5">
                    <small style="color: #64748b; display: block; margin-top: 5px;">
                        <i class="fas fa-info-circle"></i> Format: 5 chiffres
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="montant"><i class="fas fa-money-bill"></i> Montant (HTG) *</label>
                    <input type="number" id="montant" name="montant" class="form-control" 
                           placeholder="0.00" step="0.01" min="0.01" required>
                </div>
                
                <div class="form-group">
                    <label for="description"><i class="fas fa-pen"></i> Description (optionnel)</label>
                    <input type="text" id="description" name="description" class="form-control" 
                           placeholder="Dépôt en espèces" value="Dépôt en espèces">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check-circle"></i> Valider le dépôt
                </button>
            </form>
            <?php else: ?>
            <div class="result-card">
                <h3 style="margin-bottom: 20px; color: #1e293b;"><i class="fas fa-check-circle" style="color: #16a34a;"></i> Dépôt effectué avec succès</h3>
                <div class="result-row">
                    <span class="result-label">Compte</span>
                    <span class="result-value"><?= htmlspecialchars($compte_info['id_compte']) ?></span>
                </div>
                <div class="result-row">
                    <span class="result-label">Titulaire</span>
                    <span class="result-value"><?= htmlspecialchars($compte_info['titulaire']) ?></span>
                </div>
                <div class="result-row">
                    <span class="result-label">Type de compte</span>
                    <span class="result-value"><?= htmlspecialchars($compte_info['type_compte']) ?></span>
                </div>
                <div class="result-row">
                    <span class="result-label">Ancien solde</span>
                    <span class="result-value"><?= number_format($compte_info['ancien_solde'], 2, ',', ' ') ?> HTG</span>
                </div>
                <div class="result-row">
                    <span class="result-label">Montant déposé</span>
                    <span class="result-value" style="color: #16a34a;">+ <?= number_format($compte_info['montant'], 2, ',', ' ') ?> HTG</span>
                </div>
                <div class="result-row">
                    <span class="result-label">Nouveau solde</span>
                    <span class="result-value highlight"><?= number_format($compte_info['nouveau_solde'], 2, ',', ' ') ?> HTG</span>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button class="btn btn-primary" onclick="window.print()" style="flex: 1;">
                        <i class="fas fa-print"></i> Imprimer le reçu
                    </button>
                    <a href="depot.php" class="btn btn-secondary" style="flex: 1; text-align: center; text-decoration: none;">
                        <i class="fas fa-plus"></i> Nouveau dépôt
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Validation du formulaire
        document.getElementById('depotForm')?.addEventListener('submit', function(e) {
            const idCompte = document.getElementById('id_compte').value;
            const montant = document.getElementById('montant').value;
            
            if (!/^\d{5}$/.test(idCompte)) {
                e.preventDefault();
                alert('Le numéro de compte doit contenir exactement 5 chiffres.');
                return false;
            }
            
            if (parseFloat(montant) <= 0) {
                e.preventDefault();
                alert('Le montant doit être supérieur à 0.');
                return false;
            }
            
            if (confirm('Confirmer le dépôt de ' + parseFloat(montant).toLocaleString('fr-FR') + ' HTG sur le compte ' + idCompte + ' ?')) {
                return true;
            }
            e.preventDefault();
            return false;
        });
        
        // Formatage du numéro de compte
        document.getElementById('id_compte')?.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').substring(0, 5);
        });
    </script>
</body>
</html>