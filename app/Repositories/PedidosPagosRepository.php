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
        dt.saldo_actual
        FROM verificaciones_pagos_detalles pd
        LEFT JOIN distribuidor_temporada dt ON pd.distribuidor_temporada_id = dt.id 
        LEFT JOIN usuario u ON pd.idusuario = u.idusuario
        WHERE pd.verificacion_pago_id = ?
        ",[$verificacion_pago_id]);
        return $query;
    }
}
?>