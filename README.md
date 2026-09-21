# AURUM FITNESS CLUB — Sistema Profesional para Gimnasios
**Creado por Diana Trujillo © 2026 — v2.0.0**

Sistema elegante (negro & dorado) para atraer clientes + panel administrativo completo y seguro.

## Acceso
- URL: http://localhost/gym-diana/
- Admin: admin@gym.com / Admin123*
- Entrenador: entrenador@gym.com / Entrenador123*
- Recepción: recepcion@gym.com / Recepcion123*
- Cliente: cliente@gym.com / Cliente123*

> Cambie las claves en Mi perfil al entrar.

## Módulos
Landing premium, login/registro, dashboard por rol, miembros, planes, membresías, pagos, asistencia check-in/out, clases e inscripciones, rutinas, tienda + stock + ventas, usuarios staff, mensajes web, reportes, perfil, logs de auditoría.

## Seguridad implementada
- password_hash bcrypt + bloqueo por 5 intentos (15 min)
- PDO prepared statements (anti SQLi), htmlspecialchars (anti XSS)
- CSRF tokens en todos los formularios, session endurecida (httponly, strict, SameSite, timeout 30 min, regenerate)
- Headers: CSP, X-Frame-Options, nosniff, Referrer-Policy
- Validación de email/roles/estados, control de acceso por rol (403), .htaccess protege config y DB
- SQLite auto-creada en database/gym.db (sin configurar MySQL)

## Instalación
1. Copie la carpeta `gym-diana` a `C:\laragon\www\`
2. Abra http://localhost/gym-diana/
3. Listo. Para producción cambie a HTTPS y claves fuertes.
