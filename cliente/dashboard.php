<?php
require_once(__DIR__ . '/../includes/config.php');
require_once(__DIR__ . '/../includes/auth.php');

// Solo clientes pueden acceder
if (!isClient()) {
    header("Location: /login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Obtener información del cliente
try {
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = :id");
    $stmt->bindParam(':id', $user_id);
    $stmt->execute();
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener eventos del cliente
    $stmt = $conn->prepare("SELECT * FROM eventos WHERE cliente_id = :cliente_id ORDER BY fecha_evento DESC");
    $stmt->bindParam(':cliente_id', $user_id);
    $stmt->execute();
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Error al obtener datos: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Cliente - Reminiscencia Photography</title>
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
      text-align: center;
    }

    .client-avatar {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      background: var(--primary-gradient);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1rem auto;
      font-weight: 600;
      font-size: 1.5rem;
      color: white;
    }

    .client-name {
      font-size: 1.2rem;
      font-weight: 600;
      margin-bottom: 0.25rem;
    }

    .client-role {
      color: var(--text-secondary);
      font-size: 0.9rem;
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

    .stats-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-bottom: 40px;
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

    .stat-icon {
      width: 50px;
      height: 50px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      background: var(--primary-gradient);
      margin: 0 auto 1rem auto;
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

    .stat-title {
      font-size: 1.2rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
    }

    .stat-text {
      color: var(--text-secondary);
      font-size: 0.9rem;
      margin-bottom: 1.5rem;
    }

    .stat-link {
      color: var(--text-primary);
      text-decoration: none;
      font-weight: 500;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      transition: all 0.3s ease;
    }

    .stat-link:hover {
      color: var(--text-primary);
      transform: translateX(5px);
    }

    .card {
      background: var(--card-bg);
      backdrop-filter: blur(20px);
      border: 1px solid var(--glass-border);
      border-radius: 20px;
      overflow: hidden;
      margin-bottom: 2rem;
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
    }

    .card-body {
      padding: 2rem;
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

    .badge.bg-danger {
      background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%) !important;
    }

    .badge.bg-info {
      background: var(--info-gradient) !important;
      color: var(--dark-bg) !important;
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

    .btn-warning {
      background: var(--warning-gradient);
      color: var(--dark-bg);
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
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
      margin-bottom: 1.5rem;
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
        padding-bottom: 6rem;
      }

      .stats-grid {
        grid-template-columns: 1fr;
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

    @media (max-width: 1024px) {
      .mobile-nav {
        display: block;
      }
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
      .menu-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
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

    .stat-card {
      animation: fadeInUp 0.6s ease forwards;
    }

    .stat-card:nth-child(1) { animation-delay: 0.1s; }
    .stat-card:nth-child(2) { animation-delay: 0.2s; }
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
      <div class="client-avatar">
        <?php 
          // Obtener iniciales del cliente
          $nombres = explode(' ', $cliente['nombre']);
          $iniciales = '';
          foreach ($nombres as $nombre) {
            $iniciales .= strtoupper(substr($nombre, 0, 1));
          }
          echo substr($iniciales, 0, 2);
        ?>
      </div>
      <div class="client-name"><?php echo htmlspecialchars($cliente['nombre']); ?></div>
      <div class="client-role">Cliente</div>
    </div>
    
    <div class="nav-menu">
      <div class="nav-item">
        <a href="dashboard.php" class="nav-link active">
          <i class="bi bi-grid-fill"></i>
          <span>Dashboard</span>
        </a>
      </div>
      <div class="nav-item">
        <a href="crear_evento.php" class="nav-link">
          <i class="bi bi-plus-circle-fill"></i>
          <span>Crear Evento</span>
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
      <h1>Bienvenid@, <?php echo htmlspecialchars($cliente['nombre']); ?></h1>
      <p>Panel de cliente de Reminiscencia Photography</p>
    </div>
    
    <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
      <div class="alert alert-success mb-4">
        <i class="bi bi-check-circle"></i>
        Evento creado exitosamente!
      </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card" onclick="window.location.href='crear_evento.php'">
        <div class="stat-icon">
          <i class="bi bi-plus-circle"></i>
        </div>
        <div class="stat-title">Crear Nuevo Evento</div>
        <div class="stat-text">¿Tienes un evento especial que deseas capturar?</div>
        <a href="crear_evento.php" class="stat-link">
          Solicitar Evento <i class="bi bi-arrow-right"></i>
        </a>
      </div>
      
      <div class="stat-card success" onclick="window.open('https://wa.me/524491543138', '_blank')">
        <div class="stat-icon">
          <i class="bi bi-whatsapp"></i>
        </div>
        <div class="stat-title">Contacto Rápido</div>
        <div class="stat-text">¿Necesitas ayuda o tienes preguntas?</div>
        <a href="https://wa.me/524491543138" class="stat-link" target="_blank">
          WhatsApp <i class="bi bi-arrow-right"></i>
        </a>
      </div>
    </div>

    <!-- Events Table -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title text-white">
          <i class="bi bi-calendar-event me-2 text-white"></i>
          Mis Eventos
        </h3>
      </div>
      <div class="card-body">
        <?php if (count($eventos) > 0): ?>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th class="text-dark">Nombre</th>
                  <th class="text-dark">Tipo</th>
                  <th class="text-dark">Fecha</th>
                  <th class="text-dark">Lugar</th>
                  <th class="text-dark">Estado</th>
                  <th class="text-dark">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($eventos as $evento): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($evento['nombre']); ?></td>
                    <td><?php echo ucfirst($evento['tipo']); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($evento['fecha_evento'])); ?></td>
                    <td><?php echo htmlspecialchars($evento['lugar']); ?></td>
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
                    <td>
                      <a href="detalles_evento.php?id=<?php echo $evento['id']; ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-eye"></i> Ver
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <i class="bi bi-calendar-x"></i>
            <h4>No tienes eventos registrados</h4>
            <p>Comienza creando tu primer evento para capturar esos momentos especiales</p>
            <a href="crear_evento.php" class="btn btn-primary">
              <i class="bi bi-plus-circle"></i> Crear mi primer evento
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