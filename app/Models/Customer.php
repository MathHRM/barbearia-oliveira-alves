<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'phone_e164', 'email', 'document', 'notes',
        'asaas_customer_id', 'first_seen_at', 'last_visit_at',
    ];

    protected function casts(): array
    {
        return ['first_seen_at' => UtcDateTime::class, 'last_visit_at' => UtcDateTime::class];
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /** Situação usada na lista de clientes e no churn. */
    public function situation(int $churnDays): string
    {
        $visits = $this->attended_count ?? $this->appointments()->where('status', 'attended')->count();

        if ($visits <= 1) {
            return 'Novo';
        }

        if ($this->last_visit_at?->lt(now()->subDays($churnDays))) {
            return 'Perdido';
        }

        return $visits >= 5 ? 'Fiel' : 'Ativo';
    }
}
