/**
 * FormFieldHandler - Maneja el comportamiento dinámico de los campos del formulario
 */
class FormFieldHandler {
    constructor() {        this.tipoProveedorGeneral = document.getElementById('tipo_proveedor_general');
        this.rncContainer = document.getElementById('rncContainer');
        this.rncInput = document.getElementById('rnc');
        this.rncLabel = document.getElementById('rncLabel');
        this.proveedorInput = document.getElementById('proveedor');
        
        this.seccionEmpresas = document.getElementById('seccionEmpresas');
        this.seccionRepresentante = document.getElementById('seccionRepresentante');
        
        this.tipoBancario = document.getElementById('tipo_bancario');
        this.camposLocal = document.getElementById('camposLocal');
        this.camposExtranjera = document.getElementById('camposExtranjera');
        
        // Variables para validación de RNC
        this.rncValidationTimer = null;
        this.rncValidado = false;
        this.rncValidando = false;
        this.rncStatus = null;
        this.rncRequestSeq = 0;

        // Variables para validación de correo de invitación
        this.correoInvTimer     = null;
        this.correoInvValidado  = false;  // null = sin token (no aplica), false = inválido, true = válido
        this.tokenInvitacion    = new URLSearchParams(window.location.search).get('token') || '';
        
        this.initializeEventListeners();
        this.initializeFormatters();
        this.initializeRncValidation();
        this.initializeCorreoInvitacionValidation();
        this.initializeDocPreviews();
        this.updateRncVisibility();
        this.updateBankingFields();
        
        }
    
    initializeEventListeners() {
        // Cambio de tipo de proveedor
        this.tipoProveedorGeneral?.addEventListener('change', () => {
            this.updateRncVisibility();
            });
        
        // Cambio de tipo bancario
        this.tipoBancario?.addEventListener('change', () => this.updateBankingFields());
        
        // Prevenir Enter en inputs (excepto submit)
        const form = document.getElementById('registroForm');
        if (form) {
            const allInputs = form.querySelectorAll('input, select');
            allInputs.forEach(el => {
                el.addEventListener('keydown', e => {
                    if (e.key === 'Enter' && el.type !== 'textarea' && el.type !== 'submit' && el.type !== 'button') {
                        e.preventDefault();
                    }
                });
            });
        }
    }
    
    initializeFormatters() {
        // Formateo automático de teléfono principal
        const telefonoInput = document.getElementById('telefono');
        telefonoInput?.addEventListener('input', (e) => formatearTelefono(e.target));
        
        // Formateo automático de teléfono del representante
        const representanteTelInput = document.getElementById('representante_telefono');
        representanteTelInput?.addEventListener('input', (e) => formatearTelefono(e.target));
        
        // Convertir a mayúsculas el nombre del banco
        const nombreBancoInput = document.getElementById('nombre_banco');
        nombreBancoInput?.addEventListener('input', (e) => {
            e.target.value = e.target.value.toUpperCase();
        });
    }
    
    /**
     * Inicializa la validación en tiempo real del RNC/Cédula
     */
    initializeRncValidation() {
        if (!this.rncInput) {
            return;
        }
        
        if (!this.rncContainer) {
            return;
        }
        
        // Verificar si ya existe el mensaje de validación
        let mensajeValidacion = document.getElementById('rnc-validation-message');
        
        if (!mensajeValidacion) {
            // Crear elemento para mostrar mensaje de validación
            mensajeValidacion = document.createElement('div');
            mensajeValidacion.id = 'rnc-validation-message';
            mensajeValidacion.style.cssText = `
                margin-top: 5px;
                font-size: 0.85em;
                font-weight: 500;
                display: none;
                padding: 6px 10px;
                border-radius: 4px;
                transition: all 0.3s ease;
            `;
            
            try {
                this.rncContainer.appendChild(mensajeValidacion);
                } catch (error) {
                console.error('❌ Error al agregar mensaje de validación:', error);
                return;
            }
        } else {
            }
        
        // Listener para validar RNC en tiempo real con debounce
        this.rncInput.addEventListener('input', (e) => {
            // Aplicar formateo si es persona individual
            if (this.tipoProveedorGeneral?.value === 'persona_individual') {
                formatearCedula(e.target);
            }
            
            const valor = e.target.value.trim();
            
            // Limpiar timer anterior
            clearTimeout(this.rncValidationTimer);
            
            // Ocultar mensaje si el campo está vacío
            if (!valor) {
                mensajeValidacion.style.display = 'none';
                this.rncValidado = false;
                this.rncValidando = false;
                this.rncStatus = null;
                this.rncInput.style.borderColor = '';
                this.setRncActionsEnabled(true);
                return;
            }

            this.rncValidado = false;
            this.rncValidando = true;
            this.rncStatus = 'pending';
            this.setRncActionsEnabled(false);
            
            // Mostrar mensaje de "validando..."
            mensajeValidacion.style.display = 'block';
            mensajeValidacion.style.backgroundColor = '#e3f2fd';
            mensajeValidacion.style.color = '#1976d2';
            mensajeValidacion.style.border = '1px solid #90caf9';
            mensajeValidacion.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando disponibilidad...';
            // Debounce de escritura (no se usa para el submit)
            this.rncValidationTimer = setTimeout(() => {
                this.validarRncEnBD(e.target.value.trim(), mensajeValidacion);
            }, 800);
        });
        
        }
    
