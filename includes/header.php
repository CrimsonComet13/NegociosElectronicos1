<?php if (!isset($no_header)): ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #1a1a1a;
            --secondary-color: #f8f9fa;
            --accent-color: rgb(0, 17, 255);
        }
        
        .navbar {
            background-color: var(--primary-color) !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 0.8rem 0;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--secondary-color) !important;
            font-family: 'Playfair Display', serif;
        }
        
        .navbar-brand span {
            color: var(--accent-color);
        }
        
        .nav-link {
            color: var(--secondary-color) !important;
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            margin: 0 0.2rem;
            transition: all 0.3s ease;
            border-radius: 4px;
        }
        
        .nav-link:hover, .nav-link:focus {
            color: var(--accent-color) !important;
            transform: translateY(-2px);
        }
        
        .btn-admin {
            background-color: rgba(0, 17, 255, 0.8);
            border: 1px solid var(--accent-color);
            border-radius: 20px;
            padding: 0.3rem 1rem !important;
            transition: all 0.3s ease;
        }
        
        .btn-admin:hover {
            background-color: var(--accent-color);
            color: var(--primary-color) !important;
            transform: translateY(-2px);
        }
        
        .whatsapp-btn {
            background-color: #25D366;
            border-radius: 20px;
            padding: 0.3rem 1rem !important;
            transition: all 0.3s ease;
        }
        
        .whatsapp-btn:hover {
            background-color: #128C7E;
            transform: translateY(-2px);
        }
        
        .nav-buttons {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        @media (max-width: 992px) {
            .nav-buttons {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
                padding: 1rem 0;
            }
            
            .btn-admin, .whatsapp-btn {
                width: 100%;
                text-align: left;
                padding: 0.5rem 1rem !important;
            }
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container">
                <a class="navbar-brand" href="/">
                    <i class="bi bi-camera2 me-2"></i>ReminiscenciaPhotography
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto align-items-lg-center">
                        <li class="nav-item d-lg-none">
                            <a class="nav-link" href="tel:+524491543138">
                                <i class="bi bi-telephone me-2"></i>Llamar ahora
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link whatsapp-btn" href="https://wa.me/524491543138?text=Hola,%20quiero%20más%20información%20sobre%20sus%20servicios%20fotográficos" target="_blank">
                                <i class="fab fa-whatsapp me-2"></i>WhatsApp
                            </a>
                        </li>
                        <div class="nav-buttons">
                            <li class="nav-item">
                                <a class="nav-link" href="/agendar_llamada.php">
                                    <i class="bi bi-calendar-check me-2"></i>Agendar llamada
                                </a>
                            </li>
                            <li class="nav-item">
                                <?php
                                    // Solución definitiva para la redirección
                                    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
                                    $base_url .= $_SERVER['HTTP_HOST'];
                                    $colaborador_url = $base_url . '/login_colaborador.php';
                                ?>
                                                                <a class="nav-link" href="<?php echo $colaborador_url; ?>">
                                                                    <i class="bi bi-people-fill me-2"></i>Área colaboradores
                                                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link btn-admin" href="/admin_login.php">
                                    <i class="bi bi-shield-lock me-2"></i>Administración
                                </a>
                            </li>
                        </div>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php endif; ?>