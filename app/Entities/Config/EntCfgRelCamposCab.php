<?php

namespace App\Entities\Config;

use App\Libraries\MyCampo;
use CodeIgniter\Entity\Entity;

/**
 * Aba "Cabeçalho" do relatório tipo DOCUMENTO — campos label:valor, sempre
 * "1 valor só" (não uma grade). Cada campo pode vir de rel_tabela_base
 * (registro único, filtrado por :id_registro) OU de rel_tabela_detalhe
 * (nesse caso pega a primeira linha da grade — ver CriamPdf2026::
 * _buscarDadosDocumento()). Espelho de EntCfgRelColunas (mesmo picker
 * tabela|campo|tamanho|tipo), sem alinhamento/totalizar (não é coluna
 * tabular) e com `rcc_largura_col` no lugar de `rco_largura` (largura em
 * colunas Bootstrap do grid do PDF/preview, não em caracteres).
 */
class EntCfgRelCamposCab extends Entity
{
    protected $attributes = [
        'rcc_id'          => null,
        'rel_id'          => null,
        'rcc_tabela'      => null,
        'rcc_campo'       => null,
        'rcc_label'       => null,
        'rcc_tamanho'     => 0,
        'rcc_tipo_dado'   => '',
        'rcc_largura_col' => 'col-3',
        'rcc_ordem'       => 0,
    ];

    public static function defCampos($dados = false, bool $show = false, int $pos = 0): array
    {
        $ret = [];

        // Ocultos
        $ret['rcc_id'] = (new MyCampo('cfg_rel_camposcab', 'rcc_id'))
            ->setValor($dados['rcc_id'] ?? '')
            ->setOrdem($pos)
            ->crOculto();

        $ret['rcc_tabela'] = (new MyCampo('cfg_rel_camposcab', 'rcc_tabela'))
            ->setValor($dados['rcc_tabela'] ?? '')
            ->setOrdem($pos)
            ->crOculto();

        $ret['rcc_tamanho'] = (new MyCampo('cfg_rel_camposcab', 'rcc_tamanho'))
            ->setValor($dados['rcc_tamanho'] ?? 0)
            ->setOrdem($pos)
            ->crOculto();

        $ret['rcc_tipo_dado'] = (new MyCampo('cfg_rel_camposcab', 'rcc_tipo_dado'))
            ->setValor($dados['rcc_tipo_dado'] ?? '')
            ->setOrdem($pos)
            ->crOculto();

        $ret['rcc_ordem'] = (new MyCampo('cfg_rel_camposcab', 'rcc_ordem'))
            ->setValor($dados['rcc_ordem'] ?? $pos)
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
        // *claude* crSelect() puro, NÃO crDepende() — o mecanismo genérico de
        // "1 campo pai" (setPai()/data-pai) não serve pra "depende de
        // rel_tabela_base E rel_tabela_detalhe ao mesmo tempo". A população
        // das opções é 100% via JS customizado (atualizaCamposDocumento() em
        // my_relatorio.js), mesmo estilo já usado por atualizaDependeDe()
        // (aba Filtros) — só a pré-seleção vinda do banco continua igual.
        $opcaoCampo = [];
        if (!empty($dados['rcc_campo'])) {
            $selecionado = ($dados['rcc_tabela'] ?? '') . '|' . $dados['rcc_campo'] . '|' . ($dados['rcc_tamanho'] ?? 0) . '|' . ($dados['rcc_tipo_dado'] ?? '');
            $opcaoCampo[$selecionado] = '[' . ($dados['rcc_tabela'] ?? '') . '] ' . ucwords(str_replace('_', ' ', $dados['rcc_campo']));
        }

        $ret['rcc_campo'] = (new MyCampo('cfg_rel_camposcab', 'rcc_campo'))
            ->setValor($opcaoCampo ? array_key_first($opcaoCampo) : '')
            ->setSelecionado($opcaoCampo ? array_key_first($opcaoCampo) : '')
            ->setOpcoes($opcaoCampo)
            ->setObrigatorio()
            ->setOrdem($pos)
            ->setDispForm('col-6 float-start')
            ->setLargura(40)
            ->setLeitura($show)
            ->crSelect();

        // Rótulo opcional (usuário, 2026-09-23) — vazio imprime só o valor.
        $ret['rcc_label'] = (new MyCampo('cfg_rel_camposcab', 'rcc_label'))
            ->setValor($dados['rcc_label'] ?? '')
            ->setOrdem($pos)
            ->setDispForm('col-4 float-start')
            ->setLargura(30)
            ->setLeitura($show)
            ->crInput();

        $ret['rcc_largura_col'] = (new MyCampo('cfg_rel_camposcab', 'rcc_largura_col'))
            ->setValor($dados['rcc_largura_col'] ?? 'col-3')
            ->setSelecionado($dados['rcc_largura_col'] ?? 'col-3')
            ->setOpcoes([
                'col-3'  => '1/4 da linha',
                'col-4'  => '1/3 da linha',
                'col-6'  => '1/2 da linha',
                'col-12' => 'Linha inteira',
            ])
            ->setOrdem($pos)
            ->setDispForm('col-2 float-start')
            ->setLeitura($show)
            ->crSelect();

        // Botões
        $atrib = ['data-index' => $pos];

        $add           = new MyCampo();
        $add->attrdata = $atrib;
        $add->tipo     = 'button';
        $add->dispForm = '1col';
        $add->nome     = "bt_add_cab[{$pos}]";
        $add->id       = "bt_add_cab[{$pos}]";
        $add->i_cone   = "<i class='fas fa-plus'></i>";
        $add->place    = 'Adicionar Campo';
        $add->classep  = 'btn-outline-success btn-sm bt-repete';
        // *claude* objdest = 'cabecalho' — precisa bater com url_amigavel('Cabeçalho')
        // (o slug usado por vw_edicao_relatorio.php pro id="rep_cabecalho"/classe
        // "table-cabecalho" da linha), não com o nome da tabela cfg_rel_camposcab.
        $add->funcChan = "addCampo('" . base_url('CfgRelatorio/addCampoCab/') . "','cabecalho',this)";
        $ret['bt_add'] = $add->crBotao();

        $del           = new MyCampo();
        $del->attrdata = $atrib;
        $del->tipo     = 'button';
        $del->dispForm = '1col';
        $del->nome     = "bt_del_cab[{$pos}]";
        $del->id       = "bt_del_cab[{$pos}]";
        $del->i_cone   = "<i class='fas fa-trash'></i>";
        $del->classep  = 'btn-outline-danger btn-sm bt-exclui';
        $del->funcChan = "exclui_campo('cabecalho',this)";
        $del->place    = 'Excluir Campo';
        $ret['bt_del'] = $del->crBotao();

        return $ret;
    }
}
