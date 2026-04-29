<?php
// ============================================
// ملف: memorization_session.php
// جلسة التسميع الذكية
// آخر تحديث: 2026-03-16
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isTeacher()) {
    redirect('login.php');
}

$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$part_number = isset($_GET['part']) ? (int)$_GET['part'] : 1;

// جلب معلومات الاختبار
$exam = $pdo->prepare("
    SELECT e.*, s.name as student_name, s.id as student_id
    FROM final_student_exams e
    JOIN students s ON e.student_id = s.id
    WHERE e.id = ? AND e.teacher_id = ?
");
$exam->execute([$exam_id, $_SESSION['user_id']]);
$exam = $exam->fetch();

if (!$exam) {
    redirect('final_exam_dashboard.php');
}

// معالجة حفظ الجلسة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_session'])) {
    $session_date = $_POST['session_date'];
    $ayah_mistakes = (int)$_POST['ayah_mistakes'];
    $haraka_mistakes = (int)$_POST['haraka_mistakes'];
    $tajweed_mistakes = (int)$_POST['tajweed_mistakes'];
    $hesitation_count = (int)$_POST['hesitation_count'];
    $duration = (int)$_POST['duration'];
    $notes = trim($_POST['notes']);
    
    // حساب الدرجة
    $base_score = 100;
    $deductions = ($ayah_mistakes * 1.5) + 
                  ($haraka_mistakes * 0.5) + 
                  ($tajweed_mistakes * 0.5) + 
                  ($hesitation_count * 0.5);
    
    $session_score = max(0, $base_score - $deductions);
    
    try {
        $pdo->beginTransaction();
        
        // حفظ الجلسة
        $stmt = $pdo->prepare("
            INSERT INTO memorization_sessions 
            (student_exam_id, part_number, session_date, ayah_mistakes, haraka_mistakes, 
             tajweed_mistakes, hesitation_count, session_score, duration_minutes, notes, recorded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $exam_id, $part_number, $session_date, $ayah_mistakes, $haraka_mistakes,
            $tajweed_mistakes, $hesitation_count, $session_score, $duration, $notes, $_SESSION['user_id']
        ]);
        
        // تحديث عدد الأجزاء المكتملة
        $update = $pdo->prepare("
            UPDATE final_student_exams 
            SET completed_parts = completed_parts + 1 
            WHERE id = ?
        ");
        $update->execute([$exam_id]);
        
        // التحقق مما إذا كان هذا هو الجزء الأخير
        $exam_data = $pdo->prepare("
            SELECT completed_parts, total_parts FROM final_student_exams WHERE id = ?
        ");
        $exam_data->execute([$exam_id]);
        $progress = $exam_data->fetch();
        
        $pdo->commit();
        
        // رسالة نجاح مع تحفيز
        if ($progress && $progress['completed_parts'] >= $progress['total_parts']) {
            $_SESSION['success'] = "🎉 مبروك! تم إكمال جميع الأجزاء بنجاح!";
            header("Location: final_exam_dashboard.php?completed=1");
        } elseif ($session_score >= 95) {
            $_SESSION['success'] = "🌟 أداء ممتاز! استمر بنفس القوة!";
            header("Location: memorization_session.php?exam_id=$exam_id&part=" . ($part_number + 1));
        } elseif ($session_score >= 85) {
            $_SESSION['success'] = "✅ أداء جيد جداً! تقدم رائع!";
            header("Location: memorization_session.php?exam_id=$exam_id&part=" . ($part_number + 1));
        } else {
            $_SESSION['success'] = "✅ تم تسجيل الجزء $part_number بنجاح";
            header("Location: memorization_session.php?exam_id=$exam_id&part=" . ($part_number + 1));
        }
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "❌ خطأ في الحفظ: " . $e->getMessage();
        header("Location: memorization_session.php?exam_id=$exam_id&part=$part_number");
        exit;
    }
}

$pageTitle = "تسجيل الجزء $part_number";
require_once 'includes/header.php';
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
}

* { margin: 0; padding: 0; box-sizing: border-box; }

.session-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 15px;
}

