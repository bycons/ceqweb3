<?php

namespace App\Entities\Config;

use App\Libraries\MyCampo;
use CodeIgniter\Entity\Entity;

/**
 * Aba "Tabela" do relatório tipo DOCUMENTO — colunas da grade repetível (N
 * linhas). Cada coluna pode vir de rel_tabela_detalhe (valor varia por
 * linha, filtrado por rel_detalhe_campo_vinculo = :id_registro) OU de
 * rel_tabela_base (nesse caso o valor é único — vem do registro do
 * cabeçalho — e aparece REPETIDO em todas as linhas da grade; ver
 * CriamPdf2026::_buscarDadosDocumento()). Espelho de EntCfgRelColunas na
 * estrutura do picker (tabela|campo|tamanho|tipo), mas usando
 * Buscas::busca_campos_tabela_unica() em vez de busca_campos_colunas_rel() —
 * decisão do usuário: o Documento não faz join automático, então este
 * picker só lista colunas de rel_tabela_base/rel_tabela_detalhe (nunca de
 * tabelas relacionadas a elas). Se for preciso combinar mais tabelas, a
 * saída é criar uma VIEW no banco.
 */
class EntCfgRelColunasDoc extends Entity
{
    protected $attributes = [
        'rct_id'        => null,
        'rel_id'        => null,
        'rct_tabela'    => null,
        'rct_campo'     => null,
        'rct_label'     => null,
        'rct_tamanho'   => 0,
        'rct_tipo_dado' => '',
        'rct_largura'   => 0,
        'rct_ordem'     => 0,
    ];

    public static function defCampos($dados = false, bool $show = false, int $pos = 0): array
    {
        $ret = [];

        // Ocultos
        $ret['rct_id'] = (new MyCampo('cfg_rel_colunas_doc', 'rct_id'))
            ->setValor($dados['rct_id'] ?? '')
            ->setOrdem($pos)
            ->crOculto();

        $ret['rct_tabela'] = (new MyCampo('cfg_rel_colunas_doc', 'rct_tabela'))
            ->setValor($dados['rct_tabela'] ?? '')
            ->setOrdem($pos)
            ->crOculto();

        $ret['rct_tamanho'] = (new MyCampo('cfg_rel_colunas_doc', 'rct_tamanho'))
            ->setValor($dados['rct_tamanho'] ?? 0)
            ->setOrdem($pos)
            ->crOculto();

        $ret['rct_tipo_dado'] = (new MyCampo('cfg_rel_colunas_doc', 'rct_tipo_dado'))
            ->setValor($dados['rct_tipo_dado'] ?? '')
            ->setOrdem($pos)
            ->crOculto();

        $ret['rct_ordem'] = (new MyCampo('cfg_rel_colunas_doc', 'rct_ordem'))
            ->setValor($dados['rct_ordem'] ?? $pos)
            ->setOrdem($pos)
            ->crOculto();

        // Select de campo — pode ser de rel_tabela_detalhe OU rel_tabela_base
        // (decisão do usuário: Cabeçalho/Tabela/Textos Livres aceitam campos
        // das duas). NÃO reaproveita o endpoint do Tabular
        // (busca_campos_colunas_rel), que expande a busca pra tabelas
        // pai/filha/neta via FK — o Documento não faz join automático (se
        // precisar de mais tabelas, cria uma VIEW no banco). Usa
        // busca_campos_tabela_unica(), estritamente escopado às 2 tabelas
        // informadas (ver Buscas.php).
        //
        // *claude* crSelect() puro, NÃO crDepende() — mesmo motivo de
        // EntCfgRelCamposCab::rcc_campo (ver lá): não dá pra depender de
        // "2 tabelas pai" com o mecanismo genérico de 1 campo pai. População
        // via atualizaCamposDocumento() em my_relatorio.js.
        $opcaoCampo = [];
        if (!empty($dados['rct_campo'])) {
            $selecionado = ($dados['rct_tabela'] ?? '') . '|' . $dados['rct_campo'] . '|' . ($dados['rct_tamanho'] ?? 0) . '|' . ($dados['rct_tipo_dado'] ?? '');
            $opcaoCampo[$selecionado] = '[' . ($dados['rct_tabela'] ?? '') . '] ' . ucwords(str_replace('_', ' ', $dados['rct_campo']));
        }

        $ret['rct_campo'] = (new MyCampo('cfg_rel_colunas_doc', 'rct_campo'))
            ->setValor($opcaoCampo ? array_key_first($opcaoCampo) : '')
            ->setSelecionado($opcaoCampo ? array_key_first($opcaoCampo) : '')
            ->setOpcoes($opcaoCampo)
            ->setObrigatorio()
            ->setOrdem($pos)
            ->setDispForm('col-6 float-start')
            ->setLargura(40)
            ->setLeitura($show)
            ->crSelect();

        $ret['rct_label'] = (new MyCampo('cfg_rel_colunas_doc', 'rct_label'))
            ->setValor($dados['rct_label'] ?? '')
            ->setObrigatorio()
            ->setMinLength(3)
            ->setOrdem($pos)
            ->setDispForm('col-3 float-start')
            ->setLargura(30)
            ->setLeitura($show)
            ->crInput();

        $ret['rct_largura'] = (new MyCampo('cfg_rel_colunas_doc', 'rct_largura'))
            ->setValor($dados['rct_largura'] ?? $dados['rct_tamanho'] ?? 0)
            ->setOrdem($pos)
            ->setDispForm('col-2 float-start')
            ->setLargura(10)
            ->setLeitura($show)
            ->crInput();

        // Botões
        $atrib = ['data-index' => $pos];

        $add           = new MyCampo();
        $add->attrdata = $atrib;
        $add->tipo     = 'button';
        $add->dispForm = '1col';
        $add->nome     = "bt_add_coldoc[{$pos}]";
        $add->id       = "bt_add_coldoc[{$pos}]";
        $add->i_cone   = "<i class='fas fa-plus'></i>";
        $add->place    = 'Adicionar Coluna';
        $add->classep  = 'btn-outline-success btn-sm bt-repete';
        // *claude* objdest = 'tabela' — precisa bater com url_amigavel('Tabela')
        // (o slug usado por vw_edicao_relatorio.php pro id="rep_tabela"/classe
        // "table-tabela" da linha), não com o nome da tabela cfg_rel_colunas_doc.
        $add->funcChan = "addCampo('" . base_url('CfgRelatorio/addColunaDoc/') . "','tabela',this)";
        $ret['bt_add'] = $add->crBotao();

        $del           = new MyCampo();
        $del->attrdata = $atrib;
        $del->tipo     = 'button';
        $del->dispForm = '1col';
        $del->nome     = "bt_del_coldoc[{$pos}]";
        $del->id       = "bt_del_coldoc[{$pos}]";
        $del->i_cone   = "<i class='fas fa-trash'></i>";
        $del->classep  = 'btn-outline-danger btn-sm bt-exclui';
        $del->funcChan = "exclui_campo('tabela',this)";
        $del->place    = 'Excluir Coluna';
        $ret['bt_del'] = $del->crBotao();

        return $ret;
    }
}
