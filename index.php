<?php
session_start();
require_once 'bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>aBility - Next Gen Stock Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Marvel:ital,wght@0,400;0,700;1,400;1,700&display=swap');

        * {
            font-family: "Marvel", sans-serif;
        }

        :root {
            --primary: #234c6a;
            --primary-light: #2c5a7a;
            --accent: #00d2ff;
            --dark: #0f172a;
        }

        body {
            background-color: var(--dark);
            color: #fff;
            overflow-x: hidden;
        }

        /* Hero Section */
        .hero-section {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            background: url('assets/images/hero_bg.png') no-repeat center center/cover;
            padding-top: 80px;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to bottom, rgba(15, 23, 42, 0.8), rgba(15, 23, 42, 0.95));
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-tag {
            background: rgba(35, 76, 106, 0.3);
            border: 1px solid var(--accent);
            color: var(--accent);
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-block;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(5px);
        }

        .hero-title {
            font-size: 4.5rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            background: linear-gradient(to right, #fff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            font-size: 1.25rem;
            color: #94a3b8;
            max-width: 600px;
            margin-bottom: 2.5rem;
        }

        /* Carousel Styling */
        .carousel-container {
            position: relative;
            z-index: 2;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 30px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .carousel-inner {
            border-radius: 20px;
        }

        .carousel-item img {
            height: 450px;
            object-fit: cover;
            filter: brightness(0.9);
        }

        .glass-caption {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 1.5rem;
            bottom: 30px;
            left: 8%;
            right: 8%;
            text-align: left;
        }

        .glass-caption h5 {
            font-weight: 700;
            color: #fff;
            margin-bottom: 0.5rem;
            font-size: 1.25rem;
        }

        .glass-caption p {
            color: #cbd5e1;
            margin-bottom: 0;
            font-size: 0.9rem;
        }

        /* Buttons */
        .btn-premium {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            border: none;
            padding: 14px 32px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px rgba(35, 76, 106, 0.3);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .btn-premium:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(35, 76, 106, 0.4);
            color: white;
        }

        .btn-outline-white {
            background: transparent;
            color: white !important;
            border: 2px solid rgba(255, 255, 255, 0.2);
            padding: 12px 30px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .btn-outline-white:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: white;
            color: white;
        }

        /* Feature Cards */
        .feature-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 24px;
            padding: 2.5rem;
            transition: all 0.3s ease;
            height: 100%;
        }

        .feature-card:hover {
            background: rgba(255, 255, 255, 0.04);
            transform: translateY(-10px);
            border-color: var(--accent);
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            background: rgba(0, 210, 255, 0.1);
            color: var(--accent);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
        }

        /* Nav */
        .navbar {
            padding: 20px 0;
            background: transparent;
            transition: all 0.3s ease;
        }

        .navbar.scrolled {
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(10px);
            padding: 15px 0;
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 1.5rem;
            color: #fff !important;
        }

        .navbar-brand span {
            color: var(--accent);
        }

        @media (max-width: 991px) {
            .hero-title {
                font-size: 3rem;
            }

            .hero-section {
                text-align: center;
            }

            .hero-subtitle {
                margin-left: auto;
                margin-right: auto;
            }

            .carousel-container {
                margin-top: 3rem;
            }
        }
    </style>
</head>

<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">a<span>Bility</span></a>
            <div class="ms-auto">
                <?php if (isLoggedIn()): ?>
                    <a href="dashboard_full.php" class="btn btn-premium btn-sm">Go to Dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-white btn-sm me-2">Login</a>
                    <a href="register.php" class="btn btn-premium btn-sm">Get Started</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content text-lg-start text-center">
                    <span class="hero-tag">
                        <i class="fas fa-sparkles me-2"></i>Coming Soon v2.0
                    </span>
                    <h1 class="hero-title">The Future of <br>Stock Control.</h1>
                    <p class="hero-subtitle">Experience a redefined workflow for asset tracking, inventory management, and logistics reporting. Faster, smarter, and more powerful than ever.</p>
                    <div class="d-flex gap-3 flex-wrap justify-content-lg-start justify-content-center">
                        <a href="login.php" class="btn btn-premium">
                            <i class="fas fa-sign-in-alt me-2"></i> Access Current System
                        </a>
                        <a href="#preview" class="btn btn-outline-white">
                            <i class="fas fa-eye me-2"></i> View Sneak Peek
                        </a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="carousel-container mt-lg-0 mt-5" id="preview">
                        <div id="landingCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel">
                            <div class="carousel-inner">
                                <div class="carousel-item active" data-bs-interval="5000">
                                    <img src="assets/mockups/dashboard.png" class="d-block w-100" alt="Dashboard">
                                    <div class="carousel-caption d-none d-md-block glass-caption">
                                        <h5>Intelligent Inventory Dashboard</h5>
                                        <p>Real-time tracking of assets with predictive analytics.</p>
                                    </div>
                                </div>
                                <div class="carousel-item" data-bs-interval="5000">
                                    <img src="assets/mockups/scan.png" class="d-block w-100" alt="Scanning">
                                    <div class="carousel-caption d-none d-md-block glass-caption">
                                        <h5>Mobile QR Scanning</h5>
                                        <p>Seamlessly scan equipment on the go using your mobile device.</p>
                                    </div>
                                </div>
                                <div class="carousel-item" data-bs-interval="5000">
                                    <img src="assets/mockups/analytics.png" class="d-block w-100" alt="Analytics">
                                    <div class="carousel-caption d-none d-md-block glass-caption">
                                        <h5>Advanced Asset Reporting</h5>
                                        <p>Generate comprehensive heatmaps and utilization reports.</p>
                                    </div>
                                </div>
                            </div>
                            <button class="carousel-control-prev" type="button" data-bs-target="#landingCarousel" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#landingCarousel" data-bs-slide="next">
                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5" style="background-color: var(--dark);">
        <div class="container py-5">
            <div class="text-center mb-5">
                <h2 class="fw-bold display-6">Coming Soon in v2.0</h2>
                <p class="text-muted">A completely new engine under the hood.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card text-center">
                        <div class="feature-icon mx-auto"><i class="fas fa-bolt"></i></div>
                        <h4 class="mb-3">Lightning Fast</h4>
                        <p class="text-muted">Optimized database queries and asynchronous updates for an instantaneous user experience.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card text-center">
                        <div class="feature-icon mx-auto"><i class="fas fa-shield-halved"></i></div>
                        <h4 class="mb-3">Enterprise Security</h4>
                        <p class="text-muted">Advanced role-based access control and encrypted digital signatures for every movement.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card text-center">
                        <div class="feature-icon mx-auto"><i class="fas fa-chart-pie"></i></div>
                        <h4 class="mb-3">Pro Analytics</h4>
                        <p class="text-muted">Visualize your stock lifecycle with beautiful charts and automated stocktaking reports.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-5 text-center border-top border-secondary border-opacity-10">
        <div class="container">
            <p class="text-muted small">&copy; <?php echo date('Y'); ?> aBility System. Next Generation Stock Management.</p>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                document.querySelector('.navbar').classList.add('scrolled');
            } else {
                document.querySelector('.navbar').classList.remove('scrolled');
            }
        });
    </script>
</body>

</html>