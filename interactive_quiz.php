<?php
// ============================================
// ملف: interactive_quiz.php
// الأسئلة التفاعلية للسور
// آخر تحديث: 2026-03-18
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isStudent() && !isTeacher() && !isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'الأسئلة التفاعلية';
require_once 'includes/header.php';

$surah_number = isset($_GET['surah']) ? (int)$_GET['surah'] : 1;
$student_id = isStudent() ? $_SESSION['user_id'] : null;

// جلب الأسئلة للسورة
$questions = $pdo->prepare("
    SELECT * FROM interactive_questions 
    WHERE surah_number = ? 
    ORDER BY difficulty_level, id
");
$questions->execute([$surah_number]);
$questions = $questions->fetchAll();

// جلب إجابات الطالب السابقة
$answered = [];
if ($student_id) {
    $answers = $pdo->prepare("
        SELECT question_id, is_correct, points_earned 
        FROM student_answers 
        WHERE student_id = ?
    ");
    $answers->execute([$student_id]);
    foreach ($answers->fetchAll() as $a) {
        $answered[$a['question_id']] = $a;
    }
}

// جلب إحصائيات الطالب
$stats = null;
if ($student_id) {
    $stats = $pdo->prepare("
        SELECT * FROM student_question_stats WHERE student_id = ?
    ");
    $stats->execute([$student_id]);
    $stats = $stats->fetch();
    
    if (!$stats) {
        // إنشاء سجل إحصائي جديد
        $pdo->prepare("
            INSERT INTO student_question_stats (student_id) VALUES (?)
        ")->execute([$student_id]);
        $stats = [
            'total_answered' => 0,
            'correct_answers' => 0,
            'total_points' => 0,
            'current_streak' => 0,
            'best_streak' => 0
        ];
    }
}

// معالجة إجابة جديدة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['answer_question']) && $student_id) {
    $question_id = (int)$_POST['question_id'];
    $selected_option = (int)$_POST['selected_option'];
    $time_taken = (int)$_POST['time_taken'];
    
    // جلب معلومات السؤال
    $question = $pdo->prepare("SELECT * FROM interactive_questions WHERE id = ?");
    $question->execute([$question_id]);
    $q = $question->fetch();
    
    if ($q) {
        $is_correct = ($selected_option == $q['correct_option']) ? 1 : 0;
        $points_earned = $is_correct ? $q['points_reward'] : 0;
        
        // حفظ الإجابة
        $stmt = $pdo->prepare("
            INSERT INTO student_answers 
            (student_id, question_id, selected_option, is_correct, time_taken, points_earned)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$student_id, $question_id, $selected_option, $is_correct, $time_taken, $points_earned]);
        
        // تحديث الإحصائيات
        $new_total = $stats['total_answered'] + 1;
        $new_correct = $stats['correct_answers'] + $is_correct;
        $new_points = $stats['total_points'] + $points_earned;
        
        if ($is_correct) {
            $new_streak = $stats['current_streak'] + 1;
            $new_best = max($stats['best_streak'], $new_streak);
        } else {
            $new_streak = 0;
            $new_best = $stats['best_streak'];
        }
        
        $update = $pdo->prepare("
            UPDATE student_question_stats SET
                total_answered = ?,
                correct_answers = ?,
                total_points = ?,
                current_streak = ?,
                best_streak = ?
            WHERE student_id = ?
        ");
        $update->execute([$new_total, $new_correct, $new_points, $new_streak, $new_best, $student_id]);
        
        // إرجاع نتيجة AJAX
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'is_correct' => $is_correct,
                'points' => $points_earned,
                'explanation' => $q['explanation'],
                'correct_option' => $q['correct_option']
            ]);
            exit;
        }
        
        $_SESSION['success'] = $is_correct ? '✅ إجابة صحيحة! +' . $points_earned . ' نقطة' : '❌ إجابة خاطئة';
        header("Location: interactive_quiz.php?surah=$surah_number");
        exit;
    }
}

// قائمة السور للتنقل
$surah_list = [];
for ($i = 1; $i <= 114; $i++) {
    $surah_list[$i] = getSurahName($i);
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
}

.quiz-page {
    max-width: 1200px;
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
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
    position: relative;
    overflow: hidden;
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
    display: flex;
    align-items: center;
    gap: 15px;
    font-size: 2rem;
    position: relative;
    z-index: 2;
}

.page-header h1 i {
    color: var(--secondary);
}

