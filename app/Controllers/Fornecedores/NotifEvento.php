<?php

namespace App\Controllers\Fornecedores;

use App\Controllers\BaseController;
use App\Entities\Fornecedores\EntOcoNotifEvento;
use App\Entities\Fornecedores\EntOcoNotifEventoAcao;
use App\Entities\Fornecedores\EntOcoNotifEventoAnexo;
use App\Entities\Fornecedores\EntOcoNotifEventoProduto;
use App\Libraries\MyCampo;
use App\Models\CommonModel;
use App\Models\Fornec\FornecNotifDesvioModel;
use App\Models\Fornec\FornecNotifEventoModel;
use App\Models\Ocorre\OcorreTipoAcaoModel;

/**
 * T43 — Fornecedores > Notificação de Evento (oco_notif_evento).
 *
 * Fluxo (ver docs/desenvolvimento/fornecedores-t42-t43-dev.md, seção 5.2):
 *  1. add()               — tela intermediária de seleção de produtos (RN03.1).
 *  2. selecionaProdutos() — recebe os ndv_id marcados, abre o cadastro (4 abas).
 *  3. store()              — grava cabeçalho + produtos + anexos + ações, já
 *                             concluindo na mesma transação (mesma decisão de
 *                             T42), e executa as ações cadastradas em ordem
 *                             (RN03.22).
 *
 * NÃO existe add() no sentido "formulário vazio direto" — o botão "+" da
 * listagem (tel_texto_botao, cfg_tela) aponta para este add(), que já é a
 * seleção de produtos.
 */
class NotifEvento extends BaseController
{
    /**
     * RN03.16/RN03.20 — whitelist única de anexos, usada tanto no client
     * (MyCampo::setTipoArq(), ver EntOcoNotifEventoAnexo::defCampos())
     * quanto na validação server-side de salvaAnexos() — mesma lista, sem
     * duplicar em dois lugares.
     */
    public const ANEXO_EXTENSOES_PERMITIDAS = ['pdf', 'png', 'jpeg', 'jpg'];
    public const ANEXO_MIMES_PERMITIDOS = [
        'application/pdf',
        'image/png',
        'image/jpeg',
    ];

    public $data = [];
    public $permissao = '';
    public $model;
    public $modelDesvio;

    public function __construct()
    {
        $this->data       = session()->get('dados_tela') ?? session()->getFlashdata('dados_tela') ?? [];
        $this->permissao  = $this->data['permissao'] ?? '';
        $this->model       = new FornecNotifEventoModel();
        $this->modelDesvio = new FornecNotifDesvioModel();

        if (($this->data['erromsg'] ?? '') != '') {
            $this->__erro();
        }
    }

    public function __erro()
    {
        echo view('vw_semacesso', $this->data);
    }

    /**
     * index
     */
    public function index()
    {
        $this->data['colunas']   = montaColunasLista($this->data, 'nev_id');
        $this->data['url_lista'] = base_url($this->data['controler'] . '/lista');
        $this->data['scripts']   = 'my_fornecedores';
        echo view('vw_lista', $this->data);
    }

    /**
     * lista
     */
    public function lista()
    {
        $campos = montaColunasCampos($this->data, 'nev_id');
        $dados  = $this->model->getListaCompleta();

        $this->data['allconsulta'] = true;

        // "Usuário" aqui é quem registrou a notificação (usu_criou de
        // oco_notif_evento) — diferente de T42, onde vem de T11; em T43 uma
        // notificação pode agregar produtos de mais de um oco_id (RN03.1),
        // então não há um único "usuário de origem" para resolver.
        $nevIds   = array_map(fn($n) => $n->nev_id, $dados);
        $logCriou = buscaLogTabelaFirst('oco_notif_evento', $nevIds);

        foreach ($dados as $nov) {
            $nov->usu_nome    = $logCriou[$nov->nev_id]['usua_alterou'] ?? '';
            $nov->acao_person = [];

            // Decisão do byarq (2026-07-12): Produto/Lote mostram
            // "primeiro item + e mais N" com a lista completa em
            // <ttp>...</ttp> (tooltip nativo de montaListaDados(), já
            // documentado em rascunho-runtime-js.md) quando a notificação
            // tem mais de 1 produto/lote — nada de mostrar tudo separado
            // por vírgula na célula. Fabricante já vem pronto da view
            // (MIN, sem agregação — RN03.1 garante 1 único fabricante).
            $nov->nev_produtos = $this->resumeListaComTooltip($nov->nev_produtos_raw ?? '');
            $nov->nev_lotes    = $this->resumeListaComTooltip($nov->nev_lotes_raw ?? '');

            // RN04.1 — botão Imprimir na listagem (etiqueta), gatilho
            // independente/não bloqueante — mesmo mecanismo de T42.
            $urlEtiqueta        = base_url($this->data['controler'] . '/GeraEtiqueta/' . $nov->nev_id . '/1');
            $nov->acao_person[] = "
                <button class='btn btn-outline-dark btn-sm border-0 mx-0 fs-0'
                    title='Imprimir'
                    onclick='geraEiquetaGenerico(this, \"{$urlEtiqueta}\")'>
                    <i class='fa-solid fa-print'></i>
                </button>
            ";
        }

        return $this->response->setJSON([
            'data' => montaListaColunasEnt($this->data, 'nev_id', $dados, $campos[1]),
        ]);
    }

