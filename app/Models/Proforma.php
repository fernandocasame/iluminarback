<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proforma extends Model
{
    use HasFactory;
    protected $table = "f_proforma";
    protected $primaryKey = 'prof_id';
    protected $fillable = [
        'prof_id',
        'usu_codigo',
        'pedido_id',
        'emp_id',
        'prof_observacion',
        'prof_descuento',
        'pro_des_por',
        'prof_iva',
        'prof_iva_por',
        'prof_total',
        'prof_estado',
        'prof_tipo_proforma',
        'created_at',
        'updated_at',
    ];
}
