<?php
    session_start();

    $supabaseUrl = "https://rxzrbyqqhkxemdjbcntc.supabase.co";
    $supabaseKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InJ4enJieXFxaGt4ZW1kamJjbnRjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUyMTU5ODUsImV4cCI6MjA5MDc5MTk4NX0.F9r_81C1dIvhlMoyEmxnVtAzIby_66kTlXc0wBRjpmQ";

    if (isset($_SESSION['role'])) {
        switch ($_SESSION['role']) {
            case 'admin':
                header("Location: admin/dashboard.php");
                break;
            case 'dlh':
                header("Location: dlh/dashboard.php");
                break;
            case 'bank sampah':
                header("Location: bank_sampah/dashboard.php");
                break;
        }
        exit;
    }

    $error = '';

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        $email = $_POST['email'];
        $password = md5($_POST['password']);

        $url = $supabaseUrl . "/rest/v1/admin?select=*,role(*)&email=eq." . urlencode($email);

        $headers = [
            "apikey: $supabaseKey",
            "Authorization: Bearer $supabaseKey",
            "Content-Type: application/json"
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        curl_close($ch);

        $result = json_decode($response, true);

        if (!empty($result)) {
            $user = $result[0];
            if ($user['password'] == $password) {
                $_SESSION['id_admin'] = $user['id_admin'];
                $_SESSION['nama_admin'] = $user['nama_admin'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['foto_profil'] = $user['foto_profil'];
                $_SESSION['role'] = $user['role']['nama_role'];

                switch ($user['role']['nama_role']) {
                    case 'admin':
                        header("Location: admin/dashboard.php");
                        break;
                    case 'dlh':
                        header("Location: dlh/dashboard.php");
                        break;
                    case 'bank sampah':
                        header("Location: bank_sampah/dashboard.php");
                        break;
                    default:
                        $error = "Role tidak dikenali!";
                        break;
                }

                exit;
            } else {
                $error = "Password salah!";
            }
        } else {
            $error = "Email tidak ditemukan!";
        }
    }
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — ReWorth</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --green-50:  #f0fdf4;
            --green-100: #dcfce7;
            --green-200: #bbf7d0;
            --green-400: #4ade80;
            --green-500: #22c55e;
            --green-600: #16a34a;
            --green-700: #15803d;
            --green-800: #166534;
            --white:     #ffffff;
            --gray-50:   #f9fafb;
            --gray-100:  #f3f4f6;
            --gray-300:  #d1d5db;
            --gray-400:  #9ca3af;
            --gray-500:  #6b7280;
            --gray-700:  #374151;
        }

        body {
            font-family: 'Bricolage Grotesque', sans-serif;
            background: var(--green-50);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        body::before,
        body::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: .45;
            pointer-events: none;
            z-index: 0;
        }
        body::before {
            width: 520px;
            height: 520px;
            background: radial-gradient(circle, var(--green-200) 0%, transparent 70%);
            top: -120px;
            left: -120px;
            animation: drift1 12s ease-in-out infinite alternate;
        }
        body::after {
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, var(--green-400) 0%, transparent 70%);
            bottom: -100px;
            right: -80px;
            animation: drift2 14s ease-in-out infinite alternate;
        }

        @keyframes drift1 {
            from { transform: translate(0, 0) scale(1); }
            to   { transform: translate(40px, 30px) scale(1.08); }
        }
        @keyframes drift2 {
            from { transform: translate(0, 0) scale(1); }
            to   { transform: translate(-30px, -40px) scale(1.1); }
        }

        .particles {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }
        .leaf {
            position: absolute;
            width: 8px;
            height: 8px;
            border-radius: 50% 0 50% 0;
            background: var(--green-400);
            opacity: 0;
            animation: floatLeaf linear infinite;
        }
        .leaf:nth-child(1)  { left: 10%; width: 6px;  height: 6px;  animation-duration: 18s; animation-delay: 0s;    background: var(--green-300, #86efac); }
        .leaf:nth-child(2)  { left: 25%; width: 10px; height: 10px; animation-duration: 22s; animation-delay: 3s;   }
        .leaf:nth-child(3)  { left: 40%; width: 7px;  height: 7px;  animation-duration: 16s; animation-delay: 6s;   background: var(--green-500); }
        .leaf:nth-child(4)  { left: 60%; width: 9px;  height: 9px;  animation-duration: 20s; animation-delay: 1s;   }
        .leaf:nth-child(5)  { left: 75%; width: 5px;  height: 5px;  animation-duration: 24s; animation-delay: 8s;   background: var(--green-300, #86efac); }
        .leaf:nth-child(6)  { left: 88%; width: 8px;  height: 8px;  animation-duration: 19s; animation-delay: 4s;   }

        @keyframes floatLeaf {
            0%   { transform: translateY(110vh) rotate(0deg);   opacity: 0; }
            10%  { opacity: .6; }
            90%  { opacity: .4; }
            100% { transform: translateY(-10vh) rotate(360deg); opacity: 0; }
        }

        .card-wrapper {
            position: relative;
            z-index: 1;
            display: flex;
            width: min(920px, 95vw);
            min-height: 560px;
            background: var(--white);
            border-radius: 28px;
            box-shadow:
                0 2px 4px rgba(0,0,0,.04),
                0 8px 24px rgba(0,0,0,.08),
                0 32px 64px rgba(22,163,74,.10);
            overflow: hidden;
            animation: cardIn .7s cubic-bezier(.22,1,.36,1) both;
        }

        @keyframes cardIn {
            from { opacity: 0; transform: translateY(32px) scale(.97); }
            to   { opacity: 1; transform: translateY(0)    scale(1); }
        }

        .panel-left {
            flex: 1;
            background: url(img/background.jpg) no-repeat center/cover;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px 36px;
            position: relative;
            overflow: hidden;
        }

        .panel-left::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle at 20% 80%, rgba(255,255,255,.12) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255,255,255,.08) 0%, transparent 50%);
        }

        .panel-left .brand {
            position: relative;
            z-index: 1;
            margin-top: 8px;
            text-align: center;
        }

        .panel-left .brand h1 {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 3rem;
            font-weight: 600;
            color: var(--white);
            text-shadow: #0000008a 1px 1px 7px;
            letter-spacing: -.5px;
            line-height: 1;
        }

        .panel-left .brand p {
            font-size: 1rem;
            color: var(--white);
            margin-top: 8px;
            font-weight: 300;
            letter-spacing: .3px;
            text-shadow: #0000008a 1px 1px 7px;
        }

        .panel-right {
            width: 380px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 52px 44px;
            animation: formIn .8s .15s cubic-bezier(.22,1,.36,1) both;
        }

        @keyframes formIn {
            from { opacity: 0; transform: translateX(24px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        .panel-right .greeting {
            margin-bottom: 32px;
        }

        .panel-right .greeting h2 {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 1.9rem;
            font-weight: 600;
            color: var(--green-800);
            line-height: 1.2;
        }

        .panel-right .greeting p {
            margin-top: 6px;
            font-size: .88rem;
            color: var(--gray-500);
            font-weight: 300;
        }

        .error-banner {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 11px 14px;
            border-radius: 12px;
            margin-bottom: 22px;
            font-size: .84rem;
            animation: shake .4s cubic-bezier(.36,.07,.19,.97);
        }

        .error-banner svg {
            flex-shrink: 0;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%       { transform: translateX(-6px); }
            40%       { transform: translateX(6px); }
            60%       { transform: translateX(-4px); }
            80%       { transform: translateX(4px); }
        }

        .input-group {
            margin-bottom: 22px;
            display: flex;
            flex-direction: column;
        }

        .input-group label {
            display: block;
            margin-bottom: 10px;
            font-size: .84rem;
            font-weight: 600;
            color: var(--gray-700);
            letter-spacing: .2px;
        }

        .input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrap i {
            position: absolute;
            left: 14px;
            color: var(--gray-400);
            font-size: 14px;
            transition: .2s;
            z-index: 2;
        }

        .input-wrap input {
            width: 100%;
            height: 48px;
            padding: 0 44px 0 42px;
            border: 1.5px solid var(--gray-300);
            border-radius: 12px;
            font-size: .92rem;
            font-family: 'DM Sans', sans-serif;
            color: var(--gray-700);
            background: var(--gray-50);
            transition: .2s;
            outline: none;
        }

        .input-wrap input:focus {
            border-color: var(--green-500);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(34,197,94,.12);
        }

        .input-wrap:focus-within i {
            color: var(--green-600);
        }

        .toggle-pw {
            position: absolute;
            right: 50px;
            top: 35%;
            transform: translateY(-50%);
            border: none;
            background: none;
            color: var(--gray-400);
            cursor: pointer;
            font-size: 14px;
            transition: .2s;
            z-index: 2;
        }

        .toggle-pw:hover {
            color: var(--green-600);
        }

        .btn-login {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--green-600) 0%, var(--green-500) 100%);
            color: var(--white);
            font-family: 'DM Sans', sans-serif;
            font-size: .95rem;
            font-weight: 600;
            letter-spacing: .3px;
            cursor: pointer;
            margin-top: 8px;
            position: relative;
            overflow: hidden;
            transition: transform .15s, box-shadow .2s;
            box-shadow: 0 4px 14px rgba(22,163,74,.35);
        }

        .btn-login::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,.15) 0%, transparent 60%);
            pointer-events: none;
        }

        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 7px 20px rgba(22,163,74,.42);
        }

        .btn-login:active {
            transform: translateY(0);
            box-shadow: 0 3px 10px rgba(22,163,74,.3);
        }

        .btn-login .btn-text { display: inline-block; transition: opacity .2s; }
        .btn-login .btn-spinner { display: none; }
        .btn-login.loading .btn-text { opacity: 0; }
        .btn-login.loading .btn-spinner {
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            inset: 0;
        }
        .btn-login.loading .btn-spinner::after {
            content: '';
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,.4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .footer-note {
            margin-top: 28px;
            text-align: center;
            font-size: .76rem;
            color: var(--gray-400);
        }

        @media (max-width: 680px) {
            .card-wrapper {
                flex-direction: column;
                width: 95vw;
                min-height: unset;
            }
            .panel-left {
                padding: 36px 24px 28px;
            }
            .panel-left dotlottie-wc {
                width: 200px !important;
                height: 200px !important;
            }
            .panel-right {
                width: 100%;
                padding: 36px 28px 40px;
            }
        }
    </style>
</head>
<body>
    <div class="particles" aria-hidden="true">
        <div class="leaf"></div>
        <div class="leaf"></div>
        <div class="leaf"></div>
        <div class="leaf"></div>
        <div class="leaf"></div>
        <div class="leaf"></div>
    </div>

    <div class="card-wrapper">
        <div class="panel-left">
            <div class="brand">
                <h1>ReWorth</h1>
                <p>Kelola sampah, ciptakan nilai.</p>
            </div>
        </div>

        <div class="panel-right">
            <div class="greeting">
                <h2>Selamat datang<br>kembali</h2>
                <p>Masuk ke akun ReWorth Anda</p>
            </div>

            <?php if ($error != ''): ?>
            <div class="error-banner" role="alert">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <?= htmlspecialchars($error) ?>
            </div>

            <?php endif; ?>
            <form method="POST" id="loginForm" novalidate>
                <div class="input-group">
                    <label for="email">Email</label>

                    <div class="input-wrap">
                        <i class="fa-regular fa-envelope"></i>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="example@gmail.com"
                            required
                            autocomplete="email">
                    </div>
                </div>

                <div class="input-group">
                    <label for="password">Password</label>

                    <div class="input-wrap">
                        <i class="fa-solid fa-lock"></i>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="••••••••"
                            required
                            autocomplete="current-password">
                        <button type="button" class="toggle-pw" id="togglePw">
                            <i class="fa-regular fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login" id="loginBtn">
                    <span class="btn-text">Masuk</span>
                    <span class="btn-spinner" aria-hidden="true"></span>
                </button>

            </form>

            <p class="footer-note">© <?= date('Y') ?> ReWorth — Sistem Pengelolaan Sampah</p>

        </div>
    </div>

    <script>
        const togglePw = document.getElementById('togglePw');
        const pwInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');

        togglePw.addEventListener('click', () => {
            const isPassword = pwInput.type === 'password';
            pwInput.type = isPassword ? 'text' : 'password';
            eyeIcon.classList.toggle('fa-eye');
            eyeIcon.classList.toggle('fa-eye-slash');
        });

        const form     = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');

        form.addEventListener('submit', () => {
            loginBtn.classList.add('loading');
            loginBtn.disabled = true;
        });
    </script>
</body>
</html>