<?php
// ============================================
// ملف: rings.php - عرض الحلقات (نسخة كاملة ومضبوطة)
// آخر تحديث: 2026-04-10
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) redirect('login.php');

$pageTitle = 'الحلقات القرآنية';
require_once 'includes/header.php';

// عرض رسائل النجاح والخطأ
if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' . htmlspecialchars($_SESSION['success']) . '</div>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
}

// عرض رسائل من GET
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'students_added') {
        $count = isset($_GET['count']) ? (int)$_GET['count'] : 0;
        echo '<div class="alert alert-success"><i class="fas fa-check-circle"></i> تم إضافة ' . $count . ' طالب إلى الحلقة بنجاح.</div>';
    } elseif ($_GET['msg'] == 'deleted') {
        echo '<div class="alert alert-success"><i class="fas fa-check-circle"></i> تم حذف الحلقة بنجاح.</div>';
    } elseif ($_GET['msg'] == 'added') {
        echo '<div class="alert alert-success"><i class="fas fa-check-circle"></i> تم إضافة الحلقة بنجاح.</div>';
    } elseif ($_GET['msg'] == 'updated') {
        echo '<div class="alert alert-success"><i class="fas fa-check-circle"></i> تم تحديث الحلقة بنجاح.</div>';
    } elseif ($_GET['msg'] == 'ring_transferred') {
        echo '<div class="alert alert-success"><i class="fas fa-exchange-alt"></i> تم نقل الحلقة بنجاح.</div>';
    }
}

// دالة لتحويل رقم اليوم إلى اسم عربي
function getDayName($day) {
    $days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
    return isset($days[$day - 1]) ? $days[$day - 1] : '';
}

// دالة لتحويل الوقت إلى صيغة عربية
function formatTimeArabic($time) {
    if (empty($time)) return '';
    $timestamp = strtotime($time);
    $hour = date('H', $timestamp);
    $minute = date('i', $timestamp);
    if ($hour < 12) {
        $period = 'صباحاً';
        $display_hour = $hour == 0 ? 12 : $hour;
    } else {
        $period = 'مساءً';
        $display_hour = $hour == 12 ? 12 : $hour - 12;
    }
    return $display_hour . ':' . $minute . ' ' . $period;
}

// ============================================
// جلب الحلقات حسب الدور (نسخة مضبوطة)
// ============================================

$rings = [];

