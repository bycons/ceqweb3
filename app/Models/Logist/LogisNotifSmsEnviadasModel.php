<?php

namespace App\Models\Logis;

use CodeIgniter\Model;

/**
 * Histórico de SMS Enviados (log_notif_sms_enviadas, prefixo nse_).
 * Tela só de leitura pela administração (gravação é feita pelo Controller
 * CLI de disparo, fora do escopo deste ciclo) — mesmo padrão de
 * App\Controllers\Config\CfgLoguser, sem callbacks de auditoria.
 */
class LogisNotifSmsEnviadasModel extends Model
{
    protected $DBGroup    = 'dbLogistica';
    protected $table      = 'log_notif_sms_enviadas';
    protected $primaryKey = 'nse_id';

    protected $returnType = 'array';

    protected $allowedFields = ['nse_chave', 'nse_nsc_id', 'nse_data_envio'];

    /**
     * Retorna os SMS enviados no período informado, com o nome da regra
     * (nsc_nome) via JOIN, opcionalmente filtrado por regra (nse_nsc_id).
     *
     * LEFT JOIN (não INNER): uma linha de log_notif_sms_enviadas com
     * nse_nsc_id órfão (regra excluída, ou convertida para 0 pela correção
     * de schema físico em dev) não pode sumir silenciosamente da grid —
     * aparece com rótulo "Regra desconhecida/excluída" (apontamento
     * não-bloqueante do byrev).
     *
     * @param string   $dataIni Y-m-d
     * @param string   $dataFim Y-m-d
     * @param int|null $nscId
     */
    public function getEnviadasPeriodo(string $dataIni, string $dataFim, ?int $nscId = null): array
    {
        $builder = $this->builder();
        $builder->select("log_notif_sms_enviadas.*, COALESCE(log_notif_sms_config.nsc_nome, 'Regra desconhecida/excluída') as nsc_nome");
        $builder->join('log_notif_sms_config', 'log_notif_sms_config.nsc_id = log_notif_sms_enviadas.nse_nsc_id', 'left');
        $builder->where('log_notif_sms_enviadas.nse_data_envio >=', $dataIni . ' 00:00:00');
        $builder->where('log_notif_sms_enviadas.nse_data_envio <=', $dataFim . ' 23:59:59');

        if ($nscId) {
            $builder->where('log_notif_sms_enviadas.nse_nsc_id', $nscId);
        }

        $builder->orderBy('log_notif_sms_enviadas.nse_data_envio', 'DESC');

        return $builder->get()->getResultArray();
    }

    /**
     * Deduplicação: verifica se já existe registro de envio para a chave
     * (ex: "REN:1234" ou "SALDO:2026-07-16") e a regra informada.
     */
    public function jaEnviado(string $chave, int $nscId): bool
    {
        return $this->where('nse_chave', $chave)->where('nse_nsc_id', $nscId)->countAllResults() > 0;
    }

    /**
     * Registra o envio (chave de deduplicação) após o disparo dos SMS de
     * uma regra.
     */
    public function registrar(string $chave, int $nscId): void
    {
        $this->insert(['nse_chave' => $chave, 'nse_nsc_id' => $nscId, 'nse_data_envio' => date('Y-m-d H:i:s')]);
    }
}
