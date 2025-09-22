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

// Récupérer les participants actifs du tournoi
$stmt = $conn->prepare("
    SELECT tp.*, u.username,
           (SELECT COUNT(*) FROM user_answers WHERE participant_id = tp.id) as answers_count
    FROM tournament_participants tp
    JOIN users u ON tp.user_id = u.id
    WHERE tp.tournament_id = ? AND tp.is_active = 1
    ORDER BY tp.score DESC, tp.id ASC
");
$stmt->execute([$tournament_id]);
$participants = $stmt->fetchAll();

// Récupérer le nombre total de questions
$stmt = $conn->prepare("
    SELECT COUNT(*) as count
    FROM questions
    WHERE tournament_id = ?
");
$stmt->execute([$tournament_id]);
$total_questions = $stmt->fetch()['count'];

// Retourner les données
echo json_encode([
    'participants' => $participants,
    'total_questions' => $total_questions
]);
?>
