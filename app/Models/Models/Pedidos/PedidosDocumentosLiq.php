<?php

namespace App\Models\Models\Pedidos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PedidosDocumentosLiq extends Model
{
    use HasFactory;
    protected $table        = "1_4_documento_liq";
    protected $primaryKey   = "doc_codigo";
    protected $fillable     = [
        "doc_valor",
        "doc_numero",
        "doc_nombre",
        "doc_ci",
        "doc_cuenta",
        "doc_institucion",
        "doc_tipo",
        "doc_observacion",
        "ven_codigo",
        "doc_fecha"
    ];
    public $timestamps = false;
}