if (isAdmin()) {
    // للإدارة - جميع الحلقات
    $stmt = $pdo->query("
        SELECT r.*, t.name as teacher_name
        FROM rings r
        LEFT JOIN teachers t ON r.teacher_id = t.id
        ORDER BY r.created_at DESC
    ");
    $rings = $stmt->fetchAll();
    
    // إضافة عدد الطلاب لكل حلقة (بدون استخدام updateRingStudentsCount لتجنب التكرار)
    foreach ($rings as $key => $ring) {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM ring_students WHERE ring_id = ?");
        $count_stmt->execute([$ring['id']]);
        $rings[$key]['students_count'] = (int)$count_stmt->fetchColumn();
    }
    
} elseif (isTeacher()) {
    // للمعلم - حلقاته فقط
    $teacher_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("
        SELECT r.*, t.name as teacher_name
        FROM rings r
        LEFT JOIN teachers t ON r.teacher_id = t.id
        WHERE r.teacher_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$teacher_id]);
    $rings = $stmt->fetchAll();
    
    // إضافة عدد الطلاب لكل حلقة
    foreach ($rings as $key => $ring) {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM ring_students WHERE ring_id = ?");
        $count_stmt->execute([$ring['id']]);
        $rings[$key]['students_count'] = (int)$count_stmt->fetchColumn();
    }
    
} elseif (isGuardian()) {
    // لولي الأمر - حلقات أبنائه فقط
    $guardian_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("
        SELECT DISTINCT r.*, t.name as teacher_name
        FROM rings r
        JOIN ring_students rs ON r.id = rs.ring_id
        JOIN students s ON rs.student_id = s.id
        LEFT JOIN teachers t ON r.teacher_id = t.id
        WHERE s.guardian_id = ?
        ORDER BY r.name
    ");
    $stmt->execute([$guardian_id]);
    $rings = $stmt->fetchAll();
    
    // إضافة عدد الطلاب لكل حلقة
    foreach ($rings as $key => $ring) {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM ring_students WHERE ring_id = ?");
        $count_stmt->execute([$ring['id']]);
        $rings[$key]['students_count'] = (int)$count_stmt->fetchColumn();
    }
    
} elseif (isStudent()) {
    // للطالب - حلقاته فقط
    $student_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("
        SELECT r.*, t.name as teacher_name
        FROM rings r
        JOIN ring_students rs ON r.id = rs.ring_id
        LEFT JOIN teachers t ON r.teacher_id = t.id
        WHERE rs.student_id = ?
        ORDER BY r.name
    ");
    $stmt->execute([$student_id]);
    $rings = $stmt->fetchAll();
    
    // إضافة عدد الطلاب لكل حلقة
    foreach ($rings as $key => $ring) {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM ring_students WHERE ring_id = ?");
        $count_stmt->execute([$ring['id']]);
        $rings[$key]['students_count'] = (int)$count_stmt->fetchColumn();
    }
}

// إحصائيات عامة
$total_rings = count($rings);
$total_students_in_rings = array_sum(array_column($rings, 'students_count'));
$scattered_rings = count(array_filter($rings, function($r) {
    return !empty($r['is_scattered']) && $r['is_scattered'] == 1;
}));

// أزرار واتساب بعد نقل الحلقة (إذا وجدت)
$ring_transfer_data = null;
if (isset($_SESSION['ring_transfer_completed']) && $_SESSION['ring_transfer_completed']) {
    $ring_transfer_data = [
        'ring_name' => $_SESSION['ring_transfer_name'] ?? '',
        'new_teacher_name' => $_SESSION['ring_transfer_new_teacher_name'] ?? '',
        'new_teacher_phone' => $_SESSION['ring_transfer_new_teacher_phone'] ?? '',
        'students' => $_SESSION['ring_transfer_students'] ?? [],
        'reason' => $_SESSION['ring_transfer_reason'] ?? '',
        'old_teacher_name' => $_SESSION['ring_transfer_old_teacher_name'] ?? ''
    ];
    unset($_SESSION['ring_transfer_completed'], $_SESSION['ring_transfer_name'], 
          $_SESSION['ring_transfer_new_teacher_name'], $_SESSION['ring_transfer_new_teacher_phone'],
          $_SESSION['ring_transfer_students'], $_SESSION['ring_transfer_reason'],
          $_SESSION['ring_transfer_old_teacher_name']);
}

function formatPhoneForWhatsApp($phone) {
    if (empty($phone)) return null;
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 1) == '0') {
        $phone = '20' . substr($phone, 1);
    } elseif (strlen($phone) == 10) {
        $phone = '20' . $phone;
    }
    return $phone;
}
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

.rings-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

/* ===== رأس الصفحة ===== */
.page-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 30px;
    border-radius: 25px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.page-header::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: rotate 20s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.page-header h1 {
    margin: 0;
    font-size: 2rem;
    display: flex;
    align-items: center;
    gap: 15px;
    position: relative;
    z-index: 2;
}

.page-header h1 i {
    color: var(--secondary);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.btn-add {
    background: var(--secondary);
    color: var(--primary);
    padding: 12px 30px;
    border-radius: 40px;
    text-decoration: none;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: 0.3s;
    position: relative;
    z-index: 2;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.btn-add:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.3);
    filter: brightness(1.05);
}

/* ===== إحصائيات ===== */
.stats-summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    transition: 0.3s;
    border: 1px solid #eee;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 55px;
    height: 55px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.stat-icon.rings {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
}

