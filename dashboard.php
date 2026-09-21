<?php require_once __DIR__.'/config.php';
$user = requireLogin();
$pdo = db();
// Auto-vencer membresías
$pdo->exec("UPDATE membresias SET estado='vencida' WHERE estado='activa' AND date(fecha_fin) < date('now')");
$rol = $user['rol'];
$page = preg_replace('/[^a-z_]/','', $_GET['page'] ?? 'inicio');
$msg = flash('ok'); $err = flash('err');

// Permisos por página
$perms = [
 'inicio'=>['admin','entrenador','recepcionista','cliente'],
 'miembros'=>['admin','recepcionista','entrenador'],
 'planes'=>['admin','recepcionista'],
 'membresias'=>['admin','recepcionista'],
 'pagos'=>['admin','recepcionista','cliente'],
 'asistencias'=>['admin','recepcionista','entrenador','cliente'],
 'clases'=>['admin','recepcionista','entrenador','cliente'],
 'rutinas'=>['admin','entrenador','cliente'],
 'tienda'=>['admin','recepcionista','cliente'],
 'usuarios'=>['admin'],
 'mensajes'=>['admin','recepcionista'],
 'reportes'=>['admin'],
 'perfil'=>['admin','entrenador','recepcionista','cliente'],
];
if(!isset($perms[$page]) || !in_array($rol,$perms[$page],true)) $page='inicio';

