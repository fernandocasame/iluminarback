<?php 
 
namespace App\Traits\Pedidos; 
use DB; 
trait TraitPedidosGeneral 
{ 
    public function getAllBeneficiarios($id_pedido) 
    { 
        $query = DB::SELECT("SELECT  b.*,
        CONCAT(u.nombres, ' ',u.apellidos) AS beneficiario,
        u.cedula,u.nombres,u.apellidos,p.descuento,p.total_venta
         FROM pedidos_beneficiarios b
         LEFT JOIN pedidos p ON b.id_pedido = p.id_pedido
         LEFT JOIN usuario u ON  b.id_usuario = u.idusuario
        WHERE b.id_pedido = '$id_pedido'
        ");
        return $query;
    } 
}