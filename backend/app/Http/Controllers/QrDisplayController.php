<?php

namespace App\Http\Controllers;

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
        $qr = $this->qrTokenService->generate($resource->id);

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>QR - {$resource->name}</title>
<meta http-equiv="refresh" content="25">
<style>body{display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;font-family:sans-serif;flex-direction:column}#qr{max-width:400px}h2{margin-bottom:0}</style>
</head>
<body>
<h2>{$resource->name}</h2>
<p>Scan to check in</p>
<div id="qr"></div>
<p style="font-size:12px;color:#888">Refreshes automatically</p>
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1/qrcode.min.js"></script>
<script>
var qr=qrcode(0,'M');qr.addData('{$qr['token']}');qr.make();
document.getElementById('qr').innerHTML=qr.createImgTag(8,8);
</script>
</body>
</html>
HTML;

        return response($html)->header('Content-Type', 'text/html');
    }
}
