<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jawaban siap pakai untuk pertanyaan yang berulang (KEP-030). Klien: untuk
 * pesanan custom, CS "tinggal menjawab sesuai template".
 */
class TemplateBalasan extends Model
{
    protected $table = 'template_balasan';

    protected $fillable = ['judul', 'isi', 'urutan'];
}