/* ===== رأس الصفحة ===== */
.header-card {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    padding: 30px;
    border-radius: 30px;
    margin-bottom: 25px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.header-card::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
    animation: rotate 20s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.part-badge {
    background: #c9a96b;
    color: #1e3c3f;
    padding: 10px 30px;
    border-radius: 60px;
    display: inline-block;
    font-size: 1.8rem;
    font-weight: 800;
    margin-bottom: 15px;
    box-shadow: 0 10px 20px rgba(0,0,0,0.2);
    border: 3px solid white;
}

.student-name {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 5px;
}

.progress-info {
    margin-top: 15px;
    font-size: 1.1rem;
    background: rgba(255,255,255,0.15);
    padding: 10px 20px;
    border-radius: 40px;
    display: inline-block;
}

/* ===== بطاقات الأخطاء ===== */
.mistakes-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin: 25px 0;
}

.mistake-card {
    background: white;
    border-radius: 25px;
    padding: 25px 15px;
    text-align: center;
    box-shadow: 0 10px 25px rgba(0,0,0,0.05);
    border: 1px solid #eee;
    transition: 0.3s;
}

.mistake-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
}

.mistake-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 10px;
    font-size: 1.8rem;
}

.mistake-card:nth-child(1) .mistake-icon { background: #fee2e2; color: #dc2626; }
.mistake-card:nth-child(2) .mistake-icon { background: #fef3c7; color: #d97706; }
.mistake-card:nth-child(3) .mistake-icon { background: #e0f2fe; color: #0284c7; }
.mistake-card:nth-child(4) .mistake-icon { background: #f3e8ff; color: #7e22ce; }

.mistake-title {
    font-weight: 700;
    color: #1e3c3f;
    margin-bottom: 10px;
    font-size: 1.1rem;
}

.mistake-deduction {
    color: #666;
    font-size: 0.85rem;
    margin-bottom: 10px;
}

/* ===== العدادات ===== */
.counter-container {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-top: 10px;
}

.counter-btn {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    border: none;
    font-size: 1.8rem;
    font-weight: bold;
    cursor: pointer;
    transition: 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.counter-btn.minus { background: #dc3545; }
.counter-btn.plus { background: #28a745; }
.counter-btn:hover { transform: scale(1.1); }

.counter-value {
    font-size: 2.5rem;
    font-weight: 800;
    color: #1e3c3f;
    min-width: 70px;
    text-align: center;
}

/* ===== ملخص الدرجة ===== */
.summary-box {
    background: #f8f9fa;
    border-radius: 20px;
    padding: 20px;
    margin: 20px 0;
    border: 2px solid #e9ecef;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px dashed #dee2e6;
}

.summary-row:last-child {
    border-bottom: none;
}

.summary-label {
    font-weight: 600;
    color: #495057;
}

.summary-value {
    font-weight: 700;
    color: #1e3c3f;
}

/* ===== معاينة الدرجة ===== */
.score-preview {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    border-radius: 30px;
    padding: 30px;
    text-align: center;
    margin: 20px 0;
    position: relative;
    overflow: hidden;
}

.score-preview::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
    animation: rotate 15s linear infinite;
}

.score-label {
    color: rgba(255,255,255,0.9);
    font-size: 1.1rem;
    margin-bottom: 5px;
    position: relative;
    z-index: 2;
}

.score-value {
    font-size: 5rem;
    font-weight: 800;
    color: #c9a96b;
    line-height: 1;
    text-shadow: 0 0 20px rgba(255,215,0,0.5);
    position: relative;
    z-index: 2;
}

.score-unit {
    color: white;
    font-size: 1.2rem;
    position: relative;
    z-index: 2;
}

/* ===== تحفيز ===== */
.encouragement {
    background: linear-gradient(135deg, #c9a96b, #e6c77c);
    border-radius: 20px;
    padding: 15px;
    margin: 20px 0;
    text-align: center;
    color: #1e3c3f;
    font-weight: 700;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.02); }
}

/* ===== نموذج ===== */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #1e3c3f;
}

.form-group label i {
    color: #c9a96b;
    margin-left: 5px;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e9ecef;
    border-radius: 15px;
    font-size: 1rem;
    transition: 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: #c9a96b;
    box-shadow: 0 0 0 4px rgba(201,169,107,0.1);
}

textarea.form-control {
    min-height: 100px;
    resize: vertical;
}

.action-buttons {
    display: flex;
    gap: 15px;
    margin-top: 25px;
}

.btn {
    padding: 15px 30px;
    border: none;
    border-radius: 50px;
    font-weight: 700;
    cursor: pointer;
    transition: 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 1.1rem;
    flex: 1;
}

.btn-primary { background: linear-gradient(135deg, #1e3c3f, #2a5f5a); color: white; }
.btn-success { background: #28a745; color: white; }
.btn-secondary { background: #6c757d; color: white; }

.btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}

/* ===== تحسينات للهاتف ===== */
@media (max-width: 768px) {
    .mistakes-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .part-badge {
        font-size: 1.5rem;
        padding: 8px 20px;
    }
    
    .student-name {
        font-size: 1.5rem;
    }
    
    .counter-value {
        font-size: 2rem;
        min-width: 50px;
    }
}
</style>

<div class="session-container">
    <!-- رأس الصفحة -->
    <div class="header-card">
        <div class="part-badge">الجزء <?php echo $part_number; ?></div>
        <div class="student-name"><?php echo htmlspecialchars($exam['student_name']); ?></div>
        <div class="progress-info">
            <i class="fas fa-layer-group"></i>
            تقدم الاختبار: <?php echo $exam['completed_parts']; ?>/<?php echo $exam['total_parts']; ?>
        </div>
    </div>

    <!-- رسائل التنبيه -->
    <?php if (isset($_SESSION['success'])): ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 15px; margin-bottom: 20px;">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 15px; margin-bottom: 20px;">
            <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <form method="post" id="sessionForm">
        <input type="hidden" name="session_date" value="<?php echo date('Y-m-d'); ?>">
        <input type="hidden" name="duration" id="durationInput" value="0">
        
        <!-- عدادات الأخطاء -->
        <div class="mistakes-grid">
            <div class="mistake-card">
                <div class="mistake-icon"><i class="fas fa-times-circle"></i></div>
                <div class="mistake-title">خطأ في الآية</div>
                <div class="mistake-deduction">(-1.5 نقطة)</div>
                <div class="counter-container">
                    <button type="button" class="counter-btn minus" onclick="updateCounter('ayah', -1)">−</button>
                    <span class="counter-value" id="ayahCounter">0</span>
                    <button type="button" class="counter-btn plus" onclick="updateCounter('ayah', 1)">+</button>
                </div>
            </div>
            
            <div class="mistake-card">
                <div class="mistake-icon"><i class="fas fa-underline"></i></div>
                <div class="mistake-title">خطأ في التشكيل</div>
                <div class="mistake-deduction">(-0.5 نقطة)</div>
                <div class="counter-container">
                    <button type="button" class="counter-btn minus" onclick="updateCounter('haraka', -1)">−</button>
                    <span class="counter-value" id="harakaCounter">0</span>
                    <button type="button" class="counter-btn plus" onclick="updateCounter('haraka', 1)">+</button>
                </div>
            </div>
            
            <div class="mistake-card">
                <div class="mistake-icon"><i class="fas fa-microphone-alt"></i></div>
                <div class="mistake-title">خطأ في التجويد</div>
                <div class="mistake-deduction">(-0.5 نقطة)</div>
                <div class="counter-container">
                    <button type="button" class="counter-btn minus" onclick="updateCounter('tajweed', -1)">−</button>
                    <span class="counter-value" id="tajweedCounter">0</span>
                    <button type="button" class="counter-btn plus" onclick="updateCounter('tajweed', 1)">+</button>
                </div>
            </div>
            
            <div class="mistake-card">
                <div class="mistake-icon"><i class="fas fa-hourglass-half"></i></div>
                <div class="mistake-title">تردد / شك</div>
                <div class="mistake-deduction">(-0.5 نقطة)</div>
                <div class="counter-container">
                    <button type="button" class="counter-btn minus" onclick="updateCounter('hesitation', -1)">−</button>
                    <span class="counter-value" id="hesitationCounter">0</span>
                    <button type="button" class="counter-btn plus" onclick="updateCounter('hesitation', 1)">+</button>
                </div>
            </div>
        </div>

        <!-- حقول مخفية -->
        <input type="hidden" name="ayah_mistakes" id="ayahMistakes" value="0">
        <input type="hidden" name="haraka_mistakes" id="harakaMistakes" value="0">
        <input type="hidden" name="tajweed_mistakes" id="tajweedMistakes" value="0">
        <input type="hidden" name="hesitation_count" id="hesitationMistakes" value="0">

        <!-- ملخص سريع -->
        <div class="summary-box">
            <div class="summary-row">
                <span class="summary-label">إجمالي الأخطاء:</span>
                <span class="summary-value" id="totalMistakes">0</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">إجمالي الخصم:</span>
                <span class="summary-value" id="totalDeduction">0.0</span>
            </div>
        </div>

        <!-- معاينة الدرجة -->
        <div class="score-preview">
            <div class="score-label">درجة هذا الجزء</div>
            <div class="score-value" id="liveScore">100</div>
            <div class="score-unit">من 100</div>
        </div>

        <!-- رسالة تحفيزية ديناميكية -->
        <div class="encouragement" id="encouragementMessage">
            🌟 ابدأ التسميع، وفقك الله
        </div>

        <!-- ملاحظات -->
        <div class="form-group">
            <label><i class="fas fa-sticky-note"></i> ملاحظات</label>
            <textarea name="notes" class="form-control" rows="3" placeholder="أي ملاحظات عن التسميع..."></textarea>
        </div>

        <!-- أزرار الإجراءات -->
        <div class="action-buttons">
            <a href="final_exam_dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> عودة
            </a>
            <button type="submit" name="save_session" class="btn btn-primary">
                <i class="fas fa-save"></i> حفظ وتقييم
            </button>
        </div>
    </form>
</div>

<script>
// تعريف المتغيرات
let mistakes = {
    ayah: 0,
    haraka: 0,
    tajweed: 0,
    hesitation: 0
};

// رسائل تحفيزية
const encouragementMessages = [
    "🌟 أحسنت! واصل بنفس القوة",
    "📖 تلاوة مباركة، بارك الله فيك",
    "💫 أداء رائع، استمر",
    "✨ أنت في تقدم مستمر",
    "🎯 ركز وحسن أداءك أكثر",
    "💪 واصل، نحن فخورون بك",
    "📚 كل خطأ تتعلم منه تفوز",
    "🤲 اللهم زد وبارك"
];

// تحديث العداد
function updateCounter(type, change) {
    if (!mistakes.hasOwnProperty(type)) return;
    
    let newVal = mistakes[type] + change;
    if (newVal < 0) return;
    
    mistakes[type] = newVal;
    
    // تحديث العرض
    document.getElementById(type + 'Counter').innerText = newVal;
    document.getElementById(type + 'Mistakes').value = newVal;
    
    // إعادة حساب المجموع
    calculateTotal();
}

// حساب المجموع والدرجة
function calculateTotal() {
    let total = mistakes.ayah + mistakes.haraka + mistakes.tajweed + mistakes.hesitation;
    document.getElementById('totalMistakes').innerText = total;
    
    let deduction = (mistakes.ayah * 1.5) + 
                    (mistakes.haraka * 0.5) + 
                    (mistakes.tajweed * 0.5) + 
                    (mistakes.hesitation * 0.5);
    document.getElementById('totalDeduction').innerText = deduction.toFixed(1);
    
    let score = 100 - deduction;
    if (score < 0) score = 0;
    document.getElementById('liveScore').innerText = score.toFixed(1);
    
    // تحديث الرسالة التحفيزية
    updateEncouragement(score);
}

// تحديث الرسالة التحفيزية حسب الدرجة
function updateEncouragement(score) {
    let message = '';
    if (score >= 95) {
        message = '🎉 أداء استثنائي! أنت قدوة للجميع';
    } else if (score >= 85) {
        message = '🌟 ممتاز! حافظ على هذا المستوى';
    } else if (score >= 75) {
        message = '💫 جيد جداً، استمر في التحسن';
    } else if (score >= 60) {
        message = '📚 مقبول، يمكنك الأفضل';
    } else {
        message = encouragementMessages[Math.floor(Math.random() * encouragementMessages.length)];
    }
    document.getElementById('encouragementMessage').innerHTML = message;
}

// بدء توقيت الجلسة
let startTime = Date.now();
setInterval(() => {
    let minutes = Math.floor((Date.now() - startTime) / 60000);
    document.getElementById('durationInput').value = minutes;
}, 60000);

// تهيئة عند التحميل
document.addEventListener('DOMContentLoaded', function() {
    calculateTotal();
});
</script>

<?php require_once 'includes/footer.php'; ?>