<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Temas;

class TemaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {   
        if($request->byAsignatura){
            $temas = DB::SELECT("SELECT 
            t.id AS id, t.idusuario, t.nombre_tema AS label,
            `id_asignatura`, `unidad`, a.nombreasignatura,
            t.clasificacion, t.id_unidad 
            FROM `temas` t, `asignatura` a
            WHERE t.id_asignatura = a.idasignatura
            AND t.estado = '1'
            AND a.idasignatura = '$request->asignatura'
            ORDER BY a.idasignatura");

            return $temas;
        }else{
            $temas = DB::SELECT("SELECT t.id AS id, t.idusuario, t.nombre_tema AS label, `id_asignatura`, `unidad`, a.nombreasignatura, t.clasificacion, t.id_unidad FROM `temas` t, `asignatura` a WHERE t.id_asignatura = a.idasignatura AND t.estado=1 ORDER BY a.idasignatura");

            return $temas;
        }
       

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
        if( $request->id ){
            $tema = Temas::find($request->id);
        }else{
            $tema = new Temas();
        }

        $tema->nombre_tema = $request->nombre;
        $tema->id_asignatura = $request->asignatura;
        $tema->unidad = $request->unidad;
        $tema->id_unidad = $request->id_unidad;
        $tema->clasificacion = $request->clasificacion;
        $tema->idusuario = $request->idusuario;
        $tema->estado = $request->estado;
        $tema->save();

        return $tema;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $temas = DB::SELECT("SELECT t.id AS id, t.idusuario, t.nombre_tema AS label, t.id_asignatura, t.unidad, a.nombreasignatura, t.clasificacion FROM temas t, asignatura a, asignaturausuario au WHERE t.id_asignatura = a.idasignatura AND a.idasignatura = au.asignatura_idasignatura AND t.idusuario = $id AND t.estado=1 ORDER BY a.idasignatura");

        return $temas;
    }

    public function temasignunidadExport(Request $request)
    {
        $temas = DB::SELECT("SELECT t.nombre_tema, t.id AS id,
            t.nombre_tema AS label, t.id_asignatura, t.unidad,
            a.nombreasignatura, t.clasificacion 
            FROM temas t, asignatura a 
            WHERE t.id_asignatura = a.idasignatura
            AND t.unidad = $request->unidad
            AND t.id_asignatura = $request->asignatura
            AND t.estado=1
            -- ORDER BY cast(t.nombre_tema as int) ASC
        ");
        return $temas;
    }
    public function temasignunidad(Request $request)
    {
        // $temas = DB::SELECT("SELECT t.nombre_tema, t.id AS id,
        // t.nombre_tema AS label, t.id_asignatura,
        // t.unidad, a.nombreasignatura, t.clasificacion
        // FROM temas t, asignatura a
        // WHERE t.id_asignatura = a.idasignatura 
        // AND t.unidad = $request->unidad 
        // AND t.id_asignatura = $request->asignatura 
        // AND t.estado=1 
        // -- ORDER BY cast(t.nombre_tema as int) ASC
        // ");

        $temas = DB::SELECT("SELECT t.nombre_tema, t.id AS id, t.nombre_tema AS label, t.id_asignatura, t.unidad, a.nombreasignatura, t.clasificacion FROM temas t, asignatura a WHERE t.id_asignatura = a.idasignatura AND t.unidad = $request->unidad AND t.id_asignatura = $request->asignatura AND t.estado=1 ORDER BY cast(t.nombre_tema as int) ASC");

        return $temas;
    }

    
    public function temAsignaruta($id)
    {
         $temas = DB::SELECT("SELECT * from temas t where id_asignatura = $id");

        return $temas;
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
        $tema = Temas::find($id);
        $tema->nombre_tema = $request->nombre;
        $tema->id_asignatura = $request->asignatura;
        $tema->unidad = $request->unidad;
        $tema->save();

        return $tema;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        $tema = Temas::find($request->id);

        if($tema->delete()){
            return 1;
        }else{
            return 0;
        }

    }

    
    public function eliminar_tema(Request $request)
    {
        $temas = DB::UPDATE("UPDATE `temas` SET `idusuario`=$request->idusuario,`estado`=0 WHERE `id` = $request->id_tema;");

        return $temas;
    }


}