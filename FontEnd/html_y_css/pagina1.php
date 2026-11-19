<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if(session_status()==PHP_SESSION_NONE){
    session_start();
}
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <link rel="stylesheet" href="css.css"/>
        <link rel="stylesheet" href="asistentebot.css"/> <!-- NUEVO: estilos del chatbot -->
        <title>Principal</title>
        <link
            href="https://fonts.googleapis.com/icon?family=Material+Icons"
            rel="stylesheet"
        />
    </head>
    <body class="pagina-principal">
        <?php include("../../backend/funcion_header.php");
 ?>
            <div class="div__mapa">
                <div class="hero__contenido">
                    <span class="hero__eyebrow">Servicios de confianza, cerca tuyo</span>
                    <h1>Encontrá ayuda para tu hogar, al toque.</h1>
                    <h2>Conectamos personas con profesionales listos para ayudarte.</h2>
                </div>
            </div>
        <div class="div">
            <div class="div_busqueda">
                <span>Filtros:  </span>
                <select class="details" name="" id="combo_filtro">
                    <option class="details__lista-item" value="Localidad">Localidad</option>
                    <option class="details__lista-item" value="Profesion">Profesión</option>
                </select>
            </div>

            <div class="div_busqueda__div_buscador">
                <label for="input_buscador_localidad" class="label-icono">
                    <span class="material-icons">search</span>
                </label>
                <input
                    class="input_buscador_localidad"
                    id="input_buscador_localidad"
                    type="text"
                    placeholder="Escribir la localidad"
                    list="lista_input"
                />
                <datalist id="lista_input">

                </datalist>
            </div>
        </div>
        <section class="servicios">
    <div class="servicios__cabecera">
        <div>
            <span class="servicios__etiqueta">Elegí con tranquilidad</span>
            <h2>Servicios disponibles</h2>
            <p>Profesionales para resolver lo que necesitás.</p>
        </div>
    </div>
    <div class="servicios__panel">
        <div class="tarjetas" id="contenedorServicios">
    <?php 
    $conexion = mysqli_connect("sql213.infinityfree.com","if0_42442157","Tomabenja666","if0_42442157_bd_altoque");
       $conexion->set_charset("utf8");
    if($_SESSION["id_cliente"]){
        $id_cliente=$_SESSION["id_cliente"];
    }else{
        $id_cliente=0;
    }
    $respuesta = mysqli_query($conexion, "SELECT *, publicaciones.id AS id_real_publicacion FROM publicaciones INNER JOIN usuarios ON usuarios.id = id_usuario WHERE disponible = true ");
    $publicaciones = mysqli_fetch_all($respuesta, MYSQLI_ASSOC);
    shuffle($publicaciones);
    usort($publicaciones, function($a, $b) {
        if ($a["rol"] === $b["rol"]) return 0;
        $dado = rand(1, 100);
        if ($a["rol"] === "usuario_premium") {
            return ($dado <= 60) ? -1 : 1;
        }
        
        if ($b["rol"] === "usuario_premium") {
            return ($dado <= 60) ? 1 : -1;
        }
        return 0;
    });
    foreach($publicaciones as $publicacion) {
        $clase = ($publicacion["rol"] == "usuario_premium") ? "tarjeta-servicio tarjeta_premium" : "tarjeta-servicio";
        ?>
        <article class="<?php echo $clase; ?>">
            <?php if($publicacion["rol"] == "usuario_premium") echo '<span class="span_premium">Premium</span>'; ?>
            <h3><?php echo $publicacion["nombre_completo"]; ?></h3>
            <p><strong>Oficio: </strong><?php echo $publicacion["categoria"]; ?></p>
            <p><strong>Zona: </strong><?php echo $publicacion["zona"]; ?></p>
            <p><strong>Descripción: </strong><?php echo $publicacion["descripcion"]; ?></p>
            <p class="estrellas">estrellas pendiente.</p>
            <?php if($publicacion["id_usuario"]!=$id_cliente){
            ?><button><a id="link_publicacion" href="perfil.php?id=<?php echo $publicacion["id_real_publicacion"]; ?>">Ver perfil</a></button>
       <?php }else {
        ?><p style="text-align: center;user-select: none;"><strong>Tu Publicación</strong></p><?php
       }?>
            </article>
    <?php } ?>
        </div>
    </div>
