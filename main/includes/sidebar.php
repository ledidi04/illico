<?php
/**
 * sidebar.php — Barre latérale commune à toutes les pages
 * Inclure APRÈS session_start() et vérification de rôle.
 * Variable requise : $currentPage (ex: 'dashboard')
 */

// Éviter l'erreur si $currentPage n'est pas définie
$currentPage = $currentPage ?? '';

$role = $_SESSION['role'] ?? '';
$nom_complet = $_SESSION['nom_complet'] ?? 'Utilisateur';
$succursale_nom = $_SESSION['succursale_nom'] ?? '';

// Couleur de l'avatar selon le rôle
$avatarColor = match($role) {
    'admin'      => '#ef4444',
    'secretaire' => '#10b981',
    'caissier'   => '#3b82f6',
    default      => '#64748b',
};
$roleLabel = match($role) {
    'admin'      => 'Administrateur',
    'secretaire' => 'Secrétaire',
    'caissier'   => 'Caissier',
    default      => ucfirst($role),
};

// Définition du menu selon le rôle
$menuItems = [
    ['href' => 'dashboard.php',      'icon' => 'fa-gauge',       'label' => 'Tableau de bord', 'key' => 'dashboard',       'roles' => ['admin','secretaire','caissier']],
    ['href' => 'creer_compte.php',   'icon' => 'fa-user-plus',   'label' => 'Créer compte',    'key' => 'creer_compte',    'roles' => ['admin','secretaire']],
    ['href' => 'liste_clients.php',  'icon' => 'fa-users',       'label' => 'Liste clients',   'key' => 'liste_clients',   'roles' => ['admin','secretaire']],
    ['href' => 'ajouter_client.php', 'icon' => 'fa-user',        'label' => 'Ajouter client',  'key' => 'ajouter_client',  'roles' => ['admin','secretaire']],
    'divider',
    ['href' => 'depot.php',          'icon' => 'fa-arrow-down',  'label' => 'Dépôt',           'key' => 'depot',           'roles' => ['admin','secretaire','caissier']],
    ['href' => 'retrait.php',        'icon' => 'fa-arrow-up',    'label' => 'Retrait',         'key' => 'retrait',         'roles' => ['admin','secretaire','caissier']],
    ['href' => 'verification.php',   'icon' => 'fa-search',      'label' => 'Vérification',    'key' => 'verification',    'roles' => ['admin','secretaire','caissier']],
];

// Menu admin uniquement
if ($role === 'admin') {
    $menuItems[] = 'divider';
    $menuItems[] = ['href' => 'gestion_utilisateurs.php', 'icon' => 'fa-users-gear', 'label' => 'Utilisateurs', 'key' => 'gestion_utilisateurs', 'roles' => ['admin']];
    $menuItems[] = ['href' => 'rapports.php', 'icon' => 'fa-chart-bar', 'label' => 'Rapports', 'key' => 'rapports', 'roles' => ['admin']];
}

$menuItems[] = 'divider';
$menuItems[] = ['href' => '../logout.php', 'icon' => 'fa-sign-out-alt', 'label' => 'Déconnexion', 'key' => 'logout', 'roles' => ['admin','secretaire','caissier']];
?>
<!-- Overlay pour fermer le sidebar sur mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">
    <!-- Bouton de fermeture pour mobile -->
    <button class="sidebar-close" id="sidebarClose">&times;</button>
    
    <div class="sidebar-header">
        <h2><i class="fas fa-building-columns"></i> <span>S&amp;P illico</span></h2>
        <p>Banque Communautaire</p>
    </div>
    <div class="user-info-side">
        <div class="avatar" style="background: <?= $avatarColor ?>;">
            <i class="fas fa-user-tie"></i>
        </div>
        <div class="name"><?= htmlspecialchars($nom_complet) ?></div>
        <div class="role" style="color: <?= $avatarColor ?>;"><i class="fas fa-shield"></i> <?= $roleLabel ?></div>
        <?php if ($succursale_nom): ?>
        <div class="succursale"><i class="fas fa-building"></i> <?= htmlspecialchars($succursale_nom) ?></div>
        <?php endif; ?>
    </div>
    <nav class="nav-menu">
        <?php foreach ($menuItems as $item): ?>
            <?php if ($item === 'divider'): ?>
            <div class="nav-divider"></div>
            <?php elseif (in_array($role, $item['roles'])): ?>
            <a href="<?= $item['href'] ?>" class="nav-item <?= ($currentPage === $item['key']) ? 'active' : '' ?>">
                <i class="fas <?= $item['icon'] ?>"></i>
                <span><?= $item['label'] ?></span>
            </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
</div>

<script>
// Gestion du sidebar responsive
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    // Fonction pour ouvrir le sidebar
    window.openSidebar = function() {
        sidebar.classList.add('open');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    };
    
    // Fonction pour fermer le sidebar
    window.closeSidebar = function() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    };
    
    // Fermer avec le bouton de fermeture
    const closeBtn = document.getElementById('sidebarClose');
    if (closeBtn) {
        closeBtn.addEventListener('click', closeSidebar);
    }
    
    // Fermer avec l'overlay
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }
    
    // Fermer avec la touche Echap
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });
});
</script>