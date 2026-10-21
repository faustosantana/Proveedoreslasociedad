<?php

/**
 * -----------------------------------------------
 * Proyecto: Plataforma de Suplidores - PilarDevs
 * Autor: Chadwin Pilar
 * Fecha: 2025-09-18
 * Uso interno – No distribuir sin autorización
 * -----------------------------------------------
 */

require_once __DIR__ . '/../rutas_amigables.php';
require_once __DIR__ . '/../Base_de_datos/config.php';

// En entorno local el reCAPTCHA se omite (no requiere conexión a Google).
// En producción (APP_ENV=production) se activa automáticamente con las claves del .env.
$es_local      = env('APP_ENV', 'local') === 'local';
$recaptcha_site_key = $es_local ? '' : env('RECAPTCHA_SITE_KEY', '');
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <title>Iniciar Sesión — Plataforma de Suplidores</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <?php if (!$es_local): ?>
  <script src="https://www.google.com/recaptcha/api.js?render=<?= htmlspecialchars($recaptcha_site_key) ?>"></script>
  <?php endif; ?>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Inter', sans-serif;
      min-height: 100vh;
      display: flex;
      background: #f0eef8;
    }

    /* ── LEFT PANEL ── */
    .brand-panel {
      flex: 1;
      background: linear-gradient(160deg, #24125f 0%, #3b1f8c 55%, #1a0d44 100%);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 60px 48px;
      position: relative;
      overflow: hidden;
    }

    .brand-panel::before {
      content: '';
      position: absolute;
      width: 420px;
      height: 420px;
      border-radius: 50%;
      background: rgba(241, 230, 178, 0.07);
      top: -100px;
      right: -100px;
    }

    .brand-panel::after {
      content: '';
      position: absolute;
      width: 280px;
      height: 280px;
      border-radius: 50%;
      background: rgba(241, 230, 178, 0.05);
      bottom: -60px;
      left: -60px;
    }

    .brand-logo {
      width: 140px;
      height: 140px;
      object-fit: contain;
      border-radius: 20px;
      background: rgba(255,255,255,0.08);
      padding: 12px;
      margin-bottom: 28px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.25);
      position: relative;
      z-index: 1;
      animation: logoPulse 3.5s ease-in-out infinite, fadeSlideUp 0.7s 0.1s both;
    }

    .brand-title {
      color: #f1e6b2;
      font-size: 1.6rem;
      font-weight: 700;
      text-align: center;
      margin-bottom: 10px;
      position: relative;
      z-index: 1;
      letter-spacing: 0.3px;
    }

    .brand-subtitle {
      color: rgba(241,230,178,0.65);
      font-size: 0.92rem;
      text-align: center;
      line-height: 1.6;
      max-width: 280px;
      position: relative;
      z-index: 1;
    }

    .brand-divider {
      width: 48px;
      height: 3px;
      background: #f1e6b2;
      border-radius: 2px;
      margin: 18px auto;
      opacity: 0.5;
      position: relative;
      z-index: 1;
    }

    .brand-badges {
      display: flex;
      gap: 12px;
      margin-top: 36px;
      position: relative;
      z-index: 1;
    }

    .badge {
      display: flex;
      align-items: center;
      gap: 7px;
      background: rgba(241,230,178,0.1);
      border: 1px solid rgba(241,230,178,0.2);
      border-radius: 20px;
      padding: 6px 14px;
      font-size: 0.78rem;
      color: rgba(241,230,178,0.8);
    }

    .badge i { font-size: 0.8rem; color: #f1e6b2; }

    /* ── RIGHT PANEL ── */
    .form-panel {
      width: 460px;
      min-width: 320px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 60px 48px;
      background: #fff;
    }

    .form-inner {
      width: 100%;
      max-width: 360px;
    }

    .form-header {
      margin-bottom: 36px;
    }

    .form-header h1 {
      font-size: 1.6rem;
      font-weight: 700;
      color: #1a0d44;
      margin-bottom: 6px;
    }

    .form-header p {
      font-size: 0.9rem;
      color: #6b7280;
    }

    .field {
      margin-bottom: 20px;
    }

    .field label {
      display: block;
      font-size: 0.83rem;
      font-weight: 600;
      color: #374151;
      margin-bottom: 6px;
      letter-spacing: 0.3px;
    }

    .field-wrap {
      position: relative;
      display: flex;
      align-items: center;
    }

    .field-wrap .field-icon {
      position: absolute;
      left: 14px;
      color: #9ca3af;
      font-size: 0.95rem;
      pointer-events: none;
      transition: color 0.2s;
      z-index: 1;
    }

    .field-wrap input {
      width: 100%;
      padding: 12px 44px 12px 40px;
      border: 1.5px solid #e5e7eb;
      border-radius: 10px;
      font-size: 0.95rem;
      font-family: 'Inter', sans-serif;
      color: #111827;
      background: #f9fafb;
      transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    }

    .field-wrap input:focus {
      outline: none;
      border-color: #24125f;
      background: #fff;
      box-shadow: 0 0 0 3px rgba(36,18,95,0.08);
    }

    .field-wrap input:focus ~ .field-icon {
      color: #24125f;
    }

    .field-wrap .toggle-pwd {
      position: absolute;
      right: 12px;
      background: none;
      border: none;
      cursor: pointer;
      padding: 4px;
      color: #9ca3af;
      font-size: 0.9rem;
      transition: color 0.2s;
      width: auto;
      margin-top: 0;
      line-height: 1;
    }

    .field-wrap .toggle-pwd:hover { color: #24125f; }
    .field-wrap .toggle-pwd:focus { outline: none; }

    .btn-login {
      width: 100%;
      padding: 13px;
      background: #24125f;
      color: #f1e6b2;
      border: none;
      border-radius: 10px;
      font-size: 1rem;
      font-weight: 600;
      font-family: 'Inter', sans-serif;
      cursor: pointer;
      transition: background 0.2s, box-shadow 0.2s, transform 0.15s;
      margin-top: 8px;
      letter-spacing: 0.3px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .btn-login:hover {
      background: #3b1f8c;
      box-shadow: 0 6px 20px rgba(36,18,95,0.25);
      transform: translateY(-1px);
    }

    .btn-login:active { transform: translateY(0); }

    .btn-login .spinner {
      display: none;
      width: 16px;
      height: 16px;
      border: 2px solid rgba(241,230,178,0.4);
      border-top-color: #f1e6b2;
      border-radius: 50%;
      animation: spin 0.7s linear infinite;
    }

    .btn-login.loading .spinner { display: block; }
    .btn-login.loading .btn-text { opacity: 0.7; }

    @keyframes spin { to { transform: rotate(360deg); } }

    .form-footer {
      margin-top: 32px;
      text-align: center;
      font-size: 0.8rem;
      color: #9ca3af;
    }

    .form-footer a {
      color: #24125f;
      text-decoration: none;
      font-weight: 500;
    }

    .form-footer a:hover { text-decoration: underline; }

    /* ── RESPONSIVE ── */
    @media (max-width: 768px) {
      body { flex-direction: column; }

      .brand-panel {
        flex: none;
        padding: 40px 24px 32px;
      }

      .brand-logo { width: 90px; height: 90px; margin-bottom: 16px; }
      .brand-title { font-size: 1.2rem; }
      .brand-badges { display: none; }

      .form-panel {
        width: 100%;
        padding: 40px 24px;
        flex: 1;
      }
    }

    @media (max-width: 480px) {
      .form-panel { padding: 32px 20px; }
    }

    /* ── FADE IN ── */
    .form-panel { animation: slideIn 0.5s cubic-bezier(.22,.68,0,1.2); }

    @keyframes slideIn {
      from { opacity: 0; transform: translateX(24px); }
      to   { opacity: 1; transform: translateX(0); }
    }

    /* ── PARTICLES ── */
    .particles {
      position: absolute;
      inset: 0;
      overflow: hidden;
      pointer-events: none;
      z-index: 0;
    }

    .p {
      position: absolute;
      border-radius: 50%;
      animation: floatRise linear infinite;
      opacity: 0;
    }

    @keyframes floatRise {
      0%   { transform: translateY(0)       translateX(0)              scale(0.6); opacity: 0; }
      12%  { opacity: 1; }
      88%  { opacity: 0.5; }
      100% { transform: translateY(-110vh) translateX(var(--drift,0px)) scale(0.9); opacity: 0; }
    }

    @keyframes logoPulse {
      0%, 100% { box-shadow: 0 8px 32px rgba(0,0,0,.25), 0 0 0 0   rgba(241,230,178,.4); }
      50%       { box-shadow: 0 8px 32px rgba(0,0,0,.25), 0 0 0 18px rgba(241,230,178,0); }
    }

    @keyframes fadeSlideUp {
      from { opacity: 0; transform: translateY(20px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .brand-title    { animation: fadeSlideUp 0.7s 0.25s both; }
    .brand-divider  { animation: fadeSlideUp 0.6s 0.40s both; }
    .brand-subtitle { animation: fadeSlideUp 0.7s 0.50s both; }
    .brand-badges   { animation: fadeSlideUp 0.7s 0.65s both; }
  </style>
</head>

<body>

  <!-- Brand side -->
  <div class="brand-panel">

    <!-- Floating particles -->
    <div class="particles" aria-hidden="true">
      <!-- small solid dots -->
      <span class="p" style="left:7%;bottom:-20px;width:5px;height:5px;background:rgba(241,230,178,.75);animation-duration:11s;animation-delay:0s;--drift:18px;"></span>
      <span class="p" style="left:20%;bottom:-30px;width:4px;height:4px;background:rgba(255,255,255,.6);animation-duration:9s;animation-delay:2.5s;--drift:-12px;"></span>
      <span class="p" style="left:38%;bottom:-10px;width:6px;height:6px;background:rgba(241,230,178,.65);animation-duration:14s;animation-delay:0.8s;--drift:10px;"></span>
      <span class="p" style="left:62%;bottom:-25px;width:5px;height:5px;background:rgba(255,255,255,.55);animation-duration:10s;animation-delay:4s;--drift:-22px;"></span>
      <span class="p" style="left:85%;bottom:-15px;width:4px;height:4px;background:rgba(241,230,178,.7);animation-duration:12s;animation-delay:1.5s;--drift:8px;"></span>
      <span class="p" style="left:52%;bottom:-12px;width:5px;height:5px;background:rgba(255,255,255,.5);animation-duration:8s;animation-delay:6s;--drift:15px;"></span>
      <!-- medium outline circles -->
      <span class="p" style="left:14%;bottom:-40px;width:18px;height:18px;background:rgba(241,230,178,.07);border:1px solid rgba(241,230,178,.25);animation-duration:19s;animation-delay:3s;--drift:22px;"></span>
      <span class="p" style="left:48%;bottom:-40px;width:22px;height:22px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.18);animation-duration:23s;animation-delay:1s;--drift:-18px;"></span>
      <span class="p" style="left:75%;bottom:-35px;width:15px;height:15px;background:rgba(241,230,178,.09);border:1px solid rgba(241,230,178,.2);animation-duration:17s;animation-delay:5.5s;--drift:14px;"></span>
      <span class="p" style="left:32%;bottom:-45px;width:20px;height:20px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.14);animation-duration:21s;animation-delay:7s;--drift:-16px;"></span>
      <span class="p" style="left:91%;bottom:-40px;width:13px;height:13px;background:rgba(241,230,178,.06);border:1px solid rgba(241,230,178,.22);animation-duration:16s;animation-delay:6s;--drift:-28px;"></span>
      <!-- large blurry orbs -->
      <span class="p" style="left:22%;bottom:-70px;width:50px;height:50px;background:rgba(241,230,178,.04);filter:blur(5px);animation-duration:27s;animation-delay:2s;--drift:35px;"></span>
      <span class="p" style="left:65%;bottom:-70px;width:38px;height:38px;background:rgba(255,255,255,.03);filter:blur(3px);animation-duration:31s;animation-delay:9s;--drift:-28px;"></span>
      <span class="p" style="left:5%;bottom:-80px;width:60px;height:60px;background:rgba(59,31,140,.25);filter:blur(6px);animation-duration:22s;animation-delay:4s;--drift:20px;"></span>
    </div>

    <img src="/SystemSuplidor/img/logo.jpeg" alt="Logo La Sociedad" class="brand-logo" />
    <div class="brand-title">Plataforma de Proveedores</div>
    <div class="brand-divider"></div>
    <div class="brand-subtitle">Gestión de proveedores, facturas y pagos en un solo lugar</div>
    <div class="brand-badges">
      <span class="badge"><i class="fas fa-shield-alt"></i> Seguro</span>
      <span class="badge"><i class="fas fa-bolt"></i> Rápido</span>
      <span class="badge"><i class="fas fa-lock"></i> Privado</span>
    </div>
  </div>

  <!-- Form side -->
  <div class="form-panel">
    <div class="form-inner">
      <div class="form-header">
        <h1>Bienvenido</h1>
        <p>Ingresa tus credenciales para continuar</p>
      </div>

      <form method="POST" action="/SystemSuplidor/router.php<?= $RUTA_PROCESAR_LOGIN ?>" id="loginForm" autocomplete="on" accept-charset="UTF-8">

        <div class="field">
          <label for="usuario">Usuario o Correo</label>
          <div class="field-wrap">
            <input type="text" id="usuario" name="usuario" placeholder="Ej: admin@empresa.com" required autocomplete="username" />
            <span class="field-icon"><i class="fas fa-user"></i></span>
          </div>
        </div>

        <div class="field">
          <label for="contrasena">Contraseña</label>
          <div class="field-wrap">
            <input type="password" id="contrasena" name="contrasena" placeholder="••••••••" required autocomplete="current-password" />
            <span class="field-icon"><i class="fas fa-lock"></i></span>
            <button type="button" class="toggle-pwd" tabindex="-1" aria-label="Mostrar contraseña" onclick="togglePassword()">
              <i class="fas fa-eye" id="pwdIcon"></i>
            </button>
          </div>
        </div>

        <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">

        <button type="submit" class="btn-login" id="btnLogin">
          <div class="spinner"></div>
          <span class="btn-text"><i class="fas fa-sign-in-alt"></i> Ingresar</span>
        </button>
      </form>

      <div class="form-footer">
        <a href="javascript:void(0);" onclick="abrirModalRecuperar()" style="color: #24125f; text-decoration: none; font-weight: 500; display: block; margin-bottom: 12px;">
          <i class="fas fa-key"></i> ¿Olvidaste tu contraseña?
        </a>
        ¿Problemas para ingresar? <a href="mailto:portalproveedores@lasociedad.com.do">Contactar soporte</a>
      </div>
    </div>
  </div>

  <script>
    function togglePassword() {
      const input = document.getElementById('contrasena');
      const icon  = document.getElementById('pwdIcon');
      if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
      } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
      }
    }

    document.getElementById('loginForm').addEventListener('submit', function(e) {
      e.preventDefault();
      const btn = document.getElementById('btnLogin');
      btn.classList.add('loading');
      btn.disabled = true;

      <?php if ($es_local): ?>
      // LOCAL: reCAPTCHA desactivado — el token queda vacío y el servidor lo omite
      document.getElementById('loginForm').submit();
      <?php else: ?>
      var submitted = false;
      function submitLogin() {
        if (submitted) return;
        submitted = true;
        document.getElementById('loginForm').submit();
      }
      if (typeof grecaptcha === 'undefined' || !grecaptcha.ready) {
        submitLogin();
      } else {
        var recaptchaTimer = setTimeout(submitLogin, 4000);
        grecaptcha.ready(function() {
          grecaptcha.execute('<?= htmlspecialchars($recaptcha_site_key) ?>', {action: 'login'})
            .then(function(token) {
              clearTimeout(recaptchaTimer);
              document.getElementById('g-recaptcha-response').value = token;
              submitLogin();
            })
            .catch(function() {
              clearTimeout(recaptchaTimer);
              submitLogin();
            });
        });
      }
      <?php endif; ?>
    });
  </script>

  <script>
    // Ruta base para peticiones AJAX de recuperación de contraseña
    const BASE_ADMIN_PATH = '<?php echo getBaseUrl(); ?>/PanelAdmin/';
    
    // Rutas amigables para recuperación de contraseña (sin revelar nombres de archivos)
    <?php require_once __DIR__ . '/../rutas_amigables.php'; ?>
    const AUTH_REQUEST_CODE_URL = '<?php echo getBaseUrl() . $RUTA_AUTH_REQUEST_CODE; ?>';
    const AUTH_VERIFY_CODE_URL = '<?php echo getBaseUrl() . $RUTA_AUTH_VERIFY_CODE; ?>';
  </script>

  <?php include __DIR__ . '/modal/modalRecuperarPassword.php'; ?>

</body>
</html>