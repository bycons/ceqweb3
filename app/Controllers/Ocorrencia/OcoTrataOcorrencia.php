<?php

namespace App\Controllers\Ocorrencia;

use App\Controllers\BaseController;
use App\Controllers\Ocorrencia\OcoOcorrencia;

use App\Entities\Ocorrencia\EntOcoOcorrencia;
use App\Entities\Ocorrencia\EntOcoSubtOcorrencia;
use App\Entities\Ocorrencia\EntOcoTratativa;
use App\Models\Ocorre\OcorreOcorrenciaModel;
use App\Models\Ocorre\OcorreOcorrenciaAcaoModel;
use App\Models\Ocorre\OcorreSubtOcorrenciaModel;
use App\Models\Ocorre\OcorreTipoAcaoModel;
use App\Models\Fornec\FornecNotifDesvioModel;
use App\Models\Produt\ProdutProdutoModel;
use App\Models\Produt\ProdutLoteModel;
use App\Models\Config\ConfigStatusModel;
use App\Models\Estoqu\EstoquRequisicaoModel;
use App\Models\Estoqu\EstoquRequisicaoProdutoModel;
use App\Models\Estoqu\EstoquRequisicaoProdutoAtendimentoModel;

class OcoTrataOcorrencia extends BaseController
{
    public $data = [];
    public $permissao;
    public $ocorrencia;
    public $ocorrenciaAcao;
    public $subtocorrencia;
    public $tipoacao;

    public function __construct()
    {
        $this->data      = session()->getFlashdata('dados_tela');
        $this->permissao = $this->data['permissao'];

        // Inicialização dos models auxiliares
        $this->ocorrencia     = new OcorreOcorrenciaModel();
        $this->ocorrenciaAcao = new OcorreOcorrenciaAcaoModel();
        $this->subtocorrencia = new OcorreSubtOcorrenciaModel();
        $this->tipoacao       = new OcorreTipoAcaoModel();

        // debug($this->data['erromsg'], true);
        if ($this->data['erromsg'] != '') {
            $this->__erro();
        }
    }
    /**
     * Erro de Acesso
     * errof7
     */
    public function __erro()
    {
        echo view('vw_semacesso', $this->data);
    }

    /**
     * Tela de abertura
     * index
     *
     * @param mixed $id
     * @return void
     */
    public function index()
    {
        $this->data['colunas']   = montaColunasLista($this->data, 'oco_id');
        $this->data['url_lista'] = base_url($this->data['controler'] . '/lista');
        // Renderiza view de listagem
        echo view('vw_lista', $this->data);
    }

    /**
     * Tela de listagem
     * lista
     *
     * @param mixed $id
     * @return void
     */
    public function lista()
    {
        // Monta definição dos campos da listagem
        $campos = montaColunasCampos($this->data, 'oco_id');
        $dados  = $this->ocorrencia->getListaPendente([28, 37]);
        // Filtra por perfil
        $dados = filtrarPorPerfil($dados);

        // Caso não existam registros
        if (! $dados) {
            return $this->response->setJSON(['data' => []]);
        }

        $oco_ids    = array_map(fn($o) => $o->oco_id, $dados);
        // debug($oco_ids);
        $logGeracao = buscaLogTabelaFirst('oco_ocorrencia', $oco_ids);
        // debug($logGeracao, true);

        $this->data['exclusao']    = false; // não tem exclusão
        $this->data['edicao']      = false; // não tem edição
        $this->data['allconsulta'] = true;

        $base_url = base_url($this->data['controler']);

        // Processa cada ocorrência
        foreach ($dados as $nov) {
            unset($nov->oco_ativo);
            // Usuário que realizou a última alteração
            if ($nov->usu_nome == null) {
                $nov->usu_nome    = $logGeracao[$nov->oco_id]['usua_alterou'] ?? '';
            }

            // // Define usuário de finalização se estiver finalizada
            // if ((int) $nov->stt_id === 30) {
            //     $nov->usu_fina = $usuLog;
            // } else {
            //     $nov->usu_fina = '';
            // }

            $nov->acao_person = [];

            // Botão de finalizar se estiver pendente
            // if (trim($nov->stt_nome ?? '') === 'Pendente') {
            $url_finalizar      = $base_url . '/finalizar/' . $nov->oco_id;
            $nov->acao_person[] = "
                    <button class='btn btn-outline-success btn-sm border-0 mx-0 fs-0'
                        data-mdb-toggle='tooltip'
                        data-mdb-placement='top'
                        title='Finalizar Tratativa'
                        onclick='redireciona(\"$url_finalizar\")'>
                        <i class='fas fa-check'></i>
                    </button>
                ";
            // }
        }
        // Retorna JSON formatado para DataTable
        return $this->response->setJSON([
            'data' => montaListaColunasEnt($this->data, 'oco_id', $dados, $campos[1]),
        ]);
    }

    /**
     * visualização
     * show
     *
     * @param mixed $id
     * @return void
     */
    public function show($id)
    {
        return redirect()->to('/OcoOcorrencia/show/' . $id);
    }

