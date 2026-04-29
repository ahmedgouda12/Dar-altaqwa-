<?php
// ============================================
// ملف: my_wirds.php
// صفحة الأوراد اليومية للطالب
// ============================================

require_once 'config.php';
require_once 'includes/wirds_functions.php';

if (!isStudent()) {
    redirect('login.php');
}

$pageTitle = 'أورادي اليومية';
require_once 'includes/header.php';

$student_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$wirds = getActiveWirds($pdo);
$message = '';
$message_type = '';

// معالجة تحديث الورد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_wird'])) {
    $wird_id = (int)$_POST['wird_id'];
    $count = (int)$_POST['count'];
    $notes = trim($_POST['notes'] ?? '');
    
    if (updateStudentWird($pdo, $student_id, $wird_id, $count, $notes)) {
        $message = "✅ تم تحديث الورد بنجاح";
        $message_type = 'success';
    } else {
        $message = "❌ حدث خطأ في تحديث الورد";
        $message_type = 'error';
    }
}

// جلب تسجيلات اليوم لكل ورد
$records = [];
foreach ($wirds as $wird) {
    $records[$wird['id']] = getStudentWirdRecord($pdo, $student_id, $wird['id'], $today);
}

// إحصائيات الطالب
$stats = getStudentPointsData($pdo, $student_id);
$topWirds = getTopStudentsByWirds($pdo, null, 5);
?>

<style>
.wirds-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    padding: 30px;
    border-radius: 30px;
    margin-bottom: 30px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.page-header h1 {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    margin: 0;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.stat-number {
    font-size: 2rem;
    font-weight: 800;
}

.wirds-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.wird-card {
    background: white;
    border-radius: 25px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    transition: 0.3s;
    border-top: 5px solid;
}

.wird-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
}

.wird-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.wird-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: white;
}

.wird-title {
    flex: 1;
}

.wird-name {
    font-size: 1.3rem;
    font-weight: 800;
    margin-bottom: 5px;
}

.wird-target {
    color: #666;
    font-size: 0.85rem;
}

.counter-section {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 15px;
    margin: 15px 0;
}

.counter-display {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    margin-bottom: 10px;
}

.counter-btn {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    border: none;
    font-size: 1.5rem;
    font-weight: bold;
    cursor: pointer;
    transition: 0.2s;
}

