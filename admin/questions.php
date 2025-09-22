<?php
session_start();
require_once '../config/database.php';

// Vérifier si l'utilisateur est connecté et est administrateur
if(!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: ../login.php');
    exit;
}

// Filtres
$tournament_id = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Paramètres de pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Construction de la requête
$query = "SELECT q.*, t.title as tournament_title 
          FROM questions q
          JOIN tournaments t ON q.tournament_id = t.id
          WHERE 1=1";
$params = [];

if($tournament_id > 0) {
    $query .= " AND q.tournament_id = ?";
    $params[] = $tournament_id;
}

if(!empty($search)) {
    $query .= " AND (q.question_text LIKE ? OR q.correct_answer LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Compter le nombre total de questions pour la pagination
$count_query = str_replace("q.*, t.title as tournament_title", "COUNT(*) as total", $query);
$stmt = $conn->prepare($count_query);
$stmt->execute($params);
$total_questions = $stmt->fetchColumn();
$total_pages = ceil($total_questions / $limit);

// Ajouter l'ordre et la limite à la requête principale
$query .= " ORDER BY q.id DESC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$questions = $stmt->fetchAll();

// Récupérer la liste des tournois pour le filtre
$stmt = $conn->prepare("SELECT id, title FROM tournaments ORDER BY start_time DESC");
$stmt->execute();
$tournaments = $stmt->fetchAll();

// Traitement de la suppression
if(isset($_POST['delete_question']) && isset($_POST['question_id'])) {
    $question_id = $_POST['question_id'];
    
    try {
        // Vérifier si la question est utilisée dans des réponses
        $stmt = $conn->prepare("SELECT COUNT(*) FROM user_answers WHERE question_id = ?");
        $stmt->execute([$question_id]);
        $has_answers = $stmt->fetchColumn() > 0;
        
        if($has_answers) {
            $error_message = "Impossible de supprimer cette question car elle a déjà été utilisée dans le tournoi.";
        } else {
            $stmt = $conn->prepare("DELETE FROM questions WHERE id = ?");
            $stmt->execute([$question_id]);
            
            // Rediriger pour éviter la resoumission du formulaire
            header('Location: questions.php?deleted=1' . ($tournament_id ? '&tournament_id=' . $tournament_id : '') . ($search ? '&search=' . urlencode($search) : '') . '&page=' . $page);
            exit;
        }
    } catch(Exception $e) {
        $error_message = "Erreur lors de la suppression : " . $e->getMessage();
    }
}

// Traitement de l'ajout/modification
if(isset($_POST['save_question'])) {
    $question_id = isset($_POST['question_id']) ? (int)$_POST['question_id'] : 0;
    $question_tournament_id = (int)$_POST['question_tournament_id'];
    $question_text = trim($_POST['question_text'] ?? '');
    $correct_answer = trim($_POST['correct_answer'] ?? '');
    
    $errors = [];
    
    // Validation
    if($question_tournament_id <= 0) {
        $errors[] = "Veuillez sélectionner un tournoi";
    }
    
    if(empty($question_text)) {
        $errors[] = "Le texte de la question est requis";
    }
    
    if(empty($correct_answer)) {
        $errors[] = "La réponse correcte est requise";
    }
    
    if(empty($errors)) {
        try {
            if($question_id > 0) {
                // Mettre à jour une question existante
                $stmt = $conn->prepare("UPDATE questions SET tournament_id = ?, question_text = ?, correct_answer = ? WHERE id = ?");
                $stmt->execute([$question_tournament_id, $question_text, $correct_answer, $question_id]);
                $success_message = "Question mise à jour avec succès";
            } else {
                // Ajouter une nouvelle question
                $stmt = $conn->prepare("INSERT INTO questions (tournament_id, question_text, correct_answer) VALUES (?, ?, ?)");
                $stmt->execute([$question_tournament_id, $question_text, $correct_answer]);
                $success_message = "Question ajoutée avec succès";
            }
            
            // Rediriger pour éviter la resoumission du formulaire
            header('Location: questions.php?success=1' . ($tournament_id ? '&tournament_id=' . $tournament_id : '') . ($search ? '&search=' . urlencode($search) : '') . '&page=' . $page);
            exit;
        } catch(Exception $e) {
            $error_message = "Erreur lors de l'enregistrement : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des questions - Administration Quiz Battle Royale</title>
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
                    <h1 class="h2">Gestion des questions</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
                            <i class="bi bi-plus-circle"></i> Nouvelle question
                        </button>
                    </div>
                </div>
                
                <?php if(isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        La question a été supprimée avec succès.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($_GET['success']) && $_GET['success'] == 1): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        La question a été enregistrée avec succès.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $error_message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form action="" method="GET" class="row g-3">
                            <div class="col-md-5">
                                <label for="tournament_id" class="form-label">Tournoi</label>
                                <select class="form-select" id="tournament_id" name="tournament_id">
                                    <option value="0">Tous les tournois</option>
                                    <?php foreach($tournaments as $t): ?>
                                        <option value="<?= $t['id'] ?>" <?= $tournament_id == $t['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($t['title']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label for="search" class="form-label">Rechercher</label>
                                <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Question ou réponse...">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Filtrer</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Questions list -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Questions (<?= $total_questions ?>)</h3>
                    </div>
                    <div class="card-body p-0">
                        <?php if(empty($questions)): ?>
                            <div class="alert alert-info m-3">
                                Aucune question trouvée avec les critères de recherche actuels.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Tournoi</th>
                                            <th>Question</th>
                                            <th>Réponse correcte</th>
                                            <th>Date de création</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($questions as $question): ?>
                                            <tr>
                                                <td><?= $question['id'] ?></td>
                                                <td><?= htmlspecialchars($question['tournament_title']) ?></td>
                                                <td><?= htmlspecialchars($question['question_text']) ?></td>
                                                <td><?= htmlspecialchars($question['correct_answer']) ?></td>
                                                <td><?= date('d/m/Y H:i', strtotime($question['created_at'])) ?></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-sm btn-warning edit-question" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editQuestionModal"
                                                                data-id="<?= $question['id'] ?>"
                                                                data-tournament="<?= $question['tournament_id'] ?>"
                                                                data-question="<?= htmlspecialchars($question['question_text']) ?>"
                                                                data-answer="<?= htmlspecialchars($question['correct_answer']) ?>">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $question['id'] ?>">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                    
                                                    <!-- Delete Modal -->
                                                    <div class="modal fade" id="deleteModal<?= $question['id'] ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Confirmer la suppression</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <p>Êtes-vous sûr de vouloir supprimer cette question ?</p>
                                                                    <div class="alert alert-secondary">
                                                                        <?= htmlspecialchars($question['question_text']) ?>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                                    <form method="POST" action="">
                                                                        <input type="hidden" name="question_id" value="<?= $question['id'] ?>">
                                                                        <button type="submit" name="delete_question" class="btn btn-danger">Supprimer</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if($total_pages > 1): ?>
                        <div class="card-footer">
                            <nav>
                                <ul class="pagination justify-content-center mb-0">
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $page - 1 ?>&tournament_id=<?= $tournament_id ?>&search=<?= urlencode($search) ?>">Précédent</a>
                                    </li>
                                    
                                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>&tournament_id=<?= $tournament_id ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>&tournament_id=<?= $tournament_id ?>&search=<?= urlencode($search) ?>">Suivant</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Add Question Modal -->
    <div class="modal fade" id="addQuestionModal" tabindex="-1" aria-labelledby="addQuestionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addQuestionModalLabel">Ajouter une question</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="question_tournament_id" class="form-label">Tournoi</label>
                            <select class="form-select" id="question_tournament_id" name="question_tournament_id" required>
                                <option value="">Sélectionner un tournoi</option>
                                <?php foreach($tournaments as $t): ?>
                                    <option value="<?= $t['id'] ?>" <?= $tournament_id == $t['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($t['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="question_text" class="form-label">Texte de la question</label>
                            <textarea class="form-control" id="question_text" name="question_text" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="correct_answer" class="form-label">Réponse correcte</label>
                            <input type="text" class="form-control" id="correct_answer" name="correct_answer" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="save_question" class="btn btn-primary">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Question Modal -->
    <div class="modal fade" id="editQuestionModal" tabindex="-1" aria-labelledby="editQuestionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editQuestionModalLabel">Modifier la question</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="edit_question_id" name="question_id">
                        <div class="mb-3">
                            <label for="edit_question_tournament_id" class="form-label">Tournoi</label>
                            <select class="form-select" id="edit_question_tournament_id" name="question_tournament_id" required>
                                <option value="">Sélectionner un tournoi</option>
                                <?php foreach($tournaments as $t): ?>
                                    <option value="<?= $t['id'] ?>">
                                        <?= htmlspecialchars($t['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_question_text" class="form-label">Texte de la question</label>
                            <textarea class="form-control" id="edit_question_text" name="question_text" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_correct_answer" class="form-label">Réponse correcte</label>
                            <input type="text" class="form-control" id="edit_correct_answer" name="correct_answer" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="save_question" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Remplir le modal d'édition avec les données de la question
        $('.edit-question').click(function() {
            const id = $(this).data('id');
            const tournament = $(this).data('tournament');
            const question = $(this).data('question');
            const answer = $(this).data('answer');
            
            $('#edit_question_id').val(id);
            $('#edit_question_tournament_id').val(tournament);
            $('#edit_question_text').val(question);
            $('#edit_correct_answer').val(answer);
        });
    });
    </script>
</body>
</html>
