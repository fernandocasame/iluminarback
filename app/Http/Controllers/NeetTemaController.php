<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\NeetSubTema;
use App\Models\NeetTema;
use Illuminate\Http\Request;
use DB;
class NeetTemaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    //api:get/neetTema?listadoTemas=yes
    public function index(Request $request)
    {
        //temas
        if($request->listadoTemas){
            return $this->listadoTemas();
        }
        //subtemas
        if($request->listadoSubTemas){
            return $this->listadoSubTemas();
        }
        //Documentos
          if($request->listadoDocumentos){
            return $this->listadoDocumentos();
        }
    }
    public function listadoTemas(){
        $query = DB::SELECT("SELECT * FROM neet_temas t ORDER BY t.id DESC ");
        return $query; 
    }
    public function listadoSubTemas(){
        $query = DB::SELECT("SELECT st.*, t.nombre AS tema
        FROM neet_subtemas st
        LEFT JOIN neet_temas t ON st.neet_temas_id = t.id
        ORDER BY st.id DESC");
        return $query; 
    }
    public function listadoDocumentos(){
        $query = DB::SELECT("SELECT nu.* , t.nombre AS tema
        FROM neet_upload nu
        LEFT JOIN neet_temas t ON nu.tema_id = t.id
        ORDER BY nu.id DESC
        ");
        if(empty($query)){
            return $query;
        }
        $datos = [];
        foreach($query as $key => $item){
            $files = DB::SELECT("SELECT * FROM neet_upload_files WHERE neet_upload_id = '$item->id'");
            $datos[$key] = [
                "id"                => $item->id,
                "nombre"            => $item->nombre,
                "descripcion"       => $item->descripcion,
                "estado"            => $item->estado,
                "tema"              => $item->tema,
                "tema_id"           => $item->tema_id,
                "user_created"      => $item->user_created,
                "created_at"        => $item->created_at,
                "updated_at"        => $item->updated_at,
                "files"             => $files
            ];
        }
        return $datos;
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
        //AGREGAR TEMAS
        if($request->save_temas){
            return $this->save_temas($request);
        }
        //AGREGAR SUBTEMAS
        if($request->save_sub_temas){
            return $this->save_sub_temas($request);
        }
    }
    
    public function save_temas($request){
        if($request->id > 0){
            $tema       = NeetTema::findOrFail($request->id);
        }else{
            $tema       = new NeetTema();
        }
        $tema->nombre   = $request->nombre;
        $tema->estado   = $request->estado;
        $tema->save();
        if($tema){
            return ["status" => "1","message" => "Se guardo correctamente"];
        }else{
            return ["status" => "0","message" => "No se pudo guardar"];
        }
    }
    public function save_sub_temas($request){
        if($request->id > 0){
            $subtema               = NeetSubTema::findOrFail($request->id);
        }else{
            $subtema               = new NeetSubTema();
        }
        $subtema->nombre           = $request->nombre;
        $subtema->descripcion      = $request->descripcion;
        $subtema->neet_temas_id    = $request->neet_temas_id;
        $subtema->save();
        if($subtema){
            return ["status" => "1","message" => "Se guardo correctamente"];
        }else{
            return ["status" => "0","message" => "No se pudo guardar"];
        }
    }
    public function neetEliminar(Request $request){
        //ELIMINAR TEMA
        if($request->eliminar_tema){
            return $this->eliminar_tema($request);
        }
        //ELIMINAR SUBTEMAS
        if($request->eliminar_sub_tema){
            return $this->eliminar_sub_tema($request);
        }
    }
    public function eliminar_tema($request){
        //Validar que no hay temas hijos
        $query = $this->getSubTemasxId($request->id);
        if(sizeof($query) > 0){
            return ["status" => "0","message" => "No se puede eliminar existe subtemas"];
        }
        $tema = NeetTema::findOrFail($request->id)->delete();
    }
    public function eliminar_sub_tema($request){
        $tema = NeetSubTema::findOrFail($request->id)->delete();
    }

    public function getSubTemasxId($tema){
        $query = DB::SELECT("SELECT st.id, st.nombre FROM neet_subtemas st
        WHERE st.neet_temas_id = '$tema'
        ");
        return $query;
    }
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    //api:get/neetTema/{id}
    //subtemas asignados
    public function show($id)
    {
        //traer subtemas asignados a un registro de documentos
        $subtemas = DB::SELECT("SELECT sb.subtema_id as id, st.nombre 
        FROM neet_upload_subtemas sb
        LEFT JOIN neet_subtemas  st ON sb.subtema_id = st.id
        WHERE sb.meet_upload_id = '$id'
        ");
        $files = DB::SELECT("SELECT * FROM neet_upload_files WHERE neet_upload_id = '$id'");
        return ["files" => $files,"subtemas" => $subtemas];
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
