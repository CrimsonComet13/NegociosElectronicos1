<?php
require_once(__DIR__ . '/../includes/config.php');
require_once(__DIR__ . '/../includes/auth.php');

// Solo administradores pueden acceder
if (!isAdmin()) {
    header("Location: /login.php");
    exit();
}

$error = '';
$success = '';

// Procesar acciones
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $llamada_id = $_GET['id'] ?? 0;
    
    try {
        if ($action === 'delete') {
            // Eliminar llamada
            $stmt = $conn->prepare("DELETE FROM llamadas WHERE id = :id");
            $stmt->bindParam(':id', $llamada_id);
            $stmt->execute();
            
            $success = 'Llamada eliminada correctamente';
        } elseif ($action === 'update_status') {
            // Actualizar estado
            $nuevo_estado = $_GET['status'];
            $stmt = $conn->prepare("UPDATE llamadas SET estado = :estado WHERE id = :id");
            $stmt->bindParam(':estado', $nuevo_estado);
            $stmt->bindParam(':id', $llamada_id);
            $stmt->execute();
            
            $success = 'Estado de la llamada actualizado';
        }
    } catch(PDOException $e) {
        $error = 'Error al procesar la acción: ' . $e->getMessage();
    }
}

// Obtener todas las llamadas
try {
    $stmt = $conn->query("SELECT * FROM llamadas ORDER BY fecha DESC, hora DESC");
    $llamadas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = 'Error al obtener llamadas: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestión de Llamadas - Reminiscencia Photography</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
      --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
      --danger-gradient: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
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
    /* Stats Grid */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      margin-bottom: 2rem;
    }
    .stat-card {
      background: var(--card-bg);
      backdrop-filter: blur(20px);
      border: 1px solid var(--glass-border);
      border-radius: 16px;
      padding: 1.5rem;
      position: relative;
      overflow: hidden;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      cursor: pointer;
      animation: fadeInUp 0.6s ease forwards;
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
      transform: translateY(-5px);
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
      width: 40px;
      height: 40px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
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
      font-size: 1.8rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
    }
    .stat-label {
      color: var(--text-secondary);
      font-size: 0.8rem;
      margin-bottom: 0.5rem;
    }
    /* Card Styles */
    .card {
      background: var(--card-bg);
      backdrop-filter: blur(20px);
      border: 1px solid var(--glass-border);
      border-radius: 16px;
      overflow: hidden;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      animation: fadeInUp 0.6s ease forwards;
    }
    .card:hover {
      transform: translateY(-3px);
      box-shadow: var(--shadow-glow);
    }
    .card-header {
      padding: 1.2rem 1.5rem;
      border-bottom: 1px solid var(--glass-border);
      background: rgba(255, 255, 255, 0.02);
    }
    .card-title {
      font-size: 1.2rem;
      font-weight: 600;
      margin: 0;
      color: var(--text-primary);
    }
    .card-body {
      padding: 1.5rem;
    }
    /* Table Styles */
    .table {
      color: var(--text-primary);
      background: transparent;
      border-collapse: separate;
      border-spacing: 0;
      width: 100%;
    }
    .table th {
      background: rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(10px);
      border-bottom: 1px solid var(--glass-border);
      border-top: 1px solid var(--glass-border);
      color: var(--text-secondary);
      font-weight: 500;
      padding: 0.8rem;
      white-space: nowrap;
    }
    .table td {
      background: rgba(255, 255, 255, 0.03);
      backdrop-filter: blur(5px);
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
      padding: 0.8rem;
      vertical-align: middle;
    }
    .table tbody tr:hover td {
      background: rgba(255, 255, 255, 0.08);
    }
    /* Badge Styles */
    .badge {
      padding: 0.4rem 0.8rem;
      border-radius: 16px;
      font-weight: 500;
      font-size: 0.75rem;
    }
    .badge.bg-success {
      background: var(--success-gradient) !important;
    }
    .badge.bg-warning {
      background: var(--warning-gradient) !important;
      color: var(--dark-bg) !important;
    }
    .badge.bg-danger {
      background: var(--danger-gradient) !important;
    }
    .badge.bg-secondary {
      background: rgba(255, 255, 255, 0.2) !important;
    }
    /* Button Styles */
    .btn {
      border: none;
      border-radius: 10px;
      padding: 0.6rem 0.8rem;
      font-weight: 500;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
      font-size: 0.8rem;
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
    .btn-danger {
      background: var(--danger-gradient);
      color: white;
    }
    .btn-secondary {
      background: rgba(255, 255, 255, 0.1);
      color: var(--text-primary);
    }
    .btn-sm {
      padding: 0.3rem 0.6rem;
      font-size: 0.7rem;
    }
    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }
    /* Indicadores de estado */
    .status-indicator {
      display: inline-block;
      width: 8px;
      height: 8px;
      border-radius: 50%;
      margin-right: 6px;
    }
    .status-pendiente {
      background: #fee140;
      box-shadow: 0 0 8px rgba(254, 225, 64, 0.5);
    }
    .status-atendida {
      background: #00f2fe;
      box-shadow: 0 0 8px rgba(0, 242, 254, 0.5);
    }
    .status-cancelada {
      background: #ff6b6b;
      box-shadow: 0 0 8px rgba(255, 107, 107, 0.5);
    }
    /* Table-responsive mejorado */
    .table-responsive {
      position: relative;
      z-index: 1;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      scrollbar-width: thin;
      border-radius: 12px;
      margin-bottom: 1rem;
    }
    .table-responsive::-webkit-scrollbar {
      height: 6px;
    }
    .table-responsive::-webkit-scrollbar-track {
      background: rgba(255, 255, 255, 0.1);
      border-radius: 3px;
    }
    .table-responsive::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.3);
      border-radius: 3px;
    }
    .table-responsive::-webkit-scrollbar-thumb:hover {
      background: rgba(255, 255, 255, 0.5);
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
      align-items: center;
    }
    .mobile-nav-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      color: var(--text-secondary);
      text-decoration: none;
      transition: all 0.3s ease;
      padding: 0.3rem;
      border-radius: 8px;
      font-size: 0.7rem;
    }
    .mobile-nav-item.active,
    .mobile-nav-item:hover {
      color: var(--text-primary);
      background: rgba(255, 255, 255, 0.1);
    }
    .mobile-nav-item i {
      font-size: 1rem;
      margin-bottom: 0.2rem;
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
      width: 44px;
      height: 44px;
      color: var(--text-primary);
      font-size: 1.1rem;
      align-items: center;
      justify-content: center;
      cursor: pointer;
    }
    /* Alertas */
    .alert {
      padding: 1rem;
      border-radius: 12px;
      margin-bottom: 1.5rem;
    }
    /* Animaciones */
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    /* Estilos específicos para móviles */
    @media (max-width: 1024px) {
      .sidebar {
        transform: translateX(-100%);
        width: 280px;
      }
      .sidebar.open {
        transform: translateX(0);
      }
      .main-content {
        margin-left: 0;
      }
      .mobile-nav {
        display: flex;
      }
      .menu-toggle {
        display: flex;
      }
      .main-content {
        padding-bottom: 5rem;
      }
    }
    @media (max-width: 768px) {
      .main-content {
        padding: 1rem;
      }
      .header h1 {
        font-size: 1.8rem;
      }
      .header p {
        font-size: 1rem;
      }
      .stats-grid {
        grid-template-columns: 1fr;
      }
      .card-body {
        padding: 1rem;
      }
      .table th, 
      .table td {
        padding: 0.6rem;
        font-size: 0.85rem;
      }
      .badge {
        padding: 0.3rem 0.6rem;
        font-size: 0.7rem;
      }
      .btn {
        padding: 0.5rem 0.7rem;
        font-size: 0.75rem;
      }
    }
    @media (max-width: 576px) {
      .header h1 {
        font-size: 1.6rem;
      }
      .stat-card {
        padding: 1.2rem;
      }
      .stat-value {
        font-size: 1.5rem;
      }
      .table-responsive {
        border-radius: 10px;
      }
      .table th:first-child,
      .table td:first-child {
        min-width: 120px;
      }
      .mobile-nav-item {
        font-size: 0.6rem;
        padding: 0.2rem;
      }
      .mobile-nav-item i {
        font-size: 0.9rem;
      }
    }
    /* Estilo para tarjetas de llamadas en móvil */
    .mobile-call-card {
      background: var(--card-bg);
      backdrop-filter: blur(20px);
      border: 1px solid var(--glass-border);
      border-radius: 12px;
      padding: 1rem;
      margin-bottom: 1rem;
      transition: all 0.3s ease;
    }
    .mobile-call-card:hover {
      transform: translateY(-3px);
      box-shadow: var(--shadow-glow);
    }
    .call-status {
      display: inline-block;
      padding: 0.3rem 0.8rem;
      border-radius: 12px;
      font-size: 0.75rem;
      font-weight: 500;
      margin-bottom: 0.5rem;
    }
    .call-actions {
      display: flex;
      gap: 0.5rem;
      margin-top: 0.8rem;
      flex-wrap: wrap;
    }
    .call-actions .btn {
      flex: 1;
      min-width: calc(50% - 0.5rem);
    }
  </style>