</section>

        <!-- NUEVO: chatbot (panel deslizable abajo a la derecha) -->
        <aside class="chatbot" id="chatbot" aria-label="Asistente virtual">
            <button class="chatbot__tab" id="chatbot-tab" type="button"
                    aria-expanded="false" aria-controls="chatbot-panel"
                    aria-label="Abrir asistente virtual">
                <span class="material-icons" id="chatbot-tab-icono" aria-hidden="true">chat</span>
            </button>
            <div class="chatbot__panel" id="chatbot-panel" inert>
                <div class="chatbot__header">Asistente Al Toque</div>
                <div class="chatbot__mensajes" id="chatbot-mensajes" role="log" aria-live="polite" aria-label="Conversación con el asistente"></div>
                <form class="chatbot__form" id="chatbot-form">
                    <label for="chatbot-input" class="chatbot__sr">Escribí tu consulta</label>
                    <input class="chatbot__input" id="chatbot-input" type="text" maxlength="1000"
                           placeholder="Escribí tu consulta..." autocomplete="off" required>
                    <button class="chatbot__enviar" id="chatbot-enviar" type="submit">Enviar</button>
                </form>
            </div>
        </aside>
        
        <script src="../javascript/script.js"></script>

        <!-- NUEVO: lógica del chatbot (AJAX con fetch, sin recargar la página) -->
        <script>
            (() => {
                // Ruta al backend, relativa a pagina1.php. Si tu carpeta backend queda en otro lado, cambiala acá.
                const ENDPOINT = 'asistentebot.php';

                const chatbot  = document.getElementById('chatbot');
                const tab      = document.getElementById('chatbot-tab');
                const icono    = document.getElementById('chatbot-tab-icono');
                const panel    = document.getElementById('chatbot-panel');
                const mensajes = document.getElementById('chatbot-mensajes');
                const form     = document.getElementById('chatbot-form');
                const input    = document.getElementById('chatbot-input');
                const enviar   = document.getElementById('chatbot-enviar');

                function agregar(texto, clase) {
                    const div = document.createElement('div');
                    div.className = 'chatbot__msg chatbot__msg--' + clase;
                    div.textContent = texto; // textContent y no innerHTML: evita inyección de HTML
                    mensajes.appendChild(div);
                    mensajes.scrollTop = mensajes.scrollHeight;
                    return div;
                }

                // Abre o cierra el panel (se desliza con la transición del CSS)
                function alternar(abrir) {
                    chatbot.classList.toggle('chatbot--abierto', abrir);
                    tab.setAttribute('aria-expanded', String(abrir));
                    tab.setAttribute('aria-label', abrir ? 'Cerrar asistente virtual' : 'Abrir asistente virtual');
                    icono.textContent = abrir ? 'chevron_right' : 'chat';
                    panel.inert = !abrir; // cerrado: no se puede tabular hacia adentro del panel
                    if (abrir) input.focus();
                }

                tab.addEventListener('click', () => alternar(!chatbot.classList.contains('chatbot--abierto')));

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && chatbot.classList.contains('chatbot--abierto')) {
                        alternar(false);
                        tab.focus();
                    }
                });

                form.addEventListener('submit', async (e) => {
                    e.preventDefault(); // evita que la página se recargue

                    const texto = input.value.trim();
                    if (!texto) return;

                    agregar(texto, 'usuario');
                    input.value = '';
                    input.disabled = enviar.disabled = true;
                    const pensando = agregar('El asistente está escribiendo...', 'bot chatbot__msg--pensando');

try {
    const res = await fetch(ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mensaje: texto }),
    });
    const crudo = await res.text();
    pensando.remove();
    let datos;
    try {
        datos = JSON.parse(crudo);
    } catch {
        throw new Error('Error ' + res.status + ': ' + crudo.slice(0, 200));
    }
    if (!res.ok) throw new Error(datos.error || 'Ocurrió un error inesperado.');
    agregar(datos.respuesta, 'bot');
} catch (err) {
    pensando.remove();
    agregar(err.message, 'error');
} finally {
                        input.disabled = enviar.disabled = false;
                        input.focus();
                    }
                });

                agregar('¡Hola! Soy el asistente de Al Toque. ¿En qué te puedo ayudar?', 'bot');
            })();
        </script>
    </body>
</html>
