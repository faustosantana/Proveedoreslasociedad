// ===== HELPER PARA SWEETALERT SOBRE MODALES =====
function ensureSwalZIndex() {
  if (!document.getElementById('swal-z-index-fix')) {
    const style = document.createElement('style');
    style.id = 'swal-z-index-fix';
    style.innerHTML = '.swal2-container { z-index: 10100 !important; }';
    document.head.appendChild(style);
  }
}

// ===== TOGGLE DE FILTROS =====
document.addEventListener('DOMContentLoaded', function() {
  const toggleBtn = document.getElementById('btn-toggle-filters');
  const filtersContainer = document.getElementById('filters-container');

  toggleBtn.addEventListener('click', function() {
    filtersContainer.classList.toggle('show');
    toggleBtn.classList.toggle('active');

    // Cambiar texto del botón
    const span = toggleBtn.querySelector('span');
    if (filtersContainer.classList.contains('show')) {
      span.textContent = 'Ocultar Filtros';
    } else {
      span.textContent = 'Mostrar Filtros';
    }
  });

  // Actualizar texto inicial si los filtros están mostrados
  if (filtersContainer.classList.contains('show')) {
    toggleBtn.classList.add('active');
    toggleBtn.querySelector('span').textContent = 'Ocultar Filtros';
  } else {
    toggleBtn.querySelector('span').textContent = 'Mostrar Filtros';
  }
});

// ===== BOTÓN VER RECHAZADAS =====
document.getElementById('btn-ver-rechazadas').onclick = function() {
  const url = new URL(window.location.href);
  if (!url.searchParams.get('ver_rechazadas')) {
    url.searchParams.set('ver_rechazadas', '1');
  } else {
    url.searchParams.delete('ver_rechazadas');
  }
  window.location.href = url.toString();
};

// ===== DROPDOWN MENU =====
function toggleDropdown(btn) {
  const dropdown = btn.nextElementSibling;
  const wasOpen = dropdown.classList.contains('show');
  const currentRow = btn.closest('tr');

  // Cerrar todos los dropdowns y remover clase de filas
  document.querySelectorAll('.dropdown-menu.show').forEach(d => {
    d.classList.remove('show');
    const row = d.closest('tr');
    if (row) row.classList.remove('dropdown-open');
  });

  // Toggle el actual
  if (!wasOpen) {
    dropdown.classList.add('show');
    if (currentRow) currentRow.classList.add('dropdown-open');
  }
}

// Cerrar dropdowns al hacer clic fuera
document.addEventListener('click', function(e) {
  if (!e.target.closest('.dropdown-actions')) {
    document.querySelectorAll('.dropdown-menu.show').forEach(d => {
      d.classList.remove('show');
      const row = d.closest('tr');
      if (row) row.classList.remove('dropdown-open');
    });
  }
});

// Cerrar dropdowns al hacer scroll
const tableWrapper = document.querySelector('.table-wrapper');
if (tableWrapper) {
  tableWrapper.addEventListener('scroll', function() {
    document.querySelectorAll('.dropdown-menu.show').forEach(d => {
      d.classList.remove('show');
      const row = d.closest('tr');
      if (row) row.classList.remove('dropdown-open');
    });
  });
}

// ===== FUNCIONES ELIMINAR/RESTAURAR FACTURA =====
function restaurarFactura(idFactura, csrfToken) {
  Swal.fire({
    title: '¿Restaurar factura?',
    text: 'La factura volverá a estado pendiente y no será eliminada automáticamente.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Sí, restaurar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      const form = document.createElement('form');
      form.method = 'post';
      form.action = 'php/restaurar_factura.php';
      const input1 = document.createElement('input');
      input1.type = 'hidden';
      input1.name = 'id_factura';
      input1.value = idFactura;
      const input2 = document.createElement('input');
      input2.type = 'hidden';
      input2.name = 'nuevo_estado';
      input2.value = 'pendiente';
      const input3 = document.createElement('input');
      input3.type = 'hidden';
      input3.name = 'csrf_token';
      input3.value = csrfToken;
      form.appendChild(input1);
      form.appendChild(input2);
      form.appendChild(input3);
      document.body.appendChild(form);
      form.submit();
    }
  });
}

