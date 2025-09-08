<?php

namespace App\Utils;

use App\Models\Business;
use App\Models\ReferenceCount;
use Carbon\Carbon;

class GeneralUtils
{
    public function setAndGetReferenceCount($type, $business_id)
    {
        $ref = ReferenceCount::where('ref_type', $type)->where('business_id', $business_id)->first();

        if (!empty($ref)) {
            $ref->ref_count += 1;
            $ref->save();

            return $ref->ref_count;
        } else {
            $new_ref = ReferenceCount::create([
                'ref_type' => $type,
                'business_id' => $business_id,
                'ref_count' => 1,
            ]);

            return $new_ref->ref_count;
        }
    }

    public function generateReferenceNumber($type, $ref_count, $business_id, $default_prefix = null): string
    {
        $prefix = '';

        if (!empty($business_id)) {
            $business = Business::find($business_id);
            $prefixes = $business->ref_no_prefixes;
            $prefix = ! empty($prefixes[$type]) ? $prefixes[$type] : '';
        }

        if (!empty($default_prefix)) {
            $prefix = $default_prefix;
        }

        $ref_digits = str_pad($ref_count, 4, 0, STR_PAD_LEFT);

        if (!in_array($type, ['contacts', 'business_location', 'username'])) {
            $ref_year = Carbon::now()->year;
            $ref_number = $prefix . $ref_year . '/' . $ref_digits;
        } else {
            $ref_number = $prefix . $ref_digits;
        }

        return $ref_number;
    }
}
