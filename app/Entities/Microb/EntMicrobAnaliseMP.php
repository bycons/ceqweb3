<?php

namespace App\Entities\Microb;

use CodeIgniter\Entity\Entity;
use App\Libraries\MyCampo;
use App\Models\Microb\MicrobAnaliseModel;

class EntMicrobAnaliseMP extends Entity
{
    public array $campos = [];

    public function __construct(?array $data = null)
    {
        parent::__construct($data);
        $this->campos  = $this->defCampos();
    }

    public function defCampos(): array
    {
        $ret = [];

        // PRODUTO || Não usei o selectRelativo pois pega da View...
        $prod = new MyCampo();
        $prod->id       = 'codProdut';
        $prod->nome     = 'codPro';
        $prod->label    = 'Produto:';
        $prod->place    = 'Selecione o produto';
        $prod->opcoes   = array_column(model(MicrobAnaliseModel::class)->getAnaliseFiltro([], '', null, null, true),'pro_despro','pro_id');
        $prod->dispForm = 'col-3';
        $prod->largura  = 38;
        
        $ret['sal_prod'] = $prod->crMultiple();

        // LOTE
        $lote = new MyCampo();
        $lote->id     = 'codLot';
        $lote->label  = 'Lote:';
        $lote->place  = 'Digite o lote do produto';
        $lote->size   = 20;
        $lote->dispForm = 'col-3';

        $ret['sal_lote'] = $lote->crInput();

        // Botão Buscar único 
        $btLote = new MyCampo();
        $btLote->id       = 'btBuscarAnalises';
        $btLote->funcChan = 'buscaAnaliseMP()';
        $btLote->i_cone   = '<i class="fa-solid fa-magnifying-glass"></i> Buscar';
        $btLote->place    = 'Buscar';
        $btLote->classep  = 'btn-primary mt-3 px-4';

        $ret['sal_btlote'] = $btLote->crBotao();

        // DATA/PERÍODO
        $periodo = new MyCampo();
        $periodo->id     = 'perAnalise';
        $periodo->label  = 'Período da Análise';
        $periodo->place  = 'Selecione data/período da análise';
        $periodo->size   = 18;
        $periodo->dispForm = 'col-3';

        $ret['sal_data'] = $periodo->crDaterange();

        return $ret;
    }
}