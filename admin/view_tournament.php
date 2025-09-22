<?php
session_start();
require_once '../config/database.php';

// Vérifier si l'utilisateur est connecté et est administrateur
if(!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: ../login.php');
    exit;
}

// Vérifier si l'ID du tournoi est fourni
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: tournaments.php');
    exit;
}

$tournament_id = $_GET['id'];

// Récupérer les informations du tournoi
$stmt = $conn->prepare("SELECT * FROM tournaments WHERE id = ?");
$stmt->execute([$tournament_id]);
$tournament = $stmt->fetch();

if(!$tournament) {
    header('Location: tournaments.php');
    exit;
}

// Récupérer les questions du tournoi
$stmt = $conn->prepare("SELECT * FROM questions WHERE tournament_id = ? ORDER BY id ASC");
$stmt->execute([$tournament_id]);
$questions = $stmt->fetchAll();

// Récupérer les participants du tournoi
$stmt = $conn->prepare("
    SELECT tp.*, u.username, u.email,
           (SELECT COUNT(*) FROM user_answers WHERE participant_id = tp.id) as answers_count
    FROM tournament_participants tp
    JOIN users u ON tp.user_id = u.id
    WHERE tp.tournament_id = ?
    ORDER BY tp.score DESC, tp.id ASC
");
$stmt->execute([$tournament_id]);
$participants = $stmt->fetchAll();

// Déterminer le statut du tournoi
$now = new DateTime();
$start_time = new DateTime($tournament['start_time']);

if($start_time > $now) {
    $status = 'upcoming';
    $status_text = 'À venir';
    $status_class = 'bg-info';
} else {
    // Vérifier s'il y a des participants actifs
    $active_participants = array_filter($participants, function($p) {
        return $p['is_active'] == 1;
    });
    
    if(count($active_participants) > 1) {
        $status = 'active';
        $status_text = 'En cours';
        $status_class = 'bg-success';
    } elseif(count($active_participants) == 1) {
        $status = 'finished';
        $status_text = 'Terminé';
        $status_class = 'bg-primary';
    } else {
        $status = 'no_participants';
        $status_text = 'Aucun participant';
        $status_class = 'bg-warning';
    }
}

// Récupérer le gagnant si le tournoi est terminé
$winner = null;
if($status === 'finished' && !empty($active_participants)) {
    $winner = reset($active_participants);
}

// Récupérer les statistiques du tournoi
$stats = [
    'total_participants' => count($participants),
    'active_participants' => count(array_filter($participants, function($p) { return $p['is_active'] == 1; })),
    'eliminated_participants' => count(array_filter($participants, function($p) { return $p['is_active'] == 0; })),
    'total_questions' => count($questions),
    'highest_score' => !empty($participants) ? max(array_column($participants, 'score')) : 0,
    'average_score' => !empty($participants) ? array_sum(array_column($participants, 'score')) / count($participants) : 0,
    'max_round' => !empty($participants) ? max(array_column($participants, 'round')) : 1
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails du tournoi - Administration Quiz Battle Royale</title>
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
                    <h1 class="h2"><?= htmlspecialchars($tournament['title']) ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if($status === 'upcoming'): ?>
                            <a href="edit_tournament.php?id=<?= $tournament_id ?>" class="btn btn-sm btn-warning me-2">
                                <i class="bi bi-pencil"></i> Modifier
                            </a>
                        <?php endif; ?>
                        <a href="tournaments.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Retour aux tournois
                        </a>
                    </div>
                </div>
                
                <?php if(isset($_GET['error']) && $_GET['error'] === 'started'): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        Ce tournoi a déjà commencé et ne peut plus être modifié.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-8">
                        <!-- Tournament details -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3 class="card-title">Informations du tournoi</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>ID:</strong> <?= $tournament_id ?></p>
                                        <p><strong>Titre:</strong> <?= htmlspecialchars($tournament['title']) ?></p>
                                        <p><strong>Nombre de questions:</strong> <?= $tournament['total_questions'] ?></p>
                                        <p><strong>Date de début:</strong> <?= date('d/m/Y H:i', strtotime($tournament['start_time'])) ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Statut:</strong> <span class="badge <?= $status_class ?>"><?= $status_text ?></span></p>
                                        <p><strong>Participants:</strong> <?= $stats['total_participants'] ?> (<?= $stats['active_participants'] ?> actifs, <?= $stats['eliminated_participants'] ?> éliminés)</p>
                                        <p><strong>Round actuel:</strong> <?= $stats['max_round'] ?></p>
                                        <p><strong>Créé le:</strong> <?= date('d/m/Y H:i', strtotime($tournament['created_at'])) ?></p>
                                    </div>
                                </div>
                                
                                <?php if($status === 'finished' && $winner): ?>
                                    <div class="alert alert-success mt-3">
                                        <h4 class="alert-heading"><i class="bi bi-trophy"></i> Tournoi terminé !</h4>
                                        <p>Le gagnant est <strong><?= htmlspecialchars($winner['username']) ?></strong> avec un score de <strong><?= $winner['score'] ?></strong> points.</p>
                                    </div>
                                <?php elseif($status === 'active'): ?>
                                    <div class="alert alert-info mt-3">
                                        <h4 class="alert-heading"><i class="bi bi-play-circle"></i> Tournoi en cours</h4>
                                        <p>Le tournoi est actuellement en cours avec <?= $stats['active_participants'] ?> participants actifs au round <?= $stats['max_round'] ?>.</p>
                                    </div>
                                <?php elseif($status === 'upcoming'): ?>
                                    <div class="alert alert-warning mt-3">
                                        <h4 class="alert-heading"><i class="bi bi-clock"></i> Tournoi à venir</h4>
                                        <p>Le tournoi commencera le <?= date('d/m/Y à H:i', strtotime($tournament['start_time'])) ?>.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Participants -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h3 class="card-title mb-0">Participants (<?= count($participants) ?>)</h3>
                                <?php if(!empty($participants)): ?>
                                    <a href="export_participants.php?id=<?= $tournament_id ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-download"></i> Exporter
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="card-body p-0">
                                <?php if(empty($participants)): ?>
                                    <div class="alert alert-info m-3">
                                        Aucun participant n'est inscrit à ce tournoi pour le moment.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Utilisateur</th>
                                                    <th>Email</th>
                                                    <th>Score</th>
                                                    <th>Round</th>
                                                    <th>Réponses</th>
                                                    <th>Statut</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($participants as $participant): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($participant['username']) ?></td>
                                                        <td><?= htmlspecialchars($participant['email']) ?></td>
                                                        <td><?= $participant['score'] ?></td>
                                                        <td><?= $participant['round'] ?></td>
                                                        <td><?= $participant['answers_count'] ?> / <?= $stats['total_questions'] ?></td>
                                                        <td>
                                                            <?php if($participant['is_active'] == 1): ?>
                                                                <span class="badge bg-success">Actif</span>
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
                    </div>
                    
                    <div class="col-md-4">
                        <!-- Tournament stats -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3 class="card-title">Statistiques</h3>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-3">
                                    <div class="text-center">
                                        <h4><?= $stats['total_participants'] ?></h4>
                                        <p class="text-muted">Participants</p>
                                    </div>
                                    <div class="text-center">
                                        <h4><?= $stats['total_questions'] ?></h4>
                                        <p class="text-muted">Questions</p>
                                    </div>
                                    <div class="text-center">
                                        <h4><?= $stats['max_round'] ?></h4>
                                        <p class="text-muted">Rounds</p>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="mb-3">
                                    <label class="form-label">Score le plus élevé</label>
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: 100%;" aria-valuenow="<?= $stats['highest_score'] ?>" aria-valuemin="0" aria-valuemax="<?= $stats['highest_score'] ?>">
                                            <?= $stats['highest_score'] ?> points
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Score moyen</label>
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar bg-info" role="progressbar" style="width: <?= ($stats['average_score'] / max(1, $stats['highest_score'])) * 100 ?>%;" aria-valuenow="<?= round($stats['average_score']) ?>" aria-valuemin="0" aria-valuemax="<?= $stats['highest_score'] ?>">
                                            <?= round($stats['average_score']) ?> points
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Participants actifs vs éliminés</label>
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= ($stats['active_participants'] / max(1, $stats['total_participants'])) * 100 ?>%;" aria-valuenow="<?= $stats['active_participants'] ?>" aria-valuemin="0" aria-valuemax="<?= $stats['total_participants'] ?>">
                                            <?= $stats['active_participants'] ?> actifs
                                        </div>
                                        <div class="progress-bar bg-danger" role="progressbar" style="width: <?= ($stats['eliminated_participants'] / max(1, $stats['total_participants'])) * 100 ?>%;" aria-valuenow="<?= $stats['eliminated_participants'] ?>" aria-valuemin="0" aria-valuemax="<?= $stats['total_participants'] ?>">
                                            <?= $stats['eliminated_participants'] ?> éliminés
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Questions preview -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h3 class="card-title mb-0">Questions (<?= count($questions) ?>)</h3>
                                <a href="questions.php?tournament_id=<?= $tournament_id ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-list-check"></i> Gérer
                                </a>
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush">
                                    <?php if(empty($questions)): ?>
                                        <div class="list-group-item text-center py-4">
                                            Aucune question n'a été ajoutée à ce tournoi.
                                        </div>
                                    <?php else: ?>
                                        <?php foreach(array_slice($questions, 0, 5) as $index => $question): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1">Question <?= $index + 1 ?></h6>
                                                </div>
                                                <p class="mb-1"><?= htmlspecialchars($question['question_text']) ?></p>
                                                <small class="text-muted">Réponse: <?= htmlspecialchars($question['correct_answer']) ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                        
                                        <?php if(count($questions) > 5): ?>
                                            <div class="list-group-item text-center">
                                                <a href="questions.php?tournament_id=<?= $tournament_id ?>" class="text-decoration-none">
                                                    Voir toutes les questions (<?= count($questions) ?>)
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3 class="card-title">Actions</h3>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <?php if($status === 'upcoming'): ?>
                                        <a href="edit_tournament.php?id=<?= $tournament_id ?>" class="btn btn-warning">
                                            <i class="bi bi-pencil"></i> Modifier le tournoi
                                        </a>
                                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                            <i class="bi bi-trash"></i> Supprimer le tournoi
                                        </button>
                                    <?php endif; ?>
                                    
                                    <a href="../tournament.php?id=<?= $tournament_id ?>" class="btn btn-primary" target="_blank">
                                        <i class="bi bi-eye"></i> Voir côté utilisateur
                                    </a>
                                    
                                    <?php if($status === 'active'): ?>
                                        <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#monitorModal">
                                            <i class="bi bi-display"></i> Surveiller en direct
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer le tournoi <strong><?= htmlspecialchars($tournament['title']) ?></strong> ?</p>
                    <p class="text-danger">Cette action est irréversible et supprimera toutes les questions et participations associées.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <form action="tournaments.php" method="POST">
                        <input type="hidden" name="tournament_id" value="<?= $tournament_id ?>">
                        <button type="submit" name="delete_tournament" class="btn btn-danger">Supprimer</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Monitor Modal -->
    <?php if($status === 'active'): ?>
    <div class="modal fade" id="monitorModal" tabindex="-1" aria-labelledby="monitorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="monitorModalLabel">Surveillance en direct - <?= htmlspecialchars($tournament['title']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="mb-0">Statut actuel</h5>
                                </div>
                                <div class="card-body">
                                    <div id="live-status">
                                        <div class="d-flex justify-content-center">
                                            <div class="spinner-border" role="status">
                                                <span class="visually-hidden">Chargement...</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Activité récente</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div id="live-activity" class="list-group list-group-flush" style="max-height: 300px; overflow-y: auto;">
                                        <div class="list-group-item text-center py-4">
                                            <div class="spinner-border spinner-border-sm" role="status">
                                                <span class="visually-hidden">Chargement...</span>
                                            </div>
                                            Chargement de l'activité...
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Participants actifs</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div id="live-participants" class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                                        <div class="list-group-item text-center py-4">
                                            <div class="spinner-border spinner-border-sm" role="status">
                                                <span class="visually-hidden">Chargement...</span>
                                            </div>
                                            Chargement des participants...
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    $(document).ready(function() {
        let monitorInterval;
        
        // Charger les données en direct lorsque le modal est ouvert
        $('#monitorModal').on('shown.bs.modal', function () {
            loadLiveData();
            monitorInterval = setInterval(loadLiveData, 5000); // Rafraîchir toutes les 5 secondes
        });
        
        // Arrêter le rafraîchissement lorsque le modal est fermé
        $('#monitorModal').on('hidden.bs.modal', function () {
            clearInterval(monitorInterval);
        });
        
        function loadLiveData() {
            // Charger le statut actuel
            $.ajax({
                url: 'ajax/get_tournament_status.php',
                type: 'GET',
                data: { tournament_id: <?= $tournament_id ?> },
                dataType: 'json',
                success: function(response) {
                    updateLiveStatus(response);
                }
            });
            
            // Charger les participants actifs
            $.ajax({
                url: 'ajax/get_active_participants.php',
                type: 'GET',
                data: { tournament_id: <?= $tournament_id ?> },
                dataType: 'json',
                success: function(response) {
                    updateLiveParticipants(response);
                }
            });
            
            // Charger l'activité récente
            $.ajax({
                url: 'ajax/get_recent_activity.php',
                type: 'GET',
                data: { tournament_id: <?= $tournament_id ?> },
                dataType: 'json',
                success: function(response) {
                    updateLiveActivity(response);
                }
            });
        }
        
        function updateLiveStatus(data) {
            let html = `
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Round actuel:</strong> ${data.current_round}</p>
                        <p><strong>Participants actifs:</strong> ${data.active_participants}</p>
                        <p><strong>Questions restantes:</strong> ${data.remaining_questions}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Statut:</strong> <span class="badge ${data.status_class}">${data.status_text}</span></p>
                        <p><strong>Score le plus élevé:</strong> ${data.highest_score} points</p>
                        <p><strong>Temps écoulé:</strong> ${data.elapsed_time}</p>
                    </div>
                </div>
            `;
            
            $('#live-status').html(html);
        }
        
        function updateLiveParticipants(data) {
            if(data.participants.length === 0) {
                $('#live-participants').html('<div class="list-group-item text-center py-4">Aucun participant actif</div>');
                return;
            }
            
            let html = '';
            
            data.participants.forEach(function(participant, index) {
                html += `
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">${index + 1}. ${participant.username}</h6>
                            <span class="badge bg-primary">${participant.score} pts</span>
                        </div>
                        <p class="mb-1">Round: ${participant.round}</p>
                        <small class="text-muted">Réponses: ${participant.answers_count} / ${data.total_questions}</small>
                    </div>
                `;
            });
            
            $('#live-participants').html(html);
        }
        
        function updateLiveActivity(data) {
            if(data.activities.length === 0) {
                $('#live-activity').html('<div class="list-group-item text-center py-4">Aucune activité récente</div>');
                return;
            }
            
            let html = '';
            
            data.activities.forEach(function(activity) {
                let icon, badgeClass;
                
                switch(activity.type) {
                    case 'answer':
                        icon = activity.is_correct ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger';
                        badgeClass = activity.is_correct ? 'bg-success' : 'bg-danger';
                        break;
                    case 'join':
                        icon = 'bi-person-plus-fill text-primary';
                        badgeClass = 'bg-primary';
                        break;
                    case 'elimination':
                        icon = 'bi-person-x-fill text-warning';
                        badgeClass = 'bg-warning';
                        break;
                    case 'advance':
                        icon = 'bi-arrow-up-circle-fill text-info';
                        badgeClass = 'bg-info';
                        break;
                    default:
                        icon = 'bi-info-circle-fill text-secondary';
                        badgeClass = 'bg-secondary';
                }
                
                html += `
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <div>
                                <i class="bi ${icon} me-2"></i>
                                <span>${activity.message}</span>
                            </div>
                            <small class="text-muted">${activity.time_ago}</small>
                        </div>
                    </div>
                `;
            });
            
            $('#live-activity').html(html);
        }
    });
    </script>
    <?php endif; ?>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
