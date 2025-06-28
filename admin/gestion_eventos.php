<?php
session_start();
require_once(__DIR__ . '/../includes/config.php');
require_once(__DIR__ . '/../includes/auth.php');

// Solo administradores pueden acceder
if (!isAdmin()) {
    header("Location: /login.php");
    exit();
}

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Manejar mensajes flash
$error = $_SESSION['flash_error'] ?? '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['flash_error'] = 'Token CSRF inválido';
        header("Location: gestion_eventos.php");
        exit();
    }

    // Validar acción (lista blanca)
    $allowed_actions = ['delete', 'update_status'];
    $action = $_POST['action'];
    if (!in_array($action, $allowed_actions)) {
        $_SESSION['flash_error'] = 'Acción no permitida';
        header("Location: gestion_eventos.php");
        exit();
    }

    // Validar ID (debe ser entero positivo)
    $evento_id = $_POST['id'] ?? 0;
    if (!is_numeric($evento_id) || $evento_id <= 0) {
        $_SESSION['flash_error'] = 'ID de evento inválido';
        header("Location: gestion_eventos.php");
        exit();
    }
    $evento_id = intval($evento_id);

    try {
        if ($action === 'delete') {
            // Eliminar primero las relaciones con colaboradores
            $stmt = $conn->prepare("DELETE FROM evento_colaboradores WHERE evento_id = :id");
            $stmt->bindParam(':id', $evento_id);
            $stmt->execute();
            
            // Luego eliminar el evento
            $stmt = $conn->prepare("DELETE FROM eventos WHERE id = :id");
            $stmt->bindParam(':id', $evento_id);
            $stmt->execute();
            
            $_SESSION['flash_success'] = 'Evento eliminado correctamente';
        } elseif ($action === 'update_status') {
            // Validar estado (lista blanca)
            $estados_permitidos = ['pendiente', 'confirmado', 'en_progreso', 'completado', 'cancelado'];
            $nuevo_estado = $_POST['status'] ?? '';
            
            if (!in_array($nuevo_estado, $estados_permitidos)) {
                $_SESSION['flash_error'] = 'Estado no válido';
                header("Location: gestion_eventos.php");
                exit();
            }
            
            // Actualizar estado
            $stmt = $conn->prepare("UPDATE eventos SET estado = :estado WHERE id = :id");
            $stmt->bindParam(':estado', $nuevo_estado);
            $stmt->bindParam(':id', $evento_id);
            $stmt->execute();
            
            $_SESSION['flash_success'] = 'Estado del evento actualizado';
        }
        
        // Redirigir para evitar reenvío de formulario
        header("Location: gestion_eventos.php");
        exit();
        
    } catch(PDOException $e) {
        $_SESSION['flash_error'] = 'Error al procesar la acción: ' . $e->getMessage();
        header("Location: gestion_eventos.php");
        exit();
    }
}

// Definir estados y tipos de evento para mostrar
$estados_evento = [
    'pendiente'    => 'Pendiente',
    'confirmado'   => 'Confirmado',
    'en_progreso'  => 'En Progreso',
    'completado'   => 'Completado',
    'cancelado'    => 'Cancelado'
];

$tipos_evento = [
    'bodas'        => 'Boda',
    'xv'           => 'XV Años',
    'graduaciones' => 'Graduación',
    'corporativos' => 'Evento Corporativo',
    'otro'         => 'Otro'
];

// Paginación
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$eventos_por_pagina = 10;
$offset = ($pagina_actual - 1) * $eventos_por_pagina;

// Obtener total de eventos para paginación
$total_eventos = 0;
try {
    $stmt = $conn->query("SELECT COUNT(*) as total FROM eventos");
    $total_eventos = $stmt->fetchColumn();
} catch(PDOException $e) {
    $error = 'Error al contar eventos: ' . $e->getMessage();
}

