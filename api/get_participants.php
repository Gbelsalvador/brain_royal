<?php
session_start();
require_once '../config/database.php';
header('Content-Type: application/json');

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

// Check if tournament_id is provided
if(!isset($_GET['tournament_id'])) {
    echo json_encode(['error' => 'Missing tournament_id parameter']);
    exit;
}

$tournament_id = $_GET['tournament_id'];

// Get all participants with usernames
$stmt = $conn->prepare("
    SELECT tp.*, u.username, 
           (SELECT COUNT(*) = 1 FROM tournament_participants 
            WHERE tournament_id = ? AND is_active = 1) as is_winner
    FROM tournament_participants tp
    JOIN users u ON tp.user_id = u.id
    WHERE tp.tournament_id = ?
    ORDER BY tp.score DESC, tp.id ASC
");
$stmt->execute([$tournament_id, $tournament_id]);
$participants = $stmt->fetchAll();

// Count active participants
$active_count = 0;
foreach($participants as $participant) {
    if($participant['is_active'] == 1) {
        $active_count++;
    }
}

echo json_encode([
    'participants' => $participants,
    'active_count' => $active_count,
    'total_count' => count($participants)
]);
?>
