<?php

namespace App\Http\Controllers;
use DB;
use App\Quotation;
use App\Models\Periodo;
use Illuminate\Http\Request;

class PeriodoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //para traer los periodos sierra y costa  inactivos
        if($request->porEstados){
            $sierra = DB::SELECT("SELECT p.* FROM periodoescolar p
            WHERE p.region_idregion = '1'
            AND p.estado = '0'
            ORDER BY p.idperiodoescolar DESC
            ");

            $costa = DB::SELECT("SELECT p.* FROM periodoescolar p
            WHERE p.region_idregion = '2'
            AND p.estado = '0'
            ORDER BY p.idperiodoescolar DESC
            ");

            return ["sierra" => $sierra, "costa" => $costa];
        }
        //para filtrar por el estado los periodos
        if($request->porEstado){
            $periodos = DB::SELECT("SELECT p.* FROM periodoescolar p
            WHERE p.region_idregion = '$request->region'
            AND p.estado = '$request->estado'
            ");
            return $periodos;
        }
        if($request->porAllEstados){
            $periodos = DB::SELECT("SELECT p.*,
            IF(p.estado = '1',CONCAT(p.periodoescolar,' ','activo'),CONCAT(p.periodoescolar,' ','desactivado')) AS periodo
             FROM periodoescolar p
              WHERE p.region_idregion = '$request->region'
              ORDER BY p.idperiodoescolar DESC
         
            ");
            return $periodos;
        }
        //traer todos los periodos
        else{
            $periodo = DB::SELECT("SELECT * FROM periodoescolar ORDER BY idperiodoescolar  DESC");
            return $periodo;
        }
      
    
    }

    

    public function usuariosXperiodoSierra(Request $request){
        
        set_time_limit(6000);
        ini_set('max_execution_time', 6000);

        if($request->periodoSierra == ""){
            return ["status" => "0", "message" => "No se encontro datos"];
        }

        $estudiantePeriodo = DB::SELECT("SELECT DISTINCT  u.*, c.id_periodo ,p.periodoescolar FROM codigoslibros  c
            LEFT JOIN  usuario u  ON c.idusuario = u.idusuario
            LEFT JOIN  periodoescolar p ON c.id_periodo = p.idperiodoescolar
            WHERE u.id_group = '4'
            AND c.id_periodo = '$request->periodoSierra'
            AND u.estado_idEstado = '1'
            AND u.institucion_idInstitucion <> 66
            AND u.institucion_idInstitucion <> 981");

            $DocentePeriodo = DB::SELECT("SELECT DISTINCT u.* , c.id_periodo ,p.periodoescolar FROM curso c
            LEFT JOIN  usuario u  ON c.idusuario = u.idusuario
            LEFT JOIN  periodoescolar p ON c.id_periodo = p.idperiodoescolar
            WHERE u.id_group = '6'
            AND c.id_periodo = '$request->periodoSierra'
            AND u.estado_idEstado = '1'
            AND u.institucion_idInstitucion <> 66
            AND u.institucion_idInstitucion <> 981");
       

            $estudiantes =[];
            $docentes =[];

            if(count($estudiantePeriodo) > 0){
                $estudiantes =[
                    "cantidad" => count($estudiantePeriodo),
                    "periodo" => $estudiantePeriodo[0]->id_periodo,
                    "nombre_periodo" => $estudiantePeriodo[0]->periodoescolar
                ];
            }else{
                $estudiantes =[
                    "cantidad" => "0",
                    "periodo" => "0",
                    "nombre_periodo" => "0"
                ];     
            }

            if(count($DocentePeriodo) > 0){
                $docentes =[
                    "cantidad" => count($DocentePeriodo),
                    "periodo" => $DocentePeriodo[0]->id_periodo,
                    "nombre_periodo" => $estudiantePeriodo[0]->periodoescolar
                ];

            }else{  
                $docentes =[
                    "cantidad" => "0",
                    "periodo" => "0",
                    "nombre_periodo" => "0"
                ];

            }

    

            return ["estudiantes" => $estudiantes ,"docentes" => $docentes];
    }


    public function usuariosXperiodoCosta(Request $request){
        
        set_time_limit(6000);
        ini_set('max_execution_time', 6000);

        if($request->periodoCosta == ""){
            return ["status" => "0", "message" => "No se encontro datos"];
        }

        $estudiantePeriodo = DB::SELECT("SELECT DISTINCT  u.*, c.id_periodo ,p.periodoescolar FROM codigoslibros  c
            LEFT JOIN  usuario u  ON c.idusuario = u.idusuario
            LEFT JOIN  periodoescolar p ON c.id_periodo = p.idperiodoescolar
            WHERE u.id_group = '4'
            AND c.id_periodo = '$request->periodoCosta'
            AND u.estado_idEstado = '1'
            AND u.institucion_idInstitucion <> 66
            AND u.institucion_idInstitucion <> 981");

            $DocentePeriodo = DB::SELECT("SELECT DISTINCT u.* , c.id_periodo ,p.periodoescolar FROM curso c
            LEFT JOIN  usuario u  ON c.idusuario = u.idusuario
            LEFT JOIN  periodoescolar p ON c.id_periodo = p.idperiodoescolar
            WHERE u.id_group = '6'
            AND c.id_periodo = '$request->periodoCosta'
            AND u.estado_idEstado = '1'
            AND u.institucion_idInstitucion <> 66
            AND u.institucion_idInstitucion <> 981");
       

            $estudiantes =[];
            $docentes =[];

            if(count($estudiantePeriodo) > 0){
                $estudiantes =[
                    "cantidad" => count($estudiantePeriodo),
                    "periodo" => $estudiantePeriodo[0]->id_periodo,
                    "nombre_periodo" => $estudiantePeriodo[0]->periodoescolar
                ];
            }else{
                $estudiantes =[
                    "cantidad" => "0",
                    "periodo" => "0",
                    "nombre_periodo" => "0"
                ];     
            }

            if(count($DocentePeriodo) > 0){
                $docentes =[
                    "cantidad" => count($DocentePeriodo),
                    "periodo" => $DocentePeriodo[0]->id_periodo,
                    "nombre_periodo" => $estudiantePeriodo[0]->periodoescolar
                ];

            }else{  
                $docentes =[
                    "cantidad" => "0",
                    "periodo" => "0",
                    "nombre_periodo" => "0"
                ];

            }

    

            return ["estudiantes" => $estudiantes ,"docentes" => $docentes];
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function periodoRegion(Request $request)
    {
        if($request->region =="SIERRA"){
            $periodo = DB::SELECT("SELECT * FROM periodoescolar
            WHERE  region_idregion  = '1'
           
             ORDER BY idperiodoescolar  DESC");
            return $periodo;
        }
        if($request->region =="COSTA"){
            $periodo = DB::SELECT("SELECT * FROM periodoescolar
            WHERE  region_idregion  = '2'
            
             ORDER BY idperiodoescolar  DESC");
            return $periodo;
        }else{
            return ["status"=> "0", "message"=>"NO SE ENCONTRO LA REGiON"];
        }
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
            $periodo = Periodo::find($request->id);
            $periodo->fecha_inicial = $request->fecha_inicial;
            $periodo->fecha_final = $request->fecha_final;
            $periodo->region_idregion = $request->region_idregion;
            $periodo->descripcion = $request->descripcion;
            $periodo->periodoescolar = $request->periodoescolar;
            $periodo->fhasta_limite = $request->fhasta_limite;
    
        }else{
        

            $periodo = new Periodo();
            $periodo->fecha_inicial = $request->fecha_inicial;
            $periodo->fecha_final = $request->fecha_final;
            $periodo->region_idregion = $request->region_idregion;
            $periodo->descripcion = $request->descripcion;
            $periodo->periodoescolar = $request->periodoescolar;
            $periodo->fhasta_limite = $request->fhasta_limite;
            
        }


        
       
        $periodo->save();

        return $periodo;
       
    }

    public function select()
    {
        $periodo = DB::select("SELECT * FROM periodoescolar inner join region on periodoescolar.region_idregion = region.idregion ");
        return $periodo;
    }
    public function institucion(Request $request)
    {
        $id=$request->idInstitucion;
        if($id == 66){
            return 1;
        }else{
            $periodo = DB::select("SELECT * 
            FROM periodoescolar_has_institucion
            INNER JOIN periodoescolar ON periodoescolar.idperiodoescolar = periodoescolar_has_institucion.periodoescolar_idperiodoescolar
            WHERE institucion_idInstitucion = ? AND periodoescolar.estado = '1' ",[$id]);
            if(empty($periodo)){
                return 0;
            }else{
                return 1;
            }
        }
    }

    public function activar(Request $request){
        $idperiodoescolar=$request->idperiodoescolar;
        $res = DB::table('periodoescolar')
        ->where('idperiodoescolar', $idperiodoescolar)
        ->update(['estado' => "1"]);
         return $res;
        
    }

    public function desactivar(Request $request){
        $idperiodoescolar=$request->idperiodoescolar;
        $res = DB::table('periodoescolar')
        ->where('idperiodoescolar', $idperiodoescolar)
        ->update(['estado' => "0"]);
         return $res;
    }

 
    public function UsuariosPeriodo(Request $request){
        set_time_limit(6000);
        ini_set('max_execution_time', 6000);



        $periodo = DB::SELECT("SELECT DISTINCT  p.* FROM periodoescolar p
        LEFT JOIN  codigoslibros c ON p.idperiodoescolar  = c.id_periodo
        WHERE  p.estado = '1'");

        
        $datos =[];
        $estudiantes=[];
        foreach($periodo as $key => $item){
            
            $estudiantePeriodo = DB::SELECT("SELECT DISTINCT  u.*, c.id_periodo ,p.periodoescolar FROM codigoslibros  c
            LEFT JOIN  usuario u  ON c.idusuario = u.idusuario
            LEFT JOIN  periodoescolar p ON c.id_periodo = p.idperiodoescolar
            WHERE u.id_group = '4'
            AND c.id_periodo = '$item->idperiodoescolar'
            AND u.estado_idEstado = '1'
            AND u.institucion_idInstitucion <> 66
            AND u.institucion_idInstitucion <> 981");

    
        $DocentePeriodo = DB::SELECT("SELECT DISTINCT u.* , c.id_periodo ,p.periodoescolar FROM curso c
            LEFT JOIN  usuario u  ON c.idusuario = u.idusuario
            LEFT JOIN  periodoescolar p ON c.id_periodo = p.idperiodoescolar
            WHERE u.id_group = '6'
            AND c.id_periodo = '$item->idperiodoescolar'
            AND u.estado_idEstado = '1'
            AND u.institucion_idInstitucion <> 66
            AND u.institucion_idInstitucion <> 981");
            
            $estudiantes[$key] =[
                "cantidad" => count($estudiantePeriodo),
                "periodo" => $estudiantePeriodo[$key]->id_periodo,
                "nombre_periodo" => $estudiantePeriodo[$key]->periodoescolar
            ];

            $docentes[$key] =[
                "cantidad" => count($DocentePeriodo),
                "periodo" => $DocentePeriodo[$key]->id_periodo,
                "nombre_periodo" => $estudiantePeriodo[$key]->periodoescolar
            ];

        
        }

        $datos = [
            "estudiantes" => $estudiantes,
            "docentes" => $docentes
            
        ];
        return $datos;

    
     
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Periodo  $periodo
     * @return \Illuminate\Http\Response
     */
    public function edit(Periodo $periodo)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Periodo  $periodo
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Periodo $periodo)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Periodo  $periodo
     * @return \Illuminate\Http\Response
     */
    public function destroy(Periodo $periodo)
    {
        //
    }

    public function periodoActivo()
    {
        $periodo = DB::SELECT("SELECT * FROM periodoescolar WHERE estado = '1' ");
        return $periodo;
    }

    public function periodoActivoPorRegion(Request $request)
    {
        if($request->region =="SIERRA"){
            $periodo = DB::SELECT("SELECT descripcion, idperiodoescolar,periodoescolar,estado,region_idregion FROM periodoescolar
            WHERE  region_idregion  = '1' AND estado = '1'
           
             ORDER BY idperiodoescolar  DESC");
            return $periodo;
        }
        if($request->region =="COSTA"){
            $periodo = DB::SELECT("SELECT descripcion, idperiodoescolar,periodoescolar,estado,region_idregion FROM periodoescolar
            WHERE  region_idregion  = '2' AND estado = '1'
            
             ORDER BY idperiodoescolar  DESC");
            return $periodo;
        }else{
            return ["status"=> "0", "message"=>"NO SE ENCONTRO LA REGiON"];
        }
    }

    
}