// Obtener eventos con información de colaboradores (con paginación)
try {
    $stmt = $conn->prepare(
        "SELECT e.*, 
                u.nombre AS cliente_nombre,
                GROUP_CONCAT(uc.nombre SEPARATOR ', ') AS colaboradores
         FROM eventos e
         JOIN usuarios u ON e.cliente_id = u.id
         LEFT JOIN evento_colaboradores ec ON e.id = ec.evento_id
         LEFT JOIN usuarios uc ON ec.colaborador_id = uc.id
         GROUP BY e.id
         ORDER BY e.fecha_evento DESC
         LIMIT :limit OFFSET :offset"
    );
    
    $stmt->bindValue(':limit', $eventos_por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = 'Error al obtener eventos: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Gestión de Eventos - Reminiscencia Photography</title>
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
  />
  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
  />
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
    rel="stylesheet"
  />
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

    /* Sidebar Styles */
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

    /* Main Content Styles */
    .main-content {
      margin-left: 280px;
      padding: 2rem;
      min-height: 100vh;
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

    /* Card Styles */
    .card {
      background: var(--card-bg);
      backdrop-filter: blur(20px);
      border: 1px solid var(--glass-border);
      border-radius: 20px;
      overflow: hidden;
      margin-bottom: 2rem;
      animation: fadeInUp 0.6s ease forwards;
    }

    .card-header {
      padding: 1.5rem 2rem;
      border-bottom: 1px solid var(--glass-border);
      background: rgba(255, 255, 255, 0.02);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .card-title {
      font-size: 1.3rem;
      font-weight: 600;
      margin: 0;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .card-body {
      padding: 2rem;
    }

    /* Alert Styles */
    .alert {
      border: none;
      border-radius: 12px;
      padding: 1rem 1.5rem;
      margin-bottom: 1.5rem;
      backdrop-filter: blur(20px);
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .alert-danger {
      background: rgba(255, 107, 107, 0.1);
      border: 1px solid rgba(255, 107, 107, 0.2);
      color: #ff6b6b;
    }

    .alert-success {
      background: rgba(75, 181, 67, 0.1);
      border: 1px solid rgba(75, 181, 67, 0.2);
      color: #4bb543;
    }

    /* Button Styles */
    .btn {
      border: none;
      border-radius: 12px;
      padding: 0.8rem 1rem;
      font-weight: 500;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
      display: inline-flex;
      align-items: center;
      justify-content: center;
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
      backdrop-filter: blur(10px);
    }

    .btn-sm {
      padding: 0.4rem 0.8rem;
      font-size: 0.75rem;
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    }

    /* Ripple Effect */
    @keyframes ripple {
      to {
        transform: scale(2);
        opacity: 0;
      }
    }

    /* Table Styles */
    .table-responsive {
      position: relative;
      z-index: 1;
      overflow-x: auto;
      overflow-y: hidden;
      -webkit-overflow-scrolling: touch;
      scrollbar-width: thin;
      border-radius: 12px;
      margin-bottom: 1rem;
    }

    .table-responsive::-webkit-scrollbar {
      height: 8px;
    }

    .table-responsive::-webkit-scrollbar-track {
      background: rgba(255, 255, 255, 0.1);
      border-radius: 4px;
    }

    .table-responsive::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.3);
      border-radius: 4px;
    }

    .table-responsive::-webkit-scrollbar-thumb:hover {
      background: rgba(255, 255, 255, 0.5);
    }

    .table {
      color: var(--text-primary);
      background: transparent;
      border-collapse: separate;
      border-spacing: 0;
      position: relative;
      z-index: auto;
      min-width: 100%;
      width: auto;
      white-space: nowrap;
    }

    .table th {
      background: rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(10px);
      border-bottom: 1px solid var(--glass-border);
      border-top: 1px solid var(--glass-border);
      color: var(--text-secondary);
      font-weight: 500;
      padding: 1rem;
      position: sticky;
      top: 0;
    }

    .table td {
      background: rgba(255, 255, 255, 0.03);
      backdrop-filter: blur(5px);
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
      padding: 1rem;
      vertical-align: middle;
    }

    .table tbody tr:hover td {
      background: rgba(255, 255, 255, 0.08);
    }

    .table th:first-child,
    .table td:first-child {
      min-width: 80px;
      position: sticky;
      left: 0;
      z-index: 2;
      background: rgba(15, 20, 25, 0.95);
    }

    .table th:nth-child(2),
    .table td:nth-child(2) {
      min-width: 200px;
    }

    .table th:nth-child(3),
    .table td:nth-child(3) {
      min-width: 120px;
    }

    .table th:nth-child(4),
    .table td:nth-child(4) {
      min-width: 160px;
    }

    .table th:nth-child(5),
    .table td:nth-child(5) {
      min-width: 180px;
    }

    .table th:nth-child(6),
    .table td:nth-child(6) {
      min-width: 140px;
    }

    .table th:last-child,
    .table td:last-child {
      min-width: 200px;
    }

    /* Status Indicator */
    .status-indicator {
      display: inline-block;
      width: 10px;
      height: 10px;
      border-radius: 50%;
      margin-right: 8px;
    }

    .status-pendiente {
      background: #fee140;
      box-shadow: 0 0 10px rgba(254, 225, 64, 0.5);
    }

    .status-confirmado {
      background: #00f2fe;
      box-shadow: 0 0 10px rgba(0, 242, 254, 0.5);
    }

    .status-en_progreso {
      background: #a8edea;
      box-shadow: 0 0 10px rgba(168, 237, 234, 0.5);
    }

    .status-completado {
      background: #667eea;
      box-shadow: 0 0 10px rgba(102, 126, 234, 0.5);
    }

    .status-cancelado {
      background: #ff6b6b;
      box-shadow: 0 0 10px rgba(255, 107, 107, 0.5);
    }

    /* Client Avatar */
    .client-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: var(--primary-gradient);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      font-size: 0.9rem;
      color: white;
      margin-right: 0.5rem;
      flex-shrink: 0;
    }

    .client-info {
      display: flex;
      align-items: center;
    }

    /* Responsive */
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
    }

    @media (max-width: 768px) {
      .main-content {
        padding: 1rem;
      }

      .header h1 {
        font-size: 2rem;
      }

      .table th:nth-child(4),
      .table td:nth-child(4) {
        display: none;
      }

      .table th:nth-child(5),
      .table td:nth-child(5) {
        display: none;
      }
    }

    @media (max-width: 576px) {
      .table th:nth-child(3),
      .table td:nth-child(3) {
        display: none;
      }
    }

    /* Animation */
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

    /* Pagination Styles */
    .pagination {
      margin-top: 1.5rem;
    }

    .page-link {
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid var(--glass-border);
      color: var(--text-secondary);
      margin: 0 0.25rem;
      border-radius: 8px !important;
    }

    .page-link:hover {
      background: var(--primary-gradient);
      color: white;
      border-color: transparent;
    }

    .page-item.active .page-link {
      background: var(--primary-gradient);
      border-color: transparent;
    }

  </style>
</head>
<body>
  <!-- Botón de menú (solo móvil/tablet) -->
  <button class="menu-toggle btn btn-secondary" onclick="toggleSidebar()">
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
        <a href="gestion_eventos.php" class="nav-link active">
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

  <!-- Contenido Principal -->
  <main class="main-content">
    <div class="header">
      <h1>Gestión de Eventos</h1>
      <p>Administra todos los eventos de fotografía de Reminiscencia Photography</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>
        <?php echo htmlspecialchars($success); ?>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title text-white">
          <i class="bi bi-calendar-event-fill text-white"></i>
          Todos los Eventos
        </h3>
      </div>
      <div class="card-body">
        <?php if (count($eventos) > 0): ?>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th class="text-white"></th>
                  <th class="text-white">Evento</th>
                  <th class="text-white">Fecha</th>
                  <th class="text-white">Cliente</th>
                  <th class="text-white">Colaboradores</th>
                  <th class="text-white">Estado</th>
                  <th class="text-white">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($eventos as $evento): ?>
                  <tr>
                    <!-- Ícono según tipo -->
                    <td>
                      <?php
                        $iconos = [
                          'graduaciones' => 'bi-mortarboard-fill',
                          'xv'           => 'bi-gem',
                          'bodas'        => 'bi-heart-fill',
                          'corporativos' => 'bi-briefcase-fill',
                          'otro'         => 'bi-camera-fill'
                        ];
                        $icono = $iconos[$evento['tipo']] ?? $iconos['otro'];
                        $bg_class = match($evento['tipo']) {
                          'graduaciones' => 'background: var(--success-gradient); color: var(--dark-bg);',
                          'xv'           => 'background: var(--warning-gradient); color: var(--dark-bg);',
                          'bodas'        => 'background: var(--danger-gradient); color: white;',
                          'corporativos' => 'background: var(--info-gradient); color: var(--dark-bg);',
                          default        => 'background: var(--primary-gradient); color: white;'
                        };
                      ?>
                      <div
                        class="d-flex align-items-center justify-content-center"
                        style="width: 40px; height: 40px; border-radius: 8px; <?php echo $bg_class; ?>"
                      >
                        <i class="bi <?php echo $icono; ?>"></i>
                      </div>
                    </td>

                    <!-- Nombre y tipo de evento -->
                    <td>
                      <div class="d-flex flex-column">
                        <strong class="text-white"><?php echo htmlspecialchars($evento['nombre']); ?></strong>
                        <span class="text-secondary text-white" style="font-size:0.9rem;">
                          <?php echo htmlspecialchars($tipos_evento[$evento['tipo']] ?? ucfirst($evento['tipo'])); ?>
                        </span>
                      </div>
                    </td>

                    <!-- Fecha de evento -->
                    <td class="text-white"><?php echo date('d/m/Y', strtotime($evento['fecha_evento'])); ?></td>

                    <!-- Cliente -->
                    <td>
                      <div class="client-info">
                        <div class="client-avatar">
                          <?php
                            $nombres = explode(' ', $evento['cliente_nombre']);
                            $iniciales = '';
                            foreach ($nombres as $n) {
                              $iniciales .= strtoupper(substr($n, 0, 1));
                            }
                            echo substr($iniciales, 0, 2);
                          ?>
                        </div>
                        <span class="text-white"><?php echo htmlspecialchars($evento['cliente_nombre']); ?></span>
                      </div>
                    </td>

                    <!-- Colaboradores -->
                    <td class="text-white" style="max-width:150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($evento['colaboradores'] ?? 'Sin asignar'); ?>">
                      <?php echo htmlspecialchars($evento['colaboradores'] ?? 'Sin asignar'); ?>
                    </td>

                    <!-- Estado -->
                    <td>
                      <?php
                        $estado = $evento['estado'];
                        $badge_class = match($estado) {
                          'pendiente'    => 'warning',
                          'confirmado'   => 'success',
                          'en_progreso'  => 'info',
                          'completado'   => 'primary',
                          'cancelado'    => 'danger',
                          default        => 'secondary'
                        };
                      ?>
                      <span class="badge bg-<?php echo $badge_class; ?>">
                        <span class="status-indicator status-<?php echo $estado; ?>"></span>
                        <?php echo ucfirst(str_replace('_', ' ', $estado)); ?>
                      </span>
                    </td>

                    <!-- Acciones -->
                    <td>
                      <div class="d-flex flex-wrap gap-2">
                        <!-- Ver detalles -->
                        <a
                          href="detalles_evento.php?id=<?php echo $evento['id']; ?>"
                          class="btn btn-sm btn-secondary"
                          title="Ver Detalles"
                        >
                          <i class="bi bi-eye-fill"></i>
                        </a>

                        <!-- Cambiar a Pendiente si no está pendiente -->
                        <?php if ($estado !== 'pendiente'): ?>
                          <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="id" value="<?php echo $evento['id']; ?>">
                            <input type="hidden" name="status" value="pendiente">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <button type="submit" class="btn btn-sm btn-warning" title="Marcar como Pendiente">
                              <i class="bi bi-hourglass"></i>
                            </button>
                          </form>
                        <?php endif; ?>

                        <!-- Cambiar a Confirmado si no está confirmado -->
                        <?php if ($estado !== 'confirmado'): ?>
                          <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="id" value="<?php echo $evento['id']; ?>">
                            <input type="hidden" name="status" value="confirmado">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <button type="submit" class="btn btn-sm btn-success" title="Marcar como Confirmado">
                              <i class="bi bi-check-circle"></i>
                            </button>
                          </form>
                        <?php endif; ?>

                        <!-- Cambiar a Completado si no está completado -->
                        <?php if ($estado !== 'completado'): ?>
                          <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="id" value="<?php echo $evento['id']; ?>">
                            <input type="hidden" name="status" value="completado">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <button type="submit" class="btn btn-sm btn-primary" title="Marcar como Completado">
                              <i class="bi bi-check2-all"></i>
                            </button>
                          </form>
                        <?php endif; ?>

                        <!-- Cambiar a Cancelado si no está cancelado -->
                        <?php if ($estado !== 'cancelado'): ?>
                          <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="id" value="<?php echo $evento['id']; ?>">
                            <input type="hidden" name="status" value="cancelado">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <button type="submit" class="btn btn-sm btn-danger" title="Marcar como Cancelado">
                              <i class="bi bi-x-circle"></i>
                            </button>
                          </form>
                        <?php endif; ?>

                        <!-- Eliminar evento -->
                        <form method="post" class="d-inline" 
                              onsubmit="return confirm('¿Estás seguro de que deseas eliminar este evento permanentemente?');">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="id" value="<?php echo $evento['id']; ?>">
                          <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                          <button type="submit" class="btn btn-sm btn-secondary" title="Eliminar Evento">
                            <i class="bi bi-trash-fill text-danger"></i>
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          
          <!-- Paginación -->
          <?php if ($total_eventos > $eventos_por_pagina): ?>
            <nav>
              <ul class="pagination justify-content-center">
                <?php if ($pagina_actual > 1): ?>
                  <li class="page-item">
                    <a class="page-link" href="?pagina=<?php echo $pagina_actual - 1; ?>">
                      Anterior
                    </a>
                  </li>
                <?php endif; ?>
                
                <?php
                  $total_paginas = ceil($total_eventos / $eventos_por_pagina);
                  $inicio = max(1, $pagina_actual - 2);
                  $fin = min($total_paginas, $pagina_actual + 2);
                  
                  if ($inicio > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?pagina=1">1</a></li>';
                    if ($inicio > 2) {
                      echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                  }
                  
                  for ($i = $inicio; $i <= $fin; $i++):
                ?>
                  <li class="page-item <?php echo $i == $pagina_actual ? 'active' : ''; ?>">
                    <a class="page-link" href="?pagina=<?php echo $i; ?>">
                      <?php echo $i; ?>
                    </a>
                  </li>
                <?php endfor; ?>
                
                <?php
                  if ($fin < $total_paginas) {
                    if ($fin < $total_paginas - 1) {
                      echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?pagina='.$total_paginas.'">'.$total_paginas.'</a></li>';
                  }
                ?>
                
                <?php if ($pagina_actual < $total_paginas): ?>
                  <li class="page-item">
                    <a class="page-link" href="?pagina=<?php echo $pagina_actual + 1; ?>">
                      Siguiente
                    </a>
                  </li>
                <?php endif; ?>
              </ul>
            </nav>
          <?php endif; ?>
        <?php else: ?>
          <div class="text-center py-5">
            <i class="bi bi-calendar-x" style="font-size: 3rem; opacity: 0.5;"></i>
            <p class="text-secondary mt-3">No hay eventos registrados</p>
            <a href="crear_evento.php" class="btn btn-primary mt-3">
              <i class="bi bi-plus-circle-fill me-1"></i> Crear Primer Evento
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function toggleSidebar() {
      const sidebar = document.getElementById('sidebar');
      sidebar.classList.toggle('open');
    }

    document.addEventListener('click', function(event) {
      const sidebar = document.getElementById('sidebar');
      const menuToggle = document.querySelector('.menu-toggle');
      if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
        sidebar.classList.remove('open');
      }
    });

    // Agregar efecto ripple a botones
    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('.btn').forEach(element => {
        element.addEventListener('click', function(e) {
          // Evitar efecto en botones de formulario
          if (e.target.tagName === 'A' || e.target.closest('a') || 
              e.target.tagName === 'BUTTON' || e.target.closest('button')) {
            return;
          }
          
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
    });
  </script>
</body>
</html>