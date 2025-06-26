<?php
require_once(__DIR__ . '/../includes/config.php');
require_once(__DIR__ . '/../includes/auth.php');

// Verificar permisos de administrador
if (!isAdmin()) {
    header("Location: /login.php");
    exit();
}

// Verificar que se haya proporcionado un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: gestion_clientes.php?error=id_invalido");
    exit();
}

$cliente_id = intval($_GET['id']);

// Obtener datos del cliente
try {
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ? AND rol = 'cliente'");
    $stmt->execute([$cliente_id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        header("Location: gestion_clientes.php?error=cliente_no_encontrado");
        exit();
    }

    // Valores por defecto
    $default_values = [
        'nombre' => 'Nombre no especificado',
        'email' => '',
        'telefono' => '',
        'fecha_registro' => date('Y-m-d H:i:s'),
        'activo' => 1
    ];

    // Combinar con datos de la BD
    $cliente = array_merge($default_values, $cliente);

    // Obtener eventos del cliente
    $stmt_eventos = $conn->prepare("SELECT e.id, e.nombre, e.fecha_evento, e.estado, e.tipo
                                   FROM eventos e 
                                   WHERE e.cliente_id = ?
                                   ORDER BY e.fecha_evento DESC");
    $stmt_eventos->execute([$cliente_id]);
    $eventos = $stmt_eventos->fetchAll(PDO::FETCH_ASSOC);

    // Obtener estadísticas del cliente
    $stmt_stats = $conn->prepare("SELECT 
                                  COUNT(*) as total_eventos,
                                  SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as eventos_completados,
                                  SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as eventos_pendientes
                                  FROM eventos WHERE cliente_id = ?");
    $stmt_stats->execute([$cliente_id]);
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error al obtener datos del cliente: " . $e->getMessage());
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_STRING) ?? '';
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?? '';
        $telefono = filter_input(INPUT_POST, 'telefono', FILTER_SANITIZE_STRING) ?? '';
        $activo = isset($_POST['activo']) ? 1 : 0;

        // Validaciones
        if (empty($nombre) || empty($email)) {
            throw new Exception("Nombre y email son campos obligatorios");
        }

        // Actualizar en BD
        $stmt = $conn->prepare("UPDATE usuarios SET 
                              nombre = ?, 
                              email = ?,
                              telefono = ?,
                              activo = ?
                              WHERE id = ?");
        
        $stmt->execute([
            $nombre, 
            $email,
            $telefono,
            $activo,
            $cliente_id
        ]);

        $_SESSION['mensaje_exito'] = "Cliente actualizado correctamente";
        header("Location: detalles_cliente.php?id=" . $cliente_id);
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
  <title>Detalles del Cliente - Reminiscencia Photography</title>
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

    .client-header {
      background: var(--card-bg);
      backdrop-filter: blur(20px);
      border: 1px solid var(--glass-border);
      border-radius: 20px;
      padding: 2rem;
      margin-bottom: 2rem;
      position: relative;
      overflow: hidden;
    }

    .client-header::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: var(--primary-gradient);
    }

    .client-title {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
      gap: 1rem;
    }

    .client-name {
      font-size: 2rem;
      font-weight: 700;
      margin: 0;
    }

    .status-badge {
      padding: 0.5rem 1rem;
      border-radius: 20px;
      font-weight: 500;
      font-size: 0.9rem;
    }

    .status-badge.active {
      background: var(--success-gradient);
      color: white;
    }

    .status-badge.inactive {
      background: var(--danger-gradient);
      color: white;
    }

    .contact-info {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1rem;
    }

    .contact-item {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      padding: 0.5rem 0;
      color: var(--text-secondary);
    }

    .contact-item i {
      font-size: 1.2rem;
      color: var(--text-primary);
      width: 20px;
    }

    .contact-item a {
      color: var(--text-secondary);
      text-decoration: none;
      transition: color 0.3s ease;
    }

    .contact-item a:hover {
      color: var(--text-primary);
    }

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
      text-align: center;
      position: relative;
      overflow: hidden;
      transition: all 0.3s ease;
    }

    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 3px;
      background: var(--primary-gradient);
    }

    .stat-card.success::before {
      background: var(--success-gradient);
    }

    .stat-card.warning::before {
      background: var(--warning-gradient);
    }

    .stat-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--shadow-glow);
    }

    .stat-value {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
    }

    .stat-label {
      color: var(--text-secondary);
      font-size: 0.9rem;
    }

    .content-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 2rem;
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
      transform: translateY(-2px);
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

    .btn-outline-primary {
      background: transparent;
      border: 1px solid rgba(102, 126, 234, 0.5);
      color: var(--text-primary);
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    }

    .table {
      color: var(--text-primary);
      background: transparent;
    }

    .table th {
      border-bottom: 2px solid var(--glass-border);
      color: var(--text-secondary);
      font-weight: 500;
      padding: 1rem 0.75rem;
      background: rgba(255, 255, 255, 0.02);
    }

    .table td {
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
      padding: 1rem 0.75rem;
      vertical-align: middle;
    }

    .table tbody tr:hover {
      background: rgba(255, 255, 255, 0.02);
    }

    .badge {
      padding: 0.5rem 1rem;
      border-radius: 20px;
      font-weight: 500;
      font-size: 0.8rem;
    }

    .badge.bg-warning {
      background: var(--warning-gradient) !important;
      color: var(--dark-bg) !important;
    }

    .badge.bg-success {
      background: var(--success-gradient) !important;
    }

    .badge.bg-primary {
      background: var(--primary-gradient) !important;
    }

    .badge.bg-info {
      background: var(--info-gradient) !important;
      color: var(--dark-bg) !important;
    }

    .badge.bg-danger {
      background: var(--danger-gradient) !important;
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

    .alert-info {
      background: rgba(168, 237, 234, 0.1);
      border-color: rgba(168, 237, 234, 0.2);
      color: var(--text-primary);
    }

    .form-check-input {
      background: rgba(255, 255, 255, 0.1);
      border: 1px solid var(--glass-border);
    }

    .form-check-input:checked {
      background: var(--success-gradient);
      border-color: transparent;
    }

    .form-check-label {
      color: var(--text-primary);
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

      .content-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
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

      .stats-grid {
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
      }

      .page-header h1 {
        font-size: 1.8rem;
      }

      .client-header {
        padding: 1.5rem;
      }

      .client-name {
        font-size: 1.5rem;
      }

      .contact-info {
        grid-template-columns: 1fr;
        gap: 0.5rem;
      }

      .client-title {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
      }
    }

    @media (max-width: 480px) {
      .stats-grid {
        grid-template-columns: 1fr;
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

    .client-header {
      animation: fadeInUp 0.6s ease forwards;
    }

    .stat-card {
      animation: fadeInUp 0.6s ease forwards;
    }

    .stat-card:nth-child(1) { animation-delay: 0.1s; }
    .stat-card:nth-child(2) { animation-delay: 0.2s; }
    .stat-card:nth-child(3) { animation-delay: 0.3s; }

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
        <a href="gestion_clientes.php" class="nav-link active">
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
      <a href="gestion_clientes.php" class="back-button">
        <i class="bi bi-arrow-left"></i>
        Volver
      </a>
      <h1>Detalles del Cliente</h1>
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

    <!-- Client Header -->
    <div class="client-header">
      <div class="client-title">
        <h2 class="client-name"><?php echo htmlspecialchars($cliente['nombre']); ?></h2>
        <span class="status-badge <?php echo $cliente['activo'] ? 'active' : 'inactive'; ?>">
          <i class="bi bi-<?php echo $cliente['activo'] ? 'check-circle' : 'x-circle'; ?>"></i>
          <?php echo $cliente['activo'] ? 'Activo' : 'Inactivo'; ?>
        </span>
      </div>
      
      <div class="contact-info">
        <div class="contact-item">
          <i class="bi bi-envelope"></i>
          <a href="mailto:<?php echo htmlspecialchars($cliente['email']); ?>">
            <?php echo htmlspecialchars($cliente['email']); ?>
          </a>
        </div>
        <?php if (!empty($cliente['telefono'])): ?>
        <div class="contact-item">
          <i class="bi bi-telephone"></i>
          <span><?php echo htmlspecialchars($cliente['telefono']); ?></span>
        </div>
        <?php endif; ?>
        <div class="contact-item">
          <i class="bi bi-calendar"></i>
          <span>Registrado el <?php echo date('d/m/Y', strtotime($cliente['fecha_registro'])); ?></span>
        </div>
      </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-value"><?php echo $stats['total_eventos']; ?></div>
        <div class="stat-label">Total de Eventos</div>
      </div>
      <div class="stat-card success">
        <div class="stat-value"><?php echo $stats['eventos_completados']; ?></div>
        <div class="stat-label">Eventos Completados</div>
      </div>
      <div class="stat-card warning">
        <div class="stat-value"><?php echo $stats['eventos_pendientes']; ?></div>
        <div class="stat-label">Eventos Pendientes</div>
      </div>
    </div>

    <!-- Content Grid -->
    <div class="content-grid">
      <!-- Client Information Form -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title text-white">
            <i class="bi bi-pencil-square text-white"></i>
            Editar Información
          </h3>
        </div>
        <div class="card-body">
          <form method="POST">
            <div class="mb-3">
              <label for="nombre" class="form-label">Nombre Completo</label>
              <input type="text" class="form-control" id="nombre" name="nombre" 
                     value="<?php echo htmlspecialchars($cliente['nombre']); ?>" required>
            </div>
            
            <div class="mb-3">
              <label for="email" class="form-label">Correo Electrónico</label>
              <input type="email" class="form-control" id="email" name="email" 
                     value="<?php echo htmlspecialchars($cliente['email']); ?>" required>
            </div>
            
            <div class="mb-3">
              <label for="telefono" class="form-label">Teléfono</label>
              <input type="tel" class="form-control" id="telefono" name="telefono" 
                     value="<?php echo htmlspecialchars($cliente['telefono']); ?>">
            </div>
       
            <button type="submit" class="btn btn-primary w-100">
              <i class="bi bi-save"></i>
              Guardar Cambios
            </button>
          </form>
        </div>
      </div>
      
      <!-- Client Events -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title text-white">
            <i class="bi bi-calendar-event text-white"></i>
            Eventos del Cliente
          </h3>
        </div>
        <div class="card-body">
          <?php if (count($eventos) > 0): ?>
            <div class="table-responsive">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>Evento</th>
                    <th>Tipo</th>
                    <th>Fecha</th>
                    <th>Estado</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($eventos as $evento): ?>
                    <tr onclick="window.location.href='detalles_evento.php?id=<?php echo $evento['id']; ?>'" style="cursor: pointer;">
                      <td><?php echo htmlspecialchars($evento['nombre']); ?></td>
                      <td><?php echo htmlspecialchars($evento['tipo']); ?></td>
                      <td><?php echo date('d/m/Y', strtotime($evento['fecha_evento'])); ?></td>
                      <td>
                        <span class="badge bg-<?php 
                          switch($evento['estado']) {
                            case 'pendiente': echo 'warning'; break;
                            case 'confirmado': echo 'success'; break;
                            case 'cancelado': echo 'danger'; break;
                            case 'completado': echo 'info'; break;
                            default: echo 'secondary';
                          }
                        ?>">
                          <?php echo ucfirst($evento['estado']); ?>
                        </span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="text-center py-4">
              <i class="bi bi-calendar-x" style="font-size: 3rem; opacity: 0.5;"></i>
              <p class="mt-3" style="color: var(--text-secondary);">Este cliente no tiene eventos registrados</p>
            </div>
          <?php endif; ?>
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