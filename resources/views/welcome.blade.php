<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OFROO - منصة الكوبونات والعروض المحلية</title>
    <meta name="description"
        content="منصة OFROO للكوبونات والعروض المحلية في مصر - اكتشف أفضل العروض والخصومات من التجار المحليين">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;600;700&family=Inter:wght@300;400;600;700&display=swap"
        rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Tajawal', 'Inter', sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .container {
        max-width: 1200px;
        width: 100%;
        background: white;
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        overflow: hidden;
        display: grid;
        grid-template-columns: 1fr 1fr;
        min-height: 600px;
    }

    .left-section {
        padding: 60px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    }

    .right-section {
        padding: 60px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        background: white;
    }

    .logo {
        font-size: 48px;
        font-weight: 700;
        color: #667eea;
        margin-bottom: 20px;
        text-align: center;
    }

    .logo-en {
        font-size: 24px;
        color: #764ba2;
        margin-bottom: 40px;
        text-align: center;
    }

    .welcome-title {
        font-size: 32px;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 20px;
        text-align: center;
    }

    .welcome-subtitle {
        font-size: 18px;
        color: #4a5568;
        margin-bottom: 40px;
        text-align: center;
        line-height: 1.6;
    }

    .info-cards {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 30px;
    }

    .info-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        text-align: center;
    }

    .info-card-icon {
        font-size: 32px;
        margin-bottom: 10px;
    }

    .info-card-title {
        font-size: 14px;
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 5px;
    }

    .info-card-value {
        font-size: 18px;
        font-weight: 700;
        color: #667eea;
    }

    .features-list {
        list-style: none;
        margin-bottom: 30px;
    }

    .features-list li {
        padding: 10px 0;
        color: #4a5568;
        font-size: 16px;
        display: flex;
        align-items: center;
    }

    .features-list li:before {
        content: "✓";
        color: #48bb78;
        font-weight: bold;
        margin-left: 10px;
        font-size: 20px;
    }

    .login-form {
        background: white;
        padding: 40px;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    .form-title {
        font-size: 28px;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 10px;
        text-align: center;
    }

    .form-subtitle {
        font-size: 14px;
        color: #718096;
        margin-bottom: 30px;
        text-align: center;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-label {
        display: block;
        font-size: 14px;
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 8px;
    }

    .form-input {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 16px;
        transition: all 0.3s;
        font-family: 'Tajawal', sans-serif;
    }

    .form-input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .btn-primary {
        width: 100%;
        padding: 14px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        font-family: 'Tajawal', sans-serif;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
    }

    .btn-secondary {
        width: 100%;
        padding: 14px;
        background: white;
        color: #667eea;
        border: 2px solid #667eea;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        margin-top: 10px;
        font-family: 'Tajawal', sans-serif;
    }

    .btn-secondary:hover {
        background: #667eea;
        color: white;
    }

    .compliance-badge {
        background: #f0fff4;
        border: 2px solid #48bb78;
        border-radius: 8px;
        padding: 15px;
        margin-top: 20px;
        text-align: center;
    }

    .compliance-badge-text {
        font-size: 12px;
        color: #22543d;
        font-weight: 600;
    }

    .api-link {
        text-align: center;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #e2e8f0;
    }

    .api-link a {
        color: #667eea;
        text-decoration: none;
        font-weight: 600;
    }

    .api-link a:hover {
        text-decoration: underline;
    }

    @media (max-width: 991.98px) {
        .container {
            grid-template-columns: 1fr;
            min-height: auto;
        }

        .left-section,
        .right-section {
            padding: 40px 30px;
        }

        .info-cards {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 767.98px) {
        body {
            padding: 10px;
        }

        .container {
            border-radius: 12px;
            min-height: auto;
        }

        .left-section,
        .right-section {
            padding: 30px 20px;
        }

        .logo {
            font-size: 36px;
            margin-bottom: 15px;
        }

        .logo-en {
            font-size: 18px;
            margin-bottom: 30px;
        }

        .welcome-title {
            font-size: 24px;
            margin-bottom: 15px;
        }

        .welcome-subtitle {
            font-size: 15px;
            margin-bottom: 30px;
        }

        .info-card {
            padding: 16px;
        }

        .info-card-icon {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .info-card-title {
            font-size: 12px;
        }

        .info-card-value {
            font-size: 16px;
        }

        .features-list li {
            font-size: 14px;
            padding: 8px 0;
        }

        .login-form {
            padding: 30px 20px;
        }

        .form-title {
            font-size: 22px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-control {
            padding: 12px 16px;
            font-size: 16px; /* Prevents zoom on iOS */
        }

        .btn-primary {
            padding: 12px 24px;
            font-size: 16px;
            width: 100%;
        }

        .compliance-badge {
            padding: 12px;
            margin-top: 20px;
        }

        .compliance-badge-text {
            font-size: 11px;
        }
    }

    @media (max-width: 424.98px) {
        body {
            padding: 5px;
        }

        .container {
            border-radius: 8px;
        }

        .left-section,
        .right-section {
            padding: 20px 16px;
        }

        .logo {
            font-size: 32px;
        }

        .logo-en {
            font-size: 16px;
        }

        .welcome-title {
            font-size: 20px;
        }

        .welcome-subtitle {
            font-size: 14px;
        }

        .info-card {
            padding: 12px;
        }

        .login-form {
            padding: 24px 16px;
        }

        .form-title {
            font-size: 20px;
        }
    }

    @media (max-width: 374.98px) {
        .left-section,
        .right-section {
            padding: 16px 12px;
        }

        .logo {
            font-size: 28px;
        }

        .welcome-title {
            font-size: 18px;
        }
    }
    </style>
</head>

<body>
    <div class="container">
        <!-- Left Section: Information -->
        <div class="left-section">
            <div class="logo">OFROO</div>
            <div class="logo-en">Local Coupons & Offers Platform</div>

            <h1 class="welcome-title">مرحباً بك في منصة OFROO</h1>
            <p class="welcome-subtitle">
                منصة رائدة للكوبونات والعروض المحلية في دولة مصر<br>
                اكتشف أفضل العروض والخصومات من التجار المحليين
            </p>

            <div class="info-cards">
                <div class="info-card">
                    <div class="info-card-icon">💰</div>
                    <div class="info-card-title">العملة</div>
                    <div class="info-card-value">جنيه مصري (EGP)</div>
                </div>
                <div class="info-card">
                    <div class="info-card-icon">📍</div>
                    <div class="info-card-title">النطاق</div>
                    <div class="info-card-value">دولة مصر</div>
                </div>
                <div class="info-card">
                    <div class="info-card-icon">📊</div>
                    <div class="info-card-title">العمولة</div>
                    <div class="info-card-value">6% (الشهور الأولى)</div>
                </div>
                <div class="info-card">
                    <div class="info-card-icon">💳</div>
                    <div class="info-card-title">طرق الدفع</div>
                    <div class="info-card-value">نقدي / إلكتروني</div>
                </div>
            </div>

            <ul class="features-list">
                <li>عروض حصرية من التجار المحليين</li>
                <li>كوبونات قابلة للاستخدام فوراً</li>
                <li>نظام مالي متكامل وآمن</li>
                <li>تقارير مفصلة وإحصائيات</li>
                <li>دعم متعدد اللغات (عربي/إنجليزي)</li>
            </ul>

            <div class="compliance-badge">
                <div class="compliance-badge-text">
                    ✓ متوافق مع قوانين التجارة وحماية المستهلك في دولة مصر
                </div>
            </div>
        </div>

        <!-- Right Section: Login Form -->
        <div class="right-section">
            <div class="login-form">
                <h2 class="form-title">تسجيل الدخول</h2>
                <p class="form-subtitle">للوصول إلى لوحة التحكم والبدء في استخدام المنصة</p>

                <form id="loginForm" onsubmit="handleLogin(event)">
                    <div class="form-group">
                        <label class="form-label" for="email">البريد الإلكتروني</label>
                        <input type="email" id="email" class="form-input" placeholder="example@email.com" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">كلمة المرور</label>
                        <input type="password" id="password" class="form-input" placeholder="••••••••" required>
                    </div>

                    <button type="submit" class="btn-primary">
                        تسجيل الدخول
                    </button>

                    <button type="button" class="btn-secondary" onclick="window.location.href='/api'">
                        عرض API Documentation
                    </button>
                </form>

                <div class="api-link">
                    <a href="/api/documentation" target="_blank">📚 وثائق API الكاملة</a> |
                    <a href="/docs/postman_collection.json" target="_blank">📬 Postman Collection</a>
                </div>
            </div>
        </div>
    </div>

    <script>
    function handleLogin(event) {
        event.preventDefault();

        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;

        // This is a demo form - in production, this would make an API call
        alert('هذا نموذج للعرض فقط. للدخول الفعلي، استخدم API endpoint: POST /api/auth/login\n\nEmail: ' + email +
            '\nPassword: ' + password);

        // Example API call (commented out for demo):
        /*
        fetch('/api/auth/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                email: email,
                password: password
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.token) {
                localStorage.setItem('auth_token', data.token);
                window.location.href = '/dashboard';
            } else {
                alert('خطأ في تسجيل الدخول');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('حدث خطأ أثناء تسجيل الدخول');
        });
        */
    }
    </script>
</body>

</html>