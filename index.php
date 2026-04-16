<?php
session_start();

// Si déjà connecté, rediriger vers le dashboard approprié
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? '';
    switch ($role) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'secretaire':
            header('Location: secretaire/dashboard.php');
            break;
        case 'caissier':
            header('Location: caissier/dashboard.php');
            break;
        default:
            header('Location: login.php');
    }
    exit;
}

// Taux de change simulés (à remplacer par une API en production)
$taux_jour = [
    'USD' => ['achat' => 132.50, 'vente' => 135.75],
    'EUR' => ['achat' => 142.80, 'vente' => 146.50],
    'CAD' => ['achat' => 96.40, 'vente' => 99.20]
];

$date_taux = date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="S&P illico - Banque Communautaire moderne, sécurisée et accessible">
    <meta name="keywords" content="banque, Haïti, S&P illico, services bancaires, épargne, crédit">
    <title>S&P illico - Banque Communautaire</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    
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
        
        .nav-link.active {
            color: var(--primary);
        }
        
        .nav-link.active::after {
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
        
        /* ========== CARROUSEL ========== */
        .carousel {
            margin-top: 80px;
            position: relative;
            height: 600px;
            overflow: hidden;
        }
        
        .carousel-container {
            position: relative;
            height: 100%;
        }
        
        .carousel-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 0.8s ease;
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: center;
        }
        
        .carousel-slide.active {
            opacity: 1;
        }
        
        .slide-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, rgba(15, 23, 42, 0.8) 0%, rgba(15, 23, 42, 0.4) 100%);
        }
        
        .slide-content {
            position: relative;
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
            color: white;
            z-index: 10;
        }
        
        .slide-content h1 {
            font-size: 56px;
            font-weight: 800;
            margin-bottom: 20px;
            max-width: 700px;
            line-height: 1.2;
            animation: slideInUp 0.8s ease;
        }
        
        .slide-content p {
            font-size: 18px;
            margin-bottom: 30px;
            max-width: 500px;
            opacity: 0.9;
            animation: slideInUp 0.8s ease 0.2s both;
        }
        
        .slide-buttons {
            display: flex;
            gap: 15px;
            animation: slideInUp 0.8s ease 0.4s both;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            padding: 14px 32px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-outline-light {
            background: transparent;
            color: white;
            padding: 14px 32px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            border: 2px solid rgba(255, 255, 255, 0.5);
            transition: all 0.3s;
        }
        
        .btn-outline-light:hover {
            border-color: white;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .carousel-indicators {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 12px;
            z-index: 20;
        }
        
        .indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .indicator.active {
            background: white;
            width: 30px;
            border-radius: 6px;
        }
        
        .carousel-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(5px);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s;
            z-index: 20;
            border: none;
        }
        
        .carousel-arrow:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .carousel-arrow.prev { left: 30px; }
        .carousel-arrow.next { right: 30px; }
        
        /* ========== CARTES SERVICES ========== */
        .services {
            padding: 80px 0;
            background: white;
        }
        
        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 60px;
        }
        
        .section-header h2 {
            font-size: 36px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 15px;
        }
        
        .section-header p {
            color: var(--gray);
            font-size: 18px;
        }
        
        .services-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
        }
        
        .service-card {
            background: var(--light);
            padding: 40px 30px;
            border-radius: 20px;
            text-align: center;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            background: white;
        }
        
        .service-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            color: white;
            font-size: 32px;
        }
        
        .service-card h3 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .service-card p {
            color: var(--gray);
            font-size: 14px;
            line-height: 1.6;
        }
        
        /* ========== TAUX DU JOUR ========== */
        .rates-section {
            padding: 80px 0;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }
        
        .rates-header {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .rates-header h2 {
            font-size: 36px;
            margin-bottom: 10px;
        }
        
        .rates-header p {
            opacity: 0.9;
        }
        
        .rates-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 40px;
        }
        
        .rates-table {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .rates-table h3 {
            margin-bottom: 25px;
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .rates-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .rates-table th {
            text-align: left;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            font-weight: 600;
            border-radius: 10px 10px 0 0;
        }
        
        .rates-table td {
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 18px;
            font-weight: 500;
        }
        
        .rates-table td:last-child {
            text-align: right;
        }
        
        .rate-up { color: var(--success); }
        .rate-change { font-size: 13px; margin-left: 8px; }
        
        .converter-box {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .converter-box h3 {
            margin-bottom: 25px;
            font-size: 22px;
        }
        
        .converter-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .converter-input {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 15px;
        }
        
        .converter-input label {
            display: block;
            margin-bottom: 8px;
            opacity: 0.8;
            font-size: 13px;
        }
        
        .converter-input input,
        .converter-input select {
            width: 100%;
            background: transparent;
            border: none;
            color: white;
            font-size: 20px;
            font-weight: 600;
            outline: none;
        }
        
        .converter-input select option {
            background: var(--primary-dark);
            color: white;
        }
        
        .converter-input input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        
        .converter-result {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            margin-top: 10px;
        }
        
        .converter-result .amount {
            font-size: 32px;
            font-weight: 700;
        }
        
        .converter-result .label {
            font-size: 13px;
            opacity: 0.8;
            margin-top: 5px;
        }
        
        .btn-convert {
            background: white;
            color: var(--primary);
            border: none;
            padding: 15px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn-convert:hover {
            transform: scale(1.02);
        }
        
        .rate-date {
            text-align: right;
            margin-top: 20px;
            opacity: 0.7;
            font-size: 13px;
        }
        
        /* ========== FOOTER ========== */
        .footer {
            background: var(--dark);
            color: white;
            padding: 60px 0 30px;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1.5fr;
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .footer-about p {
            color: #94a3b8;
            line-height: 1.8;
            margin: 20px 0;
        }
        
        .footer-social {
            display: flex;
            gap: 15px;
        }
        
        .social-link {
            width: 40px;
            height: 40px;
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
            font-size: 18px;
            margin-bottom: 20px;
        }
        
        .footer-links ul {
            list-style: none;
        }
        
        .footer-links li {
            margin-bottom: 12px;
        }
        
        .footer-links a {
            color: #94a3b8;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-links a:hover {
            color: white;
        }
        
        .footer-contact p {
            color: #94a3b8;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .footer-contact i {
            width: 20px;
            color: var(--primary-light);
        }
        
        .footer-newsletter input {
            width: 100%;
            padding: 12px 15px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: white;
            margin-bottom: 10px;
        }
        
        .footer-newsletter input::placeholder {
            color: #94a3b8;
        }
        
        .btn-subscribe {
            width: 100%;
            padding: 12px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-subscribe:hover {
            background: var(--primary-light);
        }
        
        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #94a3b8;
            font-size: 14px;
        }
        
        .footer-bottom-links {
            display: flex;
            gap: 30px;
        }
        
        .footer-bottom-links a {
            color: #94a3b8;
            text-decoration: none;
        }
        
        /* ========== ANIMATIONS ========== */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* ========== RESPONSIVE ========== */
        @media (max-width: 1024px) {
            .services-grid { grid-template-columns: repeat(2, 1fr); }
            .footer-content { grid-template-columns: 1fr 1fr; }
            .rates-container { grid-template-columns: 1fr; }
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
            .carousel { height: 500px; }
            .slide-content h1 { font-size: 36px; }
            .services-grid { grid-template-columns: 1fr; }
            .footer-content { grid-template-columns: 1fr; }
            .footer-bottom { flex-direction: column; gap: 15px; text-align: center; }
        }
    </style>
</head>
<body>
    <!-- ========== NAVBAR ========== -->
    <nav class="navbar" id="navbar">
        <div class="nav-container">
            <a href="index.php" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-building-columns"></i>
                </div>
                <div class="logo-text">S&P <span>illico</span></div>
            </a>
            
            <button class="mobile-toggle" id="mobileToggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="nav-menu">
                <div class="nav-links" id="navLinks">
                    <a href="index.php" class="nav-link active">Accueil</a>
                    <a href="#services" class="nav-link">Services</a>
                    <a href="#rates" class="nav-link">Taux</a>
                    <a href="#about" class="nav-link">À propos</a>
                    <a href="#contact" class="nav-link">Contact</a>
                </div>
                <a href="main/index.php" class="btn-login">
                    <i class="fas fa-lock"></i>
                    Espace Client
                </a>
            </div>
        </div>
    </nav>
    
    <!-- ========== CARROUSEL ========== -->
    <section class="carousel">
        <div class="carousel-container">
            <!-- Slide 1 -->
            <div class="carousel-slide active" style="background-image: url('https://images.unsplash.com/photo-1560520653-9e0e4c89eb11?w=1600');">
                <div class="slide-overlay"></div>
                <div class="slide-content">
                    <h1>Votre banque communautaire de confiance</h1>
                    <p>Des services bancaires modernes, accessibles et sécurisés pour toute la communauté.</p>
                    <div class="slide-buttons">
                        <a href="main/index.php" class="btn-primary">
                            <i class="fas fa-user"></i>
                            Ouvrir un compte
                        </a>
                        <a href="#services" class="btn-outline-light">Découvrir nos services</a>
                    </div>
                </div>
            </div>
            
            <!-- Slide 2 -->
            <div class="carousel-slide" style="background-image: url('https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=1600');">
                <div class="slide-overlay"></div>
                <div class="slide-content">
                    <h1>Épargnez pour votre avenir</h1>
                    <p>Des taux d'intérêt compétitifs et des solutions d'épargne adaptées à vos besoins.</p>
                    <div class="slide-buttons">
                        <a href="main/index.php" class="btn-primary">
                            <i class="fas fa-piggy-bank"></i>
                            Commencer à épargner
                        </a>
                        <a href="#rates" class="btn-outline-light">Voir les taux</a>
                    </div>
                </div>
            </div>
            
            <!-- Slide 3 -->
            <div class="carousel-slide" style="background-image: url('https://images.unsplash.com/photo-1579621970563-ebec7560ff3e?w=1600');">
                <div class="slide-overlay"></div>
                <div class="slide-content">
                    <h1>Gérez vos finances en toute simplicité</h1>
                    <p>Accédez à vos comptes 24/7, effectuez des transactions et suivez vos opérations.</p>
                    <div class="slide-buttons">
                        <a href="main/index.php" class="btn-primary">
                            <i class="fas fa-sign-in-alt"></i>
                            Se connecter
                        </a>
                        <a href="#contact" class="btn-outline-light">Nous contacter</a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Indicateurs -->
        <div class="carousel-indicators">
            <div class="indicator active" data-slide="0"></div>
            <div class="indicator" data-slide="1"></div>
            <div class="indicator" data-slide="2"></div>
        </div>
        
        <!-- Flèches -->
        <button class="carousel-arrow prev" onclick="changeSlide(-1)">
            <i class="fas fa-chevron-left"></i>
        </button>
        <button class="carousel-arrow next" onclick="changeSlide(1)">
            <i class="fas fa-chevron-right"></i>
        </button>
    </section>
    
    <!-- ========== CARTES SERVICES ========== -->
    <section class="services" id="services">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <h2>Nos Services</h2>
                <p>Des solutions bancaires complètes pour tous vos besoins</p>
            </div>
            
            <div class="services-grid">
                <div class="service-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="service-icon">
                        <i class="fas fa-piggy-bank"></i>
                    </div>
                    <h3>Compte Épargne</h3>
                    <p>Faites fructifier votre argent avec nos comptes épargne à taux compétitifs.</p>
                </div>
                
                <div class="service-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="service-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <h3>Compte Courant</h3>
                    <p>Gérez vos opérations quotidiennes avec flexibilité et simplicité.</p>
                </div>
                
                <div class="service-card" data-aos="fade-up" data-aos-delay="300">
                    <div class="service-icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <h3>Virements</h3>
                    <p>Transférez de l'argent rapidement et en toute sécurité entre comptes.</p>
                </div>
                
                <div class="service-card" data-aos="fade-up" data-aos-delay="400">
                    <div class="service-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Sécurité 24/7</h3>
                    <p>Vos transactions et données sont protégées par les dernières technologies.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- ========== TAUX DU JOUR ========== -->
    <section class="rates-section" id="rates">
        <div class="container">
            <div class="rates-header" data-aos="fade-up">
                <h2><i class="fas fa-chart-line"></i> Taux du jour</h2>
                <p>Taux de change en temps réel - <?= $date_taux ?></p>
            </div>
            
            <div class="rates-container">
                <div class="rates-table" data-aos="fade-right">
                    <h3><i class="fas fa-dollar-sign"></i> Taux de change</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Devise</th>
                                <th>Achat (HTG)</th>
                                <th>Vente (HTG)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($taux_jour as $devise => $taux): ?>
                            <tr>
                                <td>
                                    <strong><?= $devise ?></strong>
                                    <?php if ($devise == 'USD'): ?>
                                    <span style="color: #10b981; margin-left: 5px;"><i class="fas fa-arrow-up"></i></span>
                                    <?php elseif ($devise == 'EUR'): ?>
                                    <span style="color: #ef4444; margin-left: 5px;"><i class="fas fa-arrow-down"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= number_format($taux['achat'], 2) ?></td>
                                <td><?= number_format($taux['vente'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="rate-date">
                        <i class="far fa-clock"></i> Dernière mise à jour : aujourd'hui à 09:00
                    </div>
                </div>
                
                <div class="converter-box" data-aos="fade-left">
                    <h3><i class="fas fa-calculator"></i> Convertisseur</h3>
                    <div class="converter-form">
                        <div class="converter-input">
                            <label>Montant</label>
                            <input type="number" id="amount" placeholder="0.00" value="100">
                        </div>
                        <div class="converter-input">
                            <label>De</label>
                            <select id="fromCurrency">
                                <option value="USD">USD - Dollar US</option>
                                <option value="HTG">HTG - Gourde Haïtienne</option>
                                <option value="EUR">EUR - Euro</option>
                            </select>
                        </div>
                        <div class="converter-input">
                            <label>Vers</label>
                            <select id="toCurrency">
                                <option value="HTG">HTG - Gourde Haïtienne</option>
                                <option value="USD">USD - Dollar US</option>
                                <option value="EUR">EUR - Euro</option>
                            </select>
                        </div>
                        <button class="btn-convert" onclick="convertCurrency()">
                            <i class="fas fa-sync-alt"></i> Convertir
                        </button>
                        <div class="converter-result" id="converterResult">
                            <div class="amount" id="resultAmount">13,250.00</div>
                            <div class="label" id="resultLabel">HTG</div>
                        </div>
                    </div>
                </div>
            </div>
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
                        <li><a href="#services">Services</a></li>
                        <li><a href="#rates">Taux du jour</a></li>
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
                    <p><i class="fas fa-phone"></i> +509 2222-1111</p>
                    <p><i class="fas fa-envelope"></i> contact@spillico.ht</p>
                    <p><i class="fas fa-clock"></i> Lun-Ven: 8h00 - 16h00</p>
                    
                    <div class="footer-newsletter" style="margin-top: 20px;">
                        <input type="email" placeholder="Votre email">
                        <button class="btn-subscribe">S'abonner à la newsletter</button>
                    </div>
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
        // Initialisation AOS
        AOS.init({
            duration: 800,
            once: true
        });
        
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
        
        // Carousel
        let currentSlide = 0;
        const slides = document.querySelectorAll('.carousel-slide');
        const indicators = document.querySelectorAll('.indicator');
        let autoSlideInterval;
        
        function showSlide(index) {
            if (index >= slides.length) index = 0;
            if (index < 0) index = slides.length - 1;
            
            slides.forEach(slide => slide.classList.remove('active'));
            indicators.forEach(ind => ind.classList.remove('active'));
            
            slides[index].classList.add('active');
            indicators[index].classList.add('active');
            currentSlide = index;
        }
        
        function changeSlide(direction) {
            showSlide(currentSlide + direction);
            resetAutoSlide();
        }
        
        function resetAutoSlide() {
            clearInterval(autoSlideInterval);
            autoSlideInterval = setInterval(() => showSlide(currentSlide + 1), 5000);
        }
        
        // Event listeners pour les indicateurs
        indicators.forEach((indicator, index) => {
            indicator.addEventListener('click', () => {
                showSlide(index);
                resetAutoSlide();
            });
        });
        
        // Démarrer le défilement automatique
        autoSlideInterval = setInterval(() => showSlide(currentSlide + 1), 5000);
        
        // Mobile menu toggle
        document.getElementById('mobileToggle').addEventListener('click', function() {
            document.getElementById('navLinks').classList.toggle('active');
        });
        
        // Smooth scroll pour les liens d'ancrage
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
        
        // Convertisseur de devises
        const rates = <?= json_encode($taux_jour) ?>;
        rates['HTG'] = { achat: 1, vente: 1 };
        
        function convertCurrency() {
            const amount = parseFloat(document.getElementById('amount').value) || 0;
            const from = document.getElementById('fromCurrency').value;
            const to = document.getElementById('toCurrency').value;
            
            let result;
            if (from === to) {
                result = amount;
            } else if (from === 'HTG') {
                result = amount / rates[to].vente;
            } else if (to === 'HTG') {
                result = amount * rates[from].achat;
            } else {
                const inHTG = amount * rates[from].achat;
                result = inHTG / rates[to].vente;
            }
            
            document.getElementById('resultAmount').textContent = result.toLocaleString('fr-FR', { 
                minimumFractionDigits: 2, 
                maximumFractionDigits: 2 
            });
            document.getElementById('resultLabel').textContent = to;
        }
        
        // Initialiser le convertisseur
        convertCurrency();
        
        // Mettre à jour le convertisseur quand les sélecteurs changent
        document.getElementById('fromCurrency').addEventListener('change', convertCurrency);
        document.getElementById('toCurrency').addEventListener('change', convertCurrency);
        document.getElementById('amount').addEventListener('input', convertCurrency);
    </script>
</body>
</html>