function eliminarPermanente(idFactura, csrfToken) {
  Swal.fire({
    title: '¿Eliminar definitivamente?',
    html: '<p style="color: #dc2626; font-weight: 600;">⚠️ ESTA ACCIÓN NO SE PUEDE DESHACER ⚠️</p><p>La factura y todos sus datos relacionados (abonos, archivos, etc.) serán eliminados permanentemente de la base de datos.</p>',
    icon: 'error',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'Sí, eliminar definitivamente',
    cancelButtonText: 'Cancelar',
    focusCancel: true
  }).then((result) => {
    if (result.isConfirmed) {
      const form = document.createElement('form');
      form.method = 'post';
      form.action = 'acciones/eliminar_factura_permanente.php';
      const input1 = document.createElement('input');
      input1.type = 'hidden';
      input1.name = 'id';
      input1.value = idFactura;
      const input2 = document.createElement('input');
      input2.type = 'hidden';
      input2.name = 'csrf_token';
      input2.value = csrfToken;
      form.appendChild(input1);
      form.appendChild(input2);
      document.body.appendChild(form);
      form.submit();
    }
  });
}

function confirmarEliminar(idFactura, csrfToken) {
  Swal.fire({
    title: '¿Eliminar factura?',
    text: 'La factura se marcará como eliminada.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      const form = document.createElement('form');
      form.method = 'post';
      form.action = 'acciones/eliminar_factura.php';
      const input1 = document.createElement('input');
      input1.type = 'hidden';
      input1.name = 'id';
      input1.value = idFactura;
      const input2 = document.createElement('input');
      input2.type = 'hidden';
      input2.name = 'csrf_token';
      input2.value = csrfToken;
      form.appendChild(input1);
      form.appendChild(input2);
      document.body.appendChild(form);
      form.submit();
    }
  });
}

// ===== BÚSQUEDA SERVER-SIDE (AJAX) =====
let busquedaTimeout = null;
let busquedaEnCurso = false;

function buscarFacturasAjax(pagina) {
  const buscador = document.getElementById('buscadorFacturas');
  const filtro = buscador?.value.trim() || '';
  const tbody = document.getElementById('tablaFacturasBody');
  const pagContainer = document.getElementById('paginacionContainer');
  if (!tbody) return;

  // Construir URL con filtros actuales + búsqueda
  const params = new URLSearchParams(window.location.search);
  if (filtro) params.set('q', filtro);
  else params.delete('q');
  if (pagina) params.set('pagina', pagina);
  else params.delete('pagina');

  busquedaEnCurso = true;
  if (tbody) tbody.style.opacity = '0.5';

  fetch('historial/ajax/buscar_facturas.php?' + params.toString())
    .then(res => {
      if (!res.ok) throw new Error('Error del servidor');
      return res.json();
    })
    .then(data => {
      if (data.success) {
        tbody.innerHTML = data.html + `
          <tr class="spacer-row"><td colspan="12">&nbsp;</td></tr>
          <tr class="spacer-row"><td colspan="12">&nbsp;</td></tr>
          <tr class="spacer-row"><td colspan="12">&nbsp;</td></tr>
          <tr class="spacer-row"><td colspan="12">&nbsp;</td></tr>
          <tr class="spacer-row"><td colspan="12">&nbsp;</td></tr>`;
        if (pagContainer) {
          if (data.paginacion) {
            pagContainer.innerHTML = data.paginacion;
          } else {
            pagContainer.innerHTML = '';
          }
        }
        // Actualizar contador de resultados si existe
        const totalInfo = document.getElementById('totalRegistros');
        if (totalInfo) totalInfo.textContent = data.total_registros;
      }
    })
    .catch(err => {
      console.error('Error en búsqueda:', err);
    })
    .finally(() => {
      busquedaEnCurso = false;
      if (tbody) tbody.style.opacity = '1';
    });
}

const buscador = document.getElementById('buscadorFacturas');
buscador?.addEventListener('input', (e) => {
  clearTimeout(busquedaTimeout);
  busquedaTimeout = setTimeout(() => {
    const filtro = e.target.value.trim();
    if (filtro.length > 0 && filtro.length < 2) return;
    if (filtro === '') {
      const url = new URL(window.location.href);
      url.searchParams.delete('q');
      url.searchParams.delete('pagina');
      window.location.href = url.toString();
      return;
    }
    buscarFacturasAjax(1);
  }, 350);
});

