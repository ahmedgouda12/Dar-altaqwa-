<?php
// ============================================
// ملف: special_students.php
// قائمة الطلاب الخاصين - لوحة تحكم الإدارة
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'الطلاب الخاصين - لوحة التحكم';
require_once 'includes/header.php';

// إحصائيات سريعة
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN student_type_id = 1 THEN 1 ELSE 0 END) as onsite_count,
        SUM(CASE WHEN student_type_id = 2 THEN 1 ELSE 0 END) as online_count,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
        COUNT(DISTINCT teacher_id) as teachers_count
    FROM special_students
    WHERE status = 'active'
")->fetch();

// جلب قائمة الطلاب الخاصين
$students = $pdo->query("
    SELECT s.*, t.name as teacher_name, st.name_ar as type_name,
           (SELECT COUNT(*) FROM special_student_reports WHERE student_id = s.id) as reports_count,
           (SELECT COUNT(*) FROM special_notification_logs WHERE student_id = s.id) as notifications_count
    FROM special_students s
    LEFT JOIN teachers t ON s.teacher_id = t.id
    LEFT JOIN special_student_types st ON s.student_type_id = st.id
    ORDER BY s.student_name
")->fetchAll();

// معالجة حذف طالب
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $student_id = (int)$_GET['id'];
    $pdo->prepare("DELETE FROM special_students WHERE id = ?")->execute([$student_id]);
    $_SESSION['success'] = "✅ تم حذف الطالب بنجاح";
    header("Location: special_students.php");
    exit;
}

$success_message = $_SESSION['success'] ?? '';
unset($_SESSION['success']);
?>

<style>
:root {
    --primary: #1e3c3f;
    --primary-light: #2a5f5a;
    --secondary: #c9a96b;
    --success: #28a745;
    --danger: #dc3545;
    --warning: #ffc107;
    --info: #17a2b8;
    --whatsapp: #25d366;
}

.special-students-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    padding: 30px;
    border-radius: 25px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.page-header h1 {
    margin: 0;
    font-size: 2rem;
    display: flex;
    align-items: center;
    gap: 15px;
}

.page-header h1 i {
    color: #c9a96b;
    animation: crownGlow 2s ease-in-out infinite;
}

@keyframes crownGlow {
    0%, 100% { text-shadow: 0 0 5px rgba(201,169,107,0.3); }
    50% { text-shadow: 0 0 20px rgba(201,169,107,0.6); }
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    transition: 0.3s;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, var(--secondary), var(--primary));
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--primary);
}

.stat-label {
    color: #666;
    font-size: 0.9rem;
    margin-top: 5px;
}

.stat-icon {
    font-size: 2rem;
    margin-bottom: 10px;
}

.students-table-container {
    background: white;
    border-radius: 25px;
    padding: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    overflow-x: auto;
}

.students-table {
    width: 100%;
    border-collapse: collapse;
}

.students-table th {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 15px;
    font-weight: 600;
    text-align: center;
}

.students-table td {
    padding: 12px;
    text-align: center;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}

.students-table tr:hover {
    background: #f8f9fa;
}

.student-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin: 0 auto;
}

