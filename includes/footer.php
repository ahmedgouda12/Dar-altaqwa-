    <!-- شريط التقدم العلوي - يتم التحكم به من هنا -->
    <script>
        // شريط التقدم عند التمرير
        window.addEventListener('scroll', function() {
            const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
            const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrolled = (winScroll / height) * 100;
            document.getElementById('progress-bar').style.width = scrolled + '%';
        });

        // إخفاء مؤشر التحميل بعد تحميل الصفحة
        window.addEventListener('load', function() {
            setTimeout(function() {
                document.getElementById('pageLoader').classList.add('hidden');
            }, 500);
        });

        // إظهار مؤشر التحميل عند النقر على الروابط
        document.querySelectorAll('a:not(.no-loader)').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.href && !this.href.includes('#') && !this.href.includes('javascript') && !this.href.includes('logout')) {
                    document.getElementById('pageLoader').classList.remove('hidden');
                }
            });
        });

        // دالة تبديل القائمة للهاتف
        function toggleMenu() {
            document.getElementById('navbar').classList.toggle('show');
        }

        // إغلاق القائمة عند النقر على رابط
        document.querySelectorAll('.navbar a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    document.getElementById('navbar').classList.remove('show');
                }
            });
        });

        // الوضع الليلي
        const darkModeToggle = document.getElementById('darkModeToggle');
        if (darkModeToggle) {
            // استعادة التفضيل المحفوظ
            if (localStorage.getItem('darkMode') === 'true') {
                document.body.classList.add('dark-mode');
                const icon = darkModeToggle.querySelector('i');
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
            }
            
            // عند النقر على الزر
            darkModeToggle.addEventListener('click', () => {
                document.body.classList.toggle('dark-mode');
                const isDarkMode = document.body.classList.contains('dark-mode');
                localStorage.setItem('darkMode', isDarkMode);
                const icon = darkModeToggle.querySelector('i');
                if (isDarkMode) {
                    icon.classList.remove('fa-moon');
                    icon.classList.add('fa-sun');
                } else {
                    icon.classList.remove('fa-sun');
                    icon.classList.add('fa-moon');
                }
            });
        }

        // حل مشكلة الرجوع للخلف
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });

        // إضافة timestamp للروابط لمنع التخزين المؤقت
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.href && !this.href.includes('#') && !this.href.includes('javascript') && !this.href.includes('logout')) {
                    const timestamp = new Date().getTime();
                    const separator = this.href.includes('?') ? '&' : '?';
                    if (this.href.indexOf(window.location.origin) === 0) {
                        this.href = this.href + separator + '_t=' + timestamp;
                    }
                }
            });
        });
    </script>

    <!-- AOS (أنيميشن عند التمرير) -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            once: true,
            offset: 50
        });
    </script>

    <footer style="text-align: center; margin-top: 60px; padding: 30px; background: linear-gradient(135deg, #1e3c3f, #2a5f5a); color: white; border-radius: 20px; position: relative; overflow: hidden;">
        <div style="position: relative; z-index: 2;">
            <p>دار التقوى لتحفيظ القرآن الكريم - جميع الحقوق محفوظة © <?php echo date('Y'); ?></p>
            <p style="margin-top: 10px; opacity: 0.8; font-size: 0.9rem;">
                <i class="fas fa-map-marker-alt"></i> منيا القمح - الشرقية
            </p>
        </div>
    </footer>

    </div> <!-- نهاية container -->
    <script>
// ============================================
// الحفاظ على الجلسة - تحديث تلقائي كل 5 دقائق
// ============================================

let keepAliveInterval;

function keepSessionAlive() {
    fetch('keep_alive.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('✅ ' + data.message + ' - ' + data.last_activity);
            } else if (data.redirect) {
                console.log('⚠️ الجلسة منتهية، سيتم إعادة التوجيه');
                window.location.href = data.redirect;
            }
        })
        .catch(error => {
            console.log('⚠️ خطأ في تحديث الجلسة: ' + error);
        });
}

// بدء التحديث التلقائي كل 5 دقائق (300000 مللي ثانية)
// يمكنك تغيير المدة حسب الحاجة
if (typeof keepAliveInterval !== 'undefined') {
    clearInterval(keepAliveInterval);
}
keepAliveInterval = setInterval(keepSessionAlive, 300000);

// تحديث الجلسة عند تحريك الماوس أو الضغط على لوحة المفاتيح
let activityTimeout;
function resetActivityTimer() {
    clearTimeout(activityTimeout);
    activityTimeout = setTimeout(() => {
        keepSessionAlive();
    }, 60000); // تحديث بعد دقيقة من عدم النشاط
}

document.addEventListener('mousemove', resetActivityTimer);
document.addEventListener('keypress', resetActivityTimer);
document.addEventListener('click', resetActivityTimer);

console.log('✅ نظام الحفاظ على الجلسة يعمل بنجاح');
</script>
<?php 
if (file_exists('includes/prayer_notification.php')) {
    require_once 'includes/prayer_notification.php';
    echo showPrayerNotification();
}
?>
</body>
</html>