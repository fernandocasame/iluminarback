<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\NeetSubTema;
use App\Models\NeetTema;
use App\Models\NeetUpload;
use App\Models\NeetUsuarioDocumento;
use App\Traits\Codigos\TraitCodigosGeneral;
use App\Traits\Usuario\TraitUsuarioGeneral;
use Illuminate\Http\Request;
use DB;
class NeetTemaController extends Controller
{
    use TraitCodigosGeneral;
    use TraitUsuarioGeneral;
    //api:get>>getUsuarios/{rol}/{institucion}
    public function getUsuarios($rol,$institucion){
        $query = $this->userxRolxInstitucion($rol,$institucion);
        return $query;
    }
    //api:get/neetTema?listadoTemas=yes
    public function index(Request $request)
    {
        //temas
        if($request->listadoTemas){
            return $this->listadoTemas();
        }
        //Documentos
        if($request->listadoDocumentos){
            return $this->listadoDocumentos();
        }
        //Subniveles
        if($request->listadoSubniveles){
            return $this->listadoSubniveles();
        }
        //asignados x usuario
        if($request->getAsignados){
            return $this->getAsignados($request);
        }
        //todos asignados incluyendo los documentos generales
        if($request->getAsignadosAll){
            return $this->getAsignadosAll($request);
        }
        //TRAER ASIGNACIONES POR DOCUMENTOS DE DOCENTES
         if($request->getDocentesXDocumento){
            return $this->getDocentesXDocumento($request->periodo_id,$request->neet_upload_id,$request->institucion_id);
        }
    }
    public function listadoTemas(){
        $query = DB::SELECT("SELECT t.*
        FROM neet_temas t
        ORDER BY t.id DESC
        ");
        return $query;
    }
    public function listadoDocumentos(){
        $query = DB::SELECT("SELECT nu.* , t.nombre AS tema,s.nombre as subnivel
        FROM neet_upload nu
        LEFT JOIN neet_temas t ON nu.tema_id = t.id
        LEFT JOIN neet_subnivel s ON nu.nee_subnivel = s.id
        ORDER BY nu.id DESC
        ");
        if(empty($query)){
            return $query;
        }
        $datos = [];
        foreach($query as $key => $item){
            $files = $this->getFilesUpload($item->id);
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
                "nee_subnivel"      => $item->nee_subnivel,
                "tipo"              => $item->tipo,
                "subnivel"          => $item->subnivel,
                "actividad1"        => $item->actividad1,
                "actividad2"        => $item->actividad2,
                "actividad3"        => $item->actividad3,
                "actividad4"        => $item->actividad4,
                "actividad5"        => $item->actividad5,
                "solucionario"      => $item->solucionario,
                "files"             => $files
            ];
        }
        return $datos;
    }
    public function getFilesUpload($id){
        $files = DB::SELECT("SELECT * FROM neet_upload_files WHERE neet_upload_id = '$id'");
        return $files;
    }
    public function listadoSubniveles(){
        $query = DB::SELECT("SELECT * FROM neet_subnivel");
        return $query;
    }
    public function getAsignados($request){
        $buscarPeriodo = $this->PeriodoInstitucion($request->institucion_id);
        if(empty($buscarPeriodo)) return ["status" => "0", "message" => "No se encontro un período para la institución"];
        $periodo = $buscarPeriodo[0]->periodo;
        $query = DB::SELECT("SELECT nd.*, nu.nombre AS documento, pe.periodoescolar AS periodo,
        sn.nombre AS subnivel,t.nombre as tema,nu.nee_subnivel
        FROM neet_usuario_documento nd
        LEFT JOIN neet_upload  nu ON nd.neet_upload_id = nu.id
        LEFT JOIN periodoescolar pe ON nd.periodo_id = pe.idperiodoescolar
        LEFT JOIN neet_subnivel sn ON sn.id =  nu.nee_subnivel
        LEFT JOIN neet_temas t ON nu.tema_id = t.id
        WHERE nd.idusuario      = '$request->idusuario'
        AND nd.periodo_id       = '$periodo'
        ORDER BY nd.id DESC;
        ");
        return $query;
    }
    public function getAsignadosAll($request){
        $array_resultante = [];
        $buscarPeriodo = $this->PeriodoInstitucion($request->institucion_id);
        if(empty($buscarPeriodo)) return ["status" => "0", "message" => "No se encontro un período para la institución"];
        $periodo = $buscarPeriodo[0]->periodo;
        $query = DB::SELECT("SELECT nd.*, nu.nombre AS documento, pe.periodoescolar AS periodo,
        sn.nombre AS subnivel,t.nombre as tema,nu.nee_subnivel,
        nu.actividad1, nu.actividad2 , nu.actividad3, nu.actividad4, nu.actividad5,nu.solucionario
        FROM neet_usuario_documento nd
        LEFT JOIN neet_upload  nu ON nd.neet_upload_id = nu.id
        LEFT JOIN periodoescolar pe ON nd.periodo_id = pe.idperiodoescolar
        LEFT JOIN neet_subnivel sn ON sn.id =  nu.nee_subnivel
        LEFT JOIN neet_temas t ON nu.tema_id = t.id
        WHERE nd.idusuario      = '$request->idusuario'
        AND nd.periodo_id       = '$periodo'
        AND nu.estado           = '1'
        ORDER BY nd.id DESC;
        ");
        $datos  = [];
        $datos2 = [];
        foreach($query as $key => $item){
            $files = $this->getFilesUpload($item->neet_upload_id);
            $datos[$key] = [
                "id"                => $item->id,
                "idusuario"         => $item->idusuario,
                "user_created"      => $item->user_created,
                "neet_upload_id"    => $item->neet_upload_id,
                "nee_subnivel"      => $item->nee_subnivel,
                "periodo_id"        => $item->periodo_id,
                "created_at"        => $item->created_at,
                "documento"         => $item->documento,
                "periodo"           => $item->periodo,
                "subnivel"          => $item->subnivel,
                "tema"              => $item->tema,
                "actividad1"        => $item->actividad1,
                "actividad2"        => $item->actividad2,
                "actividad3"        => $item->actividad3,
                "actividad4"        => $item->actividad4,
                "actividad5"        => $item->actividad5,
                "solucionario"      => $item->solucionario,
                "files"             => $files
            ];
        }
        //archivos generales
        $query2 = DB::SELECT("SELECT nu.id, nu.nombre AS documento,
        sn.nombre AS subnivel,t.nombre as tema,nu.nee_subnivel
        FROM neet_upload nu
        LEFT JOIN neet_subnivel sn ON sn.id =  nu.nee_subnivel
        LEFT JOIN neet_temas t ON nu.tema_id = t.id
        WHERE nu.nee_subnivel = '5'
        AND nu.estado = '1'
        ");
        foreach($query2 as $key => $item){
            $files = $this->getFilesUpload($item->id);
            $datos2[$key] = [
                "neet_upload_id"    => $item->id,
                "nee_subnivel"      => $item->nee_subnivel,
                "documento"         => $item->documento,
                "subnivel"          => $item->subnivel,
                "tema"              => $item->tema,
                "files"             => $files
            ];
        }
        $array_resultante= array_merge($datos,$datos2);
        return $array_resultante;
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
    //neetTema
    public function store(Request $request)
    {
        //AGREGAR TEMAS
        if($request->save_temas){
            return $this->save_temas($request);
        }
        //ASIGNAR DOCUMENTO
        if($request->asignarDocumento){
            return $this->asignarDocumento($request);
        }
        //ASIGNAR DOCUMENTOS DOCENTES
        if($request->asignarDocentes){
            return $this->asignarDocentes($request);
        }
        //GUARDAR ACTIVIDADES
        if($request->guardarActividadesNeet){
            return $this->guardarActividadesNeet($request);
        }
    }

    public function save_temas($request){
        if($request->id > 0){
            $tema               = NeetTema::findOrFail($request->id);
        }else{
            $tema               = new NeetTema();
        }
        $tema->nombre           = $request->nombre;
        $tema->estado           = $request->estado;
        $tema->user_created     = $request->user_created;
        $tema->save();
        if($tema){
            return ["status" => "1","message" => "Se guardo correctamente"];
        }else{
            return ["status" => "0","message" => "No se pudo guardar"];
        }
    }
    public function asignarDocumento($request){
        $buscarPeriodo = $this->PeriodoInstitucion($request->institucion_id);
        if(empty($buscarPeriodo)) return ["status" => "0", "message" => "No se encontro un período para la institución"];
        $periodo = $buscarPeriodo[0]->periodo;
        $query = $this->getAsignadoXDocente($request->idusuario,$periodo,$request->neet_upload_id);
        //validar que si existe no se crea
        if(count($query) > 0) return ["status" => "0", "message" => "El documento ya ha sido asignado anteriormente"];
        //PROCESO
        $documento = $this->saveAsignacionDocente($request,$periodo,$request->idusuario);
        if($documento){
            return ["status" => "1", "message" => "Se guardo correctamente"];
        }else{
            return ["status" => "0", "message" => "No se pudo guardar"];
        }
    }
    public function getAsignadoXDocente($idusuario,$periodo,$neet_upload_id){
        $query = DB::SELECT("SELECT nd.*
        FROM neet_usuario_documento nd
        WHERE nd.idusuario      = '$idusuario'
        AND nd.periodo_id       = '$periodo'
        AND nd.neet_upload_id   = '$neet_upload_id'
        ");
        return $query;
    }
    public function saveAsignacionDocente($request,$periodo,$idusuario){
        $documento = new NeetUsuarioDocumento();
        $documento->idusuario       = $idusuario;
        $documento->user_created    = $request->user_created;
        $documento->neet_upload_id  = $request->neet_upload_id;
        $documento->periodo_id      = $periodo;
        $documento->id_group        = $request->id_group;
        $documento->save();
        return $documento;
    }
    public function asignarDocentes($request){
        $periodo_id             = $request->periodo_id;
        $neet_upload_id         = $request->neet_upload_id;
        $institucion_id         = $request->institucion_id;
        $datos                  = json_decode($request->docentes);
        //eliminar si ya han quitado al docente
        //traer los documentos asignados a los docentes
        $getDocentesAsignados   = $this->getDocentesXDocumento($periodo_id,$neet_upload_id,$institucion_id);
        if(sizeOf($getDocentesAsignados) > 0){
            foreach($getDocentesAsignados as $key => $item){
                $docente        = "";
                $docente        = $item->idusuario;
                $searchDocente  = collect($datos)->filter(function ($objeto) use ($docente) {
                    // Condición de filtro
                    return $objeto->idusuario == $docente;
                });
                if(sizeOf($searchDocente) == 0){
                    DB::DELETE("DELETE FROM neet_usuario_documento
                      WHERE neet_upload_id  = '$neet_upload_id'
                      AND idusuario         = '$docente'
                      AND periodo_id        = '$periodo_id'
                    ");
                }
            }
        }
        //guardar las asignaciones para los docentes
        foreach($datos as $key => $item){
            $query = $this->getAsignadoXDocente($item->idusuario,$periodo_id,$request->neet_upload_id);
            if(empty($query)){
                //guardar
                $this->saveAsignacionDocente($request,$periodo_id,$item->idusuario);
            }
        }
    }
    public function getDocentesXDocumento($periodo_id,$neet_upload_id,$institucion_id){
        $query = DB::SELECT("SELECT  CONCAT(u.nombres,' ',u.apellidos) as nombre_completo,
        u.id_group,u.cedula,u.idusuario,u.institucion_idInstitucion
        FROM neet_usuario_documento d
        LEFT JOIN usuario u ON d.idusuario  = u.idusuario
        WHERE d.id_group                    = '6'
        AND d.periodo_id                    = '$periodo_id'
        AND d.neet_upload_id                = '$neet_upload_id'
        AND u.institucion_idInstitucion     = '$institucion_id'
        ");
        return $query;
    }
    public function guardarActividadesNeet($request){
        $actividad1 = $request->actividad1;
        $actividad2 = $request->actividad2;
        $actividad3 = $request->actividad3;
        $actividad4 = $request->actividad4;
        $actividad5 = $request->actividad5;
        $dato = NeetUpload::find($request->id);
        $dato->actividad1      = $actividad1 == null || $actividad1 == "null" ? null :$actividad1;
        $dato->actividad2      = $actividad2 == null || $actividad1 == "null" ? null :$actividad2;
        $dato->actividad3      = $actividad3 == null || $actividad1 == "null" ? null :$actividad3;
        $dato->actividad4      = $actividad4 == null || $actividad1 == "null" ? null :$actividad4;
        $dato->actividad5      = $actividad5 == null || $actividad1 == "null" ? null :$actividad5;
        $dato->user_created    = $request->user_created;
        $dato->save();
        if($dato){
            return ["status" => "1", "message" => "Se guardo correctamente"];
        }else{
            return ["status" => "0", "message" => "No se pudo guardar"];
        }
    }
    public function neetEliminar(Request $request){
        //ELIMINAR TEMA
        if($request->eliminar_tema){
            return $this->eliminar_tema($request);
        }
    }
    public function eliminar_tema($request){
        //validar que no tenga hijos
        $query = DB::SELECT("SELECT * FROM neet_upload nu
        WHERE nu.tema_id = '$request->id'
        ");
        if(count($query) > 0){
            return ["status" => "0", "message" => "No se puede eliminar el tema por que hay documentos asociados al tema"];
        }
        $tema = NeetTema::findOrFail($request->id)->delete();
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
        $files = DB::SELECT("SELECT * FROM neet_upload_files WHERE neet_upload_id = '$id'");
        $asignaciones = DB::SELECT("SELECT * FROM neet_usuario_documento nud
        WHERE nud.neet_upload_id = '$id'
        ");
        return ["files" => $files,"asignaciones"=>$asignaciones];
    }
    //API:GET/eliminaAsignacionNeet/id
    public function eliminaAsignacionNeet($id){
        $data = NeetUsuarioDocumento::find($id);
        $data->delete();
        return $data;
    }
    //API:POST/quitarTodasDocumentosAsignados
    public function quitarTodasDocumentosAsignados(Request $request)
    {
        $ids = explode(",",$request->ids);
        $data = NeetUsuarioDocumento::destroy($ids);
        return $data;
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
