<?php require_once __DIR__.'/config.php'; $pdo = db();
$planes = $pdo->query("SELECT * FROM planes WHERE estado='activo' ORDER BY precio")->fetchAll();
$clases = $pdo->query("SELECT c.*, u.nombre as entrenador FROM clases c LEFT JOIN users u ON u.id=c.entrenador_id WHERE c.estado='activa' LIMIT 4")->fetchAll();
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['contacto'])) {
  if (!hash_equals($_SESSION['csrf']??'', $_POST['csrf']??'')) die('CSRF');
  $n=trim(substr($_POST['nombre']??'',0,80)); $em=trim($_POST['email']??''); $t=trim(substr($_POST['telefono']??'',0,30)); $m=trim(substr($_POST['mensaje']??'',0,1000));
  if($n && validateEmail($em) && $m){
    $pdo->prepare("INSERT INTO mensajes (nombre,email,telefono,mensaje) VALUES (?,?,?,?)")->execute([$n,$em,$t,$m]);
    $ok="¡Gracias $n! Te contactaremos muy pronto. - Equipo Diana Trujillo";
  } else $err="Verifica nombre, email y mensaje.";
}
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?=e(APP_NAME)?> | Creado por <?=e(APP_CREATOR)?> | Gimnasio Elite</title>
<meta name="description" content="Aurum Fitness Club - Sistema profesional para gimnasios creado por Diana Trujillo. Fuerza, elegancia y resultados. Precios en USD.">
<link rel="stylesheet" href="assets/style.css">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;800&family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head><body>
<div class="topbar">✦ Black Elite $79 USD con 20% OFF este mes ✦ Solo 10 cupos VIP</div>
<nav class="nav"><div class="container nav-inner">
<a class="brand" href="index.php"><span class="brand-mark">A</span><span><b>AURUM FITNESS</b><small>by Diana Trujillo</small></span></a>
<div class="links"><a href="#planes">Planes</a><a href="#clases">Clases</a><a href="#entrenadores">Coaches</a><a href="#imc">IMC</a><a href="#contacto">Contacto</a></div>
<div class="nav-actions"><a class="btn btn-ghost btn-sm" href="login.php">Ingresar</a><a class="btn btn-gold btn-sm" href="registro.php">Únete ahora</a><button class="menu-btn" onclick="document.getElementById('mmenu').classList.toggle('open')" aria-label="Menú">☰</button></div>
</div><div id="mmenu" class="mobile-menu"><a href="#planes">Planes en USD</a><a href="#clases">Clases</a><a href="#entrenadores">Coaches</a><a href="#imc">Calculadora IMC</a><a href="#contacto">Contacto</a><a href="login.php">Ingresar</a><a href="registro.php">Únete ahora</a></div></nav>

<header class="hero"><div class="container hero-grid">
<div>
<span class="badge">✦ El gym más elegante de la ciudad</span>
<h1>Esculpe tu mejor versión con <span>lujo, fuerza y disciplina</span></h1>
<p class="lead">Más que un gimnasio: una experiencia premium. Entrenadores elite, tecnología de seguimiento, sauna, zona funcional y un sistema profesional creado por <b style="color:var(--gold2)">Diana Trujillo</b> para que cada entrenamiento cuente.</p>
<div class="hero-cta"><a class="btn btn-gold" href="registro.php">⚡ Prueba 7 días gratis</a><a class="btn btn-ghost" href="#planes">Ver planes</a></div>
<div class="stats"><div class="stat"><b>2.500+</b><span>Miembros activos</span></div><div class="stat"><b>35+</b><span>Clases semanales</span></div><div class="stat"><b>4.9★</b><span>Satisfacción</span></div></div>
</div>
<div class="hero-card"><div class="ph"></div><div class="hero-card-body">
<div class="price-row"><div><small style="color:var(--muted)">DESDE</small><br><b>$25.00 USD</b><small>/mes</small></div><span class="badge2 ok">● Inscripción $0 hoy</span></div>
<p style="color:var(--muted);font-size:.9rem">Incluye valoración física + plan nutricional inicial + app de seguimiento.</p>
<a class="btn btn-gold" style="width:100%" href="registro.php">Reservar mi cupo</a>
</div></div>
</div></header>

