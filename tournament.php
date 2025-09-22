<?php
session_start();
require_once 'config/database.php';

// Redirect if not logged in
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if tournament ID is provided
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: tournaments.php');
    exit;
}

$tournament_id = $_GET['id'];

// Get tournament details
$stmt = $conn->prepare("SELECT * FROM tournaments WHERE id = ?");
$stmt->execute([$tournament_id]);
$tournament = $stmt->fetch();

if(!$tournament) {
    header('Location: tournaments.php');
    exit;
}

// Check if tournament has started
$tournament_started = strtotime($tournament['start_time']) <= time();

// Check if user is already a participant
$stmt = $conn->prepare("SELECT * FROM tournament_participants WHERE tournament_id = ? AND user_id = ?");
$stmt->execute([$tournament_id, $_SESSION['user_id']]);
$participant = $stmt->fetch();

// If tournament has started and user is not a participant, register them
if($tournament_started && !$participant) {
    $stmt = $conn->prepare("INSERT INTO tournament_participants (tournament_id, user_id, is_active, score, round) VALUES (?, ?, 1, 0, 1)");
    $stmt->execute([$tournament_id, $_SESSION['user_id']]);
    
    // Get the newly created participant
    $stmt = $conn->prepare("SELECT * FROM tournament_participants WHERE tournament_id = ? AND user_id = ?");
    $stmt->execute([$tournament_id, $_SESSION['user_id']]);
    $participant = $stmt->fetch();
}

// Get total number of participants
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM tournament_participants WHERE tournament_id = ?");
$stmt->execute([$tournament_id]);
$total_participants = $stmt->fetch()['total'];

