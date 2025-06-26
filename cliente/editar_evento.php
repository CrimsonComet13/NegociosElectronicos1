<?php
require_once(__DIR__ . '/../includes/config.php');
require_once(__DIR__ . '/../includes/auth.php');

// Solo clientes pueden acceder
if (!isClient()) {
    header("Location: /login_cliente.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$evento_id = $_GET['id'] ?? 0;
$error = '';
$success = '';
$errors = [];

// Generar token CSRF solo si vamos a mostrar el formulario
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Token CSRF inválido.';
    } else {
        $nombre = trim($_POST['nombre'] ?? '');
        $fecha = trim($_POST['fecha'] ?? '');
        $hora_inicio = trim($_POST['hora_inicio'] ?? '');
        $hora_fin = trim($_POST['hora_fin'] ?? '');
        $lugar = trim($_POST['lugar'] ?? '');
        $personas_estimadas = trim($_POST['personas_estimadas'] ?? 0);
        $descripcion = trim($_POST['descripcion'] ?? '');
        
        // Validación de campos
        // Nombre: máximo 255 caracteres, solo caracteres permitidos
        if (empty($nombre)) {
            $errors[] = 'El nombre del evento es obligatorio.';
        } elseif (strlen($nombre) > 255) {
            $errors[] = 'El nombre no puede exceder los 255 caracteres.';
        } elseif (!preg_match('/^[\p{L}\p{N}\s\-\'",.:;()áéíóúÁÉÍÓÚñÑ!?¿¡]{1,255}$/u', $nombre)) {
            $errors[] = 'El nombre contiene caracteres no permitidos.';
        }
        
        // Lugar: máximo 255 caracteres
        if (strlen($lugar) > 255) {
            $errors[] = 'El lugar no puede exceder los 255 caracteres.';
        }
        
        // Descripción: máximo 5000 caracteres
        if (strlen($descripcion) > 5000) {
            $errors[] = 'La descripción no puede exceder los 5000 caracteres.';
        }
        
        // Fecha: formato válido y fecha futura
        $fecha_actual = new DateTime();
        $fecha_actual->setTime(0, 0, 0); // Solo fecha sin hora
        
        if (!DateTime::createFromFormat('Y-m-d', $fecha)) {
            $errors[] = 'Formato de fecha inválido (YYYY-MM-DD).';
        } else {
            $fecha_evento = DateTime::createFromFormat('Y-m-d', $fecha);
            $fecha_evento->setTime(0, 0, 0);
            
            if ($fecha_evento < $fecha_actual) {
                $errors[] = 'La fecha debe ser hoy o en el futuro.';
            }
        }
        
        // Horas: formato válido y hora_fin posterior a hora_inicio
        $formato_hora = '/^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/';
        
        if (!preg_match($formato_hora, $hora_inicio)) {
            $errors[] = 'Formato de hora inicio inválido (HH:MM).';
        }
        
        if (!preg_match($formato_hora, $hora_fin)) {
            $errors[] = 'Formato de hora fin inválido (HH:MM).';
        }
        
        // Validar que hora_fin sea posterior a hora_inicio
        if (empty($errors) {
            $hora_inicio_obj = DateTime::createFromFormat('H:i', $hora_inicio);
            $hora_fin_obj = DateTime::createFromFormat('H:i', $hora_fin);
            
            if ($hora_inicio_obj >= $hora_fin_obj) {
                $errors[] = 'La hora de fin debe ser posterior a la hora de inicio.';
            }
        }
        
        // Personas estimadas: entero positivo entre 1 y 10000
        $personas_estimadas = filter_var($personas_estimadas, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 1,
                'max_range' => 10000
            ]
        ]);
        
        if ($personas_estimadas === false) {
            $errors[] = 'El número de personas debe ser un entero entre 1 y 10000.';
        }
        
        // Si no hay errores, procesar la actualización
        if (empty($errors)) {
            try {
                // Verificar que el evento pertenece al cliente antes de editar
                $stmt = $conn->prepare("UPDATE eventos SET 
                                      nombre = ?, 
                                      fecha_evento = ?, 
                                      hora_inicio = ?,
                                      hora_fin = ?,
                                      lugar = ?,
                                      personas_estimadas = ?,
                                      descripcion = ?
                                      WHERE id = ? AND cliente_id = ?");
                
                $stmt->execute([
                    $nombre, 
                    $fecha, 
                    $hora_inicio,
                    $hora_fin,
                    $lugar,
                    $personas_estimadas,
                    $descripcion,
                    $evento_id,
                    $user_id
                ]);
                
                if ($stmt->rowCount() === 1) {
                    $success = 'Evento actualizado correctamente';
                } else {
                    $error = 'No se pudo actualizar el evento o no tienes permiso';
                }
            } catch(PDOException $e) {
                $error = 'Error al actualizar: ' . $e->getMessage();
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}

// Obtener datos del evento (solo si pertenece al cliente)
try {
    $stmt = $conn->prepare("SELECT * FROM eventos WHERE id = ? AND cliente_id = ?");
    $stmt->execute([$evento_id, $user_id]);
    $evento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$evento) {
        $error = 'Evento no encontrado o no tienes permiso para editarlo';
    }
} catch(PDOException $e) {
    $error = 'Error al obtener detalles del evento: ' . $e->getMessage();
}

// Regenerar token si vamos a mostrar el formulario
if (isset($evento) && empty($success)) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Editar Evento - Cliente</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <style>
    .card-stat {
      transition: transform 0.3s;
      margin-bottom: 15px;
      height: 100%;
    }
    .card-stat:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    .sidebar {
      min-height: 100vh;
      background: #343a40;
    }
    .sidebar .nav-link {
      color: rgba(255,255,255,0.8);
      margin-bottom: 5px;
    }
    .sidebar .nav-link:hover, .sidebar .nav-link.active {
      color: white;
      background: rgba(255,255,255,0.1);
    }
    .sidebar .nav-link i {
      margin-right: 10px;
    }
    /* Estilos para móviles */
    @media (max-width: 768px) {
      .sidebar {
        min-height: auto;
        width: 100%;
        position: fixed;
        bottom: 0;
        z-index: 1000;
      }
      .sidebar .nav {
        flex-direction: row;
        overflow-x: auto;
        white-space: nowrap;
      }
      .sidebar .nav-link {
        padding: 0.5rem;
        font-size: 0.8rem;
      }
      .sidebar .nav-link i {
        margin-right: 0;
        display: block;
        text-align: center;
        font-size: 1.2rem;
      }
      .sidebar .nav-item {
        display: inline-block;
      }
      main {
        margin-bottom: 60px;
      }
      .card-stat {
        margin-bottom: 15px;
      }
      .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
      }
    }
    /* Estilos para tablets */
    @media (min-width: 769px) and (max-width: 992px) {
      .sidebar {
        width: 70px;
        overflow: hidden;
      }
      .sidebar .nav-link span {
        display: none;
      }
      .sidebar .nav-link i {
        margin-right: 0;
        font-size: 1.2rem;
        display: block;
        text-align: center;
      }
      .sidebar .nav-item {
        text-align: center;
      }
      main {
        margin-left: 70px;
      }
    }
    .form-section {
      margin-bottom: 2rem;
    }
    .time-inputs {
      display: flex;
      gap: 1rem;
    }
    .time-inputs .form-group {
      flex: 1;
    }
  </style>
</head>
<body>
  <div class="container-fluid">
    <div class="row">
      <!-- Sidebar - Se adapta a móvil y tablet -->
      <div class="col-lg-2 d-none d-lg-block sidebar bg-dark">
        <div class="position-sticky pt-3">
          <div class="text-center mb-4">
            
            <h5 class="text-white mt-2"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h5>
            <p class="text-muted">Cliente</p>
          </div>
          
          <ul class="nav flex-column">
            <li class="nav-item">
              <a class="nav-link" href="dashboard.php">
                <i class="bi bi-speedometer2"></i> <span>Dashboard</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="detalles_evento.php?id=<?php echo $evento_id; ?>">
                <i class="bi bi-info-circle"></i> <span>Detalles Evento</span>
              </a>
            </li>
            <li class="nav-item active">
              <a class="nav-link" href="#">
                <i class="bi bi-pencil-square"></i> <span>Editar Evento</span>
              </a>
            </li>
            <li class="nav-item mt-3">
              <a class="nav-link text-danger" href="/logout.php">
                <i class="bi bi-box-arrow-right"></i> <span>Cerrar Sesión</span>
              </a>
            </li>
          </ul>
        </div>
      </div>
      
      <!-- Mobile Bottom Nav -->
      <div class="d-lg-none fixed-bottom bg-dark sidebar">
        <ul class="nav justify-content-around">
          <li class="nav-item">
            <a class="nav-link" href="dashboard.php">
              <i class="bi bi-speedometer2"></i>
              <small>Dashboard</small>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="detalles_evento.php?id=<?php echo $evento_id; ?>">
              <i class="bi bi-info-circle"></i>
              <small>Detalles</small>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link active" href="#">
              <i class="bi bi-pencil-square"></i>
              <small>Editar</small>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link text-danger" href="/logout.php">
              <i class="bi bi-box-arrow-right"></i>
              <small>Salir</small>
            </a>
          </li>
        </ul>
      </div>
      
      <!-- Main content -->
      <main class="col-lg-10 ms-sm-auto px-md-4 py-4">
        <?php if ($error): ?>
          <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php elseif ($success): ?>
          <div class="alert alert-success d-flex justify-content-between align-items-center">
            <div>
              <i class="bi bi-check-circle me-2"></i> <?php echo $success; ?>
            </div>
            <a href="dashboard.php" class="btn btn-sm btn-success">
              <i class="bi bi-speedometer2 me-1"></i> Volver al Dashboard
            </a>
          </div>
        <?php elseif ($evento): ?>
          <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2">Editar Evento: <?php echo htmlspecialchars($evento['nombre']); ?></h1>
          </div>
          
          <div class="card shadow">
            <div class="card-header bg-primary text-white">
              <h5 class="mb-0"><i class="bi bi-calendar-event me-2"></i> Información del Evento</h5>
            </div>
            <div class="card-body">
              <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="form-section">
                  <h5 class="mb-3">Datos Básicos</h5>
                  <div class="mb-3">
                    <label for="nombre" class="form-label">Nombre del Evento <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nombre" name="nombre" 
                           value="<?php echo htmlspecialchars($evento['nombre']); ?>" required
                           maxlength="255">
                    <small class="form-text text-muted">Máximo 255 caracteres. Solo letras, números y signos básicos</small>
                  </div>
                  
                  <div class="row">
                    <div class="col-md-6 mb-3">
                      <label for="fecha" class="form-label">Fecha del Evento <span class="text-danger">*</span></label>
                      <input type="date" class="form-control" id="fecha" name="fecha" 
                             value="<?php echo htmlspecialchars($evento['fecha_evento']); ?>" required>
                      <small class="form-text text-muted">Formato: AAAA-MM-DD. Debe ser hoy o en el futuro</small>
                    </div>
                    <div class="col-md-6 mb-3">
                      <label for="lugar" class="form-label">Lugar</label>
                      <input type="text" class="form-control" id="lugar" name="lugar" 
                             value="<?php echo htmlspecialchars($evento['lugar']); ?>"
                             maxlength="255">
                      <small class="form-text text-muted">Máximo 255 caracteres</small>
                    </div>
                  </div>
                  
                  <div class="row">
                    <div class="col-md-6 mb-3">
                      <label class="form-label">Horario <span class="text-danger">*</span></label>
                      <div class="time-inputs">
                        <div class="form-group">
                          <label for="hora_inicio" class="form-label">Inicio</label>
                          <input type="time" class="form-control" id="hora_inicio" name="hora_inicio" 
                                 value="<?php echo htmlspecialchars($evento['hora_inicio']); ?>" required>
                        </div>
                        <div class="form-group">
                          <label for="hora_fin" class="form-label">Fin</label>
                          <input type="time" class="form-control" id="hora_fin" name="hora_fin" 
                                 value="<?php echo htmlspecialchars($evento['hora_fin']); ?>" required>
                        </div>
                      </div>
                      <small class="form-text text-muted">Hora fin debe ser posterior a hora inicio</small>
                    </div>
                    <div class="col-md-6 mb-3">
                      <label for="personas_estimadas" class="form-label">Personas Estimadas</label>
                      <input type="number" class="form-control" id="personas_estimadas" name="personas_estimadas" 
                             value="<?php echo htmlspecialchars($evento['personas_estimadas']); ?>"
                             min="1" max="10000">
                      <small class="form-text text-muted">Entre 1 y 10000 personas</small>
                    </div>
                  </div>
                </div>
                
                <div class="form-section">
                  <h5 class="mb-3">Detalles Adicionales</h5>
                  <div class="mb-3">
                    <label for="descripcion" class="form-label">Descripción / Notas</label>
                    <textarea class="form-control" id="descripcion" name="descripcion" rows="5"
                              maxlength="5000"><?php 
                      echo htmlspecialchars($evento['descripcion']); 
                    ?></textarea>
                    <small class="form-text text-muted">Máximo 5000 caracteres</small>
                  </div>
                </div>
                
                <div class="d-flex justify-content-between mt-4">
                  <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Guardar Cambios
                  </button>
                  <a href="detalles_evento.php?id=<?php echo $evento_id; ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle me-1"></i> Cancelar
                  </a>
                </div>
              </form>
            </div>
          </div>
        <?php endif; ?>
      </main>
    </div>
  </div>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Script para manejar el menú en móviles
    document.addEventListener('DOMContentLoaded', function() {
      // Manejar el menú activo en móviles
      const currentPage = window.location.pathname.split('/').pop();
      const mobileLinks = document.querySelectorAll('.sidebar.mobile-nav .nav-link');
      
      mobileLinks.forEach(link => {
        if (link.getAttribute('href') === currentPage) {
          link.classList.add('active');
        }
      });
      
      // Validación de fechas
      const fechaInput = document.getElementById('fecha');
      if (fechaInput) {
        const today = new Date().toISOString().split('T')[0];
        fechaInput.min = today;
      }
    });
  </script>
</body>
</html>