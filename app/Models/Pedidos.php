<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pedidos extends Model
{
    protected $table = "pedidos";
    protected $primaryKey = 'id_pedido';
    public function scopeActualizarElPedido($query, $id_pedido, $datos)
    {
        return $query->where('id_pedido', $id_pedido)->update($datos);
    }
}
