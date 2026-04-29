<?php
// ============================================
// ملف: guardian_dashboard.php
// لوحة تحكم ولي الأمر - نسخة مصححة بالكامل
// آخر تحديث: 2026-04-25
// ============================================

require_once 'config.php';
require_once 'functions.php';

// التأكد من أن المستخدم ولي أمر
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'guardian') {
    redirect('guardian_login.php');
}

$pageTitle = 'لوحة تحكم ولي الأمر';
$guardian_id = $_SESSION['user_id'];
$guardian_name = $_SESSION['user_name'] ?? 'ولي أمر';
// جلب رقم الهاتف من قاعدة البيانات بدلاً من الجلسة
$stmt = $pdo->prepare("SELECT phone FROM guardians WHERE id = ?");
$stmt->execute([$guardian_id]);
$guardian_phone = $stmt->fetchColumn() ?: 'غير محدد';

// ============================================
// دوال مساعدة (إذا لم تكن موجودة في functions.php)
// ============================================

if (!function_exists('getGuardianStudents')) {
    function getGuardianStudents($pdo, $guardian_id) {
        $stmt = $pdo->prepare("
            SELECT s.*, t.name as teacher_name
            FROM students s
            LEFT JOIN teachers t ON s.teacher_id = t.id
            WHERE s.guardian_id = ?
            ORDER BY s.name
        ");
        $stmt->execute([$guardian_id]);
        return $stmt->fetchAll();
    }
}

if (!function_exists('getStudentAttendanceStats')) {
    function getStudentAttendanceStats($pdo, $student_id) {
        $current_month = date('Y-m');
        $year = substr($current_month, 0, 4);
        $month = substr($current_month, 5, 2);
        
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
                COUNT(CASE WHEN status = 'absent' AND is_excused = 0 THEN 1 END) as absent_count,
                COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count,
                COUNT(CASE WHEN is_excused = 1 THEN 1 END) as excused_count
            FROM attendance
            WHERE person_type = 'student' 
            AND person_id = ?
            AND YEAR(date) = ? 
            AND MONTH(date) = ?
        ");
        $stmt->execute([$student_id, $year, $month]);
        $result = $stmt->fetch();
        
        return [
            'present' => $result['present_count'] ?? 0,
            'absent' => $result['absent_count'] ?? 0,
            'late' => $result['late_count'] ?? 0,
            'excused' => $result['excused_count'] ?? 0
        ];
    }
}

if (!function_exists('getStudentMemorizedCount')) {
    function getStudentMemorizedCount($pdo, $student_id) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM student_surah_progress 
            WHERE student_id = ? AND completed = 1
        ");
        $stmt->execute([$student_id]);
        return $stmt->fetchColumn() ?: 0;
    }
}

// جلب جميع أبناء ولي الأمر
$students = getGuardianStudents($pdo, $guardian_id);

// إحصائيات إضافية للطلاب
foreach ($students as &$student) {
    $attendance = getStudentAttendanceStats($pdo, $student['id']);
    $student['present_count'] = $attendance['present'];
    $student['absent_count'] = $attendance['absent'];
    $student['late_count'] = $attendance['late'];
    $student['excused_count'] = $attendance['excused'];
    $student['memorized_count'] = getStudentMemorizedCount($pdo, $student['id']);
}