// ============ ACCIONES POST SEGURAS ============
if($_SERVER['REQUEST_METHOD']==='POST'){
  verify_csrf();
  $act = $_POST['act'] ?? '';
  try{
  // --- crear/editar usuario/miembro ---
  if($act==='save_user' && in_array($rol,['admin','recepcionista'])){
    $id=(int)($_POST['id']??0); $nombre=trim(substr($_POST['nombre']??'',0,100));
    $email=strtolower(trim($_POST['email']??'')); $tel=trim(substr($_POST['telefono']??'',0,30));
    $r=($_POST['rol']??'cliente'); if($rol!=='admin') $r='cliente';
    if(!in_array($r,['admin','entrenador','recepcionista','cliente'],true)) throw new Exception('Rol inválido');
    $estado=$_POST['estado']??'activo'; if(!in_array($estado,['activo','inactivo','suspendido'],true)) $estado='activo';
    if(strlen($nombre)<3||!validateEmail($email)) throw new Exception('Datos inválidos');
    if($id>0){ $pdo->prepare("UPDATE users SET nombre=?,email=?,telefono=?,rol=?,estado=? WHERE id=?")->execute([$nombre,$email,$tel,$r,$estado,$id]);
      if(!empty($_POST['pass'])){ if(strlen($_POST['pass'])<8) throw new Exception('Clave mín 8'); $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($_POST['pass'],PASSWORD_DEFAULT),$id]); }
    } else {
      $pass=$_POST['pass']??''; if(strlen($pass)<8) throw new Exception('Clave mín 8 caracteres');
      $pdo->prepare("INSERT INTO users (nombre,email,password,rol,telefono,estado) VALUES (?,?,?,?,?,?)")->execute([$nombre,$email,password_hash($pass,PASSWORD_DEFAULT),$r,$tel,$estado]);
    }
    logAction($user['id'],'save_user '.$email); flash('ok','Usuario guardado correctamente.'); redirect('dashboard.php?page='.($r==='cliente'?'miembros':'usuarios'));
  }
  if($act==='del_user' && $rol==='admin'){
    $id=(int)$_POST['id']; if($id===$user['id']) throw new Exception('No puede eliminarse a sí mismo');
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]); logAction($user['id'],'del_user '.$id); flash('ok','Usuario eliminado.');
    redirect('dashboard.php?page='.$page);
  }
  // --- planes ---
  if($act==='save_plan' && $rol==='admin'){
    $id=(int)($_POST['id']??0); $n=trim(substr($_POST['nombre']??'',0,80)); $pr=(float)($_POST['precio']??0);
    $d=(int)($_POST['duracion']??30); $desc=trim(substr($_POST['descripcion']??'',0,500)); $ben=trim(substr($_POST['beneficios']??'',0,1000));
    if(!$n||$pr<=0||$d<=0) throw new Exception('Plan inválido');
    if($id>0) $pdo->prepare("UPDATE planes SET nombre=?,precio=?,duracion_dias=?,descripcion=?,beneficios=? WHERE id=?")->execute([$n,$pr,$d,$desc,$ben,$id]);
    else $pdo->prepare("INSERT INTO planes (nombre,precio,duracion_dias,descripcion,beneficios) VALUES (?,?,?,?,?)")->execute([$n,$pr,$d,$desc,$ben]);
    flash('ok','Plan guardado.'); redirect('dashboard.php?page=planes');
  }
  if($act==='del_plan' && $rol==='admin'){ $pdo->prepare("DELETE FROM planes WHERE id=?")->execute([(int)$_POST['id']]); flash('ok','Plan eliminado.'); redirect('dashboard.php?page=planes'); }
  // --- membresía ---
  if($act==='save_mem' && in_array($rol,['admin','recepcionista'])){
    $uid=(int)$_POST['user_id']; $pid=(int)$_POST['plan_id']; $ini=$_POST['inicio']??hoy();
    $pl=$pdo->prepare("SELECT * FROM planes WHERE id=?"); $pl->execute([$pid]); $pl=$pl->fetch(); if(!$pl) throw new Exception('Plan no existe');
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$ini)) $ini=hoy();
    $fin=date('Y-m-d',strtotime($ini.' +'.$pl['duracion_dias'].' days'));
    $m=$pdo->prepare("INSERT INTO membresias (user_id,plan_id,fecha_inicio,fecha_fin,estado) VALUES (?,?,?,?,?)");
    $m->execute([$uid,$pid,$ini,$fin,'activa']); $mid=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO pagos (user_id,membresia_id,monto,metodo,concepto,registrado_por) VALUES (?,?,?,?,?,?)")->execute([$uid,$mid,$pl['precio'],$_POST['metodo']??'efectivo','Membresía '.$pl['nombre'],$user['id']]);
    logAction($user['id'],'nueva membresia u='.$uid); flash('ok','Membresía creada y pago registrado.'); redirect('dashboard.php?page=membresias');
  }
  if($act==='mem_estado' && in_array($rol,['admin','recepcionista'])){
    $est=$_POST['estado']??'activa'; if(!in_array($est,['activa','vencida','congelada','cancelada'],true)) $est='activa';
    $pdo->prepare("UPDATE membresias SET estado=? WHERE id=?")->execute([$est,(int)$_POST['id']]); flash('ok','Estado actualizado.'); redirect('dashboard.php?page=membresias');
  }
  // --- pagos ---
  if($act==='save_pago' && in_array($rol,['admin','recepcionista'])){
    $uid=(int)$_POST['user_id']; $monto=(float)$_POST['monto']; $conc=trim(substr($_POST['concepto']??'',0,150));
    if($monto<=0||!$conc) throw new Exception('Monto/concepto inválidos');
    $pdo->prepare("INSERT INTO pagos (user_id,membresia_id,monto,metodo,concepto,registrado_por) VALUES (?,?,?,?,?,?)")->execute([$uid,$_POST['membresia_id']?:null,$monto,$_POST['metodo']??'efectivo',$conc,$user['id']]);
    flash('ok','Pago registrado.'); redirect('dashboard.php?page=pagos');
  }
  // --- asistencia checkin/out ---
  if($act==='checkin'){
    $uid = ($rol==='cliente') ? $user['id'] : (int)($_POST['user_id']??$user['id']);
    if($rol!=='cliente' && $rol!=='admin' && $rol!=='recepcionista' && $uid!==$user['id']) throw new Exception('Sin permiso');
    $open=$pdo->prepare("SELECT * FROM asistencias WHERE user_id=? AND hora_salida='' ORDER BY id DESC LIMIT 1"); $open->execute([$uid]); $o=$open->fetch();
    if($o){ $pdo->prepare("UPDATE asistencias SET hora_salida=? WHERE id=?")->execute([date('H:i:s'),$o['id']]); flash('ok','Salida registrada. ¡Buen entreno!'); }
    else{ $pdo->prepare("INSERT INTO asistencias (user_id,fecha,hora_entrada) VALUES (?,?,?)")->execute([$uid,hoy(),date('H:i:s')]); flash('ok','Entrada registrada. ¡A entrenar!'); }
    redirect('dashboard.php?page=asistencias');
  }
  // --- clases ---
  if($act==='save_clase' && in_array($rol,['admin','recepcionista'])){
    $id=(int)($_POST['id']??0); $n=trim(substr($_POST['nombre']??'',0,100)); $h=trim(substr($_POST['horario']??'',0,50)); $d=trim(substr($_POST['dias']??'',0,100));
    $cap=max(1,(int)($_POST['capacidad']??20)); $ent=(int)($_POST['entrenador_id']??0)?:null; $desc=trim(substr($_POST['descripcion']??'',0,500));
    if(!$n||!$h||!$d) throw new Exception('Clase inválida');
    if($id>0) $pdo->prepare("UPDATE clases SET nombre=?,entrenador_id=?,capacidad=?,horario=?,dias=?,descripcion=? WHERE id=?")->execute([$n,$ent,$cap,$h,$d,$desc,$id]);
    else $pdo->prepare("INSERT INTO clases (nombre,entrenador_id,capacidad,horario,dias,descripcion) VALUES (?,?,?,?,?,?)")->execute([$n,$ent,$cap,$h,$d,$desc]);
    flash('ok','Clase guardada.'); redirect('dashboard.php?page=clases');
  }
  if($act==='del_clase' && $rol==='admin'){ $pdo->prepare("DELETE FROM clases WHERE id=?")->execute([(int)$_POST['id']]); redirect('dashboard.php?page=clases'); }
  if($act==='inscribir_clase'){
    $cid=(int)$_POST['clase_id']; $uid=($rol==='cliente')?$user['id']:(int)($_POST['user_id']??$user['id']);
    $c=$pdo->prepare("SELECT * FROM clases WHERE id=?"); $c->execute([$cid]); $c=$c->fetch(); if(!$c) throw new Exception('Clase no existe');
    $n=$pdo->prepare("SELECT COUNT(*) t FROM clase_inscripciones WHERE clase_id=?"); $n->execute([$cid]); if((int)$n->fetch()['t']>=(int)$c['capacidad']) throw new Exception('Clase llena');
    $pdo->prepare("INSERT OR IGNORE INTO clase_inscripciones (clase_id,user_id) VALUES (?,?)")->execute([$cid,$uid]);
    flash('ok','Inscripción exitosa.'); redirect('dashboard.php?page=clases');
  }
  if($act==='salir_clase'){ $pdo->prepare("DELETE FROM clase_inscripciones WHERE clase_id=? AND user_id=?")->execute([(int)$_POST['clase_id'],($rol==='cliente')?$user['id']:(int)$_POST['user_id']]); redirect('dashboard.php?page=clases'); }
  // --- rutinas ---
  if($act==='save_rutina' && in_array($rol,['admin','entrenador'])){
    $uid=(int)$_POST['user_id']; $t=trim(substr($_POST['titulo']??'',0,120)); $det=trim(substr($_POST['detalle']??'',0,5000));
    if(!$t||!$det) throw new Exception('Rutina incompleta');
    $pdo->prepare("INSERT INTO rutinas (user_id,entrenador_id,titulo,nivel,objetivo,detalle) VALUES (?,?,?,?,?,?)")->execute([$uid,$user['id'],$t,$_POST['nivel']??'Intermedio',$_POST['objetivo']??'',$det]);
    flash('ok','Rutina asignada.'); redirect('dashboard.php?page=rutinas');
  }
  if($act==='del_rutina' && in_array($rol,['admin','entrenador'])){ $pdo->prepare("DELETE FROM rutinas WHERE id=?")->execute([(int)$_POST['id']]); redirect('dashboard.php?page=rutinas'); }
  // --- tienda ---
  if($act==='save_prod' && $rol==='admin'){
    $id=(int)($_POST['id']??0); $n=trim(substr($_POST['nombre']??'',0,100)); $pr=(float)$_POST['precio']; $st=(int)$_POST['stock'];
    if(!$n||$pr<0) throw new Exception('Producto inválido');
    if($id>0) $pdo->prepare("UPDATE productos SET nombre=?,precio=?,stock=?,categoria=?,descripcion=? WHERE id=?")->execute([$n,$pr,$st,$_POST['categoria']??'Suplemento',$_POST['descripcion']??'',$id]);
    else $pdo->prepare("INSERT INTO productos (nombre,precio,stock,categoria,descripcion) VALUES (?,?,?,?,?)")->execute([$n,$pr,$st,$_POST['categoria']??'Suplemento',$_POST['descripcion']??'']);
    flash('ok','Producto guardado.'); redirect('dashboard.php?page=tienda');
  }
  if($act==='vender' ){
    if(!in_array($rol,['admin','recepcionista','cliente'])) throw new Exception('Sin permiso');
    $pid=(int)$_POST['producto_id']; $cant=max(1,(int)$_POST['cantidad']); $uid=($rol==='cliente')?$user['id']:(int)($_POST['user_id']??$user['id']);
    $p=$pdo->prepare("SELECT * FROM productos WHERE id=?"); $p->execute([$pid]); $p=$p->fetch(); if(!$p) throw new Exception('Producto no existe');
    if($p['stock']<$cant) throw new Exception('Stock insuficiente');
    $pdo->prepare("UPDATE productos SET stock=stock-? WHERE id=?")->execute([$cant,$pid]);
    $pdo->prepare("INSERT INTO ventas (producto_id,user_id,cantidad,total) VALUES (?,?,?,?)")->execute([$pid,$uid,$cant,$p['precio']*$cant]);
    $pdo->prepare("INSERT INTO pagos (user_id,monto,metodo,concepto,registrado_por) VALUES (?,?,?,?,?)")->execute([$uid,$p['precio']*$cant,$_POST['metodo']??'efectivo','Tienda: '.$p['nombre'].' x'.$cant,$user['id']]);
    flash('ok','Venta registrada.'); redirect('dashboard.php?page=tienda');
  }
  // --- perfil ---
  if($act==='save_perfil'){
    $n=trim(substr($_POST['nombre']??'',0,100)); $t=trim(substr($_POST['telefono']??'',0,30));
    if(strlen($n)<3) throw new Exception('Nombre inválido');
    $pdo->prepare("UPDATE users SET nombre=?,telefono=? WHERE id=?")->execute([$n,$t,$user['id']]);
    if(!empty($_POST['pass'])){ if(strlen($_POST['pass'])<8) throw new Exception('Clave mín 8'); $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($_POST['pass'],PASSWORD_DEFAULT),$user['id']]); }
    flash('ok','Perfil actualizado.'); redirect('dashboard.php?page=perfil');
  }
  if($act==='marcar_leido' && in_array($rol,['admin','recepcionista'])){ $pdo->prepare("UPDATE mensajes SET leido=1 WHERE id=?")->execute([(int)$_POST['id']]); redirect('dashboard.php?page=mensajes'); }
  }catch(Throwable $t){ flash('err',$t->getMessage()); redirect('dashboard.php?page='.$page); }
}

