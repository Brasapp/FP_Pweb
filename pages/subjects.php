<?php
require_once '../config/database.php';
requireLogin();

$user = getCurrentUser();
$user_id = $user['id'];

// Get all subjects
$subjects = queryAll("SELECT * FROM " . table('subjects') . " WHERE user_id = ? ORDER BY name", "i", [$user_id]);

// Available colors and icons
$colors = ['blue', 'green', 'purple', 'red', 'orange', 'yellow', 'cyan'];
$icons = [
    'laptop-code' => 'Programming',
    'globe' => 'Web',
    'project-diagram' => 'Graph Theory',
    'calculator' => 'Mathematics',
    'network-wired' => 'Networking',
    'robot' => 'AI',
    'tools' => 'Software',
    'database' => 'Database',
    'mobile-alt' => 'Mobile',
    'paint-brush' => 'Design'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subjects - Study Tracker</title>
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    
    <style>
        :root {
            --blue: #3b82f6;
            --green: #10b981;
            --purple: #8b5cf6;
            --red: #ef4444;
            --orange: #f97316;
            --yellow: #eab308;
            --cyan: #06b6d4;
        }
        
        body {
            background: linear-gradient(135deg, #10b981 0%, #047857 100%);
            min-height: 100vh;
        }
        
        .main-container {
            padding: 2rem 0;
        }
        
        .page-header {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
        
        .subject-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .subject-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
        }
        
        .subject-icon {
            width: 70px;
            height: 70px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            margin-bottom: 1rem;
        }
        
        .progress-ring {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.5rem;
            position: relative;
        }
        
        .progress-ring::before {
            content: '';
            position: absolute;
            inset: 8px;
            background: white;
            border-radius: 50%;
            z-index: 0;
        }
        
        .progress-ring span {
            position: relative;
            z-index: 1;
        }
        
        .btn-add-subject {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981, #047857);
            border: none;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 5px 25px rgba(16, 185, 129, 0.5);
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .btn-add-subject:hover {
            transform: scale(1.1) rotate(90deg);
        }
        
        .color-option {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            cursor: pointer;
            border: 3px solid transparent;
            transition: all 0.2s;
        }
        
        .color-option:hover,
        .color-option.selected {
            border-color: #374151;
            transform: scale(1.1);
        }
        
        .icon-option {
            padding: 15px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        
        .icon-option:hover,
        .icon-option.selected {
            border-color: #10b981;
            background: #f0fdf4;
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container main-container">
        <!-- Page Header -->
        <div class="page-header" data-aos="fade-up">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0">
                        <i class="fas fa-book text-primary"></i> My Subjects
                    </h2>
                    <p class="text-muted mb-0">Manage your courses and track progress</p>
                </div>
                <div>
                    <h4 class="mb-0"><?= count($subjects) ?></h4>
                    <small class="text-muted">Total Subjects</small>
                </div>
            </div>
        </div>

        <!-- Subjects Grid -->
        <?php if (empty($subjects)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>No subjects yet. Click the + button to add your first subject!
        </div>
        <?php else: ?>
        <div class="row">
            <?php 
            $delay = 100;
            $color_map = [
                'blue' => 'var(--blue)',
                'green' => 'var(--green)',
                'purple' => 'var(--purple)',
                'red' => 'var(--red)',
                'orange' => 'var(--orange)',
                'yellow' => 'var(--yellow)',
                'cyan' => 'var(--cyan)'
            ];
            
            foreach ($subjects as $subject): 
                $color = $color_map[$subject['color']] ?? 'var(--green)';
                $progress = round($subject['progress_percentage']);
            ?>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?= $delay ?>">
                <div class="subject-card">
                    <div style="background: <?= $color ?>; position: absolute; top: 0; left: 0; right: 0; height: 5px;"></div>
                    
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="subject-icon" style="background: linear-gradient(135deg, <?= $color ?>, <?= $color ?>dd);">
                            <i class="fas fa-<?= $subject['icon'] ?>"></i>
                        </div>
                        <div class="progress-ring" style="background: conic-gradient(<?= $color ?> <?= $progress ?>%, #e5e7eb 0);">
                            <span><?= $progress ?>%</span>
                        </div>
                    </div>
                    
                    <h5 class="fw-bold mb-3"><?= e($subject['name']) ?></h5>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Progress</span>
                            <span class="fw-bold"><?= $subject['completed_tasks'] ?> / <?= $subject['total_tasks'] ?> tasks</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar" style="width: <?= $progress ?>%; background: <?= $color ?>;"></div>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary flex-grow-1" onclick="viewSubjectTasks(<?= $subject['id'] ?>)">
                            <i class="fas fa-tasks"></i> View Tasks
                        </button>
                        <button class="btn btn-sm btn-outline-success" onclick="editSubject(<?= $subject['id'] ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteSubject(<?= $subject['id'] ?>, '<?= e($subject['name']) ?>')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php 
            $delay += 50;
            endforeach; 
            ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Add Subject Button -->
    <button class="btn-add-subject" onclick="openAddSubjectModal()">
        <i class="fas fa-plus"></i>
    </button>

    <!-- Subject Modal -->
    <div class="modal fade" id="subjectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="subjectModalTitle">
                        <i class="fas fa-plus-circle"></i> Add New Subject
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="subjectForm">
                    <div class="modal-body">
                        <input type="hidden" id="subject_id" name="subject_id">
                        <input type="hidden" id="form_action" name="action" value="create">
                        
                        <!-- Name -->
                        <div class="mb-3">
                            <label class="form-label">Subject Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="subject_name" name="name" required placeholder="e.g., Pemrograman Web">
                        </div>

                        <!-- Color -->
                        <div class="mb-3">
                            <label class="form-label">Color <span class="text-danger">*</span></label>
                            <div class="d-flex gap-2">
                                <?php foreach ($colors as $color): ?>
                                <div class="color-option" 
                                     style="background: var(--<?= $color ?>);" 
                                     data-color="<?= $color ?>"
                                     onclick="selectColor('<?= $color ?>')"></div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" id="subject_color" name="color" value="green" required>
                        </div>

                        <!-- Icon -->
                        <div class="mb-3">
                            <label class="form-label">Icon <span class="text-danger">*</span></label>
                            <div class="row g-2">
                                <?php foreach ($icons as $icon => $label): ?>
                                <div class="col-4">
                                    <div class="icon-option" data-icon="<?= $icon ?>" onclick="selectIcon('<?= $icon ?>')">
                                        <i class="fas fa-<?= $icon ?> fa-2x mb-2"></i>
                                        <div><small><?= $label ?></small></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" id="subject_icon" name="icon" value="laptop-code" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-save"></i> Save Subject
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    
    <script>
        AOS.init({ duration: 800, once: true });

        // Color selection
        function selectColor(color) {
            document.querySelectorAll('.color-option').forEach(el => el.classList.remove('selected'));
            document.querySelector(`[data-color="${color}"]`).classList.add('selected');
            document.getElementById('subject_color').value = color;
        }

        // Icon selection
        function selectIcon(icon) {
            document.querySelectorAll('.icon-option').forEach(el => el.classList.remove('selected'));
            document.querySelector(`[data-icon="${icon}"]`).classList.add('selected');
            document.getElementById('subject_icon').value = icon;
        }

        // Open add modal
        function openAddSubjectModal() {
            document.getElementById('subjectForm').reset();
            document.getElementById('subject_id').value = '';
            document.getElementById('form_action').value = 'create';
            document.getElementById('subjectModalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Add New Subject';
            
            // Select defaults
            selectColor('green');
            selectIcon('laptop-code');
            
            const modal = new bootstrap.Modal(document.getElementById('subjectModal'));
            modal.show();
        }

        // Edit subject
        function editSubject(id) {
            fetch(`/api/subject-crud.php?action=get&subject_id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const subject = data.subject;
                    document.getElementById('subject_id').value = subject.id;
                    document.getElementById('form_action').value = 'update';
                    document.getElementById('subject_name').value = subject.name;
                    
                    selectColor(subject.color);
                    selectIcon(subject.icon);
                    
                    document.getElementById('subjectModalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Subject';
                    
                    const modal = new bootstrap.Modal(document.getElementById('subjectModal'));
                    modal.show();
                } else {
                    alert('Failed to load subject data');
                }
            });
        }

        // Delete subject
        function deleteSubject(id, name) {
            if (!confirm(`Delete subject "${name}"?\n\nAll tasks in this subject will also be deleted!`)) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('subject_id', id);

            fetch('/api/subject-crud.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            });
        }

        // View subject tasks
        function viewSubjectTasks(id) {
            window.location.href = '/pages/tasks.php?subject=' + id;
        }

        // Form submission
        document.getElementById('subjectForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';
            
            fetch('/api/subject-crud.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Subject';
                }
            });
        });
    </script>
</body>
</html>