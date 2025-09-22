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

// Récupérer les activités récentes (réponses, éliminations, etc.)
$activities = [];

// Récupérer les réponses récentes
$stmt = $conn->prepare("
    SELECT ua.*, u.username, q.question_text, q.correct_answer, ua.created_at
    FROM user_answers ua
    JOIN tournament_participants tp ON ua.participant_id = tp.id
    JOIN users u ON tp.user_id = u.id
    JOIN questions q ON ua.question_id = q.id
    WHERE tp.tournament_id = ?
    ORDER BY ua.created_at DESC
    LIMIT 10
");
$stmt->execute([$tournament_id]);
$recent_answers = $stmt->fetchAll();

foreach($recent_answers as $answer) {
    $time_ago = time_elapsed_string($answer['created_at']);
    
    $activities[] = [
        'type' => 'answer',
        'is_correct' => $answer['is_correct'] == 1,
        'message' => $answer['username'] . ' a répondu ' . 
                    ($answer['is_correct'] == 1 ? 'correctement' : 'incorrectement') . 
                    ' à la question: "' . substr($answer['question_text'], 0, 50) . '..."',
        'time_ago' => $time_ago
    ];
}

// Récupérer les éliminations récentes
$stmt = $conn->prepare("
    SELECT tp.*, u.username, tp.updated_at
    FROM tournament_participants tp
    JOIN users u ON tp.user_id = u.id
    WHERE tp.tournament_id = ? AND tp.is_active = 0
    ORDER BY tp.updated_at DESC
    LIMIT 5
");
$stmt->execute([$tournament_id]);
$recent_eliminations = $stmt->fetchAll();

foreach($recent_eliminations as $elimination) {
    $time_ago = time_elapsed_string($elimination['updated_at']);
    
    $activities[] = [
        'type' => 'elimination',
        'message' => $elimination['username'] . ' a été éliminé au round ' . $elimination['round'],
        'time_ago' => $time_ago
    ];
}

// Récupérer les avancées de round récentes
$stmt = $conn->prepare("
    SELECT tp.*, u.username, tp.updated_at
    FROM tournament_participants tp
    JOIN users u ON tp.user_id = u.id
    WHERE tp.tournament_id = ? AND tp.is_active = 1 AND tp.round > 1
    ORDER BY tp.updated_at DESC
    LIMIT 5
");
$stmt->execute([$tournament_id]);
$recent_advances = $stmt->fetchAll();

foreach($recent_advances as $advance) {
    $time_ago = time_elapsed_string($advance['updated_at']);
    
    $activities[] = [
        'type' => 'advance',
        'message' => $advance['username'] . ' a avancé au round ' . $advance['round'],
        'time_ago' => $time_ago
    ];
}

// Récupérer les nouveaux participants
$stmt = $conn->prepare("
    SELECT tp.*, u.username, tp.created_at
    FROM tournament_participants tp
    JOIN users u ON tp.user_id = u.id
    WHERE tp.tournament_id = ?
    ORDER BY tp.created_at DESC
    LIMIT 5
");
$stmt->execute([$tournament_id]);
$recent_joins = $stmt->fetchAll();

foreach($recent_joins as $join) {
    $time_ago = time_elapsed_string($join['created_at']);
    
    $activities[] = [
        'type' => 'join',
        'message' => $join['username'] . ' a rejoint le tournoi',
        'time_ago' => $time_ago
    ];
}

// Trier les activités par date (les plus récentes en premier)
usort($activities, function($a, $b) {
    return strtotime($b['time_ago']) - strtotime($a['time_ago']);
});

// Limiter à 15 activités
$activities = array_slice($activities, 0, 15);

// Retourner les données
echo json_encode([
    'activities' => $activities
]);

// Fonction pour calculer le temps écoulé
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = [
        'y' => 'an',
        'm' => 'mois',
        'w' => 'semaine',
        'd' => 'jour',
        'h' => 'heure',
        'i' => 'minute',
        's' => 'seconde',
    ];
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 && $k != 'm' ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? 'il y a ' . implode(', ', $string) : 'à l\'instant';
}
?>
