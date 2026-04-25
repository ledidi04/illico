<?php
session_start();

// ═══════════════════════════════════════════════════════════════
// DÉTECTION DYNAMIQUE DE LA RACINE DU SITE
// ═══════════════════════════════════════════════════════════════
$protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host      = $_SERVER['HTTP_HOST'];
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
$base_url  = ($script_dir === '/' || $script_dir === '\\') ? '/' : rtrim($script_dir, '/\\') . '/';
$full_base = $protocol . '://' . $host . $base_url;

// ═══════════════════════════════════════════════════════════════
// CODE & CONFIGURATION D'ERREUR
// ═══════════════════════════════════════════════════════════════
$error_code = (int)(http_response_code() ?: 500);
if (isset($_GET['code'])) {
    $error_code = (int)$_GET['code'];
}

$error_message = $_GET['message'] ?? '';
$error_type    = $_GET['type']    ?? '';

$error_config = [
    400 => [
        'title'    => 'Requête incorrecte',
        'message'  => 'La requête envoyée au serveur est invalide ou mal formée.',
        'icon'     => 'fa-exclamation-triangle',
        'accent'   => '#f59e0b',
        'gradient' => 'linear-gradient(135deg,#78350f,#92400e)',
        'emoji'    => '⚠️',
    ],
    401 => [
        'title'    => 'Non autorisé',
        'message'  => 'Vous devez être authentifié pour accéder à cette ressource.',
        'icon'     => 'fa-lock',
        'accent'   => '#ef4444',
        'gradient' => 'linear-gradient(135deg,#7f1d1d,#991b1b)',
        'emoji'    => '🔒',
    ],
    403 => [
        'title'    => 'Accès interdit',
        'message'  => "Vous n'avez pas les permissions nécessaires pour accéder à cette page.",
        'icon'     => 'fa-ban',
        'accent'   => '#ef4444',
        'gradient' => 'linear-gradient(135deg,#7f1d1d,#b91c1c)',
        'emoji'    => '🚫',
    ],
    404 => [
        'title'    => 'Page introuvable',
        'message'  => "La page que vous recherchez a été déplacée, supprimée ou n'a jamais existé.",
        'icon'     => 'fa-compass',
        'accent'   => '#f59e0b',
        'gradient' => 'linear-gradient(135deg,#451a03,#78350f)',
        'emoji'    => '🧭',
    ],
    408 => [
        'title'    => "Délai d'attente dépassé",
        'message'  => 'Le serveur a mis trop de temps à répondre. Veuillez réessayer.',
        'icon'     => 'fa-hourglass-half',
        'accent'   => '#f59e0b',
        'gradient' => 'linear-gradient(135deg,#422006,#713f12)',
        'emoji'    => '⏱️',
    ],
    429 => [
        'title'    => 'Trop de requêtes',
        'message'  => 'Vous avez envoyé trop de requêtes. Veuillez patienter quelques instants.',
        'icon'     => 'fa-gauge-high',
        'accent'   => '#f59e0b',
        'gradient' => 'linear-gradient(135deg,#451a03,#92400e)',
        'emoji'    => '⚡',
    ],
    500 => [
        'title'    => 'Erreur interne du serveur',
        'message'  => 'Une erreur inattendue s\'est produite. Nos équipes ont été notifiées.',
        'icon'     => 'fa-server',
        'accent'   => '#ef4444',
        'gradient' => 'linear-gradient(135deg,#450a0a,#7f1d1d)',
        'emoji'    => '🛠️',
    ],
    502 => [
        'title'    => 'Passerelle incorrecte',
        'message'  => 'Le serveur a reçu une réponse invalide du serveur amont.',
        'icon'     => 'fa-network-wired',
        'accent'   => '#ef4444',
        'gradient' => 'linear-gradient(135deg,#450a0a,#991b1b)',
        'emoji'    => '🔌',
    ],
    503 => [
        'title'    => 'Service indisponible',
        'message'  => 'Le service est temporairement en maintenance. Veuillez réessayer plus tard.',
        'icon'     => 'fa-tools',
        'accent'   => '#ef4444',
        'gradient' => 'linear-gradient(135deg,#1e1b4b,#312e81)',
        'emoji'    => '🔧',
    ],
    504 => [
        'title'    => 'Délai de passerelle dépassé',
        'message'  => 'Le serveur amont a mis trop de temps à répondre.',
        'icon'     => 'fa-hourglass-end',
        'accent'   => '#ef4444',
        'gradient' => 'linear-gradient(135deg,#1e1b4b,#4c1d95)',
        'emoji'    => '🕐',
    ],
];

if (!isset($error_config[$error_code])) $error_code = 500;
$cfg = $error_config[$error_code];

