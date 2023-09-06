<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PedidoConvenio;
use App\Models\PedidoConvenioDetalle;
use App\Models\PedidoConvenioHistorico;
use App\Models\Pedidos;
use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Facades\Http;
class ConvenioController extends Controller
{
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
        //informacion Convenio
        if($request->getInformacionConvenio){
            return $this->getInformacionConvenio($request->institucion_id,$request->id_pedido);
        }
    }
    public function getConvenioInstitucion($institucion){
        $query = DB::SELECT("SELECT * FROM pedidos_convenios c
        WHERE c.institucion_id = '$institucion'
        AND c.estado = '1'
        ");
        return $query;
    }
    public function getInformacionConvenio($institucion,$pedido){
        $query = DB::SELECT("SELECT * FROM pedidos_convenios c
        WHERE c.institucion_id = '$institucion'
        AND c.id_pedido = '$pedido'
        ");
        $idConvenio     = $query[0]->id;
        //traer los hijos del convenio global
        $query2 = DB::SELECT("SELECT * FROM pedidos_convenios_detalle cd
            WHERE cd.pedido_convenio_institucion = '$idConvenio'
            AND cd.estado = '1'
        ");
        $datos = [];
        $contador =0;
        foreach($query2 as $key => $item){
            try {
                $dato = Http::get("http://186.4.218.168:9095/api/Contrato/".$item->contrato);
                $JsonContrato = json_decode($dato, true);
                if($JsonContrato == "" || $JsonContrato == null){
                    $datos[$contador] = [
                        "id"                            => $item->id,
                        "pedido_convenio_institucion"   => $item->pedido_convenio_institucion,
                        "id_pedido"                     => $item->id_pedido,
                        "contrato"                      => $item->contrato,
                        "estado"                        => $item->estado,
                        "created_at"                    => $item->created_at,
                        "datos"                         => []
                    ];
                    // return ["status" => "0", "message" => "No existe el contrato en facturación"];
                }else{
                    $covertido      = $JsonContrato["veN_CONVERTIDO"];
                    $estado         = $JsonContrato["esT_VEN_CODIGO"];
                    //verificar que no sea anulado ni convertido
                    if($estado != 3 && !str_starts_with($covertido , 'C')){
                        //===PROCESO======
                        $dato2 = Http::get("http://186.4.218.168:9095/api/f_DocumentoLiq/Get_docliq_venta_x_vencod?venCodigo=".$item->contrato);
                        $JsonDocumentos = json_decode($dato2, true);
                        $datos[$contador] = [
                            "id"                            => $item->id,
                            "pedido_convenio_institucion"   => $item->pedido_convenio_institucion,
                            "id_pedido"                     => $item->id_pedido,
                            "contrato"                      => $item->contrato,
                            "estado"                        => $item->estado,
                            "created_at"                    => $item->created_at,
                            "datos"                         => $JsonDocumentos
                        ];
                    }else{
                        $datos[$contador] = [
                            "id"                            => $item->id,
                            "pedido_convenio_institucion"   => $item->pedido_convenio_institucion,
                            "id_pedido"                     => $item->id_pedido,
                            "contrato"                      => $item->contrato,
                            "estado"                        => $item->estado,
                            "created_at"                    => $item->created_at,
                            "datos"                         => []
                        ];
                        // return ["status" => "0", "message" => "El contrato $item->contrato esta anulado o pertenece a un ven_convertido"];
                    }
                }
                $contador++;
            } catch (\Exception  $ex) {
            return ["status" => "0","message" => "Hubo problemas con la conexión al servidor"];
            }
        }
        return ["convenio" => $query, "hijos_convenio" => $datos];
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
    public function store(Request $request)
    {
        if($request->saveGlobal){
            return $this->saveGlobal($request);
        }
    }
    public function saveGlobal($request){
        //busco si hay convenio abierto
        $query = $this->getConvenioInstitucion($request->institucion_id);
        if(!empty($query)){
            $id = $query[0]->id;
            $global = PedidoConvenio::findOrFail($id);
        }else{
            $global = new PedidoConvenio;
            //convenio Fuera plataforma
            if($request->convenioFuera == 1){
                //Colocar id_pedido_origen el id_pedido para futuras consultas
                DB::UPDATE("UPDATE pedidos SET convenio_origen = '$request->id_pedido', convenio_hijo_receptor_fuera = '$request->convenio_hijo_receptor_fuera' WHERE id_pedido = '$request->tempid_pedido'");
            }
            //Convenio en la plataforma
            else{
                DB::UPDATE("UPDATE pedidos SET convenio_anios = '$request->convenio_anios', convenio_origen = '$request->id_pedido', convenio_hijo_receptor_fuera = '$request->convenio_hijo_receptor_fuera' WHERE id_pedido = '$request->tempid_pedido'");
            }
        }
            $global->anticipo_global = $request->anticipo_global;
            $global->convenio_anios  = $request->convenio_anios;
            $global->institucion_id  = $request->institucion_id;
            $global->periodo_id      = $request->periodo_id;
            $global->id_pedido       = $request->id_pedido;
            $global->user_created    = $request->user_created;
            $global->observacion     = $request->observacion;
            $global->save();
            $this->saveHistorico($request);
            //validar que si ya tiene contrato y no ha sido registrado en la tabla de hijo crearlos
            $this->crearConvenioHijos($request);
            if($global){
                return ["status" => "1","message" => "Se guardo correctamente"];
            }else{
                return ["status" => "0","message" => "No se puedo guardar"];
            }
    }
    public function crearConvenioHijos($request){
        $getConvenio = $this->getConvenioInstitucion($request->institucion_id);
        if(!empty($getConvenio)){
            $idConvenio = $getConvenio[0]->id;
            //validate que el contrato hijo convenio no este creado
            $query = DB::SELECT("SELECT * FROM pedidos_convenios_detalle cd
            WHERE cd.id_pedido = '$request->id_pedido'
            AND cd.institucion_id = '$request->institucion_id'
            ");
            if(empty($query)){
                //si es un convenio fuera de prolipa
                if($request->convenioFuera == 1){
                    $datos = explode("*", $request->contratosFuera);
                    $tam   = sizeof($datos);
                    for( $i=0; $i<$tam; $i++ ){
                        $hijoConvenio = new PedidoConvenioDetalle();
                        $hijoConvenio->pedido_convenio_institucion  = $idConvenio;
                        $hijoConvenio->id_pedido                    = $request->id_pedido;
                        $hijoConvenio->contrato                     = $datos[$i];
                        $hijoConvenio->institucion_id               = $request->institucion_id;
                        $hijoConvenio->save();
                    }
                    // si ya existe el contrato hecho y quiere convenio de valores anteriores
                    $query2 = DB::SELECT("SELECT * FROM pedidos_convenios_detalle cd
                    WHERE cd.id_pedido = '$request->tempid_pedido'
                    AND cd.institucion_id = '$request->institucion_id'
                    ");
                    if(empty($query2)){
                        //solo se va a crear si tiene contrato
                        $pedido = Pedidos::findOrFail($request->tempid_pedido);
                        $contrato = $pedido->contrato_generado;
                        if($contrato == null || $contrato == ""){
                        }else{
                            $hijoConvenio = new PedidoConvenioDetalle();
                            $hijoConvenio->pedido_convenio_institucion  = $idConvenio;
                            $hijoConvenio->id_pedido                    = $request->tempid_pedido;
                            $hijoConvenio->contrato                     = $contrato;
                            $hijoConvenio->institucion_id               = $request->institucion_id;
                            $hijoConvenio->save();
                        }
                    }
                }
                //convenio dentro de prolipa
                else{
                    //solo se va a crear si tiene contrato
                    $pedido = Pedidos::findOrFail($request->id_pedido);
                    $contrato = $pedido->contrato_generado;
                    if($contrato == null || $contrato == ""){
                    }else{
                        $hijoConvenio = new PedidoConvenioDetalle();
                        $hijoConvenio->pedido_convenio_institucion  = $idConvenio;
                        $hijoConvenio->id_pedido                    = $request->id_pedido;
                        $hijoConvenio->contrato                     = $contrato;
                        $hijoConvenio->institucion_id               = $request->institucion_id;
                        $hijoConvenio->save();
                    }
                }

            }
        }
    }
    public function saveHistorico($request){
        $historico = new PedidoConvenioHistorico();
        $historico->institucion_id  = $request->institucion_id;
        $historico->periodo_id      = $request->periodo_id;
        $historico->id_pedido       = $request->id_pedido;
        $historico->user_created    = $request->user_created;
        $historico->cantidad        = $request->anticipo_global;
        $historico->save();
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
