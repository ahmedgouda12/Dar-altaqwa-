<?php
// ============================================
// ملف: surah_mindmap.php
// الخرائط الذهنية للسور
// آخر تحديث: 2026-03-18
// ============================================

require_once 'config.php';
require_once 'functions.php';

if (!isStudent() && !isTeacher() && !isAdmin()) {
    redirect('login.php');
}

$pageTitle = 'الخرائط الذهنية';
require_once 'includes/header.php';

$surah_number = isset($_GET['surah']) ? (int)$_GET['surah'] : 1;
$student_id = isStudent() ? $_SESSION['user_id'] : null;

// جلب معلومات السورة
$surah_info = $pdo->prepare("SELECT * FROM surah_mindmaps WHERE surah_number = ?");
$surah_info->execute([$surah_number]);
$surah_data = $surah_info->fetch();

if (!$surah_data) {
    // إذا لم توجد بيانات، نستخدم بيانات افتراضية
    $surah_data = [
        'surah_number' => $surah_number,
        'title' => 'سورة ' . getSurahName($surah_number),
        'main_topics' => '[]',
        'key_verses' => '[]',
        'connections' => '[]',
        'lessons' => '[]'
    ];
}

// جلب عقد الخريطة
$nodes = $pdo->prepare("
    SELECT * FROM mindmap_nodes 
    WHERE surah_number = ? 
    ORDER BY level, id
");
$nodes->execute([$surah_number]);
$nodes = $nodes->fetchAll();

// تجميع العقد حسب المستوى
$main_nodes = array_filter($nodes, fn($n) => $n['node_type'] == 'main');
$sub_nodes = array_filter($nodes, fn($n) => $n['node_type'] == 'sub');

// تسجيل مشاهدة الطالب
if ($student_id) {
    $progress = $pdo->prepare("
        INSERT INTO student_mindmap_progress (student_id, surah_number, viewed_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE viewed_at = NOW()
    ");
    $progress->execute([$student_id, $surah_number]);
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
    --accent: #9b59b6;
    --success: #28a745;
    --warning: #ffc107;
}

.mindmap-page {
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
}

.page-header h1 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 15px;
    font-size: 2rem;
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
}

.surah-selector select {
    background: white;
    color: var(--primary);
    padding: 8px 15px;
    border-radius: 30px;
    border: none;
    font-size: 1rem;
}

/* ===== الخريطة الذهنية ===== */
.mindmap-container {
    background: white;
    border-radius: 30px;
    padding: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
}

.mindmap-title {
    text-align: center;
    color: var(--primary);
    font-size: 2.5rem;
    margin-bottom: 40px;
    padding-bottom: 15px;
    border-bottom: 3px solid var(--secondary);
    font-family: 'Amiri', serif;
}

/* ===== شبكة العقد ===== */
.nodes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}

.main-node {
    background: linear-gradient(135deg, #f8f9fa, white);
    border-radius: 25px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    border: 2px solid transparent;
    transition: 0.3s;
    cursor: pointer;
    position: relative;
}

.main-node:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
    border-color: var(--secondary);
}

.node-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    font-size: 2rem;
    color: white;
}

.node-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 10px;
    text-align: center;
}

.node-content {
    color: #666;
    font-size: 0.95rem;
    line-height: 1.6;
    margin-bottom: 15px;
    text-align: center;
}

.ayah-range {
    display: inline-block;
    background: var(--secondary);
    color: white;
    padding: 3px 12px;
    border-radius: 30px;
    font-size: 0.85rem;
    margin-top: 10px;
}

/* ===== العقد الفرعية ===== */
.sub-nodes {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin: 20px 0;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 20px;
}

.sub-node {
    flex: 1 1 200px;
    background: white;
    border-radius: 15px;
    padding: 15px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.05);
    border-right: 4px solid var(--secondary);
}

.sub-node-title {
    font-weight: 600;
    color: var(--primary);
    margin-bottom: 8px;
}

.sub-node-content {
    color: #666;
    font-size: 0.9rem;
}

/* ===== معلومات إضافية ===== */
.info-section {
    background: #f8f9fa;
    border-radius: 20px;
    padding: 25px;
    margin: 30px 0;
}

.info-title {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--primary);
    margin-bottom: 20px;
    font-size: 1.3rem;
}

.info-title i {
    color: var(--secondary);
}

.topics-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 20px;
}

.topic-tag {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 8px 20px;
    border-radius: 50px;
    font-size: 0.95rem;
}

.verses-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.verse-item {
    background: white;
    border-radius: 15px;
    padding: 15px;
    border-right: 4px solid var(--secondary);
}

.connections-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.connection-item {
    background: var(--warning);
    color: #212529;
    padding: 8px 20px;
    border-radius: 50px;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* ===== أسئلة تفاعلية سريعة ===== */
.quick-quiz {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border-radius: 30px;
    padding: 30px;
    margin-top: 30px;
    position: relative;
    overflow: hidden;
}

.quick-quiz::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: rotate 20s linear infinite;
}

.quiz-question {
    font-size: 1.3rem;
    margin-bottom: 20px;
    position: relative;
    z-index: 2;
}

.quiz-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    position: relative;
    z-index: 2;
}

.quiz-option {
    background: rgba(255,255,255,0.15);
    border: 2px solid rgba(255,255,255,0.2);
    border-radius: 15px;
    padding: 15px;
    cursor: pointer;
    transition: 0.3s;
    text-align: center;
}

.quiz-option:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-3px);
}

.quiz-option.correct {
    background: var(--success);
    border-color: white;
}

