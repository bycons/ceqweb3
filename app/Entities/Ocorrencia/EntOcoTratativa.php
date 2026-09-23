<?php

namespace App\Entities\Ocorrencia;

use CodeIgniter\Entity\Entity;
use App\Libraries\MyCampo;
use App\Models\Config\ConfigStatusModel;
use App\Models\Estoqu\EstoquTipoMovimentacaoModel;
use App\Models\Produt\ProdutLoteModel;
use App\Models\Produt\ProdutProdutoModel;

class EntOcoTratativa extends Entity
{
    protected $attributes = [
        'oco_id'        => null,
        'tpo_id'        => null,
        'tpa_id'        => null,
        'lot_lote'      => null,
        'oco_descricao' => null,
        'pro_despro'    => null,
        'oco_qtd'       => null,
        'oco_data'      => null,
        'stt_id'        => null,
        'tmo_id'        => null,
        'oco_justi'     => null,
        'usu_nome'      => null,
        'tel_id'        => null,
    ];

    protected $casts = [
        'oco_id' => 'integer',
        'tpo_id' => 'integer',
        'tpa_id' => 'integer',
        'stt_id' => 'integer',
        'tmo_id' => 'integer',
        'tel_id' => 'integer',
    ];

    public array $campos = [];

    public function __construct(object|array|null $data = null)
    {
        if (is_array($data)) {
            $data = (object) $data;
        }
        parent::__construct((array) ($data ?? []));
        $this->campos = $this->defCampos($data ?? new \stdClass());
    }

    public function defCampos(object $dados)
    // DADOS GERAIS
    {
        $dados = (array) $dados;
        $ret = [];

        // ID DA OCORRÊNCIA
        $ocoid = new MyCampo('oco_ocorrencia', 'oco_id');
        $ocoid->valor = $dados['oco_id'] ?? null;
        $ret['oco_id'] = $ocoid->crOculto();

        // TIPO DE AÇÃO
        $config['Label'] = 'Ação';
        $config['Pai'] = 'tpo_id';
        $config['Urlbusca'] = base_url('Buscas/buscaAcoesPorTipo');

        $ret['tpa_id'] = criaSelectRelativo(
            'oco_tipo_acao',
            'tpa_id',
            'tpa_nome',
            $dados['tpa_id'] ?? null,
            2,
            'oco_ocorrencia',
            [],
            $config
        );

        // SUBTIPO
        $config['Label'] = 'Subtipo';

        $ret['sut_id'] = criaSelectRelativo(
            'oco_subt_ocorrencia',
            'sut_id',
            'sut_nome',
            $dados['sut_id'] ?? null,
            1,
            'oco_ocorrencia',
            [],
            $config
        );

        // USUÁRIO
        $usu           = new MyCampo('oco_ocorrencia', 'usu_nome');
        $usu->valor    = (isset($dados['usu_nome'])) ? $dados['usu_nome'] : '';
        $usu->objeto   = '';
        $usu->label    = 'Usuário';
        $usu->dispForm = '2col';
        $usu->size     = 40;
        $usu->leitura  = true;
        $ret['usu_nome'] = $usu->crInput();

        // DESCRIÇÃO
        $desc              = new MyCampo('oco_ocorrencia', 'oco_descricao');
        $desc->nome        = 'oco_descricao';
        $desc->valor       = (isset($dados['oco_descricao'])) ? $dados['oco_descricao'] : '';
        $desc->leitura     = true;
        $desc->label       = 'Descrição';
        $desc->linhas      = 3;
        $desc->colunas     = 56;
        $desc->dispForm    = '2col';
        $ret['oco_descricao'] = $desc->crTexto();

        // LOTE
        $lotid              = new MyCampo('oco_ocorrencia', 'lot_id');
        $lotid->valor       = (isset($dados['lot_id'])) ? $dados['lot_id'] : '';
        $ret['lot_id'] = $lotid->crOculto();

        $lote              = new MyCampo('pro_sap_lote', 'lot_lote');
        $lote->valor       = model(ProdutLoteModel::class)->getBuscaLote($dados['lot_id'] ?? null);
        $lote->leitura     = true;
        $lote->label       = 'Lote';
        $lote->size        = 54;
        $lote->funcBlur    = "buscaLoteProduto(this,'" . base_url('/buscas/buscaProdutoporLote') . "')";
        $ret['lot_lote']   = $lote->crInput();

        $descpro = '';
        if (isset($dados['pro_id']) && !empty($dados['pro_id'])) {
            $modProduto = new ProdutProdutoModel();
            $prod = $modProduto->getProduto($dados['pro_id']);
            $descpro = !empty($listaProd) ? $prod[0]->pro_despro : '';
        }
        // PRODUTO
        $produto           = new MyCampo('pro_sap_produto', 'pro_despro');
        $produto->valor    = $descpro;
        $produto->dispForm = '2col';
        $produto->label    = ' ';
        $produto->size     = 54;
        $produto->leitura  = true;
        $ret['pro_despro'] = $produto->crInput();

        // QUANTIDADE
        $qtd               = new MyCampo('oco_ocorrencia', 'oco_qtd');
        $qtd->valor        = (isset($dados['oco_qtd'])) ? $dados['oco_qtd'] : '';
        $qtd->label        = 'Quantidade';
        $qtd->dispForm     = '2col';
        $qtd->largura      = 5;
        $qtd->leitura      = true;
        $ret['oco_qtd'] = $qtd->crInput();


        // DATA
        $data              = new MyCampo('oco_ocorrencia', 'oco_data');
        $data->valor       = $dados->oco_data ?? date('Y-m-d\TH:i');
        $data->label       = 'Data da Ocorrência';
        $data->dispForm    = '2col';
        $data->leitura     = true;
        $data->largura     = 30;

        $ret['oco_data'] = $data->crInput();

        return $ret;
    }

