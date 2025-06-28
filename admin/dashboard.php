<?php
require_once(__DIR__ . '/../includes/config.php');
require_once(__DIR__ . '/../includes/auth.php');

// Solo administradores pueden acceder
if (!isAdmin()) {
    header("Location: /login.php");
    exit();
}

// Obtener estadísticas
try {
    // Total de eventos
    $stmt = $conn->query("SELECT COUNT(*) as total FROM eventos");
    $total_eventos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total de colaboradores
    $stmt = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE rol = 'colaborador'");
    $total_colaboradores = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total de clientes
    $stmt = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE rol = 'cliente'");
    $total_clientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Llamadas pendientes
    $stmt = $conn->query("SELECT COUNT(*) as total FROM llamadas WHERE estado = 'pendiente'");
    $llamadas_pendientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Últimos eventos
    $stmt = $conn->query("SELECT e.*, u.nombre as cliente_nombre 
                         FROM eventos e 
                         JOIN usuarios u ON e.cliente_id = u.id 
                         ORDER BY e.fecha_creacion DESC LIMIT 5");
    $ultimos_eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Error al obtener datos: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Admin - Reminiscencia Photography</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
      --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
      --info-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
      --dark-bg: #0f1419;
      --card-bg: rgba(255, 255, 255, 0.08);
      --glass-border: rgba(255, 255, 255, 0.18);
      --text-primary: #ffffff;
      --text-secondary: rgba(255, 255, 255, 0.7);
      --shadow-glow: 0 8px 32px rgba(0, 0, 0, 0.3);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: var(--dark-bg);
      background-image: 
        radial-gradient(circle at 20% 50%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%),
        radial-gradient(circle at 40% 80%, rgba(120, 219, 255, 0.3) 0%, transparent 50%);
      min-height: 100vh;
      color: var(--text-primary);
      overflow-x: hidden;
    }

    .sidebar {
      position: fixed;
      left: 0;
      top: 0;
      height: 100vh;
      width: 280px;
      background: rgba(15, 20, 25, 0.9);
      backdrop-filter: blur(20px);
      border-right: 1px solid var(--glass-border);
      z-index: 1000;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .sidebar-header {
      padding: 2rem 1.5rem;
      border-bottom: 1px solid var(--glass-border);
    }

    .logo {
      font-size: 1.5rem;
      font-weight: 700;
      background: var(--primary-gradient);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .nav-menu {
      padding: 1rem 0;
    }

    .nav-item {
      margin: 0.5rem 1rem;
    }

    .nav-link {
      display: flex;
      align-items: center;
      padding: 1rem 1.5rem;
      color: var(--text-secondary);
      text-decoration: none;
      border-radius: 12px;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }

    .nav-link::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: var(--primary-gradient);
      transition: left 0.3s ease;
      z-index: -1;
    }

    .nav-link:hover::before,
    .nav-link.active::before {
      left: 0;
    }

    .nav-link:hover,
    .nav-link.active {
      color: var(--text-primary);
      transform: translateX(5px);
    }

    .nav-link i {
      margin-right: 12px;
      font-size: 1.2rem;
      width: 20px;
    }

    .main-content {
      margin-left: 280px;
      padding: 2rem;
      min-height: 100vh;
      transition: all 0.3s ease;
    }

    .header {
      margin-bottom: 2rem;
    }

    .header h1 {
      font-size: 2.5rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
      background: var(--primary-gradient);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .header p {
      color: var(--text-secondary);
      font-size: 1.1rem;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      grid-template-rows: 1fr 1fr;
      gap: 20px;
      max-width: 800px;
      margin: 0 auto 40px auto;
    }

    .stat-card {
      background: var(--card-bg);
      backdrop-filter: blur(20px);
      border: 1px solid var(--glass-border);
      border-radius: 20px;
      padding: 2rem;
      position: relative;
      overflow: hidden;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      cursor: pointer;
      text-align: center;
    }

    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: var(--primary-gradient);
    }

    .stat-card:hover {
      transform: translateY(-8px);
      box-shadow: var(--shadow-glow);
    }

    .stat-card.success::before {
      background: var(--success-gradient);
    }

    .stat-card.warning::before {
      background: var(--warning-gradient);
    }

    .stat-card.info::before {
      background: var(--info-gradient);
    }

    .stat-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1rem;
    }

    .stat-icon {
      width: 50px;
      height: 50px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      background: var(--primary-gradient);
    }

    .stat-card.success .stat-icon {
      background: var(--success-gradient);
    }

    .stat-card.warning .stat-icon {
      background: var(--warning-gradient);
    }

    .stat-card.info .stat-icon {
      background: var(--info-gradient);
    }

    .stat-value {
      font-size: 2.5rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
    }

    .stat-label {
      color: var(--text-secondary);
      font-size: 0.9rem;
      margin-bottom: 1rem;
    }

    .stat-link {
      color: var(--text-primary);
      text-decoration: none;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.3s ease;
    }

    .stat-link:hover {
      color: var(--text-primary);
      transform: translateX(5px);
    }

    .content-grid {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 2rem;
      align-items: stretch;
    }

    .card {
      background: var(--card-bg);
      backdrop-filter: blur(20px);
      border: 1px solid var(--glass-border);
      border-radius: 20px;
      overflow: hidden;
      height: 100%;
      display: flex;
      flex-direction: column;
    }

    .card-body {
      flex: 1;
      padding: 2rem;
    }

    .card-header {
      padding: 1.5rem 2rem;
      border-bottom: 1px solid var(--glass-border);
      background: rgba(255, 255, 255, 0.02);
    }

    .card-title {
      font-size: 1.3rem;
      font-weight: 600;
      margin: 0;
      color: var(--text-primary);
    }

    .events-list {
      display: flex;
      flex-direction: column;
      gap: 0;
    }

    .event-item {
      display: flex;
      align-items: center;
      padding: 1.5rem 2rem;
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      overflow: hidden;
    }

    .event-item:last-child {
      border-bottom: none;
    }

    .event-item::before {
      content: '';
      position: absolute;
      left: 0;
      top: 0;
      bottom: 0;
      width: 4px;
      background: var(--primary-gradient);
      transform: scaleY(0);
      transition: transform 0.3s ease;
    }

    .event-item:hover {
      background: rgba(255, 255, 255, 0.03);
      transform: translateX(8px);
    }

    .event-item:hover::before {
      transform: scaleY(1);
    }

    .event-icon {
      width: 50px;
      height: 50px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.3rem;
      margin-right: 1.5rem;
      background: var(--primary-gradient);
      color: white;
      flex-shrink: 0;
    }

    .event-icon.graduacion {
      background: var(--success-gradient);
    }

    .event-icon.xv,
    .event-icon.quinceañera {
      background: var(--warning-gradient);
      color: var(--dark-bg);
    }

    .event-icon.bautizo {
      background: var(--info-gradient);
      color: var(--dark-bg);
    }

    .event-icon.boda {
      background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
    }

    .event-icon.sesion {
      background: var(--primary-gradient);
    }

    .event-info {
      flex: 1;
      display: grid;
      grid-template-columns: 2fr 1fr 1fr 120px;
      gap: 1rem;
      align-items: center;
    }

    .event-name {
      display: flex;
      flex-direction: column;
    }

    .event-title {
      font-size: 1.1rem;
      font-weight: 600;
      color: var(--text-primary);
      margin-bottom: 0.25rem;
    }

    .event-type {
      font-size: 0.85rem;
      color: var(--text-secondary);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .event-date {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      color: var(--text-secondary);
      font-size: 0.9rem;
    }

    .event-client {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .client-avatar {
      width: 35px;
      height: 35px;
      border-radius: 50%;
      background: var(--primary-gradient);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      font-size: 0.8rem;
      color: white;
    }

    .client-name {
      font-size: 0.95rem;
      color: var(--text-primary);
    }

    .event-status {
      display: flex;
      justify-content: center;
    }

    .status-badge {
      padding: 0.6rem 1.2rem;
      border-radius: 25px;
      font-weight: 500;
      font-size: 0.8rem;
      text-align: center;
      min-width: 100px;
      border: none;
      position: relative;
      overflow: hidden;
    }

    .status-badge::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: rgba(255, 255, 255, 0.2);
      transition: left 0.3s ease;
    }

    .status-badge:hover::before {
      left: 100%;
    }

    .status-pendiente {
      background: var(--warning-gradient);
      color: var(--dark-bg);
    }

    .status-confirmado {
      background: var(--success-gradient);
      color: white;
    }

    .status-completado {
      background: var(--info-gradient);
      color: var(--dark-bg);
    }

    .status-cancelado {
      background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
      color: white;
    }

    .empty-state {
      text-align: center;
      padding: 4rem 2rem;
      color: var(--text-secondary);
    }

    .empty-state i {
      font-size: 4rem;
      opacity: 0.3;
      margin-bottom: 1rem;
      display: block;
    }

    .empty-state p {
      font-size: 1.1rem;
      margin-bottom: 0;
    }

    .quick-actions {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .btn {
      border: none;
      border-radius: 12px;
      padding: 0.8rem 1.5rem;
      font-weight: 500;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
      width: 100%;
      text-align: center;
    }

    .btn-primary {
      background: var(--primary-gradient);
      color: white;
    }

    .btn-success {
      background: var(--success-gradient);
      color: white;
    }

    .btn-warning {
      background: var(--warning-gradient);
      color: var(--dark-bg);
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    }

    .alert {
      background: rgba(255, 107, 107, 0.1);
      border: 1px solid rgba(255, 107, 107, 0.2);
      border-radius: 12px;
      color: #ff6b6b;
    }

    .menu-toggle {
      display: none;
      position: fixed;
      top: 1rem;
      left: 1rem;
      z-index: 1001;
      background: var(--card-bg);
      backdrop-filter: blur(20px);
      border: 1px solid var(--glass-border);
      border-radius: 50%;
      width: 50px;
      height: 50px;
      color: var(--text-primary);
      font-size: 1.2rem;
      align-items: center;
      justify-content: center;
      cursor: pointer;
    }

    /* Mobile Navigation */
    .mobile-nav {
      display: none;
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      background: rgba(15, 20, 25, 0.95);
      backdrop-filter: blur(20px);
      border-top: 1px solid var(--glass-border);
      padding: 0.5rem;
      z-index: 1000;
    }

    .mobile-nav-items {
      display: flex;
      justify-content: space-around;
      width: 100%;
    }

    .mobile-nav-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      color: var(--text-secondary);
      text-decoration: none;
      padding: 0.5rem;
      font-size: 0.8rem;
      transition: all 0.3s ease;
    }

    .mobile-nav-item i {
      font-size: 1.2rem;
      margin-bottom: 0.25rem;
    }

    .mobile-nav-item.active,
    .mobile-nav-item:hover {
      color: var(--text-primary);
      background: rgba(255, 255, 255, 0.1);
    }

    /* Animations */
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes slideInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .stat-card {
      animation: fadeInUp 0.6s ease forwards;
    }

    .event-item {
      animation: slideInUp 0.6s ease forwards;
    }

    .stat-card:nth-child(1) { animation-delay: 0.1s; }
    .stat-card:nth-child(2) { animation-delay: 0.2s; }
    .stat-card:nth-child(3) { animation-delay: 0.3s; }
    .stat-card:nth-child(4) { animation-delay: 0.4s; }
    
    .event-item:nth-child(1) { animation-delay: 0.1s; }
    .event-item:nth-child(2) { animation-delay: 0.2s; }
    .event-item:nth-child(3) { animation-delay: 0.3s; }
    .event-item:nth-child(4) { animation-delay: 0.4s; }
    .event-item:nth-child(5) { animation-delay: 0.5s; }

    /* Responsive Design */
    @media (max-width: 1024px) {
      .sidebar {
        transform: translateX(-100%);
      }

      .sidebar.open {
        transform: translateX(0);
      }

      .main-content {
        margin-left: 0;
      }

      .content-grid {
        grid-template-columns: 1fr;
      }

      .mobile-nav {
        display: flex;
      }

      .menu-toggle {
        display: flex;
      }

      .main-content {
        padding-bottom: 6rem;
      }
    }

    @media (max-width: 768px) {
      .main-content {
        padding: 1rem;
      }

      .header h1 {
        font-size: 2rem;
      }

      .header p {
        font-size: 1rem;
      }

      .stats-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
      }

      .stat-card {
        padding: 1.5rem;
      }

      .stat-value {
        font-size: 2rem;
      }

      .event-item {
        padding: 1rem;
        flex-direction: column;
      }

      .event-icon {
        margin-right: 0;
        margin-bottom: 1rem;
        width: 40px;
        height: 40px;
        font-size: 1.1rem;
      }

      .event-info {
        grid-template-columns: 1fr;
        width: 100%;
      }

      .event-date, 
      .event-client {
        display: none;
      }

      .event-status {
        justify-content: flex-start;
        margin-top: 1rem;
      }

      .status-badge {
        min-width: 70px;
        padding: 0.5rem 1rem;
      }

      .btn {
        padding: 1rem;
        font-size: 1rem;
      }
    }

    @media (max-width: 480px) {
      .sidebar {
        width: 85%;
      }

      .header h1 {
        font-size: 1.8rem;
      }

      .stat-card {
        padding: 1.2rem;
      }

      .stat-value {
        font-size: 1.8rem;
      }

      .event-title {
        font-size: 1rem;
      }

      .mobile-nav-item {
        padding: 0.3rem;
        font-size: 0.7rem;
      }

      .mobile-nav-item i {
        font-size: 1rem;
      }
    }
  </style>
</head>
<body>
  <!-- Menu Toggle Button -->
  <button class="menu-toggle btn" onclick="toggleSidebar()">
    <i class="bi bi-list"></i>
  </button>

  <!-- Sidebar -->
  <nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="logo">
        <i class="bi bi-camera-reels"></i>
        Reminiscencia
      </div>
    </div>
    
    <div class="nav-menu">
      <div class="nav-item">
        <a href="dashboard.php" class="nav-link active">
          <i class="bi bi-grid-fill"></i>
          <span>Dashboard</span>
        </a>
      </div>
      <div class="nav-item">
        <a href="gestion_eventos.php" class="nav-link">
          <i class="bi bi-calendar-event-fill"></i>
          <span>Eventos</span>
        </a>
      </div>
      <div class="nav-item">
        <a href="gestion_colaboradores.php" class="nav-link">
          <i class="bi bi-people-fill"></i>
          <span>Colaboradores</span>
        </a>
      </div>
      <div class="nav-item">
        <a href="gestion_clientes.php" class="nav-link">
          <i class="bi bi-person-lines-fill"></i>
          <span>Clientes</span>
        </a>
      </div>
      <div class="nav-item">
        <a href="llamadas.php" class="nav-link">
          <i class="bi bi-telephone-fill"></i>
          <span>Llamadas</span>
        </a>
      </div>
      <div class="nav-item" style="margin-top: 2rem;">
        <a href="/logout.php" class="nav-link" style="color: #ff6b6b;">
          <i class="bi bi-box-arrow-right"></i>
          <span>Cerrar Sesión</span>
        </a>
      </div>
    </div>
  </nav>

  <!-- Main Content -->
  <main class="main-content">
    <div class="header">
      <h1>Dashboard</h1>
      <p>Bienvenido al panel de administración de Reminiscencia Photography</p>
    </div>

    <?php if (isset($error)): ?>
      <div class="alert alert-danger mb-4">
        <i class="bi bi-exclamation-triangle"></i>
        <?php echo $error; ?>
      </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card" onclick="window.location.href='gestion_eventos.php'">
        <div class="stat-header">
          <div class="stat-icon">
            <i class="bi bi-calendar-event"></i>
          </div>
        </div>
        <div class="stat-value"><?php echo $total_eventos; ?></div>
        <div class="stat-label">Total de Eventos</div>
        <a href="gestion_eventos.php" class="stat-link">
          Ver todos <i class="bi bi-arrow-right"></i>
        </a>
      </div>
      <div class="stat-card success" onclick="window.location.href='gestion_colaboradores.php'">
        <div class="stat-header">
          <div class="stat-icon">
            <i class="bi bi-people"></i>
          </div>
        </div>
        <div class="stat-value"><?php echo $total_colaboradores; ?></div>
        <div class="stat-label">Colaboradores Activos</div>
        <a href="gestion_colaboradores.php" class="stat-link">
          Ver todos <i class="bi bi-arrow-right"></i>
        </a>
      </div>
      <div class="stat-card info" onclick="window.location.href='gestion_clientes.php'">
        <div class="stat-header">
          <div class="stat-icon">
            <i class="bi bi-person-lines-fill"></i>
          </div>
        </div>
        <div class="stat-value"><?php echo $total_clientes; ?></div>
        <div class="stat-label">Clientes Registrados</div>
        <a href="gestion_clientes.php" class="stat-link">
          Ver todos <i class="bi bi-arrow-right"></i>
        </a>
      </div>
      <div class="stat-card warning" onclick="window.location.href='llamadas.php'">
        <div class="stat-header">
          <div class="stat-icon">
            <i class="bi bi-telephone"></i>
          </div>
        </div>
        <div class="stat-value"><?php echo $llamadas_pendientes; ?></div>
        <div class="stat-label">Llamadas Pendientes</div>
        <a href="llamadas.php" class="stat-link">
          Ver todas <i class="bi bi-arrow-right"></i>
        </a>
      </div>
    </div>

    <!-- Content Grid -->
    <div class="content-grid">
      <!-- Recent Events -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">
            <i class="bi bi-clock-history me-2"></i>
            Eventos Recientes
          </h3>
        </div>
        <div class="card-body">
          <?php if (count($ultimos_eventos) > 0): ?>
            <div class="events-list">
              <?php foreach ($ultimos_eventos as $evento): ?>
                <div class="event-item">
                  <div class="event-icon <?php echo strtolower($evento['tipo']); ?>">
                    <?php
                    $iconos = [
                      'graduacion' => 'bi-mortarboard-fill',
                      'xv' => 'bi-gem',
                      'bautizo' => 'bi-droplet-fill',
                      'boda' => 'bi-heart-fill',
                      'sesion' => 'bi-camera-fill',
                      'otro' => 'bi-calendar-event-fill'
                    ];
                    $tipo_lower = strtolower($evento['tipo']);
                    $icono = isset($iconos[$tipo_lower]) ? $iconos[$tipo_lower] : $iconos['otro'];
                    ?>
                    <i class="bi <?php echo $icono; ?>"></i>
                  </div>
                  <div class="event-info">
                    <div class="event-name">
                      <div class="event-title"><?php echo htmlspecialchars($evento['nombre']); ?></div>
                      <div class="event-type"><?php echo htmlspecialchars($evento['tipo']); ?></div>
                    </div>
                    <div class="event-date mobile-hidden">
                      <i class="bi bi-calendar3"></i>
                      <span><?php echo date('d/m/Y', strtotime($evento['fecha_evento'])); ?></span>
                    </div>
                    <div class="event-client mobile-hidden">
                      <div class="client-avatar">
                        <?php 
                        $nombres = explode(' ', $evento['cliente_nombre']);
                        $iniciales = '';
                        foreach ($nombres as $nombre) {
                          $iniciales .= strtoupper(substr($nombre, 0, 1));
                        }
                        echo substr($iniciales, 0, 2);
                        ?>
                      </div>
                      <div class="client-name"><?php echo htmlspecialchars($evento['cliente_nombre']); ?></div>
                    </div>
                    <div class="event-status">
                      <span class="status-badge status-<?php echo $evento['estado']; ?>">
                        <?php echo ucfirst($evento['estado']); ?>
                      </span>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="empty-state">
              <i class="bi bi-calendar-x"></i>
              <p>No hay eventos registrados</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
      
      <!-- Quick Actions -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">
            <i class="bi bi-lightning-fill me-2"></i>
            Acciones Rápidas
          </h3>
        </div>
        <div class="card-body">
          <div class="quick-actions">
            <form action="formulario_colaborador.php" method="post">
              <button type="submit" class="btn btn-success w-100">
                <i class="bi bi-person-plus"></i>
                Registrar Colaborador
              </button>
            </form>
            
            <a href="llamadas.php" class="btn btn-warning w-100">
              <i class="bi bi-telephone"></i>
              Gestionar Llamadas
            </a>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Toggle sidebar
    function toggleSidebar() {
      const sidebar = document.getElementById('sidebar');
      sidebar.classList.toggle('open');
    }

    // Close sidebar when clicking outside
    document.addEventListener('click', function(event) {
      const sidebar = document.getElementById('sidebar');
      const menuToggle = document.querySelector('.menu-toggle');
      
      if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
        sidebar.classList.remove('open');
      }
    });

    // Add smooth scrolling and animations
    document.addEventListener('DOMContentLoaded', function() {
      // Intersection Observer for animations
      const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
      };

      const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.style.animationPlayState = 'running';
          }
        });
      }, observerOptions);

      // Observe all stat cards
      document.querySelectorAll('.stat-card').forEach(card => {
        observer.observe(card);
      });

      // Observe all event items
      document.querySelectorAll('.event-item').forEach(item => {
        observer.observe(item);
      });

      // Add click ripple effect
      document.querySelectorAll('.btn, .stat-card').forEach(element => {
        element.addEventListener('click', function(e) {
          const ripple = document.createElement('span');
          const rect = this.getBoundingClientRect();
          const size = Math.max(rect.width, rect.height);
          const x = e.clientX - rect.left - size / 2;
          const y = e.clientY - rect.top - size / 2;
          
          ripple.style.cssText = `
            position: absolute;
            width: ${size}px;
            height: ${size}px;
            left: ${x}px;
            top: ${y}px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: scale(0);
            animation: ripple 0.6s ease-out;
            pointer-events: none;
          `;
          
          this.style.position = 'relative';
          this.style.overflow = 'hidden';
          this.appendChild(ripple);
          
          setTimeout(() => ripple.remove(), 600);
        });
      });

      // Add hover effects for event items
      document.querySelectorAll('.event-item').forEach(item => {
        item.addEventListener('mouseenter', function() {
          this.style.transform = 'translateX(12px)';
          this.style.boxShadow = '0 8px 32px rgba(255, 255, 255, 0.1)';
        });

        item.addEventListener('mouseleave', function() {
          this.style.transform = 'translateX(0)';
          this.style.boxShadow = 'none';
        });
      });
    });

    // Add CSS for ripple animation
    const style = document.createElement('style');
    style.textContent = `
      @keyframes ripple {
        to {
          transform: scale(2);
          opacity: 0;
        }
      }
    `;
    document.head.appendChild(style);
  </script>
</body>
</html>