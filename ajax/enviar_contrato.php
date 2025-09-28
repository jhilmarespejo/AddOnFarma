<?php
ob_start();
if (strlen(session_id()) < 1) {
    session_start();
}

header('Content-Type: text/plain');

// Cargar PHPMailer y tu conexión existente
require __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require __DIR__ . '/../vendor/phpmailer/src/SMTP.php';
require __DIR__ . '/../vendor/phpmailer/src/Exception.php';
require_once "../config/Conexion.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set('America/La_Paz');

$tipo = isset($_POST['tipo']) ? $_POST['tipo'] : '';
$id_temp = isset($_POST['id']) ? $_POST['id'] : '';

if (!$tipo || !$id_temp) {
    echo "Datos incompletos.";
    exit;
}

// Paso 1: Obtener datos del cliente y contrato usando tu conexión existente
try {
    $sql = "
        SELECT 
            c.ap_paterno, c.ap_materno, c.nombres, c.num_documento, c.extension, c.expedido, 
            c.fecha_nacimiento, c.fecha_creacion, c.correo, c.pais_nacimiento, c.telefono, t.contrato, c.canal
        FROM clientes c 
        LEFT JOIN temp t ON t.id_contratante = c.id   
        WHERE t.id = '$id_temp'
    ";
    
    $result = ejecutarConsulta($sql);
    $cliente = $result->fetch_assoc(); // Si usas MySQLi
    
    // var_dump($cliente['canal']); exit;
    // Si usas PDO en tu Conexion.php, sería:
    // $cliente = $result->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente) {
        echo "No se encontraron datos del cliente.";
        exit;
    }
    
} catch (Exception $e) {
    echo "Error al obtener datos del cliente: " . $e->getMessage();
    exit;
}

// Paso 2: Preparar los 3 archivos PDF
$archivos = [];

// 2.1 Contrato del cliente
$contratoPath = "../files/contratosfirmados/" . $cliente['contrato'] . "_" . $cliente['num_documento'] . ".pdf";
if (!file_exists($contratoPath)) {
    echo "El contrato no está disponible: " . basename($contratoPath);
    exit;
}
$archivos['contrato'] = $contratoPath;

// 2.2 Anexo - Extraer prefijo del contrato
// $prefijo = explode('-', $cliente['contrato'])[0];
// $anexoPath = "../files/anexos/" . $prefijo . "_ANEXO.pdf";
// if (!file_exists($anexoPath)) {
//     echo "El anexo no está disponible: " . basename($anexoPath);
//     exit;
// }
// $archivos['anexo'] = $anexoPath;

// 2.3 Manual del cliente
$manualPath = "../files/anexos/ManualCliente.pdf";
if (!file_exists($manualPath)) {
    echo "El manual del cliente no está disponible.";
    exit;
}
$archivos['manual'] = $manualPath;

// 2.4 Servicios_del_Plan
$serviciosPath = "../files/anexos/Servicios_del_Plan.pdf";
if (!file_exists($serviciosPath)) {
    echo "Servicios_del_Plan no está disponible.";
    exit;
}
$archivos['servicios_plan'] = $serviciosPath;



// Función para enviar por correo
function enviarPorCorreo($cliente, $archivos) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuración SMTP
        $mail->isSMTP();
        $mail->Host = 'mail.innovasalud.bo';
        $mail->SMTPAuth = true;
        $mail->SMTPDebug = 0;
        $mail->SMTPSecure = 'tls';
        $mail->Username = 'mailapp@innovasalud.bo';
        $mail->Password = 'Prueba12345$';
        $mail->Port = 587;
        
        // Remitente
        $mail->setFrom('mailapp@innovasalud.bo', 'Innovasalud');

        // Destinatario principal
        $mail->addAddress($cliente['correo'], $cliente['nombres'] . ' ' . $cliente['ap_paterno']);

        // Copia (CC) si el campo pais_nacimiento contiene un correo válido
        if (!empty($cliente['pais_nacimiento']) && filter_var($cliente['pais_nacimiento'], FILTER_VALIDATE_EMAIL)) {
            $mail->addCC($cliente['pais_nacimiento']);
        }
        // $mail->addBCC('mteran@innovasalud.bo');
        // $mail->addBCC('mlazarte@innovasalud.bo');
        $mail->addBCC('pluris.tj@gmail.com');
        
        
        // Contenido del correo
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'INNOVASALUD - Su contrato';

        $mail->addEmbeddedImage('../files/anexos/cuerpo-mail.jpg', 'cuerpoMailCID', 'cuerpo-mail.jpg');
        
        
        $cuerpo = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>InnovaSalud Contigo</title>
            </head>
            <body style="margin:0; padding:0;">
                <img src="cid:cuerpoMailCID" alt="InnovaSalud Contigo" style="width:100%; max-width:600px; display:block; margin:auto;">
            </body>
            </html>';

        $mail->isHTML(true);
        $mail->Body = $cuerpo;
        
        // Adjuntar archivos
        $mail->addAttachment($archivos['contrato'], "Contrato_" . $cliente['num_documento'] . ".pdf");
        if($cliente['canal'] == 'C002'){
            $mail->addAttachment($archivos['servicios_plan'], "Servicios_del_Plan.pdf");
        } else {
            $mail->addAttachment($archivos['anexo'], "Anexo_Contrato.pdf");
            $mail->addAttachment($archivos['manual'], "Manual_del_Cliente.pdf");
        }
        
        
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $mail->send();
        return "Documentos enviados por correo correctamente a " . $cliente['correo'];
        
    } catch (Exception $e) {
        return "Error al enviar por correo: " . $mail->ErrorInfo;
    }
}

// Función para enviar por WhatsApp
function enviarPorWhatsapp($cliente, $archivos) {
    // Por ahora, simulamos el envío exitoso
    return "Documentos preparados para envío por WhatsApp al " . $cliente['telefono'] . 
           ". Archivos listos: " . implode(", ", array_map('basename', $archivos));
}

// Ejecutar el envío según el tipo
if ($tipo === 'correo') {
    $resultado = enviarPorCorreo($cliente, $archivos);
    echo $resultado;
    
} elseif ($tipo === 'whatsapp') {
    $resultado = enviarPorWhatsapp($cliente, $archivos);
    echo $resultado;
    
} else {
    echo "Tipo de envío no reconocido.";
}

// Registrar envío en base de datos usando tu conexión existente
try {
    $archivos_str = implode(", ", array_map('basename', $archivos));
    $estado = strpos($resultado, 'Error') === false ? 'enviado' : 'error';
    
    $sql_registro = "
        INSERT INTO envio_contratos 
        (numero_documento, correo, telefono, archivos, tipo_envio, estado, mensaje) 
        VALUES (
            '" . $cliente['num_documento'] . "',
            '" . $cliente['correo'] . "',
            '" . $cliente['telefono'] . "',
            '" . $archivos_str . "',
            '" . $tipo . "',
            '" . $estado . "',
            '" . $resultado . "'
        )
    ";
    
    $result_registro = ejecutarConsulta($sql_registro);
    
    if (!$result_registro) {
        error_log("Error al registrar envío en la base de datos");
    }
    
} catch (Exception $e) {
    error_log("Error al registrar envío: " . $e->getMessage());
}

exit();
ob_end_flush();
?>