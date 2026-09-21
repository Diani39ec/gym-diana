<?php require_once __DIR__.'/config.php';
if (currentUser()) redirect('dashboard.php');
$err=''; $planId=(int)($_GET['plan']??0);
if ($_SERVER['REQUEST_METHOD']==='POST') {
  verify_csrf();
  $nombre=trim(substr($_POST['nombre']??'',0,100)); $email=strtolower(trim($_POST['email']??''));
  $tel=trim(substr($_POST['telefono']??'',0,30)); $p1=$_POST['p1']??''; $p2=$_POST['p2']??'';
  $plan=(int)($_POST['plan_id']??0);
  if(strlen($nombre)<3) $err='Nombre muy corto.';
  elseif(!validateEmail($email)) $err='Email inválido.';
  elseif(strlen($p1)<8 || !preg_match('/[A-Z]/',$p1) || !preg_match('/[0-9]/',$p1) || !preg_match('/[^A-Za-z0-9]/',$p1)) $err='La contraseña debe tener 8+ caracteres, mayúscula, número y símbolo.';
  elseif($p1!==$p2) $err='Las contraseñas no coinciden.';
  else{
    try{
      $pdo=db();
      $ex=$pdo->prepare("SELECT id FROM users WHERE email=?"); $ex->execute([$email]);
      if($ex->fetch()) $err='Ese email ya está registrado. Intente ingresar.';
      else{
        $hash=password_hash($p1,PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (nombre,email,password,rol,telefono) VALUES (?,?,?,?,?)")->execute([$nombre,$email,$hash,'cliente',$tel]);
        $uid=(int)$pdo->lastInsertId();
        if($plan>0){ $pl=$pdo->prepare("SELECT * FROM planes WHERE id=?"); $pl->execute([$plan]); $pl=$pl->fetch();
          if($pl){ $ini=date('Y-m-d'); $fin=date('Y-m-d',strtotime('+'.$pl['duracion_dias'].' days'));
            $m=$pdo->prepare("INSERT INTO membresias (user_id,plan_id,fecha_inicio,fecha_fin,estado) VALUES (?,?,?,?,?)");
            $m->execute([$uid,$plan,$ini,$fin,'activa']); $mid=(int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO pagos (user_id,membresia_id,monto,metodo,concepto,registrado_por) VALUES (?,?,?,?,?,?)")->execute([$uid,$mid,$pl['precio'],'pendiente','Membresía '.$pl['nombre'],$uid]);
          }}
        logAction($uid,'registro web'); redirect('login.php?ok=1');
      }
    }catch(Throwable $t){ $err='Error al registrar. Intente de nuevo.'; }
  }
}
$planes=db()->query("SELECT * FROM planes WHERE estado='activo' ORDER BY precio")->fetchAll();
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Registro | <?=e(APP_NAME)?></title><link rel="stylesheet" href="assets/style.css">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Inter:wght@400;700&display=swap" rel="stylesheet"></head>
<body><div class="auth-wrap"><div class="auth-card">
<div style="text-align:center"><a class="brand" style="justify-content:center" href="index.php"><span class="brand-mark">A</span><span style="text-align:left"><b>AURUM FITNESS</b><small>by Diana Trujillo</small></span></a></div>
<div class="form"><h1>Únete al club</h1><p style="color:var(--muted)">7 días gratis · Sin permanencia en plan mensual · Datos protegidos.</p>
<?php if($err) echo '<div class="alert alert-err">'.e($err).'</div>'; ?>
<form method="post"><?=csrf_field()?>
<label>Nombre completo</label><input name="nombre" required>
<label>Email</label><input name="email" type="email" required>
<label>WhatsApp</label><input name="telefono" placeholder="300 000 0000">
<label>Elige tu plan</label><select name="plan_id"><?php foreach($planes as $p): ?><option value="<?=$p['id']?>" <?= $planId==$p['id']?'selected':'' ?>><?=e($p['nombre'])?> - <?=money($p['precio'])?></option><?php endforeach; ?></select>
<label>Contraseña segura</label><input name="p1" type="password" required placeholder="Mín. 8 + Mayús + número + símbolo">
<label>Confirmar contraseña</label><input name="p2" type="password" required>
<button class="btn btn-gold" style="width:100%;margin-top:1.1rem">Crear mi cuenta</button></form>
<p style="text-align:center;color:var(--muted);font-size:.9rem">¿Ya eres miembro? <a href="login.php" style="color:var(--gold2)">Ingresar</a></p></div>
<p style="text-align:center;color:var(--muted)"><small>© 2026 Creado por Diana Trujillo · Protección de datos garantizada</small></p>
</div></div></body></html>