.stat-icon.students {
    background: linear-gradient(135deg, #28a745, #20c997);
}

.stat-icon.scattered {
    background: linear-gradient(135deg, #f39c12, #e67e22);
}

.stat-number {
    font-size: 1.8rem;
    font-weight: 800;
    color: #1e3c3f;
}

.stat-label {
    color: #666;
    font-size: 0.85rem;
}

/* ===== إشعار واتساب ===== */
.whatsapp-notice {
    background: #e8f5e9;
    border-right: 5px solid var(--whatsapp);
    padding: 20px;
    border-radius: 15px;
    margin-bottom: 25px;
}

.whatsapp-buttons {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    margin-top: 15px;
}

.whatsapp-btn {
    background: var(--whatsapp);
    color: white;
    padding: 12px 25px;
    border-radius: 40px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-weight: 700;
    transition: 0.3s;
}

.whatsapp-btn:hover {
    transform: translateY(-3px);
    filter: brightness(1.05);
}

/* ===== رسائل ===== */
.alert {
    padding: 15px 20px;
    border-radius: 15px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-right: 5px solid var(--success);
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border-right: 5px solid var(--danger);
}

/* ===== شبكة الحلقات ===== */
.rings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 25px;
}

/* ===== بطاقة الحلقة ===== */
.ring-card {
    background: white;
    border-radius: 25px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    transition: 0.3s;
    position: relative;
    border: 1px solid #eee;
}

.ring-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.15);
}

.ring-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: linear-gradient(90deg, var(--secondary), var(--primary));
}

.scattered-badge {
    position: absolute;
    top: 15px;
    left: 15px;
    background: linear-gradient(135deg, #f39c12, #e67e22);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 5px;
    z-index: 2;
}

.ring-header {
    padding: 20px 20px 0 20px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 10px;
}

.ring-name {
    font-size: 1.4rem;
    font-weight: 800;
    color: #1e3c3f;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.ring-name i {
    color: var(--secondary);
}

.ring-badge {
    background: #e9ecef;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.ring-content {
    padding: 20px;
}

.ring-teacher {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 15px;
    margin-bottom: 15px;
}

.ring-teacher i {
    width: 35px;
    height: 35px;
    background: #1e3c3f;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.scattered-time {
    background: #fff3cd;
    border-radius: 15px;
    padding: 12px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-right: 4px solid #f39c12;
}

.schedule-section {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 12px;
    margin-bottom: 15px;
}

.schedule-title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
}

.schedule-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.schedule-item {
    background: white;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    gap: 5px;
    border: 1px solid #e9ecef;
}

.students-section {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 0;
    border-top: 1px solid #eee;
}

.students-count {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #1e3c3f;
}

.students-count span {
    font-weight: 700;
    font-size: 1.2rem;
}

/* ===== أزرار الإجراءات ===== */
.ring-actions {
    padding: 15px 20px 20px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    border-top: 1px solid #eee;
}

.btn {
    flex: 1;
    padding: 10px 15px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: 0.3s;
    border: none;
    cursor: pointer;
}

.btn-info {
    background: var(--info);
    color: white;
}

.btn-success {
    background: var(--success);
    color: white;
}

.btn-warning {
    background: var(--warning);
    color: #212529;
}

.btn-danger {
    background: var(--danger);
    color: white;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
}

