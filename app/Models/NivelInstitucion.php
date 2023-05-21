<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NivelInstitucion extends Model
{
    protected $table = "mat_niveles_institucion";
    protected $primaryKey = 'nivelInstitucion_id';
    use HasFactory;
}
