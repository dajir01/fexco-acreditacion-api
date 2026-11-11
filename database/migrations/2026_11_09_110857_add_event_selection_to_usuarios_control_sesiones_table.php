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
        if (! Schema::hasTable('usuarios_control_sesiones')) {
            return;
        }

        Schema::table('usuarios_control_sesiones', function (Blueprint $table) {
            if (! Schema::hasColumn('usuarios_control_sesiones', 'id_feria')) {
                $table->bigInteger('id_feria')->nullable();
            }

            if (! Schema::hasColumn('usuarios_control_sesiones', 'numero_puerta')) {
                $table->unsignedSmallInteger('numero_puerta')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('usuarios_control_sesiones')) {
            return;
        }

        Schema::table('usuarios_control_sesiones', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('usuarios_control_sesiones', 'id_feria')) {
                $columns[] = 'id_feria';
            }

            if (Schema::hasColumn('usuarios_control_sesiones', 'numero_puerta')) {
                $columns[] = 'numero_puerta';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
