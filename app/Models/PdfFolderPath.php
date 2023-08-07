<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdfFolderPath extends Model
{
    use HasFactory;
    protected  $table = 'pdf_folder_path';
    //public $timestamps = true;

    protected $fillable = [
        'name',
        'path'
    ];
}
