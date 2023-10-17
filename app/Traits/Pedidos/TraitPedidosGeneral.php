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