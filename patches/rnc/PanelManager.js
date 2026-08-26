/**
 * PanelManager - Gestiona la navegación entre paneles del formulario
 */
class PanelManager {
    constructor() {
        this.panels = {
            panel1: document.getElementById('panel1'),
            panel2: document.getElementById('panel2'),
            panel3: document.getElementById('panel3')
        };
        
        this.buttons = {
            next1: document.getElementById('next1'),
            next2: document.getElementById('next2'),
            back2: document.getElementById('back2'),
            back3: document.getElementById('back3')
        };
        
        this.form = document.getElementById('registroForm');
        this.currentPanel = 1;
        
        this.initializeEventListeners();
    }
    
    initializeEventListeners() {
        // Botón siguiente del panel 1
        this.buttons.next1?.addEventListener('click', () => this.validateAndNavigate(1, 2));
        
        // Botón siguiente del panel 2
        this.buttons.next2?.addEventListener('click', () => this.validateAndNavigate(2, 3));
        
        // Botón atrás del panel 2
        this.buttons.back2?.addEventListener('click', () => this.navigateToPanel(2, 1));
        
        // Botón atrás del panel 3
        this.buttons.back3?.addEventListener('click', () => this.navigateToPanel(3, 2));
    }
    
    navigateToPanel(from, to) {
        const toPanel = this.panels[`panel${to}`];
        this.panels[`panel${from}`]?.classList.remove('active');
        toPanel?.classList.remove('from-back', 'active');
        if (to < from) toPanel?.classList.add('from-back');
        // Force reflow to restart CSS animation
        void toPanel?.offsetWidth;
        toPanel?.classList.add('active');
        this.currentPanel = to;
        this.updateStepper(to);

        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    updateStepper(activePanel) {
        document.querySelectorAll('#formStepper .step').forEach((step, i) => {
            const n = i + 1;
            step.classList.toggle('active', n === activePanel);
            step.classList.toggle('completed', n < activePanel);
        });
        document.querySelectorAll('#formStepper .step-connector').forEach((conn, i) => {
            conn.classList.toggle('completed', i + 1 < activePanel);
        });
    }
    
    validateAndNavigate(from, to) {
        if (this.validateCurrentPanel(from)) {
            this.navigateToPanel(from, to);
        }
    }
    
    validateCurrentPanel(panelNumber) {
        const panel = this.panels[`panel${panelNumber}`];
        if (!panel) return false;
        
        // Obtener inputs requeridos visibles
        const inputs = Array.from(panel.querySelectorAll('input[required], select[required]'))
            .filter(el => el.offsetParent !== null);
        
        // Validar cada input
        for (const input of inputs) {
            if (!input.checkValidity()) {
                input.scrollIntoView({ behavior: 'smooth', block: 'center' });
                // Pequeño delay para que el scroll termine antes de mostrar el tooltip
                setTimeout(() => input.reportValidity(), 300);
                return false;
            }
        }
        
        // Validaciones específicas por panel
        switch(panelNumber) {
            case 1:
                return this.validatePanel1();
            case 2:
                return this.validatePanel2();
            default:
                return true;
        }
    }
    
    validatePanel1() {
        const tipoProveedor = document.getElementById('tipo_proveedor_general')?.value;
        const esPersonaIndividual = tipoProveedor === 'persona_individual';
        
        // Validar campos de texto sin números
        const textFields = ['proveedor'];
        for (const fieldId of textFields) {
            const field = document.getElementById(fieldId);
            if (field && !validarTextoSinNumeros(field.value.trim())) {
                Swal.fire('Error', `El campo "${fieldId.replace(/_/g, ' ')}" no debe contener números`, 'warning');
                field.focus();
                return false;
            }
        }
        
        // Validar dirección fiscal
        const direccionFiscal = document.getElementById('direccion_fiscal');
        if (direccionFiscal && direccionFiscal.value.trim().length === 0) {
            Swal.fire('Error', 'La dirección fiscal es obligatoria', 'warning');
            direccionFiscal.focus();
            return false;
        }
        
        // Validar teléfono
        const telefono = document.getElementById('telefono');
        if (telefono && !/^[0-9]{3}-[0-9]{3}-[0-9]{4}$/.test(telefono.value.trim())) {
            Swal.fire('Error', 'El teléfono debe tener el formato: 809-000-0000', 'warning');
            telefono.focus();
            return false;
        }
        
        // Validar RNC/Cédula según el tipo de proveedor
        const rncInput = document.getElementById('rnc');
        const rncContainer = document.getElementById('rncContainer');
        
        if (rncInput && rncContainer && window.getComputedStyle(rncContainer).display !== 'none') {
            const rncValue = rncInput.value.trim();
            
            
            // Verificar que el campo no esté vacío
            if (!rncValue) {
                Swal.fire('Error', esPersonaIndividual ? 'La cédula es obligatoria' : 'El RNC es obligatorio', 'warning');
                rncInput.focus();
                return false;
            }
            
            // Validar formato de cédula si es persona individual
            if (esPersonaIndividual) {
                if (!/^[0-9]{3}-[0-9]{7}-[0-9]{1}$/.test(rncValue)) {
                    Swal.fire('Error', 'La cédula debe tener el formato: 000-0000000-0', 'warning');
                    rncInput.focus();
                    return false;
                }
            }
            
            // Verificar que el RNC esté validado contra la BD
            if (window.formApp && window.formApp.formFieldHandler) {
                
                const rncHandler = window.formApp.formFieldHandler;
                if (rncHandler.rncValidando || rncHandler.rncStatus === 'pending') {
                    Swal.fire({
                        icon: 'info',
                        title: 'Validando…',
                        text: 'Espera a que aparezca la confirmación verde antes de continuar.',
                        confirmButtonColor: '#24125F'
                    });
                    rncInput.focus();
                    return false;
                }
                if (rncHandler.rncStatus === 'DUPLICATE' || rncHandler.rncStatus === 'DUPLICADO') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Documento ya registrado',
                        text: 'Esta cédula/RNC ya se encuentra registrada.',
                        confirmButtonColor: '#c0392b'
                    });
                    rncInput.focus();
                    rncInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return false;
                }
                if (rncHandler.rncStatus === 'ERROR' || rncHandler.rncStatus === 'INVALID' || rncHandler.rncStatus === 'INVALIDO') {
                    Swal.fire({
                        icon: 'error',
                        title: 'No pudimos validar',
                        text: 'No pudimos validar la cédula/RNC. Verifica el número e intenta nuevamente.',
                        confirmButtonColor: '#c0392b'
                    });
                    rncInput.focus();
                    return false;
                }
                if (!rncHandler.rncValidado) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Validación pendiente',
                        text: 'Espera a que aparezca la confirmación verde antes de continuar.',
                        confirmButtonColor: '#24125F'
                    });
                    rncInput.focus();
                    rncInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return false;
                }
            } else {
                console.warn('⚠️ FormFieldHandler no encontrado - saltando validación de BD');
            }
        }
        
        // Validar selección de empresas
        if (window.empresasSeleccionadas && window.empresasSeleccionadas.length === 0) {
            Swal.fire('Error', 'Debe seleccionar al menos una empresa', 'warning');
            document.getElementById('toggle_empresas_btn')?.focus();
            return false;
        }
        
        // Guardar datos del representante en localStorage (solo para empresa)
        if (!esPersonaIndividual) {
            this.saveRepresentativeData();
        }
        
        return true;
    }
    
    validatePanel2() {
        const tipoBancario = document.getElementById('tipo_bancario')?.value;
        
        if (tipoBancario === 'local') {
            return this.validateLocalBankingFields();
        } else {
            return this.validateForeignBankingFields();
        }
    }
    
    validateLocalBankingFields() {
        const camposLocal = document.getElementById('camposLocal');
        if (!camposLocal) return false;
        
        const inputs = camposLocal.querySelectorAll('input[required], select[required]');
        for (const input of inputs) {
            if (!input.checkValidity()) {
                input.reportValidity();
                return false;
            }
        }
        
        // Validar texto sin números
        const textFields = ['nombre_banco', 'nombre_titular'];
        for (const fieldId of textFields) {
            const field = document.getElementById(fieldId);
            if (field && !validarTextoSinNumeros(field.value.trim())) {
                Swal.fire('Error', `El campo "${fieldId.replace(/_/g, ' ')}" no debe contener números`, 'warning');
                field.focus();
                return false;
            }
        }
        
        // Validar numéricos (solo si tienen valor; vacíos ya fueron capturados como required)
        const numberFields = ['rnc_titular', 'numero_cuenta'];
        for (const fieldId of numberFields) {
            const field = document.getElementById(fieldId);
            if (field && field.value.trim() && !validarNumerosSolo(field.value.trim())) {
                Swal.fire('Error', `El campo "${fieldId.replace(/_/g, ' ')}" solo debe contener números`, 'warning');
                field.focus();
                return false;
            }
        }
        
        return true;
    }
    
    validateForeignBankingFields() {
        const camposExtranjera = document.getElementById('camposExtranjera');
        if (!camposExtranjera) return false;
        
        const inputs = camposExtranjera.querySelectorAll('input[required]');
        for (const input of inputs) {
            if (!input.checkValidity()) {
                input.reportValidity();
                return false;
            }
        }
        
        // Validar texto sin números en Name y Bank Name
        const textFields = ['foreign_name', 'foreign_bank_name'];
        for (const fieldId of textFields) {
            const field = document.getElementById(fieldId);
            if (field && !validarTextoSinNumeros(field.value.trim())) {
                Swal.fire('Error', `Field "${fieldId.replace(/foreign_/g, '').replace(/_/g, ' ')}" must not contain numbers`, 'warning');
                field.focus();
                return false;
            }
        }
        
        // Validar ABA No. numérico
        const abaField = document.getElementById('foreign_aba_no');
        if (abaField && !validarNumerosSolo(abaField.value.trim())) {
            Swal.fire('Error', 'Field "ABA No." must be numeric', 'warning');
            abaField.focus();
            return false;
        }
        
        // Validar formato SWIFT
        const swiftField = document.getElementById('foreign_swift_code');
        if (swiftField && !/^[A-Z0-9]{8,11}$/i.test(swiftField.value.trim())) {
            Swal.fire('Error', 'Field "Swift Code" format is invalid', 'warning');
            swiftField.focus();
            return false;
        }
        
        return true;
    }
    
    saveRepresentativeData() {
        const fields = ['representante_nombre', 'representante_telefono', 'representante_correo'];
        fields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                localStorage.setItem(fieldId, field.value);
            }
        });
    }
    
    restoreRepresentativeData() {
        const fields = ['representante_nombre', 'representante_telefono', 'representante_correo'];
        fields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.value = localStorage.getItem(fieldId) || '';
            }
        });
    }
}
