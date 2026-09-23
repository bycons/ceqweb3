<?php

namespace App\Entities\Config;

use App\Traits\HasTela;
use App\Traits\HasModulo;
use App\Libraries\MyCampo;
use App\Models\Config\ConfigDicDadosModel;
use App\Models\Config\ConfigModuloModel;
use CodeIgniter\Entity\Entity;

class EntCfgRelatorios extends Entity
{
    use HasModulo, HasTela;

    protected $attributes = [
        'rel_id'                     => null,
        'rel_nome'                   => null,
        'mod_id'                     => null,
        'tel_id'                     => null,
        'rel_titulo'                 => null,
        // *claude* só usada quando rel_tipo_saida=DOCUMENTO — tabela de
        // origem da coluna escolhida como Título. NULL/sem uso no Tabular.
        'rel_titulo_tabela'          => null,
        // *claude* TABULAR (comportamento original) | DOCUMENTO (cabeçalho +
        // tabela repetível + textos livres) — default retrocompatível.
        'rel_tipo_saida'             => 'TABULAR',
        'rel_tabela_base'            => null,
        // *claude* só usados quando rel_tipo_saida=DOCUMENTO
        'rel_tabela_detalhe'         => null,
        'rel_detalhe_campo_vinculo'  => null,
        'rel_formato'                => 'P',
        'rel_tamanho_fonte'          => 10,
        'rel_chars_por_linha'        => 0,
        'rel_totalizar_registros'    => 0,
        'rel_sql_gerado'             => null,
        'rel_ativo'                  => 1,
        'rel_criado_por'             => null,
        'rel_criado_em'              => null,
        'rel_atualizado_em'          => null,
    ];

    protected $dates = ['rel_criado_em', 'rel_atualizado_em'];

    public array $campos     = [];