</head>
<body>
  <!-- Botón de menú (solo móvil/tablet) -->
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
        <a href="dashboard.php" class="nav-link">
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
        <a href="llamadas.php" class="nav-link active">
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

  <!-- Contenido Principal -->
  <main class="main-content">
    <div class="header">
      <h1>Gestión de Llamadas</h1>
      <p>Administra las llamadas programadas con clientes</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
      <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>
        <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <!-- Tarjetas de Estadísticas -->
    <div class="stats-grid">
      <div class="stat-card" onclick="window.location.href='llamadas.php?status=pendiente'">
        <div class="stat-header">
          <div class="stat-icon">
            <i class="bi bi-hourglass"></i>
          </div>
        </div>
        <div class="stat-value">
          <?php 
            $count_pendientes = 0;
            foreach ($llamadas as $llamada) {
              if ($llamada['estado'] === 'pendiente') $count_pendientes++;
            }
            echo $count_pendientes;
          ?>
        </div>
        <div class="stat-label">Llamadas Pendientes</div>
      </div>

      <div class="stat-card success" onclick="window.location.href='llamadas.php?status=atendida'">
        <div class="stat-header">
          <div class="stat-icon">
            <i class="bi bi-check-circle"></i>
          </div>
        </div>
        <div class="stat-value">
          <?php 
            $count_atendidas = 0;
            foreach ($llamadas as $llamada) {
              if ($llamada['estado'] === 'atendida') $count_atendidas++;
            }
            echo $count_atendidas;
          ?>
        </div>
        <div class="stat-label">Llamadas Atendidas</div>
      </div>

      <div class="stat-card warning" onclick="window.location.href='llamadas.php'">
        <div class="stat-header">
          <div class="stat-icon">
            <i class="bi bi-telephone"></i>
          </div>
        </div>
        <div class="stat-value"><?php echo count($llamadas); ?></div>
        <div class="stat-label">Total de Llamadas</div>
      </div>
    </div>

    <!-- Tarjeta Principal con Tabla de Llamadas -->
    <div class="card">
      <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
          <h3 class="card-title">
            <i class="bi bi-telephone me-2"></i>
            Llamadas Agendadas
          </h3>
        </div>
      </div>
      <div class="card-body">
        <?php if (count($llamadas) > 0): ?>
          <!-- Vista de escritorio (tabla) -->
          <div class="d-none d-md-block">
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Nombre</th>
                    <th>Contacto</th>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                    // Filtrar por estado si viene en la URL
                    $filter_estado = $_GET['status'] ?? '';
                    foreach ($llamadas as $llamada): 
                      if ($filter_estado && strtolower($llamada['estado']) !== strtolower($filter_estado)) {
                        continue;
                      }
                  ?>
                    <tr>
                      <td>
                        <strong class="text-white"><?php echo htmlspecialchars($llamada['nombre']); ?></strong>
                        <?php if ($llamada['asunto']): ?>
                          <div class="text-white"><?php echo htmlspecialchars($llamada['asunto']); ?></div>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div class="d-flex flex-column text-white">
                          <span><i class="bi bi-telephone me-2 text-white"></i><?php echo htmlspecialchars($llamada['telefono']); ?></span>
                          <span><i class="bi bi-envelope me-2 text-white"></i><?php echo htmlspecialchars($llamada['email']); ?></span>
                        </div>
                      </td>
                      <td class="text-white"><?php echo date('d/m/Y', strtotime($llamada['fecha'])); ?></td>
                      <td class="text-white"><?php echo substr($llamada['hora'], 0, 5); ?></td>
                      <td>
                        <span class="badge bg-<?php 
                          switch($llamada['estado']) {
                            case 'pendiente': echo 'warning'; break;
                            case 'atendida': echo 'success'; break;
                            case 'cancelada': echo 'danger'; break;
                            default: echo 'secondary';
                          }
                        ?>">
                          <span class="  status-indicator status-<?php echo $llamada['estado']; ?>"></span>
                          <?php echo ucfirst($llamada['estado']); ?>
                        </span>
                      </td>
                      <td>
                        <div class="d-flex flex-wrap gap-2">
                          <?php if ($llamada['estado'] !== 'pendiente'): ?>
                            <a href="?action=update_status&id=<?php echo $llamada['id']; ?>&status=pendiente" 
                               class="btn btn-sm btn-warning" 
                               title="Marcar como Pendiente">
                              <i class="bi bi-hourglass"></i>
                            </a>
                          <?php endif; ?>

                          <?php if ($llamada['estado'] !== 'atendida'): ?>
                            <a href="?action=update_status&id=<?php echo $llamada['id']; ?>&status=atendida" 
                               class="btn btn-sm btn-success" 
                               title="Marcar como Atendida">
                              <i class="bi bi-check-circle"></i>
                            </a>
                          <?php endif; ?>

                          <?php if ($llamada['estado'] !== 'cancelada'): ?>
                            <a href="?action=update_status&id=<?php echo $llamada['id']; ?>&status=cancelada" 
                               class="btn btn-sm btn-danger" 
                               title="Marcar como Cancelada">
                              <i class="bi bi-x-circle"></i>
                            </a>
                          <?php endif; ?>

                          <a href="?action=delete&id=<?php echo $llamada['id']; ?>" 
                             onclick="return confirm('¿Estás seguro de eliminar esta llamada?')" 
                             class="btn btn-sm btn-secondary" 
                             title="Eliminar Llamada">
                            <i class="bi bi-trash"></i>
                          </a>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Vista móvil (tarjetas) -->
          <div class="d-md-none">
            <?php 
              foreach ($llamadas as $llamada): 
                if ($filter_estado && strtolower($llamada['estado']) !== strtolower($filter_estado)) {
                  continue;
                }
            ?>
              <div class="mobile-call-card">
                <span class="call-status bg-<?php 
                  switch($llamada['estado']) {
                    case 'pendiente': echo 'warning'; break;
                    case 'atendida': echo 'success'; break;
                    case 'cancelada': echo 'danger'; break;
                    default: echo 'secondary';
                  }
                ?>">
                  <span class="status-indicator status-<?php echo $llamada['estado']; ?>"></span>
                  <?php echo ucfirst($llamada['estado']); ?>
                </span>
                
                <h5><?php echo htmlspecialchars($llamada['nombre']); ?></h5>
                
                <?php if ($llamada['asunto']): ?>
                  <p class="text-white-50 mb-2"><?php echo htmlspecialchars($llamada['asunto']); ?></p>
                <?php endif; ?>
                
                <div class="d-flex align-items-center mb-1">
                  <i class="bi bi-telephone me-2"></i>
                  <span><?php echo htmlspecialchars($llamada['telefono']); ?></span>
                </div>
                
                <div class="d-flex align-items-center mb-2">
                  <i class="bi bi-envelope me-2"></i>
                  <span><?php echo htmlspecialchars($llamada['email']); ?></span>
                </div>
                
                <div class="d-flex justify-content-between text-white-50 mb-2">
                  <span><i class="bi bi-calendar me-1"></i> <?php echo date('d/m/Y', strtotime($llamada['fecha'])); ?></span>
                  <span><i class="bi bi-clock me-1"></i> <?php echo substr($llamada['hora'], 0, 5); ?></span>
                </div>
                
                <div class="call-actions">
                  <?php if ($llamada['estado'] !== 'pendiente'): ?>
                    <a href="?action=update_status&id=<?php echo $llamada['id']; ?>&status=pendiente" 
                       class="btn btn-warning" 
                       title="Marcar como Pendiente">
                      <i class="bi bi-hourglass"></i>
                    </a>
                  <?php endif; ?>

                  <?php if ($llamada['estado'] !== 'atendida'): ?>
                    <a href="?action=update_status&id=<?php echo $llamada['id']; ?>&status=atendida" 
                       class="btn btn-success" 
                       title="Marcar como Atendida">
                      <i class="bi bi-check-circle"></i>
                    </a>
                  <?php endif; ?>

                  <?php if ($llamada['estado'] !== 'cancelada'): ?>
                    <a href="?action=update_status&id=<?php echo $llamada['id']; ?>&status=cancelada" 
                       class="btn btn-danger" 
                       title="Marcar como Cancelada">
                      <i class="bi bi-x-circle"></i>
                    </a>
                  <?php endif; ?>

                  <a href="?action=delete&id=<?php echo $llamada['id']; ?>" 
                     onclick="return confirm('¿Estás seguro de eliminar esta llamada?')" 
                     class="btn btn-secondary" 
                     title="Eliminar Llamada">
                    <i class="bi bi-trash"></i>
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="alert alert-info text-center py-4">
            <i class="bi bi-telephone-x" style="font-size: 2.5rem; opacity: 0.5;"></i>
            <h4 class="mt-3">No hay llamadas registradas</h4>
            <p class="mb-0">Agrega nuevas llamadas para comenzar</p>
          </div>
        <?php endif; ?>
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

    // Cerrar sidebar al hacer clic fuera (en pantallas pequeñas)
    document.addEventListener('click', function(event) {
      const sidebar = document.getElementById('sidebar');
      const menuToggle = document.querySelector('.menu-toggle');
      
      if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
        sidebar.classList.remove('open');
      }
    });

    // Efecto ripple para botones y tarjetas
    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('.btn, .stat-card, .card, .mobile-call-card').forEach(element => {
        element.addEventListener('click', function(e) {
          // No crear ripple si es un enlace dentro del elemento
          if (e.target.tagName === 'A' || e.target.closest('a')) return;
          
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

      // Scroll horizontal vía arrastre (desktop/mobile)
      const tableResponsive = document.querySelector('.table-responsive');
      if (tableResponsive) {
        let startX, scrollLeft;
        let isDown = false;

        tableResponsive.addEventListener('mousedown', (e) => {
          isDown = true;
          startX = e.pageX - tableResponsive.offsetLeft;
          scrollLeft = tableResponsive.scrollLeft;
        });
        tableResponsive.addEventListener('mouseleave', () => { isDown = false; });
        tableResponsive.addEventListener('mouseup', () => { isDown = false; });
        tableResponsive.addEventListener('mousemove', (e) => {
          if (!isDown) return;
          e.preventDefault();
          const x = e.pageX - tableResponsive.offsetLeft;
          const walk = (x - startX) * 2;
          tableResponsive.scrollLeft = scrollLeft - walk;
        });

        // Eventos touch para móvil
        tableResponsive.addEventListener('touchstart', (e) => {
          isDown = true;
          startX = e.touches[0].pageX - tableResponsive.offsetLeft;
          scrollLeft = tableResponsive.scrollLeft;
        });
        tableResponsive.addEventListener('touchend', () => { isDown = false; });
        tableResponsive.addEventListener('touchmove', (e) => {
          if (!isDown) return;
          e.preventDefault();
          const x = e.touches[0].pageX - tableResponsive.offsetLeft;
          const walk = (x - startX) * 2;
          tableResponsive.scrollLeft = scrollLeft - walk;
        });
      }
    });

    // Agregar CSS para ripple animation
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