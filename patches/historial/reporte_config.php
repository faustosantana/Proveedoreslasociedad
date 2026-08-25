<?php
session_start();
require_once __DIR__ . "/../Base_de_datos/config.php";

$tipo_usuario = $_SESSION['tipo'] ?? null;
if (!$tipo_usuario) {
    include __DIR__ . "/../plantillas/no_autorizado.php";
    exit;
}

// Obtener empresas para filtro
$empresas = $pdo->query("SELECT id_empresa, nombre FROM empresas ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

// Obtener monedas disponibles
$stmt_monedas = $pdo->prepare("SELECT DISTINCT TRIM(UPPER(moneda)) AS moneda FROM facturas WHERE moneda IS NOT NULL AND moneda != '' ORDER BY moneda");
$stmt_monedas->execute();
$monedas = $stmt_monedas->fetchAll(PDO::FETCH_ASSOC);

// Campos posibles
$campos_disponibles = [
    'ncf' => 'NCF',
    'proveedor' => 'Proveedor',
    'empresa_emisora' => 'Empresa',
    'fecha_digital' => 'Fecha',
    'producto' => 'Descripción',
    'total' => 'Total',
    'total_pagado' => 'Pagado',
    'pendiente' => 'Pendiente',
    'es_credito' => 'Condición',
    'estado' => 'Estado',
    'moneda' => 'Moneda'
];

$selected_campos = $_GET['campos'] ?? array_keys($campos_disponibles);
if (!is_array($selected_campos)) {
    $selected_campos = [$selected_campos];
}

$selected_fecha_inicio = $_GET['fecha_inicio'] ?? '';
$selected_fecha_fin = $_GET['fecha_fin'] ?? '';
$selected_estado = $_GET['estado'] ?? '';
$selected_empresa = $_GET['empresa'] ?? '';
$selected_moneda = $_GET['moneda'] ?? '';
$selected_limit = $_GET['limit'] ?? '25';
$selected_orden = $_GET['orden'] ?? 'f.id_factura DESC';
$selected_encabezados = isset($_GET['incluir_encabezados']) ? true : true;

$validOrdenes = [
    'f.id_factura DESC' => 'Más recientes',
    'f.id_factura ASC' => 'Más antiguos',
    'f.total DESC' => 'Mayor total',
    'f.total ASC' => 'Menor total'
];

if (!array_key_exists($selected_orden, $validOrdenes)) {
    $selected_orden = 'f.id_factura DESC';
}

// Detectar si PhpSpreadsheet está instalado
$hasPhpSpreadsheet = file_exists(__DIR__ . '/../vendor/autoload.php');
if ($hasPhpSpreadsheet) {
    require_once __DIR__ . '/../vendor/autoload.php';
    $hasPhpSpreadsheet = class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet');
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Generar Reporte de Facturas</title>
  <link rel="stylesheet" href="/SystemSuplidor/css/historial.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      background: #eef1f6;
      font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, sans-serif;
      min-height: 100vh;
      color: #333;
    }

    .container {
      width: 100%;
      max-width: none;
      margin: 0;
      background: transparent;
      border-radius: 0;
      box-shadow: none;
      overflow: visible;
      padding: 0;
      display: flex;
      flex-direction: column;
    }

    .header {
      background: linear-gradient(135deg, #232946 0%, #1a1f3a 100%);
      color: white;
      padding: 28px 40px;
      text-align: center;
      width: 100%;
    }

    .header h1 {
      font-size: 24px;
      font-weight: 700;
      margin-bottom: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
    }

    .header p {
      font-size: 13px;
      opacity: 0.9;
      font-weight: 500;
    }

    .steps {
      display: flex;
      justify-content: flex-start;
      padding: 16px 40px;
      background: #fff;
      border-bottom: 1px solid #e2e8f0;
      width: 100%;
      overflow-x: auto;
      gap: 0;
    }

    .step {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 12px;
      color: #64748b;
      cursor: pointer;
      transition: all 0.3s ease;
      position: relative;
      flex: 0 0 auto;
      padding: 8px 0;
      margin-right: 30px;
    }

    .step:last-child {
      margin-right: 0;
    }

    .step:not(:last-child)::after {
      content: '';
      position: absolute;
      right: -16px;
      top: 50%;
      transform: translateY(-50%);
      width: 16px;
      height: 2px;
      background: #cbd5e1;
    }

    .step.active::after {
      background: #5b8db8;
    }

    .step-number {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background: #e2e8f0;
      font-weight: 600;
      color: #475569;
      font-size: 11px;
      transition: all 0.3s ease;
    }

    .step.active .step-number {
      background: #5b8db8;
      color: white;
    }

    .step.completed .step-number {
      background: #10b981;
      color: white;
    }

    .step.completed::after {
      background: #10b981;
    }

    .step-label {
      font-weight: 500;
      color: #334155;
      white-space: nowrap;
    }

    .step.active .step-label {
      color: #5b8db8;
      font-weight: 600;
    }

    .content {
      padding: 32px 40px;
      min-height: calc(100vh - 280px);
      width: 100%;
      background: #fff;
      flex: 1;
    }

    .section {
      display: none;
      max-width: 1200px;
      margin: 0 auto;
    }

    .section.active {
      display: block;
      animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .section-title {
      font-size: 18px;
      font-weight: 600;
      color: #232946;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .section-title i {
      color: #5b8db8;
      font-size: 22px;
    }

    .section > p {
      color: #64748b;
      margin-bottom: 20px;
      font-size: 14px;
    }

    .grid-2 {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 24px;
      margin-bottom: 28px;
    }

    .grid-full {
      display: grid;
      grid-template-columns: 1fr;
      gap: 24px;
      margin-bottom: 28px;
    }

    @media (max-width: 1024px) {
      .grid-2 { grid-template-columns: 1fr; }
      .content { padding: 24px 30px; }
      .header { padding: 24px 30px; }
      .steps { padding: 12px 30px; }
    }

    @media (max-width: 768px) {
      .header h1 { font-size: 20px; }
      .steps { padding: 12px 16px; }
      .step { margin-right: 20px; }
      .step:not(:last-child)::after { right: -12px; width: 12px; }
      .content { padding: 20px 16px; }
    }

    .form-group {
      display: flex;
      flex-direction: column;
    }

    .form-group label {
      font-weight: 500;
      color: #475569;
      margin-bottom: 8px;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .form-group input,
    .form-group select {
      padding: 10px 12px;
      border: 1px solid #cbd5e1;
      border-radius: 6px;
      font-size: 14px;
      font-family: inherit;
      transition: all 0.2s ease;
      background: #fff;
      color: #333;
    }

    .form-group input:focus,
    .form-group select:focus {
      outline: none;
      border-color: #5b8db8;
      box-shadow: 0 0 0 3px rgba(91, 141, 184, 0.1);
      background: #fff;
    }

    .checkbox-group {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 12px;
      margin-bottom: 24px;
    }

    @media (max-width: 768px) {
      .checkbox-group { grid-template-columns: 1fr; }
    }

    .checkbox-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 12px;
      border-radius: 6px;
      background: #f8fafc;
      border: 1px solid transparent;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .checkbox-item:hover {
      background: #ecf0f5;
      border-color: #d4d9e3;
    }

    .checkbox-item input[type="checkbox"] {
      width: 18px;
      height: 18px;
      cursor: pointer;
      accent-color: #5b8db8;
    }

    .checkbox-item label {
      margin: 0;
      cursor: pointer;
      font-weight: 400;
      text-transform: none;
      letter-spacing: normal;
      color: #333;
      font-size: 14px;
    }

    .summary-card {
      background: #f1f5f9;
      border: 1px solid #d4d9e3;
      border-radius: 8px;
      padding: 16px;
      margin-bottom: 20px;
    }

    .summary-item {
      display: flex;
      justify-content: space-between;
      padding: 8px 0;
      border-bottom: 1px solid rgba(212, 217, 227, 0.5);
      font-size: 13px;
    }

    .summary-item:last-child {
      border-bottom: none;
    }

    .summary-label {
      color: #1a1f3a;
      font-weight: 500;
    }

    .summary-value {
      color: #5b8db8;
      font-weight: 600;
    }

    .preview-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 12px;
      max-height: 400px;
      overflow-y: auto;
      border: 1px solid #d4d9e3;
      border-radius: 6px;
      margin-bottom: 20px;
      background: #fff;
    }

    .preview-table thead {
      background: linear-gradient(to right, #f1f5f9 0%, #ecf0f5 100%);
      position: sticky;
      top: 0;
    }

    .preview-table th {
      padding: 12px;
      text-align: left;
      font-weight: 600;
      color: #232946;
      border-bottom: 2px solid #cbd5e1;
    }

    .preview-table td {
      padding: 10px 12px;
      border-bottom: 1px solid #f1f5f9;
      color: #333;
    }

    .preview-table tbody tr:hover {
      background: #f8fafc;
    }

    .preview-stats {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      margin-top: 16px;
    }

    .stat-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 12px;
      background: #e2eaf6;
      color: #1a1f3a;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 600;
    }

    .stat-badge.warning {
      background: #fdebd0;
      color: #7d3c0a;
    }

    .stat-badge.success {
      background: #d5f4e6;
      color: #186a3b;
    }

    .format-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 10px 16px;
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: white;
      border-radius: 6px;
      font-weight: 600;
      font-size: 13px;
      margin-bottom: 16px;
    }

    .format-badge i {
      font-size: 16px;
    }

    .footer {
      padding: 24px 40px;
      background: #f1f5f9;
      border-top: 1px solid #e2e8f0;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
      width: 100%;
    }

    @media (max-width: 768px) {
      .footer {
        padding: 16px;
        flex-direction: column;
        align-items: stretch;
      }
    }

    .btn {
      padding: 10px 20px;
      border: none;
      border-radius: 6px;
      font-weight: 600;
      cursor: pointer;
      font-size: 13px;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .btn-primary {
      background: linear-gradient(135deg, #5b8db8 0%, #232946 100%);
      color: white;
    }

    .btn-primary:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(91, 141, 184, 0.3);
    }

    .btn-secondary {
      background: #e2e8f0;
      color: #334155;
      border: 1px solid #cbd5e1;
    }

    .btn-secondary:hover {
      background: #cbd5e1;
    }

    .btn-link {
      background: none;
      color: #5b8db8;
      text-decoration: none;
      padding: 8px 0;
    }

    .btn-link:hover {
      text-decoration: underline;
    }

    .empty-state {
      text-align: center;
      padding: 40px;
      color: #64748b;
    }

    .empty-state i {
      font-size: 48px;
      color: #cbd5e1;
      margin-bottom: 16px;
    }

    .loading {
      display: inline-block;
      width: 16px;
      height: 16px;
      border: 2px solid #cbd5e1;
      border-radius: 50%;
      border-top-color: #5b8db8;
      animation: spin 0.6s linear infinite;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    .hidden { display: none !important; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>
        <i class="fas fa-file-export"></i>
        Generar Reporte de Facturas
      </h1>
      <p>Configura los campos, filtros y opciones para descargar tu reporte personalizado</p>
    </div>

    <div class="steps">
      <div class="step active" data-step="1">
        <div class="step-number">1</div>
        <div class="step-label">Campos</div>
      </div>
      <div class="step" data-step="2">
        <div class="step-number">2</div>
        <div class="step-label">Filtros</div>
      </div>
      <div class="step" data-step="3">
        <div class="step-number">3</div>
        <div class="step-label">Opciones</div>
      </div>
      <div class="step" data-step="4">
        <div class="step-number">4</div>
        <div class="step-label">Previsualización</div>
      </div>
    </div>

    <form id="reportForm">
      <div class="content">
        <!-- PASO 1: CAMPOS -->
        <div class="section active" id="section-1">
          <div class="section-title">
            <i class="fas fa-columns"></i>
            Selecciona los campos a incluir
          </div>
          <p style="color: #64748b; margin-bottom: 20px; font-size: 14px;">Elige qué información deseas que aparezca en tu reporte. Todos están seleccionados por defecto.</p>
          <div class="checkbox-group">
            <?php foreach ($campos_disponibles as $key => $label): ?>
              <div class="checkbox-item">
                <input type="checkbox" id="campo_<?= htmlspecialchars($key) ?>" name="campos[]" value="<?= htmlspecialchars($key) ?>" <?= in_array($key, $selected_campos, true) ? 'checked' : '' ?>>
                <label for="campo_<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- PASO 2: FILTROS -->
        <div class="section" id="section-2">
          <div class="section-title">
            <i class="fas fa-funnel"></i>
            Aplica filtros (Opcional)
          </div>
          <p style="color: #64748b; margin-bottom: 20px; font-size: 14px;">Define rangos de fechas, estados y otras condiciones. Deja en blanco para incluir todos los registros.</p>
          
          <div class="grid-2">
            <div class="form-group">
              <label for="fecha_inicio"><i class="fas fa-calendar"></i> Fecha Inicio</label>
              <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?= htmlspecialchars($selected_fecha_inicio) ?>">
            </div>
            <div class="form-group">
              <label for="fecha_fin"><i class="fas fa-calendar"></i> Fecha Fin</label>
              <input type="date" id="fecha_fin" name="fecha_fin" value="<?= htmlspecialchars($selected_fecha_fin) ?>">
            </div>
          </div>

          <div class="grid-2">
            <div class="form-group">
              <label for="estado"><i class="fas fa-flag"></i> Estado</label>
              <select id="estado" name="estado">
                <option value="">Todos los estados</option>
                <option value="pendiente" <?= $selected_estado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                <option value="aceptada" <?= $selected_estado === 'aceptada' ? 'selected' : '' ?>>Aceptada</option>
                <option value="pagada" <?= $selected_estado === 'pagada' ? 'selected' : '' ?>>Pagada</option>
                <option value="rechazada" <?= $selected_estado === 'rechazada' ? 'selected' : '' ?>>Rechazada</option>
              </select>
            </div>
            <div class="form-group">
              <label for="empresa"><i class="fas fa-building"></i> Empresa</label>
              <select id="empresa" name="empresa">
                <option value="">Todas las empresas</option>
                <?php foreach ($empresas as $e): ?>
                  <option value="<?= htmlspecialchars($e['id_empresa']) ?>" <?= $selected_empresa == $e['id_empresa'] ? 'selected' : '' ?>><?= htmlspecialchars($e['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <?php if (!empty($monedas)): ?>
          <div class="form-group">
            <label for="moneda"><i class="fas fa-coins"></i> Moneda</label>
            <select id="moneda" name="moneda">
              <option value="">Todas las monedas</option>
              <?php foreach ($monedas as $m): ?>
                <option value="<?= htmlspecialchars($m['moneda']) ?>" <?= $selected_moneda === $m['moneda'] ? 'selected' : '' ?>><?= htmlspecialchars($m['moneda'] === 'DOP' ? 'RD$ - Pesos Dominicanos' : $m['moneda']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
        </div>

        <!-- PASO 3: OPCIONES -->
        <div class="section" id="section-3">
          <div class="section-title">
            <i class="fas fa-sliders-h"></i>
            Configura las opciones de exportación
          </div>
          <p style="color: #64748b; margin-bottom: 20px; font-size: 14px;">Define cuántos registros incluir, el orden y otras preferencias.</p>

          <div class="grid-2">
            <div class="form-group">
              <label for="limit"><i class="fas fa-list"></i> Número de Registros</label>
              <select id="limit" name="limit">
                <option value="25" <?= $selected_limit === '25' ? 'selected' : '' ?>>25 registros</option>
                <option value="50" <?= $selected_limit === '50' ? 'selected' : '' ?>>50 registros</option>
                <option value="100" <?= $selected_limit === '100' ? 'selected' : '' ?>>100 registros</option>
                <option value="0" <?= $selected_limit === '0' ? 'selected' : '' ?>>Todos los registros</option>
              </select>
            </div>
            <div class="form-group">
              <label for="orden"><i class="fas fa-sort"></i> Ordenar Por</label>
              <select id="orden" name="orden">
                <?php foreach ($validOrdenes as $value => $label): ?>
                  <option value="<?= htmlspecialchars($value) ?>" <?= $selected_orden === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="summary-card">
            <div style="margin-bottom: 12px; display: flex; align-items: center; gap: 8px; color: #0369a1; font-weight: 600;">
              <i class="fas fa-info-circle"></i>
              Opciones adicionales
            </div>
            <div class="checkbox-item">
              <input type="checkbox" id="encabezados" name="incluir_encabezados" value="1" <?= $selected_encabezados ? 'checked' : '' ?>>
              <label for="encabezados" style="margin: 0;">Incluir encabezados en la primera fila</label>
            </div>
          </div>
        </div>

        <!-- PASO 4: PREVISUALIZACIÓN -->
        <div class="section" id="section-4">
          <div class="section-title">
            <i class="fas fa-eye"></i>
            Previsualización de Datos
          </div>
          <p style="color: #64748b; margin-bottom: 20px; font-size: 14px;">Verifica cómo se verá tu reporte antes de descargarlo.</p>

          <div class="format-badge" id="formatBadge">
            <i class="fas fa-file-excel"></i>
            <span id="formatText">Se descargará como Excel (.xlsx)</span>
          </div>

          <div id="previewContainer" style="display: flex; align-items: center; justify-content: center; min-height: 200px;">
            <div style="text-align: center; color: #64748b;">
              <div class="loading" style="margin: 0 auto 12px;"></div>
              <p>Cargando previsualización...</p>
            </div>
          </div>

          <div class="preview-stats" id="previewStats"></div>
        </div>
      </div>

      <div class="footer">
        <div>
          <button type="button" class="btn btn-secondary" id="btnPrev" onclick="prevStep()" style="display: none;">
            <i class="fas fa-chevron-left"></i> Anterior
          </button>
        </div>
        <div style="display: flex; gap: 12px;">
          <a href="../historial.php" class="btn btn-link">
            <i class="fas fa-times"></i> Cancelar
          </a>
          <button type="button" class="btn btn-primary" id="btnNext" onclick="nextStep()">
            Siguiente <i class="fas fa-chevron-right"></i>
          </button>
          <button type="submit" class="btn btn-primary hidden" id="btnSubmit" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
            <i class="fas fa-download"></i> Descargar Reporte
          </button>
        </div>
      </div>
    </form>
  </div>

  <script>
    let currentStep = 1;
    const maxSteps = 4;

    function goToStep(step) {
      if (step < 1 || step > maxSteps) return;

      // Ocultar secciones
      document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
      document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));

      // Mostrar sección actual
      document.getElementById(`section-${step}`).classList.add('active');
      document.querySelector(`[data-step="${step}"]`).classList.add('active');

      // Marcar pasos completados
      for (let i = 1; i < step; i++) {
        document.querySelector(`[data-step="${i}"]`).classList.add('completed');
      }
      for (let i = step + 1; i <= maxSteps; i++) {
        document.querySelector(`[data-step="${i}"]`).classList.remove('completed');
      }

      // Botones
      document.getElementById('btnPrev').style.display = step === 1 ? 'none' : 'inline-flex';
      document.getElementById('btnNext').style.display = step === maxSteps ? 'none' : 'inline-flex';
      document.getElementById('btnSubmit').classList.toggle('hidden', step !== maxSteps);

      currentStep = step;

      // Cargar previsualización si estamos en el paso 4
      if (step === 4) {
        loadPreview();
      }

      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function nextStep() {
      if (currentStep === 1 && !hasSelectedCampos()) {
        Swal.fire({
          icon: 'warning',
          title: 'Selecciona al menos un campo',
          text: 'Debes seleccionar al menos un campo para continuar.',
          confirmButtonText: 'Entendido'
        });
        return;
      }
      goToStep(currentStep + 1);
    }

    function prevStep() {
      goToStep(currentStep - 1);
    }

    function hasSelectedCampos() {
      return document.querySelectorAll('input[name="campos[]"]:checked').length > 0;
    }

    function loadPreview() {
      const formData = new FormData(document.getElementById('reportForm'));
      const params = new URLSearchParams(formData);

      fetch('preview_reporte.php?' + params.toString())
        .then(r => r.json())
        .then(data => {
          if (data.error) {
            document.getElementById('previewContainer').innerHTML = `
              <div class="empty-state">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Error</h3>
                <p>${data.error}</p>
              </div>
            `;
            return;
          }

          const { headers, rows, total, format } = data;
          const isEmpty = rows.length === 0;

          // Actualizar badge de formato
          const formatText = format === 'xlsx' ? 
            'Se descargará como Excel (.xlsx)' : 
            'Se descargará como CSV (.csv)';
          document.getElementById('formatText').textContent = formatText;
          document.getElementById('formatBadge').style.background = 
            format === 'xlsx' ? 'linear-gradient(135deg, #10b981 0%, #059669 100%)' : 'linear-gradient(135deg, #5b8db8 0%, #232946 100%)';

          if (isEmpty) {
            document.getElementById('previewContainer').innerHTML = `
              <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>Sin datos</h3>
                <p>No hay registros que coincidan con los filtros seleccionados.</p>
              </div>
            `;
            document.getElementById('previewStats').innerHTML = '';
            return;
          }

          // Construir tabla
          let html = '<table class="preview-table"><thead><tr>';
          headers.forEach(h => {
            html += `<th>${h}</th>`;
          });
          html += '</tr></thead><tbody>';
          rows.forEach(row => {
            html += '<tr>';
            row.forEach(cell => {
              html += `<td>${cell ?? ''}</td>`;
            });
            html += '</tr>';
          });
          html += '</tbody></table>';

          document.getElementById('previewContainer').innerHTML = html;

          // Estadísticas
          let statsHtml = `
            <div class="stat-badge success">
              <i class="fas fa-check-circle"></i>
              ${total} registros
            </div>
            <div class="stat-badge">
              <i class="fas fa-columns"></i>
              ${headers.length} campos
            </div>
          `;
          document.getElementById('previewStats').innerHTML = statsHtml;
        })
        .catch(err => {
          console.error(err);
          document.getElementById('previewContainer').innerHTML = `
            <div class="empty-state">
              <i class="fas fa-exclamation-circle"></i>
              <h3>Error al cargar previsualización</h3>
              <p>${err.message}</p>
            </div>
          `;
        });
    }

    // Envío del formulario
    document.getElementById('reportForm').addEventListener('submit', function(e) {
      e.preventDefault();

      const campos = document.querySelectorAll('input[name="campos[]"]:checked').length;
      if (campos === 0) {
        Swal.fire({
          icon: 'warning',
          title: 'Selecciona campos',
          text: 'Debes seleccionar al menos un campo.'
        });
        return;
      }

      // Convertir a form tradicional para envío POST.
      // cloneNode() superficial en <select> omite los <option>, el navegador
      // no envía el campo y PHP cae al default 25 (rompe "Todos" = 0).
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = 'generar_reporte.php';

      document.querySelectorAll('#reportForm input, #reportForm select').forEach(input => {
        if (!input.name) return;
        if (input.type === 'checkbox' && !input.checked) return;
        // Incluir limit=0 ("Todos los registros"); omitir el resto de vacíos.
        if (input.name !== 'limit' && (input.value === undefined || input.value === '')) return;
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = input.name;
        hidden.value = input.value;
        form.appendChild(hidden);
      });

      document.body.appendChild(form);

      Swal.fire({
        title: 'Generando reporte...',
        html: 'Por favor espera mientras se prepara tu descarga.',
        didOpen: () => {
          Swal.showLoading();
          setTimeout(() => form.submit(), 500);
        }
      });
    });

    // Inicializar
    goToStep(1);
  </script>
</body>
</html>
