<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

	protected $fillable = [
		'user_id',
		'created_by',
		'invoice_number',
		'invoice_date',
		'due_date',
		'total_amount',
		'status',
	];
}
