<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternshipCertificate extends Model
{
    protected $fillable = ['user_id', 'name', 'program', 'location', 'institution', 'birth_date', 'start_date', 'end_date', 'issued_date'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'issued_date' => 'date',
            'birth_date' => 'date',
        ];
    }

    public function getNumberAttribute(): string
    {
        $months = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

        return sprintf('%03d/Sertifikat/PKL/%s/%s', $this->id + 21, $months[$this->issued_date->month - 1], $this->issued_date->year);
    }
}