.quiz-option.wrong {
    background: #dc3545;
    border-color: white;
}

.quiz-feedback {
    margin-top: 20px;
    padding: 15px;
    border-radius: 15px;
    background: rgba(255,255,255,0.1);
    position: relative;
    z-index: 2;
}

/* ===== تحسينات للهاتف ===== */
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        text-align: center;
    }
    
    .surah-selector {
        margin-right: 0;
        width: 100%;
    }
    
    .nodes-grid {
        grid-template-columns: 1fr;
    }
    
    .quiz-options {
        grid-template-columns: 1fr;
    }
}
</style>

<section class="mindmap-page">
    <!-- رأس الصفحة -->
    <div class="page-header">
        <h1>
            <i class="fas fa-map"></i>
            الخرائط الذهنية
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

    <!-- الخريطة الذهنية -->
    <div class="mindmap-container">
        <h2 class="mindmap-title"><?php echo $surah_data['title']; ?></h2>

        <!-- العقد الرئيسية -->
        <div class="nodes-grid">
            <?php foreach ($main_nodes as $node): 
                $json_content = json_decode($node['content'], true) ?: $node['content'];
            ?>
                <div class="main-node" onclick="toggleSubNodes(<?php echo $node['id']; ?>)">
                    <div class="node-icon" style="background: <?php echo $node['color']; ?>;">
                        <i class="fas <?php echo $node['icon']; ?>"></i>
                    </div>
                    <div class="node-title"><?php echo $node['title']; ?></div>
                    <div class="node-content">
                        <?php echo is_array($json_content) ? implode(' - ', $json_content) : $json_content; ?>
                    </div>
                    <?php if ($node['ayah_range']): ?>
                        <div class="ayah-range">الآيات: <?php echo $node['ayah_range']; ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- العقد الفرعية -->
        <?php if (!empty($sub_nodes)): ?>
            <div class="sub-nodes">
                <?php foreach ($sub_nodes as $node): ?>
                    <div class="sub-node">
                        <div class="sub-node-title">
                            <i class="fas fa-circle" style="color: <?php echo $node['color']; ?>; font-size: 0.5rem;"></i>
                            <?php echo $node['title']; ?>
                        </div>
                        <div class="sub-node-content"><?php echo $node['content']; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- معلومات إضافية -->
        <div class="info-section">
            <div class="info-title">
                <i class="fas fa-tags"></i>
                <h3>المواضيع الرئيسية</h3>
            </div>
            <div class="topics-list">
                <?php 
                $topics = json_decode($surah_data['main_topics'], true) ?: [];
                foreach ($topics as $topic): 
                ?>
                    <span class="topic-tag"><?php echo $topic; ?></span>
                <?php endforeach; ?>
            </div>

            <div class="info-title" style="margin-top: 30px;">
                <i class="fas fa-link"></i>
                <h3>الآيات الرئيسية</h3>
            </div>
            <div class="verses-list">
                <?php 
                $verses = json_decode($surah_data['key_verses'], true) ?: [];
                foreach ($verses as $verse): 
                ?>
                    <div class="verse-item"><?php echo $verse; ?></div>
                <?php endforeach; ?>
            </div>

            <div class="info-title" style="margin-top: 30px;">
                <i class="fas fa-project-diagram"></i>
                <h3>روابط مع سور أخرى</h3>
            </div>
            <div class="connections-list">
                <?php 
                $connections = json_decode($surah_data['connections'], true) ?: [];
                foreach ($connections as $conn): 
                ?>
                    <span class="connection-item">
                        <i class="fas fa-link"></i>
                        <?php echo $conn; ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- اختبار سريع -->
        <div class="quick-quiz">
            <div class="quiz-question" id="quizQuestion">
                <i class="fas fa-question-circle"></i>
                ما هو الموضوع الرئيسي في سورة <?php echo getSurahName($surah_number); ?>؟
            </div>
            <div class="quiz-options" id="quizOptions">
                <?php 
                $quiz_options = [
                    'التوحيد',
                    'القصص',
                    'الأحكام',
                    'العبادات'
                ];
                shuffle($quiz_options);
                foreach ($quiz_options as $option): 
                ?>
                    <div class="quiz-option" onclick="checkAnswer(this, '<?php echo $topics[0] ?? 'التوحيد'; ?>')">
                        <?php echo $option; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="quiz-feedback" id="quizFeedback" style="display: none;"></div>
        </div>
    </div>
</section>

<script>
// تبديل العقد الفرعية
function toggleSubNodes(nodeId) {
    // يمكن إضافة تأثيرات هنا
}

// التحقق من الإجابة
function checkAnswer(element, correctAnswer) {
    let options = document.querySelectorAll('.quiz-option');
    let feedback = document.getElementById('quizFeedback');
    
    options.forEach(opt => {
        opt.style.pointerEvents = 'none';
    });
    
    if (element.textContent.trim() === correctAnswer) {
        element.classList.add('correct');
        feedback.innerHTML = '✅ إجابة صحيحة! أحسنت.';
        feedback.style.display = 'block';
    } else {
        element.classList.add('wrong');
        feedback.innerHTML = '❌ إجابة خاطئة. حاول مرة أخرى مع سورة أخرى.';
        feedback.style.display = 'block';
        
        // إظهار الإجابة الصحيحة
        options.forEach(opt => {
            if (opt.textContent.trim() === correctAnswer) {
                opt.classList.add('correct');
            }
        });
    }
}

// تأثيرات حركية
document.querySelectorAll('.main-node').forEach(node => {
    node.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-5px)';
    });
    
    node.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>