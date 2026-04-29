<?php
// ============================================
// ملف: daily_commitment_report.php
// تقرير التزام الطلاب بالحفظ اليومي
// آخر تحديث: 2026-03-17
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isTeacher() && !isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'تقرير الالتزام اليومي';
require_once 'includes/header.php';

$teacher_id = isTeacher() ? $_SESSION['user_id'] : null;
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selected_student = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$period = isset($_GET['period']) ? $_GET['period'] : '30'; // 7, 30, 90 يوم

// جلب إحصائيات الالتزام
if (isTeacher()) {
    $stats = $pdo->prepare("
        SELECT 
            s.id,
            s.name,
            s.level,
            sc.preferred_time,
            COUNT(dm.id) as total_recordings,
            SUM(CASE WHEN dm.is_on_time = 1 THEN 1 ELSE 0 END) as on_time_count,
            SUM(CASE WHEN dm.is_on_time = 0 THEN 1 ELSE 0 END) as late_count,
            AVG(CASE WHEN dm.is_on_time = 0 THEN dm.delay_minutes ELSE NULL END) as avg_delay,
            MAX(dm.memorized_date) as last_memorized,
            COUNT(DISTINCT dm.memorized_date) as active_days
        FROM students s
        LEFT JOIN student_daily_schedule sc ON s.id = sc.student_id
        LEFT JOIN daily_memorization dm ON s.id = dm.student_id 
            AND dm.memorized_date >= DATE_SUB(?, INTERVAL ? DAY)
        WHERE s.teacher_id = ?
        GROUP BY s.id
        ORDER BY on_time_count DESC, total_recordings DESC
    ");
    $stats->execute([$selected_date, $period, $teacher_id]);
    $students_stats = $stats->fetchAll();
} else {
    $stats = $pdo->prepare("
        SELECT 
            s.id,
            s.name,
            s.level,
            t.name as teacher_name,
            sc.preferred_time,
            COUNT(dm.id) as total_recordings,
            SUM(CASE WHEN dm.is_on_time = 1 THEN 1 ELSE 0 END) as on_time_count,
            SUM(CASE WHEN dm.is_on_time = 0 THEN 1 ELSE 0 END) as late_count,
            AVG(CASE WHEN dm.is_on_time = 0 THEN dm.delay_minutes ELSE NULL END) as avg_delay,
            MAX(dm.memorized_date) as last_memorized,
            COUNT(DISTINCT dm.memorized_date) as active_days
        FROM students s
        LEFT JOIN teachers t ON s.teacher_id = t.id
        LEFT JOIN student_daily_schedule sc ON s.id = sc.student_id
        LEFT JOIN daily_memorization dm ON s.id = dm.student_id 
            AND dm.memorized_date >= DATE_SUB(?, INTERVAL ? DAY)
        GROUP BY s.id
        ORDER BY on_time_count DESC, total_recordings DESC
    ");
    $stats->execute([$selected_date, $period]);
    $students_stats = $stats->fetchAll();
}

// جلب تفاصيل الطالب المحدد
$student_details = [];
if ($selected_student > 0) {
    $details = $pdo->prepare("
        SELECT dm.*, getSurahName(dm.surah_number) as surah_name
        FROM daily_memorization dm
        WHERE dm.student_id = ? AND dm.memorized_date >= DATE_SUB(?, INTERVAL ? DAY)
        ORDER BY dm.memorized_date DESC, dm.memorized_time DESC
    ");
    $details->execute([$selected_student, $selected_date, $period]);
    $student_details = $details->fetchAll();
    
    // جلب اسم الطالب
    $student_name = $pdo->prepare("SELECT name FROM students WHERE id = ?");
    $student_name->execute([$selected_student]);
    $student_name = $student_name->fetchColumn();
}
?>

<style>
:root {
    --primary: #1e3c3f;
    --primary-light: #2a5f5a;
    --secondary: #c9a96b;
    --secondary-light: #dbb87c;
    --success: #28a745;
    --success-light: #d4edda;
    --danger: #dc3545;
    --danger-light: #f8d7da;
    --warning: #ffc107;
    --warning-light: #fff3cd;
    --info: #17a2b8;
    --info-light: #d1ecf1;
}

.report-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

/* ===== رأس الصفحة ===== */
.page-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 25px 30px;
    border-radius: 30px;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.page-header h1 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 15px;
}

.page-header h1 i {
    color: var(--secondary);
}

/* ===== أدوات التصفية ===== */
.filter-section {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.filter-group {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-select {
    padding: 10px 20px;
    border: 2px solid #e9ecef;
    border-radius: 30px;
    font-size: 0.95rem;
    min-width: 150px;
}

.filter-btn {
    padding: 10px 25px;
    border: none;
    border-radius: 30px;
    background: var(--primary);
    color: white;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
}

.filter-btn:hover {
    background: var(--primary-light);
    transform: translateY(-2px);
}

/* ===== بطاقات الإحصائيات ===== */
.stats-summary {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.summary-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.summary-number {
    font-size: 2rem;
    font-weight: 800;
    color: var(--primary);
}

.summary-label {
    color: #666;
    font-size: 0.9rem;
}

/* ===== جدول الطلاب ===== */
.students-table {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    margin-bottom: 30px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: var(--primary);
    color: white;
    padding: 15px;
    font-weight: 600;
}

td {
    padding: 12px;
    border-bottom: 1px solid #eee;
    text-align: center;
}

tr:hover {
    background: #f8f9fa;
}

.student-link {
    color: var(--primary);
    font-weight: 600;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
    justify-content: center;
}

.student-link:hover {
    color: var(--secondary);
}

.student-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
}

.commitment-bar {
    width: 100px;
    height: 8px;
    background: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
    margin: 0 auto;
}

.commitment-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--success), #20c997);
    border-radius: 10px;
}

/* ===== بطاقة الطالب المحدد ===== */
.student-detail-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    margin-top: 20px;
}

.detail-header {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--secondary);
}

