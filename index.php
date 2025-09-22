<?php
session_start();
require_once 'config/database.php';

// Check if there are any active tournaments
$stmt = $conn->prepare("SELECT * FROM tournaments WHERE start_time IS NOT NULL AND start_time > NOW() ORDER BY start_time ASC");
$stmt->execute();
$upcoming_tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check for ongoing tournaments
$stmt = $conn->prepare("SELECT * FROM tournaments WHERE start_time IS NOT NULL AND start_time <= NOW() ORDER BY start_time DESC");
$stmt->execute();
$ongoing_tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Battle Royale</title>
    <link rel="stylesheet" href="css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <div class="row mt-5">
            <div class="col-md-12">
                <div class="jumbotron text-center">
                    <h1 class="display-4">Quiz Battle Royale</h1>
                    <p class="lead">Affrontez d'autres joueurs dans un tournoi de quiz en temps réel !</p>
                    
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="tournaments.php" class="btn btn-primary btn-lg mt-3">Voir les tournois</a>
                    <?php else: ?>
                        <p>Connectez-vous pour participer aux tournois</p>
                        <a href="login.php" class="btn btn-primary">Connexion</a>
                        <a href="register.php" class="btn btn-secondary">Inscription</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if(!empty($upcoming_tournaments)): ?>
        <div class="row mt-5">
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
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if(!empty($ongoing_tournaments)): ?>
        <div class="row mt-5">
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
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
