<?php
// ============================================
// ملف: courses_list.php - قائمة الدورات المتاحة (تصميم مميز)
// التسجيل مفتوح لجميع الدورات النشطة بغض النظر عن تاريخ البداية
// ============================================

ob_start();
require_once 'config.php';

$pageTitle = 'الدورات المتاحة - دار التقوى';

// جلب الدورات النشطة (جميعها، بغض النظر عن التواريخ)
$courses = $pdo->query("
    SELECT c.*, t.name as teacher_name,
           (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id) as enrolled_count
    FROM courses c
    LEFT JOIN teachers t ON c.teacher_id = t.id
    WHERE c.status = 'active'
    ORDER BY c.start_date
")->fetchAll();

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5">
    <title>الدورات المتاحة - دار التقوى</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&family=Amiri:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #1e3c3f;
            --primary-light: #2a5f5a;
            --primary-dark: #0a2a2c;
            --secondary: #c9a96b;
            --secondary-light: #dbb87c;
            --secondary-dark: #b38b4a;
            --success: #28a745;
            --success-light: #d4edda;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --info-light: #d1ecf1;
            --purple: #6f42c1;
            --purple-light: #e9d8fd;
            --orange: #fd7e14;
            --teal: #20c997;
            
            --gradient-primary: linear-gradient(135deg, #1e3c3f, #2a5f5a);
            --gradient-secondary: linear-gradient(135deg, #c9a96b, #dbb87c);
            --gradient-success: linear-gradient(135deg, #28a745, #20c997);
            --gradient-info: linear-gradient(135deg, #17a2b8, #138496);
            --gradient-purple: linear-gradient(135deg, #6f42c1, #9b59b6);
            --gradient-orange: linear-gradient(135deg, #fd7e14, #ffc107);
            
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 8px rgba(0,0,0,0.1);
            --shadow-lg: 0 8px 16px rgba(0,0,0,0.15);
            --shadow-xl: 0 12px 24px rgba(0,0,0,0.2);
            --shadow-2xl: 0 20px 40px rgba(0,0,0,0.25);
            --shadow-gold: 0 5px 15px rgba(201, 169, 107, 0.3);
            --shadow-gold-hover: 0 8px 25px rgba(201, 169, 107, 0.5);
            
            --border-radius-sm: 8px;
            --border-radius-md: 12px;
            --border-radius-lg: 20px;
            --border-radius-xl: 30px;
            --border-radius-2xl: 40px;
            --border-radius-full: 9999px;
            
            --transition: 0.3s ease;
            --transition-bounce: 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Cairo', 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            min-height: 100vh;
            position: relative;
        }
        
        /* خلفية زخرفية */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: radial-gradient(circle at 10% 20%, rgba(201, 169, 107, 0.03) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
        }
        
        body::after {
            content: "﷽";
            position: fixed;
            bottom: 20px;
            right: 20px;
            font-size: 120px;
            font-family: 'Amiri', serif;
            color: var(--secondary);
            opacity: 0.03;
            transform: rotate(-10deg);
            pointer-events: none;
            z-index: -1;
        }
        
        /* ===== رأس الصفحة ===== */
        .courses-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 60px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .courses-header::before {
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
        
        .courses-header h1 {
            font-size: 3rem;
            margin-bottom: 15px;
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            animation: fadeInDown 0.8s ease;
        }
        
        .courses-header h1 i {
            color: var(--secondary);
            animation: starPulse 2s infinite;
        }
        
        @keyframes starPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .courses-header p {
            font-size: 1.2rem;
            opacity: 0.9;
            position: relative;
            z-index: 2;
            animation: fadeInUp 0.8s ease;
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* ===== زر العودة ===== */
        .back-home {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(5px);
            padding: 10px 25px;
            border-radius: 50px;
            color: white;
            text-decoration: none;
            margin-top: 20px;
            transition: var(--transition);
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .back-home:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-3px);
        }
        
        /* ===== الحاوية الرئيسية ===== */
        .courses-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 50px 20px;
        }
        
        /* ===== إحصائيات سريعة ===== */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin-bottom: 50px;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--border-radius-xl);
            padding: 25px;
            text-align: center;
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: var(--gradient-secondary);
        }
        
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-2xl);
        }
        
        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2rem;
            color: white;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
        }
        
        .stat-label {
            color: #666;
            font-size: 1rem;
            font-weight: 600;
        }
        
        /* ===== شبكة الدورات ===== */
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 35px;
        }
        
        /* ===== بطاقة الدورة المميزة ===== */
        .course-card {
            background: white;
            border-radius: var(--border-radius-2xl);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            transition: var(--transition-bounce);
            position: relative;
            animation: fadeInUp 0.5s ease;
            animation-fill-mode: both;
        }
        
        .course-card:hover {
            transform: translateY(-12px);
            box-shadow: var(--shadow-2xl);
        }
        
        /* شريط الحالة العلوي */
        .course-status-bar {
            height: 6px;
            background: var(--gradient-secondary);
        }
        
        .course-card.full .course-status-bar {
            background: linear-gradient(90deg, #dc3545, #ff6b6b);
        }
        
        /* رأس البطاقة */
        .course-header {
            padding: 25px 25px 15px;
            position: relative;
        }
        
        .course-badge {
            position: absolute;
            top: 20px;
            left: 20px;
            background: var(--gradient-secondary);
            color: var(--primary-dark);
            padding: 5px 15px;
            border-radius: var(--border-radius-full);
            font-size: 0.8rem;
            font-weight: 700;
            z-index: 2;
            box-shadow: var(--shadow-sm);
        }
        
        .course-badge.full {
            background: linear-gradient(135deg, #dc3545, #ff6b6b);
            color: white;
        }
        
        .course-type {
            display: inline-block;
            padding: 5px 12px;
            border-radius: var(--border-radius-full);
            font-size: 0.75rem;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .course-type.tajweed { background: #e8f5e9; color: #2e7d32; }
        .course-type.tafsir { background: #e3f2fd; color: #1565c0; }
        .course-type.qiraat { background: #f3e5f5; color: #6a1b9a; }
        .course-type.arabic { background: #fff3e0; color: #ef6c00; }
        .course-type.memorization { background: #e0f2fe; color: #0284c7; }
        .course-type.other { background: #f1f8e9; color: #558b2f; }
        
        .course-title {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--primary);
            margin: 10px 0;
            line-height: 1.3;
        }
        
        .course-description {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.6;
            margin: 15px 0;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* تفاصيل الدورة */
        .course-details {
            background: #f8f9fa;
            padding: 20px;
            margin: 15px 20px;
            border-radius: var(--border-radius-lg);
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px dashed #e9ecef;
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .detail-icon {
            width: 35px;
            height: 35px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--secondary);
            font-size: 1rem;
            box-shadow: var(--shadow-sm);
        }
        
        .detail-content {
            flex: 1;
        }
        
        .detail-label {
            font-size: 0.75rem;
            color: #999;
            text-transform: uppercase;
        }
        
        .detail-value {
            font-weight: 700;
            color: var(--primary);
            font-size: 0.95rem;
        }
        
        /* عداد المقاعد */
        .seats-counter {
            margin: 20px;
            background: linear-gradient(135deg, #f8f9fa, white);
            border-radius: var(--border-radius-lg);
            padding: 15px;
            text-align: center;
        }
        
        .seats-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--success);
        }
        
        .seats-label {
            color: #666;
            font-size: 0.85rem;
        }
        
        .seats-progress {
            height: 8px;
            background: #e9ecef;
            border-radius: 10px;
            margin: 10px 0;
            overflow: hidden;
        }
        
        .seats-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success), #20c997);
            border-radius: 10px;
            transition: width 0.5s;
        }
        
        .seats-fill.warning {
            background: linear-gradient(90deg, #ffc107, #ffdb58);
        }
        
        .seats-fill.danger {
            background: linear-gradient(90deg, #dc3545, #ff6b6b);
        }
        
        /* السعر */
        .course-price {
            margin: 15px 20px;
            padding: 15px;
            background: linear-gradient(135deg, var(--gradient-purple), var(--gradient-orange));
            border-radius: var(--border-radius-lg);
            text-align: center;
            color: white;
        }
        
        .course-price.price-pending {
            background: linear-gradient(135deg, #6c757d, #5a6268);
        }
        
        .price-amount {
            font-size: 1.8rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .price-label {
            font-size: 0.85rem;
            opacity: 0.9;
        }
        
        /* زر التسجيل */
        .btn-register {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin: 20px 20px 25px;
            padding: 15px;
            background: var(--gradient-primary);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius-full);
            font-weight: 700;
            font-size: 1.1rem;
            transition: var(--transition-bounce);
            border: none;
            cursor: pointer;
            width: calc(100% - 40px);
        }
        
        .btn-register:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-gold-hover);
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
        }
        
        .btn-register.disabled {
            background: #6c757d;
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        .btn-register.disabled:hover {
            transform: none;
            box-shadow: none;
        }
        
        /* نص توضيحي لتاريخ بدء الدورة */
        .course-start-note {
            text-align: center;
            margin-top: 10px;
            font-size: 0.8rem;
            color: var(--secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        /* ===== حالة عدم وجود دورات ===== */
        .empty-state {
            text-align: center;
            padding: 80px 40px;
            background: white;
            border-radius: var(--border-radius-2xl);
            box-shadow: var(--shadow-lg);
            animation: fadeInUp 0.5s ease;
        }
        
        .empty-state-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 4rem;
            color: var(--secondary);
        }
        
        .empty-state h3 {
            font-size: 1.8rem;
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        .empty-state p {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }
        
        .btn-home {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--gradient-primary);
            color: white;
            padding: 12px 30px;
            border-radius: var(--border-radius-full);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .btn-home:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-gold);
        }
        
        /* ===== تذييل الصفحة ===== */
        .courses-footer {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            color: white;
            text-align: center;
            padding: 40px;
            margin-top: 60px;
            position: relative;
            overflow: hidden;
        }
        
        .courses-footer::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }
        
        .courses-footer p {
            position: relative;
            z-index: 2;
        }
        
        /* ===== تحسينات للهاتف ===== */
        @media (max-width: 992px) {
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .courses-header h1 {
                font-size: 2rem;
            }
            
            .stats-row {
                grid-template-columns: 1fr;
            }
            
            .courses-grid {
                grid-template-columns: 1fr;
            }
            
            .course-title {
                font-size: 1.3rem;
            }
        }
        
        @media (max-width: 480px) {
            .courses-header h1 {
                font-size: 1.5rem;
            }
            
            .courses-header p {
                font-size: 1rem;
            }
            
            .stat-number {
                font-size: 1.8rem;
            }
        }
        
        /* ===== تأثيرات حركية إضافية ===== */
        @keyframes shimmer {
            0% { background-position: -1000px 0; }
            100% { background-position: 1000px 0; }
        }
        
        .loading-shimmer {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 1000px 100%;
            animation: shimmer 2s infinite;
        }
    </style>
</head>
<body>

<div class="courses-header">
    <h1>
        <i class="fas fa-graduation-cap"></i>
        الدورات المتاحة
    </h1>
    <p>اختر الدورة المناسبة لك وسجل الآن، واستثمر وقتك في العلم النافع</p>
    <a href="index.php" class="back-home">
        <i class="fas fa-home"></i>
        العودة للرئيسية
    </a>
</div>

<div class="courses-container">
    <?php 
    $total_courses = count($courses);
    $total_seats = array_sum(array_column($courses, 'max_students'));
    $total_enrolled = array_sum(array_column($courses, 'enrolled_count'));
    $available_seats = $total_seats - $total_enrolled;
    $types_count = array_count_values(array_column($courses, 'course_type'));
    ?>
    
    <!-- إحصائيات سريعة -->
    <div class="stats-row">
        <div class="stat-card" data-aos="fade-up" data-aos-delay="0">
            <div class="stat-icon" style="background: linear-gradient(135deg, #1e3c3f, #2a5f5a);">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div class="stat-number"><?php echo $total_courses; ?></div>
            <div class="stat-label">دورة متاحة</div>
        </div>
        <div class="stat-card" data-aos="fade-up" data-aos-delay="100">
            <div class="stat-icon" style="background: linear-gradient(135deg, #c9a96b, #dbb87c);">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-number"><?php echo $total_enrolled; ?></div>
            <div class="stat-label">مسجل حالياً</div>
        </div>
        <div class="stat-card" data-aos="fade-up" data-aos-delay="200">
            <div class="stat-icon" style="background: linear-gradient(135deg, #28a745, #20c997);">
                <i class="fas fa-chair"></i>
            </div>
            <div class="stat-number"><?php echo $available_seats; ?></div>
            <div class="stat-label">مقعد متاح</div>
        </div>
        <div class="stat-card" data-aos="fade-up" data-aos-delay="300">
            <div class="stat-icon" style="background: linear-gradient(135deg, #17a2b8, #138496);">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-number"><?php echo count($types_count); ?></div>
            <div class="stat-label">نوع من الدورات</div>
        </div>
    </div>
    
    <?php if (empty($courses)): ?>
        <!-- حالة عدم وجود دورات -->
        <div class="empty-state" data-aos="fade-up">
            <div class="empty-state-icon">
                <i class="fas fa-calendar-times"></i>
            </div>
            <h3>لا توجد دورات متاحة حالياً</h3>
            <p>سيتم الإعلان عن دورات جديدة قريباً، تابعونا على وسائل التواصل الاجتماعي</p>
            <a href="index.php" class="btn-home">
                <i class="fas fa-home"></i>
                العودة للرئيسية
            </a>
        </div>
    <?php else: ?>
        <!-- شبكة الدورات -->
        <div class="courses-grid">
            <?php foreach ($courses as $index => $course): 
                $remaining = $course['max_students'] - $course['enrolled_count'];
                $percentage = ($course['max_students'] > 0) ? ($course['enrolled_count'] / $course['max_students']) * 100 : 0;
                $is_full = ($remaining <= 0);
                // تعطيل شرط "قريباً" - التسجيل مفتوح دائماً للدورات النشطة
                $is_soon = false;
                $is_future = (strtotime($course['start_date']) > strtotime('now'));
                
                // تحديد لون شريط التقدم
                $fill_class = '';
                if ($percentage >= 90) $fill_class = 'danger';
                elseif ($percentage >= 70) $fill_class = 'warning';
                
                // تحديد نوع الدورة للتصنيف
                $type_class = $course['course_type'];
                $type_name = [
                    'tajweed' => 'تجويد',
                    'tafsir' => 'تفسير',
                    'qiraat' => 'قراءات',
                    'arabic' => 'لغة عربية',
                    'memorization' => 'تحفيظ',
                    'other' => 'أخرى'
                ][$course['course_type']] ?? $course['course_type'];
                
                // تحديد لون البطاقة
                $card_class = '';
                if ($is_full) $card_class = 'full';
            ?>
                <div class="course-card <?php echo $card_class; ?>" data-aos="fade-up" data-aos-delay="<?php echo $index * 50; ?>">
                    <div class="course-status-bar"></div>
                    
                    <div class="course-header">
                        <div class="course-badge <?php echo $is_full ? 'full' : ''; ?>">
                            <?php if ($is_full): ?>
                                <i class="fas fa-times-circle"></i> اكتمل العدد
                            <?php else: ?>
                                <i class="fas fa-check-circle"></i> متاح
                            <?php endif; ?>
                        </div>
                        
                        <div class="course-type <?php echo $type_class; ?>">
                            <i class="fas <?php 
                                echo $course['course_type'] == 'tajweed' ? 'fa-microphone-alt' : 
                                    ($course['course_type'] == 'tafsir' ? 'fa-book-open' : 
                                    ($course['course_type'] == 'qiraat' ? 'fa-quran' : 
                                    ($course['course_type'] == 'arabic' ? 'fa-language' : 
                                    ($course['course_type'] == 'memorization' ? 'fa-brain' : 'fa-star')))); 
                            ?>"></i>
                            <?php echo $type_name; ?>
                        </div>
                        
                        <h3 class="course-title"><?php echo htmlspecialchars($course['course_name']); ?></h3>
                        
                        <?php if (!empty($course['course_description'])): ?>
                            <p class="course-description"><?php echo nl2br(htmlspecialchars(mb_substr($course['course_description'], 0, 120))); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="course-details">
                        <div class="detail-item">
                            <div class="detail-icon">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                            <div class="detail-content">
                                <div class="detail-label">المعلم المشرف</div>
                                <div class="detail-value"><?php echo $course['teacher_name'] ?? 'سيتم تحديده قريباً'; ?></div>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="detail-content">
                                <div class="detail-label">مدة الدورة</div>
                                <div class="detail-value"><?php echo $course['start_date']; ?> - <?php echo $course['end_date']; ?></div>
                            </div>
                        </div>
                        
                        <?php if ($course['start_time']): ?>
                        <div class="detail-item">
                            <div class="detail-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="detail-content">
                                <div class="detail-label">وقت الانعقاد</div>
                                <div class="detail-value"><?php echo $course['start_time']; ?> - <?php echo $course['end_time']; ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($course['days_of_week']): 
                            $days_map = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
                            $days_array = explode(',', $course['days_of_week']);
                            $days_text = [];
                            foreach ($days_array as $d) {
                                if (isset($days_map[$d-1])) $days_text[] = $days_map[$d-1];
                            }
                        ?>
                        <div class="detail-item">
                            <div class="detail-icon">
                                <i class="fas fa-calendar-week"></i>
                            </div>
                            <div class="detail-content">
                                <div class="detail-label">أيام الانعقاد</div>
                                <div class="detail-value"><?php echo implode(' - ', $days_text); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($course['location']): ?>
                        <div class="detail-item">
                            <div class="detail-icon">
                                <i class="fas fa-location-dot"></i>
                            </div>
                            <div class="detail-content">
                                <div class="detail-label">المكان</div>
                                <div class="detail-value"><?php echo htmlspecialchars($course['location']); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- عداد المقاعد -->
                    <div class="seats-counter">
                        <div class="seats-number"><?php echo $remaining; ?></div>
                        <div class="seats-label">مقعد متاح من <?php echo $course['max_students']; ?></div>
                        <div class="seats-progress">
                            <div class="seats-fill <?php echo $fill_class; ?>" style="width: <?php echo $percentage; ?>%;"></div>
                        </div>
                    </div>
                    
                    <!-- السعر -->
                    <div class="course-price <?php echo $course['price'] == 0 ? 'price-pending' : ''; ?>">
                        <?php if ($course['price'] > 0): ?>
                            <div class="price-amount"><?php echo number_format($course['price'], 2); ?> ج.م</div>
                            <div class="price-label">رسوم الدورة</div>
                        <?php else: ?>
                            <div class="price-amount">
                                <i class="fas fa-clock"></i> لم يتم التحديد بعد
                            </div>
                            <div class="price-label">سيتم الإعلان لاحقاً</div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- زر التسجيل -->
                    <?php if ($is_full): ?>
                        <div class="btn-register disabled">
                            <i class="fas fa-times-circle"></i> اكتمل العدد
                        </div>
                    <?php else: ?>
                        <a href="course_register.php?course_id=<?php echo $course['id']; ?>" class="btn-register">
                            <i class="fas fa-pen-alt"></i> سجل الآن
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        <?php if ($is_future): ?>
                            <div class="course-start-note">
                                <i class="fas fa-info-circle"></i>
                                التسجيل مفتوح الآن، الدورة تبدأ في <?php echo $course['start_date']; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="courses-footer">
    <p>دار التقوى لتحفيظ القرآن الكريم - منيا القمح - الشرقية</p>
    <p style="margin-top: 10px; opacity: 0.8; font-size: 0.9rem;">
        <i class="fas fa-phone"></i> للاستفسار: 01234567890
    </p>
</div>

<!-- AOS Animation -->
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({
        duration: 800,
        once: true,
        offset: 50
    });
    // تأثيرات إضافية عند التمرير
    window.addEventListener('scroll', function() {
        const cards = document.querySelectorAll('.course-card');
        cards.forEach((card, index) => {
            const rect = card.getBoundingClientRect();
            if (rect.top < window.innerHeight - 100) {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }
        });
    });
</script>

</body>
</html>