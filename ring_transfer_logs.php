<?php
// ============================================
// ملف: ring_transfer_logs.php - سجل نقل الحلقات
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isAdmin() && !isTeacher()) {
    redirect('login.php');
}

$pageTitle = 'سجل نقل الحلقات';
require_once 'includes/header.php';

$teacher_id = isTeacher() ? $_SESSION['user_id'] : null;
$logs = getRingTransferLogs($pdo, $teacher_id, 50);
?>

<style>
.logs-page {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    padding: 25px;
    border-radius: 20px;
    margin-bottom: 25px;
}

.log-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    border-right: 5px solid #c9a96b;
}

.log-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    flex-wrap: wrap;
}

.ring-name {
    font-weight: bold;
    color: #1e3c3f;
    font-size: 1.1rem;
}

.transfer-date {
    color: #666;
    font-size: 0.85rem;
}

.transfer-details {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 10px;
    margin: 10px 0;
}

.teacher-transfer {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.arrow-icon {
    color: #c9a96b;
    font-size: 1.2rem;
}

.students-info {
    margin-top: 10px;
    color: #28a745;
    font-size: 0.9rem;
}

.reason {
    margin-top: 10px;
    color: #6c757d;
    font-style: italic;
    font-size: 0.85rem;
}

@media (max-width: 768px) {
    .teacher-transfer {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<section class="logs-page">
    <div class="page-header">
        <h1><i class="fas fa-history"></i> سجل نقل الحلقات</h1>
        <p>سجل جميع عمليات نقل الحلقات بين المعلمين</p>
    </div>

    <?php if (empty($logs)): ?>
        <div class="card" style="text-align: center; padding: 50px;">
            <i class="fas fa-exchange-alt" style="font-size: 4rem; color: #dee2e6;"></i>
            <h3 style="margin-top: 15px;">لا توجد سجلات نقل</h3>
            <p>لم يتم نقل أي حلقة بعد</p>
        </div>
    <?php else: ?>
        <?php foreach ($logs as $log): ?>
            <div class="log-card">
                <div class="log-header">
                    <span class="ring-name">
                        <i class="fas fa-ring"></i> <?php echo htmlspecialchars($log['ring_name']); ?>
                    </span>
                    <span class="transfer-date">
                        <i class="fas fa-calendar-alt"></i> <?php echo $log['transferred_at']; ?>
                    </span>
                </div>
                
                <div class="transfer-details">
                    <div class="teacher-transfer">
                        <span>
                            <i class="fas fa-chalkboard-teacher"></i>
                            <?php echo htmlspecialchars($log['old_teacher_name']); ?>
                        </span>
                        <i class="fas fa-arrow-left arrow-icon"></i>
                        <span>
                            <i class="fas fa-chalkboard-teacher"></i>
                            <?php echo htmlspecialchars($log['new_teacher_name']); ?>
                        </span>
                    </div>
                    
                    <?php if ($log['students_count'] > 0): ?>
                        <div class="students-info">
                            <i class="fas fa-users"></i>
                            <?php echo $log['students_count']; ?> طالب 
                            <?php echo $log['transfer_students'] ? 'تم نقلهم مع الحلقة' : 'لم يتم نقلهم'; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($log['transfer_reason']): ?>
                        <div class="reason">
                            <i class="fas fa-sticky-note"></i>
                            السبب: <?php echo htmlspecialchars($log['transfer_reason']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div style="margin-top: 8px; font-size: 0.8rem; color: #999;">
                        <i class="fas fa-user"></i> تم بواسطة: <?php echo htmlspecialchars($log['transferred_by_name'] ?? 'نظام'); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>