<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PedidoConvenio;
use App\Models\PedidoConvenioDetalle;
use App\Models\PedidoConvenioHistorico;
use App\Models\Pedidos;
use App\Models\Temporada;
use App\Traits\Pedidos\TraitPedidosGeneral;
use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Facades\Http;
class ConvenioController extends Controller
{
    use TraitPedidosGeneral;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    //convenio
    public function index(Request $request)
    {
        //traer convenio de institucion
        if($request->getConvenioInstitucion){
            return $this->getConvenioInstitucion($request->institucion_id);
        }
        if($request->AllConvenios){
            return $this->AllConvenios($request->institucion_id);
        }
        //informacion Convenio
        if($request->getInformacionConvenio){
            return $this->getInformacionConvenio($request->institucion_id,$request->periodo_id);
        }
        //convenios x id
        if($request->getConveniosXId){
            return $this->getConveniosXId($request->id);
        }
        //traer todos los contratos
          if($request->allContratoXInstitucion){
            return $this->allContratoXInstitucion($request->institucion_id);
        }
    }
    public function getConvenioInstitucion($institucion){
        $query = DB::SELECT("SELECT c.*, i.nombreInstitucion, p.periodoescolar as periodo
        FROM pedidos_convenios c
        LEFT JOIN institucion i ON i.idInstitucion = c.institucion_id
        LEFT JOIN periodoescolar p ON p.idperiodoescolar = c.periodo_id
        WHERE c.institucion_id = ?
        AND c.estado = '1'
        ",[$institucion]);
        return $query;
    }
    public function AllConvenios($institucion){
        $query = DB::SELECT("SELECT c.*, i.nombreInstitucion, p.periodoescolar as periodo
        FROM pedidos_convenios c
        LEFT JOIN institucion i ON i.idInstitucion = c.institucion_id
        LEFT JOIN periodoescolar p ON p.idperiodoescolar = c.periodo_id
        WHERE c.institucion_id = ?
        ORDER BY c.id DESC
        ",[$institucion]);
        return $query;
    }
    public function getInformacionConvenio($institucion,$periodo_id){
        $query = DB::SELECT("SELECT * FROM pedidos_convenios c
        WHERE c.institucion_id = '$institucion'
        AND c.periodo_id = '$periodo_id'
        ");
        if(empty($query)){
            return $query;
        }
        $idConvenio     = $query[0]->id;
        //traer los hijos del convenio global
        $query2 = $this->getConveniosXId($idConvenio);
        $datos = [];
        $contador =0;
        foreach($query2 as $key => $item){
            try {
                //===PROCESO======
                // $JsonDocumentos = $this->obtenerDocumentosLiq($item->contrato);
                $datos[$contador] = [
                    "id"                            => $item->id,
                    "pedido_convenio_institucion"   => $item->pedido_convenio_institucion,
                    "id_pedido"                     => $item->id_pedido,
                    "contrato"                      => $item->contrato,
                    "totalAnticipos"                => $item->valor,
                    "estado"                        => $item->estado,
                    "created_at"                    => $item->created_at,
                    // "datos"                         => $JsonDocumentos
                ];
                $contador++;
            } catch (\Exception  $ex) {
            return ["status" => "0","message" => "Hubo problemas con la conexión al servidor".$ex];
            }
        }
        return ["convenio" => $query, "hijos_convenio" => $datos];
    }
    //api:get/convenio?allContratoXInstitucion=yes&institucion_id=6
    public function allContratoXInstitucion($institucion){
        $query = DB::table('temporadas as t')
        ->select(DB::raw('t.*, p.id_pedido'))
        ->leftjoin('pedidos as p','t.contrato','p.contrato_generado')
        ->where('t.idInstitucion',$institucion)
        ->get();
        return $query;
    }
    public function getConveniosXId($idConvenio){
        $query = DB::SELECT("SELECT * FROM pedidos_convenios_detalle cd
            WHERE cd.pedido_convenio_institucion = '$idConvenio'
            AND cd.estado = '1'
        ");
        return $query;
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    //API:POST/convenio
    public function store(Request $request)
    {
        if($request->saveGlobal){
            return $this->saveGlobal($request);
        }
        //validar que el convenio este activo y no finalizado
        $convenio         = PedidoConvenio::findOrFail($request->idConvenio);
        $estadoConvenio   = $convenio->estado;
        if($estadoConvenio == 0) {
            return ["status" => "0", "message" => "Ya no se puede modificar el convenio ya finalizo"];
        }
        if($request->updateCamposDatos){
            return $this->updateCamposDatos($request);
        }
        if($request->updateCamposConvenio){
            return $this->updateCamposConvenio($request);
        }
        if($request->updateValuesSuggested){
            return $this->updateValuesSuggested($request);
        }
        if($request->saveContratoConvenio){
            return $this->saveContratoConvenio($request);
        }
    }
    public function saveGlobal($request){
        //variables
        $institucion_id     = $request->institucion_id;
        $periodo_id         = $request->periodo_id;
        $id_pedido          = $request->id_pedido;
        $user_created       = $request->user_created;
        $anticipo_global    = $request->anticipo_global;
        $old_values         = [];
        //===PROCESS===
         //busco si hay convenio abierto
        $query = $this->getConvenioInstitucion($request->institucion_id);
        if(!empty($query)){
            return ["status" => "0", "message" => "Ya existe un convenio que esta abierto"];
            // $id = $query[0]->id;
            // $global = PedidoConvenio::findOrFail($id);
            // $old_values = $global;
        }else{
            $global = new PedidoConvenio;
        }
            $global->anticipo_global = $request->anticipo_global;
            $global->convenio_anios  = $request->convenio_anios;
            $global->institucion_id  = $request->institucion_id;
            $global->periodo_id      = $request->periodo_id;
            $global->id_pedido       = $request->id_pedido;
            $global->user_created    = $request->user_created;
            $global->observacion     = $request->observacion;
            $global->save();
            $this->saveHistorico($institucion_id,0,$user_created,$anticipo_global,null,0,$old_values,"Actualizar anticipo global");
            //validar que si ya tiene contrato y no ha sido registrado en la tabla de hijo crearlos
            if($global){
                return ["status" => "1","message" => "Se guardo correctamente"];
            }else{
                return ["status" => "0","message" => "No se puedo guardar"];
            }
    }
    public function updateCamposDatos($request){
        $datos = [];
        $campo1 = $request->campo1;
        $valor1 = $request->valor1;
        $user_created   = $request->user_created;
        if($request->unCampo){ $datos = [ $campo1 => $valor1]; }
        $old_values         = PedidoConvenio::findOrFail($request->id);
        DB::table('pedidos_convenios')
        ->where('id',$request->id)
        ->update($datos);
        //history
        //variables
        $institucion_id = $old_values->institucion_id;
        $id_pedido      = $old_values->id_pedido;
        $this->saveHistorico($institucion_id,$id_pedido,$user_created,$valor1,null,0,$old_values,"Se actualizo el campo $campo1");
    }
    public function updateValuesSuggested($request){
        $user_created   = $request->user_created;
        $contratos      = json_decode($request->data_contratos);
        $padreConvenio   = PedidoConvenio::findOrFail($request->idConvenio);
        foreach($contratos as $key => $item){
            //variables
            $institucion_id     = $item->institucion_id;
            $id_pedido          = $item->id_pedido;
            $contrato           = $item->contrato;
            $old_values         = PedidoConvenioDetalle::findOrFail($item->id);
            DB::table('pedidos_convenios_detalle')
            ->where('id',$item->id)
            ->update(["valor" => $item->valueSuggested]);
            //update a pedido
            $this->updatePedido($contrato,$padreConvenio->convenio_anios,$request->idConvenio);
            //history
            $this->saveHistorico($institucion_id,$id_pedido,$user_created,$item->valueSuggested,$contrato,1,$old_values,"Se actualizo el campo valor");
        }
        return "Se guardo correctamente";
    }
    public function saveContratoConvenio($request){
        $institucion_id  = $request->institucion_id;
        $id_pedido       = $request->id_pedido;
        $user_created    = $request->user_created;
        $valor           = $request->valor;
        $contrato        = $request->contrato;
        $old_values      = [];
        $padreConvenio   = PedidoConvenio::findOrFail($request->idConvenio);
        if($request->id > 0){
            $hijoConvenio = PedidoConvenioDetalle::findOrFail($request->id);
            $old_values   = $hijoConvenio;
        }else{
            //validar que el contrato ya no este creado
            $getConvenioHijo = PedidoConvenioDetalle::Where('contrato',$contrato)->get();
            if(count($getConvenioHijo) > 0){
                return ["status" => "0","message" => "Ya existe el contrato $contrato creado en el convenio"];
            }
            $hijoConvenio = new PedidoConvenioDetalle();
        }
        $hijoConvenio->pedido_convenio_institucion  = $request->idConvenio;
        $hijoConvenio->id_pedido                    = $request->id_pedido;
        $hijoConvenio->contrato                     = $request->contrato;
        $hijoConvenio->institucion_id               = $request->institucion_id;
        $hijoConvenio->valor                        = $request->valor;
        $hijoConvenio->save();
        //update a pedido
        $this->updatePedido($contrato,$padreConvenio->convenio_anios,$request->idConvenio);
        //history
        $this->saveHistorico($institucion_id,$id_pedido,$user_created,$valor,$contrato,1,$old_values,"Se actualizo el campo valor o contrato");
        if($hijoConvenio){
            return ["status" => "1", "message" => "Se guardo correctamente"];
        }
    }
    public function saveHistorico($institucion_id,$id_pedido,$user_created,$cantidad,$contrato,$tipo,$old_values,$observacion){
        $historico = new PedidoConvenioHistorico();
        $historico->institucion_id  = $institucion_id;
        $historico->id_pedido       = $id_pedido;
        $historico->user_created    = $user_created;
        $historico->cantidad        = $cantidad;
        $historico->contrato        = $contrato;
        $historico->tipo            = $tipo;
        if(isset($old_values->created_at)){
            $historico->old_values      = $old_values;
        }else{
            $historico->old_values      = count($old_values) == 0 ? "" : $old_values;
        }
        $historico->observacion     = $observacion;
        $historico->save();
    }
    //API:POST/eliminarConvenio
    public function eliminarConvenio(Request $request){
        //validar que el convenio este activo y no finalizado
        $convenio         = PedidoConvenio::findOrFail($request->idConvenio);
        $estadoConvenio   = $convenio->estado;
        if($estadoConvenio == 0) {
            return ["status" => "0", "message" => "Ya no se puede modificar el convenio ya finalizo"];
        }
        if($request->eliminarHijoConvenio){
            return $this->eliminarHijoConvenio($request->id);
        }
    }
    public function eliminarHijoConvenio($id){
        $convenio = PedidoConvenioDetalle::findOrFail($id);
        $contrato = $convenio->contrato;
        //limipiar en pedido
        $this->updatePedido($contrato,null,0);
        $convenio->delete();
        return "Se elimino correctamente";
    }
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
