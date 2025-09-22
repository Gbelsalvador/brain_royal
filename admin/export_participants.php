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

// Récupérer les participants du tournoi
$stmt = $conn->prepare("
    SELECT tp.*, u.username, u.email,
           (SELECT COUNT(*) FROM user_answers WHERE participant_id = tp.id) as answers_count,
           (SELECT COUNT(*) FROM user_answers WHERE participant_id = tp.id AND is_correct = 1) as correct_answers
    FROM tournament_participants tp
    JOIN users u ON tp.user_id = u.id
    WHERE tp.tournament_id = ?
    ORDER BY tp.score DESC, tp.id ASC
");
$stmt->execute([$tournament_id]);
$participants = $stmt->fetchAll();

// Définir le nom du fichier
$filename = 'participants_tournoi_' . $tournament_id . '_' . date('Y-m-d') . '.csv';

// Définir les en-têtes pour le téléchargement
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Créer un gestionnaire de fichier pour la sortie
$output = fopen('php://output', 'w');

// Ajouter le BOM UTF-8 pour Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Écrire l'en-tête du fichier CSV
fputcsv($output, [
    'ID',
    'Nom d\'utilisateur',
    'Email',
    'Score',
    'Round',
    'Réponses',
    'Réponses correctes',
    'Taux de réussite',
    'Statut',
    'Date d\'inscription'
]);

// Écrire les données des participants
foreach($participants as $participant) {
    $success_rate = $participant['answers_count'] > 0 ? round(($participant['correct_answers'] / $participant['answers_count']) * 100, 2) : 0;
    
    fputcsv($output, [
        $participant['id'],
        $participant['username'],
        $participant['email'],
        $participant['score'],
        $participant['round'],
        $participant['answers_count'],
        $participant['correct_answers'],
        $success_rate . '%',
        $participant['is_active'] ? 'Actif' : 'Éliminé',
        date('d/m/Y H:i', strtotime($participant['created_at']))
    ]);
}

// Fermer le gestionnaire de fichier
fclose($output);
exit;
?>
