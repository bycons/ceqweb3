<?php

namespace App\Controllers\Config;

use App\Controllers\BaseController;
use App\Entities\Config\EntCfgRelatorios;
use App\Entities\Config\EntCfgRelFiltros;
use App\Entities\Config\EntCfgRelColunas;
use App\Entities\Config\EntCfgRelCamposCab;
use App\Entities\Config\EntCfgRelColunasDoc;
use App\Entities\Config\EntCfgRelTextos;
use App\Models\CommonModel;
use App\Models\Config\ConfigDicDadosModel;
use App\Models\Config\ConfigRelatoriosModel;
use App\Models\Config\ConfigRelFiltrosModel;
use App\Models\Config\ConfigRelColunasModel;
use App\Models\Config\ConfigRelJoinsModel;
use App\Models\Config\ConfigRelCamposCabModel;
use App\Models\Config\ConfigRelColunasDocModel;
use App\Models\Config\ConfigRelTextosModel;
use App\Traits\ForeignKeyUsageChecker;


class CfgRelatorio extends BaseController
{
    use ForeignKeyUsageChecker;

    protected ConfigRelatoriosModel $relatorios;
    protected ConfigRelFiltrosModel $filtros;
    protected ConfigRelColunasModel $colunas;
    protected ConfigRelJoinsModel   $joins;
    // *claude* modelos das 3 abas exclusivas do tipo de saída DOCUMENTO
    // (Cabeçalho / Tabela / Textos Livres) — ver rel_tipo_saida.
    protected ConfigRelCamposCabModel  $camposCab;
    protected ConfigRelColunasDocModel $colunasDoc;
    protected ConfigRelTextosModel     $textosLivres;
    protected ConfigDicDadosModel   $dicionario;
    protected CommonModel           $common;
    protected array                 $data;

    public function __construct()
    {
        $this->data      = session()->getFlashdata('dados_tela') ?? [];
        $this->permissao = $this->data['permissao'];

        $this->relatorios = new ConfigRelatoriosModel();
        $this->filtros    = new ConfigRelFiltrosModel();
        $this->colunas    = new ConfigRelColunasModel();
        $this->joins      = new ConfigRelJoinsModel();
        $this->camposCab    = new ConfigRelCamposCabModel();
        $this->colunasDoc   = new ConfigRelColunasDocModel();
        $this->textosLivres = new ConfigRelTextosModel();
        $this->dicionario      = new ConfigDicDadosModel();
        $this->common     = new CommonModel();

        if ($this->data['erromsg'] != '') {
            $this->__erro();
        }
    }

