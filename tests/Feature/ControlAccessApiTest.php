<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function (): void {
    Schema::create('personal_access_tokens', function (Blueprint $table): void {
        $table->id();
        $table->morphs('tokenable');
        $table->text('name');
        $table->string('token', 64)->unique();
        $table->text('abilities')->nullable();
        $table->timestamp('last_used_at')->nullable();
        $table->timestamp('expires_at')->nullable()->index();
        $table->timestamps();
    });

    Schema::create('eventos', function (Blueprint $table): void {
        $table->bigIncrements('id_feria');
        $table->string('nombre_feria');
        $table->date('fecha_inicio')->nullable();
        $table->date('fecha_fin')->nullable();
        $table->integer('inicio')->nullable();
        $table->string('puertas_acceso')->nullable();
    });

    Schema::create('usuarios_control', function (Blueprint $table): void {
        $table->bigIncrements('id_usuario_control');
        $table->string('nombre', 150);
        $table->string('usuario', 100)->unique();
        $table->string('password');
        $table->boolean('activo')->default(true);
        $table->timestamps();
    });

    Schema::create('usuarios_control_permisos', function (Blueprint $table): void {
        $table->unsignedBigInteger('id_usuario_control');
        $table->string('permiso', 100);
        $table->timestamps();
        $table->primary(['id_usuario_control', 'permiso']);
    });

    Schema::create('usuarios_control_asignaciones', function (Blueprint $table): void {
        $table->bigIncrements('id');
        $table->unsignedBigInteger('id_usuario_control');
        $table->bigInteger('id_feria');
        $table->unsignedSmallInteger('numero_puerta');
        $table->timestamps();
        $table->unique(['id_usuario_control', 'id_feria', 'numero_puerta']);
    });

    Schema::create('usuarios_control_sesiones', function (Blueprint $table): void {
        $table->bigIncrements('id');
        $table->unsignedBigInteger('id_usuario_control');
        $table->unsignedBigInteger('token_id')->nullable()->unique();
        $table->bigInteger('id_feria')->nullable();
        $table->unsignedSmallInteger('numero_puerta')->nullable();
        $table->string('dispositivo_ref', 100)->nullable();
        $table->string('ip_address', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->timestamp('inicio_at');
        $table->timestamp('ultima_actividad_at')->nullable();
        $table->timestamp('fin_at')->nullable();
        $table->timestamps();
    });

    Schema::create('expositores', function (Blueprint $table): void {
        $table->increments('id_exp');
        $table->string('nombre');
        $table->string('ci')->nullable();
        $table->string('foto')->nullable();
    });

    Schema::create('credenciales', function (Blueprint $table): void {
        $table->increments('id_credencial');
        $table->unsignedBigInteger('id_feria');
        $table->unsignedInteger('id_expositor');
        $table->unsignedTinyInteger('tipo_credencial');
        $table->unsignedTinyInteger('modalidad_credencial')->nullable();
        $table->unsignedTinyInteger('estado_credencial');
    });

    Schema::create('credencial_qr_tokens', function (Blueprint $table): void {
        $table->bigIncrements('id');
        $table->unsignedInteger('id_credencial');
        $table->string('token_hash', 64)->unique();
        $table->boolean('activo')->default(true);
    });

    Schema::create('credencial_ingresos', function (Blueprint $table): void {
        $table->bigIncrements('id');
        $table->unsignedInteger('id_credencial');
        $table->bigInteger('id_feria');
        $table->unsignedBigInteger('id_usuario_control')->nullable();
        $table->unsignedSmallInteger('numero_puerta')->nullable();
        $table->unsignedBigInteger('id_qr_token')->nullable();
        $table->unsignedTinyInteger('tipo_credencial');
        $table->unsignedTinyInteger('modalidad_credencial');
        $table->dateTime('fecha_ingreso');
        $table->string('idempotency_key', 80)->unique();
        $table->string('dispositivo_ref', 100)->nullable();
        $table->string('punto_acceso', 150)->nullable();
        $table->timestamp('created_at')->nullable();
    });
});

function createControlAccessEvent(string $name = 'FEXCO 2026', string $doors = '1;2'): int
{
    return (int) DB::table('eventos')->insertGetId([
        'nombre_feria' => $name,
        'puertas_acceso' => $doors,
        'fecha_inicio' => '2026-11-09',
        'fecha_fin' => '2026-11-12',
    ], 'id_feria');
}

/**
 * @param  list<string>  $permissions
 * @param  list<int>  $doors
 * @return array{id: int, username: string}
 */