// ===== MODAL DE ABONO =====
const modalAbono = document.getElementById('modalAbono');

const obtenerSaldoPendiente = async (idFactura) => {
  try {
    const res = await fetch(`php/obtener_saldo_factura.php?id_factura=${encodeURIComponent(idFactura)}`);
    const data = await res.json();
    return data.saldo_pendiente ?? 0;
  } catch (e) {
    return 0;
  }
};

const mostrarModalAbono = async (btn) => {
  const idFactura = btn.dataset.idFactura;
  modalAbono.classList.add('show');
  document.getElementById('id_factura_abono').value = idFactura;
  document.getElementById('modalAbonoIdFactura').textContent = idFactura;
  document.getElementById('monto_abono').value = '';
  document.getElementById('comentario_abono').value = '';
  modalAbono.querySelector('#cargandoAbono').style.display = 'none';

  const saldo = await obtenerSaldoPendiente(idFactura);
  document.getElementById('modalAbonoSaldoPendiente').textContent = saldo.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  document.getElementById('monto_abono').max = saldo;
  setTimeout(() => document.getElementById('monto_abono').focus(), 200);
};

// Event delegation para botones de abono dentro de dropdowns
document.addEventListener('click', function(e) {
  if (e.target.closest('.abonar-btn')) {
    const btn = e.target.closest('.abonar-btn');
    mostrarModalAbono(btn);
  }
});

document.getElementById('cerrarModalAbono').onclick = () => {
  modalAbono.classList.remove('show');
  modalAbono.querySelector('#cargandoAbono').style.display = 'none';
};

// ===== ENVÍO DE ABONO =====
const validarAbono = (monto, saldo) => {
  if (isNaN(monto) || monto <= 0) {
    Swal.fire('Error', 'El monto debe ser mayor a 0.', 'error');
    return false;
  }
  if (monto > saldo) {
    Swal.fire('Error', 'El pago no puede superar el saldo pendiente.', 'error');
    return false;
  }
  return true;
};

const enviarAbono = async (formData, btnAbonar, cargando) => {
  btnAbonar.disabled = true;
  cargando.style.display = 'block';

  const res = await fetch('php/registrar_abono.php', {
    method: 'POST',
    body: formData
  });
  const text = await res.text();

  cargando.style.display = 'none';
  btnAbonar.disabled = false;

  Swal.fire({
    title: 'Abono',
    text: text,
    icon: res.ok ? 'success' : 'error',
    customClass: {
      popup: 'swal2-top-z'
    }
  }).then(() => {
    if (res.ok) {
      modalAbono.classList.remove('show');
      setTimeout(() => location.reload(), 1200);
    }
  });

  const swalStyle = document.createElement('style');
  swalStyle.innerHTML = '.swal2-container { z-index: 10100 !important; }';
  document.head.appendChild(swalStyle);
};

document.getElementById('formAbono').onsubmit = async (e) => {
  e.preventDefault();
  const monto = parseFloat(document.getElementById('monto_abono').value);
  const saldo = parseFloat(document.getElementById('modalAbonoSaldoPendiente').textContent.replace(/,/g, ''));

  if (!validarAbono(monto, saldo)) return;

  const formData = new FormData(e.target);
  const btnAbonar = document.getElementById('btnAbonar');
  const cargando = modalAbono.querySelector('#cargandoAbono');

  await enviarAbono(formData, btnAbonar, cargando);
};

// ===== SELECCIÓN Y EXPORTACIÓN =====
const checkTodos = document.getElementById('check-todos');
const checkboxes = document.querySelectorAll('.check-factura');
const btnExportar = document.getElementById('btn-exportar-excel');
const inputIdsExport = document.getElementById('ids_factura_export');
const btnEliminarSeleccionadas = document.getElementById('btn-eliminar-seleccionadas');

// Detectar si estamos en vista de eliminadas
const urlParams = new URLSearchParams(window.location.search);
const esVistaEliminadas = urlParams.get('estado') === 'eliminada';

