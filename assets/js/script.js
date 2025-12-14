// Study Tracker Main JavaScript

// Task Management Functions
class TaskManager {
    constructor() {
        this.initializeEventListeners();
    }

    initializeEventListeners() {
        // Add Task Form Submit
        const addTaskForm = document.getElementById('addTaskForm');
        if (addTaskForm) {
            addTaskForm.addEventListener('submit', (e) => this.handleAddTask(e));
        }

        // Task Checkbox Change
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('task-checkbox')) {
                this.handleTaskComplete(e.target);
            }
        });

        // Priority change affects XP
        const prioritySelect = document.querySelector('select[name="priority"]');
        if (prioritySelect) {
            prioritySelect.addEventListener('change', (e) => {
                this.updateXPReward(e.target.value);
            });
        }
    }

    async handleAddTask(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        formData.append('action', 'create');

        try {
            const response = await fetch('api/task-crud.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                // Show confetti
                confetti({
                    particleCount: 150,
                    spread: 100,
                    origin: { y: 0.5 }
                });

                // Show success notification
                this.showNotification('✅ Task created successfully!', 'success');

                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('addTaskModal'));
                modal.hide();

                // Reset form
                e.target.reset();

                // Reload tasks
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                this.showNotification('❌ ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showNotification('❌ An error occurred!', 'error');
        }
    }

    async handleTaskComplete(checkbox) {
        const taskCard = checkbox.closest('.task-card');
        const taskId = checkbox.dataset.taskId;

        if (checkbox.checked) {
            // Confirm completion
            if (!confirm('Mark this task as completed?')) {
                checkbox.checked = false;
                return;
            }

            const formData = new FormData();
            formData.append('action', 'complete');
            formData.append('task_id', taskId);

            try {
                const response = await fetch('api/task-crud.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    // Add completed class
                    taskCard.classList.add('completed');

                    // Strike through text
                    const title = taskCard.querySelector('h6');
                    if (title) {
                        title.classList.add('text-decoration-line-through');
                    }

                    // Show confetti
                    confetti({
                        particleCount: 100,
                        spread: 70,
                        origin: { y: 0.6 }
                    });

                    // Build completion message
                    let message = `🎉 Task completed! +${data.xp_earned} XP`;
                    if (data.bonus_xp > 0) {
                        message += ` + ${data.bonus_xp} bonus XP`;
                    }
                    message += ` earned!`;

                    // Check for level up
                    if (data.level_up) {
                        message += `\n\n🌟 LEVEL UP! You are now Level ${data.new_level}!`;
                        
                        // Extra confetti for level up
                        setTimeout(() => {
                            confetti({
                                particleCount: 200,
                                spread: 120,
                                origin: { y: 0.5 }
                            });
                        }, 500);
                    }

                    // Show notification
                    this.showNotification(message, 'success');

                    // Reload after delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    checkbox.checked = false;
                    this.showNotification('❌ ' + data.message, 'error');
                }
            } catch (error) {
                checkbox.checked = false;
                console.error('Error:', error);
                this.showNotification('❌ An error occurred!', 'error');
            }
        }
    }

    updateXPReward(priority) {
        const xpInput = document.querySelector('input[name="xp_reward"]');
        if (xpInput) {
            const xpValues = {
                'low': 10,
                'medium': 20,
                'high': 40
            };
            xpInput.value = xpValues[priority] || 20;
        }
    }

    showNotification(message, type = 'info') {
        // Using alert for now, can be replaced with better notification library
        alert(message);
    }
}

// Pomodoro Timer
class PomodoroTimer {
    constructor(duration = 25) {
        this.duration = duration * 60; // Convert to seconds
        this.remaining = this.duration;
        this.isRunning = false;
        this.interval = null;
    }

    start() {
        if (this.isRunning) return;
        
        this.isRunning = true;
        this.interval = setInterval(() => {
            this.remaining--;
            this.updateDisplay();

            if (this.remaining <= 0) {
                this.complete();
            }
        }, 1000);
    }

    pause() {
        this.isRunning = false;
        if (this.interval) {
            clearInterval(this.interval);
        }
    }

    reset() {
        this.pause();
        this.remaining = this.duration;
        this.updateDisplay();
    }

    complete() {
        this.pause();
        
        // Play notification sound (if available)
        // Show completion notification
        confetti({
            particleCount: 150,
            spread: 100,
            origin: { y: 0.5 }
        });
        
        alert('🎉 Pomodoro completed! Great work!');
        
        // Save to focus_sessions table
        this.saveFocusSession();
    }