// ============ DATOS DASHBOARD ============
$totalMiembros=(int)$pdo->query("SELECT COUNT(*) c FROM users WHERE rol='cliente'")->fetch()['c'];
$memActivas=(int)$pdo->query("SELECT COUNT(*) c FROM membresias WHERE estado='activa'")->fetch()['c'];
$ingMes=(float)($pdo->query("SELECT COALESCE(SUM(monto),0) t FROM pagos WHERE strftime('%Y-%m',fecha_pago)=strftime('%Y-%m','now')")->fetch()['t']??0);
$asisHoy=(int)$pdo->query("SELECT COUNT(*) c FROM asistencias WHERE fecha=date('now')")->fetch()['c'];
$q=trim($_GET['q']??'');
function menu($rol){ $m=[['inicio','🏠','Inicio']];
 if(in_array($rol,['admin','recepcionista','entrenador'])) $m[]=['miembros','👥','Miembros'];
 if(in_array($rol,['admin','recepcionista'])) $m[]=['planes','💎','Planes'];
 if(in_array($rol,['admin','recepcionista'])) $m[]=['membresias','🎫','Membresías'];
 $m[]=['pagos','💰','Pagos']; $m[]=['asistencias','✅','Asistencia']; $m[]=['clases','🔥','Clases']; $m[]=['rutinas','📋','Rutinas']; $m[]=['tienda','🛒','Tienda'];
 if($rol==='admin') $m[]=['usuarios','🛡️','Usuarios'];
 if(in_array($rol,['admin','recepcionista'])) $m[]=['mensajes','✉️','Mensajes'];
 if($rol==='admin') $m[]=['reportes','📊','Reportes'];
 $m[]=['perfil','👤','Mi perfil']; return $m; }
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Panel | <?=e(APP_NAME)?></title><link rel="stylesheet" href="assets/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"></head>
<body><div class="side-overlay" id="ovl" onclick="toggleSide(false)"></div><div class="layout">
<aside class="side" id="side">
<a class="brand" href="index.php" style="text-decoration:none;margin-bottom:1rem"><span class="brand-mark">A</span><span><b>AURUM</b><small>by Diana Trujillo</small></span></a>
<div style="margin:.6rem 0;padding:.7rem;border:1px solid var(--line);border-radius:12px;background:#101016"><b><?=e($user['nombre'])?></b><br><small style="color:var(--gold2)"><?=e(strtoupper($rol))?> · <?=e($user['email'])?></small></div>
<?php foreach(menu($rol) as [$slug,$ico,$lab]): ?><a class="item <?= $page===$slug?'active':'' ?>" href="dashboard.php?page=<?=$slug?>"><?=$ico?> <?=$lab?></a><?php endforeach; ?>
<a class="item" href="index.php">🌐 Ver web</a><a class="item" href="logout.php">🚪 Salir</a>
<div style="margin-top:1rem;font-size:.75rem;color:var(--muted)">© 2026 Diana Trujillo<br>v<?=e(APP_VERSION)?> · Seguro 🔒</div>
</aside>
<main class="main"><button class="btn btn-ghost btn-sm dash-toggle" onclick="toggleSide()">☰ Menú</button>
<div class="top"><div><span class="kicker">Panel <?=e($rol)?></span><h2 style="margin:.2rem 0"><?=e(ucfirst($page))?></h2></div>
<div style="display:flex;gap:.6rem"><form method="post" style="display:flex;gap:.5rem"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="act" value="checkin"><?php if($rol!=='cliente'): ?><input name="user_id" placeholder="ID miembro" style="max-width:130px"><?php endif; ?><button class="btn btn-gold btn-sm">✅ Check-in/out</button></form></div></div>
<?php if($msg) echo '<div class="alert alert-ok">'.e($msg).'</div>'; if($err) echo '<div class="alert alert-err">'.e($err).'</div>'; ?>

