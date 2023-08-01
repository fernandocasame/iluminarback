<?php

namespace App\Http\Controllers;

use App\Models\CodigoLibros;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;
use App\Imports\CodigosImport;
use App\Models\CodigosDevolucion;
use App\Models\CodigosLibros;
use App\Models\HistoricoCodigos;
use Maatwebsite\Excel\Facades\Excel;
use PDO;

class CodigoLibrosController extends Controller
{
     //api:post//codigos/importar
     public function importar(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $codigos = json_decode($request->data_codigos);
        $institucion        =  $request->institucion_id;
        $traerPeriodo       =  $request->periodo_id;
        $nombreInstitucion  =  $request->nombreInstitucion;
        $nombrePeriodo      =  $request->nombrePeriodo;
        $venta_estado       =  $request->venta_estado;
        $comentario         =  "Codigo leido de ".$nombreInstitucion." - ".$nombrePeriodo;
        $codigosNoCambiados=[];
        $codigosLeidos =[];
        $codigoNoExiste = [];
        $porcentaje = 0;
        $contador = 0;
        $todate  = date('Y-m-d H:i:s');
        foreach($codigos as $key => $item){
            //validar si el codigo existe
            $validar = $this->getCodigos($item->codigo,0);
            //valida que el codigo existe
            if(count($validar)>0){
                //validar si el codigo ya haya sido leido
                $ifLeido            = $validar[0]->bc_estado;
                //validar si el codigo ya esta liquidado
                $ifLiquidado        = $validar[0]->estado_liquidacion;
                //validar si el codigo no este liquidado
                $ifBloqueado        = $validar[0]->estado;
                //validar si tiene bc_institucion
                $ifBc_Institucion   = $validar[0]->bc_institucion;
                //validar que el periodo del estudiante sea 0 o sea igual al que se envia
                $ifid_periodo       = $validar[0]->id_periodo;
                //validar que el venta_estado sea cero o igual al enviado desde el front
                $ifventa_estado     = $validar[0]->venta_estado;
                //validar el bc_periodo
                $ifbc_periodo       = $validar[0]->bc_periodo;
                if(($ifid_periodo  == $traerPeriodo || $ifid_periodo == 0 ||  $ifid_periodo == null  ||  $ifid_periodo == "") && ($ifBc_Institucion  == $institucion || $ifBc_Institucion == 0) && ($ifbc_periodo  == $traerPeriodo || $ifbc_periodo == 0) && ($ifventa_estado == 0 || $ifventa_estado == $venta_estado) && $ifLeido == '1' && $ifLiquidado == '1' && $ifBloqueado !=2){
                    $codigo =  DB::table('codigoslibros')
                    ->where('codigo', $item->codigo)
                    ->where('bc_estado', '1')
                    ->where('estado','<>', '2')
                    ->where('estado_liquidacion','=', '1')
                    ->update([
                        'bc_institucion'        => $institucion,
                        'bc_estado'             => 2,
                        'bc_periodo'            => $traerPeriodo,
                        'bc_fecha_ingreso'      => $todate,
                        'venta_estado'          => $venta_estado
                    ]);
                    if($codigo){
                        $porcentaje++;
                        //ingresar en el historico
                        $historico                  = new HistoricoCodigos();
                        $historico->codigo_libro    = $item->codigo;
                        $historico->usuario_editor  = $institucion;
                        $historico->idInstitucion   = $request->id_usuario;
                        $historico->id_periodo      = $traerPeriodo;
                        $historico->observacion     = $comentario;
                        $historico->b_estado        = "1";
                        $historico->save();
                    }else{
                        $codigosNoCambiados[$key] =[
                            "codigo" => $item->codigo
                        ];
                    }
                }else{
                    $codigosLeidos[$contador] = [
                        "codigo" => $item->codigo,
                        "prueba_diagnostica" => $validar[0]->prueba_diagnostica,
                        "tipoCodigo"         => $validar[0]->tipoCodigo,
                        "barrasEstado" => $validar[0]->barrasEstado,
                        "codigoEstado" => $validar[0]->codigoEstado,
                        "liquidacion" => $validar[0]->liquidacion,
                        "ventaEstado" => $validar[0]->ventaEstado,
                        "idusuario" => $validar[0]->idusuario,
                        "estudiante" => $validar[0]->estudiante,
                        "nombreInstitucion" => $validar[0]->nombreInstitucion,
                        "institucionBarra" => $validar[0]->institucionBarra,
                        "periodo" => $validar[0]->periodo,
                        "periodo_barras" => $validar[0]->periodo_barras,
                        "cedula" => $validar[0]->cedula,
                        "email" => $validar[0]->email,
                        "estado_liquidacion" => $validar[0]->estado_liquidacion,
                        "estado" => $validar[0]->estado,
                        "status" => $validar[0]->status,
                        "contador" => $validar[0]->contador,
                        "porcentaje_descuento" => $validar[0]->porcentaje_descuento,
                        "factura"               => $validar[0]->factura
                    ];
                    $contador++;
                }
            }else{
                $codigoNoExiste[$key] =[
                    "codigo" => $item->codigo
                ];
            }
        }
        return [
            "cambiados" => $porcentaje,
            "codigosNoCambiados" => $codigosNoCambiados,
            "codigosLeidos" => $codigosLeidos,
            "codigoNoExiste" => $codigoNoExiste
        ];
     }
     public function GuardarEnHistorico ($id_usuario,$institucion_id,$periodo_id,$codigo,$usuario_editor,$comentario,$old_values){
        $historico = new HistoricoCodigos();
        $historico->id_usuario     =  $id_usuario;
        $historico->usuario_editor =  $institucion_id;
        $historico->id_periodo     =  $periodo_id;
        $historico->codigo_libro   =  $codigo;
        $historico->idInstitucion  =  $usuario_editor;
        $historico->observacion    =  $comentario;
        $historico->old_values     =  $old_values;
        $historico->save();
     }

