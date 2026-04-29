<?php
require_once 'config.php';
if (!isStudent()) redirect('login.php');
$pageTitle = 'خطة الحفظ الذكية';
require_once 'includes/header.php';

$student_id = $_SESSION['user_id'];
?>

<section class="card">
    <h2 class="card-title"><i class="fas fa-robot"></i> خطة الحفظ الذكية</h2>
    <p>هذه الخطة مبنية على أدائك السابق والأخطاء التي سجلها معلمك.</p>
    
    <div id="loading" style="text-align: center; padding: 40px;">
        <i class="fas fa-spinner fa-spin" style="font-size: 40px;"></i>
        <p>جاري تحليل بياناتك وإنشاء خطتك المخصصة...</p>
    </div>
    
    <div id="plan-content" style="display: none;"></div>
</section>

<script>
fetch('ai_plan.php?student_id=<?php echo $student_id; ?>')
    .then(response => response.json())
    .then(data => {
        document.getElementById('loading').style.display = 'none';
        
        let html = `
            <div style="background: #e8f5e9; padding: 20px; border-radius: 15px; margin-bottom: 20px;">
                <h3>مستوى الطالب: ${data.level}</h3>
                <p>عدد الآيات المقترحة يومياً: ${data.daily_ayahs} آية</p>
            </div>
            <h3>📋 خطة اليوم:</h3>
            <div style="display: grid; gap: 15px; margin-bottom: 20px;">
        `;
        
        data.plan.forEach(item => {
            if (item.type === 'new' || item.type === 'review') {
                html += `
                    <div style="background: white; padding: 15px; border-radius: 10px; border-right: 5px solid ${item.type === 'new' ? '#28a745' : '#ffc107'};">
                        <strong>${item.type === 'new' ? '🆕 حفظ جديد' : '🔄 مراجعة'}</strong>
                        <p>${item.description}</p>
                        <small>${item.surah || ''} من آية ${item.from_ayah} إلى ${item.to_ayah}</small>
                    </div>
                `;
            } else if (item.type === 'recommendation') {
                html += `
                    <div style="background: #fff3cd; padding: 15px; border-radius: 10px; border-right: 5px solid #ffc107;">
                        <strong>🎯 توصية:</strong>
                        <p>${item.description}</p>
                    </div>
                `;
            } else if (item.type === 'advice') {
                html += `
                    <div style="background: #d1ecf1; padding: 15px; border-radius: 10px; border-right: 5px solid #17a2b8;">
                        <strong>💡 نصيحة:</strong>
                        <p>${item.description}</p>
                    </div>
                `;
            }
        });
        
        html += `</div>`;
        
        if (data.mistakes.length > 0) {
            html += `
                <div class="card">
                    <h4>⚠️ أخطاء تحتاج متابعة:</h4>
                    <ul>
                        ${data.mistakes.map(m => `<li>${m}</li>`).join('')}
                    </ul>
                </div>
            `;
        }
        
        document.getElementById('plan-content').innerHTML = html;
        document.getElementById('plan-content').style.display = 'block';
    });
</script>

<?php require_once 'includes/footer.php'; ?>