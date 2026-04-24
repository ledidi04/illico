<?php
session_start();

// ═══════════════════════════════════════════════════════════════
// DÉTECTION DYNAMIQUE DE LA RACINE DU SITE
// ═══════════════════════════════════════════════════════════════
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];

// Supprimer le sous-dossier si le site est dans un sous-répertoire
$script_name = $_SERVER['SCRIPT_NAME'];
$script_dir = dirname($script_name);
$base_path = ($script_dir === '/' || $script_dir === '\\') ? '' : $script_dir;

// URL de base complète (pour les liens absolus)
$base_url = $protocol . '://' . $host . $base_path . '/';

// Redirection si déjà connecté
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? '';
    switch ($role) {
        case 'admin': header('Location: ' . $base_url . 'admin/dashboard.php'); break;
        case 'secretaire': header('Location: ' . $base_url . 'secretaire/dashboard.php'); break;
        case 'caissier': header('Location: ' . $base_url . 'caissier/dashboard.php'); break;
        default: header('Location: ' . $base_url . 'login.php');
    }
    exit;
}

$taux_jour = [
    'USD' => ['achat' => 132.50, 'vente' => 135.75, 'change' => '+0.8%'],
    'EUR' => ['achat' => 142.80, 'vente' => 146.50, 'change' => '-0.3%'],
    'CAD' => ['achat' => 96.40, 'vente' => 99.20, 'change' => '+0.2%']
];

