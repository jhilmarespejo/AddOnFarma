<?php
// CONEXIÓN A LA BASE DE DATOS (ya deberías tenerla)
require_once "../config/Conexion.php";

// Consultar los IDs desde la tabla temp
$sql = "SELECT id FROM temp WHERE cobranza = 'PENDIENTE' and codigo_plan = 'PC0088' LIMIT 60";
// O si quieres todos los registros recientes:
// $sql = "SELECT id FROM temp ORDER BY id DESC LIMIT 60";

$resultado = ejecutarConsulta($sql);
$registrosIds = []; // Array para almacenar los IDs

// Llenar el array con los IDs de la base de datos
while ($fila = $resultado->fetch_assoc()) {
    $registrosIds[] = $fila['id'];
}

// Si no hay resultados, mostrar mensaje
if (empty($registrosIds)) {
    echo "<div class='alert alert-warning'>No hay registros para procesar</div>";
    return;
}
?>

<!-- Mostrar cantidad de registros -->
<div class="alert alert-info">
    <strong>Total de registros encontrados: <?php echo count($registrosIds); ?></strong>
</div>

<form id="formProcesamiento">
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th width="50px">
                    <input type="checkbox" id="selectAll">
                </th>
                <th>ID</th>
                <th>Cliente</th>
                <th>Documento</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Volver a consultar pero con más datos para mostrar en la tabla
            $sql_detalle = "SELECT 
                t.id, 
                c.nombres, 
                c.ap_paterno, 
                c.num_documento,
                t.cobranza,
                t.facturacion
            FROM temp t 
            LEFT JOIN clientes c ON t.id_contratante = c.id 
            WHERE t.id IN (" . implode(',', $registrosIds) . ") 
            ORDER BY t.id DESC";
            
            $result_detalle = ejecutarConsulta($sql_detalle);
            
            while ($registro = $result_detalle->fetch_assoc()) {
                $estado_color = '';
                if ($registro['cobranza'] == 'COBRADO') $estado_color = 'success';
                if ($registro['facturacion'] == 'FACTURADO') $estado_color = 'info';
                
                echo '<tr id="registro-' . $registro['id'] . '">';
                echo '<td><input type="checkbox" name="registros[]" value="' . $registro['id'] . '" class="registro-checkbox"></td>';
                echo '<td>' . $registro['id'] . '</td>';
                echo '<td>' . $registro['nombres'] . ' ' . $registro['ap_paterno'] . '</td>';
                echo '<td>' . $registro['num_documento'] . '</td>';
                echo '<td><span class="badge badge-' . $estado_color . '">' . $registro['cobranza'] . '</span></td>';
                echo '<td>';
                echo '<button type="button" class="btn btn-primary btn-sm" onclick="procesarIndividual(' . $registro['id'] . ')">';
                echo '<i class="fa fa-cog"></i> Individual';
                echo '</button>';
                echo '</td>';
                echo '</tr>';
            }
            ?>
        </tbody>
    </table>
    <div id="loading" style=""></div>
    
    <!-- <div class="btn-group">
        <button type="button" class="btn btn-primary" onclick="procesarSeleccionados()">
            <i class="fa fa-cogs"></i> Procesar Seleccionados
        </button>
        
        <button type="button" class="btn btn-success" onclick="procesarTodos(<?php echo json_encode($registrosIds); ?>)">
            <i class="fa fa-play"></i> Procesar Todos (<?php echo count($registrosIds); ?>)
        </button>
        
        <button type="button" class="btn btn-warning" onclick="procesarEnLotes(<?php echo json_encode($registrosIds); ?>)">
            <i class="fa fa-layer-group"></i> Procesar en Lotes de 10
        </button>
    </div> -->
</form>

<script>
    // Procesar un registro individual %%%
function procesarIndividual(registroId) {
    if (!confirm('¿Procesar registro ' + registroId + '?')) return;
    mostrarLoading('Procesando registro ' + registroId);
    
    $.post('../ajax/test_facturacion.php', {
        op: 'procesarFacturacionCompleta',
        registros: [registroId]
    }, function(response) {
        ocultarLoading();
        manejarRespuestaIndividual(response, registroId);
    });
}

// Procesar registros seleccionados con checkboxes
function procesarSeleccionados() {
    var registrosSeleccionados = [];
    $('.registro-checkbox:checked').each(function() {
        registrosSeleccionados.push(parseInt($(this).val()));
    });
    
    if (registrosSeleccionados.length === 0) {
        alert('Por favor selecciona al menos un registro');
        return;
    }
    
    if (!confirm('¿Procesar ' + registrosSeleccionados.length + ' registros seleccionados?')) return;
    
    procesarLote(registrosSeleccionados);
}

