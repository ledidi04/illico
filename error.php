<?php
session_start();

// ═══════════════════════════════════════════════════════════════
// DÉTECTION DE LA RACINE DU SITE
// ═══════════════════════════════════════════════════════════════
// Définir l'URL de base du site (à adapter selon votre configuration)
$base_url = '/'; // Racine du site
// Si votre site est dans un sous-dossier, utilisez :
// $base_url = '/illico/'; // ou '/main/' selon votre structure

// Alternative : détection automatique
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
$base_url = rtrim($script_dir, '/\\') . '/';
// Si error.php est à la racine, $base_url sera '/'

// Récupérer le code d'erreur et le message
$error_code = http_response_code() ?: 500;
if (isset($_GET['code'])) {
    $error_code = intval($_GET['code']);
}

$error_message = $_GET['message'] ?? '';
$error_type = $_GET['type'] ?? '';

// Définir les messages selon le code d'erreur
$error_config = [
    400 => [
        'title' => 'Requête incorrecte',
        'message' => 'La requête envoyée au serveur est invalide ou mal formée.',
        'icon' => 'fa-exclamation-triangle',
        'color' => '#f59e0b'
    ],
    401 => [
        'title' => 'Non autorisé',
        'message' => 'Vous devez être authentifié pour accéder à cette ressource.',
        'icon' => 'fa-lock',
        'color' => '#ef4444'
    ],
    403 => [
        'title' => 'Accès interdit',
        'message' => 'Vous n\'avez pas les permissions nécessaires pour accéder à cette page.',
        'icon' => 'fa-ban',
        'color' => '#ef4444'
    ],
    404 => [
        'title' => 'Page introuvable',
        'message' => 'La page que vous recherchez a été déplacée, supprimée ou n\'a jamais existé.',
        'icon' => 'fa-map-signs',
        'color' => '#f59e0b'
    ],
    405 => [
        'title' => 'Méthode non autorisée',
        'message' => 'La méthode HTTP utilisée n\'est pas supportée pour cette ressource.',
        'icon' => 'fa-slash',
        'color' => '#ef4444'
    ],
    408 => [
        'title' => 'Délai d\'attente dépassé',
        'message' => 'Le serveur a mis trop de temps à répondre. Veuillez réessayer.',
        'icon' => 'fa-clock',
        'color' => '#f59e0b'
    ],
    429 => [
        'title' => 'Trop de requêtes',
        'message' => 'Vous avez envoyé trop de requêtes. Veuillez patienter quelques instants.',
        'icon' => 'fa-gauge-high',
        'color' => '#f59e0b'
    ],
    500 => [
        'title' => 'Erreur interne du serveur',
        'message' => 'Une erreur inattendue s\'est produite. Nos équipes ont été notifiées.',
        'icon' => 'fa-server',
        'color' => '#dc2626'
    ],
    502 => [
        'title' => 'Passerelle incorrecte',
        'message' => 'Le serveur a reçu une réponse invalide du serveur amont.',
        'icon' => 'fa-network-wired',
        'color' => '#dc2626'
    ],
    503 => [
        'title' => 'Service indisponible',
        'message' => 'Le service est temporairement en maintenance. Veuillez réessayer plus tard.',
        'icon' => 'fa-tools',
        'color' => '#dc2626'
    ],
    504 => [
        'title' => 'Délai de passerelle dépassé',
        'message' => 'Le serveur amont a mis trop de temps à répondre.',
        'icon' => 'fa-hourglass-end',
        'color' => '#dc2626'
    ]
];

// Si code non défini, utiliser 500
if (!isset($error_config[$error_code])) {
    $error_code = 500;
}

$config = $error_config[$error_code];

// Personnaliser le message si fourni
if (!empty($error_message)) {
    $config['message'] = htmlspecialchars($error_message);
}
if (!empty($error_type)) {
    $config['title'] = htmlspecialchars($error_type);
}