$date_taux = date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google-site-verification" content="ikYgHEBCKlQWimzFOnFPfwoOUqXJ41fPNc6nXbI1lng" />
    <title>S&P illico — Banque Communautaire</title>
    <link rel="icon" type="image/jpeg" href="main/logo.jpeg">
    <base href="<?= $base_url ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ========== RESET & ROOT ========== */
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --navy: #0a1628;
            --navy-mid: #112240;
            --blue: #1e3a8a;
            --blue-mid: #1d4ed8;
            --accent: #f59e0b;
            --accent-light: #fbbf24;
            --gold: #c9a84c;
            --emerald: #10b981;
            --rose: #ef4444;
            --sky: #38bdf8;
            --white: #ffffff;
            --off-white: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-400: #94a3b8;
            --gray-600: #475569;
            --text: #0f172a;
            --r: 16px;
            --r-lg: 24px;
            --shadow: 0 20px 60px rgba(10,22,40,0.15);
            --shadow-lg: 0 40px 80px rgba(10,22,40,0.25);
            --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--off-white);
            color: var(--text);
            overflow-x: hidden;
        }

        /* ========== NAVBAR ========== */
        .navbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 9000;
            padding: 0;
            transition: var(--transition);
        }

        .navbar-inner {
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(30,58,138,0.08);
            transition: var(--transition);
        }

        .navbar.scrolled .navbar-inner {
            background: rgba(255,255,255,0.98);
            box-shadow: 0 4px 30px rgba(10,22,40,0.12);
        }

        .nav-wrap {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 74px;
        }

        /* Logo */
        .logo {
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
            flex-shrink: 0;
        }

        .logo-mark {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, var(--navy) 0%, var(--blue-mid) 100%);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 22px;
            box-shadow: 0 4px 16px rgba(30,58,138,0.35);
            overflow: hidden;
        }

        .logo-mark img { width: 100%; height: 100%; object-fit: cover; }

        .logo-name {
            display: flex; flex-direction: column; line-height: 1;
        }

        .logo-name .brand { font-family: 'Playfair Display', serif; font-size: 22px; font-weight: 900; color: var(--navy); }
        .logo-name .brand span { color: var(--blue-mid); }
        .logo-name .tagline { font-size: 10px; color: var(--gray-400); letter-spacing: 0.1em; text-transform: uppercase; margin-top: 3px; }

        /* Nav Links */
        .nav-center { display: flex; align-items: center; gap: 4px; }

        .nav-item { position: relative; }

        .nav-link {
            display: flex; align-items: center; gap: 6px;
            padding: 10px 16px;
            color: var(--gray-600);
            font-size: 14px; font-weight: 500;
            text-decoration: none;
            border-radius: 10px;
            transition: var(--transition);
            white-space: nowrap;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--blue-mid);
            background: rgba(29,78,216,0.07);
        }

        .nav-link i.chevron { font-size: 10px; transition: transform 0.3s; }
        .nav-item:hover .nav-link i.chevron { transform: rotate(180deg); }

        /* Mega Dropdown */
        .dropdown {
            position: absolute;
            top: calc(100% + 12px);
            left: 50%;
            transform: translateX(-50%) translateY(-8px);
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(10,22,40,0.18), 0 0 0 1px rgba(30,58,138,0.08);
            padding: 28px;
            min-width: 580px;
            opacity: 0;
            pointer-events: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 9999;
        }

        .nav-item:hover .dropdown {
            opacity: 1;
            pointer-events: all;
            transform: translateX(-50%) translateY(0);
        }

        .dropdown::before {
            content: '';
            position: absolute;
            top: -6px; left: 50%;
            transform: translateX(-50%);
            width: 12px; height: 12px;
            background: white;
            border-radius: 2px;
            transform: translateX(-50%) rotate(45deg);
            box-shadow: -2px -2px 4px rgba(0,0,0,0.05);
        }

        .dropdown-cols {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }

        .dropdown-col-header {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--gray-400);
            padding: 8px 12px 4px;
            grid-column: 1/-1;
        }

        .dropdown-divider {
            grid-column: 1/-1;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }

        .dropdown-item {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 14px;
            border-radius: 12px;
            text-decoration: none;
            color: var(--text);
            transition: var(--transition);
        }

        .dropdown-item:hover { background: var(--gray-100); color: var(--blue-mid); }

        .dropdown-item-icon {
            width: 38px; height: 38px;
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: var(--blue-mid);
            font-size: 16px;
            flex-shrink: 0;
        }

        .dropdown-item:hover .dropdown-item-icon {
            background: linear-gradient(135deg, var(--blue-mid), var(--blue));
            color: white;
        }

        .dropdown-item-text .title { font-size: 13px; font-weight: 600; }
        .dropdown-item-text .desc { font-size: 11px; color: var(--gray-400); margin-top: 2px; }

        /* Nav CTA */
        .nav-cta { display: flex; align-items: center; gap: 12px; }

        .btn-space {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 11px 24px;
            background: linear-gradient(135deg, var(--navy) 0%, var(--blue-mid) 100%);
            color: white;
            border-radius: 40px;
            text-decoration: none;
            font-size: 14px; font-weight: 600;
            box-shadow: 0 4px 20px rgba(30,58,138,0.4);
            transition: var(--transition);
            white-space: nowrap;
        }

        .btn-space:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(30,58,138,0.5);
        }

        .mobile-toggle {
            display: none;
            background: none; border: none;
            font-size: 22px; color: var(--navy); cursor: pointer;
        }

        /* ========== HERO WITH PARALLAX ========== */
        .hero {
            position: relative;
            height: 100vh; min-height: 680px;
            display: flex; align-items: center;
            overflow: hidden;
        }

        .hero-bg {
            position: absolute; inset: 0;
            background: url('https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=1800&auto=format&fit=crop') center/cover no-repeat;
            will-change: transform;
            transform: scale(1.15);
        }

        .hero-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(125deg, rgba(10,22,40,0.92) 0%, rgba(10,22,40,0.6) 60%, rgba(29,78,216,0.3) 100%);
        }

        .hero-content {
            position: relative; z-index: 10;
            max-width: 1400px; margin: 0 auto; padding: 0 32px;
            width: 100%;
            padding-top: 74px;
        }

        .hero-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(245,158,11,0.2);
            border: 1px solid rgba(245,158,11,0.4);
            color: var(--accent-light);
            padding: 8px 18px; border-radius: 40px;
            font-size: 12px; font-weight: 600; letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 32px;
            animation: fadeUp 0.8s ease both;
        }

        .hero-badge::before {
            content: '';
            width: 6px; height: 6px;
            background: var(--accent);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        .hero-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(42px, 5.5vw, 80px);
            font-weight: 900;
            color: white;
            line-height: 1.08;
            max-width: 760px;
            margin-bottom: 28px;
            animation: fadeUp 0.8s 0.15s ease both;
        }

        .hero-title em { font-style: normal; color: var(--accent); }

        .hero-sub {
            font-size: 18px; color: rgba(255,255,255,0.75);
            max-width: 500px; line-height: 1.7;
            margin-bottom: 44px;
            animation: fadeUp 0.8s 0.3s ease both;
        }

        .hero-actions {
            display: flex; gap: 16px; flex-wrap: wrap;
            animation: fadeUp 0.8s 0.45s ease both;
        }

        .btn-hero-primary {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 17px 38px;
            background: linear-gradient(135deg, var(--accent) 0%, var(--gold) 100%);
            color: var(--navy);
            border-radius: 50px;
            text-decoration: none;
            font-weight: 700; font-size: 15px;
            box-shadow: 0 8px 30px rgba(245,158,11,0.45);
            transition: var(--transition);
        }

        .btn-hero-primary:hover { transform: translateY(-3px); box-shadow: 0 14px 40px rgba(245,158,11,0.55); }

        .btn-hero-ghost {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 17px 38px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.35);
            color: white;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600; font-size: 15px;
            backdrop-filter: blur(8px);
            transition: var(--transition);
        }

        .btn-hero-ghost:hover { background: rgba(255,255,255,0.2); border-color: rgba(255,255,255,0.6); }

        /* Hero Stats */
        .hero-stats {
            position: absolute; bottom: 60px; left: 0; right: 0;
            z-index: 10;
        }

        .hero-stats-inner {
            max-width: 400px; margin: 0 auto; padding: 0 32px;
            display: flex; gap: 0;
        }

        .stat-card {
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.15);
            padding: 24px 40px;
            flex: 1;
            transition: var(--transition);
        }

        .stat-card:first-child { border-radius: 20px 0 0 20px; }
        .stat-card:last-child { border-radius: 0 20px 20px 0; }
        .stat-card:not(:last-child) { border-right: none; }
        .stat-card:hover { background: rgba(255,255,255,0.15); }

        .stat-num {
            font-family: 'Playfair Display', serif;
            font-size: 36px; font-weight: 900;
            color: white;
        }

        .stat-num span { color: var(--accent); }
        .stat-label { color: rgba(255,255,255,0.6); font-size: 13px; margin-top: 4px; }

        /* Scroll indicator */
        .scroll-hint {
            position: absolute; bottom: 180px; right: 40px; z-index: 10;
            display: flex; flex-direction: column; align-items: center; gap: 8px;
            color: rgba(255,255,255,0.4); font-size: 11px; letter-spacing: 0.1em;
            text-transform: uppercase; animation: bounce 2s infinite;
        }

        .scroll-hint .line {
            width: 1px; height: 50px;
            background: linear-gradient(to bottom, rgba(255,255,255,0.4), transparent);
        }

        /* ========== TICKER ========== */
        .ticker-bar {
            background: var(--navy);
            padding: 12px 0;
            overflow: hidden;
            position: relative;
        }

        .ticker-track {
            display: flex; gap: 60px;
            animation: ticker 30s linear infinite;
            white-space: nowrap;
        }

        .ticker-item {
            display: flex; align-items: center; gap: 10px;
            color: rgba(255,255,255,0.7); font-size: 13px;
        }

        .ticker-item .currency { color: var(--accent); font-weight: 700; }
        .ticker-item .up { color: var(--emerald); }
        .ticker-item .down { color: var(--rose); }
        .ticker-sep { color: rgba(255,255,255,0.2); }

        /* ========== SERVICES ========== */
        .section { padding: 100px 0; }
        .section-alt { background: white; }

        .wrap { max-width: 1400px; margin: 0 auto; padding: 0 32px; }

        .section-header {
            display: flex; align-items: flex-end; justify-content: space-between;
            margin-bottom: 64px;
        }

        .section-eyebrow {
            font-size: 11px; font-weight: 700; letter-spacing: 0.15em;
            text-transform: uppercase; color: var(--blue-mid);
            margin-bottom: 14px;
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(32px, 4vw, 52px);
            font-weight: 800; color: var(--navy);
            line-height: 1.1; max-width: 520px;
        }

        .section-title em { font-style: normal; color: var(--blue-mid); }
        .section-desc { color: var(--gray-600); font-size: 16px; line-height: 1.7; max-width: 380px; }

        /* Service Cards */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
        }

        .svc-card {
            background: white;
            border-radius: 24px;
            padding: 36px 28px;
            border: 1px solid var(--gray-200);
            transition: var(--transition);
            position: relative; overflow: hidden;
            cursor: pointer;
        }

        .svc-card::before {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--blue-mid), var(--sky));
            transform: scaleX(0);
            transition: transform 0.4s ease;
            transform-origin: left;
        }

        .svc-card:hover::before { transform: scaleX(1); }
        .svc-card:hover { transform: translateY(-12px); box-shadow: var(--shadow); border-color: transparent; }

        .svc-icon {
            width: 64px; height: 64px;
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            font-size: 28px; color: var(--blue-mid);
            margin-bottom: 24px;
            transition: var(--transition);
        }

        .svc-card:hover .svc-icon {
            background: linear-gradient(135deg, var(--navy), var(--blue-mid));
            color: white; transform: scale(1.1) rotate(-5deg);
        }

        .svc-title { font-size: 18px; font-weight: 700; color: var(--navy); margin-bottom: 12px; }
        .svc-desc { font-size: 14px; color: var(--gray-600); line-height: 1.7; }

        .svc-arrow {
            display: inline-flex; align-items: center; gap: 6px;
            color: var(--blue-mid); font-size: 13px; font-weight: 600;
            margin-top: 20px; text-decoration: none;
            opacity: 0; transform: translateX(-8px);
            transition: var(--transition);
        }

        .svc-card:hover .svc-arrow { opacity: 1; transform: translateX(0); }

        /* ========== CARDS LIKE IMAGE (Course Cards) ========== */
        .offers-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 28px;
        }

        .offer-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid var(--gray-200);
            transition: var(--transition);
            position: relative;
        }

        .offer-card:hover { transform: translateY(-8px); box-shadow: var(--shadow); }

        .offer-img {
            height: 200px;
            background-size: cover; background-position: center;
            position: relative; overflow: hidden;
        }

        .offer-img::after {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(to bottom, transparent 40%, rgba(10,22,40,0.6));
        }

        .offer-badge {
            position: absolute; top: 16px; left: 16px;
            background: var(--accent);
            color: var(--navy); font-size: 11px; font-weight: 700;
            padding: 5px 12px; border-radius: 20px;
            z-index: 2; text-transform: uppercase; letter-spacing: 0.05em;
        }

        .offer-body { padding: 28px; }

        .offer-code {
            font-size: 11px; font-weight: 700; letter-spacing: 0.1em;
            color: var(--blue-mid); text-transform: uppercase; margin-bottom: 10px;
        }

        .offer-title { font-size: 18px; font-weight: 700; color: var(--navy); margin-bottom: 12px; line-height: 1.35; }
        .offer-desc { font-size: 14px; color: var(--gray-600); line-height: 1.7; }

        .offer-footer {
            padding: 18px 28px;
            border-top: 1px solid var(--gray-100);
            display: flex; align-items: center; justify-content: space-between;
        }

        .offer-meta { font-size: 12px; color: var(--gray-400); }
        .offer-meta strong { color: var(--navy); font-weight: 700; font-size: 15px; }

        .btn-offer {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 10px 20px;
            background: linear-gradient(135deg, var(--navy), var(--blue-mid));
            color: white; border-radius: 30px;
            text-decoration: none; font-size: 13px; font-weight: 600;
            transition: var(--transition);
        }

        .btn-offer:hover { transform: scale(1.05); box-shadow: 0 6px 20px rgba(30,58,138,0.4); }

        /* ========== CAROUSEL ========== */
        .carousel-section { background: var(--navy); padding: 100px 0; overflow: hidden; }

        .carousel-track-wrap { position: relative; overflow: hidden; }

        .carousel-track {
            display: flex; gap: 24px;
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .carousel-card {
            min-width: 360px;
            border-radius: 24px; overflow: hidden;
            position: relative; flex-shrink: 0;
        }

        .carousel-card-img {
            width: 100%; height: 260px;
            background-size: cover; background-position: center;
            display: block;
        }

        .carousel-card-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(to top, rgba(10,22,40,0.95) 0%, transparent 55%);
        }

        .carousel-card-content {
            position: absolute; bottom: 0; left: 0; right: 0;
            padding: 28px;
        }

        .carousel-card-tag {
            display: inline-block;
            background: var(--accent); color: var(--navy);
            font-size: 11px; font-weight: 700; padding: 4px 12px;
            border-radius: 20px; margin-bottom: 12px;
            text-transform: uppercase;
        }

        .carousel-card-title { font-size: 20px; font-weight: 700; color: white; margin-bottom: 8px; }
        .carousel-card-desc { font-size: 13px; color: rgba(255,255,255,0.65); line-height: 1.6; }

        .carousel-nav {
            display: flex; justify-content: center; align-items: center;
            gap: 16px; margin-top: 40px;
        }

        .c-btn {
            width: 50px; height: 50px;
            background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: white; cursor: pointer; transition: var(--transition);
            backdrop-filter: blur(8px);
        }

        .c-btn:hover { background: var(--accent); border-color: var(--accent); color: var(--navy); }

        .c-dots { display: flex; gap: 8px; }

        .c-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: rgba(255,255,255,0.3); cursor: pointer; transition: var(--transition);
        }

        .c-dot.active { background: var(--accent); width: 28px; border-radius: 4px; }

        /* ========== RATES SECTION ========== */
        .rates-section {
            background: linear-gradient(135deg, var(--navy) 0%, #0d2150 50%, var(--blue) 100%);
            padding: 100px 0;
            position: relative; overflow: hidden;
        }

        .rates-section::before {
            content: '';
            position: absolute; top: -40%; right: -20%;
            width: 700px; height: 700px;
            background: radial-gradient(circle, rgba(29,78,216,0.3) 0%, transparent 70%);
            pointer-events: none;
        }

        .rates-section::after {
            content: '';
            position: absolute; bottom: -40%; left: -20%;
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(245,158,11,0.1) 0%, transparent 70%);
            pointer-events: none;
        }

        .rates-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-top: 60px; position: relative; z-index: 2; }

        .rates-table-wrap {
            background: rgba(255,255,255,0.06);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 24px; padding: 36px;
        }

        .rates-table-wrap h3 {
            font-size: 20px; font-weight: 700; color: white;
            margin-bottom: 28px; display: flex; align-items: center; gap: 10px;
        }

        .rates-table { width: 100%; border-collapse: collapse; }

        .rates-table th {
            text-align: left; padding: 12px 16px;
            background: rgba(255,255,255,0.08);
            color: rgba(255,255,255,0.6); font-size: 12px; font-weight: 700;
            letter-spacing: 0.08em; text-transform: uppercase;
        }

        .rates-table th:first-child { border-radius: 10px 0 0 10px; }
        .rates-table th:last-child { border-radius: 0 10px 10px 0; }

        .rates-table td {
            padding: 16px; color: white; font-size: 16px; font-weight: 600;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .rates-table tr:last-child td { border-bottom: none; }

        .currency-flag {
            display: flex; align-items: center; gap: 10px;
        }

        .flag-dot {
            width: 36px; height: 36px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; background: rgba(255,255,255,0.1);
        }

        .change-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600;
        }

        .change-badge.up { background: rgba(16,185,129,0.15); color: var(--emerald); }
        .change-badge.down { background: rgba(239,68,68,0.15); color: var(--rose); }

        /* Converter */
        .converter-wrap {
            background: rgba(255,255,255,0.06);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 24px; padding: 36px;
        }

        .converter-wrap h3 {
            font-size: 20px; font-weight: 700; color: white;
            margin-bottom: 28px; display: flex; align-items: center; gap: 10px;
        }

        .conv-group { margin-bottom: 18px; }

        .conv-group label {
            display: block; font-size: 12px; font-weight: 600; letter-spacing: 0.06em;
            color: rgba(255,255,255,0.5); text-transform: uppercase; margin-bottom: 8px;
        }

        .conv-input-wrap {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 14px; padding: 14px 18px;
            display: flex; align-items: center; gap: 12px;
        }

        .conv-input-wrap input,
        .conv-input-wrap select {
            background: transparent; border: none; outline: none;
            color: white; font-size: 20px; font-weight: 700;
            font-family: 'DM Sans', sans-serif;
            flex: 1; width: 100%;
        }

        .conv-input-wrap select option { background: var(--navy-mid); }
        .conv-input-wrap input::placeholder { color: rgba(255,255,255,0.3); }

        .conv-result {
            background: rgba(245,158,11,0.12);
            border: 1px solid rgba(245,158,11,0.25);
            border-radius: 14px; padding: 22px;
            text-align: center; margin: 20px 0;
        }

        .conv-result .amount { font-size: 36px; font-weight: 900; color: var(--accent); font-family: 'Playfair Display', serif; }
        .conv-result .curr { font-size: 14px; color: rgba(255,255,255,0.5); margin-top: 6px; }

        .btn-convert {
            width: 100%; padding: 16px;
            background: linear-gradient(135deg, var(--accent), var(--gold));
            color: var(--navy); border: none; border-radius: 14px;
            font-size: 16px; font-weight: 700; cursor: pointer;
            transition: var(--transition); font-family: 'DM Sans', sans-serif;
        }

        .btn-convert:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(245,158,11,0.4); }

        /* ========== PARALLAX FEATURE SECTION ========== */
        .feature-section {
            position: relative; padding: 120px 0;
            overflow: hidden; background: white;
        }

        .feature-parallax-bg {
            position: absolute; inset: 0;
            background: url('https://images.unsplash.com/photo-1554224155-6726b3ff858f?w=1600&auto=format') center/cover no-repeat;
            opacity: 0.04;
            will-change: transform;
        }

        .feature-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 80px;
            align-items: center; position: relative; z-index: 2;
        }

        .feature-img-wrap {
            position: relative;
        }

        .feature-img-main {
            width: 100%; height: 520px;
            background: url('https://images.unsplash.com/photo-1601597111158-2fceff292cdc?w=900&auto=format') center/cover no-repeat;
            border-radius: 28px;
            box-shadow: var(--shadow-lg);
        }

        .feature-img-card {
            position: absolute; bottom: -30px; right: -30px;
            background: white; border-radius: 20px;
            padding: 22px 28px;
            box-shadow: 0 20px 50px rgba(10,22,40,0.15);
            min-width: 220px;
        }

        .feature-img-card .label { font-size: 12px; color: var(--gray-400); }
        .feature-img-card .value { font-size: 28px; font-weight: 900; color: var(--navy); font-family: 'Playfair Display', serif; margin: 4px 0; }
        .feature-img-card .trend { color: var(--emerald); font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 4px; }

        .feature-img-card2 {
            position: absolute; top: -20px; left: -20px;
            background: linear-gradient(135deg, var(--navy), var(--blue-mid));
            border-radius: 20px; padding: 20px;
            color: white;
        }

        .feature-img-card2 i { font-size: 28px; color: var(--accent); margin-bottom: 8px; }
        .feature-img-card2 .t { font-size: 12px; opacity: 0.7; }
        .feature-img-card2 .n { font-size: 20px; font-weight: 800; }

        .feature-list { list-style: none; display: flex; flex-direction: column; gap: 16px; margin: 32px 0; }

        .feature-item {
            display: flex; align-items: flex-start; gap: 16px;
            padding: 20px;
            background: var(--gray-100); border-radius: 16px;
            transition: var(--transition);
        }

        .feature-item:hover { background: #eff6ff; transform: translateX(6px); }

        .feature-item-icon {
            width: 44px; height: 44px; flex-shrink: 0;
            background: linear-gradient(135deg, var(--navy), var(--blue-mid));
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 18px;
        }

        .feature-item-text .t { font-size: 15px; font-weight: 700; color: var(--navy); margin-bottom: 4px; }
        .feature-item-text .d { font-size: 13px; color: var(--gray-600); line-height: 1.6; }

        /* ========== FAQ ========== */
        .faq-section { padding: 100px 0; background: var(--gray-100); }

        .faq-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 60px;
            align-items: start;
        }

        .faq-list { display: flex; flex-direction: column; gap: 12px; }

        .faq-item {
            background: white; border-radius: 18px;
            border: 1px solid var(--gray-200);
            overflow: hidden; transition: var(--transition);
        }

        .faq-item.open { box-shadow: 0 8px 30px rgba(10,22,40,0.08); }

        .faq-q {
            display: flex; justify-content: space-between; align-items: center;
            padding: 22px 26px; cursor: pointer;
            font-weight: 600; font-size: 15px; color: var(--navy);
        }

        .faq-q .icon {
            width: 32px; height: 32px;
            background: var(--gray-100); border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: var(--blue-mid); font-size: 14px; flex-shrink: 0;
            transition: var(--transition);
        }

        .faq-item.open .faq-q .icon { background: var(--blue-mid); color: white; transform: rotate(45deg); }

        .faq-a {
            max-height: 0; overflow: hidden; transition: max-height 0.4s ease, padding 0.3s ease;
            padding: 0 26px; color: var(--gray-600); font-size: 14px; line-height: 1.8;
        }

        .faq-item.open .faq-a { max-height: 200px; padding: 0 26px 22px; }

        .faq-aside {
            position: sticky; top: 100px;
        }

        .faq-aside-card {
            background: linear-gradient(135deg, var(--navy), var(--blue-mid));
            border-radius: 28px; padding: 48px 40px;
            color: white; text-align: center;
        }

        .faq-aside-card i { font-size: 48px; color: var(--accent); margin-bottom: 20px; }
        .faq-aside-card h3 { font-family: 'Playfair Display', serif; font-size: 26px; font-weight: 800; margin-bottom: 14px; }
        .faq-aside-card p { color: rgba(255,255,255,0.7); font-size: 15px; line-height: 1.7; margin-bottom: 28px; }

        .btn-contact {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 15px 32px;
            background: var(--gray-200);
            border-radius: 40px; text-decoration: none;
            font-weight: 700; font-size: 15px;
            transition: var(--transition);
        }

        .btn-contact:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(245,158,11,0.5); }

        /* ========== TESTIMONIALS ========== */
        .testi-section { padding: 100px 0; background: white; }

        .testi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }

        .testi-card {
            background: var(--gray-100); border-radius: 24px; padding: 32px;
            border: 1px solid var(--gray-200); transition: var(--transition);
            position: relative;
        }

        .testi-card:hover { background: white; box-shadow: var(--shadow); transform: translateY(-6px); }

        .testi-quote { font-size: 48px; color: var(--blue-mid); opacity: 0.15; line-height: 1; margin-bottom: -16px; font-family: Georgia, serif; }

        .testi-text { font-size: 15px; color: var(--gray-600); line-height: 1.8; margin-bottom: 24px; font-style: italic; }

        .testi-author { display: flex; align-items: center; gap: 14px; }

        .testi-avatar {
            width: 48px; height: 48px; border-radius: 50%;
            background: linear-gradient(135deg, var(--navy), var(--blue-mid));
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 18px; font-weight: 800;
            font-family: 'Playfair Display', serif; flex-shrink: 0;
        }

        .testi-name { font-weight: 700; color: var(--navy); font-size: 15px; }
        .testi-role { font-size: 12px; color: var(--gray-400); }

        .testi-stars { color: var(--accent); font-size: 13px; margin-bottom: 16px; }

        /* ========== CTA SECTION ========== */
        .cta-section {
            padding: 120px 0;
            background: linear-gradient(135deg, var(--navy) 0%, #0d2150 100%);
            position: relative; overflow: hidden; text-align: center;
        }

        .cta-section::before {
            content: '';
            position: absolute; inset: 0;
            background: url('https://images.unsplash.com/photo-1559526324-4b87b5e36e44?w=1800&auto=format') center/cover;
            opacity: 0.06;
        }

        .cta-section .wrap { position: relative; z-index: 2; }

        .cta-section h2 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(36px, 5vw, 64px); font-weight: 900;
            color: white; margin-bottom: 22px; line-height: 1.1;
        }

        .cta-section h2 em { font-style: normal; color: var(--accent); }
        .cta-section p { color: rgba(255,255,255,0.65); font-size: 18px; max-width: 520px; margin: 0 auto 44px; line-height: 1.7; }

        .cta-btns { display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; }

        /* ========== FOOTER ========== */
        .footer { background: var(--navy); color: white; padding: 80px 0 0; }

        .footer-grid { display: grid; grid-template-columns: 2.5fr 1fr 1fr 1.5fr; gap: 48px; padding-bottom: 60px; }

        .footer-brand p { color: rgba(255,255,255,0.5); font-size: 14px; line-height: 1.8; margin: 20px 0 28px; max-width: 300px; }

        .footer-socials { display: flex; gap: 10px; }

        .social-btn {
            width: 40px; height: 40px;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: rgba(255,255,255,0.5); text-decoration: none;
            transition: var(--transition);
        }

        .social-btn:hover { background: var(--blue-mid); border-color: var(--blue-mid); color: white; transform: translateY(-3px); }

        .footer-col h4 { font-size: 14px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: rgba(255,255,255,0.4); margin-bottom: 22px; }

        .footer-col ul { list-style: none; display: flex; flex-direction: column; gap: 12px; }

        .footer-col a { color: rgba(255,255,255,0.6); text-decoration: none; font-size: 14px; transition: var(--transition); display: flex; align-items: center; gap: 6px; }

        .footer-col a:hover { color: white; padding-left: 4px; }

        .footer-contact-item { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 16px; }
        .footer-contact-item i { color: var(--accent); width: 18px; margin-top: 2px; flex-shrink: 0; }
        .footer-contact-item span { color: rgba(255,255,255,0.6); font-size: 14px; line-height: 1.6; }

        .footer-bar {
            border-top: 1px solid rgba(255,255,255,0.08);
            padding: 24px 0;
            display: flex; justify-content: space-between; align-items: center;
        }

        .footer-bar p { color: rgba(255,255,255,0.35); font-size: 13px; }

        .footer-bar-links { display: flex; gap: 28px; }
        .footer-bar-links a { color: rgba(255,255,255,0.35); font-size: 13px; text-decoration: none; transition: var(--transition); }
        .footer-bar-links a:hover { color: white; }

        /* ========== ANIMATIONS ========== */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(10px); }
        }

        @keyframes ticker {
            from { transform: translateX(0); }
            to { transform: translateX(-50%); }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-12px); }
        }

        /* Reveal animations */
        .reveal { opacity: 0; transform: translateY(40px); transition: opacity 0.7s ease, transform 0.7s ease; }
        .reveal.visible { opacity: 1; transform: translateY(0); }
        .reveal-left { opacity: 0; transform: translateX(-40px); transition: opacity 0.7s ease, transform 0.7s ease; }
        .reveal-left.visible { opacity: 1; transform: translateX(0); }
        .reveal-right { opacity: 0; transform: translateX(40px); transition: opacity 0.7s ease, transform 0.7s ease; }
        .reveal-right.visible { opacity: 1; transform: translateX(0); }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1100px) {
            .services-grid { grid-template-columns: repeat(2, 1fr); }
            .offers-grid { grid-template-columns: repeat(2, 1fr); }
            .testi-grid { grid-template-columns: 1fr 1fr; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
            .feature-grid { grid-template-columns: 1fr; gap: 48px; }
            .feature-img-wrap { display: none; }
            .rates-grid { grid-template-columns: 1fr; }
            .faq-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .nav-center { display: none; }
            .mobile-toggle { display: block; }
            .section-header { flex-direction: column; align-items: flex-start; gap: 16px; }
            .hero-stats-inner { flex-direction: column; gap: 2px; }
            .stat-card { border-radius: 0 !important; }
            .stat-card:first-child { border-radius: 20px 20px 0 0 !important; }
            .stat-card:last-child { border-radius: 0 0 20px 20px !important; }
            .services-grid { grid-template-columns: 1fr; }
            .offers-grid { grid-template-columns: 1fr; }
            .testi-grid { grid-template-columns: 1fr; }
            .footer-grid { grid-template-columns: 1fr; }
            .footer-bar { flex-direction: column; gap: 12px; text-align: center; }
            .hero-stats { bottom: 20px; }
            .dropdown { min-width: 280px; left: -20px; transform: none; }
            .nav-item:hover .dropdown { transform: none; }
        }

        /* Mobile nav */
        .mobile-nav {
            display: none;
            position: fixed; inset: 0; z-index: 8999;
            background: rgba(10,22,40,0.95); backdrop-filter: blur(20px);
            flex-direction: column; align-items: center; justify-content: center; gap: 24px;
        }

        .mobile-nav.open { display: flex; }

        .mobile-nav a {
            font-family: 'Playfair Display', serif;
            font-size: 28px; font-weight: 700; color: white; text-decoration: none;
            transition: var(--transition);
        }

        .mobile-nav a:hover { color: var(--accent); }

        .mobile-close {
            position: absolute; top: 28px; right: 28px;
            background: none; border: none; color: white; font-size: 28px; cursor: pointer;
        }
    </style>
</head>
<body>

<!-- ===== MOBILE NAV ===== -->
<div class="mobile-nav" id="mobileNav">
    <button class="mobile-close" onclick="closeMobileNav()"><i class="fas fa-times"></i></button>
    <a href="index.php">Accueil</a>
    <a href="#services">Services</a>
    <a href="#rates">Taux</a>
    <a href="#about">À propos</a>
    <a href="#faq">FAQ</a>
    <a href="#contact">Contact</a>
    <a href="main/index.php" style="color: var(--accent);">Espace Client →</a>
</div>

<!-- ===== NAVBAR ===== -->
<nav class="navbar" id="navbar">
    <div class="navbar-inner">
        <div class="nav-wrap">
            <a href="index.php" class="logo">
                <div class="logo-mark">
                    <i class="fas fa-building-columns" style="font-size:20px;"></i>
                </div>
                <div class="logo-name">
                    <div class="brand">S&P <span>illico</span></div>
                    <div class="tagline">Banque Communautaire</div>
                </div>
            </a>

            <div class="nav-center">
                <!-- Accueil -->
                <div class="nav-item">
                    <a href="index.php" class="nav-link active">Accueil</a>
                </div>

                <!-- Services dropdown -->
                <div class="nav-item">
                    <a href="#services" class="nav-link">Services <i class="fas fa-chevron-down chevron"></i></a>
                    <div class="dropdown">
                        <div class="dropdown-cols">
                            <div class="dropdown-col-header">Nos Services</div>
                            <div class="dropdown-divider">
                                <a href="main/index.php" class="dropdown-item">
                                    <div class="dropdown-item-icon"><i class="fas fa-piggy-bank"></i></div>
                                    <div class="dropdown-item-text"><div class="title">Compte Épargne</div><div class="desc">Taux compétitifs</div></div>
                                </a>
                                <a href="main/index.php" class="dropdown-item">
                                    <div class="dropdown-item-icon"><i class="fas fa-credit-card"></i></div>
                                    <div class="dropdown-item-text"><div class="title">Compte Courant</div><div class="desc">Gestion quotidienne</div></div>
                                </a>
                                <a href="main/index.php" class="dropdown-item">
                                    <div class="dropdown-item-icon"><i class="fas fa-exchange-alt"></i></div>
                                    <div class="dropdown-item-text"><div class="title">Virements</div><div class="desc">Rapide et sécurisé</div></div>
                                </a>
                                <a href="main/index.php" class="dropdown-item">
                                    <div class="dropdown-item-icon"><i class="fas fa-hand-holding-usd"></i></div>
                                    <div class="dropdown-item-text"><div class="title">Prêts & Crédits</div><div class="desc">Financement sur mesure</div></div>
                                </a>
                                <a href="#rates" class="dropdown-item">
                                    <div class="dropdown-item-icon"><i class="fas fa-chart-line"></i></div>
                                    <div class="dropdown-item-text"><div class="title">Change de devises</div><div class="desc">USD, EUR, CAD</div></div>
                                </a>
                                <a href="main/index.php" class="dropdown-item">
                                    <div class="dropdown-item-icon"><i class="fas fa-mobile-alt"></i></div>
                                    <div class="dropdown-item-text"><div class="title">Banque en ligne</div><div class="desc">24/7 accessible</div></div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Taux -->
                <div class="nav-item">
                    <a href="#rates" class="nav-link">Taux <i class="fas fa-chevron-down chevron"></i></a>
                    <div class="dropdown" style="min-width: 320px;">
                        <div class="dropdown-cols">
                            <div class="dropdown-col-header">Taux du jour</div>
                            <div class="dropdown-divider" style="grid-template-columns: 1fr 1fr;">
                                <a href="#rates" class="dropdown-item">
                                    <div class="dropdown-item-icon"><i class="fas fa-dollar-sign"></i></div>
                                    <div class="dropdown-item-text"><div class="title">USD/HTG</div><div class="desc">132.50 / 135.75</div></div>
                                </a>
                                <a href="#rates" class="dropdown-item">
                                    <div class="dropdown-item-icon"><i class="fas fa-euro-sign"></i></div>
                                    <div class="dropdown-item-text"><div class="title">EUR/HTG</div><div class="desc">142.80 / 146.50</div></div>
                                </a>
                                <a href="#rates" class="dropdown-item">
                                    <div class="dropdown-item-icon" style="font-size:13px; font-weight:800;">CA$</div>
                                    <div class="dropdown-item-text"><div class="title">CAD/HTG</div><div class="desc">96.40 / 99.20</div></div>
                                </a>
                                <a href="#rates" class="dropdown-item">
                                    <div class="dropdown-item-icon"><i class="fas fa-calculator"></i></div>
                                    <div class="dropdown-item-text"><div class="title">Convertisseur</div><div class="desc">Calculez en direct</div></div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- À propos -->
                <div class="nav-item">
                    <a href="#about" class="nav-link">À propos <i class="fas fa-chevron-down chevron"></i></a>
                    <div class="dropdown" style="min-width: 380px;">
                        <div class="dropdown-cols">
                            <div class="dropdown-col-header">Notre institution</div>
                            <div class="dropdown-divider" style="grid-template-columns: 1fr 1fr;">
                                <a href="#about" class="dropdown-item">
                                    <div class="dropdown-item-icon"><i class="fas fa-landmark"></i></div>
                                    <div class="dropdown-item-text"><div class="title">Notre histoire</div><div class="desc">Depuis 2015</div></div>
                                </a>
                                <a href="#about" class="dropdown-item">
                                    <div class="dropdown-item-icon"><i class="fas fa-users"></i></div>
                                    <div class="dropdown-item-text"><div class="title">Notre équipe</div><div class="desc">Professionnels dévoués</div></div>
                                </a>
                                <a href="#about" class="dropdown-item">
                                    <div class="dropdown-item-icon"><i class="fas fa-shield-alt"></i></div>
                                    <div class="dropdown-item-text"><div class="title">Sécurité & Conformité</div><div class="desc">Certifié & réglementé</div></div>
                                </a>
                                <a href="#contact" class="dropdown-item">
                                    <div class="dropdown-item-icon"><i class="fas fa-map-marker-alt"></i></div>
                                    <div class="dropdown-item-text"><div class="title">Nos agences</div><div class="desc">Terrier-Rouge, Haïti</div></div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="nav-item"><a href="#faq" class="nav-link">FAQ</a></div>
                <div class="nav-item"><a href="#contact" class="nav-link">Contact</a></div>
            </div>

            <div class="nav-cta">
                <a href="main/index.php" class="btn-space">
                    <i class="fas fa-lock"></i> Espace Client
                </a>
                <button class="mobile-toggle" onclick="openMobileNav()">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </div>
</nav>

<!-- ===== HERO ===== -->
<section class="hero" id="home">
    <div class="hero-bg" id="heroBg"></div>
    <div class="hero-overlay"></div>

    <div class="hero-content">
        <div class="hero-badge">
            <i class="fas fa-star" style="font-size:10px;"></i>
            Banque Communautaire A Terrier-Rouge
        </div>
        <h1 class="hero-title">
            Votre avenir financier<br>commence <em>ici</em>.
        </h1>
        <p class="hero-sub">
            Des services bancaires modernes, accessibles et sécurisés pour toute la communauté haïtienne.
        </p>
        <div class="hero-actions">
            <a href="main/index.php" class="btn-hero-primary">
                <i class="fas fa-user-plus"></i> Se Connecter
            </a>
            <a href="#services" class="btn-hero-ghost">
                <i class="fas fa-play-circle"></i> Découvrir nos services
            </a>
        </div>
    </div>

   

    <div class="scroll-hint">
        <span>Défiler</span>
        <div class="line"></div>
    </div>
</section>

<!-- ===== TICKER ===== -->
<div class="ticker-bar">
    <div class="ticker-track" id="tickerTrack">
        <div class="ticker-item"><span class="currency">USD/HTG</span><span>Achat: 132.50</span><span class="up">▲ +0.8%</span></div>
        <span class="ticker-sep">•</span>
        <div class="ticker-item"><span class="currency">EUR/HTG</span><span>Achat: 142.80</span><span class="down">▼ -0.3%</span></div>
        <span class="ticker-sep">•</span>
        <div class="ticker-item"><span class="currency">CAD/HTG</span><span>Achat: 96.40</span><span class="up">▲ +0.2%</span></div>
        <span class="ticker-sep">•</span>
        <div class="ticker-item"><i class="fas fa-clock" style="color:var(--accent);"></i> Mise à jour: <?= $date_taux ?> — 09:00</div>
        <span class="ticker-sep">•</span>
        <div class="ticker-item"><span class="currency">USD/HTG</span><span>Achat: 132.50</span><span class="up">▲ +0.8%</span></div>
        <span class="ticker-sep">•</span>
        <div class="ticker-item"><span class="currency">EUR/HTG</span><span>Achat: 142.80</span><span class="down">▼ -0.3%</span></div>
        <span class="ticker-sep">•</span>
        <div class="ticker-item"><span class="currency">CAD/HTG</span><span>Achat: 96.40</span><span class="up">▲ +0.2%</span></div>
        <span class="ticker-sep">•</span>
        <div class="ticker-item"><i class="fas fa-clock" style="color:var(--accent);"></i> Mise à jour: <?= $date_taux ?> — 09:00</div>
    </div>
</div>

<!-- ===== SERVICES ===== -->
<section class="section section-alt" id="services">
    <div class="wrap">
        <div class="section-header">
            <div>
                <div class="section-eyebrow">Ce que nous offrons</div>
                <h2 class="section-title">Des services bancaires <em>complets</em></h2>
            </div>
            <p class="section-desc">Des solutions adaptées à chaque étape de votre vie financière.</p>
        </div>

        <div class="services-grid">
            <div class="svc-card reveal">
                <div class="svc-icon"><i class="fas fa-piggy-bank"></i></div>
                <h3 class="svc-title">Compte Épargne</h3>
                <p class="svc-desc">Faites fructifier votre argent avec nos comptes épargne à taux compétitifs et sans frais cachés.</p>
                <a href="main/index.php" class="svc-arrow">Commencer <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="svc-card reveal" style="transition-delay:0.1s">
                <div class="svc-icon"><i class="fas fa-credit-card"></i></div>
                <h3 class="svc-title">Compte Courant</h3>
                <p class="svc-desc">Gérez vos opérations quotidiennes avec flexibilité, simplicité et des outils numériques avancés.</p>
                <a href="main/index.php" class="svc-arrow">Découvrir <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="svc-card reveal" style="transition-delay:0.2s">
                <div class="svc-icon"><i class="fas fa-exchange-alt"></i></div>
                <h3 class="svc-title">Virements</h3>
                <p class="svc-desc">Transférez de l'argent rapidement et en toute sécurité entre comptes locaux et internationaux.</p>
                <a href="main/index.php" class="svc-arrow">En savoir plus <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="svc-card reveal" style="transition-delay:0.3s">
                <div class="svc-icon"><i class="fas fa-shield-alt"></i></div>
                <h3 class="svc-title">Sécurité 24/7</h3>
                <p class="svc-desc">Vos transactions et données sont protégées par des technologies de chiffrement de pointe.</p>
                <a href="#" class="svc-arrow">En savoir plus <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
</section>

<!-- ===== OFFER CARDS (like image) ===== -->
<section class="section" id="about">
    <div class="wrap">
        <div class="section-header">
            <div>
                <div class="section-eyebrow">Nos offres phares</div>
                <h2 class="section-title">Choisissez votre <em>formule</em></h2>
            </div>
            <p class="section-desc">Des produits financiers conçus pour répondre à vos besoins réels.</p>
        </div>

        <div class="offers-grid">
            <div class="offer-card reveal">
                <div class="offer-img" style="background-image:url('https://images.unsplash.com/photo-1579621970563-ebec7560ff3e?w=600&auto=format');">
                    <div class="offer-badge">Populaire</div>
                </div>
                <div class="offer-body">
                    <div class="offer-code">01 · Épargne</div>
                    <h3 class="offer-title">Compte Épargne Plus — Croissance garantie</h3>
                    <p class="offer-desc">Un compte épargne à taux progressif. Plus vous épargnez, plus vous gagnez. Aucun frais de tenue de compte.</p>
                </div>
                <div class="offer-footer">
                    <div class="offer-meta">Taux: <strong>0% / an</strong></div>
                    <a href="main/index.php" class="btn-offer"><i class="fas fa-arrow-right"></i> Ouvrir</a>
                </div>
            </div>

            <div class="offer-card reveal" style="transition-delay:0.15s">
                <div class="offer-img" style="background-image:url('https://images.unsplash.com/photo-1560520653-9e0e4c89eb11?w=600&auto=format');">
                    <div class="offer-badge">Nouveau</div>
                </div>
                <div class="offer-body">
                    <div class="offer-code">02 · Courant</div>
                    <h3 class="offer-title">Compte Courant Premium — Liberté totale</h3>
                    <p class="offer-desc">Gérez votre argent sans contraintes. Virements illimités, carte de débit incluse et accès en ligne 24h/24.</p>
                </div>
                <div class="offer-footer">
                    <div class="offer-meta">Frais: <strong>0 HTG / mois</strong></div>
                    <a href="main/index.php" class="btn-offer"><i class="fas fa-arrow-right"></i> Ouvrir</a>
                </div>
            </div>

            <div class="offer-card reveal" style="transition-delay:0.3s">
                <div class="offer-img" style="background-image:url('https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=600&auto=format');">
                    <div class="offer-badge">Exclusif</div>
                </div>
                <div class="offer-body">
                    <div class="offer-code">03 · Prêt</div>
                    <h3 class="offer-title">Prêt Communautaire — Financement solidaire</h3>
                    <p class="offer-desc">Accédez à un financement adapté à vos projets. Remboursement flexible et taux avantageux pour les membres.</p>
                </div>
                <div class="offer-footer">
                    <div class="offer-meta">À partir de <strong>0% / an</strong></div>
                    <a href="main/index.php" class="btn-offer"><i class="fas fa-arrow-right"></i> Demander</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== CAROUSEL ===== -->
<section class="carousel-section">
    <div class="wrap">
        <div class="section-header" style="margin-bottom:48px;">
            <div>
                <div class="section-eyebrow" style="color:var(--accent);">Notre actualité</div>
                <h2 class="section-title" style="color:white;">Ce qui se passe <em style="color:var(--accent)">chez nous</em></h2>
            </div>
            <p class="section-desc" style="color:rgba(255,255,255,0.55);">Découvrez les dernières nouvelles et événements de S&P illico.</p>
        </div>

        <div class="carousel-track-wrap">
            <div class="carousel-track" id="carouselTrack">
                <div class="carousel-card">
                    <div class="carousel-card-img" style="background-image:url('https://images.unsplash.com/photo-1521737711867-e3b97375f902?w=600&auto=format');"></div>
                    <div class="carousel-card-overlay"></div>
                    <div class="carousel-card-content">
                        <span class="carousel-card-tag">Actualité</span>
                        <div class="carousel-card-title">Lancement de notre application mobile</div>
                        <div class="carousel-card-desc">Gérez vos comptes depuis votre téléphone partout en Haïti.</div>
                    </div>
                </div>
                <div class="carousel-card">
                    <div class="carousel-card-img" style="background-image:url('https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=600&auto=format');"></div>
                    <div class="carousel-card-overlay"></div>
                    <div class="carousel-card-content">
                        <span class="carousel-card-tag">Promotion</span>
                        <div class="carousel-card-title">Taux épargne 0% jusqu'au 31 décembre</div>
                        <div class="carousel-card-desc">Profitez de notre offre spéciale fin d'année sur les comptes épargne.</div>
                    </div>
                </div>
                <div class="carousel-card">
                    <div class="carousel-card-img" style="background-image:url('https://images.unsplash.com/photo-1559526324-4b87b5e36e44?w=600&auto=format');"></div>
                    <div class="carousel-card-overlay"></div>
                    <div class="carousel-card-content">
                        <span class="carousel-card-tag">Communauté</span>
                        <div class="carousel-card-title">Programme d'éducation financière gratuit</div>
                        <div class="carousel-card-desc">Rejoignez nos ateliers mensuels ouverts à tous les membres.</div>
                    </div>
                </div>
                <div class="carousel-card">
                    <div class="carousel-card-img" style="background-image:url('https://images.unsplash.com/photo-1600880292203-757bb62b4baf?w=600&auto=format');"></div>
                    <div class="carousel-card-overlay"></div>
                    <div class="carousel-card-content">
                        <span class="carousel-card-tag">Partenariat</span>
                        <div class="carousel-card-title">Nouveau partenaire de transfert international</div>
                        <div class="carousel-card-desc">Recevez vos fonds de la diaspora sans frais supplémentaires.</div>
                    </div>
                </div>
                <div class="carousel-card">
                    <div class="carousel-card-img" style="background-image:url('https://images.unsplash.com/photo-1552664730-d307ca884978?w=600&auto=format');"></div>
                    <div class="carousel-card-overlay"></div>
                    <div class="carousel-card-content">
                        <span class="carousel-card-tag">Événement</span>
                        <div class="carousel-card-title">Foire aux crédits — Terrier-Rouge</div>
                        <div class="carousel-card-desc">Rencontrez nos conseillers et découvrez nos offres de prêt.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="carousel-nav">
            <button class="c-btn" onclick="moveCarousel(-1)"><i class="fas fa-chevron-left"></i></button>
            <div class="c-dots">
                <div class="c-dot active" data-idx="0"></div>
                <div class="c-dot" data-idx="1"></div>
                <div class="c-dot" data-idx="2"></div>
                <div class="c-dot" data-idx="3"></div>
                <div class="c-dot" data-idx="4"></div>
            </div>
            <button class="c-btn" onclick="moveCarousel(1)"><i class="fas fa-chevron-right"></i></button>
        </div>
    </div>
</section>

<!-- ===== RATES ===== -->
<section class="rates-section" id="rates">
    <div class="wrap">
        <div style="text-align:center; position:relative; z-index:2;">
            <div class="section-eyebrow" style="color:var(--accent);">Marché des devises</div>
            <h2 class="section-title" style="color:white; max-width:100%; text-align:center;">Taux du jour — <em style="color:var(--accent);"><?= $date_taux ?></em></h2>
        </div>

        <div class="rates-grid">
            <div class="rates-table-wrap reveal-left">
                <h3><i class="fas fa-chart-bar" style="color:var(--accent);"></i> Taux de change officiels</h3>
                <table class="rates-table">
                    <thead>
                        <tr>
                            <th>Devise</th>
                            <th>Achat (HTG)</th>
                            <th>Vente (HTG)</th>
                            <th>Variation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($taux_jour as $devise => $info): ?>
                        <tr>
                            <td>
                                <div class="currency-flag">
                                    <div class="flag-dot">
                                        <?php
                                        $flags = ['USD' => '🇺🇸', 'EUR' => '🇪🇺', 'CAD' => '🇨🇦'];
                                        echo $flags[$devise] ?? '🏳';
                                        ?>
                                    </div>
                                    <strong><?= $devise ?></strong>
                                </div>
                            </td>
                            <td><?= number_format($info['achat'], 2) ?></td>
                            <td><?= number_format($info['vente'], 2) ?></td>
                            <td>
                                <?php $isUp = strpos($info['change'], '+') !== false; ?>
                                <span class="change-badge <?= $isUp ? 'up' : 'down' ?>">
                                    <i class="fas fa-arrow-<?= $isUp ? 'up' : 'down' ?>"></i>
                                    <?= $info['change'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="margin-top:20px; color:rgba(255,255,255,0.4); font-size:12px;"><i class="far fa-clock"></i> Mis à jour aujourd'hui à 09:00 AM</p>
            </div>

            <div class="converter-wrap reveal-right">
                <h3><i class="fas fa-calculator" style="color:var(--accent);"></i> Convertisseur de devises</h3>

                <div class="conv-group">
                    <label>Montant à convertir</label>
                    <div class="conv-input-wrap">
                        <i class="fas fa-coins" style="color:var(--accent);"></i>
                        <input type="number" id="convAmount" value="100" placeholder="0.00">
                    </div>
                </div>

                <div class="conv-group">
                    <label>De la devise</label>
                    <div class="conv-input-wrap">
                        <i class="fas fa-globe" style="color:rgba(255,255,255,0.4);"></i>
                        <select id="convFrom">
                            <option value="USD">🇺🇸 USD — Dollar américain</option>
                            <option value="HTG">🇭🇹 HTG — Gourde haïtienne</option>
                            <option value="EUR">🇪🇺 EUR — Euro</option>
                            <option value="CAD">🇨🇦 CAD — Dollar canadien</option>
                        </select>
                    </div>
                </div>

                <div class="conv-group">
                    <label>Vers la devise</label>
                    <div class="conv-input-wrap">
                        <i class="fas fa-globe" style="color:rgba(255,255,255,0.4);"></i>
                        <select id="convTo">
                            <option value="HTG">🇭🇹 HTG — Gourde haïtienne</option>
                            <option value="USD">🇺🇸 USD — Dollar américain</option>
                            <option value="EUR">🇪🇺 EUR — Euro</option>
                            <option value="CAD">🇨🇦 CAD — Dollar canadien</option>
                        </select>
                    </div>
                </div>

                <div class="conv-result">
                    <div class="amount" id="convResult">13,250.00</div>
                    <div class="curr" id="convResultCurr">Gourdes Haïtiennes (HTG)</div>
                </div>

                <button class="btn-convert" onclick="doConvert()">
                    <i class="fas fa-sync-alt"></i> Convertir maintenant
                </button>
            </div>
        </div>
    </div>
</section>

<!-- ===== FEATURE / PARALLAX ===== -->
<section class="feature-section" id="features">
    <div class="feature-parallax-bg" id="featureBg"></div>
    <div class="wrap" style="position:relative;z-index:2;">
        <div class="feature-grid">
            <div class="feature-img-wrap reveal-left">
                <div class="feature-img-main"></div>
                <div class="feature-img-card" style="animation: float 4s ease-in-out infinite;">
                    <div class="label">D'abord</div>
                    <div class="value">L'humain<span style="font-size:18px;color:var(--accent)"></span></div>
                    <div class="trend"><i class="fas fa-arrow-up"></i> Le reste suit</div>
                </div>
                <div class="feature-img-card2" style="animation: float 4s ease-in-out 1s infinite;">
                    <i class="fas fa-shield-check"></i>
                    <div class="t">Sécurité</div>
                    <div class="n">256-bit SSL</div>
                </div>
            </div>

            <div class="reveal-right">
                <div class="section-eyebrow">Pourquoi nous choisir</div>
                <h2 class="section-title">Une banque <em>différente</em>, faite pour vous</h2>
                <p style="color:var(--gray-600); margin-top:16px; line-height:1.8;">S&P illico a été fondée avec une mission claire : rendre les services financiers accessibles à tous, sans exclusion, sans complexité.</p>

                <ul class="feature-list">
                    <li class="feature-item">
                        <div class="feature-item-icon"><i class="fas fa-bolt"></i></div>
                        <div class="feature-item-text">
                            <div class="t">Ouverture de compte en 5 minutes</div>
                            <div class="d">Processus 100% en ligne, sans paperasse ni attente.</div>
                        </div>
                    </li>
                    <li class="feature-item">
                        <div class="feature-item-icon"><i class="fas fa-hand-holding-heart"></i></div>
                        <div class="feature-item-text">
                            <div class="t">Zéro frais cachés</div>
                            <div class="d">Transparence totale sur tous nos tarifs et conditions.</div>
                        </div>
                    </li>
                    <li class="feature-item">
                        <div class="feature-item-icon"><i class="fas fa-headset"></i></div>
                        <div class="feature-item-text">
                            <div class="t">Support humain 7j/7</div>
                            <div class="d">Une équipe disponible de 8h à 20h pour vous accompagner.</div>
                        </div>
                    </li>
                    <li class="feature-item">
                        <div class="feature-item-icon"><i class="fas fa-globe-americas"></i></div>
                        <div class="feature-item-text">
                            <div class="t">Transferts internationaux</div>
                            <div class="d">Recevez l'argent de la diaspora sans frais excessifs.</div>
                        </div>
                    </li>
                </ul>

                <a href="main/index.php" class="btn-hero-primary" style="display:inline-flex; margin-top:8px;">
                    <i class="fas fa-user-plus"></i> Rejoindre maintenant
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ===== TESTIMONIALS ===== -->
<section class="testi-section">
    <div class="wrap">
        <div class="section-header">
            <div>
                <div class="section-eyebrow">Témoignages</div>
                <h2 class="section-title">Ils nous font <em>confiance</em></h2>
            </div>
            <p class="section-desc">Plus de 5 000 clients nous font déjà confiance. Voici ce qu'ils disent.</p>
        </div>

        <div class="testi-grid">
            <div class="testi-card reveal">
                <div class="testi-stars">★★★★★</div>
                <div class="testi-quote">"</div>
                <p class="testi-text">S&P illico a changé ma façon de gérer mon argent. L'ouverture du compte était rapide, le personnel très professionnel.</p>
                <div class="testi-author">
                    <div class="testi-avatar">M</div>
                    <div>
                        <div class="testi-name">Marie-Claire J.</div>
                        <div class="testi-role">Commerçante, Terrier-Rouge</div>
                    </div>
                </div>
            </div>

            <div class="testi-card reveal" style="transition-delay:0.15s">
                <div class="testi-stars">★★★★★</div>
                <div class="testi-quote">"</div>
                <p class="testi-text">Je reçois les virements de ma famille aux États-Unis sans problème. Les taux de change sont les meilleurs que j'ai trouvés.</p>
                <div class="testi-author">
                    <div class="testi-avatar">J</div>
                    <div>
                        <div class="testi-name">Jean-Baptiste A.</div>
                        <div class="testi-role">Agriculteur, Cap-Haïtien</div>
                    </div>
                </div>
            </div>

            <div class="testi-card reveal" style="transition-delay:0.3s">
                <div class="testi-stars">★★★★★</div>
                <div class="testi-quote">"</div>
                <p class="testi-text">Le service client est exceptionnel. Ils m'ont aidé à obtenir un prêt pour mon petit commerce. Merci S&P illico !</p>
                <div class="testi-author">
                    <div class="testi-avatar">R</div>
                    <div>
                        <div class="testi-name">Rose-Anne D.</div>
                        <div class="testi-role">Entrepreneur, Port-au-Prince</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== FAQ ===== -->
<section class="faq-section" id="faq">
    <div class="wrap">
        <div style="text-align:center; margin-bottom:64px;">
            <div class="section-eyebrow">Centre d'aide</div>
            <h2 class="section-title" style="max-width:100%; text-align:center;">Questions <em>fréquentes</em></h2>
        </div>

        <div class="faq-grid">
            <div class="faq-list">
                <div class="faq-item open">
                    <div class="faq-q" onclick="toggleFaq(this)">
                        Comment ouvrir un compte à S&P illico ?
                        <span class="icon"><i class="fas fa-plus"></i></span>
                    </div>
                    <div class="faq-a">
                        Vous pouvez ouvrir un compte en ligne en visitant notre espace client ou en vous rendant directement à notre agence à Terrier-Rouge. L'ouverture prend moins de 5 minutes avec une pièce d'identité valide.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-q" onclick="toggleFaq(this)">
                        Quels documents sont nécessaires pour l'ouverture ?
                        <span class="icon"><i class="fas fa-plus"></i></span>
                    </div>
                    <div class="faq-a">
                        Vous avez besoin d'une pièce d'identité nationale valide (CIN, passeport ou permis de conduire), d'un justificatif de domicile récent, et d'un dépôt initial minimum de 500 HTG.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-q" onclick="toggleFaq(this)">
                        Comment puis-je accéder à mon compte en ligne ?
                        <span class="icon"><i class="fas fa-plus"></i></span>
                    </div>
                    <div class="faq-a">
                        Accédez à votre espace client via le bouton "Espace Client" sur notre site. Après inscription, vous pouvez consulter vos soldes, effectuer des virements et suivre toutes vos opérations 24h/24.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-q" onclick="toggleFaq(this)">
                        Comment puis-je recevoir un virement de l'étranger ?
                        <span class="icon"><i class="fas fa-plus"></i></span>
                    </div>
                    <div class="faq-a">
                        S&P illico est connectée aux principaux réseaux de transfert international. Fournissez simplement votre numéro de compte et le code BIC/SWIFT à l'expéditeur. Les fonds arrivent généralement sous 24 à 48 heures.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-q" onclick="toggleFaq(this)">
                        Quels sont les taux d'intérêt sur l'épargne ?
                        <span class="icon"><i class="fas fa-plus"></i></span>
                    </div>
                    <div class="faq-a">
                        Nos taux d'épargne vont de 4.5% à 6.5% par an selon le type de compte et la durée de l'engagement. Consultez notre section "Taux du jour" pour les informations actualisées.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-q" onclick="toggleFaq(this)">
                        Mes fonds sont-ils sécurisés ?
                        <span class="icon"><i class="fas fa-plus"></i></span>
                    </div>
                    <div class="faq-a">
                        Absolument. S&P illico utilise un cryptage SSL 256 bits pour toutes les transactions. Vos fonds sont également couverts par notre système d'assurance dépôts conformément à la réglementation haïtienne.
                    </div>
                </div>
            </div>

            <div class="faq-aside">
                <div class="faq-aside-card">
                    <i class="fas fa-headset"></i>
                    <h3>Besoin d'aide personnalisée ?</h3>
                    <p>Nos conseillers sont disponibles du lundi au dimanche de 8h à 17h pour répondre à toutes vos questions.</p>
                    <a href="tel:+50933383509" class="btn-contact">
                        <i class="fas fa-phone"></i> +509 3338-3509
                    </a>
                    <div style="margin-top:16px;">
                        <a href="mailto:illicoms01@gmail.com" style="color:rgba(255,255,255,0.5); font-size:14px; text-decoration:none;">
                            <i class="fas fa-envelope"></i> illicoms01@gmail.com
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== CTA ===== -->
<section class="cta-section">
    <div class="wrap">
        <div class="section-eyebrow" style="color:var(--accent); margin-bottom:20px;">Rejoignez-nous aujourd'hui</div>
        <h2>Prêt à prendre le contrôle<br>de votre <em>avenir financier</em> ?</h2>
        <p>Ouvrez votre compte en moins de 5 minutes et profitez de tous nos services bancaires sans frais cachés.</p>
        <div class="cta-btns">
            <a href="main/index.php" class="btn-hero-primary">
                <i class="fas fa-user-plus"></i> Se Connecter
            </a>
            <a href="#contact" class="btn-hero-ghost">
                <i class="fas fa-phone"></i> Nous contacter
            </a>
        </div>
    </div>
</section>

<!-- ===== FOOTER ===== -->
<footer class="footer" id="contact">
    <div class="wrap">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="index.php" class="logo" style="margin-bottom:0;">
                    <div class="logo-mark"><i class="fas fa-building-columns" style="font-size:20px;"></i></div>
                    <div class="logo-name">
                        <div class="brand" style="color:white;">S&P <span>illico</span></div>
                        <div class="tagline">Banque Communautaire</div>
                    </div>
                </a>
                <p>Votre partenaire bancaire de confiance, engagé à offrir des services financiers accessibles et innovants à toute la communauté</p>
                <div class="footer-socials">
                    <a href="#" class="social-btn"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-btn"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-btn"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-btn"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>

            <div class="footer-col">
                <h4>Navigation</h4>
                <ul>
                    <li><a href="index.php"><i class="fas fa-chevron-right" style="font-size:10px;"></i> Accueil</a></li>
                    <li><a href="#services"><i class="fas fa-chevron-right" style="font-size:10px;"></i> Services</a></li>
                    <li><a href="#rates"><i class="fas fa-chevron-right" style="font-size:10px;"></i> Taux du jour</a></li>
                    <li><a href="#about"><i class="fas fa-chevron-right" style="font-size:10px;"></i> À propos</a></li>
                    <li><a href="#faq"><i class="fas fa-chevron-right" style="font-size:10px;"></i> FAQ</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Services</h4>
                <ul>
                    <li><a href="main/index.php"><i class="fas fa-chevron-right" style="font-size:10px;"></i> Espace Client</a></li>
                    <li><a href="main/index.php"><i class="fas fa-chevron-right" style="font-size:10px;"></i> Compte Épargne</a></li>
                    <li><a href="main/index.php"><i class="fas fa-chevron-right" style="font-size:10px;"></i> Compte Courant</a></li>
                    <li><a href="main/index.php"><i class="fas fa-chevron-right" style="font-size:10px;"></i> Prêts & Crédits</a></li>
                    <li><a href="#rates"><i class="fas fa-chevron-right" style="font-size:10px;"></i> Change de devises</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Contact</h4>
                <div class="footer-contact-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Quartier Muraille, Terrier-Rouge, Haïti</span>
                </div>
                <div class="footer-contact-item">
                    <i class="fas fa-phone"></i>
                    <span>+509 3338-3509</span>
                </div>
                <div class="footer-contact-item">
                    <i class="fas fa-envelope"></i>
                    <span>illicoms01@gmail.com</span>
                </div>
                <div class="footer-contact-item">
                    <i class="fas fa-clock"></i>
                    <span>Lun–Dim : 8h00 – 20h00</span>
                </div>
            </div>
        </div>

        <div class="footer-bar">
            <p>&copy; <?= date('Y') ?> S&P illico – Banque Communautaire. Tous droits réservés.</p>
            <div class="footer-bar-links">
                <a href="#">Confidentialité</a>
                <a href="#">Conditions</a>
                <a href="#">Mentions légales</a>
            </div>
        </div>
    </div>
</footer>

<script>
/* ===== NAVBAR SCROLL ===== */
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => {
    navbar.classList.toggle('scrolled', window.scrollY > 50);
});

/* ===== PARALLAX ===== */
const heroBg = document.getElementById('heroBg');
const featureBg = document.getElementById('featureBg');

window.addEventListener('scroll', () => {
    const scrollY = window.scrollY;
    if (heroBg) heroBg.style.transform = `scale(1.15) translateY(${scrollY * 0.4}px)`;
    if (featureBg) {
        const featureTop = featureBg.closest('section').getBoundingClientRect().top + scrollY;
        const offset = (scrollY - featureTop) * 0.3;
        featureBg.style.transform = `translateY(${offset}px)`;
    }
});

/* ===== MOBILE NAV ===== */
function openMobileNav() { document.getElementById('mobileNav').classList.add('open'); document.body.style.overflow='hidden'; }
function closeMobileNav() { document.getElementById('mobileNav').classList.remove('open'); document.body.style.overflow=''; }

/* ===== SCROLL REVEAL ===== */
const revealEls = document.querySelectorAll('.reveal, .reveal-left, .reveal-right');
const revealObs = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); revealObs.unobserve(e.target); } });
}, { threshold: 0.12 });
revealEls.forEach(el => revealObs.observe(el));