    /**
     * Resume uma lista agregada (GROUP_CONCAT com separador \x1F, vindo de
     * nev_produtos_raw/nev_lotes_raw da view) como "primeiro item" quando só
     * há 1, ou "primeiro item e mais N" com a lista completa em
     * <ttp>...</ttp> quando há mais de 1 — mecanismo de tooltip já nativo de
     * montaListaDados() (ver rascunho-runtime-js.md), sem componente novo.
     */
    private function resumeListaComTooltip(string $listaRaw): string
    {
        if ($listaRaw === '') {
            return '';
        }

        $itens = explode("\x1F", $listaRaw);

        if (count($itens) <= 1) {
            return esc($itens[0] ?? '');
        }

        $resto = count($itens) - 1;

        // Bloqueante (byarq, revisão 01) — o primeiro item também precisa
        // passar por esc(): montaListaDados() renderiza a célula como HTML
        // bruto, e pro_despro/lot_lote vêm de cadastro (superfície real de
        // stored XSS se não escapado, igual ao que já era feito no tooltip).
        return esc($itens[0]) . ' e mais ' . $resto . '<ttp>' . esc(implode(', ', $itens)) . '</ttp>';
    }

    /**
     * add — RN03.1: tela intermediária de seleção de produtos. Grid com os
     * desvios (T42) Concluídos e ainda não vinculados a nenhuma notificação
     * ("disponível para notificação"); pré-seleciona os do mesmo fabricante
     * do primeiro item listado (aproximação operacional de "mesmo
     * fabricante" — o usuário pode ajustar a seleção antes de confirmar).
     */
    public function add()
    {
        $disponiveis = $this->modelDesvio->getDisponiveisParaNotificacao();

        if (empty($disponiveis)) {
            // Nada disponível para notificar — mantém padrão de mensagem
            // central (MSG), sem string solta: front decide o texto via
            // boxAlert(); aqui só sinaliza a condição.
            $this->data['semDisponiveis'] = true;
        }

        $fabricantePreSelecao = $disponiveis[0]->fab_apeFab ?? null;

        // "Usuário" (coluna da grid de seleção) — mesma resolução via log
        // de oco_ocorrencia usada em lista()/show() (view não expõe usu_nome).
        $ocoIds   = array_map(fn($d) => $d->oco_id, $disponiveis);
        $logCriou = buscaLogTabelaFirst('oco_ocorrencia', $ocoIds);
        foreach ($disponiveis as $d) {
            $d->usu_nome = $logCriou[$d->oco_id]['usua_alterou'] ?? '';
        }

        // Botão de título "Selecionar Produtos" — confirma os ndv_id[]
        // marcados na grid e envia por POST direto (fora do fluxo AJAX de
        // bt_salvar/submeteForm, que espera JSON) para selecionaProdutos(),
        // que responde com a página completa do cadastro de 4 abas — ver
        // confirmaSelecaoProdutos() em my_fornecedores.js.
        $btSelProdutos = new MyCampo();
        $btSelProdutos->nome     = 'bt_sel_produtos';
        $btSelProdutos->id       = 'bt_sel_produtos';
        $btSelProdutos->i_cone   = '<div class="align-items-center py-1 text-start float-start font-weight-bold" style="">
                            <i class="fa-solid fa-check" style="font-size: 2rem;" aria-hidden="true"></i></div>';
        $btSelProdutos->i_cone  .= '<div class="align-items-start txt-bt-manut">Selecionar Produtos</div>';
        $btSelProdutos->place    = 'Selecionar Produtos';
        $btSelProdutos->funcChan = 'confirmaSelecaoProdutos()';
        $btSelProdutos->classep  = 'btn-primary bt-manut btn-sm mb-2 float-end';

        $this->data['botao']       = $btSelProdutos->crBotao();
        $this->data['title']       = 'Selecione os Produtos';
        $this->data['desc_metodo'] = 'Nova Notificação de Evento';
        $this->data['secoes']      = ['Seleção de Produtos'];
        $this->data['campos']      = [[
            view('partials/pw_selecao_produtos_notif', [
                'disponiveis'  => $disponiveis,
                'fabricante'   => $fabricantePreSelecao,
            ]),
        ]];
        $this->data['destino'] = '';
        $this->data['scripts'] = 'my_fornecedores';

        echo view('vw_edicao', $this->data);
    }

