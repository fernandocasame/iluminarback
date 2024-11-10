<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CodigosLibros;
use App\Models\CodigosLibrosDevolucionHeader;
use App\Models\CodigosLibrosDevolucionSon;
use App\Models\DetalleVentas;
use App\Repositories\Facturacion\DevolucionRepository;
use App\Repositories\Facturacion\ProformaRepository;
use App\Repositories\pedidos\PedidosRepository;
use App\Traits\Codigos\TraitCodigosGeneral;
use Facade\FlareClient\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DevolucionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    use TraitCodigosGeneral;
    protected $proformaRepository;
    protected $pedidosRepository;
    protected $devolucionRepository;
    public function __construct(ProformaRepository $proformaRepository, PedidosRepository $pedidosRepository , DevolucionRepository  $devolucionRepository)
    {
        $this->proformaRepository    = $proformaRepository;
        $this->pedidosRepository     = $pedidosRepository;
        $this->devolucionRepository  = $devolucionRepository;
    }
    //API:GET/devoluciones
    public function index(Request $request)
    {
        if($request->listadoProformasAgrupadas)          { return $this->listadoProformasAgrupadas($request); }
        if($request->filtroDocumentosDevueltos)          { return $this->filtroDocumentosDevueltos($request); }
        if($request->getCodigosxDocumentoDevolucion)     { return $this->getCodigosxDocumentoDevolucion($request); }
        if($request->historicoDevolucionPreFacturas)     { return $this->historicoDevolucionPreFacturas($request); }
        if($request->getDocumentosDevolucion)            { return $this->getDocumentosDevolucion($request); }
       if($request->todoDevolucionCliente)  { return $this->todoDevolucionCliente($request); }
       if($request->devolucionDetalle)  { return $this->devolucionDetalle($request); }
       if($request->CargarDevolucion)  { return $this->CargarDevolucion($request); }
       if($request->CargarDocumentos)  { return $this->CargarDocumentos($request); }
       if($request->CargarDocumentosDetalles)  { return $this->CargarDocumentosDetalles($request); }
       if($request->CargarDetallesDocumentos)  { return $this->CargarDetallesDocumentos($request); }
       if($request->documentoExiste)  { return $this->verificarDocumento($request); }

    }
    //api:get/devoluciones?listadoProformasAgrupadas=1&institucion=1620
    public function listadoProformasAgrupadas(Request $request)
    {
        $institucion                = $request->input('institucion');
        $getProformas               = $this->proformaRepository->listadoProformasAgrupadas($institucion);
        if(empty($getProformas))    { return []; }
        foreach($getProformas as $key => $item){
            $query = DB::SELECT("SELECT * FROM f_venta_agrupado v
            WHERE v.id_factura = ?
            AND v.estadoPerseo = '1'
            AND v.id_empresa = ?
            ",[$item->id_factura,$item->id_empresa]);
            if(count($query) > 0){
                $getProformas[$key]->ifPedidoPerseo = 1;
            }else{
                $getProformas[$key]->ifPedidoPerseo = 0;
            }
        }
        $resultado = collect($getProformas);
        //filtrar por ifPedidoPerseo igual a 0
        $resultado = $resultado->where('ifPedidoPerseo','0')->values();
        return $resultado;
    }
    //api:get/devoluciones?filtroDocumentosDevueltos=1&fechaInicio=2024-10-01&fechaFin=2024-10-06
    public function filtroDocumentosDevueltos(Request $request)
    {
        $fechaInicio    = $request->input('fechaInicio');
        $fechaFin       = $request->input('fechaFin');
        $id_cliente     = $request->input('id_cliente');
        $revisados      = $request->input('revisados');
        $finalizados    = $request->input('finalizados');

        if ($fechaFin) {
            $fechaFin = date('Y-m-d', strtotime($fechaFin)) . ' 23:59:59';
        }

        $getDocumentos = CodigosLibrosDevolucionHeader::with([
            'institucion:idInstitucion,nombreInstitucion',
            'usuario:idusuario,nombres,apellidos',
            'usuarioRevision:idusuario,nombres,apellidos',
            'usuarioFinalizacion:idusuario,nombres,apellidos',
            'periodo',
            'devolucionSon' => function ($query) {
                $query->where('prueba_diagnostico', '0');
            }
        ])
        ->when($fechaInicio && $fechaFin, function ($query) use ($fechaInicio, $fechaFin) {
            $query->whereBetween('created_at', [$fechaInicio, $fechaFin]);
        })
        ->when($id_cliente, function ($query) use ($id_cliente) {
            $query->where('id_cliente', $id_cliente);
        })
        ->when($revisados, function ($query) use ($revisados) {
            $query->where('estado', 1);
        })
        ->when($finalizados, function ($query) use ($finalizados) {
            $query->where('estado', 2);
        })
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($documento) {
            return [
                'id'                    => $documento->id,
                'id_cliente'            => $documento->id_cliente,
                'codigo_devolucion'     => $documento->codigo_devolucion,
                'observacion'           => $documento->observacion,
                'codigo_nota_credito'   => $documento->codigo_nota_credito,
                'created_at'            => $documento->created_at_formatted,
                'estado'                => $documento->estado,
                'fecha_revisado'        => $documento->fecha_revisado,
                'fecha_finalizacion'    => $documento->fecha_finalizacion,
                'institucion'           => $documento->institucion,
                'cantidadCajas'         => $documento->cantidadCajas,
                'cantidadPaquetes'      => $documento->cantidadPaquetes,
                'tipo_importacion'      => $documento->tipo_importacion,
                'usuario'               => [
                    'nombres'           => $documento->usuario->nombres ?? null,
                    'apellidos'         => $documento->usuario->apellidos ?? null
                ],
                'usuario_revision'      => [
                    'nombres'           => $documento->usuarioRevision->nombres ?? null,
                    'apellidos'         => $documento->usuarioRevision->apellidos ?? null
                ],
                'usuario_finalizacion'  => [
                    'nombres'           => $documento->usuarioFinalizacion->nombres ?? null,
                    'apellidos'         => $documento->usuarioFinalizacion->apellidos ?? null
                ],
                'cantidadHijos'         => count($documento->devolucionSon),
                'periodo'               => $documento->periodo->periodoescolar,
                // 'devolucionSon'         => $documento->devolucionSon,
            ];
        });

        return $getDocumentos;
    }

    //api:get/devoluciones?getCodigosxDocumentoDevolucion=1&id_documento=3
    public function getCodigosxDocumentoDevolucion(Request $request)
    {
        $id_documento = $request->input('id_documento');
        $revisados    = $request->input('revisados');
        $finalizados  = $request->input('finalizados');
        $agrupar      = $request->input('agrupar');
        $porcliente   = $request->input('porcliente');
        $getCodigos = CodigosLibrosDevolucionSon::query()
            ->leftJoin('codigoslibros', 'codigoslibros.codigo', '=', 'codigoslibros_devolucion_son.codigo')
            ->leftJoin('libro', 'libro.idlibro', '=', 'codigoslibros.libro_idlibro')
            ->leftJoin('libros_series', 'libros_series.idLibro', '=', 'libro.idlibro')
            ->leftJoin('empresas', 'empresas.id', 'codigoslibros_devolucion_son.id_empresa')     
            ->leftJoin('institucion', 'institucion.idInstitucion', 'codigoslibros_devolucion_son.id_cliente')       
            ->leftJoin('codigoslibros_devolucion_header', 'codigoslibros_devolucion_header.id', 'codigoslibros_devolucion_son.codigoslibros_devolucion_id')
            ->leftJoin('periodoescolar','periodoescolar.idperiodoescolar','codigoslibros_devolucion_son.id_periodo')
            ->where('codigoslibros_devolucion_id', $id_documento)
            ->where('codigoslibros_devolucion_son.prueba_diagnostico', '0')
            ->when($revisados, function ($query) use ($revisados) {
                $query->where('codigoslibros_devolucion_son.estado', 1);
            })
            ->when($finalizados, function ($query) use ($finalizados) {
                $query->where('codigoslibros_devolucion_son.estado', 2);
            })
            ->select(
                'codigoslibros_devolucion_son.*', 
                'codigoslibros.estado_liquidacion',
                'codigoslibros.liquidado_regalado', 
                'codigoslibros.estado as estadoActualCodigo',
                'libro.nombrelibro', 
                'libros_series.codigo_liquidacion',
                'empresas.descripcion_corta', 
                'institucion.nombreInstitucion', 
                'institucion.idInstitucion as id_cliente',
                'periodoescolar.periodoescolar'
            )
            ->get();
    
        if ($agrupar == 1) {
            // Agrupar por nombre libro y contar cuántas veces se repite
            $resultado = collect($getCodigos)->groupBy('nombrelibro')->map(function ($item) {
                return [
                    'nombrelibro' => $item[0]->nombrelibro,
                    'codigo'      => $item[0]->codigo_liquidacion,
                    'cantidad'    => count($item),
                ];
            })->values();
        } else {
            $resultado = $getCodigos;
            if($porcliente){
                //Agrupar por cliente, usando "Sin cliente" para id_cliente 0 o null
                $agrupados = $resultado->groupBy(function ($item) {
                    return $item->id_cliente ?: 'sin_cliente';  // 'sin_cliente' como clave para id_cliente 0 o null
                })
                ->map(function ($items, $key) {
                    return [
                        'id_cliente'       => $key == 'sin_cliente' ? 0 : $key,
                        'nombreInstitucion' => $key == 'sin_cliente' ? 'Sin cliente' : $items[0]->nombreInstitucion,
                        'data'             => $items
                    ];
                })->values();
                $resultado = $agrupados;
            }
        }
    
        return $resultado;
    }
    //api:get/devoluciones?historicoDevolucionPreFacturas=yes
    public function historicoDevolucionPreFacturas(Request $request)
    {
        $query = DB::SELECT("SELECT s.*, e.descripcion_corta,
        i.nombreInstitucion, p.periodoescolar,
        ch.observacion,ch.fecha_revisado
        FROM codigoslibros_devolucion_son s
        LEFT JOIN empresas e ON s.id_empresa = e.id
        LEFT JOIN codigoslibros_devolucion_header ch ON s.codigoslibros_devolucion_id = ch.id
        LEFT JOIN periodoescolar p ON ch.periodo_id = p.idperiodoescolar
        LEFT JOIN institucion i ON ch.id_cliente = i.idInstitucion
        WHERE s.estado = '1'
        AND s.prueba_diagnostico = '0'
        AND s.documento IS NOT NULL 
        ORDER BY s.id desc
        ");
        return $query;
    }
    //api:get/devoluciones?getDocumentosDevolucion=1&creadas=1
    public function getDocumentosDevolucion(Request $request)
    {
        $creadas = $request->input('creadas');
        $results = DB::table('codigoslibros_devolucion_header as h')
            ->leftJoin('institucion as i', 'i.idInstitucion', '=', 'h.id_cliente')
            ->leftJoin('periodoescolar as p','p.idperiodoescolar','=','h.periodo_id')
            ->select(
                'h.*',
                'i.nombreInstitucion',
                'p.region_idregion as region',
                DB::raw("CONCAT(h.codigo_devolucion, ' - ', i.nombreInstitucion) AS documento_cliente")
            )
            ->when($creadas, function ($query) {
                $query->where('h.estado', '0');
            })
            ->get();
        
        return $results;
    }
    //api:get/devoluciones?devolucionCliente=yes
    public function devolucionCliente(Request $request)
    {
        $query = DB::SELECT("SELECT DISTINCT i.nombreInstitucion AS cliente, cl.id_cliente FROM codigoslibros_devolucion_header cl
            INNER JOIN institucion i ON i.idInstitucion = cl.id_cliente
            WHERE i.nombreInstitucion LIKE '%$request->busqueda%'
        ");
        return $query;
    }
    //api:get/devoluciones?todoDevolucionCliente=yes
    public function todoDevolucionCliente(Request $request)
    {
        $query = DB::SELECT("SELECT
                    i.nombreInstitucion AS cliente,
                    cl.*,
                    (SELECT COUNT(*) FROM codigoslibros_devolucion_son ch WHERE ch.codigoslibros_devolucion_id = cl.id) AS total_codigos_son,
                    (SELECT GROUP_CONCAT(DISTINCT ch.documento SEPARATOR ', ')
                    FROM codigoslibros_devolucion_son ch
                    WHERE ch.codigoslibros_devolucion_id = cl.id) AS documentos
                FROM
                    codigoslibros_devolucion_header cl
                INNER JOIN
                    institucion i ON i.idInstitucion = cl.id_cliente
                WHERE
                    cl.id_cliente LIKE '%$request->cliente%'");
        return $query;
    }
    //api:get/devoluciones?devolucionDetalle=yes
    public function devolucionDetalle(Request $request)
    {
        $query = DB::SELECT("SELECT ch.*, i.nombreInstitucion AS cliente, ls.id_serie, ls.nombre, a.area_idarea, ls.year
            FROM codigoslibros_devolucion_son AS ch
            LEFT JOIN codigoslibros_devolucion_header AS cl ON ch.codigoslibros_devolucion_id = cl.id
            LEFT JOIN institucion AS i ON i.idInstitucion = ch.id_cliente
            LEFT JOIN libros_series ls ON ls.idLibro = ch.id_libro
            LEFT JOIN libro l ON l.idlibro = ch.id_libro
            LEFT JOIN asignatura a ON a.idasignatura = l.asignatura_idasignatura
            WHERE cl.codigo_devolucion = '$request->busqueda'
            AND ch.prueba_diagnostico = 0");
        foreach ($query as $key => $item) {

            //Precio por cada item
            $precio = $this->pedidosRepository->getPrecioXLibro($item->id_serie, $item->id_libro, $item->area_idarea, $item->id_periodo, $item->year);

            //Añadir el precio
            $query[$key]->precio = $precio ?? 0;
        }
        return $query;
    }
    public function CargarDevolucion(Request $request)
    {
        // Obtener y decodificar los datos de la solicitud
        $datosDevolucion = json_decode($request->query('datosDevolucion'), true);

        // Validar que se haya decodificado correctamente
        if (!is_array($datosDevolucion)) {
            return response()->json(['error' => 'Invalid data'], 400);
        }

        $resultados = [];

        foreach ($datosDevolucion as $devolucion) {
            // Verificar que cada devolución tenga el campo 'codigo_devolucion'
            if (!isset($devolucion['codigo_devolucion'])) {
                return response()->json(['error' => 'codigo_devolucion is required'], 400);
            }

            $codigoDevolucion = $devolucion['codigo_devolucion'];

            // Consulta SQL
            $query = DB::SELECT("SELECT ch.*, i.nombreInstitucion AS cliente, ls.id_serie, ls.nombre, a.area_idarea, ls.year
                FROM codigoslibros_devolucion_son AS ch
                LEFT JOIN codigoslibros_devolucion_header AS cl ON ch.codigoslibros_devolucion_id = cl.id
                LEFT JOIN institucion AS i ON i.idInstitucion = ch.id_cliente
                LEFT JOIN libros_series ls ON ls.idLibro = ch.id_libro
                LEFT JOIN libro l ON l.idlibro = ch.id_libro
                LEFT JOIN asignatura a ON a.idasignatura = l.asignatura_idasignatura
                WHERE cl.codigo_devolucion = ?
                AND ch.prueba_diagnostico = 0", [$codigoDevolucion]);

            // Procesar cada ítem de la consulta
            foreach ($query as $item) {
                // Obtener precio por cada ítem
                $precio = $this->pedidosRepository->getPrecioXLibro($item->id_serie, $item->id_libro, $item->area_idarea, $item->id_periodo, $item->year);
                // Añadir el precio al ítem, asignando 0 si no se encuentra
                $item->precio = $precio ?? 0; // Asignar 0 si $precio es null
            }

            // Agregar resultados de la consulta actual a los resultados generales
            $resultados = array_merge($resultados, $query);
        }

        return response()->json($resultados);
    }


    public function CargarDocumentos(Request $request){
        $query = DB::SELECT("SELECT
            ins.ruc AS rucPuntoVenta, em.nombre AS empresa,
            CONCAT(usa.nombres, ' ', usa.apellidos) AS cliente, fv.ven_codigo,
            fv.ven_fecha, fv.user_created, fv.ven_valor, ins.nombreInstitucion, ins.direccionInstitucion, ins.telefonoInstitucion, ins.asesor_id,
            CONCAT(u.nombres, ' ', u.apellidos) AS asesor,
            usa.nombres, usa.apellidos, fpr.prof_observacion, fpr.idPuntoventa,
            COUNT(DISTINCT dfv.pro_codigo) AS item, CONCAT(us.nombres, ' ', us.apellidos) AS responsable,
            (SELECT SUM(det_ven_cantidad) FROM f_detalle_venta WHERE ven_codigo = fv.ven_codigo AND id_empresa = fv.id_empresa) AS libros,
            fv.ruc_cliente AS cedula, usa.email, usa.telefono,fv.idtipodoc, em.id AS empresa_id, fv.ven_tipo_inst, fv.ven_idproforma, fv.ven_observacion,
            fv.ven_subtotal, fv.ven_desc_por, fv.ven_descuento, fv.ven_iva, fv.ven_transporte
            FROM f_venta fv
            LEFT JOIN f_proforma fpr ON fpr.prof_id = fv.ven_idproforma
            LEFT JOIN p_libros_obsequios plo ON plo.id = fv.ven_p_libros_obsequios
            LEFT JOIN empresas em ON fpr.emp_id = em.id OR fv.id_empresa = em.id
            LEFT JOIN pedidos pe ON fpr.idPuntoventa = pe.ca_codigo_agrupado
            LEFT JOIN institucion ins ON fpr.id_ins_depacho = ins.idInstitucion OR fv.institucion_id = ins.idInstitucion
            LEFT JOIN usuario usa ON fpr.ven_cliente = usa.idusuario OR fv.ruc_cliente = usa.cedula
            INNER JOIN usuario us ON fv.user_created = us.idusuario
            LEFT JOIN usuario u ON ins.asesor_id = u.idusuario
            LEFT JOIN f_detalle_venta dfv ON fv.ven_codigo = dfv.ven_codigo AND fv.id_empresa = dfv.id_empresa
            WHERE fv.ven_codigo = '$request->documentos' AND fv.est_ven_codigo <> 3
            GROUP BY fv.ven_codigo, fv.ven_fecha,
                ins.ruc, em.nombre, usa.nombres, usa.apellidos, fpr.prof_observacion,
                fpr.idPuntoventa, u.nombres, u.apellidos, fv.user_created, fv.ven_valor, ins.nombreInstitucion,
                ins.direccionInstitucion, ins.telefonoInstitucion,  ins.asesor_id,
                fv.id_empresa, fv.ruc_cliente, usa.email, usa.telefono, em.id, fv.ven_tipo_inst, fv.ven_idproforma,
                fv.ven_observacion,fv.ven_subtotal, fv.ven_desc_por, fv.ven_descuento, fv.ven_iva, fv.ven_transporte
            ORDER BY fv.ven_fecha DESC;
            ");
      return $query;
    }
    public function CargarDocumentosDetalles(Request $request){
        $query = DB::SELECT("SELECT dv.det_ven_codigo, dv.pro_codigo, dv.det_ven_dev, dv.det_ven_cantidad, dv.det_ven_valor_u,
            l.descripcionlibro, ls.nombre, s.nombre_serie, ls.id_serie FROM f_detalle_venta AS dv
            INNER JOIN f_venta AS fv ON dv.ven_codigo=fv.ven_codigo
            INNER JOIN libros_series AS ls ON dv.pro_codigo=ls.codigo_liquidacion
            INNER JOIN series AS s ON ls.id_serie=s.id_serie
            INNER JOIN libro l ON ls.idLibro = l.idlibro
            WHERE dv.ven_codigo='$request->codigo' AND dv.id_empresa=fv.id_empresa
            AND fv.id_empresa= $request->empresa ORDER BY dv.pro_codigo");
      return $query;
    }
    public function CargarDetallesDocumentos(Request $request) {
        // Decodifica el JSON recibido en el parámetro 'documentos'
        $datosDevolucionDocuemntos = json_decode($request->query('documentos'), true);

        // Verifica que el array no esté vacío
        if (empty($datosDevolucionDocuemntos)) {
            return response()->json([], 400); // Devuelve un error si no hay documentos
        }

        // Usa implode para construir la lista de códigos en la consulta
        $codigos = implode(',', array_map('intval', $datosDevolucionDocuemntos)); // Asegúrate de que sean enteros para evitar inyecciones SQL

        // Prepara la consulta
        $query = DB::SELECT("
            SELECT fv.ven_codigo, fv.tip_ven_codigo, fv.ven_idproforma, fv.ven_tipo_inst,
                   fv.ven_valor, fv.ven_subtotal, fv.ven_desc_por, fv.ven_descuento,
                   fv.ven_fecha, fv.institucion_id, fv.ven_cliente, fv.ruc_cliente
            FROM f_venta fv
            WHERE fv.ven_codigo IN ($codigos)"); // Usa IN para buscar múltiples códigos

        // Retorna los resultados como respuesta JSON
        return response()->json($query);
    }

    public function verificarDocumento(Request $request)
    {
        $documento = $request->query('documento');
        $clienteId = $request->query('cliente');

        // Verificar si el documento existe y no está anulado para el cliente correspondiente
        $resultado = DB::table('f_venta')
            ->where('ven_codigo', $documento)
            ->where('institucion_id', $clienteId) // Asegurarse que el documento pertenezca al cliente
            ->where('est_ven_codigo', '<>', 3) // Asegurarse de que el estado no sea "anulado"
            ->first();

        if ($resultado) {
            $resultadoDevuelto = DB::table('codigoslibros_devolucion_header')
            ->where('codigo_nota_credito', $documento)
            ->first();
            if ($resultadoDevuelto) {
                return response()->json(['existe' => true, 'anulado' => false, 'devuelto' => true]);
            }else{
                return response()->json(['existe' => true, 'anulado' => false, 'devuelto' => false]);
            }
        } else {
            // Comprobar si el documento está anulado para el cliente correspondiente
            $resultadoAnulado = DB::table('f_venta')
                ->where('ven_codigo', $documento)
                ->where('institucion_id', $clienteId) // Verificar cliente
                ->where('est_ven_codigo', 3) // Comprobando estado anulado
                ->first();

            if ($resultadoAnulado) {
                return response()->json(['existe' => true, 'anulado' => true]);
            }

            // El documento no existe
            return response()->json(['existe' => false, 'anulado' => false]);
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
    //api:post/devoluciones
    public function store(Request $request)
    {
        if($request->devolverDocumentoBodega)   { return $this->devolverDocumentoBodega($request); }
        if($request->updateDocumentoDevolucion) { return $this->updateDocumentoDevolucion($request); }
        if($request->changeNotasToPrefacturas)  { return $this->changeNotasToPrefacturas($request); }
    }
    //api:post/devoluciones?devolverDocumentoBodega=1
    public function devolverDocumentoBodega($request){
        $codigos                = json_decode($request->data_codigos);
        $codigosABuscar         = array_column($codigos, 'codigo');
        CodigosLibros::whereIn('codigo', $codigosABuscar)
            ->update([
                'estado_liquidacion' => 3
            ]);
    }
    //api:post/devoluciones?updateDocumentoDevolucion=1
    public function updateDocumentoDevolucion($request) {
        try {
            // Transacción
            DB::beginTransaction();

            $id_documento   = $request->input('id_documento');
            $revision       = $request->input('revision');
            $finalizacion   = $request->input('finalizacion');
            $id_usuario     = $request->input('id_usuario');

            if ($revision) {
                $updateData['estado'] = 1;
                $updateData['user_created_revisado'] = $id_usuario;
                $updateData['fecha_revisado'] = now();
            }

            if ($finalizacion) {
                $updateData['estado'] = 2;
                $updateData['user_created_finalizado'] = $id_usuario;
                $updateData['fecha_finalizacion'] = now();
            }

            // Actualizar el encabezado
            CodigosLibrosDevolucionHeader::where('id', $id_documento)->update($updateData);

            // Actualizar la tabla codigoslibros_devolucion_son
            CodigosLibrosDevolucionSon::where('codigoslibros_devolucion_id', $id_documento)
                ->where('estado', '<>', '0')
                ->update(['estado' => $revision ? 1 : 2]);

            // Confirmar transacción
            DB::commit();
            return response()->json(['message' => 'Documento actualizado correctamente']);

        } catch (\Exception $e) {
            // Rollback de transacción
            DB::rollBack();
            // Puedes registrar el error aquí
            return response()->json(['status' => '0', 'message' => 'Error al actualizar el documento: ' . $e->getMessage()], 200);
        }
    }
    //api:post/devoluciones?changeNotasToPrefacturas=1&id_empresa=3&id_institucion=1287&id_periodo=25&id_usuario=1
    public function changeNotasToPrefacturas($request)
    {
        // $id_empresa     = $request->input('id_empresa');
        $id_institucion = $request->input('id_institucion');
        $id_periodo     = $request->input('id_periodo');
        $id_usuario     = $request->input('id_usuario'); // ID del usuario que realiza la acción
        // JSON con los códigos individuales
//        $codigosIndividuales = '[
//            {
//                "codigo": "SMLL3-K8GA4WZ",
//                "codigo_proforma": "N-C-S24-FR-BC44",
//                "codigo_liquidacion": "SMCN6",
//                "proforma_empresa": 3

//            },
//            {
//                "codigo": "SMLL3-K8GA4WZ",
//                "codigo_proforma": "N-C-S24-FR-BC44",
//                "codigo_liquidacion": "SEN3",
//                "proforma_empresa": 1
//            }
//        ]';

        $arrayLibrosNoExistenEnNota         = [];
        $arrayCodigosInsuficientesNotas     = [];
        $arrayCodigosProblemas              = [];
        $cambiados                          = 0;

        // Convertir el JSON en un array de objetos
        // $arrayCodigosNotaCredito = json_decode($codigosIndividuales, false);
        $arrayCodigosNotaCredito = json_decode($request->arrayCodigosNotaCredito);
        // Agrupar por 'codigo_proforma' y 'codigo_liquidacion'
        $agrupadoPrenotas = collect($arrayCodigosNotaCredito)
            ->groupBy(function ($item) {
                //agrupar por codigo proforma y codigo liquidacion y codigo empresa
                return $item->codigo_proforma . '-' . $item->codigo_liquidacion . '-' . $item->proforma_empresa;
            })
            ->map(function ($items, $key) {
                $firstItem = $items->first();
                return (object)[
                    'codigo_proforma'       => $firstItem->codigo_proforma,
                    'codigo_liquidacion'    => $firstItem->codigo_liquidacion,
                    'cantidad'              => $items->count(),
                    'proforma_empresa'      => $firstItem->proforma_empresa
                ];
            })
            ->values();
        // Iniciar una transacción para asegurar la integridad de los datos
        DB::beginTransaction();

        try {
            // Recorrer el $agrupadoPrenotas y validar que el codigo_liquidacion exista
            foreach ($agrupadoPrenotas as $value) {
                $cantidadLibrosNotas    = 0;
                $cantidadLibroDescontar = $value->cantidad;
                $codigo_liquidacion     = $value->codigo_liquidacion;
                $codigo_proforma        = $value->codigo_proforma;
                $codigo_empresa         = $value->proforma_empresa;
                // Obtener la nota correspondiente
                $getNota = DetalleVentas::where('ven_codigo', $codigo_proforma)
                    ->where('id_empresa', $codigo_empresa)
                    ->where('pro_codigo', $codigo_liquidacion)
                    ->first();
                // Si no existe el libro en la nota
                if (!$getNota) {
                    $arrayLibrosNoExistenEnNota[]           = $value;
                } else {
                    // Obtener la cantidad de libros en la nota
                    $cantidadLibrosNotas                    = $getNota->det_ven_cantidad;
                    // Si la cantidad de libros en la nota es insuficiente
                    if ($cantidadLibrosNotas < $cantidadLibroDescontar) {
                        $value->cantidadLibrosNotas         = $cantidadLibrosNotas;
                        $arrayCodigosInsuficientesNotas[]   = $value;
                    } else {
                        // Obtener los códigos de la nota correspondiente
                        $codigosFiltrarXDocumento = collect($arrayCodigosNotaCredito)
                            ->where('codigo_proforma', $codigo_proforma)
                            ->where('codigo_liquidacion', $codigo_liquidacion)
                            ->where('proforma_empresa', $codigo_empresa)
                            ->toArray();
                        foreach ($codigosFiltrarXDocumento as $item) {
                            $cantidadDisponible             = 0;
                            $getPrefactura                  = null;
                            $cantidadDisponiblePrefactura   = 0;
                            $resultadoSuma                  = 0;
                            $datos = (object) [
                                "pro_codigo"                => $item->codigo_liquidacion,
                                "id_institucion"            => $id_institucion,
                                "id_periodo"                => $id_periodo,
                                "id_empresa"                => $codigo_empresa   
                            ];
                            $cantidadDisponible = $this->devolucionRepository->getDisponibilidadCodigoPrefactura($datos);
                            if ($cantidadDisponible < 1) {
                                $value->disponible          = $cantidadDisponible;
                                // $value->mensaje             = 'El libro ' . $item->codigo . ' no ha sido despachado mediante pre-facturas, no es posible mover';
                                // $arrayCodigosProblemas[]    = $value;
                                //si el codigo tiene codigo_union tambien le actualizo
                                $codigoUpdate = CodigosLibros::where('codigo',$item->codigo)->first();
                                $codigo_union = $codigoUpdate->codigo_union;
                                if($codigo_union != null && $codigo_union != "null" && $codigo_union != ""){
                                    CodigosLibros::where('codigo', $codigo_union)
                                    ->update(['permitir_devolver_nota' => 1]);
                                }
                                // Actualizar la pre factura en el código
                                CodigosLibros::where('codigo', $item->codigo)
                                ->update(['permitir_devolver_nota' => 1]);
                                $cambiados++;
                            } else {
                                // Obtener la primera prefactura disponible
                                $getPrefactura                      = $this->devolucionRepository->getFacturaAvailable($datos);
                                if ($getPrefactura) {
                                    $cantidadDisponiblePrefactura   = $getPrefactura->cantidad;
                                    $resultadoSuma                  = $cantidadDisponiblePrefactura + 1;
                                    // Aumentar en las pre facturas
                                    DetalleVentas::where('ven_codigo', $getPrefactura->ven_codigo)
                                        ->where('id_empresa', $codigo_empresa)
                                        ->where('pro_codigo', $codigo_liquidacion)
                                        ->update(['det_ven_cantidad' => $resultadoSuma]);
                                    // Descontar en las notas
                                    $getNota->det_ven_cantidad -= 1;
                                    $getNota->save();
                                    //si el codigo tiene codigo_union tambien le actualizo
                                    $codigoUpdate = CodigosLibros::where('codigo',$item->codigo)->first();
                                    $codigo_union = $codigoUpdate->codigo_union;
                                    if($codigo_union != null && $codigo_union != "null" && $codigo_union != ""){
                                        CodigosLibros::where('codigo', $codigo_union)
                                        ->update(['codigo_proforma' => $getPrefactura->ven_codigo]);
                                    }
                                    // Actualizar la pre factura en el código
                                    CodigosLibros::where('codigo', $item->codigo)
                                        ->update(['codigo_proforma' => $getPrefactura->ven_codigo]);
                                    // guardar en historico
                                    $mensajeHistorico = 'Se movio de la nota ' . $codigo_proforma . ' a la prefactura ' . $getPrefactura->ven_codigo;
                                    $this->GuardarEnHistorico(0,$id_institucion,$id_periodo,$item->codigo,$id_usuario,$mensajeHistorico,null,null,null,null);
                                    //GUARDAR EN HISTORICO PARA NOTAS
                                    $datos = (Object)[
                                        "descripcion"       => $item->codigo_liquidacion,
                                        "tipo"              => "1",
                                        "nueva_prefactura"  => $getPrefactura->ven_codigo,
                                        "cantidad"          => 1,
                                        "id_periodo"        => $id_periodo,
                                        "id_empresa"        => $codigo_empresa,
                                        "observacion"       => $mensajeHistorico,
                                        "user_created"      => $id_usuario
                                    ];
                                    $this->proformaRepository->saveHistoricoNotasMove($datos);
                                    $cambiados++;
                                }else{
                                    // No hay pre facturas disponibles
                                    $value->mensaje             = 'No hay suficientes pre facturas disponibles';
                                    $arrayCodigosProblemas[]    = $value;
                                }
                            }
                        }//fin foreach
                    }
                }
            }

            // Si no hay errores, se confirma la transacción
            DB::commit();
        } catch (\Exception $e) {
            // Si hay un error, se revierte la transacción
            DB::rollBack();
            throw $e; // O manejar el error de forma apropiada
        }

        return [
            'agrupadoPrenotas'                  => $agrupadoPrenotas,
            'arrayLibrosNoExistenEnNota'        => $arrayLibrosNoExistenEnNota,
            'arrayCodigosInsuficientesNotas'    => $arrayCodigosInsuficientesNotas,
            'arrayCodigosProblemas'             => $arrayCodigosProblemas,
            'cambiados'                         => $cambiados
        ];
    }



    //api:post/metodosEliminarDevolucion
    public function metodosEliminarDevolucion(Request $request){
        if($request->eliminarDocumentoDevolucion) { return $this->eliminarDocumentoDevolucion($request); }
    }
    //api:post/devoluciones?eliminarDocumentoDevolucion=1
    public function eliminarDocumentoDevolucion($request)
    {
        try {
            DB::beginTransaction();
            $id_documento = $request->input('id_documento');

            // Validar que el id_documento tenga estado 0 o 1, si no, no se puede eliminar porque ya fue finalizado
            $documento = CodigosLibrosDevolucionHeader::find($id_documento);
            if ($documento->estado == 1) {
                return response()->json(['status' => '0', 'message' => 'No se puede eliminar un revisado por que los codigos ya fueron devueltos'], 200);
            }
            if ($documento->estado == 2) {
                return response()->json(['status' => '0', 'message' => 'No se puede eliminar un documento finalizado'], 200);
            }

            // Primero encontrar los hijos, traer los códigos, y en la tabla CodigosLibros actualizar el documento_devolucion a null
            $codigos = CodigosLibrosDevolucionSon::where('codigoslibros_devolucion_id', $id_documento)->get();
            foreach ($codigos as $codigo) {
                CodigosLibros::where('codigo', $codigo->codigo)->update(['documento_devolucion' => null,'permitir_devolver_nota' => 0]);
            }
            
            // Eliminar los hijos
            CodigosLibrosDevolucionSon::where('codigoslibros_devolucion_id', $id_documento)->delete();

            // Eliminar el documento principal
            $documento = CodigosLibrosDevolucionHeader::find($id_documento);
            if ($documento) {
                $documento->delete();
                DB::commit(); // Confirmar la transacción
                return response()->json(['message' => 'Documento eliminado correctamente']);
            } else {
                return response()->json(['message' => 'Documento no encontrado'], 404);
            }
        } catch (\Exception $e) {
            DB::rollBack(); // Revertir la transacción en caso de error
            return response()->json(['status' => '0', 'message' => 'Error al eliminar el documento: ' . $e->getMessage()], 200);
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
