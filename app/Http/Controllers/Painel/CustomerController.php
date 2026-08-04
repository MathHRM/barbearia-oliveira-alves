<?php

namespace App\Http\Controllers\Painel;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $term = trim((string) $request->query('q'));
        $churnDays = (int) config('barbearia.churn_days');

        $customers = $this->matching($term)
            ->orderByRaw('last_visit_at desc nulls last')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => Phone::format($customer->phone_e164),
                'email' => $customer->email,
                'visits' => $customer->attended_count,
                'last_visit' => $customer->last_visit_at?->timezone(config('barbearia.timezone'))->format('d/m/Y'),
                'situation' => $customer->situation($churnDays),
            ]);

        return Inertia::render('painel/clientes', [
            'customers' => $customers,
            'q' => $term,
            'churn_days' => $churnDays,
        ]);
    }

    /** Autocomplete do balcão: poucos nomes, resposta em JSON, sem paginação. */
    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));

        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $customers = $this->matching($term)
            ->orderByRaw('last_visit_at desc nulls last')
            ->limit(8)
            ->get()
            ->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => Phone::format($customer->phone_e164),
                'visits' => $customer->attended_count,
                'last_visit' => $customer->last_visit_at?->timezone(config('barbearia.timezone'))->format('d/m/Y'),
            ]);

        return response()->json($customers);
    }

    /** Nome por trecho, telefone por dígitos — a busca da listagem e a do balcão são a mesma. */
    private function matching(string $term): Builder
    {
        return Customer::query()
            ->withCount(['appointments as attended_count' => fn ($query) => $query->where('status', AppointmentStatus::Attended)])
            ->when($term !== '', function (Builder $query) use ($term) {
                $digits = preg_replace('/\D/', '', $term);

                $query->where(function (Builder $inner) use ($term, $digits) {
                    $inner->where('name', 'ilike', "%{$term}%");

                    if ($digits !== '') {
                        $inner->orWhere('phone_e164', 'like', "%{$digits}%");
                    }
                });
            });
    }
}