// Procesar TODOS los registros automáticamente
function procesarTodos(todosLosIds) {
    if (todosLosIds.length === 0) {
        alert('No hay registros para procesar');
        return;
    }
    
    if (!confirm('¿Estás seguro de procesar TODOS los ' + todosLosIds.length + ' registros?')) {
        return;
    }
    
    procesarLote(todosLosIds);
}

// Procesar en lotes más pequeños (recomendado para muchos registros)
function procesarEnLotes(todosLosIds, tamanoLote = 10) {
    if (todosLosIds.length === 0) {
        alert('No hay registros para procesar');
        return;
    }
    
    if (!confirm('¿Procesar ' + todosLosIds.length + ' registros en lotes de ' + tamanoLote + '?')) return;
    
    // Dividir en lotes
    var lotes = [];
    for (var i = 0; i < todosLosIds.length; i += tamanoLote) {
        lotes.push(todosLosIds.slice(i, i + tamanoLote));
    }
    
    procesarLotesSecuencialmente(lotes, 0);
}

// Función principal para procesar un lote
function procesarLote(ids) {
    mostrarLoading('Procesando ' + ids.length + ' registros...');
    
    $.post('../ajax/procesar_facturacion_completa.php', {
        op: 'procesarFacturacionCompleta',
        registros: ids
    }, function(response) {
        ocultarLoading();
        manejarRespuestaLote(response, ids);
    }).fail(function() {
        ocultarLoading();
        alert('Error de conexión');
    });
}

// Procesar lotes uno tras otro
function procesarLotesSecuencialmente(lotes, indiceLote) {
    if (indiceLote >= lotes.length) {
        alert('✅ Procesamiento de todos los lotes completado');
        location.reload(); // Recargar para ver cambios
        return;
    }
    
    var loteActual = lotes[indiceLote];
    mostrarLoading('Procesando lote ' + (indiceLote + 1) + '/' + lotes.length + ' (' + loteActual.length + ' registros)');
    
    $.post('../ajax/procesar_facturacion_completa.php', {
        op: 'procesarFacturacionCompleta',
        registros: loteActual
    }, function(response) {
        manejarRespuestaLote(response, loteActual);
        
        // Esperar 2 segundos y procesar siguiente lote
        setTimeout(function() {
            procesarLotesSecuencialmente(lotes, indiceLote + 1);
        }, 2000);
        
    }).fail(function() {
        alert('Error en lote ' + (indiceLote + 1) + ', continuando...');
        setTimeout(function() {
            procesarLotesSecuencialmente(lotes, indiceLote + 1);
        }, 2000);
    });
}

// Manejar respuesta individual
function manejarRespuestaIndividual(response, registroId) {
    try {
        var data = JSON.parse(response);
        var resultado = data.resultados[registroId];
        
        if (resultado.estado === 'success') {
            $('#registro-' + registroId).addClass('table-success');
            alert('✅ Registro ' + registroId + ' procesado exitosamente');
        } else {
            $('#registro-' + registroId).addClass('table-danger');
            alert('❌ Error en registro ' + registroId + ': ' + resultado.mensaje);
        }
    } catch (e) {
        alert('Error procesando respuesta: ' + e.message);
    }
}

// Manejar respuesta de lote
function manejarRespuestaLote(response, idsProcesados) {
    try {
        var data = JSON.parse(response);
        var exitosos = 0;
        var errores = 0;
        
        $.each(data.resultados, function(registroId, resultado) {
            if (resultado.estado === 'success') {
                exitosos++;
                $('#registro-' + registroId).addClass('table-success');
            } else {
                errores++;
                $('#registro-' + registroId).addClass('table-danger');
            }
        });
        
        alert('Lote procesado: ✅ ' + exitosos + ' éxitos, ❌ ' + errores + ' errores');
        
    } catch (e) {
        alert('Error procesando lote: ' + e.message);
    }
}

// Funciones auxiliares
function mostrarLoading(mensaje) {
    $('#loading').html('<i class="fa fa-spinner fa-spin"></i> ' + mensaje).show();
}

function ocultarLoading() {
    $('#loading').hide();
}

// Select all checkboxes
$('#selectAll').change(function() {
    $('.registro-checkbox').prop('checked', this.checked);
});
        </script>