// ===== LÓGICA PARA VISTA NORMAL (EXPORTACIÓN) =====
if (checkTodos && checkboxes.length > 0 && !esVistaEliminadas) {
  const actualizarEstadoGeneral = () => {
    checkTodos.checked = [...checkboxes].every(cb => cb.checked);
    const seleccionados = [...checkboxes].filter(cb => cb.checked).map(cb => cb.value);
    inputIdsExport.value = seleccionados.join(',');
    btnExportar.style.display = seleccionados.length > 0 ? 'inline-block' : 'none';
  };

  checkTodos.addEventListener('change', () => {
    checkboxes.forEach(cb => cb.checked = checkTodos.checked);
    actualizarEstadoGeneral();
  });

  checkboxes.forEach(cb => cb.addEventListener('change', actualizarEstadoGeneral));

  document.getElementById('form-exportar-excel').addEventListener('submit', (e) => {
    if (!inputIdsExport.value.trim()) {
      e.preventDefault();
      Swal.fire('Error', 'Selecciona al menos una factura para exportar.', 'error');
    }
  });

  actualizarEstadoGeneral();
}

// ===== LÓGICA PARA VISTA ELIMINADAS (ELIMINACIÓN MASIVA) =====
if (checkTodos && btnEliminarSeleccionadas && esVistaEliminadas) {
  // Marcar/Desmarcar todos los checkboxes
  checkTodos.addEventListener('change', function() {
    const checks = document.querySelectorAll('.check-factura');
    checks.forEach(check => {
      check.checked = this.checked;
    });
    actualizarBotonEliminarMasivo();
  });

  // Detectar cambios en checkboxes individuales
  document.addEventListener('change', function(e) {
    if (e.target.classList.contains('check-factura')) {
      actualizarBotonEliminarMasivo();
      
      // Actualizar estado del checkbox "todos"
      const checks = document.querySelectorAll('.check-factura');
      const checkedCount = document.querySelectorAll('.check-factura:checked').length;
      checkTodos.checked = (checkedCount === checks.length && checks.length > 0);
    }
  });

  // Mostrar/ocultar botón según selección
  function actualizarBotonEliminarMasivo() {
    if (!btnEliminarSeleccionadas) return;
    
    const checkedCount = document.querySelectorAll('.check-factura:checked').length;
    if (checkedCount > 0) {
      btnEliminarSeleccionadas.style.display = 'flex';
      btnEliminarSeleccionadas.querySelector('span').textContent = 
        `Eliminar ${checkedCount} seleccionada${checkedCount > 1 ? 's' : ''}`;
    } else {
      btnEliminarSeleccionadas.style.display = 'none';
    }
  }

  // Eliminar facturas seleccionadas
  btnEliminarSeleccionadas.addEventListener('click', function() {
    const checksSeleccionados = document.querySelectorAll('.check-factura:checked');
    
    if (checksSeleccionados.length === 0) {
      ensureSwalZIndex();
      Swal.fire('Atención', 'No hay facturas seleccionadas', 'warning');
      return;
    }

    // Los IDs pueden ser numéricos o alfanuméricos (ej: 'fact_69b885c9e5952')
    const ids = Array.from(checksSeleccionados)
      .map(check => check.getAttribute('data-id') || check.dataset.id)
      .filter(id => id && id !== '' && id !== '0');
    
    console.log('IDs extraídos:', ids);
    
    if (ids.length === 0) {
      ensureSwalZIndex();
      Swal.fire('Error', 'No se pudieron obtener los IDs de las facturas seleccionadas.', 'error');
      return;
    }

    ensureSwalZIndex();
    Swal.fire({
      title: '¿Eliminar facturas?',
      html: `Se eliminarán <strong>${ids.length}</strong> factura${ids.length > 1 ? 's' : ''} permanentemente de la base de datos.<br><br>Esta acción <strong>no se puede deshacer</strong>.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      confirmButtonColor: '#dc2626',
      cancelButtonText: 'Cancelar'
    }).then(async (result) => {
      if (result.isConfirmed) {
        try {
          const response = await fetch('acciones/eliminar_facturas_masivo.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({ ids: ids })
          });

          const data = await response.json();

          if (data.success) {
            let mensaje = `${data.eliminadas} de ${data.total} facturas eliminadas exitosamente.`;
            if (data.errores && data.errores.length > 0) {
              mensaje += '<br><br><small>Algunos errores:<br>' + data.errores.slice(0, 3).join('<br>') + '</small>';
            }

            ensureSwalZIndex();
            Swal.fire({
              icon: 'success',
              title: '¡Eliminación completada!',
              html: mensaje,
              confirmButtonColor: '#007bff'
            }).then(() => {
              window.location.reload();
            });
          } else {
            throw new Error(data.message || 'Error al eliminar las facturas');
          }
        } catch (error) {
          console.error('Error:', error);
          ensureSwalZIndex();
          Swal.fire('Error', error.message || 'No se pudieron eliminar las facturas', 'error');
        }
      }
    });
  });
}

// ===== MODAL DETALLE FACTURA =====
const modalDetalleFactura = document.getElementById('modalDetalleFactura');

function formatMoney(n, moneda) {
  const num = parseFloat(n).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  const code = (moneda || 'DOP').toUpperCase();
  const prefix = code === 'DOP' ? 'RD$' : code + ' ';
  return prefix + num;
}

function estadoBadgeClass(estado) {
  return estado; // retorna la clase CSS directamente
}

async function abrirDetalleFactura(idFactura) {
  document.getElementById('modalDetalleContenido').innerHTML = '<div id="modalDetalleLoader"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>';
  modalDetalleFactura.classList.add('show');
  try {
    const res = await fetch(`php/get_factura_detalle.php?id=${encodeURIComponent(idFactura)}`);
    const data = await res.json();
    if (data.error) { document.getElementById('modalDetalleContenido').innerHTML = `<div style="padding:40px;text-align:center;color:#dc2626">${data.error}</div>`; return; }
    const f = data.factura;
    const abonos = data.abonos;
    const esCredito = f.es_credito === 'si';
    const moneda = f.moneda || 'DOP';

    let abonosHtml = '';
    if (esCredito) {
      const filas = abonos.length === 0
        ? `<tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:16px;font-size:0.88em">Sin pagos registrados aún</td></tr>`
        : abonos.map((ab, i) => {
            const compHtml = ab.comprobante
              ? `<a href="${ab.comprobante}" target="_blank" style="color:#475569;font-size:0.85em;"><i class="fas fa-paperclip"></i></a>`
              : '<span style="color:#cbd5e1">—</span>';
            const docAdicionalHtml = ab.documento_adicional
              ? `<a href="${ab.documento_adicional}" target="_blank" style="color:#475569;font-size:0.85em;"><i class="fas fa-file-alt"></i></a>`
              : '<span style="color:#cbd5e1">—</span>';
            return `<tr>
              <td style="color:#94a3b8">${i+1}</td>
              <td style="font-weight:600">${formatMoney(ab.monto, moneda)}</td>
              <td style="color:#64748b">${ab.comentario || '<span style="color:#cbd5e1">—</span>'}</td>
              <td style="color:#64748b">${ab.fecha_abono}</td>
              <td style="text-align:center">${compHtml}</td>
              <td style="text-align:center">${docAdicionalHtml}</td>
            </tr>`;
          }).join('');
      abonosHtml = `
        <div class="md-section-title" style="margin-top:4px">Historial de pagos</div>
        <table class="detalle-abonos-table">
          <thead><tr><th>#</th><th>Monto</th><th>Comentario</th><th>Fecha</th><th style="text-align:center">Doc.</th><th style="text-align:center">Doc. 2</th></tr></thead>
          <tbody>${filas}</tbody>
        </table>`;
    }

    const docBtn = f.factura_fisica
      ? `<a href="${f.factura_fisica}" target="_blank" class="btn-detalle-accion btn-detalle-doc"><i class="fas fa-file-pdf"></i> Documento adjunto</a>`
      : '';

    const estadoClass = f.estado in {pendiente:1,aceptada:1,pagada:1,rechazada:1,eliminada:1} ? f.estado : 'pendiente';
    const estadoLabel = f.estado.charAt(0).toUpperCase() + f.estado.slice(1);
    const condPago = esCredito
      ? '<span class="tag-credito">Crédito</span>'
      : '<span class="tag-contado">Contado</span>';

    const vencimientoHtml = f.fecha_vencimiento
      ? `<div class="detalle-item"><label>Vencimiento</label><span>${f.fecha_vencimiento}</span></div>`
      : '';

    document.getElementById('modalDetalleContenido').innerHTML = `
      <div class="modal-detalle-header">
        <button class="modal-detalle-cerrar" onclick="cerrarDetalleFactura()">&#x2715;</button>
        <div class="md-kicker">Detalle de factura</div>
        <div class="md-title">${f.proveedor || 'Sin proveedor'}</div>
        <div class="md-meta">
          <span class="md-ncf">${f.ncf || 'Sin NCF'}</span>
          <span class="md-estado ${estadoClass}">${estadoLabel}</span>
          ${condPago}
        </div>
      </div>
      <div class="modal-detalle-body">
        <div class="md-section-title">Información general</div>
        <div class="detalle-grid">
          <div class="detalle-item"><label>Empresa receptora</label><span>${f.empresa_emisora || '—'}</span></div>
          <div class="detalle-item"><label>Fecha de emisión</label><span>${f.fecha_digital}</span></div>
          ${vencimientoHtml}
          <div class="detalle-item" style="grid-column:1/-1"><label>Producto / Descripción</label><span>${f.producto}</span></div>
        </div>
        <div class="md-section-title">Resumen financiero</div>
        <div class="detalle-resumen">
          <div class="detalle-resumen-card"><label>Monto total</label><div class="valor">${formatMoney(f.total, moneda)}</div></div>
          <div class="detalle-resumen-card abonado"><label>Total abonado</label><div class="valor">${formatMoney(data.total_abonado, moneda)}</div></div>
          <div class="detalle-resumen-card pendiente-card"><label>Saldo pendiente</label><div class="valor">${formatMoney(data.pendiente, moneda)}</div></div>
        </div>
        ${abonosHtml}
      </div>
      <div class="detalle-footer">
        ${docBtn}
        <a href="detalle_factura.php?id=${encodeURIComponent(f.id_factura)}" class="btn-detalle-accion btn-detalle-primary"><i class="fas fa-arrow-up-right-from-square"></i> Ver completo</a>
        <button onclick="cerrarDetalleFactura()" class="btn-detalle-accion btn-detalle-secondary">Cerrar</button>
      </div>`;
  } catch(e) {
    document.getElementById('modalDetalleContenido').innerHTML = '<div style="padding:40px;text-align:center;color:#dc2626">Error al cargar el detalle.</div>';
  }
}

function cerrarDetalleFactura() {
  modalDetalleFactura.classList.remove('show');
}

modalDetalleFactura.addEventListener('click', function(e) {
  if (e.target === modalDetalleFactura) cerrarDetalleFactura();
});

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') cerrarDetalleFactura();
});