<?php if($page==='inicio'): ?>
<div class="kpi"><div class="card"><small style="color:var(--muted)">MIEMBROS</small><br><b><?=$totalMiembros?></b></div><div class="card"><small style="color:var(--muted)">MEMBRESÍAS ACTIVAS</small><br><b style="color:var(--gold2)"><?=$memActivas?></b></div><div class="card"><small style="color:var(--muted)">INGRESOS MES</small><br><b style="color:#6ee7b7"><?=money($ingMes)?></b></div><div class="card"><small style="color:var(--muted)">ASISTENCIAS HOY</small><br><b><?=$asisHoy?></b></div></div>
<div class="grid2">
<div class="card"><h3>Últimos pagos</h3><?php $lp=$pdo->query("SELECT p.*,u.nombre FROM pagos p JOIN users u ON u.id=p.user_id ORDER BY p.id DESC LIMIT 6")->fetchAll(); ?>
<div class="table-wrap"><table class="table"><tr><th>Cliente</th><th>Concepto</th><th>Monto</th></tr><?php foreach($lp as $r): ?><tr><td><?=e($r['nombre'])?></td><td><?=e($r['concepto'])?></td><td><?=money($r['monto'])?></td></tr><?php endforeach; ?></table></div></div>
<div class="card"><h3>Mi membresía</h3><?php if($rol==='cliente'){ $m=$pdo->prepare("SELECT m.*,pl.nombre pn FROM membresias m JOIN planes pl ON pl.id=m.plan_id WHERE m.user_id=? ORDER BY m.id DESC LIMIT 1"); $m->execute([$user['id']]); $m=$m->fetch();
 echo $m?'<p>Plan <b style="color:var(--gold2)">'.e($m['pn']).'</b><br>Fin: '.e($m['fecha_fin']).' <span class="badge2 '.($m['estado']=='activa'?'ok':'bad').'">'.e($m['estado']).'</span></p>':'<p style="color:var(--muted)">Sin membresía. Ve a recepción o elige plan en la web.</p>'; }
 else echo '<p style="color:var(--muted)">Vencen en 7 días:</p>'.implode('',array_map(fn($r)=>'<div>• '.e($r['nombre']).' ('.e($r['fecha_fin']).')</div>',$pdo->query("SELECT m.fecha_fin,u.nombre FROM membresias m JOIN users u ON u.id=m.user_id WHERE m.estado='activa' AND date(m.fecha_fin)<=date('now','+7 days') LIMIT 5")->fetchAll())); ?>
 <hr style="border-color:var(--line)"><small style="color:var(--muted)">Sistema creado por <b style="color:var(--gold2)">Diana Trujillo</b></small></div>
