<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Models\Pedidos\PedidosDocumentosLiq;
use App\Traits\Pedidos\TraitPagosGeneral;
use DB;
use Illuminate\Http\Request;

class Pedidos2Controller extends Controller
{
    use TraitPagosGeneral;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    //pedidos2/pedidos
    public function index(Request $request)
    {
        if($request->getDocumentosLiq){
            return $this->getDocumentosLiq($request->contrato);
        }
    }
    //pedidos2/pedidos?getDocumentosLiq=yes&contrato=
    public function getDocumentosLiq($contrato){
        $query = $this->PagosFacturacion($contrato);
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
    //API:POST/pedidos2/pedidos
    public function store(Request $request)
    {
        if($request->saveDocumentLiq){
            return $this->saveDocumentLiq($request);
        }
    }
    //API:POST/pedidos2/pedidos/data={saveDocumentLiq=yes;$request}
    public function saveDocumentLiq($request){

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
