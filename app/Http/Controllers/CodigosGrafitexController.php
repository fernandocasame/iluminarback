<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CodigosLibros;
use App\Traits\Codigos\TraitCodigosGeneral;
use Illuminate\Http\Request;
use DB;

class CodigosGrafitexController extends Controller
{
    use TraitCodigosGeneral;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    //grafitex/codigos
    public function index()
    {
        return "xd";
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
        set_time_limit(600000);
        ini_set('max_execution_time', 600000);
        $codigos                    = json_decode($request->data_codigos);
        $contadorActivacion         = $request->contadorActivacion;
        $contadorDiagnostico        = $request->contadorDiagnostico;
        $codigosError               = [];
        $codigosGuardados           = [];
        $contadorError              = 0;
        $porcentajeA                = 0;
        $porcentajeD                = 0;
        $contadorUnion              = 0;
        $tipoCodigo                 = $request->tipoCodigo;
        foreach($codigos as $key => $item){
            $codigo_activacion      = "";
            $codigo_diagnostico     = "";
            $codigo_activacion      = $item->codigo_activacion;
            $codigo_diagnostico     = $item->codigo_diagnostico;
            $statusIngreso          = 0;
            $contadorCodigoA        = "";
            $contadorCodigoD        = "";
            //only activacion
            if($tipoCodigo == 1){
                $ingresoA               = $this->save_Codigos($request,$item,$codigo_activacion,$codigo_diagnostico,0,$contadorActivacion);
                $statusIngreso          = $ingresoA["contadorIngreso"];
                $contadorCodigoA        = $ingresoA["contador"];
            }
            //only activacion
            if($tipoCodigo == 2){
                $ingresoD               = $this->save_Codigos($request,$item,$codigo_diagnostico,$codigo_activacion,1,$contadorDiagnostico);
                $statusIngreso          = $ingresoD["contadorIngreso"];
                $contadorCodigoD        = $ingresoD["contador"];
            }
            //ambos
            if($tipoCodigo == 3){
                $ingresoA               = $this->save_Codigos($request,$item,$codigo_activacion,$codigo_diagnostico,0,$contadorActivacion);
                $ingresoD               = $this->save_Codigos($request,$item,$codigo_diagnostico,$codigo_activacion,1,$contadorDiagnostico);
                $contadorCodigoA        = $ingresoA["contador"];
                $contadorCodigoD        = $ingresoD["contador"];
                if($ingresoA["contadorIngreso"] == 1 && $ingresoD["contadorIngreso"] == 1)  $statusIngreso = 1;
                else                    $statusIngreso = 0;
            }
            //si ingresa el codigo de activacion y el codigo de diagnostico
            if($statusIngreso == 1){
                $contadorActivacion++;
                $contadorDiagnostico++;
                if($tipoCodigo == 1)  $porcentajeA++;
                if($tipoCodigo == 2)  $porcentajeD++;
                if($tipoCodigo == 3){
                    $porcentajeA++;
                    $porcentajeD++;
                }
                $codigosGuardados[$contadorUnion] = [
                    "codigo_activacion"  => $codigo_activacion,
                    "codigo_diagnostico" => $codigo_diagnostico,
                    "libro"              => $item->libro,
                    "anio"               => $item->anio,
                    "contadorCodigoA"    => $contadorCodigoA,
                    "contadorCodigoD"    => $contadorCodigoD,
                ];
                $contadorUnion++;
            }else{
                $codigosError[$contadorError] = [
                    "codigo_activacion"  => $codigo_activacion,
                    "codigo_diagnostico" => $codigo_diagnostico,
                    "message"            => "Problemas no se ingresaron bien"
                ];
                $contadorError++;
            }
        }
        return [
            "porcentajeA"           => $porcentajeA ,
            "porcentajeD"           => $porcentajeD ,
            "codigosNoIngresados"   => $codigosError,
            "codigosGuardados"      => $codigosGuardados,
        ];
    }
    public function save_Codigos($request,$item,$codigo,$codigo_union,$prueba_diagnostica,$contador){
        $contadorIngreso                            = 0;
        $codigos_libros                             = new CodigosLibros();
        $codigos_libros->serie                      = $item->serie;
        $codigos_libros->libro                      = $item->libro;
        $codigos_libros->anio                       = $item->anio;
        $codigos_libros->libro_idlibro              = $item->libro_idlibro;
        $codigos_libros->estado                     = 0;
        $codigos_libros->idusuario                  = 0;
        $codigos_libros->bc_estado                  = 1;
        $codigos_libros->idusuario_creador_codigo   = $request->user_created;
        $codigos_libros->prueba_diagnostica         = $prueba_diagnostica;
        $codigos_libros->codigo_union               = $codigo_union;
        $codigo_verificar                           = $codigo;
        $verificar_codigo = DB::SELECT("SELECT codigo from codigoslibros WHERE codigo = '$codigo_verificar'");
        if( $verificar_codigo ){
            $contadorIngreso = 0;
        }else{
            $codigos_libros->codigo = $codigo;
            $codigos_libros->contador = ++$contador;
            $codigos_libros->save();
            if($codigos_libros){
                $contadorIngreso = 1;
            }else{
                $contadorIngreso = 0;
            }
        }
        if($contadorIngreso == 1){
            return [
                "contadorIngreso" => $contadorIngreso,
                "contador"        => $codigos_libros->contador
            ];
        }else{
            return [
                "contadorIngreso" => $contadorIngreso,
                "contador"        => 0
            ];
        }

    }
    public function generarCodigosGrafitex(Request $request){
        $longitud               = $request->longitud;
        $codeA                  = $request->codeA;
        $codeD                  = $request->codeD;
        $cantidad               = $request->cantidad;
        $arregloCodigos         = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $codigo_activacion  =  $this->getCodigos($codeA,$longitud);
            $codigo_diagnostico =  $this->getCodigos($codeD,$longitud);
            $arregloCodigos[$i] = [
                "codigo_activacion"  => $codigo_activacion,
                "codigo_diagnostico" => $codigo_diagnostico
            ];
        }
        return ["codigos" => $arregloCodigos];
    }
    public function getCodigos($code,$longitud){
        $caracter   = $this->makeid($longitud);
        $codigos_validacion     = array();
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
            $validar = DB::SELECT("SELECT codigo from codigos_paquetes WHERE codigo = '$codigo'");
            $cant_int = 0;
            $codigo_disponible = 1;
            while ( count($validar) > 0 ) {
                $caracter = $this->makeid($longitud);
                $codigo = $code.$caracter;
                $validar = DB::SELECT("SELECT codigo from codigos_paquetes WHERE codigo = '$codigo'");
                $cant_int++;
                if( $cant_int == 10 ){
                    $codigo_disponible = 0;
                    $validar = ['repetido' => 'repetido'];
                }
            }
            if( $codigo_disponible == 1 ){
                return $codigo;
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
