<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Prueba;
use DB;
class PruebaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $prueba = DB::SELECT("SELECT p.id,p.descripcion,d.datos
        FROM prueba p
       LEFT JOIN prueba_detalles d ON p.id = d.prueba_id
       ");
        return $prueba;
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
        // if($request->id > 0){
        //     $prueba = Prueba::findOrFail($request->id);
        // }else{
        //     $prueba = new Prueba();
        // }
        $prueba = new Prueba();
        $prueba->descripcion = $request->descripcion;
        $prueba->save();
        DB::INSERT("INSERT INTO prueba_detalles(prueba_id) values($prueba->id)");
    }

    public function guardarPrueba(Request $request){
        $accesorios = json_decode($request->datos);
        foreach($accesorios as $key => $item){
            if($item->datos == 1){
                $goDatos = '{"checkBox1":true}';
            }else{
                $goDatos = '{"checkBox1":false}';
            }
            $codigo =  DB::table('prueba_detalles')
            ->where('prueba_id', $item->id)
            ->update([
                'datos'        =>  $goDatos,
                
            ]);   
        }
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
