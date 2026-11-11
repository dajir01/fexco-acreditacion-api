<?php

namespace App\Services;

use App\Models\ControlAccessOperator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ControlAccessService
{
    public function operatorPayload(ControlAccessOperator $operator): array
    {
        return [
            'id_usuario_control' => (int) $operator->getKey(),
            'nombre' => $operator->nombre,
            'usuario' => $operator->usuario,
            'activo' => (bool) $operator->activo,
            'permissions' => DB::table('usuarios_control_permisos')
                ->where('id_usuario_control', $operator->getKey())
                ->orderBy('permiso')
                ->pluck('permiso')
                ->all(),
        ];
    }

    public function eventsFor(ControlAccessOperator $operator, ?object $session = null): array
    {
        $assignments = DB::table('usuarios_control_asignaciones as assignment')
            ->join('eventos as event', 'event.id_feria', '=', 'assignment.id_feria')
            ->where('assignment.id_usuario_control', $operator->getKey())
            ->orderByRaw('COALESCE(event.fecha_inicio, event.inicio) DESC')
            ->orderByDesc('event.id_feria')
            ->get([
                'event.id_feria',
                'event.nombre_feria',
                'event.fecha_inicio',
                'event.fecha_fin',
                'event.puertas_acceso',
                'assignment.numero_puerta',
            ]);

        return $assignments
            ->groupBy('id_feria')
            ->map(function ($eventAssignments) use ($session): ?array {
                $event = $eventAssignments->first();
                $configuredDoors = $this->availableDoors($event->puertas_acceso);
                $assignedDoors = $eventAssignments
                    ->pluck('numero_puerta')
                    ->map(fn ($door): int => (int) $door)
                    ->unique()
                    ->filter(fn (int $door): bool => in_array($door, $configuredDoors, true))
                    ->sort()
                    ->values()
                    ->all();

                if ($assignedDoors === []) {
                    return null;
                }

                return [
                    'id_feria' => (int) $event->id_feria,
                    'nombre_feria' => $event->nombre_feria,
                    'fecha_inicio' => $event->fecha_inicio,
                    'fecha_fin' => $event->fecha_fin,
                    'puertas' => $assignedDoors,
                    'seleccionado' => $session
                        && (int) $session->id_feria === (int) $event->id_feria
                        && in_array((int) $session->numero_puerta, $assignedDoors, true),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function selectionPayload(object $session): ?array
    {
        if (! $session->id_feria || ! $session->numero_puerta) {
            return null;
        }

        $event = DB::table('eventos')
            ->where('id_feria', $session->id_feria)
            ->first(['id_feria', 'nombre_feria']);

        if (! $event) {
            return null;
        }

        return [
            'id_feria' => (int) $event->id_feria,
            'nombre_feria' => $event->nombre_feria,
            'numero_puerta' => (int) $session->numero_puerta,
        ];
    }

    public function selectEvent(object $session, int $eventId, int $door): array
    {
        $event = DB::table('eventos')
            ->where('id_feria', $eventId)
            ->first(['id_feria', 'nombre_feria', 'puertas_acceso']);

        if (! $event) {
            throw new HttpException(404, 'El evento seleccionado no existe.');
        }

        if (! in_array($door, $this->availableDoors($event->puertas_acceso), true)) {
            throw ValidationException::withMessages([
                'numero_puerta' => 'La puerta no está habilitada para el evento seleccionado.',
            ]);
        }

        $isAssigned = DB::table('usuarios_control_asignaciones')
            ->where('id_usuario_control', $session->id_usuario_control)
            ->where('id_feria', $eventId)
            ->where('numero_puerta', $door)
            ->exists();

        if (! $isAssigned) {
            throw new HttpException(403, 'El operador no está asignado a esta puerta.');
        }

        DB::table('usuarios_control_sesiones')
            ->where('id', $session->id)
            ->whereNull('fin_at')
            ->update([
                'id_feria' => $eventId,
                'numero_puerta' => $door,
                'updated_at' => now(),
            ]);

        return [
            'id_feria' => (int) $event->id_feria,
            'nombre_feria' => $event->nombre_feria,
            'numero_puerta' => $door,
        ];
    }

    public function recentScansFor(ControlAccessOperator $operator, object $session): array
    {
        $selection = $this->activeSelection($operator, $session);

        return DB::table('credencial_ingresos as access')
            ->join('credenciales as credential', 'credential.id_credencial', '=', 'access.id_credencial')
            ->join('expositores as person', 'person.id_exp', '=', 'credential.id_expositor')
            ->where('access.id_usuario_control', $operator->getKey())
            ->where('access.id_feria', $selection['id_feria'])
            ->where('access.numero_puerta', $selection['numero_puerta'])
            ->orderByDesc('access.fecha_ingreso')
            ->orderByDesc('access.id')
            ->limit(50)
            ->get([
                'access.id as id_ingreso',
                'access.fecha_ingreso',
                'access.numero_puerta',
                'credential.id_credencial',
                'person.nombre as nombre_persona',
            ])
            ->map(fn (object $scan): array => [
                'id_ingreso' => (int) $scan->id_ingreso,
                'fecha_ingreso' => $scan->fecha_ingreso,
                'numero_puerta' => (int) $scan->numero_puerta,
                'credencial' => [
                    'id_credencial' => (int) $scan->id_credencial,
                    'nombre_persona' => $scan->nombre_persona,
                ],
            ])
            ->all();
    }

    public function credentialPhotoPathFor(
        ControlAccessOperator $operator,
        object $session,
        int $credentialId,
    ): ?string {
        $selection = $this->activeSelection($operator, $session);
        $hasRecordedAccess = DB::table('credencial_ingresos')
            ->where('id_credencial', $credentialId)
            ->where('id_feria', $selection['id_feria'])
            ->where('id_usuario_control', $operator->getKey())
            ->where('numero_puerta', $selection['numero_puerta'])
            ->exists();

        if (! $hasRecordedAccess) {
            return null;
        }

        $photo = DB::table('credenciales as credential')
            ->join('expositores as person', 'person.id_exp', '=', 'credential.id_expositor')
            ->where('credential.id_credencial', $credentialId)
            ->where('credential.id_feria', $selection['id_feria'])
            ->value('person.foto');

        if (! is_string($photo) || $photo === '') {
            return null;
        }

        $filename = basename($photo);
        if (! preg_match('/^acreditado_[a-f0-9-]+\.(jpg|jpeg|png)$/i', $filename)) {
            return null;
        }

        $path = Storage::disk('legacy_photos')->path($filename);

        return is_file($path) ? $path : null;
    }

    /**
     * @return array{duplicate: bool, data: array<string, mixed>}
     */
    public function registerCredentialAccess(
        ControlAccessOperator $operator,
        object $session,
        string $rawToken,
        string $idempotencyKey,
    ): array {
        $scanContext = null;

        try {
            return DB::transaction(function () use (
                $operator,
                $session,
                $rawToken,
                $idempotencyKey,
                &$scanContext,
            ): array {
                $selection = $this->activeSelection($operator, $session);
                $qrToken = DB::table('credencial_qr_tokens')
                    ->where('token_hash', hash('sha256', $rawToken))
                    ->where('activo', true)
                    ->lockForUpdate()
                    ->first(['id', 'id_credencial']);

                if (! $qrToken) {
                    throw new HttpException(422, 'La credencial es inválida o fue reemplazada.');
                }

                $credential = DB::table('credenciales as credential')
                    ->join('expositores as person', 'person.id_exp', '=', 'credential.id_expositor')
                    ->join('eventos as event', 'event.id_feria', '=', 'credential.id_feria')
                    ->where('credential.id_credencial', $qrToken->id_credencial)
                    ->lockForUpdate()
                    ->first([
                        'credential.id_credencial',
                        'credential.id_feria',
                        'credential.tipo_credencial',
                        'credential.modalidad_credencial',
                        'credential.estado_credencial',
                        'person.nombre as nombre_persona',
                        'person.ci as ci_persona',
                        'person.foto as foto_persona',
                        'event.nombre_feria',
                    ]);

                if (! $credential || (int) $credential->estado_credencial !== 1) {
                    throw new HttpException(422, 'La credencial está inhabilitada o no vigente.');
                }

                if ((int) $credential->id_feria !== (int) $selection['id_feria']) {
                    throw new HttpException(409, 'La credencial pertenece a otro evento.');
                }

                $scanContext = [
                    'id_credencial' => (int) $credential->id_credencial,
                    'id_feria' => (int) $selection['id_feria'],
                    'id_usuario_control' => (int) $operator->getKey(),
                    'numero_puerta' => (int) $selection['numero_puerta'],
                    'id_qr_token' => (int) $qrToken->id,
                    'credential' => $credential,
                    'selection' => $selection,
                ];

                $existing = DB::table('credencial_ingresos')
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing) {
                    return $this->existingScanResult($existing, $scanContext);
                }

                $registeredAt = now();
                $accessId = DB::table('credencial_ingresos')->insertGetId([
                    'id_credencial' => $scanContext['id_credencial'],
                    'id_feria' => $scanContext['id_feria'],
                    'id_usuario_control' => $scanContext['id_usuario_control'],
                    'numero_puerta' => $scanContext['numero_puerta'],
                    'id_qr_token' => $scanContext['id_qr_token'],
                    'tipo_credencial' => (int) $credential->tipo_credencial,
                    'modalidad_credencial' => (int) $credential->modalidad_credencial,
                    'fecha_ingreso' => $registeredAt,
                    'idempotency_key' => $idempotencyKey,
                    'dispositivo_ref' => $session->dispositivo_ref,
                    'punto_acceso' => 'Puerta '.$scanContext['numero_puerta'],
                    'created_at' => $registeredAt,
                ]);

                return [
                    'duplicate' => false,
                    'data' => $this->scanPayload($accessId, $registeredAt, $scanContext),
                ];
            }, attempts: 3);
        } catch (QueryException $exception) {
            if (! $this->isIdempotencyConflict($exception)) {
                throw $exception;
            }

            $existing = DB::table('credencial_ingresos')
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if (! $existing || ! $scanContext) {
                throw $exception;
            }

            return $this->existingScanResult($existing, $scanContext);
        }
    }

    private function activeSelection(ControlAccessOperator $operator, object $session): array
    {
        if (! $session->id_feria || ! $session->numero_puerta) {
            throw new HttpException(409, 'Seleccione un evento y una puerta antes de escanear.');
        }

        $event = DB::table('eventos')
            ->where('id_feria', $session->id_feria)
            ->lockForUpdate()
            ->first(['id_feria', 'nombre_feria', 'puertas_acceso']);
        $door = (int) $session->numero_puerta;

        $isAssigned = $event
            && in_array($door, $this->availableDoors($event->puertas_acceso), true)
            && DB::table('usuarios_control_asignaciones')
                ->where('id_usuario_control', $operator->getKey())
                ->where('id_feria', $session->id_feria)
                ->where('numero_puerta', $door)
                ->exists();

        if (! $isAssigned) {
            throw new HttpException(409, 'La asignación seleccionada ya no está disponible. Seleccione el evento nuevamente.');
        }

        return [
            'id_feria' => (int) $event->id_feria,
            'nombre_feria' => $event->nombre_feria,
            'numero_puerta' => $door,
        ];
    }

    private function existingScanResult(object $existing, array $context): array
    {
        $matchesRequest = (int) $existing->id_credencial === $context['id_credencial']
            && (int) $existing->id_feria === $context['id_feria']
            && (int) $existing->id_usuario_control === $context['id_usuario_control']
            && (int) $existing->numero_puerta === $context['numero_puerta']
            && (int) $existing->id_qr_token === $context['id_qr_token'];

        if (! $matchesRequest) {
            throw new HttpException(409, 'La clave de operación ya fue utilizada para otro ingreso.');
        }

        return [
            'duplicate' => true,
            'data' => $this->scanPayload((int) $existing->id, $existing->fecha_ingreso, $context),
        ];
    }

    private function scanPayload(
        int $accessId,
        string|\DateTimeInterface $registeredAt,
        array $context,
    ): array {
        $credential = $context['credential'];
        $selection = $context['selection'];

        return [
            'id_ingreso' => $accessId,
            'fecha_ingreso' => $registeredAt instanceof \DateTimeInterface
                ? $registeredAt->format(DATE_ATOM)
                : $registeredAt,
            'credencial' => [
                'id_credencial' => (int) $credential->id_credencial,
                'nombre_persona' => $credential->nombre_persona,
                'ci' => $credential->ci_persona,
                'foto_url' => $credential->foto_persona
                    ? '/api/v1/control-access/credentials/'.$credential->id_credencial.'/photo'
                    : null,
                'tipo_credencial' => (int) $credential->tipo_credencial,
                'modalidad_credencial' => (int) $credential->modalidad_credencial,
            ],
            'evento' => [
                'id_feria' => $selection['id_feria'],
                'nombre_feria' => $credential->nombre_feria,
            ],
            'numero_puerta' => $selection['numero_puerta'],
        ];
    }

    private function isIdempotencyConflict(QueryException $exception): bool
    {
        $message = mb_strtolower($exception->getMessage());

        return str_contains($message, 'credencial_ingresos_idempotency_unique')
            || str_contains($message, 'credencial_ingresos.idempotency_key');
    }

    private function availableDoors(?string $doorList): array
    {
        if ($doorList === null || trim($doorList) === '') {
            return [];
        }

        return collect(explode(';', $doorList))
            ->map(fn (string $door): string => trim($door))
            ->filter(fn (string $door): bool => ctype_digit($door) && (int) $door > 0 && (int) $door <= 65535)
            ->map(fn (string $door): int => (int) $door)
            ->unique()
            ->values()
            ->all();
    }
}