    /**
     * selecionaProdutos — RN03.1 (confirmação): recebe ndv_id[] marcados e
     * monta o cadastro de 4 abas (Dados Gerais / Providências / Parecer
     * Final / Ações).
     */
    public function selecionaProdutos()
    {
        $ndvIds = array_filter((array) ($this->request->getPost('ndv_id') ?? []));
        if (empty($ndvIds)) {
            return $this->response->setJSON([
                'erro' => true,
                'msg'  => 'Selecione ao menos um produto para notificar',
            ]);
        }

        // RN03.1 (byarq, revisão 02) — 1ª checagem de elegibilidade (não
        // transacional, só evita abrir o cadastro de 4 abas com um produto
        // que já deixou de estar disponível). A checagem que realmente
        // protege contra corrida é a de store() (dentro da transação) +
        // UNIQUE KEY no banco.
        $indisponiveis = $this->modelDesvio->validaDisponibilidade($ndvIds);
        if (!empty($indisponiveis)) {
            return $this->response->setJSON([
                'erro' => true,
                'msg'  => 'Produto(s) não estão mais disponíveis para notificação (nº '
                    . implode(', ', $indisponiveis) . ') — atualize a lista e selecione novamente.',
            ]);
        }

        $entEvento = new EntOcoNotifEvento();
        $secao     = ['Dados Gerais', 'Providências', 'Parecer Final', 'Ações'];
        $campos    = [];

        // --- Aba 1: Dados Gerais (cabeçalho + grid de produtos) ---
        $colunas = [
            'Data',
            'Cód ERP',
            'Descrição',
            'Fabricante',
            'Lote <br>Validade',
            'Quantidade',
            'Defeito',
        ];

        $alinha = [
            'center',
            'center',
            'start',
            'start',
            'center',
            'end',
            'start',
        ];

        $gridProdutos = [];
        foreach (array_values($ndvIds) as $ordem => $ndvId) {
            $desvio = $this->modelDesvio->getNotifDesvio((int) $ndvId);
            if (!$desvio) {
                continue;
            }
            // RN03.9 — valor inicial do Defeito = ndv_descreva de T42 (editável)
            $entProd = new EntOcoNotifEventoProduto([
                'ndv_id'      => $desvio->ndv_id,
                'nvp_defeito' => $desvio->ndv_descreva,
                'oco_data'    => $desvio->oco_data,
                'pro_codpro'  => $desvio->pro_codpro,
                'pro_despro'  => $desvio->pro_despro,
                'fab_apeFab'  => $desvio->fab_apeFab,
                'lot_lote'    => $desvio->lot_lote,
                'lot_validade' => $desvio->lot_validade,
                'oco_qtd'     => $desvio->oco_qtd,
            ], false, $ordem);
            // RN03.1/RN03.9 — a grid é só leitura pras colunas descritivas
            // (por isso os valores crus de $desvio, sem o wrapper de
            // label/coluna do crInput()), mas precisa embutir na linha:
            // o hidden ndv_id[$ordem]/nvp_id[$ordem] da Entity (crOculto()
            // não tem wrapper, então não afeta o layout) — sem isso o
            // store() nunca recebe ndv_id e cai sempre no erro "Nenhum
            // produto selecionado" — e o input de verdade de
            // nvp_defeito[$ordem] (só texto puro antes, nunca era
            // submetido).
            $f              = $entProd->campos;
            $gridProdutos[] = [
                $f['ndv_id'] . $f['nvp_id'] . $desvio->oco_data,
                $desvio->pro_codpro,
                $desvio->pro_despro,
                $desvio->fab_apeFab,
                $desvio->lot_lote . '<br>' . $desvio->lot_validade,
                $desvio->oco_qtd,
                $f['nvp_defeito'],
            ];
        }

        $campos[0] = [
            $entEvento->campos['nev_id'],
            view('partials/pw_show_produtos', [
                'id'       => null,
                'colunas'  => $colunas,
                'alinha'   => $alinha,
                'produtos' => $gridProdutos,
            ]),
            $entEvento->campos['nev_qtd_adquirida'],
            $entEvento->campos['nev_numero_nf'],
            $entEvento->campos['nev_fornecedor'],
            $entEvento->campos['nev_fabricacao'],
        ];

        // --- Aba 2: Providências ---
        $campos[1] = [
            $entEvento->campos['nev_providencias'],
            $entEvento->campos['nev_notificado'],
            "<div class='col-12 float-start'>&nbsp;</div>",
            view('partials/pw_anexos_notif', ['sufixo' => 'provid', 'linhas' => $this->montaLinhasAnexos('provid', [])]),
        ];

        // --- Aba 3: Parecer Final ---
        $campos[2] = [
            $entEvento->campos['nev_parecer'],
            $entEvento->campos['nev_notivisa'],
            $entEvento->campos['nev_notivisa_num'],
            view('partials/pw_anexos_notif', ['sufixo' => 'parecer', 'linhas' => $this->montaLinhasAnexos('parecer', [])]),
        ];

        // --- Aba 4: Ações (RN03.21/RN03.22) ---
        $entAcao   = new EntOcoNotifEventoAcao(null, 0, false);
        $campos[3] = [
            view('partials/pw_acoes_notif', ['linhas' => [$entAcao->campos]]),
        ];

        $this->data['title']       = 'Notificação de Evento';
        $this->data['desc_metodo'] = 'Nova Notificação de Evento';
        $this->data['secoes']      = $secao;
        $this->data['campos']      = $campos;
        $this->data['destino']     = 'store';
        $this->data['scripts']     = 'my_fornecedores';

        echo view('vw_edicao', $this->data);
    }