    updateDisplay() {
        const minutes = Math.floor(this.remaining / 60);
        const seconds = this.remaining % 60;
        const display = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        // Update any timer display elements
        const timerDisplay = document.getElementById('timer-display');
        if (timerDisplay) {
            timerDisplay.textContent = display;
        }
    }

    async saveFocusSession() {
        const formData = new FormData();
        formData.append('action', 'save_session');
        formData.append('duration', this.duration / 60);

        try {
            await fetch('api/focus-session.php', {
                method: 'POST',
                body: formData
            });
        } catch (error) {
            console.error('Error saving focus session:', error);
        }
    }
}

// Streak Manager
class StreakManager {
    static async checkStreak() {
        try {
            const response = await fetch('api/streak-check.php');
            const data = await response.json();
            
            if (data.streak_bonus) {
                confetti({
                    particleCount: 100,
                    spread: 70,
                    origin: { y: 0.6 }
                });
                
                alert(`🔥 Amazing! ${data.streak} days streak! +${data.bonus_xp} bonus XP!`);
            }
        } catch (error) {
            console.error('Error checking streak:', error);
        }
    }
}

// Statistics Chart Manager
class StatsManager {
    static initCharts() {
        // Weekly productivity chart
        const weeklyCtx = document.getElementById('weeklyChart');
        if (weeklyCtx) {
            this.createWeeklyChart(weeklyCtx);
        }

        // Subject distribution chart
        const subjectCtx = document.getElementById('subjectChart');
        if (subjectCtx) {
            this.createSubjectChart(subjectCtx);
        }
    }

    static createWeeklyChart(ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Tasks Completed',
                    data: [3, 5, 2, 8, 4, 6, 3],
                    borderColor: 'rgb(102, 126, 234)',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    static createSubjectChart(ctx) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['PBO', 'PWEB', 'Graf', 'MatDis', 'Jarkom', 'AI', 'PPL'],
                datasets: [{
                    data: [12, 19, 8, 15, 10, 7, 13],
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(139, 92, 246, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(249, 115, 22, 0.8)',
                        'rgba(234, 179, 8, 0.8)',
                        'rgba(6, 182, 212, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}

// Utility Functions
const Utils = {
    formatDate(date) {
        return new Date(date).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    },

    formatTime(date) {
        return new Date(date).toLocaleTimeString('id-ID', {
            hour: '2-digit',
            minute: '2-digit'
        });
    },

    getDeadlineColor(deadline) {
        const now = new Date();
        const deadlineDate = new Date(deadline);
        const diffHours = (deadlineDate - now) / (1000 * 60 * 60);

        if (diffHours < 0) return 'danger'; // Overdue
        if (diffHours < 24) return 'danger'; // < 1 day
        if (diffHours < 72) return 'warning'; // < 3 days
        return 'success'; // Safe
    },

    getPriorityBadge(priority) {
        const badges = {
            'low': '<span class="badge bg-success">Low</span>',
            'medium': '<span class="badge bg-warning">Medium</span>',
            'high': '<span class="badge bg-danger">High</span>'
        };
        return badges[priority] || badges['medium'];
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    // Initialize AOS (Animate On Scroll)
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 800,
            once: true
        });
    }

    // Initialize Task Manager
    const taskManager = new TaskManager();

    // Initialize Charts if on statistics page
    if (document.getElementById('weeklyChart')) {
        StatsManager.initCharts();
    }

    // Check streak on dashboard load
    if (window.location.pathname.includes('index.php') || window.location.pathname === '/') {
        StreakManager.checkStreak();
    }

    // Animate XP bar on load
    const xpBar = document.querySelector('.xp-bar');
    if (xpBar) {
        const targetWidth = xpBar.style.width;
        xpBar.style.width = '0%';
        setTimeout(() => {
            xpBar.style.width = targetWidth;
        }, 500);
    }

    // Subject card click handler
    document.querySelectorAll('.subject-card').forEach(card => {
        card.addEventListener('click', function() {
            const subjectId = this.dataset.subjectId;
            const subjectName = this.querySelector('h5').textContent;
            
            // Navigate to subject detail page or show modal
            window.location.href = `pages/tasks.php?subject_id=${subjectId}`;
        });
    });
});

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { TaskManager, PomodoroTimer, StreakManager, StatsManager, Utils };
}