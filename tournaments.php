<?php
session_start();
require_once 'config/database.php';

// Redirect if not logged in
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get upcoming tournaments
$stmt = $conn->prepare("SELECT * FROM tournaments WHERE start_time > NOW() ORDER BY start_time ASC");
$stmt->execute();
$upcoming_tournaments = $stmt->fetchAll();

// Get ongoing tournaments
$stmt = $conn->prepare("SELECT * FROM tournaments WHERE start_time <= NOW() ORDER BY start_time DESC");
$stmt->execute();
$ongoing_tournaments = $stmt->fetchAll();

// Get user's tournaments (where they are a participant)
$stmt = $conn->prepare("
    SELECT t.*, tp.score, tp.round, tp.is_active 
    FROM tournaments t
    JOIN tournament_participants tp ON t.id = tp.tournament_id
    WHERE tp.user_id = ?
    ORDER BY t.start_time DESC
");
$stmt->execute([$_SESSION['user_id']]);
$my_tournaments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tournois - Quiz Battle Royale</title>
    <link rel="stylesheet" href="css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <div class="row mt-4">
            <div class="col-md-12">
                <h1>Tournois</h1>
            </div>
        </div>
        
        <?php if(!empty($my_tournaments)): ?>
        <div class="row mt-4">
            <div class="col-md-12">
                <h2>Mes tournois</h2>
                <div class="list-group">
                    <?php foreach($my_tournaments as $tournament): ?>
                        <a href="tournament.php?id=<?= $tournament['id'] ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1"><?= htmlspecialchars($tournament['title']) ?></h5>
                                <small>
                                    <?php if($tournament['start_time'] > date('Y-m-d H:i:s')): ?>
                                        Commence le: <?= date('d/m/Y H:i', strtotime($tournament['start_time'])) ?>
                                    <?php else: ?>
                                        Commencé le: <?= date('d/m/Y H:i', strtotime($tournament['start_time'])) ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <p class="mb-1">
                                <?= $tournament['total_questions'] ?> questions au total | 
                                Score actuel: <?= $tournament['score'] ?> | 
                                Round: <?= $tournament['round'] ?>
                            </p>
                            <small class="<?= $tournament['is_active'] ? 'text-success' : 'text-danger' ?>">
                                <?= $tournament['is_active'] ? 'Actif' : 'Éliminé' ?>
                            </small>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if(!empty($ongoing_tournaments)): ?>
        <div class="row mt-4">
            <div class="col-md-12">
                <h2>Tournois en cours</h2>
                <div class="list-group">
                    <?php foreach($ongoing_tournaments as $tournament): ?>
                        <a href="tournament.php?id=<?= $tournament['id'] ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1"><?= htmlspecialchars($tournament['title']) ?></h5>
                                <small>Commencé le: <?= date('d/m/Y H:i', strtotime($tournament['start_time'])) ?></small>
                            </div>
                            <p class="mb-1"><?= $tournament['total_questions'] ?> questions au total</p>
                            <small class="text-success">En cours - Rejoindre maintenant !</small>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if(!empty($upcoming_tournaments)): ?>
        <div class="row mt-4">
            <div class="col-md-12">
                <h2>Tournois à venir</h2>
                <div class="list-group">
                    <?php foreach($upcoming_tournaments as $tournament): ?>
                        <a href="tournament_details.php?id=<?= $tournament['id'] ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1"><?= htmlspecialchars($tournament['title']) ?></h5>
                                <small>Commence le: <?= date('d/m/Y H:i', strtotime($tournament['start_time'])) ?></small>
                            </div>
                            <p class="mb-1"><?= $tournament['total_questions'] ?> questions au total</p>
                            <small>Inscrivez-vous pour participer</small>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if(empty($upcoming_tournaments) && empty($ongoing_tournaments) && empty($my_tournaments)): ?>
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="alert alert-info">
                    Aucun tournoi n'est disponible pour le moment. Revenez plus tard !
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
