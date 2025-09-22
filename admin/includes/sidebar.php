<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
    <div class="position-sticky pt-3">
        <div class="text-center mb-4">
            <a href="../index.php" class="text-decoration-none">
                <h5 class="text-white">Quiz Battle Royale</h5>
                <p class="text-white-50">Administration</p>
            </a>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link text-white <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" href="index.php">
                    <i class="bi bi-speedometer2 me-2"></i>
                    Tableau de bord
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?= basename($_SERVER['PHP_SELF']) == 'tournaments.php' ? 'active' : '' ?>" href="tournaments.php">
                    <i class="bi bi-trophy me-2"></i>
                    Tournois
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?= basename($_SERVER['PHP_SELF']) == 'questions.php' ? 'active' : '' ?>" href="questions.php">
                    <i class="bi bi-question-circle me-2"></i>
                    Questions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>" href="users.php">
                    <i class="bi bi-people me-2"></i>
                    Utilisateurs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>" href="reports.php">
                    <i class="bi bi-bar-chart me-2"></i>
                    Rapports
                </a>
            </li>
        </ul>
        
        <hr class="text-white-50">
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link text-white" href="../index.php">
                    <i class="bi bi-house me-2"></i>
                    Retour au site
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="../logout.php">
                    <i class="bi bi-box-arrow-right me-2"></i>
                    Déconnexion
                </a>
            </li>
        </ul>
    </div>
</nav>

<style>
.sidebar {
    min-height: 100vh;
}

.sidebar .nav-link {
    font-weight: 500;
    padding: 0.75rem 1rem;
    border-radius: 0.25rem;
    margin-bottom: 0.25rem;
}

.sidebar .nav-link.active {
    background-color: rgba(255, 255, 255, 0.1);
}

.sidebar .nav-link:hover {
    background-color: rgba(255, 255, 255, 0.1);
}
</style>
