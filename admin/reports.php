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
    'total_tournaments' => 0,
    'total_users' => 0,
    'total_questions' => 0,
    'total_answers' => 0,
    'correct_answers' => 0,
    'active_tournaments' => 0
];

// Nombre total de tournois
$stmt = $conn->query("SELECT COUNT(*) FROM tournaments");
$stats['total_tournaments'] = $stmt->fetchColumn();

// Nombre total d'utilisateurs
$stmt = $conn->query("SELECT COUNT(*) FROM users");
$stats['total_users'] = $stmt->fetchColumn();

// Nombre total de questions
$stmt = $conn->query("SELECT COUNT(*) FROM questions");
$stats['total_questions'] = $stmt->fetchColumn();

// Nombre total de réponses
$stmt = $conn->query("SELECT COUNT(*) FROM user_answers");
$stats['total_answers'] = $stmt->fetchColumn();

// Nombre de réponses correctes
$stmt = $conn->query("SELECT COUNT(*) FROM user_answers WHERE is_correct = 1");
$stats['correct_answers'] = $stmt->fetchColumn();

// Nombre de tournois actifs
$stmt = $conn->query("
    SELECT COUNT(DISTINCT t.id) 
    FROM tournaments t
    JOIN tournament_participants tp ON t.id = tp.tournament_id
    WHERE t.start_time <= NOW() AND tp.is_active = 1
");
$stats['active_tournaments'] = $stmt->fetchColumn();

// Taux de réponses correctes
$stats['correct_rate'] = $stats['total_answers'] > 0 ? ($stats['correct_answers'] / $stats['total_answers']) * 100 : 0;

// Récupérer les tournois les plus populaires
$stmt = $conn->query("
    SELECT t.id, t.title, t.start_time, COUNT(tp.id) as participant_count
    FROM tournaments t
    JOIN tournament_participants tp ON t.id = tp.tournament_id
    GROUP BY t.id
    ORDER BY participant_count DESC
    LIMIT 5
");
$popular_tournaments = $stmt->fetchAll();

// Récupérer les utilisateurs les plus actifs
$stmt = $conn->query("
    SELECT u.id, u.username, COUNT(tp.id) as tournament_count, SUM(tp.score) as total_score
    FROM users u
    JOIN tournament_participants tp ON u.id = tp.user_id
    GROUP BY u.id
    ORDER BY tournament_count DESC, total_score DESC
    LIMIT 5
");
$active_users = $stmt->fetchAll();

// Récupérer les questions les plus difficiles (avec le taux de réponses correctes le plus bas)
$stmt = $conn->query("
    SELECT q.id, q.question_text, q.correct_answer, t.title as tournament_title,
           COUNT(ua.id) as answer_count,
           SUM(CASE WHEN ua.is_correct = 1 THEN 1 ELSE 0 END) as correct_count
    FROM questions q
    JOIN user_answers ua ON q.id = ua.question_id
    JOIN tournaments t ON q.tournament_id = t.id
    GROUP BY q.id
    HAVING answer_count > 5
    ORDER BY (correct_count / answer_count) ASC
    LIMIT 5
");
$difficult_questions = $stmt->fetchAll();

// Récupérer les données pour le graphique des tournois par mois
$stmt = $conn->query("
    SELECT DATE_FORMAT(start_time, '%Y-%m') as month, COUNT(*) as count
    FROM tournaments
    WHERE start_time >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY month
    ORDER BY month ASC
");
$tournaments_by_month = $stmt->fetchAll();

// Formater les données pour le graphique
$chart_labels = [];
$chart_data = [];

foreach($tournaments_by_month as $item) {
    $date = new DateTime($item['month'] . '-01');
    $chart_labels[] = $date->format('M Y');
    $chart_data[] = $item['count'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports - Administration Quiz Battle Royale</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Rapports et statistiques</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                            <i class="bi bi-printer"></i> Imprimer
                        </button>
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
                                        <h2 class="mb-0"><?= $stats['total_tournaments'] ?></h2>
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
                                        <h2 class="mb-0"><?= $stats['total_users'] ?></h2>
                                    </div>
                                    <i class="bi bi-people fs-1"></i>
                                </div>
                                <div class="mt-3">
                                    <span class="text-white-50">Participants actifs</span>
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
                                        <h2 class="mb-0"><?= $stats['total_questions'] ?></h2>
                                    </div>
                                    <i class="bi bi-question-circle fs-1"></i>
                                </div>
                                <div class="mt-3">
                                    <span class="text-white-50"><?= $stats['total_answers'] ?> réponses au total</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Tournaments chart -->
                    <div class="col-md-8 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Tournois par mois</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="tournamentsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Answers stats -->
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Statistiques des réponses</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="answersChart"></canvas>
                                <div class="text-center mt-3">
                                    <p class="mb-0">Taux de réponses correctes: <strong><?= round($stats['correct_rate']) ?>%</strong></p>
                                    <p class="text-muted"><?= $stats['correct_answers'] ?> correctes sur <?= $stats['total_answers'] ?> réponses</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Popular tournaments -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Tournois les plus populaires</h3>
                            </div>
                            <div class="card-body p-0">
                                <?php if(empty($popular_tournaments)): ?>
                                    <div class="alert alert-info m-3">
                                        Aucun tournoi trouvé.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Tournoi</th>
                                                    <th>Date</th>
                                                    <th>Participants</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($popular_tournaments as $tournament): ?>
                                                    <tr>
                                                        <td>
                                                            <a href="view_tournament.php?id=<?= $tournament['id'] ?>">
                                                                <?= htmlspecialchars($tournament['title']) ?>
                                                            </a>
                                                        </td>
                                                        <td><?= date('d/m/Y', strtotime($tournament['start_time'])) ?></td>
                                                        <td><span class="badge bg-primary"><?= $tournament['participant_count'] ?></span></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Active users -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Utilisateurs les plus actifs</h3>
                            </div>
                            <div class="card-body p-0">
                                <?php if(empty($active_users)): ?>
                                    <div class="alert alert-info m-3">
                                        Aucun utilisateur actif trouvé.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Utilisateur</th>
                                                    <th>Tournois</th>
                                                    <th>Score total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($active_users as $user): ?>
                                                    <tr>
                                                        <td>
                                                            <a href="view_user.php?id=<?= $user['id'] ?>">
                                                                <?= htmlspecialchars($user['username']) ?>
                                                            </a>
                                                        </td>
                                                        <td><span class="badge bg-success"><?= $user['tournament_count'] ?></span></td>
                                                        <td><?= $user['total_score'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Difficult questions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">Questions les plus difficiles</h3>
                    </div>
                    <div class="card-body p-0">
                        <?php if(empty($difficult_questions)): ?>
                            <div class="alert alert-info m-3">
                                Pas assez de données pour déterminer les questions difficiles.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Question</th>
                                            <th>Tournoi</th>
                                            <th>Réponse correcte</th>
                                            <th>Taux de réussite</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($difficult_questions as $question): ?>
                                            <?php $success_rate = ($question['correct_count'] / $question['answer_count']) * 100; ?>
                                            <tr>
                                                <td><?= htmlspecialchars($question['question_text']) ?></td>
                                                <td><?= htmlspecialchars($question['tournament_title']) ?></td>
                                                <td><?= htmlspecialchars($question['correct_answer']) ?></td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar <?= $success_rate < 30 ? 'bg-danger' : ($success_rate < 60 ? 'bg-warning' : 'bg-success') ?>" 
                                                             role="progressbar" 
                                                             style="width: <?= $success_rate ?>%;" 
                                                             aria-valuenow="<?= round($success_rate) ?>" 
                                                             aria-valuemin="0" 
                                                             aria-valuemax="100">
                                                            <?= round($success_rate) ?>%
                                                        </div>
                                                    </div>
                                                    <small class="text-muted"><?= $question['correct_count'] ?> / <?= $question['answer_count'] ?> réponses correctes</small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Tournois par mois
    const tournamentsCtx = document.getElementById('tournamentsChart').getContext('2d');
    const tournamentsChart = new Chart(tournamentsCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [{
                label: 'Nombre de tournois',
                data: <?= json_encode($chart_data) ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    
    // Réponses correctes vs incorrectes
    const answersCtx = document.getElementById('answersChart').getContext('2d');
    const answersChart = new Chart(answersCtx, {
        type: 'doughnut',
        data: {
            labels: ['Correctes', 'Incorrectes'],
            datasets: [{
                data: [<?= $stats['correct_answers'] ?>, <?= $stats['total_answers'] - $stats['correct_answers'] ?>],
                backgroundColor: [
                    'rgba(75, 192, 192, 0.5)',
                    'rgba(255, 99, 132, 0.5)'
                ],
                borderColor: [
                    'rgba(75, 192, 192, 1)',
                    'rgba(255, 99, 132, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    </script>
</body>
</html>