    /**
     * addCampoAcao — retorna uma nova linha da aba Ações. Mirror exato de
     * OcoSubtOcorrencia::addCampoTp(): o JS genérico addCampo() (my_fields.js)
     * espera um ARRAY JSON indexado de pedaços de HTML (não um objeto
     * {html:...} — esse formato é de adicionaAcaoExtra(), uma função
     * diferente, específica de T11/OcoTrataOcorrencia); os DOIS ÚLTIMOS
     * itens do array são tratados à parte pelo addCampo() (coluna de
     * controle/exclusão), por isso o botão de exclusão vai sempre na
     * última posição, com um "espaçador" vazio antes dele — mesmo padrão
     * de OcoSubtOcorrencia::addCampoTp().
     */
    public function addCampoAcao($ind)
    {
        $entAcao = new EntOcoNotifEventoAcao(null, (int) $ind, false);
        $f       = $entAcao->campos;

        $campo    = [];
        $campo[0] = $f['tpa_id'];
        $campo[1] = "<div id='divmovi[$ind]' class='d-none row col-6 float-start'>" . $f['tmo_id'] . '</div>';
        $campo[2] = "<div id='divstat[$ind]' class='d-none row col-6 float-start'>" . $f['stt_id'] . '</div>';
        $campo[3] = $f['nac_ordem'];
        $campo[4] = '';
        $campo[5] = $f['bt_delac'];

        return $this->response->setJSON($campo);
    }

    /**
     * addAnexoProvid / addAnexoParecer — nova linha em branco de upload
     * (aba Providências / Parecer Final), mesmo contrato JSON-array de
     * addCampoAcao()/OcoSubtOcorrencia::addCampoTa() (consumido por
     * addCampo(), my_fields.js). Ver partials/pw_anexos_notif.php.
     */
    public function addAnexoProvid($ind)
    {
        return $this->response->setJSON($this->montaLinhaAnexo('provid', (int) $ind));
    }

    public function addAnexoParecer($ind)
    {
        return $this->response->setJSON($this->montaLinhaAnexo('parecer', (int) $ind));
    }

    private function montaLinhaAnexo(string $sufixo, int $ind): array
    {
        $f = (new EntOcoNotifEventoAnexo(null, $sufixo, $ind))->campos;

        return [
            0 => $f['arquivo'],
            1 => '',
            2 => $f['bt_del'],
        ];
    }

    /**
     * Monta o array de $linhas (EntOcoNotifEventoAnexo::campos) consumido por
     * partials/pw_anexos_notif.php — anexos já persistidos + 1 linha em
     * branco no final, para novo upload. Mesmo padrão de carregaContexto()
     * para $linhaAcoes/EntOcoNotifEventoAcao.
     */
    private function montaLinhasAnexos(string $sufixo, array $anexos): array
    {
        $linhas = [];
        foreach (array_values($anexos) as $i => $anexo) {
            $linhas[] = (new EntOcoNotifEventoAnexo((array) $anexo, $sufixo, $i))->campos;
        }
        $linhas[] = (new EntOcoNotifEventoAnexo(null, $sufixo, count($anexos)))->campos;

        return $linhas;
    }

    /**
     * deleteAnexo — RN03.16/RN03.20: exclusão imediata de um anexo já
     * persistido (padrão validado pelo bydsgn — diferente de excluir uma
     * linha ainda não salva, que é só front-end via exclui_campo()).
     */
    public function deleteAnexo($nva_id)
    {
        $db    = \Config\Database::connect('dbOcorrencia');
        $anexo = $db->table('oco_notif_evento_anexo')->where('nva_id', $nva_id)->get()->getRow();

        if (!$anexo) {
            return $this->response->setJSON(['erro' => true, 'msg' => 'Anexo não encontrado']);
        }

        $caminho = WRITEPATH . 'uploads/notif_evento/' . $anexo->nva_arquivo;
        if (is_file($caminho)) {
            @unlink($caminho);
        }

        $db->table('oco_notif_evento_anexo')->where('nva_id', $nva_id)->delete();

        return $this->response->setJSON(['erro' => false, 'msg' => 'Anexo excluído com sucesso']);
    }

