<?php
namespace App\Repositories;
use DB;
use App\Models\Models\Pagos\VerificacionPago;
use App\Models\Models\Pedidos\PedidosDocumentosLiq;
use App\Models\Verificacion;
use App\Traits\Pedidos\TraitPedidosGeneral;
use PhpParser\Node\Expr\Cast\Object_;

class  PedidosPagosRepository extends BaseRepository
{
    use TraitPedidosGeneral;
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
    public function savePagoDocumentoLiq($ArrayPagos,$objectPadrePago,$contrato){
        // return $ArrayPagos;
        foreach($ArrayPagos as $key => $item){
            $id = 0;
            $id = $item["id"];
            $fecha = date("Y-m-d H:i:s");
            //valido que no haya sido creado antes
            $query = PedidosDocumentosLiq::Where('verificaciones_pagos_detalles_id',$id)->get();
            if(count($query) == 0){
                $nombre = $objectPadrePago->nombres . " ". $objectPadrePago->apellidos;
                $documento                                      = new PedidosDocumentosLiq();
                $documento->doc_valor                           = $item["valor"];
                $documento->doc_numero                          = $item["codigo"];
                $documento->doc_nombre                          = $nombre;
                $documento->doc_ci                              = $item["tipo_aplicar"] == 0 ? 'ANT':'LIQ';
                $documento->doc_cuenta                          = $objectPadrePago->num_cuenta;
                $documento->doc_institucion                     = $objectPadrePago->banco;
                $documento->doc_tipo                            = $objectPadrePago->tipo_cuenta;
                $documento->doc_observacion                     = $objectPadrePago->observacion;
                $documento->ven_codigo                          = $contrato;
                $documento->verificaciones_pagos_detalles_id    = $id;
                $documento->doc_fecha                           = $fecha;
                $documento->save();
            }
        }
    }
    public function saveDocumentosLiq($request){
        $fecha  = date("Y-m-d H:i:s");
        if($request->id){
            $documento                                  = new PedidosDocumentosLiq();
        }else{
            $documento                                  = new PedidosDocumentosLiq();
            $documento->doc_fecha                       = $fecha;
        }
        $documento->unicoEvidencia                      = $request->unicoEvidencia;
        $documento->doc_valor                           = $request->doc_valor;
        $documento->doc_numero                          = $request->doc_numero;
        $documento->doc_nombre                          = $request->doc_nombre;
        $documento->doc_apellidos                       = $request->doc_apellidos;
        $documento->doc_ruc                             = $request->doc_ruc;
        $documento->doc_cuenta                          = $request->doc_cuenta;
        $documento->doc_institucion                     = $request->doc_institucion;
        $documento->doc_ci                              = $request->tipo_aplicar== 0 ? 'ANT':'LIQ';
        $documento->doc_tipo                            = $request->doc_tipo;
        $documento->doc_observacion                     = $request->doc_observacion;
        $documento->ven_codigo                          = $request->ven_codigo;
        $documento->user_created                        = $request->user_created;
        $documento->distribuidor_temporada_id           = $request->distribuidor_temporada_id == null || $request->distribuidor_temporada_id == "null" ? 0 : $request->distribuidor_temporada_id ;
        $documento->tip_pag_codigo                      = $request->tip_pag_codigo;
        $documento->tipo_aplicar                        = $request->tipo_aplicar;
        $documento->save();
    }
}
?>
