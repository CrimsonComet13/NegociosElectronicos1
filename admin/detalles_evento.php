<?php
require_once(__DIR__ . '/../includes/config.php');
require_once(__DIR__ . '/../includes/auth.php');

// Definir la función isColaborador si no existe
if (!function_exists('isColaborador')) {
    function isColaborador() {
        return isset($_SESSION['rol']) && $_SESSION['rol'] === 'colaborador';
    }
}

// Verificar permisos
if (!isAdmin() && !isColaborador()) {
    header("Location: /login.php");
    exit();
}

// Verificar ID del evento
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: gestion_eventos.php?error=id_invalido");
    exit();
}

$evento_id = intval($_GET['id']);

// Definir valores por defecto para todas las claves posibles
$default_values = [
    'nombre' => 'Sin nombre',
    'descripcion' => '',
    'fecha_evento' => date('Y-m-d'),
    'hora_inicio' => '08:00',
    'hora_fin' => '18:00',
    'tipo' => 'otros',
    'estado' => 'pendiente',
    'lugar' => 'Ubicación no especificada',
    'fecha_creacion' => date('Y-m-d H:i:s'),
    'fecha_modificacion' => null,
    'cliente_nombre' => 'Cliente no especificado',
    'cliente_email' => '',
    'cliente_telefono' => ''
];

// Estados disponibles
$estados_evento = [
    'pendiente' => 'Pendiente',
    'confirmado' => 'Confirmado',
    'en_progreso' => 'En progreso',
    'completado' => 'Completado',
    'cancelado' => 'Cancelado'
];

// Tipos de evento
$tipos_evento = [
    'bodas' => 'Boda',
    'xv' => 'XV Años',
    'graduaciones' => 'Graduación',
    'corporativos' => 'Evento Corporativo',
    'otro' => 'Otro'
];