// ===== MENSAJE DE ÉXITO AL REGISTRAR FACTURA =====
if (urlParams.get('exito') === '1') {
  Swal.fire({
    icon: 'success',
    title: '¡Factura registrada!',
    text: 'La factura se ha registrado exitosamente.',
    confirmButtonColor: '#007bff',
    timer: 3000,
    timerProgressBar: true
  });

  // Limpiar el parámetro de la URL sin recargar la página
  const newUrl = window.location.pathname + (urlParams.toString().replace('exito=1', '').replace(/^&/, '?').replace(/\?$/, '') || '');
  window.history.replaceState({}, document.title, newUrl);
}

// ===== MODAL CREAR FACTURA (ADMIN) =====
const modalCrearFactura = document.getElementById('modalCrearFactura');
const formCrearFactura = document.getElementById('formCrearFactura');

/**
 * Configura un input de búsqueda para filtrar las opciones de un <select>.
 * Requiere que el select tenga _allOptions cargado.
 */
function configurarBuscadorSelect(inputId, selectId) {
  const input = document.getElementById(inputId);
  const select = document.getElementById(selectId);
  if (!input || !select) return;

  // Remover listener anterior si existe
  const nuevoInput = input.cloneNode(true);
  input.parentNode.replaceChild(nuevoInput, input);

  nuevoInput.addEventListener('input', function () {
    const query = this.value.trim().toLowerCase();
    const allOptions = select._allOptions || [];

    // Reconstruir las opciones visibles
    select.innerHTML = '';
    const filtradas = allOptions.filter(opt =>
      !opt.value || opt.text.toLowerCase().includes(query)
    );

    if (filtradas.length === 0 || (filtradas.length === 1 && !filtradas[0].value)) {
      // Mostrar mensaje "sin resultados" como opción deshabilitada
      const noResult = document.createElement('option');
      noResult.disabled = true;
      noResult.selected = true;
      noResult.textContent = 'Sin resultados';
      select.appendChild(noResult);
    } else {
      filtradas.forEach(opt => select.appendChild(opt.cloneNode(true)));
    }
  });
}

