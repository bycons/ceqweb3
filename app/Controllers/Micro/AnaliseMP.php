<?php

namespace App\Controllers\Micro;

use App\Controllers\BaseController;
use App\Entities\Microb\EntMicrobAnaliseMP;
use App\Models\Microb\MicrobAnaliseModel;
use App\Models\Config\ConfigStatusModel;
use App\Models\Config\ConfigUsuarioModel;
use App\Models\LogMonModel;

class AnaliseMP extends BaseController
{
    public $data      = [];
    public $permissao = '';
    public $analise;
    public $usuario;

    /**
     * Construtor da AnaliseMP
     * construct
     */
    public function __construct()
    {
        $this->data      = session()->getFlashdata('dados_tela');
        $this->permissao = $this->data['permissao'];
        $this->analise   = model(MicrobAnaliseModel::class);
        $this->status    = model(ConfigStatusModel::class);
        $this->usuario   = model(ConfigUsuarioModel::class);

        if ($this->data['erromsg'] != '') {
            $this->__erro();
        }
    }

    /**
     * Erro de Acesso
     * erro
     */
    public function __erro()
    {
        echo view('vw_semacesso', $this->data);
    }

    /**
     * Tela de Abertura
     * index
     */
    public function index()
    {
        $entity = new EntMicrobAnaliseMP();
        $fields = $entity->campos;

        $secao[0]     = 'Buscar';
        $campos[0][] = $fields['sal_prod'];
        $campos[0][] = $fields['sal_lote'];
        $campos[0][] = $fields['sal_data'];
        $campos[0][] = "<div id='ig_btBuscarAna' class='d-inline-block ms-1'>";
        $campos[0][] = $fields['sal_btlote'];
        $campos[0][] = "</div>";

        $colunas = ['Produto', 'Fabricante', 'Lote', 'Validade', 'Data', 'Status', 'Usuário'];

        $this->data['secoes']  = $secao;
        $this->data['campos']  = $campos;
        $this->data['colunas'] = $colunas;
        $this->data['destino'] = 'lista';

        echo view('vw_filtro', $this->data);
    }

    /**
     * Listagem
     * lista
     *
     * @return void
     */
    public function lista()
    {
        // Pega os parametros enviados pela requisição
        $vars = $_REQUEST;
        $pro  = trim($vars['codPro'] ?? '');
        $lot  = trim($vars['codLot'] ?? '');
        $per  = trim($vars['perAnalise'] ?? '');

        $dtIni = $dtFim = null;
        // Se um período foi informado
        if ($per !== '' && str_contains($per, ' - ')) {
            [$dtIniStr, $dtFimStr] = explode(' - ', $per);
            $dtIni = data_db(trim($dtIniStr));
            $dtFim = data_db(trim($dtFimStr));
        }

        // Transforma a string de produtos num array
        $proIds = $pro !== '' ? array_filter(explode(',', $pro)) : [];

        // Busca no banco as análises que batem com os filtros
        $dados_analise = $this->analise->getAnaliseFiltro($proIds, $lot, $dtIni, $dtFim, true);
        // Extrai só os ids de todas as analises encontradas
        $ana_ids       = array_column($dados_analise, 'ana_id');
        // Busca o ÚLTIMO log de cada uma dessas análises
        $log           = buscaLogTabela('pro_mic_analise', $ana_ids);

        // Instancia o model que acessa a collection de logs no MongoDB
        $logdb = new LogMonModel();

        // busca o histórico bruto de cada análise e coleta os stt_id e usu_id usados
        $historicosPorAna = [];
        $sttIdsHistorico  = [];
        $usuIdsHistorico  = [];

        foreach ($dados_analise as $ana) {

            // Para cada análise já filtrada, busca TODO o histórico de logs dela no MongoDB
            $logsAna   = $logdb->get_logs_all('pro_mic_analise', strval($ana['ana_id']));
            $historico = [];

            foreach ($logsAna as $item) {
                // Ignora qualquer log que não tenha mudança de status registrada
                if (!isset($item->log_dados->stt_id)) {
                    continue;
                }

                // Ignora logs fora do período informado (dtIni/dtFim)
                $dataLog = substr($item->log_data, 0, 10);
                if ($dtIni !== null && $dataLog < $dtIni) {
                    continue;
                }
                if ($dtFim !== null && $dataLog > $dtFim) {
                    continue;
                }

                $sttId             = $item->log_dados->stt_id;
                // acumula todos os stt_id usados, de todas as análises,
                $sttIdsHistorico[] = $sttId;

                $usuId = $item->log_id_usuario ?? '';
                // se for um ID numérico, acumula para buscar o nome depois; senão, já é o nome do usuário
                if (ctype_digit(strval($usuId))) {
                    $usuIdsHistorico[] = (int) $usuId;
                }

                // Monta um registro do histórico com data formatada, o status (ainda como ID) e o usuário (ainda como ID ou nome)
                $historico[] = [
                    'data'    => date('d/m/Y H:i:s', strtotime($item->log_data)),
                    'stt_id'  => $sttId,
                    'usuario' => $usuId,
                ];
            }

            // get_logs_all vem do mais recente pro mais antigo
            $historicosPorAna[$ana['ana_id']] = array_reverse($historico);
        }

        // Busca a cor de todos os status usados no histórico
        $statusMap = $this->status->getStatusPorIds(array_unique($sttIdsHistorico));
        // Busca o nome de todos os usuários (por ID) usados no histórico
        $usuarioMap = $this->usuario->getUsuariosPorIds(array_unique($usuIdsHistorico));

        // monta o retorno final
        $analises = [];

        foreach ($dados_analise as $ana) {
            // Percorre o histórico bruto dessa análise
            $historico = array_map(function ($mov) use ($statusMap, $usuarioMap) {
                // Busca no mapa de status
                $s = $statusMap[$mov['stt_id']] ?? null;

                return [
                    'dataHora'    => $mov['data'],
                    // Se achou o status no mapa, monta o badge colorido
                    'status'  => $s
                        ? fmtEtiquetaCor($s['stt_cor'], $s['stt_nome'])
                        : $mov['stt_id'],
                    // Se o usuário estava gravado como ID, busca o nome no mapa; senão já é o nome
                    'usuario' => $usuarioMap[$mov['usuario']] ?? $mov['usuario'],
                ];
            }, $historicosPorAna[$ana['ana_id']] ?? []); // histórico dessa análise específica

            // Monta o registro final da análise, com os dados atuais dela
            $analises[] = [
                'ana_id'      => $ana['ana_id'],
                'Produto'     => ($ana['pro_codpro'] ?? '') . ' - ' . ($ana['pro_despro'] ?? ''),
                'Fabricante'  => $ana['fab_apeFab'] ?? '',
                'lote'        => $ana['lot_lote'] ?? '',
                'validade'    => data_br($ana['lot_validade'] ?? ''),
                'validadeord' => $ana['lot_validade'] ?? '',
                'dataHora'    => data_br($ana['ana_data'] ?? ''),
                'dataHoraord' => $ana['ana_data'] ?? '',
                'status'      => fmtEtiquetaCor($ana['stt_cor'] ?? '', $ana['stt_nome'] ?? ''),
                'usuario'     => buscaUsuarioLog($log[$ana['ana_id']] ?? []),
                'historico'   => $historico,
            ];
        }

        echo json_encode($analises);
    }
}
