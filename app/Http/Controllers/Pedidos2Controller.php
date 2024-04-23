<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Models\Pedidos\PedidosDocumentosLiq;
use App\Repositories\pedidos\PedidosRepository;
use App\Traits\Pedidos\TraitPedidosGeneral;
use DB;
use Illuminate\Http\Request;

class Pedidos2Controller extends Controller
{
    use TraitPedidosGeneral;
    protected $pedidosRepository = null;
    public function __construct(PedidosRepository $pedidosRepository)
    {
        $this->pedidosRepository = $pedidosRepository;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    //pedidos2/pedidos
    public function index(Request $request)
    {
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        if($request->getLibrosFormato)              { return $this->getLibrosFormato($request->periodo_id); }
        if($request->geAllLibrosxAsesor)            { return $this->geAllLibrosxAsesor($request->asesor_id,$request->periodo_id); }
        //API:GET/pedidos2/pedidos?getValoresLibrosContratos
        // if($request->getValoresLibrosContratos)    { return $this->getValoresLibrosContratos($request->asesor_id,$request->periodo_id,$request); }
        //api:get/pedidos2/pedidos?getAsesoresPedidos=1
        if($request->getAsesoresPedidos)            { return $this->getAsesoresPedidos(); }
    }
    //API:GET/pedidos2/pedidos?getLibrosFormato=yes&periodo_id=22
    /**
     * Get the libros formato for a given periodo.
     *
     * @param  string  $periodo
     * @return \Illuminate\Support\Collection
     */
    public function getLibrosFormato($periodo){
        $librosNormales = [];
        $librosPlan     = [];
        $resultado      = [];
        $librosNormales = $this->pedidosRepository->getLibrosNormalesFormato($periodo);
        $librosPlan     = $this->pedidosRepository->getLibrosPlanLectorFormato($periodo);
        //unir los dos arreglos
        $resultado      = array_merge(array($librosNormales),array($librosPlan));
        $coleccion      = collect($resultado)->flatten(10);
        return $coleccion;
    }
    //API:GET/pedidos2/pedidos?geAllLibrosxAsesor=yes&asesor_id=4179&periodo_id=22
    public function geAllLibrosxAsesor($asesor_id,$periodo_id){
        $val_pedido = DB::SELECT("SELECT DISTINCT
        pv.id_area, pv.tipo_val, pv.id_serie, pv.year,pv.plan_lector,pv.alcance,
        p.id_periodo,
        CONCAT(se.nombre_serie,' ',ar.nombrearea) as serieArea,
        se.nombre_serie
        FROM pedidos_val_area pv
        LEFT JOIN area ar ON  pv.id_area = ar.idarea
        LEFT JOIN series se ON pv.id_serie = se.id_serie
        LEFT JOIN pedidos p ON pv.id_pedido = p.id_pedido
        LEFT JOIN usuario u ON p.id_asesor = u.idusuario
        WHERE p.id_asesor = '$asesor_id'
        AND p.id_periodo  = '$periodo_id'
        AND p.tipo        = '0'
        AND p.estado      = '1'
        GROUP BY pv.id
        ");
        if(empty($val_pedido)){
            return $val_pedido;
        }
        $arreglo = [];
        $cont    = 0;
        //obtener solo los alcances activos
        foreach($val_pedido as $k => $tr){
            //Cuando es el pedido original
            $alcance_id = 0;
            $alcance_id = $tr->alcance;
            if($alcance_id == 0){
                $arreglo[$cont] =   (object)[
                    "id_area"           => $tr->id_area,
                    "tipo_val"          => $tr->tipo_val,
                    "id_serie"          => $tr->id_serie,
                    "year"              => $tr->year,
                    "plan_lector"       => $tr->plan_lector,
                    "id_periodo"        => $tr->id_periodo,
                    "serieArea"         => $tr->serieArea,
                    "nombre_serie"      => $tr->nombre_serie,
                    "alcance"           => $tr->alcance,
                    "alcance"           => $alcance_id
                ];
            }else{
                //validate que el alcance este cerrado o aprobado
                $query = $this->getAlcanceAbiertoXId($alcance_id);
                if(count($query) > 0){
                    $arreglo[$cont] = (object) [
                        "id_area"           => $tr->id_area,
                        "tipo_val"          => $tr->tipo_val,
                        "id_serie"          => $tr->id_serie,
                        "year"              => $tr->year,
                        "plan_lector"       => $tr->plan_lector,
                        "id_periodo"        => $tr->id_periodo,
                        "serieArea"         => $tr->serieArea,
                        "nombre_serie"      => $tr->nombre_serie,
                        "alcance"           => $tr->alcance,
                        "alcance"           => $alcance_id
                    ];
                }
            }
            $cont++;
        }
        //mostrar el arreglo bien
        $renderSet = [];
        $renderSet = array_values($arreglo);
        if(count($renderSet) == 0){
            return $renderSet;
        }
        $datos = [];
        $contador = 0;
        //return $renderSet;
        foreach($renderSet as $key => $item){
            $valores = [];
            //plan lector
            if($item->plan_lector > 0 ){
                $getPlanlector = DB::SELECT("SELECT l.nombrelibro,l.idlibro,
                (
                    SELECT f.pvp AS precio
                    FROM pedidos_formato f
                    WHERE f.id_serie = '6'
                    AND f.id_area = '69'
                    AND f.id_libro = '$item->plan_lector'
                    AND f.id_periodo = '$item->id_periodo'
                )as precio, ls.codigo_liquidacion,ls.version,ls.year
                FROM libro l
                left join libros_series ls  on ls.idLibro = l.idlibro
                WHERE l.idlibro = '$item->plan_lector'
                ");
                $valores = $getPlanlector;
            }else{
                $getLibros = DB::SELECT("SELECT ls.*, l.nombrelibro, l.idlibro,
                (
                    SELECT f.pvp AS precio
                    FROM pedidos_formato f
                    WHERE f.id_serie = ls.id_serie
                    AND f.id_area = a.area_idarea
                    AND f.id_periodo = '$item->id_periodo'
                )as precio
                FROM libros_series ls
                LEFT JOIN libro l ON ls.idLibro = l.idlibro
                LEFT JOIN asignatura a ON l.asignatura_idasignatura = a.idasignatura
                WHERE ls.id_serie = '$item->id_serie'
                AND a.area_idarea  = '$item->id_area'
                AND l.Estado_idEstado = '1'
                AND a.estado = '1'
                AND ls.year = '$item->year'
                LIMIT 1
                ");
                $valores = $getLibros;
            }
            $datos[$contador] = (Object)[
                "id_area"           => $item->id_area,
                // "tipo_val"          => $item->tipo_val,
                "id_serie"          => $item->id_serie,
                // "year"              => $item->year,
                // "anio"              => $valores[0]->year,
                // "version"           => $valores[0]->version,
                // "plan_lector"       => $item->plan_lector,
                "serieArea"         => $item->id_serie == 6 ? $item->nombre_serie." ".$valores[0]->nombrelibro : $item->serieArea,
                "libro_id"          => $valores[0]->idlibro,
                "nombrelibro"       => $valores[0]->nombrelibro,
                "nombre_libro"      => $valores[0]->nombrelibro,
                "precio"            => $valores[0]->precio,
                "codigo"            => $valores[0]->codigo_liquidacion,
            ];
            $contador++;
        }
        //array unicos con array unique
        $resultado  = [];
        $resultado  = array_unique($datos, SORT_REGULAR);
        $coleccion  = collect($resultado);
        return $coleccion->values();
    }
    public function getAlcanceAbiertoXId($id){
        $query = DB::SELECT("SELECT * FROM pedidos_alcance a
        WHERE a.id = '$id'
        AND a.estado_alcance = '1'");
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
    //API:POST/pedidos2/pedidos
    public function store(Request $request)
    {
        if($request->getValoresLibrosContratos){
            return $this->getValoresLibrosContratos($request->asesor_id,$request->periodo_id,$request);
        }
    }
      //API:POST/pedidos2/pedidos?getValoresLibrosContratos
      public function getValoresLibrosContratos($asesor_id,$periodo_id,$request){
        $arrayLibros = [];
        $arrayLibros = json_decode($request->arrayLibros);
        // $arrayLibros = $this->geAllLibrosxAsesor($asesor_id,$periodo_id);
        $query = DB::SELECT("SELECT p.id_pedido, p.contrato_generado,
            i.nombreInstitucion, c.nombre as ciudad
            FROM pedidos p
            LEFT JOIN institucion i ON p.id_institucion = i.idInstitucion
            LEFT JOIN ciudad c ON c.idciudad = i.ciudad_id
            WHERE p.id_asesor = ?
            AND p.estado = '1'
            AND p.tipo = '0'
            AND p.contrato_generado IS NOT NULL
            AND p.id_periodo = ?
        ",[$asesor_id,$periodo_id]);
        $datos = [];
        foreach($query as $key => $item){
            $validate               = [];
            $validate               = $this->obtenerValores($arrayLibros,$item->id_pedido);
            $datos[$key] = [
                'id_pedido'         => $item->id_pedido,
                'contrato_generado' => $item->contrato_generado,
                'nombreInstitucion' => $item->nombreInstitucion,
                'ciudad'            => $item->ciudad,
                'librosFormato'     => $validate,
            ];
        }
         return $datos;
    }
    public function obtenerValores($arrayLibros,$id_pedido){
        $validate               = [];
        $libroSolicitados       = [];
        $libroSolicitados       = $this->pedidosRepository->obtenerLibroxPedidoTodo($id_pedido);
        foreach($arrayLibros as $key =>  $item){
            $validate[$key] = $this->validarIfExistsLibro($item,$libroSolicitados);
        }
        return $validate;
    }
    public function validarIfExistsLibro($Objectlibro,$libroSolicitados){
        //buscar el idLibro en el array de libros solicitados
        $resultado  = [];
        $coleccion  = collect($libroSolicitados);
        $libro = $coleccion->where('idlibro',$Objectlibro->libro_id)->first();
        if($libro){
            $resultado = [
                'libro_id'      => $Objectlibro->libro_id,
                'nombrelibro'   => $Objectlibro->nombrelibro,
                'valor'         => $libro->valor,
                "codigo"        => $Objectlibro->codigo,
                "precio"        => $Objectlibro->precio,
            ];
        }
        else{
            $resultado = [
                'libro_id'      => $Objectlibro->libro_id,
                'nombrelibro'   => $Objectlibro->nombrelibro,
                'valor'         => "",
                "codigo"        => $Objectlibro->codigo,
                "precio"        => $Objectlibro->precio,
            ];
        }
        return $resultado;
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