    public function __construct(?array $data = null, bool $show = false)
    {
        parent::__construct($data);
        $this->campos = $this->defCampos($show);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Campos do cabeçalho (Aba Dados Gerais)
    // ─────────────────────────────────────────────────────────────────────────

    public function defCampos(bool $show = false): array
    {
        $dados = $this->toArray();
        $ret   = [];

        $ret['rel_id'] = (new MyCampo('cfg_relatorios', 'rel_id'))
            ->setValor($dados['rel_id'] ?? '')
            ->crOculto();

        $ret['rel_nome'] = (new MyCampo('cfg_relatorios', 'rel_nome'))
            ->setValor($dados['rel_nome'] ?? '')
            ->setObrigatorio()
            ->setMinLength(5)
            ->setMaxLength(50)
            ->setDispForm('col-6')
            ->setLeitura($show)
            ->crInput();

        // *claude* rel_tipo_saida define quais abas aparecem (Filtros/Colunas
        // no TABULAR x Cabeçalho/Tabela/Textos Livres no DOCUMENTO) —
        // ramificação feita em CfgRelatorio::add()/edit(), server-side, por
        // isso o tipo só é editável na CRIAÇÃO do relatório (rel_id vazio);
        // depois de salvo uma vez, fica travado (setLeitura) para não deixar
        // a tela com abas de um tipo e dados gravados de outro.
        $tipoTravado = !empty($dados['rel_id']);

        $radioTipoSaida = (new MyCampo('cfg_relatorios', 'rel_tipo_saida'))
            ->setLabel('Tipo de Saída')
            ->setValor($dados['rel_tipo_saida'] ?? 'TABULAR')
            ->setSelecionado($dados['rel_tipo_saida'] ?? 'TABULAR')
            ->setOpcoes(['TABULAR' => 'Tabular (lista)', 'DOCUMENTO' => 'Documento '])
            ->setDispForm('col-6')
            ->setLeitura($show || $tipoTravado)
            ->cr2opcoes();

        // *claude* setLeitura(true) marca os radios como disabled (ver
        // MyCampo::propriedades()) — campo disabled NÃO é enviado no POST pelo
        // navegador. Sem esse companheiro oculto, toda EDIÇÃO de um relatório
        // já salvo perderia rel_tipo_saida no submit e CfgRelatorio::store()
        // cairia sempre no branch TABULAR por padrão, corrompendo relatórios
        // DOCUMENTO já configurados.
        if ($tipoTravado && !$show) {
            $radioTipoSaida = (new MyCampo('cfg_relatorios', 'rel_tipo_saida'))
                ->setValor($dados['rel_tipo_saida'] ?? 'TABULAR')
                ->crOculto()
                . $radioTipoSaida;
        }

        $ret['rel_tipo_saida'] = $radioTipoSaida;

        $config = ['Leitura' => $show, 'Largura' => 50, 'Dispform' => 'col-6'];

        $ret['mod_id'] = criaSelectRelativo(
            'cfg_modulo',
            'mod_id',
            'mod_nome',
            $dados['mod_id'] ?? '',
            1,
            'cfg_relatorios',
            [],
            $config
        );

        $configTela             = $config;
        // *claude* regra de negócio nova (DOCUMENTO): a Tela passa a ser
        // obrigatória — ela "ancora" rel_tabela_base (regra 2, validada em
        // CfgRelatorio::_validarRegrasDocumento()). No Tabular continua
        // opcional, igual sempre foi.
        $configTela['Obrigatorio'] = ($dados['rel_tipo_saida'] ?? 'TABULAR') === 'DOCUMENTO';
        $configTela['Pai']      = 'mod_id';
        $configTela['Urlbusca'] = base_url('buscas/busca_tela_modulo');

        $ret['tel_id'] = criaSelectRelativo(
            'cfg_tela',
            'tel_id',
            'tel_nome',
            $dados['tel_id'] ?? '',
            2,
            'cfg_relatorios',
            [],
            $configTela
        );

        // *claude* Regra nova (byarq/usuário): no DOCUMENTO, rel_titulo deixa
        // de ser texto livre — é um campo selecionável igual aos de
        // Cabeçalho (rcc_campo), podendo vir de rel_tabela_base OU
        // rel_tabela_detalhe. No TABULAR continua EXATAMENTE como sempre foi
        // (texto livre, crInput()).
        if (($dados['rel_tipo_saida'] ?? 'TABULAR') === 'DOCUMENTO') {
            // crSelect() puro, SEM crDepende()/setPai() — mesmo motivo de
            // rcc_campo/rct_campo/rtx_campo: depende de DUAS tabelas ao mesmo
            // tempo, população via JS customizado (atualizaCamposDocumento(),
            // my_relatorio.js), não pelo mecanismo genérico de 1 campo pai.
            // Opções vazias aqui — só a opção pré-selecionada (vinda do
            // banco) é semeada, no mesmo formato composto "tabela|campo|
            // tamanho|tipo" usado pelo resto do picker do gerador (tamanho/
            // tipo não são usados pelo Título, ficam zerados/vazios só pra
            // reaproveitar o mesmo parsing já existente no JS).
            $opcaoTitulo = [];
            if (!empty($dados['rel_titulo'])) {
                $selecionadoTitulo = ($dados['rel_titulo_tabela'] ?? '') . '|' . $dados['rel_titulo'] . '|0|';
                $opcaoTitulo[$selecionadoTitulo] = '[' . ($dados['rel_titulo_tabela'] ?? '') . '] ' . ucwords(str_replace('_', ' ', $dados['rel_titulo']));
            }

            $ret['rel_titulo'] = (new MyCampo('cfg_relatorios', 'rel_titulo'))
                ->setValor($opcaoTitulo ? array_key_first($opcaoTitulo) : '')
                ->setSelecionado($opcaoTitulo ? array_key_first($opcaoTitulo) : '')
                ->setOpcoes($opcaoTitulo)
                ->setObrigatorio()
                ->setDispForm('col-8')
                ->setLeitura($show)
                ->crSelect();
        } else {
            $ret['rel_titulo'] = (new MyCampo('cfg_relatorios', 'rel_titulo'))
                ->setValor($dados['rel_titulo'] ?? '')
                ->setObrigatorio()
                ->setDispForm('col-8')
                ->setLeitura($show)
                ->crInput();
        }

        $opTabelas = [];
        if (!empty($dados['mod_id'])) {
            $mod = (new ConfigModuloModel())->find((int) $dados['mod_id']);
            if ($mod && !empty($mod->mod_dbgroup)) {
                foreach ((new ConfigDicDadosModel())->getTabelasPorDbGroup($mod->mod_dbgroup) as $t) {
                    $opTabelas[$t['table_name']] = $t['table_comment'];
                }
            }
        }

        // *claude* Regra A (byarq/usuário): rel_tabela_base do DOCUMENTO não
        // pode ser VIEW — só tabela física. O Tabular (e rel_tabela_detalhe,
        // mais abaixo) continua podendo listar views, comportamento idêntico
        // ao de sempre. ?apenas_tabela=1 é lido por Buscas::busca_tabelas_modulo().
        $urlTabelaBase = base_url('buscas/busca_tabelas_modulo');
        if (($dados['rel_tipo_saida'] ?? 'TABULAR') === 'DOCUMENTO') {
            $urlTabelaBase .= '?apenas_tabela=1';
        }

        $ret['rel_tabela_base'] = (new MyCampo('cfg_relatorios', 'rel_tabela_base'))
            ->setValor($dados['rel_tabela_base'] ?? '')
            ->setSelecionado([$dados['rel_tabela_base'] ?? ''])
            ->setOpcoes($opTabelas)
            ->setObrigatorio()
            ->setDispForm('col-6')
            ->setLeitura($show)
            ->setPai('mod_id')
            ->setUrlbusca($urlTabelaBase)
            ->crDepende();

        // *claude* só usados quando rel_tipo_saida=DOCUMENTO — tabela da grade
        // repetível (aba "Tabela") e a coluna dela que vincula com :id_registro.
        // Mesmas opções de tabela de rel_tabela_base (mesmo mod_dbgroup) e mesmo
        // endpoint AJAX (busca_tabelas_modulo) — só troca o campo pai no
        // data-pai, igual ao restante do picker tabela|campo do gerador.
        $ret['rel_tabela_detalhe'] = (new MyCampo('cfg_relatorios', 'rel_tabela_detalhe'))
            ->setLabel('Tabela de Detalhe (grade)')
            ->setValor($dados['rel_tabela_detalhe'] ?? '')
            ->setSelecionado([$dados['rel_tabela_detalhe'] ?? ''])
            ->setOpcoes($opTabelas)
            ->setDispForm('col-6')
            ->setLeitura($show)
            ->setPai('mod_id')
            ->setUrlbusca(base_url('buscas/busca_tabelas_modulo'))
            ->crDepende();

        // *claude* Regra de negócio (byarq/usuário): rel_detalhe_campo_vinculo
        // deixou de ser escolha livre do admin — é SEMPRE a FK de
        // rel_tabela_detalhe que aponta pra PK de rel_tabela_base, calculada
        // automaticamente no servidor (ver ConfigDicDadosModel::
        // buscarCampoVinculo(), CfgRelatorio::_storeDocumento()). O campo
        // visível aqui (rel_detalhe_campo_vinculo_display) é só informativo,
        // somente leitura; quem viaja no POST com o name real
        // (rel_detalhe_campo_vinculo) é o oculto abaixo, atualizado via AJAX
        // em my_relatorio.js (Buscas::busca_campo_vinculo_rel()) só pra
        // alimentar o preview visualmente — o store() real recalcula do zero
        // a partir do banco e ignora o que vier daqui, então não há risco de
        // inconsistência mesmo com o JS desatualizado.
        $campoVinculoAtual = $dados['rel_detalhe_campo_vinculo'] ?? '';

        $campoVinculoOculto = (new MyCampo('cfg_relatorios', 'rel_detalhe_campo_vinculo'))
            ->setValor($campoVinculoAtual)
            ->crOculto();

        $campoVinculoDisplay           = new MyCampo();
        $campoVinculoDisplay->nome     = 'rel_detalhe_campo_vinculo_display';
        $campoVinculoDisplay->id       = 'rel_detalhe_campo_vinculo_display';
        $campoVinculoDisplay->label    = 'Coluna de Vínculo (calculada automaticamente)';
        $campoVinculoDisplay->hint     = 'FK de rel_tabela_detalhe que aponta pra PK de rel_tabela_base — calculada automaticamente ao escolher as tabelas, não é uma escolha manual.';
        $campoVinculoDisplay->tipo     = 'text';
        $campoVinculoDisplay->objeto   = 'input';
        $campoVinculoDisplay->valor    = $campoVinculoAtual !== '' ? $campoVinculoAtual : '(selecione a Tabela Cabeçalho e a Tabela Detalhe)';
        $campoVinculoDisplay->largura  = 40;
        $campoVinculoDisplay->dispForm = 'col-5';
        $campoVinculoDisplay->leitura  = true;

        $ret['rel_detalhe_campo_vinculo'] = $campoVinculoOculto . $campoVinculoDisplay->crInput();

        // *claude* campo visual (não grava — sem coluna correspondente em
        // cfg_relatorios), só pra alimentar o preview do Documento
        // (CfgRelatorio::previewDocumento()) com dados reais de um registro já
        // existente; vazio = preview mostra só a estrutura com valores de exemplo.
        $campoIdTeste            = new MyCampo();
        $campoIdTeste->nome      = 'id_teste_documento';
        $campoIdTeste->id        = 'id_teste_documento';
        $campoIdTeste->label     = 'ID de Teste (preview)';
        $campoIdTeste->hint      = 'ID de um registro já existente na tabela base, só para visualizar o preview com dados reais.';
        $campoIdTeste->tipo      = 'number';
        $campoIdTeste->objeto    = 'input';
        $campoIdTeste->valor     = '';
        $campoIdTeste->largura   = 15;
        $campoIdTeste->dispForm  = 'col-2';
        $ret['id_teste_documento'] = $campoIdTeste->crInput();

        $ret['rel_formato'] = (new MyCampo('cfg_relatorios', 'rel_formato'))
            ->setValor($dados['rel_formato'] ?? 'P')
            ->setSelecionado($dados['rel_formato'] ?? 'P')
            ->setOpcoes(['P' => 'Retrato', 'L' => 'Paisagem'])
            ->setDispForm('col-3')
            ->setLeitura($show)
            ->cr2opcoes();

        $opFonte = [];
        // Fontes de 6 a 16pt — alterado para permitir fontes menores e maiores
        for ($i = 6; $i <= 16; $i++) {
            $opFonte[$i] = $i . ' pt';
        }

        $ret['rel_tamanho_fonte'] = (new MyCampo('cfg_relatorios', 'rel_tamanho_fonte'))
            ->setValor($dados['rel_tamanho_fonte'] ?? 10)
            ->setSelecionado($dados['rel_tamanho_fonte'] ?? 10)
            ->setOpcoes($opFonte)
            ->setDispForm('col-2')
            ->setLargura(20)
            ->setLeitura($show)
            ->crSelect();

        $ret['rel_totalizar_registros'] = (new MyCampo('cfg_relatorios', 'rel_totalizar_registros'))
            ->setValor($dados['rel_totalizar_registros'] ?? 0)
            ->setSelecionado($dados['rel_totalizar_registros'] ?? 0)
            ->setOpcoes([0 => 'Não', 1 => 'Sim'])
            ->setDispForm('col-2')
            ->setLeitura($show)
            ->cr2opcoes();

        // Campo visual (não grava) — mostra chars por linha conforme orientação e fonte
        $charsCalc = 0;
        $fmt = $dados['rel_formato'] ?? 'P';
        $fnt = (int) ($dados['rel_tamanho_fonte'] ?? 10);
        if ($fnt > 0) {
            $largMm   = ($fmt === 'L') ? 277 : 190;
            $charsCalc = (int) floor($largMm / ($fnt * 0.353 * 0.45));
        }
        $campoChars = new MyCampo();
        $campoChars->nome     = 'rel_chars_display';
        $campoChars->id       = 'rel_chars_display';
        $campoChars->label    = 'Caracteres por linha';
        $campoChars->tipo     = 'text';
        $campoChars->objeto   = 'input';
        $campoChars->valor    = $charsCalc;
        $campoChars->largura  = 12;
        $campoChars->dispForm = 'col-2';
        $campoChars->leitura  = true;
        $ret['rel_chars_display'] = $campoChars->crInput();

        $ret['prf_id'] = criaSelectRelativo(
            'cfg_perfil',
            'prf_id',
            'prf_nome',
            $dados['prf_id'] ?? '',
            3,
            'cfg_rel_permissao',
            [],
            $config
        );

        return $ret;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Campos das abas Filtros e Colunas — delegados para as entities corretas
    // ─────────────────────────────────────────────────────────────────────────

    public function defCamposFiltro($dados = false, bool $show = false, int $pos = 0, array $opcoesCampo = []): array
    {
        return EntCfgRelFiltros::defCampos($dados, $show, $pos, $opcoesCampo);
    }

    public function defCamposColunas($dados = false, bool $show = false, int $pos = 0): array
    {
        return EntCfgRelColunas::defCampos($dados, $show, $pos);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────

    public function getFormatoLabel(): string
    {
        return $this->rel_formato === 'L' ? 'Paisagem' : 'Retrato';
    }

    public function temCharsCalculados(): bool
    {
        return (int) $this->rel_chars_por_linha > 0;
    }
}
