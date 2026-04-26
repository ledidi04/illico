<?php
// Inclure la configuration AVANT de démarrer la session
require_once 'config/connexion.php';

// Maintenant on peut démarrer la session
session_start();

// Redirection si déjà connecté
if (isset($_SESSION['user_id'])) {
    // Rediriger selon le rôle sans afficher le formulaire
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
            header('Location: dashboard.php');
    }
    exit;
}

$error = '';
$success = '';

// Récupérer les messages de l'URL
if (isset($_GET['logout'])) {
    $success = "Vous avez été déconnecté avec succès.";
}
if (isset($_GET['timeout'])) {
    $error = "Votre session a expiré. Veuillez vous reconnecter.";
}
if (isset($_GET['error'])) {
    $error = "Une erreur est survenue. Veuillez vous reconnecter.";
}

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login) || empty($password)) {
        $error = "Veuillez remplir tous les champs.";
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT u.*, s.nom as succursale_nom, s.code as succursale_code 
                FROM utilisateurs u
                JOIN succursales s ON u.succursale_id = s.id
                WHERE (u.username = ? OR u.email = ?) AND u.actif = 1
            ");
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Vérification du mot de passe (en clair pour le développement)
            if ($user && $user['password'] === $password) {
                // Mise à jour de la dernière connexion
                $updateStmt = $pdo->prepare("UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);
                
                // Création de la session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['succursale_id'] = $user['succursale_id'];
                $_SESSION['succursale_nom'] = $user['succursale_nom'];
                $_SESSION['nom_complet'] = $user['nom_complet'];
                $_SESSION['last_activity'] = time();

                // "Se souvenir de moi"
                if (isset($_POST['remember'])) {
                    setcookie('remember_login', $login, time() + 3600*24*30, '/');
                }

                // REDIRECTION APRÈS POST (Pattern Post/Redirect/Get)
                switch ($user['role']) {
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
                        header('Location: dashboard.php');
                }
                exit;
            } else {
                $error = "Identifiants incorrects ou compte désactivé.";
            }
        } catch (PDOException $e) {
            $error = "Erreur système. Veuillez réessayer plus tard.";
            error_log("Erreur DB connexion: " . $e->getMessage());
        }
    }
    
    
    // Rediriger vers la même page avec l'erreur en GET pour éviter la resoumission
    if ($error) {
        $_SESSION['login_error'] = $error;
        header('Location: index.php');
        exit;
    }
}

// Récupérer l'erreur de session si elle existe
if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

