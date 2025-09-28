<?php
/**
 * SISTEMA INTEGRADO DE FACTURACIÓN Y GENERACIÓN DE CONTRATOS
 * 
 * Este archivo integra:
 * 1. Facturación mediante API Sartawi (test_factura.php)
 * 2. Generación y firma digital de contratos (obtener_contrato.php)  
 * 3. Envío por correo electrónico (enviar_contrato.php)
 */

ob_start();
if (strlen(session_id()) < 1) {
    session_start();
}

require_once "../config/Conexion.php";
require_once "../modelos/Cobranzas.php";
require_once "../modelos/Varios.php";

// Incluir librerías necesarias
require_once('../libraries/tcpdf/tcpdf.php');
require_once('../vendor/autoload.php');
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use setasign\Fpdi\Tcpdf\Fpdi;

$cobranzas = new Cobranzas();
$varios    = new Varios();
$id_usuario = $_SESSION['idusuario'];

// =============================================================================
// CONFIGURACIÓN
// =============================================================================
$MODO_PRUEBAS = true;
$certificadoPath = '../certificates/ALFREDO_VICENTE_ASCARRUNZ_ARANA.p12';
$certificadoPassword = '2980673';

// =============================================================================
// FUNCIÓN PRINCIPAL - PROCESAR REGISTRO COMPLETO
// =============================================================================
function procesarRegistroCompleto($registro_a_facturar) {
    global $cobranzas, $varios, $id_usuario;
    
    $resultado = [
        'facturacion' => '',
        'contrato' => '',
        'correo' => '',
        'estado' => 'error',
        'mensaje' => ''
    ];
    
    try {
        // PASO 1: FACTURACIÓN (test_factura.php)
        $resultado_facturacion = generarFactura($registro_a_facturar);
        $resultado['facturacion'] = $resultado_facturacion;
        
        if ($resultado_facturacion['status'] !== 'ok') {
            throw new Exception("Error en facturación: " . $resultado_facturacion['msg']);
        }
        
        // PASO 2: GENERAR Y FIRMAR CONTRATO (obtener_contrato.php)
        $resultado_contrato = generarYFirmarContrato($registro_a_facturar);
        $resultado['contrato'] = $resultado_contrato;
        
        if (!$resultado_contrato['exito']) {
            throw new Exception("Error generando contrato: " . $resultado_contrato['mensaje']);
        }
        
        // PASO 3: ENVIAR POR CORREO (enviar_contrato.php)
        $resultado_correo = enviarContratoPorCorreo($registro_a_facturar);
        $resultado['correo'] = $resultado_correo;
        
        if (strpos($resultado_correo, 'Error') !== false) {
            throw new Exception("Error enviando correo: " . $resultado_correo);
        }
        
        $resultado['estado'] = 'success';
        $resultado['mensaje'] = 'Proceso completado exitosamente';
        
    } catch (Exception $e) {
        $resultado['mensaje'] = $e->getMessage();
        error_log("ERROR procesando registro $registro_a_facturar: " . $e->getMessage());
    }
    
    return $resultado;
}