    /**
     * salvaAnexos — processa os arquivos enviados em `nva_arquivo_{$sufixo}[]`
     * (multipart, upload junto com o Salvar geral — padrão validado pelo
     * bydsgn), movendo cada um para WRITEPATH.'uploads/notif_evento/' e
     * gravando a linha correspondente em oco_notif_evento_anexo. Chamado de
     * dentro da transação de store().
     */
    private function salvaAnexos(int $nevId, string $sufixo, string $origem): void
    {
        $arquivos = $this->request->getFiles()["nva_arquivo_{$sufixo}"] ?? [];

        if (empty($arquivos)) {
            return;
        }

        $pasta = WRITEPATH . 'uploads/notif_evento/';
        if (!is_dir($pasta)) {
            mkdir($pasta, 0755, true);
        }

        foreach ($arquivos as $arquivo) {
            if (!$arquivo || !$arquivo->isValid() || $arquivo->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            // Validação server-side de tipo de arquivo (RN03.16/RN03.20) —
            // setTipoArq() no client só impede a seleção na UI; sem isso,
            // um POST forjado passava qualquer extensão/MIME direto pro
            // disco. Extensão do nome original + MIME detectado pelo PHP
            // (getMimeType() do CI4 usa finfo sobre o conteúdo real do
            // arquivo, não o que o client informou) — os dois precisam
            // bater com a whitelist.
            $extensao = strtolower(pathinfo($arquivo->getClientName(), PATHINFO_EXTENSION));
            if (
                !in_array($extensao, self::ANEXO_EXTENSOES_PERMITIDAS, true)
                || !in_array($arquivo->getMimeType(), self::ANEXO_MIMES_PERMITIDOS, true)
            ) {
                throw new \Exception('Tipo de arquivo não permitido: ' . esc($arquivo->getClientName()) . ' (permitidos: .' . implode(', .', self::ANEXO_EXTENSOES_PERMITIDAS) . ')');
            }

            $nomeOriginal = $arquivo->getClientName();
            $novoNome     = $arquivo->getRandomName();
            $arquivo->move($pasta, $novoNome);

            (new CommonModel())->insertReg('dbOcorrencia', 'oco_notif_evento_anexo', [
                'nev_id'            => $nevId,
                'nva_origem'        => $origem,
                'nva_arquivo'       => $novoNome,
                'nva_nome_original' => $nomeOriginal,
                'usu_criou'         => session()->get('usu_id'),
                'nva_criado'        => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * show
     */
    public function show($id)
    {
        $dados = $this->carregaContexto((int) $id, true);

        if (!$dados) {
            throw new \Exception('Notificação de Evento não encontrada');
        }

        $this->data['desc_edicao'] = 'Notificação n° ' . str_pad($id, 6, '0', STR_PAD_LEFT)
            . ' - ' . fmtEtiquetaCor($dados['header']->stt_cor, $dados['header']->stt_nome, 1);
        $this->data['secoes']  = ['Dados Gerais', 'Providências', 'Parecer Final', 'Ações'];
        $this->data['campos']  = $dados['campos'];
        $this->data['destino'] = '';

        echo view('vw_edicao', $this->data);
    }

    /**
     * edit
     */
    public function edit($id)
    {
        $dados = $this->carregaContexto((int) $id, false);

        if (!$dados) {
            throw new \Exception('Notificação de Evento não encontrada');
        }

        // Bloqueio server-side — mesma regra de T42: só edita Pendente.
        // Na prática T43 nasce Concluída no Salvar (RN03.22/decisão de
        // arquitetura), então em uso normal este caminho não deve nunca
        // liberar edição — mantido por simetria/segurança (fail-closed).
        if (trim($dados['header']->stt_nome ?? '') !== 'Pendente') {
            throw new \Exception('Alteração não Permitida');
        }

        $this->data['secoes']      = ['Dados Gerais', 'Providências', 'Parecer Final', 'Ações'];
        $this->data['campos']      = $dados['campos'];
        $this->data['destino']     = 'store';
        $this->data['scripts']     = 'my_fornecedores';
        $this->data['desc_edicao'] = 'Notificação n° ' . str_pad($id, 6, '0', STR_PAD_LEFT);

        echo view('vw_edicao', $this->data);
    }

    /**
     * Monta os dados de show()/edit(): cabeçalho + grid de produtos +
     * anexos + ações, reaproveitando as Entities.
     */
    private function carregaContexto(int $id, bool $show): ?array
    {
        $header = $this->model->getNotifEvento($id);
        if (!$header) {
            return null;
        }

        $entEvento = new EntOcoNotifEvento((array) $header, $show);

        $produtos     = $this->model->getProdutosNotificacao($id);
        $colunas = [
            'Data',
            'Cód ERP',
            'Descrição',
            'Fabricante',
            'Lote <br>Validade',
            'Quantidade',
            'Defeito',
        ];

        $alinha = [
            'center',
            'center',
            'start',
            'start',
            'center',
            'end',
            'start',
        ];

        $gridProdutos = [];
        foreach (array_values($produtos) as $ordem => $p) {
            // $entProd        = new EntOcoNotifEventoProduto((array) $p, $show, $ordem);
            $gridProdutos[] = [
                $p->oco_data,
                $p->pro_codpro,
                $p->pro_despro,
                $p->fab_apeFab,
                $p->lot_lote . '<br>' . data_br($p->lot_validade),
                $p->oco_qtd,
                $p->nvp_defeito, // RN03.9: Defeito editável
            ];
        }

        $acoes      = $this->model->getAcoesNotificacao($id);
        $linhaAcoes = [];
        foreach (array_values($acoes) as $ordem => $a) {
            $entAcao      = new EntOcoNotifEventoAcao((array) $a, $ordem, $show);
            $linhaAcoes[] = $entAcao->campos;
        }

        $anexosProvid  = $this->model->getAnexos($id, 'PROVID');
        $anexosParecer = $this->model->getAnexos($id, 'PARECER');

        $campos = [];
        $campos[0] = [
            $entEvento->campos['nev_id'],
            view('partials/pw_show_produtos', [
                'id'       => $id,
                'colunas'  => $colunas,
                'alinha'   => $alinha,
                'produtos' => $gridProdutos,
            ]),
            $entEvento->campos['nev_qtd_adquirida'],
            $entEvento->campos['nev_numero_nf'],
            $entEvento->campos['nev_fornecedor'],
            $entEvento->campos['nev_fabricacao'],
        ];
        $campos[1] = [
            $entEvento->campos['nev_providencias'],
            $entEvento->campos['nev_notificado'],
            view('partials/pw_anexos_notif', ['sufixo' => 'provid', 'linhas' => $this->montaLinhasAnexos('provid', $anexosProvid)]),
        ];
        $campos[2] = [
            $entEvento->campos['nev_parecer'],
            $entEvento->campos['nev_notivisa'],
            $entEvento->campos['nev_notivisa_num'],
            view('partials/pw_anexos_notif', ['sufixo' => 'parecer', 'linhas' => $this->montaLinhasAnexos('parecer', $anexosParecer)]),
        ];
        $campos[3] = [
            view('partials/pw_acoes_notif', ['linhas' => empty($linhaAcoes) ? [(new EntOcoNotifEventoAcao(null, 0, $show))->campos] : $linhaAcoes]),
        ];

        return ['header' => $header, 'campos' => $campos];
    }

    /**
     * store — RN03.22: grava cabeçalho + produtos + anexos + ações; já
     * conclui na mesma transação (mesma decisão de T42, 3.3); em seguida
     * executa as ações cadastradas na ordem de cadastro (nac_ordem).
     * Idempotência: como este método só roda no fluxo de CRIAÇÃO (edit()
     * bloqueia qualquer notificação que não esteja mais Pendente — e, na
     * prática, nenhuma notificação fica Pendente depois do primeiro Salvar,
     * que já a conclui), as ações nunca são reexecutadas para o mesmo nev_id.
     */
    public function store()
    {
        $postado = $this->request->getPost();
        $ret     = [];

        $db = \Config\Database::connect('dbOcorrencia');
        $db->transStart();

        try {
            $ndvIds = array_filter((array) ($postado['ndv_id'] ?? []));
            if (empty($ndvIds)) {
                throw new \Exception('Nenhum produto selecionado para a notificação');
            }

            // RN03.1 (byarq, revisão 02) — 2ª camada de proteção, agora
            // dentro da transação: revalida elegibilidade (Concluída em T42
            // + ainda não vinculado) no momento real do Salvar, cobrindo a
            // janela entre a seleção e o submit (corrida de duplo
            // clique/outra notificação concorrente). Sem isso, o UNIQUE
            // KEY(ndv_id) do banco (oco_notif_evento_produto) estouraria
            // como erro de SQL cru pro usuário em vez de mensagem de negócio.
            $indisponiveis = $this->modelDesvio->validaDisponibilidade($ndvIds);
            if (!empty($indisponiveis)) {
                throw new \Exception(
                    'Produto(s) não estão mais disponíveis para notificação (nº '
                        . implode(', ', $indisponiveis) . ') — outra notificação pode já tê-los usado. Atualize a lista e selecione novamente.'
                );
            }

            // RN03.9 (byarq) — validação server-side de nvp_defeito: campo
            // obrigatório, 5-200 caracteres, por produto. insertReg()
            // (CommonModel) não roda Model::validate(), então sem isso o
            // campo persiste vazio (achado bytest T43-15).
            $defeitosValidar = (array) ($postado['nvp_defeito'] ?? []);
            foreach (array_values($ndvIds) as $ordem => $ndvId) {
                $defeito = trim((string) ($defeitosValidar[$ordem] ?? ''));
                $tam     = mb_strlen($defeito);
                if ($tam < 5 || $tam > 200) {
                    throw new \Exception('O campo Defeito é obrigatório (5 a 200 caracteres) para todos os produtos selecionados');
                }
            }

            // RN03.21 (byarq) — ao menos 1 Ação é obrigatória. O loop de
            // gravação de ações (abaixo) só ignora linhas vazias, sem checar
            // se sobra pelo menos 1 válida (achado bytest T43-40).
            $tpaIdsValidar = array_filter((array) ($postado['tpa_id'] ?? []));
            if (empty($tpaIdsValidar)) {
                throw new \Exception('É obrigatório cadastrar ao menos uma Ação (aba Ações)');
            }

            $sttConcluida = $this->model->getStatusId('Finalizada');
            if (!$sttConcluida) {
                throw new \Exception('Status "Finalizada" de Notificação de Evento não configurado (cfg_status)');
            }

            $dadosCabecalho = [
                'nev_qtd_adquirida' => $postado['nev_qtd_adquirida'] ?? null,
                'nev_numero_nf'     => $postado['nev_numero_nf'] ?? null,
                'nev_fornecedor'    => $postado['nev_fornecedor'] ?? null,
                'nev_fabricacao'    => $postado['nev_fabricacao'] ?? null,
                'nev_providencias'  => $postado['nev_providencias'] ?? null,
                'nev_notificado'    => $postado['nev_notificado'] ?? null,
                'nev_parecer'       => $postado['nev_parecer'] ?? null,
                'nev_notivisa'      => ($postado['nev_notivisa'] ?? 'N') === 'S' ? 'S' : 'N',
                'nev_notivisa_num'  => $postado['nev_notivisa_num'] ?? null,
                'stt_id'            => $sttConcluida,
                'usu_criou'         => session()->get('usu_id'),
            ];

            $nevId = (int) ($postado['nev_id'] ?? 0);

            if ($nevId) {
                // Bloqueio server-side (byarq, achado na revisão do plano de
                // testes) — store() é rota própria (POST direto), não passa
                // por edit(); o comentário anterior ("bloqueio já impede
                // chegar aqui") era falso — um POST forjado com nev_id de
                // notificação já Concluída caía direto no update()/deletes
                // sem checagem nenhuma. Mesmo padrão de
                // NotifDesvio::store() (T42): só grava enquanto Pendente.
                $existente = $this->model->getNotifEvento($nevId);
                if (!$existente) {
                    throw new \Exception('Notificação de Evento não encontrada');
                }
                if (trim($existente->stt_nome ?? '') !== 'Pendente') {
                    throw new \Exception('Alteração não Permitida — Notificação de Evento já concluída');
                }

                unset($dadosCabecalho['usu_criou']);
                $this->model->update($nevId, $dadosCabecalho);
                (new CommonModel())->deleteReg('dbOcorrencia', 'oco_notif_evento_produto', "nev_id = {$nevId}");
                (new CommonModel())->deleteReg('dbOcorrencia', 'oco_notif_evento_acao', "nev_id = {$nevId}");
            } else {
                $nevId = $this->model->skipValidation(false)->insert($dadosCabecalho);
                if (!$nevId) {
                    throw new \Exception(implode('<br>', $this->model->errors() ?: ['Erro ao gravar Notificação de Evento']));
                }
            }

            // --- Produtos (RN03.9) ---
            $defeitos = (array) ($postado['nvp_defeito'] ?? []);
            $produtosInseridos = [];
            foreach (array_values($ndvIds) as $ordem => $ndvId) {
                $linha = [
                    'nev_id'      => $nevId,
                    'ndv_id'      => (int) $ndvId,
                    'nvp_defeito' => $defeitos[$ordem] ?? '',
                ];
                (new CommonModel())->insertReg('dbOcorrencia', 'oco_notif_evento_produto', $linha);
                $produtosInseridos[] = $linha;
            }

            // --- Anexos (RN03.16/RN03.20) — padrão validado pelo bydsgn:
            // sobem junto com o Salvar geral do form (submeteForm()/
            // executaAjax() já montam FormData automaticamente para
            // qualquer <input type="file"> dentro de #form1 — sem upload
            // assíncrono item-a-item). Ver partials/pw_anexos_notif.php.
            $this->salvaAnexos($nevId, 'provid', 'PROVID');
            $this->salvaAnexos($nevId, 'parecer', 'PARECER');

            // --- Ações (RN03.21/RN03.22) ---
            $tpaIds  = (array) ($postado['tpa_id'] ?? []);
            $tmoIds  = (array) ($postado['tmo_id_tpa'] ?? []);
            $sttIds  = (array) ($postado['stt_id_tpa'] ?? []);
            $ordens  = (array) ($postado['nac_ordem'] ?? []);

            $acoesParaExecutar = [];
            foreach ($tpaIds as $i => $tpaId) {
                if (!$tpaId) {
                    continue;
                }
                $linhaAcao = [
                    'nev_id'    => $nevId,
                    'tpa_id'    => $tpaId,
                    'nac_ordem' => $ordens[$i] ?? $i,
                    'stt_id'    => $sttIds[$i] ?? null,
                    'tmo_id'    => $tmoIds[$i] ?? null,
                ];
                (new CommonModel())->insertReg('dbOcorrencia', 'oco_notif_evento_acao', $linhaAcao);
                $acoesParaExecutar[] = $linhaAcao;
            }

            // Ordena por nac_ordem (sequência de cadastro — RN03.22)
            usort($acoesParaExecutar, fn($a, $b) => $a['nac_ordem'] <=> $b['nac_ordem']);

            $tipoAcaoModel = new OcorreTipoAcaoModel();
            $catalogo      = [];
            foreach ($tipoAcaoModel->getTipoAcao(array_column($acoesParaExecutar, 'tpa_id')) as $tp) {
                $catalogo[$tp->tpa_id] = $tp;
            }

            foreach ($acoesParaExecutar as $acao) {
                $tipo = (int) ($catalogo[$acao['tpa_id']]->tpa_tipo ?? 0);

                if ($tipo === 3 && !empty($acao['tmo_id'])) {
                    // RN03.22 — "gerar movimentação": itera por CADA produto
                    // selecionado, saldo = Quantidade (RN03.8/oco_qtd) de
                    // cada linha — generalização de
                    // OcoTrataOcorrencia::gerarMovimentacao() (que trata 1
                    // único produto) para N produtos.
                    $movsOco = [];
                    foreach ($this->model->getProdutosNotificacao($nevId) as $produto) {
                        $movsOco[] = [
                            'id'           => $acao['tmo_id'],
                            'qt'           => $produto->oco_qtd,
                            'msg'          => $produto->nvp_defeito ?? $dadosCabecalho['nev_providencias'],
                            'pro_id'       => $produto->pro_id,
                            'rep_id'       => null,
                            'reserva'      => null,
                            'lot_lote'     => $produto->lot_lote,
                            'lot_validade' => $produto->lot_validade,
                        ];
                    }
                    if (!empty($movsOco)) {
                        $movim = geraMovimentoRequisicoes($movsOco, $this->data['controler']);
                        if (($movim['status'] ?? '') === 'Erro') {
                            throw new \Exception($movim['mensagem'] ?? 'Erro ao gerar movimentação de estoque');
                        }
                    }
                }
                // tipo 1 (Justificar) / 2 (Abrir Tela) / 4 (Alterar Status):
                // sem efeito colateral adicional em T43 — a ação em si já
                // fica registrada em oco_notif_evento_acao (RN03.21).
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new \Exception('Não foi possível gravar a Notificação de Evento, verifique!');
            }

            $ret['erro'] = false;
            $ret['msg']  = 'Notificação de Evento gravada e concluída com sucesso!';
            session()->setFlashdata('msg', $ret['msg']);
            $ret['url'] = site_url($this->data['controler']);
        } catch (\Exception $e) {
            $db->transRollback();
            $ret['erro'] = true;
            $ret['msg']  = $e->getMessage();
        }

        return $this->response->setJSON($ret);
    }

    /**
     * delete — mesma regra de T42: sem exclusão manual prevista pelas RNs;
     * bloqueado no server-side.
     */
    public function delete($id)
    {
        return $this->response->setJSON([
            'erro' => true,
            'msg'  => 'Exclusão não permitida para Notificação de Evento',
        ]);
    }

    /**
     * GeraEtiqueta — mirror exato do padrão de T42/Estoque\EtqProduto.
     */
    public function GeraEtiqueta(int $id, int $qtia): string
    {
        $redis     = \Config\Services::redis();
        $sessionId = session_id();

        $chave = "etq:{$sessionId}:" . md5('nev_' . $id . '_' . $qtia);

        $cached = $redis->get($chave);

        if (!$cached) {
            $dados = $this->model->getNotifEvento($id);

            if (!$dados) {
                return json_encode([
                    'erro' => 'Notificação de Evento não encontrada',
                ]);
            }

            $etiquetas = array_fill(0, $qtia, $dados);

            $redis->setex($chave, 900, json_encode($etiquetas));
            $redis->sAdd("etq_session:{$sessionId}", $chave);
        }

        return json_encode([
            'link'  => base_url('/CriaEtiquetaZPL/emiteEtiqueta'),
            'chave' => $chave,
        ]);
    }
}
