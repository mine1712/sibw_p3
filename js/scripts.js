// function enviarComentario() {
//     let datos = new FormData();
//     datos.append('nombre', document.getElementById('nombre').value);
//     datos.append('email', document.getElementById('email').value);
//     datos.append('texto', document.getElementById('comentario-texto').value);
//     datos.append('id_noticia', document.getElementById('id_noticia').value);

//     fetch('noticia.php?id=' + document.getElementById('id_noticia').value, {
//         method: 'POST',
//         headers: {
//             'X-Requested-With': 'XMLHttpRequest'
//         },
//         body: datos
//     })
//     .then(response => response.text())
//     .then(data => {
//         if (data.trim() === "OK") {
            
//             var nombre = document.getElementById('nombre').value;
//             var texto = document.getElementById('comentario-texto').value;
//             var fecha = "Ahora mismo";
            
//             agregarComentarioAlDOM(nombre, fecha, texto);
            
            
//             document.getElementById('form-comentario').reset();
//             document.getElementById('form-comentario').classList.add('oculto');
//             document.getElementById('btn-nuevo-comentario').classList.remove('oculto');
//         } else {
//             alert("Error al guardar el comentario.");
//         }
//     });
// }