</div>
<?php endif; ?>

<?php if($page==='miembros'): ?>
<div class="toolbar"><form method="get"><input type="hidden" name="page" value="miembros"><input class="search" name="q" value="<?=e($q)?>" placeholder="🔍 Buscar miembro..."></form></div>
<div class="card"><h3>Registrar miembro</h3><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="act" value="save_user"><input type="hidden" name="id" value="0">
<input name="nombre" required placeholder="Nombre completo"><input name="email" type="email" required placeholder="Email"><input name="telefono" placeholder="Teléfono"><input name="pass" type="password" required placeholder="Clave inicial (8+)"><select name="rol"><option value="cliente">Cliente</option><?php if($rol==='admin'): ?><option value="entrenador">Entrenador</option><option value="recepcionista">Recepcionista</option><option value="admin">Admin</option><?php endif; ?></select><select name="estado"><option value="activo">Activo</option><option value="inactivo">Inactivo</option><option value="suspendido">Suspendido</option></select>
<button class="btn btn-gold btn-sm">Guardar</button></form></div>
<?php $st=$pdo->prepare("SELECT * FROM users WHERE rol='cliente' AND (nombre LIKE ? OR email LIKE ?) ORDER BY id DESC LIMIT 100"); $st->execute(["%$q%","%$q%"]); $rows=$st->fetchAll(); ?>
<div class="table-wrap"><table class="table" style="margin-top:1rem"><tr><th>ID</th><th>Nombre</th><th>Email</th><th>Tel</th><th>Estado</th><th>Acción</th></tr>
<?php foreach($rows as $r): ?><tr><td><?=$r['id']?></td><td><?=e($r['nombre'])?></td><td><?=e($r['email'])?></td><td><?=e($r['telefono'])?></td><td><span class="badge2 <?= $r['estado']=='activo'?'ok':'bad' ?>"><?=e($r['estado'])?></span></td>
<td><form method="post" style="display:flex;gap:.3rem"><?=csrf_field()?><input type="hidden" name="act" value="save_user"><input type="hidden" name="id" value="<?=$r['id']?>"><input type="hidden" name="nombre" value="<?=e($r['nombre'])?>"><input type="hidden" name="email" value="<?=e($r['email'])?>"><input type="hidden" name="telefono" value="<?=e($r['telefono'])?>"><input type="hidden" name="rol" value="cliente">
<select name="estado" onchange="this.form.submit()"><option <?= $r['estado']=='activo'?'selected':'' ?> value="activo">activo</option><option <?= $r['estado']=='suspendido'?'selected':'' ?> value="suspendido">suspendido</option><option <?= $r['estado']=='inactivo'?'selected':'' ?> value="inactivo">inactivo</option></select></form></td></tr><?php endforeach; ?></table></div>
<?php endif; ?>

<?php if($page==='planes'): $pls=$pdo->query("SELECT * FROM planes ORDER BY precio")->fetchAll(); ?>
<div class="grid4"><?php foreach($pls as $p): ?><div class="card"><h3><?=e($p['nombre'])?></h3><div class="plan-price"><?=money($p['precio'])?></div><p><?=e($p['descripcion'])?> · <?=e($p['duracion_dias'])?> días</p><?php if($rol==='admin'): ?><form method="post"><?=csrf_field()?><input type="hidden" name="act" value="del_plan"><input type="hidden" name="id" value="<?=$p['id']?>"><button class="btn btn-ghost btn-sm" onclick="return confirm('¿Eliminar?')">Eliminar</button></form><?php endif; ?></div><?php endforeach; ?></div>
<?php if($rol==='admin'): ?><div class="card" style="margin-top:1rem"><h3>Nuevo / editar plan</h3><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="act" value="save_plan"><input type="hidden" name="id" value="0"><input name="nombre" required placeholder="Nombre"><input name="precio" type="number" step="0.01" min="0" required placeholder="Precio"><input name="duracion" type="number" value="30" placeholder="Días"><input name="descripcion" placeholder="Descripción"><input name="beneficios" placeholder="Beneficios separados por línea"><button class="btn btn-gold btn-sm">Guardar plan</button></form></div><?php endif; ?>
<?php endif; ?>

