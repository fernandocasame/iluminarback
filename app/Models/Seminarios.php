<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Seminarios extends Model
{
    use HasFactory;
    protected $table = "seminarios";
    protected $primaryKey = 'id_seminario';
    protected $fillable = [
        'nombre',
        'descripcion',
        'fecha_inicio',
        'fecha_fin',
        'link_reunion',
        'id_institucion',
        'estado',
        'capacitador',
        'cant_asistentes',
        'asistencia_activa',
    ];
}
