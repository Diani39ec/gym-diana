<?php
/*
 * AURUM FITNESS CLUB - Sistema Profesional para Gimnasios
 * Creado por: Diana Trujillo © 2026
 * Versión: 2.0 Profesional + Seguridad Avanzada
 */

// ============ CONFIGURACIÓN BASE ============
define('APP_NAME', 'AURUM FITNESS CLUB');
define('APP_CREATOR', 'Diana Trujillo');
define('APP_VERSION', '2.1.0');
define('BASE_PATH', __DIR__);
define('DB_FILE', __DIR__ . '/database/gym.db');
define('CURRENCY', 'USD');
define('CURRENCY_SYMBOL', '$');

// ============ SEGURIDAD: SESIÓN ENDURECIDA ============
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    // Solo se pueden cambiar estos ini antes de iniciar sesión y sin salida previa
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    if (PHP_VERSION_ID >= 70300) ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', '1800'); // 30 min
}
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
if (session_status() === PHP_SESSION_ACTIVE) {
    // Timeout por inactividad
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
        session_unset(); session_destroy();
        if (!headers_sent()) { session_start(); }
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['LAST_ACTIVITY'] = time();
        if (empty($_SESSION['initiated'])) { session_regenerate_id(true); $_SESSION['initiated'] = true; }
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}

// ============ HEADERS DE SEGURIDAD ============
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // CSP permisiva pero segura para CDN usados en el proyecto
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: https:; connect-src 'self'; frame-ancestors 'self';");
}

// ============ BASE DE DATOS (PDO + SQLite + Prepared Statements) ============
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            http_response_code(500);
            die('<h2>Falta el driver pdo_sqlite en PHP</h2><p>Actívelo en Laragon: Menú &gt; PHP &gt; Extensiones &gt; pdo_sqlite, o cambie a PHP 8.3.13 que ya lo trae. Luego recargue Apache.</p>');
        }
        try {
            $dir = dirname(DB_FILE);
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $pdo = new PDO('sqlite:' . DB_FILE);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec('PRAGMA foreign_keys = ON;');
            initDb($pdo);
        } catch (Throwable $t) {
            http_response_code(500);
            // En producción no exponer detalles; en local ayuda al diagnóstico
            die('<h2>Error de base de datos</h2><p>Verifique permisos de escritura en /database y que SQLite esté activo.</p>');
        }
    }
    return $pdo;
}

