<?php
// ============================================
// ملف: teacher_students_list.php - توزيع الطلاب حسب المعلم
// آخر تحديث: 2026-04-18
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'توزيع الطلاب حسب المعلم';
$message = '';
$message_type = '';

// جلب جميع المعلمين مع إحصائيات عدد الطلاب
$teachers = $pdo->query("
    SELECT t.*, COUNT(s.id) as student_count
    FROM teachers t
    LEFT JOIN students s ON t.id = s.teacher_id
    GROUP BY t.id
    ORDER BY t.name
")->fetchAll();

// إذا تم اختيار معلم معين
$selected_teacher = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;
$teacher_name = '';
$students = [];

if ($selected_teacher) {
    $students = $pdo->prepare("
        SELECT s.*
        FROM students s
        WHERE s.teacher_id = ?
        ORDER BY s.name
    ");
    $students->execute([$selected_teacher]);
    $students = $students->fetchAll();
    
    // جلب اسم المعلم
    $teacher_name_stmt = $pdo->prepare("SELECT name FROM teachers WHERE id = ?");
    $teacher_name_stmt->execute([$selected_teacher]);
    $teacher_name = $teacher_name_stmt->fetchColumn();
}

require_once 'includes/header.php';
?>

<style>
/* ===== تصميم صفحة توزيع الطلاب ===== */
.teachers-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    padding: 25px;
    border-radius: 20px;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.page-header h1 {
    margin: 0;
    font-size: 1.5rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* ===== بطاقات المعلمين ===== */
.teachers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.teacher-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    transition: 0.3s;
    cursor: pointer;
    border: 2px solid transparent;
    text-align: center;
}

.teacher-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.15);
    border-color: #c9a96b;
}

.teacher-card.selected {
    border-color: #28a745;
    background: #f8f9fa;
}

.teacher-avatar {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    margin: 0 auto 15px;
}

.teacher-name {
    font-size: 1.2rem;
    font-weight: bold;
    color: #1e3c3f;
    margin-bottom: 5px;
}

.teacher-stats {
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 15px;
}

.student-count {
    display: inline-block;
    background: #28a745;
    color: white;
    padding: 5px 15px;
    border-radius: 30px;
    font-size: 0.9rem;
}

/* ===== قسم الطلاب ===== */
.back-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 20px;
    color: #1e3c3f;
    text-decoration: none;
    font-weight: 600;
    padding: 8px 20px;
    background: #f8f9fa;
    border-radius: 30px;
    transition: 0.3s;
}

.back-btn:hover {
    background: #e9ecef;
    transform: translateX(-5px);
}

