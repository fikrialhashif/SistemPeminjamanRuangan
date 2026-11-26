<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Resource;
use App\Services\QrTokenService;
use Illuminate\Http\Response;

class QrDisplayController extends Controller
{
    public function __construct(
        protected QrTokenService $qrTokenService,
    ) {}

    public function show(Resource $resource): Response
    {
        $this->ensurePermission(Permission::RESOURCES_VIEW);

        return $this->render(
            title: 'Check-in '.$resource->name,
            kicker: 'QR pintu · statis',
            name: $resource->name,
            detail: 'Lokasi: '.($resource->location?->name ?? 'Belum diatur'),
            hint: 'Tempel QR ini di pintu. Arahkan kamera aplikasi saat check-in.',
            foot: 'QR permanen · tidak perlu diganti',
            token: $this->qrTokenService->generate($resource->id)['token'],
            ariaLabel: 'Kode QR pintu',
        );
    }

    public function assetLabel(Resource $resource): Response
    {
        $this->ensurePermission(Permission::RESOURCES_VIEW);

        abort_unless($resource->asset !== null, 404, 'Resource ini bukan aset.');

        return $this->render(
            title: 'Label aset '.$resource->name,
            kicker: 'Kode aset · statis',
            name: $resource->name,
            detail: 'Kode '.$resource->asset->code.' · '.($resource->location?->name ?? 'Belum diatur'),
            hint: 'Tempel pada unit aset. Pindai saat serah-terima.',
            foot: 'Identitas aset · bukan tanda izin',
            token: $this->qrTokenService->generateForAsset($resource->asset)['token'],
            ariaLabel: 'Kode QR aset',
        );
    }

    private function render(
        string $title,
        string $kicker,
        string $name,
        string $detail,
        string $hint,
        string $foot,
        string $token,
        string $ariaLabel,
    ): Response {
        $title = e($title);
        $kicker = e($kicker);
        $name = e($name);
        $detail = e($detail);
        $hint = e($hint);
        $foot = e($foot);
        $ariaLabel = e($ariaLabel);
        $token = json_encode($token, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Hanken+Grotesk:wght@400;500;600;700&display=swap');
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f1f5f2;color:#17251e;font-family:'Hanken Grotesk',system-ui,sans-serif;line-height:1.5}
.kiosk{width:min(480px,100%);overflow:hidden;border:1px solid #b9cbbf;border-radius:8px;background:#fff;box-shadow:0 30px 60px -40px rgba(22,56,43,.55)}
.kiosk-head{padding:26px 26px 22px;background:#2b5f4a;color:#fff;text-align:center}
.kiosk-kicker{margin:0;color:#cfe0d7;font-size:12.5px}
.kiosk-name{margin:5px 0 0;font-family:'Bricolage Grotesque',sans-serif;font-size:clamp(1.5rem,5vw,2rem);font-weight:800;letter-spacing:-.02em;line-height:1.05}
.kiosk-detail{margin:10px 0 0;color:#e4efe8;font-size:13px}
.kiosk-body{display:flex;flex-direction:column;align-items:center;gap:16px;padding:28px 26px 24px}
.qr{display:grid;place-items:center;padding:14px;border:1px solid #d3dfd7;border-radius:6px;background:#fff}
.qr img{display:block;width:min(280px,68vw);height:auto;image-rendering:pixelated}
.kiosk-hint{max-width:34ch;color:#5d6f65;font-size:14px;text-align:center}
.kiosk-actions{display:flex;justify-content:center;padding:0 24px 22px}
.kiosk-print{padding:10px 18px;border:1px solid #2b5f4a;border-radius:4px;background:#2b5f4a;color:#fff;font:inherit;font-weight:700;cursor:pointer}
.kiosk-print:focus-visible{outline:3px solid #eca017;outline-offset:2px}
.kiosk-foot{display:flex;align-items:center;justify-content:center;gap:8px;padding:14px 24px;border-top:1px solid #d3dfd7;background:#fbeac9;color:#6b4a08;font-size:12.5px}
@media print{
  body{padding:0;background:#fff}
  .kiosk{width:100%;max-width:none;border-color:#17251e;box-shadow:none}
  .kiosk-head{background:#2b5f4a !important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .kiosk-foot{background:#fbeac9 !important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .kiosk-actions{display:none}
  .qr img{width:240px}
}
</style>
</head>
<body>
<main class="kiosk">
<header class="kiosk-head">
<p class="kiosk-kicker">{$kicker}</p>
<h1 class="kiosk-name">{$name}</h1>
<p class="kiosk-detail">{$detail}</p>
</header>
<div class="kiosk-body">
<div class="qr" id="qr" aria-label="{$ariaLabel}"></div>
<p class="kiosk-hint">{$hint}</p>
</div>
<div class="kiosk-actions"><button type="button" class="kiosk-print" onclick="window.print()">Cetak QR</button></div>
<footer class="kiosk-foot">{$foot}</footer>
</main>
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1/qrcode.min.js"></script>
<script>
var qr=qrcode(0,'M');qr.addData({$token});qr.make();
document.getElementById('qr').innerHTML=qr.createImgTag(8,8);
</script>
</body>
</html>
HTML;

        return response($html)->header('Content-Type', 'text/html');
    }
}
