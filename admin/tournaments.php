<?php
session_start();
require_once '../config/database.php';

// Vérifier si l'utilisateur est connecté et est administrateur
if(!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: ../login.php');
    exit;
}

// Paramètres de pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filtres
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Construction de la requête
$query = "SELECT t.*, 
                 (SELECT COUNT(*) FROM tournament_participants WHERE tournament_id = t.id) as participant_count
          FROM tournaments t
          WHERE 1=1";
$params = [];

if(!empty($search)) {
    $query .= " AND t.title LIKE ?";
    $params[] = "%$search%";
}

if($status_filter === 'upcoming') {
    $query .= " AND t.start_time > NOW()";
} elseif($status_filter === 'active') {
    $query .= " AND t.start_time <= NOW() AND EXISTS (
                    SELECT 1 FROM tournament_participants 
                    WHERE tournament_id = t.id AND is_active = 1
                )";
} elseif($status_filter === 'completed') {
    $query .= " AND t.start_time <= NOW() AND NOT EXISTS (
                    SELECT 1 FROM tournament_participants 
                    WHERE tournament_id = t.id AND is_active = 1
                )";
}

// Compter le nombre total de tournois pour la pagination
$count_query = str_replace("t.*, (SELECT COUNT(*) FROM tournament_participants WHERE tournament_id = t.id) as participant_count", "COUNT(*) as total", $query);
$stmt = $conn->prepare($count_query);
$stmt->execute($params);
$total_tournaments = $stmt->fetchColumn();
$total_pages = ceil($total_tournaments / $limit);

// Ajouter l'ordre et la limite à la requête principale
$query .= " ORDER BY t.start_time DESC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$tournaments = $stmt->fetchAll();

// Traitement de la suppression
if(isset($_POST['delete_tournament']) && isset($_POST['tournament_id'])) {
    $tournament_id = $_POST['tournament_id'];
    
    try {
        $conn->beginTransaction();
        
        // Supprimer les réponses des utilisateurs
        $stmt = $conn->prepare("
            DELETE ua FROM user_answers ua
            JOIN tournament_participants tp ON ua.participant_id = tp.id
            WHERE tp.tournament_id = ?
        ");
        $stmt->execute([$tournament_id]);
        
        // Supprimer les participants
        $stmt = $conn->prepare("DELETE FROM tournament_participants WHERE tournament_id = ?");
        $stmt->execute([$tournament_id]);
        
        // Supprimer les questions
        $stmt = $conn->prepare("DELETE FROM questions WHERE tournament_id = ?");
        $stmt->execute([$tournament_id]);
        
        // Supprimer le tournoi
        $stmt = $conn->prepare("DELETE FROM tournaments WHERE id = ?");
        $stmt->execute([$tournament_id]);
        
        $conn->commit();
        
        // Rediriger pour éviter la resoumission du formulaire
        header('Location: tournaments.php?deleted=1');
        exit;
    } catch(Exception $e) {
        $conn->rollBack();
        $error_message = "Erreur lors de la suppression : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des tournois - Administration Quiz Battle Royale</title>
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
                    <h1 class="h2">Gestion des tournois</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="create_tournament.php" class="btn btn-sm btn-primary">
                            <i class="bi bi-plus-circle"></i> Nouveau tournoi
                        </a>
                    </div>
                </div>
                
                <?php if(isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Le tournoi a été supprimé avec succès.
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
                            <div class="col-md-4">
                                <label for="search" class="form-label">Rechercher</label>
                                <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Titre du tournoi...">
                            </div>
                            <div class="col-md-4">
                                <label for="status" class="form-label">Statut</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="" <?= $status_filter === '' ? 'selected' : '' ?>>Tous</option>
                                    <option value="upcoming" <?= $status_filter === 'upcoming' ? 'selected' : '' ?>>À venir</option>
                                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>En cours</option>
                                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Terminés</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">Filtrer</button>
                                <a href="tournaments.php" class="btn btn-secondary">Réinitialiser</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Tournaments list -->
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Titre</th>
                                        <th>Questions</th>
                                        <th>Date de début</th>
                                        <th>Statut</th>
                                        <th>Participants</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($tournaments)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">Aucun tournoi trouvé</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($tournaments as $tournament): ?>
                                            <?php
                                                // Déterminer le statut du tournoi
                                                $now = new DateTime();
                                                $start_time = new DateTime($tournament['start_time']);
                                                
                                                if($start_time > $now) {
                                                    $status = 'À venir';
                                                    $status_class = 'bg-info';
                                                } else {
                                                    // Vérifier s'il y a des participants actifs
                                                    $stmt = $conn->prepare("
                                                        SELECT COUNT(*) FROM tournament_participants 
                                                        WHERE tournament_id = ? AND is_active = 1
                                                    ");
                                                    $stmt->execute([$tournament['id']]);
                                                    $active_participants = $stmt->fetchColumn();
                                                    
                                                    if($active_participants > 0) {
                                                        $status = 'En cours';
                                                        $status_class = 'bg-success';
                                                    } else {
                                                        $status = 'Terminé';
                                                        $status_class = 'bg-secondary';
                                                    }
                                                }
                                            ?>
                                            <tr>
                                                <td><?= $tournament['id'] ?></td>
                                                <td><?= htmlspecialchars($tournament['title']) ?></td>
                                                <td><?= $tournament['total_questions'] ?></td>
                                                <td><?= date('d/m/Y H:i', strtotime($tournament['start_time'])) ?></td>
                                                <td><span class="badge <?= $status_class ?>"><?= $status ?></span></td>
                                                <td><?= $tournament['participant_count'] ?></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="view_tournament.php?id=<?= $tournament['id'] ?>" class="btn btn-sm btn-info">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <?php if($start_time > $now): ?>
                                                            <a href="edit_tournament.php?id=<?= $tournament['id'] ?>" class="btn btn-sm btn-warning">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $tournament['id'] ?>">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <!-- Delete Modal -->
                                                    <div class="modal fade" id="deleteModal<?= $tournament['id'] ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?= $tournament['id'] ?>" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="deleteModalLabel<?= $tournament['id'] ?>">Confirmer la suppression</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    Êtes-vous sûr de vouloir supprimer le tournoi <strong><?= htmlspecialchars($tournament['title']) ?></strong> ?
                                                                    <p class="text-danger mt-2">Cette action est irréversible et supprimera toutes les questions et participations associées.</p>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                                    <form method="POST" action="">
                                                                        <input type="hidden" name="tournament_id" value="<?= $tournament['id'] ?>">
                                                                        <button type="submit" name="delete_tournament" class="btn btn-danger">Supprimer</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if($total_pages > 1): ?>
                        <div class="card-footer">
                            <nav>
                                <ul class="pagination justify-content-center mb-0">
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $page - 1 ?>&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>">Précédent</a>
                                    </li>
                                    
                                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>">Suivant</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