.surah-selector {
    background: rgba(255,255,255,0.15);
    padding: 10px 25px;
    border-radius: 50px;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-right: auto;
    position: relative;
    z-index: 2;
    backdrop-filter: blur(5px);
}

.surah-selector select {
    background: white;
    color: var(--primary);
    padding: 8px 15px;
    border-radius: 30px;
    border: none;
    font-size: 1rem;
    cursor: pointer;
}

/* ===== بطاقات الإحصائيات ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    border: 1px solid #eee;
    transition: 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 10px;
    font-size: 1.5rem;
    color: white;
}

.stat-number {
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--primary);
}

.stat-label {
    color: #666;
    font-size: 0.9rem;
}

/* ===== شريط التقدم ===== */
.streak-bar {
    background: linear-gradient(135deg, #fff3cd, #ffe69c);
    border-radius: 60px;
    padding: 15px 25px;
    margin-bottom: 30px;
    display: flex;
    align-items: center;
    gap: 15px;
    border: 2px solid var(--warning);
}

.streak-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--warning);
    color: #212529;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
}

.streak-content {
    flex: 1;
}

.streak-text {
    font-weight: 700;
    color: #856404;
}

.streak-count {
    font-size: 2rem;
    font-weight: 800;
    color: #856404;
}

/* ===== بطاقات الأسئلة ===== */
.questions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.question-card {
    background: white;
    border-radius: 25px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    border: 1px solid #eee;
    transition: 0.3s;
    position: relative;
    overflow: hidden;
}

.question-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
}

.question-card.answered {
    background: #f8f9fa;
    opacity: 0.9;
}

.question-card.correct {
    border-right: 6px solid var(--success);
}

.question-card.wrong {
    border-right: 6px solid var(--danger);
}

.difficulty-badge {
    position: absolute;
    top: 15px;
    left: 15px;
    padding: 5px 12px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 700;
}

.difficulty-1 { background: #d4edda; color: #155724; }
.difficulty-2 { background: #fff3cd; color: #856404; }
.difficulty-3 { background: #f8d7da; color: #721c24; }

.question-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px dashed var(--secondary);
}

.question-type {
    background: var(--secondary);
    color: white;
    padding: 3px 10px;
    border-radius: 30px;
    font-size: 0.8rem;
}

.question-text {
    font-size: 1.1rem;
    color: var(--primary);
    margin-bottom: 20px;
    line-height: 1.6;
    font-weight: 600;
}

.ayah-ref {
    background: #f8f9fa;
    padding: 5px 12px;
    border-radius: 30px;
    font-size: 0.85rem;
    display: inline-block;
    margin-bottom: 15px;
}

/* ===== خيارات الإجابة ===== */
.options-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 20px;
}

.option-item {
    background: #f8f9fa;
    border: 2px solid #e9ecef;
    border-radius: 15px;
    padding: 15px;
    cursor: pointer;
    transition: 0.3s;
    text-align: center;
    font-weight: 600;
    position: relative;
    overflow: hidden;
}

.option-item:hover:not(.disabled) {
    background: #e9ecef;
    border-color: var(--secondary);
    transform: translateY(-2px);
}

.option-item.selected {
    background: var(--secondary);
    color: white;
    border-color: var(--secondary);
}

.option-item.correct {
    background: var(--success);
    color: white;
    border-color: var(--success);
}

.option-item.wrong {
    background: var(--danger);
    color: white;
    border-color: var(--danger);
}

.option-item.disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.option-letter {
    display: inline-block;
    width: 25px;
    height: 25px;
    background: rgba(0,0,0,0.1);
    border-radius: 50%;
    line-height: 25px;
    margin-left: 8px;
    font-weight: 700;
}

/* ===== نتيجة الإجابة ===== */
.result-box {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 15px;
    margin: 15px 0;
    border-right: 4px solid transparent;
}

.result-box.correct {
    background: #d4edda;
    border-right-color: var(--success);
}

.result-box.wrong {
    background: #f8d7da;
    border-right-color: var(--danger);
}

.points-earned {
    display: inline-block;
    background: var(--secondary);
    color: white;
    padding: 3px 12px;
    border-radius: 30px;
    font-size: 0.85rem;
    margin-top: 10px;
}

/* ===== التوقيت ===== */
.timer {
    display: inline-block;
    background: var(--info);
    color: white;
    padding: 3px 12px;
    border-radius: 30px;
    font-size: 0.8rem;
    margin-left: 10px;
}

/* ===== حالة عدم وجود أسئلة ===== */
.empty-state {
    text-align: center;
    padding: 80px 20px;
    background: white;
    border-radius: 30px;
    grid-column: 1 / -1;
}

.empty-state i {
    font-size: 5rem;
    color: #dee2e6;
    margin-bottom: 20px;
}

/* ===== تحسينات للهاتف ===== */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .questions-grid {
        grid-template-columns: 1fr;
    }
    
    .options-grid {
        grid-template-columns: 1fr;
    }
    
    .page-header {
        flex-direction: column;
        text-align: center;
    }
    
    .surah-selector {
        margin-right: 0;
        width: 100%;
    }
}
</style>