<?php if($page==='membresias'): ?>
<div class="card"><h3>Asignar membresía + cobro</h3><form method="post" class="form-grid-4"><?=csrf_field()?><input type="hidden" name="act" value="save_mem">
<select name="user_id" required><option value="">-- Cliente --</option><?php foreach($pdo->query("SELECT id,nombre FROM users WHERE rol='cliente' AND estado='activo'")->fetchAll() as $u): ?><option value="<?=$u['id']?>"><?=e($u['nombre'])?></option><?php endforeach; ?></select>
<select name="plan_id" required><?php foreach($pdo->query("SELECT * FROM planes")->fetchAll() as $p): ?><option value="<?=$p['id']?>"><?=e($p['nombre'])?> <?=money($p['precio'])?></option><?php endforeach; ?></select>
<input type="date" name="inicio" value="<?=hoy()?>"><select name="metodo"><option>efectivo</option><option>tarjeta</option><option>transferencia</option><option>nequi</option></select>
<button class="btn btn-gold btn-sm">Crear</button></form></div>
<?php $ms=$pdo->query("SELECT m.*,u.nombre un,pl.nombre pn FROM membresias m JOIN users u ON u.id=m.user_id JOIN planes pl ON pl.id=m.plan_id ORDER BY m.id DESC LIMIT 80")->fetchAll(); ?>
<div class="table-wrap"><table class="table" style="margin-top:1rem"><tr><th>Cliente</th><th>Plan</th><th>Inicio</th><th>Fin</th><th>Estado</th><th>Cambiar</th></tr>
<?php foreach($ms as $m): ?><tr><td><?=e($m['un'])?></td><td><?=e($m['pn'])?></td><td><?=e($m['fecha_inicio'])?></td><td><?=e($m['fecha_fin'])?></td><td><span class="badge2 <?= $m['estado']=='activa'?'ok':($m['estado']=='vencida'?'bad':'warn') ?>"><?=e($m['estado'])?></span></td>
<td><form method="post" style="display:flex;gap:.3rem"><?=csrf_field()?><input type="hidden" name="act" value="mem_estado"><input type="hidden" name="id" value="<?=$m['id']?>"><select name="estado" onchange="this.form.submit()"><?php foreach(['activa','vencida','congelada','cancelada'] as $es): ?><option <?= $m['estado']==$es?'selected':'' ?>><?=$es?></option><?php endforeach; ?></select></form></td></tr><?php endforeach; ?></table></div>
<?php endif; ?>

<?php if($page==='pagos'): ?>
<?php if(in_array($rol,['admin','recepcionista'])): ?><div class="card"><h3>Registrar pago / venta manual</h3><form method="post" class="form-grid-4"><?=csrf_field()?><input type="hidden" name="act" value="save_pago">
<select name="user_id" required><option value="">-- Cliente --</option><?php foreach($pdo->query("SELECT id,nombre FROM users WHERE rol='cliente'")->fetchAll() as $u): ?><option value="<?=$u['id']?>"><?=e($u['nombre'])?></option><?php endforeach; ?></select>
<input name="monto" type="number" step="0.01" min="0" required placeholder="Monto"><select name="metodo"><option>efectivo</option><option>tarjeta</option><option>transferencia</option><option>nequi</option></select><input name="concepto" required placeholder="Concepto"><input name="membresia_id" placeholder="ID membresía (opcional)"><button class="btn btn-gold btn-sm">Registrar</button></form></div><?php endif; ?>
<?php $sqlP = $rol==='cliente' ? "SELECT p.*,u.nombre FROM pagos p JOIN users u ON u.id=p.user_id WHERE p.user_id={$user['id']} ORDER BY p.id DESC LIMIT 50" : "SELECT p.*,u.nombre FROM pagos p JOIN users u ON u.id=p.user_id ORDER BY p.id DESC LIMIT 80";
$ps=$pdo->query($sqlP)->fetchAll(); ?>
<div class="table-wrap"><table class="table" style="margin-top:1rem"><tr><th>#</th><th>Cliente</th><th>Concepto</th><th>Método</th><th>Monto</th><th>Fecha</th></tr>
<?php foreach($ps as $r): ?><tr><td><?=$r['id']?></td><td><?=e($r['nombre'])?></td><td><?=e($r['concepto'])?></td><td><?=e($r['metodo'])?></td><td><b style="color:var(--gold2)"><?=money($r['monto'])?></b></td><td><?=e($r['fecha_pago'])?></td></tr><?php endforeach; ?></table></div>
<?php endif; ?>

<?php if($page==='asistencias'): ?>
<?php $la=$pdo->prepare($rol==='cliente'?"SELECT a.*,u.nombre FROM asistencias a JOIN users u ON u.id=a.user_id WHERE a.user_id=? ORDER BY a.id DESC LIMIT 40":"SELECT a.*,u.nombre FROM asistencias a JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 60");
$la->execute($rol==='cliente'?[$user['id']]:[]); $rows=$la->fetchAll(); ?>
<div class="table-wrap"><table class="table"><tr><th>Miembro</th><th>Fecha</th><th>Entrada</th><th>Salida</th></tr><?php foreach($rows as $r): ?><tr><td><?=e($r['nombre'])?></td><td><?=e($r['fecha'])?></td><td><?=e($r['hora_entrada'])?></td><td><?=e($r['hora_salida']?:'— en gym 🏋️')?></td></tr><?php endforeach; ?></table></div>
<?php endif; ?>

