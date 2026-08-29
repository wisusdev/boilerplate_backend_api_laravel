<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Expense extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'tour_id',
        'expense_category_id',
        'guide_id',
        'user_id',
        'amount',
        'comment',
        'spent_at',
        'currency_code',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'spent_at' => 'date',
    ];

    public function registerMediaCollections(): void
    {
        // Foto del recibo (una sola).
        $this->addMediaCollection('receipt')->singleFile();
    }

    /** Tour al que se imputa el gasto (NULL = gasto general). */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /** Guía (usuario con rol 'guia') al que se atribuye el gasto. */
    public function guide(): BelongsTo
    {
        // Igual que en Booking: un guía dado de baja no debe ocultar sus gastos.
        return $this->belongsTo(User::class, 'guide_id')->withTrashed();
    }

    /** Usuario que registró el gasto. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** URL de la foto del recibo, o null si no hay. */
    public function receiptUrl(): ?string
    {
        $media = $this->getFirstMedia('receipt');

        return $media ? $media->getUrl() : null;
    }

    public function getResourceType(): string
    {
        return 'expenses';
    }
}