    public function __erro()
    {
        echo view('vw_semacesso', $this->data);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  INDEX
    // ─────────────────────────────────────────────────────────────────────────

    public function index()
    {
        $this->data['colunas']   = montaColunasLista($this->data, 'mod_id');
        $this->data['url_lista'] = base_url($this->data['controler'] . '/lista');
        echo view('vw_lista', $this->data);
    }

    public function lista()
    {
        $dados = $this->relatorios->getRelatorios();
        // debug($dados, true);

        foreach ($dados as $ent) {
            $ent->tabela = 'cfg_relatorios';
        }


        $this->data['exclusao'] = false;
        echo json_encode([
            'data' => montaListaColunasEnt($this->data, 'rel_id', $dados, 'rel_titulo')
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  ADD
    // ─────────────────────────────────────────────────────────────────────────

    public function add()
    {
        // *claude* o tipo de saída só é escolhido na criação (ver EntCfgRelatorios::
        // defCampos() — "tipoTravado"); como as abas são montadas aqui no servidor,
        // a escolha do tipo é feita por reload via querystring (?tipo=), disparado
        // pelo onchange do radio rel_tipo_saida em my_relatorio.js.
        $tipo = strtoupper((string) ($this->request->getGet('tipo') ?? 'TABULAR'));
        if (!in_array($tipo, ['TABULAR', 'DOCUMENTO'], true)) {
            $tipo = 'TABULAR';
        }

        // Limpa a tabela base da sessão (será atualizada via AJAX quando o usuário selecionar)
        session()->set('rel_tabela_base_atual', '');

        $erel = new EntCfgRelatorios(['rel_tipo_saida' => $tipo]);

        if ($tipo === 'DOCUMENTO') {
            $this->_montaTelaDocumento($erel, false, null);
        } else {
            $this->_montaTelaTabular($erel, false, null);
        }

        $this->data['destino'] = 'store';
        $this->data['script']  = $this->_scriptTela();

        echo view('vw_edicao_relatorio', $this->data);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  EDIT
    // ─────────────────────────────────────────────────────────────────────────

    public function edit(int $id, bool $show = false)
    {
        $dados = $this->relatorios->getRelatorios($id);

        if (!$dados) {
            return redirectWithError($this->data['controler'], 41);
        }

        $dadosArray = is_object($dados) && method_exists($dados, 'toRawArray') ? $dados->toRawArray() : (array) $dados;

        // debug($dadosArray, true);

        // Guarda a tabela base na sessão para uso em addFiltro/addColuna (AJAX não envia o form completo)
        session()->set('rel_tabela_base_atual', $dadosArray['rel_tabela_base'] ?? '');

        $erel = new EntCfgRelatorios($dadosArray, $show);

        if (($dadosArray['rel_tipo_saida'] ?? 'TABULAR') === 'DOCUMENTO') {
            $this->_montaTelaDocumento($erel, $show, $id);
        } else {
            $this->_montaTelaTabular($erel, $show, $id);
        }

        $this->data['destino']         = 'store';
        $charsLinha = $dadosArray['rel_chars_por_linha'] ?? 0;
        $this->data['rel_chars_linha'] = $charsLinha;
        $this->data['log']             = buscaLog('cfg_relatorios', $id);
        $this->data['script']          = $this->_scriptTela((int) $charsLinha);

        echo view('vw_edicao_relatorio', $this->data);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Monta $this->data['secoes']/['displ']/['campos'] — TABULAR
    //  Usado por add() (novo, $id=null) e edit() (existente, $id preenchido).
    //  Comportamento IDÊNTICO ao existente antes da introdução do Documento —
    //  só foi extraído de dentro de add()/edit() para permitir a ramificação
    //  por rel_tipo_saida.
    // ─────────────────────────────────────────────────────────────────────────

    private function _montaTelaTabular(EntCfgRelatorios $erel, bool $show, ?int $id): void
    {
        // ── Aba Filtros ──────────────────────────────────────────────────────
        $lst_filtros = $id ? $this->filtros->getFiltros($id) : [];
        $tabelaBase  = $erel->rel_tabela_base ?? '';
        $opcoesFil   = $this->_opcoesFiltroPorTabela($tabelaBase);

        $campos_filtros = [];
        if (count($lst_filtros) > 0) {
            foreach ($lst_filtros as $pos => $f) {
                $fArray = is_object($f) && method_exists($f, 'toRawArray') ? $f->toRawArray() : (array) $f;
                $fields               = $erel->defCamposFiltro($fArray, $show, $pos, $opcoesFil);
                $campos_filtros[$pos] = $this->_linhaFiltro($fields);
            }
        } else {
            $fields            = $erel->defCamposFiltro(false, false, 0, $opcoesFil);
            $campos_filtros[0] = $this->_linhaFiltro($fields);
        }

        // ── Aba Colunas ──────────────────────────────────────────────────────
        $lst_colunas = $id ? $this->colunas->getColunas($id) : [];

        $campos_colunas = [];
        if (count($lst_colunas) > 0) {
            foreach ($lst_colunas as $pos => $c) {
                $cArray               = is_object($c) && method_exists($c, 'toRawArray') ? $c->toRawArray() : (array) $c;
                $fields               = $erel->defCamposColunas($cArray, $show, $pos);
                $campos_colunas[$pos] = $this->_linhaColunas($fields);
            }
        } else {
            $fields            = $erel->defCamposColunas();
            $campos_colunas[0] = $this->_linhaColunas($fields);
        }

        $this->data['secoes'] = ['Dados Gerais', 'Filtros', 'Colunas', 'Permissões'];
        $this->data['displ']  = [null, 'tabela', 'tabela', null];

        $this->data['campos'] = [
            [
                $erel->campos['rel_id'],
                $erel->campos['rel_nome'],
                $erel->campos['rel_tipo_saida'],
                $erel->campos['mod_id'],
                $erel->campos['tel_id'],
                $erel->campos['rel_tabela_base'],
                $erel->campos['rel_totalizar_registros'],
                $erel->campos['rel_formato'],
                $erel->campos['rel_tamanho_fonte'],
                $erel->campos['rel_titulo'],
                $erel->campos['rel_chars_display'],
            ],
            $campos_filtros,
            $campos_colunas,
            [
                $erel->campos['prf_id'],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Monta $this->data['secoes']/['displ']/['campos'] — DOCUMENTO
    //  Abas: Dados Gerais / Cabeçalho / Tabela / Textos Livres / Permissões.
    // ─────────────────────────────────────────────────────────────────────────

    private function _montaTelaDocumento(EntCfgRelatorios $erel, bool $show, ?int $id): void
    {
        // ── Aba Cabeçalho ────────────────────────────────────────────────────
        $lst_cab = $id ? $this->camposCab->getCamposCab($id) : [];

        $campos_cab = [];
        if (count($lst_cab) > 0) {
            foreach ($lst_cab as $pos => $c) {
                $cArray            = is_object($c) && method_exists($c, 'toRawArray') ? $c->toRawArray() : (array) $c;
                $fields            = EntCfgRelCamposCab::defCampos($cArray, $show, $pos);
                $campos_cab[$pos]  = $this->_linhaCampoCab($fields);
            }
        } else {
            $fields        = EntCfgRelCamposCab::defCampos();
            $campos_cab[0] = $this->_linhaCampoCab($fields);
        }

        // ── Aba Tabela ───────────────────────────────────────────────────────
        $lst_coldoc = $id ? $this->colunasDoc->getColunasDoc($id) : [];

        $campos_coldoc = [];
        if (count($lst_coldoc) > 0) {
            foreach ($lst_coldoc as $pos => $c) {
                $cArray               = is_object($c) && method_exists($c, 'toRawArray') ? $c->toRawArray() : (array) $c;
                $fields               = EntCfgRelColunasDoc::defCampos($cArray, $show, $pos);
                $campos_coldoc[$pos]  = $this->_linhaColunaDoc($fields);
            }
        } else {
            $fields           = EntCfgRelColunasDoc::defCampos();
            $campos_coldoc[0] = $this->_linhaColunaDoc($fields);
        }

        // ── Aba Rodapé (cfg_rel_textoslivres — antiga "Textos Livres") ──────
        $lst_txt = $id ? $this->textosLivres->getTextos($id) : [];

        $campos_txt = [];
        if (count($lst_txt) > 0) {
            foreach ($lst_txt as $pos => $t) {
                $tArray           = is_object($t) && method_exists($t, 'toRawArray') ? $t->toRawArray() : (array) $t;
                $fields           = EntCfgRelTextos::defCampos($tArray, $show, $pos);
                $campos_txt[$pos] = $this->_linhaTexto($fields);
            }
        } else {
            $fields        = EntCfgRelTextos::defCampos();
            $campos_txt[0] = $this->_linhaTexto($fields);
        }

        // *claude* "Rodapé" (usuário, 2026-09-23) — é impresso depois da
        // Tabela. Slug explícito 'rodape_doc' (vw_edicao_relatorio.php usa
        // $slugs[$s] quando informado): url_amigavel('Rodapé') daria 'rodape',
        // que colide com o rodapé do layout (strut/vw_rodape.php: id/classe
        // 'rodape', alvo de my_menu.js/my_wsconn.js). Usado por
        // EntCfgRelTextos (botões) e my_relatorio.js (.table-rodape_doc/#rep_rodape_doc).
        $this->data['secoes'] = ['Dados Gerais', 'Cabeçalho', 'Tabela', 'Rodapé', 'Permissões'];
        $this->data['slugs']  = [3 => 'rodape_doc'];
        $this->data['displ']  = [null, 'tabela', 'tabela', 'tabela', null];

        $this->data['campos'] = [
            [
                $erel->campos['rel_id'],
                $erel->campos['rel_nome'],
                $erel->campos['rel_tipo_saida'],
                $erel->campos['mod_id'],
                $erel->campos['tel_id'],
                $erel->campos['rel_tabela_base'],
                $erel->campos['rel_tabela_detalhe'],
                $erel->campos['rel_detalhe_campo_vinculo'],
                $erel->campos['rel_formato'],
                // *claude* feedback do usuário (byarq): tamanho de fonte precisa
                // ser configurável também no Documento, mas só afeta o CORPO
                // (tabela repetível) — cabeçalho (campos_cab) e textos livres
                // permanecem com fonte fixa, igual htmlAnaRequisicao() hoje.
                // Ver CriamPdf2026::htmlDocumentoGenerico().
                $erel->campos['rel_tamanho_fonte'],
                $erel->campos['rel_titulo'],
                $erel->campos['id_teste_documento'],
            ],
            $campos_cab,
            $campos_coldoc,
            $campos_txt,
            [
                $erel->campos['prf_id'],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  AJAX — addFiltro
    //  Chamado pelo botão (+) da aba Filtros para adicionar nova linha vazia
    //  URL: CfgRelatorio/addFiltro/{pos}
    // ─────────────────────────────────────────────────────────────────────────

    public function addFiltro(int $pos)
    {
        // O AJAX do addCampo() não envia o form inteiro, por isso a tabela base
        // vem da sessão (atualizada em add/edit e nos endpoints busca_campos_*_rel)
        $tabelaBase = session()->get('rel_tabela_base_atual') ?? '';
        $opcoesFil  = $this->_opcoesFiltroPorTabela($tabelaBase);
        $fields     = (new EntCfgRelatorios())->defCamposFiltro(false, false, $pos, $opcoesFil);
        echo json_encode($this->_linhaFiltro($fields));
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  AJAX — addColuna
    //  Chamado pelo botão (+) da aba Colunas para adicionar nova linha vazia
    //  URL: CfgRelatorio/addColuna/{pos}
    // ─────────────────────────────────────────────────────────────────────────

    public function addColuna(int $pos)
    {
        $erel   = new EntCfgRelatorios();
        $fields = $erel->defCamposColunas(false, false, $pos);
        echo json_encode($this->_linhaColunas($fields));
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  AJAX — addCampoCab (DOCUMENTO — aba Cabeçalho)
    //  Espelho de addColuna(): rcc_campo usa crDepende (pai=rel_tabela_base),
    //  populado 100% via AJAX/JS ao inserir a linha — sem depender de sessão.
    //  URL: CfgRelatorio/addCampoCab/{pos}
    // ─────────────────────────────────────────────────────────────────────────

    public function addCampoCab(int $pos)
    {
        $fields = EntCfgRelCamposCab::defCampos(false, false, $pos);
        echo json_encode($this->_linhaCampoCab($fields));
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  AJAX — addColunaDoc (DOCUMENTO — aba Tabela)
    //  URL: CfgRelatorio/addColunaDoc/{pos}
    // ─────────────────────────────────────────────────────────────────────────

    public function addColunaDoc(int $pos)
    {
        $fields = EntCfgRelColunasDoc::defCampos(false, false, $pos);
        echo json_encode($this->_linhaColunaDoc($fields));
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  AJAX — addTextoLivre (DOCUMENTO — aba Rodapé)
    //  URL: CfgRelatorio/addTextoLivre/{pos}
    // ─────────────────────────────────────────────────────────────────────────

    public function addTextoLivre(int $pos)
    {
        $fields = EntCfgRelTextos::defCampos(false, false, $pos);
        echo json_encode($this->_linhaTexto($fields));
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  AJAX — previewRelatorio
    //  Retorna HTML do preview do relatório com dados fictícios (10 registros)
    // ─────────────────────────────────────────────────────────────────────────

    public function previewRelatorio()
    {
        $postado = $this->request->getPost();

        $colunas       = [];
        $campos        = $postado['rco_campo']         ?? [];
        $labels        = $postado['rco_label']         ?? [];
        $alinhams      = $postado['rco_alinhamento']   ?? [];
        $totalizar     = $postado['rco_totalizar']     ?? [];
        $tiposDado     = $postado['rco_tipo_dado']     ?? [];
        $larguras      = $postado['rco_largura']       ?? [];
        $comportaments = $postado['rco_comportamento'] ?? [];

        foreach ($campos as $i => $campo) {
            if (empty($campo)) continue;
            $partes = explode('|', $campo);
            $colunas[] = [
                'tabela'        => $partes[0] ?? '',
                'campo'         => $partes[1] ?? $campo,
                'tamanho'       => (int) ($partes[2] ?? 0),
                'tipo_dado'     => $partes[3] ?? ($tiposDado[$i] ?? ''),
                'label'         => $labels[$i] ?? '',
                'alinhamento'   => $alinhams[$i] ?? 'E',
                'totalizar'     => !empty($totalizar[$i]) ? 1 : 0,
                'largura'       => (int) ($larguras[$i] ?? 0),
                'comportamento' => $comportaments[$i] ?? 'cortar',
            ];
        }

        $filtros = [];
        $fCampos = $postado['rfi_campo'] ?? [];
        $fLabels = $postado['rfi_label'] ?? [];
        $fTabelas = $postado['rfi_tabela'] ?? [];
        $fTipos  = $postado['rfi_tipo_filtro'] ?? [];

        foreach ($fCampos as $i => $fc) {
            if (empty($fc)) continue;
            $partes = explode('|', $fc);
            $filtros[] = [
                'campo'       => $partes[0] ?? $fc,
                'tabela'      => !empty($fTabelas[$i]) ? $fTabelas[$i] : ($partes[1] ?? ''),
                'tipo_filtro' => !empty($fTipos[$i]) ? $fTipos[$i] : ($partes[2] ?? 'FK'),
                'label'       => $fLabels[$i] ?? '',
            ];
        }

        $config = [
            'titulo'                => $postado['rel_titulo'] ?? '',
            'nome'                  => $postado['rel_nome'] ?? '',
            'formato'               => $postado['rel_formato'] ?? 'P',
            'tamanho_fonte'         => (int) ($postado['rel_tamanho_fonte'] ?? 10),
            'tabela_base'           => $postado['rel_tabela_base'] ?? '',
            'totalizar_registros'   => !empty($postado['rel_totalizar_registros']) ? 1 : 0,
            'colunas'               => $colunas,
            'filtros'               => $filtros,
        ];

        $html = (new \App\Controllers\CriamPdf2026())->htmlRelatorioGenerico($config, true);
        echo json_encode(['html' => $html]);
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  AJAX — previewDocumento (DOCUMENTO)
    //  Espelho de previewRelatorio(): extrai rcc_*/rct_*/rtx_* do POST em
    //  andamento (a config ainda não foi salva) e monta o HTML via
    //  CriamPdf2026::htmlDocumentoGenerico(). Só busca dados reais quando o
    //  admin preenche "id_teste_documento" — senão mostra placeholders.
    // ─────────────────────────────────────────────────────────────────────────

    public function previewDocumento()
    {
        $postado = $this->request->getPost();

        $idTeste = !empty($postado['id_teste_documento']) ? (int) $postado['id_teste_documento'] : null;

        // *claude* rel_titulo chega composto ("tabela|campo|tamanho|tipo",
        // mesmo formato do picker de Cabeçalho — ver EntCfgRelatorios::
        // defCampos()) — decompõe aqui pro mesmo shape que
        // CriamPdf2026::_montarDadosDocumento() usa na impressão real
        // (titulo_tabela/titulo_campo), consumido por _buscarDadosDocumento().
        $partesTitulo = explode('|', $postado['rel_titulo'] ?? '');

        $config = [
            'titulo_tabela'  => $partesTitulo[0] ?? '',
            'titulo_campo'   => $partesTitulo[1] ?? '',
            'formato'        => $postado['rel_formato'] ?? 'P',
            // *claude* controla só a fonte do CORPO (tabela repetível) — o
            // cabeçalho/textos livres ficam com fonte fixa, ver
            // CriamPdf2026::htmlDocumentoGenerico().
            'tamanho_fonte'  => (int) ($postado['rel_tamanho_fonte'] ?? 10),
            'tabela_base'    => $postado['rel_tabela_base'] ?? '',
            'tabela_detalhe' => $postado['rel_tabela_detalhe'] ?? '',
            // *claude* rel_detalhe_campo_vinculo agora é sempre o nome puro da
            // coluna (campo oculto atualizado via AJAX em my_relatorio.js —
            // ver EntCfgRelatorios::defCampos()); deixou de ser composto
            // porque deixou de ser um crDepende (regra 4 — não é mais escolha
            // livre do admin). Só feedback visual no preview: o store() real
            // recalcula do zero e ignora isso.
            'campo_vinculo'  => $postado['rel_detalhe_campo_vinculo'] ?? '',
            'id_registro'    => $idTeste,
            // *claude* _extrairCamposCabPost()/_extrairColunasDocPost()/
            // _extrairTextosPost() devolvem linhas com chaves PREFIXADAS
            // (rcc_/rct_/rtx_ — formato usado pra sincronizarCamposCab() etc.
            // no banco); CriamPdf2026::_buscarDadosDocumento() espera o
            // formato GENÉRICO ('tabela'/'campo'/'label'/...), o mesmo que
            // CriamPdf2026::_montarDadosDocumento() já produz na impressão
            // real — por isso normaliza aqui antes de montar $config.
            'campos_cab'     => array_map(fn($l) => $this->_normalizaDefDocumento($l, 'rcc'), $this->_extrairCamposCabPost($postado)),
            'colunas_doc'    => array_map(fn($l) => $this->_normalizaDefDocumento($l, 'rct'), $this->_extrairColunasDocPost($postado)),
            'textos_livres'  => array_map(fn($l) => $this->_normalizaDefDocumento($l, 'rtx'), $this->_extrairTextosPost($postado)),
            // *claude* sem join nenhum, de propósito — decisão do usuário: o
            // Documento não combina rel_tabela_base/rel_tabela_detalhe
            // automaticamente (cada aba só referencia a própria tabela,
            // reforçado pelo picker — Buscas::busca_campos_tabela_unica()). Se
            // precisar de mais tabelas, a saída é criar uma VIEW no banco.
        ];
        $html = (new \App\Controllers\CriamPdf2026())->htmlDocumentoGenerico($config, true);
        echo json_encode(['html' => $html]);
        exit;
    }

    /**
     * Converte UMA linha de _extrairCamposCabPost()/_extrairColunasDocPost()/
     * _extrairTextosPost() (chaves prefixadas — "rcc_tabela"/"rcc_campo"/...,
     * formato usado pra sincronizarCamposCab()/sincronizarColunasDoc()/
     * sincronizarTextosLivres() no banco) para o formato GENÉRICO ('tabela',
     * 'campo', 'label', 'tipo_dado', 'largura_col', 'largura') que
     * CriamPdf2026::_montarDadosDocumento()/_buscarDadosDocumento() esperam
     * — mesmo shape nos dois caminhos (preview e impressão real), pra não
     * duplicar/desalinhar a leitura lá.
     *
     * @param  array  $linha    Uma linha de retorno de _extrairXPost()
     * @param  string $prefixo  'rcc' | 'rct' | 'rtx'
     */
    private function _normalizaDefDocumento(array $linha, string $prefixo): array
    {
        return [
            'tabela'      => $linha["{$prefixo}_tabela"]    ?? '',
            'campo'       => $linha["{$prefixo}_campo"]     ?? '',
            'label'       => $linha["{$prefixo}_label"]     ?? '',
            'tipo_dado'   => $linha["{$prefixo}_tipo_dado"] ?? '',
            'largura_col' => $linha["{$prefixo}_largura_col"] ?? 'col-3',
            'largura'     => $linha["{$prefixo}_largura"]   ?? 0,
            // só o Rodapé (rtx) tem texto digitado
            'texto'       => $linha["{$prefixo}_texto"]     ?? '',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  AJAX — camposFiltro
    //  Retorna campos _id e DATE da tabela base para popular o select de campo
    //  URL: CfgRelatorio/camposFiltro?tabela=xxx
    // ─────────────────────────────────────────────────────────────────────────

    public function camposFiltro()
    {
        $tabela = $this->request->getGet('tabela');

        if (!$tabela) {
            echo json_encode(['erro' => true, 'msg' => 'Tabela não informada.']);
            return;
        }
        // $dbGrSche = $this->dicionario->getDbGroupAndSchema($tabela);

        // $db     = db_connect($dbGrSche['dbGroup']);
        $campos = $this->dicionario->getCampos($tabela);
        // debug($campos, true);
        $ret    = [];

        foreach ($campos as $col) {
            $isDate = in_array(strtolower($col['DATA_TYPE']), ['date', 'datetime', 'timestamp']);
            if (str_ends_with($col['COLUMN_NAME'], '_excluido')) continue;
            if ($col['COLUMN_KEY'] !== '' || $isDate) {
                $ret[] = [
                    'id'   => $col['COLUMN_NAME'],
                    'text' => $col['NOME_COMPLETO'],
                ];
            }
        }

        echo json_encode(['erro' => false, 'campos' => $ret]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  AJAX — camposColunas
    //  Retorna TODOS os campos de todas as tabelas relacionadas via
    //  getRelacionamentos(), com tamanho, para popular o select de campo
    //  URL: CfgRelatorio/camposColunas?tabela=xxx
    // ─────────────────────────────────────────────────────────────────────────

    public function camposColunas()
    {
        $tabela = $this->request->getGet('tabela');

        if (!$tabela) {
            echo json_encode(['erro' => true, 'msg' => 'Tabela não informada.']);
            return;
        }

        $relacionamentos = $this->dicionario->getRelacionamentos($tabela);
        // debug($relacionamentos);
        $ret = [];

        $tabelas = [];
        $tabelas[] = $tabela;
        foreach ($relacionamentos['relacionamentos'] as $rel) {
            // debug($rel);
            if (!empty($rel['REFERENCED_TABLE_NAME'])) {
                $tabelas[] = $rel['REFERENCED_TABLE_NAME'];
            }
        }
        // debug($tabelas, true);
        $tabelas = array_unique($tabelas);
        foreach ($tabelas as $tab) {
            $campos = $this->dicionario->getCampos($tab);

            foreach ($campos as $col) {
                if (str_ends_with($col['COLUMN_NAME'], '_excluido')) continue;
                if ($col['COLUMN_KEY'] !== '') continue;

                $ret[] = [
                    'id'   => $tab . '|' . $col['COLUMN_NAME'] . '|' . ($col['CHARACTER_MAXIMUM_LENGTH'] ?? 0),
                    'text' => '[' . $tab . '] ' . $col['NOME_COMPLETO'],
                ];
            }
        }

        echo json_encode(['erro' => false, 'campos' => $ret]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  AJAX — charsLinha
    //  Recalcula e devolve chars_por_linha quando formato ou fonte mudam
    //  URL: CfgRelatorio/charsLinha?formato=P&fonte=10
    // ─────────────────────────────────────────────────────────────────────────

    public function charsLinha()
    {
        $formato = $this->request->getGet('formato') ?? 'P';
        $fonte   = (int) ($this->request->getGet('fonte') ?? 10);
        $chars   = $this->relatorios->calcCharsPorLinha($formato, $fonte);

        echo json_encode(['erro' => false, 'chars_por_linha' => $chars]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  DELETE
    // ─────────────────────────────────────────────────────────────────────────

    public function delete(int $id)
    {
        $ret = [];
        try {
            // ON DELETE CASCADE cuida das filhas automaticamente
            $this->relatorios->delete($id);
            $ret['erro'] = false;
            session()->setFlashdata('msg', 'Relatório excluído com sucesso!');
        } catch (\Exception $e) {
            $ret['erro'] = true;
            $ret['msg']  = 3;
        }
        echo json_encode($ret);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  ATIVAR / INATIVAR
    // ─────────────────────────────────────────────────────────────────────────

    public function ativinativ(int $id, int $tipo)
    {
        $ret = [];
        try {
            if ($tipo == 1) {
                $dad_atin = [
                    'rel_ativo' => 'A'
                ];
            } else {
                $dad_atin = [
                    'rel_ativo' => 'I'
                ];
            }
            $this->relatorios->update($id, $dad_atin);
            $ret['erro'] = false;
            session()->setFlashdata('msg', 'Relatório alterado com sucesso!');
            $ret['msg']  = 'Relatório alterado com sucesso!';
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            $ret['erro'] = true;
            $ret['msg']  = 14;
        } catch (\Exception $e) {
            $ret['erro'] = true;
            $ret['msg']  = 14;
        }
        echo json_encode($ret);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  STORE
    // ─────────────────────────────────────────────────────────────────────────

    public function store()
    {
        $ret     = ['erro' => false];
        $postado = $this->request->getPost();
        // debug($postado);

        $tipoSaida = strtoupper($postado['rel_tipo_saida'] ?? 'TABULAR');
        if (!in_array($tipoSaida, ['TABULAR', 'DOCUMENTO'], true)) {
            $tipoSaida = 'TABULAR';
        }

        // *claude* no DOCUMENTO, rel_titulo chega composto ("tabela|campo|
        // tamanho|tipo" — mesmo formato do picker de Cabeçalho, ver
        // EntCfgRelatorios::defCampos()) porque virou um campo selecionável
        // (pode vir de rel_tabela_base OU rel_tabela_detalhe), não mais texto
        // livre. Decompõe ANTES de montar $dados/chamar salvarRelatorio() —
        // no Tabular rel_titulo continua chegando como texto livre puro, sem
        // "|", então este bloco não se aplica (guardado por $tipoSaida).
        if ($tipoSaida === 'DOCUMENTO' && !empty($postado['rel_titulo'])) {
            $partesTitulo = explode('|', $postado['rel_titulo']);
            $postado['rel_titulo_tabela'] = $partesTitulo[0] ?? '';
            $postado['rel_titulo']        = $partesTitulo[1] ?? $postado['rel_titulo'];
        }

        $this->relatorios->transBegin();

        try {
            // ── 1. Grava cabeçalho (calcula chars_por_linha internamente) ────
            $camposHeader = [
                'rel_id',
                'rel_nome',
                'rel_tipo_saida',
                'mod_id',
                'tel_id',
                'rel_titulo',
                // *claude* só preenchida quando rel_tipo_saida=DOCUMENTO (ver
                // decomposição acima) — no Tabular fica ausente do POST,
                // então nem entra em $dados (rel_titulo_tabela permanece NULL
                // no banco pra relatórios Tabular).
                'rel_titulo_tabela',
                'rel_tabela_base',
                // *claude* só relevantes quando rel_tipo_saida=DOCUMENTO — em
                // TABULAR simplesmente não vêm no POST (campos não renderizados)
                'rel_tabela_detalhe',
                'rel_detalhe_campo_vinculo',
                'rel_formato',
                'rel_tamanho_fonte',
                'rel_totalizar_registros',
            ];
            $dados = array_intersect_key($postado, array_flip($camposHeader));

            // *claude* rel_detalhe_campo_vinculo aqui é só um "melhor palpite"
            // vindo do POST (campo oculto atualizado por JS, ver
            // EntCfgRelatorios::defCampos()) — a Regra 4 (byarq/usuário) manda
            // o SERVIDOR recalcular esse valor do zero a partir do schema real
            // dentro de _storeDocumento(), que sobrescreve o que for gravado
            // aqui. Para TABULAR essa chave nem vem no POST (campo não
            // renderizado nesse tipo).

            // debug($dados);
            $rel_id = $this->relatorios->salvarRelatorio($dados);
            // debug($rel_id);
            if (!$rel_id) {
                throw new \RuntimeException(implode('<br>', $this->relatorios->errors()));
            }

            if ($tipoSaida === 'DOCUMENTO') {
                $this->_storeDocumento($rel_id, $postado);
            } else {
                $this->_storeTabular($rel_id, $postado);
            }

            // ── Sincroniza permissões por perfil (comum aos dois tipos) ──────
            $this->common->deleteReg('default', 'cfg_rel_permissao', 'rel_id = ' . $rel_id);

            if (!empty($postado['prf_id'])) {
                $prfIds = is_array($postado['prf_id'][0] ?? null)
                    ? array_merge(...$postado['prf_id'])
                    : $postado['prf_id'];

                foreach ($prfIds as $prf) {
                    $this->common->insertReg('default', 'cfg_rel_permissao', [
                        'rel_id'         => $rel_id,
                        'prf_id'         => $prf,
                        'rlp_atualizado' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            $this->relatorios->transCommit();

            session()->setFlashdata('msg', 'Relatório gravado com sucesso!');
            $ret['url'] = site_url($this->data['controler']);
        } catch (\Throwable $e) {
            $this->relatorios->transRollback();
            $ret = [
                'erro' => true,
                'msg'  => $e->getMessage() ?: 'Erro ao salvar o Relatório.',
            ];
        }

        echo json_encode($ret);
    }

    /**
     * Persiste filtros/colunas/joins/SQL do relatório TABULAR. Comportamento
     * IDÊNTICO ao que existia antes da introdução do Documento — só foi
     * extraído de dentro de store() para permitir a ramificação por tipo.
     */
    private function _storeTabular(int $rel_id, array $postado): void
    {
        // ── Sincroniza filtros ───────────────────────────────────────────
        $filtros = $this->_extrairFiltrosPost($postado);
        // debug($filtros);
        $this->filtros->sincronizarFiltros($rel_id, $filtros);

        // ── Sincroniza colunas ───────────────────────────────────────────
        $colunas = $this->_extrairColunasPost($postado);
        // debug($colunas);
        $this->colunas->sincronizarColunas($rel_id, $colunas);

        // ── Reconstrói JOINs a partir das colunas gravadas ───────────────
        $this->_sincronizarJoins($rel_id, $postado['rel_tabela_base']);

        // ── Gera e salva o SQL ───────────────────────────────────────────
        $this->relatorios->gerarSQL($rel_id);
    }

    /**
     * Persiste campos de cabeçalho/colunas da tabela/textos livres do
     * relatório DOCUMENTO. NÃO gera rel_sql_gerado — o Documento não tem SQL
     * único pré-gerado, a query é montada em tempo de impressão/preview (ver
     * CriamPdf2026::_buscarDadosDocumento()).
     *
     * NÃO sincroniza cfg_rel_joins — decisão do usuário: o Documento não faz
     * join automático entre rel_tabela_base/rel_tabela_detalhe. Cabeçalho e
     * Textos Livres só podem referenciar campos de rel_tabela_base; Tabela só
     * pode referenciar campos de rel_tabela_detalhe (reforçado pelo picker —
     * ver Buscas::busca_campos_tabela_unica()). Se for preciso combinar dados
     * de mais tabelas, quem configura o relatório cria uma VIEW no banco e
     * aponta rel_tabela_base/rel_tabela_detalhe pra ela. `cfg_rel_joins`/
     * `rjo_grupo` continuam existindo no schema (usados pelo Tabular via
     * _storeTabular()), só não são mais gravados/lidos para o Documento.
     */
    private function _storeDocumento(int $rel_id, array $postado): void
    {
        // ── Regras 1-3 (byarq/usuário) — só hard-enforced no store() real,
        // nunca no preview (previewDocumento() não chama isto). Lança
        // RuntimeException, capturada pelo catch(\Throwable) de store().
        $this->_validarRegrasDocumento($postado);

        $tabelaBase    = $postado['rel_tabela_base']    ?? '';
        $tabelaDetalhe = $postado['rel_tabela_detalhe'] ?? '';

        // ── Regra 4 — rel_detalhe_campo_vinculo NUNCA é o que veio do form
        // (o campo virou só informativo, ver EntCfgRelatorios::defCampos()) —
        // é sempre recalculado aqui a partir do schema real e sobrescreve o
        // "melhor palpite" que o cabeçalho gravou em store(). Se
        // rel_tabela_detalhe estiver vazia (Documento sem aba "Tabela"
        // configurada), fica null — nada a vincular.
        $campoVinculoReal = (!empty($tabelaBase) && !empty($tabelaDetalhe))
            ? $this->dicionario->buscarCampoVinculo($tabelaDetalhe, $tabelaBase)
            : null;
        $this->relatorios->update($rel_id, ['rel_detalhe_campo_vinculo' => $campoVinculoReal]);

        // ── Sincroniza Cabeçalho ─────────────────────────────────────────
        $camposCab = $this->_extrairCamposCabPost($postado);
        $this->camposCab->sincronizarCamposCab($rel_id, $camposCab);

        // ── Sincroniza Tabela ────────────────────────────────────────────
        $colunasDoc = $this->_extrairColunasDocPost($postado);
        $this->colunasDoc->sincronizarColunasDoc($rel_id, $colunasDoc);

        // ── Sincroniza Textos Livres ─────────────────────────────────────
        $textos = $this->_extrairTextosPost($postado);
        $this->textosLivres->sincronizarTextosLivres($rel_id, $textos);
    }

    /**
     * Regras de negócio exclusivas do tipo DOCUMENTO (byarq/usuário,
     * 2026-09-16) — hard-enforced SÓ no store() real (nunca no preview):
     *
     *  1. tel_id obrigatório. NÃO está em ConfigRelatoriosModel::
     *     $validationRules porque "required_if" não existe no conjunto de
     *     regras deste projeto (Config\Validation::$ruleSets usa
     *     CodeIgniter\Validation\StrictRules\Rules, sem required_if — usar
     *     esse nome de regra quebra TODO save com "'required_if' is not a
     *     valid rule", erro real que apareceu em produção/teste) — por isso
     *     é validada aqui, em PHP puro.
     *  2. rel_tabela_base precisa ser a própria tabela da Tela (tel_model),
     *     ou estar relacionada a ela (FK real ou potencial, 1 hop, qualquer
     *     direção — ver ConfigDicDadosModel::tabelasRelacionadas()).
     *  3. rel_tabela_detalhe precisa ter uma coluna (FK real ou potencial)
     *     que aponte pra rel_tabela_base — ver ConfigDicDadosModel::
     *     buscarCampoVinculo().
     *
     * Regras 2/3 só rodam se os campos envolvidos já estiverem preenchidos —
     * campo vazio nesses dois é responsabilidade da validação padrão de
     * "obrigatório" no front (rel_tabela_base já é sempre obrigatório via
     * $validationRules; se vier vazio mesmo assim, Regra 2 simplesmente não
     * tem o que validar e deixa passar — o save vai falhar mais adiante por
     * outro motivo, ex. FK/consulta inválida).
     */
    private function _validarRegrasDocumento(array $postado): void
    {
        $telId         = (int) ($postado['tel_id'] ?? 0);
        $tabelaBase    = $postado['rel_tabela_base']    ?? '';
        $tabelaDetalhe = $postado['rel_tabela_detalhe'] ?? '';

        // ── Regra 1 ──────────────────────────────────────────────────────
        if (!$telId) {
            throw new \RuntimeException(
                'A Tela é obrigatória para o tipo Documento.'
            );
        }

        // ── Regra 2 ──────────────────────────────────────────────────────
        if ($telId && !empty($tabelaBase)) {
            $tabelaDaTela = $this->_resolverTabelaDaTela($telId);

            if ($tabelaDaTela === null) {
                throw new \RuntimeException(
                    'A Tela selecionada não tem um Model configurado (tel_model) — configure isso no cadastro de Tela antes de usar como base de um Documento.'
                );
            }

            if ($tabelaBase !== $tabelaDaTela && !$this->dicionario->tabelasRelacionadas($tabelaBase, $tabelaDaTela)) {
                throw new \RuntimeException(
                    'A Tabela Cabeçalho precisa ser a própria tabela da Tela selecionada, ou estar relacionada a ela.'
                );
            }
        }

        // ── Regra 3 ──────────────────────────────────────────────────────
        if (!empty($tabelaBase) && !empty($tabelaDetalhe)) {
            if ($this->dicionario->buscarCampoVinculo($tabelaDetalhe, $tabelaBase) === null) {
                throw new \RuntimeException(
                    'A Tabela Detalhe precisa ter um campo relacionado (FK) com a Tabela Cabeçalho.'
                );
            }
        }
    }

    /**
     * Resolve a tabela física de uma Tela (tel_id) a partir do Model
     * configurado em cfg_tela.tel_model — mesmo padrão já usado por
     * CfgTela::edit() (CfgTela.php) e Buscas::busca_campo_tela() (Buscas.php):
     * as 6 primeiras letras do nome da classe indicam a subpasta em
     * App\Models\ (ex.: "EstoquRequisicaoModel" -> App\Models\Estoqu\).
     *
     * @return string|null null quando a tela não existe, não tem tel_model,
     *                     ou a classe não resolve — CfgRelatorio::
     *                     _validarRegrasDocumento() trata isso como bloqueio
     *                     (Regra 2 precisa saber a tabela da Tela).
     */
    private function _resolverTabelaDaTela(int $telId): ?string
    {
        $telaRows  = (new \App\Models\Config\ConfigTelaModel())->getTelaId($telId);
        $dadosTela = $telaRows[0] ?? null;

        if (!$dadosTela || empty($dadosTela->tel_model)) {
            return null;
        }

        $model      = $dadosTela->tel_model;
        $complModel = substr($model, 0, 6);
        $nomeModel  = "App\\Models\\{$complModel}\\{$model}";

        if (!class_exists($nomeModel)) {
            return null;
        }

        $modelAtual = new $nomeModel();

        return $modelAtual->table ?? null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Retorna as opções do select de campo de filtro para a tabela informada.
     */
    private function _opcoesFiltroPorTabela(string $tabela): array
    {
        if (empty($tabela)) return [];

        $campos = $this->dicionario->getCampos($tabela);

        // Verifica se é VIEW — se for, todos os campos não-inteiros são filtro
        $dbGrSche = $this->dicionario->getDbGroupAndSchema($tabela);
        $dbInfo   = db_connect($dbGrSche['dbGroup']);
        $chkView  = $dbInfo->table('information_schema.TABLES')
            ->select('TABLE_TYPE')
            ->where('TABLE_NAME', $tabela)
            ->where('TABLE_SCHEMA', $dbGrSche['schema'])
            ->get();
        $rowView = $chkView ? $chkView->getFirstRow() : null;
        $isView  = ($rowView && $rowView->TABLE_TYPE === 'VIEW');

        $ret = [];
        foreach ($campos as $col) {
            if (str_ends_with($col['COLUMN_NAME'], '_excluido')) continue;

            $isDate = in_array(strtolower($col['DATA_TYPE']), ['date', 'datetime', 'timestamp']);
            $isFK   = $col['COLUMN_KEY'] !== '';
            $isInt  = in_array(strtolower($col['DATA_TYPE']), ['int', 'tinyint', 'smallint', 'mediumint', 'bigint']);

            // View: todos os campos não-inteiros | Tabela: apenas FK e date
            if (($isView && !$isInt) || $isFK || $isDate) {
                $tipo = $isDate ? 'DATE' : 'FK';
                $chave = $col['COLUMN_NAME'] . '|' . $tabela . '|' . $tipo;
                $ret[$chave] = $col['NOME_COMPLETO'];
            }
        }

        return $ret;
    }

    /**
     * Monta o array de campos de UMA linha da aba Filtros.
     * Espelho do padrão _linhaEtiqueta() da CfgEtiqueta.
     */
    private function _linhaFiltro(array $fields): array
    {
        return [
            $fields['rfi_id'],
            $fields['rfi_campo'],       // select dependente da tabela base
            $fields['rfi_tipo_filtro'], // oculto — preenchido ao selecionar campo
            $fields['rfi_tabela'],      // oculto — preenchido ao selecionar campo
            $fields['rfi_campo_pai'],   // *claude* novo: de qual outro filtro este depende (cascata) — opções montadas via JS (my_relatorio.js) a partir dos rfi_campo já escolhidos nas outras linhas
            $fields['rfi_label'],
            $fields['rfi_obrigatorio'],
            $fields['rfi_ordem'],       // oculto
            $fields['bt_add'],
            $fields['bt_del'],
        ];
    }

    /**
     * Monta o array de campos de UMA linha da aba Colunas.
     */
    private function _linhaColunas(array $fields): array
    {
        return [
            $fields['rco_id'],
            $fields['rco_campo'],           // select dependente da tabela base
            $fields['rco_tabela'],          // oculto — preenchido ao selecionar campo
            $fields['rco_tamanho'],         // oculto — preenchido ao selecionar campo
            $fields['rco_label'],
            $fields['rco_alias'],
            $fields['rco_largura'],         // largura editável
            $fields['rco_comportamento'],   // cortar / quebrar / linha inteira
            $fields['rco_alinhamento'],
            $fields['rco_tipo_dado'],       // oculto — preenchido ao selecionar campo
            $fields['rco_totalizar'],       // habilitado via JS quando tipo numérico
            $fields['rco_ordem'],           // oculto
            $fields['bt_add'],
            $fields['bt_del'],
        ];
    }

    /**
     * Monta o array de campos de UMA linha da aba Cabeçalho (DOCUMENTO).
     */
    private function _linhaCampoCab(array $fields): array
    {
        return [
            $fields['rcc_id'],
            $fields['rcc_campo'],        // select dependente de rel_tabela_base
            $fields['rcc_tabela'],       // oculto — preenchido ao selecionar campo
            $fields['rcc_tamanho'],      // oculto — preenchido ao selecionar campo
            $fields['rcc_label'],
            $fields['rcc_largura_col'],  // largura no grid do PDF/preview (col-3/4/6/12)
            $fields['rcc_tipo_dado'],    // oculto — preenchido ao selecionar campo
            $fields['rcc_ordem'],        // oculto
            $fields['bt_add'],
            $fields['bt_del'],
        ];
    }

    /**
     * Monta o array de campos de UMA linha da aba Tabela (DOCUMENTO).
     */
    private function _linhaColunaDoc(array $fields): array
    {
        return [
            $fields['rct_id'],
            $fields['rct_campo'],     // select dependente de rel_tabela_detalhe
            $fields['rct_tabela'],    // oculto — preenchido ao selecionar campo
            $fields['rct_tamanho'],   // oculto — preenchido ao selecionar campo
            $fields['rct_label'],
            $fields['rct_largura'],
            $fields['rct_tipo_dado'], // oculto — preenchido ao selecionar campo
            $fields['rct_ordem'],     // oculto
            $fields['bt_add'],
            $fields['bt_del'],
        ];
    }

    /**
     * Monta o array de campos de UMA linha da aba Rodapé (DOCUMENTO).
     */
    private function _linhaTexto(array $fields): array
    {
        return [
            $fields['rtx_id'],
            $fields['rtx_label'],  // rótulo (opcional)
            $fields['rtx_texto'],  // texto digitado (opcional)
            $fields['rtx_campo'],  // campo vinculado (opcional)
            $fields['rtx_tabela'], // oculto — preenchido ao selecionar campo
            $fields['rtx_ordem'],  // oculto
            $fields['bt_add'],
            $fields['bt_del'],
        ];
    }

    /**
     * Extrai do POST os filtros como array para sincronizarFiltros().
     * O front envia arrays paralelos indexados: rfi_campo[0], rfi_campo[1]...
     */
    private function _extrairFiltrosPost(array $postado): array
    {
        $filtros = [];

        $campos       = $postado['rfi_campo']       ?? [];
        $tabelas      = $postado['rfi_tabela']       ?? [];
        $tipos        = $postado['rfi_tipo_filtro']  ?? [];
        $labels       = $postado['rfi_label']        ?? [];
        $obrigatorios = $postado['rfi_obrigatorio']  ?? [];
        // *claude* array paralelo do novo select "depende de"
        $camposPai    = $postado['rfi_campo_pai']    ?? [];

        foreach ($campos as $i => $campo) {
            if (empty($campo)) {
                continue;
            }

            $partes = explode('|', $campo);

            $filtros[] = [
                'rfi_campo'       => $partes[0] ?? $campo,
                'rfi_tabela'      => !empty($tabelas[$i]) ? $tabelas[$i] : ($partes[1] ?? ''),
                'rfi_tipo_filtro' => !empty($tipos[$i])   ? $tipos[$i]   : ($partes[2] ?? 'FK'),
                // *claude* nome puro da coluna (não é composto igual rfi_campo) — vazio = sem cascata
                'rfi_campo_pai'   => !empty($camposPai[$i]) ? $camposPai[$i] : null,
                'rfi_label'       => $labels[$i]        ?? '',
                'rfi_obrigatorio' => $obrigatorios[$i]  ?? 0,
            ];
        }

        // *claude* valida as dependências antes de persistir (sem custo de DB — só cruza o
        // array já montado, na mesma ordem de exibição/rfi_ordem). Mesma regra do
        // atualizaDependeDe() em my_relatorio.js: um filtro só pode depender de outro que
        // apareça ANTES dele na lista — por isso o array de campos válidos é acumulado
        // conforme percorre $filtros, nunca contendo a própria linha nem as seguintes.
        // Filtro DATA não depende de nada (usa daterange, não select) nem serve de "pai".
        $camposAteAqui = [];
        foreach ($filtros as $i => &$f) {
            $tipo = $f['rfi_tipo_filtro'] ?? 'FK';

            if ($tipo === 'DATE') {
                $f['rfi_campo_pai'] = null;
            } elseif (!empty($f['rfi_campo_pai']) && !in_array($f['rfi_campo_pai'], $camposAteAqui, true)) {
                throw new \RuntimeException(
                    "O filtro \"{$f['rfi_label']}\" só pode depender de um filtro que apareça ANTES dele na lista."
                );
            }

            if ($tipo !== 'DATE' && !empty($f['rfi_campo'])) {
                $camposAteAqui[] = $f['rfi_campo'];
            }
        }
        unset($f);

        return $filtros;
    }

    /**
     * Extrai do POST as colunas como array para sincronizarColunas().
     */
    private function _extrairColunasPost(array $postado): array
    {
        $colunas = [];

        $campos        = $postado['rco_campo']         ?? [];
        $tabelas       = $postado['rco_tabela']        ?? [];
        $labels        = $postado['rco_label']         ?? [];
        $aliases       = $postado['rco_alias']         ?? [];
        $tamanhos      = $postado['rco_tamanho']       ?? [];
        $alinhams      = $postado['rco_alinhamento']   ?? [];
        $totalizar     = $postado['rco_totalizar']     ?? [];
        $tiposDado     = $postado['rco_tipo_dado']     ?? [];
        $larguras      = $postado['rco_largura']       ?? [];
        $comportaments = $postado['rco_comportamento'] ?? [];

        foreach ($campos as $i => $campo) {
            if (empty($campo)) {
                continue;
            }

            $partes = explode('|', $campo);

            $colunas[] = [
                'rco_campo'         => $partes[1] ?? $campo,
                'rco_tabela'        => !empty($tabelas[$i])   ? $tabelas[$i]   : ($partes[0] ?? ''),
                'rco_alias'         => $aliases[$i]   ?? null,
                'rco_label'         => $labels[$i]    ?? '',
                'rco_tamanho'       => !empty($tamanhos[$i])  ? (int) $tamanhos[$i]  : (int) ($partes[2] ?? 0),
                'rco_alinhamento'   => $alinhams[$i]  ?? 'E',
                'rco_totalizar'     => !empty($totalizar[$i]) ? 1 : 0,
                'rco_tipo_dado'     => !empty($tiposDado[$i]) ? $tiposDado[$i] : ($partes[3] ?? ''),
                'rco_largura'       => (int) ($larguras[$i] ?? 0),
                'rco_comportamento' => $comportaments[$i] ?? 'cortar',
            ];
        }

        return $colunas;
    }

    /**
     * Extrai do POST os campos de cabeçalho (DOCUMENTO) como array para
     * ConfigRelCamposCabModel::sincronizarCamposCab(). Mesmo formato composto
     * "tabela|campo|tamanho|tipo" de rco_campo (EntCfgRelColunas).
     */
    private function _extrairCamposCabPost(array $postado): array
    {
        $camposCab = [];

        $campos       = $postado['rcc_campo']        ?? [];
        $tabelas      = $postado['rcc_tabela']        ?? [];
        $labels       = $postado['rcc_label']         ?? [];
        $tamanhos     = $postado['rcc_tamanho']        ?? [];
        $tiposDado    = $postado['rcc_tipo_dado']      ?? [];
        $largurasCol  = $postado['rcc_largura_col']    ?? [];

        foreach ($campos as $i => $campo) {
            if (empty($campo)) {
                continue;
            }

            $partes = explode('|', $campo);

            $camposCab[] = [
                'rcc_campo'       => $partes[1] ?? $campo,
                'rcc_tabela'      => !empty($tabelas[$i])  ? $tabelas[$i]  : ($partes[0] ?? ''),
                'rcc_label'       => $labels[$i]           ?? '',
                'rcc_tamanho'     => !empty($tamanhos[$i]) ? (int) $tamanhos[$i] : (int) ($partes[2] ?? 0),
                'rcc_tipo_dado'   => !empty($tiposDado[$i]) ? $tiposDado[$i] : ($partes[3] ?? ''),
                'rcc_largura_col' => $largurasCol[$i]      ?? 'col-3',
            ];
        }

        return $camposCab;
    }

    /**
     * Extrai do POST as colunas da grade "Tabela" (DOCUMENTO) como array para
     * ConfigRelColunasDocModel::sincronizarColunasDoc(). Mesmo formato composto
     * "tabela|campo|tamanho|tipo" de rco_campo (EntCfgRelColunas).
     */
    private function _extrairColunasDocPost(array $postado): array
    {
        $colunasDoc = [];

        $campos    = $postado['rct_campo']    ?? [];
        $tabelas   = $postado['rct_tabela']   ?? [];
        $labels    = $postado['rct_label']    ?? [];
        $tamanhos  = $postado['rct_tamanho']  ?? [];
        $tiposDado = $postado['rct_tipo_dado'] ?? [];
        $larguras  = $postado['rct_largura']  ?? [];

        foreach ($campos as $i => $campo) {
            if (empty($campo)) {
                continue;
            }

            $partes = explode('|', $campo);

            $colunasDoc[] = [
                'rct_campo'     => $partes[1] ?? $campo,
                'rct_tabela'    => !empty($tabelas[$i])  ? $tabelas[$i]  : ($partes[0] ?? ''),
                'rct_label'     => $labels[$i]           ?? '',
                'rct_tamanho'   => !empty($tamanhos[$i]) ? (int) $tamanhos[$i] : (int) ($partes[2] ?? 0),
                'rct_tipo_dado' => !empty($tiposDado[$i]) ? $tiposDado[$i] : ($partes[3] ?? ''),
                'rct_largura'   => (int) ($larguras[$i] ?? 0),
            ];
        }

        return $colunasDoc;
    }

    /**
     * Extrai do POST as linhas do Rodapé (DOCUMENTO) como array para
     * ConfigRelTextosModel::sincronizarTextosLivres(). Cada linha tem texto
     * digitado (rtx_texto) e/ou campo vinculado — formato composto
     * "tabela|campo" (rtx não guarda tamanho/tipo — é sempre exibido como
     * texto/box, nunca como coluna tabular). Linha sem texto E sem campo é
     * descartada; rótulo é opcional.
     */
    private function _extrairTextosPost(array $postado): array
    {
        $textos = [];

        $campos      = $postado['rtx_campo']  ?? [];
        $tabelas     = $postado['rtx_tabela'] ?? [];
        $labels      = $postado['rtx_label']  ?? [];
        $textosDig   = $postado['rtx_texto']  ?? [];

        // índices de qualquer um dos arrays paralelos — uma linha pode ter só
        // texto (rtx_campo vazio) ou só campo
        $indices = array_unique(array_merge(array_keys($campos), array_keys($textosDig)));
        sort($indices);

        foreach ($indices as $i) {
            $campo = $campos[$i]    ?? '';
            $texto = trim((string) ($textosDig[$i] ?? ''));

            if (empty($campo) && $texto === '') {
                continue;
            }

            $partes = empty($campo) ? [] : explode('|', $campo);

            $textos[] = [
                'rtx_campo'  => empty($campo) ? null : ($partes[1] ?? $campo),
                'rtx_tabela' => empty($campo) ? null : (!empty($tabelas[$i]) ? $tabelas[$i] : ($partes[0] ?? '')),
                'rtx_texto'  => $texto !== '' ? $texto : null,
                'rtx_label'  => $labels[$i] ?? '',
            ];
        }

        return $textos;
    }

    /**
     * Reconstrói cfg_rel_joins a partir das tabelas distintas usadas pelas
     * colunas/campos gravados, dentro do GRUPO informado. Usa
     * getRelacionamentos() para obter o ON.
     *
     * Retrocompatível com o Tabular: chamado sem $grupo/$tabelasDistintasForcadas,
     * $grupo assume 'CABECALHO' (default da coluna rjo_grupo) e a lista de
     * tabelas distintas continua vindo de cfg_rel_colunas (via $this->colunas),
     * EXATAMENTE como antes da introdução do Documento.
     *
     * Único chamador hoje: _storeTabular(), sem $grupo/$tabelasDistintasForcadas
     * (comportamento 100% original). O tipo DOCUMENTO NÃO chama este método —
     * decisão do usuário: o Documento não faz join automático entre
     * rel_tabela_base/rel_tabela_detalhe (cada aba só referencia a própria
     * tabela configurada, reforçado pelo picker — ver Buscas::
     * busca_campos_tabela_unica()); se for preciso combinar tabelas, a saída é
     * criar uma VIEW no banco. Os parâmetros $grupo/$tabelasDistintasForcadas
     * (e a coluna cfg_rel_joins.rjo_grupo) continuam existindo — generalização
     * inofensiva, só sem uso pelo Documento — e podem ser reaproveitados no
     * futuro se essa decisão mudar.
     *
     * @param  int         $rel_id
     * @param  string      $tabelaBase                  Tabela "principal" do grupo (base ou detalhe)
     * @param  string      $grupo                       'CABECALHO' (default) | 'TABELA'
     * @param  string[]|null $tabelasDistintasForcadas  Quando informado, usado no lugar da
     *                                                   consulta a cfg_rel_colunas (Documento)
     */
    private function _sincronizarJoins(int $rel_id, string $tabelaBase, string $grupo = 'CABECALHO', ?array $tabelasDistintasForcadas = null): void
    {
        if ($tabelasDistintasForcadas !== null) {
            $tabelasDistintas = array_fill_keys($tabelasDistintasForcadas, true);
        } else {
            $colunasSalvas    = $this->colunas->getColunas($rel_id);
            $tabelasDistintas = [];
            foreach ($colunasSalvas as $col) {
                if (!empty($col->rco_tabela) && $col->rco_tabela !== $tabelaBase) {
                    $tabelasDistintas[$col->rco_tabela] = true;
                }
            }
        }

        if (empty($tabelasDistintas)) {
            $this->joins->sincronizarJoins($rel_id, [], $grupo);
            return;
        }

        $rels  = $this->dicionario->getRelacionamentos($tabelaBase);
        // debug($rels);
        $relMap = [];
        foreach ($rels['relacionamentos'] as $r) {
            if (!empty($r['REFERENCED_TABLE_NAME'])) {
                $tabRef = $r['REFERENCED_TABLE_NAME'];
                if (!isset($relMap[$tabRef])) {
                    $relMap[$tabRef] = $r['TABLE_NAME'] . '.' . $r['COLUMN_NAME']
                        . ' = ' . $r['REFERENCED_TABLE_NAME'] . '.' . $r['REFERENCED_COLUMN_NAME'];
                }
            }
        }

        $joins = [];
        foreach (array_keys($tabelasDistintas) as $tab) {
            if (!isset($relMap[$tab])) {
                continue;
            }
            $joins[] = [
                'rjo_tipo_join'   => 'LEFT',
                'rjo_tabela_join' => $tab,
                'rjo_alias_join'  => null,
                'rjo_condicao_on' => $relMap[$tab],
            ];
        }

        $this->joins->sincronizarJoins($rel_id, $joins, $grupo);
    }

    /**
     * Monta o bloco <script> injetado na view, ativando os botões
     * das tabelas repetíveis e inicializando o contador de largura.
     */
    private function _scriptTela(int $charsLinha = 0): string
    {
        $urlCharsLinha       = base_url('CfgRelatorio/charsLinha');
        $urlCamposFil        = base_url('buscas/busca_campos_filtro_rel');
        $urlPreview          = base_url('CfgRelatorio/previewRelatorio');
        // *claude* usados só pelo tipo DOCUMENTO — preview próprio, reload da
        // tela de criação ao trocar rel_tipo_saida, e feedback ao vivo da
        // Regra 4 (rel_detalhe_campo_vinculo calculado) — ver my_relatorio.js
        $urlPreviewDocumento = base_url('CfgRelatorio/previewDocumento');
        $urlAddRelatorio     = base_url('CfgRelatorio/add');
        $urlCampoVinculo     = base_url('buscas/busca_campo_vinculo_rel');
        // *claude* Regra B — popula rcc_campo/rct_campo/rtx_campo (agora
        // crSelect() puro, não mais crDepende()) com os campos de
        // rel_tabela_base + rel_tabela_detalhe juntos — ver
        // atualizaCamposDocumento() em my_relatorio.js.
        $urlCamposDocumento  = base_url('buscas/busca_campos_tabela_unica');

        return "<script>
            var charsLinha    = {$charsLinha};
            var urlCharsLinha = '{$urlCharsLinha}';
            var urlCamposFil  = '{$urlCamposFil}';
            var urlPreview    = '{$urlPreview}';
            var urlPreviewDocumento = '{$urlPreviewDocumento}';
            var urlAddRelatorio     = '{$urlAddRelatorio}';
            var urlCampoVinculo     = '{$urlCampoVinculo}';
            var urlCamposDocumento  = '{$urlCamposDocumento}';
        </script>";
    }
}