<section class="section container"><span class="kicker">Por qué Aurum</span><h2>Lujo que se entrena, resultados que se notan</h2>
<p class="sub">Diseñado para atraer y retener clientes: ambiente premium, higiene impecable, música, iluminación y atención personalizada.</p>
<div class="grid4">
<div class="card"><div class="icon">🏋️</div><h3>Equipos de última generación</h3><p>Technogym, peso libre, racks olímpicos y zona cardio con pantallas.</p></div>
<div class="card"><div class="icon">🧖</div><h3>Zona wellness</h3><p>Sauna, vestuarios premium, lockers inteligentes y toallas incluidas en Black.</p></div>
<div class="card"><div class="icon">📱</div><h3>App + seguimiento real</h3><p>Tu rutina, asistencia, pagos y progreso en un panel elegante y seguro.</p></div>
<div class="card"><div class="icon">🥗</div><h3>Nutrición elite</h3><p>Valoración mensual y planes alimenticios adaptados a tu objetivo.</p></div>
</div></section>

<section id="planes" class="section container"><span class="kicker">Membresías</span><h2>Planes que se venden solos</h2><p class="sub">Precios claros, beneficios irresistibles. Todos con acceso a app y control de asistencia.</p>
<div class="grid4">
<?php foreach($planes as $i=>$p): $ben=explode("\n",$p['beneficios']); ?>
<div class="card <?= $i==1?'featured':'' ?>"><?= $i==1?'<span class="badge2 warn" style="position:absolute;top:12px;right:12px">MÁS POPULAR</span>':'' ?>
<h3><?=e($p['nombre'])?></h3><div class="plan-price"><?=money($p['precio'])?><small style="font-size:.85rem;color:var(--muted)"> / <?= $p['duracion_dias']>=300?'año':'mes' ?></small></div>
<p><?=e($p['descripcion'])?></p><ul class="plan-list"><?php foreach($ben as $b) echo '<li>'.e(trim($b)).'</li>'; ?></ul>
<a class="btn <?= $i==1?'btn-gold':'btn-ghost' ?>" style="width:100%" href="registro.php?plan=<?=$p['id']?>">Elegir <?=e($p['nombre'])?></a></div>
<?php endforeach; ?></div></section>

<section id="clases" class="section container"><span class="kicker">Horarios</span><h2>Clases que enamoran</h2><div class="grid4">
<?php foreach($clases as $c): ?><div class="card"><span class="badge2 info"><?=e($c['horario'])?></span><h3><?=e($c['nombre'])?></h3><p><?=e($c['descripcion'])?><br><small>Días: <?=e($c['dias'])?> · Coach: <?=e($c['entrenador']??'Staff')?> · Cupo: <?=e($c['capacidad'])?></small></p></div><?php endforeach; ?>
</div></section>

<section id="entrenadores" class="section container"><span class="kicker">Team elite</span><h2>Coaches certificados</h2>
<div class="grid3">
<div class="card"><h3>💪 Carlos Coach</h3><p>CrossFit L2 · 10 años. Especialista en fuerza e hipertrofia.</p></div>
<div class="card"><h3>🧘 Diana Trujillo</h3><p>Fundadora & Head Coach. Nutrición deportiva, funcional y wellness premium.</p></div>
<div class="card"><h3>🚴 Elite Team</h3><p>Spinning, yoga, box y movilidad. Acompañamiento real en cada sesión.</p></div>
</div></section>

<section id="imc" class="section container"><div class="card">
<span class="kicker">Interactivo</span><h2>Calcula tu IMC en segundos</h2><p class="sub">Herramienta gratuita para atraer clientes. Pruébala y únete para tu valoración completa.</p>
<div class="calc"><div><label>Peso (kg)</label><input id="peso" type="number" value="70"></div><div><label>Altura (m) Ej: 1.70</label><input id="altura" type="number" step="0.01" value="1.70"></div><button class="btn btn-gold" onclick="calcIMC()">Calcular</button></div>
<p id="imcRes" style="font-weight:800;color:var(--gold2);margin-top:.8rem"></p>
</div></section>