// Obtener datos del evento
try {
    $stmt = $conn->prepare("SELECT e.*, 
                           c.nombre as cliente_nombre, 
                           c.email as cliente_email,
                           c.telefono as cliente_telefono
                           FROM eventos e
                           LEFT JOIN usuarios c ON e.cliente_id = c.id
                           WHERE e.id = ?");
    $stmt->execute([$evento_id]);
    $evento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$evento) {
        header("Location: gestion_eventos.php?error=evento_no_encontrado");
        exit();
    }

    // Combinar con los datos obtenidos de la BD
    $evento = array_merge($default_values, $evento);

    // Obtener colaboradores asignados a este evento
    $stmt_colab = $conn->prepare("SELECT u.id, u.nombre 
                                 FROM evento_colaboradores ec
                                 JOIN usuarios u ON ec.colaborador_id = u.id
                                 WHERE ec.evento_id = ?");
    $stmt_colab->execute([$evento_id]);
    $colaboradores_asignados = $stmt_colab->fetchAll(PDO::FETCH_ASSOC);

    // Obtener todos los colaboradores disponibles
    $stmt_colaboradores = $conn->prepare("SELECT id, nombre FROM usuarios WHERE rol = 'colaborador' AND activo = 1");
    $stmt_colaboradores->execute();
    $colaboradores = $stmt_colaboradores->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error al obtener datos del evento: " . $e->getMessage());
}

// Procesar actualización si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_STRING) ?? '';
        $descripcion = filter_input(INPUT_POST, 'descripcion', FILTER_SANITIZE_STRING) ?? '';
        $fecha_evento = $_POST['fecha_evento'] ?? '';
        $hora_inicio = $_POST['hora_inicio'] ?? '';
        $hora_fin = $_POST['hora_fin'] ?? '';
        $tipo_evento = $_POST['tipo_evento'] ?? '';
        $estado = $_POST['estado'] ?? '';
        $lugar = filter_input(INPUT_POST, 'lugar', FILTER_SANITIZE_STRING) ?? '';
        $colaboradores_seleccionados = $_POST['colaboradores'] ?? [];

        // Validaciones básicas
        if (empty($nombre) || empty($fecha_evento) || empty($tipo_evento) || empty($estado)) {
            throw new Exception("Todos los campos obligatorios deben completarse");
        }

        // Actualizar en la base de datos
        $stmt = $conn->prepare("UPDATE eventos SET 
                              nombre = ?,
                              descripcion = ?, 
                              fecha_evento = ?,
                              hora_inicio = ?,
                              hora_fin = ?,
                              tipo = ?,
                              estado = ?,
                              lugar = ?,
                              fecha_modificacion = NOW()
                              WHERE id = ?");
        
        $stmt->execute([
            $nombre,
            $descripcion, 
            $fecha_evento,
            $hora_inicio,
            $hora_fin,
            $tipo_evento,
            $estado,
            $lugar,
            $evento_id
        ]);

        // Actualizar colaboradores asignados
        // 1. Eliminar asignaciones anteriores
        $stmt_delete = $conn->prepare("DELETE FROM evento_colaboradores WHERE evento_id = ?");
        $stmt_delete->execute([$evento_id]);
        
        // 2. Insertar nuevas asignaciones
        if (!empty($colaboradores_seleccionados)) {
            $stmt_insert = $conn->prepare("INSERT INTO evento_colaboradores (evento_id, colaborador_id) VALUES (?, ?)");
            foreach ($colaboradores_seleccionados as $colab_id) {
                $stmt_insert->execute([$evento_id, $colab_id]);
            }
        }

        $_SESSION['mensaje_exito'] = "Evento actualizado correctamente";
        header("Location: detalles_evento.php?id=" . $evento_id);
        exit();

    } catch (Exception $e) {
        $error_actualizacion = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Detalles del Evento - Reminiscencia Photography</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
      --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
      --info-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
      --danger-gradient: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
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
    }

    .page-header {
      margin-bottom: 2rem;
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .page-header h1 {
      font-size: 2.5rem;
      font-weight: 700;
      margin: 0;
      background: var(--primary-gradient);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .back-button {
      background: var(--card-bg);
      backdrop-filter: blur(20px);
      border: 1px solid var(--glass-border);
      border-radius: 12px;
      padding: 0.8rem 1.2rem;
      color: var(--text-primary);
      text-decoration: none;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .back-button:hover {
      color: var(--text-primary);
      transform: translateY(-2px);
      box-shadow: var(--shadow-glow);
    }

    .event-header {
      background: var(--card-bg);
      backdrop-filter: blur(20px);
      border: 1px solid var(--glass-border);
      border-radius: 20px;
      padding: 2rem;
      margin-bottom: 2rem;
      position: relative;
      overflow: hidden;
    }

    .event-header::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: var(--primary-gradient);
    }

    .event-title {
      font-size: 1.8rem;
      font-weight: 700;
      margin-bottom: 1rem;
      background: var(--primary-gradient);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .event-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      margin-bottom: 1rem;
    }

    .event-meta-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      color: var(--text-secondary);
    }

    .badge {
      padding: 0.6rem 1.2rem;
      border-radius: 20px;
      font-weight: 500;
      font-size: 0.9rem;
    }

    .badge-tipo {
      background: var(--info-gradient);
      color: var(--dark-bg);
    }

    .badge-estado {
      background: var(--warning-gradient);
      color: var(--dark-bg);
    }

    .badge-estado.confirmado {
      background: var(--primary-gradient);
      color: white;
    }

    .badge-estado.en_progreso {
      background: var(--info-gradient);
      color: var(--dark-bg);
    }

    .badge-estado.completado {
      background: var(--success-gradient);
      color: white;
    }

    .badge-estado.cancelado {
      background: var(--danger-gradient);
      color: white;
    }

    .card {
      background: var(--card-bg);
      backdrop-filter: blur(20px);
      border: 1px solid var(--glass-border);
      border-radius: 20px;
      overflow: hidden;
      transition: all 0.3s ease;
    }

    .card:hover {
      transform: translateY(-5px);
      box-shadow: var(--shadow-glow);
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
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .card-body {
      padding: 2rem;
    }

    .form-control {
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid var(--glass-border);
      border-radius: 12px;
      padding: 0.8rem 1rem;
      color: var(--text-primary);
      transition: all 0.3s ease;
    }

    .form-control:focus {
      background: rgba(255, 255, 255, 0.08);
      border-color: rgba(102, 126, 234, 0.5);
      box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
      color: var(--text-primary);
    }

    .form-control::placeholder {
      color: rgba(255, 255, 255, 0.5);
    }

    .form-label {
      color: var(--text-primary);
      font-weight: 500;
      margin-bottom: 0.5rem;
    }

    .form-select {
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid var(--glass-border);
      border-radius: 12px;
      padding: 0.8rem 1rem;
      color: var(--text-primary);
      transition: all 0.3s ease;
    }

    .form-select:focus {
      background: rgba(255, 255, 255, 0.08);
      border-color: rgba(102, 126, 234, 0.5);
      box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
      color: var(--text-primary);
    }

    .btn {
      border: none;
      border-radius: 12px;
      padding: 0.8rem 1.5rem;
      font-weight: 500;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }

    .btn-primary {
      background: var(--primary-gradient);
      color: white;
    }

    .btn-secondary {
      background: rgba(255, 255, 255, 0.1);
      color: var(--text-primary);
      border: 1px solid var(--glass-border);
    }

    .btn-danger {
      background: var(--danger-gradient);
      color: white;
    }

    .btn-outline-primary {
      background: transparent;
      border: 1px solid rgba(102, 126, 234, 0.5);
      color: var(--text-primary);
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    }

    .btn-group {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .alert {
      background: rgba(102, 126, 234, 0.1);
      border: 1px solid rgba(102, 126, 234, 0.2);
      border-radius: 12px;
      color: var(--text-primary);
      backdrop-filter: blur(10px);
    }

    .alert-success {
      background: rgba(79, 172, 254, 0.1);
      border-color: rgba(79, 172, 254, 0.2);
    }

    .alert-danger {
      background: rgba(255, 107, 107, 0.1);
      border-color: rgba(255, 107, 107, 0.2);
    }

    .timeline {
      position: relative;
      padding-left: 2rem;
    }

    .timeline::before {
      content: '';
      position: absolute;
      left: 0.5rem;
      top: 0;
      bottom: 0;
      width: 2px;
      background: var(--glass-border);
    }

    .timeline-item {
      position: relative;
      padding-bottom: 1.5rem;
    }

    .timeline-item::before {
      content: '';
      position: absolute;
      left: -2rem;
      top: 0.2rem;
      width: 1rem;
      height: 1rem;
      border-radius: 50%;
      background: var(--primary-gradient);
    }

    .timeline-item h6 {
      font-size: 1rem;
      margin-bottom: 0.25rem;
    }

    .timeline-item small {
      color: var(--text-secondary);
      font-size: 0.8rem;
    }

    .timeline-item p {
      margin-top: 0.5rem;
      color: var(--text-secondary);
      font-size: 0.9rem;
    }

    .team-member {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 0.75rem;
      border-radius: 12px;
      transition: all 0.3s ease;
    }

    .team-member:hover {
      background: rgba(255, 255, 255, 0.05);
    }

    .team-member-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: var(--primary-gradient);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: bold;
    }

    .team-member-name {
      flex: 1;
    }

    /* Menu Toggle Button */
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
      padding: 1rem;
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
      padding: 0.5rem;
      border-radius: 8px;
    }

    .mobile-nav-item.active,
    .mobile-nav-item:hover {
      color: var(--text-primary);
      background: rgba(255, 255, 255, 0.1);
    }

    .mobile-nav-item i {
      font-size: 1.2rem;
      margin-bottom: 0.25rem;
    }

    .mobile-nav-item span {
      font-size: 0.7rem;
    }

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

      .menu-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .mobile-nav {
        display: block;
      }

      .main-content {
        padding-bottom: 6rem;
      }
    }

    @media (max-width: 768px) {
      .main-content {
        padding: 1rem;
      }

      .event-header {
        padding: 1.5rem;
      }

      .event-title {
        font-size: 1.5rem;
      }

      .card-body {
        padding: 1.5rem;
      }
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

    @keyframes slideInLeft {
      from {
        opacity: 0;
        transform: translateX(-30px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    .event-header {
      animation: fadeInUp 0.6s ease forwards;
    }

    .card {
      animation: slideInLeft 0.6s ease forwards;
    }

    .card:nth-child(1) { animation-delay: 0.2s; }
    .card:nth-child(2) { animation-delay: 0.4s; }
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

  <!-- Main Content -->
  <main class="main-content">
    <div class="page-header">
      <a href="gestion_eventos.php" class="back-button">
        <i class="bi bi-arrow-left"></i>
        Volver
      </a>
      <h1>Detalles del Evento</h1>
    </div>

    <?php if (isset($_SESSION['mensaje_exito'])): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>
        <?php echo htmlspecialchars($_SESSION['mensaje_exito']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php unset($_SESSION['mensaje_exito']); ?>
    <?php endif; ?>

    <?php if (isset($error_actualizacion)): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <?php echo htmlspecialchars($error_actualizacion); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <!-- Event Header -->
    <div class="event-header">
      <h2 class="event-title"><?php echo htmlspecialchars($evento['nombre']); ?></h2>
      
      <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <span class="badge badge-tipo">
          <i class="bi bi-tag"></i>
          <?php echo htmlspecialchars($tipos_evento[$evento['tipo']]); ?>
        </span>
        <span class="badge badge-estado <?php echo $evento['estado']; ?>">
          <i class="bi bi-<?php 
            switch($evento['estado']) {
              case 'pendiente': echo 'clock'; break;
              case 'confirmado': echo 'check-circle'; break;
              case 'en_progreso': echo 'arrow-repeat'; break;
              case 'completado': echo 'check-circle-fill'; break;
              case 'cancelado': echo 'x-circle-fill'; break;
              default: echo 'question-circle';
            }
          ?>"></i>
          <?php echo htmlspecialchars($estados_evento[$evento['estado']]); ?>
        </span>
      </div>
      
      <div class="event-meta">
        <div class="event-meta-item">
          <i class="bi bi-calendar-event"></i>
          <span><?php echo date('d/m/Y', strtotime($evento['fecha_evento'])); ?></span>
        </div>
        <div class="event-meta-item">
          <i class="bi bi-clock"></i>
          <span><?php echo substr($evento['hora_inicio'], 0, 5); ?> - <?php echo substr($evento['hora_fin'], 0, 5); ?></span>
        </div>
        <div class="event-meta-item">
          <i class="bi bi-geo-alt"></i>
          <span><?php echo htmlspecialchars($evento['lugar']); ?></span>
        </div>
      </div>
    </div>

    <div class="row">
      <!-- Left Column - Event Details -->
      <div class="col-lg-8">
        <!-- Event Details Card -->
        <div class="card mb-4">
          <div class="card-header">
            <h3 class="card-title text-white">
              <i class="bi bi-pencil-square text-white"></i>
              Editar Información
            </h3>
          </div>
          <div class="card-body">
            <form method="POST">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="nombre" class="form-label">Nombre*</label>
                  <input 
                    type="text" 
                    class="form-control" 
                    id="nombre" 
                    name="nombre" 
                    value="<?php echo htmlspecialchars($evento['nombre']); ?>" 
                    required>
                </div>

                <div class="col-md-6 mb-3">
                  <label for="tipo_evento" class="form-label">Tipo de Evento*</label>
                  <select class="form-select" id="tipo_evento" name="tipo_evento" required>
                    <?php foreach ($tipos_evento as $valor => $texto): ?>
                      <option class= "text-dark"value="<?php echo htmlspecialchars($valor); ?>" 
                        <?php echo ($evento['tipo'] === $valor) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($texto); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              
              <div class="row">
                <div class="col-md-4 mb-3 text-white">
                  <label for="fecha_evento" class="form-label">Fecha*</label>
                  <input type="date" class="form-control" id="fecha_evento" name="fecha_evento"
                         value="<?php echo htmlspecialchars($evento['fecha_evento']); ?>" required>
                </div>
                
                <div class="col-md-4 mb-3">
                  <label for="hora_inicio" class="form-label">Hora Inicio</label>
                  <input type="time" class="form-control" id="hora_inicio" name="hora_inicio"
                         value="<?php echo htmlspecialchars($evento['hora_inicio']); ?>">
                </div>
                
                <div class="col-md-4 mb-3">
                  <label for="hora_fin" class="form-label">Hora Fin</label>
                  <input type="time" class="form-control" id="hora_fin" name="hora_fin"
                         value="<?php echo htmlspecialchars($evento['hora_fin']); ?>">
                </div>
              </div>
              
              <div class="mb-3">
                <label for="lugar" class="form-label">Ubicación</label>
                <input type="text" class="form-control" id="lugar" name="lugar"
                       value="<?php echo htmlspecialchars($evento['lugar']); ?>">
              </div>
              
              <div class="mb-3">
                <label for="descripcion" class="form-label">Descripción</label>
                <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?php 
                  echo htmlspecialchars($evento['descripcion']); 
                ?></textarea>
              </div>
              
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="estado" class="form-label">Estado*</label>
                  <select class="form-select" id="estado" name="estado" required>
                    <?php foreach ($estados_evento as $valor => $texto): ?>
                      <option class="text-dark" value="<?php echo htmlspecialchars($valor); ?>" 
                        <?php echo ($evento['estado'] === $valor) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($texto); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              
              <div class="mb-3">
                <label class="form-label">Equipo de Trabajo</label>
                <select class="form-select" name="colaboradores[]" multiple size="5">
                  <?php foreach ($colaboradores as $colab): ?>
                    <option value="<?php echo htmlspecialchars($colab['id']); ?>"
                      <?php echo in_array($colab['id'], array_column($colaboradores_asignados, 'id')) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($colab['nombre']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <small class=" text-white">Mantén presionado Ctrl para seleccionar múltiples</small>
              </div>
              
              <div class="d-flex flex-column flex-md-row justify-content-between mt-4 gap-2">
                <a href="gestion_eventos.php" class="btn btn-secondary">
                  <i class="bi bi-arrow-left"></i> Volver al listado
                </a>
                <div class="btn-group">
                  <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Guardar Cambios
                  </button>
                  <?php if (isAdmin()): ?>
                  <a href="eliminar_evento.php?id=<?php echo $evento_id; ?>" 
                     class="btn btn-danger"
                     onclick="return confirm('¿Estás seguro de eliminar este evento?')">
                    <i class="bi bi-trash"></i> Eliminar
                  </a>
                  <?php endif; ?>
                </div>
              </div>
            </form>
          </div>
        </div>
        
        <!-- Timeline Card -->
        <div class="card mb-4 text-white">
          <div class="card-header">
            <h3 class="card-title">
              <i class="bi bi-list-task"></i>
              Historial del Evento
            </h3>
          </div>
          <div class="card-body">
            <div class="timeline">
              <div class="timeline-item">
                <h6>Evento creado</h6>
                <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($evento['fecha_creacion'])); ?></small>
                <p>Por el sistema</p>
              </div>
              <?php if ($evento['fecha_modificacion']): ?>
              <div class="timeline-item">
                <h6>Última modificación</h6>
                <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($evento['fecha_modificacion'])); ?></small>
                <p>Cambios realizados</p>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Right Column - Client and Team Info -->
      <div class="col-lg-4">
        <!-- Client Card -->
        <div class="card mb-4">
          <div class="card-header">
            <h3 class="card-title text-white">
              <i class="bi bi-person"></i>
              Información del Cliente
            </h3>
          </div>
          <div class="card-body">
            <div class="team-member text-white">
              <div class="team-member-avatar">
                <?php echo strtoupper(substr($evento['cliente_nombre'], 0, 1)); ?>
              </div>
              <div class="team-member-name">
                <h6><?php echo htmlspecialchars($evento['cliente_nombre']); ?></h6>
                <small class="text-white"><?php echo htmlspecialchars($evento['cliente_email']); ?></small>
              </div>
            </div>
            
            <div class="mt-3">
              <?php if (!empty($evento['cliente_telefono'])): ?>
                <div class="d-flex align-items-center gap-2 mb-2 text-white">
                  <i class="bi bi-telephone text-white"></i>
                  <span><?php echo htmlspecialchars($evento['cliente_telefono']); ?></span>
                </div>
              <?php endif; ?>
              
              <?php if (file_exists(__DIR__ . '/detalles_cliente.php')): ?>
                <a href="detalles_cliente.php?id=<?php echo $evento['cliente_id']; ?>" class="btn btn-outline-primary w-100 mt-2">
                  Ver perfil completo
                </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
        
        <!-- Team Card -->
        <div class="card mb-4">
          <div class="card-header">
            <h3 class="card-title text-white">
              <i class="bi bi-people"></i>
              Equipo Asignado
            </h3>
          </div>
          <div class="card-body">
            <?php if (!empty($colaboradores_asignados)): ?>
              <div class="d-flex flex-column gap-2">
                <?php foreach ($colaboradores_asignados as $colab): ?>
                  <div class="team-member text-white">
                    <div class="team-member-avatar">
                      <?php echo strtoupper(substr($colab['nombre'], 0, 1)); ?>
                    </div>
                    <div class="team-member-name">
                      <h6><?php echo htmlspecialchars($colab['nombre']); ?></h6>
                    </div>
                    <a href="detalles_colaborador.php?id=<?php echo $colab['id']; ?>" 
                       class="btn btn-sm btn-outline-primary">
                      Ver
                    </a>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="text-white text-center py-3">No hay colaboradores asignados a este evento.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
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

    // Add ripple effect to buttons
    document.querySelectorAll('.btn').forEach(button => {
      button.addEventListener('click', function(e) {
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
        
        this.appendChild(ripple);
        setTimeout(() => ripple.remove(), 600);
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