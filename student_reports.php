<?php
// ============================================
// ملف: student_reports.php - عرض تقارير الطالب
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$pageTitle = 'تقاريري';
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : (isStudent() ? $_SESSION['user_id'] : 0);
$error = '';
$student = null;
$reports = [];

// التحقق من الصلاحية
if ($student_id > 0) {
    // جلب معلومات الطالب
    $stmt = $pdo->prepare("SELECT s.*, t.name as teacher_name FROM students s LEFT JOIN teachers t ON s.teacher_id = t.id WHERE s.id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    if (!$student) {
        $error = "الطالب غير موجود";
    } else {
        // التحقق من الصلاحية
        $can_view = false;
        if (isAdmin()) {
            $can_view = true;
        } elseif (isTeacher() && $student['teacher_id'] == $_SESSION['user_id']) {
            $can_view = true;
        } elseif (isStudent() && $_SESSION['user_id'] == $student_id) {
            $can_view = true;
        } elseif (isGuardian()) {
            $guardian_stmt = $pdo->prepare("SELECT id FROM students WHERE id = ? AND guardian_id = ?");
            $guardian_stmt->execute([$student_id, $_SESSION['user_id']]);
            $can_view = $guardian_stmt->fetch();
        }
        
        if (!$can_view) {
            $error = "لا تملك صلاحية عرض تقارير هذا الطالب";
        } else {
            // جلب التقارير
            $stmt = $pdo->prepare("
                SELECT r.*, t.name as teacher_name
                FROM student_reports r
                LEFT JOIN teachers t ON r.teacher_id = t.id
                WHERE r.student_id = ?
                ORDER BY r.report_date DESC, r.created_at DESC
            ");
            $stmt->execute([$student_id]);
            $reports = $stmt->fetchAll();
        }
    }
}

$evaluation_names = [
    1 => 'ممتاز',
    2 => 'جيد جداً',
    3 => 'جيد',
    4 => 'مقبول',
    5 => 'يحتاج لمتابعة'
];

require_once 'includes/header.php';
?>

<style>
    .reports-page {
        max-width: 1000px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .page-header {
        background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
        color: white;
        padding: 30px;
        border-radius: 25px;
        margin-bottom: 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }
    
    .student-info {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .student-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
    }
    
    .stats-summary {
        display: flex;
        gap: 15px;
        background: rgba(255,255,255,0.15);
        padding: 8px 20px;
        border-radius: 30px;
    }
    
    .reports-grid {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    
    .report-card {
        background: white;
        border-radius: 20px;
        padding: 20px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        transition: 0.3s;
        border-right: 5px solid #c9a96b;
    }
    
    .report-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    
    .report-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }
    
    .report-date {
        background: #1e3c3f;
        color: white;
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 0.85rem;
    }
    
    .teacher-name {
        color: #666;
        font-size: 0.85rem;
    }
    
    .evaluation-badge {
        display: inline-block;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    .evaluation-1 { background: #d4edda; color: #155724; }
    .evaluation-2 { background: #d1ecf1; color: #0c5460; }
    .evaluation-3 { background: #fff3cd; color: #856404; }
    .evaluation-4 { background: #f8d7da; color: #721c24; }
    .evaluation-5 { background: #f8d7da; color: #721c24; }
    
    .report-content {
        background: #f8f9fa;
        border-radius: 15px;
        padding: 15px;
        margin-top: 15px;
        white-space: pre-wrap;
        font-family: monospace;
        font-size: 0.9rem;
        line-height: 1.6;
    }
    
    .no-reports {
        text-align: center;
        padding: 60px;
        background: white;
        border-radius: 20px;
    }
    
    .no-reports i {
        font-size: 4rem;
        color: #dee2e6;
        margin-bottom: 15px;
    }
    
    .btn-new-report {
        background: #28a745;
        color: white;
        padding: 10px 25px;
        border-radius: 30px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            text-align: center;
        }
        
        .student-info {
            flex-direction: column;
        }
        
        .report-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<section class="reports-page">
    <div class="page-header">
        <div class="student-info">
            <div class="student-avatar">
                <?php echo $student ? mb_substr($student['name'], 0, 1, 'UTF-8') : '?'; ?>
            </div>
            <div>
                <h2 style="margin: 0;"><?php echo $student ? htmlspecialchars($student['name']) : 'غير معروف'; ?></h2>
                <p style="margin: 5px 0 0; opacity: 0.9;">
                    <i class="fas fa-chalkboard-teacher"></i> 
                    <?php echo $student ? htmlspecialchars($student['teacher_name'] ?? 'غير محدد') : ''; ?>
                </p>
            </div>
        </div>
        <div class="stats-summary">
            <span><i class="fas fa-file-alt"></i> <?php echo count($reports); ?> تقرير</span>
            <?php if (isTeacher() || isAdmin()): ?>
            <a href="send_report.php?student_id=<?php echo $student_id; ?>" class="btn-new-report">
                <i class="fas fa-plus-circle"></i> تقرير جديد
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-error" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 15px;">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php elseif (empty($reports)): ?>
        <div class="no-reports">
            <i class="fas fa-file-alt"></i>
            <h3>لا توجد تقارير بعد</h3>
            <p>لم يتم إضافة أي تقارير لهذا الطالب حتى الآن</p>
            <?php if (isTeacher() || isAdmin()): ?>
            <a href="send_report.php?student_id=<?php echo $student_id; ?>" class="btn-new-report" style="margin-top: 15px; display: inline-block;">
                <i class="fas fa-plus-circle"></i> إضافة أول تقرير
            </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="reports-grid">
            <?php foreach ($reports as $report): ?>
                <div class="report-card">
                    <div class="report-header">
                        <div class="report-date">
                            <i class="fas fa-calendar-alt"></i> <?php echo $report['report_date']; ?>
                        </div>
                        <div class="teacher-name">
                            <i class="fas fa-chalkboard-teacher"></i> <?php echo htmlspecialchars($report['teacher_name'] ?? 'غير محدد'); ?>
                        </div>
                        <div class="evaluation-badge evaluation-<?php echo $report['evaluation_score']; ?>">
                            <i class="fas fa-star"></i> <?php echo $evaluation_names[$report['evaluation_score']] ?? 'مقبول'; ?>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin: 15px 0;">
                        <?php if ($report['new_memorization']): ?>
                        <div style="background: #e8f5e9; padding: 10px; border-radius: 12px;">
                            <strong><i class="fas fa-book-open"></i> حفظ جديد</strong>
                            <p style="margin-top: 5px; font-size: 0.9rem;"><?php echo nl2br(htmlspecialchars($report['new_memorization'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($report['recent_review']): ?>
                        <div style="background: #e3f2fd; padding: 10px; border-radius: 12px;">
                            <strong><i class="fas fa-history"></i> مراجعة قريبة</strong>
                            <p style="margin-top: 5px; font-size: 0.9rem;"><?php echo nl2br(htmlspecialchars($report['recent_review'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($report['old_review']): ?>
                        <div style="background: #fff3e0; padding: 10px; border-radius: 12px;">
                            <strong><i class="fas fa-archive"></i> مراجعة بعيدة</strong>
                            <p style="margin-top: 5px; font-size: 0.9rem;"><?php echo nl2br(htmlspecialchars($report['old_review'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($report['notes']): ?>
                    <div style="background: #f8f9fa; padding: 12px; border-radius: 12px; margin: 10px 0;">
                        <strong><i class="fas fa-sticky-note"></i> ملاحظات:</strong>
                        <p style="margin-top: 5px;"><?php echo nl2br(htmlspecialchars($report['notes'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="report-content">
                        <?php echo nl2br(htmlspecialchars($report['report_content'])); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>