.type-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.type-onsite { background: #d4edda; color: #155724; }
.type-online { background: #d1ecf1; color: #0c5460; }

.status-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-active { background: #d4edda; color: #155724; }
.status-inactive { background: #f8d7da; color: #721c24; }

.action-buttons {
    display: flex;
    gap: 8px;
    justify-content: center;
    flex-wrap: wrap;
}

.action-btn {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: 0.3s;
    text-decoration: none;
    color: white;
}

.action-btn.edit { background: var(--warning); color: #212529; }
.action-btn.whatsapp { background: var(--whatsapp); }
.action-btn.report { background: var(--info); }
.action-btn.delete { background: var(--danger); }
.action-btn.transfer { background: var(--secondary); }

.action-btn:hover {
    transform: scale(1.1);
    filter: brightness(1.05);
}

.empty-state {
    text-align: center;
    padding: 60px;
    background: white;
    border-radius: 25px;
}

.empty-state i {
    font-size: 5rem;
    color: #dee2e6;
    margin-bottom: 20px;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 12px 25px;
    border-radius: 30px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: 0.3s;
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .students-table-container {
        overflow-x: auto;
    }
    .students-table {
        min-width: 800px;
    }
}
</style>

<section class="special-students-page">
    <div class="page-header">
        <h1><i class="fas fa-crown"></i> الطلاب الخاصين</h1>
        <a href="special_requests.php" class="btn-primary">
            <i class="fas fa-user-plus"></i> طلبات الالتحاق
        </a>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon">👑</div><div class="stat-number"><?php echo $stats['total']; ?></div><div class="stat-label">إجمالي الطلاب</div></div>
        <div class="stat-card"><div class="stat-icon">🏛️</div><div class="stat-number"><?php echo $stats['onsite_count']; ?></div><div class="stat-label">حضوري</div></div>
        <div class="stat-card"><div class="stat-icon">💻</div><div class="stat-number"><?php echo $stats['online_count']; ?></div><div class="stat-label">أونلاين</div></div>
        <div class="stat-card"><div class="stat-icon">✅</div><div class="stat-number"><?php echo $stats['active_count']; ?></div><div class="stat-label">نشط</div></div>
        <div class="stat-card"><div class="stat-icon">👨‍🏫</div><div class="stat-number"><?php echo $stats['teachers_count']; ?></div><div class="stat-label">معلمين</div></div>
    </div>

    <?php if (empty($students)): ?>
        <div class="empty-state"><i class="fas fa-users-slash"></i><h3>لا يوجد طلاب خاصين</h3><p>لم يتم قبول أي طالب خاص بعد</p><a href="special_requests.php" class="btn-primary">عرض طلبات الالتحاق</a></div>
    <?php else: ?>
        <div class="students-table-container">
            <table class="students-table">
                <thead>
                    <tr><th>#</th><th>الطالب</th><th>النوع</th><th>المعلم</th><th>الموعد</th><th>التقارير</th><th>الحالة</th><th>الإجراءات</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $index => $s): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><div style="display: flex; align-items: center; gap: 10px; justify-content: center;"><div class="student-avatar"><?php echo mb_substr($s['student_name'], 0, 1, 'UTF-8'); ?></div><?php echo htmlspecialchars($s['student_name']); ?></div></td>
                        <td><span class="type-badge type-<?php echo $s['student_type_id'] == 1 ? 'onsite' : 'online'; ?>"><?php echo $s['type_name']; ?></span></td>
                        <td><?php echo htmlspecialchars($s['teacher_name'] ?? 'غير محدد'); ?></td>
                        <td><?php echo $s['schedule_time'] ? date('h:i A', strtotime($s['schedule_time'])) : 'غير محدد'; ?></td>
                        <td><?php echo $s['reports_count']; ?></td>
                        <td><span class="status-badge status-<?php echo $s['status']; ?>"><?php echo $s['status'] == 'active' ? 'نشط' : 'غير نشط'; ?></span></td>
                        <td class="action-buttons">
                            <a href="special_edit_student.php?id=<?php echo $s['id']; ?>" class="action-btn edit" title="تعديل"><i class="fas fa-edit"></i></a>
                            <a href="special_send_report.php?student_id=<?php echo $s['id']; ?>" class="action-btn report" title="إرسال تقرير"><i class="fas fa-file-alt"></i></a>
                            <a href="special_whatsapp.php?student_id=<?php echo $s['id']; ?>" class="action-btn whatsapp" title="واتساب"><i class="fab fa-whatsapp"></i></a>
                            <a href="special_transfer_student.php?student_id=<?php echo $s['id']; ?>" class="action-btn transfer" title="نقل"><i class="fas fa-exchange-alt"></i></a>
                            <a href="?delete=1&id=<?php echo $s['id']; ?>" class="action-btn delete" title="حذف" onclick="return confirm('هل أنت متأكد؟')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>