require_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم ولي الأمر - دار التقوى</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .welcome-card {
            background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
            color: white;
            padding: 30px;
            border-radius: 30px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .guardian-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            border: 3px solid #c9a96b;
        }
        .students-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        .student-card {
            background: white;
            border-radius: 25px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: 0.3s;
            border: 1px solid #eee;
        }
        .student-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        .student-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 15px;
            border: 3px solid #c9a96b;
        }
        .student-name {
            font-size: 1.3rem;
            font-weight: 800;
            color: #1e3c3f;
            margin-bottom: 5px;
        }
        .student-teacher {
            color: #666;
            font-size: 0.85rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .stats-badges {
            display: flex;
            gap: 10px;
            margin: 15px 0;
            flex-wrap: wrap;
        }
        .stat-badge {
            background: #f8f9fa;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .stat-badge.present { background: #d4edda; color: #155724; }
        .stat-badge.absent { background: #f8d7da; color: #721c24; }
        .stat-badge.late { background: #fff3cd; color: #856404; }
        .stat-badge.memorized { background: #c9a96b20; color: #1e3c3f; }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .btn {
            flex: 1;
            padding: 10px;
            border-radius: 30px;
            text-decoration: none;
            text-align: center;
            font-weight: 600;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.85rem;
        }
        .btn-primary { background: #1e3c3f; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .btn-whatsapp { background: #25d366; color: white; }
        .btn:hover { transform: translateY(-2px); filter: brightness(1.05); }
        .empty-state {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 30px;
        }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: #1e3c3f;
        }
        @media (max-width: 768px) {
            .students-grid { grid-template-columns: 1fr; }
            .stats-row { grid-template-columns: 1fr; }
            .welcome-card { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <!-- رأس الصفحة -->
    <div class="welcome-card">
        <div class="guardian-avatar">
            <i class="fas fa-user-tie"></i>
        </div>
        <div class="welcome-content">
            <h2>مرحباً، <?php echo htmlspecialchars($guardian_name ?? 'ولي أمر'); ?></h2>
            <p><i class="fas fa-phone"></i> رقم هاتفك: <?php echo htmlspecialchars($guardian_phone ?? 'غير محدد'); ?></p>
            <p><i class="fas fa-users"></i> عدد أبنائك المسجلين: <?php echo count($students); ?></p>
        </div>
        <a href="update_phone.php" class="btn btn-primary" style="background: rgba(255,255,255,0.15);">
            <i class="fas fa-edit"></i> تحديث رقم الهاتف
        </a>
    </div>

    <!-- إحصائيات سريعة -->
    <?php
    $total_present = array_sum(array_column($students, 'present_count'));
    $total_memorized = array_sum(array_column($students, 'memorized_count'));
    $total_absent = array_sum(array_column($students, 'absent_count'));
    ?>
    <div class="stats-row">
        <div class="stat-card"><div class="stat-number"><?php echo count($students); ?></div><div>عدد الأبناء</div></div>
        <div class="stat-card"><div class="stat-number" style="color: #28a745;"><?php echo $total_present; ?></div><div>✅ إجمالي أيام الحضور</div></div>
        <div class="stat-card"><div class="stat-number" style="color: #c9a96b;"><?php echo $total_memorized; ?></div><div>📖 إجمالي السور المحفوظة</div></div>
    </div>

    <?php if (empty($students)): ?>
        <div class="empty-state">
            <i class="fas fa-child" style="font-size: 4rem; color: #dee2e6;"></i>
            <h3>لا يوجد أبناء مسجلين</h3>
            <p>لم يتم ربط أي طالب بحسابك بعد. يرجى التواصل مع الإدارة.</p>
            <a href="enroll.php" class="btn btn-primary" style="margin-top: 15px; display: inline-block; padding: 10px 25px;">
                <i class="fas fa-user-plus"></i> تقديم طالب جديد
            </a>
        </div>
    <?php else: ?>
        <div class="students-grid">
            <?php foreach ($students as $student): ?>
                <div class="student-card">
                    <div class="student-avatar">
                        <?php echo htmlspecialchars(mb_substr($student['name'], 0, 1, 'UTF-8')); ?>
                    </div>
                    <div class="student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                    <div class="student-teacher">
                        <i class="fas fa-chalkboard-teacher"></i>
                        المعلم: <?php echo htmlspecialchars($student['teacher_name'] ?? 'غير محدد'); ?>
                    </div>
                    
                    <div class="stats-badges">
                        <span class="stat-badge present">
                            <i class="fas fa-check-circle"></i> <?php echo $student['present_count']; ?> حضور
                        </span>
                        <span class="stat-badge late">
                            <i class="fas fa-clock"></i> <?php echo $student['late_count']; ?> تأخير
                        </span>
                        <span class="stat-badge absent">
                            <i class="fas fa-times-circle"></i> <?php echo $student['absent_count']; ?> غياب
                        </span>
                        <span class="stat-badge memorized">
                            <i class="fas fa-quran"></i> <?php echo $student['memorized_count']; ?> سورة
                        </span>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="view_progress.php?student_id=<?php echo $student['id']; ?>" class="btn btn-info">
                            <i class="fas fa-chart-line"></i> التقدم
                        </a>
                        <a href="guardian_daily_memorization.php?student_id=<?php echo $student['id']; ?>" class="btn btn-success">
                            <i class="fas fa-pen-alt"></i> تسجيل حفظ
                        </a>
                        <a href="guardian_custom_wirds.php?student_id=<?php echo $student['id']; ?>" class="btn btn-primary">
                           <i class="fas fa-star-and-crescent"></i> أوراد إضافية
                         </a>
                         <a href="guardian_full_worship.php" class="action-btn" style="background: linear-gradient(135deg, #6f42c1, #9b59b6);">
    <i class="fas fa-praying-hands"></i>
    <span>لوحة العبادات</span>
</a>
                      </div>
                        <?php if (!empty($student['parent_phone'])): ?>
                            <?php
                            $whatsapp_phone = formatWhatsAppNumber($student['parent_phone']);
                            if ($whatsapp_phone):
                            ?>
                                <a href="https://wa.me/<?php echo $whatsapp_phone; ?>?text=السلام%20عليكم،%20أود%20متابعة%20الطالب%20<?php echo urlencode($student['name']); ?>" target="_blank" class="btn btn-whatsapp">
                                    <i class="fab fa-whatsapp"></i> تواصل
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>