<?php
session_start();
require_once '../../config/database.php';
header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté et est administrateur
if(!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Vérifier si l'ID du tournoi est fourni
if(!isset($_GET['tournament_id']) || !is_numeric($_GET['tournament_id'])) {
    echo json_encode(['error' => 'ID de tournoi manquant']);
    exit;
}

$tournament_id = $_GET['tournament_id'];

// Récupérer les informations du tournoi
$stmt = $conn->prepare("SELECT * FROM tournaments WHERE id = ?");
$stmt->execute([$tournament_id]);
$tournament = $stmt->fetch();

if(!$tournament) {
    echo json_encode(['error' => 'Tournoi non trouvé']);
    exit;
}

// Récupérer le nombre de participants actifs
$stmt = $conn->prepare("
    SELECT COUNT(*) as count
    FROM tournament_participants
    WHERE tournament_id = ? AND is_active = 1
");
$stmt->execute([$tournament_id]);
$active_participants = $stmt->fetch()['count'];

// Récupérer le round actuel
$stmt = $conn->prepare("
    SELECT MAX(round) as max_round
    FROM tournament_participants
    WHERE tournament_id = ?
");
$stmt->execute([$tournament_id]);
$current_round = $stmt->fetch()['max_round'];

// Récupérer le nombre de questions restantes
$stmt = $conn->prepare("
    SELECT COUNT(*) as count
    FROM questions
    WHERE tournament_id = ? AND id NOT IN (
        SELECT DISTINCT question_id
        FROM user_answers ua
        JOIN tournament_participants tp ON ua.participant_id = tp.id
        WHERE tp.tournament_id = ?
    )
");
$stmt->execute([$tournament_id, $tournament_id]);
$remaining_questions = $stmt->fetch()['count'];

// Récupérer le score le plus élevé
$stmt = $conn->prepare("
    SELECT MAX(score) as max_score
    FROM tournament_participants
    WHERE tournament_id = ?
");
$stmt->execute([$tournament_id]);
$highest_score = $stmt->fetch()['max_score'];

// Calculer le temps écoulé depuis le début du tournoi
$start_time = new DateTime($tournament['start_time']);
$now = new DateTime();
$interval = $start_time->diff($now);

$elapsed_time = '';
if($interval->days > 0) {
    $elapsed_time .= $interval->days . ' jours, ';
}
$elapsed_time .= $interval->format('%H:%I:%S');

// Déterminer le statut du tournoi
if($active_participants <= 1) {
    $status = 'finished';
    $status_text = 'Terminé';
    $status_class = 'bg-primary';
} else {
    $status = 'active';
    $status_text = 'En cours';
    $status_class = 'bg-success';
}

// Retourner les données
echo json_encode([
    'current_round' => $current_round,
    'active_participants' => $active_participants,
    'remaining_questions' => $remaining_questions,
    'highest_score' => $highest_score,
    'elapsed_time' => $elapsed_time,
    'status' => $status,
    'status_text' => $status_text,
    'status_class' => $status_class
]);
?>