// Calculate total rounds needed
$total_rounds = ceil(log($total_participants, 2));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tournament['title']) ?> - Quiz Battle Royale</title>
    <link rel="stylesheet" href="css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <div class="row mt-4">
            <div class="col-md-12">
                <h1><?= htmlspecialchars($tournament['title']) ?></h1>
                <p>Total de questions: <?= $tournament['total_questions'] ?></p>
                
                <?php if(!$tournament_started): ?>
                <div class="alert alert-info">
                    Ce tournoi commencera le <?= date('d/m/Y à H:i', strtotime($tournament['start_time'])) ?>
                </div>
                <?php else: ?>
                <div class="row">
                    <div class="col-md-8">
                        <div id="tournament-content">
                            <div class="text-center mb-4">
                                <h3>Chargement du tournoi...</h3>
                                <div class="spinner-border" role="status">
                                    <span class="visually-hidden">Chargement...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h3>Participants</h3>
                            </div>
                            <div class="card-body p-0">
                                <div id="participants-list" class="participants-list">
                                    <div class="text-center p-3">
                                        <div class="spinner-border spinner-border-sm" role="status">
                                            <span class="visually-hidden">Chargement...</span>
                                        </div>
                                        Chargement des participants...
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <div class="d-flex justify-content-between">
                                    <span>Round: <span id="current-round"><?= $participant ? $participant['round'] : '1' ?></span>/<span id="total-rounds"><?= $total_rounds ?></span></span>
                                    <span>Participants: <span id="active-participants">0</span>/<span id="total-participants"><?= $total_participants ?></span></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if($tournament_started): ?>
    <script>
    $(document).ready(function() {
        const tournamentId = <?= $tournament_id ?>;
        const participantId = <?= $participant ? $participant['id'] : 'null' ?>;
        let currentQuestionId = null;
        let timerInterval = null;
        let selectedAnswer = null;
        let isAnswerSubmitted = false;
        
        // Initial load
        checkTournamentStatus();
        loadParticipants();
        
        // Set up polling
        setInterval(checkTournamentStatus, 5000); // Check status every 5 seconds
        setInterval(loadParticipants, 10000); // Refresh participants every 10 seconds
        
        // Check tournament status
        function checkTournamentStatus() {
            $.ajax({
                url: 'api/check_status.php',
                type: 'GET',
                data: {
                    tournament_id: tournamentId,
                    participant_id: participantId
                },
                dataType: 'json',
                success: function(response) {
                    if(response.status === 'waiting') {
                        showWaitingScreen(response);
                    } else if(response.status === 'question') {
                        loadCurrentQuestion();
                    } else if(response.status === 'round_end') {
                        showRoundEndScreen(response);
                    } else if(response.status === 'tournament_end') {
                        showTournamentEndScreen(response);
                    }
                    
                    // Update round information
                    $('#current-round').text(response.current_round);
                    $('#total-rounds').text(response.total_rounds);
                }
            });
        }
        
        // Load current question
        function loadCurrentQuestion() {
            $.ajax({
                url: 'api/get_question.php',
                type: 'GET',
                data: {
                    tournament_id: tournamentId,
                    participant_id: participantId
                },
                dataType: 'json',
                success: function(response) {
                    if(response.error) {
                        $('#tournament-content').html(`<div class="alert alert-danger">${response.error}</div>`);
                        return;
                    }
                    
                    currentQuestionId = response.question.id;
                    
                    // Check if already answered
                    if(response.already_answered) {
                        showAnsweredQuestion(response.question, response.user_answer, response.is_correct);
                        return;
                    }
                    
                    // Display question
                    let html = `
                        <div class="question-container">
                            <div class="timer-container">
                                <div class="timer" id="timer">${response.time_remaining}</div>
                                <div class="progress">
                                    <div class="progress-bar" role="progressbar" style="width: 100%"></div>
                                </div>
                            </div>
                            <h3>${response.question.question_text}</h3>
                            <div class="mt-4" id="answer-options">`;
                    
                    // Generate random options including the correct answer
                    const options = generateOptions(response.question.correct_answer);
                    
                    options.forEach((option, index) => {
                        html += `
                            <button class="answer-option" data-answer="${option}">
                                ${String.fromCharCode(65 + index)}. ${option}
                            </button>`;
                    });
                    
                    html += `
                            </div>
                        </div>`;
                    
                    $('#tournament-content').html(html);
                    
                    // Start timer
                    startTimer(response.time_remaining);
                    
                    // Handle answer selection
                    $('.answer-option').click(function() {
                        if(isAnswerSubmitted) return;
                        
                        selectedAnswer = $(this).data('answer');
                        $('.answer-option').removeClass('selected');
                        $(this).addClass('selected');
                        
                        // Submit answer after a short delay
                        setTimeout(function() {
                            submitAnswer();
                        }, 500);
                    });
                }
            });
        }
        
        // Generate random options including the correct answer
        function generateOptions(correctAnswer) {
            const options = [correctAnswer];
            
            // Generate some fake options
            const fakeOptions = [
                "Paris", "London", "Berlin", "Madrid", "Rome", "Tokyo", "Beijing", "Moscow",
                "Washington", "Ottawa", "Canberra", "Wellington", "Cairo", "Nairobi", "Pretoria",
                "42", "100", "365", "1000", "7", "12", "24", "60", "1024",
                "Red", "Blue", "Green", "Yellow", "Purple", "Orange", "Black", "White",
                "Mercury", "Venus", "Earth", "Mars", "Jupiter", "Saturn", "Uranus", "Neptune"
            ];
            
            // Filter out the correct answer from fake options
            const filteredFakeOptions = fakeOptions.filter(option => option !== correctAnswer);
            
            // Shuffle and take 3 fake options
            for(let i = 0; i < 3; i++) {
                const randomIndex = Math.floor(Math.random() * filteredFakeOptions.length);
                options.push(filteredFakeOptions[randomIndex]);
                filteredFakeOptions.splice(randomIndex, 1);
            }
            
            // Shuffle all options
            return shuffleArray(options);
        }
        
        // Shuffle array
        function shuffleArray(array) {
            for(let i = array.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [array[i], array[j]] = [array[j], array[i]];
            }
            return array;
        }
        
        // Start timer
        function startTimer(seconds) {
            let timeRemaining = seconds;
            const $timer = $('#timer');
            const $progressBar = $('.progress-bar');
            const totalTime = seconds;
            
            clearInterval(timerInterval);
            
            timerInterval = setInterval(function() {
                timeRemaining--;
                $timer.text(timeRemaining);
                
                // Update progress bar
                const percentage = (timeRemaining / totalTime) * 100;
                $progressBar.css('width', percentage + '%');
                
                if(timeRemaining <= 5) {
                    $timer.addClass('text-danger');
                }
                
                if(timeRemaining <= 0) {
                    clearInterval(timerInterval);
                    
                    // If no answer selected, submit null
                    if(!isAnswerSubmitted) {
                        submitAnswer();
                    }
                }
            }, 1000);
        }
        
        // Submit answer
        function submitAnswer() {
            if(isAnswerSubmitted) return;
            isAnswerSubmitted = true;
            clearInterval(timerInterval);
            
            $.ajax({
                url: 'api/submit_answer.php',
                type: 'POST',
                data: {
                    tournament_id: tournamentId,
                    participant_id: participantId,
                    question_id: currentQuestionId,
                    answer: selectedAnswer
                },
                dataType: 'json',
                success: function(response) {
                    showAnsweredQuestion(response.question, selectedAnswer, response.is_correct);
                }
            });
        }
        
        // Show answered question with feedback
        function showAnsweredQuestion(question, userAnswer, isCorrect) {
            let html = `
                <div class="question-container">
                    <h3>${question.question_text}</h3>
                    <div class="mt-4">`;
            
            // Generate options
            const options = generateOptions(question.correct_answer);
            
            options.forEach((option, index) => {
                let optionClass = '';
                
                if(option === question.correct_answer) {
                    optionClass = 'correct';
                } else if(option === userAnswer && userAnswer !== question.correct_answer) {
                    optionClass = 'incorrect';
                }
                
                html += `
                    <div class="answer-option ${optionClass}">
                        ${String.fromCharCode(65 + index)}. ${option}
                    </div>`;
            });
            
            html += `
                    </div>
                    <div class="mt-4 text-center">
                        ${isCorrect 
                            ? '<div class="alert alert-success">Bonne réponse !</div>' 
                            : `<div class="alert alert-danger">Mauvaise réponse. La réponse correcte était: ${question.correct_answer}</div>`}
                        <p>En attente de la prochaine question...</p>
                    </div>
                </div>`;
            
            $('#tournament-content').html(html);
        }
        
        // Show waiting screen
        function showWaitingScreen(data) {
            let html = `
                <div class="text-center">
                    <h3>${data.message}</h3>`;
            
            if(data.countdown > 0) {
                html += `<div class="round-countdown">${data.countdown}</div>`;
            } else {
                html += `
                    <div class="spinner-border my-4" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>`;
            }
            
            html += `</div>`;
            
            $('#tournament-content').html(html);
        }
        
        // Show round end screen
        function showRoundEndScreen(data) {
            let html = `
                <div class="text-center">
                    <h3>Fin du round ${data.current_round}</h3>`;
            
            if(data.is_active) {
                html += `
                    <div class="status-message status-qualified my-4">
                        <h4>Félicitations !</h4>
                        <p>Vous êtes qualifié pour le prochain round.</p>
                    </div>`;
            } else {
                html += `
                    <div class="status-message status-eliminated my-4">
                        <h4>Dommage !</h4>
                        <p>Vous êtes éliminé du tournoi.</p>
                    </div>`;
            }
            
            html += `
                    <p>Le prochain round commencera dans:</p>
                    <div class="round-countdown">${data.countdown}</div>
                </div>`;
            
            $('#tournament-content').html(html);
        }
        
        // Show tournament end screen
        function showTournamentEndScreen(data) {
            let html = `
                <div class="text-center">
                    <h3>Fin du tournoi</h3>`;
            
            if(data.is_winner) {
                html += `
                    <div class="status-message status-winner my-4">
                        <h4>Félicitations !</h4>
                        <p>Vous êtes le champion du Quiz Battle Royale !</p>
                    </div>`;
            } else {
                html += `
                    <div class="status-message status-eliminated my-4">
                        <h4>Merci d'avoir participé !</h4>
                        <p>Le tournoi est terminé.</p>
                    </div>`;
            }
            
            html += `
                    <h4 class="mt-4">Classement final</h4>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Position</th>
                                    <th>Joueur</th>
                                    <th>Score</th>
                                </tr>
                            </thead>
                            <tbody>`;
            
            data.leaderboard.forEach((player, index) => {
                html += `
                                <tr${player.user_id == <?= $_SESSION['user_id'] ?> ? ' class="table-primary"' : ''}>
                                    <td>${index + 1}</td>
                                    <td>${player.username}</td>
                                    <td>${player.score}</td>
                                </tr>`;
            });
            
            html += `
                            </tbody>
                        </table>
                    </div>
                    <a href="tournaments.php" class="btn btn-primary mt-3">Retour aux tournois</a>
                </div>`;
            
            $('#tournament-content').html(html);
        }
        
        // Load participants
        function loadParticipants() {
            $.ajax({
                url: 'api/get_participants.php',
                type: 'GET',
                data: {
                    tournament_id: tournamentId
                },
                dataType: 'json',
                success: function(response) {
                    let html = '';
                    
                    response.participants.forEach(participant => {
                        const isCurrentUser = participant.user_id == <?= $_SESSION['user_id'] ?>;
                        const statusClass = !participant.is_active ? 'eliminated' : (participant.is_winner ? 'winner' : '');
                        
                        html += `
                            <div class="participant-item ${statusClass}${isCurrentUser ? ' fw-bold' : ''}">
                                <span>${participant.username}</span>
                                <span>${participant.score}</span>
                            </div>`;
                    });
                    
                    $('#participants-list').html(html);
                    $('#active-participants').text(response.active_count);
                    $('#total-participants').text(response.total_count);
                }
            });
        }
    });
    </script>
    <?php endif; ?>
</body>
</html>
