<?php

namespace App\Models\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un modelo que emite DTE (factura, nota, documento de compra). Lo implementan
 * con el trait HasDteDocuments.
 *
 * @mixin Model
 */
interface DteOwner
{
    public function dteForeignKey(): string;

    public function dteDocuments(): HasMany;

    /** @param  array<string, mixed>  $attrs */
    public function applyDteSummary(array $attrs): void;
}
