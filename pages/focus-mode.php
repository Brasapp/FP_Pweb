<?php
require_once '../config/database.php';
requireLogin();

$user = getCurrentUser();
$user_id = $user['id'];

// Get active tasks
$active_tasks = queryAll("
    SELECT t.id, t.title, s.name as subject_name, s.color, s.icon
    FROM " . table('tasks') . " t
    JOIN " . table('subjects') . " s ON t.subject_id = s.id
    WHERE t.user_id = ? AND t.status != 'completed'
    ORDER BY t.deadline ASC
    LIMIT 20
", "i", [$user_id]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title id="pageTitle">25:00 - Focus Mode</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⏱️</text></svg>">
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            min-height: 100vh;
            color: white;
            overflow-x: hidden;
        }
        
        .focus-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
        }
        
        .timer-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-radius: 30px;
            padding: 3rem;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
        }
        
        .timer-display {
            font-size: 6rem;
            font-weight: bold;
            margin: 2rem 0;
            font-family: 'Courier New', monospace;
            text-shadow: 0 0 30px rgba(255, 255, 255, 0.5);
        }
        
        .timer-controls {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin: 2rem 0;
        }
        
        .timer-btn {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: none;
            font-size: 2rem;
            transition: all 0.3s;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        }
        
        .timer-btn:hover {
            transform: scale(1.1);
        }
        
        .btn-start {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .btn-pause {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }
        
        .btn-stop {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .preset-timers {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin: 2rem 0;
        }
        
        .preset-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 1rem;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .preset-btn:hover,
        .preset-btn.active {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            transform: scale(1.05);
        }
        
        .task-card {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 15px;
            padding: 1rem;
            margin: 0.5rem 0;
            text-align: left;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .task-card:hover {
            background: rgba(255, 255, 255, 0.25);
        }
        
        .task-card.selected {
            background: rgba(16, 185, 129, 0.3);
            border: 2px solid #10b981;
        }
        
        .session-info {
            background: rgba(16, 185, 129, 0.2);
            border-radius: 15px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .back-btn {
            position: fixed;
            top: 2rem;
            left: 2rem;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
    </style>
</head>
<body>
    <button class="back-btn" onclick="exitFocus()">
        <i class="fas fa-arrow-left me-2"></i>Exit
    </button>

    <div class="focus-container">
        <div class="timer-card">
            <h2 class="mb-4">
                <i class="fas fa-brain me-2"></i>Focus Mode
            </h2>
            
            <!-- Current Task Display -->
            <div id="currentTaskDisplay" class="d-none">
                <div class="session-info">
                    <small class="text-white-50">Currently working on:</small>
                    <h5 class="mb-0 mt-2" id="currentTaskTitle"></h5>
                </div>
            </div>
            
            <!-- Timer Display -->
            <div class="timer-display" id="timerDisplay">25:00</div>
            
            <!-- Preset Timers -->
            <div class="preset-timers">
                <button class="preset-btn active" onclick="setTimer(25)">
                    <i class="fas fa-clock d-block mb-2 fa-2x"></i>
                    <strong>25</strong><br>
                    <small>Pomodoro</small>
                </button>
                <button class="preset-btn" onclick="setTimer(15)">
                    <i class="fas fa-coffee d-block mb-2 fa-2x"></i>
                    <strong>15</strong><br>
                    <small>Short</small>
                </button>
                <button class="preset-btn" onclick="setTimer(45)">
                    <i class="fas fa-book d-block mb-2 fa-2x"></i>
                    <strong>45</strong><br>
                    <small>Deep Work</small>
                </button>
                <button class="preset-btn" onclick="setTimer(5)">
                    <i class="fas fa-pause d-block mb-2 fa-2x"></i>
                    <strong>5</strong><br>
                    <small>Break</small>
                </button>
            </div>
            
            <!-- Custom Timer -->
            <div class="my-3 d-flex gap-2 justify-content-center">
                <input type="number" class="form-control" id="customMinutes" placeholder="Custom minutes" min="1" max="120" style="max-width: 150px; background: rgba(255,255,255,0.2); color: white; border: 2px solid rgba(255,255,255,0.3);">
                <button class="btn btn-light" onclick="setCustomTimer()">
                    <i class="fas fa-check me-1"></i>Set
                </button>
            </div>
            
            <!-- Controls -->
            <div class="timer-controls">
                <button class="timer-btn btn-start" id="startBtn" onclick="startTimer()">
                    <i class="fas fa-play"></i>
                </button>
                <button class="timer-btn btn-pause d-none" id="pauseBtn" onclick="pauseTimer()">
                    <i class="fas fa-pause"></i>
                </button>
                <button class="timer-btn btn-stop" onclick="stopTimer()">
                    <i class="fas fa-stop"></i>
                </button>
            </div>
            
            <!-- Task Selection -->
            <div class="mt-4">
                <h6 class="text-start"><i class="fas fa-tasks me-2"></i>What are you working on? (Optional)</h6>
                <div style="max-height: 250px; overflow-y: auto;">
                    <?php if (empty($active_tasks)): ?>
                    <div class="alert alert-info mt-3">
                        No active tasks. <a href="../index.php" class="text-white fw-bold">Add tasks</a> first!
                    </div>
                    <?php else: ?>
                    <?php foreach ($active_tasks as $task): ?>
                    <div class="task-card" onclick="selectTask(<?= $task['id'] ?>, '<?= e($task['title']) ?>')">
                        <h6 class="mb-1"><?= e($task['title']) ?></h6>
                        <small>
                            <span class="badge" style="background: var(--<?= $task['color'] ?>);">
                                <i class="fas fa-<?= $task['icon'] ?>"></i> <?= e($task['subject_name']) ?>
                            </span>
                        </small>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="mt-4">
                <small class="text-white-50">
                    <i class="fas fa-keyboard me-1"></i>Tip: Press <kbd>Space</kbd> to start/pause
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let timerInterval;
        let timeLeft = 25 * 60;
        let totalTime = 25 * 60;
        let isRunning = false;
        let selectedTaskTitle = '';
        
        const timerDisplay = document.getElementById('timerDisplay');
        const pageTitle = document.getElementById('pageTitle');
        const startBtn = document.getElementById('startBtn');
        const pauseBtn = document.getElementById('pauseBtn');
        
        function setTimer(minutes) {
            if (isRunning) {
                if (!confirm('Timer is running. Reset?')) return;
                stopTimer();
            }
            
            timeLeft = minutes * 60;
            totalTime = minutes * 60;
            updateDisplay();
            
            document.querySelectorAll('.preset-btn').forEach(btn => btn.classList.remove('active'));
            if (event && event.target) {
                event.target.closest('.preset-btn').classList.add('active');
            }
        }
        
        function setCustomTimer() {
            const minutes = parseInt(document.getElementById('customMinutes').value);
            if (minutes && minutes > 0 && minutes <= 120) {
                setTimer(minutes);
                document.getElementById('customMinutes').value = '';
            } else {
                alert('⚠️ Please enter 1-120 minutes');
            }
        }
        
        function selectTask(taskId, title) {
            selectedTaskTitle = title;
            
            // Highlight selected task
            document.querySelectorAll('.task-card').forEach(card => card.classList.remove('selected'));
            event.target.closest('.task-card').classList.add('selected');
            
            // Show current task
            document.getElementById('currentTaskDisplay').classList.remove('d-none');
            document.getElementById('currentTaskTitle').textContent = title;
        }
        
        function startTimer() {
            if (isRunning) return;
            
            isRunning = true;
            startBtn.classList.add('d-none');
            pauseBtn.classList.remove('d-none');
            
            timerInterval = setInterval(() => {
                timeLeft--;
                updateDisplay();
                
                if (timeLeft <= 0) {
                    completeSession();
                }
            }, 1000);
        }
        
        function pauseTimer() {
            isRunning = false;
            clearInterval(timerInterval);
            startBtn.classList.remove('d-none');
            pauseBtn.classList.add('d-none');
        }
        
        function stopTimer() {
            isRunning = false;
            clearInterval(timerInterval);
            timeLeft = totalTime;
            updateDisplay();
            startBtn.classList.remove('d-none');
            pauseBtn.classList.add('d-none');
        }
        
        function updateDisplay() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            const display = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            timerDisplay.textContent = display;
            
            // Update browser tab title with timer
            if (isRunning) {
                if (selectedTaskTitle) {
                    pageTitle.textContent = `⏱️ ${display} - ${selectedTaskTitle}`;
                } else {
                    pageTitle.textContent = `⏱️ ${display} - Focus Mode`;
                }
            } else {
                pageTitle.textContent = `${display} - Focus Mode`;
            }
        }
        
        function completeSession() {
            stopTimer();
            playCompletionSound();
            
            let message = '🎉 Focus session completed!\n\n';
            message += `⏱️ You focused for ${Math.round(totalTime / 60)} minutes!`;
            
            if (selectedTaskTitle) {
                message += `\n📚 Task: ${selectedTaskTitle}`;
            }
            
            alert(message);
            
            // Ask if want another session
            if (confirm('Start another focus session?')) {
                location.reload();
            }
        }
        
        function playCompletionSound() {
            // Simple beep
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            
            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.5);
        }
        
        function exitFocus() {
            if (isRunning) {
                if (!confirm('Timer is running. Exit anyway?')) {
                    return;
                }
            }
            window.location.href = '../index.php';
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.code === 'Space') {
                e.preventDefault();
                if (isRunning) {
                    pauseTimer();
                } else {
                    startTimer();
                }
            }
            if (e.code === 'Escape') {
                exitFocus();
            }
        });
        
        // Warn before closing if timer is running
        window.addEventListener('beforeunload', (e) => {
            if (isRunning) {
                e.preventDefault();
                e.returnValue = '';
                return '';
            }
        });
    </script>
</body>
</html>