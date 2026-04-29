<?php
require_once 'config.php';
require_once 'functions.php';

if (!isAdmin()) redirect('login.php');
$pageTitle = 'تسجيل حضور الطلاب (الإدارة)';
require_once 'includes/header.php';

$today = date('Y-m-d');
$message = '';
$message_type = '';

// جلب جميع الطلاب مع معلميهم
$students = $pdo->prepare("
    SELECT s.id, s.name, t.name as teacher_name 
    FROM students s 
    LEFT JOIN teachers t ON s.teacher_id = t.id 
    ORDER BY s.name
");
$students->execute();
$students = $students->fetchAll();

// جلب جميع المعلمين (للفلترة)
$teachers = $pdo->query("SELECT id, name FROM teachers ORDER BY name")->fetchAll();

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_submit'])) {
    $student_id = (int)$_POST['student_id'];
    $status = $_POST['status'];
    $notes = trim($_POST['notes'] ?? '');

    // التحقق من عدم تسجيل مسبق
    $exists = $pdo->prepare("SELECT id FROM attendance WHERE person_type = 'student' AND person_id = ? AND date = ?");
    $exists->execute([$student_id, $today]);
    if ($exists->fetch()) {
        $message = "⚠️ تم تسجيل حضور هذا الطالب مسبقاً اليوم!";
        $message_type = 'warning';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO attendance (person_type, person_id, date, status, notes) VALUES ('student', ?, ?, ?, ?)");
            $stmt->execute([$student_id, $today, $status, $notes]);
            $message = "✅ تم تسجيل حضور الطالب بنجاح";
            $message_type = 'success';
            
            // تفعيل الغياب التلقائي للطلاب الآخرين
            checkAutoAttendance($pdo, 'student', $today);
            
        } catch (PDOException $e) {
            $message = "❌ خطأ في قاعدة البيانات: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// جلب حضور الطلاب المسجل اليوم
$attendance = $pdo->prepare("
    SELECT a.*, s.name as person_name, t.name as teacher_name
    FROM attendance a
    JOIN students s ON a.person_id = s.id
    LEFT JOIN teachers t ON s.teacher_id = t.id
    WHERE a.person_type = 'student' AND a.date = ?
    ORDER BY s.name
");
$attendance->execute([$today]);
$todayAttendance = $attendance->fetchAll();
?>

<section class="card" style="max-width: 1000px; margin: 0 auto;">
    <h2 class="card-title"><i class="fas fa-users"></i> تسجيل حضور الطلاب (الإدارة) - <?php echo $today; ?></h2>

    <?php if ($message): ?>
        <div style="background: <?php 
            if($message_type == 'success') echo '#d4edda';
            elseif($message_type == 'warning') echo '#fff3cd';
            else echo '#f8d7da';
        ?>; color: <?php 
            if($message_type == 'success') echo '#155724';
            elseif($message_type == 'warning') echo '#856404';
            else echo '#721c24';
        ?>; padding: 15px; border-radius: 10px; margin-bottom:20px;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <!-- نموذج تسجيل الحضور -->
        <div style="background: #f8f9fa; padding: 20px; border-radius: 15px;">
            <h3 style="color: #1e3c3f; margin-bottom: 15px;">📝 تسجيل حضور جديد</h3>
            <form method="post">
                <div class="form-group">
                    <label>اختر الطالب</label>
                    <select name="student_id" required style="width: 100%; padding: 12px;">
                        <option value="">-- اختر --</option>
                        <?php foreach ($students as $s): ?>
                            <?php
                            // التحقق إذا كان الطالب مسجلاً مسبقاً اليوم
                            $already = $pdo->prepare("SELECT id FROM attendance WHERE person_type='student' AND person_id=? AND date=?");
                            $already->execute([$s['id'], $today]);
                            $disabled = $already->fetch() ? 'disabled' : '';
                            ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo $disabled; ?>>
                                <?php echo htmlspecialchars($s['name']); ?> 
                                (معلم: <?php echo htmlspecialchars($s['teacher_name'] ?? 'غير محدد'); ?>)
                                <?php if ($disabled): ?> [مسجل] <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>الحالة</label>
                    <select name="status" style="width: 100%; padding: 12px;">
                        <option value="present">حاضر ✅</option>
                        <option value="absent">غائب ❌</option>
                        <option value="late">متأخر ⏰</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>ملاحظات (اختياري)</label>
                    <input type="text" name="notes" placeholder="مثال: جاء متأخراً 10 دقائق" style="width: 100%; padding: 12px;">
                </div>
                <input type="hidden" name="student_submit" value="1">
                <button type="submit" style="width: 100%; background: #28a745;"><i class="fas fa-save"></i> تسجيل الحضور</button>
            </form>
        </div>

        <!-- إحصائيات سريعة -->
        <div style="background: #f8f9fa; padding: 20px; border-radius: 15px;">
            <h3 style="color: #1e3c3f; margin-bottom: 15px;">📊 إحصائيات اليوم</h3>
            <?php
            $total = count($students);
            $present = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE person_type='student' AND date=? AND status='present'");
            $present->execute([$today]);
            $present_count = $present->fetchColumn();
            
            $absent = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE person_type='student' AND date=? AND status='absent'");
            $absent->execute([$today]);
            $absent_count = $absent->fetchColumn();
            
            $late = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE person_type='student' AND date=? AND status='late'");
            $late->execute([$today]);
            $late_count = $late->fetchColumn();
            
            $remaining = $total - ($present_count + $absent_count + $late_count);
            ?>
            <ul style="list-style: none; padding: 0;">
                <li style="padding: 8px; background: #d4edda; border-radius: 8px; margin-bottom: 5px;">
                    <strong>إجمالي الطلاب:</strong> <?php echo $total; ?>
                </li>
                <li style="padding: 8px; background: #cce5ff; border-radius: 8px; margin-bottom: 5px;">
                    <strong>✅ حاضر:</strong> <?php echo $present_count; ?>
                </li>
                <li style="padding: 8px; background: #f8d7da; border-radius: 8px; margin-bottom: 5px;">
                    <strong>❌ غائب:</strong> <?php echo $absent_count; ?>
                </li>
                <li style="padding: 8px; background: #fff3cd; border-radius: 8px; margin-bottom: 5px;">
                    <strong>⏰ متأخر:</strong> <?php echo $late_count; ?>
                </li>
                <li style="padding: 8px; background: #e2e3e5; border-radius: 8px; margin-bottom: 5px;">
                    <strong>⌛ لم يسجل بعد:</strong> <?php echo $remaining; ?>
                </li>
            </ul>
        </div>
    </div>

    <hr style="margin: 30px 0;">

    <h3>📋 حضور الطلاب المسجل اليوم</h3>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>الاسم</th>
                    <th>المعلم</th>
                    <th>الحالة</th>
                    <th>ملاحظات</th>
                    <th>وقت التسجيل</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($todayAttendance as $index => $a): ?>
                <tr>
                    <td><?php echo $index+1; ?></td>
                    <td><?php echo htmlspecialchars($a['person_name']); ?></td>
                    <td><?php echo htmlspecialchars($a['teacher_name'] ?? 'غير محدد'); ?></td>
                    <td>
                        <?php 
                        if ($a['status'] == 'present') echo '<span style="color:#27ae60;">✅ حاضر</span>';
                        elseif ($a['status'] == 'absent') echo '<span style="color:#e74c3c;">❌ غائب</span>';
                        else echo '<span style="color:#f39c12;">⏰ متأخر</span>';
                        
                        if ($a['auto_generated']) {
                            echo ' <span style="background: #f0f0f0; padding: 2px 5px; border-radius: 3px; font-size:0.7rem;">تلقائي</span>';
                        }
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars($a['notes'] ?: '-'); ?></td>
                    <td><?php echo date('H:i', strtotime($a['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($todayAttendance)): ?>
                <tr><td colspan="6" style="text-align: center;">لم يسجل أي حضور للطلاب اليوم</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>