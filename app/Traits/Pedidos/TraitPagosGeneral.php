<?php

namespace App\Traits\Pedidos;

use App\Models\Models\Pedidos\PedidosDocumentosLiq;
use DB;
use Illuminate\Support\Facades\Http;
trait TraitPagosGeneral
{
    public function PagosFacturacion($contrato){
        $query = DB::SELECT("SELECT lq.*, pd.archivo,pd.url,
        CONCAT(u.nombres,' ', u.apellidos) AS distribuidor_usuario,
        dt.saldo_actual, tp.tip_pag_nombre,pd.tipo_Pago
        FROM 1_4_documento_liq lq
        LEFT JOIN verificaciones_pagos_detalles pd ON lq.verificaciones_pagos_detalles_id = pd.id
        LEFT JOIN distribuidor_temporada dt ON pd.distribuidor_temporada_id = dt.id
        LEFT JOIN 1_4_tipo_pago tp ON tp.tip_pag_codigo = pd.tip_pag_codigo
        LEFT JOIN usuario u ON pd.idusuario = u.idusuario
        WHERE lq.ven_codigo = ?
        AND (lq.doc_ci like '%ANT%' OR lq.doc_ci like '%LIQ%')
        ORDER BY lq.doc_codigo DESC
        ",[$contrato]);
        return $query;
    }
}
