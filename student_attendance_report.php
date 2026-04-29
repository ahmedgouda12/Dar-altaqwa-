<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('login.php');
$pageTitle = 'تقرير حضور الطلاب';
require_once 'includes/header.php';

$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$year = substr($month, 0, 4);
$monthNum = substr($month, 5, 2);

// جلب جميع الطلاب مع معلميهم
$students = $pdo->query("
    SELECT s.*, t.name as teacher_name 
    FROM students s 
    LEFT JOIN teachers t ON s.teacher_id = t.id 
    ORDER BY s.name
")->fetchAll();

// عدد أيام الدراسة في الشهر (يمكن تعديله)
$workingDaysInMonth = 22;

// دالة لحساب عدد أيام الحضور حسب الحالة
function getStudentAttendanceCount($pdo, $studentId, $year, $month, $status = 'present') {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM attendance 
        WHERE person_type = 'student' 
        AND person_id = ? 
        AND YEAR(date) = ? 
        AND MONTH(date) = ? 
        AND status = ?
    ");
    $stmt->execute([$studentId, $year, $month, $status]);
    return $stmt->fetchColumn();
}

// دالة لتحديد الفئة (أولاد، بنات، أطفال)
function getCategoryName($category) {
    switch($category) {
        case 'boy': return 'أولاد';
        case 'girl': return 'بنات';
        case 'child': return 'أطفال';
        default: return $category;
    }
}
?>

<section class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
        <h2 class="card-title"><i class="fas fa-users"></i> تقرير حضور الطلاب</h2>
        <form method="get" style="display: flex; gap: 10px;">
            <input type="month" name="month" value="<?php echo $month; ?>" required style="padding: 8px; border-radius: 10px; border: 1px solid #ccc;">
            <button type="submit" class="btn">عرض</button>
        </form>
    </div>

    <?php if (count($students) > 0): ?>
        <!-- عرض البطاقات بدلاً من الجدول -->
        <div style="display: flex; flex-direction: column; gap: 20px;">
            <?php foreach ($students as $index => $s): 
                $present = getStudentAttendanceCount($pdo, $s['id'], $year, $monthNum, 'present');
                $absent = getStudentAttendanceCount($pdo, $s['id'], $year, $monthNum, 'absent');
                $late = getStudentAttendanceCount($pdo, $s['id'], $year, $monthNum, 'late');
                $totalRecorded = $present + $absent + $late;
                
                $attendanceRate = $workingDaysInMonth > 0 ? round(($present / $workingDaysInMonth) * 100, 2) : 0;
                
            ?>
                <div style="background: white; border-radius: 20px; padding: 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); border: 1px solid #eee;">
                    <!-- رأس البطاقة: أيقونة الطالب والفئة -->
                    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                        <div style="width: 50px; height: 50px; border-radius: 50%; background: <?php 
                            if($s['category'] == 'boy') echo '#d4edda';
                            elseif($s['category'] == 'girl') echo '#f8d7da';
                            else echo '#fff3cd';
                        ?>; display: flex; align-items: center; justify-content: center;">
                            <i class="fas <?php 
                                if($s['category'] == 'boy') echo 'fa-child';
                                elseif($s['category'] == 'girl') echo 'fa-female';
                                else echo 'fa-baby';
                            ?>" style="font-size: 24px; color: <?php 
                                if($s['category'] == 'boy') echo '#155724';
                                elseif($s['category'] == 'girl') echo '#721c24';
                                else echo '#856404';
                            ?>;"></i>
                        </div>
                        <div style="flex:1;">
                            <h3 style="margin:0; color:#1e3c3f;"><?php echo htmlspecialchars($s['name']); ?></h3>
                            <div style="display: flex; gap: 10px; font-size: 0.9em; color: #7f8c8d;">
                                <span><i class="fas fa-tag"></i> <?php echo getCategoryName($s['category']); ?></span>
                                <?php if (!empty($s['teacher_name'])): ?>
                                    <span><i class="fas fa-chalkboard-teacher"></i> <?php echo htmlspecialchars($s['teacher_name']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- إحصائيات الحضور -->
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 15px;">
                        <div style="text-align: center;">
                            <div style="background: #27ae60; color: white; border-radius: 10px; padding: 8px;">
                                <div style="font-size: 20px; font-weight: bold;"><?php echo $present; ?></div>
                                <div>حاضر</div>
                            </div>
                        </div>
                        <div style="text-align: center;">
                            <div style="background: #e74c3c; color: white; border-radius: 10px; padding: 8px;">
                                <div style="font-size: 20px; font-weight: bold;"><?php echo $absent; ?></div>
                                <div>غائب</div>
                            </div>
                        </div>
                        <div style="text-align: center;">
                            <div style="background: #f39c12; color: white; border-radius: 10px; padding: 8px;">
                                <div style="font-size: 20px; font-weight: bold;"><?php echo $late; ?></div>
                                <div>متأخر</div>
                            </div>
                        </div>
                    </div>

                    <!-- شريط نسبة الحضور -->
                    <div style="margin-top: 10px;">
                        <div style="display: flex; justify-content: space-between; font-size: 0.9em; margin-bottom: 5px;">
                            <span>نسبة الحضور</span>
                            <span><?php echo $attendanceRate; ?>%</span>
                        </div>
                        <div style="background: #ecf0f1; border-radius: 20px; height: 20px; overflow: hidden;">
                            <div style="background: #27ae60; width: <?php echo $attendanceRate; ?>%; height: 100%;"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-top: 8px;">
                            <span>أيام الغياب</span>
                            <span style="color: #e74c3c;"><?php echo $absent; ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- ملاحظة عن عدد أيام العمل -->
        <p style="margin-top: 20px; color: #7f8c8d; text-align: center;">
            <i class="fas fa-info-circle"></i> عدد أيام العمل المفترضة في الشهر: <?php echo $workingDaysInMonth; ?> يوم (يمكن تعديله).
        </p>
        
    <?php else: ?>
        <p style="text-align: center; padding: 40px; background: #f9f9f9; border-radius: 20px;">
            <i class="fas fa-info-circle" style="font-size: 40px; color: #95a5a6; margin-bottom: 15px; display: block;"></i>
            لا يوجد طلاب مسجلين بعد. <a href="add_student.php">أضف طالب أولاً</a>
        </p>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>