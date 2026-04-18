<?php
require_once __DIR__ . '/auth.php';
checkAuth();
checkSessionTimeout();

$currentUser = getCurrentUser();
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta name="description" content="S&P illico - Système de gestion bancaire moderne">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= APP_URL ?>/assets/img/favicon.png">
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/dashboard.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Chart.js pour les graphiques -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
        /* Styles supplémentaires pour le responsive */
        .app-wrapper {
            display: flex;
            min-height: 100vh;
            position: relative;
        }
        
        /* Animation pour le sidebar */
        .sidebar {
            transition: transform 0.3s ease-in-out;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: 0;
                top: 0;
                height: 100vh;
                z-index: 1002;
                transform: translateX(-100%);
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 1001;
            }
            
            .sidebar-overlay.active {
                display: block;
            }
        }
        
        /* Animation des alertes */
        .alert {
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Responsive pour la top bar */
        @media (max-width: 1024px) {
            .navbar-right {
                gap: 12px;
            }
            
            .user-details {
                display: none;
            }
            
            .global-search .search-input {
                width: 200px;
            }
        }
        
        @media (max-width: 768px) {
            .navbar-left {
                gap: 10px;
            }
            
            .page-title h1 {
                font-size: 18px;
            }
            
            .breadcrumb {
                font-size: 11px;
            }
            
            .global-search {
                display: none;
            }
            
            .user-details {
                display: none;
            }
            
            .user-profile {
                margin-left: 0;
            }
            
            .top-navbar {
                padding: 12px 16px;
            }
        }
        
        @media (max-width: 480px) {
            .navbar-right {
                gap: 8px;
            }
            
            .notification-btn {
                width: 35px;
                height: 35px;
            }
            
            .user-avatar {
                width: 35px;
                height: 35px;
            }
        }
    </style>
</head>
<body>
    <!-- Overlay pour fermer le sidebar sur mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="app-wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation Bar -->
            <nav class="top-navbar">
                <div class="navbar-left">
                    <button class="sidebar-toggle" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="page-title">
                        <h1><?= htmlspecialchars($pageTitle) ?></h1>
                        <?php if (isset($breadcrumb)): ?>
                        <div class="breadcrumb">
                            <?php foreach ($breadcrumb as $index => $item): ?>
                                <?php if ($index > 0): ?> <span class="separator">/</span> <?php endif; ?>
                                <?php if (isset($item['url'])): ?>
                                    <a href="<?= $item['url'] ?>"><?= htmlspecialchars($item['label']) ?></a>
                                <?php else: ?>
                                    <span><?= htmlspecialchars($item['label']) ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="navbar-right">
                    <!-- Barre de recherche globale -->
                    <div class="global-search">
                        <form action="<?= APP_URL ?>/commun/recherche.php" method="GET" class="search-form">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" name="q" placeholder="Rechercher NIF, CINU, compte..." 
                                   class="search-input" autocomplete="off" id="globalSearch">
                            <button type="submit" class="search-btn">
                                Rechercher
                            </button>
                        </form>
                        <div class="search-suggestions" id="searchSuggestions" style="display: none;">
                            <div class="suggestions-list"></div>
                        </div>
                    </div>
                    
                    <!-- Notifications -->
                    <div class="notifications-dropdown">
                        <button class="notification-btn" id="notificationBtn">
                            <i class="fas fa-bell"></i>
                            <span class="badge" id="notificationCount">3</span>
                        </button>
                        <div class="notifications-menu" id="notificationsMenu">
                            <div class="notification-header">
                                <h3>Notifications</h3>
                                <a href="#" class="mark-all-read">Tout marquer comme lu</a>
                            </div>
                            <div class="notification-list">
                                <div class="notification-item unread">
                                    <div class="notification-icon success">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="notification-content">
                                        <p>Dépôt de 5,000 HTG - Compte 00123</p>
                                        <span class="time">Il y a 5 minutes</span>
                                    </div>
                                </div>
                                <div class="notification-item unread">
                                    <div class="notification-icon warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                    <div class="notification-content">
                                        <p>Solde bas - Compte 00456</p>
                                        <span class="time">Il y a 1 heure</span>
                                    </div>
                                </div>
                                <div class="notification-item">
                                    <div class="notification-icon info">
                                        <i class="fas fa-user-plus"></i>
                                    </div>
                                    <div class="notification-content">
                                        <p>Nouveau compte créé : 00789</p>
                                        <span class="time">Il y a 3 heures</span>
                                    </div>
                                </div>
                            </div>
                            <div class="notification-footer">
                                <a href="<?= APP_URL ?>/notifications.php">Voir toutes les notifications</a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Profil utilisateur -->
                    <div class="user-profile">
                        <div class="user-info" id="userProfileBtn">
                            <img src="<?= APP_URL ?>/assets/img/avatar.png" alt="Avatar" class="user-avatar">
                            <div class="user-details">
                                <span class="user-name"><?= htmlspecialchars($currentUser['nom_complet'] ?? 'Utilisateur') ?></span>
                                <span class="user-role">
                                    <i class="fas fa-shield-alt"></i>
                                    <?= ucfirst($currentUser['role'] ?? 'Guest') ?>
                                </span>
                            </div>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="profile-dropdown" id="profileDropdown">
                            <div class="profile-header">
                                <img src="<?= APP_URL ?>/assets/img/avatar.png" alt="Avatar" class="dropdown-avatar">
                                <div class="profile-info">
                                    <strong><?= htmlspecialchars($currentUser['nom_complet'] ?? 'Utilisateur') ?></strong>
                                    <span><?= htmlspecialchars($currentUser['email'] ?? '') ?></span>
                                </div>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a href="<?= APP_URL ?>/profil.php"><i class="fas fa-user"></i> Mon profil</a>
                            <a href="<?= APP_URL ?>/parametres.php"><i class="fas fa-cog"></i> Paramètres</a>
                            <a href="<?= APP_URL ?>/securite.php"><i class="fas fa-lock"></i> Sécurité</a>
                            <div class="dropdown-divider"></div>
                            <a href="#" id="helpBtn"><i class="fas fa-question-circle"></i> Aide & Support</a>
                            <a href="<?= APP_URL ?>/logout.php" class="logout-link">
                                <i class="fas fa-sign-out-alt"></i> Déconnexion
                            </a>
                        </div>
                    </div>
                </div>
            </nav>
            
            <!-- Contenu principal -->
            <div class="content-wrapper">
                <!-- Messages flash -->
                <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($_SESSION['success']) ?>
                    <button type="button" class="close-alert">&times;</button>
                </div>
                <?php unset($_SESSION['success']); endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <button type="button" class="close-alert">&times;</button>
                </div>
                <?php unset($_SESSION['error']); endif; ?>
                
                <?php if (isset($_SESSION['warning'])): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($_SESSION['warning']) ?>
                    <button type="button" class="close-alert">&times;</button>
                </div>
                <?php unset($_SESSION['warning']); endif; ?>
                
                <?php if (isset($_SESSION['info'])): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <?= htmlspecialchars($_SESSION['info']) ?>
                    <button type="button" class="close-alert">&times;</button>
                </div>
                <?php unset($_SESSION['info']); endif; ?>

<script>
// Gestion du sidebar responsive
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    // Fonction pour ouvrir le sidebar
    function openSidebar() {
        if (sidebar) {
            sidebar.classList.add('open');
        }
        if (sidebarOverlay) {
            sidebarOverlay.classList.add('active');
        }
        document.body.style.overflow = 'hidden';
    }
    
    // Fonction pour fermer le sidebar
    function closeSidebar() {
        if (sidebar) {
            sidebar.classList.remove('open');
        }
        if (sidebarOverlay) {
            sidebarOverlay.classList.remove('active');
        }
        document.body.style.overflow = '';
    }
    
    // Toggle sidebar (mobile seulement)
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                if (sidebar && sidebar.classList.contains('open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            }
        });
    }
    
    // Fermer avec l'overlay
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }
    
    // Fermer avec la touche Echap
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });
    
    // Réinitialiser sur redimensionnement
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768 && sidebar && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });
    
    // Gestion des notifications
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationsMenu = document.getElementById('notificationsMenu');
    
    if (notificationBtn && notificationsMenu) {
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationsMenu.classList.toggle('show');
        });
        
        // Fermer le menu en cliquant ailleurs
        document.addEventListener('click', function() {
            notificationsMenu.classList.remove('show');
        });
        
        notificationsMenu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
    
    // Gestion du profil dropdown
    const userProfileBtn = document.getElementById('userProfileBtn');
    const profileDropdown = document.getElementById('profileDropdown');
    
    if (userProfileBtn && profileDropdown) {
        userProfileBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        });
        
        document.addEventListener('click', function() {
            profileDropdown.classList.remove('show');
        });
        
        profileDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
    
    // Fermeture des alertes
    const closeButtons = document.querySelectorAll('.close-alert');
    closeButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const alert = this.closest('.alert');
            if (alert) {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    alert.remove();
                }, 300);
            }
        });
    });
    
    // Auto-fermeture des alertes après 5 secondes
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            if (alert) {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    if (alert && alert.parentNode) {
                        alert.remove();
                    }
                }, 300);
            }
        }, 5000);
    });
    
    // Recherche globale avec suggestions
    const searchInput = document.getElementById('globalSearch');
    const searchSuggestions = document.getElementById('searchSuggestions');
    
    if (searchInput && searchSuggestions) {
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                searchSuggestions.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                // Simulation de recherche AJAX
                // À remplacer par votre vraie requête AJAX
                fetch(`<?= APP_URL ?>/api/search.php?q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.length > 0) {
                            const suggestionsList = searchSuggestions.querySelector('.suggestions-list');
                            suggestionsList.innerHTML = data.map(item => `
                                <a href="${item.url}" class="suggestion-item">
                                    <i class="fas ${item.icon}"></i>
                                    <div>
                                        <strong>${item.title}</strong>
                                        <small>${item.subtitle}</small>
                                    </div>
                                </a>
                            `).join('');
                            searchSuggestions.style.display = 'block';
                        } else {
                            searchSuggestions.style.display = 'none';
                        }
                    })
                    .catch(() => {
                        searchSuggestions.style.display = 'none';
                    });
            }, 300);
        });
        
        // Cacher les suggestions en cliquant ailleurs
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchSuggestions.contains(e.target)) {
                searchSuggestions.style.display = 'none';
            }
        });
    }
});
</script>

<style>
/* Styles additionnels pour les dropdowns */
.notifications-menu.show,
.profile-dropdown.show {
    display: block !important;
    animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Style pour les suggestions de recherche */
.search-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
    margin-top: 8px;
    z-index: 1000;
    max-height: 300px;
    overflow-y: auto;
}

.suggestions-list {
    padding: 8px;
}

.suggestion-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    text-decoration: none;
    color: #1e293b;
    border-radius: 8px;
    transition: all 0.2s;
}

.suggestion-item:hover {
    background: #f1f5f9;
}

.suggestion-item i {
    width: 24px;
    color: #3b82f6;
}

.suggestion-item strong {
    display: block;
    font-size: 14px;
}

.suggestion-item small {
    font-size: 11px;
    color: #64748b;
}

/* Animation pour le sidebar toggle */
.sidebar-toggle {
    transition: transform 0.2s;
}

.sidebar-toggle:hover {
    transform: scale(1.1);
}

/* Responsive pour les petits écrans */
@media (max-width: 480px) {
    .notification-item {
        padding: 10px;
    }
    
    .notification-content p {
        font-size: 12px;
    }
    
    .notification-content .time {
        font-size: 10px;
    }
    
    .profile-dropdown {
        width: 280px;
        right: -80px;
    }
    
    .profile-header {
        padding: 12px;
    }
    
    .dropdown-avatar {
        width: 40px;
        height: 40px;
    }
    
    .profile-info strong {
        font-size: 13px;
    }
    
    .profile-info span {
        font-size: 11px;
    }
}
</style>