    /**
     * RN03.15 — retorna o HTML de uma nova linha de "ação extra" (avulsa),
     * para ser adicionada dinamicamente na aba Ações de finalizar().
     * Bloqueante 2 (revisão 01): a linha traz também os campos condicionais
     * (oco_justi/tmo_id/mod_id+tel_id/stt_id) escondidos por padrão — o
     * mesmo padrão já usado em T9, alternado via `verificaTipoAcao()`
     * (my_fields.js).
     *
     * @param mixed $oco_id
     * @param mixed $ind
     * @return void
     */
    public function addCampoAcao($oco_id, $ind)
    {
        $dados = $this->ocorrencia->getOcorrencia($oco_id);

        $entity = new EntOcoTratativa($dados);
        $fields = $entity->defCamposAcao(null, (int) $ind);
        // $html = debug($fields);

        $html = "<tr><td><div class='row col-12'>";
        $html .= "<div class='col-11'>";
        $html .= "<div class='col-4 float-start'>";
        $html .= $fields['tpa_id'];
        $html .= "</div>";
        $html .= "<div class='col-6 float-start'>";
        $html .= "<div id='divjust[$ind]' class='d-none row col-12'>" . $fields['oco_justi'] . "</div>";
        $html .= "<div id='divmovi[$ind]' class='d-none row col-12'>" . $fields['tmo_id'] . "</div>";
        $html .= "<div id='divtela[$ind]' class='d-none row col-12'>" . $fields['mod_id'] . $fields['tel_id'] . "</div>";
        $html .= "<div id='divstat[$ind]' class='d-none row col-12'>" . $fields['stt_id'] . "</div>";
        $html .= "</div>";
        $html .= $fields['executar'];
        $html .= "</div>";
        $html .= "<div class='col-1'>";
        $html .= $fields['bt_del'];
        $html .= "</div>";
        $html .= '</div></td></tr>';

        return $this->response->setJSON(['html' => $html]);
    }

    /**
     * finalização da tratativa
     * finalizar
     *
     * @param mixed $id
     * @return void
     */
    public function finalizar($id)
    {
        $dados = $this->ocorrencia->getOcorrencia($id);
        // debug($dados, true);

        // Valida se a ocorrência existe
        if (! $dados) {
            throw new \Exception('Ocorrência não encontrada');
        }

        $log             = buscaLogTabela('oco_ocorrencia', [$id]);
        $dados->usu_nome = $log[$id]['usua_alterou'] ?? null;
        // Instancia a entity
        // debug($dados, true);
        $entoco    = new EntOcoOcorrencia((array) $dados, true);
        $fields    = $entoco->campos;
        $contOcorr = new OcoOcorrencia();
        $secao[0]  = 'Dados Gerais';
        // debug($dados, true);
        $campos    = $contOcorr->showCabecalho($dados);

        $etiqueta = fmtEtiquetaCor($dados->stt_cor, $dados->stt_nome, 1);

        // BLOCO TELAS APLICAVEIS
        $entity   = new EntOcoSubtOcorrencia((array) $dados);
        $sutModel = new OcorreSubtOcorrenciaModel();

        // BLOCO DAS AÇÕES — fonte passa a ser oco_ocorrencia_acao (semeada
        // na criação/processAfterSave), não mais o catálogo
        // oco_subt_ocorrencia_acao. Reflete TODAS as linhas da ocorrência
        // (pendentes e já executadas, de qualquer rodada — automática ou
        // manual). getAcoesComNome() já traz tpa_nome via join com
        // oco_tipo_acao (sem isso a coluna "Ação" renderiza em branco).
        // PASSO 0 — Seed idempotente: cobre a primeira abertura manual de
        // uma ocorrência sem linhas ainda (ex.: criada antes desta feature
        // existir), igual ao que store() já faz na gravação.
        $this->seedAcoes((int) $dados->oco_id, (int) $dados->sut_id);

        $entity = new EntOcoTratativa($dados, true);
        $acoes  = $this->ocorrenciaAcao->getAcoesComNome($id);
        $acoesResultado = [];

        foreach ($acoes as $pos => $acao) {
            $acao                  = (object) $acao;
            $acao->somente_leitura = ($acao->oac_executada === 'S');
            $camposAcao            = $entity->defCamposAcao($acao, $pos);
            $acoesResultado[]      = $camposAcao;
        }

        $secao[1]  = 'Ações';
        // RN03.15 — permite adicionar ação extra apenas na tratativa (edição)
        $campos[1][] = view(
            'partials/pw_acoes_ocorrencia',
            [
                'acoes'            => $acoesResultado,
                'oco_id'           => $id,
                'permiteAcaoExtra' => true,
            ]
        );
        // CONFIG VIEW
        $this->data['desc_edicao'] = ' Ocorrência. Nº ' . str_pad($id, 6, '0', STR_PAD_LEFT) . ' - ' . $etiqueta;

        $this->data['secoes']      = $secao;
        $this->data['campos']      = $campos;
        $this->data['destino']     = "store";
        $this->data['desc_metodo'] = '';
        $this->data['script']      = '<script>jQuery("#form1").attr("data-alter", true);</script>';
        // RN03.18.2 — carrega o script com confirmaAcaoTratativa() (MSG 6)
        $this->data['scripts']     = 'my_ocorrencia';
        // debug($this->data, true);

        echo view('vw_edicao', $this->data);
    }

