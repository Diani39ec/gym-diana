<?php require_once __DIR__.'/config.php';
if (currentUser()) redirect('dashboard.php');
$err='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  verify_csrf();
  $email=strtolower(trim($_POST['email']??'')); $pass=$_POST['password']??'';
  if(!validateEmail($email)||!$pass){ $err='Ingrese email y contraseña válidos.'; }
  else{
    $pdo=db(); $st=$pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1"); $st->execute([$email]); $u=$st->fetch();
    if($u && (int)$u['bloqueado_hasta']>time()){ $err='Cuenta bloqueada temporalmente por intentos fallidos. Intente en '.ceil(((int)$u['bloqueado_hasta']-time())/60).' min.'; }
    elseif($u && password_verify($pass,$u['password'])){
      if($u['estado']!=='activo'){ $err='Cuenta inactiva o suspendida. Contacte recepción.'; }
      else{
        $pdo->prepare("UPDATE users SET intentos=0,bloqueado_hasta=0,ultimo_acceso=datetime('now') WHERE id=?")->execute([$u['id']]);
        session_regenerate_id(true); $_SESSION['uid']=$u['id']; $_SESSION['rol']=$u['rol'];
        logAction($u['id'],'login ok'); redirect('dashboard.php');
      }
    } else {
      if($u){ $int=(int)$u['intentos']+1; $bloq=$int>=5?time()+900:0;
        $pdo->prepare("UPDATE users SET intentos=?,bloqueado_hasta=? WHERE id=?")->execute([$int,$bloq,$u['id']]);
        logAction($u['id'],'login fallido'); }
      sleep(1); $err='Credenciales incorrectas. Intento registrado por seguridad.';
    }
  }
}
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Ingresar | <?=e(APP_NAME)?></title><link rel="stylesheet" href="assets/style.css">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Inter:wght@400;700&display=swap" rel="stylesheet"></head>
<body><div class="auth-wrap"><div class="auth-card">
<div style="text-align:center;margin-bottom:1rem"><a class="brand" style="justify-content:center" href="index.php"><span class="brand-mark">A</span><span style="text-align:left"><b>AURUM FITNESS</b><small>by Diana Trujillo</small></span></a></div>
<div class="form"><h1>Bienvenido de nuevo</h1><p style="color:var(--muted)">Acceso seguro con cifrado y protección anti-fuerza bruta.</p>
<?php if($err) echo '<div class="alert alert-err">'.e($err).'</div>'; if(isset($_GET['ok'])) echo '<div class="alert alert-ok">Cuenta creada. Ingrese ahora.</div>'; if(isset($_GET['err'])&&$_GET['err']=='inactivo') echo '<div class="alert alert-err">Cuenta inactiva.</div>'; ?>
<form method="post"><?=csrf_field()?>
<label>Email</label><input name="email" type="email" required autocomplete="username" placeholder="tu@email.com">
<label>Contraseña</label><input name="password" type="password" required autocomplete="current-password" placeholder="••••••••">
<button class="btn btn-gold" style="width:100%;margin-top:1.2rem">Ingresar al panel</button></form>
<p style="text-align:center;color:var(--muted);font-size:.9rem">¿Sin cuenta? <a href="registro.php" style="color:var(--gold2)">Regístrate gratis</a> · <a href="index.php" style="color:var(--muted)">← Volver</a></p>
<div class="card" style="margin-top:1rem;font-size:.8rem;color:var(--muted)">🔐 <b>Demo:</b> admin@gym.com / Admin123* · cliente@gym.com / Cliente123* · entrenador@gym.com / Entrenador123* · recepcion@gym.com / Recepcion123*</div>
</div><p style="text-align:center;color:var(--muted)"><small>© 2026 Creado por Diana Trujillo</small></p></div></div></body></html>
