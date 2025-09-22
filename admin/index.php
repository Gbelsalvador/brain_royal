<?php
session_start();
require_once '../config/database.php';

// Vérifier si l'utilisateur est connecté et est administrateur
if(!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: ../login.php');
    exit;
}

// Récupérer les statistiques générales
$stats = [
    'tournaments' => 0,
    'active_tournaments' => 0,
    'users' => 0,
    'questions' => 0,
    'participants' => 0
];

// Nombre total de tournois
$stmt = $conn->query("SELECT COUNT(*) FROM tournaments");
$stats['tournaments'] = $stmt->fetchColumn();

// Nombre de tournois actifs (dont la date de début est passée mais qui ont encore des participants actifs)
$stmt = $conn->query("
    SELECT COUNT(DISTINCT t.id) 
    FROM tournaments t
    JOIN tournament_participants tp ON t.id = tp.tournament_id
    WHERE t.start_time <= NOW() AND tp.is_active = 1
");
$stats['active_tournaments'] = $stmt->fetchColumn();

// Nombre total d'utilisateurs
$stmt = $conn->query("SELECT COUNT(*) FROM users");
$stats['users'] = $stmt->fetchColumn();

// Nombre total de questions
$stmt = $conn->query("SELECT COUNT(*) FROM questions");
$stats['questions'] = $stmt->fetchColumn();

// Nombre total de participations
$stmt = $conn->query("SELECT COUNT(*) FROM tournament_participants");
$stats['participants'] = $stmt->fetchColumn();

// Récupérer les tournois récents
$stmt = $conn->query("
    SELECT t.*, 
           (SELECT COUNT(*) FROM tournament_participants WHERE tournament_id = t.id) as participant_count
    FROM tournaments t
    ORDER BY t.created_at DESC
    LIMIT 5
");
$recent_tournaments = $stmt->fetchAll();

// Récupérer les utilisateurs récents
$stmt = $conn->query("
    SELECT * FROM users
    ORDER BY created_at DESC
    LIMIT 5
");
$recent_users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Administration Quiz Battle Royale</title>
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
                    <h1 class="h2">Tableau de bord</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="create_tournament.php" class="btn btn-sm btn-primary">
                            <i class="bi bi-plus-circle"></i> Nouveau tournoi
                        </a>
                    </div>
                </div>
                
                <!-- Stats cards -->
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Tournois</h6>
                                        <h2 class="mb-0"><?= $stats['tournaments'] ?></h2>
                                    </div>
                                    <i class="bi bi-trophy fs-1"></i>
                                </div>
                                <div class="mt-3">
                                    <span class="text-white-50">Dont <?= $stats['active_tournaments'] ?> actifs</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-4">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Utilisateurs</h6>
                                        <h2 class="mb-0"><?= $stats['users'] ?></h2>
                                    </div>
                                    <i class="bi bi-people fs-1"></i>
                                </div>
                                <div class="mt-3">
                                    <span class="text-white-50"><?= $stats['participants'] ?> participations au total</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-4">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Questions</h6>
                                        <h2 class="mb-0"><?= $stats['questions'] ?></h2>
                                    </div>
                                    <i class="bi bi-question-circle fs-1"></i>
                                </div>
                                <div class="mt-3">
                                    <span class="text-white-50">Dans la base de données</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Recent tournaments -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Tournois récents</h5>
                                    <a href="tournaments.php" class="btn btn-sm btn-outline-secondary">Voir tous</a>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Titre</th>
                                                <th>Date de début</th>
                                                <th>Participants</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(empty($recent_tournaments)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">Aucun tournoi trouvé</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach($recent_tournaments as $tournament): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($tournament['title']) ?></td>
                                                        <td><?= date('d/m/Y H:i', strtotime($tournament['start_time'])) ?></td>
                                                        <td><?= $tournament['participant_count'] ?></td>
                                                        <td>
                                                            <a href="view_tournament.php?id=<?= $tournament['id'] ?>" class="btn btn-sm btn-info">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent users -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Utilisateurs récents</h5>
                                    <a href="users.php" class="btn btn-sm btn-outline-secondary">Voir tous</a>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Nom d'utilisateur</th>
                                                <th>Email</th>
                                                <th>Date d'inscription</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(empty($recent_users)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">Aucun utilisateur trouvé</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach($recent_users as $user): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($user['username']) ?></td>
                                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                                        <td><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td>
                                                        <td>
                                                            <a href="view_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-info">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick actions -->
                <div class="row">
                    <div class="col-md-12 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Actions rapides</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <a href="create_tournament.php" class="btn btn-primary w-100 py-3">
                                            <i class="bi bi-plus-circle fs-4 d-block mb-2"></i>
                                            Créer un tournoi
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="questions.php" class="btn btn-success w-100 py-3">
                                            <i class="bi bi-question-circle fs-4 d-block mb-2"></i>
                                            Gérer les questions
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="users.php" class="btn btn-info w-100 py-3">
                                            <i class="bi bi-people fs-4 d-block mb-2"></i>
                                            Gérer les utilisateurs
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="reports.php" class="btn btn-warning w-100 py-3">
                                            <i class="bi bi-bar-chart fs-4 d-block mb-2"></i>
                                            Voir les rapports
                                        </a>
                                    </div>
                                </div>
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
