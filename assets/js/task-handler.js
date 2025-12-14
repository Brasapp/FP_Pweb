// ==========================================
// TASK COMPLETION HANDLER
// ==========================================
function completeTask(taskId) {
    if (!confirm('Mark this task as completed?')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'complete');
    formData.append('task_id', taskId);

    fetch('/api/task-crud.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show confetti animation
            if (typeof confetti !== 'undefined') {
                confetti({
                    particleCount: 100,
                    spread: 70,
                    origin: { y: 0.6 }
                });
            }

            // Build success message
            let message = `🎉 ${data.message}\n\n`;
            message += `⭐ XP Earned: ${data.xp_earned}`;
            
            if (data.bonus_xp > 0) {
                message += ` + ${data.bonus_xp} bonus`;
            }
            
            message += ` = ${data.total_xp} total XP\n`;

            if (data.level_up) {
                message += `\n🆙 LEVEL UP! You are now Level ${data.new_level}!\n`;
            }

            if (data.new_streak > 1) {
                message += `\n🔥 ${data.new_streak} Day Streak!\n`;
            }

            if (data.new_achievements && data.new_achievements.length > 0) {
                message += `\n🏆 New Achievement(s) Unlocked:\n`;
                data.new_achievements.forEach(achievement => {
                    message += `   • ${achievement}\n`;
                });
            }

            alert(message);

            // Remove task card with animation
            const taskCard = document.querySelector(`[data-task-id="${taskId}"]`).closest('.task-card');
            if (taskCard) {
                taskCard.style.transition = 'all 0.3s ease';
                taskCard.style.opacity = '0';
                taskCard.style.transform = 'translateX(50px)';
                
                setTimeout(() => {
                    taskCard.remove();
                    
                    // Check if no tasks left
                    if (document.querySelectorAll('.task-card').length === 0) {
                        location.reload();
                    }
                }, 300);
            } else {
                // Fallback: reload page
                location.reload();
            }
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to complete task. Please try again.');
    });
}

// ==========================================
// DELETE TASK HANDLER
// ==========================================
function deleteTask(taskId) {
    if (!confirm('Are you sure you want to delete this task?')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('task_id', taskId);

    fetch('/api/task-crud.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            
            // Remove task card
            const taskCard = document.querySelector(`[data-task-id="${taskId}"]`).closest('.task-card');
            if (taskCard) {
                taskCard.remove();
            } else {
                location.reload();
            }
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to delete task. Please try again.');
    });
}

// ==========================================
// CHECKBOX HANDLER (AUTO COMPLETE)
// ==========================================
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.task-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                const taskId = this.getAttribute('data-task-id');
                completeTask(taskId);
            }
        });
    });
});