    /**
     * Monta os campos de UMA linha da aba Ações da tratativa (T12).
     *
     * - `$dados === null`: linha ad-hoc nova (botão "+", RN03.15) — select
     *   livre de `tpa_id` (`FunChan='verificaTipoAcao(this)'`), todos os
     *   campos condicionais disponíveis (JS decide visibilidade via
     *   `verificaTipoAcao()`), sem `oac_id` (fica implícito que é um
     *   INSERT em `oco_ocorrencia_acao`), e checkbox "Executar agora"
     *   marcado por padrão.
     * - `$dados !== null` e `oac_executada === 'S'`: linha já executada
     *   (automática ou manual, de qualquer rodada) — somente leitura, com
     *   indicação de quando/como foi executada.
     * - `$dados !== null` e pendente: linha vinda do seed (criação da
     *   ocorrência) ou de uma rodada anterior que não a marcou — campo
     *   oculto `oac_id[$pos]` (identifica o UPDATE), ação já definida pelo
     *   catálogo (exibida como rótulo, não é mais um select livre),
     *   checkbox "Executar agora" marcado por padrão, e os campos
     *   condicionais editáveis conforme `tpa_tipo` (oco_justi/tmo_id/
     *   stt_id/tel_id).
     *
     * Todos os campos usam `MyCampo::setOrdem($pos)` (gera `nome[$pos]`),
     * garantindo indexação posicional consistente entre as linhas — sem
     * distinção "origem"/"extra".
     *
     * @param bool $forcaLeitura Quando `true`, força o branch somente-leitura
     *   INDEPENDENTE de `oac_executada` — usado por telas de CONSULTA
     *   (ex.: `OcoOcorrencia::show()`), onde nenhuma linha pode ser editável
     *   mesmo que a ação ainda esteja pendente. Nesse caso (pendente +
     *   forçado) o texto exibido é "Pendente" em vez de "Executada em...",
     *   sem checkbox nem campos condicionais editáveis. `finalizar()` e
     *   `store()` continuam chamando sem esse argumento (default `false`).
     */
    public function defCamposAcao(?object $dados = null, int $pos = 0, bool $forcaLeitura = false): array
    {
        $ret = [];

        if ($dados === null) {
            // AÇÃO (editável — linha ad-hoc nova, botão "+")
            $config = [];
            $config['Label']    = 'Ação';
            $config['DispForm'] = 'col-12';
            $config['Ordem']    = $pos;
            $config['FunChan']  = 'verificaTipoAcao(this)';

            $ret['tpa_id'] = criaSelectRelativo(
                'oco_tipo_acao',
                'tpa_id',
                'tpa_nome',
                null,
                1,
                'oco_ocorrencia',
                ['tpa_ativo' => 'A'],
                $config,
            );

            // TIPO DE MOVIMENTAÇÃO (tpa_tipo=3 — Gerar Movimentação)
            $config['Label']    = 'Tipo de Movimentação';
            $config['FunChan']  = '';
            $config['DispForm'] = 'col-12';
            $ret['tmo_id'] = criaSelectRelativo(
                'est_tipo_movimentacao',
                'tmo_id',
                'tmo_nome',
                null,
                1,
                'oco_tipo_acao',
                [],
                $config,
            );

            // JUSTIFICATIVA (tpa_tipo=1 — Justificar)
            $justi = new MyCampo('oco_ocorrencia', 'oco_justi');
            $justi->label    = 'Justificativa';
            $justi->dispForm = 'col-12';
            $justi->linhas   = 3;
            $justi->colunas  = 56;
            $justi->setOrdem($pos);
            $ret['oco_justi'] = $justi->crTexto();

            // MÓDULO + TELA (tpa_tipo=2 — Abrir Tela)
            $config['Label']    = 'Módulo';
            $config['Largura'] = 30;
            $config['DispForm'] = 'col-6';
            $ret['mod_id'] = criaSelectRelativo(
                'cfg_modulo',
                'mod_id',
                'mod_nome',
                null,
                1,
                'oco_tipo_acao',
                [],
                $config,
            );

            $config['Label']    = 'Tela';
            $config['Pai']      = "mod_id[$pos]";
            $config['Urlbusca'] = base_url('buscas/busca_tela_modulo');
            $ret['tel_id'] = criaSelectRelativo(
                'cfg_tela',
                'tel_id',
                'tel_nome',
                null,
                2,
                'oco_tipo_acao',
                [],
                $config,
            );

            // STATUS (tpa_tipo=4 — Alterar Status)
            $config = [];
            $config['Label']    = 'Status';
            $config['DispForm'] = 'col-12';
            $config['Ordem']    = $pos;
            $ret['stt_id'] = criaSelectRelativo(
                'vw_cfg_status_relac',
                'stt_id',
                'stt_tela_status',
                null,
                1,
                'oco_tipo_acao',
                [],
                $config,
            );

            // EXECUTAR AGORA — marcado por padrão (mesmo tratamento das
            // demais linhas, não é mais uma linha "extra" à parte)
            $ret['executar'] = $this->campoExecutar($pos);

            // EXCLUIR (somente ações adicionadas manualmente podem ser excluídas)
            $del            = new MyCampo();
            $del->dispForm  = '2col';
            $del->nome      = "bt_del[$pos]";
            $del->id        = "bt_del[$pos]";
            $del->i_cone    = "<i class='fas fa-trash'></i>";
            $del->classep   = "btn-outline-danger btn-sm bt-exclui";
            $del->funcChan  = "exclui_campo('acoes',this)";
            $del->place     = "Excluir Campo";
            $ret['bt_del']  = $del->crBotao();

            return $ret;
        }

        $dados->tpa_id      = $dados->tpa_id ?? null;
        $dados->tpa_tipo    = $dados->tpa_tipo ?? null;
        $executada          = ($dados->oac_executada ?? 'N') === 'S';
        // "somente leitura" cobre tanto a linha já executada quanto uma
        // linha pendente exibida numa tela de CONSULTA ($forcaLeitura) — em
        // ambos os casos não há checkbox nem campo condicional editável.
        $readonly           = $executada || $forcaLeitura;

        // NOME DA AÇÃO (somente leitura — a ação já vem definida pelo
        // catálogo/seed, não é mais um select livre nesta linha)
        $acao = new MyCampo('oco_tipo_acao', 'tpa_nome');
        $acao->valor    = $dados->tpa_nome ?? '';
        $acao->leitura  = true;
        $acao->size     = 40;
        $acao->dispForm = 'col-4';
        $ret['tpa_nome'] = $acao->crInput();


        // JUSTIFICAR (tpa_tipo=1)
        if ((int) $dados->tpa_tipo === 1) {
            $justi = new MyCampo('oco_ocorrencia', 'oco_justi');
            $justi->valor       = $dados->oco_justi ?? '';
            $justi->dispForm    = 'col-6';
            $justi->linhas      = 3;
            $justi->colunas     = 56;
            $justi->leitura     = $readonly;
            $justi->obrigatorio = !$readonly;
            $justi->setOrdem($pos);

            $ret['oco_justi'] = $justi->crInput();
        }

        // MOVIMENTAÇÃO (tpa_tipo=3 — Gerar Movimentação)
        if ((int) $dados->tpa_tipo === 3) {
            if ($readonly) {
                $tmoModel = new EstoquTipoMovimentacaoModel();
                $opc_tmo  = [];
                foreach ($tmoModel->asObject()->findAll() as $tmo) {
                    $opc_tmo[$tmo->tmo_id] = $tmo->tmo_nome;
                }

                $movNome = new MyCampo('est_tipo_movimentacao', 'tmo_nome');
                $movNome->valor    = $opc_tmo[$dados->tmo_id ?? null] ?? '';
                $movNome->label    = 'Movimentação';
                $movNome->leitura  = true;
                $movNome->dispForm = 'col-6';
                $movNome->size     = 50;

                $ret['tmo_id'] = $movNome->crInput();
            } else {
                $config = [];
                $config['Label']    = 'Tipo de Movimentação';
                $config['DispForm'] = 'col-6';
                $config['Ordem']    = $pos;
                // criaSelectRelativo() força leitura automática quando um
                // $valor pré-selecionado é passado (ver funcoes_helper.php)
                // — precisa ser sobrescrito para o campo continuar editável.
                $config['Leitura']  = false;

                $ret['tmo_id'] = criaSelectRelativo(
                    'est_tipo_movimentacao',
                    'tmo_id',
                    'tmo_nome',
                    $dados->tmo_id ?? null,
                    1,
                    'oco_tipo_acao',
                    [],
                    $config,
                );
            }
        }

        // ABRIR TELA (tpa_tipo=2)
        if ((int) $dados->tpa_tipo === 2) {
            if ($readonly) {
                // Correção de bug pré-existente: o código original chamava
                // getTelas() em OcorreSubtOcorrenciaModel, que não tem esse
                // método (só existe em ConfigTelaModel) — resultava em erro
                // fatal sempre que uma ação "Abrir Tela" já executada era
                // renderizada.
                $mod = new \App\Models\Config\ConfigTelaModel();
                $opc = [];
                foreach ($mod->getTelas() as $tel) {
                    $opc[$tel->tel_id] = $tel->tel_nome;
                }

                $tela = new MyCampo('', 'tel_id');
                $tela->valor    = $opc[$dados->tel_id ?? null] ?? '';
                $tela->label    = 'Tela';
                $tela->leitura  = true;
                $tela->dispForm = 'col-6';
                $tela->size     = 60;

                $ret['tel_id'] = $tela->crInput();
            } else {
                $config = [];
                $config['Label']    = 'Tela';
                $config['DispForm'] = 'col-6';
                $config['Ordem']    = $pos;
                $config['Leitura']  = false;

                $ret['tel_id'] = criaSelectRelativo(
                    'cfg_tela',
                    'tel_id',
                    'tel_nome',
                    $dados->tel_id ?? null,
                    1,
                    'oco_tipo_acao',
                    [],
                    $config,
                );
            }
        }

        // ALTERAR STATUS (tpa_tipo=4)
        if ((int) $dados->tpa_tipo === 4) {
            if ($readonly) {
                $statModel = new ConfigStatusModel();
                $stt       = $dados->stt_id ? $statModel->getStatus($dados->stt_id) : null;

                $stat = new MyCampo();
                $stat->id       = $stat->nome = 'stt_nome';
                $stat->label    = 'Status';
                $stat->leitura  = true;
                $stat->dispForm = 'col-4';
                $stat->size     = 50;
                $stat->valor    = $stt ? fmtEtiquetaCor($stt->cor_valorrgb, $stt->stt_nome) : '';
                $ret['stt_nome'] = $stat->crShow();
            } else {
                $config = [];
                $config['Label']    = 'Status';
                $config['DispForm'] = 'col-6';
                $config['Ordem']    = $pos;
                $config['Leitura']  = false;

                $ret['stt_id'] = criaSelectRelativo(
                    'cfg_status',
                    'stt_id',
                    'stt_nome',
                    $dados->stt_id ?? null,
                    1,
                    'oco_tipo_acao',
                    [],
                    $config,
                );
            }
        }

        if ($executada) {
            // LINHA JÁ EXECUTADA — somente leitura, sem reenvio ao servidor
            $ret['tpa_id'] = '';

            $quando = !empty($dados->oac_executado_em)
                ? toDataBr(new \DateTime($dados->oac_executado_em))
                : '';
            $status = 'Executada em ' . $quando . (!empty($dados->oac_automatica) ? ' (Automática)' : ' (Manual)');

            $info            = new MyCampo();
            $info->id        = $info->nome = 'oac_status_exec';
            $info->label     = 'Status';
            $info->dispForm  = 'col-4 float-end';
            $info->valor     = $status;
            $info->size     = 50;
            $info->leitura     = true;
            $ret['oac_status_exec'] = $info->crInput();
        } elseif ($forcaLeitura) {
            // LINHA PENDENTE, EXIBIDA EM TELA DE CONSULTA — somente
            // informativa: sem reenvio ao servidor, sem checkbox.
            $ret['tpa_id'] = '';

            $info            = new MyCampo();
            $info->id        = $info->nome = 'oac_status_exec';
            $info->label     = 'Status';
            $info->dispForm  = 'col-4 float-end';
            $info->valor     = 'Pendente';
            $info->leitura     = true;
            $ret['oac_status_exec'] = $info->crInput();
        } else {
            // LINHA PENDENTE (do seed ou de rodada anterior) — oac_id
            // oculto identifica o UPDATE; checkbox "Executar agora" marcado
            // por padrão.
            $oacHidden        = new MyCampo();
            $oacHidden->nome  = 'oac_id';
            $oacHidden->valor = $dados->oac_id ?? null;
            $oacHidden->setOrdem($pos);
            $ret['oac_id'] = $oacHidden->crOculto();

            $tpaHidden        = new MyCampo();
            $tpaHidden->nome  = 'tpa_id';
            $tpaHidden->valor = $dados->tpa_id;
            $tpaHidden->setOrdem($pos);
            $ret['tpa_id'] = $tpaHidden->crOculto();

            // RN03.18.2 — marca oculta com o tipo da ação (não é dado de
            // negócio; usada apenas pelo front para saber se há ação
            // "Gerar Movimentação" (tpa_tipo=3) marcada, e então pedir
            // confirmação (MSG 6) antes de submeter a tratativa.
            $tpaTipoHidden        = new MyCampo();
            $tpaTipoHidden->nome  = 'tpa_tipo_marca[]';
            $tpaTipoHidden->valor = $dados->tpa_tipo;
            $ret['tpa_tipo_marca'] = $tpaTipoHidden->crOculto();
        }

        // EXECUTAR AGORA — vem por último, na mesma ordem usada em
        // addCampoAcao() (tpa_id, campos condicionais, executar, bt_del).
        // Só existe para a linha pendente editável (não para executada nem
        // para exibição forçada em somente-leitura).
        if (!$executada && !$forcaLeitura) {
            $ret['executar'] = $this->campoExecutar($pos);
        }

        return $ret;
    }

    /**
     * Checkbox "Executar agora" (marcado por padrão) de uma linha pendente
     * da aba Ações. `valor` = valor SUBMETIDO quando marcado (fixo 'S');
     * `selecionado` = 'S' também, para nascer sempre marcado (comportamento
     * padrão da tratativa: tudo que está pendente é executado, a menos que
     * o usuário desmarque manualmente).
     */
    private function campoExecutar(int $pos): string
    {
        $simnao['S'] = 'Sim';
        $simnao['N'] = 'Não';

        $exec              = new MyCampo();
        $exec->nome = $exec->id        = 'executar';
        $exec->label       = 'Executar Agora';
        $exec->opcoes      = $simnao;
        $exec->dispForm    = 'col-2';
        $exec->valor       = $exec->selecionado = 'S';
        $exec->setOrdem($pos);

        return $exec->cr2opcoes();
    }
}