    /**
     * Gravação
     * store
     *
     * @param mixed $id
     * @return void
     */
    public function store(?array $data = null)
    {
        $automatica = false;
        // Se veio via chamada direta
        if ($data !== null) {
            $automatica = true;
            $postado    = $data;
        } else {
            // Se veio via requisição HTTP
            $postado = $this->request->getPost();
        }

        // PASSO 0 — Seed idempotente: se a ocorrência ainda não tem nenhuma
        // linha em oco_ocorrencia_acao, copia o catálogo de ações do
        // subtipo (oco_subt_ocorrencia_acao) como pendentes. Cobre tanto a
        // primeira chamada automática (criação) quanto uma eventual
        // primeira abertura manual de uma ocorrência antiga sem linhas.
        $this->seedAcoes((int) $postado['oco_id'], (int) $postado['sut_id']);

        // PASSO 1 — Seleciona as ações a processar nesta rodada.
        if ($automatica) {
            $acoesExecutar = $this->montaAcoesAutomaticas((int) $postado['oco_id'], $postado);
        } else {
            $acoesExecutar = $this->montaAcoesManuais($postado);
        }

        // debug($postado, true);
        // debug($acoesExecutar, true);

        // PASSO 2 — Execução (mesmo switch por tpa_tipo já existente) e
        // persistência POR AÇÃO (B3), imediatamente após cada ação ser
        // processada, FORA de qualquer transação agregada: cada ação grava
        // seu próprio resultado (sucesso ou falha) na hora, e não fica
        // pendente de rollback por causa de outra ação do mesmo lote. Isso
        // garante idempotência em retry — uma ação com sucesso real (ex.:
        // gerarMovimentacao() já gerou movimentação de estoque) fica marcada
        // oac_executada='S' e não é reprocessada; só as que falharam (que
        // continuam 'N') voltam a ser tentadas.
        $erroExecucao = null;
        foreach ($acoesExecutar as $valor) {
            $valor->erro = false;
            $valor->msg  = null;

            switch ((int) $valor->tpa_tipo) {
                case 1:
                    // RN03.19 — "Justificar" apenas resolve o texto (ver
                    // resolveJustificativa()); a gravação em
                    // oco_ocorrencia.oco_justi ocorre junto com o update
                    // final da ocorrência, igual ao stt_id em case 4.
                    break;
                case 2:
                    // lógica para Abrir Tela
                    break;
                case 3:
                    // lógica para Gerar Movimentação
                    $retAcao     = $this->gerarMovimentacao($postado, $valor);
                    $valor->erro = $retAcao['erro'] ?? false;
                    $valor->msg  = $retAcao['msg'] ?? null;
                    break;
                case 4:
                    // RN03.18 — "Alterar Status" altera o status de OUTRO
                    // registro (não o da própria ocorrência), a depender da
                    // TELA à qual o stt_id escolhido pertence (Produtos ->
                    // pro_sap_produto, Lotes -> pro_sap_lote). Resolvido em
                    // resolveAlteracoesStatus() e gravado após o loop.
                    break;
                case 5:
                    // RN02.3 de T42 — "Notificação do Fornecedor": cria
                    // automaticamente um registro Pendente em
                    // oco_notif_desvio (Fornecedores > Desvio de Qualidade).
                    // Ver docs/desenvolvimento/fornecedores-t42-t43-dev.md,
                    // decisão 3.2.
                    $retAcao     = $this->gerarNotificacaoDesvio($postado);
                    $valor->erro = $retAcao['erro'] ?? false;
                    $valor->msg  = $retAcao['msg'] ?? null;
                    break;
                case 6:
                    // "Cancelar Atendimento" — exclui o registro de
                    // atendimento do produto nesta requisição e recalcula o
                    // status da requisição (Pendente/Atendido Parcial). Ver
                    // cancelarAtendimentoRequisicao().
                    $retAcao     = $this->cancelarAtendimentoRequisicao($postado);
                    $valor->erro = $retAcao['erro'] ?? false;
                    $valor->msg  = $retAcao['msg'] ?? null;
                    break;
                case 7:
                    // "Cancelar Conferência" — limpa rpa_conferida/
                    // rpa_data_conferencia do atendimento deste produto
                    // nesta requisição (e rpa_aprovada/rpa_data_inspecao, se
                    // já preenchidos — não há inspeção válida sem
                    // conferência) e recalcula o status da requisição. Ver
                    // cancelarConferenciaRequisicao().
                    $retAcao     = $this->cancelarConferenciaRequisicao($postado);
                    $valor->erro = $retAcao['erro'] ?? false;
                    $valor->msg  = $retAcao['msg'] ?? null;
                    break;
                case 8:
                    // "Cancelar Inspeção" — limpa rpa_aprovada/
                    // rpa_data_inspecao do atendimento deste produto nesta
                    // requisição (rpa_conferida não é tocado) e recalcula o
                    // status da requisição. Ver
                    // cancelarInspecaoRequisicao().
                    $retAcao     = $this->cancelarInspecaoRequisicao($postado);
                    $valor->erro = $retAcao['erro'] ?? false;
                    $valor->msg  = $retAcao['msg'] ?? null;
                    break;
                default:
                    // opcional: tratar valores inesperados
                    break;
            }

            // Persistência por ação (B3) — UPDATE se a linha já existia
            // (seed ou rodada anterior), INSERT se for ad-hoc (adicionada
            // via botão "+", sem oac_id). Sucesso: marca oac_executada='S'
            // (não reprocessa em retry futuro). Falha: mantém
            // oac_executada='N' (continua elegível para nova tentativa).
            $dadosLinha = [
                'oco_id'    => $postado['oco_id'],
                'tpa_id'    => $valor->tpa_id,
                'tpa_tipo'  => $valor->tpa_tipo,
                'tmo_id'    => $valor->tmo_id,
                'stt_id'    => $valor->stt_id,
                'tel_id'    => $valor->tel_id,
                'oco_justi' => $valor->oco_justi,
                'oac_erro'  => (int) $valor->erro,
                'oac_msg'   => $valor->msg,
            ];

            if ($valor->erro) {
                $dadosLinha['oac_executada'] = 'N';
            } else {
                $dadosLinha['oac_executada']    = 'S';
                $dadosLinha['oac_executado_em'] = date('Y-m-d H:i:s');
                $dadosLinha['usu_executou']     = $automatica ? null : session()->get('usu_id');
                $dadosLinha['oac_automatica']   = $automatica ? 1 : 0;
            }

            if (!empty($valor->oac_id)) {
                $this->ocorrenciaAcao->update($valor->oac_id, $dadosLinha);
            } else {
                // Ação ad-hoc (botão "+") — nunca é automática, não
                // faz parte do catálogo do subtipo.
                $dadosLinha['oac_auto']   = 'N';
                $dadosLinha['oac_criado'] = date('Y-m-d H:i:s');
                $this->ocorrenciaAcao->insert($dadosLinha);
            }

            if ($valor->erro && $erroExecucao === null) {
                $erroExecucao = $valor->msg;
            }
        }

        $retTrat = [];
        if ($erroExecucao === null) {
            // B4 — grupo dbOcorrencia (onde estão oco_ocorrencia/
            // pro_sap_produto), não mais o grupo default. Cobre só o
            // resumo/status final (Passos 4/5) — a persistência por ação do
            // Passo 2 já foi commitada individualmente acima.
            $db = \Config\Database::connect('dbOcorrencia');
            $db->transBegin();
            try {
                // PASSO 4 — Resumo (compatibilidade): primeiro valor não
                // vazio, entre as ações desta rodada, grava em
                // oco_ocorrencia.oco_justi / no registro (Produto, Lote...)
                // da tela a que o status "Alterar Status" pertence.
                $justificativa    = $this->resolveJustificativa($acoesExecutar);
                $alteracoesStatus = $this->resolveAlteracoesStatus($acoesExecutar, $postado);

                // PASSO 5 — Status final da ocorrência, conforme execução
                // real (não mais previsão fixa 29/30).
                $sttIdFinal = $this->resolveStatusOcorrencia((int) $postado['oco_id'], $automatica);

                $sql_save = [
                    'stt_id'       => $sttIdFinal,
                    'usu_fina'     => session()->get('usu_id'),
                    'oco_data_fim' => date('Y-m-d H:i:s'),
                ];
                if ($justificativa !== null) {
                    $sql_save['oco_justi'] = $justificativa;
                }

                $this->ocorrencia->update($postado['oco_id'], $sql_save);

                foreach ($alteracoesStatus as $alteracao) {
                    (new $alteracao['model']())->update($alteracao['id'], ['stt_id' => $alteracao['stt_id']]);
                }

                $db->transCommit();
                $retTrat['erro'] = false;
                $retTrat['msg']  = 'Ocorrência tratada com sucesso!';
                session()->setFlashdata('msg', $retTrat['msg']);
                $retTrat['url'] = site_url($this->data['controler']);
            } catch (\Exception $e) {
                $db->transRollback();
                $retTrat['erro'] = true;
                $retTrat['msg']  = $e->getMessage();
                // Sem isto, uma falha aqui no fluxo automático
                // (processAfterSave) fica muda: o retorno é descartado pelo
                // chamador e não sobra rastro nenhum para diagnosticar.
                log_message('error', 'OcoTrataOcorrencia::store() falhou ao resolver status final (oco_id=' . ($postado['oco_id'] ?? '?') . '): ' . $e);
            }
        } else {
            $retTrat['erro'] = true;
            $retTrat['msg']  = $erroExecucao;
        }

        // Chamado via HTTP (tela de tratativa) precisa de uma Response de
        // verdade — um array puro retornado aqui é descartado pelo
        // CodeIgniter (CodeIgniter::gatherOutput() só usa o retorno quando é
        // string ou ResponseInterface), resultando em corpo de resposta
        // vazio. Chamada automática (OcorrenciaService::processAfterSave())
        // instancia o controller manualmente (sem passar por
        // initController()), então $this->response não existe nesse
        // contexto — ignora o retorno, só devolve o array.
        if ($automatica) {
            return $retTrat;
        }

        return $this->response->setJSON($retTrat);
    }

