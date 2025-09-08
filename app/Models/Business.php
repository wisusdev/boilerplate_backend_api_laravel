<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;

class Business extends Model
{
    use HasFactory, HasRoles, SoftDeletes;

    protected $table = 'businesses';

    protected $fillable = [
        'name',
        'currency_id',
        'start_date',
        'tax_number_1',
        'tax_label_1',
        'tax_number_2',
        'tax_label_2',
        'code_label_1',
        'code_1',
        'code_label_2',
        'code_2',
        'default_sales_tax',
        'default_profit_percent',
        'owner_id',
        'time_zone',
        'fy_start_month',
        'accounting_method',
        'default_sales_discount',
        'sell_price_tax',
        'logo',
        'sku_prefix',
        'enable_product_expiry',
        'expiry_type',
        'on_product_expiry',
        'stop_selling_before',
        'enable_tooltip',
        'purchase_in_diff_currency',
        'purchase_currency_id',
        'p_exchange_rate',
        'transaction_edit_days',
        'stock_expiry_alert_days',
        'keyboard_shortcuts',
        'pos_settings',
        'weighing_scale_setting',
        'essentials_settings',
        'enable_brand',
        'enable_category',
        'enable_sub_category',
        'enable_price_tax',
        'enable_purchase_status',
        'enable_lot_number',
        'default_unit',
        'enable_sub_units',
        'enable_racks',
        'enable_row',
        'enable_position',
        'enable_editing_product_from_purchase',
        'sales_cmsn_agnt',
        'item_addition_method',
        'enable_inline_tax',
        'currency_symbol_placement',
        'enabled_modules',
        'date_format',
        'time_format',
        'ref_no_prefixes',
        'theme_color',
        'created_by',
        'enable_rp',
        'rp_name',
        'amount_for_unit_rp',
        'min_order_total_for_rp',
        'max_rp_per_order',
        'redeem_amount_per_unit_rp',
        'min_order_total_for_redeem',
        'min_redeem_point',
        'max_redeem_point',
        'rp_expiry_period',
        'rp_expiry_type',
        'email_settings',
        'sms_settings',
        'custom_labels',
        'common_settings',
        'is_active',
        'currency_precision',
        'quantity_precision',
    ];

    public function getResourceType(): string
    {
        return 'businesses';
    }

    public function variation_templates(): HasMany
    {
        return $this->hasMany(VariationTemplate::class, 'business_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'business_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'business_id');
    }

    public function tax_rates(): HasMany
    {
        return $this->hasMany(TaxRate::class, 'business_id');
    }

    public function taxRates(): HasMany
    {
        return $this->tax_rates();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'business_id');
    }

    public function selling_price_groups(): HasMany
    {
        return $this->hasMany(SellingPriceGroup::class, 'business_id');
    }

    public function res_tables(): HasMany
    {
        return $this->hasMany(ResTable::class, 'business_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'business_id');
    }

    public function printers(): HasMany
    {
        return $this->hasMany(Printer::class, 'business_id');
    }

    public function invoice_schemes(): HasMany
    {
        return $this->hasMany(InvoiceScheme::class, 'business_id');
    }

    public function invoice_layouts(): HasMany
    {
        return $this->hasMany(InvoiceLayout::class, 'business_id');
    }

    public function expense_categories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class, 'business_id');
    }

    public function dashboard_configurations(): HasMany
    {
        return $this->hasMany(DashboardConfiguration::class, 'business_id');
    }

    public function customer_groups(): HasMany
    {
        return $this->hasMany(CustomerGroup::class, 'business_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'business_id');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'business_id');
    }

    public function cash_registers(): HasMany
    {
        return $this->hasMany(CashRegister::class, 'business_id');
    }

    public function business_locations(): HasMany
    {
        return $this->hasMany(BusinessLocation::class, 'business_id');
    }

    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class, 'business_id');
    }

    // Nuevas relaciones agregadas
    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function purchase_currency()
    {
        return $this->belongsTo(Currency::class, 'purchase_currency_id');
    }

    public function default_sales_tax_rate()
    {
        return $this->belongsTo(TaxRate::class, 'default_sales_tax');
    }

    public function default_unit_relation()
    {
        return $this->belongsTo(Unit::class, 'default_unit');
    }

    protected function casts(): array
    {
        return [
            'name' => 'string',
            'currency_id' => 'integer',
            'start_date' => 'date',
            'tax_number_1' => 'string',
            'tax_label_1' => 'string',
            'tax_number_2' => 'string',
            'tax_label_2' => 'string',
            'code_label_1' => 'string',
            'code_1' => 'string',
            'code_label_2' => 'string',
            'code_2' => 'string',
            'default_sales_tax' => 'integer',
            'default_profit_percent' => 'double',
            'owner_id' => 'integer',
            'time_zone' => 'string',
            'fy_start_month' => 'integer',
            'default_sales_discount' => 'decimal:2',
            'logo' => 'string',
            'sku_prefix' => 'string',
            'enable_product_expiry' => 'boolean',
            'stop_selling_before' => 'integer',
            'enable_tooltip' => 'boolean',
            'purchase_in_diff_currency' => 'boolean',
            'purchase_currency_id' => 'integer',
            'p_exchange_rate' => 'decimal:3',
            'transaction_edit_days' => 'integer',
            'stock_expiry_alert_days' => 'integer',
            'enable_brand' => 'boolean',
            'enable_category' => 'boolean',
            'enable_sub_category' => 'boolean',
            'enable_price_tax' => 'boolean',
            'enable_purchase_status' => 'boolean',
            'enable_lot_number' => 'boolean',
            'default_unit' => 'integer',
            'enable_sub_units' => 'boolean',
            'enable_racks' => 'boolean',
            'enable_row' => 'boolean',
            'enable_position' => 'boolean',
            'enable_editing_product_from_purchase' => 'boolean',
            'item_addition_method' => 'boolean',
            'enable_inline_tax' => 'boolean',
            'date_format' => 'string',
            'currency_precision' => 'integer',
            'quantity_precision' => 'integer',
            'created_by' => 'integer',
            'enable_rp' => 'boolean',
            'rp_name' => 'string',
            'amount_for_unit_rp' => 'decimal:4',
            'min_order_total_for_rp' => 'decimal:4',
            'max_rp_per_order' => 'integer',
            'redeem_amount_per_unit_rp' => 'decimal:4',
            'min_order_total_for_redeem' => 'decimal:4',
            'min_redeem_point' => 'integer',
            'max_redeem_point' => 'integer',
            'rp_expiry_period' => 'integer',
            'is_active' => 'boolean'
        ];
    }
}
