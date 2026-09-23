<?php

namespace App\Entities\Ocorrencia;

use CodeIgniter\Entity\Entity;
use App\Libraries\MyCampo;
use App\Models\Config\ConfigStatusModel;
use App\Models\Ocorre\OcorreSubtOcorrenciaModel;
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

    public function defCamposAcao(object $dados): array
    {
        $ret = [];
        $dados->tpa_id = $dados->tpa_id ?? null;
        $leitura = $dados->somente_leitura ?? false;

        // ENVIA O TPA_ID OCULTO
        $tpaHidden = new MyCampo('oco_ocorrencia', 'tpa_id[]');
        $tpaHidden->valor = $dados->tpa_id ?? null;

        $ret['tpa_id'] = $tpaHidden->crOculto();

        // RN03.18.2 — marca oculta com o tipo da ação (não é enviada ao
        // servidor como dado de negócio; usada apenas pelo front para saber
        // se há ação "Gerar Movimentação" (tpa_tipo=3) marcada, e então pedir
        // confirmação (MSG 6) antes de submeter a tratativa.
        $tpaTipoHidden = new MyCampo();
        $tpaTipoHidden->nome  = 'tpa_tipo_marca[]';
        $tpaTipoHidden->valor = $dados->tpa_tipo ?? null;
        $ret['tpa_tipo_marca'] = $tpaTipoHidden->crOculto();

        // TIPO DE AÇÃO
        $acao = new MyCampo('oco_tipo_acao', 'tpa_nome');
        $acao->valor    = $dados->tpa_nome ?? '';
        $acao->leitura  = true;
        $acao->size     = 50;
        $acao->dispForm = 'col-6';

        $ret['tpa_nome'] = $acao->crInput();

        // JUSTIFICAR
        if ((int)$dados->tpa_tipo === 1) {
            $justi = new MyCampo('oco_ocorrencia', 'oco_justi');
            $justi->valor       = $dados->oco_justi ?? '';
            $justi->obrigatorio = true;
            $justi->dispForm    = 'col-6';
            $justi->linhas      = 3;
            $justi->colunas     = 56;
            $justi->leitura     = $leitura;
            $justi->obrigatorio = !$leitura;

            $ret['oco_justi'] = $justi->crInput();
        }

        // MOVIMENTAÇÃO
        if ((int)$dados->tpa_id === 3) {

            $tmo_id = $dados->tmo_id ?? null;

            $tmoModel = new EstoquTipoMovimentacaoModel();
            $opc_tmo = [];

            foreach ($tmoModel->asObject()->findAll() as $tmo) {
                $opc_tmo[$tmo->tmo_id] = $tmo->tmo_nome;
            }

            $movNome = new MyCampo('est_tipo_movimentacao', 'tmo_nome');
            $movNome->valor    = $opc_tmo[$tmo_id] ?? '';
            $movNome->label    = 'Movimentação';
            $movNome->leitura  = true;
            $movNome->dispForm = 'col-6';
            $movNome->size     = 50;

            $ret['tmo_id'] = $movNome->crInput();
        }

        // STATUS
        if ((int) $dados->stt_id > 0) {

            $statModel = new ConfigStatusModel();

            // Busca status completo no cfg_status
            $stt = $statModel->getStatus($dados->stt_id);

            $stat = new MyCampo();
            $stat->id       = $stat->nome = 'stt_nome';
            $stat->label    = 'Status';
            $stat->leitura  = true;
            $stat->dispForm = 'col-4';
            $stat->size     = 50;
            // debug($stt);
            $stat->valor = $stt
                // ? fmtEtiquetaClasse($stt->stt_cor, $stt->stt_nome)
                ? fmtEtiquetaCor($stt->cor_valorrgb, $stt->stt_nome)
                : '';
            $ret['stt_nome'] = $stat->crShow();
        }

        // TELA
        if ((int)$dados->tpa_id === 2) {

            $mod = new OcorreSubtOcorrenciaModel();
            $opc = [];

            foreach ($mod->getTelas() as $tel) {
                $opc[$tel->tel_id] = $tel->tel_nome;
            }

            $tel_id = $mod->getTelaByTpoTpa(
                $dados->tpo_id,
                $dados->tpa_id
            );

            $tela = new MyCampo('', 'tel_id');
            $tela->valor    = $opc[$tel_id] ?? '';
            $tela->label    = 'Tela';
            $tela->leitura  = true;
            $tela->dispForm = 'col-6';
            $tela->size     = 60;

            $ret['tel_id'] = $tela->crInput();
        }

        return $ret;
    }

    /**
     * RN03.15 — Linha de "ação extra" (avulsa), adicionada manualmente pelo
     * usuário na aba Ações da tratativa (T12), além das ações de origem do
     * subtipo. Vai em name diferente (tpa_id_extra[]/tmo_id_extra[]/
     * stt_id_extra[]/mod_id_extra[]/tel_id_extra[]) para o store() distinguir
     * origem x manual sem precisar de flag nova de schema — e para permitir
     * excluir apenas as manuais (a linha de origem nunca recebe botão de
     * excluir).
     *
     * Bloqueante 2 (revisão 01, decisão do byarq — alternativa b): a ação
     * extra NÃO é restrita a "Justificar". Replica o mesmo padrão condicional
     * de campos já usado em EntOcoSubtOcorrencia::defCamposAcao() —
     * tmo_id para tpa_tipo=3 (Gerar Movimentação), mod_id/tel_id para
     * tpa_tipo=2 (Abrir Tela), stt_id para tpa_tipo=4 (Alterar Status) — para
     * que a ação extra tenha efeito real independente do tipo escolhido.
     * O toggle de visibilidade (divmovi/divtela/divstat) é feito pela função
     * JS já existente `verificaTipoAcao()` (my_fields.js), reaproveitada sem
     * alterações — o `FunChan` do select de tpa_id já a aciona.
     */
    public function defCamposAcaoExtra(int $pos = 0): array
    {
        $ret = [];

        // AÇÃO (editável)
        $config = [];
        $config['Label']    = 'Ação';
        $config['DispForm'] = 'col-6';
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
            'tpa_id_extra'
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
            'tmo_id_extra'
        );

        // MÓDULO + TELA (tpa_tipo=2 — Abrir Tela)
        $config['Label']    = 'Módulo';
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
            'mod_id_extra'
        );

        $config['Label']    = 'Tela';
        $config['Pai']      = 'mod_id';
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
            'tel_id_extra'
        );

        // STATUS (tpa_tipo=4 — Alterar Status)
        $config = [];
        $config['Label']    = 'Status';
        $config['DispForm'] = 'col-12';
        $ret['stt_id'] = criaSelectRelativo(
            'cfg_status',
            'stt_id',
            'stt_nome',
            null,
            1,
            'oco_tipo_acao',
            [],
            $config,
            'stt_id_extra'
        );

        // EXCLUIR (somente ações adicionadas manualmente podem ser excluídas)
        $del            = new MyCampo();
        $del->dispForm  = '2col';
        $del->nome      = "bt_delacaoextra[$pos]";
        $del->id        = "bt_delacaoextra[$pos]";
        $del->i_cone    = "<i class='fas fa-trash'></i>";
        $del->classep   = "btn-outline-danger btn-sm bt-exclui";
        $del->funcChan  = "removeAcaoExtra(this)";
        $del->place     = "Excluir Ação";
        $ret['bt_del']  = $del->crBotao();

        return $ret;
    }
}
