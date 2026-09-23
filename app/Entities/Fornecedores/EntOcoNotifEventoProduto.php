<?php

namespace App\Entities\Fornecedores;

use CodeIgniter\Entity\Entity;
use App\Libraries\MyCampo;

/**
 * T43 — aba "Dados Gerais", grid "Dados do Produto" (oco_notif_evento_produto,
 * prefixo nvp_). Uma linha por produto/lote selecionado na tela intermediária
 * (RN03.1). Campos read-only vêm de vw_oco_notif_evento_produto_relac (JOIN
 * até T42/T11); único campo editável é nvp_defeito (RN03.9), pré-preenchido
 * com ndv_descreva de T42 mas alterável aqui.
 */
class EntOcoNotifEventoProduto extends Entity
{
    protected $attributes = [
        'nvp_id'      => null,
        'nev_id'      => null,
        'ndv_id'      => null,
        'nvp_defeito' => null,
    ];

    protected $casts = [
        'nvp_id' => 'integer',
        'nev_id' => 'integer',
        'ndv_id' => 'integer',
    ];

    public array $campos = [];

    public function __construct(?array $data = null, bool $show = false, int $ordem = 0)
    {
        parent::__construct($data);
        $this->campos = $this->defCampos($show, $ordem);
    }

    public function defCampos(bool $show = false, int $ordem = 0): array
    {
        $dados = $this->toArray();
        $ret   = [];

        $ndvId              = new MyCampo();
        $ndvId->nome        = $ndvId->id = "ndv_id[{$ordem}]";
        $ndvId->valor       = $dados['ndv_id'] ?? '';
        $ndvId->objeto      = 'oculto';
        $ret['ndv_id'] = $ndvId->crOculto();

        $nvpId              = new MyCampo();
        $nvpId->nome        = $nvpId->id = "nvp_id[{$ordem}]";
        $nvpId->valor       = $dados['nvp_id'] ?? '';
        $nvpId->objeto      = 'oculto';
        $ret['nvp_id'] = $nvpId->crOculto();

        $data              = new MyCampo();
        $data->valor       = isset($dados['oco_data']) ? data_br($dados['oco_data']) : '';
        $data->label       = 'Data';
        $data->leitura     = true;
        $data->dispForm    = 'col-2';
        $ret['oco_data'] = $data->crInput();

        $codErp              = new MyCampo();
        $codErp->valor       = $dados['pro_codpro'] ?? '';
        $codErp->label       = 'Código ERP';
        $codErp->leitura     = true;
        $codErp->dispForm    = 'col-2';
        $ret['pro_codpro'] = $codErp->crInput();

        $descricao              = new MyCampo();
        $descricao->valor       = $dados['pro_despro'] ?? '';
        $descricao->label       = 'Descrição';
        $descricao->leitura     = true;
        $descricao->dispForm    = 'col-4';
        $ret['pro_despro'] = $descricao->crInput();

        $fabricante              = new MyCampo();
        $fabricante->valor       = $dados['fab_apeFab'] ?? '';
        $fabricante->label       = 'Fabricante';
        $fabricante->leitura     = true;
        $fabricante->dispForm    = 'col-2';
        $ret['fab_apeFab'] = $fabricante->crInput();

        $lote              = new MyCampo();
        $lote->valor       = $dados['lot_lote'] ?? '';
        $lote->label       = 'Lote';
        $lote->leitura     = true;
        $lote->dispForm    = 'col-2';
        $ret['lot_lote'] = $lote->crInput();

        $validade              = new MyCampo();
        $validade->valor       = isset($dados['lot_validade']) ? data_br($dados['lot_validade']) : '';
        $validade->label       = 'Validade';
        $validade->leitura     = true;
        $validade->dispForm    = 'col-2';
        $ret['lot_validade'] = $validade->crInput();

        $quantidade              = new MyCampo();
        $quantidade->valor       = $dados['oco_qtd'] ?? '';
        $quantidade->label       = 'Quantidade';
        $quantidade->leitura     = true;
        $quantidade->dispForm    = 'col-2';
        $ret['oco_qtd'] = $quantidade->crInput();

        // RN03.9 — Defeito (editável), name/id em array (nvp_defeito[N])
        // para casar com a linha N da grid no POST do store().
        $defeito              = new MyCampo('oco_notif_evento_produto', 'nvp_defeito');
        $defeito->nome        = $defeito->id = "nvp_defeito[{$ordem}]";
        $defeito->valor       = $dados['nvp_defeito'] ?? '';
        $defeito->obrigatorio = true;
        $defeito->leitura     = $show;
        $defeito->linhas      = 3;
        $defeito->colunas      = 80;
        $defeito->minimo      = 5;
        $defeito->maximo      = 200;
        $defeito->dispForm    = 'col-12';
        $ret['nvp_defeito'] = $defeito->crInput();

        return $ret;
    }
}
