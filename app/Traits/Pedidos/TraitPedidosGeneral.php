<?php

namespace App\Traits\Pedidos;

use App\Models\Models\Pedidos\PedidosDocumentosLiq;
use App\Models\Pedidos;
use DB;
use Illuminate\Support\Facades\Http;
trait TraitPedidosGeneral
{
    public function FacturacionGet($endpoint)
    {
        $dato = Http::get("http://186.4.218.168:9095/api/".$endpoint);
        return $JsonContrato = json_decode($dato, true);
    }
    public function FacturacionPost($endpoint,$data){
        $dato = Http::post("http://186.4.218.168:9095/api/".$endpoint,$data);
        return $JsonContrato = json_decode($dato, true);
    }

    public function getPedido($filtro,$parametro1=null,$parametro2=null){
        $resultado = DB::table('pedidos as p')
        ->select(DB::RAW('p.*,
        i.nombreInstitucion,i.zona_id,i.codigo_institucion_milton, c.nombre AS nombre_ciudad,
        CONCAT(u.nombres," ",u.apellidos) as responsable, CONCAT(u.nombres," ",u.apellidos) as asesor, u.cedula as cedula_asesor,u.iniciales,
        ph.estado as historicoEstado,ph.evidencia_cheque,ph.evidencia_pagare,
        IF(p.estado = 2,"Anulado","Activo") AS estadoPedido,
        (SELECT f.id_facturador from pedidos_asesores_facturador
        f where f.id_asesor = p.id_asesor  LIMIT 1) as id_facturador,
        i.ruc,i.nivel,i.tipo_descripcion,i.direccionInstitucion,i.telefonoInstitucion,
        (
            SELECT SUM(pa.venta_bruta) AS contador_alcance
            FROM pedidos_alcance pa
            WHERE pa.id_pedido = p.id_pedido
            AND pa.estado_alcance = "1"
            AND pa.venta_bruta > 0
        ) AS contador_alcance,
        (
            SELECT SUM(pa.total_unidades)  AS alcanceUnidades
            FROM pedidos_alcance pa
            WHERE pa.id_pedido = p.id_pedido
            AND pa.estado_alcance = "1"
            AND pa.venta_bruta > 0
        ) AS alcanceUnidades,
        (SELECT COUNT(*) FROM verificaciones v WHERE v.contrato = p.contrato_generado AND v.nuevo = "1" AND v.estado = "0") as verificaciones,
        (
            SELECT COUNT(a.id) AS contadorAlcanceAbierto
            FROM pedidos_alcance a
            LEFT JOIN pedidos ped ON ped.id_pedido = a.id_pedido
            WHERE  a.id_pedido = p.id_pedido
            AND a.estado_alcance  = "0"
            AND ped.estado = "1"
            AND a.venta_bruta > 0
        ) as contadorAlcanceAbierto,
        (
            SELECT COUNT(a.id) AS contadorAlcanceCerrado
            FROM pedidos_alcance a
            LEFT JOIN pedidos ped ON ped.id_pedido = a.id_pedido
            WHERE  a.id_pedido = p.id_pedido
            AND a.estado_alcance  = "1"
            AND ped.estado = "1"
        ) as contadorAlcanceCerrado,
        pe.periodoescolar as periodo,pe.codigo_contrato,
        CONCAT(uf.apellidos, " ",uf.nombres) as facturador,
        i.region_idregion as region,uf.cod_usuario,
        ph.fecha_generar_contrato,
        (p.TotalVentaReal - ((p.TotalVentaReal * p.descuento)/100)) AS ven_neta,
        (p.TotalVentaReal * p.descuento)/100 as valorDescuento
        '))
        ->leftjoin('usuario as u',          'p.id_asesor',          'u.idusuario')
        ->leftjoin('usuario as uf',         'p.id_usuario_verif',   'uf.idusuario')
        ->leftjoin('institucion as i',      'p.id_institucion',     'i.idInstitucion')
        ->leftjoin('ciudad as c',           'i.ciudad_id',          'c.idciudad')
        ->leftjoin('periodoescolar as pe',  'pe.idperiodoescolar',  'p.id_periodo')
        ->leftjoin('pedidos_historico as ph','p.id_pedido',         'ph.id_pedido')
        ->where('p.tipo','=','0');
        //fitlro por x id
        if($filtro == 0) { $resultado->where('p.id_pedido', '=', $parametro1); }
        //filtro x periodo
        if($filtro == 1) { $resultado->where('p.id_periodo','=',$parametro1)->where('p.estado','<>','0')->OrderBy('p.id_pedido','DESC'); }
        //filtro por asesor
        if($filtro == 2) { $resultado->where('p.id_periodo','=', $parametro1)->where('p.id_asesor','=',$parametro2)->OrderBy('p.id_pedido','DESC'); }
        //filtro facturador no admin
        if($filtro == 3) { $resultado->where('p.id_periodo','=', $parametro1)->where('p.id_asesor','=',$parametro2)->where('p.estado','<>','0')->OrderBy('p.id_pedido','DESC'); }
        $consulta = $resultado->get();
        return $consulta;
    }
    public function getVerificaciones($contrato){
        $query = DB::SELECT("SELECT * FROM verificaciones
            WHERE contrato =  '$contrato'
            and nuevo = '1'
            and estado = '0'
        ");
        return $query;
    }
    public function getAllBeneficiarios($id_pedido)
    {
        $query = DB::SELECT("SELECT  b.*,
        CONCAT(u.nombres, ' ',u.apellidos) AS beneficiario,
        u.cedula,u.nombres,u.apellidos,p.descuento,p.total_venta,p.contrato_generado
         FROM pedidos_beneficiarios b
         LEFT JOIN pedidos p ON b.id_pedido = p.id_pedido
         LEFT JOIN usuario u ON  b.id_usuario = u.idusuario
        WHERE b.id_pedido = '$id_pedido'
        ");
        return $query;
    }
    public function obtenerDocumentosLiq($contrato){
        $query = DB::SELECT("SELECT lq.*
        FROM 1_4_documento_liq lq
        WHERE lq.ven_codigo = ?
        AND (lq.doc_ci like '%ANT%' OR lq.doc_ci like '%LIQ%')
        ORDER BY lq.doc_codigo DESC
        ",[$contrato]);
        $datos  = [];
        foreach($query as $key => $item){
            $datos[$key] = [
                "venCodigo"                         => $item->ven_codigo,
                "docCodigo"                         => $item->doc_codigo,
                "docValor"                          => $item->doc_valor,
                "docNumero"                         => $item->doc_numero,
                "docNombre"                         => $item->doc_nombre,
                "docCi"                             => $item->doc_ci,
                "docCuenta"                         => $item->doc_cuenta,
                "docInstitucion"                    => $item->doc_institucion,
                "docTipo"                           => $item->doc_tipo,
                "docObservacion"                    => $item->doc_observacion,
                "docFecha"                          => $item->ven_codigo,
                "estVenCodigo"                      => $item->doc_fecha,
                "verificaciones_pagos_detalles_id"  => $item->verificaciones_pagos_detalles_id
            ];
        }
        return $datos;
    }
    //CONVENIOS
    public function obtenerConvenioInstitucionPeriodo($institucion,$periodo_id){
        $query = DB::SELECT("SELECT * FROM pedidos_convenios c
        WHERE c.institucion_id  = '$institucion'
        AND c.periodo_id        = '$periodo_id'
        AND (c.estado = '0' OR c.estado = '1')
        ");
        return $query;
    }
    public function getConvenioInstitucion($institucion){
        $query = DB::SELECT("SELECT * FROM pedidos_convenios c
        WHERE c.institucion_id = '$institucion'
        AND c.estado = '1'
        ");
        return $query;
    }
    public function updateDatosVerificacionPorIngresar($contrato,$estado){
        $query = Pedidos::Where('contrato_generado','=',$contrato)->update(['datos_verificacion_por_ingresar' => $estado]);
    }
    //asesores que tiene pedidos
    public function getAsesoresPedidos(){
        $query = DB::SELECT("SELECT DISTINCT p.id_asesor,
        CONCAT(u.nombres,' ',u.apellidos) as asesor
        FROM pedidos p
        LEFT JOIN usuario u ON p.id_asesor = u.idusuario
        WHERE p.estado = '1'
        ORDER BY u.nombres ASC
        ");
        return $query;
    }
}
