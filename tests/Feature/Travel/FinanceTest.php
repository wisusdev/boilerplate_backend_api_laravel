<?php

namespace Tests\Feature\Travel;

use App\Models\Booking;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Role;
use App\Models\Tour;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    private function apiJson(string $method, string $uri, array $payload = []): TestResponse
    {
        return $this->call($method, $uri, [], [], [], [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ], $payload ? json_encode($payload) : null);
    }

    private function plainJson(string $method, string $uri, array $payload = []): TestResponse
    {
        return $this->call($method, $uri, [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], $payload ? json_encode($payload) : null);
    }

    private function user(string $prefix = 'u'): User
    {
        return User::create([
            'username' => $prefix.uniqid(),
            'first_name' => 'Fin',
            'last_name' => 'Ance',
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password123'),
        ]);
    }

    private function admin(): User
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
        $admin = $this->user('admin');
        $admin->assignRole('admin');

        return $admin;
    }

    private function tour(string $title): Tour
    {
        return Tour::create([
            'title' => $title, 'description' => 'x', 'price' => 50,
            'max_capacity' => 20, 'location' => 'SV', 'currency_code' => 'USD', 'is_active' => true,
        ]);
    }

    private function category(string $name = 'Gasolina'): ExpenseCategory
    {
        return ExpenseCategory::create(['name' => $name, 'icon' => 'bi-fuel-pump']);
    }

    private function confirmedBooking(Tour $tour, float $total): Booking
    {
        return Booking::create([
            'user_id' => $this->user()->id,
            'bookable_type' => Tour::class,
            'bookable_id' => $tour->id,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3),
            'party_size' => 2,
            'total_price' => $total,
            'currency_code' => 'USD',
            'status' => Booking::STATUS_CONFIRMED,
        ]);
    }

    public function test_non_admin_cannot_access_finance(): void
    {
        Passport::actingAs($this->user());

        $this->apiJson('GET', '/api/v1/finance/summary')->assertForbidden();
        $this->apiJson('GET', '/api/v1/expenses')->assertForbidden();
    }

    public function test_admin_can_crud_expense_categories(): void
    {
        Passport::actingAs($this->admin());

        // Crear (slug auto).
        $create = $this->apiJson('POST', '/api/v1/expense-categories', [
            'data' => ['type' => 'expense-categories', 'attributes' => ['name' => 'Peaje', 'icon' => 'bi-signpost']],
        ]);
        $create->assertCreated()->assertJsonPath('data.attributes.slug', 'peaje');
        $id = $create->json('data.id');

        // Actualizar.
        $this->apiJson('PATCH', "/api/v1/expense-categories/{$id}", [
            'data' => ['type' => 'expense-categories', 'id' => (string) $id, 'attributes' => ['name' => 'Peajes']],
        ])->assertOk()->assertJsonPath('data.attributes.name', 'Peajes');

        // Listar.
        $this->apiJson('GET', '/api/v1/expense-categories')->assertOk()
            ->assertJsonPath('data.0.attributes.name', 'Peajes');

        // Eliminar.
        $this->apiJson('DELETE', "/api/v1/expense-categories/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('expense_categories', ['id' => $id]);
    }

    public function test_admin_can_create_expense_and_filter(): void
    {
        $admin = $this->admin();
        Passport::actingAs($admin);
        $tour = $this->tour('Santa Ana');
        $cat = $this->category();
        $guide = $this->user('guide');

        $create = $this->apiJson('POST', '/api/v1/expenses', [
            'data' => ['type' => 'expenses', 'attributes' => [
                'tour_id' => $tour->id,
                'expense_category_id' => $cat->id,
                'guide_id' => $guide->id,
                'amount' => 27.50,
                'comment' => 'Gasolina del tour',
            ]],
        ]);
        $create->assertCreated()
            ->assertJsonPath('data.attributes.amount', '27.50')
            ->assertJsonPath('data.attributes.tour_title', 'Santa Ana')
            ->assertJsonPath('data.attributes.category_name', 'Gasolina');
        // spent_at por defecto = hoy.
        $this->assertSame(now()->toDateString(), $create->json('data.attributes.spent_at'));

        // Otro gasto general (sin tour) y otra categoría.
        $cat2 = $this->category('Comida');
        Expense::create(['expense_category_id' => $cat2->id, 'amount' => 10, 'spent_at' => now()]);

        // Filtro por tour.
        $this->apiJson('GET', '/api/v1/expenses?tour_id='.$tour->id)->assertOk()
            ->assertJsonCount(1, 'data');
        // Filtro por categoría.
        $this->apiJson('GET', '/api/v1/expenses?expense_category_id='.$cat2->id)->assertOk()
            ->assertJsonCount(1, 'data');
        // Filtro por guía.
        $this->apiJson('GET', '/api/v1/expenses?guide_id='.$guide->id)->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_upload_receipt(): void
    {
        Storage::fake('public');
        Passport::actingAs($this->admin());
        $cat = $this->category();
        $expense = Expense::create(['expense_category_id' => $cat->id, 'amount' => 5, 'spent_at' => now()]);

        $response = $this->post(
            "/api/v1/expenses/{$expense->id}/receipt",
            ['image' => UploadedFile::fake()->image('recibo.jpg')],
            ['Accept' => 'application/json']
        );

        $response->assertOk();
        $this->assertNotNull($response->json('data.attributes.receipt_url'));
        $this->assertSame(1, $expense->fresh()->getMedia('receipt')->count());
    }

    /**
     * Regresión: un rol personalizado con expenses:update/expenses:delete pero
     * SIN expenses:view-all (una combinación distinta de la del preset
     * 'finanzas', pero válida vía RolesController) no debía poder tocar el
     * gasto de otro usuario. Antes update()/destroy() no comprobaban
     * ownership, solo el permiso de ruta.
     */
    private function customExpenseEditor(): User
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
        $user = $this->user('editor-gastos');
        $user->givePermissionTo(['expenses:index', 'expenses:update', 'expenses:delete']);

        return $user;
    }

    public function test_expense_update_bloquea_a_quien_no_ve_todo_y_no_es_el_dueno(): void
    {
        $cat = $this->category();
        $ajeno = $this->user('ajeno');
        $expense = Expense::create(['expense_category_id' => $cat->id, 'guide_id' => $ajeno->id, 'amount' => 5, 'spent_at' => now()]);

        Passport::actingAs($this->customExpenseEditor());

        $this->apiJson('PATCH', "/api/v1/expenses/{$expense->id}", [
            'data' => ['id' => (string) $expense->id, 'type' => 'expenses', 'attributes' => ['amount' => 9999]],
        ])->assertForbidden();

        $this->assertSame('5.00', $expense->fresh()->amount);
    }

    public function test_expense_destroy_bloquea_a_quien_no_ve_todo_y_no_es_el_dueno(): void
    {
        $cat = $this->category();
        $ajeno = $this->user('ajeno');
        $expense = Expense::create(['expense_category_id' => $cat->id, 'guide_id' => $ajeno->id, 'amount' => 5, 'spent_at' => now()]);

        Passport::actingAs($this->customExpenseEditor());

        $this->apiJson('DELETE', "/api/v1/expenses/{$expense->id}")->assertForbidden();

        $this->assertDatabaseHas('expenses', ['id' => $expense->id]);
    }

    public function test_expense_update_permite_al_dueno_editar_su_propio_gasto_sin_reasignarlo(): void
    {
        $cat = $this->category();
        $editor = $this->customExpenseEditor();
        $otro = $this->user('otro-guia');
        $expense = Expense::create(['expense_category_id' => $cat->id, 'guide_id' => $editor->id, 'amount' => 5, 'spent_at' => now()]);

        Passport::actingAs($editor);

        $response = $this->apiJson('PATCH', "/api/v1/expenses/{$expense->id}", [
            'data' => ['id' => (string) $expense->id, 'type' => 'expenses', 'attributes' => ['amount' => 12.34, 'guide_id' => $otro->id]],
        ]);

        $response->assertOk()->assertJsonPath('data.attributes.amount', '12.34');
        // guide_id enviado se ignora: no puede reasignar su propio gasto a otro guía.
        $this->assertSame($editor->id, $expense->fresh()->guide_id);
    }

    public function test_expense_destroy_permite_al_dueno_borrar_su_propio_gasto(): void
    {
        $cat = $this->category();
        $editor = $this->customExpenseEditor();
        $expense = Expense::create(['expense_category_id' => $cat->id, 'guide_id' => $editor->id, 'amount' => 5, 'spent_at' => now()]);

        Passport::actingAs($editor);

        $this->apiJson('DELETE', "/api/v1/expenses/{$expense->id}")->assertNoContent();
        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    public function test_admin_can_grant_and_revoke_guide_role(): void
    {
        Role::findOrCreate('guia', 'api');
        Passport::actingAs($this->admin());
        $person = $this->user('person');

        // Otorgar.
        $this->plainJson('POST', '/api/v1/guides', ['data' => ['attributes' => ['user_id' => $person->id]]])
            ->assertCreated()
            ->assertJsonPath('data.attributes.email', $person->email);
        $this->assertTrue($person->fresh()->hasRole('guia'));

        // Listar incluye al guía.
        $this->plainJson('GET', '/api/v1/guides')->assertOk()
            ->assertJsonPath('data.0.id', (string) $person->id);

        // Revocar.
        $this->plainJson('DELETE', "/api/v1/guides/{$person->id}")->assertNoContent();
        $this->assertFalse($person->fresh()->hasRole('guia'));
    }

    /**
     * Regresión: 'guia' otorga expenses:index/expenses:store (ver RoleSeeder).
     * Un rol personalizado con solo 'guides:store' (posible vía
     * RolesController) no debía poder usarse para auto-otorgarse esos
     * permisos, ni para otorgárselos a otro usuario. Antes GuideController no
     * comprobaba nada más allá del permiso de ruta.
     */
    public function test_guides_store_no_permite_auto_otorgarse_permisos_no_poseidos(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
        Role::findOrCreate('guia', 'api');
        $actor = $this->user('coordinador');
        $actor->givePermissionTo('guides:store');

        Passport::actingAs($actor);

        $this->plainJson('POST', '/api/v1/guides', ['data' => ['attributes' => ['user_id' => $actor->id]]])
            ->assertForbidden();

        $this->assertFalse($actor->fresh()->hasRole('guia'));
    }

    public function test_guides_store_no_permite_otorgar_guia_a_otro_sin_los_permisos_que_conlleva(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
        Role::findOrCreate('guia', 'api');
        $actor = $this->user('coordinador');
        $actor->givePermissionTo('guides:store');
        $victima = $this->user('victima');

        Passport::actingAs($actor);

        $this->plainJson('POST', '/api/v1/guides', ['data' => ['attributes' => ['user_id' => $victima->id]]])
            ->assertForbidden();

        $this->assertFalse($victima->fresh()->hasRole('guia'));
    }

    public function test_guides_store_permite_otorgar_guia_si_el_actor_ya_tiene_esos_permisos(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
        Role::findOrCreate('guia', 'api');
        $actor = $this->user('coordinador-completo');
        $actor->givePermissionTo(['guides:store', 'expenses:index', 'expenses:store']);
        $victima = $this->user('nuevo-guia');

        Passport::actingAs($actor);

        $this->plainJson('POST', '/api/v1/guides', ['data' => ['attributes' => ['user_id' => $victima->id]]])
            ->assertCreated();

        $this->assertTrue($victima->fresh()->hasRole('guia'));
    }

    public function test_finance_summary_computes_margin_and_ranking(): void
    {
        Passport::actingAs($this->admin());
        $cat = $this->category();

        $tourA = $this->tour('Tour A');
        $tourB = $this->tour('Tour B');

        // Ingreso: reserva confirmada de 100 para Tour A (y una pendiente que NO cuenta).
        $this->confirmedBooking($tourA, 100);
        Booking::create([
            'user_id' => $this->user()->id, 'bookable_type' => Tour::class, 'bookable_id' => $tourA->id,
            'starts_at' => now(), 'ends_at' => now(), 'party_size' => 1, 'total_price' => 999,
            'currency_code' => 'USD', 'status' => Booking::STATUS_PENDING,
        ]);

        // Gastos: 30 a Tour A + 20 general (sin tour).
        Expense::create(['tour_id' => $tourA->id, 'expense_category_id' => $cat->id, 'amount' => 30, 'spent_at' => now()]);
        Expense::create(['expense_category_id' => $cat->id, 'amount' => 20, 'spent_at' => now()]);

        $res = $this->apiJson('GET', '/api/v1/finance/summary')->assertOk();

        // Totales: ingreso 100 (solo confirmadas), gastos 50, margen 50.
        $res->assertJsonPath('data.attributes.totals.income', 100)
            ->assertJsonPath('data.attributes.totals.expenses', 50)
            ->assertJsonPath('data.attributes.totals.margin', 50);

        // Ranking por margen: Tour A (70), Tour B (0), Gastos generales (-20).
        $res->assertJsonPath('data.attributes.tours.0.name', 'Tour A')
            ->assertJsonPath('data.attributes.tours.0.income', 100)
            ->assertJsonPath('data.attributes.tours.0.expenses', 30)
            ->assertJsonPath('data.attributes.tours.0.margin', 70)
            ->assertJsonPath('data.attributes.tours.0.label', 'rentable');

        $tours = $res->json('data.attributes.tours');
        $last = end($tours);
        $this->assertSame('Gastos generales', $last['name']);
        $this->assertSame(-20, $last['margin']);
        $this->assertSame('sin_ingresos', $last['label']);

        // Gráfico de gastos por categoría.
        $res->assertJsonPath('data.attributes.charts.expenses_by_category.0.name', 'Gasolina')
            ->assertJsonPath('data.attributes.charts.expenses_by_category.0.total', 50);
    }
}
