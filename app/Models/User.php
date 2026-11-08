<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'role', 'role_description', 'department', 'active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLES = [
        'investigador' => 'Investigador / Coordinador',
        'vicerrectoria' => 'Vicerrectoría / Subdirección',
        'financiero' => 'Área financiera',
        'admin' => 'Administrador del sistema',
    ];

    public const ROLE_DESCRIPTIONS = [
        'investigador' => 'Ejecuta el proyecto o contrato: carga el avance mensual, registra gastos, solicita compras y sube documentos.',
        'vicerrectoria' => 'Aprueba: revisa técnicamente las compras, aprueba avances, autoriza traslados, crea proyectos y los liquida.',
        'financiero' => 'Valida la disponibilidad presupuestal, expide el CDP, registra pagos y valida soportes.',
        'admin' => 'Gestiona usuarios, roles y permisos; tiene acceso completo.',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    public function initials(): string
    {
        $words = collect(explode(' ', $this->name))
            ->reject(fn ($w) => Str::endsWith($w, '.'))   // omite "Dra.", "Dr."
            ->values();

        return Str::upper(Str::substr($words[0] ?? '', 0, 1) . Str::substr($words[1] ?? '', 0, 1));
    }

    /** Página de inicio según el rol. */
    public function homeRoute(): string
    {
        return match ($this->role) {
            'vicerrectoria' => route('vic.panel'),
            'admin' => route('adm.usuarios'),
            'financiero' => route('fin.control'),
            'investigador' => route('inv.home'),
            default => route('pendiente'),
        };
    }
}
