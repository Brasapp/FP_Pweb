// ==========================================
// TASK MODAL HANDLERS
// ==========================================

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('Task Modal JS Loaded');
    
    // Update XP preview based on priority
    const prioritySelect = document.getElementById('task_priority');
    if (prioritySelect) {
        prioritySelect.addEventListener('change', function() {
            const xpMap = { 'low': '10 XP', 'medium': '30 XP', 'high': '50 XP' };
            document.getElementById('xp_preview').textContent = xpMap[this.value] || '30 XP';
        });
    }

    // Reset modal when closed
    const taskModal = document.getElementById('taskModal');
    if (taskModal) {
        taskModal.addEventListener('hidden.bs.modal', function() {
            const form = document.getElementById('taskForm');
            if (form) {
                form.reset();
                document.getElementById('task_id').value = '';
                document.getElementById('form_action').value = 'create';
                document.getElementById('taskModalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Add New Task';
                document.getElementById('status_field').style.display = 'none';
                document.getElementById('xp_preview').textContent = '30 XP';
                document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Save Task';
            }
        });
    }

    // Form submission handler
    const taskForm = document.getElementById('taskForm');
    if (taskForm) {
        taskForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = document.getElementById('submitBtn');
            const originalBtnText = submitBtn.innerHTML;
            
            // Disable button
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
            
            fetch('/api/task-crud.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    alert('✅ ' + data.message);
                    
                    // Close modal
                    const modalInstance = bootstrap.Modal.getInstance(document.getElementById('taskModal'));
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                    
                    // Reload page to show new task
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                } else {
                    alert('❌ Error: ' + data.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Failed to save task. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
        });
    }
});

// ==========================================
// GLOBAL FUNCTIONS FOR MODAL
// ==========================================

// Open modal for creating new task
function openAddTaskModal() {
    console.log('Opening Add Task Modal');
    const modalElement = document.getElementById('taskModal');
    if (modalElement) {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    } else {
        console.error('Task modal element not found!');
    }
}

// Open modal for editing task
function openEditTaskModal(task) {
    console.log('Opening Edit Task Modal', task);
    
    // Set form values
    document.getElementById('task_id').value = task.id;
    document.getElementById('form_action').value = 'update';
    document.getElementById('task_title').value = task.title;
    document.getElementById('task_subject').value = task.subject_id;
    
    // Format deadline for datetime-local input
    const deadline = task.deadline.replace(' ', 'T');
    document.getElementById('task_deadline').value = deadline;
    
    document.getElementById('task_priority').value = task.priority;
    document.getElementById('task_estimated_time').value = task.estimated_time || 30;
    document.getElementById('task_description').value = task.description || '';
    document.getElementById('task_status').value = task.status;
    
    // Show status field for edit
    document.getElementById('status_field').style.display = 'block';
    
    // Update modal title
    document.getElementById('taskModalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Task';
    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Update Task';
    
    // Update XP preview
    const xpMap = { 'low': '10 XP', 'medium': '30 XP', 'high': '50 XP' };
    document.getElementById('xp_preview').textContent = xpMap[task.priority] || '30 XP';
    
    // Open modal
    const modal = new bootstrap.Modal(document.getElementById('taskModal'));
    modal.show();
}