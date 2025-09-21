<?php
ob_start();
if (strlen(session_id()) < 1) {
    session_start();
}

header('Content-Type: text/plain');

$tipo = isset($_POST['tipo']) ? $_POST['tipo'] : '';
$id   = isset($_POST['id']) ? $_POST['id'] : '';

//var_dump($tipo, $id);exit;

if (!$tipo || !$id) {
    echo "Datos incompletos.";
    exit;
}

// Simulación de envío
$archivo = "../files/contratosfirmados/contrato_" . basename($id) . "_firmado.pdf";

if (!file_exists($archivo)) {
    echo "El contrato no está disponible.";
    exit;
}

if ($tipo === 'correo') {
    // Aquí iría la lógica real de envío por correo
    echo "Contrato enviado por correo correctamente.";
} elseif ($tipo === 'whatsapp') {
    // Aquí iría la lógica real de envío por WhatsApp
    echo "Contrato enviado por WhatsApp correctamente.";
} else {
    echo "Tipo de envío no reconocido.";
}
exit();
ob_end_flush();
?>
