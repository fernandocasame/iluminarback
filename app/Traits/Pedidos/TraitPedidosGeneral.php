<?php

namespace App\Traits\Pedidos;
use DB;
use Illuminate\Support\Facades\Http;
trait TraitPedidosGeneral
{
    public function FacturacionGet($endpoint)
    {
        $dato = Http::get("http://186.4.218.168:9095/api/".$endpoint);
        return $JsonContrato = json_decode($dato, true);
    }
    public function FacturacionPost($endpoint,$data){
        $dato = Http::post("http://186.4.218.168:9095/api/".$endpoint,$data);
        return $JsonContrato = json_decode($dato, true);
    }
    public function getPedidoXID($id_pedido){
        $pedido = DB::SELECT("SELECT p.*, pe.periodoescolar as periodo,
        pe.region_idregion as region, CONCAT(u.nombres, ' ', u.apellidos) AS asesor,
        u.cedula as cedula_asesor,
        u.iniciales, i.nombreInstitucion,i.telefonoInstitucion,
        i.direccionInstitucion, i.ruc, i.tipo_descripcion,
        i.nivel, c.nombre AS nombre_ciudad, uf.cod_usuario,
        CONCAT(uf.apellidos, ' ',uf.nombres) as facturador, ph.fecha_generar_contrato,
        pe.codigo_contrato
        FROM pedidos p
        INNER JOIN usuario u ON p.id_asesor = u.idusuario
        LEFT JOIN usuario uf ON p.id_usuario_verif = uf.idusuario
        LEFT JOIN institucion i ON p.id_institucion = i.idInstitucion
        LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
        LEFT JOIN periodoescolar pe ON p.id_periodo = pe.idperiodoescolar
        LEFT JOIN pedidos_historico ph ON p.id_pedido = ph.id_pedido
        WHERE p.id_pedido = '$id_pedido'
        LIMIT 1
        ");
        return $pedido;
    }
    public function getVerificaciones($contrato){
        $query = DB::SELECT("SELECT * FROM verificaciones
            WHERE contrato =  '$contrato'
            and nuevo = '1'
            and estado = '0'
        ");
        return $query;
    }
    public function getAllBeneficiarios($id_pedido)
    {
        $query = DB::SELECT("SELECT  b.*,
        CONCAT(u.nombres, ' ',u.apellidos) AS beneficiario,
        u.cedula,u.nombres,u.apellidos,p.descuento,p.total_venta,p.contrato_generado
         FROM pedidos_beneficiarios b
         LEFT JOIN pedidos p ON b.id_pedido = p.id_pedido
         LEFT JOIN usuario u ON  b.id_usuario = u.idusuario
        WHERE b.id_pedido = '$id_pedido'
        ");
        return $query;
    }
}