/* ===== CAROUSEL ===== */
let carIdx = 0;
const carTrack = document.getElementById('carouselTrack');
const carCards = carTrack.querySelectorAll('.carousel-card');
const carDots = document.querySelectorAll('.c-dot');
const CARD_WIDTH = 384; // 360 + 24 gap

function moveCarousel(dir) {
    carIdx = Math.max(0, Math.min(carIdx + dir, carCards.length - 1));
    carTrack.style.transform = `translateX(-${carIdx * CARD_WIDTH}px)`;
    carDots.forEach((d, i) => d.classList.toggle('active', i === carIdx));
}

carDots.forEach(d => d.addEventListener('click', () => {
    carIdx = parseInt(d.dataset.idx);
    moveCarousel(0);
}));

// Auto carousel
setInterval(() => moveCarousel(carIdx < carCards.length - 1 ? 1 : -carIdx), 4000);

/* ===== CONVERTER ===== */
const rates = <?= json_encode($taux_jour) ?>;
rates['HTG'] = { achat: 1, vente: 1 };

const currNames = { USD: 'Dollars américains (USD)', EUR: 'Euros (EUR)', CAD: 'Dollars canadiens (CAD)', HTG: 'Gourdes haïtiennes (HTG)' };

function doConvert() {
    const amount = parseFloat(document.getElementById('convAmount').value) || 0;
    const from = document.getElementById('convFrom').value;
    const to = document.getElementById('convTo').value;
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

    document.getElementById('convResult').textContent = result.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('convResultCurr').textContent = currNames[to] || to;
}

['convAmount','convFrom','convTo'].forEach(id => document.getElementById(id).addEventListener('input', doConvert));
doConvert();

/* ===== FAQ ===== */
function toggleFaq(el) {
    const item = el.closest('.faq-item');
    const isOpen = item.classList.contains('open');
    document.querySelectorAll('.faq-item.open').forEach(i => i.classList.remove('open'));
    if (!isOpen) item.classList.add('open');
}

/* ===== SMOOTH SCROLL ===== */
document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
        const target = document.querySelector(a.getAttribute('href'));
        if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth' }); }
    });
});
</script>
</body>
</html>