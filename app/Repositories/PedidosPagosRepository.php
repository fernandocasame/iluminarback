<?php
namespace App\Repositories;
use DB;
use App\Models\Models\Pagos\VerificacionPago;

class  PedidosPagosRepository extends BaseRepository
{
    public function __construct(VerificacionPago $modelo)
    {
        parent::__construct($modelo);
    }
    public function getPagosXID($verificacion_pago_id){
        $query = DB::SELECT("SELECT pd.* ,
        CONCAT(u.nombres,' ', u.apellidos) AS distribuidor_usuario,
        dt.saldo_actual, tp.tip_pag_nombre
        FROM verificaciones_pagos_detalles pd
        LEFT JOIN distribuidor_temporada dt ON pd.distribuidor_temporada_id = dt.id
        LEFT JOIN 1_4_tipo_pago tp ON tp.tip_pag_codigo = pd.tip_pag_codigo
        LEFT JOIN usuario u ON pd.idusuario = u.idusuario
        WHERE pd.verificacion_pago_id = ?
        ",[$verificacion_pago_id]);
        return $query;
    }
    public function tipoPagosFacturacion(){
        $query = DB::SELECT("SELECT * FROM 1_4_tipo_pago");
        return $query;
    }
}
?>
