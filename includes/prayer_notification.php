<?php
// ============================================
// ملف: includes/prayer_notification.php
// تذكير بالصلاة على النبي ﷺ كل 20 دقيقة
// يظهر مرة واحدة فقط عند فتح الموقع
// ============================================

function showPrayerNotification() {
    // التحقق من تسجيل الدخول
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
        return '';
    }
    
    $css = '
    <style>
        .prayer-notification {
            position: fixed;
            bottom: 30px;
            left: 30px;
            z-index: 100000;
            animation: slideInRight 0.5s ease;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        .prayer-notification.closing {
            animation: slideOutRight 0.5s ease forwards;
        }
        
        .prayer-card {
            background: linear-gradient(145deg, #ffffff, #fefefa);
            border-radius: 20px;
            width: 320px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            border: 1px solid rgba(201,169,107,0.3);
            overflow: hidden;
            direction: rtl;
        }
        
        .prayer-header {
            background: linear-gradient(135deg, #1e3c3f, #2a5f5a);
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .prayer-header-icon {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #c9a96b;
        }
        
        .prayer-header-icon i {
            font-size: 1.2rem;
            color: #c9a96b;
        }
        
        .prayer-header-title {
            flex: 1;
        }
        
        .prayer-header-title h4 {
            color: white;
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
        }
        
        .prayer-header-title p {
            color: rgba(255,255,255,0.7);
            margin: 3px 0 0;
            font-size: 0.7rem;
        }
        
        .prayer-close {
            background: none;
            border: none;
            color: rgba(255,255,255,0.6);
            font-size: 1.2rem;
            cursor: pointer;
            transition: 0.3s;
        }
        
        .prayer-close:hover {
            color: white;
            transform: rotate(90deg);
        }
        
        .prayer-body {
            padding: 25px 20px;
            text-align: center;
        }
        
        .prayer-phrase {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1e3c3f;
            margin-bottom: 15px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 50px;
            border-right: 3px solid #c9a96b;
        }
        
        .prayer-dua {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e3c3f;
            margin: 15px 0;
            padding: 10px;
        }
        
        .prayer-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #c9a96b, #dbb87c);
            color: #1e3c3f;
            border: none;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .prayer-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(201,169,107,0.3);
        }
        
        .prayer-timer {
            margin-top: 12px;
            font-size: 0.7rem;
            color: #999;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .prayer-timer span {
            font-weight: bold;
            color: #c9a96b;
        }
        
        @media (max-width: 550px) {
            .prayer-card {
                width: 280px;
            }
            .prayer-phrase {
                font-size: 1rem;
            }
        }
    </style>
    ';
    
    $html = '
    <div id="prayerNotification" class="prayer-notification" style="display: none;">
        <div class="prayer-card">
            <div class="prayer-header">
                <div class="prayer-header-icon">
                    <i class="fas fa-star-and-crescent"></i>
                </div>
                <div class="prayer-header-title">
                    <h4>تذكير بالصلاة على النبي ﷺ</h4>
                    <p>اللهم صل على سيدنا محمد</p>
                </div>
                <button class="prayer-close" onclick="closePrayerNotification()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="prayer-body">
                <div class="prayer-phrase">
                    🤲 ﷺ
                </div>
                <div class="prayer-dua">
                    اللهم صل على محمد وعلى آل محمد<br>
                    كما صليت على إبراهيم وعلى آل إبراهيم<br>
                    إنك حميد مجيد
                </div>
                <button class="prayer-btn" onclick="closePrayerNotification()">
                    <i class="fas fa-check"></i> تم
                </button>
                <div class="prayer-timer">
                    <i class="fas fa-clock"></i>
                    سيختفي الإشعار خلال <span id="timerSeconds">10</span> ثوانٍ
                </div>
            </div>
        </div>
    </div>
    ';
    
    $js = '
    <script>
    // ============================================
    // تذكير بالصلاة على النبي ﷺ كل 20 دقيقة
    // يظهر مرة واحدة عند فتح الموقع ثم يتكرر كل 20 دقيقة
    // ============================================
    
    (function() {
        var timerInterval;
        var closeTimerInterval;
        var secondsLeft = 10;
        var isClosing = false;
        var notificationVisible = false;
        
        // المفتاح المستخدم في localStorage
        var LAST_SHOWN_KEY = "prayer_last_shown_time";
        var INTERVAL_MINUTES = 20; // 20 دقيقة
        
        function updateTimerDisplay() {
            var timerSpan = document.getElementById("timerSeconds");
            if (timerSpan) {
                timerSpan.innerHTML = secondsLeft;
            }
        }
        
        function startCloseTimer() {
            if (closeTimerInterval) clearInterval(closeTimerInterval);
            secondsLeft = 10;
            updateTimerDisplay();
            
            closeTimerInterval = setInterval(function() {
                if (secondsLeft <= 1) {
                    clearInterval(closeTimerInterval);
                    if (!isClosing && notificationVisible) {
                        closePrayerNotification();
                    }
                } else {
                    secondsLeft--;
                    updateTimerDisplay();
                }
            }, 1000);
        }
        
        window.closePrayerNotification = function() {
            if (isClosing) return;
            isClosing = true;
            notificationVisible = false;
            
            if (closeTimerInterval) clearInterval(closeTimerInterval);
            if (timerInterval) clearInterval(timerInterval);
            
            var notification = document.getElementById("prayerNotification");
            if (notification) {
                notification.classList.add("closing");
                setTimeout(function() {
                    notification.style.display = "none";
                    notification.classList.remove("closing");
                    isClosing = false;
                }, 500);
            }
        };
        
        function showNotification() {
            if (notificationVisible) return;
            
            var notification = document.getElementById("prayerNotification");
            if (notification) {
                notification.style.display = "block";
                notificationVisible = true;
                isClosing = false;
                startCloseTimer();
            }
        }
        
        function shouldShowNotification() {
            var lastShown = localStorage.getItem(LAST_SHOWN_KEY);
            if (!lastShown) {
                return true;
            }
            
            var lastShownTime = parseInt(lastShown);
            var now = Date.now();
            var minutesPassed = (now - lastShownTime) / (1000 * 60);
            
            return minutesPassed >= INTERVAL_MINUTES;
        }
        
        function updateLastShownTime() {
            localStorage.setItem(LAST_SHOWN_KEY, Date.now().toString());
        }
        
        function checkAndShow() {
            if (shouldShowNotification()) {
                showNotification();
                updateLastShownTime();
            }
        }
        
        // بدء المؤقت: يفحص كل دقيقة إذا كان الوقت قد حان لإظهار الإشعار
        function startNotificationTimer() {
            // فحص فوري عند تحميل الصفحة
            setTimeout(function() {
                checkAndShow();
            }, 2000); // تأخير 2 ثانية لتحميل الصفحة
            
            // ثم يفحص كل دقيقة
            timerInterval = setInterval(function() {
                checkAndShow();
            }, 60000); // كل 60 ثانية
        }
        
        // بدء النظام عند تحميل الصفحة
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", startNotificationTimer);
        } else {
            startNotificationTimer();
        }
    })();
    </script>
    ';
    
    return $css . $html . $js;
}
?>