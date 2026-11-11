<?php

namespace App\Services;

use App\Models\Credencial;
use App\Models\CredencialPin;
use App\Models\Expositor;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class CredentialRegistrationService
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly LegacyPhotoStorageService $photoStorage,
    ) {}

    /**
     * @param  array{pin: string, tipo_documento: string, numero_documento: string, nombre_completo: string, telefono: string, correo: string}  $data
     * @return array{credential: Credencial, person: Expositor}
     */
    public function register(array $data, UploadedFile $photo): array
    {
        $photoPath = null;
        $oldPhotoPath = null;

        try {
            $result = $this->database->transaction(function () use ($data, $photo, &$photoPath, &$oldPhotoPath): array {
                $pin = CredencialPin::query()
                    ->where('pin_hash', hash('sha256', strtoupper(trim($data['pin']))))
                    ->lockForUpdate()
                    ->first();

                if (! $pin || ! in_array($pin->estado, ['disponible', 'enviado'], true)) {
                    throw ValidationException::withMessages([
                        'pin' => 'El PIN no existe, fue utilizado o no esta disponible.',
                    ]);
                }

                $person = Expositor::query()
                    ->where('ci', $data['numero_documento'])
                    ->lockForUpdate()
                    ->first();

                if ($person && $person->credenciales()
                    ->where('id_feria', $pin->id_feria)
                    ->where('estado_credencial', '!=', 3)
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'numero_documento' => 'Esta persona ya tiene una credencial en este evento.',
                    ]);
                }

                $photoPath = $this->photoStorage->store($photo);
                $oldPhotoPath = $person?->foto;

                $person ??= new Expositor;
                $person->fill([
                    'ci' => $data['numero_documento'],
                    'tipo_documento' => $data['tipo_documento'],
                    'nombre' => $data['nombre_completo'],
                    'email' => $data['correo'],
                    'celular' => $data['telefono'],
                    'foto' => $photoPath,
                    'estado' => true,
                ]);
                $person->save();

                $credential = Credencial::query()->create([
                    'id_empresa' => $pin->id_empresa,
                    'id_feria' => $pin->id_feria,
                    'id_contrato' => $pin->id_contrato,
                    'id_expositor' => $person->id_exp,
                    'tipo_credencial' => $this->credentialTypeCode($pin->tipo_credencial),
                    'modalidad_credencial' => $pin->modalidad_credencial,
                    'estado_credencial' => 1,
                    'cod_credencial' => 'ACR-'.$pin->id_feria.'-PIN-'.$pin->id.'-'.$person->id_exp,
                    'com_medio' => '',
                    'com_dias' => '',
                    'ingreso' => 0,
                ]);

                if (Schema::hasTable('credencial_estado_historial')) {
                    $this->database->table('credencial_estado_historial')->insert([
                        'id_credencial' => $credential->id_credencial,
                        'accion' => 'creacion',
                        'estado_anterior' => 0,
                        'estado_nuevo' => 1,
                        'motivo' => 'Registro mediante PIN: '.strtoupper(trim($data['pin'])).'.',
                        'id_usuario' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $pin->update([
                    'estado' => 'usado',
                    'id_credencial' => $credential->id_credencial,
                    'nombre_destinatario' => $person->nombre,
                    'celular_destinatario' => $person->celular,
                    'email_destinatario' => $person->email,
                    'usado_at' => now(),
                ]);

                return ['credential' => $credential->load('expositor'), 'person' => $person];
            });
        } catch (Throwable $exception) {
            if ($photoPath) {
                $this->photoStorage->delete($photoPath);
            }

            throw $exception;
        }

        if ($oldPhotoPath && $oldPhotoPath !== $photoPath) {
            $this->photoStorage->delete($oldPhotoPath);
        }

        return $result;
    }

    /**
     * @return array{already_accredited: bool, person: ?Expositor, credential: ?Credencial}
     */
    public function checkPerson(string $pinValue, string $documentNumber): array
    {
        $pin = CredencialPin::query()
            ->where('pin_hash', hash('sha256', strtoupper(trim($pinValue))))
            ->first();

        if (! $pin || ! in_array($pin->estado, ['disponible', 'enviado'], true)) {
            throw ValidationException::withMessages([
                'pin' => 'El PIN no existe o no esta disponible.',
            ]);
        }

        $person = Expositor::query()
            ->where('ci', strtoupper(trim($documentNumber)))
            ->first();

        $credential = $person?->credenciales()
            ->where('id_feria', $pin->id_feria)
            ->where('estado_credencial', '!=', 3)
            ->first();

        return [
            'already_accredited' => $credential !== null,
            'person' => $credential ? $person : null,
            'credential' => $credential?->load('expositor'),
        ];
    }

    private function credentialTypeCode(string $type): int
    {
        return match (mb_strtolower(trim($type))) {
            'expositor' => 1,
            'prensa' => 2,
            'oficial' => 3,
            'servicios' => 4,
            'negocios' => 5,
            default => 1,
        };
    }
}