.counter-btn.minus { background: #dc3545; color: white; }
.counter-btn.plus { background: #28a745; color: white; }
.counter-value { font-size: 2rem; font-weight: 700; min-width: 80px; text-align: center; }

.progress-bar {
    height: 8px;
    background: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #28a745, #20c997);
    border-radius: 10px;
    transition: width 0.3s;
}

.points-badge {
    background: #c9a96b;
    color: #1e3c3f;
    padding: 5px 12px;
    border-radius: 30px;
    font-size: 0.8rem;
    display: inline-block;
    margin-top: 10px;
}

.completed-badge {
    background: #d4edda;
    color: #155724;
    padding: 8px;
    border-radius: 12px;
    text-align: center;
    margin-top: 15px;
}

.top-students-section {
    background: white;
    border-radius: 25px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

.top-students-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.top-student-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 15px;
}

@media (max-width: 768px) {
    .stats-row { grid-template-columns: repeat(2, 1fr); }
    .wirds-grid { grid-template-columns: 1fr; }
}
</style>

<section class="wirds-page">
    <div class="page-header">
        <h1>
            <i class="fas fa-praying-hands"></i>
            أورادي اليومية
        </h1>
        <p>سجل أذكارك اليومية واحصل على النقاط</p>
    </div>

    <!-- إحصائيات سريعة -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total_points']; ?></div>
            <div>إجمالي النقاط</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total_wird_points']; ?></div>
            <div>نقاط الأوراد</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['level']; ?></div>
            <div>المستوى</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['level_name']; ?></div>
            <div>اللقب</div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- بطاقات الأوراد -->
    <div class="wirds-grid">
        <?php foreach ($wirds as $wird): 
            $record = $records[$wird['id']] ?? null;
            $current_count = $record['current_count'] ?? 0;
            $target = $wird['recommended_count'];
            $percentage = min(100, round(($current_count / $target) * 100));
            $is_completed = ($current_count >= $target);
        ?>
            <div class="wird-card" style="border-top-color: <?php echo $wird['color']; ?>;">
                <div class="wird-header">
                    <div class="wird-icon" style="background: <?php echo $wird['color']; ?>;">
                        <i class="fas <?php echo $wird['icon']; ?>"></i>
                    </div>
                    <div class="wird-title">
                        <div class="wird-name"><?php echo htmlspecialchars($wird['wird_name']); ?></div>
                        <div class="wird-target">الهدف: <?php echo number_format($target); ?> مرة</div>
                    </div>
                </div>

                <form method="post">
                    <input type="hidden" name="wird_id" value="<?php echo $wird['id']; ?>">
                    
                    <div class="counter-section">
                        <div class="counter-display">
                            <button type="button" class="counter-btn minus" onclick="updateCounter(this, -1, <?php echo $wird['id']; ?>)">−</button>
                            <span class="counter-value" id="counter_<?php echo $wird['id']; ?>"><?php echo number_format($current_count); ?></span>
                            <button type="button" class="counter-btn plus" onclick="updateCounter(this, 1, <?php echo $wird['id']; ?>)">+</button>
                        </div>
                        <input type="hidden" name="count" id="count_<?php echo $wird['id']; ?>" value="<?php echo $current_count; ?>">
                        
                        <div class="progress-bar">
                            <div class="progress-fill" id="progress_<?php echo $wird['id']; ?>" style="width: <?php echo $percentage; ?>%;"></div>
                        </div>
                        
                        <div class="points-badge">
                            <i class="fas fa-star"></i> النقاط: <?php echo round($current_count * $wird['points_per_unit']); ?>
                        </div>
                    </div>

                    <?php if ($is_completed): ?>
                        <div class="completed-badge">
                            <i class="fas fa-check-circle"></i> ✅ تم إكمال الهدف اليومي!
                        </div>
                    <?php endif; ?>

                    <button type="submit" name="update_wird" class="btn-save" style="width: 100%; margin-top: 15px; padding: 12px; background: linear-gradient(135deg, #1e3c3f, #2a5f5a); color: white; border: none; border-radius: 30px; font-weight: 600; cursor: pointer;">
                        <i class="fas fa-save"></i> حفظ التقدم
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- أفضل الطلاب في الأذكار -->
    <?php if (!empty($topWirds)): ?>
    <div class="top-students-section">
        <h3><i class="fas fa-crown" style="color: gold;"></i> أفضل الطلاب في الأذكار</h3>
        <div class="top-students-list">
            <?php foreach ($topWirds as $index => $student): ?>
                <div class="top-student-item">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: <?php echo $index < 3 ? 'gold' : '#6c757d'; ?>; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                        <?php echo $index + 1; ?>
                    </div>
                    <div style="flex: 1;">
                        <strong><?php echo htmlspecialchars($student['name']); ?></strong>
                        <br><small><?php echo htmlspecialchars($student['teacher_name'] ?? 'غير محدد'); ?></small>
                    </div>
                    <div style="color: #c9a96b; font-weight: bold;">
                        <?php echo $student['total_points']; ?> نقطة
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</section>

<script>
function updateCounter(btn, change, wirdId) {
    const counterSpan = document.getElementById('counter_' + wirdId);
    const counterInput = document.getElementById('count_' + wirdId);
    let current = parseInt(counterSpan.innerText.replace(/,/g, '')) || 0;
    let newValue = current + change;
    if (newValue < 0) newValue = 0;
    counterSpan.innerText = newValue.toLocaleString();
    counterInput.value = newValue;
    
    // تحديث شريط التقدم
    const target = <?php echo json_encode(array_combine(array_column($wirds, 'id'), array_column($wirds, 'recommended_count'))); ?>;
    const percentage = Math.min(100, (newValue / (target[wirdId] || 100)) * 100);
    document.getElementById('progress_' + wirdId).style.width = percentage + '%';
}
</script>

<?php require_once 'includes/footer.php'; ?>