.detail-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
}

.detail-title {
    flex: 1;
}

.detail-title h2 {
    color: var(--primary);
    margin-bottom: 5px;
}

.detail-title p {
    color: #666;
}

.detail-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.detail-stat {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 15px;
    text-align: center;
}

.detail-stat .number {
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--primary);
}

.detail-stat .label {
    color: #666;
    font-size: 0.9rem;
}

.records-table {
    width: 100%;
    border-collapse: collapse;
}

.records-table th {
    background: var(--secondary);
    color: var(--primary);
    padding: 10px;
}

.records-table td {
    padding: 8px;
}

.status-badge {
    padding: 3px 10px;
    border-radius: 30px;
    font-size: 0.8rem;
    font-weight: 600;
}

.status-on-time {
    background: var(--success-light);
    color: #155724;
}

.status-late {
    background: var(--warning-light);
    color: #856404;
}

@media (max-width: 768px) {
    .stats-summary {
        grid-template-columns: 1fr 1fr;
    }
    
    .filter-section {
        flex-direction: column;
    }
    
    .filter-group {
        width: 100%;
    }
    
    .filter-select {
        flex: 1;
    }
    
    table {
        font-size: 0.85rem;
    }
    
    th, td {
        padding: 8px;
    }
    
    .detail-stats {
        grid-template-columns: 1fr;
    }
}
</style>

