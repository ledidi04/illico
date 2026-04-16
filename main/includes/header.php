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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js pour les graphiques -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
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
                        <!-- Suggestions de recherche -->
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