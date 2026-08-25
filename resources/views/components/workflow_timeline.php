<?php
/**
 * Workflow Timeline Component
 *
 * Expected variables:
 * $workflow_history (array of history records)
 */

if (!isset($workflow_history) || empty($workflow_history)) {
    echo '<div class="timeline-empty"><i class="fas fa-info-circle"></i> No workflow history available.</div>';
    return;
}
?>

<div class="workflow-timeline">
    <?php foreach ($workflow_history as $history): 
        // Determine icon and color based on action/status
        $icon = 'fa-circle';
        $color = '#6c757d'; // default gray

        $action_lower = strtolower($history['action_type']);
        if (strpos($action_lower, 'submit') !== false || strpos($action_lower, 'ajukan') !== false || strpos($action_lower, 'save') !== false) {
            $icon = 'fa-paper-plane';
            $color = '#007bff'; // blue
        } elseif (strpos($action_lower, 'verif') !== false || strpos($action_lower, 'accept') !== false || strpos($action_lower, 'approve') !== false) {
            $icon = 'fa-check';
            $color = '#28a745'; // green
        } elseif (strpos($action_lower, 'reject') !== false || strpos($action_lower, 'return') !== false) {
            $icon = 'fa-times';
            $color = '#dc3545'; // red
        } elseif (strpos($action_lower, 'update') !== false || strpos($action_lower, 'resubmit') !== false) {
            $icon = 'fa-sync-alt';
            $color = '#17a2b8'; // teal
        }
    ?>
        <div class="timeline-item">
            <div class="timeline-icon" style="background-color: <?= $color ?>">
                <i class="fas <?= $icon ?>"></i>
            </div>
            <div class="timeline-content">
                <h5 class="timeline-title"><?= htmlspecialchars($history['action_type']) ?></h5>
                
                <div class="timeline-meta">
                    <span class="timeline-user">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($history['actor_name'] ?? 'System') ?> 
                        <?php if(!empty($history['actor_role'])): ?>
                            <span class="timeline-role">(<?= htmlspecialchars($history['actor_role']) ?>)</span>
                        <?php endif; ?>
                    </span>
                    <span class="timeline-date">
                        <i class="fas fa-clock"></i> 
                        <?= date('d F Y • H:i', strtotime($history['created_at'])) ?>
                    </span>
                </div>

                <?php if (!empty($history['notes'])): ?>
                    <div class="timeline-notes">
                        <strong>Notes:</strong> <?= nl2br(htmlspecialchars($history['notes'])) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<style>
.workflow-timeline {
    position: relative;
    padding: 20px 0;
}
.workflow-timeline::before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: 20px;
    width: 2px;
    background: #e9ecef;
}
.timeline-item {
    position: relative;
    margin-bottom: 25px;
    padding-left: 50px;
}
.timeline-item:last-child {
    margin-bottom: 0;
}
.timeline-icon {
    position: absolute;
    left: 6px;
    top: 0;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    color: white;
    text-align: center;
    line-height: 30px;
    font-size: 14px;
    z-index: 1;
}
.timeline-content {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 15px;
}
.timeline-title {
    margin: 0 0 10px 0;
    font-size: 16px;
    font-weight: 600;
    color: #333;
}
.timeline-meta {
    font-size: 13px;
    color: #6c757d;
    margin-bottom: 8px;
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}
.timeline-user i, .timeline-date i {
    margin-right: 5px;
}
.timeline-role {
    color: #888;
    font-style: italic;
}
.timeline-notes {
    margin-top: 10px;
    padding: 10px;
    background: #fff;
    border-left: 3px solid #ffc107;
    border-radius: 4px;
    font-size: 13px;
    color: #495057;
}
.timeline-empty {
    padding: 20px;
    text-align: center;
    color: #6c757d;
    background: #f8f9fa;
    border-radius: 6px;
    border: 1px dashed #ced4da;
}
</style>