<section class="report-page">
    <div class="page-header">
        <h1>
            <i class="fas fa-chart-line"></i>
            تقرير الالتزام اليومي
        </h1>
        <div class="date-badge">
            <i class="fas fa-calendar-alt"></i>
            آخر 30 يوم
        </div>
    </div>

    <!-- أدوات التصفية -->
    <div class="filter-section">
        <div class="filter-group">
            <select class="filter-select" id="periodSelect" onchange="changePeriod()">
                <option value="7" <?php echo $period == '7' ? 'selected' : ''; ?>>آخر 7 أيام</option>
                <option value="30" <?php echo $period == '30' ? 'selected' : ''; ?>>آخر 30 يوم</option>
                <option value="90" <?php echo $period == '90' ? 'selected' : ''; ?>>آخر 90 يوم</option>
            </select>
            <input type="date" class="filter-select" id="dateInput" value="<?php echo $selected_date; ?>">
            <button class="filter-btn" onclick="applyFilters()">
                <i class="fas fa-filter"></i> تطبيق
            </button>
        </div>
        <div class="filter-group">
            <span style="color: #666;">
                <i class="fas fa-info-circle"></i>
                التقرير يوضح نسبة الالتزام بالمواعيد المحددة
            </span>
        </div>
    </div>

    <!-- إحصائيات سريعة -->
    <?php
    $total_students = count($students_stats);
    $total_recordings = array_sum(array_column($students_stats, 'total_recordings'));
    $total_on_time = array_sum(array_column($students_stats, 'on_time_count'));
    $avg_commitment = $total_recordings > 0 ? round(($total_on_time / $total_recordings) * 100) : 0;
    ?>
    <div class="stats-summary">
        <div class="summary-card">
            <div class="summary-number"><?php echo $total_students; ?></div>
            <div class="summary-label">طلاب نشطون</div>
        </div>
        <div class="summary-card">
            <div class="summary-number"><?php echo $total_recordings; ?></div>
            <div class="summary-label">إجمالي التسجيلات</div>
        </div>
        <div class="summary-card">
            <div class="summary-number"><?php echo $total_on_time; ?></div>
            <div class="summary-label">في الموعد</div>
        </div>
        <div class="summary-card">
            <div class="summary-number"><?php echo $avg_commitment; ?>%</div>
            <div class="summary-label">نسبة الالتزام</div>
        </div>
    </div>

    <!-- جدول الطلاب -->
    <div class="students-table">
        <table>
            <thead>
                <tr>
                    <th>الطالب</th>
                    <th>الموعد المفضل</th>
                    <th>إجمالي التسجيلات</th>
                    <th>في الموعد</th>
                    <th>متأخر</th>
                    <th>نسبة الالتزام</th>
                    <th>آخر تسجيل</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students_stats as $stat): 
                    $total = $stat['total_recordings'] ?: 1;
                    $commitment_rate = round(($stat['on_time_count'] / $total) * 100);
                    $color = $commitment_rate >= 80 ? 'success' : ($commitment_rate >= 50 ? 'warning' : 'danger');
                ?>
                    <tr>
                        <td>
                            <a href="?student_id=<?php echo $stat['id']; ?>&period=<?php echo $period; ?>" class="student-link">
                                <span class="student-avatar"><?php echo mb_substr($stat['name'], 0, 1, 'UTF-8'); ?></span>
                                <?php echo htmlspecialchars($stat['name']); ?>
                            </a>
                        </td>
                        <td><?php echo $stat['preferred_time'] ? date('h:i A', strtotime($stat['preferred_time'])) : 'غير محدد'; ?></td>
                        <td><?php echo $stat['total_recordings']; ?></td>
                        <td style="color: var(--success);"><?php echo $stat['on_time_count']; ?></td>
                        <td style="color: var(--warning);"><?php echo $stat['late_count']; ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 5px; justify-content: center;">
                                <span style="color: <?php 
                                    echo $color == 'success' ? '#28a745' : ($color == 'warning' ? '#ffc107' : '#dc3545'); 
                                ?>;"><?php echo $commitment_rate; ?>%</span>
                                <div class="commitment-bar">
                                    <div class="commitment-fill" style="width: <?php echo $commitment_rate; ?>%;"></div>
                                </div>
                            </div>
                        </td>
                        <td><?php echo $stat['last_memorized'] ?: 'لا يوجد'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- تفاصيل الطالب المحدد -->
    <?php if ($selected_student > 0 && !empty($student_details)): ?>
        <div class="student-detail-card">
            <div class="detail-header">
                <div class="detail-avatar">
                    <?php echo mb_substr($student_name, 0, 1, 'UTF-8'); ?>
                </div>
                <div class="detail-title">
                    <h2><?php echo htmlspecialchars($student_name); ?></h2>
                    <p>تفاصيل التسجيلات في آخر <?php echo $period; ?> يوم</p>
                </div>
            </div>

            <?php
            $student_total = count($student_details);
            $student_on_time = count(array_filter($student_details, fn($d) => $d['is_on_time'] == 1));
            $student_late = count(array_filter($student_details, fn($d) => $d['is_on_time'] == 0));
            $student_rate = $student_total > 0 ? round(($student_on_time / $student_total) * 100) : 0;
            ?>
            <div class="detail-stats">
                <div class="detail-stat">
                    <div class="number"><?php echo $student_total; ?></div>
                    <div class="label">إجمالي التسجيلات</div>
                </div>
                <div class="detail-stat">
                    <div class="number" style="color: var(--success);"><?php echo $student_on_time; ?></div>
                    <div class="label">في الموعد</div>
                </div>
                <div class="detail-stat">
                    <div class="number" style="color: var(--warning);"><?php echo $student_late; ?></div>
                    <div class="label">متأخر</div>
                </div>
            </div>

            <table class="records-table">
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>الوقت</th>
                        <th>السورة</th>
                        <th>الآيات</th>
                        <th>الحالة</th>
                        <th>التأخير</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($student_details as $detail): ?>
                        <tr>
                            <td><?php echo $detail['memorized_date']; ?></td>
                            <td><?php echo date('h:i A', strtotime($detail['memorized_time'])); ?></td>
                            <td><?php echo $detail['surah_name']; ?></td>
                            <td>
                                <?php if ($detail['from_ayah'] && $detail['to_ayah']): ?>
                                    <?php echo $detail['from_ayah']; ?> - <?php echo $detail['to_ayah']; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $detail['is_on_time'] ? 'status-on-time' : 'status-late'; ?>">
                                    <?php echo $detail['is_on_time'] ? 'في الموعد' : 'متأخر'; ?>
                                </span>
                            </td>
                            <td><?php echo $detail['delay_minutes'] ?: '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<script>
function changePeriod() {
    let period = document.getElementById('periodSelect').value;
    let date = document.getElementById('dateInput').value;
    window.location.href = '?period=' + period + '&date=' + date;
}

function applyFilters() {
    let period = document.getElementById('periodSelect').value;
    let date = document.getElementById('dateInput').value;
    window.location.href = '?period=' + period + '&date=' + date;
}
</script>

<?php require_once 'includes/footer.php'; ?>