      //api:post//codigos/import/gestion
    public function importGestion(Request $request)
    {
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        //variables
        $usuario_editor = $request->id_usuario;
        $institucion_id = $request->institucion_id;
        $periodo_id = $request->periodo_id;
        $codigosNoExisten =[];
        $codigos = json_decode($request->data_codigos);
        $codigosNoCambiados = [];
        $codigosDemas = [];
        $porcentaje = 0;
        $contador = 0;
         //traer usuario quemado
        $encontrarUsuarioQuemado = DB::select("SELECT idusuario, institucion_idInstitucion
        FROM usuario
        WHERE email = 'quemarcodigos@prolipa.com'
        AND id_group = '4'
        ");
        //almacenar usuario quemado
        $usuarioQuemado = $encontrarUsuarioQuemado[0]->idusuario;
        //almacenar  institucion del usuario quemado
        $usuarioQuemadoInstitucion = $encontrarUsuarioQuemado[0]->institucion_idInstitucion;
        //periodo del usuario quemado
        $periodo = $this->PeriodoInstitucion($usuarioQuemadoInstitucion);
        //guardar el periodo del usuario quemado
        $traerPeriodo = $periodo[0]->periodo;
        foreach($codigos as $key => $item){
            //hace el update a la tabla codigos libros, donde el estado sera a 2 y el bc_estado el que envie por excel
            //y colocar los del usuario quemado
            //SI ENVIAN POR INSTITUCION
            //USAN Y LIQUIDAN /VENTA DIRECTA
            //validar si el codigo existe
            $validar = $this->getCodigos($item->codigo,0);
            // return $validar;
            //valida que el codigo existe
            if(count($validar)>0){
                //VENTA DIRECTA/VENTA POR LISTA
                if($request->regalado == '0'){
                    //validar si el codigo ya haya sido leido
                    $ifLeido            = $validar[0]->bc_estado;
                    //validar si el codigo ya esta liquidado
                    $ifLiquidado        = $validar[0]->estado_liquidacion;
                    //validar si el codigo no este liquidado
                    $ifBloqueado        = $validar[0]->estado;
                    //validar si tiene bc_institucion
                    $ifBc_Institucion   = $validar[0]->bc_institucion;
                    //validar que el periodo del estudiante sea 0 o sea igual al que se envia
                    $ifid_periodo   = $validar[0]->id_periodo;
                    //validar si el codigo tiene venta_estado
                    $venta_estado = $validar[0]->venta_estado;
                    //venta lista
                    $ifventa_lista_institucion = $validar[0]->venta_lista_institucion;
                    //VENTA DIRECTA
                    if($request->venta_estado == '1'){
                        //(SE QUITARA PARA AHORA EL ESTUDIANTE envia los codigos por formulario)if(($ifid_periodo  == $periodo_id || $ifid_periodo == 0 ||  $ifid_periodo == null  ||  $ifid_periodo == "") && ( $ifBc_Institucion == 0 || $ifBc_Institucion == $institucion_id )  && $ifLeido == '1' && $ifLiquidado == '1' && $ifBloqueado !=2 && ($venta_estado == 0  || $venta_estado == null || $venta_estado == "null")){
                        if(($ifid_periodo  == $periodo_id || $ifid_periodo == 0 ||  $ifid_periodo == null  ||  $ifid_periodo == "") && ( $ifBc_Institucion == 0 || $ifBc_Institucion == $institucion_id )  && ($ifLeido == '1' || $ifLeido == '2') && $ifLiquidado == '1' && $ifBloqueado !=2 && ($venta_estado == 0  || $venta_estado == null || $venta_estado == "null")){
                            $codigo = DB::table('codigoslibros')
                            ->where('codigo', '=', $item->codigo)
                            ->where('estado_liquidacion', '=', '1')
                            //(SE QUITARA PARA AHORA EL ESTUDIANTE YA ENVIA LEIDO) ->where('bc_estado', '=', '1')
                            ->update([
                                'factura'           => $request->factura,
                                'bc_institucion'    => $request->institucion_id,
                                'bc_periodo'        => $request->periodo_id,
                                'venta_estado'      => $request->venta_estado,
                        ]);
                            if($codigo){
                                $porcentaje++;
                                //Guardar en el historico
                                $this->GuardarEnHistorico($usuario_editor,$institucion_id,$periodo_id,$item->codigo,$usuario_editor,$request->comentario,null);
                            }else{
                                $codigosNoCambiados[$key] =[
                                    "codigo" => $item->codigo
                                ];
                            }
                        }else{
                            $codigosDemas[$contador] = [
                                "codigo" => $item->codigo,
                                "prueba_diagnostica" => $validar[0]->prueba_diagnostica,
                                "tipoCodigo"         => $validar[0]->tipoCodigo,
                                "barrasEstado" => $validar[0]->barrasEstado,
                                "codigoEstado" => $validar[0]->codigoEstado,
                                "liquidacion" => $validar[0]->liquidacion,
                                "ventaEstado" => $validar[0]->ventaEstado,
                                "idusuario" => $validar[0]->idusuario,
                                "estudiante" => $validar[0]->estudiante,
                                "nombreInstitucion" => $validar[0]->nombreInstitucion,
                                "institucionBarra" => $validar[0]->institucionBarra,
                                "periodo" => $validar[0]->periodo,
                                "periodo_barras" => $validar[0]->periodo_barras,
                                "cedula" => $validar[0]->cedula,
                                "email" => $validar[0]->email,
                                "estado_liquidacion" => $validar[0]->estado_liquidacion,
                                "estado" => $validar[0]->estado,
                                "status" => $validar[0]->status,
                                "contador" => $validar[0]->contador,
                                "InstitucionLista" => $validar[0]->InstitucionLista,
                                "porcentaje_descuento" => $validar[0]->porcentaje_descuento,
                                "factura"               => $validar[0]->factura
                            ];
                            $contador++;
                        }
                    }
                    //VENTA LIBRE/LISTA
                    else{
                        //validacion que sea el periodo en cero o nulo o sea similar al periodo enviado
                        //validacion que el bc_estado sea 1 o 2  leido o no leido
                        //el venta estado puede ser 0 o nulo
                        //estado liquidacion = 1
                        //que no este bloqueado
                        if(($ifid_periodo  == $periodo_id || $ifid_periodo == 0 ||  $ifid_periodo == null  ||  $ifid_periodo == "")  && ($ifLeido == '1' || $ifLeido == '2') && ($venta_estado == 0  || $venta_estado == null || $venta_estado == "null") && $ifLiquidado == '1' && $ifBloqueado !=2 && $ifventa_lista_institucion == '0'){
                        //(PARA EL VERIFICACION POR CODIGOS QUE EL ESTUDIANTE INGRESE)if(($ifid_periodo  == $periodo_id || $ifid_periodo == 0 ||  $ifid_periodo == null  ||  $ifid_periodo == "")  && $ifLeido == '1' && $ifLiquidado == '1' && $ifBloqueado !=2 && $ifventa_lista_institucion == '0'){
                            $codigo = DB::table('codigoslibros')
                            ->where('codigo', '=', $item->codigo)
                            ->where('estado_liquidacion', '=', '1')
                            //(SE QUITARA PARA AHORA EL ESTUDIANTE YA ENVIA LEIDO) ->where('bc_estado', '=', '1')
                            ->update([
                                'factura'                   => $request->factura,
                                'venta_lista_institucion'   => $request->institucion_id,
                                'bc_periodo'                => $request->periodo_id,
                                'venta_estado'              => $request->venta_estado,
                            ]);
                            if($codigo){
                                $porcentaje++;
                                //Guardar en el historico
                                $this->GuardarEnHistorico($usuario_editor,$institucion_id,$periodo_id,$item->codigo,$usuario_editor,$request->comentario,null);
                            }else{
                                $codigosNoCambiados[$key] =[
                                    "codigo" => $item->codigo
                                ];
                            }
                        }else{
                            $codigosDemas[$contador] = [
                                "codigo" => $item->codigo,
                                "prueba_diagnostica" => $validar[0]->prueba_diagnostica,
                                "tipoCodigo"         => $validar[0]->tipoCodigo,
                                "barrasEstado" => $validar[0]->barrasEstado,
                                "codigoEstado" => $validar[0]->codigoEstado,
                                "liquidacion" => $validar[0]->liquidacion,
                                "ventaEstado" => $validar[0]->ventaEstado,
                                "idusuario" => $validar[0]->idusuario,
                                "estudiante" => $validar[0]->estudiante,
                                "nombreInstitucion" => $validar[0]->nombreInstitucion,
                                "institucionBarra" => $validar[0]->institucionBarra,
                                "periodo" => $validar[0]->periodo,
                                "periodo_barras" => $validar[0]->periodo_barras,
                                "cedula" => $validar[0]->cedula,
                                "email" => $validar[0]->email,
                                "estado_liquidacion" => $validar[0]->estado_liquidacion,
                                "estado" => $validar[0]->estado,
                                "status" => $validar[0]->status,
                                "contador" => $validar[0]->contador,
                                "InstitucionLista" => $validar[0]->InstitucionLista,
                                "porcentaje_descuento" => $validar[0]->porcentaje_descuento,
                                "factura"               => $validar[0]->factura
                            ];
                            $contador++;
                        }
                    }

                }
                //REGALADO NO ENTRA A LA LIQUIDACION
                if($request->regalado == '1'){
                    //validar si el codigo tiene estado liquidacion = 2 Y que no este liquidado
                    $estado_liquidacion = $validar[0]->estado_liquidacion;
                    if($estado_liquidacion!=2 && $estado_liquidacion!= 0){
                        $codigo = DB::table('codigoslibros')
                        ->where('codigo', '=', $item->codigo)
                        ->update([
                            'bc_estado' => '1',
                            'estado_liquidacion' => '2',
                            'bc_institucion' => $request->institucion_id,
                            'bc_periodo' => $request->periodo_id
                        ]);
                        if($codigo){
                            $porcentaje++;
                            //Guardar en el historico
                            $this->GuardarEnHistorico($usuario_editor,$institucion_id,$periodo_id,$item->codigo,$usuario_editor,$request->comentario,null);
                        }else{
                            $codigosNoCambiados[$key] =[
                                "codigo" => $item->codigo
                            ];
                        }
                    }else{
                        $codigosDemas[$contador] = [
                            "codigo" => $item->codigo,
                            "prueba_diagnostica" => $validar[0]->prueba_diagnostica,
                            "tipoCodigo"         => $validar[0]->tipoCodigo,
                            "barrasEstado" => $validar[0]->barrasEstado,
                            "codigoEstado" => $validar[0]->codigoEstado,
                            "liquidacion" => $validar[0]->liquidacion,
                            "ventaEstado" => $validar[0]->ventaEstado,
                            "idusuario" => $validar[0]->idusuario,
                            "estudiante" => $validar[0]->estudiante,
                            "nombreInstitucion" => $validar[0]->nombreInstitucion,
                            "institucionBarra" => $validar[0]->institucionBarra,
                            "periodo" => $validar[0]->periodo,
                            "periodo_barras" => $validar[0]->periodo_barras,
                            "cedula" => $validar[0]->cedula,
                            "email" => $validar[0]->email,
                            "estado_liquidacion" => $validar[0]->estado_liquidacion,
                            "estado" => $validar[0]->estado,
                            "status" => $validar[0]->status,
                            "contador" => $validar[0]->contador,
                            "InstitucionLista" => $validar[0]->InstitucionLista,
                            "porcentaje_descuento" => $validar[0]->porcentaje_descuento,
                            "factura"               => $validar[0]->factura
                        ];
                        $contador++;
                    }
                }
                //REGALADO Y BLOQUEADO(No usan y no liquidan)
                if($request->regalado == '2'){
                    //validar si el codigo tiene estado bloqueado y liquidado
                    $estado_liquidacion = $validar[0]->estado_liquidacion;
                    $validarEstado = $validar[0]->estado;
                    if($estado_liquidacion !='0' && $validarEstado !=2){
                        $codigo = DB::table('codigoslibros')
                        ->where('codigo', '=', $item->codigo)
                        ->update([
                            'bc_estado' => '1',
                            'estado'    => '2',
                            'estado_liquidacion' => '2',
                            'bc_institucion' => $request->institucion_id,
                            'bc_periodo' => $request->periodo_id
                        ]);
                        if($codigo){
                            $porcentaje++;
                            //Guardar en el historico
                            $this->GuardarEnHistorico($usuario_editor,$institucion_id,$periodo_id,$item->codigo,$usuario_editor,$request->comentario,null);
                        }else{
                            $codigosNoCambiados[$key] =[
                                "codigo" => $item->codigo
                            ];
                        }
                    }else{
                        $codigosDemas[$contador] = [
                            "codigo" => $item->codigo,
                            "prueba_diagnostica" => $validar[0]->prueba_diagnostica,
                            "tipoCodigo"         => $validar[0]->tipoCodigo,
                            "barrasEstado" => $validar[0]->barrasEstado,
                            "codigoEstado" => $validar[0]->codigoEstado,
                            "liquidacion" => $validar[0]->liquidacion,
                            "ventaEstado" => $validar[0]->ventaEstado,
                            "idusuario" => $validar[0]->idusuario,
                            "estudiante" => $validar[0]->estudiante,
                            "nombreInstitucion" => $validar[0]->nombreInstitucion,
                            "institucionBarra" => $validar[0]->institucionBarra,
                            "periodo" => $validar[0]->periodo,
                            "periodo_barras" => $validar[0]->periodo_barras,
                            "cedula" => $validar[0]->cedula,
                            "email" => $validar[0]->email,
                            "estado_liquidacion" => $validar[0]->estado_liquidacion,
                            "estado" => $validar[0]->estado,
                            "status" => $validar[0]->status,
                            "contador" => $validar[0]->contador,
                            "InstitucionLista" => $validar[0]->InstitucionLista,
                            "porcentaje_descuento" => $validar[0]->porcentaje_descuento,
                            "factura"               => $validar[0]->factura
                        ];
                        $contador++;
                    }
                }
             }else{
                $codigosNoExisten[$key] =[
                    "codigo" => $item->codigo
                ];
            }
        }
         $data = [
            "ingresados" => $porcentaje,
            "codigosNoCambiados"    => array_values($codigosNoCambiados),
            "codigosNoExisten"      => array_values($codigosNoExisten),
            "codigoConProblemas"    => $codigosDemas,
        ];
        return $data;
     }
    //API:POST/codigos/import/gestion/diagnostico
    public function importGestionDiagnostico(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $miArrayDeObjetos   = json_decode($request->data_codigos);
        //variables
        $usuario_editor     = $request->id_usuario;
        $institucion_id     = $request->institucion_id;
        $comentario         = $request->comentario;
        $periodo_id         = $request->periodo_id;
        $contadorA          = 0;
        $contadorD          = 0;
        $getLongitud        = sizeof($miArrayDeObjetos);
        $longitud           = $getLongitud/2;
        $TipoVenta          = $request->venta_estado;
        $tipoBodega         = $request->tipoBodega;
        // Supongamos que tienes una colección vacía
        $codigosNoExisten   = collect();
        $codigoConProblemas = collect();
        for($i = 0; $i<$longitud; $i++){
            // Creamos un nuevo array para almacenar los objetos quitados
            $nuevoArray             = [];
            $codigoActivacion       = "";
            $codigoDiagnostico      = "";
            $validarA               = [];
            $validarD               = [];
            // Eliminamos los dos primeros objetos del array original y los agregamos al nuevo array
            $nuevoArray[]           = array_shift($miArrayDeObjetos);
            $nuevoArray[]           = array_shift($miArrayDeObjetos);
            //ACTIVACION - DIAGNOSTICO
            if($tipoBodega == 1){
                $codigoActivacion       = $nuevoArray[0]->codigo;
                $codigoDiagnostico      = $nuevoArray[1]->codigo;
            }
            //DIAGNOSTICO - ACTIVACION
            if($tipoBodega == 2){
                $codigoActivacion       = $nuevoArray[0]->codigo;
                $codigoDiagnostico      = $nuevoArray[1]->codigo;
            }
            //===CODIGO DE ACTIVACION====
            //validacion
            $validarA               = $this->getCodigos($codigoActivacion,0);
            $validarD               = $this->getCodigos($codigoDiagnostico,0);
            //======si ambos codigos existen========
            if(count($validarA) > 0 && count($validarD) > 0){
                //====VARIABLES DE CODIGOS===
                //====Activacion=====
                //validar si el codigo ya esta liquidado
                $ifLiquidadoA                = $validarA[0]->estado_liquidacion;
                //validar si el codigo no este liquidado
                $ifBloqueadoA                = $validarA[0]->estado;
                //validar si tiene bc_institucion
                $ifBc_InstitucionA           = $validarA[0]->bc_institucion;
                //validar que el periodo del estudiante sea 0 o sea igual al que se envia
                $ifid_periodoA               = $validarA[0]->id_periodo;
                //validar si el codigo tiene venta_estado
                $venta_estadoA               = $validarA[0]->venta_estado;
                //venta lista
                $ifventa_lista_institucionA  = $validarA[0]->venta_lista_institucion;
                //======Diagnostico=====
                //validar si el codigo ya esta liquidado
                $ifLiquidadoD                = $validarD[0]->estado_liquidacion;
                //validar si el codigo no este liquidado
                $ifBloqueadoD                = $validarD[0]->estado;
                //validar si tiene bc_institucion
                $ifBc_InstitucionD           = $validarD[0]->bc_institucion;
                //validar que el periodo del estudiante sea 0 o sea igual al que se envia
                $ifid_periodoD               = $validarD[0]->id_periodo;
                //validar si el codigo tiene venta_estado
                $venta_estadoD               = $validarD[0]->venta_estado;
                //venta lista
                $ifventa_lista_institucionD  = $validarD[0]->venta_lista_institucion;
                //===VENTA DIRECTA====
                if($TipoVenta == 1){
                    if(($ifid_periodoA  == $periodo_id || $ifid_periodoA == 0 ||  $ifid_periodoA == null  ||  $ifid_periodoA == "") && ( $ifBc_InstitucionA == 0 || $ifBc_InstitucionA == $institucion_id )   && $ifLiquidadoA == '1' && $ifBloqueadoA !=2 && ($venta_estadoA == 0  || $venta_estadoA == null || $venta_estadoA == "null")){
                        if(($ifid_periodoD  == $periodo_id || $ifid_periodoD == 0 ||  $ifid_periodoD == null  ||  $ifid_periodoD == "") && ( $ifBc_InstitucionD == 0 || $ifBc_InstitucionD == $institucion_id )   && $ifLiquidadoD == '1' && $ifBloqueadoD !=2 && ($venta_estadoD == 0  || $venta_estadoD == null || $venta_estadoD == "null")){
                            //Ingresar Union a codigo de activacion
                           $old_valuesA = CodigosLibros::Where('codigo',$codigoActivacion)->get();
                           $codigoA     =  $this->UpdateCodigo($codigoActivacion,$codigoDiagnostico,$request);
                           if($codigoA){  $contadorA++; $this->GuardarEnHistorico(0,$institucion_id,$periodo_id,$codigoActivacion,$usuario_editor,$comentario,$old_valuesA); }
                           //Ingresar Union a codigo de prueba diagnostico
                           $old_valuesD = CodigosLibros::findOrFail($codigoDiagnostico);
                           $codigoB = $this->UpdateCodigo($codigoDiagnostico,$codigoActivacion,$request);
                           if($codigoB){  $contadorD++; $this->GuardarEnHistorico(0,$institucion_id,$periodo_id,$codigoDiagnostico,$usuario_editor,$comentario,$old_valuesD); }
                        }else{
                            $codigoConProblemas->push($validarD);
                        }
                    }else{
                        $codigoConProblemas->push($validarA);
                    }
                }
                //==VENTA LISTA=====
                if($TipoVenta == 2){
                    if(($ifid_periodoA  == $periodo_id || $ifid_periodoA == 0 ||  $ifid_periodoA == null  ||  $ifid_periodoA == "") && ($venta_estadoA == 0  || $venta_estadoA == null || $venta_estadoA == "null") && $ifLiquidadoA == '1' && $ifBloqueadoA !=2 && $ifventa_lista_institucionA == '0'){
                        if(($ifid_periodoD  == $periodo_id || $ifid_periodoD == 0 ||  $ifid_periodoD == null  ||  $ifid_periodoD == "") && ($venta_estadoD == 0  || $venta_estadoD == null || $venta_estadoD == "null") && $ifLiquidadoD == '1' && $ifBloqueadoD !=2 && $ifventa_lista_institucionD == '0'){
                            //Ingresar Union a codigo de activacion
                            $old_valuesA    = CodigosLibros::findOrFail($codigoActivacion);
                            $codigoA        =  $this->UpdateCodigo($codigoActivacion,$codigoDiagnostico,$request);
                            if($codigoA){  $contadorA++; $this->GuardarEnHistorico(0,$institucion_id,$periodo_id,$codigoActivacion,$usuario_editor,$comentario,$old_valuesA); }
                            //Ingresar Union a codigo de prueba diagnostico
                            $old_valuesD    = CodigosLibros::findOrFail($codigoDiagnostico);
                            $codigoB        = $this->UpdateCodigo($codigoDiagnostico,$codigoActivacion,$request);
                            if($codigoB){  $contadorD++; $this->GuardarEnHistorico(0,$institucion_id,$periodo_id,$codigoDiagnostico,$usuario_editor,$comentario,$old_valuesD); }
                        }else{
                            $codigoConProblemas->push($validarD);
                        }
                    }
                    else{
                        $codigoConProblemas->push($validarA);
                    }
                }
            }
            //Si uno de los 2 codigos no existen
            else{
                //si no existe el codigo de activacion
                if(count($validarA) == 0 && count($validarD) > 0){
                    $codigosNoExisten->push(['codigoNoExiste' => "activacion", 'codigoActivacion' => $codigoActivacion, 'codigoDiagnostico' => $codigoDiagnostico]);
                }
                //si no existe el codigo de diagnostico
                if(count($validarD) == 0 && count($validarA) > 0){
                    $codigosNoExisten->push(['codigoNoExiste' => "diagnostico",'codigoActivacion' => $codigoActivacion, 'codigoDiagnostico' => $codigoDiagnostico]);
                }
                //si no existe ambos
                if(count($validarA) == 0 && count($validarD) == 0){
                    $codigosNoExisten->push(['codigoNoExiste' => "ambos",      'codigoActivacion' => $codigoActivacion, 'codigoDiagnostico' => $codigoDiagnostico]);
                }
            }
        }
        return [
            "CodigosDiagnosticoNoexisten"      => $codigosNoExisten->all(),
            "codigoConProblemas"               => array_merge(...$codigoConProblemas->all()),
            "contadorA"                        => $contadorA,
            "contadorD"                        => $contadorD,
        ];
    }
    public function UpdateCodigo($codigo,$union,$request){
        if($request->venta_estado == 1){
            return $this->updateCodigoVentaDirecta($codigo,$union,$request);
        }
        if($request->venta_estado == 2){
            return $this->updateCodigoVentaLista($codigo,$union,$request);
        }
    }
    public function updateCodigoVentaDirecta($codigo,$union,$request){
        $codigo = DB::table('codigoslibros')
            ->where('codigo', '=', $codigo)
            ->where('estado_liquidacion', '=', '1')
            //(SE QUITARA PARA AHORA EL ESTUDIANTE YA ENVIA LEIDO) ->where('bc_estado', '=', '1')
            ->update([
                'factura'           => $request->factura,
                'bc_institucion'    => $request->institucion_id,
                'bc_periodo'        => $request->periodo_id,
                'venta_estado'      => $request->venta_estado,
                'codigo_union'      => $union
            ]);
        return $codigo;
    }
    public function updateCodigoVentaLista($codigo,$union,$request){
        $codigo = DB::table('codigoslibros')
            ->where('codigo', '=', $codigo)
            ->where('estado_liquidacion', '=', '1')
            //(SE QUITARA PARA AHORA EL ESTUDIANTE YA ENVIA LEIDO) ->where('bc_estado', '=', '1')
            ->update([
                'factura'                   => $request->factura,
                'venta_lista_institucion'   => $request->institucion_id,
                'bc_periodo'                => $request->periodo_id,
                'venta_estado'              => $request->venta_estado,
                'codigo_union'              => $union
            ]);
        return $codigo;
    }
     //api:get>>/codigos/revision
     public function revision(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $codigos = json_decode($request->data_codigos);
        $datos=[];
        $data=[];
        $codigosNoExisten=[];
        $contador = 0;
        //conDevolucion => 1 = si; 0 = no;
        $conDevolucion = $request->conDevolucion;
        foreach($codigos as $key => $item){
            $consulta = $this->getCodigos($item->codigo,$conDevolucion);
            if(count($consulta) > 0){
               $datos[] = $consulta[0];
            }else{
                $codigosNoExisten[$contador] = [
                    "codigo" => $item->codigo
                ];
                $contador++;
            }
        }
        $data = [
            "codigosNoExisten" =>$codigosNoExisten,
            "informacion" => $datos
        ];
        return $data;

     }
     public function getTipoVenta(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $tipoVenta = DB::SELECT("SELECT
        c.prueba_diagnostica,c.factura,
        IF(c.prueba_diagnostica ='1', 'Prueba de diagnóstico','Código normal') as tipoCodigo,
        c.contrato,c.porcentaje_descuento,
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
        WHERE (c.bc_institucion = '$request->institucion_id' OR venta_lista_institucion = '$request->institucion_id')
        AND c.bc_periodo = '$request->periodo_id'
        ");
        return $tipoVenta;
     }
     //api:post/codigos/bloquear
     public function bloquearCodigos(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $codigos = json_decode($request->data_codigos);
        $codigosNoCambiados=[];
        $codigoLiquidados =[];
        $codigoNoExiste = [];
        $porcentaje = 0;
        $contador = 0;
        $usuario_editor = $request->id_usuario;
        $comentario     = $request->comentario;
        $periodo_id     = $request->periodo_id;
        foreach($codigos as $key => $item){
            //validar si el codigo existe
            $validar = $this->getCodigos($item->codigo,0);
            //valida que el codigo existe
            if(count($validar)>0){
                $ifLiquidado        = $validar[0]->estado_liquidacion;
                //validar si el codigo no este liquidado
                $ifBloqueado        = $validar[0]->estado;
                if($ifLiquidado !='0' && $ifBloqueado !=2){
                    $codigo =  DB::table('codigoslibros')
                    ->where('codigo', $item->codigo)
                    ->where('estado','<>', '2')
                    ->where('estado_liquidacion','<>', '0')
                    ->update([
                        'estado'             => 2,
                    ]);
                    if($codigo){
                        $porcentaje++;
                        //ingresar en el historico
                        $old_values = CodigosLibros::Where('codigo',$item->codigo)->get();
                        $this->GuardarEnHistorico(0,$request->institucion_id,$periodo_id,$item->codigo,$usuario_editor,$comentario,$old_values);
                    }else{
                        $codigosNoCambiados[$key] =[
                            "codigo" => $item->codigo
                        ];
                    }
                }else{
                    $codigoLiquidados[$contador] = [
                        "codigo" => $item->codigo,
                        "prueba_diagnostica" => $validar[0]->prueba_diagnostica,
                        "tipoCodigo"         => $validar[0]->tipoCodigo,
                        "barrasEstado" => $validar[0]->barrasEstado,
                        "codigoEstado" => $validar[0]->codigoEstado,
                        "liquidacion" => $validar[0]->liquidacion,
                        "ventaEstado" => $validar[0]->ventaEstado,
                        "idusuario" => $validar[0]->idusuario,
                        "estudiante" => $validar[0]->estudiante,
                        "nombreInstitucion" => $validar[0]->nombreInstitucion,
                        "institucionBarra" => $validar[0]->institucionBarra,
                        "periodo" => $validar[0]->periodo,
                        "periodo_barras" => $validar[0]->periodo_barras,
                        "cedula" => $validar[0]->cedula,
                        "email" => $validar[0]->email,
                        "estado_liquidacion" => $validar[0]->estado_liquidacion,
                        "estado" => $validar[0]->estado,
                        "status" => $validar[0]->status,
                        "contador" => $validar[0]->contador,
                        "porcentaje_descuento" => $validar[0]->porcentaje_descuento,
                        "factura"               => $validar[0]->factura
                    ];
                    $contador++;
                }
            }else{
                $codigoNoExiste[$key] =[
                    "codigo" => $item->codigo
                ];
            }
        }
        return [
            "cambiados" => $porcentaje,
            "codigosNoCambiados" => $codigosNoCambiados,
            "codigoLiquidados" => $codigoLiquidados,
            "codigoNoExiste" => $codigoNoExiste
        ];
     }
     //conDevolucion => 1 si; 0 no;
     public function getCodigos($codigo,$conDevolucion){
        $consulta = DB::SELECT("SELECT c.factura, c.prueba_diagnostica,c.contador,c.codigo_union,
        IF(c.prueba_diagnostica ='1', 'Prueba de diagnóstico','Código normal') as tipoCodigo,
        c.porcentaje_descuento,
        c.libro as book,c.serie,c.created_at,
        c.codigo,c.bc_estado,c.estado,c.estado_liquidacion,c.bc_fecha_ingreso,
        c.venta_estado,c.bc_periodo,c.bc_institucion,c.idusuario,c.id_periodo,
        c.contrato,c.libro, c.venta_lista_institucion,
        CONCAT(u.nombres, ' ', u.apellidos) as estudiante, u.email,u.cedula, ib.nombreInstitucion as institucion_barras,
        i.nombreInstitucion, p.periodoescolar as periodo,pb.periodoescolar as periodo_barras,
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
        ib.nombreInstitucion as institucionBarra, i.nombreInstitucion,
        p.periodoescolar as periodo, pb.periodoescolar as periodo_barras,ivl.nombreInstitucion as InstitucionLista
        FROM codigoslibros c
        LEFT JOIN usuario u ON c.idusuario = u.idusuario
        LEFT JOIN institucion ib ON c.bc_institucion = ib.idInstitucion
        LEFT JOIN institucion i ON u.institucion_idInstitucion = i.idInstitucion
        LEFT JOIN institucion ivl ON c.venta_lista_institucion = ivl.idInstitucion
        LEFT JOIN periodoescolar p ON c.id_periodo = p.idperiodoescolar
        LEFT JOIN periodoescolar pb ON c.bc_periodo = pb.idperiodoescolar
        WHERE codigo = '$codigo'
        ");
        if(empty($consulta)){
            return $consulta;
        }
        $datos = [];
        foreach($consulta as $key => $item){
            $devolucionInstitucion = "";
            //conDevolucion => 1 si; 0 no;
            if($conDevolucion == 1){
                //ULTIMA INSTITUCION
                $query = DB::SELECT("SELECT CONCAT(' Cliente: ', d.cliente  , ' - ',d.fecha_devolucion) AS devolucion
                FROM codigos_devolucion d
                WHERE d.codigo = '$item->codigo'
                AND d.estado = '1'
                ORDER BY d.id DESC
                LIMIT 1");
                if(count($query) > 0){
                $devolucionInstitucion =  $query[0]->devolucion;
                }
            }
            $datos[$key] = (Object)[
                "InstitucionLista"              => $item->InstitucionLista,
                "barrasEstado"                  => $item->barrasEstado,
                "bc_estado"                     => $item->bc_estado,
                "bc_fecha_ingreso"              => $item->bc_fecha_ingreso,
                "bc_institucion"                => $item->bc_institucion,
                "bc_periodo"                    => $item->bc_periodo,
                "book"                          => $item->book,
                "cedula"                        => $item->cedula,
                "codigo"                        => $item->codigo,
                "codigoEstado"                  => $item->codigoEstado,
                "contador"                      => $item->contador,
                "contrato"                      => $item->contrato,
                "created_at"                    => $item->created_at,
                "devolucionInstitucion"         => $devolucionInstitucion,
                "email"                         => $item->email,
                "estado"                        => $item->estado,
                "estado_liquidacion"            => $item->estado_liquidacion,
                "estudiante"                    => $item->estudiante,
                "factura"                       => $item->factura,
                "id_periodo"                    => $item->id_periodo,
                "idusuario"                     => $item->idusuario,
                "institucionBarra"              => $item->institucionBarra,
                "institucion_barras"            => $item->institucion_barras,
                "libro"                         => $item->libro,
                "liquidacion"                   => $item->liquidacion,
                "nombreInstitucion"             => $item->nombreInstitucion,
                "periodo"                       => $item->periodo,
                "periodo_barras"                => $item->periodo_barras,
                "porcentaje_descuento"          => $item->porcentaje_descuento,
                "prueba_diagnostica"            => $item->prueba_diagnostica,
                "serie"                         => $item->serie,
                "status"                        => $item->status,
                "tipoCodigo"                    => $item->tipoCodigo,
                "ventaEstado"                   => $item->ventaEstado,
                "venta_estado"                  => $item->venta_estado,
                "venta_lista_institucion"       => $item->venta_lista_institucion,
                "codigo_union"                  => $item->codigo_union,
            ];
        }
        return $datos;
     }

     public function eliminar(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $codigos = json_decode($request->data_codigos);
        $datos=[];
        $codigosNoCambiados=[];
        $codigoNoExiste = [];
        $codigosConUsuario = [];
        $porcentaje = 0;
        $contador = 0;
        foreach($codigos as $key => $item){
             //validar si el codigo existe
             $validar = $this->getCodigos($item->codigo,0);
             //valida que el codigo existe
             if(count($validar)>0){
                //validar si el codigo tiene usuario y si ya esta liquidado
                $usuario = $validar[0]->idusuario;
                $liquidado  = $validar[0]->estado_liquidacion;
                if(($usuario == 0  || $usuario == null || $usuario == "null") && $liquidado > 0){
                    $codigo = DB::table('codigoslibros')->where('codigo', '=', $item->codigo)->delete();
                    if($codigo){
                        $porcentaje++;
                        //ingresar en el historico
                        $historico = new HistoricoCodigos();
                        $historico->id_usuario   =  $request->id_usuario;
                        $historico->codigo_libro   =  $item->codigo;
                        $historico->usuario_editor = '';
                        $historico->idInstitucion = $request->id_usuario;
                        $historico->observacion = 'Se elimino el codigo';
                        $historico->save();
                    }else{
                        $codigosNoCambiados[$key] =[
                            "codigo" => $item->codigo
                        ];
                    }
                }else{
                    $codigosConUsuario[$contador] = [
                        "codigo" => $item->codigo,
                        "prueba_diagnostica" => $validar[0]->prueba_diagnostica,
                        "tipoCodigo"         => $validar[0]->tipoCodigo,
                        "barrasEstado" => $validar[0]->barrasEstado,
                        "codigoEstado" => $validar[0]->codigoEstado,
                        "liquidacion" => $validar[0]->liquidacion,
                        "ventaEstado" => $validar[0]->ventaEstado,
                        "idusuario" => $validar[0]->idusuario,
                        "estudiante" => $validar[0]->estudiante,
                        "nombreInstitucion" => $validar[0]->nombreInstitucion,
                        "institucionBarra" => $validar[0]->institucionBarra,
                        "periodo" => $validar[0]->periodo,
                        "periodo_barras" => $validar[0]->periodo_barras,
                        "cedula" => $validar[0]->cedula,
                        "email" => $validar[0]->email,
                        "estado_liquidacion" => $validar[0]->estado_liquidacion,
                        "estado" => $validar[0]->estado,
                        "status" => $validar[0]->status,
                        "contador" => $validar[0]->contador,
                        "contrato" => $validar[0]->contrato,
                        "porcentaje_descuento" => $validar[0]->porcentaje_descuento,
                        "factura"               => $validar[0]->factura
                    ];
                    $contador++;
                 }
            }else{
                $codigoNoExiste[$key] =[
                    "codigo" => $item->codigo
                ];
            }
        }
        return [
            "eliminados" => $porcentaje,
            "codigosNoCambiados" => $codigosNoCambiados,
            "codigosConUsuario" => $codigosConUsuario,
            "codigoNoExiste" => $codigoNoExiste
        ];
     }

     public function bodegaEliminar(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $codigos = json_decode($request->data_codigos);
        $datos=[];
        $codigosNoCambiados=[];
        $codigoNoExiste = [];
        $codigosConLibro = [];
        $porcentaje = 0;
        $contador = 0;
        foreach($codigos as $key => $item){
            //validar si el codigo existe
            $validar = DB::SELECT("SELECT c.codigo, c.libro_idlibro
            FROM bodega_codigos c
            WHERE c.codigo = '$item->codigo'
            ORDER BY id DESC
            ");
            //valida que el codigo existe
            if(count($validar)>0){
                //validar si el codigo tiene un libro y no es un codigo original
                $libro_id = $validar[0]->libro_idlibro;
                if($libro_id == 0){
                    $codigo = DB::table('bodega_codigos')
                    ->where('codigo', '=', $item->codigo)
                    ->where('libro_idlibro', '=', '0')
                    ->delete();
                    if($codigo){
                        $porcentaje++;
                        //ingresar en el historico
                        $historico = new HistoricoCodigos();
                        $historico->id_usuario   =  $request->id_usuario;
                        $historico->codigo_libro   =  $item->codigo;
                        $historico->usuario_editor = '';
                        $historico->idInstitucion = $request->id_usuario;
                        $historico->observacion = 'Se elimino el codigo de la bodega de codigos';
                        $historico->save();
                    }else{
                        $codigosNoCambiados[$key] =[
                            "codigo" => $item->codigo
                        ];
                    }
                }else{
                    $codigosConLibro[$contador] = [
                    "codigo" => $item->codigo,
                    ];
                    $contador++;
                }
            }else{
                $codigoNoExiste[$key] =[
                "codigo" => $item->codigo
                ];
            }
        }
        return [
            "eliminados" => $porcentaje,
            "codigosNoCambiados" => $codigosNoCambiados,
            "codigosConLibro" => $codigosConLibro,
            "codigoNoExiste" => $codigoNoExiste
        ];
     }

     //api:post//codigos/import/periodo
     public function changePeriodo(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $codigos = json_decode($request->data_codigos);
        $datos=[];
        $codigosNoCambiados=[];
        $codigosConUsuario =[];
        $codigoNoExiste = [];
        $porcentaje = 0;
        $contador = 0;
        foreach($codigos as $key => $item){
                //validar si el codigo existe
                $validar = $this->getCodigos($item->codigo,0);
                //valida que el codigo existe
                if(count($validar)>0){
                    //validar si el codigo tiene usuario
                    $usuario = $validar[0]->idusuario;
                    if($usuario == 0  || $usuario == null || $usuario == "null"){
                        $codigo = DB::table('codigoslibros')
                        ->where('codigo', '=', $item->codigo)
                        ->update([
                           'idusuario' =>  $request->usuario_id,
                           'id_periodo' => $request->periodo_id
                        ]);
                        if($codigo){
                            $porcentaje++;
                            //ingresar en el historico
                            $historico = new HistoricoCodigos();
                            $historico->id_usuario   =  $request->usuario_id;
                            $historico->codigo_libro   =  $item->codigo;
                            $historico->usuario_editor = '';
                            $historico->idInstitucion = $request->usuario_editor;
                            $historico->id_periodo = $request->periodo_id;
                            $historico->observacion = 'Se cambio el periodo del codigo';
                            $historico->save();
                        }else{
                            $codigosNoCambiados[$key] = [
                                "codigo" => $item->codigo
                            ];
                        }
                    }else{
                        $codigosConUsuario[$contador] = [
                            "codigo" => $item->codigo,
                            "prueba_diagnostica" => $validar[0]->prueba_diagnostica,
                            "tipoCodigo"         => $validar[0]->tipoCodigo,
                            "barrasEstado" => $validar[0]->barrasEstado,
                            "codigoEstado" => $validar[0]->codigoEstado,
                            "liquidacion" => $validar[0]->liquidacion,
                            "ventaEstado" => $validar[0]->ventaEstado,
                            "idusuario" => $validar[0]->idusuario,
                            "estudiante" => $validar[0]->estudiante,
                            "nombreInstitucion" => $validar[0]->nombreInstitucion,
                            "institucionBarra" => $validar[0]->institucionBarra,
                            "periodo" => $validar[0]->periodo,
                            "periodo_barras" => $validar[0]->periodo_barras,
                            "cedula" => $validar[0]->cedula,
                            "email" => $validar[0]->email,
                            "estado_liquidacion" => $validar[0]->estado_liquidacion,
                            "estado" => $validar[0]->estado,
                            "status" => $validar[0]->status,
                            "contador" => $validar[0]->contador,
                            "porcentaje_descuento" => $validar[0]->porcentaje_descuento,
                            "factura"               => $validar[0]->factura
                        ];
                        $contador++;
                    }
                }else{
                    $codigoNoExiste[$key] =[
                        "codigo" => $item->codigo
                    ];
                }
        }
        return [
            "cambiados" => $porcentaje,
            "codigosNoCambiados" => $codigosNoCambiados,
            "codigosConUsuario" => $codigosConUsuario,
            "codigoNoExiste" => $codigoNoExiste
        ];
     }
     //api:post//codigos/import/periodo/varios
    public function changePeriodoVarios(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $codigos = json_decode($request->data_codigos);
        $datos=[];
        $codigosNoCambiados=[];
        $codigosConUsuario =[];
        $codigoNoExiste = [];
        $porcentaje = 0;
        $contador =0;
        foreach($codigos as $key => $item){
            //validar si el codigo existe
            $validar = $this->getCodigos($item->codigo,0);
            //valida que el codigo existe
            if(count($validar)>0){
                //validar si el codigo tiene usuario
                $usuario = $validar[0]->idusuario;
                if($usuario == 0  || $usuario == null || $usuario == "null"){
                    $codigo = DB::table('codigoslibros')
                    ->where('codigo', '=', $item->codigo)
                    ->update([
                        'idusuario' =>  $item->idusuario,
                        'id_periodo' => $item->id_periodo
                    ]);
                    if($codigo){
                        $porcentaje++;
                        //ingresar en el historico
                        $historico = new HistoricoCodigos();
                        $historico->id_usuario   =  $item->idusuario;
                        $historico->codigo_libro   =  $item->codigo;
                        $historico->usuario_editor = '';
                        $historico->idInstitucion = $request->id_usuario;
                        $historico->id_periodo = $item->id_periodo;
                        $historico->observacion = $item->comentario;
                        $historico->save();
                    }else{
                        $codigosNoCambiados[$key] =[
                            "codigo" => $item->codigo
                        ];
                    }
                }else{
                    $codigosConUsuario[$contador] = [
                        "codigo" => $item->codigo,
                        "prueba_diagnostica" => $validar[0]->prueba_diagnostica,
                        "tipoCodigo"         => $validar[0]->tipoCodigo,
                        "barrasEstado" => $validar[0]->barrasEstado,
                        "codigoEstado" => $validar[0]->codigoEstado,
                        "liquidacion" => $validar[0]->liquidacion,
                        "ventaEstado" => $validar[0]->ventaEstado,
                        "idusuario" => $validar[0]->idusuario,
                        "estudiante" => $validar[0]->estudiante,
                        "nombreInstitucion" => $validar[0]->nombreInstitucion,
                        "institucionBarra" => $validar[0]->institucionBarra,
                        "periodo" => $validar[0]->periodo,
                        "periodo_barras" => $validar[0]->periodo_barras,
                        "cedula" => $validar[0]->cedula,
                        "email" => $validar[0]->email,
                        "estado_liquidacion" => $validar[0]->estado_liquidacion,
                        "estado" => $validar[0]->estado,
                        "status" => $validar[0]->status,
                        "contador" => $validar[0]->contador,
                        "porcentaje_descuento" => $validar[0]->porcentaje_descuento,
                        "factura"               => $validar[0]->factura
                    ];
                    $contador++;
                }
            }else{
                $codigoNoExiste[$key] =[
                    "codigo" => $item->codigo,
                ];
            }
        }
        return [
            "cambiados" => $porcentaje,
            "codigosNoCambiados" => $codigosNoCambiados,
            "codigosConUsuario" => $codigosConUsuario,
            "codigoNoExiste" => $codigoNoExiste
        ];
    }
    //api:GET/codigo/devoluciones/{codigo}
    public function verDevoluciones($codigo){
        $getReturns = DB::SELECT("SELECT d.*,
        i.nombreInstitucion,p.periodoescolar,
        CONCAT(u.nombres, ' ', u.apellidos) AS editor
        FROM codigos_devolucion d
        LEFT JOIN institucion i ON d.institucion_id 	= i.idInstitucion
        LEFT JOIN periodoescolar p ON d.periodo_id      = p.idperiodoescolar
        LEFT JOIN usuario u ON d.usuario_editor         = u.idusuario
        WHERE d.codigo = '$codigo'
        ORDER BY id DESC
        ");
        return $getReturns;
    }
    //api:post/codigos/bodega/devolver
    public function devolucionBodega(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $codigos = json_decode($request->data_codigos);
        $datos=[];
        $codigosNoCambiados=[];
        $codigosConLiquidacion =[];
        $codigoNoExiste = [];
        $porcentaje = 0;
        $contador = 0;
        $institucion_id = $request->institucion_id;
        $fecha  = date('Y-m-d H:i:s');
        if($request->codigo){
            return $this->devolucionIndividualBodega($request->codigo,$request->id_usuario,$request->cliente,$request->institucion_id,$request->periodo_id,$request->observacion);
        }
        foreach($codigos as $key => $item){
            //validar si el codigo existe
            $validar = $this->getCodigos($item->codigo,0);
            //valida que el codigo existe
            if(count($validar)>0){
                //validar venta estado
                $ifventa_estado = $validar[0]->venta_estado;
                //validar si el codigo se encuentra liquidado
                $ifLiquidado = $validar[0]->estado_liquidacion;
                //validar que el bc_institucion sea el mismo desde el front
                $ifBc_Institucion = $validar[0]->bc_institucion;
                //institucion del venta lista
                $ifventa_lista_institucion = $validar[0]->venta_lista_institucion;
                //ADMINISTRADOR (SI PUEDE DEVOLVER AUNQUE LA INSTITUCION SEA DIFERENTE)
                if($request->admin == "yes"){
                    if($ifLiquidado == '1' || $ifLiquidado == '2'){
                        //devolucion
                        $mensaje = "Se devolvio el código";
                        $codigo = DB::table('codigoslibros')
                        ->where('codigo', '=', $item->codigo)
                        ->where('estado_liquidacion',   '<>', '0')
                        ->update([
                            'estado_liquidacion'    => '3',
                            'bc_estado'             => '1',
                        ]);
                        if($codigo){
                            $porcentaje++;
                            //ingresar en el historico
                            $this->GuardarEnHistorico(0,$request->institucion_id,$request->periodo_id,$item->codigo,$request->id_usuario,$mensaje,null);
                            //ingresar a la tabla de devolucion
                            $this->saveDevolucion($item->codigo,$request->cliente,$request->institucion_id,$request->periodo_id,$fecha,$request->observacion,$request->id_usuario);
                        }else{
                            $codigosNoCambiados[$key] =[
                                "codigo" => $item->codigo
                            ];
                        }
                    }else{
                        $codigosConLiquidacion[$contador] = [
                            "codigo" => $item->codigo,
                            "prueba_diagnostica" => $validar[0]->prueba_diagnostica,
                            "tipoCodigo"         => $validar[0]->tipoCodigo,
                            "barrasEstado" => $validar[0]->barrasEstado,
                            "codigoEstado" => $validar[0]->codigoEstado,
                            "liquidacion" => $validar[0]->liquidacion,
                            "ventaEstado" => $validar[0]->ventaEstado,
                            "idusuario" => $validar[0]->idusuario,
                            "estudiante" => $validar[0]->estudiante,
                            "nombreInstitucion" => $validar[0]->nombreInstitucion,
                            "institucionBarra" => $validar[0]->institucionBarra,
                            "periodo" => $validar[0]->periodo,
                            "periodo_barras" => $validar[0]->periodo_barras,
                            "cedula" => $validar[0]->cedula,
                            "email" => $validar[0]->email,
                            "estado_liquidacion" => $validar[0]->estado_liquidacion,
                            "estado" => $validar[0]->estado,
                            "status" => $validar[0]->status,
                            "contador" => $validar[0]->contador,
                            "contrato" => $validar[0]->contrato,
                            "porcentaje_descuento" => $validar[0]->porcentaje_descuento,
                            "factura"               => $validar[0]->factura
                        ];
                        $contador++;
                    }
                }
                //BODEGA(NO PUEDE DEVOLVER SI ES DIFERNTE INSTITUCION O PUNTO DE DEVOLUCION)
                else{
                    if(($ifLiquidado == '1' || $ifLiquidado == '2') && ($ifBc_Institucion == $institucion_id || $ifventa_lista_institucion == $institucion_id )){
                        //devolucion
                        $mensaje = "Se devolvio el código";
                        $codigo = DB::table('codigoslibros')
                        ->where('codigo', '=', $item->codigo)
                        ->where('estado_liquidacion',   '<>', '0')
                        ->update([
                            'estado_liquidacion'    => '3',
                            'bc_estado'             => '1',
                        ]);
                        if($codigo){
                            $porcentaje++;
                            //ingresar en el historico
                            $this->GuardarEnHistorico(0,$request->institucion_id,$request->periodo_id,$item->codigo,$request->id_usuario,$mensaje,null);
                            //ingresar a la tabla de devolucion
                            $this->saveDevolucion($item->codigo,$request->cliente,$request->institucion_id,$request->periodo_id,$fecha,$request->observacion,$request->id_usuario);
                        }else{
                            $codigosNoCambiados[$key] =[
                                "codigo" => $item->codigo
                            ];
                        }
                    }else{
                        $mensaje_personalizado = "";
                        //mensaje personalizado front
                        if($ifLiquidado == 0){
                            $mensaje_personalizado = "Código liquidado";
                        }
                        if($ifLiquidado == 3){
                            $mensaje_personalizado = "Código  ya devuelto";
                        }
                        if(($ifLiquidado == 1 || $ifLiquidado == 2) && ($ifBc_Institucion <> $institucion_id || $ifventa_lista_institucion <> $institucion_id)){
                            $mensaje_personalizado = "Código no pertenece a la institución que salio";
                        }
                        $codigosConLiquidacion[$contador] = [
                            "codigo" => $item->codigo,
                            "prueba_diagnostica" => $validar[0]->prueba_diagnostica,
                            "tipoCodigo"         => $validar[0]->tipoCodigo,
                            "barrasEstado" => $validar[0]->barrasEstado,
                            "codigoEstado" => $validar[0]->codigoEstado,
                            "liquidacion" => $validar[0]->liquidacion,
                            "ventaEstado" => $validar[0]->ventaEstado,
                            "idusuario" => $validar[0]->idusuario,
                            "estudiante" => $validar[0]->estudiante,
                            "nombreInstitucion" => $validar[0]->nombreInstitucion,
                            "institucionBarra" => $validar[0]->institucionBarra,
                            "periodo" => $validar[0]->periodo,
                            "periodo_barras" => $validar[0]->periodo_barras,
                            "cedula" => $validar[0]->cedula,
                            "email" => $validar[0]->email,
                            "estado_liquidacion" => $validar[0]->estado_liquidacion,
                            "estado" => $validar[0]->estado,
                            "status" => $validar[0]->status,
                            "contador" => $validar[0]->contador,
                            "contrato" => $validar[0]->contrato,
                            "mensaje" => $mensaje_personalizado,
                            "porcentaje_descuento" => $validar[0]->porcentaje_descuento,
                            "factura"               => $validar[0]->factura
                        ];
                        $contador++;
                    }
                }

            }else{
                $codigoNoExiste[$key] =[
                    "codigo" => $item->codigo
                ];
            }
        }
        return [
            "cambiados" => $porcentaje,
            "codigosNoCambiados" => $codigosNoCambiados,
            "codigosConLiquidacion" => $codigosConLiquidacion,
            "codigoNoExiste" => $codigoNoExiste
        ];
    }
    public function saveDevolucion($codigo,$cliente,$institucion_id,$periodo_id,$fecha,$observacion,$id_usuario){
        $devolucion                     = new CodigosDevolucion();
        $devolucion->codigo             = $codigo;
        $devolucion->cliente            = $cliente;
        $devolucion->institucion_id     = $institucion_id;
        $devolucion->periodo_id         = $periodo_id;
        $devolucion->fecha_devolucion   = $fecha;
        $devolucion->observacion        = $observacion;
        $devolucion->usuario_editor     = $id_usuario;
        $devolucion->save();
    }

    public function devolucionIndividualBodega($getCodigo,$id_usuario,$cliente,$institucion_id,$periodo_id,$observacion){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $codigosNoCambiados=[];
        $codigosConLiquidacion =[];
        $codigoNoExiste = [];
        $porcentaje = 0;
        $contador = 0;
        $fecha  = date('Y-m-d H:i:s');
            //validar si el codigo existe
             $validar = $this->getCodigos($getCodigo,0);
            //valida que el codigo existe
            if(count($validar)>0){
                $ifventa_estado = $validar[0]->venta_estado;
                //validar si el codigo no se encuentra liquidado
                $ifLiquidado = $validar[0]->estado_liquidacion;
                //validar que el bc_institucion sea el mismo desde el front
                $ifBc_Institucion = $validar[0]->bc_institucion;
                //institucion del venta lista
                $ifventa_lista_institucion = $validar[0]->venta_lista_institucion;
                if(($ifLiquidado == '1' || $ifLiquidado == '2') && ($ifBc_Institucion == $institucion_id || $ifventa_lista_institucion == $institucion_id )){
                    //devolucion
                    $mensaje = "Se devolvio el código";
                    $codigo = DB::table('codigoslibros')
                    ->where('codigo', '=', $getCodigo)
                    ->where('estado_liquidacion',   '<>', '0')
                    ->update([
                        'estado_liquidacion'    => '3',
                        'bc_estado'             => '1'
                    ]);
                    if($codigo){
                        $porcentaje++;
                        //ingresar en el historico
                        $historico = new HistoricoCodigos();
                        $historico->id_usuario     = $id_usuario;
                        $historico->codigo_libro   = $getCodigo;
                        $historico->usuario_editor = $institucion_id;
                        $historico->idInstitucion  = $id_usuario;
                        $historico->observacion    = $mensaje;
                        $historico->id_periodo     = $periodo_id;
                        $historico->save();
                        //ingresar a la tabla de devolucion
                        $devolucion                     = new CodigosDevolucion();
                        $devolucion->codigo             = $getCodigo;
                        $devolucion->cliente            = $cliente;
                        $devolucion->institucion_id     = $institucion_id;
                        $devolucion->periodo_id         = $periodo_id;
                        $devolucion->fecha_devolucion   = $fecha;
                        $devolucion->observacion        = $observacion;
                        $devolucion->usuario_editor     = $id_usuario;
                        $devolucion->save();
                    }else{
                        $codigosNoCambiados[0] =[
                            "codigo" => $getCodigo
                        ];
                    }
                }else{
                    $mensaje_personalizado = "";
                    //mensaje personalizado front
                    if($ifLiquidado == 0){
                        $mensaje_personalizado = "Código liquidado";
                    }else{
                        $mensaje_personalizado = "Código no pertenece a la institución que salio";
                    }
                    $codigosConLiquidacion[$contador] = [
                        "codigo" => $getCodigo,
                        "prueba_diagnostica" => $validar[0]->prueba_diagnostica,
                        "tipoCodigo"         => $validar[0]->tipoCodigo,
                        "liquidacion" => $validar[0]->liquidacion,
                        "institucionBarra" => $validar[0]->institucion_barras,
                        "periodo_barras" => $validar[0]->periodo_barras,
                        "estado_liquidacion" => $validar[0]->estado_liquidacion,
                        "mensaje"          => $mensaje_personalizado
                    ];
                    $contador++;
                }
            }else{
                $codigoNoExiste[0] =[
                    "codigo" => $getCodigo
                ];
            }

        return [
            "cambiados" => $porcentaje,
            "codigosNoCambiados" => $codigosNoCambiados,
            "codigosConLiquidacion" => $codigosConLiquidacion,
            "codigoNoExiste" => $codigoNoExiste
        ];
    }
       //api:post//codigos/devolucion/activar
       public function ActivardevolucionCodigos(Request $request){
        //api:post//codigos/import/periodo
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $codigos = json_decode($request->data_codigos);
        $datos=[];
        $codigosNoCambiados=[];
        $codigoSinDevolucion =[];
        $codigoNoExiste = [];
        $porcentaje = 0;
        $contador = 0;
        $fecha  = date('Y-m-d H:i:s');
        foreach($codigos as $key => $item){
        //validar si el codigo existe
            $validar = DB::SELECT("SELECT
            c.prueba_diagnostica, c.factura,
            IF(c.prueba_diagnostica ='1', 'Prueba de diagnóstico','Código normal') as tipoCodigo,
            c.codigo,c.bc_estado,c.estado,c.estado_liquidacion,contador,c.porcentaje_descuento,
            c.venta_estado,c.bc_periodo,c.bc_institucion,c.idusuario,c.id_periodo,
            CONCAT(u.nombres, ' ', u.apellidos) as estudiante, u.email,u.cedula, ib.nombreInstitucion as institucion_barras,
            i.nombreInstitucion, p.periodoescolar as periodo,pb.periodoescolar as periodo_barras,
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
            ib.nombreInstitucion as institucionBarra, i.nombreInstitucion,
            p.periodoescolar as periodo, pb.periodoescolar as periodo_barras
            FROM codigoslibros c
            LEFT JOIN usuario u ON c.idusuario = u.idusuario
            LEFT JOIN institucion ib ON c.bc_institucion = ib.idInstitucion
            LEFT JOIN institucion i ON u.institucion_idInstitucion = i.idInstitucion
            LEFT JOIN periodoescolar p ON c.id_periodo = p.idperiodoescolar
            LEFT JOIN periodoescolar pb ON c.bc_periodo = pb.idperiodoescolar
            WHERE codigo = '$item->codigo'
            ");
            //valida que el codigo existe
            if(count($validar)>0){
                //validar si el codigo se encuentra liquidado
                $ifDevuelto = $validar[0]->estado_liquidacion;
                //validar si el codigo se encuentra leido
                $ifLeido     = $validar[0]->bc_estado;
                if($ifDevuelto == '3'){
                    $codigo = DB::table('codigoslibros')
                    ->where('codigo', '=', $item->codigo)
                    ->where('estado_liquidacion',   '<>', '0')
                    ->where('bc_estado',            '=', '1')
                    ->update([
                        'idusuario'             => "0",
                        'id_periodo'            => "0",
                        'id_institucion'        => '',
                        'bc_estado'             => '1',
                        'estado'                => '0',
                        'estado_liquidacion'    => '1',
                        'venta_estado'          => '0',
                        'bc_periodo'            => '',
                        'bc_institucion'        => '',
                        'bc_fecha_ingreso'      => null,
                        'contrato'              => '',
                        'verif1'                => null,
                        'verif2'                => null,
                        'verif3'                => null,
                        'verif4'                => null,
                        'verif5'                => null,
                        'verif6'                => null,
                        'verif7'                => null,
                        'verif8'                => null,
                        'verif9'                => null,
                        'verif10'               => null,
                        'venta_lista_institucion'=> '0',
                        'porcentaje_descuento'  => '0',
                        'factura'               => null,
                    ]);
                    if($codigo){
                        $porcentaje++;
                        //ingresar en el historico
                        $historico = new HistoricoCodigos();
                        $historico->id_usuario     = $request->id_usuario;
                        $historico->codigo_libro   = $item->codigo;
                        $historico->usuario_editor = '';
                        $historico->idInstitucion  = $request->id_usuario;
                        $historico->observacion    = $request->observacion;
                        $historico->save();
                    }else{
                        $codigosNoCambiados[$key] =[
                            "codigo" => $item->codigo
                        ];
                    }
                }else{
                    $codigoSinDevolucion[$contador] = [
                        "codigo"                => $item->codigo,
                        "prueba_diagnostica"    => $validar[0]->prueba_diagnostica,
                        "tipoCodigo"            => $validar[0]->tipoCodigo,
                        "barrasEstado"          => $validar[0]->barrasEstado,
                        "codigoEstado"          => $validar[0]->codigoEstado,
                        "liquidacion"           => $validar[0]->liquidacion,
                        "ventaEstado"           => $validar[0]->ventaEstado,
                        "idusuario"             => $validar[0]->idusuario,
                        "estudiante"            => $validar[0]->estudiante,
                        "nombreInstitucion"     => $validar[0]->nombreInstitucion,
                        "institucionBarra"      => $validar[0]->institucionBarra,
                        "periodo"               => $validar[0]->periodo,
                        "periodo_barras"        => $validar[0]->periodo_barras,
                        "cedula"                => $validar[0]->cedula,
                        "email"                 => $validar[0]->email,
                        "estado_liquidacion"    => $validar[0]->estado_liquidacion,
                        "estado"                => $validar[0]->estado,
                        "status"                => $validar[0]->status,
                        "contador"              => $validar[0]->contador,
                        "porcentaje_descuento"  => $validar[0]->porcentaje_descuento,
                        "factura"               => $validar[0]->factura
                    ];
                    $contador++;
                }
            }else{
                $codigoNoExiste[$key] =[
                    "codigo" => $item->codigo
                ];
            }
        }
        return [
            "cambiados" => $porcentaje,
            "codigosNoCambiados" => $codigosNoCambiados,
            "codigoSinDevolucion" => $codigoSinDevolucion,
            "codigoNoExiste" => $codigoNoExiste
        ];
    }

    public function PeriodoInstitucion($institucion){
        $periodoInstitucion = DB::SELECT("SELECT idperiodoescolar AS periodo , periodoescolar AS descripcion FROM periodoescolar WHERE idperiodoescolar = (
            SELECT  pir.periodoescolar_idperiodoescolar as id_periodo
            from institucion i,  periodoescolar_has_institucion pir
            WHERE i.idInstitucion = pir.institucion_idInstitucion
            AND pir.id = (SELECT MAX(phi.id) AS periodo_maximo FROM periodoescolar_has_institucion phi
            WHERE phi.institucion_idInstitucion = i.idInstitucion
            AND i.idInstitucion = '$institucion'))
        ");
        return $periodoInstitucion;
    }

    public function index(Request $request)
    {
        $libros = DB::SELECT("SELECT * FROM codigoslibros join libro on libro.idlibro = codigoslibros.libro_idlibro  WHERE codigoslibros.idusuario = ?",[$request->idusuario]);
        return $libros;
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
        $codigo = DB::UPDATE("UPDATE `codigoslibros` SET `idusuario`= ? WHERE `codigo` = ?",[$request->idusuario,"$request->codigo"]);
        return $codigo;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\CodigoLibros  $codigoLibros
     * @return \Illuminate\Http\Response
     */
    public function show(CodigoLibros $codigoLibros)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\CodigoLibros  $codigoLibros
     * @return \Illuminate\Http\Response
     */
    public function edit(CodigoLibros $codigoLibros)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\CodigoLibros  $codigoLibros
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, CodigoLibros $codigoLibros)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\CodigoLibros  $codigoLibros
     * @return \Illuminate\Http\Response
     */
    public function destroy(CodigoLibros $codigoLibros)
    {
        //
    }
     //api:get/getEstudianteCodigos
     public function getEstudianteCodigos($data){
        $datos = explode("*", $data);
        $periodo     = $datos[0];
        $institucion = $datos[1];
        $query = DB::SELECT("SELECT c.idusuario,
        CONCAT(u.nombres,' ',u.apellidos) AS estudiante,
          c.codigo,l.nombrelibro
         FROM codigoslibros c
        LEFT JOIN usuario u ON c.idusuario = u.idusuario
        LEFT JOIN libro l ON l.idlibro = c.libro_idlibro
        WHERE u.institucion_idInstitucion ='$institucion'
        AND c.id_periodo = '$periodo'
        AND c.estado <> '2'
        ");
        return $query;
    }
    //api:post/codigos/leidos/venta_directa
    public function LeerVentaDirecta(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $codigos = json_decode($request->data_codigos);
        $institucion        =  $request->institucion_id;
        $traerPeriodo       =  $request->periodo_id;
        $nombreInstitucion  =  $request->nombreInstitucion;
        $nombrePeriodo      =  $request->nombrePeriodo;
        $venta_estado       =  $request->venta_estado;
        $comentario         =  "Codigo leido de ".$nombreInstitucion." - ".$nombrePeriodo;
        $codigosNoCambiados=[];
        $codigosLeidos =[];
        $codigoNoExiste = [];
        $porcentaje = 0;
        $contador = 0;
        $todate  = date('Y-m-d H:i:s');
        foreach($codigos as $key => $item){
            //validar si el codigo existe
            $validar = $this->getCodigos($item->codigo,0);
            //valida que el codigo existe
            if(count($validar)>0){
                //validar si el codigo ya haya sido leido
                $ifLeido            = $validar[0]->bc_estado;
                //validar si el codigo ya esta liquidado
                $ifLiquidado        = $validar[0]->estado_liquidacion;
                //validar si el codigo no este liquidado
                $ifBloqueado        = $validar[0]->estado;
                //validar si tiene bc_institucion
                $ifBc_Institucion   = $validar[0]->bc_institucion;
                //validar que el periodo del estudiante sea 0 o sea igual al que se envia
                $ifid_periodo       = $validar[0]->id_periodo;
                //validar que el venta_estado sea cero o igual al enviado desde el front
                $ifventa_estado     = $validar[0]->venta_estado;
                //validar el bc_periodo
                $ifbc_periodo       = $validar[0]->bc_periodo;
                if(($ifid_periodo  == $traerPeriodo || $ifid_periodo == 0 ||  $ifid_periodo == null  ||  $ifid_periodo == "") && ($ifBc_Institucion  == $institucion || $ifBc_Institucion == 0) && ($ifbc_periodo  == $traerPeriodo || $ifbc_periodo == 0) && ($ifventa_estado == 0 || $ifventa_estado == $venta_estado) && ($ifLeido == '1' || $ifLeido == '2') && $ifLiquidado == '1' && $ifBloqueado !=2){
                    $codigo =  DB::table('codigoslibros')
                    ->where('codigo', $item->codigo)
                    // ->where('bc_estado', '1')
                    ->where('estado','<>', '2')
                    ->where('estado_liquidacion','=', '1')
                    ->update([
                        'bc_institucion'        => $institucion,
                        'bc_estado'             => 2,
                        'bc_periodo'            => $traerPeriodo,
                        'bc_fecha_ingreso'      => $todate,
                        'venta_estado'          => $venta_estado
                    ]);
                    if($codigo){
                        $porcentaje++;
                        //ingresar en el historico
                        $historico                  = new HistoricoCodigos();
                        $historico->codigo_libro    = $item->codigo;
                        $historico->usuario_editor  = $institucion;
                        $historico->idInstitucion   = $request->id_usuario;
                        $historico->id_periodo      = $traerPeriodo;
                        $historico->observacion     = $comentario;
                        $historico->b_estado        = "1";
                        $historico->save();
                    }else{
                        $codigosNoCambiados[$key] =[
                            "codigo" => $item->codigo
                        ];
                    }
                }else{
                    $codigosLeidos[$contador] = [
                        "codigo"                => $item->codigo,
                        "prueba_diagnostica"    => $validar[0]->prueba_diagnostica,
                        "tipoCodigo"            => $validar[0]->tipoCodigo,
                        "barrasEstado"          => $validar[0]->barrasEstado,
                        "codigoEstado"          => $validar[0]->codigoEstado,
                        "liquidacion"           => $validar[0]->liquidacion,
                        "ventaEstado"           => $validar[0]->ventaEstado,
                        "idusuario"             => $validar[0]->idusuario,
                        "estudiante"            => $validar[0]->estudiante,
                        "nombreInstitucion"     => $validar[0]->nombreInstitucion,
                        "institucionBarra"      => $validar[0]->institucionBarra,
                        "periodo"               => $validar[0]->periodo,
                        "periodo_barras"        => $validar[0]->periodo_barras,
                        "cedula"                => $validar[0]->cedula,
                        "email"                 => $validar[0]->email,
                        "estado_liquidacion"    => $validar[0]->estado_liquidacion,
                        "estado"                => $validar[0]->estado,
                        "status"                => $validar[0]->status,
                        "contador"              => $validar[0]->contador,
                        "porcentaje_descuento"  => $validar[0]->porcentaje_descuento,
                        "factura"               => $validar[0]->factura
                    ];
                    $contador++;
                }
            }else{
                $codigoNoExiste[$key] =[
                    "codigo" => $item->codigo
                ];
            }
        }
        return [
            "cambiados" => $porcentaje,
            "codigosNoCambiados" => $codigosNoCambiados,
            "codigosLeidos" => $codigosLeidos,
            "codigoNoExiste" => $codigoNoExiste
        ];
    }
    //api:post/codigos/ingreso
    public function importIngresoCodigos(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $codigos           = json_decode($request->data_codigos);
        $pruebaDiagnostica = $request->pruebaDiagnostica;
        $idlibro           = $request->idlibro;
        $id_usuario        = $request->id_usuario;
        $anio              = $request->anio;
        $libro             = $request->libro;
        $serie             = $request->serie;
        $datos             = [];
        $NoIngresados      = [];
        $porcentaje        = 0;
        $contador          = 0;
        foreach($codigos as $key => $item){
            $consulta = $this->getCodigos($item->codigo,0);
            //si ya existe el codigo lo mando a un array
            if(count($consulta) > 0){
               $datos[] = $consulta[0];
            }else{
                //si no existen los agrego
                $codigos_libros                             = new CodigosLibros();
                $codigos_libros->serie                      = $serie;
                $codigos_libros->libro                      = $libro;
                $codigos_libros->anio                       = $anio;
                $codigos_libros->libro_idlibro              = $idlibro;
                $codigos_libros->estado                     = 0;
                $codigos_libros->idusuario                  = 0;
                $codigos_libros->bc_estado                  = 1;
                $codigos_libros->idusuario_creador_codigo   = $id_usuario;
                $codigos_libros->prueba_diagnostica         = $pruebaDiagnostica;
                $codigos_libros->codigo                     = $item->codigo;
                $codigos_libros->contador                   = 1;
                $codigos_libros->save();
                if($codigos_libros){
                    $porcentaje++;
                }else{
                    $NoIngresados[$contador] =[
                        "codigo" => $item->codigo
                    ];
                    $contador++;
                }
            }
        }
        $data = [
            "cambiados"             => $porcentaje,
            "CodigosExisten"        => $datos,
            "CodigosNoIngresados"   => $NoIngresados,
        ];
        return $data;
    }
}
