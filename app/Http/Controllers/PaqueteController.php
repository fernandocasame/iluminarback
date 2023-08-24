<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CodigosPaquete;
use Illuminate\Http\Request;
use DB;
use App\Traits\Codigos\TraitCodigosGeneral;
class PaqueteController extends Controller
{
    use TraitCodigosGeneral;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    //api/get/paquetes/paquetes
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
            $verificar_codigo = DB::SELECT("SELECT codigo from codigos_paquetes
            WHERE codigo = '$codigo_verificar'");
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
                $validar = DB::SELECT("SELECT codigo from codigos_paquetes WHERE codigo = '$codigo'");
                $cant_int = 0;
                $codigo_disponible = 1;
                while ( count($validar) > 0 ) {
                    // array_push($repetidos, $codigo);
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
