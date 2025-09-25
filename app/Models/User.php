<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, SoftDeletes, HasRoles, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_type',
        'business_id',
        'crm_contact_id',
        'first_name',
        'last_name',
        'username',
        'email',
        'password',
        'avatar',
        'language',
        'contact_no',
        'address',
        'max_sales_discount_percent',
        'allow_login',
        'essentials_department_id',
        'essentials_designation_id',
        'essentials_salary',
        'essentials_pay_period',
        'essentials_pay_cycle',
        'status',
        'is_cmmsn_agnt',
        'cmmsn_percent',
        'selected_contacts',
        'dob',
        'gender',
        'marital_status',
        'blood_group',
        'contact_number',
        'alt_number',
        'family_number',
        'fb_link',
        'twitter_link',
        'social_media_1',
        'social_media_2',
        'permanent_address',
        'current_address',
        'guardian_name',
        'custom_field_1',
        'custom_field_2',
        'custom_field_3',
        'custom_field_4',
        'bank_details',
        'id_proof_name',
        'id_proof_number',
        'location_id',
        'is_enable_service_staff_pin',
        'service_staff_pin',
        'available_at',
        'paused_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'service_staff_pin',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'max_sales_discount_percent' => 'decimal:5',
            'essentials_salary' => 'decimal:22,4',
            'cmmsn_percent' => 'decimal:4',
            'available_at' => 'datetime',
            'paused_at' => 'datetime',
            'dob' => 'date',
        ];
    }

    /**
     * Get the businesses owned by the user.
     */
    public function businesses()
    {
        return $this->hasMany(Business::class, 'owner_id');
    }
}
