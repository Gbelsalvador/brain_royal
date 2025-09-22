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

// Vérifier si le tournoi n'a pas encore commencé
$now = new DateTime();
$start_time = new DateTime($tournament['start_time']);

if($start_time <= $now) {
    header('Location: view_tournament.php?id=' . $tournament_id . '&error=started');
    exit;
}

// Récupérer les questions du tournoi
$stmt = $conn->prepare("SELECT * FROM questions WHERE tournament_id = ? ORDER BY id ASC");
$stmt->execute([$tournament_id]);
$questions = $stmt->fetchAll();

$errors = [];
$success = false;

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $total_questions = intval($_POST['total_questions'] ?? 0);
    $start_time = $_POST['start_time'] ?? '';
    $questions_text = $_POST['questions'] ?? [];
    $correct_answers = $_POST['correct_answers'] ?? [];
    $question_ids = $_POST['question_ids'] ?? [];
    
    // Validation
    if(empty($title)) {
        $errors[] = "Le titre du tournoi est requis";
    }
    
    if($total_questions <= 0) {
        $errors[] = "Le nombre de questions doit être supérieur à 0";
    }
    
    if(empty($start_time)) {
        $errors[] = "La date de début est requise";
    } else {
        $start_timestamp = strtotime($start_time);
        if($start_timestamp === false || $start_timestamp < time()) {
            $errors[] = "La date de début doit être dans le futur";
        }
    }
    
    // Vérifier que chaque question a un texte et une réponse correcte
    foreach($questions_text as $index => $question) {
        if(empty($question)) {
            $errors[] = "La question " . ($index + 1) . " ne peut pas être vide";
        }
        
        if(empty($correct_answers[$index])) {
            $errors[] = "La réponse correcte pour la question " . ($index + 1) . " ne peut pas être vide";
        }
    }
    
    // Mettre à jour le tournoi si pas d'erreurs
    if(empty($errors)) {
        try {
            $conn->beginTransaction();
            
            // Mettre à jour le tournoi
            $stmt = $conn->prepare("UPDATE tournaments SET title = ?, total_questions = ?, start_time = ? WHERE id = ?");
            $stmt->execute([$title, $total_questions, date('Y-m-d H:i:s', $start_timestamp), $tournament_id]);
            
            // Mettre à jour les questions existantes
            foreach($question_ids as $index => $question_id) {
                if(!empty($question_id)) {
                    $stmt = $conn->prepare("UPDATE questions SET question_text = ?, correct_answer = ? WHERE id = ?");
                    $stmt->execute([$questions_text[$index], $correct_answers[$index], $question_id]);
                } else {
                    // Ajouter une nouvelle question
                    $stmt = $conn->prepare("INSERT INTO questions (tournament_id, question_text, correct_answer) VALUES (?, ?, ?)");
                    $stmt->execute([$tournament_id, $questions_text[$index], $correct_answers[$index]]);
                }
            }
            
            $conn->commit();
            $success = true;
            
            // Recharger les données mises à jour
            $stmt = $conn->prepare("SELECT * FROM tournaments WHERE id = ?");
            $stmt->execute([$tournament_id]);
            $tournament = $stmt->fetch();
            
            $stmt = $conn->prepare("SELECT * FROM questions WHERE tournament_id = ? ORDER BY id ASC");
            $stmt->execute([$tournament_id]);
            $questions = $stmt->fetchAll();
        } catch(Exception $e) {
            $conn->rollBack();
            $errors[] = "Une erreur est survenue: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un tournoi - Administration Quiz Battle Royale</title>
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
                    <h1 class="h2">Modifier le tournoi</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="view_tournament.php?id=<?= $tournament_id ?>" class="btn btn-sm btn-outline-secondary me-2">
                            <i class="bi bi-eye"></i> Voir le tournoi
                        </a>
                        <a href="tournaments.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Retour aux tournois
                        </a>
                    </div>
                </div>
                
                <?php if($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <strong>Succès !</strong> Le tournoi a été mis à jour avec succès.
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
                
                <form method="POST" action="" id="tournament-form">
                    <div class="row">
                        <div class="col-md-8">
                            <!-- Tournament details -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h3 class="card-title">Informations du tournoi</h3>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Titre du tournoi</label>
                                        <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($tournament['title']) ?>" required>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="total_questions" class="form-label">Nombre total de questions</label>
                                            <input type="number" class="form-control" id="total_questions" name="total_questions" min="<?= count($questions) ?>" value="<?= $tournament['total_questions'] ?>" required>
                                            <div class="form-text">Vous ne pouvez pas réduire le nombre en dessous du nombre actuel de questions (<?= count($questions) ?>).</div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="start_time" class="form-label">Date et heure de début</label>
                                            <input type="datetime-local" class="form-control" id="start_time" name="start_time" value="<?= date('Y-m-d\TH:i', strtotime($tournament['start_time'])) ?>" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Questions -->
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h3 class="card-title mb-0">Questions du tournoi</h3>
                                    <button type="button" id="update-questions" class="btn btn-primary btn-sm">
                                        <i class="bi bi-arrow-repeat"></i> Mettre à jour les champs
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div id="questions-container">
                                        <?php foreach($questions as $index => $question): ?>
                                            <div class="card mb-3">
                                                <div class="card-header bg-light">
                                                    <h5 class="mb-0">Question <?= $index + 1 ?></h5>
                                                </div>
                                                <div class="card-body">
                                                    <input type="hidden" name="question_ids[]" value="<?= $question['id'] ?>">
                                                    <div class="mb-3">
                                                        <label for="question_<?= $index ?>" class="form-label">Texte de la question</label>
                                                        <textarea class="form-control" id="question_<?= $index ?>" name="questions[]" rows="2" required><?= htmlspecialchars($question['question_text']) ?></textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="correct_answer_<?= $index ?>" class="form-label">Réponse correcte</label>
                                                        <input type="text" class="form-control" id="correct_answer_<?= $index ?>" name="correct_answers[]" value="<?= htmlspecialchars($question['correct_answer']) ?>" required>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        
                                        <?php
                                        // Ajouter des champs vides si le nombre total de questions est supérieur au nombre actuel
                                        $current_count = count($questions);
                                        $total_count = $tournament['total_questions'];
                                        
                                        for($i = $current_count; $i < $total_count; $i++):
                                        ?>
                                            <div class="card mb-3">
                                                <div class="card-header bg-light">
                                                    <h5 class="mb-0">Question <?= $i + 1 ?> (Nouvelle)</h5>
                                                </div>
                                                <div class="card-body">
                                                    <input type="hidden" name="question_ids[]" value="">
                                                    <div class="mb-3">
                                                        <label for="question_<?= $i ?>" class="form-label">Texte de la question</label>
                                                        <textarea class="form-control" id="question_<?= $i ?>" name="questions[]" rows="2" required></textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="correct_answer_<?= $i ?>" class="form-label">Réponse correcte</label>
                                                        <input type="text" class="form-control" id="correct_answer_<?= $i ?>" name="correct_answers[]" required>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-4">
                                <a href="tournaments.php" class="btn btn-outline-secondary">Annuler</a>
                                <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <!-- Status card -->
                            <div class="card mb-4 border-info">
                                <div class="card-header bg-info text-white">
                                    <h3 class="card-title mb-0">Statut du tournoi</h3>
                                </div>
                                <div class="card-body">
                                    <p><strong>ID du tournoi:</strong> <?= $tournament_id ?></p>
                                    <p><strong>Date de création:</strong> <?= date('d/m/Y H:i', strtotime($tournament['created_at'])) ?></p>
                                    <p><strong>Statut:</strong> <span class="badge bg-info">À venir</span></p>
                                    <p><strong>Commence dans:</strong> 
                                        <?php
                                        $interval = $now->diff($start_time);
                                        if($interval->days > 0) {
                                            echo $interval->format('%a jours, %h heures et %i minutes');
                                        } else {
                                            echo $interval->format('%h heures et %i minutes');
                                        }
                                        ?>
                                    </p>
                                    
                                    <hr>
                                    
                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle"></i> Attention: Une fois que le tournoi a commencé, vous ne pourrez plus le modifier.
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Help card -->
                            <div class="card mb-4 bg-light">
                                <div class="card-header">
                                    <h3 class="card-title">Aide</h3>
                                </div>
                                <div class="card-body">
                                    <h5><i class="bi bi-lightbulb"></i> Conseils pour modifier un tournoi</h5>
                                    <ul>
                                        <li>Vous pouvez augmenter le nombre de questions, mais pas le réduire</li>
                                        <li>Assurez-vous que toutes les questions sont claires et précises</li>
                                        <li>Vérifiez l'orthographe des réponses correctes</li>
                                        <li>La date de début doit être dans le futur</li>
                                    </ul>
                                    
                                    <h5 class="mt-4"><i class="bi bi-arrow-repeat"></i> Mettre à jour les champs</h5>
                                    <p>Si vous modifiez le nombre total de questions, cliquez sur "Mettre à jour les champs" pour ajouter ou supprimer des champs de questions.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Mettre à jour les champs de questions en fonction du nombre total
        $('#update-questions').click(function() {
            const totalQuestions = $('#total_questions').val();
            const currentQuestions = $('#questions-container .card').length;
            
            if(totalQuestions < currentQuestions) {
                alert('Vous ne pouvez pas réduire le nombre de questions en dessous du nombre actuel.');
                $('#total_questions').val(currentQuestions);
                return;
            }
            
            // Sauvegarder les valeurs actuelles
            const questionIds = [];
            const questionTexts = [];
            const correctAnswers = [];
            
            $('#questions-container .card').each(function(index) {
                questionIds.push($(this).find('input[name="question_ids[]"]').val());
                questionTexts.push($(this).find('textarea[name="questions[]"]').val());
                correctAnswers.push($(this).find('input[name="correct_answers[]"]').val());
            });
            
            // Générer le HTML pour toutes les questions
            let html = '';
            
            for(let i = 0; i < totalQuestions; i++) {
                const isNew = i >= currentQuestions;
                
                html += `
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Question ${i + 1}${isNew ? ' (Nouvelle)' : ''}</h5>
                        </div>
                        <div class="card-body">
                            <input type="hidden" name="question_ids[]" value="${isNew ? '' : questionIds[i]}">
                            <div class="mb-3">
                                <label for="question_${i}" class="form-label">Texte de la question</label>
                                <textarea class="form-control" id="question_${i}" name="questions[]" rows="2" required>${isNew ? '' : questionTexts[i] || ''}</textarea>
                            </div>
                            <div class="mb-3">
                                <label for="correct_answer_${i}" class="form-label">Réponse correcte</label>
                                <input type="text" class="form-control" id="correct_answer_${i}" name="correct_answers[]" value="${isNew ? '' : correctAnswers[i] || ''}" required>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            $('#questions-container').html(html);
        });
    });
    </script>
</body>
</html>
