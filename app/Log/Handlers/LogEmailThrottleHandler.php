<?php

namespace App\Log\Handlers;

use CodeIgniter\Email\Email;
use CodeIgniter\Log\Handlers\FileHandler;
use Throwable;

class LogEmailThrottleHandler extends FileHandler
{
    protected array $config;
    protected string $emailTo;
    // protected string $dateFormat = 'd-m-Y H:i:s';
    protected $logPath;

    public function __construct(array $config)
    {
        // $this->config = $config;
        parent::__construct($config);
        $this->emailTo = 'douglas@4web.net.br';
        $this->logPath = rtrim($this->path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    public function handle($level, $message): bool
    {
        // Fora de produção não envia e-mail de erro (evita recursão via log_message do Email::spoolEmail)
        if (ENVIRONMENT !== 'production') {
            return true;
        }

        // Trace temporário — remover após diagnóstico
        file_put_contents(
            WRITEPATH . 'logs/_trace.log',
            get_class($this) . " -> $level\n",
            FILE_APPEND
        );
        $debugFile = $this->logPath . '/emaildebug.log';

        try {

            // file_put_contents($debugFile, "[1] START\n", FILE_APPEND);

            $level = strtoupper((string) $level);
            $dataatual = date('d/m/Y H:i:s');

            // file_put_contents($debugFile, "[2] LEVEL: $level\n", FILE_APPEND);

            $text = $message instanceof Throwable
                ? $message->__toString()
                : (string) $message;

            // file_put_contents($debugFile, "[3] MESSAGE OK\n", FILE_APPEND);

            // Informações básicas
            $ip        = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
            $uri       = $_SERVER['REQUEST_URI'] ?? 'indefinido';
            $module    = explode('/', trim((string) $uri, '/'))[0] ?? 'indefinido';
            $method    = $_SERVER['REQUEST_METHOD'] ?? 'indefinido';
            $baseURL = config('App')->baseURL;

            // Dados do usuário
            $usuId   = $_SESSION['usu_id'] ?? 'não identificado';
            $usuNome = $_SESSION['usu_nome'] ?? 'não identificado';

            // file_put_contents($debugFile, "[4] SERVER DATA OK\n", FILE_APPEND);

            // CACHE TESTE
            try {
                // file_put_contents($debugFile, "[5] CACHE INIT\n", FILE_APPEND);

                $cacheConfig = new \Config\Cache();
                $cache = new \CodeIgniter\Cache\Handlers\FileHandler($cacheConfig);

                $cacheKey = 'log_email_hash_' . md5($level . '_' . $ip . '_' . $module);
                $currentHash = md5($level . $text);
                $lastHash = $cache->get($cacheKey);

                // file_put_contents($debugFile, "[6] CACHE GET OK\n", FILE_APPEND);

                if ($currentHash === $lastHash) {
                    // file_put_contents($debugFile, "[7] CACHE HIT (IGNORED)\n", FILE_APPEND);
                    return true;
                }

                $cache->save($cacheKey, $currentHash, 600);

                // file_put_contents($debugFile, "[8] CACHE SAVE OK\n", FILE_APPEND);
            } catch (Throwable $e) {
                file_put_contents($debugFile, "[ERRO CACHE] " . $e->getMessage() . "\n", FILE_APPEND);
            }

            // EMAIL TESTE
            try {

                // file_put_contents($debugFile, "[9] EMAIL INIT\n", FILE_APPEND);

                $email = service('email');

                if (!$email) {
                    file_put_contents($debugFile, "[ERRO] EMAIL SERVICE NULL\n", FILE_APPEND);
                    return false;
                }

                $email->setTo($this->emailTo);

                $isRota404Anonima = str_starts_with(
                    haystack: trim($text),
                    needle: 'Rota 404'
                ) && $usuId === 'não identificado';

                $subject = $isRota404Anonima
                    ? "CeqWeb3 ROTA ERRADA em {$dataatual} - [{$level}]"
                    : "CeqWeb3 Log automático em {$dataatual} - [{$level}]";

                $email->setSubject($subject);

                $body = <<<HTML
                        <b>Nível:</b> $level<br>
                        <b>Data:</b> $dataatual<br>
                        <b>IP:</b> $ip<br>
                        <b>Controler:</b> $module<br>
                        <b>URI:</b> $uri<br>
                        <b>Método:</b> $method<br>
                        <b>Ambiente:</b>$baseURL<br><br>

                        <b>Usuário logado:</b><br>
                        ID: $usuId<br>
                        Nome: $usuNome<br><br>

                        <b>Mensagem do log:</b><br>
                        <pre>$text</pre>
                        HTML;


                $email->setMessage($body);
                // $email->setMessage($text);

                // file_put_contents($debugFile, "[10] EMAIL READY\n", FILE_APPEND);

                $enviar = @$email->send();

                // file_put_contents($debugFile, "[11] EMAIL SEND: " . json_encode($enviar) . "\n", FILE_APPEND);

                return true;
            } catch (Throwable $e) {
                file_put_contents($debugFile, "[ERRO EMAIL] " . $e->__toString() . "\n", FILE_APPEND);
                return false;
            }
        } catch (Throwable $e) {

            file_put_contents($debugFile, "[ERRO GERAL] " . $e->__toString() . "\n", FILE_APPEND);

            return false;
        }
    }
}