    /**
     * Inicializa la validación en tiempo real del correo contra la invitación
     */
    initializeCorreoInvitacionValidation() {
        // Solo actúa cuando hay token en la URL (acceso por invitación)
        if (!this.tokenInvitacion) {
            this.correoInvValidado = null; // no aplica
            return;
        }

        const correoInput = document.getElementById('correo');
        if (!correoInput) return;

        // Crear elemento de mensaje debajo del campo
        let msgEl = document.getElementById('correo-inv-validation-message');
        if (!msgEl) {
            msgEl = document.createElement('div');
            msgEl.id = 'correo-inv-validation-message';
            msgEl.style.cssText = [
                'margin-top:5px',
                'font-size:0.85em',
                'font-weight:500',
                'display:none',
                'padding:6px 10px',
                'border-radius:4px',
                'transition:all 0.3s ease'
            ].join(';');
            correoInput.insertAdjacentElement('afterend', msgEl);
        }

        correoInput.addEventListener('input', () => {
            const valor = correoInput.value.trim();
            clearTimeout(this.correoInvTimer);

            if (!valor) {
                msgEl.style.display = 'none';
                this.correoInvValidado = false;
                correoInput.style.borderColor = '';
                return;
            }

            // Mostrar "validando..."
            msgEl.style.cssText += ';display:block;background:#e3f2fd;color:#1976d2;border:1px solid #90caf9';
            msgEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando correo...';

            this.correoInvTimer = setTimeout(() => {
                this._validarCorreoInvitacion(valor, correoInput, msgEl);
            }, 700);
        });
    }