function abrirModalCrearFactura() {
  modalCrearFactura.classList.add('show');
  // Cargar proveedores y empresas
  cargarProveedoresYEmpresas();
}

function cerrarModalCrearFactura() {
  const loadingOverlay = document.getElementById('modalLoadingOverlay');
  
  // No permitir cerrar si está cargando
  if (loadingOverlay && loadingOverlay.classList.contains('active')) {
    return;
  }
  
  // Rehabilitar botones
  const btnSubmit = formCrearFactura.querySelector('button[type="submit"]');
  const btnCancel = formCrearFactura.querySelector('.btn-modal-secondary');
  const btnClose = document.querySelector('.btn-close-modal');
  
  if (btnSubmit) btnSubmit.disabled = false;
  if (btnCancel) btnCancel.disabled = false;
  if (btnClose) btnClose.disabled = false;
  
  // Cerrar modal y resetear formulario
  modalCrearFactura.classList.remove('show');
  formCrearFactura.reset();

  // Limpiar buscadores y restaurar todas las opciones
  const searchProveedor = document.getElementById('searchProveedor');
  const searchEmpresa = document.getElementById('searchEmpresa');
  if (searchProveedor) searchProveedor.value = '';
  if (searchEmpresa) searchEmpresa.value = '';

  const selectProveedor = document.getElementById('proveedorFactura');
  const selectEmpresa = document.getElementById('empresaFactura');
  if (selectProveedor && selectProveedor._allOptions) {
    selectProveedor.innerHTML = '';
    selectProveedor._allOptions.forEach(opt => selectProveedor.appendChild(opt.cloneNode(true)));
  }
  if (selectEmpresa && selectEmpresa._allOptions) {
    selectEmpresa.innerHTML = '';
    selectEmpresa._allOptions.forEach(opt => selectEmpresa.appendChild(opt.cloneNode(true)));
  }
}

