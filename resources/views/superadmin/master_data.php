<?php
$page_title = 'Master Data Center';
require_once dirname(__DIR__, 3) . '/bootstrap/app.php';
require_once dirname(__DIR__, 3) . '/app/Helpers/auth_helper.php';

// RBAC: Check generic access to master data
requirePermission('admin.access'); 

require_once dirname(__DIR__) . '/layouts/header.php';
?>

<style>
    .md-dashboard { font-family: 'Inter', sans-serif; padding: 20px 0; }
    .md-card { background: #fff; border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); transition: transform 0.2s; height: 100%; display: flex; flex-direction: column; text-decoration: none; color: inherit; }
    .md-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); color: inherit; }
    .md-icon { font-size: 2.5rem; margin-bottom: 15px; color: #3b82f6; }
    .md-title { font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 10px; }
    .md-desc { font-size: 0.9rem; color: #64748b; flex-grow: 1; }
</style>

<div class="container-fluid md-dashboard">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-1 fw-bold" style="color: #1e293b;">Master Data Center</h2>
            <p class="text-muted mb-0">Manage STELA reference data</p>
        </div>
    </div>

    <div class="row g-4">
        <?php if(hasPermission('position.view')): ?>
        <div class="col-md-4 col-sm-6">
            <a href="positions.php" class="md-card p-4">
                <i class="fas fa-briefcase md-icon text-primary"></i>
                <div class="md-title">Positions</div>
                <div class="md-desc">Manage job positions, types (Operasional/Teknis), and map them to competencies.</div>
            </a>
        </div>
        <?php endif; ?>

        <?php if(hasPermission('competency.view')): ?>
        <div class="col-md-4 col-sm-6">
            <a href="competencies.php" class="md-card p-4">
                <i class="fas fa-star md-icon text-success"></i>
                <div class="md-title">Competencies</div>
                <div class="md-desc">Define standard competencies categorized by position types.</div>
            </a>
        </div>
        <?php endif; ?>

        <?php if(hasPermission('competency.view')): ?>
        <div class="col-md-4 col-sm-6">
            <a href="sub_competencies.php" class="md-card p-4">
                <i class="fas fa-layer-group md-icon text-info"></i>
                <div class="md-title">Sub Competencies</div>
                <div class="md-desc">Manage specific competency levels and link them to main competencies.</div>
            </a>
        </div>
        <?php endif; ?>

        <?php if(hasPermission('certification.view')): ?>
        <div class="col-md-4 col-sm-6">
            <a href="certifications.php" class="md-card p-4">
                <i class="fas fa-certificate md-icon text-warning"></i>
                <div class="md-title">Certifications</div>
                <div class="md-desc">Master list of certifications, validity periods, and issuing authorities.</div>
            </a>
        </div>
        <?php endif; ?>

        <?php if(hasPermission('supervision_area.view')): ?>
        <div class="col-md-4 col-sm-6">
            <a href="supervision_areas.php" class="md-card p-4">
                <i class="fas fa-map-marked-alt md-icon text-danger"></i>
                <div class="md-title">Supervision Areas</div>
                <div class="md-desc">Manage geographical or departmental areas of supervision.</div>
            </a>
        </div>
        <?php endif; ?>

        <?php if(hasPermission('position_requirement.view')): ?>
        <div class="col-md-4 col-sm-6">
            <a href="position_requirements.php" class="md-card p-4">
                <i class="fas fa-clipboard-list md-icon text-secondary"></i>
                <div class="md-title">Position Requirements</div>
                <div class="md-desc">Map mandatory and optional certifications to specific positions.</div>
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
