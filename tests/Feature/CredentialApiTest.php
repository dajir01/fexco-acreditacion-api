<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Schema::create('eventos', function ($table): void {
        $table->increments('id_feria');
        $table->string('nombre_feria');
    });

    Schema::create('empresas', function ($table): void {
        $table->increments('id_empresa');
        $table->string('nombre_empresa');
    });

    Schema::create('expositores', function ($table): void {
        $table->increments('id_exp');
        $table->string('ci')->nullable();
        $table->string('tipo_documento')->nullable();
        $table->string('nombre')->nullable();
        $table->string('email')->nullable();
        $table->string('celular')->nullable();
        $table->string('foto')->nullable();
        $table->boolean('estado')->nullable();
        $table->timestamps();
    });

    Schema::create('credenciales', function ($table): void {
        $table->increments('id_credencial');
        $table->unsignedInteger('id_empresa');
        $table->unsignedInteger('id_feria');
        $table->unsignedInteger('id_contrato')->nullable();
        $table->unsignedInteger('id_expositor');
        $table->unsignedTinyInteger('tipo_credencial');
        $table->unsignedTinyInteger('modalidad_credencial')->nullable();
        $table->unsignedTinyInteger('estado_credencial');
        $table->string('cod_credencial')->nullable();
        $table->string('com_medio')->nullable();
        $table->string('com_dias')->nullable();
        $table->boolean('ingreso')->nullable();
        $table->timestamps();
    });

    Schema::create('credencial_pines', function ($table): void {
        $table->id();
        $table->unsignedInteger('id_feria');
        $table->unsignedInteger('id_empresa');
        $table->unsignedInteger('id_contrato')->nullable();
        $table->unsignedInteger('id_empresa_evento_credencial')->nullable();
        $table->string('tipo_credencial');
        $table->unsignedTinyInteger('modalidad_credencial')->default(1);
        $table->string('pin_hash', 64);
        $table->string('estado');
        $table->unsignedInteger('id_credencial')->nullable();
        $table->string('nombre_destinatario')->nullable();
        $table->string('celular_destinatario')->nullable();
        $table->string('email_destinatario')->nullable();
        $table->timestamp('usado_at')->nullable();
        $table->timestamps();
    });

    Schema::create('credencial_estado_historial', function ($table): void {
        $table->id();
        $table->unsignedInteger('id_credencial');
        $table->string('accion');
        $table->unsignedTinyInteger('estado_anterior');
        $table->unsignedTinyInteger('estado_nuevo');
        $table->text('motivo');
        $table->unsignedInteger('id_usuario')->nullable();
        $table->timestamps();
    });

    Storage::fake('legacy_photos');
});