<section id="contacto" class="section container"><div class="grid2">
<div><span class="kicker">Contacto</span><h2>Agenda tu clase de cortesía</h2><p class="sub">Cra 15 # 85-32 · Lun-Sáb 5am-10pm · Dom 7am-12m<br>📞 300 000 0000 · hola@aurumfitness.com</p>
<div class="card" style="padding:1.1rem">
<div style="font-size:2rem">📍</div>
<b>AURUM FITNESS CLUB</b><br><small style="color:var(--muted)">Cra 15 # 85-32, Bogotá · Zona premium<br>Lun-Vie 5am-10pm · Sáb 6am-6pm · Dom 7am-12m</small>
<div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-top:.9rem">
<a class="btn btn-gold btn-sm" href="https://www.google.com/maps/search/?api=1&query=Cra+15+%23+85-32+Bogota" target="_blank" rel="noopener">Ver en Google Maps</a>
<a class="btn btn-ghost btn-sm" href="https://wa.me/573000000000?text=Hola%20AURUM%20quiero%20mi%20clase%20gratis" target="_blank" rel="noopener">WhatsApp</a>
</div></div></div>
<div class="form"><h3>Escríbenos</h3>
<?php if(!empty($ok)) echo '<div class="alert alert-ok">'.e($ok).'</div>'; if(!empty($err)) echo '<div class="alert alert-err">'.e($err).'</div>'; ?>
<form method="post"><?=csrf_field()?><input type="hidden" name="contacto" value="1">
<label>Nombre</label><input name="nombre" required maxlength="80"><label>Email</label><input name="email" type="email" required><label>Teléfono / WhatsApp</label><input name="telefono"><label>Mensaje</label><textarea name="mensaje" rows="4" required placeholder="Quiero la prueba gratis..."></textarea>
<button class="btn btn-gold" style="width:100%;margin-top:1rem">Enviar mensaje</button></form></div>
</div></section>

<footer class="footer"><div class="container">
<div class="footer-grid"><div><a class="brand" href="#"><span class="brand-mark">A</span><span><b>AURUM FITNESS</b><small>by Diana Trujillo</small></span></a><p><small>Sistema profesional de gestión para gimnasios: miembros, pagos, rutinas, clases y reportes con seguridad avanzada.</small></p></div>
<div><b>Explorar</b><br><small><a href="#planes" style="text-decoration:none">Planes</a><br><a href="#clases" style="text-decoration:none">Clases</a><br><a href="login.php" style="text-decoration:none">Ingresar</a></small></div>
<div><b>Legal</b><br><small>Tratamiento de datos<br>Términos y privacidad<br>Contrato de membresía</small></div>
<div><b>Horario</b><br><small>Lun-Vie 5am-10pm<br>Sáb 6am-6pm<br>Dom 7am-12m</small></div></div>
<div class="creator"><span>✦ <b>Diseñado y desarrollado por Diana Trujillo</b> © 2026 · Todos los derechos reservados · v<?=e(APP_VERSION)?></span><span><small>🔒 Pagos seguros · Datos cifrados · Soporte premium</small></span></div>
</div></footer>
<a class="wa" href="https://wa.me/573000000000?text=Hola%20AURUM%20quiero%20mi%20clase%20gratis" target="_blank" rel="noopener">✆</a>
<script>function calcIMC(){let p=parseFloat(document.getElementById('peso').value),a=parseFloat(document.getElementById('altura').value);if(!p||!a){return}let imc=p/(a*a);let c=imc<18.5?'Bajo peso - te ayudamos a ganar masa muscular':imc<25?'¡Excelente! Mantén y define con nosotros':imc<30?'Sobrepeso - plan quema grasa + nutrición ideal para ti':'Obesidad - programa personalizado urgente contigo';document.getElementById('imcRes').textContent='Tu IMC: '+imc.toFixed(1)+' → '+c;}</script>
</body></html>
