<?php

namespace App\Services;

use App\Models\Credencial;
use App\Models\CredencialQrToken;
use App\Models\CredencialTipoDiseno;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CredentialImageService
{
    public function generateDigital(Credencial $credential): string
    {
        $credential->loadMissing(['expositor', 'feria', 'empresa']);

        $design = CredencialTipoDiseno::query()
            ->where('id_feria', $credential->id_feria)
            ->where('tipo_credencial', $this->credentialTypeName((int) $credential->tipo_credencial))
            ->first();

        if (! $design) {
            throw ValidationException::withMessages([
                'credential' => 'No existe un diseño para esta credencial.',
            ]);
        }

        $designPath = $this->designPath($design->ruta);
        if (! is_file($designPath)) {
            throw ValidationException::withMessages([
                'credential' => 'El diseño de la credencial no está disponible.',
            ]);
        }

        $image = new Imagick($designPath);
        $image->setImageFormat('png');
        $width = $image->getImageWidth();
        $height = $image->getImageHeight();
        $font = $this->fontPath();

        $this->addCircularPhoto($image, $credential->expositor?->foto, $width, $height);
        $this->addWrappedName($image, $credential->expositor?->nombre ?: 'Persona acreditada', 8, 56, 69.6, $font);
        $document = 'CI: '.trim(($credential->expositor?->ci ?? '').' '.($credential->expositor?->exp ?? ''));
        $this->addCenteredText($image, $document, 8, 73, 69.6, 40, false, $font);
        $this->addQrCode($image, $this->qrToken($credential), 20.3, 79, 45, $width, $height);

        $image->setImagePage(0, 0, 0, 0);
        $content = $image->getImagesBlob();
        $image->clear();
        $image->destroy();

        return $content;
    }

    private function addCenteredText(Imagick $image, string $text, float $xMm, float $yMm, float $widthMm, int $fontSize, bool $bold, string $font): void
    {
        $draw = $this->textDraw($image, $fontSize, $bold, $font);
        $text = trim($text);
        $metrics = $image->queryFontMetrics($draw, $text);
        $x = (($xMm + ($widthMm / 2)) / 85.6) * $image->getImageWidth() - ($metrics['textWidth'] / 2);
        $y = ($yMm / 133.3) * $image->getImageHeight() + $metrics['ascender'];
        $image->annotateImage($draw, $x, $y, 0, $text);
    }

    private function addWrappedName(Imagick $image, string $name, float $xMm, float $yMm, float $widthMm, string $font): void
    {
        $name = mb_convert_case(mb_strtolower(trim($name), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        $maxWidth = ($widthMm / 85.6) * $image->getImageWidth();
        $fontSize = 55;

        do {
            $draw = $this->textDraw($image, $fontSize, true, $font);
            $lines = $this->wrapText($image, $draw, $name, $maxWidth);
            $fontSize -= 2;
        } while (count($lines) > 3 && $fontSize >= 22);

        $lineHeight = ($image->getImageHeight() / 133.3) * 5.2;
        $top = ($yMm / 133.3) * $image->getImageHeight();

        foreach ($lines as $index => $line) {
            $metrics = $image->queryFontMetrics($draw, $line);
            $x = (($xMm + ($widthMm / 2)) / 85.6) * $image->getImageWidth() - ($metrics['textWidth'] / 2);
            $y = $top + $metrics['ascender'] + ($index * $lineHeight);
            $image->annotateImage($draw, $x, $y, 0, $line);
        }
    }

    /**
     * @return list<string>
     */
    private function wrapText(Imagick $image, ImagickDraw $draw, string $text, float $maxWidth): array
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            if ($image->queryFontMetrics($draw, $candidate)['textWidth'] <= $maxWidth) {
                $current = $candidate;

                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
                $current = '';
            }

            if ($image->queryFontMetrics($draw, $word)['textWidth'] <= $maxWidth) {
                $current = $word;

                continue;
            }

            foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
                $candidate = $current === '' ? $character : $current.$character;
                if ($image->queryFontMetrics($draw, $candidate)['textWidth'] > $maxWidth && $current !== '') {
                    $lines[] = $current;
                    $current = $character;
                } else {
                    $current = $candidate;
                }
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        if (count($lines) <= 3) {
            return $lines;
        }

        $visibleLines = array_slice($lines, 0, 3);
        $lastLine = $visibleLines[2].'...';
        while ($image->queryFontMetrics($draw, $lastLine)['textWidth'] > $maxWidth && mb_strlen($visibleLines[2]) > 1) {
            $visibleLines[2] = mb_substr($visibleLines[2], 0, -1);
            $lastLine = $visibleLines[2].'...';
        }
        $visibleLines[2] = $lastLine;

        return $visibleLines;
    }

    private function textDraw(Imagick $image, int $fontSize, bool $bold, string $font): ImagickDraw
    {
        $draw = new ImagickDraw;
        $draw->setFont($font);
        $draw->setFontSize($fontSize * ($image->getImageWidth() / 856));
        $draw->setFillColor(new ImagickPixel('white'));
        $draw->setStrokeColor(new ImagickPixel('rgba(0,0,0,0.35)'));
        $draw->setStrokeWidth($bold ? 1.2 : 0.6);

        return $draw;
    }

    private function addCircularPhoto(Imagick $image, ?string $photoPath, int $canvasWidth, int $canvasHeight): void
    {
        if (! $photoPath) {
            return;
        }

        $path = Storage::disk('legacy_photos')->path(basename($photoPath));
        if (! is_file($path)) {
            return;
        }

        $photo = new Imagick($path);
        $side = (int) round((32 / 85.6) * $canvasWidth);
        $photo->cropThumbnailImage($side, $side);
        $mask = new Imagick;
        $mask->newImage($side, $side, new ImagickPixel('transparent'));
        $mask->setImageFormat('png');
        $maskDraw = new ImagickDraw;
        $maskDraw->setFillColor(new ImagickPixel('white'));
        $maskDraw->ellipse($side / 2, $side / 2, $side / 2, $side / 2, 0, 360);
        $mask->drawImage($maskDraw);
        $photo->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);
        $photo->compositeImage($mask, Imagick::COMPOSITE_COPYOPACITY, 0, 0);

        $x = (int) round((26.8 / 85.6) * $canvasWidth);
        $y = (int) round((20 / 133.3) * $canvasHeight);
        $image->compositeImage($photo, Imagick::COMPOSITE_OVER, $x, $y);
        $photo->clear();
        $mask->clear();
    }

    private function addQrCode(Imagick $image, string $token, float $xMm, float $yMm, float $sizeMm, int $canvasWidth, int $canvasHeight): void
    {
        $qr = new Imagick;
        $qr->readImageBlob((string) QrCode::format('png')->size(450)->margin(1)->generate($token));
        $size = (int) round(($sizeMm / 85.6) * $canvasWidth);
        $qr->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1);
        $x = (int) round(($xMm / 85.6) * $canvasWidth);
        $y = (int) round(($yMm / 133.3) * $canvasHeight);
        $image->compositeImage($qr, Imagick::COMPOSITE_OVER, $x, $y);
        $qr->clear();
    }

    private function qrToken(Credencial $credential): string
    {
        $existing = CredencialQrToken::query()
            ->where('id_credencial', $credential->id_credencial)
            ->where('activo', true)
            ->latest('version')
            ->first();

        if ($existing) {
            try {
                return $this->legacyEncrypter()->decryptString($existing->token_cifrado);
            } catch (\Throwable) {
                $existing->update([
                    'activo' => false,
                    'revocado_at' => now(),
                    'motivo_revocacion' => 'rotacion_api_token_incompatible',
                ]);
            }
        }

        $encrypter = $this->legacyEncrypter();

        $token = 'crd_'.Str::lower(Str::random(48));
        CredencialQrToken::query()->create([
            'id_credencial' => $credential->id_credencial,
            'token_hash' => hash('sha256', $token),
            'token_cifrado' => $encrypter->encryptString($token),
            'version' => ((int) CredencialQrToken::query()->where('id_credencial', $credential->id_credencial)->max('version')) + 1,
            'activo' => true,
        ]);

        return $token;
    }

    private function legacyEncrypter(): Encrypter
    {
        $key = (string) config('filesystems.legacy_app_key');
        if (! str_starts_with($key, 'base64:')) {
            throw ValidationException::withMessages([
                'credential' => 'La generación requiere configurar LEGACY_APP_KEY con la APP_KEY de intranetfexco.',
            ]);
        }

        return new Encrypter(base64_decode(substr($key, 7)), config('app.cipher'));
    }

    private function designPath(string $path): string
    {
        return rtrim((string) config('filesystems.legacy_designs_path'), '/').'/'.basename($path);
    }

    private function fontPath(): string
    {
        return is_file('/System/Library/Fonts/Supplemental/Arial.ttf')
            ? '/System/Library/Fonts/Supplemental/Arial.ttf'
            : '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
    }

    private function credentialTypeName(int $type): string
    {
        return match ($type) {
            1 => 'Expositor',
            2 => 'Prensa',
            3 => 'Oficial',
            4 => 'Servicios',
            5 => 'Negocios',
            default => 'Expositor',
        };
    }
}
