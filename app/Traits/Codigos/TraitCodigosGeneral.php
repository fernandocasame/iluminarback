<?php
namespace App\Traits\Codigos; 
use DB;
trait TraitCodigosGeneral{
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
            ];
        }
        return $datos;
    }
} 
?>