function createPin(string $value = 'A7K2P9', string $state = 'disponible'): int
{
    DB::table('eventos')->insert(['id_feria' => 10, 'nombre_feria' => 'FEXCO 2026']);
    DB::table('empresas')->insert(['id_empresa' => 20, 'nombre_empresa' => 'Empresa Demo']);

    return (int) DB::table('credencial_pines')->insertGetId([
        'id_feria' => 10,
        'id_empresa' => 20,
        'tipo_credencial' => 'Expositor',
        'modalidad_credencial' => 1,
        'pin_hash' => hash('sha256', $value),
        'estado' => $state,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function accessTokenFor(string $value = 'A7K2P9'): string
{
    return (string) test()->postJson('/api/v1/pins/validate', ['pin' => $value])->json('access_token');
}

test('rejects protected endpoints without a temporary token', function (): void {
    createPin();

    $this->postJson('/api/v1/registrations/check-person', [
        'pin' => 'A7K2P9',
        'numero_documento' => '998877',
    ])->assertUnauthorized();
});

test('returns not found for an unknown pin', function (): void {
    $this->postJson('/api/v1/pins/validate', ['pin' => 'A7K2P9'])
        ->assertNotFound()
        ->assertJsonPath('status', 'not_found');
});

test('returns validation errors in Spanish', function (): void {
    $this->postJson('/api/v1/pins/validate', [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.pin.0', 'El campo PIN es obligatorio.');
});

test('throttles repeated pin validation attempts', function (): void {
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/pins/validate', [
            'pin' => str_pad((string) $attempt, 6, '0', STR_PAD_LEFT),
        ])->assertNotFound();
    }

    $this->postJson('/api/v1/pins/validate', ['pin' => '999999'])
        ->assertTooManyRequests()
        ->assertJsonPath('message', 'Demasiados intentos de validación. Espera unos minutos antes de volver a intentar.');
});

test('throttles repeated registration attempts', function (): void {
    createPin();
    $token = accessTokenFor();

    foreach (range(1, 3) as $attempt) {
        $this->withToken($token)->postJson('/api/v1/registrations', [])->assertUnprocessable();
    }

    $this->withToken($token)->postJson('/api/v1/registrations', [])
        ->assertTooManyRequests()
        ->assertJsonPath('message', 'Demasiados intentos de registro. Espera unos minutos antes de volver a intentar.');
});

test('returns available pin context', function (): void {
    createPin();

    $this->postJson('/api/v1/pins/validate', ['pin' => 'a7k2p9'])
        ->assertOk()
        ->assertJsonPath('valid', true)
        ->assertJsonPath('data.status', 'available')
        ->assertJsonPath('data.event.name', 'FEXCO 2026');
});

test('accepts a six-character pin', function (): void {
    createPin('A7K2P9');

    $this->postJson('/api/v1/pins/validate', ['pin' => 'A7K2P9'])
        ->assertOk()
        ->assertJsonPath('valid', true);
});

test('rejects PINs that are not exactly six characters', function (): void {
    foreach (['A7K2P', 'A7K2P9M4Q7TX'] as $pin) {
        $this->postJson('/api/v1/pins/validate', ['pin' => $pin])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pin']);
    }
});

test('checks whether a person is already accredited for the pin event', function (): void {
    createPin();
    $personId = DB::table('expositores')->insertGetId([
        'ci' => '998877',
        'tipo_documento' => 'Cédula de identidad',
        'nombre' => 'Persona Registrada',
        'estado' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $credentialId = DB::table('credenciales')->insertGetId([
        'id_empresa' => 20,
        'id_feria' => 10,
        'id_expositor' => $personId,
        'tipo_credencial' => 1,
        'modalidad_credencial' => 1,
        'estado_credencial' => 1,
        'cod_credencial' => 'ACR-CHECK',
        'com_medio' => '',
        'com_dias' => '',
        'ingreso' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->withToken(accessTokenFor())->postJson('/api/v1/registrations/check-person', [
        'pin' => 'A7K2P9',
        'numero_documento' => '998877',
    ])
        ->assertOk()
        ->assertJsonPath('data.already_accredited', true)
        ->assertJsonPath('data.person.document_number', '998877')
        ->assertJsonPath('data.credential.id', $credentialId);
});

test('returns only used status for a previously consumed pin', function (): void {
    $pinId = createPin(state: 'usado');
    $personId = DB::table('expositores')->insertGetId([
        'ci' => '998877',
        'tipo_documento' => 'Cédula de identidad',
        'nombre' => 'Persona Registrada',
        'email' => 'registrada@example.com',
        'celular' => '+59170000001',
        'foto' => 'images/fotos/acreditado_demo.jpg',
        'estado' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $credentialId = DB::table('credenciales')->insertGetId([
        'id_empresa' => 20,
        'id_feria' => 10,
        'id_expositor' => $personId,
        'tipo_credencial' => 1,
        'modalidad_credencial' => 1,
        'estado_credencial' => 1,
        'cod_credencial' => 'ACR-USED',
        'com_medio' => '',
        'com_dias' => '',
        'ingreso' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('credencial_pines')->where('id', $pinId)->update(['id_credencial' => $credentialId]);

    $response = $this->postJson('/api/v1/pins/validate', ['pin' => 'A7K2P9']);

    $response
        ->assertOk()
        ->assertJsonPath('valid', false)
        ->assertJsonPath('status', 'used')
        ->assertJsonPath('data.status', 'used')
        ->assertJsonMissingPath('data.person')
        ->assertJsonMissingPath('data.credential')
        ->assertJsonMissingPath('data.event')
        ->assertJsonMissingPath('data.company');
    $this->assertStringNotContainsString('998877', $response->getContent());
    $this->assertStringNotContainsString('registrada@example.com', $response->getContent());
    $this->assertStringNotContainsString('ACR-USED', $response->getContent());
});

test('registers person, credential and photo atomically', function (): void {
    createPin();

    $response = $this->withToken(accessTokenFor())->post('/api/v1/registrations', [
        'pin' => 'A7K2P9',
        'tipo_documento' => 'Cédula de identidad',
        'numero_documento' => '1234567',
        'nombre_completo' => 'Persona Demo',
        'telefono' => '+59170000000',
        'correo' => 'demo@example.com',
        'foto' => UploadedFile::fake()->image('foto.jpg'),
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.person.document_number', '1234567');

    $this->assertDatabaseHas('credencial_pines', [
        'pin_hash' => hash('sha256', 'A7K2P9'),
        'estado' => 'usado',
    ]);
    $this->assertDatabaseHas('expositores', ['ci' => '1234567', 'nombre' => 'Persona Demo']);
    $this->assertDatabaseHas('credenciales', ['id_feria' => 10, 'id_empresa' => 20, 'estado_credencial' => 1]);
    $this->assertDatabaseHas('credencial_estado_historial', [
        'id_credencial' => DB::table('credenciales')->value('id_credencial'),
        'motivo' => 'Registro mediante PIN: A7K2P9.',
    ]);
    expect(Storage::disk('legacy_photos')->exists(basename(DB::table('expositores')->value('foto'))))->toBeTrue();
});

test('rejects unsafe personal data formats', function (): void {
    createPin();

    $payload = [
        'pin' => 'A7K2P9',
        'tipo_documento' => 'Documento inventado',
        'numero_documento' => '1234567',
        'nombre_completo' => '<script>alert(1)</script>',
        'telefono' => '+591 700-000-00',
        'correo' => 'demo@example.com',
        'foto' => UploadedFile::fake()->image('foto.jpg'),
    ];

    $this->withToken(accessTokenFor())->post('/api/v1/registrations', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tipo_documento', 'nombre_completo', 'telefono']);
});

test('returns pickup instructions for a physical credential', function (): void {
    $pinId = createPin();
    $credentialId = DB::table('credenciales')->insertGetId([
        'id_empresa' => 20,
        'id_feria' => 10,
        'id_expositor' => 0,
        'tipo_credencial' => 1,
        'modalidad_credencial' => 0,
        'estado_credencial' => 1,
        'cod_credencial' => 'ACR-PHYSICAL',
        'com_medio' => '',
        'com_dias' => '',
        'ingreso' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('credencial_pines')->where('id', $pinId)->update(['id_credencial' => $credentialId]);

    $this->getJson('/api/v1/credentials/'.$credentialId.'/download')
        ->assertUnauthorized();

    $this->withToken(accessTokenFor())->withHeader('X-Credential-Pin', 'A7K2P9')
        ->getJson('/api/v1/credentials/'.$credentialId.'/download')
        ->assertOk()
        ->assertJsonPath('downloadable', false)
        ->assertJsonPath('modality', 'physical');
});
