document.addEventListener('DOMContentLoaded', function () {

    var lista = document.getElementById('lista-comentarios');
    var sidebar = document.querySelector('.zona-comentarios');
    var btnNuevo = document.getElementById('btn-nuevo-comentario');
    var btnCancelar = document.getElementById('btn-cancelar');
    var form = document.getElementById('form-comentario');
    var area = document.getElementById('comentario-texto');
    var btnCerrar = document.getElementById('btn-cerrar-panel');

    
    var localidades = [];
    async function cargarLugares() {
        try {
           
            const respuesta = await fetch('noticia.php?ajax_lugares=1');
            localidades = await respuesta.json(); 
        } catch (e) {
            console.error("Error cargando lugares:", e);
        }
    }
    cargarLugares();

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

    area.oninput = function () {
        var textoActual = area.value;
        
        localidades.forEach(function(pueblo) {
            var nombrePueblo = pueblo.nombre;
            
            if (nombrePueblo) {
              
                var reg = new RegExp('\\b' + nombrePueblo + '\\b', 'gi');
                textoActual = textoActual.replace(reg, nombrePueblo.toUpperCase());
            }
        });
        area.value = textoActual;
    };

   
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

   
    form.onsubmit = function (e) {
        e.preventDefault();

        var nombreVal = document.getElementById('nombre').value.trim();
        var emailVal  = document.getElementById('email').value.trim();
        var textoVal  = area.value.trim(); 
        var idNoticia = sidebar.getAttribute('data-id-noticia');

       
        var expEmail = /^\w+([.-_+]?\w+)*@\w+([.-]?\w+)*(\.\w{2,10})+$/;
        if (nombreVal === "" || emailVal === "" || textoVal === "") {
            alert('Error: Todos los campos son obligatorios.');
            return;
        }
        if (!expEmail.test(emailVal)) {
            alert('Error: Email no válido.');
            return;
        }

        var datos = new FormData();
        datos.append('id_noticia', idNoticia);
        datos.append('nombre', nombreVal);
        datos.append('email', emailVal);
        datos.append('comentario', textoVal); 

        fetch('noticia.php', {
            method: 'POST',
            body: datos
        })
        .then(response => response.json())
        .then(res => {
            if (res.status === 'success') {
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