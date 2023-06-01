<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;
use App\Models\CodigosLibros;
use App\Models\HistoricoCodigos;

class GestionCodigosController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $consulta = DB::SELECT("SELECT 
        (SELECT CONCAT(' Cliente: ', d.cliente  , ' - ',d.fecha_devolucion) AS devolucion 
            FROM codigos_devolucion d
            WHERE d.codigo = c.codigo
            AND d.estado = '1'
            ORDER BY d.id DESC
            LIMIT 1) as devolucionInstitucion,
        (SELECT COUNT(d.id) FROM codigos_devolucion d
        WHERE d.codigo = c.codigo AND d.estado = '1') as devolucion,c.venta_lista_institucion,
        c.codigos_barras,c.anio,c.serie, c.codigo,c.bc_estado,c.estado,c.estado_liquidacion,contador,c.serie,
        c.venta_estado,c.bc_periodo,c.bc_institucion,c.idusuario,c.id_periodo,c.contrato,c.libro as book,c.libro_idlibro,
        CONCAT(u.nombres, ' ', u.apellidos) as estudiante, CONCAT(ucr.nombres, ' ', ucr.apellidos) as creador,
         u.email,u.cedula, ib.nombreInstitucion as institucion_barras,c.created_at,
        i.nombreInstitucion, p.periodoescolar as periodo,pb.periodoescolar as periodo_barras,c.bc_fecha_ingreso,
        c.verif1,c.verif2,c.verif3,c.verif4,c.verif5,c.verif6,c.verif7,c.verif8,c.verif9,c.verif10,
        IF(c.estado ='2', 'bloqueado','activo') as codigoEstado,
        (case when (c.estado_liquidacion = '0') then 'liquidado'
            when (c.estado_liquidacion = '1') then 'sin liquidar'
            when (c.estado_liquidacion = '2') then 'codigo regalado'
            when (c.estado_liquidacion = '3') then 'codigo devuelto'
        end) as liquidacion,
        (case when (c.bc_estado = '2') then 'codigo leido'
        when (c.bc_estado = '1') then 'codigo sin leer'
        end) as barrasEstado,
        (case when (c.codigos_barras = '1') then 'con código de barras'
            when (c.codigos_barras = '0')  then 'sin código de barras'
        end) as status,
        (case when (c.venta_estado = '0') then ''
            when (c.venta_estado = '1') then 'Venta directa'
            when (c.venta_estado = '2') then 'Venta por lista'
        end) as ventaEstado,ib.nombreInstitucion as institucionBarra,
        p.periodoescolar as periodo, pb.periodoescolar as periodo_barras,ivl.nombreInstitucion as InstitucionLista
        FROM codigoslibros c
        LEFT JOIN usuario u ON c.idusuario = u.idusuario
        LEFT JOIN usuario ucr ON c.idusuario_creador_codigo = ucr.idusuario
        LEFT JOIN institucion ib ON c.bc_institucion = ib.idInstitucion
        LEFT JOIN institucion i ON u.institucion_idInstitucion = i.idInstitucion
        LEFT JOIN institucion ivl ON c.venta_lista_institucion = ivl.idInstitucion
        LEFT JOIN periodoescolar p ON c.id_periodo = p.idperiodoescolar
        LEFT JOIN periodoescolar pb ON c.bc_periodo = pb.idperiodoescolar
        WHERE c.codigo LIKE '%$request->codigo%'");
        if(empty($consulta)){
            return ["status" => "0","message" => "No se encontro codigos"];
        }else{
            return $consulta;
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
        //editar
        if($request->editar == "1"){
            $codigo     = CodigosLibros::findOrFail($request->codigo);
            $old_values = CodigosLibros::findOrFail($request->codigo);
            $comentario = "Se edito el codigo desde administracion";
        }
        //guardar
        else{
            $comentario = "Se creo el codigo desde administracion";
            $old_values = "";
            //validar si existe codigo
            $validate = DB::SELECT("SELECT * FROM codigoslibros WHERE codigo = '$request->codigo'");
            if(empty($validate)){
            }else{
                return  ["status" => "3", "message" => "El codigo ya existe"];
            }
            $codigo                             = new CodigosLibros();
            $codigo->codigo                     = $request->codigo;
        }
            $codigo->serie                      = $request->serie;
            $codigo->libro                      = $request->libro;
            $codigo->anio                       = $request->anio;
            $codigo->idusuario                  = $request->idusuario;
            $codigo->idusuario_creador_codigo   = $request->user_created;
            $codigo->libro_idlibro              = $request->libro_idlibro;
            $codigo->estado                     = $request->estado;
            $codigo->id_periodo                 = $request->id_periodo;
            $codigo->contrato                   = $request->contrato;
            $codigo->venta_lista_institucion    = $request->venta_lista_institucion;
            $codigo->verif1                     = $request->verif1;
            $codigo->verif2                     = $request->verif2;
            $codigo->verif3                     = $request->verif3;
            $codigo->verif4                     = $request->verif4;
            $codigo->verif5                     = $request->verif5;
            $codigo->verif6                     = $request->verif6;
            $codigo->verif7                     = $request->verif7;
            $codigo->verif8                     = $request->verif8;
            $codigo->verif9                     = $request->verif9;
            $codigo->verif10                    = $request->verif10;
            $codigo->estado_liquidacion         = $request->estado_liquidacion;
            $codigo->bc_estado                  = $request->bc_estado;
            $codigo->codigos_barras             = $request->codigos_barras;
            $codigo->bc_institucion             = $request->bc_institucion;
            $codigo->bc_periodo                 = $request->bc_periodo;
            $codigo->bc_fecha_ingreso           = $request->bc_fecha_ingreso;
            $codigo->venta_estado               = $request->venta_estado;
            $codigo->contador                   = $request->contador;
            $codigo->save();
            if($codigo){
             //Guardar en el historico
            $this->GuardarEnHistorico($request->user_created,$request->institucion_id,$request->periodo_id,$request->codigo,$request->user_created,$comentario,$old_values,$codigo);
                return ["status" => "1", "message" => "Se guardo correctamente"];
            }else{
                return ["status" => "0", "message" => "No se pudo guardar"];
            }
    }
    public function guardarCodigoParametros(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $codigos    = json_decode($request->data_codigos); 
        $todate     = date('Y-m-d H:i:s');  
        $contador   = 0;
        //editar
        foreach($codigos as $key => $item){
            $codigo     = CodigosLibros::findOrFail($item->codigo);
            $old_values = CodigosLibros::findOrFail($item->codigo);
            $comentario = "Se edito el codigo desde administracion";
            //update
            if($request->chkIdusuario                   == '1') $codigo->idusuario                  = $request->idusuario;
            if($request->chkBc_Estado                   == '1') $codigo->estado                     = $request->estado;
            if($request->chkId_periodo                  == '1') $codigo->id_periodo                 = $request->id_periodo;
            if($request->chkContrato                    == '1') $codigo->contrato                   = $request->contrato;
            if($request->chkBc_Venta_lista_institucion  == '1') $codigo->venta_lista_institucion    = $request->venta_lista_institucion;
            if($request->chkBc_Verif1                   == '1') $codigo->verif1                     = $request->verif1;
            if($request->chkBc_Verif2                   == '1') $codigo->verif2                     = $request->verif2;
            if($request->chkBc_Verif3                   == '1') $codigo->verif3                     = $request->verif3;
            if($request->chkBc_Verif4                   == '1') $codigo->verif4                     = $request->verif4;
            if($request->chkBc_Verif5                   == '1') $codigo->verif5                     = $request->verif5;
            if($request->chkBc_Verif6                   == '1') $codigo->verif6                     = $request->verif6;
            if($request->chkBc_Verif7                   == '1') $codigo->verif7                     = $request->verif7;
            if($request->chkBc_Verif8                   == '1') $codigo->verif8                     = $request->verif8;
            if($request->chkBc_Verif9                   == '1') $codigo->verif9                     = $request->verif9;
            if($request->chkBc_Verif10                  == '1') $codigo->verif10                    = $request->verif10;
            if($request->chkEstado_liquidacion          == '1') $codigo->estado_liquidacion         = $request->estado_liquidacion;
            if($request->chkBc_estado                   == '1') $codigo->bc_estado                  = $request->bc_estado;
            if($request->chkBc_Codigos_barras           == '1') $codigo->codigos_barras             = $request->codigos_barras;
            if($request->chkBc_Bc_institucion           == '1') $codigo->bc_institucion             = $request->bc_institucion;
            if($request->chkBc_Bc_periodo               == '1') $codigo->bc_periodo                 = $request->bc_periodo;
            //if($request->chkIdusuario                 == '1') $codigo->bc_fecha_ingreso           = $request->bc_fecha_ingreso;
            if($request->chkBc_Venta_estado             == '1') $codigo->venta_estado               = $request->venta_estado;
            $codigo->save();
            if($codigo){
                $contador++;
                //Guardar en el historico
                $this->GuardarEnHistorico($request->user_created,$request->institucion_id,$request->periodo_id,$item->codigo,$request->user_created,$comentario,$old_values,$codigo);
            }    
        }
        return [
            "contador" => $contador
        ];
    }
    //api:get>/traerCodigosParametros
    public function traerCodigosParametros(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        //CODIGOS REGALADOS
        if($request->tipo == "regalado"){
           $codigos = DB::SELECT("SELECT c.contrato,
           c.codigo,c.bc_estado,c.estado,c.estado_liquidacion,contador,
           c.venta_estado,c.bc_periodo,c.bc_institucion,c.idusuario,c.id_periodo,c.contrato,c.libro,
           ib.nombreInstitucion as institucion_barras,
           pb.periodoescolar as periodo_barras,
           IF(c.estado ='2', 'bloqueado','activo') as codigoEstado,
           (case when (c.estado_liquidacion = '0') then 'liquidado'
               when (c.estado_liquidacion = '1') then 'sin liquidar'
               when (c.estado_liquidacion = '2') then 'codigo regalado'
               when (c.estado_liquidacion = '3') then 'codigo devuelto'
           end) as liquidacion,
           (case when (c.bc_estado = '2') then 'codigo leido'
           when (c.bc_estado = '1') then 'codigo sin leer'
           end) as barrasEstado,
           (case when (c.codigos_barras = '1') then 'con código de barras'
               when (c.codigos_barras = '0')  then 'sin código de barras'
           end) as status,
           (case when (c.venta_estado = '0') then ''
               when (c.venta_estado = '1') then 'Venta directa'
               when (c.venta_estado = '2') then 'Venta por lista'
           end) as ventaEstado,
           ib.nombreInstitucion as institucionBarra,
           pb.periodoescolar as periodo_barras,ivl.nombreInstitucion as InstitucionLista
           FROM codigoslibros c
           LEFT JOIN institucion ib ON c.bc_institucion = ib.idInstitucion
           LEFT JOIN institucion ivl ON c.venta_lista_institucion = ivl.idInstitucion
           LEFT JOIN periodoescolar pb ON c.bc_periodo = pb.idperiodoescolar
           WHERE  
                (
                c.bc_institucion = '$request->institucion_id' 
                OR venta_lista_institucion = '$request->institucion_id'
                ) 
            AND c.bc_periodo = '$request->periodo_id'
            AND 
                (
                c.estado = '1' OR c.estado = '0' OR c.estado = '' OR c.estado IS NULL
                )
            AND c.estado_liquidacion = '2'
            LIMIT 2000
            ");
            return $codigos;
        }
        //CODIGOS LIQUIDADOS
        if($request->tipo == "liquidados"){
            $codigos = DB::SELECT("SELECT c.contrato,
            c.codigo,c.bc_estado,c.estado,c.estado_liquidacion,contador,
            c.venta_estado,c.bc_periodo,c.bc_institucion,c.idusuario,c.id_periodo,c.contrato,c.libro,
            ib.nombreInstitucion as institucion_barras,
            pb.periodoescolar as periodo_barras,
            IF(c.estado ='2', 'bloqueado','activo') as codigoEstado,
            (case when (c.estado_liquidacion = '0') then 'liquidado'
                when (c.estado_liquidacion = '1') then 'sin liquidar'
                when (c.estado_liquidacion = '2') then 'codigo regalado'
                when (c.estado_liquidacion = '3') then 'codigo devuelto'
            end) as liquidacion,
            (case when (c.bc_estado = '2') then 'codigo leido'
            when (c.bc_estado = '1') then 'codigo sin leer'
            end) as barrasEstado,
            (case when (c.codigos_barras = '1') then 'con código de barras'
                when (c.codigos_barras = '0')  then 'sin código de barras'
            end) as status,
            (case when (c.venta_estado = '0') then ''
                when (c.venta_estado = '1') then 'Venta directa'
                when (c.venta_estado = '2') then 'Venta por lista'
            end) as ventaEstado,
            ib.nombreInstitucion as institucionBarra,
            pb.periodoescolar as periodo_barras,ivl.nombreInstitucion as InstitucionLista
            FROM codigoslibros c
            LEFT JOIN institucion ib ON c.bc_institucion = ib.idInstitucion
            LEFT JOIN institucion ivl ON c.venta_lista_institucion = ivl.idInstitucion
            LEFT JOIN periodoescolar pb ON c.bc_periodo = pb.idperiodoescolar
            WHERE  
                (
                c.bc_institucion = '$request->institucion_id' 
                OR venta_lista_institucion = '$request->institucion_id'
                ) 
             AND c.bc_periodo = '$request->periodo_id'
             AND 
                (
                c.estado = '1' OR c.estado = '0' OR c.estado = '' OR c.estado IS NULL
                )
            AND c.estado_liquidacion = '0'
            LIMIT 2000
            ");
            return $codigos;
        }
        //CODIGOS DEVUELTOS
        if($request->tipo == "devueltos"){
            $codigos = DB::SELECT("SELECT c.contrato,
            c.codigo,c.bc_estado,c.estado,c.estado_liquidacion,contador,
            c.venta_estado,c.bc_periodo,c.bc_institucion,c.idusuario,c.id_periodo,c.contrato,c.libro,
            ib.nombreInstitucion as institucion_barras,
            pb.periodoescolar as periodo_barras,
            IF(c.estado ='2', 'bloqueado','activo') as codigoEstado,
            (case when (c.estado_liquidacion = '0') then 'liquidado'
                when (c.estado_liquidacion = '1') then 'sin liquidar'
                when (c.estado_liquidacion = '2') then 'codigo regalado'
                when (c.estado_liquidacion = '3') then 'codigo devuelto'
            end) as liquidacion,
            (case when (c.bc_estado = '2') then 'codigo leido'
            when (c.bc_estado = '1') then 'codigo sin leer'
            end) as barrasEstado,
            (case when (c.codigos_barras = '1') then 'con código de barras'
                when (c.codigos_barras = '0')  then 'sin código de barras'
            end) as status,
            (case when (c.venta_estado = '0') then ''
                when (c.venta_estado = '1') then 'Venta directa'
                when (c.venta_estado = '2') then 'Venta por lista'
            end) as ventaEstado,
            ib.nombreInstitucion as institucionBarra,
            pb.periodoescolar as periodo_barras,ivl.nombreInstitucion as InstitucionLista
            FROM codigoslibros c
            LEFT JOIN institucion ib ON c.bc_institucion = ib.idInstitucion
            LEFT JOIN institucion ivl ON c.venta_lista_institucion = ivl.idInstitucion
            LEFT JOIN periodoescolar pb ON c.bc_periodo = pb.idperiodoescolar
            WHERE c.estado_liquidacion = '3'
            LIMIT 2000
            ");
            return $codigos;
        }
        //CODIGOS LEIDOS
        if($request->tipo == "leidos"){
        $codigos = DB::SELECT("SELECT c.contrato,
            c.codigo,c.bc_estado,c.estado,c.estado_liquidacion,contador,
            c.venta_estado,c.bc_periodo,c.bc_institucion,c.idusuario,c.id_periodo,c.contrato,c.libro,
            ib.nombreInstitucion as institucion_barras,
            pb.periodoescolar as periodo_barras,
            IF(c.estado ='2', 'bloqueado','activo') as codigoEstado,
            (case when (c.estado_liquidacion = '0') then 'liquidado'
                when (c.estado_liquidacion = '1') then 'sin liquidar'
                when (c.estado_liquidacion = '2') then 'codigo regalado'
                when (c.estado_liquidacion = '3') then 'codigo devuelto'
            end) as liquidacion,
            (case when (c.bc_estado = '2') then 'codigo leido'
            when (c.bc_estado = '1') then 'codigo sin leer'
            end) as barrasEstado,
            (case when (c.codigos_barras = '1') then 'con código de barras'
                when (c.codigos_barras = '0')  then 'sin código de barras'
            end) as status,
            (case when (c.venta_estado = '0') then ''
                when (c.venta_estado = '1') then 'Venta directa'
                when (c.venta_estado = '2') then 'Venta por lista'
            end) as ventaEstado,
            ib.nombreInstitucion as institucionBarra,
            pb.periodoescolar as periodo_barras,ivl.nombreInstitucion as InstitucionLista
            FROM codigoslibros c
            LEFT JOIN institucion ib ON c.bc_institucion = ib.idInstitucion
            LEFT JOIN institucion ivl ON c.venta_lista_institucion = ivl.idInstitucion
            LEFT JOIN periodoescolar pb ON c.bc_periodo = pb.idperiodoescolar
            WHERE  
                (
                c.bc_institucion = '$request->institucion_id' 
                OR venta_lista_institucion = '$request->institucion_id'
                ) 
                AND c.bc_periodo = '$request->periodo_id'
                AND 
                (
                c.estado = '1' OR c.estado = '0' OR c.estado = '' OR c.estado IS NULL
                )
            AND c.estado_liquidacion = '1'
            AND c.bc_estado          = '2'
            LIMIT 2000
            ");
            return $codigos;
        }
        //CODIGOS NO LEIDOS
        if($request->tipo == "no_leidos"){
        $codigos = DB::SELECT("SELECT c.contrato,
            c.codigo,c.bc_estado,c.estado,c.estado_liquidacion,contador,
            c.venta_estado,c.bc_periodo,c.bc_institucion,c.idusuario,c.id_periodo,c.contrato,c.libro,
            ib.nombreInstitucion as institucion_barras,
            pb.periodoescolar as periodo_barras,
            IF(c.estado ='2', 'bloqueado','activo') as codigoEstado,
            (case when (c.estado_liquidacion = '0') then 'liquidado'
                when (c.estado_liquidacion = '1') then 'sin liquidar'
                when (c.estado_liquidacion = '2') then 'codigo regalado'
                when (c.estado_liquidacion = '3') then 'codigo devuelto'
            end) as liquidacion,
            (case when (c.bc_estado = '2') then 'codigo leido'
            when (c.bc_estado = '1') then 'codigo sin leer'
            end) as barrasEstado,
            (case when (c.codigos_barras = '1') then 'con código de barras'
                when (c.codigos_barras = '0')  then 'sin código de barras'
            end) as status,
            (case when (c.venta_estado = '0') then ''
                when (c.venta_estado = '1') then 'Venta directa'
                when (c.venta_estado = '2') then 'Venta por lista'
            end) as ventaEstado,
            ib.nombreInstitucion as institucionBarra,
            pb.periodoescolar as periodo_barras,ivl.nombreInstitucion as InstitucionLista
            FROM codigoslibros c
            LEFT JOIN institucion ib ON c.bc_institucion = ib.idInstitucion
            LEFT JOIN institucion ivl ON c.venta_lista_institucion = ivl.idInstitucion
            LEFT JOIN periodoescolar pb ON c.bc_periodo = pb.idperiodoescolar
            WHERE  
                (
                c.bc_institucion = '$request->institucion_id' 
                OR venta_lista_institucion = '$request->institucion_id'
                ) 
                AND c.bc_periodo = '$request->periodo_id'
                AND 
                (
                c.estado = '1' OR c.estado = '0' OR c.estado = '' OR c.estado IS NULL
                )
            AND c.estado_liquidacion = '1'
            AND c.bc_estado          = '1'
            LIMIT 2000
            ");
            return $codigos;
        }
        //TODOS
        if($request->tipo == "todos"){
        $codigos = DB::SELECT("SELECT c.contrato,
            c.codigo,c.bc_estado,c.estado,c.estado_liquidacion,contador,
            c.venta_estado,c.bc_periodo,c.bc_institucion,c.idusuario,c.id_periodo,c.contrato,c.libro,
            ib.nombreInstitucion as institucion_barras,
            pb.periodoescolar as periodo_barras,
            IF(c.estado ='2', 'bloqueado','activo') as codigoEstado,
            (case when (c.estado_liquidacion = '0') then 'liquidado'
                when (c.estado_liquidacion = '1') then 'sin liquidar'
                when (c.estado_liquidacion = '2') then 'codigo regalado'
                when (c.estado_liquidacion = '3') then 'codigo devuelto'
            end) as liquidacion,
            (case when (c.bc_estado = '2') then 'codigo leido'
            when (c.bc_estado = '1') then 'codigo sin leer'
            end) as barrasEstado,
            (case when (c.codigos_barras = '1') then 'con código de barras'
                when (c.codigos_barras = '0')  then 'sin código de barras'
            end) as status,
            (case when (c.venta_estado = '0') then ''
                when (c.venta_estado = '1') then 'Venta directa'
                when (c.venta_estado = '2') then 'Venta por lista'
            end) as ventaEstado,
            ib.nombreInstitucion as institucionBarra,
            pb.periodoescolar as periodo_barras,ivl.nombreInstitucion as InstitucionLista
            FROM codigoslibros c
            LEFT JOIN institucion ib ON c.bc_institucion = ib.idInstitucion
            LEFT JOIN institucion ivl ON c.venta_lista_institucion = ivl.idInstitucion
            LEFT JOIN periodoescolar pb ON c.bc_periodo = pb.idperiodoescolar
            WHERE  
                (
                c.bc_institucion = '$request->institucion_id' 
                OR venta_lista_institucion = '$request->institucion_id'
                ) 
                AND c.bc_periodo = '$request->periodo_id'
                LIMIT 2000
            ");
            return $codigos;
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

    public function eliminarCode(Request $request){
        $old_values = CodigosLibros::findOrFail($request->codigo);
        $eliminar = DB::DELETE("DELETE FROM codigoslibros WHERE codigo = '$request->codigo'
        AND estado_liquidacion <> '0'
        ");
        if($eliminar){
            $usuario_editor = $request->idusuario;
            $comentario     = "Se elimino el codigo de la base de datos";
            //Guardar en el historico
            $this->GuardarEnHistorico($usuario_editor,$request->institucion_id,$request->periodo_id,$request->codigo,$usuario_editor,$comentario,$old_values,"");
            return ["status" => "1" ,"message" => "Se elimino correctamente"];
        }else{
            return ["status" => "0" ,"message" => "No se pudo eliminar el codigo puede que este liquidado"];
        }
      
    }
    public function GuardarEnHistorico ($id_usuario,$institucion_id,$periodo_id,$codigo,$usuario_editor,$comentario,$old_values,$new_values){
        $historico = new HistoricoCodigos();
        $historico->id_usuario     =  $id_usuario;
        $historico->usuario_editor =  $institucion_id;
        $historico->id_periodo     =  $periodo_id;
        $historico->codigo_libro   =  $codigo;
        $historico->idInstitucion  =  $usuario_editor;
        $historico->observacion    =  $comentario;
        $historico->old_values     =  $old_values;
        $historico->new_values     =  $new_values;
        $historico->save();
     }
    public function destroy($id)
    {
        
    }
}
