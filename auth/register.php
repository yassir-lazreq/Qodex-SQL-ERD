<?php
session_start();
require_once '../config/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    header("Location: ../$role/dashboard.php");
    exit();
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nom = trim($_POST['nom'])??'';
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'etudiant';

    // Val    idation
    if (empty($nom) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Tous les champs sont requis.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Adresse email invalide.";
    } elseif (strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères.";
    } elseif ($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $result = $stmt->fetch();
        
        if ($result) {
            $error = "Cette adresse email est déjà utilisée.";
        } else {
            // Hash password and insert user
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (nom, email, password_hash, role) VALUES (?, ?, ?, ?)");

            if ($stmt->execute([$nom, $email, $password_hash, $role])) {
                $success = "Inscription réussie ! Vous pouvez maintenant vous connecter.";
                // Optionally auto-login
                $_SESSION['user_id'] = $conn->lastInsertId();
                $_SESSION['nom'] = $nom;
                $_SESSION['role'] = $role;
                header("Location: ../$role/dashboard.php");
                exit();
            } else {
                $error = "Une erreur est survenue lors de l'inscription. Veuillez réessayer.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Qodex</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .register-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 450px;
            padding: 40px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #667eea;
            font-size: 2.5em;
            margin-bottom: 5px;
        }
        
        .logo p {
            color: #666;
            font-size: 0.95em;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 0.95em;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .role-group {
            display: flex;
            gap: 10px;
        }
        
        .role-option {
            flex: 1;
            position: relative;
        }
        
        .role-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .role-option label {
            display: block;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 0;
        }
        
        .role-option input[type="radio"]:checked + label {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.95em;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 0.95em;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">
            <h1>Qodex</h1>
            <p>Créer un compte</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="nom">Nom complet</label>
                <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($nom ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Adresse email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirmer le mot de passe</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <div class="form-group">
                <label>Vous êtes :</label>
                <div class="role-group">
                    <div class="role-option">
                        <input type="radio" id="role_etudiant" name="role" value="etudiant" <?php echo (!isset($role) || $role == 'etudiant') ? 'checked' : ''; ?>>
                        <label for="role_etudiant">Étudiant</label>
                    </div>
                    <div class="role-option">
                        <input type="radio" id="role_enseignant" name="role" value="enseignant" <?php echo (isset($role) && $role == 'enseignant') ? 'checked' : ''; ?>>
                        <label for="role_enseignant">Enseignant</label>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn">S'inscrire</button>
        </form>
        
        <div class="login-link">
            Vous avez déjà un compte ? <a href="login.php">Se connecter</a>
        </div>
    </div>
</body>
</html>