// URL de retour
$referer = $_SERVER['HTTP_REFERER'] ?? $base_url . 'index.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="S&P illico - Banque Communautaire moderne, sécurisée et accessible">
    <title>Erreur <?= $error_code ?> - S&P illico</title>
    
    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- BALISE BASE POUR LES CHEMINS ABSOLUS -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <base href="<?= $protocol ?>://<?= $host ?>/">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="main/logo.jpeg">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --primary: #1e3a8a;
            --primary-dark: #1e40af;
            --primary-light: #3b82f6;
            --secondary: #f59e0b;
            --success: #10b981;
            --danger: #ef4444;
            --dark: #0f172a;
            --gray: #64748b;
            --light: #f8fafc;
            --white: #ffffff;
        }
        
        body { 
            font-family: 'Inter', sans-serif; 
            background: var(--light);
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        /* ========== NAVBAR ========== */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
            z-index: 1000;
            padding: 15px 0;
            transition: all 0.3s ease;
        }
        
        .navbar.scrolled {
            padding: 10px 0;
            background: rgba(255, 255, 255, 0.98);
        }
        
        .nav-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        
        .logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            overflow: hidden;
        }
        
        .logo-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .logo-text {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark);
        }
        
        .logo-text span {
            color: var(--primary);
        }
        
        .nav-menu {
            display: flex;
            align-items: center;
            gap: 30px;
        }
        
        .nav-links {
            display: flex;
            gap: 30px;
        }
        
        .nav-link {
            text-decoration: none;
            color: var(--gray);
            font-weight: 500;
            font-size: 15px;
            transition: color 0.3s;
            position: relative;
        }
        
        .nav-link:hover {
            color: var(--primary);
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary);
            transition: width 0.3s;
        }
        
        .nav-link:hover::after {
            width: 100%;
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white !important;
            padding: 12px 28px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(30, 58, 138, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(30, 58, 138, 0.4);
        }
        
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            color: var(--dark);
            cursor: pointer;
        }
        
        /* ========== SECTION ERREUR ========== */
        .error-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 100px 24px 60px;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            margin-top: 0;
        }
        
        .error-container {
            max-width: 600px;
            width: 100%;
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .error-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: <?= $config['color'] ?>;
        }
        
        .error-code {
            font-size: 100px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 10px;
            background: linear-gradient(135deg, <?= $config['color'] ?> 0%, var(--primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .error-icon {
            width: 70px;
            height: 70px;
            background: <?= $config['color'] ?>15;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .error-icon i {
            font-size: 35px;
            color: <?= $config['color'] ?>;
        }
        
        .error-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 12px;
        }
        
        .error-message {
            font-size: 15px;
            color: var(--gray);
            line-height: 1.6;
            margin-bottom: 25px;
        }
        
        .error-details {
            background: #f8fafc;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 25px;
            text-align: left;
        }
        
        .detail-row {
            display: flex;
            padding: 6px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            width: 110px;
            font-weight: 600;
            color: var(--dark);
            font-size: 12px;
        }
        
        .detail-value {
            flex: 1;
            color: var(--gray);
            font-size: 12px;
            word-break: break-word;
        }
        
        .error-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
        }
        
        .btn-primary {
            background: <?= $config['color'] ?>;
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px <?= $config['color'] ?>40;
            filter: brightness(1.1);
        }
        
        .btn-secondary {
            background: white;
            color: var(--dark);
            border: 2px solid #e2e8f0;
        }
        
        .btn-secondary:hover {
            background: var(--light);
            border-color: #cbd5e1;
        }
        
        .btn-outline {
            background: transparent;
            color: <?= $config['color'] ?>;
            border: 2px solid <?= $config['color'] ?>;
        }
        
        .btn-outline:hover {
            background: <?= $config['color'] ?>;
            color: white;
        }
        
        /* ========== FOOTER ========== */
        .footer {
            background: var(--dark);
            color: white;
            padding: 50px 0 25px;
        }
        
        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1.5fr;
            gap: 35px;
            margin-bottom: 35px;
        }
        
        .footer-about p {
            color: #94a3b8;
            line-height: 1.7;
            margin: 18px 0;
        }
        
        .footer-social {
            display: flex;
            gap: 12px;
        }
        
        .social-link {
            width: 36px;
            height: 36px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .social-link:hover {
            background: var(--primary);
            transform: translateY(-3px);
        }
        
        .footer-links h4 {
            font-size: 16px;
            margin-bottom: 18px;
        }
        
        .footer-links ul {
            list-style: none;
        }
        
        .footer-links li {
            margin-bottom: 10px;
        }
        
        .footer-links a {
            color: #94a3b8;
            text-decoration: none;
            transition: color 0.3s;
            font-size: 14px;
        }
        
        .footer-links a:hover {
            color: white;
        }
        
        .footer-contact p {
            color: #94a3b8;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        
        .footer-contact i {
            width: 18px;
            color: var(--primary-light);
        }
        
        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #94a3b8;
            font-size: 13px;
        }
        
        .footer-bottom-links {
            display: flex;
            gap: 25px;
        }
        
        .footer-bottom-links a {
            color: #94a3b8;
            text-decoration: none;
        }
        
        /* ========== RESPONSIVE ========== */
        @media (max-width: 1024px) {
            .footer-content { grid-template-columns: 1fr 1fr; }
        }
        
        @media (max-width: 768px) {
            .nav-links { display: none; }
            .nav-links.active {
                display: flex;
                flex-direction: column;
                position: absolute;
                top: 80px;
                left: 0;
                right: 0;
                background: white;
                padding: 20px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            }
            .mobile-toggle { display: block; }
            .error-section { padding: 90px 16px 40px; }
            .error-container { padding: 30px 20px; }
            .error-code { font-size: 70px; }
            .error-title { font-size: 20px; }
            .footer-content { grid-template-columns: 1fr; }
            .footer-bottom { flex-direction: column; gap: 12px; text-align: center; }
            .detail-row { flex-direction: column; }
            .detail-label { width: 100%; margin-bottom: 3px; }
        }
        
        /* Animation */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .pulse-animation {
            animation: pulse 2s infinite;
        }
    </style>
</head>
<body>
    <!-- ========== NAVBAR ========== -->
    <nav class="navbar" id="navbar">
        <div class="nav-container">
            <a href="index.php" class="logo">
                <div class="logo-icon">
                    <img src="main/logo.jpeg" alt="S&P illico" style="width: 100%; height: auto;">
                </div>
                <div class="logo-text">S&P <span>illico</span></div>
            </a>
            
            <button class="mobile-toggle" id="mobileToggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="nav-menu">
                <div class="nav-links" id="navLinks">
                    <a href="index.php" class="nav-link">Accueil</a>
                    <a href="index.php#services" class="nav-link">Services</a>
                    <a href="index.php#rates" class="nav-link">Taux</a>
                    <a href="index.php#about" class="nav-link">À propos</a>
                    <a href="index.php#contact" class="nav-link">Contact</a>
                </div>
                <a href="main/index.php" class="btn-login">
                    <i class="fas fa-lock"></i>
                    Espace Client
                </a>
            </div>
        </div>
    </nav>
    
    <!-- ========== SECTION ERREUR ========== -->
    <section class="error-section">
        <div class="error-container" data-aos="fade-up">
            <div class="error-code pulse-animation"><?= $error_code ?></div>
            
            <div class="error-icon">
                <i class="fas <?= $config['icon'] ?>"></i>
            </div>
            
            <h1 class="error-title"><?= $config['title'] ?></h1>
            <p class="error-message"><?= $config['message'] ?></p>
            
            <div class="error-details">
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-code"></i> Code erreur</span>
                    <span class="detail-value">HTTP <?= $error_code ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-clock"></i> Date et heure</span>
                    <span class="detail-value"><?= date('d/m/Y à H:i:s') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-globe"></i> URL demandée</span>
                    <span class="detail-value"><?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-server"></i> Serveur</span>
                    <span class="detail-value"><?= htmlspecialchars($_SERVER['SERVER_NAME'] ?? 'S&P illico') ?></span>
                </div>
                <?php if (!empty($error_type)): ?>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-tag"></i> Type d'erreur</span>
                    <span class="detail-value"><?= $error_type ?></span>
                </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['user_id'])): ?>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-user"></i> Utilisateur</span>
                    <span class="detail-value"><?= htmlspecialchars($_SESSION['nom_complet'] ?? $_SESSION['username'] ?? 'Connecté') ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="error-actions">
                <a href="<?= htmlspecialchars($referer) ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Retour
                </a>
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-home"></i>
                    Accueil
                </a>
                <button class="btn btn-outline" onclick="window.location.reload()">
                    <i class="fas fa-sync-alt"></i>
                    Réessayer
                </button>
            </div>
            
            <p style="margin-top: 20px; font-size: 12px; color: var(--gray);">
                <i class="fas fa-headset"></i> Besoin d'aide ? Contactez le support : 
                <strong>+509 3338-3509</strong> • <strong>illicoms01@gmail.com</strong>
            </p>
        </div>
    </section>
    
    <!-- ========== FOOTER ========== -->
    <footer class="footer" id="contact">
        <div class="container">
            <div class="footer-content">
                <div class="footer-about">
                    <div class="logo">
                        <div class="logo-icon">
                            <i class="fas fa-building-columns"></i>
                        </div>
                        <div class="logo-text" style="color: white;">S&P <span>illico</span></div>
                    </div>
                    <p>Votre partenaire bancaire de confiance, engagé à offrir des services financiers accessibles et innovants à toute la communauté haïtienne.</p>
                    <div class="footer-social">
                        <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                
                <div class="footer-links">
                    <h4>Liens rapides</h4>
                    <ul>
                        <li><a href="index.php">Accueil</a></li>
                        <li><a href="index.php#services">Services</a></li>
                        <li><a href="index.php#rates">Taux du jour</a></li>
                        <li><a href="#">À propos</a></li>
                        <li><a href="#">Carrières</a></li>
                    </ul>
                </div>
                
                <div class="footer-links">
                    <h4>Services</h4>
                    <ul>
                        <li><a href="main/index.php">Banque en ligne</a></li>
                        <li><a href="#">Comptes épargne</a></li>
                        <li><a href="#">Comptes courant</a></li>
                        <li><a href="#">Prêts</a></li>
                        <li><a href="#">Cartes bancaires</a></li>
                    </ul>
                </div>
                
                <div class="footer-contact">
                    <h4>Contact</h4>
                    <p><i class="fas fa-map-marker-alt"></i> Quartier Muraille, Terrier-Rouge, Haïti</p>
                    <p><i class="fas fa-phone"></i> +509 3338-3509</p>
                    <p><i class="fas fa-envelope"></i> illicoms01@gmail.com</p>
                    <p><i class="fas fa-clock"></i> Lun-Dim: 8h00 - 20h00</p>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> S&P illico - Banque Communautaire. Tous droits réservés.</p>
                <div class="footer-bottom-links">
                    <a href="#">Confidentialité</a>
                    <a href="#">Conditions d'utilisation</a>
                    <a href="#">Mentions légales</a>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Scripts -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 800, once: true });
        
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
        
        // Mobile menu toggle
        document.getElementById('mobileToggle').addEventListener('click', function() {
            document.getElementById('navLinks').classList.toggle('active');
        });
    </script>
</body>
</html>