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
use App\Repositories\Codigos\CodigosRepository;
use App\Traits\Codigos\TraitCodigosGeneral;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use PDO;

class CodigoLibrosController extends Controller
{
    use TraitCodigosGeneral;
    private $codigosRepository;
    public function __construct(CodigosRepository $codigosRepository) {
        $this->codigosRepository = $codigosRepository;
    }
    //api:post//codigos/importar
    public function importar(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $codigos                = json_decode($request->data_codigos);
        $institucion            = $request->institucion_id;
        $traerPeriodo           = $request->periodo_id;
        $nombreInstitucion      = $request->nombreInstitucion;
        $nombrePeriodo          = $request->nombrePeriodo;
        $venta_estado           = $request->venta_estado;
        $comentario             = "Codigo leido de ".$nombreInstitucion." - ".$nombrePeriodo;
        $id_usuario             = $request->id_usuario;
        $codigosNoCambiados     = [];
        $contadorNoCambiado     = 0;
        $codigosLeidos          = [];
        $codigoNoExiste         = [];
        $codigosSinCodigoUnion  = [];
        $contadorNoExiste       = 0;
        $porcentaje             = 0;
        $contador               = 0;
        foreach($codigos as $key => $item){
            //validar si el codigo existe
            $validar = $this->getCodigos($item->codigo,0);
            //valida que el codigo existe
            if(count($validar) > 0){
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
                //validar si tiene codigo de union
                $codigo_union       = $validar[0]->codigo_union;
                //===PROCESO===========
                if(($ifid_periodo  == $traerPeriodo || $ifid_periodo == 0 ||  $ifid_periodo == null  ||  $ifid_periodo == "") && ($ifBc_Institucion  == $institucion || $ifBc_Institucion == 0) && ($ifbc_periodo  == $traerPeriodo || $ifbc_periodo == 0) && ($ifventa_estado == 0 || $ifventa_estado == $venta_estado) && $ifLeido == '1' && $ifLiquidado == '1' && $ifBloqueado !=2){
                    //VALIDAR CODIGOS QUE NO TENGA CODIGO UNION
                    $getcodigoPrimero = CodigosLibros::Where('codigo',$item->codigo)->get();
                    $getcodigoUnion   = CodigosLibros::Where('codigo',$codigo_union)->get();
                    if($codigo_union != null || $codigo_union != ""){
                        //PASAR A LEIDO CON CODIGO DE UNION
                        $ingreso = $this->pasarLeidos($item->codigo,$codigo_union,$request,$getcodigoUnion);
                        //si ingresa correctamente
                        if($ingreso == 1){
                            $porcentaje++;
                            //====CODIGO====
                            //ingresar en el historico codigo
                            $this->GuardarEnHistorico(0,$institucion,$traerPeriodo,$item->codigo,$id_usuario,$comentario,$getcodigoPrimero,null);
                            //====CODIGO UNION=====
                            $this->GuardarEnHistorico(0,$institucion,$traerPeriodo,$codigo_union,$id_usuario,$comentario,$getcodigoUnion,null);
                        }else{
                            $codigosNoCambiados[$contadorNoCambiado] =[
                                "codigo"        => $item->codigo,
                                "mensaje"       => "Problema con el código union $codigo_union"
                            ];
                            $contadorNoCambiado++;
                        }
                    }
                    //ACTUALIZAR CODIGO SIN UNION
                    else{
                        $ingreso =  $this->pasarLeidos($item->codigo,0,$request,null);
                        if($ingreso == 1){
                            $porcentaje++;
                            //ingresar en el historico
                            $this->GuardarEnHistorico(0,$institucion,$traerPeriodo,$item->codigo,$id_usuario,$comentario,$getcodigoPrimero,null);
                            $codigosSinCodigoUnion[] = $validar[0];
                        }
                        else{
                            $codigosNoCambiados[$contadorNoCambiado] =[
                                "codigo"        => $item->codigo,
                                "mensaje"       => "0"
                            ];
                            $contadorNoCambiado++;
                        }
                    }
                }else{
                    $codigosLeidos[$contador] = $validar[0];
                    $contador++;
                }
            }else{
                $codigoNoExiste[$contadorNoExiste] =[
                    "codigo" => $item->codigo
                ];
                $contadorNoExiste++;
            }
        }
        return [
            "cambiados"             => $porcentaje,
            "codigosNoCambiados"    => $codigosNoCambiados,
            "codigosLeidos"         => $codigosLeidos,
            "codigoNoExiste"        => $codigoNoExiste,
            "codigosSinCodigoUnion" => $codigosSinCodigoUnion,
        ];
    }
    public function pasarLeidos($codigo,$codigo_union,$request,$objectCodigoUnion){
        $withCodigoUnion = 1;
        $estadoIngreso   = 0;
        $todate          = date('Y-m-d H:i:s');
        $institucion     = $request->institucion_id;
        $traerPeriodo    = $request->periodo_id;
        $venta_estado    = $request->venta_estado;
        $unionCorrecto   = false;
        ///estadoIngreso => 1 = ingresado; 2 = no se puedo ingresar el codigo de union;
        if($codigo_union == '0') $withCodigoUnion = 0;
        else                     $withCodigoUnion = 1;
        $arraySave  = [
            'bc_institucion'        => $institucion,
            'bc_estado'             => 2,
            'bc_periodo'            => $traerPeriodo,
            'bc_fecha_ingreso'      => $todate,
            'venta_estado'          => $venta_estado
        ];
        //si hay codigo de union lo actualizo
        if($withCodigoUnion == 1){
            //VALIDO SI NO EXISTE EL CODIGO DE UNION LO MANDO COMO ERROR
            if(count($objectCodigoUnion) == 0){
                //no se ingreso
                return 2;
            }
            //VALIDO QUE EL CODIGO UNION CUMPLA LA VALIDACION
            //validar si el codigo ya haya sido leido
            $ifLeido            = $objectCodigoUnion[0]->bc_estado;
            //validar si el codigo ya esta liquidado
            $ifLiquidado        = $objectCodigoUnion[0]->estado_liquidacion;
            //validar si el codigo no este liquidado
            $ifBloqueado        = $objectCodigoUnion[0]->estado;
            //validar si tiene bc_institucion
            $ifBc_Institucion   = $objectCodigoUnion[0]->bc_institucion;
            //validar que el periodo del estudiante sea 0 o sea igual al que se envia
            $ifid_periodo       = $objectCodigoUnion[0]->id_periodo;
            //validar que el venta_estado sea cero o igual al enviado desde el front
            $ifventa_estado     = $objectCodigoUnion[0]->venta_estado;
            //validar el bc_periodo
            $ifbc_periodo       = $objectCodigoUnion[0]->bc_periodo;
            if(($ifid_periodo  == $traerPeriodo || $ifid_periodo == 0 ||  $ifid_periodo == null  ||  $ifid_periodo == "") && ($ifBc_Institucion  == $institucion || $ifBc_Institucion == 0) && ($ifbc_periodo  == $traerPeriodo || $ifbc_periodo == 0) && ($ifventa_estado == 0 || $ifventa_estado == $venta_estado) && $ifLeido == '1' && $ifLiquidado == '1' && $ifBloqueado !=2) $unionCorrecto = true;
            else $unionCorrecto = false;
            if($unionCorrecto){
                $codigoU = DB::table('codigoslibros')
                ->where('codigo', '=', $codigo_union)
                ->where('bc_estado', '1')
                ->where('estado','<>', '2')
                ->where('estado_liquidacion','=', '1')
                ->update($arraySave);
                //si el codigo de union se actualiza actualizo el codigo
                if($codigoU){
                    //actualizar el primer codigo
                    $codigo = DB::table('codigoslibros')
                    ->where('codigo', '=', $codigo)
                    ->where('bc_estado', '1')
                    ->where('estado','<>', '2')
                    ->where('estado_liquidacion','=', '1')
                    ->update($arraySave);
                }
            }else{
                //no se ingreso
                return 2;
            }
        }else{
            //actualizar el primer codigo
            $codigo = DB::table('codigoslibros')
            ->where('codigo', '=', $codigo)
            ->where('bc_estado', '1')
            ->where('estado','<>', '2')
            ->where('estado_liquidacion','=', '1')
            ->update($arraySave);
        }
        //con codigo union
        ///estadoIngreso => 1 = ingresado; 2 = no se puedo ingresar el codigo de union;
        if($withCodigoUnion == 1){
            if($codigo && $codigoU)  $estadoIngreso = 1;
            else                     $estadoIngreso = 2;
        }
        //si no existe el codigo de union
        if($withCodigoUnion == 0){
            if($codigo)              $estadoIngreso = 1;
        }
        return $estadoIngreso;
    }
    public function procesoGestionBodega($numeroProceso,$codigo,$codigo_union,$request,$objectCodigoUnion,$factura){
        //numero proceso => 0 = usan y liquidan; 1 = venta lista; 2 = regalado; 3 regalado y bloqueado
        $withCodigoUnion = 1;
        $estadoIngreso   = 0;
        $periodo_id      = $request->periodo_id;
        $unionCorrecto   = false;
        ///estadoIngreso => 1 = ingresado; 2 = no se puedo ingresar el codigo de union;
        if($codigo_union == null) $withCodigoUnion = 0;
        else                      $withCodigoUnion = 1;
        //si hay codigo de union lo actualizo
        if($withCodigoUnion == 1){
            //VALIDO SI NO EXISTE EL CODIGO DE UNION LO MANDO COMO ERROR
            if(count($objectCodigoUnion) == 0){
                //no se ingreso
                return 2;
            }
            //validar si el codigo ya haya sido leido
            $ifLeido                    = $objectCodigoUnion[0]->bc_estado;
            //validar si el codigo ya esta liquidado
            $ifLiquidado                = $objectCodigoUnion[0]->estado_liquidacion;
            //validar si el codigo no este liquidado
            $ifBloqueado                = $objectCodigoUnion[0]->estado;
            //validar si tiene bc_institucion
            $ifBc_Institucion           = $objectCodigoUnion[0]->bc_institucion;
            //validar que el periodo del estudiante sea 0 o sea igual al que se envia
            $ifid_periodo               = $objectCodigoUnion[0]->id_periodo;
            //validar si el codigo tiene venta_estado
            $venta_estado               = $objectCodigoUnion[0]->venta_estado;
            //venta lista
            $ifventa_lista_institucion  = $objectCodigoUnion[0]->venta_lista_institucion;
            //para ver si un codigo regalado esta liquidado
            $ifliquidado_regalado       = $objectCodigoUnion[0]->liquidado_regalado;
            //=====USAN Y LIQUIDAN=========================
            if($numeroProceso == '0'){
                if(($ifid_periodo  == $periodo_id || $ifid_periodo == 0 ||  $ifid_periodo == null  ||  $ifid_periodo == "")  && ($ifLeido == '1') && $ifLiquidado == '1' && $ifBloqueado !=2 ) $unionCorrecto = true;
                else $unionCorrecto = false;
            }
            //======REGALADO NO ENTRA A LA LIQUIDACION============
            if($numeroProceso == '1'){
                if($ifLiquidado == '1' && $ifliquidado_regalado == '0') $unionCorrecto = true;
                else $unionCorrecto = false;
            }
            //======REGALADO Y BLOQUEADO(No usan y no liquidan)=============
            if($numeroProceso == '2'){
                if(($ifLiquidado !='0' && $ifLiquidado !='4') && $ifBloqueado !=2 && $ifliquidado_regalado == '0') $unionCorrecto = true;
                else $unionCorrecto = false;
            }
            //======BLOQUEADO(No usan y no liquidan)=============
            if($numeroProceso == '3'){
                if(($ifLiquidado !='0' && $ifLiquidado !='4') && $ifBloqueado !=2 && $ifliquidado_regalado == '0') $unionCorrecto = true;
                else $unionCorrecto = false;
            }
            //=======CODIGO GUIA==================================
            if($numeroProceso == '4'){
                $numeroProceso     = 4;
                if(($ifLiquidado == '1') && $ifLeido == '1' && $ifBloqueado !=2 && $ifliquidado_regalado == '0') $unionCorrecto = true;
                else $unionCorrecto = false;
            }
            if($unionCorrecto){
                $codigoU             = $this->codigosRepository->procesoUpdateGestionBodega($numeroProceso,$codigo_union,$codigo,$request,$factura);
                if($codigoU) $codigo = $this->codigosRepository->procesoUpdateGestionBodega($numeroProceso,$codigo,$codigo_union,$request,$factura);
            }else{
                //no se ingreso
                return 2;
            }
        }
        //SI EL CODIGO NO TIENE CODIGO DE UNION
        else{
            $codigo = $this->codigosRepository->procesoUpdateGestionBodega($numeroProceso,$codigo,null,$request,$factura);
        }
        //resultado
        //con codigo union
        ///estadoIngreso => 1 = ingresado; 2 = no se puedo ingresar el codigo de union;
        if($withCodigoUnion == 1){
            if($codigo && $codigoU)  $estadoIngreso = 1;
            else                     $estadoIngreso = 2;
        }
        //si no existe el codigo de union
        if($withCodigoUnion == 0){
            if($codigo)              $estadoIngreso = 1;
        }
        return $estadoIngreso;
    }
    //api:post//codigos/import/gestion
    public function importGestion(Request $request)
    {
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $codigos            = json_decode($request->data_codigos);
        //variables
        $usuario_editor     = $request->id_usuario;
        $institucion        = $request->institucion_id;
        $comentario         = $request->comentario;
        $periodo_id         = $request->periodo_id;
        //0=> USAN Y LIQUIDAN ; 1=> regalado; 2 regalado y bloqueado; 3 = bloqueado
        $tipoProceso        = $request->regalado;
        $codigoNoExiste     = [];
        $codigosDemas       = [];
        $contadorNoExiste   = 0;
        $codigosNoCambiados = [];
        $codigosSinCodigoUnion = [];
        $usuarioQuemado     = 45017;
        $instUserQuemado    = 66;
        $contador           = 0;
        $contadorNoCambiado = 0;
        $numeroProceso      = 0;
        $porcentaje         = 0;
        $factura            = "";
        foreach($codigos as $key => $item){
            //validar si el codigo existe
            $validacionCodigo               = false;
            $numeroProceso                  = 0;
            $user                           = 0;
            $validar = $this->getCodigos($item->codigo,0);
            //valida que el codigo existe
            if(count($validar) > 0){
                //validar si el codigo ya haya sido leido
                $ifLeido                    = $validar[0]->bc_estado;
                //validar si el codigo ya esta liquidado
                $ifLiquidado                = $validar[0]->estado_liquidacion;
                //validar si el codigo no este liquidado
                $ifBloqueado                = $validar[0]->estado;
                //validar si tiene bc_institucion
                $ifBc_Institucion           = $validar[0]->bc_institucion;
                //validar que el periodo del estudiante sea 0 o sea igual al que se envia
                $ifid_periodo               = $validar[0]->id_periodo;
                //validar si el codigo tiene venta_estado
                $venta_estado               = $validar[0]->venta_estado;
                //venta lista
                $ifventa_lista_institucion  = $validar[0]->venta_lista_institucion;
                //validar si tiene codigo de union
                $codigo_union               = $validar[0]->codigo_union;
                //obtener la factura si no envian nada le dejo lo mismo
                $facturaA                   = $validar[0]->factura;
                //validar si un regalado esta liquidado
                $ifliquidado_regalado       = $validar[0]->liquidado_regalado;
                if($request->factura == null || $request->factura == "")   { $factura = $facturaA; }
                else{ $factura = $request->factura; }
                //===PROCESO===========
                //=====USAN Y LIQUIDAN=========================
                if($tipoProceso == '0'){
                    //SE QUITA ESTE CODIGO POR JORGE DICE QUE SI ENVIA A VENTA DIRECTA O LISTA NO HAY PROBLEMA
                    // //VENTA DIRECTA
                    // if($TipoVenta == 1){
                    //     $numeroProceso     = 0;
                    //     if(($ifid_periodo  == $periodo_id || $ifid_periodo == 0 ||  $ifid_periodo == null  ||  $ifid_periodo == "") && ( $ifBc_Institucion == 0 || $ifBc_Institucion == $institucion )  && ($ifLeido == '1' || $ifLeido == '2') && $ifLiquidado == '1' && $ifBloqueado !=2 && ($venta_estado == 0  || $venta_estado == null || $venta_estado == "null")) $validacionCodigo = true;
                    //     else $validacionCodigo = false;
                    // }
                    // //VENTA LISTA
                    // if($TipoVenta == 2){
                    //     $numeroProceso     = 1;
                    //     if(($ifid_periodo  == $periodo_id || $ifid_periodo == 0 ||  $ifid_periodo == null  ||  $ifid_periodo == "")  && ($ifLeido == '1' || $ifLeido == '2') && ($venta_estado == 0  || $venta_estado == null || $venta_estado == "null") && $ifLiquidado == '1' && $ifBloqueado !=2 && $ifventa_lista_institucion == '0') $validacionCodigo = true;
                    //     else $validacionCodigo = false;
                    // }
                    $numeroProceso     = 0;
                    if(($ifid_periodo  == $periodo_id || $ifid_periodo == 0 ||  $ifid_periodo == null  ||  $ifid_periodo == "") && $ifLeido == '1' && $ifLiquidado == '1' && $ifBloqueado !=2  && $ifliquidado_regalado == '0') {  $validacionCodigo = true; }
                    else { $validacionCodigo = false; }
                }
                //======REGALADO NO ENTRA A LA LIQUIDACION============
                if($tipoProceso == '1'){
                    $numeroProceso     = 1;
                    if($ifLiquidado == '1' && $ifliquidado_regalado == '0') { $validacionCodigo = true; }
                    else  { $validacionCodigo = false; }
                }
                //======REGALADO Y BLOQUEADO(No usan y no liquidan)=============
                if($tipoProceso == '2'){
                    $numeroProceso     = 2;
                    $user              = $usuarioQuemado;
                    if(($ifLiquidado !='0' && $ifLiquidado !='4') && $ifBloqueado !=2 && $ifliquidado_regalado == '0') { $validacionCodigo = true; }
                    else { $validacionCodigo = false; }
                }
                //======BLOQUEADO(No usan y no liquidan)=============
                if($tipoProceso == '3'){
                    $numeroProceso     = 3;
                    $user              = $usuarioQuemado;
                    if(($ifLiquidado !='0' && $ifLiquidado !='4') && $ifBloqueado !=2 && $ifliquidado_regalado == '0') { $validacionCodigo = true; }
                    else { $validacionCodigo = false; }
                }
                //=======CODIGO GUIA==================================
                if($tipoProceso == '4'){
                    $numeroProceso     = 4;
                    if(($ifLiquidado == '1') && $ifLeido == '1' && $ifBloqueado !=2 && $ifliquidado_regalado == '0') { $validacionCodigo = true; }
                    else { $validacionCodigo = false; }
                }
                //si todo sale bien
                if($validacionCodigo){
                    //VALIDAR CODIGOS QUE NO TENGA CODIGO UNION
                    $getcodigoPrimero = CodigosLibros::Where('codigo',$item->codigo)->get();
                    $getcodigoUnion   = CodigosLibros::Where('codigo',$codigo_union)->get();
                    if($codigo_union != null || $codigo_union != ""){
                        //numero proceso => 0 = Usan y liquidan; 1 =  regalado; 2 = regalado y bloqueado ; 3 = bloqueado; 4 = guia
                        $ingreso = $this->procesoGestionBodega($numeroProceso,$item->codigo,$codigo_union,$request,$getcodigoUnion,$factura);
                        //si ingresa correctamente
                        if($ingreso == 1){
                            $porcentaje++;
                            //====CODIGO====
                            //ingresar en el historico codigo
                            $this->GuardarEnHistorico($user,$institucion,$periodo_id,$item->codigo,$usuario_editor,$comentario,$getcodigoPrimero,null);
                            //====CODIGO UNION=====
                            $this->GuardarEnHistorico($user,$institucion,$periodo_id,$codigo_union,$usuario_editor,$comentario,$getcodigoUnion,null);
                        }else{
                            $codigosNoCambiados[$contadorNoCambiado] =[
                                "codigo"        => $item->codigo,
                                "mensaje"       => "Problema con el código union $codigo_union"
                            ];
                            $contadorNoCambiado++;
                        }
                    }
                    //ACTUALIZAR CODIGO SIN UNION
                    else{
                        $ingreso = $this->procesoGestionBodega($numeroProceso,$item->codigo,null,$request,null,$factura);
                        if($ingreso == 1){
                            $porcentaje++;
                            //ingresar en el historico
                            $this->GuardarEnHistorico($user,$institucion,$periodo_id,$item->codigo,$usuario_editor,$comentario,$getcodigoPrimero,null);
                            $codigosSinCodigoUnion[] = $validar[0];
                        }
                        else{
                            $codigosNoCambiados[$contadorNoCambiado] =[
                                "codigo"        => $item->codigo,
                                "mensaje"       => "0"
                            ];
                            $contadorNoCambiado++;
                        }
                    }
                }else{
                    $codigosDemas[$contador] = $validar[0];
                    $contador++;
                }
            }else{
                $codigoNoExiste[$contadorNoExiste] =[
                    "codigo" => $item->codigo
                ];
                $contadorNoExiste++;
            }
        }
         $data = [
            "ingresados"            => $porcentaje,
            "codigosNoCambiados"    => $codigosNoCambiados,
            "codigosNoExisten"      => $codigoNoExiste,
            "codigoConProblemas"    => $codigosDemas,
            "codigosSinCodigoUnion" => $codigosSinCodigoUnion,
        ];
        return $data;
     }
    //API:POST/codigos/import/gestion/diagnostico
    public function importGestionDiagnostico(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        //0=> venta directa ; 1=> regalado; 2 regalado y bloqueado
        $tipoProceso        = $request->regalado;
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
        $usuarioQuemado     = 45017;
        $facturaA           = "";
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
            $old_valuesA            = [];
            $old_valuesD            = [];
            // Eliminamos los dos primeros objetos del array original y los agregamos al nuevo array
            $nuevoArray[]           = array_shift($miArrayDeObjetos);
            $nuevoArray[]           = array_shift($miArrayDeObjetos);
            //ACTIVACION - DIAGNOSTICO
            if($tipoBodega == 1){
                $codigoActivacion       = strtoupper($nuevoArray[0]->codigo);
                $codigoDiagnostico      = strtoupper($nuevoArray[1]->codigo);
            }
            //DIAGNOSTICO - ACTIVACION
            if($tipoBodega == 2){
                $codigoActivacion       = strtoupper($nuevoArray[0]->codigo);
                $codigoDiagnostico      = strtoupper($nuevoArray[1]->codigo);
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
                //validar si el codigo no este leido
                $ifBcEstadoA                  = $validarA[0]->bc_estado;
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
                //codigo de union
                $codigo_unionA               = strtoupper($validarA[0]->codigo_union);
                $ifliquidado_regaladoA       = $validarA[0]->liquidado_regalado;
                //======Diagnostico=====
                //validar si el codigo ya esta liquidado
                $ifLiquidadoD                = $validarD[0]->estado_liquidacion;
                //validar si el codigo no este leido
                $ifBcEstadoD                  = $validarA[0]->bc_estado;
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
                //codigo de union
                $codigo_unionD               = strtoupper($validarD[0]->codigo_union);
                $ifliquidado_regaladoD       = $validarD[0]->liquidado_regalado;
                $old_valuesA = CodigosLibros::Where('codigo',$codigoActivacion)->get();
                $old_valuesD = CodigosLibros::findOrFail($codigoDiagnostico);
                //obtener la factura si no envian nada le dejo lo mismo
                $facturaA                   = $validarA[0]->factura;
                if($request->factura == null || $request->factura == "")   $factura = $facturaA;
                else  $factura = $request->factura;
                //===PROCESO===========
                //=====USAN Y LIQUIDAN=========================
                if($tipoProceso == '0'){
                    if(($ifid_periodoA  == $periodo_id || $ifid_periodoA == 0 ||  $ifid_periodoA == null  ||  $ifid_periodoA == "")  && ($ifBcEstadoA == '1')  && $ifLiquidadoA == '1' && $ifBloqueadoA !=2 &&  (($codigo_unionA == $codigoDiagnostico) || ($codigo_unionA == null || $codigo_unionA == "" || $codigo_unionA == "0")) && ($ifliquidado_regaladoA == '0')){
                        if(($ifid_periodoD  == $periodo_id || $ifid_periodoD == 0 ||  $ifid_periodoD == null  ||  $ifid_periodoD == "") && ($ifBcEstadoD == '1')  && $ifLiquidadoD == '1' && $ifBloqueadoD !=2 && (($codigo_unionD == $codigoActivacion) || ($codigo_unionD == null || $codigo_unionD == "" || $codigo_unionD == "0")) && ($ifliquidado_regaladoD == '0')){
                        //Ingresar Union a codigo de activacion
                        $codigoA     = $this->codigosRepository->procesoUpdateGestionBodega($tipoProceso,$codigoActivacion,$codigoDiagnostico,$request,$factura);
                        if($codigoA){ $contadorA++; $this->GuardarEnHistorico(0,$institucion_id,$periodo_id,$codigoActivacion,$usuario_editor,$comentario,$old_valuesA,null); }
                        //Ingresar Union a codigo de prueba diagnostico
                        $codigoB     = $this->codigosRepository->procesoUpdateGestionBodega($tipoProceso,$codigoDiagnostico,$codigoActivacion,$request,$factura);
                        if($codigoB){  $contadorD++; $this->GuardarEnHistorico(0,$institucion_id,$periodo_id,$codigoDiagnostico,$usuario_editor,$comentario,$old_valuesD,null
                        ); }
                        }else{
                            $codigoConProblemas->push($validarD);
                        }
                    }else{
                        $codigoConProblemas->push($validarA);
                    }
                }
                //======REGALADO NO ENTRA A LA LIQUIDACION============
                //numeroProceso => 1 regalado ; 2 regalado y bloqueado; 3 = bloqueado;
                if($tipoProceso == '1'){
                    if($ifLiquidadoA == '1' && (($codigo_unionA == $codigoDiagnostico) || ($codigo_unionA == null || $codigo_unionA == "" || $codigo_unionA == "0")) && ($ifliquidado_regaladoA == '0') ){
                        if($ifLiquidadoD == '1' && (($codigo_unionD == $codigoActivacion) || ($codigo_unionD == null || $codigo_unionD == "" || $codigo_unionD == "0")) && ($ifliquidado_regaladoD == '0') ){
                        //Cambiar a regalado a codigo de activacion
                        //(numeroProceso,codigo,$request)
                        $codigoA     = $this->codigosRepository->procesoUpdateGestionBodega($tipoProceso,$codigoActivacion,$codigoDiagnostico,$request,$factura);
                        if($codigoA){  $contadorA++; $this->GuardarEnHistorico(0,$institucion_id,$periodo_id,$codigoActivacion,$usuario_editor,$comentario,$old_valuesA,null); }
                        //Cambiar a regalado a codigo de diagnostico
                        $codigoB     = $this->codigosRepository->procesoUpdateGestionBodega($tipoProceso,$codigoDiagnostico,$codigoActivacion,$request,$factura);
                        if($codigoB){  $contadorD++; $this->GuardarEnHistorico(0,$institucion_id,$periodo_id,$codigoDiagnostico,$usuario_editor,$comentario,$old_valuesD,null); }
                        }else{
                            $codigoConProblemas->push($validarD);
                        }
                    }else{
                        $codigoConProblemas->push($validarA);
                    }
                }
                //======REGALADO Y BLOQUEADO(No usan y no liquidan)=============
                if($tipoProceso == '2'){
                    if((    $ifLiquidadoA !='0' && $ifLiquidadoA !='4') && $ifBloqueadoA !=2 && (($codigo_unionA == $codigoDiagnostico) || ($codigo_unionA == null || $codigo_unionA == "" || $codigo_unionA == "0")) && ($ifliquidado_regaladoA == '0') ){
                        if(($ifLiquidadoD !='0' && $ifLiquidadoD !='4') && $ifBloqueadoD !=2 && (($codigo_unionD == $codigoActivacion) || ($codigo_unionD == null || $codigo_unionD == "" || $codigo_unionD == "0")) && ($ifliquidado_regaladoD == '0') ){
                        //Cambiar a regalado a codigo de activacion
                        //(numeroProceso,codigo,$request)
                        $codigoA     = $this->codigosRepository->procesoUpdateGestionBodega($tipoProceso,$codigoActivacion,$codigoDiagnostico,$request,$factura);
                        if($codigoA){  $contadorA++; $this->GuardarEnHistorico($usuarioQuemado,$institucion_id,$periodo_id,$codigoActivacion,$usuario_editor,$comentario,$old_valuesA,null); }
                        //Cambiar a regalado a codigo de diagnostico
                        $codigoB     = $this->codigosRepository->procesoUpdateGestionBodega($tipoProceso,$codigoDiagnostico,$codigoActivacion,$request,$factura);
                        if($codigoB){  $contadorD++; $this->GuardarEnHistorico($usuarioQuemado,$institucion_id,$periodo_id,$codigoDiagnostico,$usuario_editor,$comentario,$old_valuesD,null); }
                        }else{
                            $codigoConProblemas->push($validarD);
                        }
                    }else{
                        $codigoConProblemas->push($validarA);
                    }
                }
                //===== BLOQUEADO(No usan y no liquidan)=============
                if($tipoProceso == '3'){
                    if((    $ifLiquidadoA !='0' && $ifLiquidadoA !='4') && $ifBloqueadoA !=2 && (($codigo_unionA == $codigoDiagnostico) || ($codigo_unionA == null || $codigo_unionA == "" || $codigo_unionA == "0")) && ($ifliquidado_regaladoA == '0') ){
                        if(($ifLiquidadoD !='0' && $ifLiquidadoD !='4') && $ifBloqueadoD !=2 && (($codigo_unionD == $codigoActivacion) || ($codigo_unionD == null || $codigo_unionD == "" || $codigo_unionD == "0")) && ($ifliquidado_regaladoD == '0')  ){
                        //Cambiar a regalado a codigo de activacion
                        //(numeroProceso,codigo,$request)
                        $codigoA     = $this->codigosRepository->procesoUpdateGestionBodega($tipoProceso,$codigoActivacion,$codigoDiagnostico,$request,$factura);
                        if($codigoA){  $contadorA++; $this->GuardarEnHistorico($usuarioQuemado,$institucion_id,$periodo_id,$codigoActivacion,$usuario_editor,$comentario,$old_valuesA,null); }
                        //Cambiar a regalado a codigo de diagnostico
                        $codigoB     = $this->codigosRepository->procesoUpdateGestionBodega($tipoProceso,$codigoDiagnostico,$codigoActivacion,$request,$factura);
                        if($codigoB){  $contadorD++; $this->GuardarEnHistorico($usuarioQuemado,$institucion_id,$periodo_id,$codigoDiagnostico,$usuario_editor,$comentario,$old_valuesD,null); }
                        }else{
                            $codigoConProblemas->push($validarD);
                        }
                    }else{
                        $codigoConProblemas->push($validarA);
                    }
                }
                //==GUIA===
                if($tipoProceso == '4'){
                    if((  $ifLiquidadoA   =='1')   && $ifBcEstadoA == '1' && $ifBloqueadoA !=2 && (($codigo_unionA == $codigoDiagnostico) || ($codigo_unionA == null || $codigo_unionA == "" || $codigo_unionA == "0")) && ($ifliquidado_regaladoA == '0') ){
                        if(($ifLiquidadoD =='1')   && $ifBcEstadoD == '1' && $ifBloqueadoD !=2 && (($codigo_unionD == $codigoActivacion)  || ($codigo_unionD == null || $codigo_unionD == "" || $codigo_unionD == "0")) && ($ifliquidado_regaladoD == '0')  ){
                        //Cambiar a regalado a codigo de activacion
                        //(numeroProceso,codigo,$request)
                        $codigoA     = $this->codigosRepository->procesoUpdateGestionBodega($tipoProceso,$codigoActivacion,$codigoDiagnostico,$request,$factura);
                        if($codigoA){  $contadorA++; $this->GuardarEnHistorico($usuarioQuemado,$institucion_id,$periodo_id,$codigoActivacion,$usuario_editor,$comentario,$old_valuesA,null); }
                        //Cambiar a regalado a codigo de diagnostico
                        $codigoB     = $this->codigosRepository->procesoUpdateGestionBodega($tipoProceso,$codigoDiagnostico,$codigoActivacion,$request,$factura);
                        if($codigoB){  $contadorD++; $this->GuardarEnHistorico($usuarioQuemado,$institucion_id,$periodo_id,$codigoDiagnostico,$usuario_editor,$comentario,$old_valuesD,null); }
                        }else{
                            $codigoConProblemas->push($validarD);
                        }
                    }else{
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
        if(count($codigoConProblemas) == 0){
            return [
                "CodigosDiagnosticoNoexisten"      => $codigosNoExisten->all(),
                "codigoConProblemas"               => [],
                "contadorA"                        => $contadorA,
                "contadorD"                        => $contadorD,
            ];
        }else{
            return [
                "CodigosDiagnosticoNoexisten"      => $codigosNoExisten->all(),
                "codigoConProblemas"               => array_merge(...$codigoConProblemas->all()),
                "contadorA"                        => $contadorA,
                "contadorD"                        => $contadorD,
            ];
        }
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
        c.libro as book,c.serie,
        c.prueba_diagnostica,c.factura,c.codigo_union,
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
            when (c.estado_liquidacion = '4') then 'Código Guia'
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
        AND c.prueba_diagnostica = '0'
        ");
        return $tipoVenta;
     }
    //api:post/codigos/bloquear
    public function bloquearCodigos(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $codigos                = json_decode($request->data_codigos);
        $codigosNoCambiados     = [];
        $codigoLiquidados       = [];
        $codigoNoExiste         = [];
        $codigosSinCodigoUnion  = [];
        $porcentaje             = 0;
        $contador               = 0;
        $contadorNoExiste       = 0;
        $contadorNoCambiado     = 0;
        $id_usuario             = $request->id_usuario;
        $comentario             = $request->comentario;
        $traerPeriodo           = $request->periodo_id;
        $institucion            = $request->institucion_id;
        $usuarioQuemado         = 45017;
        foreach($codigos as $key => $item){
            //validar si el codigo existe
            $validar = $this->getCodigos($item->codigo,0);
            //valida que el codigo existe
            if(count($validar)>0){
                $ifLiquidado        = $validar[0]->estado_liquidacion;
                //validar si el codigo no este liquidado
                $ifBloqueado        = $validar[0]->estado;
                //validar si tiene codigo de union
                $codigo_union       = $validar[0]->codigo_union;
                //validar que el si es regalado no este liquidado
                $liquidado_regalado = $validar[0]->liquidado_regalado;
                if($ifLiquidado != '0' && $ifLiquidado != '4' && $ifBloqueado != 2 && $liquidado_regalado == '0'){
                    //VALIDAR CODIGOS QUE NO TENGA CODIGO UNION
                    $getcodigoPrimero = CodigosLibros::Where('codigo',$item->codigo)->get();
                    $getcodigoUnion   = CodigosLibros::Where('codigo',$codigo_union)->get();
                    if($codigo_union != null || $codigo_union != ""){
                        //PASAR A LEIDO CON CODIGO DE UNION
                       $ingreso = $this->pasarABloqueados($item->codigo,$codigo_union,$request,$getcodigoUnion);
                       //si ingresa correctamente
                        if($ingreso == 1){
                            $porcentaje++;
                            //====CODIGO====
                            //ingresar en el historico codigo
                            $this->GuardarEnHistorico($usuarioQuemado,$institucion,$traerPeriodo,$item->codigo,$id_usuario,$comentario,$getcodigoPrimero,null);
                            //====CODIGO UNION=====
                            $this->GuardarEnHistorico($usuarioQuemado,$institucion,$traerPeriodo,$codigo_union,$id_usuario,$comentario,$getcodigoUnion,null);
                        }else{
                            $codigosNoCambiados[$contadorNoCambiado] =[
                                "codigo"        => $item->codigo,
                                "mensaje"       => "Problema con el código union $codigo_union"
                            ];
                            $contadorNoCambiado++;
                        }
                    }
                    //ACTUALIZAR CODIGO SIN UNION
                    else{
                        $ingreso =  $this->pasarABloqueados($item->codigo,0,$request,null);
                        if($ingreso == 1){
                            $porcentaje++;
                            //ingresar en el historico
                            $this->GuardarEnHistorico($usuarioQuemado,$institucion,$traerPeriodo,$item->codigo,$id_usuario,$comentario,$getcodigoPrimero,null);
                            $codigosSinCodigoUnion[] = $validar[0];
                        }
                        else{
                            $codigosNoCambiados[$contadorNoCambiado] =[
                                "codigo"        => $item->codigo,
                                "mensaje"       => "0"
                            ];
                            $contadorNoCambiado++;
                        }
                    }
                }else{
                    $codigoLiquidados[$contador] = $validar[0];
                    $contador++;
                }
            }else{
                $codigoNoExiste[$contadorNoExiste] =[
                    "codigo" => $item->codigo
                ];
                $contadorNoExiste++;
            }
        }
        return [
            "cambiados"             => $porcentaje,
            "codigosNoCambiados"    => $codigosNoCambiados,
            "codigoLiquidados"      => $codigoLiquidados,
            "codigoNoExiste"        => $codigoNoExiste,
            "codigosSinCodigoUnion" => $codigosSinCodigoUnion,
        ];
    }
    public function pasarABloqueados($codigo,$codigo_union,$request,$objectCodigoUnion){
        $withCodigoUnion = 1;
        $estadoIngreso   = 0;
        $unionCorrecto   = false;
        ///estadoIngreso => 1 = ingresado; 2 = no se puedo ingresar el codigo de union;
        if($codigo_union == '0') $withCodigoUnion = 0;
        else                     $withCodigoUnion = 1;
        $arraySave  = [
            'estado'             => 2,
        ];
        //si hay codigo de union lo actualizo
        if($withCodigoUnion == 1){
            //VALIDO SI NO EXISTE EL CODIGO DE UNION LO MANDO COMO ERROR
            if(count($objectCodigoUnion) == 0){
                //no se ingreso
                return 2;
            }
            //VALIDO QUE EL CODIGO UNION CUMPLA LA VALIDACION
            $ifLiquidado        = $objectCodigoUnion[0]->estado_liquidacion;
            //validar si el codigo no este liquidado
            $ifBloqueado        = $objectCodigoUnion[0]->estado;
            //validar que el si es regalado no este liquidado
            $liquidado_regalado = $objectCodigoUnion[0]->liquidado_regalado;
            if($ifLiquidado != '0' && $ifLiquidado != '4' && $ifBloqueado != 2 && $liquidado_regalado == '0') $unionCorrecto = true;
            else $unionCorrecto = false;
            if($unionCorrecto){
                $codigoU = DB::table('codigoslibros')
                ->where('codigo', '=', $codigo_union)
                ->where('estado','<>', '2')
                ->where('estado_liquidacion','<>', '0')
                ->update($arraySave);
                //si el codigo de union se actualiza actualizo el codigo
                if($codigoU){
                    //actualizar el primer codigo
                    $codigo = DB::table('codigoslibros')
                    ->where('codigo', '=', $codigo)
                    ->where('estado','<>', '2')
                    ->where('estado_liquidacion','<>', '0')
                    ->update($arraySave);
                }
            }else{
                //no se ingreso
                return 2;
            }
        }else{
            //actualizar el primer codigo
            $codigo = DB::table('codigoslibros')
            ->where('codigo', '=', $codigo)
            ->where('estado','<>', '2')
            ->where('estado_liquidacion','<>', '0')
            ->update($arraySave);
        }
        //con codigo union
        ///estadoIngreso => 1 = ingresado; 2 = no se puedo ingresar el codigo de union;
        if($withCodigoUnion == 1){
            if($codigo && $codigoU)  $estadoIngreso = 1;
            else                     $estadoIngreso = 2;
        }
        //si no existe el codigo de union
        if($withCodigoUnion == 0){
            if($codigo)              $estadoIngreso = 1;
        }
        return $estadoIngreso;
    }
    //API:POST/codigos/eliminar
    public function eliminar(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $codigos                = json_decode($request->data_codigos);
        $codigosNoCambiados     = [];
        $codigoNoExiste         = [];
        $codigosConUsuario      = [];
        $codigosSinCodigoUnion  = [];
        $porcentaje             = 0;
        $contador               = 0;
        $contadorNoCambiado     = 0;
        $traerPeriodo           = $request->periodo_id;
        $id_usuario             = $request->id_usuario;
        $comentario             = "Se elimino el codigo";
        foreach($codigos as $key => $item){
            //validar si el codigo existe
            $validar = $this->getCodigos($item->codigo,0);
            //valida que el codigo existe
            if(count($validar)>0){
                //validar si el codigo tiene usuario y si ya esta liquidado
                $usuario            = $validar[0]->idusuario;
                $liquidado          = $validar[0]->estado_liquidacion;
                $liquidado_regalado = $validar[0]->liquidado_regalado;
                //validar si tiene codigo de union
                $codigo_union       = $validar[0]->codigo_union;
                if(($usuario == 0  || $usuario == null || $usuario == "null") && ($liquidado == '1' || $liquidado == '2' || $liquidado == '3') && $liquidado_regalado == '0'){
                    //VALIDAR CODIGOS QUE NO TENGA CODIGO UNION
                    $getcodigoPrimero = CodigosLibros::Where('codigo',$item->codigo)->get();
                    $getcodigoUnion   = CodigosLibros::Where('codigo',$codigo_union)->get();
                    if($codigo_union != null || $codigo_union != ""){
                        //PASAR A LEIDO CON CODIGO DE UNION
                        $eliminar = $this->pasarAEliminado($item->codigo,$codigo_union,$request,$getcodigoUnion);
                        //si elimina correctamente correctamente
                        if($eliminar == 1){
                            $porcentaje++;
                            //====CODIGO====
                            //ingresar en el historico codigo
                            $this->GuardarEnHistorico(0,0,$traerPeriodo,$item->codigo,$id_usuario,$comentario,$getcodigoPrimero,null);
                            //====CODIGO UNION=====
                            $this->GuardarEnHistorico(0,0,$traerPeriodo,$codigo_union,$id_usuario,$comentario,$getcodigoUnion,null);
                        }else{
                            $codigosNoCambiados[$contadorNoCambiado] =[
                                "codigo"        => $item->codigo,
                                "mensaje"       => "Problema con el código union $codigo_union"
                            ];
                            $contadorNoCambiado++;
                        }
                    }
                    //ACTUALIZAR CODIGO SIN UNION
                    else{
                        $ingreso =  $this->pasarAEliminado($item->codigo,0,$request,null);
                        if($ingreso == 1){
                            $porcentaje++;
                            //ingresar en el historico
                            $this->GuardarEnHistorico(0,0,$traerPeriodo,$item->codigo,$id_usuario,$comentario,$getcodigoPrimero,null);
                            $codigosSinCodigoUnion[] = $validar[0];
                        }
                        else{
                            $codigosNoCambiados[$contadorNoCambiado] =[
                                "codigo"        => $item->codigo,
                                "mensaje"       => "0"
                            ];
                            $contadorNoCambiado++;
                        }
                    }
                }else{
                    $codigosConUsuario[$contador] = $validar[0];
                    $contador++;
                 }
            }else{
                $codigoNoExiste[$key] =[
                    "codigo" => $item->codigo
                ];
            }
        }
        return [
            "eliminados"                => $porcentaje,
            "codigosNoCambiados"        => $codigosNoCambiados,
            "codigosConUsuario"         => $codigosConUsuario,
            "codigoNoExiste"            => $codigoNoExiste,
            "codigosSinCodigoUnion"     => $codigosSinCodigoUnion
        ];
    }
    public function pasarAEliminado($codigo,$codigo_union,$request,$objectCodigoUnion){
        $withCodigoUnion = 1;
        $estadoIngreso   = 0;
        $unionCorrecto   = false;
        ///estadoIngreso => 1 = ingresado; 2 = no se puedo ingresar el codigo de union;
        if($codigo_union == '0') $withCodigoUnion = 0;
        else                     $withCodigoUnion = 1;
        //si hay codigo de union lo actualizo
        if($withCodigoUnion == 1){
            //VALIDO SI NO EXISTE EL CODIGO DE UNION LO MANDO COMO ERROR
            if(count($objectCodigoUnion) == 0){
                //no se ingreso
                return 2;
            }
            //VALIDO QUE EL CODIGO UNION CUMPLA LA VALIDACION
            //que el codigo no tenga estudiante ni este liquidado
            $usuario            = $objectCodigoUnion[0]->idusuario;
            $liquidado          = $objectCodigoUnion[0]->estado_liquidacion;
            $liquidado_regalado = $objectCodigoUnion[0]->liquidado_regalado;
            if( ($usuario == 0  || $usuario == null || $usuario == "null") && ($liquidado == '1' || $liquidado == '2' || $liquidado == '3') && $liquidado_regalado == '0') $unionCorrecto = true;
            else $unionCorrecto = false;
            if($unionCorrecto){
                $codigoU = DB::table('codigoslibros')->where('codigo', '=', $codigo_union)->delete();
                //si el codigo de union se elimina paso a eliminar el codigo
                if($codigoU){
                    $codigoU = DB::table('codigoslibros')->where('codigo', '=', $codigo)->delete();
                }
            }else{
                //no se ingreso
                return 2;
            }
        }else{
            $codigoU = DB::table('codigoslibros')->where('codigo', '=', $codigo)->delete();
        }
        //con codigo union
        ///estadoIngreso => 1 = ingresado; 2 = no se puedo ingresar el codigo de union;
        if($withCodigoUnion == 1){
            if($codigo && $codigoU)  $estadoIngreso = 1;
            else                     $estadoIngreso = 2;
        }
        //si no existe el codigo de union
        if($withCodigoUnion == 0){
            if($codigo)              $estadoIngreso = 1;
        }
        return $estadoIngreso;
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
                            "factura"               => $validar[0]->factura,
                            "codigo_union"          => $validar[0]->codigo_union,
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
                        'bc_periodo' => $item->id_periodo
                    ]);
                    if($codigo){
                        $porcentaje++;
                        //ingresar en el historico
                        $historico                  = new HistoricoCodigos();
                        $historico->id_usuario      =  0;
                        $historico->codigo_libro    =  $item->codigo;
                        $historico->usuario_editor  = '';
                        $historico->idInstitucion   = $request->id_usuario;
                        $historico->id_periodo      = $item->id_periodo;
                        $historico->observacion     = $item->comentario;
                        $historico->save();
                    }else{
                        $codigosNoCambiados[$key] =[
                            "codigo" => $item->codigo
                        ];
                    }
                }else{
                    $codigosConUsuario[$contador] = [
                        "codigo"             => $item->codigo,
                        "prueba_diagnostica" => $validar[0]->prueba_diagnostica,
                        "tipoCodigo"         => $validar[0]->tipoCodigo,
                        "barrasEstado"       => $validar[0]->barrasEstado,
                        "codigoEstado"       => $validar[0]->codigoEstado,
                        "liquidacion"        => $validar[0]->liquidacion,
                        "ventaEstado"        => $validar[0]->ventaEstado,
                        "idusuario"          => $validar[0]->idusuario,
                        "estudiante"         => $validar[0]->estudiante,
                        "nombreInstitucion"  => $validar[0]->nombreInstitucion,
                        "institucionBarra"   => $validar[0]->institucionBarra,
                        "periodo"            => $validar[0]->periodo,
                        "periodo_barras"     => $validar[0]->periodo_barras,
                        "cedula"             => $validar[0]->cedula,
                        "email"              => $validar[0]->email,
                        "estado_liquidacion" => $validar[0]->estado_liquidacion,
                        "estado"             => $validar[0]->estado,
                        "status"             => $validar[0]->status,
                        "contador"           => $validar[0]->contador,
                        "porcentaje_descuento" => $validar[0]->porcentaje_descuento,
                        "factura"            => $validar[0]->factura,
                        "codigo_union"          => $validar[0]->codigo_union,
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
        $codigosNoCambiados     = [];
        $codigosConLiquidacion  = [];
        $codigoNoExiste         = [];
        $codigosSinCodigoUnion  = [];
        $porcentaje             = 0;
        $contador               = 0;
        $institucion_id         = $request->institucion_id;
        $fecha                  = date('Y-m-d H:i:s');
        $contadorNoCambiado     = 0;
        $contadorNoexiste       = 0;
        $mensaje                = $request->observacion;
        $setContrato            = null;
        $verificacion_liquidada = null;
        if($request->codigo){
            return $this->devolucionIndividualBodega($request->codigo,$request->id_usuario,$request->cliente,$request->institucion_id,$request->periodo_id,$request->observacion,$mensaje,$request);
        }
        foreach($codigos as $key => $item){
            //validar si el codigo existe
            $validar = $this->getCodigos($item->codigo,0);
            $ingreso = 0;
            //valida que el codigo existe
            if(count($validar)>0){
                $codigo_union               = $validar[0]->codigo_union;
                //validar si el codigo se encuentra liquidado
                $ifLiquidado                = $validar[0]->estado_liquidacion;
                //validar que el bc_institucion sea el mismo desde el front
                $ifBc_Institucion           = $validar[0]->bc_institucion;
                //institucion del venta lista
                $ifventa_lista_institucion  = $validar[0]->venta_lista_institucion;
                //contrato
                $ifContrato                 = $validar[0]->contrato;
                //numero de verificacion
                $ifVerificacion             = $validar[0]->verificacion;
                //para ver si es codigo regalado no este liquidado
                $ifliquidado_regalado       = $validar[0]->liquidado_regalado;
                //ADMINISTRADOR (SI PUEDE DEVOLVER AUNQUE LA INSTITUCION SEA DIFERENTE)
                $EstatusProceso             = false;
                if($request->dLiquidado ==  '1'){
                    $setContrato            = $ifContrato;
                    $verificacion_liquidada = $ifVerificacion;
                    //VALIDACION AUNQUE ESTE LIQUIDADO
                    if($ifLiquidado == '0' || $ifLiquidado == '1' || $ifLiquidado == '2' || $ifLiquidado == '4') $EstatusProceso = true;
                }else{
                    //VALIDACION QUE NO SEA LIQUIDADO
                    if(($ifLiquidado == '1' || $ifLiquidado == '2') && $ifliquidado_regalado == '0' ) $EstatusProceso = true;
                }
                //jorge dice que se quita esta validacion
                // if(($ifLiquidado == '1' || $ifLiquidado == '2') && ($ifBc_Institucion == $institucion_id || $ifventa_lista_institucion == $institucion_id )) $EstatusProceso = true;
                //SI CUMPLE LA VALIDACION
                if($EstatusProceso){
                    //VALIDAR CODIGOS QUE NO TENGA CODIGO UNION
                    $getcodigoPrimero = CodigosLibros::Where('codigo',$item->codigo)->get();
                    if($codigo_union != null || $codigo_union != ""){
                        //devolucion
                        $getcodigoUnion   = CodigosLibros::Where('codigo',$codigo_union)->get();
                        $ingreso =  $this->updateDevolucion($item->codigo,$codigo_union,$getcodigoUnion,$request);
                        //si ingresa correctamente
                        if($ingreso == 1){
                            $porcentaje++;
                            //====CODIGO====
                            //ingresar en el historico codigo
                            $this->GuardarEnHistorico(0,$request->institucion_id,$request->periodo_id,$item->codigo,$request->id_usuario,$mensaje,$getcodigoPrimero,null,$setContrato,$verificacion_liquidada);
                            //ingresar a la tabla de devolucion
                            $this->saveDevolucion($item->codigo,$request->cliente,$request->institucion_id,$request->periodo_id,$fecha,$request->observacion,$request->id_usuario);
                            //====CODIGO UNION=====
                            $this->GuardarEnHistorico(0,$request->institucion_id,$request->periodo_id,$codigo_union,$request->id_usuario,$mensaje,$getcodigoUnion,null,$setContrato,$verificacion_liquidada);
                            $this->saveDevolucion($codigo_union,$request->cliente,$request->institucion_id,$request->periodo_id,$fecha,$request->observacion,$request->id_usuario);
                        }else{
                            $codigosNoCambiados[$contadorNoCambiado] =[
                                "codigo"        => $item->codigo,
                                "mensaje"       => "Problema con el código union $codigo_union"
                            ];
                            $contadorNoCambiado++;
                        }
                    }
                    //ACTUALIZAR CODIGO SIN UNION
                    else{
                        $ingreso =  $this->updateDevolucion($item->codigo,0,null,$request);
                        if($ingreso == 1){
                            $porcentaje++;
                            //ingresar en el historico
                            $this->GuardarEnHistorico(0,$request->institucion_id,$request->periodo_id,$item->codigo,$request->id_usuario,$mensaje,$getcodigoPrimero,null,$setContrato,$verificacion_liquidada);
                            //ingresar a la tabla de devolucion
                            $this->saveDevolucion($item->codigo,$request->cliente,$request->institucion_id,$request->periodo_id,$fecha,$request->observacion,$request->id_usuario);
                            $codigosSinCodigoUnion[] = $validar[0];
                        }
                        else{
                            $codigosNoCambiados[$contadorNoCambiado] =[
                                "codigo"        => $item->codigo,
                                "mensaje"       => "0"
                            ];
                            $contadorNoCambiado++;
                        }
                    }
                }
                //SI NO CUMPLE LA VALIDACION
                else{
                    //admin
                    if($request->admin == "yes"){
                        $codigosConLiquidacion[] = $validar[0];
                        $contador++;
                    }
                    //bodega
                    else{
                        $mensaje_personalizado = "";
                        //mensaje personalizado front
                        if($ifLiquidado == 0){
                            $mensaje_personalizado = "Código liquidado";
                        }
                        if($ifLiquidado == 3){
                            $mensaje_personalizado = "Código  ya devuelto";
                        }
                        if($ifliquidado_regalado == '1'){
                            $mensaje_personalizado = "Código Regalado liquidado";
                        }
                        // if(($ifLiquidado == 1 || $ifLiquidado == 2) && ($ifBc_Institucion <> $institucion_id || $ifventa_lista_institucion <> $institucion_id)){
                        //     $mensaje_personalizado = "Código no pertenece a la institución que salio";
                        // }
                        $codigosConLiquidacion[$contador] = [
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
                            "contrato"              => $validar[0]->contrato,
                            "mensaje"               => $mensaje_personalizado,
                            "porcentaje_descuento"  => $validar[0]->porcentaje_descuento,
                            "factura"               => $validar[0]->factura,
                            "codigo_union"          => $validar[0]->codigo_union,
                        ];
                        $contador++;
                    }
                }
            }else{
                $codigoNoExiste[$contadorNoexiste] =[
                    "codigo"        => $item->codigo,
                ];
                $contadorNoexiste++;
            }
        }
        return [
            "cambiados"             => $porcentaje,
            "codigosNoCambiados"    => $codigosNoCambiados,
            "codigosConLiquidacion" => $codigosConLiquidacion,
            "codigoNoExiste"        => $codigoNoExiste,
            "codigosSinCodigoUnion" => $codigosSinCodigoUnion,
        ];
    }
    public function updateDevolucion($codigo,$codigo_union,$objectCodigoUnion,$request){
        $withCodigoUnion = 1;
        $estadoIngreso   = 0;
        $unionCorrecto   = false;
        ///estadoIngreso => 1 = ingresado; 2 = no se puedo ingresar el codigo de union;
        if($codigo_union == '0') $withCodigoUnion = 0;
        else                     $withCodigoUnion = 1;
        //si hay codigo de union lo actualizo
        if($withCodigoUnion == 1){
            //VALIDO SI NO EXISTE EL CODIGO DE UNION LO MANDO COMO ERROR
            if(count($objectCodigoUnion) == 0){
                //no se ingreso
                return 2;
            }
            //validar si el codigo se encuentra liquidado
            $ifLiquidado                = $objectCodigoUnion[0]->estado_liquidacion;
            //para ver si es codigo regalado no este liquidado
            $ifliquidado_regalado       = $objectCodigoUnion[0]->liquidado_regalado;
            if($request->dLiquidado ==  '1'){
                //VALIDACION AUNQUE ESTE LIQUIDADO
                if($ifLiquidado == '0' || $ifLiquidado == '1' || $ifLiquidado == '2' || $ifLiquidado == '4')    $unionCorrecto = true;
                else                                                                                            $unionCorrecto = false;
            }else{
                //VALIDACION QUE NO SEA LIQUIDADO
                if(($ifLiquidado == '1' || $ifLiquidado == '2') && $ifliquidado_regalado == '0')                $unionCorrecto = true;
                else                                                                                            $unionCorrecto = false;
            }
            //==PROCESO====
            if($unionCorrecto){
                $codigoU = DB::table('codigoslibros')
                ->where('codigo', '=', $codigo_union)
                ->update([
                    'estado_liquidacion'    => '3',
                    'bc_estado'             => '1',
                ]);
                //si el codigo de union se actualiza actualizo el codigo
                if($codigoU){
                    //actualizar el primer codigo
                    $codigo = DB::table('codigoslibros')
                    ->where('codigo', '=', $codigo)
                    ->update([
                        'estado_liquidacion'    => '3',
                        'bc_estado'             => '1',
                    ]);
                }
            }else{
                //no se ingreso
                return 2;
            }
        }else{
            //actualizar el primer codigo
            $codigo = DB::table('codigoslibros')
            ->where('codigo', '=', $codigo)
            ->update([
                'estado_liquidacion'    => '3',
                'bc_estado'             => '1',
            ]);
        }
        //con codigo union
        ///estadoIngreso => 1 = ingresado; 2 = no se puedo ingresar el codigo de union;
        if($withCodigoUnion == 1){
            if($codigo && $codigoU)  $estadoIngreso = 1;
            else                     $estadoIngreso = 2;
        }
        //si no existe el codigo de union
        if($withCodigoUnion == 0){
            if($codigo)              $estadoIngreso = 1;
        }
        return $estadoIngreso;
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

    public function devolucionIndividualBodega($getCodigo,$id_usuario,$cliente,$institucion_id,$periodo_id,$observacion,$mensaje,$request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $codigosNoCambiados     = [];
        $codigosConLiquidacion  = [];
        $codigoNoExiste         = [];
        $codigosSinCodigoUnion  = [];
        $porcentaje             = 0;
        $contador               = 0;
        $fecha                  = date('Y-m-d H:i:s');
        //validar si el codigo existe
        $validar = $this->getCodigos($getCodigo,0);
        //valida que el codigo existe
        if(count($validar)>0){
            //trae codigo union
            $codigo_union       = $validar[0]->codigo_union;
            //validar si el codigo no se encuentra liquidado
            $ifLiquidado        = $validar[0]->estado_liquidacion;
            //validar que el bc_institucion sea el mismo desde el front
            $ifBc_Institucion   = $validar[0]->bc_institucion;
            //institucion del venta lista
            $ifventa_lista_institucion = $validar[0]->venta_lista_institucion;
            $ifliquidado_regalado       = $validar[0]->liquidado_regalado;
            //ADMINISTRADOR (SI PUEDE DEVOLVER AUNQUE LA INSTITUCION SEA DIFERENTE)
            if(($ifLiquidado == '1' || $ifLiquidado == '2') && $ifliquidado_regalado == '0'){
            //SE QUITA ESTA VALIDACION
            // if(($ifLiquidado == '1' || $ifLiquidado == '2') && ($ifBc_Institucion == $institucion_id || $ifventa_lista_institucion == $institucion_id )){
                $getcodigoPrimero = CodigosLibros::Where('codigo',$getCodigo)->get();
                if($codigo_union != null || $codigo_union != ""){
                    //devolucion con codigo de union
                    $getcodigoUnion   = CodigosLibros::Where('codigo',$codigo_union)->get();
                    $ingreso =  $this->updateDevolucion($getCodigo,$codigo_union,$getcodigoUnion,$request);
                    if($ingreso == 1){
                        $porcentaje++;
                        //====CODIGO====
                        //ingresar en el historico codigo
                        $this->GuardarEnHistorico(0,$institucion_id,$periodo_id,$getCodigo,$id_usuario,$mensaje,$getcodigoPrimero,null);
                        //ingresar a la tabla de devolucion
                        $this->saveDevolucion($getCodigo,$cliente,$institucion_id,$periodo_id,$fecha,$observacion,$id_usuario);
                        //====CODIGO UNION=====
                        $this->GuardarEnHistorico(0,$institucion_id,$periodo_id,$codigo_union,$id_usuario,$mensaje,$getcodigoUnion,null);
                        $this->saveDevolucion($codigo_union,$cliente,$institucion_id,$periodo_id,$fecha,$observacion,$id_usuario);
                    }else{
                        $codigosNoCambiados[0] =[
                            "codigo"    	=> $getCodigo,
                            "mensaje"       => "Problema con el código union $codigo_union"
                        ];
                    }
                }
                //devolucion sin codigo de union
                else{
                    $ingreso =  $this->updateDevolucion($getCodigo,0,null,$request);
                    if($ingreso == 1){
                        $porcentaje++;
                        //ingresar en el historico
                        $this->GuardarEnHistorico(0,$institucion_id,$periodo_id,$getCodigo,$id_usuario,$observacion,$getcodigoPrimero,null);
                        //ingresar a la tabla de devolucion
                        $this->saveDevolucion($getCodigo,$cliente,$institucion_id,$periodo_id,$fecha,$observacion,$id_usuario);
                        $codigosSinCodigoUnion[] = $validar[0];
                    }
                    else{
                        $codigosNoCambiados[0] =[
                            "codigo"        => $getCodigo,
                            "mensaje"       => "0"
                        ];
                    }
                }
            }else{
                $mensaje_personalizado = "";
                //mensaje personalizado front
                if($ifLiquidado == 0){
                    $mensaje_personalizado = "Código liquidado";
                }
                if($ifLiquidado == 2){
                    if($ifliquidado_regalado == '1'){
                        $mensaje_personalizado = "Código  Regalado liquidado";
                    }
                }
                if($ifLiquidado == 3){
                    $mensaje_personalizado = "Código  ya devuelto";
                }
                if($ifLiquidado == 4){
                    $mensaje_personalizado = "Código Guia";
                }
                // if(($ifLiquidado == 1 || $ifLiquidado == 2) && ($ifBc_Institucion <> $institucion_id || $ifventa_lista_institucion <> $institucion_id)){
                //     $mensaje_personalizado = "Código no pertenece a la institución que salio";
                // }
                $codigosConLiquidacion[$contador] = [
                    "codigo"                => $getCodigo,
                    "prueba_diagnostica"    => $validar[0]->prueba_diagnostica,
                    "tipoCodigo"            => $validar[0]->tipoCodigo,
                    "liquidacion"           => $validar[0]->liquidacion,
                    "institucionBarra"      => $validar[0]->institucion_barras,
                    "periodo_barras"        => $validar[0]->periodo_barras,
                    "estado_liquidacion"    => $validar[0]->estado_liquidacion,
                    "prueba_diagnostica"    => $validar[0]->prueba_diagnostica,
                    "codigo_union"          => $validar[0]->codigo_union,
                    "mensaje"               => $mensaje_personalizado
                ];
                $contador++;
            }
        }else{
            $codigoNoExiste[0] =[
                "codigo" => $getCodigo
            ];
        }
        return [
            "cambiados"             => $porcentaje,
            "codigosNoCambiados"    => $codigosNoCambiados,
            "codigosConLiquidacion" => $codigosConLiquidacion,
            "codigoNoExiste"        => $codigoNoExiste,
            "codigosSinCodigoUnion" => $codigosSinCodigoUnion
        ];
    }
       //api:post//codigos/devolucion/activar
       public function ActivardevolucionCodigos(Request $request){
        //api:post//codigos/import/periodo
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $codigos = json_decode($request->data_codigos);
        $codigosNoCambiados     = [];
        $codigoSinDevolucion    = [];
        $codigoNoExiste         = [];
        $codigosSinCodigoUnion  = [];
        $porcentaje             = 0;
        $contador               = 0;
        $contadorNoExiste       = 0;
        $mensaje                = $request->observacion;
        $contadorNoCambiado     = 0;
        foreach($codigos as $key => $item){
            //validar si el codigo existe
            $validar = $this->getCodigos($item->codigo,0);
            //valida que el codigo existe
            if(count($validar)>0){
                //validar si el codigo se encuentra liquidado
                $ifDevuelto = $validar[0]->estado_liquidacion;
                //validar si tiene codigo de union
                $codigo_union   = $validar[0]->codigo_union;
                if($ifDevuelto == '3'){
                    //VALIDAR CODIGOS QUE NO TENGA CODIGO UNION
                    if($codigo_union != null || $codigo_union != ""){
                        $getcodigoPrimero = CodigosLibros::Where('codigo',$item->codigo)->get();
                        $getcodigoUnion   = CodigosLibros::Where('codigo',$codigo_union)->get();
                        //ACTIVACION CON CODIGO DE UNION
                        $ingreso =  $this->updateActivacion($item->codigo,$codigo_union,$getcodigoUnion);
                        //si ingresa correctamente
                        if($ingreso == 1){
                            $porcentaje++;
                            //====CODIGO====
                            //ingresar en el historico codigo
                            $this->GuardarEnHistorico(0,null,null,$item->codigo,$request->id_usuario,$mensaje,$getcodigoPrimero,null);
                            //====CODIGO UNION=====
                            $this->GuardarEnHistorico(0,null,null,$codigo_union,$request->id_usuario,$mensaje,$getcodigoUnion,null);
                        }else{
                            $codigosNoCambiados[$contadorNoCambiado] =[
                                "codigo"        => $item->codigo,
                                "mensaje"       => "Problema con el código union $codigo_union"
                            ];
                            $contadorNoCambiado++;
                        }
                    }
                    //ACTUALIZAR CODIGO SIN UNION
                    else{
                        $ingreso =  $this->updateActivacion($item->codigo,0,null);
                        if($ingreso == 1){
                            $porcentaje++;
                            //ingresar en el historico
                            $this->GuardarEnHistorico(0,null,null,$item->codigo,$request->id_usuario,$mensaje,null,null);
                            $codigosSinCodigoUnion[] = $validar[0];
                        }
                        else{
                            $codigosNoCambiados[$contadorNoCambiado] =[
                                "codigo"        => $item->codigo,
                                "mensaje"       => "0"
                            ];
                            $contadorNoCambiado++;
                        }
                    }
                }else{
                    $codigoSinDevolucion[] = $validar[0];
                    $contador++;
                }
            }else{
                $codigoNoExiste[$contadorNoExiste] =[
                    "codigo" => $item->codigo
                ];
                $contadorNoExiste++;
            }
        }
        return [
            "cambiados"             => $porcentaje,
            "codigosNoCambiados"    => $codigosNoCambiados,
            "codigoSinDevolucion"   => $codigoSinDevolucion,
            "codigoNoExiste"        => $codigoNoExiste,
            "codigosSinCodigoUnion" => $codigosSinCodigoUnion,
        ];
    }
    public function updateActivacion($codigo,$codigo_union,$objectCodigoUnion){
        $withCodigoUnion = 1;
        $estadoIngreso   = 0;
        ///estadoIngreso => 1 = ingresado; 2 = no se puedo ingresar el codigo de union;
        if($codigo_union == '0') $withCodigoUnion = 0;
        else                     $withCodigoUnion = 1;
        $arrayLimpiar  = [
            'idusuario'                 => "0",
            'id_periodo'                => "0",
            'id_institucion'            => '',
            'bc_estado'                 => '1',
            'estado'                    => '0',
            'estado_liquidacion'        => '1',
            'venta_estado'              => '0',
            'bc_periodo'                => '',
            'bc_institucion'            => '',
            'bc_fecha_ingreso'          => null,
            'contrato'                  => '',
            'verif1'                    => null,
            'verif2'                    => null,
            'verif3'                    => null,
            'verif4'                    => null,
            'verif5'                    => null,
            'verif6'                    => null,
            'verif7'                    => null,
            'verif8'                    => null,
            'verif9'                    => null,
            'verif10'                   => null,
            'venta_lista_institucion'   => '0',
            'porcentaje_descuento'      => '0',
            'factura'                   => null,
            'liquidado_regalado'        => '0'
        ];
        //si hay codigo de union lo actualizo
        if($withCodigoUnion == 1){
            //VALIDO SI NO EXISTE EL CODIGO DE UNION LO MANDO COMO ERROR
            if(count($objectCodigoUnion) == 0){
                //no se ingreso
                return 2;
            }
            $codigoU = DB::table('codigoslibros')
            ->where('codigo', '=', $codigo_union)
            ->where('estado_liquidacion',   '<>', '0')
            ->where('bc_estado',            '=', '1')
            ->update($arrayLimpiar);
            //si el codigo de union se actualiza actualizo el codigo
            if($codigoU){
                //actualizar el primer codigo
                $codigo = DB::table('codigoslibros')
                ->where('codigo', '=', $codigo)
                ->where('estado_liquidacion',   '<>', '0')
                ->where('bc_estado',            '=', '1')
                ->update($arrayLimpiar);
            }
        }else{
            //actualizar el primer codigo
            $codigo = DB::table('codigoslibros')
            ->where('codigo', '=', $codigo)
            ->where('estado_liquidacion',   '<>', '0')
            ->where('bc_estado',            '=', '1')
            ->update($arrayLimpiar);
        }
        //con codigo union
        ///estadoIngreso => 1 = ingresado; 2 = no se puedo ingresar el codigo de union;
        if($withCodigoUnion == 1){
            if($codigo && $codigoU)  $estadoIngreso = 1;
            else                     $estadoIngreso = 2;
        }
        //si no existe el codigo de union
        if($withCodigoUnion == 0){
            if($codigo)              $estadoIngreso = 1;
        }
        return $estadoIngreso;
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
    //api:post/codigos/ingreso
    public function importIngresoCodigos(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $codigos           = json_decode($request->data_codigos);
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
                $codigos_libros->prueba_diagnostica         = $item->diagnostica;
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
    public function revisarUltimoHistoricoCodigo(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $codigos           = json_decode($request->data_codigos);
        $datos             = [];
        $NoIngresados      = [];
        $porcentaje        = 0;
        $contadorNoExiste  = 0;
        foreach($codigos as $key => $item){
            // $consulta = $this->getCodigos($item->codigo,0);
            //si ya existe el codigo lo mando a un array
            // if(count($consulta) > 0){
                $query  = DB::SELECT("SELECT h.id_codlibros,h.codigo_libro,h.idInstitucion,
                h.usuario_editor,h.observacion,h.id_periodo ,
                 CONCAT(u.nombres,'', u.apellidos) AS editor ,
                i.nombreInstitucion
                FROM hist_codlibros h
                LEFT JOIN usuario u ON h.idInstitucion = u.idusuario
                LEFT JOIN institucion i ON h.usuario_editor  = i.idInstitucion
                WHERE h.codigo_libro = '$item->codigo'
                ORDER BY h.id_codlibros DESC
                LIMIT 1
                ");
                $porcentaje++;
               $datos[] = $query[0];
            // }else{
            //     $codigoNoExiste[$contadorNoExiste] =[
            //         "codigo" => $item->codigo
            //     ];
            //     $contadorNoExiste++;
            // }
        }
        $data = [
            "porcentaje"            => $porcentaje,
            "codigosHistorico"      => $datos,
            "CodigosNoIngresados"   => $NoIngresados,
        ];
        return $data;
    }
    //API:GET/procesosbodega
    public function procesosbodega(Request $request){
        //buscar factura like
        if($request->obtenerFacturasLike){
            $key = "obtenerFacturasLike".$request->factura;
            if (Cache::has($key)) {
            $query = Cache::get($key);
            } else {
                $query = $this->obtenerFacturasLike($request->factura);
                Cache::put($key,$query);
            }
            return $query;
        }
        //Buscar documento
        if($request->getCodigosXDocumento)    {
            $key = "searchDocumento".$request->factura;
            if (Cache::has($key)) {
            $query = Cache::get($key);
            } else {
                $query = $this->getCodigosXDocumento($request->factura);
                Cache::put($key,$query);
            }
            return $query;
        }
    }
}
