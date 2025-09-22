<?php
session_start();
require_once '../config/database.php';
header('Content-Type: application/json');

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

// Check if required parameters are provided
if(!isset($_POST['tournament_id']) || !isset($_POST['participant_id']) || !isset($_POST['question_id'])) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$tournament_id = $_POST['tournament_id'];
$participant_id = $_POST['participant_id'];
$question_id = $_POST['question_id'];
$user_answer = isset($_POST['answer']) ? $_POST['answer'] : null;

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

// Get question details
$stmt = $conn->prepare("SELECT * FROM questions WHERE id = ? AND tournament_id = ?");
$stmt->execute([$question_id, $tournament_id]);
$question = $stmt->fetch();

if(!$question) {
    echo json_encode(['error' => 'Question not found']);
    exit;
}

// Check if user has already answered this question
$stmt = $conn->prepare("SELECT * FROM user_answers WHERE participant_id = ? AND question_id = ?");
$stmt->execute([$participant_id, $question_id]);
if($stmt->rowCount() > 0) {
    echo json_encode(['error' => 'You have already answered this question']);
    exit;
}

// Check if answer is correct
$is_correct = ($user_answer === $question['correct_answer']);

// Calculate points (you can adjust the scoring logic)
$points = $is_correct ? 10 : 0;

// Record the answer
$stmt = $conn->prepare("
    INSERT INTO user_answers (participant_id, question_id, user_answer, is_correct)
    VALUES (?, ?, ?, ?)
");
$stmt->execute([$participant_id, $question_id, $user_answer, $is_correct ? 1 : 0]);

// Update participant score
$stmt = $conn->prepare("UPDATE tournament_participants SET score = score + ? WHERE id = ?");
$stmt->execute([$points, $participant_id]);

// Check if all participants have answered all questions for this round
$stmt = $conn->prepare("
    SELECT COUNT(*) as total_participants
    FROM tournament_participants
    WHERE tournament_id = ? AND is_active = 1
");
$stmt->execute([$tournament_id]);
$total_active_participants = $stmt->fetch()['total_participants'];

$stmt = $conn->prepare("
    SELECT COUNT(*) as total_questions
    FROM questions
    WHERE tournament_id = ?
");
$stmt->execute([$tournament_id]);
$total_questions = $stmt->fetch()['total_questions'];

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT participant_id) as participants_answered
    FROM user_answers ua
    JOIN tournament_participants tp ON ua.participant_id = tp.id
    WHERE tp.tournament_id = ? AND tp.is_active = 1
    GROUP BY ua.question_id
    HAVING COUNT(*) = ?
");
$stmt->execute([$tournament_id, $total_active_participants]);
$all_answered = $stmt->rowCount() === $total_questions;

// If all participants have answered all questions, process eliminations
if($all_answered) {
    // Get all active participants sorted by score
    $stmt = $conn->prepare("
        SELECT *
        FROM tournament_participants
        WHERE tournament_id = ? AND is_active = 1
        ORDER BY score DESC, id ASC
    ");
    $stmt->execute([$tournament_id]);
    $participants = $stmt->fetchAll();
    
    // Calculate how many to keep (50% rounded up)
    $keep_count = ceil(count($participants) / 2);
    
    // Eliminate bottom 50%
    for($i = $keep_count; $i < count($participants); $i++) {
        $stmt = $conn->prepare("UPDATE tournament_participants SET is_active = 0 WHERE id = ?");
        $stmt->execute([$participants[$i]['id']]);
    }
    
    // Advance to next round for remaining participants
    $stmt = $conn->prepare("UPDATE tournament_participants SET round = round + 1 WHERE tournament_id = ? AND is_active = 1");
    $stmt->execute([$tournament_id]);
}

echo json_encode([
    'success' => true,
    'is_correct' => $is_correct,
    'points' => $points,
    'question' => $question
]);
?>
