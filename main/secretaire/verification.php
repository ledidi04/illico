<?php
require_once '../config/connexion.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['caissier', 'secretaire', 'admin'])) {
    header('Location: ../index.php');
    exit;
}

$compte = null;
$cotitulaires = [];
$transactions = [];
$error = '';
$search = $_GET['search'] ?? '';

if (!empty($search)) {
    try {
        // Recherche du compte
        $stmt = $pdo->prepare("
            SELECT c.*, tc.nom as type_compte, tc.taux_interet, tc.solde_minimum,
                   s.code as succursale_code, s.nom as succursale_nom,
                   CONCAT(cl.nom, ' ', cl.prenom) as titulaire,
                   cl.id_client, cl.telephone, cl.email, cl.adresse, cl.photo,
                   cl.date_naissance, cl.lieu_naissance
            FROM comptes c
            JOIN types_comptes tc ON c.type_compte_id = tc.id
            JOIN succursales s ON c.succursale_id = s.id
            JOIN clients cl ON c.titulaire_principal_id = cl.id
            WHERE c.id_compte = ? OR cl.id_client = ? OR cl.telephone LIKE ?
        ");
        $searchTerm = "%$search%";
        $stmt->execute([$search, $search, $searchTerm]);
        $compte = $stmt->fetch();
        
        if ($compte) {
            // Récupérer les co-titulaires
            $stmt = $pdo->prepare("
                SELECT cl.* FROM clients cl
                JOIN compte_cotitulaires cc ON cl.id = cc.client_id
                WHERE cc.compte_id = ?
            ");
            $stmt->execute([$compte['id']]);
            $cotitulaires = $stmt->fetchAll();
            
            // Récupérer les 10 dernières transactions
            $stmt = $pdo->prepare("
                SELECT t.*, u.username as operateur
                FROM transactions t
                JOIN utilisateurs u ON t.utilisateur_id = u.id
                WHERE t.compte_id = ?
                ORDER BY t.date_transaction DESC
                LIMIT 10
            ");
            $stmt->execute([$compte['id']]);
            $transactions = $stmt->fetchAll();
        } elseif (strlen($search) >= 3) {
            $error = "Aucun compte trouvé avec ce numéro de compte, NIF/CINU ou téléphone.";
        }
    } catch (PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

$currentPage = 'verification';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification de compte - S&P illico</title>
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
        
        .search-card { background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 30px; margin-bottom: 24px; }
        .search-form { display: flex; gap: 15px; }
        .search-input { flex: 1; padding: 16px 20px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 16px; }
        .search-input:focus { outline: none; border-color: #3b82f6; }
        .search-btn { padding: 16px 30px; background: #3b82f6; color: white; border: none; border-radius: 12px; font-size: 16px; font-weight: 600; cursor: pointer; }
        .search-btn:hover { background: #2563eb; }
        
        .alert-error { background: #fee2e2; color: #991b1b; padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; border-left: 4px solid #ef4444; }
        
        .compte-details { background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
        .compte-header { background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color: white; padding: 30px; }
        .compte-header .numero { font-size: 32px; font-weight: 700; margin-bottom: 8px; }
        .compte-header .titulaire { font-size: 20px; opacity: 0.95; }
        .compte-header .solde { font-size: 36px; font-weight: 700; margin-top: 15px; }
        
        .compte-body { padding: 30px; }
        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px; }
        .info-item { padding: 15px; background: #f8fafc; border-radius: 10px; }
        .info-label { color: #64748b; font-size: 13px; margin-bottom: 5px; }
        .info-value { color: #1e293b; font-size: 16px; font-weight: 500; }
        
        .section-title { font-size: 18px; color: #1e293b; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px 0; color: #64748b; font-size: 12px; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
        td { padding: 12px 0; border-bottom: 1px solid #e2e8f0; }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-actif { background: #dcfce7; color: #166534; }
        .badge-bloque { background: #fee2e2; color: #991b1b; }
        
        .cotitulaire-item { display: flex; align-items: center; gap: 10px; padding: 10px; background: #f8fafc; border-radius: 8px; margin-bottom: 8px; }
        
        .btn-print { background: #3b82f6; color: white; padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; margin-left: 15px; }
        
        @media print {
            .sidebar, .top-bar, .search-card, .btn-print { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 10px !important; }
            body { background: white !important; }
        }
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
            <a href="<?= $_SESSION['role'] == 'caissier' ? 'dashboard.php' : '../admin/dashboard.php' ?>" class="nav-item">
                <i class="fas fa-gauge"></i> Tableau de bord
            </a>
            <a href="depot.php" class="nav-item"><i class="fas fa-arrow-down"></i> Dépôt</a>
            <a href="retrait.php" class="nav-item"><i class="fas fa-arrow-up"></i> Retrait</a>
            <a href="verification.php" class="nav-item active"><i class="fas fa-search"></i> Vérification</a>
            <div class="nav-divider"></div>
            <a href="../logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>Vérification de compte</h1>
                <div class="breadcrumb"><a href="dashboard.php">Accueil</a> / Vérification</div>
            </div>
            <div>
                <span style="margin-right: 15px; color: #64748b;">
                    <i class="fas fa-building"></i> <?= htmlspecialchars($_SESSION['succursale_nom']) ?>
                </span>
                <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
            </div>
        </div>
        
        <div class="search-card">
            <form method="get" class="search-form">
                <input type="text" name="search" class="search-input" 
                       placeholder="N° de compte, NIF, CINU ou téléphone..." 
                       value="<?= htmlspecialchars($search) ?>" required>
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i> Rechercher
                </button>
            </form>
            <p style="margin-top: 10px; color: #64748b; font-size: 13px;">
                <i class="fas fa-info-circle"></i> 
                Recherchez par numéro de compte (5 chiffres), NIF/CINU (XXX-XXX-XXX-X) ou numéro de téléphone
            </p>
        </div>
        
        <?php if ($error): ?>
        <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($compte): ?>
        <div class="compte-details">
            <div class="compte-header">
                <div class="numero">Compte N° <?= htmlspecialchars($compte['id_compte']) ?></div>
                <div class="titulaire">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($compte['titulaire']) ?>
                    <span style="margin-left: 20px; font-size: 14px;">
                        <i class="fas fa-id-card"></i> <?= htmlspecialchars($compte['id_client']) ?>
                    </span>
                </div>
                <div class="solde">
                    <?= number_format($compte['solde'], 2, ',', ' ') ?> HTG
                </div>
                <div style="margin-top: 10px;">
                    <span class="badge <?= $compte['statut'] == 'actif' ? 'badge-actif' : 'badge-bloque' ?>" style="font-size: 14px; padding: 6px 15px;">
                        <i class="fas fa-<?= $compte['statut'] == 'actif' ? 'check-circle' : 'ban' ?>"></i>
                        <?= $compte['statut'] == 'actif' ? 'Compte actif' : 'Compte bloqué' ?>
                    </span>
                    <button class="btn-print" onclick="window.print()">
                        <i class="fas fa-print"></i> Imprimer
                    </button>
                </div>
            </div>
            
            <div class="compte-body">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-credit-card"></i> Type de compte</div>
                        <div class="info-value"><?= htmlspecialchars($compte['type_compte']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-calendar"></i> Date de création</div>
                        <div class="info-value"><?= date('d/m/Y', strtotime($compte['date_creation'])) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-building"></i> Succursale</div>
                        <div class="info-value"><?= htmlspecialchars($compte['succursale_code'] . ' - ' . $compte['succursale_nom']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-percent"></i> Taux d'intérêt</div>
                        <div class="info-value"><?= $compte['taux_interet'] ?>%</div>
                    </div>
                </div>
                
                <div style="margin-bottom: 30px;">
                    <div class="section-title"><i class="fas fa-user-circle"></i> Informations du titulaire</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Nom complet</div>
                            <div class="info-value"><?= htmlspecialchars($compte['titulaire']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">NIF/CINU</div>
                            <div class="info-value"><?= htmlspecialchars($compte['id_client']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Téléphone</div>
                            <div class="info-value"><?= htmlspecialchars($compte['telephone'] ?: 'Non renseigné') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?= htmlspecialchars($compte['email'] ?: 'Non renseigné') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Date de naissance</div>
                            <div class="info-value"><?= $compte['date_naissance'] ? date('d/m/Y', strtotime($compte['date_naissance'])) : 'Non renseigné' ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Lieu de naissance</div>
                            <div class="info-value"><?= htmlspecialchars($compte['lieu_naissance'] ?: 'Non renseigné') ?></div>
                        </div>
                        <div class="info-item" style="grid-column: span 2;">
                            <div class="info-label">Adresse</div>
                            <div class="info-value"><?= htmlspecialchars($compte['adresse'] ?: 'Non renseignée') ?></div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($cotitulaires)): ?>
                <div style="margin-bottom: 30px;">
                    <div class="section-title"><i class="fas fa-users"></i> Co-titulaires (<?= count($cotitulaires) ?>)</div>
                    <?php foreach ($cotitulaires as $cot): ?>
                    <div class="cotitulaire-item">
                        <i class="fas fa-user" style="color: #3b82f6;"></i>
                        <span><strong><?= htmlspecialchars($cot['prenom'] . ' ' . $cot['nom']) ?></strong></span>
                        <span style="color: #64748b; margin-left: 15px;"><?= htmlspecialchars($cot['id_client']) ?></span>
                        <span style="color: #64748b; margin-left: 15px;"><?= htmlspecialchars($cot['telephone']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div>
                    <div class="section-title"><i class="fas fa-history"></i> 10 dernières transactions</div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Montant</th>
                                    <th>Solde après</th>
                                    <th>Opérateur</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $t): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($t['date_transaction'])) ?></td>
                                    <td>
                                        <span class="badge <?= $t['type'] == 'depot' ? 'badge-success' : 'badge-danger' ?>">
                                            <i class="fas fa-<?= $t['type'] == 'depot' ? 'arrow-down' : 'arrow-up' ?>"></i>
                                            <?= ucfirst($t['type']) ?>
                                        </span>
                                    </td>
                                    <td style="color: <?= $t['type'] == 'depot' ? '#16a34a' : '#dc2626' ?>; font-weight: 600;">
                                        <?= $t['type'] == 'depot' ? '+' : '-' ?> <?= number_format($t['montant'], 2, ',', ' ') ?> HTG
                                    </td>
                                    <td><?= number_format($t['solde_apres'], 2, ',', ' ') ?> HTG</td>
                                    <td><?= htmlspecialchars($t['operateur']) ?></td>
                                    <td><?= htmlspecialchars($t['description'] ?: '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($transactions)): ?>
                                <tr><td colspan="6" style="text-align: center; padding: 30px; color: #64748b;">Aucune transaction</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php elseif (empty($search)): ?>
        <div style="text-align: center; padding: 60px; background: white; border-radius: 16px;">
            <i class="fas fa-search" style="font-size: 48px; color: #cbd5e1; margin-bottom: 20px;"></i>
            <h3 style="color: #1e293b; margin-bottom: 10px;">Recherchez un compte</h3>
            <p style="color: #64748b;">Saisissez un numéro de compte, NIF, CINU ou téléphone pour afficher les informations.</p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>