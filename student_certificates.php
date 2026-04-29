<?php
// ============================================
// ملف: student_certificates.php
// عرض شهادات الطالب
// آخر تحديث: 2026-03-13
// ============================================

require_once 'config.php';
require_once 'functions.php';
require_once 'hijri_date.php';

if (!isStudent()) {
    redirect('login.php');
}

$pageTitle = 'شهاداتي';
require_once 'includes/header.php';

$student_id = $_SESSION['user_id'];

// جلب شهادات الطالب
$certificates = $pdo->prepare("
    SELECT 
        a.*,
        g.target_surahs,
        g.target_pages,
        g.target_ayahs,
        hm.name_ar as month_name,
        g.hijri_year,
        t.name as teacher_name
    FROM student_achievements a
    JOIN student_monthly_goals g ON a.goal_id = g.id
    JOIN hijri_months hm ON g.hijri_month_id = hm.id
    LEFT JOIN teachers t ON a.approved_by = t.id
    WHERE a.student_id = ?
    ORDER BY a.achieved_at DESC
");
$certificates->execute([$student_id]);
$certificates = $certificates->fetchAll();

$current_hijri = getHijriDate();
?>

<style>
.certificates-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    padding: 25px 30px;
    border-radius: 30px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
}

.certificates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 25px;
    margin-top: 20px;
}

.certificate-card {
    background: white;
    border-radius: 25px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    border: 1px solid #eee;
    transition: 0.3s;
    position: relative;
    overflow: hidden;
}

.certificate-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
}

.certificate-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: linear-gradient(90deg, #c9a96b, #1e3c3f);
}

.certificate-icon {
    font-size: 3.5rem;
    color: #c9a96b;
    text-align: center;
    margin-bottom: 15px;
}

.certificate-month {
    font-size: 1.5rem;
    font-weight: bold;
    color: #1e3c3f;
    text-align: center;
    margin-bottom: 5px;
}

.certificate-date {
    text-align: center;
    color: #666;
    margin-bottom: 15px;
    font-size: 0.9rem;
}

.certificate-details {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 20px;
    margin: 15px 0;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px dashed #dee2e6;
    font-size: 1.1rem;
}

.detail-row:last-child {
    border-bottom: none;
}

.view-btn {
    display: block;
    background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
    color: white;
    text-align: center;
    padding: 15px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: bold;
    transition: 0.3s;
    margin-top: 20px;
    font-size: 1.1rem;
}

.view-btn:hover {
    background: linear-gradient(135deg, #2a5f5a, #1e3c3f);
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.2);
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
    background: white;
    border-radius: 30px;
    grid-column: 1 / -1;
}

.empty-state i {
    font-size: 100px;
    color: #dee2e6;
    margin-bottom: 20px;
}

.empty-state h3 {
    color: #1e3c3f;
    font-size: 2rem;
    margin-bottom: 10px;
}

.empty-state p {
    color: #666;
    font-size: 1.2rem;
    margin-bottom: 30px;
}

.empty-state .btn {
    display: inline-block;
    padding: 15px 40px;
    background: #1e3c3f;
    color: white;
    text-decoration: none;
    border-radius: 50px;
    font-size: 1.1rem;
    transition: 0.3s;
}

.empty-state .btn:hover {
    background: #2a5f5a;
    transform: translateY(-3px);
}
</style>

<section class="certificates-page">
    <div class="page-header">
        <h1><i class="fas fa-certificate"></i> شهادات الإنجاز</h1>
        <div>
            <i class="fas fa-calendar-alt"></i>
            <?php echo $current_hijri['formatted']; ?>
        </div>
    </div>

    <?php if (empty($certificates)): ?>
        <div class="empty-state">
            <i class="fas fa-certificate"></i>
            <h3>لا توجد شهادات بعد</h3>
            <p>استمر في تحقيق الأهداف الشهرية لتحصل على شهاداتك</p>
            <a href="student_dashboard.php" class="btn">العودة للرئيسية</a>
        </div>
    <?php else: ?>
        <div class="certificates-grid">
            <?php foreach ($certificates as $cert): ?>
                <div class="certificate-card">
                    <div class="certificate-icon">
                        <i class="fas fa-medal"></i>
                    </div>
                    <div class="certificate-month">
                        <?php echo $cert['month_name']; ?> <?php echo $cert['hijri_year']; ?> هـ
                    </div>
                    <div class="certificate-date">
                        <i class="far fa-calendar-alt"></i>
                        تم الإنجاز: <?php echo date('Y-m-d', strtotime($cert['achieved_at'])); ?>
                    </div>
                    
                    <div class="certificate-details">
                        <div class="detail-row">
                            <span>السور المحفوظة</span>
                            <span><strong><?php echo $cert['target_surahs']; ?></strong></span>
                        </div>
                        <div class="detail-row">
                            <span>الصفحات</span>
                            <span><strong><?php echo $cert['target_pages']; ?></strong></span>
                        </div>
                        <div class="detail-row">
                            <span>الآيات</span>
                            <span><strong><?php echo $cert['target_ayahs']; ?></strong></span>
                        </div>
                    </div>
                    
                    <div style="color: #666; font-size: 0.9rem; text-align: center; margin: 10px 0;">
                        <i class="fas fa-chalkboard-teacher"></i> 
                        المعلم: <?php echo htmlspecialchars($cert['teacher_name'] ?? 'دار التقوى'); ?>
                    </div>
                    
                    <a href="certificate_view.php?id=<?php echo $cert['id']; ?>" target="_blank" class="view-btn">
                        <i class="fas fa-eye"></i> عرض الشهادة
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>