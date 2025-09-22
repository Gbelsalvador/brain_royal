<?php
session_start();
require_once '../config/database.php';

// Vérifier si l'utilisateur est connecté et est administrateur
if(!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: ../login.php');
    exit;
}

// Vérifier si l'ID de l'utilisateur est fourni
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: users.php');
    exit;
}

$user_id = $_GET['id'];

// Récupérer les informations de l'utilisateur
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if(!$user) {
    header('Location: users.php');
    exit;
}

// Récupérer les participations aux tournois
$stmt = $conn->prepare("
    SELECT tp.*, t.title as tournament_title, t.start_time,
           (SELECT COUNT(*) FROM user_answers WHERE participant_id = tp.id) as answers_count,
           (SELECT COUNT(*) FROM questions WHERE tournament_id = t.id) as total_questions
    FROM tournament_participants tp
    JOIN tournaments t ON tp.tournament_id = t.id
    WHERE tp.user_id = ?
    ORDER BY t.start_time DESC
");
$stmt->execute([$user_id]);
$participations = $stmt->fetchAll();

// Récupérer les statistiques de l'utilisateur
$stats = [
    'tournaments_count' => count($participations),
    'active_tournaments' => 0,
    'total_score' => 0,
    'highest_score' => 0,
    'wins' => 0,
    'eliminations' => 0,
    'questions_answered' => 0,
    'correct_answers' => 0
];

foreach($participations as $participation) {
    if($participation['is_active'] == 1) {
        $stats['active_tournaments']++;
    }
    
    $stats['total_score'] += $participation['score'];
    
    if($participation['score'] > $stats['highest_score']) {
        $stats['highest_score'] = $participation['score'];
    }
    
    // Vérifier si l'utilisateur est le gagnant du tournoi
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM tournament_participants
        WHERE tournament_id = ? AND is_active = 1
    ");
    $stmt->execute([$participation['tournament_id']]);
    $active_count = $stmt->fetch()['count'];
    
    if($active_count == 1 && $participation['is_active'] == 1) {
        $stats['wins']++;
    }
    
    if($participation['is_active'] == 0) {
        $stats['eliminations']++;
    }
    
    $stats['questions_answered'] += $participation['answers_count'];
    
    // Compter les réponses correctes
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM user_answers
        WHERE participant_id = ? AND is_correct = 1
    ");
    $stmt->execute([$participation['id']]);
    $stats['correct_answers'] += $stmt->fetch()['count'];
}

// Calculer le taux de réponses correctes
$stats['correct_rate'] = $stats['questions_answered'] > 0 ? ($stats['correct_answers'] / $stats['questions_answered']) * 100 : 0;

// Traitement de la mise à jour du profil
if(isset($_POST['update_profile'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;
    
    $errors = [];
    
    // Validation
    if(empty($username)) {
        $errors[] = "Le nom d'utilisateur est requis";
    }
    
    if(empty($email)) {
        $errors[] = "L'email est requis";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format d'email invalide";
    }
    
    // Vérifier si le nom d'utilisateur ou l'email existe déjà (sauf pour l'utilisateur actuel)
    if(empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute([$username, $email, $user_id]);
        if($stmt->rowCount() > 0) {
            $errors[] = "Ce nom d'utilisateur ou cet email est déjà utilisé";
        }
    }
    
    // Mettre à jour le profil si pas d'erreurs
    if(empty($errors)) {
        try {
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
            $stmt->execute([$username, $email, $user_id]);
            
            // Dans une application réelle, vous auriez une colonne is_admin dans la table users
            // Pour cet exemple, nous allons simplement afficher un message de succès
            
            $success_message = "Le profil a été mis à jour avec succès";
            
            // Recharger les données mises à jour
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        } catch(Exception $e) {
            $errors[] = "Une erreur est survenue: " . $e->getMessage();
        }
    }
}

// Traitement de la réinitialisation du mot de passe
if(isset($_POST['reset_password'])) {
    $new_password = bin2hex(random_bytes(4)); // Générer un mot de passe aléatoire de 8 caractères
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    try {
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $user_id]);
        
        $password_message = "Le mot de passe a été réinitialisé avec succès. Nouveau mot de passe: <strong>$new_password</strong>";
    } catch(Exception $e) {
        $password_error = "Une erreur est survenue: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil utilisateur - Administration Quiz Battle Royale</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Profil de <?= htmlspecialchars($user['username']) ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="users.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Retour aux utilisateurs
                        </a>
                    </div>
                </div>
                
                <?php if(isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $success_message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($password_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $password_message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($password_error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $password_error ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if(!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h4 class="alert-heading">Erreurs</h4>
                        <ul class="mb-0">
                            <?php foreach($errors as $error): ?>
                                <li><?= $error ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-4">
                        <!-- User profile -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3 class="card-title">Informations du profil</h3>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Nom d'utilisateur</label>
                                        <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Date d'inscription</label>
                                        <input type="text" class="form-control" value="<?= date('d/m/Y H:i', strtotime($user['created_at'])) ?>" readonly>
                                    </div>
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="is_admin" name="is_admin" <?= $user['email'] == 'admin@example.com' || strpos($user['username'], 'admin') !== false ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="is_admin">Administrateur</label>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" name="update_profile" class="btn btn-primary">Mettre à jour le profil</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Password reset -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3 class="card-title">Réinitialisation du mot de passe</h3>
                            </div>
                            <div class="card-body">
                                <p>Générer un nouveau mot de passe pour cet utilisateur.</p>
                                <form method="POST" action="" onsubmit="return confirm('Êtes-vous sûr de vouloir réinitialiser le mot de passe de cet utilisateur ?');">
                                    <div class="d-grid">
                                        <button type="submit" name="reset_password" class="btn btn-warning">Réinitialiser le mot de passe</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- User stats -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3 class="card-title">Statistiques</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <div class="text-center">
                                            <h4><?= $stats['tournaments_count'] ?></h4>
                                            <p class="text-muted">Tournois</p>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="text-center">
                                            <h4><?= $stats['wins'] ?></h4>
                                            <p class="text-muted">Victoires</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <div class="text-center">
                                            <h4><?= $stats['total_score'] ?></h4>
                                            <p class="text-muted">Score total</p>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="text-center">
                                            <h4><?= $stats['highest_score'] ?></h4>
                                            <p class="text-muted">Meilleur score</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="mb-3">
                                    <label class="form-label">Taux de réponses correctes</label>
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $stats['correct_rate'] ?>%;" aria-valuenow="<?= round($stats['correct_rate']) ?>" aria-valuemin="0" aria-valuemax="100">
                                            <?= round($stats['correct_rate']) ?>%
                                        </div>
                                    </div>
                                    <div class="form-text text-center">
                                        <?= $stats['correct_answers'] ?> / <?= $stats['questions_answered'] ?> réponses correctes
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <!-- Tournament participations -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h3 class="card-title mb-0">Participations aux tournois (<?= count($participations) ?>)</h3>
                            </div>
                            <div class="card-body p-0">
                                <?php if(empty($participations)): ?>
                                    <div class="alert alert-info m-3">
                                        Cet utilisateur n'a participé à aucun tournoi.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Tournoi</th>
                                                    <th>Date</th>
                                                    <th>Score</th>
                                                    <th>Round</th>
                                                    <th>Réponses</th>
                                                    <th>Statut</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($participations as $participation): ?>
                                                    <tr>
                                                        <td>
                                                            <a href="view_tournament.php?id=<?= $participation['tournament_id'] ?>">
                                                                <?= htmlspecialchars($participation['tournament_title']) ?>
                                                            </a>
                                                        </td>
                                                        <td><?= date('d/m/Y H:i', strtotime($participation['start_time'])) ?></td>
                                                        <td><?= $participation['score'] ?></td>
                                                        <td><?= $participation['round'] ?></td>
                                                        <td><?= $participation['answers_count'] ?> / <?= $participation['total_questions'] ?></td>
                                                        <td>
                                                            <?php if($participation['is_active'] == 1): ?>
                                                                <?php
                                                                    // Vérifier si l'utilisateur est le gagnant du tournoi
                                                                    $stmt = $conn->prepare("
                                                                        SELECT COUNT(*) as count
                                                                        FROM tournament_participants
                                                                        WHERE tournament_id = ? AND is_active = 1
                                                                    ");
                                                                    $stmt->execute([$participation['tournament_id']]);
                                                                    $active_count = $stmt->fetch()['count'];
                                                                    
                                                                    if($active_count == 1):
                                                                ?>
                                                                    <span class="badge bg-warning">Gagnant</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-success">Actif</span>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger">Éliminé</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Recent activity -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3 class="card-title">Activité récente</h3>
                            </div>
                            <div class="card-body">
                                <?php
                                // Récupérer les réponses récentes de l'utilisateur
                                $stmt = $conn->prepare("
                                    SELECT ua.*, q.question_text, q.correct_answer, tp.tournament_id, t.title as tournament_title
                                    FROM user_answers ua
                                    JOIN tournament_participants tp ON ua.participant_id = tp.id
                                    JOIN questions q ON ua.question_id = q.id
                                    JOIN tournaments t ON q.tournament_id = t.id
                                    WHERE tp.user_id = ?
                                    ORDER BY ua.created_at DESC
                                    LIMIT 10
                                ");
                                $stmt->execute([$user_id]);
                                $recent_answers = $stmt->fetchAll();
                                
                                if(empty($recent_answers)):
                                ?>
                                    <div class="alert alert-info">
                                        Aucune activité récente pour cet utilisateur.
                                    </div>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach($recent_answers as $answer): ?>
                                            <div class="list-group-item list-group-item-action">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h5 class="mb-1">
                                                        <a href="view_tournament.php?id=<?= $answer['tournament_id'] ?>">
                                                            <?= htmlspecialchars($answer['tournament_title']) ?>
                                                        </a>
                                                    </h5>
                                                    <small><?= date('d/m/Y H:i', strtotime($answer['created_at'])) ?></small>
                                                </div>
                                                <p class="mb-1"><?= htmlspecialchars($answer['question_text']) ?></p>
                                                <div class="d-flex justify-content-between">
                                                    <small class="text-muted">
                                                        Réponse: <strong><?= htmlspecialchars($answer['user_answer'] ?: 'Aucune réponse') ?></strong>
                                                    </small>
                                                    <small class="<?= $answer['is_correct'] ? 'text-success' : 'text-danger' ?>">
                                                        <?= $answer['is_correct'] ? 'Correct' : 'Incorrect (Réponse: ' . htmlspecialchars($answer['correct_answer']) . ')' ?>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
