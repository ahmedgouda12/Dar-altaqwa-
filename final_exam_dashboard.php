<?php
// ============================================
// ملف: final_exam_dashboard.php
// لوحة تحكم الاختبارات الذكية
// آخر تحديث: 2026-03-16
// ============================================
ob_start(); // ← أضف هذا السطر في أول الملف
require_once 'config.php';
require_once 'functions.php';
require_once 'hijri_date.php';

if (!isTeacher() && !isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'الاختبارات النهائية الذكية';
require_once 'includes/header.php';

$teacher_id = isTeacher() ? $_SESSION['user_id'] : null;
$today = date('Y-m-d');

// ============================================
// معالجة بدء اختبار جديد
// ============================================
if (isset($_GET['start_exam']) && isset($_GET['student_id']) && isset($_GET['total_parts'])) {
    $student_id = (int)$_GET['student_id'];
    $total_parts = (int)$_GET['total_parts'];
    
    // التحقق من عدم وجود اختبار نشط
    $check = $pdo->prepare("
        SELECT id FROM final_student_exams 
        WHERE student_id = ? AND status = 'in_progress'
    ");
    $check->execute([$student_id]);
    
    if (!$check->fetch()) {
        // إدراج الاختبار الجديد
        $stmt = $pdo->prepare("
            INSERT INTO final_student_exams 
            (student_id, teacher_id, total_parts, start_date, status) 
            VALUES (?, ?, ?, CURDATE(), 'in_progress')
        ");
        $stmt->execute([$student_id, $teacher_id, $total_parts]);
        $exam_id = $pdo->lastInsertId();
        
        $_SESSION['success'] = "✅ تم بدء اختبار جديد للطالب";
        header("Location: memorization_session.php?exam_id=$exam_id&part=1");
        exit;
    } else {
        $_SESSION['error'] = "❌ يوجد اختبار نشط بالفعل لهذا الطالب";
        header("Location: final_exam_dashboard.php");
        exit;
    }
}

// ============================================
// معالجة إنهاء الاختبار يدوياً
// ============================================
if (isset($_GET['complete_exam']) && isset($_GET['exam_id'])) {
    $exam_id = (int)$_GET['exam_id'];
    
    // جلب بيانات الاختبار
    $exam = $pdo->prepare("
        SELECT * FROM final_student_exams WHERE id = ?
    ");
    $exam->execute([$exam_id]);
    $exam_data = $exam->fetch();
    
    if ($exam_data && $exam_data['status'] == 'in_progress') {
        // حساب المعدل النهائي
        $scores = $pdo->prepare("
            SELECT AVG(session_score) as avg_score 
            FROM memorization_sessions 
            WHERE student_exam_id = ?
        ");
        $scores->execute([$exam_id]);
        $avg_score = $scores->fetchColumn();
        
        // تحديث حالة الاختبار
        $update = $pdo->prepare("
            UPDATE final_student_exams 
            SET status = 'completed', 
                end_date = CURDATE(), 
                final_average = ?,
                completed_parts = total_parts
            WHERE id = ?
        ");
        $update->execute([$avg_score, $exam_id]);
        
        // إنشاء نتيجة الاختبار
        $grade = getGrade($avg_score);
        
        $result = $pdo->prepare("
            INSERT INTO exam_results 
            (student_exam_id, student_id, total_parts, average_score, grade, completed_at)
            VALUES (?, ?, ?, ?, ?, CURDATE())
        ");
        $result->execute([
            $exam_id, 
            $exam_data['student_id'], 
            $exam_data['total_parts'], 
            $avg_score, 
            $grade
        ]);
        
        $_SESSION['success'] = "✅ تم إنهاء الاختبار بنجاح";
    }
    
    header("Location: final_exam_dashboard.php");
    exit;
}

// دالة تقدير الدرجة
function getGrade($score) {
    if ($score >= 95) return 'ممتاز مع مرتبة الشرف';
    if ($score >= 85) return 'ممتاز';
    if ($score >= 75) return 'جيد جداً';
    if ($score >= 65) return 'جيد';
    if ($score >= 50) return 'مقبول';
    return 'ضعيف';
}

// جلب طلاب المعلم
$students = $pdo->prepare("
    SELECT s.id, s.name, s.level, s.parent_phone,
           (SELECT id FROM final_student_exams 
            WHERE student_id = s.id AND status = 'in_progress') as active_exam_id,
           (SELECT COUNT(*) FROM exam_results WHERE student_id = s.id) as exams_count
    FROM students s
    WHERE s.teacher_id = ?
    ORDER BY s.name
");
$students->execute([$teacher_id]);
$students = $students->fetchAll();

// جلب الاختبارات النشطة
$active_exams = $pdo->prepare("
    SELECT e.*, s.name as student_name,
           (SELECT COUNT(*) FROM memorization_sessions WHERE student_exam_id = e.id) as sessions_done,
           (SELECT AVG(session_score) FROM memorization_sessions WHERE student_exam_id = e.id) as current_average
    FROM final_student_exams e
    JOIN students s ON e.student_id = s.id
    WHERE e.teacher_id = ? AND e.status = 'in_progress'
    ORDER BY e.start_date
");
$active_exams->execute([$teacher_id]);
$active_exams = $active_exams->fetchAll();

// جلب آخر 10 اختبارات مكتملة
$completed_exams = $pdo->prepare("
    SELECT e.*, s.name as student_name,
           (SELECT COUNT(*) FROM memorization_sessions WHERE student_exam_id = e.id) as sessions_done,
           r.grade, r.completed_at as result_date
    FROM final_student_exams e
    JOIN students s ON e.student_id = s.id
    LEFT JOIN exam_results r ON e.id = r.student_exam_id
    WHERE e.teacher_id = ? AND e.status = 'completed'
    ORDER BY e.end_date DESC
    LIMIT 10
");
$completed_exams->execute([$teacher_id]);
$completed_exams = $completed_exams->fetchAll();

// إحصائيات سريعة
$stats = [
    'active' => count($active_exams),
    'completed' => (function() use ($pdo, $teacher_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM final_student_exams WHERE teacher_id = ? AND status = 'completed'");
    $stmt->execute([$teacher_id]);
    return $stmt->fetchColumn();
})(),
    'students_with_exams' => count(array_filter($students, fn($s) => $s['exams_count'] > 0))
];

// عرض الرسائل
$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<style>
:root {
    --primary: #1e3c3f;
    --primary-light: #2a5f5a;
    --secondary: #c9a96b;
    --success: #28a745;
    --success-light: #d4edda;
    --danger: #dc3545;
    --warning: #ffc107;
    --info: #17a2b8;
    --info-light: #d1ecf1;
    --dark: #2c3e50;
    --light: #f8f9fa;
}

* { margin: 0; padding: 0; box-sizing: border-box; }

.exam-dashboard {
    max-width: 1200px;
    margin: 0 auto;
    padding: 15px;
}

/* ===== رأس الصفحة ===== */
.page-header {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    padding: 25px 30px;
    border-radius: 30px;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
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
    animation: starPulse 2s infinite;
}

@keyframes starPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.2); }
}

.header-stats {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.stat-badge {
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(5px);
    padding: 8px 20px;
    border-radius: 40px;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 8px;
    border: 1px solid rgba(255,255,255,0.2);
}

.stat-badge i {
    color: #c9a96b;
}

/* ===== إحصائيات سريعة ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    transition: 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin: 0 auto 10px;
}

.stat-number {
    font-size: 2rem;
    font-weight: 800;
    color: #1e3c3f;
}

.stat-label {
    color: #666;
    font-size: 0.9rem;
}

/* ===== قسم الاختبارات النشطة ===== */
.section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 30px 0 20px;
    color: #1e3c3f;
    border-bottom: 2px solid #c9a96b;
    padding-bottom: 10px;
}

.section-title i {
    font-size: 1.5rem;
    color: #c9a96b;
}

.exam-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    border: 1px solid #eee;
    position: relative;
    overflow: hidden;
    transition: 0.3s;
}

.exam-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.exam-card.active {
    border-right: 8px solid #28a745;
    background: linear-gradient(to left, #f8fff8, white);
}

.exam-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    flex-wrap: wrap;
    gap: 10px;
}

.student-name {
    font-size: 1.3rem;
    font-weight: 700;
    color: #1e3c3f;
    display: flex;
    align-items: center;
    gap: 10px;
}

.student-name i {
    color: #c9a96b;
}

.progress-badge {
    background: #28a745;
    color: white;
    padding: 5px 15px;
    border-radius: 30px;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 5px;
}

.progress-badge.warning {
    background: #ffc107;
    color: #212529;
}

.progress-bar-container {
    margin: 15px 0;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
    color: #666;
    font-size: 0.9rem;
}

.progress-bar {
    height: 20px;
    background: #e9ecef;
    border-radius: 30px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #28a745, #20c997);
    border-radius: 30px;
    transition: width 0.5s;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-right: 10px;
    color: white;
    font-size: 0.8rem;
    font-weight: bold;
}

.score-preview {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 15px;
    margin: 15px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.score-value {
    font-size: 1.8rem;
    font-weight: 800;
    color: #28a745;
}

.action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    flex-wrap: wrap;
}

.btn {
    padding: 12px 25px;
    border: none;
    border-radius: 40px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 0.95rem;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.btn-primary {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
}

.btn-success {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
}

.btn-warning {
    background: #ffc107;
    color: #212529;
}

.btn-info {
    background: linear-gradient(135deg, #17a2b8, #138496);
    color: white;
}

.btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

/* ===== قائمة الطلاب ===== */
.student-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.student-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    border: 1px solid #eee;
    transition: 0.3s;
    position: relative;
}

.student-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
}

.student-card.active-exam {
    border-right: 6px solid #28a745;
}

.student-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    font-weight: bold;
    margin-bottom: 10px;
    border: 3px solid #c9a96b;
}

.student-info {
    text-align: center;
}

.student-name {
    font-size: 1.2rem;
    font-weight: 700;
    color: #1e3c3f;
    margin-bottom: 5px;
}

.student-level {
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 15px;
}

.exam-count {
    background: #f8f9fa;
    padding: 8px;
    border-radius: 30px;
    font-size: 0.85rem;
    color: #666;
    margin-bottom: 15px;
}

/* ===== نافذة منبثقة ===== */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(5px);
}

.modal.show {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 30px;
    padding: 35px;
    width: 90%;
    max-width: 400px;
    animation: modalSlide 0.3s;
}

@keyframes modalSlide {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.modal-header {
    margin-bottom: 20px;
    text-align: center;
}

.modal-header i {
    font-size: 3rem;
    color: #28a745;
    margin-bottom: 10px;
}

.modal-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

/* ===== تحسينات للهاتف ===== */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .student-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
    
    .page-header {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<section class="exam-dashboard">
    <!-- رأس الصفحة -->
    <div class="page-header">
        <h1>
            <i class="fas fa-graduation-cap"></i>
            الاختبارات النهائية الذكية
        </h1>
        <div class="header-stats">
            <span class="stat-badge">
                <i class="fas fa-play-circle"></i> <?php echo $stats['active']; ?> نشط
            </span>
            <span class="stat-badge">
                <i class="fas fa-check-circle"></i> <?php echo $stats['completed']; ?> مكتمل
            </span>
        </div>
    </div>

    <!-- رسائل التنبيه -->
    <?php if ($success_message): ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 15px; margin-bottom: 20px;">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 15px; margin-bottom: 20px;">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- إحصائيات سريعة -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-number"><?php echo count($students); ?></div>
            <div class="stat-label">إجمالي الطلاب</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-play-circle"></i></div>
            <div class="stat-number"><?php echo $stats['active']; ?></div>
            <div class="stat-label">اختبارات نشطة</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-double"></i></div>
            <div class="stat-number"><?php echo $stats['completed']; ?></div>
            <div class="stat-label">اختبارات مكتملة</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-star"></i></div>
            <div class="stat-number"><?php echo $stats['students_with_exams']; ?></div>
            <div class="stat-label">طلاب مختبرين</div>
        </div>
    </div>

    <!-- الاختبارات النشطة -->
    <?php if (!empty($active_exams)): ?>
        <div class="section-title">
            <i class="fas fa-play-circle" style="color: #28a745;"></i>
            <h2>اختبارات جارية (<?php echo count($active_exams); ?>)</h2>
        </div>
        
        <?php foreach ($active_exams as $exam): 
            $progress = ($exam['sessions_done'] / $exam['total_parts']) * 100;
            $next_part = $exam['sessions_done'] + 1;
            $is_last_part = ($next_part > $exam['total_parts']);
        ?>
            <div class="exam-card active">
                <div class="exam-header">
                    <div class="student-name">
                        <i class="fas fa-user-graduate"></i>
                        <?php echo htmlspecialchars($exam['student_name']); ?>
                    </div>
                    <div class="progress-badge">
                        <i class="fas fa-layer-group"></i>
                        <?php echo $exam['sessions_done']; ?>/<?php echo $exam['total_parts']; ?> أجزاء
                    </div>
                </div>
                
                <div class="progress-bar-container">
                    <div class="progress-info">
                        <span>تقدم الاختبار</span>
                        <span><?php echo round($progress); ?>%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $progress; ?>%;">
                            <?php if ($progress > 30): ?><?php echo round($progress); ?>%<?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($exam['current_average']): ?>
                    <div class="score-preview">
                        <i class="fas fa-star" style="color: #ffc107; font-size: 1.5rem;"></i>
                        <div>
                            <span style="color: #666;">المعدل الحالي:</span>
                            <span class="score-value"><?php echo round($exam['current_average'], 2); ?>%</span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="action-buttons">
                    <?php if (!$is_last_part): ?>
                        <a href="memorization_session.php?exam_id=<?php echo $exam['id']; ?>&part=<?php echo $next_part; ?>" 
                           class="btn btn-primary" style="flex: 2;">
                            <i class="fas fa-microphone-alt"></i> تسجيل الجزء <?php echo $next_part; ?>
                        </a>
                    <?php endif; ?>
                    
                    <a href="?complete_exam=1&exam_id=<?php echo $exam['id']; ?>" 
                       class="btn btn-success" style="flex: 1;" 
                       onclick="return confirm('إنهاء الاختبار الآن؟')">
                        <i class="fas fa-check-double"></i> إنهاء
                    </a>
                </div>
                
                <?php if ($is_last_part): ?>
                    <div style="margin-top: 10px; background: #fff3cd; padding: 10px; border-radius: 10px; color: #856404;">
                        <i class="fas fa-info-circle"></i>
                        تم إكمال جميع الأجزاء! اضغط "إنهاء" لإنهاء الاختبار.
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- قائمة الطلاب -->
    <div class="section-title">
        <i class="fas fa-users"></i>
        <h2>طلابي</h2>
    </div>

    <?php if (empty($students)): ?>
        <div style="text-align: center; padding: 60px; background: white; border-radius: 20px;">
            <i class="fas fa-users-slash" style="font-size: 4rem; color: #dee2e6;"></i>
            <h3 style="margin-top: 15px;">لا يوجد طلاب</h3>
            <p style="color: #666;">لم تقم بإضافة أي طلاب بعد</p>
 <a href="add_student.php" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> إضافة طالب
            </a>
        </div>
    <?php else: ?>
        <div class="student-grid">
            <?php foreach ($students as $student): 
                $has_active = !empty($student['active_exam_id']);
            ?>
                <div class="student-card <?php echo $has_active ? 'active-exam' : ''; ?>">
                    <div style="text-align: center;">
                        <div class="student-avatar" style="margin: 0 auto 10px;">
                            <?php echo mb_substr($student['name'], 0, 1, 'UTF-8'); ?>
                        </div>
                        <div class="student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                        <div class="student-level">
                            <i class="fas fa-level-up-alt" style="color: #c9a96b;"></i>
                            <?php echo htmlspecialchars($student['level'] ?: 'مبتدئ'); ?>
                        </div>
                        
                        <?php if ($student['exams_count'] > 0): ?>
                            <div class="exam-count">
                                <i class="fas fa-history"></i>
                                عدد الاختبارات السابقة: <?php echo $student['exams_count']; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($has_active): ?>
                            <div style="margin: 10px 0; color: #28a745;">
                                <i class="fas fa-play-circle"></i> اختبار نشط
                            </div>
                            <a href="memorization_session.php?exam_id=<?php echo $student['active_exam_id']; ?>" 
                               class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-arrow-left"></i> متابعة الاختبار
                            </a>
                        <?php else: ?>
                            <button class="btn btn-success" style="width: 100%;" 
                                    onclick="openStartExamModal(<?php echo $student['id']; ?>, '<?php echo addslashes($student['name']); ?>')">
                                <i class="fas fa-play"></i> بدء اختبار جديد
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- آخر الاختبارات المكتملة -->
    <?php if (!empty($completed_exams)): ?>
        <div class="section-title" style="margin-top: 40px;">
            <i class="fas fa-history"></i>
            <h2>آخر الاختبارات المكتملة</h2>
        </div>
        
        <?php foreach ($completed_exams as $exam): ?>
            <div class="exam-card" style="background: #f8f9fa;">
                <div class="exam-header">
                    <div class="student-name">
                        <i class="fas fa-user-graduate"></i>
                        <?php echo htmlspecialchars($exam['student_name']); ?>
                    </div>
                    <div style="background: #28a745; color: white; padding: 5px 15px; border-radius: 30px;">
                        <i class="fas fa-star"></i> <?php echo $exam['grade'] ?? 'ممتاز'; ?>
                    </div>
                </div>
                <div style="display: flex; justify-content: space-between; color: #666;">
                    <span><i class="fas fa-layer-group"></i> <?php echo $exam['sessions_done']; ?>/<?php echo $exam['total_parts']; ?> أجزاء</span>
                    <span><i class="fas fa-calendar"></i> <?php echo $exam['result_date'] ?? $exam['end_date']; ?></span>
                </div>
                <?php if ($exam['final_average']): ?>
                    <div style="margin-top: 10px; font-size: 1.2rem; color: #1e3c3f;">
                        <strong>المعدل: <?php echo round($exam['final_average'], 2); ?>%</strong>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- نافذة بدء اختبار جديد -->
    <div class="modal" id="startExamModal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-graduation-cap"></i>
                <h3 style="color: #1e3c3f;">بدء اختبار جديد</h3>
            </div>
            
            <p id="modalStudentName" style="text-align: center; font-size: 1.2rem; margin-bottom: 20px;"></p>
            
            <form method="get">
                <input type="hidden" name="start_exam" value="1">
                <input type="hidden" name="student_id" id="modalStudentId">
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #1e3c3f;">
                        عدد الأجزاء المطلوبة
                    </label>
                    <select name="total_parts" required style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 15px;">
                        <?php for ($i = 1; $i <= 30; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?> أجزاء</option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-warning" onclick="closeStartExamModal()" style="flex: 1;">
                        إلغاء
                    </button>
                    <button type="submit" class="btn btn-success" style="flex: 2;">
                        بدء الاختبار
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
function openStartExamModal(studentId, studentName) {
    document.getElementById('modalStudentId').value = studentId;
    document.getElementById('modalStudentName').innerHTML = '<i class="fas fa-user-graduate"></i> ' + studentName;
    document.getElementById('startExamModal').classList.add('show');
}

function closeStartExamModal() {
    document.getElementById('startExamModal').classList.remove('show');
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        closeStartExamModal();
    }
}
</script>

<?php 
ob_end_flush(); // ← أضف هذا السطر
require_once 'includes/footer.php'; 
?>