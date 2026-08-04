<?php

namespace Tests\Feature\Painel;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CadastrosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-02 12:00:00', config('barbearia.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function owner(): User
    {
        return User::factory()->owner()->create();
    }

    public function test_cria_e_edita_servico(): void
    {
        $this->actingAs($this->owner())->post('/painel/servicos', [
            'name' => 'Barba desenhada',
            'description' => 'Navalha e toalha quente',
            'duration_min' => 25,
            'price_cents' => 3500,
        ])->assertRedirect();

        $service = Service::firstOrFail();
        $this->assertSame(3500, $service->price_cents);

        $this->actingAs($this->owner())->put("/painel/servicos/{$service->id}", [
            'name' => 'Barba desenhada',
            'duration_min' => 30,
            'price_cents' => 4000,
            'active' => false,
        ])->assertRedirect();

        $this->assertSame(4000, $service->refresh()->price_cents);
        $this->assertFalse($service->active);
    }

    public function test_servico_com_historico_e_desativado_em_vez_de_apagado(): void
    {
        $service = Service::factory()->create();
        Appointment::factory()->for($service)->create();

        $this->actingAs($this->owner())->delete("/painel/servicos/{$service->id}")->assertRedirect();

        $this->assertTrue(Service::whereKey($service->id)->exists());
        $this->assertFalse($service->refresh()->active);
    }

    public function test_servico_sem_historico_e_apagado(): void
    {
        $service = Service::factory()->create();

        $this->actingAs($this->owner())->delete("/painel/servicos/{$service->id}")->assertRedirect();

        $this->assertFalse(Service::whereKey($service->id)->exists());
    }

    public function test_barbeiro_nao_acessa_servicos_nem_barbeiros(): void
    {
        $user = User::factory()->barber()->create();

        $this->actingAs($user)->get('/painel/servicos')->assertForbidden();
        $this->actingAs($user)->get('/painel/barbeiros')->assertForbidden();
    }

    public function test_grade_semanal_substitui_o_expediente(): void
    {
        $barber = Barber::factory()->create();
        $barber->workingHours()->create(['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '12:00']);

        $this->actingAs($this->owner())->put("/painel/horarios/{$barber->id}", [
            'hours' => [
                ['weekday' => 1, 'starts_at' => '10:00', 'ends_at' => '19:00'],
                ['weekday' => 2, 'starts_at' => '10:00', 'ends_at' => '19:00'],
            ],
        ])->assertRedirect();

        $hours = $barber->workingHours()->orderBy('weekday')->get();

        $this->assertCount(2, $hours);
        $this->assertSame('10:00:00', $hours[0]->starts_at);
    }

    public function test_barbeiro_nao_edita_a_grade_de_outro(): void
    {
        $user = User::factory()->barber()->create();
        Barber::factory()->create(['user_id' => $user->id]);
        $other = Barber::factory()->create();

        $this->actingAs($user)->put("/painel/horarios/{$other->id}", ['hours' => []])->assertForbidden();
    }

    public function test_lista_clientes_com_busca_e_situacao(): void
    {
        Customer::factory()->create(['name' => 'Marcos Vinícius', 'phone_e164' => '+5531988887777', 'last_visit_at' => now()->subDays(120)]);
        Customer::factory()->create(['name' => 'Ana Paula', 'phone_e164' => '+5531977776666', 'last_visit_at' => now()->subDays(5)]);

        $this->actingAs($this->owner())
            ->get('/painel/clientes?q=marcos')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('painel/clientes')
                ->has('customers.data', 1)
                ->where('customers.data.0.name', 'Marcos Vinícius')
                ->where('customers.data.0.situation', 'Novo'));

        $this->actingAs($this->owner())
            ->get('/painel/clientes?q=9777')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('customers.data.0.name', 'Ana Paula'));
    }

    public function test_cadastra_barbeiro_com_acesso_ao_painel(): void
    {
        $this->actingAs($this->owner())->post('/painel/barbeiros', [
            'name' => 'Rafael Alves',
            'headline' => 'Degradê e navalha',
            'email' => 'rafael@barbearia.com',
            'password' => 'senha-forte',
            'password_confirmation' => 'senha-forte',
        ])->assertRedirect();

        $barber = Barber::firstOrFail();

        $this->assertSame('RA', $barber->initials);
        $this->assertSame(UserRole::Barber, $barber->user->role);
        $this->assertTrue(Hash::check('senha-forte', $barber->user->password));
    }

    public function test_senha_sem_confirmacao_nao_passa(): void
    {
        $user = User::factory()->barber()->create();
        $barber = Barber::factory()->create(['user_id' => $user->id]);

        $this->actingAs($this->owner())->put("/painel/barbeiros/{$barber->id}", [
            'name' => $barber->display_name,
            'password' => 'senha-nova-123',
            'password_confirmation' => 'outra-coisa',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_desativar_barbeiro_derruba_o_acesso(): void
    {
        $user = User::factory()->barber()->create();
        $barber = Barber::factory()->create(['user_id' => $user->id]);

        $this->actingAs($this->owner())->put("/painel/barbeiros/{$barber->id}", [
            'name' => $barber->display_name,
            'active' => false,
        ])->assertRedirect();

        $this->assertFalse($barber->refresh()->active);
        $this->assertFalse($user->refresh()->active);
    }

    public function test_usuario_desativado_nao_consegue_entrar(): void
    {
        $user = User::factory()->barber()->create(['active' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
