<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PedidoConvenio;
use App\Models\PedidoConvenioHistorico;
use Illuminate\Http\Request;
use DB;

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
    }
    public function getConvenioInstitucion($institucion){
        $query = DB::SELECT("SELECT * FROM pedidos_convenios c
        WHERE c.institucion_id = '$institucion'
        AND c.estado = '1'
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
    public function store(Request $request)
    {
        if($request->saveGlobal){
            return $this->saveGlobal($request);
        }
    }
    public function saveGlobal($request){
        if($request->id > 0){
            $global = PedidoConvenio::findOrFail($request->id);
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
            $this->saveHistorico($request);
            if($global){
                return ["status" => "1","message" => "Se guardo correctamente"];
            }else{
                return ["status" => "0","message" => "No se puedo guardar"];
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
