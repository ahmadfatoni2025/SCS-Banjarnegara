<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" href="logo_scs_jpg.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hero Section - Makan Bergizi Gratis</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #1e40af;
            --secondary-blue: #3b82f6;
            --light-blue: #dbeafe;
            --accent-blue: #60a5fa;
            --dark-blue: #1e3a8a;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --white: #ffffff;
            --light-bg: #f8fafc;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --radius: 12px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--light-bg);
            color: var(--text-dark);
            line-height: 1.6;
            overflow-x: hidden;
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, rgba(30, 64, 175, 0.9), rgba(30, 58, 138, 0.95)), 
                        url('https://images.unsplash.com/photo-1490818387583-1baba5e638af?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: var(--white);
            padding: 6rem 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23ffffff' fill-opacity='0.05' fill-rule='evenodd'/%3E%3C/svg%3E");
            animation: float 20s infinite linear;
        }

        @keyframes float {
            0% { transform: translate(0, 0); }
            100% { transform: translate(-100px, -100px); }
        }

        .hero-content {
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .hero-badge {
            display: inline-block;
            background-color: var(--accent-blue);
            color: var(--white);
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            font-weight: 800;
            line-height: 1.2;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .hero h1 span {
            color: var(--accent-blue);
            position: relative;
        }

        .hero h1 span::after {
            content: '';
            position: absolute;
            bottom: 5px;
            left: 0;
            width: 100%;
            height: 8px;
            background-color: rgba(96, 165, 250, 0.3);
            z-index: -1;
            border-radius: 4px;
        }

        .hero p {
            font-size: 1.3rem;
            margin-bottom: 2.5rem;
            opacity: 0.9;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 3rem;
        }

        .cta-button {
            background-color: var(--accent-blue);
            color: var(--white);
            border: none;
            padding: 1rem 2rem;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
        }

        .cta-button:hover {
            background-color: var(--secondary-blue);
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .cta-button.secondary {
            background-color: transparent;
            border: 2px solid var(--white);
        }

        .cta-button.secondary:hover {
            background-color: var(--white);
            color: var(--primary-blue);
        }

        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 3rem;
            flex-wrap: wrap;
            margin-top: 3rem;
        }

        .stat-item {
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 1.5rem;
            border-radius: var(--radius);
            min-width: 150px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition);
        }

        .stat-item:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.15);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--accent-blue);
        }

        .stat-label {
            font-size: 1rem;
            opacity: 0.9;
        }

        .floating-elements {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
        }

        .floating-element {
            position: absolute;
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--white);
            animation: floatElement 15s infinite linear;
        }

        .floating-element:nth-child(1) {
            top: 20%;
            left: 10%;
            animation-delay: 0s;
            background: rgba(96, 165, 250, 0.3);
        }

        .floating-element:nth-child(2) {
            top: 60%;
            left: 5%;
            animation-delay: 2s;
            background: rgba(59, 130, 246, 0.3);
        }

        .floating-element:nth-child(3) {
            top: 30%;
            right: 10%;
            animation-delay: 4s;
            background: rgba(30, 64, 175, 0.3);
        }

        .floating-element:nth-child(4) {
            top: 70%;
            right: 15%;
            animation-delay: 6s;
            background: rgba(96, 165, 250, 0.3);
        }

        @keyframes floatElement {
            0% {
                transform: translateY(0) rotate(0deg);
            }
            25% {
                transform: translateY(-20px) rotate(90deg);
            }
            50% {
                transform: translateY(0) rotate(180deg);
            }
            75% {
                transform: translateY(20px) rotate(270deg);
            }
            100% {
                transform: translateY(0) rotate(360deg);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .hero {
                padding: 4rem 0;
                min-height: 90vh;
            }

            .hero h1 {
                font-size: 2.5rem;
            }

            .hero p {
                font-size: 1.1rem;
            }

            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }

            .cta-button {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }

            .hero-stats {
                gap: 1.5rem;
            }

            .stat-item {
                min-width: 120px;
                padding: 1rem;
            }

            .stat-number {
                font-size: 2rem;
            }
        }

        @media (max-width: 480px) {
            .hero h1 {
                font-size: 2rem;
            }

            .hero p {
                font-size: 1rem;
            }

            .hero-stats {
                gap: 1rem;
            }

            .stat-item {
                min-width: 100px;
                padding: 0.8rem;
            }

            .stat-number {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <section class="hero">
        <div class="floating-elements">
            <div class="floating-element">
                <i class="fas fa-apple-alt"></i>
            </div>
            <div class="floating-element">
                <i class="fas fa-carrot"></i>
            </div>
            <div class="floating-element">
                <i class="fas fa-leaf"></i>
            </div>
            <div class="floating-element">
                <i class="fas fa-seedling"></i>
            </div>
        </div>
        
        <div class="container">
            <div class="hero-content">
                <div class="hero-badge">
                    <i class="fas fa-heart"></i> Program Kesehatan Nasional
                </div>
                
                <h1>Makanan <span>Bergizi Gratis</span> untuk Masa Depan Sehat</h1>
                
                <p>Kami berkomitmen menyediakan makanan sehat dan bergizi secara gratis untuk anak-anak, keluarga, dan masyarakat yang membutuhkan. Mari bersama-sama memerangi kelaparan dan malnutrisi.</p>
                
                <div class="hero-buttons">
                    <button class="cta-button">
                        <i class="fas fa-utensils"></i> Daftar Sekarang
                    </button>
                    <button class="cta-button secondary">
                        <i class="fas fa-play-circle"></i> Tonton Video
                    </button>
                </div>
                
                <div class="hero-stats">
                    <div class="stat-item">
                        <div class="stat-number" id="stat1">0</div>
                        <div class="stat-label">Penerima Manfaat</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" id="stat2">0</div>
                        <div class="stat-label">Lokasi Program</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" id="stat3">0</div>
                        <div class="stat-label">Relawan Aktif</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        // Animasi counter untuk statistik
        function animateCounter(element, target, duration) {
            let start = 0;
            const increment = target / (duration / 16); // 16ms per frame
            
            const timer = setInterval(() => {
                start += increment;
                if (start >= target) {
                    element.textContent = target.toLocaleString();
                    clearInterval(timer);
                } else {
                    element.textContent = Math.floor(start).toLocaleString();
                }
            }, 16);
        }

        // Inisialisasi counter saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            // Tunggu sebentar agar animasi lebih smooth
            setTimeout(() => {
                animateCounter(document.getElementById('stat1'), 12500, 2000);
                animateCounter(document.getElementById('stat2'), 48, 1500);
                animateCounter(document.getElementById('stat3'), 850, 1800);
            }, 500);

            // Efek ketikan untuk judul (opsional)
            const title = document.querySelector('.hero h1');
            const text = title.innerHTML;
            title.innerHTML = '';
            
            let i = 0;
            function typeWriter() {
                if (i < text.length) {
                    title.innerHTML += text.charAt(i);
                    i++;
                    setTimeout(typeWriter, 50);
                }
            }
            
            // Uncomment baris di bawah jika ingin efek ketikan
            // typeWriter();
        });

        // Efek parallax sederhana
        window.addEventListener('scroll', function() {
            const scrolled = window.pageYOffset;
            const hero = document.querySelector('.hero');
            hero.style.backgroundPosition = `center ${scrolled * 0.5}px`;
        });

        // Interaksi dengan tombol
        document.querySelectorAll('.cta-button').forEach(button => {
            button.addEventListener('click', function() {
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 200);
                
                if (this.classList.contains('secondary')) {
                    alert('Video akan diputar mengenai program Makan Bergizi Gratis!');
                } else {
                    alert('Terima kasih! Anda akan diarahkan ke formulir pendaftaran.');
                }
            });
        });
    </script>
</body>
</html>
