<?php
require_once '../config/database.php';
requireLogin();

$user = getCurrentUser();
$user_id = $user['id'];

// Get all tasks for calendar
$tasks = queryAll("
    SELECT t.*, s.name as subject_name, s.color as subject_color, s.icon as subject_icon
    FROM " . table('tasks') . " t
    LEFT JOIN " . table('subjects') . " s ON t.subject_id = s.id
    WHERE t.user_id = ?
    ORDER BY t.deadline
", "i", [$user_id]);

// Format tasks for FullCalendar
$calendar_events = [];
foreach ($tasks as $task) {
    // Color based on PRIORITY, not subject
    $priority_colors = [
        'high' => '#ef4444',
        'medium' => '#f59e0b',
        'low' => '#10b981'
    ];
    
    $bg_color = $priority_colors[$task['priority']] ?? '#3b82f6';
    
    // Add emoji based on priority
    $priority_emoji = [
        'high' => '🔴 ',
        'medium' => '🟡 ',
        'low' => '🟢 '
    ];
    
    $calendar_events[] = [
        'id' => $task['id'],
        'title' => $priority_emoji[$task['priority']] . $task['title'],
        'start' => $task['deadline'],
        'backgroundColor' => $bg_color,
        'borderColor' => $bg_color,
        'textColor' => '#ffffff',
        'extendedProps' => [
            'subject' => $task['subject_name'],
            'subject_color' => $task['subject_color'],
            'subject_icon' => $task['subject_icon'],
            'priority' => $task['priority'],
            'status' => $task['status'],
            'xp' => $task['xp_reward'],
            'description' => $task['description']
        ],
        'classNames' => $task['status'] === 'completed' ? ['task-completed'] : []
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - Study Tracker</title>
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding-bottom: 2rem;
        }

        .calendar-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin: 2rem 0;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .fc {
            background: white;
        }

        .fc-event {
            cursor: pointer;
            border-radius: 5px;
            padding: 2px 5px;
            font-weight: 600;
        }

        .fc-event.task-completed {
            text-decoration: line-through;
            opacity: 0.6;
            background: #6b7280 !important;
            border-color: #6b7280 !important;
        }

        .fc-daygrid-day-number {
            color: #333;
            font-weight: 600;
        }

        .fc-col-header-cell {
            background: #f3f4f6;
            font-weight: bold;
        }

        .legend {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 10px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .legend-color {
            width: 24px;
            height: 24px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .task-modal .modal-content {
            border-radius: 15px;
        }

        .task-modal .modal-header {
            border-radius: 15px 15px 0 0;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="calendar-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-0"><i class="fas fa-calendar text-primary"></i> Calendar</h2>
                    <p class="text-muted mb-0">View all your tasks in calendar format</p>
                </div>
                <a href="tasks.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Task
                </a>
            </div>

            <!-- Legend -->
            <div class="legend">
                <div class="legend-item">
                    <div class="legend-color" style="background: #ef4444;">🔴</div>
                    <span><strong>High Priority</strong></span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #f59e0b;">🟡</div>
                    <span><strong>Medium Priority</strong></span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #10b981;">🟢</div>
                    <span><strong>Low Priority</strong></span>
                </div>
                <div class="legend-item">
                    <i class="fas fa-check-circle text-success fa-2x"></i>
                    <span><strong>Completed (with strikethrough)</strong></span>
                </div>
            </div>

            <!-- Calendar -->
            <div id="calendar"></div>
        </div>
    </div>

    <!-- Task Detail Modal -->
    <div class="modal fade task-modal" id="taskModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" id="modalHeader">
                    <h5 class="modal-title text-white" id="modalTitle"></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Content will be populated by JS -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" class="btn btn-primary" id="editTaskBtn">
                        <i class="fas fa-edit"></i> Edit Task
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            const events = <?= json_encode($calendar_events) ?>;

            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,listWeek'
                },
                events: events,
                eventClick: function(info) {
                    showTaskDetail(info.event);
                },
                eventDidMount: function(info) {
                    // Add priority badge on the left
                    const priority = info.event.extendedProps.priority;
                    info.el.style.borderLeft = '4px solid';
                    
                    if (priority === 'high') {
                        info.el.style.borderLeftColor = '#dc2626';
                    } else if (priority === 'medium') {
                        info.el.style.borderLeftColor = '#d97706';
                    } else {
                        info.el.style.borderLeftColor = '#059669';
                    }
                }
            });

            calendar.render();
        });

        function showTaskDetail(event) {
            const props = event.extendedProps;
            const modal = new bootstrap.Modal(document.getElementById('taskModal'));
            
            // Set header color based on priority
            const headerColors = {
                'high': 'linear-gradient(135deg, #ef4444, #dc2626)',
                'medium': 'linear-gradient(135deg, #f59e0b, #d97706)',
                'low': 'linear-gradient(135deg, #10b981, #059669)'
            };
            
            document.getElementById('modalHeader').style.background = headerColors[props.priority];
            document.getElementById('modalTitle').innerHTML = `
                <i class="fas fa-${props.subject_icon}"></i> ${event.title.replace(/[🔴🟡🟢] /, '')}
            `;
            
            // Status badge
            let statusBadge = '';
            if (props.status === 'completed') {
                statusBadge = '<span class="badge bg-success"><i class="fas fa-check-circle"></i> Completed</span>';
            } else if (props.status === 'in_progress') {
                statusBadge = '<span class="badge bg-info"><i class="fas fa-spinner"></i> In Progress</span>';
            } else {
                statusBadge = '<span class="badge bg-secondary"><i class="fas fa-circle"></i> To Do</span>';
            }
            
            // Priority badge
            const priorityBadges = {
                'high': '<span class="badge bg-danger">🔴 High Priority</span>',
                'medium': '<span class="badge bg-warning">🟡 Medium Priority</span>',
                'low': '<span class="badge bg-success">🟢 Low Priority</span>'
            };
            
            document.getElementById('modalBody').innerHTML = `
                <div class="mb-3">
                    ${statusBadge}
                    ${priorityBadges[props.priority]}
                    <span class="badge bg-primary">+${props.xp} XP</span>
                </div>
                
                <div class="mb-3">
                    <strong><i class="fas fa-book me-2"></i>Subject:</strong>
                    <span class="badge" style="background: var(--${props.subject_color}); font-size: 1rem;">
                        <i class="fas fa-${props.subject_icon}"></i> ${props.subject}
                    </span>
                </div>
                
                <div class="mb-3">
                    <strong><i class="fas fa-calendar me-2"></i>Deadline:</strong><br>
                    <span class="fs-5">${new Date(event.start).toLocaleString('id-ID', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    })}</span>
                </div>
                
                ${props.description ? `
                <div class="mb-3">
                    <strong><i class="fas fa-align-left me-2"></i>Description:</strong><br>
                    <div class="p-3 bg-light rounded mt-2">
                        ${props.description.replace(/\n/g, '<br>')}
                    </div>
                </div>
                ` : ''}
            `;
            
            document.getElementById('editTaskBtn').href = `tasks.php?edit=${event.id}`;
            
            modal.show();
        }
    </script>
</body>
</html>