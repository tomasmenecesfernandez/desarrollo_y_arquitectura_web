<?php
// chatbot.php  ->  va en la carpeta /backend (al lado de funcion_header.php)
// Recibe {"mensaje": "..."} por POST y devuelve {"respuesta": "..."} usando Google Gemini.
// Requisitos: PHP con la extensión cURL activada (no hay que instalar nada más).

// ===================== CONFIGURACIÓN: ACÁ VA TU API KEY =====================
const GEMINI_API_KEY = ''; // <-- tu key de Google AI Studio (empieza con AIza...)
const GEMINI_MODEL   = 'gemini-3.5-flash-lite';    // <-- modelo rápido y ideal para chatbots (también podés usar 'gemini-3.5-flash-lite')
const PROMPT_SISTEMA = 'Sos el asistente virtual de "Al Toque", una plataforma que conecta personas '
    . 'con profesionales de servicios para el hogar (plomería, electricidad, jardineria y limpieza). '
    . 'Ayudás a encontrar un profesional, a contratar, a dejar comentarios y a cambiar de plan y para le indicas como usar el programa, si quiere conseguir alguno de los profecionales decile que en la pagina principal tiene un buscador que le permite Buscar tanto por localidad como por profesión y escribe también le Aparecen las opciones ahí cuando cuando va escribiendo y selecciona zona o selecciona servicio que quiere contratar y con eso le van a aparecer todos los resultados cuando ya esté los resultados puede presionar en cualquiera de uno y puede contratarlo así bueno así con eso ya puede este contratarlo y ya puede hablarle con él por correo la persona ya va a tener su correo electrónico y se va a comunicar con él por medio de este y así es cómo quedaría contratado '
    . 'Respondé en español rioplatense, breve y amable. Si preguntan algo ajeno a la plataforma '
    . 'o a los servicios del hogar, decí amablemente que solo podés ayudar con eso.';
// ============================================================================

ini_set('display_errors', '0'); // para que ningún error de PHP rompa el JSON
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

function responderIA(string $texto): string
{
    $historial = $_SESSION['chatbot'] ?? [];

    // Armamos el historial en el formato que pide Gemini (roles: 'user' y 'model')
    $contents = [];
    foreach ($historial as $msg) {
        if (isset($msg['role'], $msg['text'])) {
            $contents[] = [
                'role'  => $msg['role'],
                'parts' => [['text' => $msg['text']]],
            ];
        }
    }
    $contents[] = [
        'role'  => 'user',
        'parts' => [['text' => $texto]],
    ];

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent';

    $payload = [
        'systemInstruction' => [
            'parts' => [['text' => PROMPT_SISTEMA]],
        ],
        'contents' => $contents,
        'generationConfig' => [
            'maxOutputTokens' => 400,
        ],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-goog-api-key: ' . GEMINI_API_KEY,
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);

    $crudo     = curl_exec($ch);
    $http      = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errorCurl = curl_error($ch);

    $datos     = json_decode((string) $crudo, true);
    $respuesta = $datos['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if ($http !== 200 || $respuesta === null) {
        $detalle = $datos['error']['message'] ?? $errorCurl ?: "HTTP $http - $crudo";
        throw new RuntimeException($detalle);
    }

    // Guardamos el intercambio para que el bot recuerde el contexto (últimos 10 mensajes)
    $historial[] = ['role' => 'user',  'text' => $texto];
    $historial[] = ['role' => 'model', 'text' => $respuesta];
    $_SESSION['chatbot'] = array_slice($historial, -10);

    return trim($respuesta);
}

// ---------- Endpoint ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

$mensaje = trim((string) (json_decode(file_get_contents('php://input'), true)['mensaje'] ?? ''));
if ($mensaje === '' || mb_strlen($mensaje) > 1000) {
    http_response_code(400);
    echo json_encode(['error' => 'Escribí un mensaje de hasta 1000 caracteres.']);
    exit;
}

try {
    echo json_encode(['respuesta' => responderIA($mensaje)], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $e) {
    http_response_code(502);
    echo json_encode(['error' => 'El asistente no está disponible en este momento. Probá de nuevo en un rato.']);
}
