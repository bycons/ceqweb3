<?php

namespace App\Entities\Fornecedores;

use CodeIgniter\Entity\Entity;
use App\Libraries\MyCampo;
use App\Controllers\Fornecedores\NotifEvento;

/**
 * T43 — linha de anexo das abas Providências/Parecer Final
 * (oco_notif_evento_anexo, prefixo nva_). RN03.16/RN03.20.
 *
 * Mesmo padrão de EntOcoNotifEventoAcao: a Entity monta os campos (MyCampo),
 * a view (partials/pw_anexos_notif.php) só renderiza o layout.
 *
 * Uma linha existe em dois "formatos", conforme nva_id vier preenchido ou não:
 *  - já persistida: exibição (ícone + link do arquivo) + exclusão imediata
 *    (deleteAnexo(), via nva_id real);
 *  - em branco (criação, ou "+ Adicionar Anexo"): input de upload + exclusão
 *    de linha ainda não salva (exclui_campo(), só front-end).
 */
class EntOcoNotifEventoAnexo extends Entity
{
    protected $attributes = [
        'nva_id'            => null,
        'nev_id'            => null,
        'nva_origem'        => null,
        'nva_arquivo'       => null,
        'nva_nome_original' => null,
    ];

    protected $casts = [
        'nva_id' => 'integer',
        'nev_id' => 'integer',
    ];

    public array $campos = [];

    public function __construct(?array $data, string $sufixo, int $pos)
    {
        parent::__construct($data);
        $this->campos = $this->defCampos($this, $sufixo, $pos);
    }

    public function defCampos(self $dados, string $sufixo, int $pos): array
    {
        $ret = [];

        if ($dados->nva_id) {
            $urlArquivo = base_url('Showfile/' . rawurlencode($dados->nva_arquivo));
            $extensao   = strtolower(pathinfo($dados->nva_arquivo, PATHINFO_EXTENSION));
            $srcIcone   = in_array($extensao, ['png', 'jpg', 'jpeg'], true)
                ? $urlArquivo
                : '/assets/uploads/tipo_arquivo/pdf.png';

            $ret['exibicao'] = "<img src='" . $srcIcone . "' style='height:20px' class='me-2'>"
                . "<a href='" . $urlArquivo . "' target='_blank'>" . esc($dados->nva_nome_original) . '</a>';

            $id        = new MyCampo();
            $id->nome  = $id->id = "nva_id_{$sufixo}[{$pos}]";
            $id->valor = $dados->nva_id;
            $ret['nva_id'] = $id->crOculto();

            $del           = new MyCampo();
            $del->nome     = $del->id = "bt_delanexoex_{$sufixo}[{$pos}]";
            $del->i_cone   = "<i class='far fa-trash-alt'></i>";
            $del->classep  = 'btn-outline-danger btn-sm';
            $del->place    = 'Excluir Anexo';
            $del->funcChan = "excluiAnexoExistente(this, {$dados->nva_id}, 'anexo_{$sufixo}')";
            $ret['bt_del'] = $del->crBotao();

            return $ret;
        }

        $arq          = new MyCampo();
        $arq->objeto  = 'file';
        $arq->nome    = $arq->id = "nva_arquivo_{$sufixo}[{$pos}]";
        $arq->label   = 'Anexo';
        $arq->size    = 200;
        $arq->selecionado = '/assets/uploads/tipo_arquivo/vazio.png';
        $arq->funcChan = "validarArquivoPorAccept(this);readURL(this, 'img_{$arq->id}', {$arq->size}, {$arq->size})";
        $arq->setTipoArq('.' . implode(',.', NotifEvento::ANEXO_EXTENSOES_PERMITIDAS));
        $ret['arquivo'] = $arq->crArquivo();

        $del           = new MyCampo();
        $del->nome     = $del->id = "bt_delanexo_{$sufixo}[{$pos}]";
        $del->i_cone   = "<i class='far fa-trash-alt'></i>";
        $del->classep  = 'btn-outline-danger btn-sm bt-exclui';
        $del->place    = 'Excluir Anexo';
        $del->attrdata = ['data-index' => $pos];
        $del->funcChan = "exclui_campo('anexo_{$sufixo}', this)";
        $ret['bt_del'] = $del->crBotao();

        return $ret;
    }
}