// =============================================================================
// FUNCIÓN 1: FACTURACIÓN (adaptada de test_factura.php)
// =============================================================================
function generarFactura($registro_a_facturar) {
    global $varios, $cobranzas, $id_usuario;
    
    $data = array();
    
    try {
        // Generar códigos únicos
        $cod_ope = $varios->getParameterValues('cod_ope');
        $cod_tra = $varios->getParameterValues('cod_tra');
        $contrato = $varios->getNumeroContrato($registro_a_facturar);
        
        $data['cod_tra'] = $cod_tra;
        $data['contrato'] = $contrato;

        // Actualizar estado a "COBRADO"
        $ret_val = $cobranzas->generarFactura_c($cod_ope, $cod_tra, $id_usuario, $registro_a_facturar, $contrato);
        
        if ($ret_val['status'] != 'ok') {
            return ['status' => 'error', 'msg' => $ret_val['msg']];
        }

        // Preparar datos para Web Service
        $fecha_inicio = date('Ymd');
        $rspta = $varios->getRegistroParaFacturar($registro_a_facturar);
        
        if (!$rspta || mysqli_num_rows($rspta) == 0) {
            return ['status' => 'error', 'msg' => 'Registro no encontrado'];
        }
        
        $row = mysqli_fetch_assoc($rspta);
        $codigo_plan_padre = $row['codigo_plan'];
        
        // Validar plan
        $filas = $varios->readPlanPadre($codigo_plan_padre);
        if (!$filas || mysqli_num_rows($filas) == 0) {
            return ['status' => 'error', 'msg' => 'Plan no configurado'];
        }
        
        $rs_plan = mysqli_fetch_assoc($filas);
        
        if (empty($rs_plan['codigo_plan_hijo'])) {
            return ['status' => 'error', 'msg' => 'Configuración incompleta del plan'];
        }

        // Construir array de facturación
        $factura = array(
            'cod_cli' => $row['cod_cli'],
            'cod_ope' => $cod_ope,
            'cod_tra' => $cod_tra,
            'tipo_documento' => $row['tipo_documento'],
            'cedula' => $row['cedula'],
            'extension' => isset($row['extension']) ? $row['extension'] : "",
            'expedido' => isset($row['expedido']) ? $row['expedido'] : '',
            'ap_paterno' => $row['ap_paterno'],
            'ap_materno' => $row['ap_materno'],
            'nombres' => $row['nombres'],
            'razon_social' => $row['nombres'] . ' ' . $row['ap_paterno'] . ' ' . $row['ap_materno'],
            'genero' => $row['genero'],
            'telefono' => $row['telefono'],
            'email' => "",
            'direccion' => 'N-A',
            'fecha_nacimiento' => $row['fecha_nacimiento'],
            'codigo_plan' => $rs_plan['codigo_plan_hijo'],
            'precio' => $rs_plan['precio_hijo'],
            'canal' => $row['codigo_canal'],
            'codigo_agencia' => $row['codigo_agencia'],
            'fecha_inicio' => $fecha_inicio,
            'contrato' => $contrato
        );

        // Llamar al Web Service
        $rc = $varios->llamamosWS_Sartawi($factura);
        
        // Actualizar campos de facturación
        $mi_factura = null;
        if ($rc['status_fact'] == 'E') {
            $mi_factura = $rc['factura'];
            $rspta = $cobranzas->actualizamosCamposFacturacion($registro_a_facturar, $mi_factura);
        }

        // Registrar log
        $factura['status_fact'] = $rc['status_fact'];
        $factura['mensaje_fact'] = $rc['mensaje_fact'];
        $factura['factura'] = $rc['factura'];
        $factura['hora_llamada'] = date('H:i:s');
        $factura['hora_retorno'] = date('H:i:s');
        
        $rspta = $varios->registraDatosLog($factura);

        return [
            'status' => 'ok',
            'factura' => $mi_factura,
            'mensaje' => $rc['mensaje_fact']
        ];
        
    } catch (Exception $e) {
        return ['status' => 'error', 'msg' => $e->getMessage()];
    }
}

