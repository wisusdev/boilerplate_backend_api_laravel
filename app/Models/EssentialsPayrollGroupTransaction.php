<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EssentialsPayrollGroupTransaction extends Model
{
    use HasFactory;

    public function essentials_payroll_group()
    {
        return $this->belongsTo(EssentialsPayrollGroup::class, 'payroll_group_id');
    }

    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'transaction_id' => 'integer'
        ];
    }
}