<section class="quiz-page">
    <!-- رأس الصفحة -->
    <div class="page-header">
        <h1>
            <i class="fas fa-question-circle"></i>
            الأسئلة التفاعلية
        </h1>
        <div class="surah-selector">
            <i class="fas fa-quran"></i>
            <select onchange="window.location.href='?surah='+this.value">
                <?php foreach ($surah_list as $num => $name): ?>
                    <option value="<?php echo $num; ?>" <?php echo $num == $surah_number ? 'selected' : ''; ?>>
                        <?php echo $num; ?>. <?php echo $name; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <?php if ($student_id && $stats): ?>
        <!-- إحصائيات الطالب -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #1e3c3f, #2a5f5a);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_answered']; ?></div>
                <div class="stat-label">إجمالي الإجابات</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #28a745;">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-number"><?php echo $stats['correct_answers']; ?></div>
                <div class="stat-label">الإجابات الصحيحة</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #ffc107;">
                    <i class="fas fa-percent"></i>
                </div>
                <div class="stat-number">
                    <?php 
                    $percent = $stats['total_answered'] > 0 
                        ? round(($stats['correct_answers'] / $stats['total_answered']) * 100) 
                        : 0;
                    echo $percent; ?>%
                </div>
                <div class="stat-label">نسبة النجاح</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #17a2b8;">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_points']; ?></div>
                <div class="stat-label">النقاط</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #ffc107;">
                    <i class="fas fa-fire"></i>
                </div>
                <div class="stat-number"><?php echo $stats['best_streak']; ?></div>
                <div class="stat-label">أفضل سلسلة</div>
            </div>
        </div>

        <!-- شريط السلسلة الحالية -->
        <?php if ($stats['current_streak'] > 0): ?>
            <div class="streak-bar">
                <div class="streak-icon">
                    <i class="fas fa-fire"></i>
                </div>
                <div class="streak-content">
                    <div class="streak-text">أنت في سلسلة انتصارات!</div>
                    <div class="streak-count"><?php echo $stats['current_streak']; ?> إجابات صحيحة متتالية</div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- قائمة الأسئلة -->
    <?php if (empty($questions)): ?>
        <div class="empty-state">
            <i class="fas fa-question-circle"></i>
            <h3>لا توجد أسئلة لهذه السورة بعد</h3>
            <p>سيتم إضافة أسئلة جديدة قريباً</p>
        </div>
    <?php else: ?>
        <div class="questions-grid">
            <?php foreach ($questions as $index => $q): 
                $is_answered = isset($answered[$q['id']]);
                $answer_data = $answered[$q['id']] ?? null;
                $options = json_decode($q['options'], true);
                $letters = ['أ', 'ب', 'ج', 'د'];
            ?>
                <div class="question-card <?php 
                    echo $is_answered ? 'answered' : '';
                    if ($is_answered) echo $answer_data['is_correct'] ? ' correct' : ' wrong';
                ?>" id="question-<?php echo $q['id']; ?>">
                    
                    <div class="difficulty-badge difficulty-<?php echo $q['difficulty_level']; ?>">
                        <?php 
                        if ($q['difficulty_level'] == 1) echo 'سهل';
                        elseif ($q['difficulty_level'] == 2) echo 'متوسط';
                        else echo 'صعب';
                        ?>
                    </div>

                    <div class="question-header">
                        <span class="question-type">
                            <?php 
                            $types = [
                                'general' => 'عام',
                                'tafsir' => 'تفسير',
                                'reason' => 'أسباب النزول',
                                'language' => 'لغويات',
                                'history' => 'تاريخي'
                            ];
                            echo $types[$q['question_type']] ?? 'عام';
                            ?>
                        </span>
                        <?php if ($q['ayah_number']): ?>
                            <span class="ayah-ref">الآية <?php echo $q['ayah_number']; ?></span>
                        <?php endif; ?>
                        <span class="timer" style="display: none;">0 ثانية</span>
                    </div>

                    <div class="question-text">
                        <?php echo $q['question_text']; ?>
                    </div>

                    <div class="options-grid">
                        <?php foreach ($options as $opt_index => $option): ?>
                            <div class="option-item <?php 
                                if ($is_answered) echo 'disabled';
                                if ($is_answered && $opt_index == $q['correct_option']) echo ' correct';
                                if ($is_answered && $opt_index == $answer_data['selected_option'] && !$answer_data['is_correct']) echo ' wrong';
                            ?>" 
                                 onclick="<?php echo $student_id && !$is_answered ? "submitAnswer($q[id], $opt_index, this)" : ''; ?>">
                                <span class="option-letter"><?php echo $letters[$opt_index]; ?></span>
                                <?php echo $option; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($is_answered): ?>
                        <div class="result-box <?php echo $answer_data['is_correct'] ? 'correct' : 'wrong'; ?>">
                            <i class="fas <?php echo $answer_data['is_correct'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                            <?php if ($answer_data['is_correct']): ?>
                                إجابة صحيحة! +<?php echo $answer_data['points_earned']; ?> نقطة
                            <?php else: ?>
                                إجابة خاطئة. الإجابة الصحيحة هي: <?php echo $options[$q['correct_option']]; ?>
                            <?php endif; ?>
                            
                            <?php if ($q['explanation']): ?>
                                <div style="margin-top: 10px; font-size: 0.9rem;">
                                    <strong>التفسير:</strong> <?php echo $q['explanation']; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php if ($student_id): ?>
