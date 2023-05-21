<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AsignaturaDocente;
use App\Models\MatHorario;
use App\Models\MatHorarioClases;
use App\Models\MatHorarioClasesDetalles;
use DateInterval;
use DateTime;
use Illuminate\Http\Request;
use DB;
class HorarioController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function makeid($longitud,$characters){
        // $characters = ['1','2','3','4','5','6'];
        shuffle($characters);
        $charactersLength = count($characters);
        $randomString = '';
        for ($i = 0; $i < $longitud; $i++) {
            $pos_rand = rand(0, ($charactersLength-1));
            $randomString .= $characters[$pos_rand];
        }
        return $randomString;
    }
    public function makeDay($longitud){
        $characters = ['Sabado','Domingo'];
        shuffle($characters);
        $charactersLength = count($characters);
        $randomString = '';
        for ($i = 0; $i < $longitud; $i++) {
            $pos_rand = rand(0, ($charactersLength-1));
            $randomString .= $characters[$pos_rand];
        }
        return $randomString;
    }
    public function index(Request $request)
    {
        //listar docente curso
        if($request->docenteCursos){
            return $this->docenteCursos($request->idcurso);
        }
        if($request->HorarioLista){
            return $this->HorarioLista($request->institucion_id,$request->periodo_id);
        }
        //para obtener el horario del curso
        if($request->getHorarioCurso){
            return $this->getHorarioCurso($request->idcurso);
        }
    }
    public function docenteCursos($idcurso){
        $materias = DB::SELECT("SELECT ac.*,l.nombrelibro,
        CONCAT(u.nombres,' ',u.apellidos) AS docente,
        CONCAT(l.nombrelibro,' - ',u.nombres,' ',u.apellidos) AS materia
        FROM asignaturausuario ac
        LEFT JOIN libro l ON ac.asignatura_idasignatura = l.idlibro
        LEFT JOIN usuario u ON ac.docente_id = u.idusuario
        WHERE ac.idcurso = '$idcurso'
        ORDER BY l.nombrelibro ASC
        ");
        return $materias;
    }
    public function HorarioLista($institucion,$periodo){
        $horario = DB::SELECT("SELECT * FROM mat_horario h
        WHERE h.institucion_id = '$institucion'
        AND h.periodo_id = '$periodo'
        ORDER BY id DESC 
        LIMIT 1
        ");
        return $horario;
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
        //
    }
    public function asignarDocenteCurso(Request $request){
        if($request->id >0){
            $asignatura = AsignaturaDocente::findOrFail($request->id);
        }else{
            $asignatura = new AsignaturaDocente();
        }
        $asignatura->docente_id = $request->docente_id;
        $asignatura->save();
    }
    public function guardarConfiguracionHorario(Request $request){
        //validar si existe la configuracion de horario para la institucion en el periodo
        $validate = $this->HorarioLista($request->institucion_id,$request->periodo_id);
        if(empty($validate)){
            $configuracion = new MatHorario();
        }else{
            $configuracion = MatHorario::findOrFail($validate[0]->id);
        }
        $configuracion->institucion_id      = $request->institucion_id;
        $configuracion->periodo_id          = $request->periodo_id;
        $configuracion->hora_inicio         = $request->hora_inicio;
        $configuracion->hora_fin            = $request->hora_fin;
        $configuracion->lapso_minutos       = $request->lapso_minutos;
        $configuracion->save();
        if($configuracion){
            return ["status" => "1","message" => "Se guardo correctamente"];
        }else{
            return ["status" => "0","message" => "No se pudo guardar"];
        } 
    }
    public function getHorarioCurso($idcurso){
        $cursoHorario = $this->validateCursoHorario($idcurso);
        if(empty($cursoHorario)){
            return ["status" => "0", "message" => "El horario aun no se ha generado"];
        }else{
            $id = $cursoHorario[0]->id;
            return $this->getHorario($id);
        }
    }
    public function validarConfiguracionHorario(Request $request){
        $cursoHorario = $this->validateCursoHorario($request->curso_id);
        if(empty($cursoHorario)){
            return $this->crearHorario($request->hora_inicio,$request->hora_fin,$request->lapso_minutos,$request->horario_id,$request->curso_id,$request->institucion_id,$request->periodo_id);
        }else{
            $id = $cursoHorario[0]->id;
            return $this->getHorario($id);
        }
    }
    public function getHorario($id){
        $getDetalles = DB::SELECT("SELECT d.*,
        ls.nombrelibro AS materiaSabado,ld.nombrelibro AS materiaDomingo,
        CONCAT(us.nombres, ' ',us.apellidos) AS docenteSabado,
        CONCAT(ud.nombres, ' ',ud.apellidos) AS docenteDomingo,
        ms.docente_id as docente_idSabado, md.docente_id as docente_idDomingo,
        h.estado
        FROM mat_horario_clases_detalles d
        LEFT JOIN asignaturausuario ms ON d.Sabado = ms.idasiguser
        LEFT JOIN libro ls ON ms.asignatura_idasignatura = ls.idlibro
        LEFT JOIN usuario us ON ms.docente_id = us.idusuario
        LEFT JOIN asignaturausuario md ON d.Domingo = md.idasiguser
        LEFT JOIN libro ld ON md.asignatura_idasignatura = ld.idlibro
        LEFT JOIN usuario ud ON md.docente_id = ud.idusuario
        LEFT JOIN mat_horario_clases h ON d.mat_horario_clases_id = h.id
        WHERE d.mat_horario_clases_id = '$id'
        ");
        return ["detalles" => $getDetalles];
    }
    public function validateCursoHorario($idcurso){
        $validate = DB::SELECT("SELECT * FROM mat_horario_clases
        WHERE curso_id = '$idcurso'
        ");
        return $validate;
    }
    function resum($in,$fin,$minutos,$columnas,$horarioClase,$idcurso,$institucion,$periodo){
        $time = new DateTime($in);
        $time->add(new DateInterval('PT' . $minutos . 'M'));
        $stamp = $time->format('h:i a');
        $format24 = $time ->format('G:i');
        $this->saveHorario($horarioClase,$in,$stamp,$idcurso,$institucion,$periodo);
        // echo '<strong id="data'.sha1($in).'">'.date('h:i a', strtotime($in)). ' - ' .$stamp.'</strong>';
        // echo "<br/>";  
        $this->sumtime($format24,$fin,$minutos,$columnas,$horarioClase,$idcurso,$institucion,$periodo);
    }
    function sumtime($in,$fin,$minutos,$columnas,$horarioClase,$idcurso,$institucion,$periodo){
        $parse1 = new DateTime($in);
        $parse2 = new DateTime($fin);   
        if ($parse2 <= $parse1){
            $getDetalles = [];
            return ["detalles" => $getDetalles];
        }else{
        $time = new DateTime($in);
        $time->add(new DateInterval('PT' . $minutos . 'M'));
        $stamp = $time->format('h:i a');
        $format24 = $time ->format('G:i');
        $this->saveHorario($horarioClase,$in,$stamp,$idcurso,$institucion,$periodo);
        // echo '<strong id="data'.sha1($in).'">'.date('h:i a', strtotime($in)). ' - ' .$stamp.'</strong>';
        // echo "<br/>";  
        $this->resum($format24,$fin,$minutos,$columnas,$horarioClase,$idcurso,$institucion,$periodo);
        }       
    }
    //api para ya no editar el horario
    public function desactivarHorario(Request $request){
        $horarioClase = MatHorarioClases::findOrFail($request->id);
        $horarioClase->estado = 0;
        $horarioClase->save();
    }
    public function crearHorario($hora_inicio,$hora_fin,$minutos,$horario_id,$idcurso,$institucion,$periodo){
        $horarioClase = new MatHorarioClases();
        $horarioClase->horario_id   = $horario_id;
        $horarioClase->curso_id     = $idcurso;
        $horarioClase->save();
        $countdays = 3;
        // Hora Inicio 24 Horas
        $inicio24 = date('G:i', strtotime($hora_inicio));
        // Hora Final 24 Horas
        $final24 = date('G:i', strtotime($hora_fin));
        //proceso
        return $this->sumtime($inicio24,$final24,$minutos,$countdays,$horarioClase->id,$idcurso,$institucion,$periodo);
    }
    public function saveHorario($mat_horario_clases_id,$hora_inicio,$hora_fin,$idcurso,$institucion,$periodo){
        $horarioDetalles                        = new MatHorarioClasesDetalles();
        $horarioDetalles->mat_horario_clases_id = $mat_horario_clases_id;
        $horarioDetalles->hora_inicio           = $hora_inicio;
        $horarioDetalles->hora_fin              = $hora_fin;
        $horarioDetalles->idcurso               = $idcurso;
        $horarioDetalles->institucion_id        = $institucion;
        $horarioDetalles->periodo_id            = $periodo;
        $horarioDetalles->save();
    }
    public function generarHorario(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        //limpiar para generar 
        $this->limpiarHorario($request->idcurso);
        //Variables
        $ids        = json_decode($request->arregloIds);
        $materias   = json_decode($request->materias);
        //proceso
        foreach($materias as $key => $item){
            $ingresar = false;
            while ($ingresar == false) {
                $id  = $this->makeid(1,$ids);
                $columna =$this->makeDay(1);
                //validar si existe ya la materia
                $validar =  DB::table('mat_horario_clases_detalles')
                ->where('id', $id)
                ->where($columna, '=',$item->idasiguser)
                ->get();
                if(count($validar) == 0){
                     //save
                    if($columna == 'Sabado'){
                        $registro = MatHorarioClasesDetalles::findOrFail($id);
                        $hora_inicio = $registro->hora_inicio;
                        //Validacion para que no choquen las horas del docente
                        $validacionDisponibleDocente = $this->disponibilidadSabado($request->institucion_id,$request->periodo_id,$item->docente_id,$hora_inicio);
                        if(empty($validacionDisponibleDocente)){
                             //validacion para no ingresar donde ya haya una materia  ingresada
                            $validate = DB::SELECT("SELECT * FROM mat_horario_clases_detalles d
                            WHERE d.id = $id
                            AND d.Sabado > 0
                            ");
                            if(empty($validate)){
                                $registro->Sabado = $item->idasiguser;
                                $registro->save();
                            }else{
                                $registro = false;
                            }
                        }else{
                            $registro = false;
                        }
                       
                    }else{
                        $registro = MatHorarioClasesDetalles::findOrFail($id);
                        $hora_inicio = $registro->hora_inicio;
                        //Validacion para que no choquen las horas del docente
                        $validacionDisponibleDocente =  $validacionDisponibleDocente = $this->disponibilidadDomingo($request->institucion_id,$request->periodo_id,$item->docente_id,$hora_inicio);
                        if(empty($validacionDisponibleDocente)){
                             //validacion para no ingresar donde ya haya una materia  ingresada
                            $validate = DB::SELECT("SELECT * FROM mat_horario_clases_detalles d
                            WHERE d.id = $id
                            AND d.Domingo > 0
                            ");
                            if(empty($validate)){
                                $registro->Domingo = $item->idasiguser;
                                $registro->save();
                            }else{
                                $registro = false;
                            }
                        }else{
                            $registro = false;
                        }
                    }
                    //VALIDACION
                    if($registro){
                        $ingresar = true;
                    }else{
                        $ingresar = false;
                    }
                }else{
                    $ingresar = true;
                }
            }
        }
        return "se guardo";
    }
    public function disponibilidadSabado($institucion,$periodo,$docente,$hora_inicio){
        $disponible = DB::SELECT("SELECT d.*
        FROM mat_horario_clases_detalles d
        LEFT JOIN asignaturausuario ms ON d.Sabado = ms.idasiguser
        LEFT JOIN mat_niveles_institucion mn ON d.idcurso = mn.nivelInstitucion_id
        WHERE ms.docente_id = '$docente'
        AND d.hora_inicio = '$hora_inicio'
        AND mn.periodo_id = '$periodo'
        AND mn.institucion_id = '$institucion'
        AND d.Sabado > 0
        ");
        return $disponible;
    }
    public function disponibilidadDomingo($institucion,$periodo,$docente,$hora_inicio){
        $disponible = DB::SELECT("SELECT d.*
        FROM mat_horario_clases_detalles d
        LEFT JOIN asignaturausuario md ON d.Domingo = md.idasiguser
        LEFT JOIN mat_niveles_institucion mn ON d.idcurso = mn.nivelInstitucion_id
        WHERE md.docente_id = '$docente'
        AND d.hora_inicio = '$hora_inicio'
        AND mn.periodo_id = '$periodo'
        AND mn.institucion_id = '$institucion'
        AND  d.Domingo > 0
        ");
        return $disponible;
    }
    public function limpiarHorario($curso){
        DB::UPDATE("UPDATE mat_horario_clases_detalles d
        SET sabado = 0,
        domingo = 0
        WHERE d.idcurso = '$curso'
        ");
    }
    public function quitarMateriaHorario(Request $request){
        //validacion para que no choquen las horas
        $this->changeHorario($request->dia,$request->id,0);
    
    }
    public function changeMateriaHorario(Request $request){
        if($request->dia == "Sabado"){
            $validacionDisponibleDocente = $this->disponibilidadSabado($request->institucion_id,$request->periodo_id,$request->docente_id,$request->hora_inicio);
                if(empty($validacionDisponibleDocente)){
                    $this->changeHorario($request->dia,$request->id,$request->materia_id);
                }else{
                    return ["status" => "3", "message" => "El docente no se encuentra disponible para las $request->hora_inicio del Sabado"];
                }
        }else{
            $validacionDisponibleDocente = $this->disponibilidadDomingo($request->institucion_id,$request->periodo_id,$request->docente_id,$request->hora_inicio);
                if(empty($validacionDisponibleDocente)){
                    $this->changeHorario($request->dia,$request->id,$request->materia_id);
                }else{
                    return ["status" => "3", "message" => "El docente no se encuentra disponible para las $request->hora_inicio del Domingo"];
                }
        }
    }
    public function changeHorario($dia,$id,$valor){
        if($dia == 'Sabado'){
            $change = MatHorarioClasesDetalles::findOrFail($id);
            $change->Sabado  = $valor;
            $change->save();
        }else{
            $change = MatHorarioClasesDetalles::findOrFail($id);
            $change->Domingo  = $valor;
            $change->save();
        }
        if($change){
            return ["status" => "1", "message" => "Se quito la materia correctamente"];
        }else{
            return ["status" => "0", "message" => "No se pudo quitar la materia"];
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