async function cargarProveedoresYEmpresas() {
  try {
    // Cargar proveedores
    const respProveedores = await fetch('php/obtener_proveedores.php');
    if (!respProveedores.ok) {
      const errorData = await respProveedores.json();
      throw new Error(errorData.error || 'Error al cargar proveedores');
    }
    const proveedores = await respProveedores.json();
    
    // Verificar que proveedores sea un array
    if (!Array.isArray(proveedores)) {
      console.error('Respuesta de proveedores no es un array:', proveedores);
      throw new Error(proveedores.error || 'Respuesta inválida del servidor');
    }
    
    const selectProveedor = document.getElementById('proveedorFactura');
    selectProveedor.innerHTML = '<option value="">Seleccione un proveedor</option>';
    proveedores.forEach(p => {
      selectProveedor.innerHTML += `<option value="${p.id}">${p.nombre_empresa} - ${p.rnc_cedula}</option>`;
    });

    // Guardar todas las opciones para el filtro de proveedor
    selectProveedor._allOptions = Array.from(selectProveedor.options);

    // Cargar empresas
    const respEmpresas = await fetch('php/obtener_empresas.php');
    if (!respEmpresas.ok) {
      const errorData = await respEmpresas.json();
      throw new Error(errorData.error || 'Error al cargar empresas');
    }
    const empresas = await respEmpresas.json();
    
    // Verificar que empresas sea un array
    if (!Array.isArray(empresas)) {
      console.error('Respuesta de empresas no es un array:', empresas);
      throw new Error(empresas.error || 'Respuesta inválida del servidor');
    }
    
    const selectEmpresa = document.getElementById('empresaFactura');
    selectEmpresa.innerHTML = '<option value="">Seleccione una empresa</option>';
    empresas.forEach(e => {
      selectEmpresa.innerHTML += `<option value="${e.id_empresa}">${e.nombre}</option>`;
    });

    // Guardar todas las opciones para el filtro de empresa
    selectEmpresa._allOptions = Array.from(selectEmpresa.options);

    // Configurar buscadores
    configurarBuscadorSelect('searchProveedor', 'proveedorFactura');
    configurarBuscadorSelect('searchEmpresa', 'empresaFactura');
  } catch (error) {
    console.error('Error al cargar datos:', error);
    ensureSwalZIndex();
    Swal.fire('Error', error.message || 'No se pudieron cargar los proveedores y empresas', 'error');
  }
}