function initDb(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        rol TEXT NOT NULL DEFAULT 'cliente' CHECK(rol IN ('admin','entrenador','recepcionista','cliente')),
        telefono TEXT DEFAULT '',
        documento TEXT DEFAULT '',
        genero TEXT DEFAULT '',
        fecha_nac TEXT DEFAULT '',
        direccion TEXT DEFAULT '',
        foto TEXT DEFAULT '',
        estado TEXT DEFAULT 'activo' CHECK(estado IN ('activo','inactivo','suspendido')),
        intentos INTEGER DEFAULT 0,
        bloqueado_hasta INTEGER DEFAULT 0,
        ultimo_acceso TEXT DEFAULT '',
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS planes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL, precio REAL NOT NULL, duracion_dias INTEGER NOT NULL,
        descripcion TEXT DEFAULT '', beneficios TEXT DEFAULT '', color TEXT DEFAULT '#d4af37',
        estado TEXT DEFAULT 'activo', created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS membresias (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        plan_id INTEGER NOT NULL REFERENCES planes(id),
        fecha_inicio TEXT NOT NULL, fecha_fin TEXT NOT NULL,
        estado TEXT DEFAULT 'activa' CHECK(estado IN ('activa','vencida','congelada','cancelada')),
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pagos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        membresia_id INTEGER REFERENCES membresias(id) ON DELETE SET NULL,
        monto REAL NOT NULL, metodo TEXT DEFAULT 'efectivo',
        concepto TEXT NOT NULL, fecha_pago TEXT DEFAULT CURRENT_TIMESTAMP,
        registrado_por INTEGER REFERENCES users(id)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS asistencias (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        fecha TEXT NOT NULL, hora_entrada TEXT NOT NULL, hora_salida TEXT DEFAULT ''
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS clases (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL, entrenador_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
        capacidad INTEGER DEFAULT 20, horario TEXT NOT NULL, dias TEXT NOT NULL,
        descripcion TEXT DEFAULT '', estado TEXT DEFAULT 'activa', created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS clase_inscripciones (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        clase_id INTEGER NOT NULL REFERENCES clases(id) ON DELETE CASCADE,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        fecha TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(clase_id, user_id)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS rutinas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        entrenador_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
        titulo TEXT NOT NULL, nivel TEXT DEFAULT 'Intermedio',
        objetivo TEXT DEFAULT '', detalle TEXT NOT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS productos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL, precio REAL NOT NULL, stock INTEGER DEFAULT 0,
        categoria TEXT DEFAULT 'Suplemento', descripcion TEXT DEFAULT ''
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ventas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        producto_id INTEGER NOT NULL REFERENCES productos(id),
        user_id INTEGER NOT NULL REFERENCES users(id),
        cantidad INTEGER NOT NULL, total REAL NOT NULL, fecha TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER, accion TEXT NOT NULL, ip TEXT DEFAULT '', fecha TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS mensajes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL, email TEXT NOT NULL, telefono TEXT DEFAULT '',
        mensaje TEXT NOT NULL, fecha TEXT DEFAULT CURRENT_TIMESTAMP, leido INTEGER DEFAULT 0
    )");

    // Seed si vacío
    $n = (int)$pdo->query("SELECT COUNT(*) c FROM users")->fetch()['c'];
    if ($n === 0) {
        $admin = password_hash('Admin123*', PASSWORD_DEFAULT);
        $ent = password_hash('Entrenador123*', PASSWORD_DEFAULT);
        $rec = password_hash('Recepcion123*', PASSWORD_DEFAULT);
        $cli = password_hash('Cliente123*', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (nombre,email,password,rol,telefono,estado) VALUES (?,?,?,?,?,?)")
            ->execute(['Diana Trujillo (Admin)','admin@gym.com',$admin,'admin','3000000001','activo']);
        $pdo->prepare("INSERT INTO users (nombre,email,password,rol,telefono,estado) VALUES (?,?,?,?,?,?)")
            ->execute(['Carlos Coach','entrenador@gym.com',$ent,'entrenador','3000000002','activo']);
        $pdo->prepare("INSERT INTO users (nombre,email,password,rol,telefono,estado) VALUES (?,?,?,?,?,?)")
            ->execute(['Laura Recepción','recepcion@gym.com',$rec,'recepcionista','3000000003','activo']);
        $pdo->prepare("INSERT INTO users (nombre,email,password,rol,telefono,estado) VALUES (?,?,?,?,?,?)")
            ->execute(['Cliente Demo','cliente@gym.com',$cli,'cliente','3000000004','activo']);
        $planes = [
            ['Básico', 25.00, 30, 'Acceso a zona cardio y pesas en horario valle.', "Acceso sala musculación\n1 clase grupal/semana\nApp de seguimiento", '#9ca3af'],
            ['Premium', 45.00, 30, 'El favorito. Acceso total + clases ilimitadas.', "Todo lo Básico\nClases ilimitadas\nSauna + zona funcional\n1 valoración mensual", '#d4af37'],
            ['Black Elite', 79.00, 30, 'Experiencia VIP con entrenador personal.', "Todo lo Premium\n2 PT al mes\nNutrición personalizada\nAcceso invitado mensual", '#7c3aed'],
            ['Anual Pro', 490.00, 365, 'Ahorra 2 meses. Compromiso total.', "12 meses acceso total\n4 PT al año\nPlan nutricional\nPrioridad en clases", '#059669'],
        ];
        $st = $pdo->prepare("INSERT INTO planes (nombre,precio,duracion_dias,descripcion,beneficios,color) VALUES (?,?,?,?,?,?)");
        foreach ($planes as $p) $st->execute($p);
        $pdo->exec("INSERT INTO clases (nombre,entrenador_id,capacidad,horario,dias,descripcion) VALUES
            ('Cross Training',2,20,'06:00 - 07:00','Lun, Mié, Vie','Alta intensidad funcional'),
            ('Yoga & Movilidad',2,25,'07:30 - 08:30','Mar, Jue, Sáb','Flexibilidad y respiración'),
            ('Spinning Elite',2,18,'18:00 - 19:00','Lun - Vie','Cardio indoor con música'),
            ('Musculación Guiada',2,15,'17:00 - 18:00','Lun, Mié, Vie','Técnica e hipertrofia')");
        $pdo->exec("INSERT INTO productos (nombre,precio,stock,categoria,descripcion) VALUES
            ('Proteína Whey 1kg',45.00,30,'Suplemento','Aislada 27g proteína'),
            ('Creatina 300g',25.00,40,'Suplemento','Monohidrato puro'),
            ('Guantes Pro',15.00,25,'Accesorios','Agarre antideslizante'),
            ('Termo Aurum 1L',12.00,50,'Accesorios','Acero inoxidable'),
            ('Barra energética x12',18.00,60,'Nutrición','Pack proteína')");
    }
}

// ============ HELPERS SEGURIDAD ============
function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.e(csrf_token()).'">'; }
function verify_csrf(): void {
    $t = $_POST['csrf'] ?? '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$t)) {
        http_response_code(419); die('Error de seguridad CSRF. Recargue la página.');
    }
}
function redirect(string $url): void { header('Location: '.$url); exit; }
function currentUser(): ?array {
    if (empty($_SESSION['uid'])) return null;
    try {
        $st = db()->prepare("SELECT id,nombre,email,rol,telefono,estado FROM users WHERE id=? LIMIT 1");
        $st->execute([(int)$_SESSION['uid']]);
        $u = $st->fetch();
        return $u ?: null;
    } catch (Throwable $t) { return null; }
}
function requireLogin(): array {
    $u = currentUser();
    if (!$u) redirect('login.php');
    if ($u['estado'] !== 'activo') { session_destroy(); redirect('login.php?err=inactivo'); }
    return $u;
}
function requireRole(array $roles): array {
    $u = requireLogin();
    if (!in_array($u['rol'], $roles, true)) { http_response_code(403); die('Acceso denegado para su rol.'); }
    return $u;
}
function logAction(?int $uid, string $accion): void {
    try { db()->prepare("INSERT INTO logs (user_id,accion,ip) VALUES (?,?,?)")->execute([$uid, $accion, $_SERVER['REMOTE_ADDR'] ?? '']); }
    catch (Throwable $t) {}
}
function validateEmail(string $email): bool { return filter_var($email, FILTER_VALIDATE_EMAIL) !== false; }
function flash(string $k, ?string $msg = null) {
    if ($msg === null) { $m = $_SESSION['flash'][$k] ?? null; unset($_SESSION['flash'][$k]); return $m; }
    $_SESSION['flash'][$k] = $msg;
}
function money($n): string { return CURRENCY_SYMBOL . number_format((float)$n, 2, '.', ',') . ' ' . CURRENCY; }
function hoy(): string { return date('Y-m-d'); }
