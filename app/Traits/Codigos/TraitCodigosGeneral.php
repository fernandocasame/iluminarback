<?php
namespace App\Traits\Codigos;

use App\Models\HistoricoCodigos;
use DB;
trait TraitCodigosGeneral{
    public $columnasSeleccionadas = [
        'contrato',
        'codigo',
        'bc_estado',
        'estado',
        'estado_liquidacion',
        'contador',
        'venta_estado',
        'bc_periodo',
        'bc_institucion',
        'idusuario',
        'id_periodo',
        'contrato',
        'libro',
        "IF(c.estado = '2', 'bloqueado', 'activo') AS codigoEstado",
        "CASE
            WHEN c.estado_liquidacion = '0' THEN 'liquidado'
            WHEN c.estado_liquidacion = '1' THEN 'sin liquidar'
            WHEN c.estado_liquidacion = '2' THEN 'codigo regalado'
            WHEN c.estado_liquidacion = '3' THEN 'codigo devuelto'
        END AS liquidacion",
        "CASE
            WHEN c.bc_estado = '2' THEN 'codigo leido'
            WHEN c.bc_estado = '1' THEN 'codigo sin leer'
        END AS barrasEstado",
        "CASE
            WHEN c.codigos_barras = '1' THEN 'con código de barras'
            WHEN c.codigos_barras = '0' THEN 'sin código de barras'
        END AS status",
        "CASE
            WHEN c.venta_estado = '0' THEN ''
            WHEN c.venta_estado = '1' THEN 'Venta directa'
            WHEN c.venta_estado = '2' THEN 'Venta por lista'
        END AS ventaEstado",
        'ib.nombreInstitucion AS institucionBarra',
        'pb.periodoescolar AS periodo_barras',
        'ivl.nombreInstitucion AS InstitucionLista'
    ];
    public function makeid($longitud){
        $characters = ['A','B','C','D','E','F','G','H','K','M','N','P','R','S','T','U','V','W','X','Y','Z','2','3','4','5','6','7','8','9'];
        shuffle($characters);
        $charactersLength = count($characters);
        $randomString = '';
        for ($i = 0; $i < $longitud; $i++) {
            $pos_rand = rand(0, ($charactersLength-1));
            $randomString .= $characters[$pos_rand];
        }
        return $randomString;
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
            (
                SELECT 
                    (case when (ci.verif1 > '0') then 'verif1'
                    when (ci.verif2 > 0) then 'verif2'
                    when (ci.verif3 > 0) then 'verif3'
                    when (ci.verif4 > 0) then 'verif4'
                    when (ci.verif5 > 0) then 'verif5'
                    when (ci.verif6 > 0) then 'verif6'
                    when (ci.verif7 > 0) then 'verif7'
                    when (ci.verif8 > 0) then 'verif8'
                    when (ci.verif9 > 0) then 'verif9'
                    when (ci.verif10 > 0) then 'verif10'
                    end) as verificacion
                FROM codigoslibros ci
                WHERE ci.codigo = c.codigo
            ) AS verificacion,
            ib.nombreInstitucion as institucionBarra, i.nombreInstitucion,
            p.periodoescolar as periodo,
            pb.periodoescolar as periodo_barras,ivl.nombreInstitucion as InstitucionLista,
            c.codigo_paquete,c.fecha_registro_paquete
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
                "codigo"                        => $item->codigo,
                "InstitucionLista"              => $item->InstitucionLista,
                "barrasEstado"                  => $item->barrasEstado,
                "bc_estado"                     => $item->bc_estado,
                "bc_fecha_ingreso"              => $item->bc_fecha_ingreso,
                "bc_institucion"                => $item->bc_institucion,
                "bc_periodo"                    => $item->bc_periodo,
                "book"                          => $item->book,
                "cedula"                        => $item->cedula,
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
                "codigo_paquete"                => $item->codigo_paquete,
                "fecha_registro_paquete"        => $item->fecha_registro_paquete,
                "verificacion"                  => $item->verificacion
            ];
        }
        return $datos;
    }
    public function GuardarEnHistorico ($id_usuario,$institucion_id,$periodo_id,$codigo,$usuario_editor,$comentario,$old_values,$new_values,$devueltos_liquidados=null,$verificacion_liquidada=null){
        $historico = new HistoricoCodigos();
        $historico->id_usuario              =  $id_usuario;
        $historico->usuario_editor          =  $institucion_id;
        $historico->id_periodo              =  $periodo_id;
        $historico->codigo_libro            =  $codigo;
        $historico->idInstitucion           =  $usuario_editor;
        $historico->observacion             =  $comentario;
        $historico->old_values              =  $old_values;
        $historico->new_values              =  $new_values;
        $historico->devueltos_liquidados    = $devueltos_liquidados;
        $historico->verificacion_liquidada  = $verificacion_liquidada;
        $historico->save();
    }
    public function PeriodoInstitucion($institucion){
        $periodoInstitucion = DB::SELECT("SELECT idperiodoescolar AS periodo ,
            periodoescolar AS descripcion,region_idregion as region,estado
            FROM periodoescolar
            WHERE idperiodoescolar = (
            SELECT  pir.periodoescolar_idperiodoescolar as id_periodo
            from institucion i,  periodoescolar_has_institucion pir
            WHERE i.idInstitucion = pir.institucion_idInstitucion
            AND pir.id = (SELECT MAX(phi.id) AS periodo_maximo FROM periodoescolar_has_institucion phi
            WHERE phi.institucion_idInstitucion = i.idInstitucion
            AND i.idInstitucion = '$institucion'))
        ");
        return $periodoInstitucion;
    }
}
?>