.btn-scattered {
    background: linear-gradient(135deg, #f39c12, #e67e22);
    color: white;
}

.btn-bulk {
    background: linear-gradient(135deg, #6f42c1, #9b59b6);
    color: white;
}

.btn:hover {
    transform: translateY(-2px);
    filter: brightness(1.05);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

/* ===== حالة فارغة ===== */
.empty-state {
    text-align: center;
    padding: 80px 40px;
    background: white;
    border-radius: 25px;
    grid-column: 1 / -1;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

.empty-state i {
    font-size: 5rem;
    color: #dee2e6;
    margin-bottom: 20px;
}

.empty-state h3 {
    color: var(--primary);
    font-size: 1.8rem;
    margin-bottom: 10px;
}

.empty-state p {
    color: #666;
    margin-bottom: 25px;
}

.empty-state .btn-primary {
    display: inline-block;
    padding: 12px 30px;
    width: auto;
}

/* ===== تحسينات للهاتف ===== */
@media (max-width: 768px) {
    .rings-page {
        padding: 15px;
    }
    
    .rings-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-summary {
        grid-template-columns: 1fr;
    }
    
    .page-header {
        flex-direction: column;
        text-align: center;
    }
    
    .ring-actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
    
    .whatsapp-buttons {
        flex-direction: column;
    }
    
    .whatsapp-btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .ring-name {
        font-size: 1.2rem;
    }
    
    .ring-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .schedule-list {
        flex-direction: column;
    }
    
    .schedule-item {
        width: 100%;
        justify-content: center;
    }
}
</style>

<section class="rings-page">
    <div class="page-header">
        <h1>
            <i class="fas fa-ring"></i>
            الحلقات القرآنية
        </h1>
        <?php if (isAdmin() || isTeacher()): ?>
            <a href="add_ring.php" class="btn-add">
                <i class="fas fa-plus-circle"></i>
                إضافة حلقة جديدة
            </a>
        <?php endif; ?>
    </div>

    <!-- إحصائيات -->
    <div class="stats-summary">
        <div class="stat-card">
            <div class="stat-icon rings">
                <i class="fas fa-ring"></i>
            </div>
            <div>
                <div class="stat-number"><?php echo $total_rings; ?></div>
                <div class="stat-label">إجمالي الحلقات</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon students">
                <i class="fas fa-users"></i>
            </div>
            <div>
                <div class="stat-number"><?php echo $total_students_in_rings; ?></div>
                <div class="stat-label">إجمالي الطلاب</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon scattered">
                <i class="fas fa-hourglass-half"></i>
            </div>
            <div>
                <div class="stat-number"><?php echo $scattered_rings; ?></div>
                <div class="stat-label">حلقات متفرقين</div>
            </div>
        </div>
    </div>

    <!-- أزرار واتساب بعد نقل الحلقة -->
    <?php if ($ring_transfer_data): ?>
    <div class="whatsapp-notice">
        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            <i class="fab fa-whatsapp" style="font-size: 2rem; color: #25d366;"></i>
            <div style="flex: 1;">
                <strong>✅ تم نقل الحلقة "<?php echo htmlspecialchars($ring_transfer_data['ring_name']); ?>" بنجاح!</strong>
                <br>يمكنك الآن إرسال إشعارات واتساب:
            </div>
        </div>
        <div class="whatsapp-buttons">
            <?php 
            $teacher_phone = formatPhoneForWhatsApp($ring_transfer_data['new_teacher_phone']);
            if ($teacher_phone):
                $msg = "السلام عليكم ورحمة الله وبركاته\n";
                $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                $msg .= "📋 *إشعار نقل حلقة إليك*\n";
                $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                $msg .= "🏷️ *اسم الحلقة:* {$ring_transfer_data['ring_name']}\n";
                $msg .= "👨‍🏫 *المعلم السابق:* {$ring_transfer_data['old_teacher_name']}\n";
                $msg .= "📚 *عدد الطلاب:* " . count($ring_transfer_data['students']) . "\n";
                $msg .= "📝 *سبب النقل:* " . ($ring_transfer_data['reason'] ?: 'لا يوجد') . "\n\n";
                $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                $msg .= "دار التقوى لتحفيظ القرآن الكريم";
                $whatsapp_url = "https://wa.me/{$teacher_phone}?text=" . urlencode($msg);
            ?>
                <a href="<?php echo $whatsapp_url; ?>" target="_blank" class="whatsapp-btn">
                    <i class="fab fa-whatsapp"></i> 📱 إشعار للمعلم الجديد
                </a>
            <?php else: ?>
                <div style="background: #fff3cd; padding: 12px; border-radius: 10px; color: #856404;">
                    <i class="fas fa-exclamation-triangle"></i> لا يوجد رقم هاتف للمعلم الجديد
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (count($rings) > 0): ?>
        <div class="rings-grid">
            <?php foreach ($rings as $ring): 
                // جلب المواعيد
                $schedules = $pdo->prepare("SELECT * FROM ring_schedules WHERE ring_id = ? ORDER BY day_of_week");
                $schedules->execute([$ring['id']]);
                $schedules = $schedules->fetchAll();
                $is_scattered = !empty($ring['is_scattered']) && $ring['is_scattered'] == 1;
                $students_count = isset($ring['students_count']) ? (int)$ring['students_count'] : 0;
            ?>
                <div class="ring-card">
                    <?php if ($is_scattered): ?>
                        <div class="scattered-badge">
                            <i class="fas fa-hourglass-half"></i> حلقة متفرقين
                        </div>
                    <?php endif; ?>
                    
                    <div class="ring-header">
                        <h3 class="ring-name">
                            <i class="fas fa-ring"></i>
                            <?php echo htmlspecialchars($ring['name']); ?>
                        </h3>
                        <span class="ring-badge">
                            <i class="fas fa-quran"></i> حلقة قرآنية
                        </span>
                    </div>
                    
                    <div class="ring-content">
                        <div class="ring-teacher">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <div>
                                <div style="font-size:0.7rem; color:#666;">المعلم المشرف</div>
                                <div><?php echo htmlspecialchars($ring['teacher_name'] ?? 'غير محدد'); ?></div>
                            </div>
                        </div>
                        
                        <?php if ($is_scattered && !empty($ring['scattered_start_time'])): ?>
                            <div class="scattered-time">
                                <i class="fas fa-clock"></i>
                                <div>
                                    <strong>وقت الحلقة:</strong> 
                                    <?php echo date('h:i A', strtotime($ring['scattered_start_time'])); ?> - 
                                    <?php echo date('h:i A', strtotime($ring['scattered_end_time'])); ?>
                                    <br><small>المدة: <?php echo $ring['scattered_total_minutes']; ?> دقيقة</small>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (count($schedules) > 0): ?>
                            <div class="schedule-section">
                                <div class="schedule-title">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span>مواعيد الانعقاد</span>
                                </div>
                                <div class="schedule-list">
                                    <?php foreach ($schedules as $sch): 
                                        $day_name = getDayName($sch['day_of_week']);
                                        $time_arabic = formatTimeArabic($sch['start_time']);
                                    ?>
                                        <div class="schedule-item">
                                            <i class="fas fa-clock"></i>
                                            <?php echo $day_name . ' - ' . $time_arabic; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="students-section">
                            <div class="students-count">
                                <i class="fas fa-users"></i>
                                <span><?php echo $students_count; ?></span>
                                <span>طالب</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="ring-actions">
                        <a href="ring_details.php?id=<?php echo $ring['id']; ?>" class="btn btn-info">
                            <i class="fas fa-info-circle"></i> التفاصيل
                        </a>
                        
                        <?php if (isAdmin() || (isTeacher() && $ring['teacher_id'] == $_SESSION['user_id'])): ?>
                            <a href="add_students_to_ring.php?ring_id=<?php echo $ring['id']; ?>" class="btn btn-success">
                                <i class="fas fa-user-plus"></i> إضافة طلاب
                            </a>
                            <a href="edit_ring.php?id=<?php echo $ring['id']; ?>" class="btn btn-warning">
                                <i class="fas fa-edit"></i> تعديل
                            </a>
                            <a href="ring_bulk_memorization.php?ring_id=<?php echo $ring['id']; ?>" class="btn btn-bulk">
                                <i class="fas fa-layer-group"></i> حفظ جماعي
                            </a>
                            <a href="transfer_ring.php?ring_id=<?php echo $ring['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-exchange-alt"></i> نقل الحلقة
                            </a>
                            <?php if ($is_scattered): ?>
                                <a href="scattered_dashboard.php?ring_id=<?php echo $ring['id']; ?>" class="btn btn-scattered">
                                    <i class="fas fa-hourglass-half"></i> لوحة المتفرقين
                                </a>
                                <a href="ring_scattered_settings.php?ring_id=<?php echo $ring['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-cog"></i> إعدادات
                                </a>
                            <?php else: ?>
                                <a href="ring_scattered_settings.php?ring_id=<?php echo $ring['id']; ?>" class="btn btn-scattered">
                                    <i class="fas fa-cog"></i> تفعيل كمتفرقين
                                </a>
                            <?php endif; ?>
                            <a href="delete_ring.php?id=<?php echo $ring['id']; ?>" class="btn btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذه الحلقة؟')">
                                <i class="fas fa-trash"></i> حذف
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-ring"></i>
            <h3>لا توجد حلقات بعد</h3>
            <p>قم بإضافة أول حلقة لتبدأ رحلة التحفيظ</p>
            <?php if (isAdmin() || isTeacher()): ?>
                <a href="add_ring.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> إضافة حلقة جديدة
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>