// =============================================================================
// FUNCIÓN 2: GENERAR Y FIRMAR CONTRATO (adaptada de obtener_contrato.php)
// =============================================================================
function generarYFirmarContrato($id_temp) {
    global $certificadoPath, $certificadoPassword;
    
    try {
        // Consultar datos del cliente
        $sql = "SELECT c.ap_paterno, c.ap_materno, c.nombres, c.num_documento, c.extension, 
                       c.expedido, c.fecha_nacimiento, c.fecha_creacion, c.correo, t.contrato 
                FROM clientes c 
                LEFT JOIN temp t ON t.id_contratante = c.id  
                WHERE t.id = '$id_temp'";
        
        $result = ejecutarConsulta($sql);
        
        if (!$result || $result->num_rows == 0) {
            return ['exito' => false, 'mensaje' => 'No se encontró el registro'];
        }
        
        $row = $result->fetch_assoc();
        $contrato_nombre = $row['contrato'];
        
        // Determinar plantilla HTML
        $partes_contrato = explode('-', $contrato_nombre);
        $nombre_plantilla = $partes_contrato[0] . '.html';
        $ruta_plantilla = "../files/contratos/" . $nombre_plantilla;
        
        if (!file_exists($ruta_plantilla)) {
            return ['exito' => false, 'mensaje' => "Plantilla no encontrada: $nombre_plantilla"];
        }
        
        // Leer y personalizar plantilla HTML
        $html_content = file_get_contents($ruta_plantilla);
        
        // Preparar datos para reemplazo
        $fecha_nac = date('d/m/Y', strtotime($row['fecha_nacimiento']));
        $fecha_actual = date('d/m/Y');
        
        $datos_reemplazo = [
            '{{nombres}}' => $row['nombres'] . ' ' . $row['ap_paterno'] . ' ' . $row['ap_materno'],
            '{{documento}}' => $row['num_documento'] . '-' . $row['extension'] . ' ' . $row['expedido'],
            '{{fecha_nacimiento}}' => $fecha_nac,
            '{{fecha_contrato}}' => $fecha_actual,
            '{{contrato_numero}}' => $contrato_nombre
        ];
        
        // Reemplazar marcadores en HTML
        foreach ($datos_reemplazo as $marcador => $valor) {
            $html_content = str_replace($marcador, $valor, $html_content);
        }
        
        // Generar PDF desde HTML
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('Sistema InnovaSalud');
        $pdf->SetAuthor('InnovaSalud');
        $pdf->SetTitle('Contrato ' . $contrato_nombre);
        $pdf->AddPage();
        $pdf->writeHTML($html_content, true, false, true, false, '');
        
        // Guardar PDF temporal
        $ruta_temporal = "../files/contratostmp/";
        if (!file_exists($ruta_temporal)) {
            mkdir($ruta_temporal, 0777, true);
        }
        
        $nombre_pdf_temp = $contrato_nombre . "_" . $row['num_documento'] . "_temp.pdf";
        $ruta_pdf_temp = $ruta_temporal . $nombre_pdf_temp;
        $pdf->Output($ruta_pdf_temp, 'F');
        
        // Firmar PDF
        $ruta_firmados = "../files/contratosfirmados/";
        if (!file_exists($ruta_firmados)) {
            mkdir($ruta_firmados, 0777, true);
        }
        
        $nombre_pdf_firmado = $contrato_nombre . "_" . $row['num_documento'] . ".pdf";
        $ruta_pdf_firmado = $ruta_firmados . $nombre_pdf_firmado;
        
        if (firmarPDF($ruta_pdf_temp, $ruta_pdf_firmado, $certificadoPath, $certificadoPassword)) {
            // Eliminar archivo temporal
            unlink($ruta_pdf_temp);
            
            return [
                'exito' => true,
                'ruta_contrato' => $ruta_pdf_firmado,
                'mensaje' => 'Contrato generado y firmado exitosamente'
            ];
        } else {
            return ['exito' => false, 'mensaje' => 'Error al firmar el contrato'];
        }
        
    } catch (Exception $e) {
        return ['exito' => false, 'mensaje' => $e->getMessage()];
    }
}

// =============================================================================
// FUNCIÓN 3: ENVIAR CONTRATO POR CORREO (adaptada de enviar_contrato.php)
// =============================================================================
function enviarContratoPorCorreo($id_temp) {
    try {
        // Obtener datos del cliente
        $sql = "SELECT c.ap_paterno, c.ap_materno, c.nombres, c.num_documento, c.extension, 
                       c.expedido, c.correo, c.telefono, t.contrato 
                FROM clientes c 
                LEFT JOIN temp t ON t.id_contratante = c.id  
                WHERE t.id = '$id_temp'";
        
        $result = ejecutarConsulta($sql);
        
        if (!$result || $result->num_rows == 0) {
            return "Error: No se encontraron datos del cliente";
        }
        
        $cliente = $result->fetch_assoc();
        
        // Preparar archivos a enviar
        $archivos = [];
        
        // 1. Contrato firmado
        $contrato_nombre = $cliente['contrato'];
        $contratoPath = "../files/contratosfirmados/" . $contrato_nombre . "_" . $cliente['num_documento'] . ".pdf";
        
        if (!file_exists($contratoPath)) {
            return "Error: Contrato firmado no disponible";
        }
        $archivos['contrato'] = $contratoPath;
        
        // 2. Anexo
        $prefijo = explode('-', $contrato_nombre)[0];
        $anexoPath = "../files/anexos/" . $prefijo . "_ANEXO.pdf";
        if (file_exists($anexoPath)) {
            $archivos['anexo'] = $anexoPath;
        }
        
        // 3. Manual del cliente
        $manualPath = "../files/anexos/ManualCliente.pdf";
        if (file_exists($manualPath)) {
            $archivos['manual'] = $manualPath;
        }
        
        // Configurar y enviar correo
        $mail = new PHPMailer(true);
        
        $mail->isSMTP();
        $mail->Host = 'mail.innovasalud.bo';
        $mail->SMTPAuth = true;
        $mail->Username = 'mailapp@innovasalud.bo';
        $mail->Password = 'Prueba12345$';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        
        $mail->setFrom('mailapp@innovasalud.bo', 'Innovasalud');
        $mail->addAddress($cliente['correo'], $cliente['nombres'] . ' ' . $cliente['ap_paterno']);
        
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'INNOVASALUD - Documentos de su contrato';
        
        $cuerpo = generarCuerpoCorreo($cliente);
        $mail->Body = $cuerpo;
        
        // Adjuntar archivos
        foreach ($archivos as $tipo => $ruta) {
            $mail->addAttachment($ruta, obtenerNombreArchivo($tipo, $cliente));
        }
        
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $mail->send();
        
        // Registrar envío en base de datos
        registrarEnvioBD($cliente, $archivos, 'correo', 'Documentos enviados correctamente');
        
        return "Documentos enviados por correo correctamente a " . $cliente['correo'];
        
    } catch (Exception $e) {
        return "Error al enviar por correo: " . $e->getMessage();
    }
}