if (!empty($error_message)) $cfg['message'] = htmlspecialchars($error_message);
if (!empty($error_type))    $cfg['title']   = htmlspecialchars($error_type);

http_response_code($error_code);

$referer    = $_SERVER['HTTP_REFERER'] ?? $base_url . 'index.php';
$request_uri= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/');
$date_now   = date('d/m/Y à H:i:s');
$date_taux  = date('d/m/Y');

// Catégorie pour l'affichage
$is_client_error = $error_code >= 400 && $error_code < 500;
$is_server_error = $error_code >= 500;

// Suggestions de pages selon l'erreur
$suggestions = [
    ['url' => $base_url . 'index.php',      'icon' => 'fa-home',        'label' => 'Accueil'],
    ['url' => $base_url . 'main/index.php', 'icon' => 'fa-lock',        'label' => 'Espace Client'],
    ['url' => $base_url . 'index.php#services', 'icon' => 'fa-piggy-bank', 'label' => 'Nos Services'],
    ['url' => $base_url . 'index.php#faq',  'icon' => 'fa-question-circle', 'label' => 'FAQ'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erreur <?= $error_code ?> — S&P illico</title>
    <base href="<?= $full_base ?>">
    <link rel="icon" type="image/jpeg" href="main/logo.jpeg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ── RESET & ROOT ─────────────────────────────────────── */
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --navy:      #0a1628;
            --navy-mid:  #112240;
            --blue:      #1e3a8a;
            --blue-mid:  #1d4ed8;
            --accent:    <?= $cfg['accent'] ?>;
            --gold:      #c9a84c;
            --emerald:   #10b981;
            --rose:      #ef4444;
            --white:     #ffffff;
            --off-white: #f8fafc;
            --gray-100:  #f1f5f9;
            --gray-200:  #e2e8f0;
            --gray-400:  #94a3b8;
            --gray-600:  #475569;
            --text:      #0f172a;
            --shadow:    0 20px 60px rgba(10,22,40,0.15);
            --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--off-white);
            color: var(--text);
            overflow-x: hidden;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── NAVBAR (identique à index.php) ──────────────────── */
        .navbar {
            position: fixed; top: 0; left: 0; right: 0;
            z-index: 9000; padding: 0;
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
            max-width: 1400px; margin: 0 auto; padding: 0 32px;
            display: flex; align-items: center; justify-content: space-between;
            height: 74px;
        }

        .logo {
            display: flex; align-items: center; gap: 14px;
            text-decoration: none; flex-shrink: 0;
        }

        .logo-mark {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, var(--navy) 0%, var(--blue-mid) 100%);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 20px;
            box-shadow: 0 4px 16px rgba(30,58,138,0.35);
            overflow: hidden;
        }

        .logo-name { display: flex; flex-direction: column; line-height: 1; }
        .logo-name .brand { font-family: 'Playfair Display', serif; font-size: 22px; font-weight: 900; color: var(--navy); }
        .logo-name .brand span { color: var(--blue-mid); }
        .logo-name .tagline { font-size: 10px; color: var(--gray-400); letter-spacing: 0.1em; text-transform: uppercase; margin-top: 3px; }

        .nav-center { display: flex; align-items: center; gap: 4px; }

        .nav-item { position: relative; }

        .nav-link {
            display: flex; align-items: center; gap: 6px;
            padding: 10px 16px; color: var(--gray-600);
            font-size: 14px; font-weight: 500;
            text-decoration: none; border-radius: 10px;
            transition: var(--transition); white-space: nowrap;
        }

        .nav-link:hover { color: var(--blue-mid); background: rgba(29,78,216,0.07); }

        .nav-link i.chevron { font-size: 10px; transition: transform 0.3s; }
        .nav-item:hover .nav-link i.chevron { transform: rotate(180deg); }

        /* Dropdown */
        .dropdown {
            position: absolute; top: calc(100% + 12px); left: 50%;
            transform: translateX(-50%) translateY(-8px);
            background: white; border-radius: 20px;
            box-shadow: 0 20px 60px rgba(10,22,40,0.18), 0 0 0 1px rgba(30,58,138,0.08);
            padding: 28px; min-width: 580px;
            opacity: 0; pointer-events: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 9999;
        }

        .nav-item:hover .dropdown { opacity: 1; pointer-events: all; transform: translateX(-50%) translateY(0); }

        .dropdown-cols { display: grid; grid-template-columns: repeat(3,1fr); gap: 8px; }

        .dropdown-col-header {
            font-size: 10px; font-weight: 700; letter-spacing: 0.12em;
            text-transform: uppercase; color: var(--gray-400);
            padding: 8px 12px 4px; grid-column: 1/-1;
        }

        .dropdown-divider { grid-column: 1/-1; display: grid; grid-template-columns: repeat(3,1fr); gap: 8px; }

        .dropdown-item {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 14px; border-radius: 12px;
            text-decoration: none; color: var(--text);
            transition: var(--transition);
        }

        .dropdown-item:hover { background: var(--gray-100); color: var(--blue-mid); }

        .dropdown-item-icon {
            width: 38px; height: 38px;
            background: linear-gradient(135deg,#eff6ff,#dbeafe);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: var(--blue-mid); font-size: 16px; flex-shrink: 0;
        }

        .dropdown-item:hover .dropdown-item-icon { background: linear-gradient(135deg,var(--blue-mid),var(--blue)); color: white; }

        .dropdown-item-text .title { font-size: 13px; font-weight: 600; }
        .dropdown-item-text .desc  { font-size: 11px; color: var(--gray-400); margin-top: 2px; }

        .nav-cta { display: flex; align-items: center; gap: 12px; }

        .btn-space {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 11px 24px;
            background: linear-gradient(135deg, var(--navy) 0%, var(--blue-mid) 100%);
            color: white; border-radius: 40px; text-decoration: none;
            font-size: 14px; font-weight: 600;
            box-shadow: 0 4px 20px rgba(30,58,138,0.4);
            transition: var(--transition); white-space: nowrap;
        }

        .btn-space:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(30,58,138,0.5); }

        .mobile-toggle { display: none; background: none; border: none; font-size: 22px; color: var(--navy); cursor: pointer; }

        /* ── MOBILE NAV ───────────────────────────────────────── */
        .mobile-nav {
            display: none; position: fixed; inset: 0; z-index: 8999;
            background: rgba(10,22,40,0.95); backdrop-filter: blur(20px);
            flex-direction: column; align-items: center; justify-content: center; gap: 24px;
        }

        .mobile-nav.open { display: flex; }

        .mobile-nav a {
            font-family: 'Playfair Display', serif;
            font-size: 28px; font-weight: 700; color: white;
            text-decoration: none; transition: var(--transition);
        }

        .mobile-nav a:hover { color: var(--accent); }

        .mobile-close {
            position: absolute; top: 28px; right: 28px;
            background: none; border: none; color: white; font-size: 28px; cursor: pointer;
        }

        /* ── TICKER ───────────────────────────────────────────── */
        .ticker-bar { background: var(--navy); padding: 10px 0; overflow: hidden; }

        .ticker-track {
            display: flex; gap: 60px;
            animation: ticker 30s linear infinite;
            white-space: nowrap;
        }

        .ticker-item { display: flex; align-items: center; gap: 10px; color: rgba(255,255,255,0.7); font-size: 13px; }
        .ticker-item .currency { color: #f59e0b; font-weight: 700; }
        .ticker-item .up   { color: #10b981; }
        .ticker-item .down { color: #ef4444; }
        .ticker-sep { color: rgba(255,255,255,0.2); }

        /* ── HERO ERROR ───────────────────────────────────────── */
        .error-hero {
            position: relative;
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            overflow: hidden;
            flex: 1;
            padding-top: 74px;
        }

        /* Arrière-plan sombre animé */
        .error-bg {
            position: absolute; inset: 0;
            background: <?= $cfg['gradient'] ?>;
            z-index: 0;
        }

        /* Grille décorative */
        .error-grid {
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 60px 60px;
            z-index: 1;
        }

        /* Cercles décoratifs */
        .error-circle-1 {
            position: absolute; top: -200px; right: -200px;
            width: 600px; height: 600px; border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,0.06) 0%, transparent 70%);
            z-index: 1;
        }

        .error-circle-2 {
            position: absolute; bottom: -150px; left: -150px;
            width: 500px; height: 500px; border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,0.04) 0%, transparent 70%);
            z-index: 1;
        }

        /* Code 404/500 géant en arrière-plan */
        .error-code-bg {
            position: absolute;
            font-family: 'Playfair Display', serif;
            font-size: clamp(200px, 30vw, 400px);
            font-weight: 900;
            color: rgba(255,255,255,0.04);
            letter-spacing: -0.05em;
            line-height: 1;
            top: 50%; left: 50%;
            transform: translate(-50%,-50%);
            z-index: 1;
            user-select: none;
            white-space: nowrap;
        }

        /* Contenu central */
        .error-content {
            position: relative; z-index: 10;
            text-align: center;
            max-width: 760px;
            padding: 0 32px;
            animation: fadeUp 0.9s ease both;
        }

        /* Badge erreur */
        .error-badge {
            display: inline-flex; align-items: center; gap: 10px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: rgba(255,255,255,0.8);
            padding: 8px 20px; border-radius: 40px;
            font-size: 12px; font-weight: 700; letter-spacing: 0.1em;
            text-transform: uppercase; margin-bottom: 32px;
            backdrop-filter: blur(8px);
        }

        .error-badge .dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--accent);
            animation: pulse 1.5s infinite;
        }

        /* Numéro d'erreur */
        .error-num {
            font-family: 'Playfair Display', serif;
            font-size: clamp(80px, 14vw, 160px);
            font-weight: 900;
            color: white;
            line-height: 1;
            margin-bottom: 12px;
            text-shadow: 0 0 80px rgba(255,255,255,0.2);
        }

        .error-num span { color: var(--accent); }

        /* Icône */
        .error-icon-wrap {
            width: 80px; height: 80px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 24px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 24px;
            font-size: 36px; color: white;
            backdrop-filter: blur(8px);
            animation: float 4s ease-in-out infinite;
        }

        .error-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(28px, 4vw, 42px);
            font-weight: 800; color: white;
            margin-bottom: 16px; line-height: 1.2;
        }

        .error-title em { font-style: normal; color: var(--accent); }

        .error-desc {
            font-size: 17px; color: rgba(255,255,255,0.65);
            line-height: 1.75; max-width: 500px; margin: 0 auto 40px;
        }

        /* Boutons d'action */
        .error-actions {
            display: flex; gap: 14px; justify-content: center; flex-wrap: wrap;
            margin-bottom: 48px;
        }

        .btn-primary-err {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 15px 34px;
            background: white; color: var(--navy);
            border-radius: 50px; text-decoration: none;
            font-weight: 700; font-size: 15px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.25);
            transition: var(--transition);
        }

        .btn-primary-err:hover { transform: translateY(-3px); box-shadow: 0 14px 40px rgba(0,0,0,0.3); }

        .btn-ghost-err {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 15px 34px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.3);
            color: white; border-radius: 50px;
            text-decoration: none; font-weight: 600; font-size: 15px;
            backdrop-filter: blur(8px);
            transition: var(--transition);
            cursor: pointer;
        }

        .btn-ghost-err:hover { background: rgba(255,255,255,0.2); border-color: rgba(255,255,255,0.5); }

        .btn-accent-err {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 15px 34px;
            background: linear-gradient(135deg, var(--accent), #d97706);
            color: #1a1a1a; border-radius: 50px;
            text-decoration: none; font-weight: 700; font-size: 15px;
            box-shadow: 0 8px 25px rgba(245,158,11,0.4);
            transition: var(--transition);
        }

        .btn-accent-err:hover { transform: translateY(-3px); box-shadow: 0 14px 35px rgba(245,158,11,0.55); }

        /* ── DÉTAILS TECHNIQUES ───────────────────────────────── */
        .error-details-wrap {
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 20px; padding: 24px 32px;
            backdrop-filter: blur(10px);
            margin-bottom: 40px;
            text-align: left;
        }

        .error-details-title {
            font-size: 11px; font-weight: 700; letter-spacing: 0.12em;
            text-transform: uppercase; color: rgba(255,255,255,0.4);
            margin-bottom: 16px;
        }

        .detail-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
        }

        .detail-item { display: flex; flex-direction: column; gap: 4px; }
        .detail-item .dlabel { font-size: 11px; color: rgba(255,255,255,0.4); font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; }
        .detail-item .dvalue { font-size: 13px; color: rgba(255,255,255,0.8); font-weight: 500; word-break: break-all; }

        /* ── SUGGESTIONS DE PAGES ──────────────────────────────── */
        .suggestions-section {
            padding: 80px 0;
            background: white;
        }

        .wrap { max-width: 1400px; margin: 0 auto; padding: 0 32px; }

        .suggestions-title {
            text-align: center; margin-bottom: 48px;
        }

        .suggestions-title .eyebrow {
            font-size: 11px; font-weight: 700; letter-spacing: 0.15em;
            text-transform: uppercase; color: var(--blue-mid); margin-bottom: 12px;
        }

        .suggestions-title h2 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(28px, 3.5vw, 42px); font-weight: 800; color: var(--navy);
        }

        .suggestions-title h2 em { font-style: normal; color: var(--blue-mid); }

        .suggestions-grid {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;
        }

        .suggestion-card {
            background: var(--gray-100); border-radius: 20px; padding: 32px 24px;
            text-align: center; text-decoration: none; color: inherit;
            border: 1px solid var(--gray-200);
            transition: var(--transition); position: relative; overflow: hidden;
        }

        .suggestion-card::before {
            content: '';
            position: absolute; bottom: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, var(--blue-mid), #38bdf8);
            transform: scaleX(0); transition: transform 0.4s ease; transform-origin: left;
        }

        .suggestion-card:hover { transform: translateY(-8px); box-shadow: var(--shadow); background: white; border-color: transparent; }
        .suggestion-card:hover::before { transform: scaleX(1); }

        .suggestion-card .sug-icon {
            width: 60px; height: 60px;
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border-radius: 16px; display: flex; align-items: center; justify-content: center;
            font-size: 26px; color: var(--blue-mid); margin: 0 auto 20px;
            transition: var(--transition);
        }

        .suggestion-card:hover .sug-icon {
            background: linear-gradient(135deg, var(--navy), var(--blue-mid));
            color: white; transform: scale(1.1) rotate(-5deg);
        }

        .suggestion-card .sug-label { font-size: 15px; font-weight: 700; color: var(--navy); }

        /* ── CONTACT RAPIDE ───────────────────────────────────── */
        .quick-contact {
            background: var(--gray-100); padding: 48px 0; text-align: center;
            border-top: 1px solid var(--gray-200);
        }

        .quick-contact p { font-size: 15px; color: var(--gray-600); margin-bottom: 20px; }

        .contact-chips { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }

        .contact-chip {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 12px 22px;
            background: white; border: 1px solid var(--gray-200);
            border-radius: 40px; text-decoration: none;
            font-size: 14px; font-weight: 600; color: var(--navy);
            transition: var(--transition);
        }

        .contact-chip i { color: var(--blue-mid); }
        .contact-chip:hover { border-color: var(--blue-mid); color: var(--blue-mid); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(29,78,216,0.15); }

        /* ── FOOTER (identique à index.php) ──────────────────── */
        .footer { background: var(--navy); color: white; padding: 80px 0 0; }

        .footer-grid { display: grid; grid-template-columns: 2.5fr 1fr 1fr 1.5fr; gap: 48px; padding-bottom: 60px; }

        .footer-brand p { color: rgba(255,255,255,0.5); font-size: 14px; line-height: 1.8; margin: 20px 0 28px; max-width: 300px; }

        .footer-socials { display: flex; gap: 10px; }

        .social-btn {
            width: 40px; height: 40px;
            background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12);
            border-radius: 10px; display: flex; align-items: center; justify-content: center;
            color: rgba(255,255,255,0.5); text-decoration: none; transition: var(--transition);
        }

        .social-btn:hover { background: var(--blue-mid); border-color: var(--blue-mid); color: white; transform: translateY(-3px); }

        .footer-col h4 { font-size: 14px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: rgba(255,255,255,0.4); margin-bottom: 22px; }

        .footer-col ul { list-style: none; display: flex; flex-direction: column; gap: 12px; }

        .footer-col a { color: rgba(255,255,255,0.6); text-decoration: none; font-size: 14px; transition: var(--transition); display: flex; align-items: center; gap: 6px; }

        .footer-col a:hover { color: white; padding-left: 4px; }

        .footer-contact-item { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 16px; }
        .footer-contact-item i { color: #f59e0b; width: 18px; margin-top: 2px; flex-shrink: 0; }
        .footer-contact-item span { color: rgba(255,255,255,0.6); font-size: 14px; line-height: 1.6; }

        .footer-bar {
            border-top: 1px solid rgba(255,255,255,0.08); padding: 24px 0;
            display: flex; justify-content: space-between; align-items: center;
        }

        .footer-bar p { color: rgba(255,255,255,0.35); font-size: 13px; }
        .footer-bar-links { display: flex; gap: 28px; }
        .footer-bar-links a { color: rgba(255,255,255,0.35); font-size: 13px; text-decoration: none; transition: var(--transition); }
        .footer-bar-links a:hover { color: white; }

        /* ── ANIMATIONS ───────────────────────────────────────── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(40px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulse {
            0%,100% { opacity: 1; transform: scale(1); }
            50%      { opacity: 0.5; transform: scale(0.8); }
        }

        @keyframes float {
            0%,100% { transform: translateY(0); }
            50%      { transform: translateY(-10px); }
        }

        @keyframes ticker {
            from { transform: translateX(0); }
            to   { transform: translateX(-50%); }
        }

        @keyframes spin-slow {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }

        /* Reveal */
        .reveal { opacity: 0; transform: translateY(30px); transition: opacity 0.6s ease, transform 0.6s ease; }
        .reveal.visible { opacity: 1; transform: translateY(0); }

        /* ── RESPONSIVE ───────────────────────────────────────── */
        @media (max-width: 1100px) {
            .suggestions-grid { grid-template-columns: repeat(2,1fr); }
            .footer-grid { grid-template-columns: 1fr 1fr; }
            .detail-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .nav-center { display: none; }
            .mobile-toggle { display: block; }
            .suggestions-grid { grid-template-columns: 1fr 1fr; }
            .footer-grid { grid-template-columns: 1fr; }
            .footer-bar { flex-direction: column; gap: 12px; text-align: center; }
            .error-actions { flex-direction: column; align-items: center; }
            .dropdown { min-width: 280px; left: -20px; transform: none; }
            .nav-item:hover .dropdown { transform: none; }
        }

        @media (max-width: 480px) {
            .suggestions-grid { grid-template-columns: 1fr; }
            .error-details-wrap { padding: 20px; }
        }
    </style>
</head>
<body>

<!-- ===== MOBILE NAV ===== -->
<div class="mobile-nav" id="mobileNav">
    <button class="mobile-close" onclick="closeMobileNav()"><i class="fas fa-times"></i></button>
    <a href="index.php">Accueil</a>
    <a href="index.php#services">Services</a>
    <a href="index.php#rates">Taux</a>
    <a href="index.php#faq">FAQ</a>
    <a href="index.php#contact">Contact</a>
    <a href="main/index.php" style="color:var(--accent);">Espace Client →</a>
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
                <div class="nav-item"><a href="index.php" class="nav-link">Accueil</a></div>

                <div class="nav-item">
                    <a href="index.php#services" class="nav-link">Services <i class="fas fa-chevron-down chevron"></i></a>
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
                                <a href="index.php#rates" class="dropdown-item">
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

                <div class="nav-item">
                    <a href="index.php#rates" class="nav-link">Taux <i class="fas fa-chevron-down chevron"></i></a>
                    <div class="dropdown" style="min-width:320px;">
                        <div class="dropdown-cols">
                            <div class="dropdown-col-header">Taux du jour</div>
                            <div class="dropdown-divider" style="grid-template-columns:1fr 1fr;">
                                <a href="index.php#rates" class="dropdown-item">
                                    <div class="dropdown-item-icon"><i class="fas fa-dollar-sign"></i></div>
                                    <div class="dropdown-item-text"><div class="title">USD/HTG</div><div class="desc">132.50 / 135.75</div></div>
                                </a>
                                <a href="index.php#rates" class="dropdown-item">
                                    <div class="dropdown-item-icon"><i class="fas fa-euro-sign"></i></div>
                                    <div class="dropdown-item-text"><div class="title">EUR/HTG</div><div class="desc">142.80 / 146.50</div></div>
                                </a>
                                <a href="index.php#rates" class="dropdown-item">
                                    <div class="dropdown-item-icon" style="font-size:13px;font-weight:800;">CA$</div>
                                    <div class="dropdown-item-text"><div class="title">CAD/HTG</div><div class="desc">96.40 / 99.20</div></div>
                                </a>
                                <a href="index.php#rates" class="dropdown-item">
                                    <div class="dropdown-item-icon"><i class="fas fa-calculator"></i></div>
                                    <div class="dropdown-item-text"><div class="title">Convertisseur</div><div class="desc">Calculez en direct</div></div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="nav-item">
                    <a href="index.php#about" class="nav-link">À propos <i class="fas fa-chevron-down chevron"></i></a>
                    <div class="dropdown" style="min-width:380px;">
                        <div class="dropdown-cols">
                            <div class="dropdown-col-header">Notre institution</div>
                            <div class="dropdown-divider" style="grid-template-columns:1fr 1fr;">
                                <a href="index.php#about" class="dropdown-item">
                                    <div class="dropdown-item-icon"><i class="fas fa-landmark"></i></div>
                                    <div class="dropdown-item-text"><div class="title">Notre histoire</div><div class="desc">Depuis 2015</div></div>
                                </a>
                                <a href="index.php#about" class="dropdown-item">
                                    <div class="dropdown-item-icon"><i class="fas fa-users"></i></div>
                                    <div class="dropdown-item-text"><div class="title">Notre équipe</div><div class="desc">Professionnels dévoués</div></div>
                                </a>
                                <a href="index.php#about" class="dropdown-item">
                                    <div class="dropdown-item-icon"><i class="fas fa-shield-alt"></i></div>
                                    <div class="dropdown-item-text"><div class="title">Sécurité</div><div class="desc">Certifié & réglementé</div></div>
                                </a>
                                <a href="index.php#contact" class="dropdown-item">
                                    <div class="dropdown-item-icon"><i class="fas fa-map-marker-alt"></i></div>
                                    <div class="dropdown-item-text"><div class="title">Nos agences</div><div class="desc">Terrier-Rouge, Haïti</div></div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="nav-item"><a href="index.php#faq"     class="nav-link">FAQ</a></div>
                <div class="nav-item"><a href="index.php#contact" class="nav-link">Contact</a></div>
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

<!-- ===== TICKER ===== -->
<div class="ticker-bar">
    <div class="ticker-track">
        <div class="ticker-item"><span class="currency">USD/HTG</span><span>Achat: 132.50</span><span class="up">▲ +0.8%</span></div>
        <span class="ticker-sep">•</span>
        <div class="ticker-item"><span class="currency">EUR/HTG</span><span>Achat: 142.80</span><span class="down">▼ -0.3%</span></div>
        <span class="ticker-sep">•</span>
        <div class="ticker-item"><span class="currency">CAD/HTG</span><span>Achat: 96.40</span><span class="up">▲ +0.2%</span></div>
        <span class="ticker-sep">•</span>
        <div class="ticker-item"><i class="fas fa-clock" style="color:#f59e0b;"></i> Mise à jour: <?= $date_taux ?> — 09:00</div>
        <span class="ticker-sep">•</span>
        <div class="ticker-item"><span class="currency">USD/HTG</span><span>Achat: 132.50</span><span class="up">▲ +0.8%</span></div>
        <span class="ticker-sep">•</span>
        <div class="ticker-item"><span class="currency">EUR/HTG</span><span>Achat: 142.80</span><span class="down">▼ -0.3%</span></div>
        <span class="ticker-sep">•</span>
        <div class="ticker-item"><span class="currency">CAD/HTG</span><span>Achat: 96.40</span><span class="up">▲ +0.2%</span></div>
        <span class="ticker-sep">•</span>
        <div class="ticker-item"><i class="fas fa-clock" style="color:#f59e0b;"></i> Mise à jour: <?= $date_taux ?> — 09:00</div>
    </div>
</div>

<!-- ===== HERO ERREUR ===== -->
<section class="error-hero">
    <div class="error-bg"></div>
    <div class="error-grid"></div>
    <div class="error-circle-1"></div>
    <div class="error-circle-2"></div>

    <!-- Code géant en fond -->
    <div class="error-code-bg"><?= $error_code ?></div>

    <div class="error-content">
        <!-- Badge -->
        <div class="error-badge">
            <div class="dot"></div>
            <?= $is_server_error ? 'Erreur serveur' : 'Erreur client' ?> — HTTP <?= $error_code ?>
        </div>

        <!-- Numéro -->
        <div class="error-num">
            <?php
            $code_str = (string)$error_code;
            echo $code_str[0];
            echo '<span>' . $code_str[1] . '</span>';
            echo $code_str[2];
            ?>
        </div>

        <!-- Icône -->
        <div class="error-icon-wrap">
            <i class="fas <?= $cfg['icon'] ?>"></i>
        </div>

        <!-- Titre & description -->
        <h1 class="error-title"><?= $cfg['title'] ?></h1>
        <p class="error-desc"><?= $cfg['message'] ?></p>

        <!-- Boutons -->
        <div class="error-actions">
            <a href="index.php" class="btn-primary-err">
                <i class="fas fa-home"></i> Retour à l'accueil
            </a>
            <a href="<?= htmlspecialchars($referer) ?>" class="btn-ghost-err">
                <i class="fas fa-arrow-left"></i> Page précédente
            </a>
            <?php if ($is_server_error): ?>
            <button onclick="window.location.reload()" class="btn-accent-err">
                <i class="fas fa-rotate-right"></i> Réessayer
            </button>
            <?php else: ?>
            <a href="main/index.php" class="btn-accent-err">
                <i class="fas fa-lock"></i> Espace Client
            </a>
            <?php endif; ?>
        </div>

        <!-- Détails techniques -->
        <div class="error-details-wrap">
            <div class="error-details-title"><i class="fas fa-info-circle"></i> &nbsp;Informations techniques</div>
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="dlabel"><i class="fas fa-code"></i> Code HTTP</span>
                    <span class="dvalue"><?= $error_code ?> — <?= htmlspecialchars($cfg['title']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="dlabel"><i class="fas fa-clock"></i> Date & heure</span>
                    <span class="dvalue"><?= $date_now ?></span>
                </div>
                <div class="detail-item">
                    <span class="dlabel"><i class="fas fa-globe"></i> URL demandée</span>
                    <span class="dvalue"><?= $request_uri ?></span>
                </div>
                <div class="detail-item">
                    <span class="dlabel"><i class="fas fa-server"></i> Serveur</span>
                    <span class="dvalue"><?= htmlspecialchars($_SERVER['SERVER_NAME'] ?? 'S&P illico') ?></span>
                </div>
                <?php if (isset($_SESSION['user_id'])): ?>
                <div class="detail-item">
                    <span class="dlabel"><i class="fas fa-user"></i> Utilisateur</span>
                    <span class="dvalue"><?= htmlspecialchars($_SESSION['nom_complet'] ?? $_SESSION['username'] ?? 'Connecté') ?></span>
                </div>
                <div class="detail-item">
                    <span class="dlabel"><i class="fas fa-id-badge"></i> Rôle</span>
                    <span class="dvalue"><?= htmlspecialchars(ucfirst($_SESSION['role'] ?? '—')) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($error_type)): ?>
                <div class="detail-item">
                    <span class="dlabel"><i class="fas fa-tag"></i> Type d'erreur</span>
                    <span class="dvalue"><?= htmlspecialchars($error_type) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ===== SUGGESTIONS ===== -->
<section class="suggestions-section reveal">
    <div class="wrap">
        <div class="suggestions-title">
            <div class="eyebrow">Où aller maintenant ?</div>
            <h2>Pages <em>suggérées</em></h2>
        </div>

        <div class="suggestions-grid">
            <?php foreach ($suggestions as $s): ?>
            <a href="<?= htmlspecialchars($s['url']) ?>" class="suggestion-card reveal">
                <div class="sug-icon"><i class="fas <?= $s['icon'] ?>"></i></div>
                <div class="sug-label"><?= $s['label'] ?></div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===== CONTACT RAPIDE ===== -->
<div class="quick-contact">
    <div class="wrap">
        <p><i class="fas fa-headset"></i> Besoin d'aide ? Notre équipe est disponible du lundi au dimanche de 8h à 20h.</p>
        <div class="contact-chips">
            <a href="tel:+50933383509" class="contact-chip">
                <i class="fas fa-phone"></i> +509 3338-3509
            </a>
            <a href="mailto:illicoms01@gmail.com" class="contact-chip">
                <i class="fas fa-envelope"></i> illicoms01@gmail.com
            </a>
            <a href="index.php#faq" class="contact-chip">
                <i class="fas fa-question-circle"></i> Centre d'aide
            </a>
        </div>
    </div>
</div>

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
                <p>Votre partenaire bancaire de confiance, engagé à offrir des services financiers accessibles et innovants à toute la communauté haïtienne depuis 2015.</p>
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
                    <li><a href="index.php#services"><i class="fas fa-chevron-right" style="font-size:10px;"></i> Services</a></li>
                    <li><a href="index.php#rates"><i class="fas fa-chevron-right" style="font-size:10px;"></i> Taux du jour</a></li>
                    <li><a href="index.php#about"><i class="fas fa-chevron-right" style="font-size:10px;"></i> À propos</a></li>
                    <li><a href="index.php#faq"><i class="fas fa-chevron-right" style="font-size:10px;"></i> FAQ</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Services</h4>
                <ul>
                    <li><a href="main/index.php"><i class="fas fa-chevron-right" style="font-size:10px;"></i> Espace Client</a></li>
                    <li><a href="main/index.php"><i class="fas fa-chevron-right" style="font-size:10px;"></i> Compte Épargne</a></li>
                    <li><a href="main/index.php"><i class="fas fa-chevron-right" style="font-size:10px;"></i> Compte Courant</a></li>
                    <li><a href="main/index.php"><i class="fas fa-chevron-right" style="font-size:10px;"></i> Prêts & Crédits</a></li>
                    <li><a href="index.php#rates"><i class="fas fa-chevron-right" style="font-size:10px;"></i> Change de devises</a></li>
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
/* ── NAVBAR SCROLL ── */
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => navbar.classList.toggle('scrolled', window.scrollY > 50));

/* ── MOBILE NAV ── */
function openMobileNav()  { document.getElementById('mobileNav').classList.add('open');    document.body.style.overflow = 'hidden'; }
function closeMobileNav() { document.getElementById('mobileNav').classList.remove('open'); document.body.style.overflow = ''; }

/* ── SCROLL REVEAL ── */
const revealEls = document.querySelectorAll('.reveal');
const obs = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); } });
}, { threshold: 0.12 });
revealEls.forEach(el => obs.observe(el));

/* ── COMPTE À REBOURS POUR LES ERREURS SERVEUR ── */
<?php if ($is_server_error): ?>
let countdown = 30;
const cd = document.createElement('p');
cd.style.cssText = 'color:rgba(255,255,255,0.45);font-size:13px;margin-top:16px;';
cd.innerHTML = `<i class="fas fa-rotate-right"></i> Actualisation automatique dans <strong id="cdNum">30</strong>s`;
document.querySelector('.error-details-wrap').after(cd);
const interval = setInterval(() => {
    countdown--;
    const el = document.getElementById('cdNum');
    if (el) el.textContent = countdown;
    if (countdown <= 0) { clearInterval(interval); window.location.reload(); }
}, 1000);
<?php endif; ?>
</script>
</body>
</html>