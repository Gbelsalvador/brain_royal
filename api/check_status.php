<?php
session_start();
require_once '../config/database.php';
header('Content-Type: application/json');

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

// Check if tournament_id and participant_id are provided
if(!isset($_GET['tournament_id']) || !isset($_GET['participant_id'])) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$tournament_id = $_GET['tournament_id'];
$participant_id = $_GET['participant_id'];

// Get tournament details
$stmt = $conn->prepare("SELECT * FROM tournaments WHERE id = ?");
$stmt->execute([$tournament_id]);
$tournament = $stmt->fetch();

if(!$tournament) {
    echo json_encode(['error' => 'Tournament not found']);
    exit;
}

// Get participant details
$stmt = $conn->prepare("SELECT * FROM tournament_participants WHERE id = ? AND tournament_id = ?");
$stmt->execute([$participant_id, $tournament_id]);
$participant = $stmt->fetch();

if(!$participant) {
    echo json_encode(['error' => 'Participant not found']);
    exit;
}

// Get total number of participants
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM tournament_participants WHERE tournament_id = ?");
$stmt->execute([$tournament_id]);
$total_participants = $stmt->fetch()['total'];

// Get active participants
$stmt = $conn->prepare("SELECT COUNT(*) as active FROM tournament_participants WHERE tournament_id = ? AND is_active = 1");
$stmt->execute([$tournament_id]);
$active_participants = $stmt->fetch()['active'];

// Calculate total rounds needed
$total_rounds = ceil(log($total_participants, 2));

// Get current round questions
$stmt = $conn->prepare("
    SELECT q.* 
    FROM questions q
    LEFT JOIN user_answers ua ON q.id = ua.question_id AND ua.participant_id = ?
    WHERE q.tournament_id = ?
    AND ua.id IS NULL
    LIMIT 1
");
$stmt->execute([$participant_id, $tournament_id]);
$next_question = $stmt->fetch();

// Check if all questions for this round have been answered
$stmt = $conn->prepare("
    SELECT COUNT(*) as total_questions,
           (SELECT COUNT(*) FROM user_answers WHERE participant_id = ?) as answered_questions
    FROM questions
    WHERE tournament_id = ?
");
$stmt->execute([$participant_id, $tournament_id]);
$question_counts = $stmt->fetch();

// Check if we're at the end of a round
$all_questions_answered = $question_counts['total_questions'] > 0 && 
                          $question_counts['answered_questions'] >= $question_counts['total_questions'];

// Check if we're at the end of the tournament
$tournament_ended = $participant['round'] >= $total_rounds && $all_questions_answered;

// Check if there's a winner
$stmt = $conn->prepare("
    SELECT COUNT(*) as winner_count
    FROM tournament_participants
    WHERE tournament_id = ? AND is_active = 1
");
$stmt->execute([$tournament_id]);
$winner_count = $stmt->fetch()['winner_count'];

$is_winner = $winner_count === 1 && $participant['is_active'] === 1;

// Determine tournament status
if($tournament_ended || $winner_count === 1) {
    // Get final leaderboard
    $stmt = $conn->prepare("
        SELECT tp.*, u.username
        FROM tournament_participants tp
        JOIN users u ON tp.user_id = u.id
        WHERE tp.tournament_id = ?
        ORDER BY tp.score DESC, tp.id ASC
    ");
    $stmt->execute([$tournament_id]);
    $leaderboard = $stmt->fetchAll();
    
    echo json_encode([
        'status' => 'tournament_end',
        'is_winner' => $is_winner,
        'leaderboard' => $leaderboard,
        'current_round' => $participant['round'],
        'total_rounds' => $total_rounds
    ]);
} else if($all_questions_answered) {
    // Simulate countdown between rounds (in a real app, you'd store this in the database)
    $countdown = 10; // 10 seconds countdown between rounds
    
    echo json_encode([
        'status' => 'round_end',
        'is_active' => $participant['is_active'] === 1,
        'countdown' => $countdown,
        'current_round' => $participant['round'],
        'total_rounds' => $total_rounds
    ]);
} else if($next_question) {
    echo json_encode([
        'status' => 'question',
        'current_round' => $participant['round'],
        'total_rounds' => $total_rounds
    ]);
} else {
    // Waiting for next question or round
    echo json_encode([
        'status' => 'waiting',
        'message' => 'En attente de la prochaine question...',
        'countdown' => 0,
        'current_round' => $participant['round'],
        'total_rounds' => $total_rounds
    ]);
}
?>