<?php if($page==='clases'): $cls=$pdo->query("SELECT c.*,u.nombre en,(SELECT COUNT(*) FROM clase_inscripciones WHERE clase_id=c.id) insc FROM clases c LEFT JOIN users u ON u.id=c.entrenador_id ORDER BY c.id")->fetchAll(); ?>
<div class="grid4"><?php foreach($cls as $c): ?><div class="card"><span class="badge2 info"><?=e($c['horario'])?> · <?=e($c['dias'])?></span><h3><?=e($c['nombre'])?></h3><p><?=e($c['descripcion'])?><br><small>Coach: <?=e($c['en']??'Staff')?> · <?=e($c['insc'])?>/<?=e($c['capacidad'])?></small></p>
<form method="post"><?=csrf_field()?><input type="hidden" name="act" value="inscribir_clase"><input type="hidden" name="clase_id" value="<?=$c['id']?>"><?php if($rol!=='cliente'): ?><input name="user_id" placeholder="ID cliente" style="margin-bottom:.4rem"><?php endif; ?><button class="btn btn-gold btn-sm" style="width:100%">Inscribirme / Inscribir</button></form></div><?php endforeach; ?></div>
<?php if(in_array($rol,['admin','recepcionista'])): ?><div class="card" style="margin-top:1rem"><h3>Crear clase</h3><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="act" value="save_clase"><input type="hidden" name="id" value="0"><input name="nombre" required placeholder="Nombre"><input name="horario" required placeholder="18:00 - 19:00"><input name="dias" required placeholder="Lun, Mié, Vie"><input name="capacidad" type="number" value="20"><select name="entrenador_id"><option value="0">-- Coach --</option><?php foreach($pdo->query("SELECT id,nombre FROM users WHERE rol='entrenador'")->fetchAll() as $t): ?><option value="<?=$t['id']?>"><?=e($t['nombre'])?></option><?php endforeach; ?></select><input name="descripcion" placeholder="Descripción"><button class="btn btn-gold btn-sm">Guardar</button></form></div><?php endif; ?>
<?php $ins=$pdo->prepare($rol==='cliente'?"SELECT ci.*,c.nombre FROM clase_inscripciones ci JOIN clases c ON c.id=ci.clase_id WHERE ci.user_id=?":"SELECT ci.*,c.nombre,u.nombre un FROM clase_inscripciones ci JOIN clases c ON c.id=ci.clase_id JOIN users u ON u.id=ci.user_id LIMIT 50"); $ins->execute($rol==='cliente'?[$user['id']]:[]); ?>
<div class="card" style="margin-top:1rem"><h3>Mis inscripciones</h3><?php foreach($ins->fetchAll() as $i): ?><div>• <?=e($i['nombre'])?> <?= isset($i['un'])?' - '.e($i['un']):'' ?></div><?php endforeach; ?></div>
<?php endif; ?>

<?php if($page==='rutinas'): ?>
<?php if(in_array($rol,['admin','entrenador'])): ?><div class="card"><h3>Asignar rutina</h3><form method="post"><?=csrf_field()?><input type="hidden" name="act" value="save_rutina">
<div class="form-grid"><select name="user_id" required><option value="">-- Cliente --</option><?php foreach($pdo->query("SELECT id,nombre FROM users WHERE rol='cliente' AND estado='activo'")->fetchAll() as $u): ?><option value="<?=$u['id']?>"><?=e($u['nombre'])?></option><?php endforeach; ?></select><input name="titulo" required placeholder="Título: Hipertrofia 4 días"><select name="nivel"><option>Principiante</option><option selected>Intermedio</option><option>Avanzado</option></select></div>
<label>Objetivo</label><input name="objetivo" placeholder="Ganar masa / Perder grasa..."><label>Detalle (ejercicios, series, reps)</label><textarea name="detalle" rows="5" required placeholder="Día 1 - Pecho/Tríceps: Press banca 4x10..."></textarea>
<button class="btn btn-gold btn-sm" style="margin-top:.6rem">Asignar rutina</button></form></div><?php endif; ?>
<?php $rq=$rol==='cliente'?$pdo->prepare("SELECT r.*,u.nombre en FROM rutinas r LEFT JOIN users u ON u.id=r.entrenador_id WHERE r.user_id=? ORDER BY r.id DESC"):$pdo->query("SELECT r.*,u.nombre un,e.nombre en FROM rutinas r JOIN users u ON u.id=r.user_id LEFT JOIN users e ON e.id=r.entrenador_id ORDER BY r.id DESC LIMIT 50");
if($rol==='cliente'){ $rq->execute([$user['id']]); } $rs=$rq->fetchAll(); ?>
<div class="grid3" style="margin-top:1rem"><?php foreach($rs as $r): ?><div class="card"><span class="badge2 warn"><?=e($r['nivel'])?></span><h3><?=e($r['titulo'])?></h3><p><small><?= isset($r['un'])?'Para: '.e($r['un']).'<br>':'' ?>Coach: <?=e($r['en']??'Staff')?> · <?=e($r['objetivo'])?> · <?=e($r['created_at'])?></small></p><pre style="white-space:pre-wrap;font-family:inherit;color:#d8d8df;font-size:.88rem"><?=e($r['detalle'])?></pre></div><?php endforeach; if(!$rs) echo '<p style="color:var(--muted)">Sin rutinas aún.</p>'; ?></div>
<?php endif; ?>

<?php if($page==='tienda'): $prods=$pdo->query("SELECT * FROM productos ORDER BY id")->fetchAll(); ?>
<div class="grid4"><?php foreach($prods as $p): ?><div class="card"><span class="badge2 <?= $p['stock']>5?'ok':'bad' ?>">Stock: <?=$p['stock']?></span><h3><?=e($p['nombre'])?></h3><p><?=e($p['categoria'])?> · <?=e($p['descripcion'])?></p><div class="plan-price" style="font-size:1.4rem"><?=money($p['precio'])?></div>
<form method="post" style="display:flex;gap:.4rem;margin-top:.6rem"><?=csrf_field()?><input type="hidden" name="act" value="vender"><input type="hidden" name="producto_id" value="<?=$p['id']?>"><input name="cantidad" type="number" value="1" min="1" style="max-width:70px"><?php if($rol!=='cliente'): ?><input name="user_id" placeholder="ID cli" style="max-width:90px"><?php endif; ?><button class="btn btn-gold btn-sm">Vender</button></form></div><?php endforeach; ?></div>
<?php if($rol==='admin'): ?><div class="card" style="margin-top:1rem"><h3>Nuevo producto</h3><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="act" value="save_prod"><input type="hidden" name="id" value="0"><input name="nombre" required placeholder="Nombre"><input name="precio" type="number" required placeholder="Precio"><input name="stock" type="number" value="10"><input name="categoria" placeholder="Categoría"><input name="descripcion" placeholder="Descripción"><button class="btn btn-gold btn-sm">Guardar</button></form></div><?php endif; ?>
<?php endif; ?>

