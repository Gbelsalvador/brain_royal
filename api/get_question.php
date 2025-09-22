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

// Get participant details
$stmt = $conn->prepare("SELECT * FROM tournament_participants WHERE id = ? AND tournament_id = ?");
$stmt->execute([$participant_id, $tournament_id]);
$participant = $stmt->fetch();

if(!$participant) {
    echo json_encode(['error' => 'Participant not found']);
    exit;
}

// Check if participant is active
if($participant['is_active'] != 1) {
    echo json_encode(['error' => 'You have been eliminated from this tournament']);
    exit;
}

// Get current question
$stmt = $conn->prepare("
    SELECT q.* 
    FROM questions q
    LEFT JOIN user_answers ua ON q.id = ua.question_id AND ua.participant_id = ?
    WHERE q.tournament_id = ?
    AND ua.id IS NULL
    LIMIT 1
");
$stmt->execute([$participant_id, $tournament_id]);
$question = $stmt->fetch();

if(!$question) {
    echo json_encode(['error' => 'No questions available']);
    exit;
}

// Check if user has already answered this question
$stmt = $conn->prepare("
    SELECT ua.*, q.correct_answer
    FROM user_answers ua
    JOIN questions q ON ua.question_id = q.id
    WHERE ua.participant_id = ? AND ua.question_id = ?
");
$stmt->execute([$participant_id, $question['id']]);
$user_answer = $stmt->fetch();

if($user_answer) {
    echo json_encode([
        'question' => $question,
        'already_answered' => true,
        'user_answer' => $user_answer['user_answer'],
        'is_correct' => $user_answer['is_correct']
    ]);
    exit;
}

// Set time limit for answering (e.g., 20 seconds)
$time_limit = 20;

echo json_encode([
    'question' => $question,
    'already_answered' => false,
    'time_remaining' => $time_limit
]);
?>
