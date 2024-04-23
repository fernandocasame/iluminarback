<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ventas extends Model
{
    use HasFactory;
    protected $table = "f_venta";
    public $timestamps = false;
    protected $primaryKey = 'ven_codigo';
    protected $fillable = [
        'ven_codigo',
        'tip_ven_codigo',
        'est_ven_codigo',
        'ven_observacion',
        'ven_comision',
        'ven_valor',
        'ven_pagado',
        'ven_com_porcentaje',
        'ven_iva',
        'ven_descuento',
        'ven_fecha',
        'ven_idproforma',
        'ven_transporte',
        'ven_devolucion',
        'ven_remision',
        'ven_fech_remision',
        'institucion_id',
        'periodo_id',
        'ven_estado',
        'updated_at',
        'user_created',
    ];

}