    /**
     * PASSO 0 do motor de execução — semeia oco_ocorrencia_acao com o
     * catálogo de ações do subtipo (oco_subt_ocorrencia_acao), como
     * pendentes, se a ocorrência ainda não tiver nenhuma linha. Idempotente
     * (checa existência antes de inserir). Se o catálogo vier vazio, não
     * insere nada (sem erro) — mesmo comportamento hoje coberto por
     * getStatusInicial() para subtipo sem ações.
     *
     * B4 — checagem + insertBatch() rodam dentro de uma transação própria
     * (grupo dbOcorrencia), fechando a janela de corrida entre duas
     * chamadas concorrentes de store() para o mesmo oco_id (que antes podia
     * duplicar o catálogo semeado).
     */
    private function seedAcoes(int $oco_id, int $sut_id): void
    {
        $db = \Config\Database::connect('dbOcorrencia');
        $db->transBegin();

        try {
            $jaExiste = $this->ocorrenciaAcao->where('oco_id', $oco_id)->countAllResults();
            if ($jaExiste > 0) {
                $db->transCommit();
                return;
            }

            $catalogo = $this->subtocorrencia->getTOAcao($sut_id);
            if (empty($catalogo)) {
                $db->transCommit();
                return;
            }

            $tipos = [];
            foreach ($this->tipoacao->getTipoAcao(array_column($catalogo, 'tpa_id')) as $tp) {
                $tipos[$tp->tpa_id] = $tp->tpa_tipo;
            }

            $agora  = date('Y-m-d H:i:s');
            $linhas = [];
            foreach ($catalogo as $acao) {
                $linhas[] = [
                    'oco_id'        => $oco_id,
                    'tpa_id'        => $acao->tpa_id,
                    'tpa_tipo'      => $tipos[$acao->tpa_id] ?? 0,
                    'oac_auto'      => $acao->sta_fina ?? 'N',
                    'tmo_id'        => $acao->tmo_id ?? null,
                    'stt_id'        => $acao->stt_id ?? null,
                    'tel_id'        => $acao->tel_id ?? null,
                    'oco_justi'     => null,
                    'oac_executada' => 'N',
                    'oac_criado'    => $agora,
                ];
            }

            $this->ocorrenciaAcao->insertBatch($linhas);
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    /**
     * PASSO 1 (execução automática) — todas as linhas de
     * oco_ocorrencia_acao com oac_auto='S' e ainda não executadas.
     */
    private function montaAcoesAutomaticas(int $oco_id, array $postado): array
    {
        $linhas = $this->ocorrenciaAcao
            ->where('oco_id', $oco_id)
            ->where('oac_auto', 'S')
            ->where('oac_executada', 'N')
            ->findAll();

        $acoesExecutar = [];
        foreach ($linhas as $linha) {
            $acoesExecutar[] = (object) [
                'oac_id'    => $linha['oac_id'],
                'tpa_id'    => $linha['tpa_id'],
                'tpa_tipo'  => $linha['tpa_tipo'],
                'tmo_id'    => $linha['tmo_id'],
                'stt_id'    => $linha['stt_id'],
                'tel_id'    => $linha['tel_id'],
                'oco_justi' => $linha['oco_justi'] ?? ($postado['oco_justi'] ?? null),
            ];
        }

        return $acoesExecutar;
    }

    /**
     * PASSO 1 (tratativa manual) — monta as ações desta rodada a partir do
     * POST: uma linha só entra na rodada se `executar[$pos]` veio marcado.
     * Para linhas com `oac_id` (pendentes vindas do seed/rodada anterior),
     * os dados AUTORITATIVOS de tpa_id/tpa_tipo vêm do próprio banco (não
     * confia no POST para isso — só usa o POST para os campos realmente
     * editáveis: oco_justi/tmo_id/stt_id/tel_id). Para linhas sem oac_id
     * (ad-hoc, adicionadas via botão "+"), tpa_id vem do select livre do
     * POST e o tpa_tipo é resolvido via catálogo (oco_tipo_acao).
     */
    private function montaAcoesManuais(array $postado): array
    {
        $executar = $postado['executar']  ?? [];
        $oacIds   = $postado['oac_id']    ?? [];
        $tpaIds   = $postado['tpa_id']    ?? [];
        $justis   = $postado['oco_justi'] ?? [];
        $tmoIds   = $postado['tmo_id']    ?? [];
        $sttIds   = $postado['stt_id']    ?? [];
        $telIds   = $postado['tel_id']    ?? [];

        $acoesExecutar = [];
        $tpaIdsUsados  = [];

        foreach ($executar as $pos => $marcado) {
            // Campo agora é cr2opcoes (Sim/Não) — sempre vem preenchido no
            // POST (diferente do checkbox antigo, que só chegava quando
            // marcado); só entra na rodada quem valer 'S'.
            if ($marcado !== 'S') {
                continue;
            }

            $oacId = $oacIds[$pos] ?? null;

            if (!empty($oacId)) {
                $linha = $this->ocorrenciaAcao->find($oacId);
                if (!$linha || $linha['oac_executada'] === 'S') {
                    continue; // linha inexistente ou já executada — ignora
                }
                $tpaId   = $linha['tpa_id'];
                $tpaTipo = $linha['tpa_tipo'];
            } else {
                $tpaId = $tpaIds[$pos] ?? null;
                if (empty($tpaId)) {
                    continue;
                }
                $tipoInfo = $this->tipoacao->getTipoAcao((int) $tpaId);
                $tpaTipo  = $tipoInfo[0]->tpa_tipo ?? null;
                if ($tpaTipo === null) {
                    continue;
                }
            }

            // Defesa contra duplicidade de tpa_id na mesma rodada — com o
            // seed, duplicar tpa_id não deveria mais ser estruturalmente
            // possível, mas mantém a checagem por ser barata.
            if (isset($tpaIdsUsados[$tpaId])) {
                continue;
            }
            $tpaIdsUsados[$tpaId] = true;

            $acoesExecutar[] = (object) [
                'oac_id'    => $oacId ?: null,
                'tpa_id'    => $tpaId,
                'tpa_tipo'  => $tpaTipo,
                'tmo_id'    => $tmoIds[$pos] ?? null,
                'stt_id'    => $sttIds[$pos] ?? null,
                'tel_id'    => $telIds[$pos] ?? null,
                'oco_justi' => $justis[$pos] ?? null,
            ];
        }

        return $acoesExecutar;
    }

    /**
     * PASSO 5 — resolve o stt_id final da ocorrência conforme a execução
     * real das ações (não mais uma previsão fixa 29/30):
     *  - sem ações (ou nenhuma pendente sobrando) -> Finalizada (29 auto /
     *    30 manual);
     *  - nenhuma ação executada ainda -> Pendente (28);
     *  - mistura (ao menos 1 executada, ao menos 1 pendente) ->
     *    Parcialmente Tratada.
     */
    private function resolveStatusOcorrencia(int $oco_id, bool $automatica): int
    {
        $total      = $this->ocorrenciaAcao->where('oco_id', $oco_id)->countAllResults();
        $executadas = $this->ocorrenciaAcao->where('oco_id', $oco_id)->where('oac_executada', 'S')->countAllResults();
        $pendentes  = $total - $executadas;

        if ($total === 0 || $pendentes === 0) {
            return $automatica ? 29 : 30;
        }
        if ($executadas === 0) {
            return 28;
        }

        return $this->getStatusParcialId();
    }

    /**
     * Resolve dinamicamente o stt_id do status "Parcialmente Tratada"
     * (inserido pela migration 2026-08-10-000001_OcoOcorrenciaAcao), sem
     * hardcode de id numérico. Cacheado em memória por request.
     */
    private function getStatusParcialId(): int
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $status = \Config\Database::connect('default')
            ->table('cfg_status')
            ->where('stt_nome', 'Parcialmente Tratada')
            ->get()->getRow();

        // Fallback defensivo (nunca deveria faltar após a migration rodar)
        // — cai para Pendente (28) em vez de quebrar a tratativa.
        $cache = $status ? (int) $status->stt_id : 28;

        return $cache;
    }

    /**
     * RN03.18 — Mapa das telas cujo status pode ser alterado por uma ação
     * "Alterar Status" (tpa_tipo=4) da tratativa. Chave = `tel_controler`
     * (mesmo valor usado no roteamento — ver
     * `ConfigStatusModel::getTelaControlerDoStatus()`); valor = model a
     * atualizar e campo do POST da tratativa que traz o id do registro
     * daquela tela. Extensível: uma tela nova só precisa de uma entrada
     * aqui, sem tocar no restante do fluxo.
     */
    private const TELAS_STATUS_ALTERAVEL = [
        'Produto' => ['model' => ProdutProdutoModel::class, 'campoId' => 'pro_id'],
        'Lote'    => ['model' => ProdutLoteModel::class,    'campoId' => 'lot_id'],
    ];

    /**
     * RN03.18 — Resolve, para cada ação "Alterar Status" (tpa_tipo=4)
     * processada nesta rodada, QUAL registro deve ter o status alterado: o
     * destino não é fixo (não é sempre o Produto) — depende da TELA à qual o
     * stt_id escolhido na ação pertence (cfg_status.tel_id ->
     * cfg_tela.tel_controler, resolvido via
     * ConfigStatusModel::getTelaControlerDoStatus()). Um stt_id de uma
     * status de tela "Produto" altera pro_sap_produto; um de tela "Lote"
     * altera pro_sap_lote; ver self::TELAS_STATUS_ALTERAVEL.
     *
     * stt_id já vem resolvido na própria ação (catálogo, seed, ou editado
     * pelo usuário — ver montaAcoesManuais()/montaAcoesAutomaticas()).
     * Primeiro valor encontrado por tela "vence" (mesmo critério de
     * resolveJustificativa()); status de uma tela sem entrada em
     * TELAS_STATUS_ALTERAVEL, ou sem o id do registro correspondente no
     * POST, é ignorado (não altera nada).
     *
     * @return array<string, array{model: string, id: mixed, stt_id: int}>
     */
    private function resolveAlteracoesStatus(array $acoesExecutar, array $postado): array
    {
        $statusModel = new ConfigStatusModel();
        $alteracoes  = [];

        foreach ($acoesExecutar as $acao) {
            if ((int) $acao->tpa_tipo !== 4 || empty($acao->stt_id)) {
                continue;
            }

            $telController = $statusModel->getTelaControlerDoStatus((int) $acao->stt_id);
            $destino       = self::TELAS_STATUS_ALTERAVEL[$telController] ?? null;
            if ($destino === null || isset($alteracoes[$telController])) {
                continue;
            }

            $registroId = $postado[$destino['campoId']] ?? null;
            if (empty($registroId)) {
                continue;
            }

            $alteracoes[$telController] = [
                'model'  => $destino['model'],
                'id'     => $registroId,
                'stt_id' => (int) $acao->stt_id,
            ];
        }

        return $alteracoes;
    }

    /**
     * RN03.19 — Resolve o texto de justificativa a gravar em
     * oco_ocorrencia.oco_justi: busca, entre as ações desta rodada, alguma
     * do tipo "Justificar" (tpa_tipo=1). Retorna null se nenhuma ação
     * "Justificar" foi processada nesta rodada ou o texto veio vazio.
     */
    private function resolveJustificativa(array $acoesExecutar): ?string
    {
        foreach ($acoesExecutar as $acao) {
            if ((int) $acao->tpa_tipo !== 1) {
                continue;
            }

            if (!empty($acao->oco_justi)) {
                return $acao->oco_justi;
            }
        }

        return null;
    }

    /**
     * Gera a movimentação de estoque para uma ação do tipo "Gerar
     * Movimentação" (tpa_tipo=3). O tmo_id já vem resolvido na própria
     * ação — do catálogo do subtipo (seed/execução automática) ou editado
     * pelo usuário na tratativa manual (montaAcoesManuais()).
     */
    private function gerarMovimentacao($postado, $acao)
    {
        $retMov         = [];
        $retMov['erro'] = false;

        // tmo_id já vem resolvido na própria ação (catálogo, no seed, ou
        // editado pelo usuário na tratativa — ver
        // montaAcoesManuais()/montaAcoesAutomaticas()).
        $tmoId = $acao->tmo_id ?? null;

        if ($tmoId) {
            $movsOco[] = [
                'id'           => $tmoId,
                'qt'           => $postado['oco_qtd'],
                'msg'          => $postado['oco_descricao'],
                'pro_id'       => $postado['pro_id'],
                'rep_id'       => null,
                'reserva'      => null,
                'lot_lote'     => $postado['lot_lote'],
                'lot_validade' => $postado['lot_validade'],
            ];
            $movim = geraMovimentoRequisicoes($movsOco, $this->data['controler']);
            if ($movim['status'] == 'Erro') {
                $retMov['erro'] = true;
                $retMov['msg']  = $movim['mensagem'];
            }
        }
        return $retMov;
    }

    /**
     * RN02.3 (T42) — ação "Notificação do Fornecedor" (tpa_tipo = 5): cria
     * automaticamente um registro Pendente em oco_notif_desvio para o
     * oco_id da ocorrência sendo tratada/finalizada.
     *
     * Idempotente: não duplica se já existir um oco_notif_desvio para o
     * mesmo oco_id (ex.: reprocessamento/reenvio do mesmo submit).
     */
    private function gerarNotificacaoDesvio($postado)
    {
        $ret = ['erro' => false];

        $modelNotif = new FornecNotifDesvioModel();

        $jaExiste = \Config\Database::connect('dbOcorrencia')
            ->table('oco_notif_desvio')
            ->where('oco_id', $postado['oco_id'])
            ->countAllResults();

        if ($jaExiste > 0) {
            return $ret; // já notificado — não duplica
        }

        $sttPendente = $modelNotif->getStatusId('Pendente');
        if (!$sttPendente) {
            $ret['erro'] = true;
            $ret['msg']  = 'Status "Pendente" de Desvio de Qualidade não configurado (cfg_status)';
            return $ret;
        }

        // Insert via Model (não CommonModel::insertReg()) — preserva os
        // hooks de auditoria (afterInsert => depoisInsert, log gravado com
        // a PK real ndv_id) e os timestamps (ndv_criado). skipValidation()
        // porque ndv_local/ndv_descreva legitimamente ainda não existem
        // neste momento — só serão preenchidos depois pelo usuário em T42
        // (RN03.7/RN03.14).
        $modelNotif->skipValidation(true)->insert([
            'oco_id'    => $postado['oco_id'],
            'stt_id'    => $sttPendente,
            'usu_criou' => session()->get('usu_id'),
        ]);

        return $ret;
    }

    /**
     * tpa_tipo=6 — "Cancelar Atendimento": ao tratar uma ocorrência aberta
     * sobre um item já atendido de uma requisição, exclui o registro de
     * atendimento desse item (est_requisicao_produto_atendimento) e
     * recalcula o status da requisição:
     *  - Pendente (stt_id=4) se, após a exclusão, nenhum item da
     *    requisição restar atendido;
     *  - Atendido Parcial (stt_id=21) se ainda houver ao menos um item
     *    atendido.
     * Mesmos stt_id já usados para esses status em
     * AteRequisicao::atender()/Requisicao::store().
     *
     * `rep_id` vem da própria ocorrência (getOcorrencia() — mesma coluna já
     * usada em OcoOcorrencia::edit() para bloquear alteração de ocorrência
     * vinculada a requisição, RN04.1): identifica de forma inequívoca a
     * linha de est_requisicao_produto (e portanto o atendimento) a cancelar,
     * sem precisar cruzar req_id+pro_id+lot_id.
     */
    private function cancelarAtendimentoRequisicao(array $postado): array
    {
        $ret = ['erro' => false];

        $dados = $this->ocorrencia->getOcorrencia($postado['oco_id']);
        if (empty($dados->rep_id) || empty($dados->req_id)) {
            $ret['erro'] = true;
            $ret['msg']  = 'Ocorrência sem vínculo com Requisição — não é possível cancelar o atendimento.';
            return $ret;
        }

        $db = \Config\Database::connect('dbEstoque');
        $db->transBegin();

        try {
            $db->table('est_requisicao_produto_atendimento')
                ->where('rep_id', $dados->rep_id)
                ->delete();

            $totalItens = count((new EstoquRequisicaoProdutoModel())->getRequisicaoProdutos((int) $dados->req_id));
            $pendencias = (new EstoquRequisicaoProdutoAtendimentoModel())->getRequisicaoPendencias((int) $dados->req_id);
            $pendente   = (int) ($pendencias[0]['pendente_atendimento'] ?? $totalItens);

            $novoStatus = ($pendente >= $totalItens) ? 4 : 21;

            (new EstoquRequisicaoModel())->update((int) $dados->req_id, ['stt_id' => $novoStatus]);

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            $ret['erro'] = true;
            $ret['msg']  = $e->getMessage();
        }

        return $ret;
    }

    /**
     * tpa_tipo=7 — "Cancelar Conferência": ao tratar uma ocorrência aberta
     * sobre um item já conferido de uma requisição, limpa
     * rpa_conferida/rpa_data_conferencia do atendimento desse item
     * (est_requisicao_produto_atendimento, identificado por rep_id — mesmo
     * critério de cancelarAtendimentoRequisicao()). Se o item já havia sido
     * inspecionado (rpa_aprovada/rpa_data_inspecao preenchidos), desfaz
     * também — não há inspeção válida sem conferência; o update é
     * incondicional (limpar um campo já nulo é inofensivo), sempre
     * restrito ao rep_id deste produto nesta requisição. Recalcula o
     * status da requisição:
     *  - Atendida, aguardando Conferência (stt_id=18) se, após a
     *    exclusão, nenhum item da requisição restar conferido;
     *  - Conferência Parcial (stt_id=24) se ainda houver ao menos um item
     *    conferido.
     * Mesmos stt_id já usados para esses status em
     * AteRequisicao::atender()/ConfRequisicao::conferir().
     */
    private function cancelarConferenciaRequisicao(array $postado): array
    {
        $ret = ['erro' => false];

        $dados = $this->ocorrencia->getOcorrencia($postado['oco_id']);
        if (empty($dados->rep_id) || empty($dados->req_id)) {
            $ret['erro'] = true;
            $ret['msg']  = 'Ocorrência sem vínculo com Requisição — não é possível cancelar a conferência.';
            return $ret;
        }

        $db = \Config\Database::connect('dbEstoque');
        $db->transBegin();

        try {
            $db->table('est_requisicao_produto_atendimento')
                ->where('rep_id', $dados->rep_id)
                ->update([
                    'rpa_conferida'        => null,
                    'rpa_data_conferencia' => null,
                    'rpa_aprovada'         => null,
                    'rpa_data_inspecao'    => null,
                ]);

            $totalItens = count((new EstoquRequisicaoProdutoModel())->getRequisicaoProdutos((int) $dados->req_id));
            $pendencias = (new EstoquRequisicaoProdutoAtendimentoModel())->getRequisicaoPendencias((int) $dados->req_id);
            $pendente   = (int) ($pendencias[0]['pendente_conferencia'] ?? $totalItens);

            $novoStatus = ($pendente >= $totalItens) ? 18 : 24;

            (new EstoquRequisicaoModel())->update((int) $dados->req_id, ['stt_id' => $novoStatus]);

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            $ret['erro'] = true;
            $ret['msg']  = $e->getMessage();
        }

        return $ret;
    }

    /**
     * tpa_tipo=8 — "Cancelar Inspeção": ao tratar uma ocorrência aberta
     * sobre um item já inspecionado de uma requisição, limpa
     * rpa_aprovada/rpa_data_inspecao do atendimento desse item
     * (est_requisicao_produto_atendimento, identificado por rep_id — mesmo
     * critério de cancelarAtendimentoRequisicao()); rpa_conferida não é
     * tocado — a conferência continua válida, só a inspeção é desfeita. O
     * update é incondicional (limpar um campo já nulo é inofensivo),
     * sempre restrito ao rep_id deste produto nesta requisição. Recalcula
     * o status da requisição:
     *  - Conferida, aguardando Inspeção (stt_id=25) se, após a exclusão,
     *    nenhum item da requisição restar inspecionado;
     *  - Inspeção Parcial (stt_id=26) se ainda houver ao menos um item
     *    inspecionado.
     * Mesmos stt_id já usados para esses status em
     * ConfRequisicao::conferir()/InspecaoProd::inspecionar().
     */
    private function cancelarInspecaoRequisicao(array $postado): array
    {
        $ret = ['erro' => false];

        $dados = $this->ocorrencia->getOcorrencia($postado['oco_id']);
        if (empty($dados->rep_id) || empty($dados->req_id)) {
            $ret['erro'] = true;
            $ret['msg']  = 'Ocorrência sem vínculo com Requisição — não é possível cancelar a inspeção.';
            return $ret;
        }

        $db = \Config\Database::connect('dbEstoque');
        $db->transBegin();

        try {
            $db->table('est_requisicao_produto_atendimento')
                ->where('rep_id', $dados->rep_id)
                ->update([
                    'rpa_aprovada'      => null,
                    'rpa_data_inspecao' => null,
                ]);

            $totalItens = count((new EstoquRequisicaoProdutoModel())->getRequisicaoProdutos((int) $dados->req_id));
            $pendencias = (new EstoquRequisicaoProdutoAtendimentoModel())->getRequisicaoPendencias((int) $dados->req_id);
            $pendente   = (int) ($pendencias[0]['pendente_inspecao'] ?? $totalItens);

            $novoStatus = ($pendente >= $totalItens) ? 25 : 26;

            (new EstoquRequisicaoModel())->update((int) $dados->req_id, ['stt_id' => $novoStatus]);

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            $ret['erro'] = true;
            $ret['msg']  = $e->getMessage();
        }

        return $ret;
    }
}
