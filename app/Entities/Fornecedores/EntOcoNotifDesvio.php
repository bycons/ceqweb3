<?php

namespace App\Entities\Fornecedores;

use CodeIgniter\Entity\Entity;
use App\Libraries\MyCampo;

/**
 * T42 — Desvio de Qualidade (oco_notif_desvio, prefixo ndv_).
 *
 * Segue o mesmo padrão de App\Entities\Ocorrencia\EntOcoOcorrencia: monta o
 * array `campos` (MyCampo já renderizado) em `defCampos()`, consumido pelo
 * Controller (add/edit/show) e pela view genérica `vw_edicao`.
 */
class EntOcoNotifDesvio extends Entity
{
    protected $attributes = [
        'ndv_id'       => null,
        'oco_id'       => null,
        'ndv_local'    => null,
        'ndv_descreva' => null,
        'stt_id'       => null,
        'usu_criou'    => null,
    ];

    protected $casts = [
        'ndv_id' => 'integer',
        'oco_id' => 'integer',
        'stt_id' => 'integer',
    ];

    public array $campos = [];

    public function __construct(?array $data = null, bool $show = false)
    {
        parent::__construct($data);
        $this->campos = $this->defCampos($show);
    }

    /**
     * @param bool $show Quando true, todos os campos ficam somente-leitura
     *                    (tela de visualização) — os campos vindos de T11
     *                    são SEMPRE somente-leitura, independente de $show
     *                    (RN03.1 a RN03.13).
     */
    public function defCampos(bool $show = false): array
    {
        $dados = $this->toArray();
        $ret   = [];

        $ndvId        = new MyCampo('oco_notif_desvio', 'ndv_id');
        $ndvId->valor = $dados['ndv_id'] ?? '';
        $ret['ndv_id'] = $ndvId->crOculto();

        $ocoId        = new MyCampo('oco_notif_desvio', 'oco_id');
        $ocoId->valor = $dados['oco_id'] ?? '';
        $ret['oco_id'] = $ocoId->crOculto();

        // RN03.1 — Desvio n°, exibição (sequencial, não editável)
        $numero              = new MyCampo();
        $numero->valor       = isset($dados['ndv_id']) && $dados['ndv_id']
            ? str_pad($dados['ndv_id'], 6, '0', STR_PAD_LEFT)
            : '(gerado ao salvar)';
        $numero->label       = 'Desvio n°';
        $numero->leitura     = true;
        $numero->dispForm    = 'col-4';
        $ret['ndv_numero'] = $numero->crShow();

        // RN03.2 / RN03.3 — Tipo / Subtipo (via T11, somente leitura)
        $tipo              = new MyCampo();
        $tipo->valor       = $dados['tpo_nome'] ?? '';
        $tipo->label       = 'Tipo de Ocorrência';
        $tipo->leitura     = true;
        $tipo->dispForm    = 'col-4';
        $ret['tpo_nome'] = $tipo->crInput();

        $subtipo              = new MyCampo();
        $subtipo->valor       = $dados['sut_nome'] ?? '';
        $subtipo->label       = 'Subtipo de Ocorrência';
        $subtipo->leitura     = true;
        $subtipo->dispForm    = 'col-4';
        $ret['sut_nome'] = $subtipo->crInput();

        // RN03.4 — Data da Ocorrência (via T11)
        $data              = new MyCampo();
        $data->valor       = isset($dados['oco_data']) ? data_br($dados['oco_data']) : '';
        $data->label       = 'Data da Ocorrência';
        $data->leitura     = true;
        $data->dispForm    = 'col-4';
        $ret['oco_data_show'] = $data->crInput();

        // RN03.5 — Usuário (via T11)
        $usuario              = new MyCampo();
        $usuario->valor       = $dados['usu_nome'] ?? '';
        $usuario->label       = 'Usuário';
        $usuario->leitura     = true;
        $usuario->dispForm    = 'col-4';
        $ret['usu_nome'] = $usuario->crInput();

        // RN03.6 — Tela Geradora (via T11, não obrigatório)
        $telaOrigem              = new MyCampo();
        $telaOrigem->valor       = $dados['oco_tel_nome'] ?? '';
        $telaOrigem->label       = 'Tela Geradora';
        $telaOrigem->leitura     = true;
        $telaOrigem->obrigatorio = false;
        $telaOrigem->dispForm    = 'col-4';
        $ret['tel_nome'] = $telaOrigem->crInput();

        // RN03.7 — Local (ÚNICO campo texto livre editável do cabeçalho)
        $local              = new MyCampo('oco_notif_desvio', 'ndv_local');
        $local->valor       = $dados['ndv_local'] ?? '';
        $local->obrigatorio = true;
        $local->leitura     = $show;
        $local->minimo      = 5;
        $local->maximo      = 50;
        $local->dispForm    = 'col-6';
        $ret['ndv_local'] = $local->crInput();

        // RN03.8 a RN03.13 — grid do produto (via T11, somente leitura)
        $codErp              = new MyCampo();
        $codErp->valor       = $dados['pro_codpro'] ?? '';
        $codErp->label       = 'Código ERP';
        $codErp->leitura     = true;
        $codErp->dispForm    = 'col-4';
        $ret['pro_codpro'] = $codErp->crInput();

        $descricao              = new MyCampo();
        $descricao->valor       = $dados['pro_despro'] ?? '';
        $descricao->label       = 'Descrição do Produto';
        $descricao->leitura     = true;
        $descricao->linhas      = 2;
        $descricao->colunas     = 50;
        $descricao->dispForm    = 'col-8';
        $ret['pro_despro'] = $descricao->crTexto();

        $fabricante              = new MyCampo();
        $fabricante->valor       = $dados['fab_apeFab'] ?? '';
        $fabricante->label       = 'Fabricante';
        $fabricante->leitura     = true;
        $fabricante->dispForm    = 'col-4';
        $ret['fab_apeFab'] = $fabricante->crInput();

        $lote              = new MyCampo();
        $lote->valor       = $dados['lot_lote'] ?? '';
        $lote->label       = 'Lote';
        $lote->leitura     = true;
        $lote->dispForm    = 'col-4';
        $ret['lot_lote'] = $lote->crInput();

        $validade              = new MyCampo();
        $validade->valor       = isset($dados['lot_validade']) ? data_br($dados['lot_validade']) : '';
        $validade->label       = 'Validade';
        $validade->leitura     = true;
        $validade->dispForm    = 'col-4';
        $ret['lot_validade'] = $validade->crInput();

        $quantidade              = new MyCampo();
        $quantidade->valor       = $dados['oco_qtd'] ?? '';
        $quantidade->label       = 'Quantidade';
        $quantidade->leitura     = true;
        $quantidade->dispForm    = 'col-4';
        $ret['oco_qtd'] = $quantidade->crInput();

        // RN03.14 — Descreva (editável)
        $descreva              = new MyCampo('oco_notif_desvio', 'ndv_descreva');
        $descreva->valor       = $dados['ndv_descreva'] ?? '';
        $descreva->obrigatorio = true;
        $descreva->leitura     = $show;
        $descreva->minimo      = 5;
        $descreva->maximo      = 200;
        $descreva->linhas      = 3;
        $descreva->colunas     = 60;
        $descreva->dispForm    = 'col-6';
        $ret['ndv_descreva'] = $descreva->crTexto();

        return $ret;
    }
}