<script>
let startTimes = {};
let timers = {};

// بدء التوقيت لكل سؤال
document.querySelectorAll('.question-card').forEach((card, index) => {
    let questionId = card.id.replace('question-', '');
    if (!card.classList.contains('answered')) {
        startTimes[questionId] = Date.now();
        
        // عرض التوقيت
        let timerSpan = card.querySelector('.timer');
        timerSpan.style.display = 'inline-block';
        
        timers[questionId] = setInterval(() => {
            let elapsed = Math.floor((Date.now() - startTimes[questionId]) / 1000);
            timerSpan.textContent = elapsed + ' ثانية';
        }, 1000);
    }
});

// إرسال الإجابة
function submitAnswer(questionId, selectedOption, element) {
    // منع النقر المتعدد
    if (element.classList.contains('disabled')) return;
    
    // إظهار التحميل
    element.style.opacity = '0.5';
    element.style.pointerEvents = 'none';
    
    // إيقاف التوقيت
    if (timers[questionId]) {
        clearInterval(timers[questionId]);
    }
    
    let timeTaken = Math.floor((Date.now() - (startTimes[questionId] || Date.now())) / 1000);
    
    // إرسال AJAX
    fetch('interactive_quiz.php?surah=<?php echo $surah_number; ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'answer_question': '1',
            'ajax': '1',
            'question_id': questionId,
            'selected_option': selectedOption,
            'time_taken': timeTaken
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // تحديث واجهة المستخدم
            let card = document.getElementById('question-' + questionId);
            let options = card.querySelectorAll('.option-item');
            
            options.forEach((opt, idx) => {
                opt.classList.add('disabled');
                if (idx == data.correct_option) {
                    opt.classList.add('correct');
                }
                if (idx == selectedOption && !data.is_correct) {
                    opt.classList.add('wrong');
                }
            });
            
            // إضافة نتيجة
            let resultBox = document.createElement('div');
            resultBox.className = 'result-box ' + (data.is_correct ? 'correct' : 'wrong');
            resultBox.innerHTML = `
                <i class="fas ${data.is_correct ? 'fa-check-circle' : 'fa-times-circle'}"></i>
                ${data.is_correct ? 'إجابة صحيحة! +' + data.points + ' نقطة' : 'إجابة خاطئة'}
                ${data.explanation ? '<div style="margin-top:10px; font-size:0.9rem;"><strong>التفسير:</strong> ' + data.explanation + '</div>' : ''}
            `;
            
            card.appendChild(resultBox);
            
            // تحديث الإحصائيات (إعادة تحميل الصفحة بعد ثانية)
            setTimeout(() => {
                location.reload();
            }, 2000);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('حدث خطأ. يرجى المحاولة مرة أخرى.');
        element.style.opacity = '1';
        element.style.pointerEvents = 'auto';
    });
}
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>