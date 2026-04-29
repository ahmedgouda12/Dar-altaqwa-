<?php
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$challenge_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// جلب معلومات التحدي
$challenge = $pdo->prepare("
    SELECT c.*, u.name as creator_name
    FROM memorization_challenges c
    LEFT JOIN users u ON c.created_by = u.id
    WHERE c.id = ?
");
$challenge->execute([$challenge_id]);
$challenge = $challenge->fetch();

if (!$challenge) {
    redirect('challenges.php');
}

// جلب نتائج التحدي
$results = $pdo->prepare("
    SELECT cr.*, s.name as student_name, t.name as teacher_name,
           DATEDIFF(cr.evaluated_at, c.start_date) as days_taken
    FROM challenge_results cr
    JOIN students s ON cr.student_id = s.id
    LEFT JOIN teachers t ON cr.evaluated_by = t.id
    JOIN memorization_challenges c ON cr.challenge_id = c.id
    WHERE cr.challenge_id = ?
    ORDER BY cr.rank_position ASC
");
$results->execute([$challenge_id]);
$results = $results->fetchAll();

$pageTitle = 'نتائج التحدي';
require_once 'includes/header.php';
?>

<style>
.results-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.challenge-info {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    padding: 30px;
    border-radius: 30px;
    margin-bottom: 30px;
}

.podium {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 40px;
    align-items: flex-end;
}

.podium-item {
    text-align: center;
    padding: 20px;
    border-radius: 20px;
    background: white;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.podium-1 { transform: scale(1.1); background: linear-gradient(145deg, #fff, #fff9e6); border: 3px solid gold; }
.podium-2 { background: linear-gradient(145deg, #fff, #f8f9fa); border: 3px solid silver; }
.podium-3 { background: linear-gradient(145deg, #fff, #f8f9fa); border: 3px solid #cd7f32; }

.podium-icon {
    font-size: 3rem;
    margin-bottom: 10px;
}

.podium-name {
    font-size: 1.3rem;
    font-weight: bold;
    color: #1e3c3f;
    margin-bottom: 5px;
}

.podium-score {
    font-size: 2rem;
    font-weight: bold;
    color: #28a745;
}

.results-table {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: #1e3c3f;
    color: white;
    padding: 15px;
}

td {
    padding: 12px;
    text-align: center;
    border-bottom: 1px solid #eee;
}

.rank-1 { background: rgba(255,215,0,0.1); }
.rank-2 { background: rgba(192,192,192,0.1); }
.rank-3 { background: rgba(205,127,50,0.1); }
</style>

<section class="results-page">
    <a href="challenges.php" class="btn" style="margin-bottom: 20px;">
        <i class="fas fa-arrow-right"></i> العودة للتحديات
    </a>

    <div class="challenge-info">
        <h1><?php echo htmlspecialchars($challenge['title']); ?></h1>
        <p><?php echo nl2br(htmlspecialchars($challenge['description'])); ?></p>
        <div style="display: flex; gap: 20px; margin-top: 15px;">
            <span><i class="fas fa-calendar"></i> من <?php echo $challenge['start_date']; ?></span>
            <span><i class="fas fa-calendar-check"></i> إلى <?php echo $challenge['end_date']; ?></span>
            <span><i class="fas fa-users"></i> <?php echo count($results); ?> مشارك</span>
        </div>
    </div>

    <?php if (!empty($results)): 
        $top3 = array_slice($results, 0, 3);
    ?>
        <!-- منصة التتويج -->
        <div class="podium">
            <?php foreach ($top3 as $index => $result): 
                $position = $index + 1;
            ?>
                <div class="podium-item podium-<?php echo $position; ?>">
                    <div class="podium-icon">
                        <?php if ($position == 1): ?>🥇
                        <?php elseif ($position == 2): ?>🥈
                        <?php else: ?>🥉
                        <?php endif; ?>
                    </div>
                    <div class="podium-name"><?php echo htmlspecialchars($result['student_name']); ?></div>
                    <div class="podium-score"><?php echo $result['evaluation_score']; ?></div>
                    <div style="color: #666;">
                        <i class="fas fa-book-open"></i> <?php echo $result['memorized_ayahs']; ?> آية<br>
                        <i class="fas fa-exclamation-circle"></i> <?php echo $result['mistakes_count']; ?> خطأ
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- جدول النتائج الكامل -->
        <div class="results-table">
            <table>
                <thead>
                    <tr>
                        <th>الترتيب</th>
                        <th>اسم الطالب</th>
                        <th>الآيات المحفوظة</th>
                        <th>عدد الأخطاء</th>
                        <th>الوقت (ساعات)</th>
                        <th>التقييم</th>
                        <th>المقيم</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $result): ?>
                        <tr class="rank-<?php echo $result['rank_position']; ?>">
                            <td><strong>#<?php echo $result['rank_position']; ?></strong></td>
                            <td><?php echo htmlspecialchars($result['student_name']); ?></td>
                            <td><?php echo $result['memorized_ayahs']; ?></td>
                            <td><?php echo $result['mistakes_count']; ?></td>
                            <td><?php echo $result['completion_time']; ?></td>
                            <td><strong style="color: #28a745;"><?php echo $result['evaluation_score']; ?></strong></td>
                            <td><?php echo htmlspecialchars($result['teacher_name'] ?? 'الإدارة'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>