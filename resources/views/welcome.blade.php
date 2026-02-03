<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Redirecting to Tohfaah ❤️</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {{-- Auto redirect --}}
    <meta http-equiv="refresh" content="3;url=https://tohfaah.com">

    <style>
        body {
            margin: 0;
            height: 100vh;
            background: radial-gradient(circle at top, #ffe4f1, #fff);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
            overflow: hidden;
        }

        .container {
            position: relative;
            z-index: 2;
            text-align: center;
            animation: fadeIn 1.2s ease;
        }

        .logo {
            font-size: 44px;
            font-weight: 700;
            color: #ff4f9a;
            letter-spacing: 1px;
        }

        .tagline {
            margin-top: 8px;
            font-size: 15px;
            color: #666;
        }

        .redirect-text {
            margin-top: 22px;
            font-size: 14px;
            color: #777;
        }

        .manual-link {
            margin-top: 14px;
            display: inline-block;
            color: #ff4f9a;
            text-decoration: none;
            font-weight: 500;
        }

        .manual-link:hover {
            text-decoration: underline;
        }

        /* 💖 FLOATING HEARTS BACKGROUND */
        .hearts {
            position: absolute;
            inset: 0;
            overflow: hidden;
            z-index: 1;
        }

        .heart {
            position: absolute;
            bottom: -40px;
            width: 18px;
            height: 18px;
            background: #ff4f9a;
            transform: rotate(45deg);
            animation: floatUp linear infinite;
            opacity: 0.7;
        }

        .heart::before,
        .heart::after {
            content: "";
            width: 18px;
            height: 18px;
            background: #ff4f9a;
            border-radius: 50%;
            position: absolute;
        }

        .heart::before {
            top: -9px;
            left: 0;
        }

        .heart::after {
            left: -9px;
            top: 0;
        }

        /* Each heart variation */
        .heart:nth-child(1) { left: 10%; animation-duration: 6s; animation-delay: 0s; }
        .heart:nth-child(2) { left: 20%; animation-duration: 7s; animation-delay: 1s; opacity: .5; }
        .heart:nth-child(3) { left: 35%; animation-duration: 5s; animation-delay: 2s; }
        .heart:nth-child(4) { left: 50%; animation-duration: 8s; animation-delay: .5s; opacity: .4; }
        .heart:nth-child(5) { left: 65%; animation-duration: 6.5s; animation-delay: 1.5s; }
        .heart:nth-child(6) { left: 80%; animation-duration: 7.5s; animation-delay: 2.5s; opacity: .6; }
        .heart:nth-child(7) { left: 90%; animation-duration: 5.5s; animation-delay: 3s; }

        @keyframes floatUp {
            0% {
                transform: translateY(0) rotate(45deg) scale(1);
                opacity: 0;
            }
            10% {
                opacity: 0.7;
            }
            100% {
                transform: translateY(-110vh) rotate(45deg) scale(1.4);
                opacity: 0;
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

    <!-- Floating hearts background -->
    <div class="hearts">
        <span class="heart"></span>
        <span class="heart"></span>
        <span class="heart"></span>
        <span class="heart"></span>
        <span class="heart"></span>
        <span class="heart"></span>
        <span class="heart"></span>
    </div>

    <!-- Content -->
    <div class="container">
        <div class="logo">Tohfaah</div>
        <div class="tagline">Gifts that speak from the heart</div>

        <div class="redirect-text">
            Preparing something special for you…
        </div>

        <a href="https://tohfaah.com" class="manual-link">
            Click here if not redirected
        </a>
    </div>

</body>
</html>
