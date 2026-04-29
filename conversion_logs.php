<?php
// ============================================
// ملف: conversion_logs.php
// عرض سجل تحويلات الطلاب
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'سجل تحويلات الطلاب';
require_once 'includes/header.php';

// إنشاء الجدول إذا لم يكن موجوداً
$pdo->exec("
    CREATE TABLE IF NOT EXISTS special_conversion_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        teacher_id INT NOT NULL,
        action ENUM('make', 'remove') DEFAULT 'make',
        notes TEXT,
        performed_by INT NOT NULL,
        performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_student (student_id),
        INDEX idx_teacher (teacher_id)
    )
");

$logs = $pdo->query("
    SELECT cl.*, 
           s.name as student_name,
           t.name as teacher_name,
           CASE WHEN cl.performed_by IN (SELECT id FROM admins) THEN (SELECT username FROM admins WHERE id = cl.performed_by)
                ELSE (SELECT name FROM teachers WHERE id = cl.performed_by)
           END as performed_by_name
    FROM special_conversion_logs cl
    JOIN students s ON cl.student_id = s.id
    LEFT JOIN teachers t ON cl.teacher_id = t.id
    ORDER BY cl.performed_at DESC
    LIMIT 50
")->fetchAll();
?>

<style>
.logs-page { max-width: 1200px; margin: 0 auto; padding: 20px; }
.page-header { background: linear-gradient(135deg, #1e3c3f, #2a5f5a); color: white; padding: 25px; border-radius: 20px; margin-bottom: 25px; }
.page-header h1 { margin: 0; display: flex; align-items: center; gap: 15px; }
table { width: 100%; background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
th { background: #1e3c3f; color: white; padding: 12px; }
td { padding: 10px; border-bottom: 1px solid #eee; text-align: center; }
.action-make { color: #28a745; font-weight: bold; }
.action-remove { color: #dc3545; font-weight: bold; }
.empty-state { text-align: center; padding: 60px; background: white; border-radius: 20px; }
@media (max-width: 768px) { table { display: block; overflow-x: auto; } }
</style>

<section class="logs-page">
    <div class="page-header">
        <h1><i class="fas fa-history"></i> سجل تحويلات الطلاب</h1>
        <p>تسجيل جميع عمليات تحويل الطلاب بين الحالتين (خاص / عادي)</p>
    </div>

    <?php if (empty($logs)): ?>
        <div class="empty-state">
            <i class="fas fa-exchange-alt" style="font-size: 4rem; color: #dee2e6;"></i>
            <h3 style="margin-top: 15px;">لا توجد سجلات تحويل</h3>
            <p>لم يتم تسجيل أي عملية تحويل للطلاب بعد</p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr><th>التاريخ</th><th>الطالب</th><th>المعلم</th><th>الإجراء</th><th>الملاحظات</th><th>تم بواسطة</th></tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo date('Y-m-d H:i', strtotime($log['performed_at'])); ?></td>
                    <td><?php echo htmlspecialchars($log['student_name']); ?></td>
                    <td><?php echo htmlspecialchars($log['teacher_name'] ?? 'غير محدد'); ?></td>
                    <td class="action-<?php echo $log['action']; ?>"><?php echo $log['action'] == 'make' ? '🔄 تحويل إلى خاص' : '⬅️ تحويل إلى عادي'; ?></td>
                    <td><?php echo nl2br(htmlspecialchars($log['notes'] ?? '-')); ?></td>
                    <td><?php echo htmlspecialchars($log['performed_by_name'] ?? 'نظام'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>