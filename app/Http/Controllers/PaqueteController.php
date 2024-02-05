<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CodigoLibros;
use App\Models\CodigosLibros;
use App\Models\CodigosPaquete;
use App\Repositories\Codigos\PaquetesRepository;
use Illuminate\Http\Request;
use DB;
use App\Traits\Codigos\TraitCodigosGeneral;
class PaqueteController extends Controller
{
    use TraitCodigosGeneral;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Respons
     */
    private $paqueteRepository;
    public function __construct(PaquetesRepository $paqueteRepository) {
        $this->paqueteRepository = $paqueteRepository;
    }
    //api:get/paquetes/paquetes
    public function index(Request $request)
    {
        if($request->traerConfiguracionPaquete){
            return $this->traerConfiguracionPaquete();
        }
    }
    public function traerConfiguracionPaquete(){
        $query = DB::SELECT("SELECT * FROM codigos_configuracion");
        return $query;
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
    public function getPaquete($paquete){
        $query = DB::SELECT("SELECT * FROM codigos_paquetes p
        WHERE p.codigo = '$paquete'
        AND p.estado   = '1'
        ");
        return $query;
    }
    public function getExistsPaquete($paquete){
        $query = DB::SELECT("SELECT * FROM codigos_paquetes p
        WHERE p.codigo = '$paquete'
        ");
        return $query;
    }
    //paquetes/guadarPaquete
    public function guardarPaquete(Request $request){
        set_time_limit(600000);
        ini_set('max_execution_time', 600000);
        $miArrayDeObjetos               = json_decode($request->data_codigos);
        //variables
        $usuario_editor                 = $request->id_usuario;
        $institucion_id                 = 0;
        $periodo_id                     = $request->periodo_id;
        $arregloResumen                 = [];
        $contadorResumen                = 0;
        $codigoConProblemas             = collect();
        $arregloProblemaPaquetes        = [];
        $contadorErrPaquetes            = 0;
        //====PROCESO===================================
        foreach($miArrayDeObjetos as $key => $item){
            $problemasconCodigo         = [];
            $contadorProblemasCodigos   = 0;
            $ExistsPaquete              = [];
            $contadorA                  = 0;
            $contadorD                  = 0;
            $noExisteA                  = 0;
            $noExisteD                  = 0;
            //VALIDAR QUE EL CODIGO DE PAQUETE EXISTE
            $ExistsPaquete = $this->getPaquete($item->codigoPaquete);
            if(!empty($ExistsPaquete)){
                foreach($item->codigosHijos as $key2 => $tr){
                    $codigoActivacion       = $tr->codigoActivacion;
                    $codigoDiagnostico      = $tr->codigoDiagnostico;
                    $errorA                 = 1;
                    $errorD                 = 1;
                    $mensajeError           = "";
                    //validacion
                    $validarA               = $this->getCodigos($codigoActivacion,0);
                    $validarD               = $this->getCodigos($codigoDiagnostico,0);
                    $comentario             = "Se agrego al paquete ".$item->codigoPaquete;
                    //======si ambos codigos existen========
                    if(count($validarA) > 0 && count($validarD) > 0){
                        //====Activacion=====
                        //validar que el codigo de paquete sea nulo
                        $ifcodigo_paqueteA           = $validarA[0]->codigo_paquete;
                        //codigo de union
                        $codigo_unionA               = strtoupper($validarA[0]->codigo_union);
                        //liquidado regalado
                        //======Diagnostico=====
                        //validar que el codigo de paquete sea nulo
                        $ifcodigo_paqueteD           = $validarD[0]->codigo_paquete;
                        //codigo de union
                        $codigo_unionD               = strtoupper($validarD[0]->codigo_union);
                        //===VALIDACION====
                        //error 0 => no hay error; 1 hay error
                        if($ifcodigo_paqueteA == null && (($codigo_unionA == $codigoDiagnostico) || ($codigo_unionA  == null || $codigo_unionA == "" || $codigo_unionA == "0")) )    $errorA = 0;
                        if($ifcodigo_paqueteD == null && (($codigo_unionD == $codigoActivacion)  || ($codigo_unionD == null || $codigo_unionD == "" || $codigo_unionD == "0")) )     $errorD = 0;
                        //===MENSAJE VALIDACION====
                        if($errorA == 1 && $errorD == 0) { $mensajeError = "Problema con el código de activación";  $codigoConProblemas->push($validarA); }
                        if($errorA == 0 && $errorD == 1) { $mensajeError = "Problema con el código de diagnóstico"; $codigoConProblemas->push($validarD); }
                        if($errorA == 1 && $errorD == 1) { $mensajeError = "Ambos códigos tienen problemas";        $codigoConProblemas->push($validarA); $codigoConProblemas->push($validarD);}
                        //SI AMBOS CODIGOS PASAN LA VALIDACION GUARDO
                        if($errorA == 0 && $errorD == 0){
                            $old_valuesA    = CodigosLibros::Where('codigo',$codigoActivacion)->get();
                            $ingresoA       = $this->updatecodigosPaquete($item->codigoPaquete,$codigoActivacion,$codigoDiagnostico);
                            $old_valuesD    = CodigosLibros::findOrFail($codigoDiagnostico);
                            $ingresoD       = $this->updatecodigosPaquete($item->codigoPaquete,$codigoDiagnostico,$codigoActivacion);
                            //si se guarda codigo de activacion
                            if($ingresoA){ $contadorA++; $this->GuardarEnHistorico(0,$institucion_id,$periodo_id,$codigoActivacion,$usuario_editor,$comentario,$old_valuesA,null); }
                            //si se guarda codigo de diagnostico
                            if($ingresoD){ $contadorD++; $this->GuardarEnHistorico(0,$institucion_id,$periodo_id,$codigoDiagnostico,$usuario_editor,$comentario,$old_valuesD,null); }
                            //colocar el paquete como utilizado
                            $this->changeUsePaquete($ExistsPaquete[0]->codigo);
                        }else{
                            //SI NO INGRESA ALGUNO DE LOS CODIGOS ENVIO AL FRONT
                            $problemasconCodigo[$contadorProblemasCodigos] = [
                                "codigo"            => $codigoActivacion,
                                "codigoUnion"       => $codigoDiagnostico,
                                "problema"          => $mensajeError
                            ];
                            $contadorProblemasCodigos++;
                        }
                    }
                    //====SI NO EXISTEN LOS CODIGOS==============
                    else{
                        if(empty($validarA)  && !empty($validarD)) { $noExisteA++;               $mensajeError = "Código de activación no existe";  }
                        if(!empty($validarA) && empty($validarD))  { $noExisteD++;               $mensajeError = "Código de diagnóstico no existe"; }
                        if(empty($validarA)  && empty($validarD))  { $noExisteA++; $noExisteD++; $mensajeError = "Ambos códigos no existen"; }
                        $problemasconCodigo[$contadorProblemasCodigos] = [
                            "codigo"            => $codigoActivacion,
                            "codigoUnion"       => $codigoDiagnostico,
                            "problema"          => $mensajeError
                        ];
                        $contadorProblemasCodigos++;
                    }
                }
                //codigos resumen
                $arregloResumen[$contadorResumen] = [
                    "codigoPaquete"     => $item->codigoPaquete,
                    "codigosHijos"      => $problemasconCodigo,
                    "mensaje"           => empty($ExistsPaquete) ? 1 : '0',
                    "ingresoA"          => $contadorA,
                    "ingresoD"          => $contadorD,
                    "noExisteA"         => $noExisteA,
                    "noExisteD"         => $noExisteD
                ];
                $contadorResumen++;
            }else{
                $getProblemaPaquete = $this->getExistsPaquete($item->codigoPaquete);
                $arregloProblemaPaquetes [$contadorErrPaquetes] = [
                    "paquete"   => $item->codigoPaquete,
                    "problema" => count($getProblemaPaquete) > 0 ? 'Paquete utilizado':'Paquete no existe'
                ];
                $contadorErrPaquetes++;
            }
        }
        if(count($codigoConProblemas) == 0){
            return [
                "arregloResumen"                   => $arregloResumen,
                "codigoConProblemas"               => [],
                "arregloErroresPaquetes"           => $arregloProblemaPaquetes,
            ];
        }else{
            return [
                "arregloResumen"                   => $arregloResumen,
                "codigoConProblemas"               => array_merge(...$codigoConProblemas->all()),
                "arregloErroresPaquetes"           => $arregloProblemaPaquetes,
            ];
        }
    }
    public function guardarPaquete2(Request $request){
        set_time_limit(600000);
        ini_set('max_execution_time', 600000);
        $miArrayDeObjetos               = json_decode($request->data_codigos);
        //variables
        $usuario_editor                 = $request->id_usuario;
        $periodo_id                     = $request->periodo_id;
        $arregloProblemaPaquetes        = [];
        $arregloResumen                 = [];
        $codigoConProblemas             = collect();
        $contadorErrPaquetes            = 0;
        $contadorResumen                = 0;
        $institucion_id                 = 0;
        //====PROCESO===================================
        foreach($miArrayDeObjetos as $key => $item){
            //variables
            $problemasconCodigo         = [];
            $contadorProblemasCodigos   = 0;
            $contadorA                  = 0;
            $contadorB                  = 0;
            $noExisteA                  = 0;
            $noExisteB                  = 0;
            //VALIDAR QUE EL CODIGO DE PAQUETE EXISTE
            $ExistsPaquete = $this->getPaquete($item->codigoPaquete);
            if(!empty($ExistsPaquete)){
                foreach($item->codigosHijos as $key2 => $tr){
                    $codigoA                = $tr->codigo;
                    $errorA                 = 1;
                    $errorB                 = 1;
                    $comentario             = "Se agrego al paquete ".$item->codigoPaquete;
                    //validar si el codigo existe
                    $validarA               = CodigosLibros::Where('codigo',$codigoA)->get();
                    if(count($validarA) > 0){
                        $codigoB        =  $validarA[0]->codigo_union;
                        $validarB       = CodigosLibros::Where('codigo',$codigoB)->get();
                        if(count($validarB) > 0){
                            //VARIABLES  PARA EL PROCESO
                            //validar que el codigo de paquete sea nulo
                            $ifcodigo_paqueteA           = $validarA[0]->codigo_paquete;
                            //codigo de union
                            $codigo_unionA               = strtoupper($validarA[0]->codigo_union);
                            //======Diagnostico=====
                            //validar que el codigo de paquete sea nulo
                            $ifcodigo_paqueteB           = $validarB[0]->codigo_paquete;
                            //codigo de union
                            $codigo_unionB               = strtoupper($validarB[0]->codigo_union);
                            //===VALIDACION====validarB
                            //error 0 => no hay error; 1 hay error
                            if($ifcodigo_paqueteA == null && (($codigo_unionA == $codigoB) || ($codigo_unionA  == null || $codigo_unionA == "" || $codigo_unionA == "0")) )    $errorA = 0;
                            if($ifcodigo_paqueteB == null && (($codigo_unionB == $codigoA)  || ($codigo_unionB == null || $codigo_unionB == "" || $codigo_unionB == "0")) )    $errorB = 0;
                            //===MENSAJE VALIDACION====
                            if($errorA == 1 && $errorB == 0) { $mensajeError = "Problema con el código de activación";  $codigoConProblemas->push($validarA); }
                            if($errorA == 0 && $errorB == 1) { $mensajeError = "Problema con el código de diagnóstico"; $codigoConProblemas->push($validarB); }
                            if($errorA == 1 && $errorB == 1) { $mensajeError = "Ambos códigos tienen problemas";        $codigoConProblemas->push($validarA); $codigoConProblemas->push($validarB);}
                            //SI AMBOS CODIGOS PASAN LA VALIDACION GUARDO
                            if($errorA == 0 && $errorB == 0){
                                $old_valuesA    = $validarA;
                                $ingresoA       = $this->updatecodigosPaquete($item->codigoPaquete,$codigoA,$codigoB);
                                $old_valuesB    = $validarB;
                                $ingresoB       = $this->updatecodigosPaquete($item->codigoPaquete,$codigoB,$codigoA);
                                //si se guarda codigo de activacion
                                if($ingresoA){ $contadorA++; $this->GuardarEnHistorico(0,$institucion_id,$periodo_id,$codigoA,$usuario_editor,$comentario,$old_valuesA,null); }
                                //si se guarda codigo de diagnostico
                                if($ingresoB){ $contadorB++; $this->GuardarEnHistorico(0,$institucion_id,$periodo_id,$codigoB,$usuario_editor,$comentario,$old_valuesB,null); }
                                //colocar el paquete como utilizado
                                $this->changeUsePaquete($ExistsPaquete[0]->codigo);
                            }else{
                                //SI NO INGRESA ALGUNO DE LOS CODIGOS ENVIO AL FRONT
                                $problemasconCodigo[$contadorProblemasCodigos] = [
                                    "codigo"            => $codigoA,
                                    "codigoUnion"       => $codigoB,
                                    "problema"          => $mensajeError
                                ];
                                $contadorProblemasCodigos++;
                            }
                        }else{
                            $noExisteB++;
                            $mensajeError = "No existe el código de union";
                            $problemasconCodigo[$contadorProblemasCodigos] = [
                                "codigo"            => $codigoA,
                                "codigoUnion"       => $codigoB,
                                "problema"          => $mensajeError
                            ];
                            $contadorProblemasCodigos++;
                        }
                    }else{
                        $noExisteA++;
                        $mensajeError = "No existe el código";
                        $problemasconCodigo[$contadorProblemasCodigos] = [
                            "codigo"            => $codigoA,
                            "codigoUnion"       => "",
                            "problema"          => $mensajeError
                        ];
                        $contadorProblemasCodigos++;
                    }
                }
                //codigos resumen
                $arregloResumen[$contadorResumen] = [
                    "codigoPaquete"     => $item->codigoPaquete,
                    "codigosHijos"      => $problemasconCodigo,
                    "mensaje"           => empty($ExistsPaquete) ? 1 : '0',
                    "ingresoA"          => $contadorA,
                    "ingresoD"          => $contadorB,
                    "noExisteA"         => $noExisteA,
                    "noExisteD"         => $noExisteB
                ];
                $contadorResumen++;
            }else{
                $getProblemaPaquete = $this->getExistsPaquete($item->codigoPaquete);
                $arregloProblemaPaquetes [$contadorErrPaquetes] = [
                    "paquete"   => $item->codigoPaquete,
                    "problema"  => count($getProblemaPaquete) > 0 ? 'Paquete utilizado':'Paquete no existe'
                ];
                $contadorErrPaquetes++;
            }
        }
        if(count($codigoConProblemas) == 0){
            return [
                "arregloResumen"                   => $arregloResumen,
                "codigoConProblemas"               => [],
                "arregloErroresPaquetes"           => $arregloProblemaPaquetes,
            ];
        }else{
            $getProblemas = [];
            $arraySinCorchetes = array_map(function ($item) { return json_decode(json_encode($item)); }, $codigoConProblemas->all());
            // return reset($arreglo);
            $getProblemas =  array_merge(...$arraySinCorchetes);
            // $preArray = (array)$codigoConProblemas->all();
            return [
                "arregloResumen"                   => $arregloResumen,
                "codigoConProblemas"               => $getProblemas,
                "arregloErroresPaquetes"           => $arregloProblemaPaquetes,
            ];
        }
    }
    public function changeUsePaquete($codigo){
        $paq = CodigosPaquete::findOrFail($codigo);
        $paq->estado = "0";
        $paq->save();
    }
    public function updatecodigosPaquete($codigoPaquete,$codigo,$codigo_union){
        $fecha = date("Y-m-d H:i:s");
        $codigo = DB::table('codigoslibros')
            ->where('codigo', '=', $codigo)
            // ->where('estado_liquidacion', '=', '1')
            // ->where('estado', '<>', '2')
            // ->whereNull('codigo_paquete')
            //(SE QUITARA PARA AHORA EL ESTUDIANTE YA ENVIA LEIDO) ->where('bc_estado', '=', '1')
            ->update([
                'codigo_paquete'            => $codigoPaquete,
                'fecha_registro_paquete'    => $fecha,
                'codigo_union'              => $codigo_union
            ]);
        return $codigo;
    }
    public function importPaqueteGestion(Request $request){
        set_time_limit(600000);
        ini_set('max_execution_time', 600000);
        $miArrayDeObjetos           = json_decode($request->data_codigos);
        //variables
        $usuario_editor             = $request->id_usuario;
        $institucion_id             = 0;
        $periodo_id                 = $request->periodo_id;
        $arregloResumen             = [];
        $contadorResumen            = 0;
        $codigoConProblemas         = collect();
        $arregloProblemaPaquetes    = [];
        $contadorErrPaquetes        = 0;
        $tipoProceso                = $request->regalado;
        $factura                    = "";
        $obsevacion                 = $request->comentario;
        //====PROCESO===================================
        foreach($miArrayDeObjetos as $key => $item){
            $problemasconCodigo         = [];
            $contadorProblemasCodigos   = 0;
            $ExistsPaquete              = [];
            $contadorA                  = 0;
            $contadorD                  = 0;
            $noExisteA                  = 0;
            $noExisteD                  = 0;
            //estadoPaquete => 0 utilizado; 1 => abierto;
            $estadoPaquete              = 0;
            //VALIDAR QUE EL CODIGO DE PAQUETE EXISTE
            $ExistsPaquete      = $this->getPaquete($item->codigoPaquete);
            if(empty($ExistsPaquete)) { $estadoPaquete = 0; }
            $getExistsPaquete   = $this->getExistsPaquete($item->codigoPaquete);
            if(!empty($getExistsPaquete)){
                foreach($item->codigosHijos as $key2 => $tr){
                    $codigoActivacion       = strtoupper($tr->codigoActivacion);
                    $codigoDiagnostico      = strtoupper($tr->codigoDiagnostico);
                    $errorA                 = 1;
                    $errorD                 = 1;
                    $mensajeError           = "";
                    //validacion
                    $validarA               = $this->getCodigos($codigoActivacion,0);
                    $validarD               = $this->getCodigos($codigoDiagnostico,0);
                    $comentario             = "Se agrego al paquete ".$item->codigoPaquete . " - " .$obsevacion;
                    //======si ambos codigos existen========
                    if(count($validarA) > 0 && count($validarD) > 0){
                        $validate = $this->paqueteRepository->validateGestion($tipoProceso,$estadoPaquete,$validarA,$validarD,$item,$codigoActivacion,$codigoDiagnostico,$request);
                        $errorA = $validate["errorA"]; $errorD = $validate["errorD"]; $factura = $validate["factura"];
                        //===MENSAJE VALIDACION====
                        if($errorA == 1 && $errorD == 0) { $mensajeError = "Problema con el código de activación";  $codigoConProblemas->push($validarA); }
                        if($errorA == 0 && $errorD == 1) { $mensajeError = "Problema con el código de diagnóstico"; $codigoConProblemas->push($validarD); }
                        if($errorA == 1 && $errorD == 1) { $mensajeError = "Ambos códigos tienen problemas";        $codigoConProblemas->push($validarA); $codigoConProblemas->push($validarD);}
                        //SI AMBOS CODIGOS PASAN LA VALIDACION GUARDO
                        if($errorA == 0 && $errorD == 0){
                            $old_valuesA    = CodigosLibros::Where('codigo',$codigoActivacion)->get();
                            $old_valuesD    = CodigosLibros::findOrFail($codigoDiagnostico);
                            //tipoProceso => 0 = Usan y liquidan; 1 =  regalado; 2 = regalado y bloqueado ; 3 = bloqueado
                            $ingreso = $this->paqueteRepository->procesoGestionBodega($tipoProceso,$codigoActivacion,$codigoDiagnostico,$request,$factura,$item->codigoPaquete);
                            //si se guarda codigo de activacion
                            if($ingreso == 1){
                                $contadorA++;
                                $contadorD++;
                                //====CODIGO====
                                //ingresar en el historico codigo
                                $this->GuardarEnHistorico(0,$institucion_id,$periodo_id,$codigoActivacion,$usuario_editor,$comentario,$old_valuesA,null);
                                 //====CODIGO UNION=====
                                $this->GuardarEnHistorico(0,$institucion_id,$periodo_id,$codigoDiagnostico,$usuario_editor,$comentario,$old_valuesD,null);
                                //colocar el paquete como utilizado
                                $this->changeUsePaquete($getExistsPaquete[0]->codigo);
                            }else{
                                //SI NO INGRESA ALGUNO DE LOS CODIGOS ENVIO AL FRONT
                                $problemasconCodigo[$contadorProblemasCodigos] = [
                                    "codigoActivacion"  => $codigoActivacion,
                                    "codigoDiagnostico" => $codigoDiagnostico,
                                    "problema"          => "No se pudo guardar"
                                ];
                                $contadorProblemasCodigos++;
                            }
                        }else{
                            //SI NO INGRESA ALGUNO DE LOS CODIGOS ENVIO AL FRONT
                            $problemasconCodigo[$contadorProblemasCodigos] = [
                                "codigoActivacion"  => $codigoActivacion,
                                "codigoDiagnostico" => $codigoDiagnostico,
                                "problema"          => $mensajeError
                            ];
                            $contadorProblemasCodigos++;
                        }
                    }
                    //====SI NO EXISTEN LOS CODIGOS==============
                    else{
                        if(empty($validarA)  && !empty($validarD)) { $noExisteA++;               $mensajeError = "Código de activación no existe";  }
                        if(!empty($validarA) && empty($validarD))  { $noExisteD++;               $mensajeError = "Código de diagnóstico no existe"; }
                        if(empty($validarA)  && empty($validarD))  { $noExisteA++; $noExisteD++; $mensajeError = "Ambos códigos no existen"; }
                        $problemasconCodigo[$contadorProblemasCodigos] = [
                            "codigoActivacion"  => $codigoActivacion,
                            "codigoDiagnostico" => $codigoDiagnostico,
                            "problema"          => $mensajeError
                        ];
                        $contadorProblemasCodigos++;
                    }
                }
                //codigos resumen
                $arregloResumen[$contadorResumen] = [
                    "codigoPaquete"     => $item->codigoPaquete,
                    "codigosHijos"      => $problemasconCodigo,
                    "mensaje"           => empty($getExistsPaquete) ? 1 : '0',
                    "ingresoA"          => $contadorA,
                    "ingresoD"          => $contadorD,
                    "noExisteA"         => $noExisteA,
                    "noExisteD"         => $noExisteD
                ];
                $contadorResumen++;
            }else{
                $getProblemaPaquete = $this->getExistsPaquete($item->codigoPaquete);
                $arregloProblemaPaquetes [$contadorErrPaquetes] = [
                    "paquete"   => $item->codigoPaquete,
                    "problema" => count($getProblemaPaquete) > 0 ? 'Paquete utilizado':'Paquete no existe'
                ];
                $contadorErrPaquetes++;
            }
        }
        if(count($codigoConProblemas) == 0){
            return [
                "arregloResumen"                   => $arregloResumen,
                "codigoConProblemas"               => [],
                "arregloErroresPaquetes"           => $arregloProblemaPaquetes,
            ];
        }else{
            return [
                "arregloResumen"                   => $arregloResumen,
                "codigoConProblemas"               => array_merge(...$codigoConProblemas->all()),
                "arregloErroresPaquetes"           => $arregloProblemaPaquetes,
            ];
        }
    }

    public function store(Request $request)
    {
        set_time_limit(600000);
        ini_set('max_execution_time', 600000);
        $codigos                            = explode(",", $request->codigo);
        $porcentajeAnterior                 = 0;
        $codigosNoIngresadosAnterior        = [];
        //only codigos
        $resultado                          = $this->save_Codigos($request,$codigos);
        $porcentajeAnterior                 = $resultado["porcentaje"];
        $codigosNoIngresadosAnterior        = $resultado["codigosNoIngresados"];
        $codigosGuardados                   = $resultado["codigosGuardados"];
        return[
            "porcentajeAnterior"            => $porcentajeAnterior,
            "codigosNoIngresadosAnterior"   => $codigosNoIngresadosAnterior,
            "codigosGuardados"              => $codigosGuardados,
        ];
    }
    public function save_Codigos($request,$codigos){
        $tam                = sizeof($codigos);
        $porcentaje         = 0;
        $codigosError       = [];
        $codigosGuardados   = [];
        $contador           = 0;
        for( $i=0; $i<$tam; $i++ ){
            $codigos_libros                             = new CodigosPaquete();
            $codigos_libros->user_created               = $request->user_created;
            $codigo_verificar                           = $codigos[$i];
            $verificar_codigo  = $this->getExistsPaquete($codigo_verificar);
            if( count($verificar_codigo) > 0 ){
                $codigosError[$contador] = [
                    "codigo" =>  $codigo_verificar
                ];
                $contador++;
            }else{
                $codigos_libros->codigo = $codigos[$i];
                $codigos_libros->save();
                $codigosGuardados[$porcentaje] = [
                    "codigo" =>  $codigos[$i]
                ];
                $porcentaje++;
            }
        }
        return ["porcentaje" =>$porcentaje ,"codigosNoIngresados" => $codigosError,"codigosGuardados" => $codigosGuardados] ;
    }
    public function generarCodigosPaquete(Request $request){
        $resp_search            = array();
        $codigos_validacion     = array();
        $longitud               = $request->longitud;
        $code                   = $request->code;
        $cantidad               = $request->cantidad;
        $codigos = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $caracter   = $this->makeid($longitud);
            $codigo     = $code.$caracter;
            // valida repetidos en generacion
            $valida_gen = 1;
            $cant_int   = 0;
            while ( $valida_gen == 1 ) {
                $caracter = $this->makeid($longitud);
                $codigo = $code.$caracter;
                $valida_gen = 0;
                for( $k=0; $k<count($codigos_validacion); $k++ ){
                    if( $codigo == $codigos_validacion[$k] ){
                        array_push($resp_search, $codigo);
                        $valida_gen = 1;
                        break;
                    }
                }
                $cant_int++;
                if( $cant_int == 10 ){
                    $codigo = "no_disponible";
                    $valida_gen = 0;
                }
            }
            if( $codigo != 'no_disponible' ){
                // valida repetidos en DB
                $validar  = $this->getExistsPaquete($codigo);
                $cant_int = 0;
                $codigo_disponible = 1;
                while ( count($validar) > 0 ) {
                    // array_push($repetidos, $codigo);
                    $caracter = $this->makeid($longitud);
                    $codigo = $code.$caracter;
                    $validar  = $this->getExistsPaquete($codigo);
                    $cant_int++;
                    if( $cant_int == 10 ){
                        $codigo_disponible = 0;
                        $validar = ['repetido' => 'repetido'];
                    }
                }
                if( $codigo_disponible == 1 ){
                    array_push($codigos_validacion, $codigo);
                    array_push($codigos, ["codigo" => $codigo]);
                }
            }
        }
        return ["codigos" => $codigos, "repetidos" => $resp_search];
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    //api/get/paquetes/paquetes/id
    public function show($paquete)
    {
        $query = DB::SELECT("SELECT pq.*,
        CONCAT(u.nombres, ' ', u.apellidos) as editor
        FROM codigos_paquetes pq
        LEFT JOIN usuario u ON pq.user_created = u.idusuario
        WHERE pq.codigo LIKE '%$paquete%'
        ");
        $datos = [];
        foreach($query as $key => $item){
            $codigosPaquetes = [];
            $codigosPaquetes = $this->getCodigosXPaquete($item->codigo);
            $datos[$key] = [
                "paquete"       => $item->codigo,
                "editor"        => $item->editor,
                "user_created"  => $item->user_created,
                "estado"        => $item->estado,
                "created_at"    => $item->created_at,
                "codigos"       => $codigosPaquetes
            ];
        }
        return $datos;
    }
    public function getCodigosXPaquete($paquete){
        $query = DB::SELECT("SELECT codigo,libro FROM codigoslibros c
        WHERE c.codigo_paquete = '$paquete'
        ");
        return $query;
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
    public function PaqueteModificar(Request $request){
      if($request->cleanPaquete) { return $this->cleanPaquete($request); }
      if($request->eliminarPaquete) { return $this->eliminarPaquete($request); }
    }
    public function cleanPaquete($request){
        codigoslibros::where('codigo_paquete',$request->paquete)
        ->update([
            'codigo_paquete'            => null,
            'fecha_registro_paquete'    => null,
        ]);
        //dejamos el paquete en estado abierto
        $codigoPaquete = CodigosPaquete::Where('codigo',$request->paquete)
        ->update([
            'estado' => '1'
        ]);
        return ["status" => "1", "message" => "Se limpio el paquete"];
    }
    public function eliminarPaquete($request){
        $codigo = CodigosPaquete::findOrFail($request->paquete);
        if($codigo->estado == 0){
            return ["status" => "1", "message" => "No se puede eliminar el paquete, ya fue utilizado"];
        }else{
            $codigo->delete();
            return ["status" => "1", "message" => "Se elimino el paquete"];
        }
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
