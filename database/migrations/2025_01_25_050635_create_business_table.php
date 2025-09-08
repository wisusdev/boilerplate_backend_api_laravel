<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 191);

            $table->unsignedInteger('currency_id')->index('business_currency_id_foreign')->comment('Moneda predeterminada para el negocio');
            $table->unsignedInteger('default_sales_tax')->nullable()->index('business_default_sales_tax_foreign')->comment('Impuesto predeterminado para las ventas');
            $table->uuid('owner_id')->index('business_owner_id_foreign')->comment('Propietario del negocio');
            $table->unsignedInteger('purchase_currency_id')->nullable()->comment('Moneda en la que se realiza la compra de productos');
            $table->unsignedInteger('transaction_edit_days')->default(30)->comment('Días permitidos para editar una transacción');
            $table->unsignedInteger('stock_expiry_alert_days')->default(30)->comment('Días para alertar sobre la expiración de stock');

            $table->date('start_date')->nullable()->comment('Fecha de inicio del negocio');
            $table->string('tax_number_1', 100)->nullable()->comment('Número de identificación fiscal del negocio');
            $table->string('tax_label_1', 10)->nullable()->comment('Etiqueta del número de identificación fiscal');
            $table->string('tax_number_2', 100)->nullable()->comment('Número de identificación fiscal adicional del negocio');
            $table->string('tax_label_2', 10)->nullable()->comment('Etiqueta del número de identificación fiscal adicional');
            $table->string('code_label_1', 191)->nullable()->comment('Etiqueta del código de negocio');
            $table->string('code_1', 191)->nullable()->comment('Código de negocio');
            $table->string('code_label_2', 191)->nullable()->comment('Etiqueta del segundo código de negocio');
            $table->string('code_2', 191)->nullable()->comment('Segundo código de negocio');
            $table->double('default_profit_percent')->default(0)->comment('Porcentaje de ganancia predeterminado para el negocio');
            $table->string('time_zone', 191)->default('Asia/Kolkata')->comment('Zona horaria del negocio');
            $table->tinyInteger('fy_start_month')->default(1)->comment('Mes de inicio del año fiscal');
            $table->enum('accounting_method', ['fifo', 'lifo', 'avco'])->default('fifo')->comment('Método contable utilizado por el negocio');
            $table->decimal('default_sales_discount', 5)->nullable()->comment('Descuento de ventas predeterminado para el negocio');
            $table->enum('sell_price_tax', ['includes', 'excludes'])->default('includes')->comment('Tipo de impuesto aplicado al precio de venta');
            $table->string('logo', 191)->nullable()->comment('Logo del negocio');
            $table->string('sku_prefix', 191)->nullable()->comment('Prefijo para el SKU de los productos');
            $table->boolean('enable_product_expiry')->default(false)->comment('Habilitar la expiración de productos');
            $table->enum('expiry_type', ['add_expiry', 'add_manufacturing'])->default('add_expiry')->comment('Tipo de expiración de productos');
            $table->enum('on_product_expiry', ['keep_selling', 'stop_selling', 'auto_delete'])->default('keep_selling')->comment('Acción a realizar cuando un producto expira');
            $table->integer('stop_selling_before')->default(0)->comment('Detener la venta de productos expirados n días antes de la fecha de vencimiento');
            $table->boolean('enable_tooltip')->default(true)->comment('Habilitar información sobre herramientas para productos');
            $table->boolean('purchase_in_diff_currency')->default(false)->comment('Permitir la compra en una moneda diferente a la moneda del negocio');
            $table->decimal('p_exchange_rate', 20, 3)->default(1)->comment('Tasa de cambio para la compra en una moneda diferente');
            $table->text('keyboard_shortcuts')->nullable()->comment('Atajos de teclado para el sistema');
            $table->text('pos_settings')->nullable()->comment('Configuración del punto de venta');
            $table->text('weighing_scale_setting')->nullable()->comment('Configuración de la balanza de pesaje');
            $table->longText('essentials_settings')->nullable();
            $table->boolean('enable_brand')->default(true);
            $table->boolean('enable_category')->default(true);
            $table->boolean('enable_sub_category')->default(true);
            $table->boolean('enable_price_tax')->default(true);
            $table->boolean('enable_purchase_status')->nullable()->default(true);
            $table->boolean('enable_lot_number')->default(false);
            $table->integer('default_unit')->nullable();
            $table->boolean('enable_sub_units')->default(false);
            $table->boolean('enable_racks')->default(false);
            $table->boolean('enable_row')->default(false);
            $table->boolean('enable_position')->default(false);
            $table->boolean('enable_editing_product_from_purchase')->default(true);
            $table->enum('sales_cmsn_agnt', ['logged_in_user', 'user', 'cmsn_agnt'])->nullable();
            $table->boolean('item_addition_method')->default(true);
            $table->boolean('enable_inline_tax')->default(true);
            $table->enum('currency_symbol_placement', ['before', 'after'])->default('before');
            $table->text('enabled_modules')->nullable();
            $table->string('date_format', 191)->default('m/d/Y');
            $table->enum('time_format', ['12', '24'])->default('24');
            $table->text('ref_no_prefixes')->nullable();
            $table->char('theme_color', 20)->nullable();
            $table->uuid('created_by')->nullable();
            $table->boolean('enable_rp')->default(false)->comment('rp is the short form of reward points');
            $table->string('rp_name', 191)->nullable()->comment('rp is the short form of reward points');
            $table->decimal('amount_for_unit_rp', 22, 4)->default(1)->comment('rp is the short form of reward points');
            $table->decimal('min_order_total_for_rp', 22, 4)->default(1)->comment('rp is the short form of reward points');
            $table->integer('max_rp_per_order')->nullable()->comment('rp is the short form of reward points');
            $table->decimal('redeem_amount_per_unit_rp', 22, 4)->default(1)->comment('rp is the short form of reward points');
            $table->decimal('min_order_total_for_redeem', 22, 4)->default(1)->comment('rp is the short form of reward points');
            $table->integer('min_redeem_point')->nullable()->comment('rp is the short form of reward points');
            $table->integer('max_redeem_point')->nullable()->comment('rp is the short form of reward points');
            $table->integer('rp_expiry_period')->nullable()->comment('rp is the short form of reward points');
            $table->enum('rp_expiry_type', ['month', 'year'])->default('year')->comment('rp is the short form of reward points');
            $table->text('email_settings')->nullable();
            $table->text('sms_settings')->nullable();
            $table->text('custom_labels')->nullable();
            $table->text('common_settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->tinyInteger('currency_precision')->default(2);
            $table->tinyInteger('quantity_precision')->default(2);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
