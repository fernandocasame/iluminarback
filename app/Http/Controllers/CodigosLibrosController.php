<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\CodigosLibros;
use DataTables;
Use Exception;
use Carbon\Carbon;

class CodigosLibrosController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $auxlibros = [];
        $auxlibrosf = [];
        $auxlibros = $codigos_libros = DB::SELECT("SELECT libro.*,asignatura.* from codigoslibros join libro on libro.idlibro = codigoslibros.libro_idlibro join asignatura on asignatura.idasignatura = libro.asignatura_idasignatura  WHERE idusuario = ?",[$request->idusuario]);
        $usuario = DB::SELECT("SELECT * FROM usuario WHERE idusuario = ?",[$request->idusuario]);
        $idinstitucion = '';
        foreach ($usuario as $key => $value) {
            $idinstitucion = $value->institucion_idInstitucion;
        }
        
        if(!empty($codigos_libros)){
            foreach ($codigos_libros as $key => $value) {
                $free = DB::SELECT("SELECT libro.*,asignatura.* FROM institucion_libro join libro on libro.idlibro = institucion_libro.idlibro join asignatura on asignatura.idasignatura = libro.asignatura_idasignatura  WHERE institucion_libro.idinstitucion = ? AND asignatura.nivel_idnivel = ? AND institucion_libro.estado = '1'",[$idinstitucion,$value->nivel_idnivel]);
                foreach ($free as $keyl => $valuel) {
                    array_push($auxlibros, $valuel);
                }
            }
        }
        $auxlibrosf = array_unique($auxlibros, SORT_REGULAR);

     
        return $auxlibrosf;
    }

    //api get>>/codigoslibrosEstudiante
    public function codigoslibrosEstudiante(Request $request){
        $auxlibros = [];
        $auxlibrosf = [];
        $auxlibros = $codigos_libros = DB::SELECT("SELECT libro.*,asignatura.* from codigoslibros join libro on libro.idlibro = codigoslibros.libro_idlibro join asignatura on asignatura.idasignatura = libro.asignatura_idasignatura  WHERE idusuario = ?",[$request->idusuario]);
        $usuario = DB::SELECT("SELECT * FROM usuario WHERE idusuario = ?",[$request->idusuario]);
        $idinstitucion = '';
        foreach ($usuario as $key => $value) {
            $idinstitucion = $value->institucion_idInstitucion;
        }
        
        if(!empty($codigos_libros)){
            foreach ($codigos_libros as $key => $value) {
                $free = DB::SELECT("SELECT libro.*,asignatura.* FROM institucion_libro join libro on libro.idlibro = institucion_libro.idlibro join asignatura on asignatura.idasignatura = libro.asignatura_idasignatura  WHERE institucion_libro.idinstitucion = ? AND asignatura.nivel_idnivel = ? AND institucion_libro.estado = '1'",[$idinstitucion,$value->nivel_idnivel]);
                foreach ($free as $keyl => $valuel) {
                    array_push($auxlibros, $valuel);
                }
            }
        }
        $auxlibrosf = array_unique($auxlibros, SORT_REGULAR);
        foreach($auxlibrosf as $k => $item){
            $data[] = DB::SELECT("SELECT j.*, a.nombreasignatura FROM juegos j
             LEFT JOIN asignatura a ON a.idasignatura = j.asignatura_idasignatura
             WHERE j.asignatura_idasignatura = $item->idasignatura
           
            ");
        }

        $contador1 =0;
        while ($contador1 < count($data)) {
         
            if(count($data) == 1){
                $array_resultante= $data[0];
            }

            if(count($data) == 2){
                $array_resultante= array_merge($data[0],$data[1]);
            }
            
            if(count($data) == 3){
                $array_resultante= array_merge($data[0],$data[1],$data[2]);
            }

            if(count($data) == 4){
                $array_resultante= array_merge($data[0],$data[1],$data[2],$data[3]);
            }

            if(count($data) == 5){
                $array_resultante= array_merge($data[0],$data[1],$data[2],$data[3],$data[4]);
            }

            if(count($data) == 6){
                $array_resultante= array_merge($data[0],$data[1],$data[2],$data[3],$data[4],$data[5]);
            }

            if(count($data) == 7){
                $array_resultante= array_merge($data[0],$data[1],$data[2],$data[3],$data[4],$data[5],$data[6]);
            }

            if(count($data) == 8){
                $array_resultante= array_merge($data[0],$data[1],$data[2],$data[3],$data[4],$data[5],$data[6],$data[7]);
            }

            if(count($data) == 9){
                $array_resultante= array_merge($data[0],$data[1],$data[2],$data[3],$data[4],$data[5],$data[6],$data[7],$data[8]);
            }

            if(count($data) == 10){
                $array_resultante= array_merge($data[0],$data[1],$data[2],$data[3],$data[4],$data[5],$data[6],$data[7],$data[8],$data[9]);
            }

            if(count($data) == 11){
                $array_resultante= array_merge($data[0],$data[1],$data[2],$data[3],$data[4],$data[5],$data[6],$data[7],$data[8],$data[9],$data[10]);
            }

            
            if(count($data) == 12){
                $array_resultante= array_merge($data[0],$data[1],$data[2],$data[3],$data[4],$data[5],$data[6],$data[7],$data[8],$data[9],$data[10],$data[11]);
            }

            if(count($data) == 13){
                $array_resultante= array_merge($data[0],$data[1],$data[2],$data[3],$data[4],$data[5],$data[6],$data[7],$data[8],$data[9],$data[10],$data[11],$data[12]);
            }

            if(count($data) == 14){
                $array_resultante= array_merge($data[0],$data[1],$data[2],$data[3],$data[4],$data[5],$data[6],$data[7],$data[8],$data[9],$data[10],$data[11],$data[12],$data[13]);
            }

            if(count($data) == 15){
                $array_resultante= array_merge($data[0],$data[1],$data[2],$data[3],$data[4],$data[5],$data[6],$data[7],$data[8],$data[9],$data[10],$data[11],$data[12],$data[13],$data[14]);
            }

            if(count($data) == 16){
                $array_resultante= array_merge($data[0],$data[1],$data[2],$data[3],$data[4],$data[5],$data[6],$data[7],$data[8],$data[9],$data[10],$data[11],$data[12],$data[13],$data[14],$data[15]);
            }
           
            $contador1=$contador1+1;
        }
        return $array_resultante;
       
    }
    
    public function codigosCuaderno(Request $request){
        $codigos_libros = DB::SELECT("SELECT cuaderno.* from codigoslibros join libro on libro.idlibro = codigoslibros.libro_idlibro join cuaderno on cuaderno.asignatura_idasignatura = libro.asignatura_idasignatura  WHERE idusuario = ? AND codigoslibros.codigo LIKE '%PLUS%'",[$request->idusuario]);
        return $codigos_libros;
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

    //CODIGO COMENTADO, SOLICITADO POR FERNANDO, 
    // public function codigos_libros_estudiante($id){
    //     $libros = DB::SELECT("SELECT l. *, c.codigo FROM libro l, codigoslibros c WHERE l.idlibro = c.libro_idlibro AND c.idusuario = $id");
    //     return $libros;
    // }
    
    public function codigos_libros_docente($id,$docente){
        $libros = DB::SELECT("SELECT l.*
        FROM libros_series ls
        LEFT JOIN libro l ON ls.idLibro = l.idlibro
        JOIN asignaturausuario a ON l.idlibro = a.asignatura_idasignatura
        WHERE ls.year = '$id'
        AND a.docente_id = '$docente'
        ");
        return $libros;
    }
    public function codigos_libros_estudiante($curso){
        $libros = DB::SELECT("SELECT l.* FROM asignaturausuario au
        LEFT JOIN libro l ON au.asignatura_idasignatura = l.idlibro
        WHERE au.idcurso = '$curso'
        ");
        return $libros;
    }
  
  

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validacion = DB::SELECT("SELECT * FROM  codigoslibros WHERE   codigo = ?",["$request->codigo"]);
        $iduser = '';
        foreach ($validacion as $key => $value) {
            $iduser = $value->idusuario;
            $estadoCodigo = $value->estado;
            $estado_liquidacion = $value->estado_liquidacion;
        }

        //para obtener los datos del estudiante para abrir el ticket
        $datosEstudiante = DB::SELECT("SELECT CONCAT(e.nombres,' ', e.apellidos) as estudiante ,e.name_usuario,e.cedula,e.idusuario, i.nombreInstitucion
        FROM usuario e, institucion i 
        WHERE e.id_group = '4'
        AND e.idusuario = $request->idusuario
        AND e.institucion_idInstitucion = i.idInstitucion
        
        ");
        //para ver cuantos tickets abiertos tiene el usuario
        $cantidadTicketOpen = DB::SELECT("SELECT t.* FROM tickets t
         WHERE t.usuario_id = $request->idusuario
         AND t.estado = '1'
        ");

        $realizarTicket = "no";
        if(empty($cantidadTicketOpen)){
            $realizarTicket = "ok";
        }
    
        if(empty($validacion)){

            $data = [
                'status' => '2',
                'codigo' => $request->codigo,
                'institucion' => $request->id_institucion,
                'usuario' => $request->idusuario,
                'datosEstudiante' => $datosEstudiante,
                'realizarTicket' => $realizarTicket,
            ];
            return $data;
        }
        //para mandar los codigos que esten bloqueados
        else if($estadoCodigo == '2'){
            $data = [
                'status' => '3',
                'codigo' => $request->codigo,
                'institucion' => $request->id_institucion,
                'usuario' => $request->idusuario,
                'datosEstudiante' => $datosEstudiante,
                'realizarTicket' => $realizarTicket,
            ];
            return $data;    
        }
          //para mandar los codigos que esten devueltos
          else if($estado_liquidacion == '3'){
            $data = [
                'status' => '4',
                'codigo' => $request->codigo,
                'institucion' => $request->id_institucion,
                'usuario' => $request->idusuario,
                'datosEstudiante' => $datosEstudiante,
                'realizarTicket' => $realizarTicket,
            ];
            return $data;    
        }
        else{

            $institucion = $request->id_institucion;
                if(empty($iduser) || $iduser == 0 || $iduser == NULL ){
                    ///Para buscar el periodo

                    $verificarperiodoinstitucion = DB::table('periodoescolar_has_institucion')
                    ->select('periodoescolar_has_institucion.periodoescolar_idperiodoescolar')
        
                    ->where('periodoescolar_has_institucion.institucion_idInstitucion','=',$request->id_institucion)
                    ->get();
                    
                     foreach($verificarperiodoinstitucion  as $clave=>$item){
                        $verificarperiodos =DB::SELECT("SELECT p.idperiodoescolar
                        FROM periodoescolar p
                        WHERE p.estado = '1'
                        and p.idperiodoescolar = $item->periodoescolar_idperiodoescolar
                        ");
                     }
                     
                   if(count($verificarperiodoinstitucion) <=0){
                        return ["status"=>"0", "message" => "No existe el periodo lectivo por favor, asigne un periodo a esta institucion"];
                    }
         
                     //verificar que el periodo exista
                    if(count($verificarperiodos) <= 0){
                                
                        return ["status"=>"0", "message" => "No existe el periodo lectivo por favor, asigne un periodo a esta institucion"];
       
                     }
                   
                  //fin de busqueda del periodo
                     //almancenar el periodo
                    $periodo =  $verificarperiodos[0]->idperiodoescolar;
                    DB::INSERT("INSERT INTO hist_codlibros(id_usuario, codigo_libro, idInstitucion, usuario_editor, observacion,id_periodo) VALUES ($request->idusuario, '$request->codigo', $request->idusuario, $request->id_institucion, 'registrado',$periodo)");
                    $contenido = CodigosLibros::find($request->codigo)->update(
                        [
                            'idusuario' => $request->idusuario,
                            'id_periodo' => $periodo
                          
                        ]
                    );

                    $data = [
                        'status' => '1',
                     
                    ];
                    return $data;
                }else{
                    $data = [
                        'status' => '0',
                        'codigo' => $request->codigo,
                        'institucion' => $request->id_institucion,
                        'usuario' => $request->idusuario,
                        'datosEstudiante' => $datosEstudiante,
                        'realizarTicket' => $realizarTicket,
                    ];
                    return $data;
                }
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
        $codigos_libros = DB::SELECT("SELECT * from codigoslibros WHERE libro = '$id'");
        return $codigos_libros;
    }


    
    public function codigosLibrosFecha($datos)
    {   
        $data = explode("*", $datos);

        if( $data[0] != "" ){
            $libro = $data[0];
            $fecha = $data[1];
                
            $codigos_libros = DB::SELECT("SELECT * from codigoslibros WHERE libro = '$libro' AND created_at like '$fecha%' ORDER BY `codigoslibros`.`fecha_create` ASC");
            
            return $codigos_libros;
            
        }else{
            return 0;
        }
        
    }


    public function librosBuscar(){
        $codigos_libros = DB::SELECT("SELECT id_libro_serie as id, nombre as label from libros_series");
        return $codigos_libros;
    }




    public function codigosLibrosExportados($data){
        $datos = explode("*", $data);
        $usuario = $datos[0];
        $cantidad = $datos[1];

        $codigos_libros = DB::SELECT("SELECT * from codigoslibros WHERE idusuario = '$usuario' ORDER BY fecha_create DESC LIMIT $cantidad");

        return $codigos_libros;
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
