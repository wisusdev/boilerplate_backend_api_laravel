<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

	protected $fillable = [
		'user_id',
		'created_by',
		'invoice_number',
		'invoice_date',
		'due_date',
		'total_amount',
		'status',
		'payment_method',
		'send_email',
	];

	public function items(): HasMany
	{
		return $this->hasMany(InvoiceItem::class);
	}

	public function user(): BelongsTo
	{
		return $this->belongsTo(User::class);
	}

	public function createdBy(): BelongsTo
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	protected static function boot(): void
	{
		parent::boot();

		static::creating(function ($invoice) {
			$latestInvoice = self::latest('created_at')->first();
			$invoice->invoice_number = $latestInvoice ? $latestInvoice->invoice_number + 1 : 1;
		});
	}
}
