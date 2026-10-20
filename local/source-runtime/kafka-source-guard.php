<?php
// Runs before historical PHP. Allow the cart summary, never the checkout steps.
if (realpath(isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '') !== '/var/www/html/index.php') {
    return;
}
$controller = isset($_POST['controller']) ? $_POST['controller'] : (isset($_GET['controller']) ? $_GET['controller'] : '');
if (!is_string($controller)) { http_response_code(400); exit; }
$controller = strtolower($controller);
if ($controller === 'order') {
    $step = isset($_POST['step']) ? $_POST['step'] : (isset($_GET['step']) ? $_GET['step'] : '0');
    $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
    if (!in_array($method, ['GET','HEAD'], true) || !is_scalar($step) || !in_array((string)$step, ['','0'], true) || isset($_GET['submitReorder'])) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="cs"><meta charset="utf-8"><title>Lokální kontrola zálohy</title><h1>Dokončení objednávky je v této kopii vypnuté</h1><p>Košík můžete prohlížet a měnit. Nákup, platby a zákaznické e-maily se zde netestují.</p><p><a href="/index.php?controller=order">Zpět do košíku</a></p></html>';
        exit;
    }
    ob_start(function ($html) {
        $notice = '<div style="padding:12px;background:#fff3cd;color:#513b00;text-align:center">Lokální kopie zálohy: košík lze vyzkoušet. Dokončení objednávky, platby a zákaznické e-maily jsou vypnuté.</div>';
        return preg_replace('/(<body\b[^>]*>)/i', '$1' . $notice, $html, 1);
    });
}
if (in_array($controller, ['orderopc','authentication','contact','password'], true) || (isset($_POST['fc']) && $_POST['fc'] === 'module') || (isset($_GET['fc']) && $_GET['fc'] === 'module')) {
    http_response_code(403);
    exit('Tento vstup je v lokální zdrojové kopii vypnutý.');
}