function createControlAccessOperator(
    int $eventId,
    array $permissions = ['controlaccesos.validar_qr', 'controlaccesos.registrar_ingreso'],
    array $doors = [1],
    bool $active = true,
    string $username = 'operador_1',
): array {
    $operatorId = (int) DB::table('usuarios_control')->insertGetId([
        'nombre' => 'Operador de prueba',
        'usuario' => $username,
        'password' => Hash::make('1234'),
        'activo' => $active,
        'created_at' => now(),
        'updated_at' => now(),
    ], 'id_usuario_control');

    foreach ($permissions as $permission) {
        DB::table('usuarios_control_permisos')->insert([
            'id_usuario_control' => $operatorId,
            'permiso' => $permission,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    foreach ($doors as $door) {
        DB::table('usuarios_control_asignaciones')->insert([
            'id_usuario_control' => $operatorId,
            'id_feria' => $eventId,
            'numero_puerta' => $door,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return ['id' => $operatorId, 'username' => $username];
}

/**
 * @return array{token: string, operator_id: int}
 */
function loginControlAccessOperator(string $username = 'operador_1'): array
{
    $response = test()->postJson('/api/v1/control-access/login', [
        'usuario' => $username,
        'password' => '1234',
        'dispositivo_ref' => 'flutter-device-01',
    ]);

    $response->assertOk();

    return [
        'token' => (string) $response->json('data.access_token'),
        'operator_id' => (int) $response->json('data.operator.id_usuario_control'),
    ];
}

/**
 * @return array{credential_id: int, person_id: int, token: string, qr_token_id: int}
 */
function createScannableCredential(int $eventId, string $personName = 'Persona de prueba', bool $active = true): array
{
    $personId = (int) DB::table('expositores')->insertGetId([
        'nombre' => $personName,
        'ci' => '12345678',
    ], 'id_exp');
    $credentialId = (int) DB::table('credenciales')->insertGetId([
        'id_feria' => $eventId,
        'id_expositor' => $personId,
        'tipo_credencial' => 1,
        'modalidad_credencial' => 1,
        'estado_credencial' => $active ? 1 : 0,
    ], 'id_credencial');
    $rawToken = 'crd_'.str_pad((string) $credentialId, 48, 'a');
    $qrTokenId = (int) DB::table('credencial_qr_tokens')->insertGetId([
        'id_credencial' => $credentialId,
        'token_hash' => hash('sha256', $rawToken),
        'activo' => true,
    ]);

    return [
        'credential_id' => $credentialId,
        'person_id' => $personId,
        'token' => $rawToken,
        'qr_token_id' => $qrTokenId,
    ];
}

test('operator login returns a scoped bearer token and only assigned events', function (): void {
    $eventId = createControlAccessEvent();
    createControlAccessEvent('Otro evento', '1');
    $operator = createControlAccessOperator($eventId, doors: [1, 2]);

    $response = $this->postJson('/api/v1/control-access/login', [
        'usuario' => $operator['username'],
        'password' => '1234',
        'dispositivo_ref' => 'flutter-device-01',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.operator.id_usuario_control', $operator['id'])
        ->assertJsonPath('data.events.0.id_feria', $eventId)
        ->assertJsonPath('data.events.0.puertas', [1, 2]);

    $tokenId = PersonalAccessToken::query()->value('id');
    $this->assertDatabaseHas('usuarios_control_sesiones', [
        'id_usuario_control' => $operator['id'],
        'token_id' => $tokenId,
        'dispositivo_ref' => 'flutter-device-01',
        'fin_at' => null,
    ]);
});

test('operator login returns 401 for invalid credentials or inactive accounts', function (): void {
    $eventId = createControlAccessEvent();
    createControlAccessOperator($eventId, active: false);

    $this->postJson('/api/v1/control-access/login', [
        'usuario' => 'operador_1',
        'password' => '1234',
    ])->assertUnauthorized()
        ->assertJsonPath('message', 'Las credenciales no coinciden con nuestros registros.');

    $this->postJson('/api/v1/control-access/login', [
        'usuario' => 'no_existe',
        'password' => '1234',
    ])->assertUnauthorized()
        ->assertJsonPath('message', 'Las credenciales no coinciden con nuestros registros.');

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('operator selects an assigned event and registers a credential access idempotently', function (): void {
    $eventId = createControlAccessEvent();
    $operator = createControlAccessOperator($eventId);
    $credential = createScannableCredential($eventId, 'Expositor Uno');
    $login = loginControlAccessOperator($operator['username']);

    $this->withToken($login['token'])
        ->putJson('/api/v1/control-access/session/event', [
            'id_feria' => $eventId,
            'numero_puerta' => 1,
        ])->assertOk()
        ->assertJsonPath('data.id_feria', $eventId)
        ->assertJsonPath('data.numero_puerta', 1);

    $payload = [
        'token' => $credential['token'],
        'idempotency_key' => '69c9ce13-0898-46ae-bcb2-f9e48e158482',
    ];

    $this->withToken($login['token'])
        ->postJson('/api/v1/control-access/scans', $payload)
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('duplicate', false)
        ->assertJsonPath('data.credencial.nombre_persona', 'Expositor Uno')
        ->assertJsonPath('data.credencial.ci', '12345678')
        ->assertJsonPath('data.credencial.foto_url', null)
        ->assertJsonPath('data.evento.id_feria', $eventId)
        ->assertJsonPath('data.numero_puerta', 1);

    $this->withToken($login['token'])
        ->postJson('/api/v1/control-access/scans', $payload)
        ->assertOk()
        ->assertJsonPath('duplicate', true);

    $otherOperator = createControlAccessOperator(
        $eventId,
        username: 'operador_2',
    );
    DB::table('credencial_ingresos')->insert([
        'id_credencial' => $credential['credential_id'],
        'id_feria' => $eventId,
        'id_usuario_control' => $otherOperator['id'],
        'numero_puerta' => 1,
        'id_qr_token' => $credential['qr_token_id'],
        'tipo_credencial' => 1,
        'modalidad_credencial' => 1,
        'fecha_ingreso' => now()->addMinute(),
        'idempotency_key' => 'foreign-operator-entry',
        'created_at' => now()->addMinute(),
    ]);

    $this->withToken($login['token'])
        ->getJson('/api/v1/control-access/scans')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.credencial.nombre_persona', 'Expositor Uno')
        ->assertJsonPath('data.0.numero_puerta', 1)
        ->assertJsonPath('data.0.id_ingreso', 1);

    $this->assertDatabaseCount('credencial_ingresos', 2);
    $this->assertDatabaseHas('credencial_ingresos', [
        'id_credencial' => $credential['credential_id'],
        'id_feria' => $eventId,
        'id_usuario_control' => $operator['id'],
        'numero_puerta' => 1,
        'id_qr_token' => $credential['qr_token_id'],
        'dispositivo_ref' => 'flutter-device-01',
    ]);
});

test('scan response includes person details and photo is available only after recorded access', function (): void {
    Storage::fake('legacy_photos');
    Storage::disk('legacy_photos')->put('acreditado_a1b2c3.jpg', 'photo-bytes');
    $eventId = createControlAccessEvent();
    $operator = createControlAccessOperator($eventId);
    $credential = createScannableCredential($eventId, 'Persona con foto');
    DB::table('expositores')
        ->where('id_exp', $credential['person_id'])
        ->update([
            'ci' => '3515585',
            'foto' => 'images/fotos/acreditado_a1b2c3.jpg',
        ]);
    $login = loginControlAccessOperator($operator['username']);

    $this->withToken($login['token'])
        ->putJson('/api/v1/control-access/session/event', [
            'id_feria' => $eventId,
            'numero_puerta' => 1,
        ])->assertOk();

    $this->withToken($login['token'])
        ->getJson('/api/v1/control-access/credentials/'.$credential['credential_id'].'/photo')
        ->assertNotFound();

    $this->withToken($login['token'])
        ->postJson('/api/v1/control-access/scans', [
            'token' => $credential['token'],
            'idempotency_key' => '8a8b12d1-ec34-49c7-aefa-259a70f29723',
        ])
        ->assertCreated()
        ->assertJsonPath('data.credencial.nombre_persona', 'Persona con foto')
        ->assertJsonPath('data.credencial.ci', '3515585')
        ->assertJsonPath(
            'data.credencial.foto_url',
            '/api/v1/control-access/credentials/'.$credential['credential_id'].'/photo',
        );

    $this->withToken($login['token'])
        ->get('/api/v1/control-access/credentials/'.$credential['credential_id'].'/photo')
        ->assertOk();
});

test('operator cannot select an event door that is not assigned to them', function (): void {
    $eventId = createControlAccessEvent();
    $operator = createControlAccessOperator($eventId, doors: [1]);
    $login = loginControlAccessOperator($operator['username']);

    $this->withToken($login['token'])
        ->putJson('/api/v1/control-access/session/event', [
            'id_feria' => $eventId,
            'numero_puerta' => 2,
        ])->assertForbidden();
});

test('scan requires a selected event and both operator permissions', function (): void {
    $eventId = createControlAccessEvent();
    $credential = createScannableCredential($eventId);
    $operator = createControlAccessOperator($eventId);
    $login = loginControlAccessOperator($operator['username']);

    $this->withToken($login['token'])
        ->postJson('/api/v1/control-access/scans', [
            'token' => $credential['token'],
            'idempotency_key' => '6cd402d7-ecaa-4ffa-9f1f-eab97c83fa34',
        ])->assertStatus(409);

    $restrictedOperator = createControlAccessOperator(
        $eventId,
        permissions: ['controlaccesos.validar_qr'],
        username: 'operador_solo_lectura',
    );
    $restrictedLogin = loginControlAccessOperator($restrictedOperator['username']);

    Auth::forgetGuards();
    $this->withToken($restrictedLogin['token'])
        ->putJson('/api/v1/control-access/session/event', [
            'id_feria' => $eventId,
            'numero_puerta' => 1,
        ])->assertOk();

    Auth::forgetGuards();
    $this->withToken($restrictedLogin['token'])
        ->postJson('/api/v1/control-access/scans', [
            'token' => $credential['token'],
            'idempotency_key' => '7f462d24-a55e-447f-8bf6-15ccf61737e4',
        ])->assertForbidden();

    $this->assertDatabaseCount('credencial_ingresos', 0);
});

test('history requires an active selection and both operator permissions', function (): void {
    $eventId = createControlAccessEvent();
    $operator = createControlAccessOperator($eventId);
    $login = loginControlAccessOperator($operator['username']);

    $this->withToken($login['token'])
        ->getJson('/api/v1/control-access/scans')
        ->assertStatus(409);

    $reader = createControlAccessOperator(
        $eventId,
        permissions: ['controlaccesos.validar_qr'],
        username: 'operador_solo_lectura',
    );
    $readerLogin = loginControlAccessOperator($reader['username']);

    Auth::forgetGuards();
    $this->withToken($readerLogin['token'])
        ->putJson('/api/v1/control-access/session/event', [
            'id_feria' => $eventId,
            'numero_puerta' => 1,
        ])->assertOk();

    Auth::forgetGuards();
    $this->withToken($readerLogin['token'])
        ->getJson('/api/v1/control-access/scans')
        ->assertForbidden();
});

test('scan rejects invalid and disabled credentials without recording an access', function (): void {
    $eventId = createControlAccessEvent();
    $operator = createControlAccessOperator($eventId);
    $disabledCredential = createScannableCredential($eventId, active: false);
    $login = loginControlAccessOperator($operator['username']);
    $this->withToken($login['token'])
        ->putJson('/api/v1/control-access/session/event', [
            'id_feria' => $eventId,
            'numero_puerta' => 1,
        ])->assertOk();

    $this->withToken($login['token'])
        ->postJson('/api/v1/control-access/scans', [
            'token' => 'invalid-qr-token',
            'idempotency_key' => 'a73bb705-2261-47d2-8d85-54fdef9c091b',
        ])->assertUnprocessable();

    $this->withToken($login['token'])
        ->postJson('/api/v1/control-access/scans', [
            'token' => $disabledCredential['token'],
            'idempotency_key' => '69212c8f-c043-41c5-8140-6892dcc86c6d',
        ])->assertUnprocessable();

    $this->assertDatabaseCount('credencial_ingresos', 0);
});

test('scan rejects credentials from another event and idempotency keys reused for another credential', function (): void {
    $eventId = createControlAccessEvent();
    $otherEventId = createControlAccessEvent('Otro evento', '1');
    $operator = createControlAccessOperator($eventId);
    $credential = createScannableCredential($eventId, 'Persona Uno');
    $anotherCredential = createScannableCredential($eventId, 'Persona Dos');
    $foreignCredential = createScannableCredential($otherEventId, 'Persona de otro evento');
    $login = loginControlAccessOperator($operator['username']);
    $this->withToken($login['token'])
        ->putJson('/api/v1/control-access/session/event', [
            'id_feria' => $eventId,
            'numero_puerta' => 1,
        ])->assertOk();

    $this->withToken($login['token'])
        ->postJson('/api/v1/control-access/scans', [
            'token' => $foreignCredential['token'],
            'idempotency_key' => '1052fc50-7682-4621-b014-3d37e77cae28',
        ])->assertStatus(409);

    $idempotencyKey = '7a2497a8-88ae-435b-a97e-95a0b7c64307';
    $this->withToken($login['token'])
        ->postJson('/api/v1/control-access/scans', [
            'token' => $credential['token'],
            'idempotency_key' => $idempotencyKey,
        ])->assertCreated();

    $this->withToken($login['token'])
        ->postJson('/api/v1/control-access/scans', [
            'token' => $anotherCredential['token'],
            'idempotency_key' => $idempotencyKey,
        ])->assertStatus(409);

    $this->assertDatabaseCount('credencial_ingresos', 1);
});

test('operator logout closes the mobile session and revokes its bearer token', function (): void {
    $eventId = createControlAccessEvent();
    $operator = createControlAccessOperator($eventId);
    $login = loginControlAccessOperator($operator['username']);

    $this->withToken($login['token'])
        ->postJson('/api/v1/control-access/logout')
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertNotNull(DB::table('usuarios_control_sesiones')
        ->where('id_usuario_control', $operator['id'])
        ->value('fin_at'));

    Auth::forgetGuards();
    $this->withToken($login['token'])
        ->getJson('/api/v1/control-access/me')
        ->assertUnauthorized();
});
