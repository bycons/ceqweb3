<?php

namespace App\Entities\Config;

use App\Libraries\MyCampo;
use CodeIgniter\Entity\Entity;

/**
 * Aba "Rodapé" (antiga "Textos Livres") do relatório tipo DOCUMENTO —
 * impressa DEPOIS da Tabela/detalhes, uma caixa por linha. Cada linha tem:
 *  - rtx_texto: texto digitado livremente na configuração (opcional);
 *  - rtx_campo: campo vinculado (opcional), "1 valor só" — pode vir de
 *    rel_tabela_base (registro único) OU de rel_tabela_detalhe (pega a
 *    primeira linha da grade — ver CriamPdf2026::_buscarDadosDocumento());
 *  - rtx_label: rótulo (opcional).
 * Ao menos texto OU campo precisa estar preenchido (linha vazia é descartada
 * em CfgRelatorio::_extrairTextosPost()).
 */
class EntCfgRelTextos extends Entity
{
    protected $attributes = [
        'rtx_id'     => null,
        'rel_id'     => null,
        'rtx_tabela' => null,
        'rtx_campo'  => null,
        'rtx_texto'  => null,
        'rtx_label'  => null,
        'rtx_ordem'  => 0,
    ];

    public static function defCampos($dados = false, bool $show = false, int $pos = 0): array
    {
        $ret = [];

        // Ocultos
        $ret['rtx_id'] = (new MyCampo('cfg_rel_textoslivres', 'rtx_id'))
            ->setValor($dados['rtx_id'] ?? '')
            ->setOrdem($pos)
            ->crOculto();

        $ret['rtx_tabela'] = (new MyCampo('cfg_rel_textoslivres', 'rtx_tabela'))
            ->setValor($dados['rtx_tabela'] ?? '')
            ->setOrdem($pos)
            ->crOculto();

        $ret['rtx_ordem'] = (new MyCampo('cfg_rel_textoslivres', 'rtx_ordem'))
            ->setValor($dados['rtx_ordem'] ?? $pos)
            ->setOrdem($pos)
            ->crOculto();

        // Select de campo — pode ser de rel_tabela_base OU rel_tabela_detalhe
        // (decisão do usuário: Cabeçalho/Tabela/Textos Livres aceitam campos
        // das duas). NÃO reaproveita o endpoint do Tabular
        // (busca_campos_colunas_rel), que expande a busca pra tabelas
        // pai/filha/neta via FK — o Documento não faz join automático (se
        // precisar de mais tabelas, cria uma VIEW no banco). Usa
        // busca_campos_tabela_unica(), estritamente escopado às 2 tabelas
        // informadas (ver Buscas.php).
        //
        // *claude* crSelect() puro, NÃO crDepende() — mesmo motivo de
        // EntCfgRelCamposCab::rcc_campo (ver lá). População via
        // atualizaCamposDocumento() em my_relatorio.js.
        $opcaoCampo = [];
        if (!empty($dados['rtx_campo'])) {
            $selecionado = ($dados['rtx_tabela'] ?? '') . '|' . $dados['rtx_campo'] . '|0|';
            $opcaoCampo[$selecionado] = '[' . ($dados['rtx_tabela'] ?? '') . '] ' . ucwords(str_replace('_', ' ', $dados['rtx_campo']));
        }

        // Campo vinculado é OPCIONAL no Rodapé — a opção vazia permite ficar
        // só com o texto digitado (my_relatorio.js mantém essa opção ao
        // repopular o select).
        $selecionado = $opcaoCampo ? array_key_first($opcaoCampo) : '';
        $opcaoCampo  = ['' => '(Nenhum — só texto)'] + $opcaoCampo;

        $ret['rtx_campo'] = (new MyCampo('cfg_rel_textoslivres', 'rtx_campo'))
            ->setValor($selecionado)
            ->setSelecionado($selecionado)
            ->setOpcoes($opcaoCampo)
            ->setOrdem($pos)
            ->setDispForm('col-4 float-start')
            ->setLargura(40)
            ->setLeitura($show)
            ->crSelect();

        $texto = (new MyCampo('cfg_rel_textoslivres', 'rtx_texto'))
            ->setValor($dados['rtx_texto'] ?? '')
            ->setOrdem($pos)
            ->setDispForm('col-5 float-start')
            ->setLeitura($show);
        $texto->linhas = 2;
        $ret['rtx_texto'] = $texto->crTexto();

        // Rótulo opcional (usuário, 2026-09-23).
        $ret['rtx_label'] = (new MyCampo('cfg_rel_textoslivres', 'rtx_label'))
            ->setValor($dados['rtx_label'] ?? '')
            ->setOrdem($pos)
            ->setDispForm('col-3 float-start')
            ->setLargura(30)
            ->setLeitura($show)
            ->crInput();

        // Botões
        $atrib = ['data-index' => $pos];

        $add           = new MyCampo();
        $add->attrdata = $atrib;
        $add->tipo     = 'button';
        $add->dispForm = '1col';
        $add->nome     = "bt_add_txt[{$pos}]";
        $add->id       = "bt_add_txt[{$pos}]";
        $add->i_cone   = "<i class='fas fa-plus'></i>";
        $add->place    = 'Adicionar Linha de Rodapé';
        $add->classep  = 'btn-outline-success btn-sm bt-repete';
        // *claude* objdest = 'rodape_doc' — precisa bater com o slug explícito
        // da aba em CfgRelatorio::_montaTelaDocumento() ($this->data['slugs'])
        // (id="rep_rodape_doc"/classe "table-rodape_doc" da linha). Não é
        // 'rodape' pra não colidir com o rodapé do layout (strut/vw_rodape.php).
        $add->funcChan = "addCampo('" . base_url('CfgRelatorio/addTextoLivre/') . "','rodape_doc',this)";
        $ret['bt_add'] = $add->crBotao();

        $del           = new MyCampo();
        $del->attrdata = $atrib;
        $del->tipo     = 'button';
        $del->dispForm = '1col';
        $del->nome     = "bt_del_txt[{$pos}]";
        $del->id       = "bt_del_txt[{$pos}]";
        $del->i_cone   = "<i class='fas fa-trash'></i>";
        $del->classep  = 'btn-outline-danger btn-sm bt-exclui';
        $del->funcChan = "exclui_campo('rodape_doc',this)";
        $del->place    = 'Excluir Linha de Rodapé';
        $ret['bt_del'] = $del->crBotao();

        return $ret;
    }
}
