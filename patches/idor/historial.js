function imprimirFactura(idFactura) {
  Swal.fire({
    title: '¿No Disponible :)?',
    text: 'Se generará un PDF con los detalles.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Sí, imprimir',

    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      const url = `php/imprimir.php?id=${encodeURIComponent(idFactura)}`;
      window.open(url, '_blank');
    }
  });
}

function confirmarEliminar(idFactura, csrfToken) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: 'Factura eliminada temporalmente. Se eliminará definitivamente en 15 días.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
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

