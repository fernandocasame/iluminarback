<?php
namespace App\Repositories\Facturacion;

use App\Models\CodigosLibrosDevolucionHeader;
use App\Models\CodigosLibrosDevolucionSon;
use App\Models\CodigosLibrosDevolucionSonFacturador;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;
class  DevolucionRepository extends BaseRepository
{
    public function __construct(CodigosLibrosDevolucionHeader $devolucionHeader)
    {
        parent::__construct($devolucionHeader);
    }
    public function getDisponibilidadCodigoPrefactura($request){
        $pro_codigo     = $request->pro_codigo;
        $id_institucion = $request->id_institucion;
        $id_periodo     = $request->id_periodo;
        $id_empresa     = $request->id_empresa;
        // Obtener la cantidad facturada
        $facturado = \DB::table('f_detalle_venta_agrupado as dg')
            ->join('f_venta_agrupado as dv', 'dg.id_factura', '=', 'dv.id_factura')
            ->where('dg.pro_codigo', '=', $pro_codigo)
            ->where('dv.id_empresa', $id_empresa)
            ->where('dg.id_empresa', $id_empresa)
            ->where('dv.institucion_id', $id_institucion)
            ->where('dv.periodo_id', $id_periodo)
            ->selectRaw('COALESCE(SUM(dg.det_ven_cantidad), 0) as cantidad')
            ->value('cantidad');
        // Obtener la cantidad disponible en prefactura
        $disponiblePrefactura = \DB::table('f_detalle_venta as dg')
        ->join('f_venta as dv', function ($join) use ($id_empresa) {
            $join->on('dg.ven_codigo', '=', 'dv.ven_codigo')
                 ->where('dg.id_empresa', '=', $id_empresa)
                 ->where('dv.id_empresa', '=', $id_empresa);
        })
        ->where('dg.pro_codigo', $pro_codigo)
        ->where('dv.institucion_id', $id_institucion)
        ->where('dv.periodo_id', $id_periodo)
        ->where('dv.idtipodoc', '1')
        ->where('dv.estadoPerseo', '0')
        ->where('dv.est_ven_codigo', '<>', 3)
        ->whereNull('dv.doc_intercambio')
        ->selectRaw('COALESCE(SUM(dg.det_ven_cantidad - dg.det_ven_dev), 0) as cantidad')
        ->value('cantidad') ?? 0;


        // Obtener la cantidad reservada creada
        $cantidadReservadaCreada = \DB::table('codigoslibros_devolucion_son as cs')
        ->join('f_venta as v', function ($join) use ($id_empresa) {
            $join->on('cs.documento', '=', 'v.ven_codigo')
                 ->where('v.id_empresa', '=', $id_empresa);
        })
        ->where('cs.id_empresa', $id_empresa)
        ->where('cs.id_cliente', $id_institucion)
        ->where('cs.id_periodo', $id_periodo)
        ->where('cs.pro_codigo', $pro_codigo)
        ->where('cs.estado', '0')
        ->where('v.idtipodoc', '1')
        ->where('v.est_ven_codigo', '<>', 3)
        ->whereNull('v.doc_intercambio')
        ->count();

        // Realizar la operación
        $resultado = abs($disponiblePrefactura - $facturado) - $cantidadReservadaCreada;
        return $resultado;
    }
    public function getFacturaAvailable($request){
        $pro_codigo      = $request->pro_codigo;
        $id_institucion  = $request->id_institucion;
        $id_periodo      = $request->id_periodo;
        $id_empresa      = $request->id_empresa;
        // Obtener la cantidad facturada
        $disponiblePrefactura = \DB::table('f_detalle_venta as dg')
            ->join('f_venta as dv', function ($join) use ($id_empresa) {
                $join->on('dg.ven_codigo', '=', 'dv.ven_codigo')
                    ->where('dg.id_empresa', '=', $id_empresa)
                    ->where('dv.id_empresa', '=', $id_empresa);
            })
            ->where('dg.pro_codigo', $pro_codigo)
            ->where('dv.id_empresa', $id_empresa)
            ->where('dg.id_empresa', $id_empresa)
            ->where('dv.institucion_id', $id_institucion)
            ->where('dv.periodo_id', $id_periodo)
            ->where('dv.idtipodoc', '1')
            ->where('dv.estadoPerseo', '0')
            ->where('dv.est_ven_codigo', '<>', 3)
            ->whereNull('dv.doc_intercambio')
            ->selectRaw('COALESCE(SUM(dg.det_ven_cantidad - dg.det_ven_dev), 0) as cantidad, dv.ven_codigo, dg.pro_codigo')
            ->groupBy('dv.ven_codigo') // Necesario para usar agregados
            ->first();

        return $disponiblePrefactura;
    }
    public function detallePrefactura($ven_codigo,$id_empresa,$id_institucion,$combos = 0)
    {
        $disponiblePrefactura = \DB::table('f_detalle_venta as dg')
        ->join('f_venta as dv', function ($join) use ($id_empresa) {
            $join->on('dg.ven_codigo', '=', 'dv.ven_codigo')
                ->where('dg.id_empresa', '=', $id_empresa)
                ->where('dv.id_empresa', '=', $id_empresa);
            })
            ->leftJoin('libros_series as ls', 'ls.codigo_liquidacion', '=', 'dg.pro_codigo')
            ->leftJoin('1_4_cal_producto as pro', 'pro.pro_codigo', '=', 'dg.pro_codigo')
            ->where('dg.ven_codigo', $ven_codigo)
            ->where('dv.id_empresa', $id_empresa)
            ->where('dv.institucion_id', $id_institucion)
            ->where('dv.idtipodoc', '1')
            ->where('dv.est_ven_codigo', '<>', 3)
            ->whereNull('dv.doc_intercambio')
            ->where('dv.estadoPerseo', '0')
            ->when($combos == 1, function($query) use ($combos) {
                $query->where('pro.ifcombo', '=', 1);
            })
            ->selectRaw('dg.*, ls.nombre as nombrelibro, pro.ifcombo, pro.codigos_combos, CONCAT(ls.nombre," ", pro.pro_codigo) as nombrePro,
            ls.idLibro
            ')
            ->get();

        return $disponiblePrefactura;
    }
    public function getCodigosCombosDocumentoDevolucion($id_documento){
        $query = DB::SELECT("SELECT s.* , ls.nombre AS nombrelibro, pro.codigos_combos, e.descripcion_corta AS empresa
        FROM codigoslibros_devolucion_son s
        LEFT JOIN libros_series ls ON ls.codigo_liquidacion = s.pro_codigo
        LEFT JOIN  1_4_cal_producto pro  ON pro.pro_codigo = ls.codigo_liquidacion
        LEFT JOIN empresas e ON e.id  = s.id_empresa
        WHERE s.tipo_codigo = '1'
        AND s.codigoslibros_devolucion_id = '$id_documento'
        ORDER BY s.id desc
        ");
        return $query;
    }
    public function devolucionCliente($idCliente,$idPeriodo)
    {
        $query = DB::SELECT("SELECT h.*, l.nombrelibro
            FROM codigoslibros_devolucion_son h
            LEFT JOIN libro l ON h.id_libro = l.idlibro
            LEFT JOIN codigoslibros_devolucion_header p ON p.id = h.codigoslibros_devolucion_id
            where h.estado <> '0'
            AND p.id_cliente = ?
            AND p.periodo_id = ?
        ",[$idCliente,$idPeriodo]);
        return $query;
    }
    public function save_son_devolucion_facturador($datos){
        //validar que no existe el pro_codigo la id_empresay el codigoslibros_devolucion_header_facturador_id
        $validar = CodigosLibrosDevolucionSonFacturador::where('pro_codigo',$datos->pro_codigo)
        ->where('codigoslibros_devolucion_header_facturador_id',$datos->documentoPadre)
        ->where('id_empresa',$datos->id_empresa)->first();
        if($validar){
            return $validar;
        }
        $devolucionH = new CodigosLibrosDevolucionSonFacturador();
        $devolucionH->codigoslibros_devolucion_header_facturador_id = $datos->documentoPadre;
        $devolucionH->codigoslibros_devolucion_header_id            = $datos->codigoslibros_devolucion_header_id;
        $devolucionH->id_empresa                                    = $datos->id_empresa;
        $devolucionH->pro_codigo                                    = $datos->pro_codigo;
        $devolucionH->cantidad                                      = $datos->cantidad;
        $devolucionH->precio                                        = $datos->precio;
        $devolucionH->descuento                                     = $datos->descuento;
        $devolucionH->observacion_codigo                            = $datos->observacion_codigo;
        $devolucionH->save();
        return $devolucionH;
    }
    public function prefacturaLibreXCodigo($id_empresa, $id_institucion, $id_periodo, $pro_codigo,$cantidadNecesaria=0)
    {
        $query = DB::table('f_venta as v')
            ->leftJoin('f_detalle_venta as dv', function ($join) {
                $join->on('dv.ven_codigo', '=', 'v.ven_codigo')
                    ->on('dv.id_empresa', '=', 'v.id_empresa');
            })
            ->select(
                'dv.*',
                DB::raw('(COALESCE(dv.det_ven_cantidad, 0) - COALESCE(dv.det_ven_dev, 0)) AS disponible')
            )
            ->where('v.institucion_id', $id_institucion)
            ->where('v.id_empresa', $id_empresa)
            ->where('v.periodo_id', $id_periodo)
            ->where('v.est_ven_codigo', '<>', '3')
            ->whereNull('v.doc_intercambio')
            ->where('v.idtipodoc', '1')
            ->where('dv.pro_codigo', $pro_codigo)
            ->whereNull('dv.doc_intercambio')
            ->having('disponible', '>', $cantidadNecesaria)
            ->get();

        return $query;
    }
    public function validateComboCreado($combo,$id_empresa,$id_devolucion)
    {
        $query = CodigosLibrosDevolucionSon::where('pro_codigo', $combo)->where('id_empresa', $id_empresa)->where('codigoslibros_devolucion_id', $id_devolucion)->get();
        return $query;
    }
}