<?php if($page==='usuarios' && $rol==='admin'): ?>
<div class="card"><h3>Crear staff</h3><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="act" value="save_user"><input type="hidden" name="id" value="0"><input name="nombre" required placeholder="Nombre"><input name="email" type="email" required placeholder="Email"><input name="telefono" placeholder="Tel"><select name="rol"><option value="recepcionista">Recepcionista</option><option value="entrenador">Entrenador</option><option value="admin">Admin</option></select><select name="estado"><option value="activo">activo</option><option value="inactivo">inactivo</option></select><input name="pass" type="password" required placeholder="Clave 8+"><button class="btn btn-gold btn-sm">Crear</button></form></div>
<?php $us=$pdo->query("SELECT * FROM users WHERE rol!='cliente' ORDER BY id")->fetchAll(); ?>
<div class="table-wrap"><table class="table" style="margin-top:1rem"><tr><th>ID</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Estado</th><th>Eliminar</th></tr><?php foreach($us as $u): ?><tr><td><?=$u['id']?></td><td><?=e($u['nombre'])?></td><td><?=e($u['email'])?></td><td><?=e($u['rol'])?></td><td><?=e($u['estado'])?></td><td><form method="post" onsubmit="return confirm('¿Eliminar?')"><?=csrf_field()?><input type="hidden" name="act" value="del_user"><input type="hidden" name="id" value="<?=$u['id']?>"><button class="btn btn-ghost btn-sm">✕</button></form></td></tr><?php endforeach; ?></table></div>
<?php endif; ?>

<?php if($page==='mensajes'): $mj=$pdo->query("SELECT * FROM mensajes ORDER BY id DESC LIMIT 50")->fetchAll(); ?>
<div class="table-wrap"><table class="table"><tr><th>Nombre</th><th>Email</th><th>Mensaje</th><th>Fecha</th><th></th></tr><?php foreach($mj as $m): ?><tr><td><?=e($m['nombre'])?><br><small><?=e($m['telefono'])?></small></td><td><?=e($m['email'])?></td><td><?=e(substr($m['mensaje'],0,160))?></td><td><?=e($m['fecha'])?></td><td><?php if(!$m['leido']): ?><form method="post"><?=csrf_field()?><input type="hidden" name="act" value="marcar_leido"><input type="hidden" name="id" value="<?=$m['id']?>"><button class="btn btn-gold btn-sm">✓</button></form><?php else: ?><span class="badge2 ok">leído</span><?php endif; ?></td></tr><?php endforeach; ?></table></div>
<?php endif; ?>

<?php if($page==='reportes' && $rol==='admin'): ?>
<div class="grid3"><div class="card"><h3>Ingresos por método</h3><?php foreach($pdo->query("SELECT metodo, SUM(monto) t, COUNT(*) n FROM pagos GROUP BY metodo")->fetchAll() as $r): ?><div><?=e($r['metodo'])?>: <b><?=money($r['t'])?></b> (<?=$r['n']?>)</div><?php endforeach; ?></div>
<div class="card"><h3>Top clases</h3><?php foreach($pdo->query("SELECT c.nombre, COUNT(ci.id) n FROM clases c LEFT JOIN clase_inscripciones ci ON ci.clase_id=c.id GROUP BY c.id ORDER BY n DESC LIMIT 5")->fetchAll() as $r): ?><div><?=e($r['nombre'])?>: <b><?=$r['n']?></b></div><?php endforeach; ?></div>
<div class="card"><h3>Actividad reciente</h3><?php foreach($pdo->query("SELECT l.*,u.nombre FROM logs l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.id DESC LIMIT 8")->fetchAll() as $r): ?><div><small><?=e($r['fecha'])?> · <?=e($r['nombre']??'?')?> · <?=e($r['accion'])?></small></div><?php endforeach; ?></div></div>
<?php endif; ?>

<?php if($page==='perfil'): $me=$pdo->prepare("SELECT * FROM users WHERE id=?"); $me->execute([$user['id']]); $me=$me->fetch(); ?>
<div class="card" style="max-width:560px"><h3>Mi perfil</h3><form method="post"><?=csrf_field()?><input type="hidden" name="act" value="save_perfil">
<label>Nombre</label><input name="nombre" value="<?=e($me['nombre'])?>" required><label>Email (no editable)</label><input value="<?=e($me['email'])?>" disabled><label>Teléfono</label><input name="telefono" value="<?=e($me['telefono'])?>"><label>Nueva contraseña (opcional)</label><input name="pass" type="password" placeholder="Dejar vacío para no cambiar">
<button class="btn btn-gold" style="margin-top:.8rem">Guardar cambios</button></form></div>
<?php endif; ?>

<div class="creator"><span>✦ Sistema <b>AURUM FITNESS</b> · Creado por <b>Diana Trujillo</b> © 2026</span><span><small>🔒 Sesión protegida · CSRF · Cifrado bcrypt</small></span></div>
</main></div><script>function toggleSide(force){var s=document.getElementById("side"),o=document.getElementById("ovl");var open=force!==undefined?force:!s.classList.contains("open");s.classList.toggle("open",open);o.classList.toggle("show",open);}</script></body></html>
