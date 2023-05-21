<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LinkAcortador extends Model
{
    use HasFactory;
    protected $table = "links_acortadores";
    protected $primaryKey = 'id';
    protected $fillable = [
        'libro_id',
        'unidad',
        'pagina',
        'link_original',
        'link_acortado',
        'codigo',
        'usuario_editor',
        'estado',
    ];
}
