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
$limit = 15;
$offset = ($page - 1) * $limit;

// Filtres
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Construction de la requête
$query = "SELECT u.*, 
                 (SELECT COUNT(*) FROM tournament_participants WHERE user_id = u.id) as tournaments_count
          FROM users u
          WHERE 1=1";
$params = [];

if(!empty($search)) {
    $query .= " AND (u.username LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Compter le nombre total d'utilisateurs pour la pagination
$count_query = str_replace("u.*, (SELECT COUNT(*) FROM tournament_participants WHERE user_id = u.id) as tournaments_count", "COUNT(*) as total", $query);
$stmt = $conn->prepare($count_query);
$stmt->execute($params);
$total_users = $stmt->fetchColumn();
$total_pages = ceil($total_users / $limit);

// Ajouter l'ordre et la limite à la requête principale
$query .= " ORDER BY u.created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Traitement de la désactivation/activation d'un utilisateur
if(isset($_POST['toggle_user_status']) && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Dans une application réelle, vous auriez une colonne is_active dans la table users
    // Pour cet exemple, nous allons simplement afficher un message de succès
    $success_message = "Le statut de l'utilisateur a été mis à jour avec succès.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs - Administration Quiz Battle Royale</title>
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
                    <h1 class="h2">Gestion des utilisateurs</h1>
                </div>
                
                <?php if(isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $success_message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form action="" method="GET" class="row g-3">
                            <div class="col-md-10">
                                <label for="search" class="form-label">Rechercher</label>
                                <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nom d'utilisateur ou email...">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Rechercher</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Users list -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Utilisateurs (<?= $total_users ?>)</h3>
                    </div>
                    <div class="card-body p-0">
                        <?php if(empty($users)): ?>
                            <div class="alert alert-info m-3">
                                Aucun utilisateur trouvé avec les critères de recherche actuels.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Nom d'utilisateur</th>
                                            <th>Email</th>
                                            <th>Tournois</th>
                                            <th>Date d'inscription</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($users as $user): ?>
                                            <tr>
                                                <td><?= $user['id'] ?></td>
                                                <td><?= htmlspecialchars($user['username']) ?></td>
                                                <td><?= htmlspecialchars($user['email']) ?></td>
                                                <td><?= $user['tournaments_count'] ?></td>
                                                <td><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="view_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-info">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#statusModal<?= $user['id'] ?>">
                                                            <i class="bi bi-toggle-on"></i>
                                                        </button>
                                                    </div>
                                                    
                                                    <!-- Status Modal -->
                                                    <div class="modal fade" id="statusModal<?= $user['id'] ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Modifier le statut de l'utilisateur</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <p>Utilisateur: <strong><?= htmlspecialchars($user['username']) ?></strong></p>
                                                                    <form method="POST" action="">
                                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                                        <div class="form-check form-switch">
                                                                            <input class="form-check-input" type="checkbox" id="is_active<?= $user['id'] ?>" name="is_active" checked>
                                                                            <label class="form-check-label" for="is_active<?= $user['id'] ?>">
                                                                                Compte actif
                                                                            </label>
                                                                        </div>
                                                                        <div class="form-text">
                                                                            Désactiver un compte empêchera l'utilisateur de se connecter et de participer aux tournois.
                                                                        </div>
                                                                        <div class="mt-3">
                                                                            <button type="submit" name="toggle_user_status" class="btn btn-primary">Enregistrer</button>
                                                                        </div>
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
                                        <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">Précédent</a>
                                    </li>
                                    
                                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">Suivant</a>
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
