<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatConfiguracionQuimestre extends Model
{
    use HasFactory;
    protected $table = "mat_quimestres";
    protected $primaryKey = 'id';
}
