<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('type', 20);          // investigacion | extension
            $table->string('modality', 30);      // convenio | contrato | recursos_propios | convocatoria
            $table->string('entity');
            $table->foreignId('coordinator_id')->constrained('users');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('preinvestment_end')->nullable();
            $table->date('investment_end')->nullable();
            $table->decimal('value', 16, 2);
            $table->decimal('executed_amount', 16, 2)->default(0);
            $table->unsignedTinyInteger('technical_progress')->default(0);
            $table->string('phase', 20)->default('preinversion'); // preinversion | inversion | operacion
            $table->string('status', 20)->default('activo');      // propuesto | activo | liquidado
            $table->string('health', 20)->default('en_tiempo');   // en_tiempo | sobrecosto | retraso
            $table->text('general_objective')->nullable();
            $table->timestamps();
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('budgeted', 16, 2);
            $table->decimal('executed', 16, 2)->default(0);
            $table->decimal('committed', 16, 2)->default(0);
            $table->timestamps();
        });

        // Curva S: acumulados mensuales en pesos
        Schema::create('curve_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('month');
            $table->decimal('planned_cumulative', 16, 2);
            $table->decimal('executed_cumulative', 16, 2)->nullable();
            $table->unique(['project_id', 'month']);
        });

        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_line_id')->constrained();
            $table->foreignId('requester_id')->constrained('users');
            $table->string('description');
            $table->decimal('value', 16, 2);
            $table->decimal('original_value', 16, 2)->nullable();
            // en_vicerrectoria | en_financiera | aprobada | rechazada | devuelta | pagada
            $table->string('status', 20)->default('en_vicerrectoria');
            $table->timestamp('submitted_at');
            $table->timestamps();
        });

        Schema::create('monthly_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users');
            $table->date('period');
            $table->unsignedTinyInteger('technical_progress');
            $table->tinyInteger('technical_delta')->default(0);
            $table->unsignedSmallInteger('supports_count')->default(0);
            $table->decimal('amount', 16, 2)->default(0);
            $table->string('status', 20)->default('pendiente'); // pendiente | aprobado | devuelto
            $table->timestamp('submitted_at');
            $table->timestamps();
        });

        Schema::create('budget_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_line_id')->constrained('budget_lines');
            $table->foreignId('to_line_id')->constrained('budget_lines');
            $table->foreignId('requester_id')->constrained('users');
            $table->decimal('amount', 16, 2);
            $table->string('reason')->nullable();
            $table->string('status', 20)->default('pendiente'); // pendiente | aprobado | rechazado
            $table->timestamp('submitted_at');
            $table->timestamps();
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('severity', 20);  // critica | advertencia
            $table->string('kind', 30);      // sobrecosto | presupuesto | entrega_vencida | rezago | ejecucion
            $table->string('title');
            $table->string('detail');
            $table->boolean('requires_action')->default(false);
            $table->boolean('resolved')->default(false);
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('action');
            $table->text('detail')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['activity_logs', 'alerts', 'budget_transfers', 'monthly_progress', 'purchase_requests', 'curve_points', 'budget_lines', 'projects'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