    /**
     * Llama al endpoint para verificar si el correo coincide con la invitación
     */
    _validarCorreoInvitacion(correo, inputEl, msgEl) {
        const params = new URLSearchParams({
            token:  this.tokenInvitacion,
            correo: correo
        });

        fetch(`../acciones/verificar_correo_invitacion.php?${params}`, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.match) {
                msgEl.style.cssText = [
                    'display:block',
                    'background:#e8f5e9',
                    'color:#2e7d32',
                    'border:1px solid #81c784',
                    'margin-top:5px',
                    'font-size:0.85em',
                    'font-weight:500',
                    'padding:6px 10px',
                    'border-radius:4px'
                ].join(';');
                msgEl.innerHTML = '<i class="fas fa-check-circle"></i> Correo verificado correctamente';
                inputEl.style.borderColor = '#4caf50';
                this.correoInvValidado = true;
            } else {
                const msg  = data.mensaje || 'El correo no coincide con la invitación.';
                const hint = data.pista ? ` <strong>${data.pista}</strong>` : '';
                msgEl.style.cssText = [
                    'display:block',
                    'background:#ffebee',
                    'color:#c62828',
                    'border:1px solid #ef5350',
                    'margin-top:5px',
                    'font-size:0.85em',
                    'font-weight:500',
                    'padding:6px 10px',
                    'border-radius:4px'
                ].join(';');
                msgEl.innerHTML = `<i class="fas fa-times-circle"></i> ${msg}${hint}`;
                inputEl.style.borderColor = '#f44336';
                this.correoInvValidado = false;
            }
        })
        .catch(() => {
            msgEl.style.cssText = [
                'display:block',
                'background:#fff3e0',
                'color:#e65100',
                'border:1px solid #ffb74d',
                'margin-top:5px',
                'font-size:0.85em',
                'font-weight:500',
                'padding:6px 10px',
                'border-radius:4px'
            ].join(';');
            msgEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error al verificar el correo';
            this.correoInvValidado = false;
        });
    }

    /**
     * Valida el RNC en la base de datos vía AJAX
     */
    setRncActionsEnabled(enabled) {
        const next1 = document.getElementById('next1');
        if (next1) next1.disabled = !enabled;
        const form = document.getElementById('registroForm');
        form?.querySelectorAll('input[type="submit"]').forEach((el) => {
            el.disabled = !enabled;
        });
    }

    validarRncEnBD(rnc, mensajeElemento) {
        const seq = ++this.rncRequestSeq;
        const formData = new FormData();
        formData.append('rnc', rnc);
        formData.append('tipo', this.tipoProveedorGeneral?.value || '');
        
        fetch('php/validar_rnc.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('❌ Error al parsear JSON:', e);
                    console.error('Texto recibido:', text);
                    throw new Error('Respuesta no es JSON válido');
                }
            });
        })
        .then(data => {
            if (seq !== this.rncRequestSeq) return;
            const status = data.status || data.codigo || '';
            this.rncStatus = status;
            this.rncValidando = false;
            mensajeElemento.style.display = 'block';
            const isDup = status === 'DUPLICATE' || status === 'DUPLICADO';
            const isDisp = status === 'DOCUMENTO_DISPONIBLE' || data.documento_disponible === true;
            const isInv = status === 'INVALIDO' || status === 'INVALID';
            if (isDup) {
                mensajeElemento.style.backgroundColor = '#ffebee';
                mensajeElemento.style.color = '#c62828';
                mensajeElemento.style.border = '1px solid #ef5350';
                mensajeElemento.innerHTML = '<i class="fas fa-times-circle"></i> ' + (data.mensaje || 'Esta cédula/RNC ya se encuentra registrada.');
                this.rncInput.style.borderColor = '#f44336';
                this.rncValidado = false;
                this.setRncActionsEnabled(true);
            } else if (isDisp && !isInv) {
                mensajeElemento.style.backgroundColor = '#e8f5e9';
                mensajeElemento.style.color = '#2e7d32';
                mensajeElemento.style.border = '1px solid #81c784';
                mensajeElemento.innerHTML = '<i class="fas fa-check-circle"></i> ' + (data.mensaje || 'Documento disponible (no registrado).');
                this.rncInput.style.borderColor = '#4caf50';
                this.rncValidado = true;
                this.setRncActionsEnabled(true);
            } else {
                mensajeElemento.style.backgroundColor = '#fff3e0';
                mensajeElemento.style.color = '#e65100';
                mensajeElemento.style.border = '1px solid #ffb74d';
                const fallback = isInv
                    ? 'El documento no tiene un formato válido.'
                    : 'Error al verificar la cédula/RNC. Intenta nuevamente.';
                mensajeElemento.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + (data.mensaje || fallback);
                this.rncInput.style.borderColor = '#ff9800';
                this.rncValidado = false;
                this.setRncActionsEnabled(true);
            }
        })
        .catch(error => {
            if (seq !== this.rncRequestSeq) return;
            console.error('❌ Error en la validación:', error);
            this.rncValidando = false;
            this.rncStatus = 'ERROR';
            this.rncValidado = false;
            mensajeElemento.style.backgroundColor = '#fff3e0';
            mensajeElemento.style.color = '#e65100';
            mensajeElemento.style.border = '1px solid #ffb74d';
            mensajeElemento.innerHTML = '<i class="fas fa-exclamation-triangle"></i> No pudimos validar la cédula/RNC. Verifica el número e intenta nuevamente.';
            mensajeElemento.style.display = 'block';
            this.setRncActionsEnabled(true);
        });
    }
    
    /**
     * Resetea la validación del RNC
     */
    resetRncValidation() {
        if (!this.rncInput) return;
        
        this.rncValidado = false;
        this.rncValidando = false;
        this.rncStatus = null;
        this.rncInput.style.borderColor = '';
        this.setRncActionsEnabled(true);
        
        const mensajeValidacion = document.getElementById('rnc-validation-message');
        if (mensajeValidacion) {
            mensajeValidacion.style.display = 'none';
        }
    }
    
    updateRncVisibility() {
        const tipoProveedor = this.tipoProveedorGeneral?.value;
        
        if (tipoProveedor === 'persona_individual') {
            this.handlePersonaIndividual();
        } else if (tipoProveedor === 'internacional') {
            this.handleInternacional();
        } else {
            this.handleLocal();
        }
    }
    
    handlePersonaIndividual() {
        // Resetear validación
        this.resetRncValidation();
        
        // Mostrar cédula
        if (this.rncContainer) this.rncContainer.style.display = 'block';
        if (this.rncLabel) this.rncLabel.textContent = 'Cédula';
        if (this.rncInput) {
            this.rncInput.setAttribute('required', 'required');
            this.rncInput.placeholder = '000-0000000-0';
        }
        if (this.proveedorInput?.previousElementSibling) {
            this.proveedorInput.previousElementSibling.textContent = 'Nombre completo';
        }
        
        // Mostrar empresas, ocultar representante
        if (this.seccionEmpresas) this.seccionEmpresas.style.display = 'block';
        if (this.seccionRepresentante) {
            this.seccionRepresentante.style.display = 'none';
            this.setRepresentativeFieldsRequired(false);
        }
        
        // Ocultar cert_bancaria del Panel 2
        this.toggleBankCertificate('panel2', false);
        
        // Panel 3: Configurar para persona individual
        this.updatePanel3ForPersonaIndividual();
    }
    
    handleInternacional() {
        // Resetear validación
        this.resetRncValidation();
        
        // Ocultar RNC
        if (this.rncContainer) this.rncContainer.style.display = 'none';
        if (this.rncLabel) this.rncLabel.textContent = 'RNC';
        if (this.rncInput) {
            this.rncInput.removeAttribute('required');
            this.rncInput.value = '';
            this.rncInput.placeholder = '';
        }
        if (this.proveedorInput?.previousElementSibling) {
            this.proveedorInput.previousElementSibling.textContent = 'Nombre comercial';
        }
        
        // Mostrar secciones de empresa
        if (this.seccionEmpresas) this.seccionEmpresas.style.display = 'block';
        if (this.seccionRepresentante) this.seccionRepresentante.style.display = 'block';
        
        // Mostrar cert_bancaria del Panel 2 (pero no required)
        this.toggleBankCertificate('panel2', true, false);
        
        // Panel 3: Configurar para empresa
        this.updatePanel3ForCompany();
    }
    
    handleLocal() {
        // Resetear validación
        this.resetRncValidation();
        
        // Mostrar RNC
        if (this.rncContainer) this.rncContainer.style.display = 'block';
        if (this.rncLabel) this.rncLabel.textContent = 'RNC';
        if (this.rncInput) {
            this.rncInput.setAttribute('required', 'required');
            this.rncInput.placeholder = '';
        }
        if (this.proveedorInput?.previousElementSibling) {
            this.proveedorInput.previousElementSibling.textContent = 'Nombre comercial';
        }
        
        // Mostrar secciones de empresa
        if (this.seccionEmpresas) this.seccionEmpresas.style.display = 'block';
        if (this.seccionRepresentante) this.seccionRepresentante.style.display = 'block';
        
        // Mostrar cert_bancaria del Panel 2 (required)
        this.toggleBankCertificate('panel2', true, true);
        
        // Panel 3: Configurar para empresa
        this.updatePanel3ForCompany();
    }
    
    setRepresentativeFieldsRequired(required) {
        const representanteInputs = this.seccionRepresentante?.querySelectorAll('input');
        representanteInputs?.forEach(input => {
            if (required) {
                input.setAttribute('required', 'required');
            } else {
                input.removeAttribute('required');
            }
        });
    }
    
    toggleBankCertificate(panel, show, required = false) {
        const label = document.getElementById('labelCertBancariaLocal');
        const input = document.getElementById('cert_bancaria');
        
        if (label) label.style.display = show ? 'block' : 'none';
        if (input) {
            input.style.display = show ? 'block' : 'none';
            if (show && required) {
                input.setAttribute('required', 'required');
            } else {
                input.removeAttribute('required');
            }
        }
    }
    
    updatePanel3ForPersonaIndividual() {
        const cardRM = document.getElementById('card_registro_mercantil');
        const cardCI = document.getElementById('card_cert_itbis');
        const cardCB = document.getElementById('card_cert_bancaria_file');
        const rncLabel = document.getElementById('labelRncCedulaCard');

        if (cardRM) cardRM.style.display = 'none';
        if (cardCI) cardCI.style.display = 'none';
        if (cardCB) cardCB.style.display = '';
        if (rncLabel) rncLabel.textContent = 'Copia de Cédula (ambos lados)';
    }
    
    updatePanel3ForCompany() {
        const cardRM = document.getElementById('card_registro_mercantil');
        const cardCI = document.getElementById('card_cert_itbis');
        const cardCB = document.getElementById('card_cert_bancaria_file');
        const rncLabel = document.getElementById('labelRncCedulaCard');

        if (cardRM) cardRM.style.display = '';
        if (cardCI) cardCI.style.display = '';
        if (cardCB) cardCB.style.display = 'none';
        if (rncLabel) rncLabel.textContent = 'Copia de RNC o Cédula';
    }
    
    initializeDocPreviews() {
        ['registro_mercantil', 'rnc_cedula', 'cert_itbis', 'cert_bancaria_file'].forEach(id => {
            const input = document.getElementById(id);
            if (input) input.addEventListener('change', () => this._showDocPreview(input));
        });
    }

    _showDocPreview(input) {
        const card = input.closest('.doc-card');
        const previewEl = card?.querySelector('.doc-preview');
        if (!previewEl) return;

        const file = input.files?.[0];
        if (!file) {
            previewEl.innerHTML = '';
            previewEl.classList.remove('visible');
            card.classList.remove('has-file');
            return;
        }

        // Validar tamaño máximo: 10 MB
        const MAX_SIZE = 10 * 1024 * 1024;
        if (file.size > MAX_SIZE) {
            input.value = '';
            Swal.fire('Archivo muy grande', `"${file.name}" supera el límite de 10 MB.`, 'warning');
            return;
        }

        const url = URL.createObjectURL(file);
        const isPdf = file.type === 'application/pdf';
        const isImg = file.type.startsWith('image/');

        let mediaHtml = '';
        if (isPdf) {
            mediaHtml = `<embed src="${url}" type="application/pdf">`;
        } else if (isImg) {
            mediaHtml = `<img src="${url}" alt="Vista previa">`;
        }

        previewEl.innerHTML = mediaHtml + `
            <div class="doc-preview-bar">
                <span class="doc-fname">${this._escapeHtml(file.name)}</span>
                <button type="button" class="doc-remove-btn" data-input="${input.id}">
                    <i class="fas fa-times"></i> Quitar
                </button>
            </div>`;

        previewEl.classList.add('visible');
        card.classList.add('has-file');

        previewEl.querySelector('.doc-remove-btn').addEventListener('click', () => {
            input.value = '';
            previewEl.innerHTML = '';
            previewEl.classList.remove('visible');
            card.classList.remove('has-file');
            URL.revokeObjectURL(url);
        });
    }

    _escapeHtml(str) {
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    updateBankingFields() {
        const isExtranjera = this.tipoBancario?.value === 'extranjera';
        
        if (this.camposLocal) {
            this.camposLocal.style.display = isExtranjera ? 'none' : 'block';
            this.setFieldsRequired(this.camposLocal, !isExtranjera);
        }
        
        if (this.camposExtranjera) {
            this.camposExtranjera.style.display = isExtranjera ? 'block' : 'none';
            this.setFieldsRequired(this.camposExtranjera, isExtranjera);
        }
        
        // Manejo especial para cert_bancaria
        const certBancaria = document.getElementById('cert_bancaria');
        if (certBancaria) {
            if (isExtranjera) {
                certBancaria.removeAttribute('required');
            } else {
                certBancaria.setAttribute('required', 'required');
            }
        }
    }
    
    setFieldsRequired(container, required) {
        const fields = container.querySelectorAll('input, select');
        fields.forEach(field => {
            if (required) {
                field.setAttribute('required', 'required');
            } else {
                field.removeAttribute('required');
            }
        });
    }
}
