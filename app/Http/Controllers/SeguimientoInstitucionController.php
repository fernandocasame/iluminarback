<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Agenda;
use App\Models\InstitucionFueraProlipa;
use Illuminate\Http\Request;
use DB;
use App\Models\SeguimientoMuestraDetalle;
use App\Models\SeguimientoInstitucion;
use App\Models\SeguimientoInstitucionTemporal;
use App\Models\SeguimientoMuestra;

class SeguimientoInstitucionController extends Controller
{
      
    public function makeid(){
        $characters = '123456789abcdefghjkmnpqrstuvwxyz';
        $charactersLength = strlen($characters);
   
        $randomString = '';
        for ($i = 0; $i < 5; $i++) {
            for ($i = 0; $i < 16; $i++) {
                $randomString .= $characters[rand(0, $charactersLength - 1)];
            }
            return $randomString;
      
        
         }   
    }
   
    //para traer el detalle de las muestras
    //api::get/muestraDetalle
    public function muestraDetalle(Request $request){
        $detalle = DB::SELECT("SELECT d.*, l.nombrelibro FROM seguimiento_muestra_detalle d
        LEFT JOIN libro l ON d.libro_id = l.idlibro
        WHERE d.muestra_id = '$request->muestra_id'
        ");
        return $detalle;
    }

    public function traerPeriodo($institucion_id){
        $periodoInstitucion = DB::SELECT("SELECT idperiodoescolar AS periodo , periodoescolar AS descripcion FROM periodoescolar WHERE idperiodoescolar = ( 
            SELECT  pir.periodoescolar_idperiodoescolar as id_periodo
            from institucion i,  periodoescolar_has_institucion pir         
            WHERE i.idInstitucion = pir.institucion_idInstitucion
            AND pir.id = (SELECT MAX(phi.id) AS periodo_maximo FROM periodoescolar_has_institucion phi
            WHERE phi.institucion_idInstitucion = i.idInstitucion
            AND i.idInstitucion = '$institucion_id'))
        ");
        if(count($periodoInstitucion)>0){
            return ["status" => "1", "message"=>"correcto","periodo" => $periodoInstitucion];
        }else{
            return ["status" => "0", "message"=>"no hay periodo"];
        }
    }
    public function index(Request $request)
    {
        if($request->pendientes){
            $institucionesProlipa = $this->pendientesProlipa($request->asesor_id);
            $institucionesTemporales = $this->pendientesProlipaTemporales($request->asesor_id);
            return[
                "institucionesProlipa" => $institucionesProlipa,
                "institucionesTemporales" => $institucionesTemporales
            ];
        }
        if($request->registrarPendiente){
            $agenda =  Agenda::findOrFail($request->id);
            $agenda->estado = "1";
            $agenda->save();
            if($agenda){
                return ["status" => "1", "message" => "Se guardo correctamente"];
            }else{
             return ["status" => "0", "message" => "No se pudo guardar"];
            }
        }
        else{
            $asesores = DB::SELECT("SELECT DISTINCT u.idusuario,u.cedula, CONCAT(u.nombres, ' ', u.apellidos) as vendedor
            
            FROM usuario u, institucion i
            WHERE u.id_group = '11'
            AND i.vendedorInstitucion = u.cedula
            AND u.estado_idEstado  ='1'
            ORDER BY u.apellidos ASC
            ");
            return $asesores;
        }
    }



     //para marcar como registrado  la planificacion del asesor
      public function registrar(Request $request){
        $seguimiento = Agenda::findOrFail($request->id);
        $seguimiento->estado = "1";
        $seguimiento->save();
        
     }

     //para traer los registros pendientes de la agenda
     public function pendientesProlipa($asesor){
        $registros = DB::SELECT("SELECT a.* ,p.idperiodoescolar,p.periodoescolar AS periodo, i.nombreInstitucion,
        CONCAT(u.nombres, ' ',u.apellidos)AS vendedor
        FROM agenda_usuario a
        LEFT JOIN usuario u ON a.id_usuario = u.idusuario
        LEFT JOIN periodoescolar p ON a.periodo_id = p.idperiodoescolar
        LEFT JOIN institucion i ON a.institucion_id = i.idInstitucion
        WHERE a.id_usuario = u.idusuario
        AND u.id_group = '11'
        AND a.id_usuario = '$asesor'
        AND a.estado = '0'
        AND a.estado_institucion_temporal = '0' 
        ORDER BY a.id DESC");
        return $registros;
    }
     //para traer los registros pendientes de la agenda
     public function pendientesProlipaTemporales($asesor){
        $registros = DB::SELECT("SELECT a.* ,p.idperiodoescolar,p.periodoescolar AS periodo, i.nombreInstitucion FROM agenda_usuario a
        LEFT JOIN usuario u ON a.id_usuario = u.idusuario
        LEFT JOIN periodoescolar p ON a.periodo_id = p.idperiodoescolar
        LEFT JOIN institucion i ON a.institucion_id = i.idInstitucion
        WHERE a.id_usuario = u.idusuario
        AND u.id_group = '11'
        AND a.id_usuario = '$asesor'
        AND a.estado = '0'
        AND a.estado_institucion_temporal = '1' 
        ORDER BY a.id DESC");
        return $registros;
    }

    //api::get>>/asesor/seguimiento
    public function visitas(Request $request){

  
        if($request->muestra){    
            $muestras = $this->listadoMuestras($request->institucion_id,$request->asesor_id,$request->periodo_id,$request->nombreInstitucion);
            return $muestras;
        }
       
        else{
            $seguimiento = $this->listadoSeguimiento($request->institucion_id,$request->asesor_id,$request->periodo_id,$request->nombreInstitucion);
            return $seguimiento;
        }
   
    }
    //para eliminar la visita / capacitacion/presentacion
     public function eliminar(Request $request){
        $seguimiento = SeguimientoInstitucion::findOrFail($request->id);
        $seguimiento->estado = "2";
        $seguimiento->save();
        
     }
    //para guardar la institucion
    public function GuardarInstitucionTemporal(Request $request){
  
        //obtener el periodo de la region
        $buscarPeriodo = $this->periodosActivosIndividual($request->region);
        $periodo = $buscarPeriodo[0]->idperiodoescolar;
        $institucion = new SeguimientoInstitucionTemporal;
        $institucion->nombre_institucion = $request->nombre_institucion;
        $institucion->ciudad = $request->ciudad;
        $institucion->region = $request->region;
        $institucion->asesor_id = $request->asesor_id;
        $institucion->periodo_id = $periodo;
        if($request->tipo){
            $institucion->tipo = '1';
        }
        $institucion->save();
        return $institucion;
       
    }

    public function periodosActivosIndividual($region){
        $periodo = DB::SELECT("SELECT DISTINCT  p.* FROM periodoescolar p
        LEFT JOIN  codigoslibros c ON p.idperiodoescolar  = c.id_periodo
        WHERE  p.estado = '1'
        AND p.region_idregion = '$region'
        ");
        return $periodo;
    }

    public function getPlanificacionesAsesor(Request $request){
        $planificaciones = DB::SELECT("SELECT i.nombreInstitucion,p.periodoescolar,DATE_FORMAT(startDate,'%Y-%m-%d')  as fecha ,
         a.* FROM agenda_usuario a
        LEFT JOIN institucion i ON a.institucion_id = i.idInstitucion
        LEFT JOIN periodoescolar p ON a.periodo_id = p.idperiodoescolar
        WHERE a.id_usuario = '$request->asesor_id'
        AND a.estado <> '2'
        ORDER BY a.id DESC
        LIMIT 500
        ");
        return $planificaciones;
    }
    //api para que el asesor complete las instituciones que esten en nulo
    public function completeInstituciones(Request $request){
        $complete = DB::SELECT("SELECT a.* FROM agenda_usuario  a
        WHERE a.id_usuario = '$request->asesor_id'
        AND (a.institucion_id IS NULL OR a.institucion_id = '' OR a.institucion_id ='0')
        AND (a.institucion_id_temporal IS NULL OR a.institucion_id_temporal = '' OR a.institucion_id_temporal ='0')
        ");
        return $complete;
    }

    public function save_planificacion(Request $request)
    {
        if($request->desactivar){
            $agenda = Agenda::find($request->id);
            $agenda->estado            = "2";
            $agenda->usuario_editor = $request->usuario_editor;
            $agenda->save();
            return ["status" => "1","message" => "Se elimino correctamente"];
        }
        if( $request->id != 0 ){
            $agenda = Agenda::find($request->id);
            if($request->finalizar){
                $agenda->estado            = "1";
                $agenda->dias_desface      = $request->dias_desface;
                $agenda->fecha_que_visita  = $request->fecha_que_visita;
                $agenda->usuario_editor = $request->usuario_editor;
            }
            
        }else{
            $agenda = new Agenda();
            $agenda->usuario_creador = $request->usuario_editor;
        }
        //si hace los cambios el administrador
        
        //si crean una insitucion temporal
        if($request->estado_institucion_temporal == 1){
            $agenda->periodo_id = $request->periodo_id_temporal;
            $agenda->institucion_id_temporal = $request->institucion_id_temporal;
            $agenda->nombre_institucion_temporal = $request->nombreInstitucion;
            $agenda->institucion_id = "";
        } 
        if($request->estado_institucion_temporal == 0){
            $agenda->institucion_id = $request->institucion_id;
            $agenda->institucion_id_temporal = "";
            $agenda->nombre_institucion_temporal = "";
               //para traer el periodo
            $buscarPeriodo = $this->traerPeriodo($request->institucion_id);
            if($buscarPeriodo["status"] == "1"){
                $obtenerPeriodo = $buscarPeriodo["periodo"][0]->periodo;
                $agenda->periodo_id = $obtenerPeriodo;
                
            }
        }
        $agenda->id_usuario = $request->idusuario;
        $agenda->title = $request->title;
        $agenda->label = $request->label;
        $agenda->classes = $request->classes;
        $agenda->startDate = $request->startDate;
        $agenda->endDate = $request->endDate;
        $agenda->hora_inicio = $request->hora_inicio;
        $agenda->hora_fin = $request->hora_fin;
        if($request->url == "null"){
            $agenda->url = "";
        }else{
            $agenda->url = $request->url;
        }
        $agenda->opciones = $request->opciones;
        $agenda->estado_institucion_temporal =$request->estado_institucion_temporal;
        $agenda->save();
        //guardar en caso que sea otra editorial
        if($request->otraEditorial == "true"){
        
            //para editar
            if( $request->id != 0 ){
               
               $validar = DB::SELECT("SELECT * FROM  institucion_fuera_prolipa where  asesor_planificacion_id = '$agenda->id'");
               if(count($validar) == 0){
                $this->guardarInstitucionFueraProlipa($request->nombre_editorial,$request->idusuario,$agenda->id,$request->estado_institucion_temporal,$agenda->periodo_id,$request->institucion_id_temporal,$request->nombreInstitucion,$request->institucion_id);
               }else{
                    if($request->estado_institucion_temporal == 1 ){
                        DB::table('institucion_fuera_prolipa')
                        ->where('asesor_planificacion_id', $agenda->id)
                        ->update([
                            'estado_institucion_temporal' =>  $request->estado_institucion_temporal,
                            'periodo_id' =>  $agenda->periodo_id,
                            'nombre_editorial' =>  $request->nombre_editorial,
                            'institucion_id_temporal' =>  $request->institucion_id_temporal,
                            'nombre_institucion_temporal' =>  $request->nombreInstitucion,
                            'institucion_id' =>  "",
                        ]);
                    }
        
                    if($request->estado_institucion_temporal == 0){
                        DB::table('institucion_fuera_prolipa')
                        ->where('asesor_planificacion_id', $agenda->id)
                        ->update([
                            'nombre_editorial' =>  $request->nombre_editorial,
                            'estado_institucion_temporal' =>  $request->estado_institucion_temporal,
                            'periodo_id' =>   $agenda->periodo_id,
                            'institucion_id' =>  $request->institucion_id,
                            'institucion_id_temporal' =>  "",
                            'nombre_institucion_temporal' =>  "",
                        ]);
                    }
               }
            }else{
          
                $this->guardarInstitucionFueraProlipa($request->nombre_editorial,$request->idusuario,$agenda->id,$request->estado_institucion_temporal,$agenda->periodo_id,$request->institucion_id_temporal,$request->nombreInstitucion,$request->institucion_id);
            }
        }  
        return 
        $agenda;
    }

    public function guardarInstitucionFueraProlipa($nombre_editorial,$idusuario,$id,$estado_institucion_temporal,$periodo_id,$institucion_id_temporal,$nombreInstitucion,$institucion_id){
        $otra_institucion = new InstitucionFueraProlipa();
        //si crean una insitucion temporal
        $otra_institucion->nombre_editorial = $nombre_editorial;
        $otra_institucion->asesor_id = $idusuario;
        $otra_institucion->asesor_planificacion_id = $id;
        $otra_institucion->estado_institucion_temporal =$estado_institucion_temporal;
        $otra_institucion->periodo_id = $periodo_id;
        //si es institucion no esta registrada en  prolipa
       if($estado_institucion_temporal == 1 ){
           $otra_institucion->institucion_id_temporal = $institucion_id_temporal;
           $otra_institucion->nombre_institucion_temporal = $nombreInstitucion;
           $otra_institucion->institucion_id = "";
       }
       //si es institucion  registrada en  prolipa
       if($estado_institucion_temporal == 0){
           $otra_institucion->institucion_id = $institucion_id;
           $otra_institucion->institucion_id_temporal = "";
           $otra_institucion->nombre_institucion_temporal = "";
           
       }
       $otra_institucion->save();
    }



    public function guardarSeguimiento(Request $request){
        

        //para editar el seguimiento
        if($request->id > 0){
           
            $seguimiento = SeguimientoInstitucion::findOrFail($request->id);
            $seguimiento->fecha_genera_visita   = $request->fecha_genera_visita;
            $seguimiento->observacion           = $request->observacion;
            $seguimiento->opciones              = $request->opciones;
            //Cuando se coloca la fecha que visita se cambia a estado finalizado
            if($request->finalizar){
                $seguimiento->estado            = "1";
                $seguimiento->fecha_que_visita  = $request->fecha_que_visita;
            }
            //si hace los cambios el administrador
            if($request->admin){
                $seguimiento->usuario_editor  = $request->usuario_editor;
            }
              $seguimiento->estado_institucion_temporal  = $request->estado_institucion_temporal;
               //si crean una insitucion temporal
               if($request->estado_institucion_temporal == 1 ){
                $seguimiento->nombre_institucion_temporal = $request->nombreInstitucion;
                $seguimiento->institucion_id_temporal = $request->institucion_id;
                $seguimiento->institucion_id           = "";
             
                }else{
                    $seguimiento->nombre_institucion_temporal = "";
                    $seguimiento->institucion_id_temporal = "";
                    $seguimiento->institucion_id           = $request->institucion_id;
            
                }
            $seguimiento->save();
            //para guardar el seguimiento de la visita
           }else{
               
               $encontrarNumeroVisita = $this->listadoSeguimientoTipo($request->institucion_id,$request->asesor_id,$request->periodo_id,$request->tipo_seguimiento,$request->nombreInstitucion);
          
                if($encontrarNumeroVisita["status"] == 0){
                 $contador = 1;
                }else{
                    $contador = $encontrarNumeroVisita["datos"][0]->num_visita+1;
            
                }
               
               
                $seguimiento = new SeguimientoInstitucion;
                $seguimiento->num_visita   = $contador;
                $seguimiento->fecha_genera_visita   = $request->fecha_genera_visita;
                if($request->observacion == "null"){
                    $seguimiento->observacion = "";    
                }else{
                    $seguimiento->observacion = $request->observacion;   
                }
               
                $seguimiento->asesor_id                      = $request->asesor_id;
                $seguimiento->tipo_seguimiento               = $request->tipo_seguimiento;
                $seguimiento->periodo_id                     = $request->periodo_id;
                $seguimiento->opciones                       = $request->opciones;
              
                //si crean una insitucion temporal

                  //comprobar que es una institucion fuera de prolipa
                $validar = DB::SELECT("SELECT * FROM seguimiento_institucion_temporal t
                WHERE t.institucion_temporal_id = '$request->institucion_id'
                AND nombre_institucion = '$request->nombreInstitucion'
                AND estado = '1'
                ");

                if(count($validar) >0){
                    $seguimiento->nombre_institucion_temporal = $request->nombreInstitucion;
                    $seguimiento->institucion_id_temporal = $request->institucion_id;
                    $seguimiento->estado_institucion_temporal    = '1';
                }else{
                    $seguimiento->nombre_institucion_temporal = "";
                    $seguimiento->institucion_id_temporal = "";
                    $seguimiento->institucion_id                 = $request->institucion_id;
                    $seguimiento->estado_institucion_temporal    = '0';
                }

              
               if($request->admin){
                   $seguimiento->usuario_editor  = $request->usuario_editor;
               }
               $seguimiento->save();
           }
           $seguimiento->save();
           if($seguimiento){
               return ["status" => "1", "message" => "Se guardo correctamente"];
           }else{
            return ["status" => "0", "message" => "No se pudo guardar"];
           }
    }


    public function listadoSeguimiento($institucion_id,$asesor_id,$periodo_id,$nombreInstitucion){
        
        //comprobar que es una institucion fuera de prolipa
        $validar = DB::SELECT("SELECT * FROM seguimiento_institucion_temporal t
        WHERE t.institucion_temporal_id = '$institucion_id'
        AND nombre_institucion = '$nombreInstitucion'
        AND estado = '1'
        ");


        if(count($validar) >0){
          
            $visitas = DB::SELECT("SELECT  s.*,i.nombreInstitucion FROM seguimiento_cliente s
            LEFT JOIN institucion i ON s.institucion_id = i.idInstitucion
            WHERE s.institucion_id_temporal = '$institucion_id'
            AND s.asesor_id = '$asesor_id'
            AND s.periodo_id = '$periodo_id'
            AND s.estado <> 2
            ORDER BY s.id DESC
            ");
        }else{
       
            $visitas = DB::SELECT("SELECT  s.*,i.nombreInstitucion FROM seguimiento_cliente s
            LEFT JOIN institucion i ON s.institucion_id = i.idInstitucion
            WHERE s.institucion_id = '$institucion_id'
            AND s.asesor_id = '$asesor_id'
            AND s.periodo_id = '$periodo_id'
            AND s.estado <> 2
            ORDER BY s.id DESC
            ");
        } 
      

        if(count($visitas) == 0){
            return ["status" => "0", "message" => "No hay  seguimiento"];
        }else{
            return $visitas;
        }
       
    }

    
    public function listadoMuestras($institucion_id,$asesor_id,$periodo_id,$nombreInstitucion){
  

        $validar = DB::SELECT("SELECT * FROM seguimiento_institucion_temporal t
        WHERE t.institucion_temporal_id = '$institucion_id'
        AND nombre_institucion = '$nombreInstitucion'
        AND estado = '1'
        ");


        if(count($validar) >0){
            $visitas = DB::SELECT("SELECT  s.* FROM seguimiento_muestra s
            WHERE s.institucion_id_temporal = '$institucion_id'
            AND s.asesor_id = '$asesor_id'
            AND s.periodo_id = '$periodo_id'
            ORDER BY s.muestra_id DESC
            ");
        }else{

            $visitas = DB::SELECT("SELECT  s.* FROM seguimiento_muestra s
            WHERE s.institucion_id = '$institucion_id'
            AND s.asesor_id = '$asesor_id'
            AND s.periodo_id = '$periodo_id'
            ORDER BY s.muestra_id DESC
            ");
            
        }

        if(count($visitas) == 0){
            return ["status" => "0", "message" => "No hay  seguimiento"];
        }else{
            return $visitas;
        }
       
    }

    public function listadoSeguimientoTipo($institucion_id,$asesor_id,$periodo_id,$tipo,$nombreInstitucion){
        //si la institucion es fuera de prolipa
        $validar = DB::SELECT("SELECT * FROM seguimiento_institucion_temporal t
        WHERE t.institucion_temporal_id = '$institucion_id'
        AND nombre_institucion = '$nombreInstitucion'
        AND estado = '1'
        ");
   

        if(count($validar) >0){
            $visitas = DB::SELECT("SELECT  s.* FROM seguimiento_cliente s
            WHERE s.institucion_id_temporal = '$institucion_id'
            AND s.asesor_id = '$asesor_id'
            AND s.periodo_id = '$periodo_id'
            AND s.tipo_seguimiento = '$tipo'
            AND s.estado <> 2
            ORDER BY s.id DESC
            ");
        }else{
        
            $visitas = DB::SELECT("SELECT  s.* FROM seguimiento_cliente s
            WHERE s.institucion_id = '$institucion_id'
            AND s.asesor_id = '$asesor_id'
            AND s.periodo_id = '$periodo_id'
            AND s.tipo_seguimiento = '$tipo'
            AND s.estado <> 2
            ORDER BY s.id DESC
            ");
            
        }
      

        if(count($visitas) == 0){
            return ["status" => "0", "message" => "No hay  seguimiento"];
        }else{
            return ["status" => "1", "message" => "Si hay  seguimiento","datos" => $visitas];
        }
       
    }

    public function listadoSeguimientoMuestra($institucion_id,$asesor_id,$periodo_id){
        $visitas = DB::SELECT("SELECT  s.* FROM seguimiento_muestra s
        WHERE s.institucion_id = '$institucion_id'
        AND s.asesor_id = '$asesor_id'
        AND s.periodo_id = '$periodo_id'
        ORDER BY s.muestra_id DESC
        ");

        if(count($visitas) == 0){
            return ["status" => "0", "message" => "No hay  seguimiento"];
        }else{
            return ["status" => "1", "message" => "No hay  seguimiento","datos" => $visitas];
        }
       
    }


 
    public function store(Request $request)
    {
        //
    }
    public function cantidadVisitas(Request $request){
        $visitas = DB::SELECT("CALL `pr_cantidadVisitasInstitucion`('$request->fromDate','$request->toDate','$request->asesor_id');
        ");
        $visitasITemporal = DB::SELECT("CALL pr_cantidadVisitasInstitucionTemporal('$request->fromDate', '$request->toDate', '$request->asesor_id')");
        return [
            "visitasInstitucion" => $visitas,
            "visitasInstitucionTemporal" => $visitasITemporal
        ];
    }
    public function ReporteVisitaAsesores(Request $request){
        $asesores = DB::SELECT("SELECT  CONCAT(u.apellidos, ' ', u.nombres) AS asesor ,u.idusuario 
        FROM usuario u
        WHERE u.id_group = '11'
        AND u.estado_idEstado = '1'
        ORDER BY u.apellidos ASC
        ");
        $data = [];
        foreach($asesores as $key => $item){
            $conteoAgenda = DB::SELECT("SELECT  COUNT(c.id) AS conteo
            FROM agenda_usuario c 
            WHERE c.id_usuario = '$item->idusuario'
            AND c.estado <> '2'
            AND c.startDate BETWEEN '$request->fromDate' AND '$request->toDate'
            ");
            $data[$key] = [
                "asesor" => $item->asesor,
                "idusuario" => $item->idusuario,
                "contador" => $conteoAgenda[0]->conteo
            ];
        }
       return $data;
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
