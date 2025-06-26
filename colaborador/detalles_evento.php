<?php
require_once(__DIR__ . '/../includes/config.php');
require_once(__DIR__ . '/../includes/auth.php');

if (!function_exists('isColaborador')) {
    function isColaborador() {
        return isset($_SESSION['rol']) && $_SESSION['rol'] === 'colaborador';
    }
}

if (!isAdmin() && !isCollaborator()) {
    header("Location: /login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: gestion_eventos.php?error=id_invalido");
    exit();
}

$evento_id = intval($_GET['id']);

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

$estados_evento = [
    'pendiente' => 'Pendiente',
    'confirmado' => 'Confirmado',
    'en_progreso' => 'En progreso',
    'completado' => 'Completado',
    'cancelado' => 'Cancelado'
];

$tipos_evento = [
    'bodas' => 'Boda',
    'xv' => 'XV Años',
    'graduaciones' => 'Graduación',
    'corporativos' => 'Evento Corporativo',
    'otro' => 'Otro'
];

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

    $evento = array_merge($default_values, $evento);

    $stmt_colab = $conn->prepare("SELECT u.id, u.nombre 
                                 FROM evento_colaboradores ec
                                 JOIN usuarios u ON ec.colaborador_id = u.id
                                 WHERE ec.evento_id = ?");
    $stmt_colab->execute([$evento_id]);
    $colaboradores_asignados = $stmt_colab->fetchAll(PDO::FETCH_ASSOC);

    $stmt_colaboradores = $conn->prepare("SELECT id, nombre FROM usuarios WHERE rol = 'colaborador' AND activo = 1");
    $stmt_colaboradores->execute();
    $colaboradores = $stmt_colaboradores->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error al obtener datos del evento: " . $e->getMessage());
}

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

        if (empty($nombre) || empty($fecha_evento) || empty($tipo_evento) || empty($estado)) {
            throw new Exception("Todos los campos obligatorios deben completarse");
        }

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

        $stmt_delete = $conn->prepare("DELETE FROM evento_colaboradores WHERE evento_id = ?");
        $stmt_delete->execute([$evento_id]);
        
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

    .card {
      background: var(--card-bg);
      backdrop-filter: blur(20px);
      border: 1px solid var(--glass-border);
      border-radius: 20px;
      overflow: hidden;
      margin-bottom: 2rem;
      position: relative;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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
      color: var(--text-primary);
      display: flex;
      align-items: center;
    }

    .card-title i {
      margin-right: 10px;
    }

    .card-body {
      padding: 2rem;
    }

    .info-card::before {
      content: '';
      position: absolute;
      left: 0;
      top: 0;
      bottom: 0;
      width: 4px;
      background: var(--primary-gradient);
    }

    .client-card::before {
      background: var(--success-gradient);
    }

    .event-header {
      background: rgba(255, 255, 255, 0.05);
      border-radius: 16px;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
    }

    .badge-estado {
      font-size: 0.9rem;
      padding: 0.5rem 1rem;
      border-radius: 20px;
      font-weight: 500;
      min-width: 100px;
      text-align: center;
    }

    .badge-pendiente {
      background: var(--warning-gradient);
      color: var(--dark-bg);
    }

    .badge-confirmado {
      background: var(--success-gradient);
    }

    .badge-en_progreso {
      background: var(--info-gradient);
      color: var(--dark-bg);
    }

    .badge-completado {
      background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
    }

    .badge-cancelado {
      background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
    }

    .badge-tipo {
      background: rgba(255, 255, 255, 0.1);
      color: var(--text-primary);
      padding: 0.5rem 1rem;
      border-radius: 20px;
      font-weight: 500;
    }

    .form-control, .form-select, .form-control:focus, .form-select:focus {
      background-color: rgba(255, 255, 255, 0.1);
      border: 1px solid var(--glass-border);
      color: var(--text-primary);
    }

    .form-control::placeholder {
      color: rgba(255, 255, 255, 0.5);
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

    .btn-success {
      background: var(--success-gradient);
      color: white;
    }

    .btn-danger {
      background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
      color: white;
    }

    .btn-outline-primary {
      background: transparent;
      border: 1px solid rgba(102, 126, 234, 0.5);
      color: rgba(102, 126, 234, 0.8);
    }

    .btn-outline-primary:hover {
      background: rgba(102, 126, 234, 0.1);
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    }

    .team-member {
      display: flex;
      align-items: center;
      padding: 0.8rem;
      border-radius: 12px;
      background: rgba(255, 255, 255, 0.05);
      margin-bottom: 0.75rem;
      transition: all 0.3s ease;
    }

    .team-member:hover {
      background: rgba(255, 255, 255, 0.1);
      transform: translateX(5px);
    }

    .client-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: var(--primary-gradient);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      color: white;
      margin-right: 1rem;
      flex-shrink: 0;
    }

    .timeline-item {
      padding: 1.5rem;
      border-left: 2px solid rgba(102, 126, 234, 0.5);
      margin-bottom: 1rem;
      position: relative;
    }

    .timeline-item::before {
      content: '';
      position: absolute;
      left: -8px;
      top: 22px;
      width: 14px;
      height: 14px;
      border-radius: 50%;
      background: var(--primary-gradient);
    }

    .content-grid {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 2rem;
    }

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

    @media (max-width: 1024px) {
      .sidebar {
        transform: translateX(-100%);
      }

      .sidebar.open {
        transform: translateX(0);
      }

      .main-content {
        margin-left: 0;
        padding-bottom: 6rem;
      }

      .content-grid {
        grid-template-columns: 1fr;
      }

      .mobile-nav {
        display: block;
      }

      .menu-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
      }
    }

    @media (max-width: 768px) {
      .main-content {
        padding: 1rem;
      }

      .header h1 {
        font-size: 2rem;
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
      <h1>Detalles del Evento</h1>
      <p>Administra los detalles y configuración del evento</p>
    </div>

    <?php if (isset($error_actualizacion)): ?>
      <div class="alert alert-danger mb-4">
        <i class="bi bi-exclamation-triangle"></i>
        <?php echo htmlspecialchars($error_actualizacion); ?>
      </div>
    <?php endif; ?>

    <!-- Encabezado del evento -->
    <div class="event-header">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars($evento['nombre']); ?></h1>
        <span class="badge-estado badge-<?php echo $evento['estado']; ?>">
          <?php echo htmlspecialchars($estados_evento[$evento['estado']]); ?>
        </span>
      </div>
      
      <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <span class="badge-tipo">
          <i class="bi bi-tag"></i> <?php echo htmlspecialchars($tipos_evento[$evento['tipo']]); ?>
        </span>
        <span><i class="bi bi-calendar-event"></i> <?php echo date('d/m/Y', strtotime($evento['fecha_evento'])); ?></span>
        <span><i class="bi bi-clock"></i> <?php echo substr($evento['hora_inicio'], 0, 5); ?> - <?php echo substr($evento['hora_fin'], 0, 5); ?></span>
        <span><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($evento['lugar']); ?></span>
      </div>
    </div>

    <div class="content-grid">
      <!-- Columna izquierda - Información del evento -->
      <div>
        <div class="card info-card">
          <div class="card-header">
            <h5 class="card-title"><i class="bi bi-info-circle"></i> Detalles del Evento</h5>
          </div>
          <div class="card-body text-white">
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
                      <option value="<?php echo htmlspecialchars($valor); ?>" 
                        <?php echo ($evento['tipo'] === $valor) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($texto); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              
              <div class="row">
                <div class="col-md-4 mb-3">
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
                      <option value="<?php echo htmlspecialchars($valor); ?>" 
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
                <small class="text-white">Mantén presionado Ctrl para seleccionar múltiples</small>
              </div>
              
              <div class="d-flex flex-column flex-md-row justify-content-between mt-4 gap-2">
                <a href="gestion_eventos.php" class="btn btn-outline-primary">
                  <i class="bi bi-arrow-left"></i> Volver al listado
                </a>
                <div class="d-flex gap-2">
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
        
        <!-- Sección de timeline -->
        <div class="card">
          <div class="card-header">
            <h5 class="card-title"><i class="bi bi-list-task"></i> Historial del Evento</h5>
          </div>
          <div class="card-body">
            <div class="timeline">
              <div class="timeline-item">
                <h6>Evento creado</h6>
                <small class="text-white"><?php echo date('d/m/Y H:i', strtotime($evento['fecha_creacion'])); ?></small>
                <p>Por el sistema</p>
              </div>
              <?php if ($evento['fecha_modificacion']): ?>
              <div class="timeline-item">
                <h6>Última modificación</h6>
                <small class="text-white"><?php echo date('d/m/Y H:i', strtotime($evento['fecha_modificacion'])); ?></small>
                <p>Cambios realizados</p>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Columna derecha - Información del cliente y colaborador -->
      <div>
        <!-- Tarjeta de información del cliente -->
        <div class="card client-card">
          <div class="card-header">
            <h5 class="card-title"><i class="bi bi-person"></i> Información del Cliente</h5>
          </div>
          <div class="card-body text-white">
            <div class="d-flex align-items-center mb-3">
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
              <h6><?php echo htmlspecialchars($evento['cliente_nombre']); ?></h6>
            </div>
            <ul class="list-unstyled">
              <li class="mb-2"><i class="bi bi-envelope me-2"></i> <?php echo htmlspecialchars($evento['cliente_email']); ?></li>
              <?php if (!empty($evento['cliente_telefono'])): ?>
                <li><i class="bi bi-telephone me-2"></i> <?php echo htmlspecialchars($evento['cliente_telefono']); ?></li>
              <?php endif; ?>
            </ul>
            <a href="detalles_cliente.php?id=<?php echo $evento['cliente_id']; ?>" class="btn btn-outline-primary mt-2">
              Ver perfil completo
            </a>
          </div>
        </div>
        
        <!-- Tarjeta de equipo asignado -->
        <div class="card">
          <div class="card-header">
            <h5 class="card-title"><i class="bi bi-people"></i> Equipo Asignado</h5>
          </div>
          <div class="card-body text-white">
            <?php if (!empty($colaboradores_asignados)): ?>
              <div>
                <?php foreach ($colaboradores_asignados as $colab): ?>
                  <div class="team-member">
                    <i class="bi bi-person-circle me-3"></i>
                    <div class="flex-grow-1"><?php echo htmlspecialchars($colab['nombre']); ?></div>
                    <a href="detalles_colaborador.php?id=<?php echo $colab['id']; ?>" 
                       class="btn btn-sm btn-outline-primary">
                      Ver
                    </a>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="text-muted">No hay colaboradores asignados a este evento.</p>
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

    document.addEventListener('click', function(event) {
      const sidebar = document.getElementById('sidebar');
      const menuToggle = document.querySelector('.menu-toggle');
      
      if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
        sidebar.classList.remove('open');
      }
    });

    document.addEventListener('DOMContentLoaded', function() {
      // Resaltar ítem activo en navbar móvil
      const currentPage = window.location.pathname.split('/').pop();
      const mobileLinks = document.querySelectorAll('.mobile-nav-item');
      
      mobileLinks.forEach(link => {
        if (link.getAttribute('href') === currentPage) {
          link.classList.add('active');
        }
      });
    });
  </script>
</body>
</html>