formCrearFactura.addEventListener('submit', async function(e) {
  e.preventDefault();

  const loadingOverlay = document.getElementById('modalLoadingOverlay');
  const btnSubmit = formCrearFactura.querySelector('button[type="submit"]');
  const btnCancel = formCrearFactura.querySelector('.btn-modal-secondary');
  const btnClose = document.querySelector('.btn-close-modal');

  // Activar loading y deshabilitar botones
  loadingOverlay.classList.add('active');
  btnSubmit.disabled = true;
  btnCancel.disabled = true;
  btnClose.disabled = true;

  const formData = new FormData(formCrearFactura);

  try {
    // Garantizar que la pantalla de carga se muestre al menos 1.5 segundos
    const minLoadingTime = new Promise(resolve => setTimeout(resolve, 1500));
    
    const fetchPromise = fetch('php/crear_factura_admin.php', {
      method: 'POST',
      body: formData
    });

    // Esperar ambos: la respuesta del servidor Y el tiempo mínimo
    const [response] = await Promise.all([fetchPromise, minLoadingTime]);
    const result = await response.json();

    // Desactivar loading antes de mostrar resultado
    loadingOverlay.classList.remove('active');

    if (result.success) {
      const swalStyle = document.createElement('style');
      swalStyle.innerHTML = '.swal2-container { z-index: 10100 !important; }';
      document.head.appendChild(swalStyle);
      
      Swal.fire({
        icon: 'success',
        title: '¡Factura creada!',
        text: 'La factura se ha creado exitosamente.',
        confirmButtonColor: '#5b8db8'
      }).then(() => {
        cerrarModalCrearFactura();
        location.reload();
      });
    } else {
      // Rehabilitar botones en caso de error
      btnSubmit.disabled = false;
      btnCancel.disabled = false;
      btnClose.disabled = false;
      ensureSwalZIndex();
      Swal.fire('Error', result.message || 'No se pudo crear la factura', 'error');
    }
  } catch (error) {
    // Desactivar loading y rehabilitar botones
    loadingOverlay.classList.remove('active');
    btnSubmit.disabled = false;
    btnCancel.disabled = false;
    btnClose.disabled = false;
    console.error('Error:', error);
    ensureSwalZIndex();
    Swal.fire('Error', 'Ocurrió un error al crear la factura', 'error');
  }
});

modalCrearFactura.addEventListener('click', function(e) {
  const loadingOverlay = document.getElementById('modalLoadingOverlay');
  // No cerrar si está cargando
  if (e.target === modalCrearFactura && !loadingOverlay.classList.contains('active')) {
    cerrarModalCrearFactura();
  }
});

document.addEventListener('keydown', function(e) {
  const loadingOverlay = document.getElementById('modalLoadingOverlay');
  // No cerrar si está cargando
  if (e.key === 'Escape' && modalCrearFactura.classList.contains('show') && !loadingOverlay.classList.contains('active')) {
    cerrarModalCrearFactura();
  }
});

// Toggle NCF field based on checkbox
document.getElementById('aplica_ncf').addEventListener('change', function() {
  const contenedor = document.getElementById('contenedor_ncf');
  const input = document.getElementById('ncf');
  if (this.checked) {
    contenedor.style.display = 'block';
    input.required = true;
  } else {
    contenedor.style.display = 'none';
    input.required = false;
    input.value = '';
  }
});
