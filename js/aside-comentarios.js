document.addEventListener('DOMContentLoaded', function () {

    // 1. Elementos del DOM
    var lista = document.getElementById('lista-comentarios');
    var sidebar = document.querySelector('.zona-comentarios');
    var btnNuevo = document.getElementById('btn-nuevo-comentario');
    var btnCancelar = document.getElementById('btn-cancelar');
    var form = document.getElementById('form-comentario');
    var area = document.getElementById('comentario-texto');
    var btnCerrar = document.getElementById('btn-cerrar-panel');

    // 2. Carga de lugares para el filtro (desde el data-attribute de Twig)
    var lugaresData = sidebar.getAttribute('data-lugares');
    var localidades = [];
    try {
        localidades = JSON.parse(lugaresData || "[]");
    } catch (e) {
        console.error("Error con los lugares:", e);
    }

    // 3. Función para pintar en la lista
    function agregarComentarioAlDOM(nombre, fecha, mensaje) {
        var aviso = document.querySelector('.sin-comentarios');
        if (aviso) aviso.remove();

        var bloque = '<div class="comentario">' +
            '<p class="autor">' + nombre + '</p>' +
            '<p class="fecha-hora">' + fecha + '</p>' +
            '<p class="texto">' + mensaje + '</p>' +
            '</div>';
        
        lista.insertAdjacentHTML('afterbegin', bloque);
    }

    // 4. Filtro de palabras (Mayúsculas)
    area.oninput = function () {
        var textoActual = area.value;
        localidades.forEach(function(pueblo) {
            var reg = new RegExp('\\b' + pueblo + '\\b', 'gi');
            textoActual = textoActual.replace(reg, pueblo.toUpperCase());
        });
        area.value = textoActual;
    };

    // 5. Interfaz (Ratón y Botones)
    document.onmousemove = function (e) {
        var width = window.innerWidth;
        if (e.clientX > (width - 20)) sidebar.classList.add('visible');
        else if (e.clientX < (width - 400)) sidebar.classList.remove('visible');
    };

    btnCerrar.onclick = function () { sidebar.classList.remove('visible'); };
    btnNuevo.onclick = function () {
        btnNuevo.classList.add('oculto');
        form.classList.remove('oculto');
    };
    btnCancelar.onclick = function () {
        form.classList.add('oculto');
        btnNuevo.classList.remove('oculto');
        form.reset();
    };

    // 6. ENVÍO AL SERVIDOR (AJAX)
    form.onsubmit = function (e) {
        e.preventDefault();

        // CAPTURA DE DATOS
        var nombreVal = document.getElementById('nombre').value.trim();
        var emailVal  = document.getElementById('email').value.trim();
        var textoVal  = area.value.trim(); // area es el textarea
        var idNoticia = sidebar.getAttribute('data-id-noticia');

        // Validaciones
        var expEmail = /^\w+([.-_+]?\w+)*@\w+([.-]?\w+)*(\.\w{2,10})+$/;
        if (nombreVal === "" || emailVal === "" || textoVal === "") {
            alert('Error: Todos los campos son obligatorios.');
            return;
        }
        if (!expEmail.test(emailVal)) {
            alert('Error: Email no válido.');
            return;
        }

        // PREPARACIÓN DE DATOS PARA PHP
        var datos = new FormData();
        datos.append('id_noticia', idNoticia);
        datos.append('nombre', nombreVal);
        datos.append('email', emailVal);
        datos.append('comentario', textoVal); // DEBE coincidir con $_POST['comentario'] en PHP

        // PETICIÓN FETCH
        fetch('noticia.php', {
            method: 'POST',
            body: datos
        })
        .then(response => response.json())
        .then(res => {
            if (res.status === 'success') {
                // Crear fecha bonita para el usuario
                var ahora = new Date();
                var fechaActual = ahora.getDate().toString().padStart(2, '0') + "/" + 
                                  (ahora.getMonth() + 1).toString().padStart(2, '0') + "/" + 
                                  ahora.getFullYear() + " - " + 
                                  ahora.getHours().toString().padStart(2, '0') + ":" + 
                                  ahora.getMinutes().toString().padStart(2, '0');

                agregarComentarioAlDOM(nombreVal, fechaActual, textoVal);
                
                alert('¡Comentario enviado!');
                form.reset();
                form.classList.add('oculto');
                btnNuevo.classList.remove('oculto');
            } else {
                alert('Error: ' + res.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión con el servidor.');
        });
    };
});