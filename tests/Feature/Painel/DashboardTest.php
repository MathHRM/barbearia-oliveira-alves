<?php

namespace Tests\Feature\Painel;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    private function attendedAt(string $date, int $priceCents, ?Service $service = null): Appointment
    {
        return Appointment::factory()
            ->for($service ?? Service::factory()->create())
            ->at(Carbon::parse($date, config('barbearia.timezone')))
            ->attended()
            ->create(['price_cents' => $priceCents]);
    }

    public function test_barbeiro_nao_entra_no_dashboard(): void
    {
        $this->actingAs(User::factory()->barber()->create())
            ->get('/painel/dashboard')
            ->assertForbidden();
    }

    public function test_kpis_somam_apenas_atendimentos_concluidos(): void
    {
        $service = Service::factory()->create();

        $this->attendedAt('2026-08-20 10:00', 5000, $service);
        $this->attendedAt('2026-08-25 10:00', 3000, $service);
        // cancelado não entra no faturamento nem na contagem de agendamentos
        Appointment::factory()->at(Carbon::parse('2026-08-26 10:00', config('barbearia.timezone')))->canceled()->create(['price_cents' => 9900]);
        // fora da janela de 30 dias
        $this->attendedAt('2026-05-10 10:00', 7000, $service);

        $this->actingAs(User::factory()->owner()->create())
            ->get('/painel/dashboard?range=30d')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('painel/dashboard')
                ->where('range', '30d')
                ->where('kpis.revenue_cents.value', 8000)
                ->where('kpis.ticket_cents.value', 4000)
                ->where('kpis.appointments.value', 2)
                ->has('weekly', 12)
                ->has('services', 1)
                ->where('services.0.revenue_cents', 8000));
    }

    public function test_churn_conta_quem_nao_volta_ha_mais_de_60_dias(): void
    {
        Customer::factory()->create(['last_visit_at' => now()->subDays(10)]);
        Customer::factory()->create(['last_visit_at' => now()->subDays(45)]);
        Customer::factory()->create(['last_visit_at' => now()->subDays(120)]);
        // sem histórico não entra na conta
        Customer::factory()->create(['last_visit_at' => null]);

        $this->actingAs(User::factory()->owner()->create())
            ->get('/painel/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('kpis.churn_rate.value', 33.3)
                ->where('retention.recent_30', 1)
                ->where('retention.recent_60', 1)
                ->where('retention.lost', 1)
                ->where('retention.total', 3));
    }

    public function test_faturamento_por_semana_cai_na_semana_certa(): void
    {
        // 2026-09-02 é quarta; a semana começa na segunda, 2026-08-31
        $this->attendedAt('2026-08-31 09:00', 4000);
        $this->attendedAt('2026-09-02 09:00', 2000);

        $this->actingAs(User::factory()->owner()->create())
            ->get('/painel/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('weekly.11.label', '31/08')
                ->where('weekly.11.revenue_cents', 6000)
                ->where('weekly.10.revenue_cents', 0));
    }
}