.teacher-header-card {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    padding: 20px;
    border-radius: 20px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.teacher-header-card h2 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.export-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.export-btn {
    padding: 8px 20px;
    border-radius: 30px;
    color: white;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    transition: 0.3s;
    border: none;
    cursor: pointer;
}

.export-btn.excel {
    background: #28a745;
}

.export-btn.pdf {
    background: #dc3545;
}

.export-btn.print {
    background: #6c757d;
}

.export-btn:hover {
    transform: translateY(-2px);
    filter: brightness(1.05);
}

/* ===== جدول الطلاب ===== */
.table-container {
    background: white;
    border-radius: 20px;
    overflow-x: auto;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 600px;
}

th {
    background: #1e3c3f;
    color: white;
    padding: 12px;
    text-align: center;
    font-weight: 600;
}

td {
    padding: 12px;
    text-align: center;
    border-bottom: 1px solid #e9ecef;
}

tr:hover {
    background: #f8f9fa;
}

.default-pass {
    background: #fff3cd;
    color: #856404;
    padding: 3px 12px;
    border-radius: 30px;
    font-size: 0.85rem;
    display: inline-block;
}

/* ===== حالة فارغة ===== */
.empty-state {
    text-align: center;
    padding: 60px;
    background: white;
    border-radius: 20px;
}

.empty-state i {
    font-size: 4rem;
    color: #dee2e6;
    margin-bottom: 15px;
}

/* ===== تنبيهات ===== */
.alert {
    padding: 15px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-right: 5px solid #28a745;
}

/* ===== تحسينات للطباعة ===== */
@media print {
    .no-print {
        display: none !important;
    }
    
    body {
        background: white;
        padding: 0;
        margin: 0;
    }
    
    .teachers-page {
        padding: 0;
    }
    
    .page-header {
        background: #1e3c3f;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .teacher-header-card {
        background: #1e3c3f;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    th {
        background: #1e3c3f;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .default-pass {
        background: #fff3cd;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    a, button {
        display: none !important;
    }
}

/* ===== تحسينات للهاتف ===== */
@media (max-width: 768px) {
    .teachers-page {
        padding: 15px;
    }
    
    .teachers-grid {
        grid-template-columns: 1fr;
    }
    
    .page-header {
        flex-direction: column;
        text-align: center;
    }
    
    .export-buttons {
        width: 100%;
        justify-content: center;
    }
    
    .teacher-header-card {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<section class="teachers-page">
    <div class="page-header">
        <h1><i class="fas fa-users"></i> توزيع الطلاب حسب المعلم</h1>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if (!$selected_teacher): ?>
        <!-- عرض بطاقات المعلمين -->
        <div class="teachers-grid">
            <?php foreach ($teachers as $teacher): ?>
                <div class="teacher-card" onclick="window.location.href='?teacher_id=<?php echo $teacher['id']; ?>'">
                    <div class="teacher-avatar">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="teacher-name"><?php echo htmlspecialchars($teacher['name']); ?></div>
                    <div class="teacher-stats">
                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($teacher['phone'] ?? 'لا يوجد'); ?>
                    </div>
                    <div class="student-count">
                        <i class="fas fa-users"></i> <?php echo $teacher['student_count']; ?> طالب
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($teachers)): ?>
            <div class="empty-state">
                <i class="fas fa-chalkboard-teacher"></i>
                <h3>لا يوجد معلمين</h3>
                <p>قم بإضافة معلمين أولاً</p>
                <a href="add_teacher.php" class="export-btn excel" style="display: inline-block; background: #1e3c3f; text-decoration: none;">إضافة معلم</a>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- عرض طلاب معلم معين -->
        <a href="?" class="back-btn no-print"><i class="fas fa-arrow-right"></i> العودة إلى قائمة المعلمين</a>
        
        <div class="teacher-header-card">
            <h2>
                <i class="fas fa-chalkboard-teacher"></i> 
                طلاب المعلم: <?php echo htmlspecialchars($teacher_name); ?>
            </h2>
            <div class="export-buttons no-print">
                <button class="export-btn print" onclick="window.print()">
                    <i class="fas fa-print"></i> طباعة
                </button>
                <a href="export_teacher_students.php?teacher_id=<?php echo $selected_teacher; ?>" class="export-btn excel">
                    <i class="fas fa-file-excel"></i> تصدير Excel
                </a>
            </div>
        </div>

        <?php if (empty($students)): ?>
            <div class="empty-state">
                <i class="fas fa-users-slash"></i>
                <h3>لا يوجد طلاب لهذا المعلم</h3>
                <p>لم يتم إضافة أي طلاب لهذا المعلم بعد</p>
                <a href="add_student.php" class="export-btn excel" style="display: inline-block; background: #1e3c3f; text-decoration: none;">إضافة طالب</a>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم الطالب</th>
                            <th>ولي الأمر (رقم الهاتف)</th>
                            <th>المستوى</th>
                            <th>اسم المستخدم</th>
                            <th>كلمة المرور</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $index => $s): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($s['name']); ?></td>
                            <td dir="ltr"><?php echo htmlspecialchars($s['parent_phone'] ?? 'لا يوجد'); ?></td>
                            <td><?php echo htmlspecialchars($s['level'] ?? 'مبتدئ'); ?></td>
                            <td><?php echo htmlspecialchars($s['username'] ?? 'لم يُنشأ'); ?></td>
                            <td><span class="default-pass">123456</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 15px; font-size: 0.85rem; text-align: center; color: #666;">
                <i class="fas fa-info-circle"></i> 
                تم إنشاء هذا التقرير بتاريخ <?php echo date('Y-m-d H:i'); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>