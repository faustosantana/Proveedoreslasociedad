/**
 * FormRegistrationApp - Aplicación principal del formulario de registro
 */
class FormRegistrationApp {
    constructor() {
        this.credentialsModal = null;
        this.panelManager = null;
        this.formFieldHandler = null;
        this.form = document.getElementById('registroForm');
        
        this.initialize();
    }
    
    initialize() {
        // Inicializar componentes
        this.credentialsModal = new CredentialsModal();
        this.panelManager = new PanelManager();
        this.formFieldHandler = new FormFieldHandler();
        
        // Configurar validación de envío del formulario
        this.setupFormSubmit();
    }
    
    validateDocuments() {
        const cards = document.querySelectorAll('#panel3 .doc-card');
        for (const card of cards) {
            if (card.style.display === 'none') continue;
            const input = card.querySelector('input[type="file"]');
            if (!input || !input.files?.length) {
                const title = card.querySelector('.doc-card-title')?.textContent || 'documento requerido';
                Swal.fire('Documento faltante', `Por favor adjunta: <b>${title}</b>`, 'warning');
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }
        }
        return true;
    }

    setupFormSubmit() {
        this.form?.addEventListener('submit', (e) => {
            // Validar que el modal de credenciales esté cerrado
            if (!this.credentialsModal.isHidden()) {
                e.preventDefault();
                mostrarAlertaLateralJS('Debe crear sus credenciales antes de enviar el formulario', 'error');
                return;
            }
            
            // Validar RNC si el campo está visible y tiene valor
            const rncInput = document.getElementById('rnc');
            const rncContainer = document.getElementById('rncContainer');
            if (rncInput && rncContainer && rncContainer.style.display !== 'none') {
                const rncValue = rncInput.value.trim();
                if (rncValue) {
                    const st = this.formFieldHandler.rncStatus;
                    if (this.formFieldHandler.rncValidando || st === 'pending') {
                        e.preventDefault();
                        mostrarAlertaLateralJS('Validando cédula/RNC. Espera la confirmación verde.', 'warning');
                        rncInput.focus();
                        return;
                    }
                    if (st === 'DUPLICATE' || st === 'DUPLICADO') {
                        e.preventDefault();
                        mostrarAlertaLateralJS('Esta cédula/RNC ya se encuentra registrada.', 'error');
                        rncInput.focus();
                        rncInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        return;
                    }
                    if (st === 'ERROR' || st === 'INVALID' || st === 'INVALIDO') {
                        e.preventDefault();
                        mostrarAlertaLateralJS('No pudimos validar la cédula/RNC. Verifica el número e intenta nuevamente.', 'error');
                        rncInput.focus();
                        return;
                    }
                    if (!this.formFieldHandler.rncValidado) {
                        e.preventDefault();
                        mostrarAlertaLateralJS('Espera a que aparezca la confirmación verde.', 'warning');
                        rncInput.focus();
                        return;
                    }
                }
            }
            
            // Validar correo de invitación (solo cuando se accede con token)
            const correoInvValidado = this.formFieldHandler.correoInvValidado;
            if (correoInvValidado === false) {
                e.preventDefault();
                const correoInput = document.getElementById('correo');
                mostrarAlertaLateralJS('El correo ingresado no coincide con el de la invitación. Por favor, verifique el campo.', 'error');
                correoInput?.focus();
                correoInput?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            // Validar documentos del panel 3
            if (!this.validateDocuments()) {
                e.preventDefault();
                return;
            }

            // Validar solo campos visibles
            const visibles = Array.from(this.form.querySelectorAll('input[required], select[required]'))
                .filter(el => el.offsetParent !== null);
            
            for (const el of visibles) {
                if (!el.checkValidity()) {
                    e.preventDefault();
                    el.reportValidity();
                    return;
                }
            }
            
            // Sincronizar campos de representante antes de enviar
            this.panelManager.restoreRepresentativeData();
            
            // Mostrar alerta de carga
            mostrarAlertaLateralJS('Formulario cargando...', 'info');
        });
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    window.formApp = new FormRegistrationApp();
});
