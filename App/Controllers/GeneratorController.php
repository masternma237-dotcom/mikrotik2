<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\ValidationService;
use App\Services\RouterOSGenerator;
use App\Services\TemplateEngine;
use App\Services\Logger;
use App\Services\RateLimiter;
use Throwable;
class GeneratorController {
    private array $config;
    private Logger $logger;
    private RateLimiter $limiter;
    public function __construct(?array $config = null) {
        $cfgFile = dirname(__DIR__, 2) . '/config/app.php';
        $this->config = $config ?? (is_file($cfgFile) ? (require $cfgFile) : []);
        if (!is_array($this->config)) $this->config = [];
        $this->config += ['version'=>'2.2.0','debug'=>false,'max_body'=>262144,'log_file'=>dirname(__DIR__,2).'/storage/logs/app.log','ratelimit_dir'=>dirname(__DIR__,2).'/storage/ratelimit','rate_limit'=>['max_requests'=>30,'window'=>60]];
        $this->logger = new Logger((string)$this->config['log_file'], (bool)($this->config['debug'] ?? false));
        $rl = $this->config['rate_limit'] ?? [];
        $this->limiter = new RateLimiter((string)$this->config['ratelimit_dir'], (int)($rl['max_requests'] ?? 30), (int)($rl['window'] ?? 60));
    }
    public function handle(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->securityHeaders($method === 'POST');
        if ($method === 'GET') { $this->renderView(); return; }
        if ($method !== 'POST') { $this->json(405, ['ok'=>false,'errors'=>['global'=>'Methode non autorisee.']]); return; }
        $ct = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
        if ($ct !== '' && stripos($ct, 'application/json') === false) { $this->json(415, ['ok'=>false,'errors'=>['global'=>'Content-Type attendu : application/json.']]); return; }
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        if (!$this->limiter->allow($ip)) { $this->logger->info('rate-limit depasse', ['ip'=>$ip]); $this->json(429, ['ok'=>false,'errors'=>['global'=>'Trop de requetes. Reessayez dans une minute.']]); return; }
        $raw = file_get_contents('php://input');
        $max = (int)($this->config['max_body'] ?? 262144);
        if ($raw === false || strlen($raw) > $max) { $this->json(413, ['ok'=>false,'errors'=>['global'=>'Corps de requete trop volumineux (256 Ko max).']]); return; }
        if (trim($raw) === '') { $this->json(400, ['ok'=>false,'errors'=>['global'=>'Corps JSON vide.']]); return; }
        $input = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
            $id = $this->logger->info('JSON invalide', ['erreur'=>json_last_error_msg()]);
            $this->json(400, ['ok'=>false,'errors'=>['global'=>'JSON invalide. Verifiez la syntaxe de la requete.'],'trace'=>$id]);
            return;
        }
        $errors = (new ValidationService($this->config))->validate($input);
        if ($errors) { $this->json(422, ['ok'=>false,'errors'=>$errors]); return; }
        try {
            $script = (new RouterOSGenerator(new TemplateEngine(), $this->config))->generate($input);
        } catch (Throwable $e) {
            $id = $this->logger->error('echec generation', ['exception'=>get_class($e),'message'=>$e->getMessage(),'fichier'=>$e->getFile().':'.$e->getLine()]);
            $detail = (bool)($this->config['debug'] ?? false) ? (' : '.$e->getMessage()) : '';
            $msg = 'Erreur interne de generation.' . $detail . ' (ref '.$id.')';
            $this->json(500, ['ok'=>false,'errors'=>['global'=>$msg],'trace'=>$id]);
            return;
        }
        $this->json(200, ['ok'=>true,'script'=>$script]);
    }
    private function renderView(): void {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        $view = dirname(__DIR__, 2) . '/App/View/layout.php';
        $legacy = dirname(__DIR__) . '/View/layout.php';
        if (is_file($view)) require_once $view;
        elseif (is_file($legacy)) require_once $legacy;
        else { http_response_code(500); echo 'Vue introuvable.'; }
    }
    private function securityHeaders(bool $isApi): void {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: no-referrer');
        if ($isApi) { header('Content-Security-Policy: default-src \'none\''); }
        else { header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self'; frame-ancestors 'self'"); }
    }
    private function json(int $code, array $payload): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
