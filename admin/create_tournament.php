<?php
session_start();
require_once '../config/database.php';

// Vérifier si l'utilisateur est connecté et est administrateur
if(!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: ../login.php');
    exit;
}

$errors = [];
$success = false;

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $total_questions = intval($_POST['total_questions'] ?? 0);
    $start_time = $_POST['start_time'] ?? '';
    $questions = $_POST['questions'] ?? [];
    $correct_answers = $_POST['correct_answers'] ?? [];
    
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
    
    if(count($questions) < $total_questions) {
        $errors[] = "Vous devez fournir au moins $total_questions questions";
    }
    
    // Vérifier que chaque question a un texte et une réponse correcte
    foreach($questions as $index => $question) {
        if(empty($question)) {
            $errors[] = "La question " . ($index + 1) . " ne peut pas être vide";
        }
        
        if(empty($correct_answers[$index])) {
            $errors[] = "La réponse correcte pour la question " . ($index + 1) . " ne peut pas être vide";
        }
    }
    
    // Créer le tournoi si pas d'erreurs
    if(empty($errors)) {
        try {
            $conn->beginTransaction();
            
            // Insérer le tournoi
            $stmt = $conn->prepare("INSERT INTO tournaments (title, total_questions, start_time) VALUES (?, ?, ?)");
            $stmt->execute([$title, $total_questions, date('Y-m-d H:i:s', $start_timestamp)]);
            $tournament_id = $conn->lastInsertId();
            
            // Insérer les questions
            for($i = 0; $i < $total_questions; $i++) {
                $stmt = $conn->prepare("INSERT INTO questions (tournament_id, question_text, correct_answer) VALUES (?, ?, ?)");
                $stmt->execute([$tournament_id, $questions[$i], $correct_answers[$i]]);
            }
            
            $conn->commit();
            $success = true;
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
    <title>Créer un tournoi - Administration Quiz Battle Royale</title>
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
                    <h1 class="h2">Créer un nouveau tournoi</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="tournaments.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Retour aux tournois
                        </a>
                    </div>
                </div>
                
                <?php if($success): ?>
                    <div class="alert alert-success">
                        <h4 class="alert-heading">Tournoi créé avec succès !</h4>
                        <p>Le tournoi a été créé et sera disponible à la date prévue.</p>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <a href="tournaments.php" class="btn btn-outline-success">Retour à la liste des tournois</a>
                            <a href="create_tournament.php" class="btn btn-success">Créer un autre tournoi</a>
                        </div>
                    </div>
                <?php else: ?>
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
                                            <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($title ?? '') ?>" required>
                                            <div class="form-text">Donnez un titre attractif à votre tournoi.</div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="total_questions" class="form-label">Nombre total de questions</label>
                                                <input type="number" class="form-control" id="total_questions" name="total_questions" min="1" value="<?= $total_questions ?? 10 ?>" required>
                                                <div class="form-text">Combien de questions souhaitez-vous inclure dans ce tournoi ?</div>
                                            </div>
                                            
                                            <div class="col-md-6 mb-3">
                                                <label for="start_time" class="form-label">Date et heure de début</label>
                                                <input type="datetime-local" class="form-control" id="start_time" name="start_time" value="<?= $start_time ?? '' ?>" required>
                                                <div class="form-text">Quand le tournoi doit-il commencer ?</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Questions -->
                                <div class="card mb-4">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h3 class="card-title mb-0">Questions du tournoi</h3>
                                        <button type="button" id="generate-questions" class="btn btn-primary btn-sm">
                                            <i class="bi bi-plus-circle"></i> Générer les champs
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <div id="questions-container">
                                            <!-- Les champs de questions seront générés ici -->
                                            <div class="alert alert-info">
                                                <i class="bi bi-info-circle"></i> Cliquez sur "Générer les champs" pour créer les champs de questions en fonction du nombre total spécifié.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-4">
                                    <a href="tournaments.php" class="btn btn-outline-secondary">Annuler</a>
                                    <button type="submit" class="btn btn-primary">Créer le tournoi</button>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <!-- Help card -->
                                <div class="card mb-4 bg-light">
                                    <div class="card-header">
                                        <h3 class="card-title">Aide</h3>
                                    </div>
                                    <div class="card-body">
                                        <h5><i class="bi bi-lightbulb"></i> Conseils pour créer un bon tournoi</h5>
                                        <ul>
                                            <li>Choisissez un titre clair et attractif</li>
                                            <li>Prévoyez suffisamment de questions pour le nombre de rounds attendus</li>
                                            <li>Variez les thèmes et la difficulté des questions</li>
                                            <li>Assurez-vous que les réponses sont précises et sans ambiguïté</li>
                                            <li>Planifiez le tournoi à une heure où les participants seront disponibles</li>
                                        </ul>
                                        
                                        <h5 class="mt-4"><i class="bi bi-question-circle"></i> Comment fonctionne l'élimination ?</h5>
                                        <p>Après chaque round, les 50% des participants ayant obtenu les meilleurs scores sont qualifiés pour le round suivant. Les autres sont éliminés.</p>
                                        
                                        <h5 class="mt-4"><i class="bi bi-calculator"></i> Calcul du nombre de rounds</h5>
                                        <p>Le nombre de rounds est calculé automatiquement en fonction du nombre de participants. Par exemple, avec 16 participants, il y aura 4 rounds (16 → 8 → 4 → 2 → 1).</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Générer les champs de questions en fonction du nombre total
        $('#generate-questions').click(function() {
            const totalQuestions = $('#total_questions').val();
            
            if(totalQuestions <= 0) {
                alert('Veuillez entrer un nombre valide de questions');
                return;
            }
            
            let html = '';
            
            for(let i = 0; i < totalQuestions; i++) {
                html += `
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Question ${i + 1}</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="question_${i}" class="form-label">Texte de la question</label>
                                <textarea class="form-control" id="question_${i}" name="questions[]" rows="2" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="correct_answer_${i}" class="form-label">Réponse correcte</label>
                                <input type="text" class="form-control" id="correct_answer_${i}" name="correct_answers[]" required>
                                <div class="form-text">Entrez la réponse exacte attendue.</div>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            $('#questions-container').html(html);
        });
        
        // Validation du formulaire
        $('#tournament-form').submit(function(e) {
            const totalQuestions = $('#total_questions').val();
            const questionFields = $('textarea[name="questions[]"]').length;
            
            if(questionFields < totalQuestions) {
                e.preventDefault();
                alert(`Veuillez générer et remplir les ${totalQuestions} champs de questions avant de soumettre le formulaire.`);
            }
        });
    });
    </script>
</body>
</html>
