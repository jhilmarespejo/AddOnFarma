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
            c.fecha_nacimiento, c.fecha_creacion, c.correo, c.telefono, t.contrato
        FROM clientes c 
        LEFT JOIN temp t ON t.id_contratante = c.id   
        WHERE t.id = '$id_temp'
    ";
    
    $result = ejecutarConsulta($sql);
    $cliente = $result->fetch_assoc(); // Si usas MySQLi
    
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
$prefijo = explode('-', $cliente['contrato'])[0];
$anexoPath = "../files/anexos/" . $prefijo . "_ANEXO.pdf";
if (!file_exists($anexoPath)) {
    echo "El anexo no está disponible: " . basename($anexoPath);
    exit;
}
$archivos['anexo'] = $anexoPath;

// 2.3 Manual del cliente
$manualPath = "../files/anexos/ManualCliente.pdf";
if (!file_exists($manualPath)) {
    echo "El manual del cliente no está disponible.";
    exit;
}
$archivos['manual'] = $manualPath;

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
        $mail->Username = 'jespejo@innovasalud.bo';
        $mail->Password = 'Prueba12345$';
        $mail->Port = 587;
        
        // Remitente y destinatario
        $mail->setFrom('jespejo@innovasalud.bo', 'Innovasalud');
        $mail->addAddress($cliente['correo'], $cliente['nombres'] . ' ' . $cliente['ap_paterno']);
        
        // Contenido del correo
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'INNOVASALUD - Documentos de su contrato';
        
        $cuerpo = '<!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
            <title>Documentos del Contrato - InnovaSalud</title>
        </head>
        <body style="font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px;">
                <tr>
                    <td align="center">
                        <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">
                            <tr style="background-color: #00587d;">
                                <td align="center" style="padding: 20px;">
                                    <img src="https://www.innovasalud.bo/contratos_test/mailer/img/LogoInnovaSM.jpg" alt="InnovaSalud" width="180" style="display: block; margin-bottom: 10px;">
                                    <h1 style="color: #ffffff; margin: 0; font-size: 26px;">Documentos de su Contrato</h1>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 20px; color: #333333; font-size: 16px;">
                                    <p>Estimado(a) <strong>' . $cliente['nombres'] . ' ' . $cliente['ap_paterno'] . '</strong>,</p>
                                    <p>Adjuntamos los documentos relacionados con su contrato de salud:</p>
                                    <ul style="padding-left: 20px;">
                                        <li><strong>Contrato firmado</strong></li>
                                        <li><strong>Anexo del contrato</strong></li>
                                        <li><strong>Manual del cliente</strong></li>
                                    </ul>
                                    <p>Estamos a su disposición para cualquier consulta o aclaración.</p>
                                    <p><strong>Atentamente,<br>El equipo de InnovaSalud</strong></p>
                                </td>
                            </tr>
                            <tr style="background-color: #e6f2f7;">
                                <td align="center" style="padding: 15px; font-size: 14px; color: #555555;">
                                    <p>
                                        <a href="https://www.innovasalud.bo" style="color: #00587d; text-decoration: none;">www.innovasalud.bo</a> &nbsp; | &nbsp;
                                        <a href="https://wa.me/59176503333" style="color: #00587d; text-decoration: none;">+591 765 03333</a>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
        
        $mail->Body = $cuerpo;
        
        // Adjuntar archivos
        $mail->addAttachment($archivos['contrato'], "Contrato_" . $cliente['num_documento'] . ".pdf");
        $mail->addAttachment($archivos['anexo'], "Anexo_Contrato.pdf");
        $mail->addAttachment($archivos['manual'], "Manual_del_Cliente.pdf");
        
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $mail->send();
        return "Documentos enviados por correo correctamente a " . $cliente['correo'];
        //return "Simulación exitosa: documentos preparados para enviar a " . $cliente['correo'];
        
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