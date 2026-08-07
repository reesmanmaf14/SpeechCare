<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SpeechCare</title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet"
          href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        :root {
            --blue: #3998E8;
            --dark-blue: #1F7FD1;
            --light-blue-bg: #E8F4FF;
            --soft-bg: #F7FBFF;
            --navy: #19192D;
            --gray: #666B7A;
            --border: #DDEEFF;
            --green: #2CB67D;
            --orange: #F6B23D;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: "Segoe UI", sans-serif;
            background-color: #ffffff;
            color: var(--navy);
        }

        /* Navbar */
        .speech-navbar {
            background-color: #ffffff;
            border-bottom: 1px solid var(--border);
            padding: 12px 48px;
        }

        .navbar-brand {
            font-style: italic;
            color: var(--blue) !important;
            font-size: 24px;
            font-weight: 500;
        }

        .nav-link {
            color: var(--navy) !important;
            font-size: 14px;
            margin: 0 10px;
        }

        .nav-link:hover {
            color: var(--blue) !important;
        }

        .btn-login {
            background-color: var(--blue);
            color: #ffffff;
            padding: 9px 22px;
            border-radius: 12px;
            font-size: 14px;
            text-decoration: none;
            transition: 0.2s;
        }

        .btn-login:hover {
            background-color: var(--dark-blue);
            color: #ffffff;
            text-decoration: none;
        }

        /* Hero */
        .hero-section {
            padding: 80px 48px;
            background-color: #ffffff;
        }

        .hero-img {
            width: 100%;
            max-width: 600px;
            border-radius: 24px;
        }

        .hero-title {
            font-size: 48px;
            font-weight: 800;
            line-height: 1.1;
            color: var(--navy);
            margin-bottom: 24px;
        }

        .hero-title span {
            color: var(--blue);
            display: block;
        }

        .hero-desc {
            font-size: 18px;
            color: var(--gray);
            line-height: 1.6;
            max-width: 470px;
            margin-bottom: 32px;
        }

        .btn-primary-custom {
            background-color: var(--blue);
            color: #ffffff;
            padding: 12px 28px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: 0.2s;
        }

        .btn-primary-custom:hover {
            background-color: var(--dark-blue);
            color: #ffffff;
            text-decoration: none;
        }

        /* Common Sections */
        .section {
            padding: 100px 48px;
            scroll-margin-top: 70px;
        }

        .section-soft {
            background-color: var(--soft-bg);
        }

        .section-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .section-tag {
            display: inline-block;
            background-color: var(--light-blue-bg);
            color: var(--blue);
            font-size: 11px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 20px;
            border: 1px solid var(--border);
            margin-bottom: 10px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .section-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 8px;
        }

        .section-sub {
            font-size: 14px;
            color: var(--gray);
            max-width: 520px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* Service Cards */
        .service-card {
            background-color: #ffffff;
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 28px 24px;
            height: 100%;
            transition: 0.3s ease;
        }

        .service-card:hover {
            transform: translateY(-8px);
            border-color: var(--blue);
            box-shadow: 0 12px 30px rgba(57, 152, 232, 0.1);
        }

        .service-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background-color: var(--light-blue-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }

        .service-icon i {
            color: var(--blue);
            font-size: 20px;
        }

        .service-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--navy);
            margin-bottom: 8px;
        }

        .service-desc {
            font-size: 13px;
            color: var(--gray);
            line-height: 1.65;
        }

        .service-tag {
            display: inline-block;
            background-color: var(--light-blue-bg);
            color: var(--blue);
            font-size: 11px;
            padding: 3px 10px;
            border-radius: 20px;
            margin-top: 14px;
        }

        /* About */
        .about-box {
            background-color: var(--light-blue-bg);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 32px;
        }

        .about-card {
            background-color: #ffffff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .about-card-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background-color: var(--light-blue-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .about-card-icon i {
            font-size: 15px;
            color: var(--blue);
        }

        .about-card-title {
            font-size: 12px;
            font-weight: 600;
            color: var(--navy);
        }

        .about-card-sub {
            font-size: 11px;
            color: var(--gray);
        }

        .about-points {
            list-style: none;
            padding-left: 0;
            margin-top: 22px;
        }

        .about-points li {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            color: var(--gray);
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .tick-container {
            width: 24px;
            height: 24px;
            background-color: var(--light-blue-bg);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .tick-container i {
            color: var(--blue);
            font-size: 12px;
        }

        /* Testimonials */
        .testi-card {
            background-color: var(--soft-bg);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 32px;
            height: 100%;
            transition: 0.3s ease;
        }

        .testi-card:hover {
            transform: translateY(-8px);
            border-color: var(--blue);
            box-shadow: 0 12px 30px rgba(57, 152, 232, 0.1);
        }

        .testi-stars {
            color: var(--orange);
            font-size: 14px;
            margin-bottom: 16px;
        }

        .testi-text {
            font-size: 15px;
            color: var(--gray);
            font-style: italic;
            line-height: 1.7;
            margin-bottom: 24px;
        }

        .testi-author {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .testi-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background-color: var(--blue);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
        }

        .avatar-green {
            background-color: var(--green);
        }

        .avatar-orange {
            background-color: var(--orange);
        }

        .testi-name {
            font-size: 14px;
            font-weight: 700;
            color: var(--navy);
        }

        .testi-role {
            font-size: 12px;
            color: var(--gray);
        }

        /* Footer */
        .footer {
            background-color: var(--navy);
            color: #ffffff;
            padding-top: 50px;
            margin-top: 80px;
        }

        .footer-logo {
            font-style: italic;
            font-size: 22px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 12px;
        }

        .footer-desc {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
            line-height: 1.6;
            max-width: 280px;
        }

        .footer-col-title {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
            color: #ffffff;
            font-weight: 600;
        }

        .footer-links a {
            display: block;
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .footer-links a:hover {
            color: var(--blue);
            text-decoration: none;
        }

        .footer-bottom {
            margin-top: 30px;
            padding: 20px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 13px;
            color: rgba(255, 255, 255, 0.4);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .speech-navbar {
                padding: 12px 24px;
            }

            .hero-section {
                padding: 50px 24px;
                text-align: center;
            }

            .hero-desc {
                margin-left: auto;
                margin-right: auto;
            }

            .hero-title {
                font-size: 40px;
            }

            .section {
                padding: 70px 24px;
            }
        }

        @media (max-width: 576px) {
            .hero-title {
                font-size: 34px;
            }

            .hero-desc {
                font-size: 16px;
            }

            .section-title {
                font-size: 24px;
            }

            .navbar-brand {
                font-size: 22px;
            }
        }
    </style>
</head>

<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light speech-navbar sticky-top">
    <a class="navbar-brand" href="home.php">SpeechCare</a>

    <!-- Hamburger Button -->
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#mainNavbar"
            aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Collapsible Nav Links -->
    <div class="collapse navbar-collapse" id="mainNavbar">
        <ul class="navbar-nav ml-auto align-items-lg-center">
            <li class="nav-item">
                <a class="nav-link" href="#home">Home</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="#services">Services</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="#about">About Us</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="#testimonials">Testimonials</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="#contact">Contact</a>
            </li>

            <li class="nav-item ml-lg-3 mt-3 mt-lg-0">
                <a href="login.php" class="btn-login">Login</a>
            </li>
        </ul>
    </div>
</nav>

<!-- Hero Section -->
<section id="home" class="hero-section">
    <div class="container-fluid">
        <div class="row align-items-center">

            <div class="col-lg-7 text-center mb-5 mb-lg-0">
                <img src="images/home.jpg" alt="therapy illustration" class="hero-img">
            </div>

            <div class="col-lg-5">
                <h1 class="hero-title">
                    Helping Your Child
                    <span>Find Their Voice</span>
                </h1>

                <p class="hero-desc">
                    Supporting children in developing clear speech and confident communication through personalized therapy sessions.
                </p>

                <a href="login.php" class="btn-primary-custom">Get Started</a>
            </div>

        </div>
    </div>
</section>

<!-- Services Section -->
<section id="services" class="section section-soft">
    <div class="section-header">
        <div class="section-tag">Our Services</div>
        <h2 class="section-title">Specialized Therapy Programs</h2>
        <p class="section-sub">
            Therapy sessions designed around each child’s unique communication needs.
        </p>
    </div>

    <div class="container-fluid">
        <div class="row">

            <div class="col-md-6 col-lg-4 mb-4">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fa-solid fa-comments"></i>
                    </div>
                    <div class="service-title">Speech Delay Therapy</div>
                    <p class="service-desc">
                        Early intervention programs to help children develop language skills at their own pace.
                    </p>
                    <span class="service-tag">Ages 2–6</span>
                </div>
            </div>

            <div class="col-md-6 col-lg-4 mb-4">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fa-solid fa-wave-square"></i>
                    </div>
                    <div class="service-title">Fluency & Stuttering</div>
                    <p class="service-desc">
                        Structured techniques to improve speech fluency and build confidence.
                    </p>
                    <span class="service-tag">Ages 4–12</span>
                </div>
            </div>

            <div class="col-md-6 col-lg-4 mb-4">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fa-solid fa-spell-check"></i>
                    </div>
                    <div class="service-title">Articulation Therapy</div>
                    <p class="service-desc">
                        Targeted exercises to help children pronounce sounds and words clearly.
                    </p>
                    <span class="service-tag">All Ages</span>
                </div>
            </div>

            <div class="col-md-6 col-lg-4 mb-4">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fa-solid fa-people-group"></i>
                    </div>
                    <div class="service-title">Language Development</div>
                    <p class="service-desc">
                        Expanding vocabulary and grammar for young learners.
                    </p>
                    <span class="service-tag">Ages 3–10</span>
                </div>
            </div>

            <div class="col-md-6 col-lg-4 mb-4">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fa-solid fa-book-open-reader"></i>
                    </div>
                    <div class="service-title">Language Literacy</div>
                    <p class="service-desc">
                        Helping children bridge the gap between spoken language and reading through phonological awareness and literacy support.
                    </p>
                    <span class="service-tag">Ages 4–10</span>
                </div>
            </div>

            <div class="col-md-6 col-lg-4 mb-4">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fa-solid fa-hands-holding-child"></i>
                    </div>
                    <div class="service-title">Parent Coaching</div>
                    <p class="service-desc">
                        Empowering parents with effective strategies and daily activities to support their child’s speech journey at home.
                    </p>
                    <span class="service-tag">All Programs</span>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- About Section -->
<section id="about" class="section section-soft">
    <div class="container-fluid">
        <div class="row align-items-center">

            <div class="col-lg-6 mb-5 mb-lg-0">
                <div class="about-box">

                    <div class="about-card">
                        <div class="about-card-icon">
                            <i class="fa-solid fa-user-doctor"></i>
                        </div>
                        <div>
                            <div class="about-card-title">Certified Therapist</div>
                            <div class="about-card-sub">5+ Years Experience</div>
                        </div>
                    </div>

                    <div class="about-card">
                        <div class="about-card-icon">
                            <i class="fa-solid fa-children"></i>
                        </div>
                        <div>
                            <div class="about-card-title">200+ Children Helped</div>
                            <div class="about-card-sub">Since 2020</div>
                        </div>
                    </div>

                    <div class="about-card">
                        <div class="about-card-icon">
                            <i class="fa-solid fa-calendar-check"></i>
                        </div>
                        <div>
                            <div class="about-card-title">Flexible Scheduling</div>
                            <div class="about-card-sub">Morning and afternoon slots</div>
                        </div>
                    </div>

                    <div class="about-card">
                        <div class="about-card-icon">
                            <i class="fa-solid fa-star"></i>
                        </div>
                        <div>
                            <div class="about-card-title">98% Satisfaction Rate</div>
                            <div class="about-card-sub">Rated by parents after every session</div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="col-lg-6">
                <div class="section-tag">About Us</div>

                <h2 class="section-title">Why SpeechCare?</h2>

                <p class="section-sub mx-0">
                    We believe every child's voice deserves to be heard.
                </p>

                <ul class="about-points">
                    <li>
                        <span class="tick-container">
                            <i class="fa-solid fa-check"></i>
                        </span>
                        Personalized speech therapy plans designed for each child’s unique needs.
                    </li>

                    <li>
                        <span class="tick-container">
                            <i class="fa-solid fa-check"></i>
                        </span>
                        Regular parent updates.
                    </li>

                    <li>
                        <span class="tick-container">
                            <i class="fa-solid fa-check"></i>
                        </span>
                        Easy in-clinic appointment booking for therapy sessions.
                    </li>

                    <li>
                        <span class="tick-container">
                            <i class="fa-solid fa-check"></i>
                        </span>
                        Trusted by over 200 families across Sri Lanka.
                    </li>
                </ul>
            </div>

        </div>
    </div>
</section>

<!-- Testimonials Section -->
<section id="testimonials" class="section">
    <div class="section-header">
        <div class="section-tag">Testimonials</div>
        <h2 class="section-title">What Parents Are Saying</h2>
        <p class="section-sub">
            Real stories from families who have seen the difference SpeechCare makes.
        </p>
    </div>

    <div class="container-fluid">
        <div class="row">

            <div class="col-lg-4 mb-4">
                <div class="testi-card">
                    <div class="testi-stars">
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                    </div>

                    <p class="testi-text">
                        "My son went from barely speaking to full sentences in just 3 months. The therapist was incredibly patient and made every session feel like playtime."
                    </p>

                    <div class="testi-author">
                        <div class="testi-avatar">AP</div>
                        <div>
                            <div class="testi-name">Amara Perera</div>
                            <div class="testi-role">Mother of Dineth, Age 5</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 mb-4">
                <div class="testi-card">
                    <div class="testi-stars">
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                    </div>

                    <p class="testi-text">
                        "The specialists here don't just treat the child; they educate the whole family. We finally feel like we have a clear roadmap for our daughter's communication development."
                    </p>

                    <div class="testi-author">
                        <div class="testi-avatar avatar-green">NF</div>
                        <div>
                            <div class="testi-name">Nimal Fernando</div>
                            <div class="testi-role">Father of Heshan, Age 7</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 mb-4">
                <div class="testi-card">
                    <div class="testi-stars">
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                    </div>

                    <p class="testi-text">
                        "We were worried about our daughter's stuttering, but the fluency techniques she learned here gave her so much confidence. She loves coming to therapy!"
                    </p>

                    <div class="testi-author">
                        <div class="testi-avatar avatar-orange">KS</div>
                        <div>
                            <div class="testi-name">Kavindi Silva</div>
                            <div class="testi-role">Mother of Yasiru, Age 6</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- Footer -->
<footer id="contact" class="footer">
    <div class="container-fluid px-5">
        <div class="row">

            <div class="col-md-6 mb-4">
                <div class="footer-logo">SpeechCare</div>
                <p class="footer-desc">
                    Helping children develop confident communication since 2020.
                </p>
            </div>

            <div class="col-md-3 mb-4">
                <h4 class="footer-col-title">Quick Links</h4>
                <div class="footer-links">
                    <a href="#home">Home</a>
                    <a href="#services">Services</a>
                    <a href="#about">About Us</a>
                </div>
            </div>

            <div class="col-md-3 mb-4">
                <h4 class="footer-col-title">Contact</h4>
                <div class="footer-links">
                    <a href="https://mail.google.com/mail/?view=cm&fs=1&to=speechcareclinic123@gmail.com" target="_blank">
    speechcareclinic123@gmail.com
</a>
                    <a href="tel:+94772634567">+94 77 263 4567</a>
                </div>
            </div>

        </div>
    </div>

    <div class="footer-bottom">
        <div class="container-fluid px-5">
            <span>
                <i class="fa-regular fa-copyright"></i>
                2026 SpeechCare. All rights reserved.
            </span>
        </div>
    </div>
</footer>

<!-- Bootstrap JS Files -->
<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>

</body>
</html>