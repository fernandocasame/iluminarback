<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsuarioTarea extends Model
{
    use HasFactory;
    protected $table      = "usuario_tarea";
    protected $primaryKey = "id";
    public $timestamps = false;
}
