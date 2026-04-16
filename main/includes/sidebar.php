<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$userRole = $_SESSION['role'] ?? '';
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo-container">
            <img src="<?= APP_URL ?>/assets/img/logo.png" alt="S&P illico" class="logo">
            <span class="logo-text">S&P illico</span>
        </div>
        <div class="succursale-badge">
            <i class="fas fa-building"></i>
            <span><?= htmlspecialchars($currentUser['succursale_nom'] ?? 'Succursale') ?></span>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <!-- Dashboard (commun à tous) -->
        <div class="nav-section">
            <a href="<?= APP_URL ?>/dashboard.php" class="nav-item <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Tableau de bord</span>
            </a>
        </div>
        
        <?php if ($userRole == 'admin'): ?>
        <!-- Menu Administrateur -->
        <div class="nav-section">
            <div class="nav-section-title">Administration</div>
            <a href="<?= APP_URL ?>/admin/utilisateurs.php" class="nav-item <?= strpos($currentPage, 'utilisateurs') !== false ? 'active' : '' ?>">
                <i class="fas fa-users-cog"></i>
                <span>Gestion des utilisateurs</span>
            </a>
            <a href="<?= APP_URL ?>/admin/succursales.php" class="nav-item <?= strpos($currentPage, 'succursales') !== false ? 'active' : '' ?>">
                <i class="fas fa-building"></i>
                <span>Succursales</span>
            </a>
            <a href="<?= APP_URL ?>/admin/rapports.php" class="nav-item">
                <i class="fas fa-chart-bar"></i>
                <span>Rapports avancés</span>
            </a>
            <a href="<?= APP_URL ?>/admin/logs.php" class="nav-item">
                <i class="fas fa-history"></i>
                <span>Journal d'activités</span>
            </a>
            <a href="<?= APP_URL ?>/admin/parametres.php" class="nav-item">
                <i class="fas fa-sliders-h"></i>
                <span>Paramètres système</span>
            </a>
        </div>
        <?php endif; ?>
        
        <?php if (in_array($userRole, ['admin', 'secretaire'])): ?>
        <!-- Menu Secrétaire -->
        <div class="nav-section">
            <div class="nav-section-title">Gestion des clients</div>
            <a href="<?= APP_URL ?>/secretaire/creer_compte.php" class="nav-item <?= $currentPage == 'creer_compte.php' ? 'active' : '' ?>">
                <i class="fas fa-user-plus"></i>
                <span>Nouveau compte</span>
            </a>
            <a href="<?= APP_URL ?>/secretaire/ajouter_client.php" class="nav-item <?= $currentPage == 'ajouter_client.php' ? 'active' : '' ?>">
                <i class="fas fa-user"></i>
                <span>Ajouter client</span>
            </a>
            <a href="<?= APP_URL ?>/secretaire/liste_clients.php" class="nav-item <?= $currentPage == 'liste_clients.php' ? 'active' : '' ?>">
                <i class="fas fa-users"></i>
                <span>Liste des clients</span>
            </a>
            <a href="<?= APP_URL ?>/secretaire/modifier_compte.php" class="nav-item">
                <i class="fas fa-edit"></i>
                <span>Modifier compte</span>
            </a>
        </div>
        <?php endif; ?>
        
        <?php if (in_array($userRole, ['admin', 'secretaire', 'caissier'])): ?>
        <!-- Menu Opérations (commun aux secrétaires et caissiers) -->
        <div class="nav-section">
            <div class="nav-section-title">Opérations bancaires</div>
            <?php 
            $opsPath = ($userRole == 'caissier') ? 'caissier' : 'secretaire';
            ?>
            <a href="<?= APP_URL ?>/<?= $opsPath ?>/depot.php" class="nav-item <?= $currentPage == 'depot.php' ? 'active' : '' ?>">
                <i class="fas fa-arrow-down"></i>
                <span>Dépôt</span>
            </a>
            <a href="<?= APP_URL ?>/<?= $opsPath ?>/retrait.php" class="nav-item <?= $currentPage == 'retrait.php' ? 'active' : '' ?>">
                <i class="fas fa-arrow-up"></i>
                <span>Retrait</span>
            </a>
            <a href="<?= APP_URL ?>/<?= $opsPath ?>/verification.php" class="nav-item <?= $currentPage == 'verification.php' ? 'active' : '' ?>">
                <i class="fas fa-search"></i>
                <span>Vérification compte</span>
            </a>
            <a href="<?= APP_URL ?>/commun/virements.php" class="nav-item">
                <i class="fas fa-exchange-alt"></i>
                <span>Virements</span>
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Menu commun -->
        <div class="nav-section">
            <div class="nav-section-title">Outils</div>
            <a href="<?= APP_URL ?>/commun/recherche.php" class="nav-item <?= $currentPage == 'recherche.php' ? 'active' : '' ?>">
                <i class="fas fa-search"></i>
                <span>Recherche avancée</span>
            </a>
            <a href="<?= APP_URL ?>/commun/statistiques.php" class="nav-item">
                <i class="fas fa-chart-pie"></i>
                <span>Statistiques</span>
            </a>
            <a href="<?= APP_URL ?>/aide.php" class="nav-item">
                <i class="fas fa-question-circle"></i>
                <span>Aide & Support</span>
            </a>
        </div>
    </nav>
    
    <div class="sidebar-footer">
        <div class="system-status">
            <i class="fas fa-circle online"></i>
            <span>Système opérationnel</span>
        </div>
        <div class="sidebar-version">
            v<?= APP_VERSION ?>
        </div>
    </div>
</aside>