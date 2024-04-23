<?php
namespace App\Repositories\pedidos;

use App\Models\Models\Pedidos\PedidosDocumentosLiq;
use App\Models\PedidoConvenio;
use App\Models\Pedidos;
use App\Repositories\BaseRepository;
use DB;
class  ConvenioRepository extends BaseRepository
{
    public function __construct(PedidoConvenio $convenioRepository)
    {
        parent::__construct($convenioRepository);
    }
    public function registrarConvenioHijo($id_pedido,$idConvenio,$contrato,$institucion,$periodo){
        $GetConvenio = PedidoConvenio::where('id','=',$idConvenio)->get();
        $global = $GetConvenio[0]->anticipo_global;
        $convenio_anios = $GetConvenio[0]->convenio_anios;
        $query = PedidosDocumentosLiq::Where('id_pedido','=',$id_pedido)->where('tipo_pago_id','4')->get();
        if(count($query) == 0){
            $hijoConvenio                               = new PedidosDocumentosLiq();
            $hijoConvenio->doc_fecha                    = date("Y-m-d H:i:s");
            $hijoConvenio->pedidos_convenios_id         = $idConvenio;
            $hijoConvenio->id_pedido                    = $id_pedido;
            $hijoConvenio->ven_codigo                   = $contrato;
            $hijoConvenio->institucion_id               = $institucion;
            $hijoConvenio->periodo_id                   = $periodo;
            $hijoConvenio->doc_valor                    = $global / $convenio_anios;
            $hijoConvenio->tipo_pago_id                 = 4;
            $hijoConvenio->forma_pago_id                = 1;
            $hijoConvenio->estado                       = 0;
            $hijoConvenio->save();
        }
        //update a pedido
        $datosUpdate = [
            "convenio_anios"        => $convenio_anios,
            "pedidos_convenios_id"  => $idConvenio
        ];
        Pedidos::Where('id_pedido','=',$id_pedido)->update($datosUpdate);
    }
    public function updatePedido($contrato,$anio,$idConvenio){
        $datosUpdate = [
            "convenio_anios"        => $anio,
            "pedidos_convenios_id"  => $idConvenio
        ];
        Pedidos::Where('contrato_generado','=',$contrato)->update($datosUpdate);
    }
}