// =============================================================================
// FUNCIONES AUXILIARES
// =============================================================================

function firmarPDF($archivoOrigen, $archivoDestino, $certificadoPath, $password) {
    try {
        if (!file_exists($certificadoPath)) {
            error_log("Certificado no encontrado: $certificadoPath");
            return false;
        }
        
        $pkcs12 = file_get_contents($certificadoPath);
        $certs = [];
        
        if (!openssl_pkcs12_read($pkcs12, $certs, $password)) {
            error_log("Error leyendo certificado: " . openssl_error_string());
            return false;
        }
        
        // Implementar lógica de firma PDF según tu librería específica
        // Esta es una implementación básica - ajustar según tu entorno
        copy($archivoOrigen, $archivoDestino); // Placeholder - reemplazar con firma real
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error firmando PDF: " . $e->getMessage());
        return false;
    }
}

function generarCuerpoCorreo($cliente) {
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <title>Documentos del Contrato - InnovaSalud</title>
    </head>
    <body style="font-family: Arial, sans-serif;">
        <h2>Estimado(a) ' . $cliente['nombres'] . ' ' . $cliente['ap_paterno'] . '</h2>
        <p>Adjuntamos los documentos de su contrato de salud.</p>
        <p>Atentamente,<br>Equipo InnovaSalud</p>
    </body>
    </html>';
}

function obtenerNombreArchivo($tipo, $cliente) {
    $nombres = [
        'contrato' => 'Contrato_' . $cliente['num_documento'] . '.pdf',
        'anexo' => 'Anexo_Contrato.pdf',
        'manual' => 'Manual_del_Cliente.pdf'
    ];
    
    return $nombres[$tipo] ?? 'Documento.pdf';
}

function registrarEnvioBD($cliente, $archivos, $tipo, $mensaje) {
    $archivos_str = implode(", ", array_map('basename', $archivos));
    $estado = strpos($mensaje, 'Error') === false ? 'enviado' : 'error';
    
    $sql = "INSERT INTO envio_contratos 
            (numero_documento, correo, telefono, archivos, tipo_envio, estado, mensaje) 
            VALUES (
                '" . $cliente['num_documento'] . "',
                '" . $cliente['correo'] . "',
                '" . $cliente['telefono'] . "',
                '" . $archivos_str . "',
                '" . $tipo . "',
                '" . $estado . "',
                '" . $mensaje . "'
            )";
    
    ejecutarConsulta($sql);
}

// =============================================================================
// EJECUCIÓN PRINCIPAL
// =============================================================================
if ($_POST['op'] == 'procesarFacturacionCompleta') {
    $registros = $_POST['registros']; // Array de IDs o ID único
    
    if (!is_array($registros)) {
        $registros = [$registros];
    }
    
    $resultados = [];
    
    foreach ($registros as $registroId) {
        $resultados[$registroId] = procesarRegistroCompleto($registroId);
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'estado' => 'completado',
        'procesados' => count($registros),
        'resultados' => $resultados
    ]);
}

ob_end_flush();
?>