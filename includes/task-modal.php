<?php
// Get subjects for dropdown (jika belum di-load)
if (!isset($subjects) || empty($subjects)) {
    $subjects = queryAll("SELECT * FROM " . table('subjects') . " WHERE user_id = ? ORDER BY name", "i", [$user_id ?? $user['id']]);
}
?>

<!-- Add/Edit Task Modal -->
<div class="modal fade" id="taskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="taskModalTitle">
                    <i class="fas fa-plus-circle"></i> Add New Task
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="taskForm">
                <div class="modal-body">
                    <input type="hidden" id="task_id" name="task_id">
                    <input type="hidden" id="form_action" name="action" value="create">
                    
                    <!-- Title -->
                    <div class="mb-3">
                        <label class="form-label">Task Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="task_title" name="title" required placeholder="e.g., Buat CRUD PHP">
                    </div>

                    <!-- Subject -->
                    <div class="mb-3">
                        <label class="form-label">Subject <span class="text-danger">*</span></label>
                        <select class="form-select" id="task_subject" name="subject_id" required>
                            <option value="">-- Select Subject --</option>
                            <?php foreach ($subjects as $subject): ?>
                            <option value="<?= $subject['id'] ?>" data-color="<?= $subject['color'] ?>" data-icon="<?= $subject['icon'] ?>">
                                <?= e($subject['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <!-- Deadline -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Deadline <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="task_deadline" name="deadline" required>
                        </div>

                        <!-- Priority -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Priority <span class="text-danger">*</span></label>
                            <select class="form-select" id="task_priority" name="priority" required>
                                <option value="low">🟢 Low (10 XP)</option>
                                <option value="medium" selected>🟡 Medium (30 XP)</option>
                                <option value="high">🔴 High (50 XP)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Estimated Time -->
                    <div class="mb-3">
                        <label class="form-label">Estimated Time</label>
                        <select class="form-select" id="task_estimated_time" name="estimated_time">
                            <option value="15">15 minutes</option>
                            <option value="30" selected>30 minutes</option>
                            <option value="60">1 hour</option>
                            <option value="120">2 hours</option>
                            <option value="180">3 hours</option>
                            <option value="240">4 hours</option>
                        </select>
                    </div>

                    <!-- Description -->
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="task_description" name="description" rows="4" placeholder="Add task details, requirements, or notes..."></textarea>
                    </div>

                    <!-- Status (hanya untuk edit) -->
                    <div class="mb-3" id="status_field" style="display: none;">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="task_status" name="status">
                            <option value="todo">To Do</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>

                    <!-- XP Preview -->
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>XP Reward:</strong> <span id="xp_preview">30 XP</span>
                        <br>
                        <small class="text-muted">Complete early for bonus XP!</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> Save Task
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.modal-content {
    border-radius: 15px;
    border: none;
}

.modal-header {
    background: linear-gradient(135deg, #10b981, #047857);
    color: white;
    border-radius: 15px 15px 0 0;
}

.modal-header .btn-close {
    filter: brightness(0) invert(1);
}

.form-label {
    font-weight: 600;
    color: #374151;
}

.form-control:focus,
.form-select:focus {
    border-color: #10b981;
    box-shadow: 0 0 0 0.2rem rgba(16, 185, 129, 0.25);
}
</style>