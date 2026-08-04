<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AvailabilityEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function barber(): Barber
    {
        $barber = Barber::factory()->create();

        foreach ([1, 2, 3, 4, 5, 6] as $weekday) {
            $barber->workingHours()->create(['weekday' => $weekday, 'starts_at' => '09:00', 'ends_at' => '12:00']);
        }

        return $barber;
    }

    public function test_wizard_recebe_catalogo_no_primeiro_render(): void
    {
        $service = Service::factory()->create();
        $barber = $this->barber();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('agendar/wizard')
                ->where('services.0.id', $service->id)
                ->where('barbers.0.id', $barber->id)
                ->where('shop.name', config('barbearia.name')));
    }

    public function test_sem_data_devolve_apenas_a_contagem_por_dia(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-02 08:00:00', config('barbearia.timezone')));

        $barber = $this->barber();
        $service = Service::factory()->create(['duration_min' => 60]);

        $response = $this->getJson("/api/availability?service_id={$service->id}&barber_id={$barber->id}")
            ->assertOk()
            ->assertJsonPath('slots', []);

        $this->assertNotEmpty($response->json('days'));
        $this->assertSame(9, $response->json('days.0.count')); // 09:00 → 11:00, passo de 15 min
    }

    public function test_com_data_devolve_os_horarios_do_dia(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-02 08:00:00', config('barbearia.timezone')));

        $barber = $this->barber();
        $service = Service::factory()->create(['duration_min' => 60]);

        $this->getJson("/api/availability?service_id={$service->id}&barber_id={$barber->id}&date=2026-09-03")
            ->assertOk()
            ->assertJsonPath('slots.0.label', '09:00')
            ->assertJsonPath('slots.0.barbers.0.id', $barber->id);
    }

    public function test_valida_servico_inexistente(): void
    {
        $this->getJson('/api/availability?service_id=999')->assertStatus(422);
    }
}
