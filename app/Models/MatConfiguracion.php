<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatConfiguracion extends Model
{
    use HasFactory;
    protected $table = "mat_configuracion_institucion";
    protected $primaryKey = 'id';

}