// Récupérer le login mémorisé
$rememberedLogin = $_COOKIE['remember_login'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S&P illico - Connexion</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" type="favicon" href="logo.jpeg">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Arial, sans-serif; 
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 50%, #3b82f6 100%);
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 20px; 
        }
        .login-container { 
            display: flex; 
            max-width: 1000px; 
            width: 100%; 
            background: white; 
            border-radius: 24px; 
            overflow: hidden; 
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); 
        }
        .login-left { 
            flex: 1; 
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%); 
            padding: 48px 40px; 
            color: white; 
            display: flex;
            flex-direction: column;
        }
        .login-right { 
            flex: 1; 
            padding: 48px 40px; 
            background: #ffffff; 
        }
        .brand { 
            text-align: center; 
            margin-bottom: 40px; 
        }
        .brand i { 
            font-size: 56px; 
            margin-bottom: 16px; 
            background: rgba(255,255,255,0.15);
            padding: 20px;
            border-radius: 20px;
        }
        .brand h1 { 
            font-size: 32px; 
            font-weight: 700;
            margin-bottom: 8px; 
            letter-spacing: -0.5px;
        }
        .brand p { 
            font-size: 14px; 
            opacity: 0.9; 
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .features { 
            margin: 20px 0 40px; 
        }
        .feature { 
            display: flex; 
            gap: 16px; 
            margin-bottom: 24px; 
        }
        .feature i { 
            font-size: 22px; 
            width: 48px; 
            height: 48px; 
            background: rgba(255,255,255,0.15); 
            border-radius: 14px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            backdrop-filter: blur(10px);
        }
        .feature h3 { 
            font-size: 16px; 
            font-weight: 600;
            margin-bottom: 6px; 
        }
        .feature p { 
            font-size: 13px; 
            opacity: 0.85; 
            line-height: 1.5;
        }
        .stats {
            display: flex;
            justify-content: space-around;
            margin: 30px 0;
        }
        .stat {
            text-align: center;
        }
        .stat-value {
            font-size: 28px;
            font-weight: 700;
        }
        .stat-label {
            font-size: 11px;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .quote {
            margin-top: auto;
            padding: 20px;
            background: rgba(255,255,255,0.1);
            border-radius: 16px;
            backdrop-filter: blur(10px);
        }
        .quote i {
            opacity: 0.5;
            margin-bottom: 12px;
            font-size: 20px;
        }
        .quote p {
            font-style: italic;
            margin-bottom: 10px;
            line-height: 1.6;
            font-size: 15px;
        }
        .quote span {
            font-size: 13px;
            opacity: 0.8;
        }
        .login-header { 
            text-align: center; 
            margin-bottom: 32px; 
        }
        .login-header h2 { 
            color: #0f172a; 
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px; 
        }
        .login-header p { 
            color: #64748b; 
            font-size: 14px; 
        }
        .form-group { 
            margin-bottom: 24px; 
        }
        .form-group label { 
            display: block; 
            margin-bottom: 8px; 
            color: #334155; 
            font-size: 14px; 
            font-weight: 500; 
        }
        .form-group label i { 
            margin-right: 8px; 
            color: #3b82f6; 
            width: 18px;
        }
        .form-control { 
            width: 100%; 
            padding: 14px 16px; 
            border: 2px solid #e2e8f0; 
            border-radius: 12px; 
            font-size: 15px; 
            transition: all 0.2s;
            background: #f8fafc;
        }
        .form-control:focus { 
            outline: none; 
            border-color: #3b82f6; 
            background: white;
            box-shadow: 0 0 0 4px rgba(59,130,246,0.1);
        }
        .password-wrapper { 
            position: relative; 
        }
        .toggle-password { 
            position: absolute; 
            right: 16px; 
            top: 50%; 
            transform: translateY(-50%); 
            background: none; 
            border: none; 
            color: #64748b; 
            cursor: pointer; 
            padding: 8px;
        }
        .toggle-password:hover {
            color: #3b82f6;
        }
        .form-options { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin: 24px 0; 
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #475569;
            font-size: 14px;
            cursor: pointer;
        }
        .checkbox-label input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .btn-login { 
            width: 100%; 
            padding: 16px; 
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            color: white; 
            border: none; 
            border-radius: 12px; 
            font-size: 16px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: all 0.3s;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .btn-login:hover { 
            background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        }
        .btn-login i {
            margin-right: 8px;
        }
        .alert { 
            padding: 14px 16px; 
            border-radius: 12px; 
            margin-bottom: 24px; 
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-error { 
            background: #fef2f2; 
            color: #dc2626; 
            border: 1px solid #fecaca; 
        }
        .alert-success { 
            background: #f0fdf4; 
            color: #16a34a; 
            border: 1px solid #bbf7d0; 
        }
        .info-box { 
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            padding: 16px; 
            border-radius: 12px; 
            margin-bottom: 24px; 
            font-size: 13px; 
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }
        .info-box i {
            margin-right: 8px;
        }
        .info-box strong {
            font-weight: 600;
        }
        .login-footer {
            text-align: center;
            margin-top: 32px;
            color: #64748b;
            font-size: 13px;
        }
        @media (max-width: 768px) { 
            .login-container { 
                flex-direction: column; 
                max-width: 450px;
            } 
            .login-left {
                padding: 32px 24px;
            }
            .login-right {
                padding: 32px 24px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <div class="brand">

                <img src="logo.jpeg" alt="S&P illico" style="width: 80px; height: auto; margin-bottom: 15px;">
                <h1>S&P illico</h1>
                <p>Banque Communautaire</p>
            </div>
            <div class="features">
                <div class="feature">
                    <i class="fas fa-bank"></i>
                    <div>
                        <h3>Sécurité Bancaire</h3>
                        <p>Protection avancée de vos transactions et données personnelles</p>
                    </div>
                </div>
                <div class="feature">
                    <i class="fas fa-clock"></i>
                    <div>
                        <h3>Temps Réel</h3>
                        <p>Suivi instantané de toutes vos opérations bancaires</p>
                    </div>
                </div>
                <div class="feature">
                    <i class="fas fa-network-wired"></i>
                    <div>
                        <h3>Succursales Interconnectées</h3>
                        <p>Accédez à vos comptes depuis n'importe quelle agence</p>
                    </div>
                </div>
            </div>
            <div class="stats">
                <div class="stat">
                    <div class="stat-value">24/7</div>
                    <div class="stat-label">Service</div>
                </div>
                <div class="stat">
                    <div class="stat-value">2</div>
                    <div class="stat-label">Succursales</div>
                </div>
                <div class="stat">
                    <div class="stat-value">100%</div>
                    <div class="stat-label">Sécurisé</div>
                </div>
            </div>
           
        </div>
        
        <div class="login-right">
            <div class="login-header">
                <h2>Connexion</h2>
                <p>Accédez à votre espace sécurisé</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> 
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> 
                <span><?= htmlspecialchars($success) ?></span>
            </div>
            <?php endif; ?>
            
          
            
            <form method="post" id="loginForm">
                <div class="form-group">
                    <label for="login">
                        <i class="fas fa-user"></i>
                        Nom d'utilisateur
                    </label>
                    <input type="text" 
                           id="login" 
                           name="login" 
                           class="form-control" 
                           value="<?= htmlspecialchars($rememberedLogin) ?>" 
                           placeholder="nom utilisateur"
                           required 
                           autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i>
                        Mot de passe
                    </label>
                    <div class="password-wrapper">
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-control" 
                               placeholder="••••••••"
                               required>
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" <?= $rememberedLogin ? 'checked' : '' ?>>
                        Se souvenir de moi
                    </label>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-arrow-right-to-bracket"></i>
                    Se connecter
                </button>
            </form>
            
            <div class="login-footer">
                <i class="fas fa-lock" style="margin-right: 6px; color: #10b981;"></i>
                Connexion sécurisée • &copy; <?= date('Y') ?> S&P illico
            </div>

            <div style="margin-top: 50px; text-align: center;">
                <a href="../index.php" class="btn-outline-light">
                    <i class="fas fa-arrow-left"></i>
                    Retour à l'accueil
                </a>
            </div>
    
        </div>
    </div>

   
    <script>
        function togglePassword() {
            const pwd = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                pwd.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Empêcher la double soumission du formulaire
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Connexion...';
        });
        
        // Disparaître les alertes après 5 secondes
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() { alert.style.display = 'none'; }, 500);
            });
        }, 5000);
    </script>
</body>
</html>
