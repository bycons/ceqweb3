<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\Config\ConfigDicDadosModel;
use App\Models\Config\ConfigEtiquetaModel;
use App\Models\Config\ConfigImpressoraModel;
use App\Models\Config\ConfigMenuModel;
use App\Models\Config\ConfigModuloModel;
use App\Models\Config\ConfigStatusModel;
use App\Models\Config\ConfigTelaModel;
use App\Models\Config\ConfigUsuarioModel;
use App\Models\Estoqu\EstoquDepositoModel;
use App\Models\Estoqu\EstoquRequisicaoModel;
use App\Models\Estoqu\EstoquTipoMovimentacaoModel;
use App\Models\Ocorre\OcorreSubtOcorrenciaModel;
use App\Models\Ocorre\OcorreTipoAcaoModel;
use App\Models\Ocorre\OcorreTipoOcorrenciaModel;
use App\Models\Produt\ProdutFamiliaModel;
use App\Models\Produt\ProdutIngredienteModel;
use App\Models\Produt\ProdutLoteModel;
use App\Models\Produt\ProdutProdutoModel;
use stdClass;

class Buscas extends BaseController
{
    public $data = [];
    public $menu;
    public $modulo;
    public $tela;
    public $usuario;
    public $admDados;
    public $status;


    public function __construct()
    {
        $this->menu     = new ConfigMenuModel();
        $this->modulo   = new ConfigModuloModel();
        $this->tela     = new ConfigTelaModel();
        $this->usuario  = new ConfigUsuarioModel();
        $this->admDados = new ConfigDicDadosModel();
        $this->status   = new ConfigStatusModel();
    }

    public function busca_hierarquia()
    {
        $hierarquia = new \stdClass();

        if ($_REQUEST['campo']) {
            $termo = $_REQUEST['campo'][0]['id_dep'];

            if ($termo == 1) {
                $hierarquia->{2} = 'Pai';
                $hierarquia->{3} = 'Filho';
            } else {
                $hierarquia->{1} = 'Órfão';
                $hierarquia->{3} = 'Filho';
                $hierarquia->{4} = 'Neto';
            }
        }

        echo json_encode($hierarquia);
    }

    public function busca_menu_pai()
    {
        $menus = $this->menu->getMenuPai();
        $menu_pai = new \stdClass();

        foreach ($menus as $m) {
            $menu_pai->{$m->men_id} = $m->men_etiqueta;
        }

        echo json_encode($menu_pai);
    }

    public function busca_submenu()
    {
        $ret = [];

        if ($_REQUEST['busca']) {
            $submenus = $this->menu->getSubMenu($_REQUEST['busca']);

            if (empty($submenus)) {
                $o = new \stdClass();
                $o->id = '-1';
                $o->text = 'SubMenu não encontrada...';
                $ret[] = $o;
            } else {
                // debug($submenus, true);
                foreach ($submenus as $s) {
                    $o = new \stdClass();
                    $o->id = $s['men_id'];
                    $o->text = $s['men_etiqueta'];
                    $ret[] = $o;
                }
            }
        }

        echo json_encode($ret);
        exit;
    }

    public function busca_modulo()
    {
        $ret = [];

        if ($_REQUEST['busca']) {
            $modulos = $this->modulo->getModulosSearch($_REQUEST['busca']);

            if (empty($modulos)) {
                $o = new \stdClass();
                $o->id = '-1';
                $o->text = 'Módulo não encontrado...';
                $ret[] = $o;
            } else {
                foreach ($modulos as $m) {
                    $o = new \stdClass();
                    $o->id = $m->mod_id;
                    $o->text = $m->mod_nome;
                    $o->icone = $m->mod_icone;
                    $ret[] = $o;
                }
            }
        }

        echo json_encode($ret);
        exit;
    }

    public function busca_modulo_id()
    {
        $ret = [];

        if ($_REQUEST['busca']) {
            $modulos = $this->modulo->getModulo($_REQUEST['busca']);

            if (empty($modulos)) {
                $o = new \stdClass();
                $o->id = '-1';
                $o->text = 'Módulo não encontrada...';
                $ret[] = $o;
            } else {
                foreach ($modulos as $m) {
                    $o = new \stdClass();
                    $o->id = $m->mod_id;
                    $o->text = $m->mod_nome;
                    $o->icone = $m->mod_icone;
                    $ret[] = $o;
                }
            }
        }

        echo json_encode($ret);
        exit;
    }

    public function busca_menu()
    {
        $tipo = $_REQUEST['campo'][0]['id_dep'];
        $menus = $this->menu->getMenuModulo($tipo);

        $menu = new \stdClass();

        foreach ($menus as $m) {
            $menu->{$m->men_id} = $m->men_nome;
        }

        echo json_encode($menu);
        exit;
    }

    public function busca_tela_modulo()
    {
        $ret    = [];
        if ($_REQUEST['busca']) {
            $termo              = $_REQUEST['busca'];
            $telas = $this->tela->getTelaModulo($termo);
            if (sizeof($telas) <= 0) {
                $ret[0]['id'] = '-1';
                $ret[0]['text'] = 'Tela não encontrada...';
            } else {
                for ($c = 0; $c < sizeof($telas); $c++) {
                    $ret[$c]['id']      = $telas[$c]->tel_id;
                    $ret[$c]['text']    = $telas[$c]->tel_nome;
                    $ret[$c]['icone']   = $telas[$c]->tel_icone;
                }
                $ret[$c]['id'] = '-1';
                $ret[$c]['text'] = 'Sem tela...';
            }
        }
        echo json_encode($ret);
        exit;
    }

    public function busca_tabelas_modulo()
    {
        $ret   = [];
        $modId = $_REQUEST['busca'] ?? null;
        // *claude* rel_tabela_base do tipo DOCUMENTO (CfgRelatorio) não pode
        // ser VIEW — só a instância que chama esse endpoint com
        // ?apenas_tabela=1 (EntCfgRelatorios::defCampos()) restringe a
        // BASE TABLE; Tabular e rel_tabela_detalhe continuam podendo listar
        // views, comportamento idêntico ao de sempre (2º parâmetro de
        // getTabelasPorDbGroup() já filtra table_type quando false).
        $includeViews = empty($_REQUEST['apenas_tabela']);

        if ($modId) {
            $mod = $this->modulo->find((int) $modId);
            if ($mod && !empty($mod->mod_dbgroup)) {
                $tabelas = $this->admDados->getTabelasPorDbGroup($mod->mod_dbgroup, $includeViews);
                foreach ($tabelas as $i => $t) {
                    $ret[$i]['id']   = $t['table_name'];
                    $ret[$i]['text'] = $t['table_name'] . ' - ' . $t['table_comment'];
                }
            }
            if (empty($ret)) {
                $ret[0]['id']   = '-1';
                $ret[0]['text'] = 'Nenhuma tabela encontrada';
            }
        }

        echo json_encode($ret);
        exit;
    }

    public function busca_campos_filtro_rel()
    {
        $ret    = [];
        $tabela = $_REQUEST['busca'] ?? null;

        if ($tabela) {
            // Persiste a tabela base atual na sessão — usada por CfgRelatorio::addFiltro/addColuna
            session()->set('rel_tabela_base_atual', $tabela);

            $campos = $this->admDados->getCampos($tabela);

            // Verifica se é VIEW — se for, todos os campos podem ser filtro
            $dbGrSche = $this->admDados->getDbGroupAndSchema($tabela);
            $dbInfo   = db_connect($dbGrSche['dbGroup']);
            $chkView  = $dbInfo->table('information_schema.TABLES')
                ->select('TABLE_TYPE')
                ->where('TABLE_NAME', $tabela)
                ->where('TABLE_SCHEMA', $dbGrSche['schema'])
                ->get();
            $rowView = $chkView ? $chkView->getFirstRow() : null;
            $isView  = ($rowView && $rowView->TABLE_TYPE === 'VIEW');

            $i = 0;
            foreach ($campos as $col) {
                if (str_ends_with($col['COLUMN_NAME'], '_excluido')) continue;

                $isDate = in_array(strtolower($col['DATA_TYPE']), ['date', 'datetime', 'timestamp']);
                $isFK   = $col['COLUMN_KEY'] !== '';

                // View: todos os campos não-inteiros são filtro | Tabela: apenas FK e date
                $isInt = in_array(strtolower($col['DATA_TYPE']), ['int', 'tinyint', 'smallint', 'mediumint', 'bigint']);
                if (($isView && !$isInt) || $isFK || $isDate) {
                    $tipo = $isDate ? 'DATE' : 'FK';
                    $ret[$i]['id']   = $col['COLUMN_NAME'] . '|' . $tabela . '|' . $tipo;
                    $ret[$i]['text'] = $col['NOME_COMPLETO'];
                    $i++;
                }
            }

            if (empty($ret)) {
                $ret[0]['id']   = '-1';
                $ret[0]['text'] = 'Nenhum campo encontrado';
            }
        }

        echo json_encode($ret);
        exit;
    }

    /**
     * Lista TODOS os campos de UMA OU DUAS tabelas literais (informadas em
     * `busca`, separadas por vírgula — ex.: "tabela_base,tabela_detalhe"),
     * sem a expansão pais/filhas/netas que busca_campos_colunas_rel() faz
     * para o Tabular. Usada pelos 3 pickers do tipo DOCUMENTO do gerador de
     * relatórios (CfgRelatorio) — Cabeçalho/Tabela/Textos Livres — que, por
     * decisão do usuário, podem referenciar campos de rel_tabela_base OU
     * rel_tabela_detalhe (as duas, em qualquer uma das 3 abas) — mas
     * continuam SEM join automático entre elas (cada campo pertence a uma
     * das duas tabelas, nunca combinado; ver CriamPdf2026::
     * _buscarDadosDocumento()). Se for preciso combinar mais tabelas além
     * dessas duas, quem configura o relatório cria uma VIEW no banco.
     *
     * Mesmo formato de retorno de busca_campos_colunas_rel() (tabela|campo|
     * tamanho|tipo, "[tabela] label"), só que estritamente escopado às
     * tabelas informadas — mesmo padrão de busca_campos_filtro_rel()
     * (Buscas.php acima), mas sem o filtro de FK/data/view (aqui entram
     * TODAS as colunas). Não faz distinct entre as duas tabelas — se ambas
     * tiverem uma coluna com o mesmo nome, aparecem como duas opções
     * distintas (o "[tabela]" no label já desambigua, e o value já é
     * "tabela|campo|...", nunca colide).
     */
    public function busca_campos_tabela_unica()
    {
        $ret     = [];
        $tabelas = array_filter(array_map('trim', explode(',', (string) ($_REQUEST['busca'] ?? ''))));

        $i = 0;
        foreach ($tabelas as $tabela) {
            $campos = $this->admDados->getCampos($tabela);

            foreach ($campos as $col) {
                if (str_ends_with($col['COLUMN_NAME'], '_excluido')) continue;

                $tamanho = $col['COLUMN_SIZE'] ?? 0;
                $tipo    = $col['DATA_TYPE'] ?? '';

                $ret[$i]['id']   = $tabela . '|' . $col['COLUMN_NAME'] . '|' . $tamanho . '|' . $tipo;
                $ret[$i]['text'] = '[' . $tabela . '] ' . $col['NOME_COMPLETO'];
                $i++;
            }
        }

        if (empty($ret)) {
            $ret[0]['id']   = '-1';
            $ret[0]['text'] = 'Nenhum campo encontrado';
        }

        echo json_encode($ret);
        exit;
    }

    /**
     * Devolve a coluna de `detalhe` que é FK (real ou potencial) pra PK de
     * `base` — feedback visual do campo "Coluna de Vínculo" (rel_detalhe_
     * campo_vinculo) da aba Dados Gerais do tipo DOCUMENTO (CfgRelatorio).
     * O valor é sempre RECALCULADO no servidor no store() real
     * (CfgRelatorio::_storeDocumento()) — isto aqui é só pra o admin ver o
     * resultado ao vivo enquanto configura, sem risco de inconsistência.
     */
    public function busca_campo_vinculo_rel()
    {
        $tabelaDetalhe = $_REQUEST['detalhe'] ?? null;
        $tabelaBase    = $_REQUEST['base'] ?? null;

        if (!$tabelaDetalhe || !$tabelaBase) {
            echo json_encode(['erro' => true, 'msg' => 'Informe a Tabela Cabeçalho e a Tabela Detalhe.']);
            exit;
        }

        $campo = $this->admDados->buscarCampoVinculo($tabelaDetalhe, $tabelaBase);

        if ($campo === null) {
            echo json_encode(['erro' => true, 'msg' => 'Nenhum campo relacionado encontrado.']);
            exit;
        }

        echo json_encode(['erro' => false, 'campo' => $campo]);
        exit;
    }

    public function busca_campos_colunas_rel()
    {
        $ret    = [];
        $tabela = $_REQUEST['busca'] ?? null;

        if ($tabela) {
            // Persiste a tabela base atual na sessão — usada por CfgRelatorio::addFiltro/addColuna
            session()->set('rel_tabela_base_atual', $tabela);

            $dbGrSche = $this->admDados->getDbGroupAndSchema($tabela);
            $dbInfo   = db_connect($dbGrSche['dbGroup']);

            // ── Pais: tabelas referenciadas pela base via FK ─────────────
            $rels = $this->admDados->getRelacionamentos($tabela);
            $pais = [];
            foreach ($rels['relacionamentos'] as $r) {
                if ($r['TABLE_NAME'] === $tabela && !empty($r['REFERENCED_TABLE_NAME'])) {
                    $pais[$r['REFERENCED_TABLE_NAME']] = true;
                }
            }
            $pais = array_keys($pais);

            // ── Filhas: tabelas que têm a PK da base ─────────────────────
            $pkBase = '';
            foreach ($this->admDados->getCampos($tabela) as $c) {
                if ($c['COLUMN_KEY'] === 'PRI') {
                    $pkBase = $c['COLUMN_NAME'];
                    break;
                }
            }

            $filhas = [];
            if ($pkBase) {
                $result = $dbInfo->table('information_schema.COLUMNS c')
                    ->distinct()->select('c.TABLE_NAME')
                    ->join('information_schema.TABLES t', 't.TABLE_NAME = c.TABLE_NAME AND t.TABLE_SCHEMA = c.TABLE_SCHEMA', 'inner')
                    ->where('c.COLUMN_NAME', $pkBase)
                    ->where('c.TABLE_SCHEMA', $dbGrSche['schema'])
                    ->where('c.TABLE_NAME !=', $tabela)
                    ->where('t.TABLE_TYPE', 'BASE TABLE')
                    ->get();
                $filhas = array_column($result ? $result->getResultArray() : [], 'TABLE_NAME');
            }

            // ── Netas: tabelas que têm a PK de alguma filha ──────────────
            $netas = [];
            foreach ($filhas as $filha) {
                $pkFilha = '';
                foreach ($this->admDados->getCampos($filha) as $c) {
                    if ($c['COLUMN_KEY'] === 'PRI') {
                        $pkFilha = $c['COLUMN_NAME'];
                        break;
                    }
                }
                if (!$pkFilha) continue;

                $result = $dbInfo->table('information_schema.COLUMNS c')
                    ->distinct()->select('c.TABLE_NAME')
                    ->join('information_schema.TABLES t', 't.TABLE_NAME = c.TABLE_NAME AND t.TABLE_SCHEMA = c.TABLE_SCHEMA', 'inner')
                    ->where('c.COLUMN_NAME', $pkFilha)
                    ->where('c.TABLE_SCHEMA', $dbGrSche['schema'])
                    ->where('c.TABLE_NAME !=', $filha)
                    ->where('c.TABLE_NAME !=', $tabela)
                    ->where('t.TABLE_TYPE', 'BASE TABLE')
                    ->get();
                $netasFilha = array_column($result ? $result->getResultArray() : [], 'TABLE_NAME');
                $netas = array_merge($netas, $netasFilha);
            }
            $netas = array_unique($netas);

            // Monta lista: base + filhas + netas + pais (sem duplicatas)
            $filhasENetas = array_unique(array_merge($filhas, $netas));
            $tabelas = array_unique(array_merge([$tabela], $filhasENetas, $pais));

            // Exceções para campos específicos
            $camposEspecificos = [
                'cfg_cor' => ['cor_nome_rgb'],
            ];

            $i = 0;
            foreach ($tabelas as $tab) {
                $campos      = $this->admDados->getCampos($tab);
                $ehBase      = ($tab === $tabela);
                $ehFilha     = in_array($tab, $filhasENetas);
                $todosCampos = ($ehBase || $ehFilha);
                $especifico  = $camposEspecificos[$tab] ?? null;

                foreach ($campos as $col) {
                    $nome = $col['COLUMN_NAME'];
                    if (str_ends_with($nome, '_excluido')) continue;

                    if ($especifico !== null) {
                        if (!in_array($nome, $especifico)) continue;
                    } elseif ($todosCampos) {
                        if (str_ends_with($nome, '_id')) continue;
                    } else {
                        if (!str_ends_with($nome, '_nome')) continue;
                    }

                    $tamanho = $col['COLUMN_SIZE'] ?? 0;
                    $tipo    = $col['DATA_TYPE'] ?? '';

                    $ret[$i]['id']   = $tab . '|' . $nome . '|' . $tamanho . '|' . $tipo;
                    $ret[$i]['text'] = '[' . $tab . '] ' . $col['NOME_COMPLETO'];
                    $i++;
                }
            }

            if (empty($ret)) {
                $ret[0]['id']   = '-1';
                $ret[0]['text'] = 'Nenhum campo encontrado';
            }
        }

        echo json_encode($ret);
        exit;
    }

    public function busca_tela()
    {
        $ret = [];

        if ($_REQUEST['busca']) {
            $telas = $this->tela->getTelaSearch($_REQUEST['busca']);

            if (empty($telas)) {
                $o = new \stdClass();
                $o->id = '-1';
                $o->text = 'Tela não encontrada...';
                $ret[] = $o;
            } else {
                foreach ($telas as $t) {
                    $o = new \stdClass();
                    $o->id = $t->tel_id;
                    $o->text = $t->tel_nome;
                    $o->icone = $t->tel_icone;
                    $ret[] = $o;
                }
            }
        }

        echo json_encode($ret);
        exit;
    }

    public function busca_tela_id()
    {
        $ret = [];

        if ($_REQUEST['busca']) {
            $class = $this->tela->getTelaId($_REQUEST['busca']);

            if (empty($class)) {
                $o = new \stdClass();
                $o->id = '-1';
                $o->text = 'Tela não encontrada...';
                $ret[] = $o;
            } else {
                foreach ($class as $t) {
                    $o = new \stdClass();
                    $o->id    = $t->tel_id;
                    $o->text  = $t->tel_nome;
                    $o->icone = $t->tel_icone;
                    $ret[] = $o;
                }
            }
        }

        echo json_encode($ret);
        exit;
    }

    public function busca_status_tela()
    {
        $ret = [];

        if ($_REQUEST['busca']) {
            $status = $this->status->getStatusTela($_REQUEST['busca']);

            if (empty($status)) {
                $o = new \stdClass();
                $o->id = '-1';
                $o->text = 'Tela não encontrada...';
                $ret[] = $o;
            } else {
                foreach ($status as $s) {
                    $o = new \stdClass();
                    $o->id   = $s->stt_id;
                    $o->text = $s->stt_nome;
                    $ret[] = $o;
                }
            }
        }

        echo json_encode($ret);
        exit;
    }

    public function busca_menupai()
    {
        $tipo  = $_REQUEST['campo'][0]['id_dep'];
        $menus = $this->menu->getMenuModulo($tipo);

        $menu = new \stdClass();

        foreach ($menus as $m) {
            $menu->{$m->men_id} = $m->men_nome;
        }

        echo json_encode($menu);
        exit;
    }

    public function busca_tabela()
    {
        $ret = [];

        if ($_REQUEST['busca']) {
            $tabelas = $this->admDados->getTabelaSearch($_REQUEST['busca']);

            if (empty($tabelas)) {
                $o = new \stdClass();
                $o->id = '-1';
                $o->text = 'Tabela não encontrada...';
                $ret[] = $o;
            } else {
                foreach ($tabelas as $t) {
                    $o = new \stdClass();
                    $o->id   = $t->table_name;
                    $o->text = $t->table_name;
                    $ret[] = $o;
                }
            }
        }

        echo json_encode($ret);
    }

    public function busca_familia()
    {
        $ret = [];

        if ($_REQUEST['busca']) {
            $familias = (new ProdutFamiliaModel())->getFamiliaOrigem($_REQUEST['busca']);

            if (empty($familias)) {
                $o = new \stdClass();
                $o->id = '-1';
                $o->text = 'Família não encontrada...';
                $ret[] = $o;
            } else {
                foreach ($familias as $f) {
                    $o = new \stdClass();
                    $o->id   = $f->fam_codFam;
                    $o->text = $f->fam_codDescricao;
                    $ret[] = $o;
                }
            }
        }

        echo json_encode($ret);
    }

    public function buscaProdutoClasse()
    {
        $ret = [];

        if ($_REQUEST['busca']) {
            $produtos = (new ProdutProdutoModel())->getProdutoClasse($_REQUEST['busca']);

            if (empty($produtos)) {
                $o = new \stdClass();
                $o->id = '-1';
                $o->text = 'Produto não encontrado...';
                $ret[] = $o;
            } else {
                foreach ($produtos as $p) {
                    $o = new \stdClass();
                    $o->id   = $p->pro_id;
                    $o->text = $p->pro_desinf;
                    $ret[] = $o;
                }
            }
        }

        echo json_encode($ret);
    }

    public function buscaDescricaoProdutoClasse()
    {
        $ret = [];

        if ($_REQUEST['busca']) {
            $produtos = (new ProdutProdutoModel())->getProdutoClasse($_REQUEST['busca']);

            if (empty($produtos)) {
                $o = new \stdClass();
                $o->id = '-1';
                $o->text = 'Produto não encontrado...';
                $ret[] = $o;
            } else {
                foreach ($produtos as $p) {
                    $o = new \stdClass();
                    $o->id   = $p->pro_id;
                    $o->text = $p->pro_despro;
                    $ret[] = $o;
                }
            }
        }

        echo json_encode($ret);
    }

    public function buscaProdutoClasseSemIngrediente($ing = 0)
    {
        $ret = [];

        if (!empty($_REQUEST['busca'])) {
            $prods = new ProdutProdutoModel();

            if ($ing != 0) {
                $produtos = $prods->getProdutoClasse($_REQUEST['busca'], $ing);
            } else {
                $produtos = $prods->getProdutoSemIngrediente(false, $_REQUEST['busca']);
            }

            if (empty($produtos)) {
                $o = new \stdClass();
                $o->id = '-1';
                $o->text = 'Produto não encontrado...';
                $ret[] = $o;
            } else {
                foreach ($produtos as $p) {
                    $o = new \stdClass();
                    $o->id   = $p->pro_id;
                    $o->text = $p->pro_despro;
                    $ret[] = $o;
                }
            }
        }

        return $this->response->setJSON($ret);
    }

    public function buscaProduto()
    {
        $ret = [];

        if ($_REQUEST['busca']) {
            $prods    = new ProdutProdutoModel();
            $produtos = $prods->getProdutoSearch($_REQUEST['busca']);

            if (empty($produtos)) {
                $o = new \stdClass();
                $o->id   = '-1';
                $o->text = 'Produto não encontrado...';
                $ret[] = $o;
            } else {
                foreach ($produtos as $p) {
                    $o = new \stdClass();
                    $o->id   = $p->pro_id;
                    $o->text = $p->pro_despro;
                    $ret[] = $o;
                }
            }
        }

        echo json_encode($ret);
    }

    public function buscaProdutoporLote()
    {
        $ret = new \stdClass();
        $busca = $this->request->getVar('busca');
        // debug($busca);
        if (!empty($busca)) {
            $sutid = $this->request->getVar('sutid');
            $classes = (new OcorreSubtOcorrenciaModel())->getClassePorSubtipo($sutid) ?? null;
            $idsClasses = array_column($classes, 'cla_id');
            $lotesm = new ProdutLoteModel();
            $lote   = $lotesm->getLoteClasse($busca, $idsClasses);
            // debug($lote, true);

            if (empty($lote)) {
                $ret->lotid = '-1';
            } else {
                $ret->proid     = $lote[0]->pro_id;
                $ret->lotid     = $lote[0]->lot_id;
                $ret->despro    = $lote[0]->pro_despro;
                $ret->validade  = $lote[0]->lot_validade ?? '';
                $ret->Fabrica   = $lote[0]->fab_nomFab ?? '';
                $ret->codErp    = $lote[0]->lot_codpro ?? '';
            }
        }

        return $this->response->setJSON($ret);
    }

    public function buscaProdutoDeposito()
    {
        $ret = [];

        if ($_REQUEST['busca']) {
            $produtos = (new ProdutProdutoModel())->getProdutoEstoqueCeqweb(false, $_REQUEST['busca']);
            // debug($produtos, true);
            if (empty($produtos)) {
                $o = new \stdClass();
                $o->id = '-1';
                $o->text = 'Produto não encontrado...';
                $ret[] = $o;
            } else {
                foreach ($produtos as $p) {
                    $o = new \stdClass();
                    $o->id   = $p->pro_id;
                    $o->text = $p->pro_coddes;
                    $ret[] = $o;
                }
            }
            // debug($ret);
        }

        echo json_encode($ret);
    }

    public function buscaIngredienteClasse()
    {
        $ret = [];

        if (!empty($_REQUEST['busca'])) {
            $ingreds      = new ProdutIngredienteModel();
            $ingredientes = $ingreds->getIngredienteClasse($_REQUEST['busca']);

            if (empty($ingredientes)) {
                $o = new \stdClass();
                $o->id   = '-1';
                $o->text = 'Ingrediente não encontrado...';
                $ret[] = $o;
            } else {
                foreach ($ingredientes as $ing) {
                    $o = new \stdClass();
                    $o->id   = $ing->ing_id;
                    $o->text = $ing->ing_nome;
                    $ret[] = $o;
                }
            }
        }

        return $this->response->setJSON($ret);
    }

    public function buscaTipoMovimentacao()
    {
        $ret = new \stdClass();

        if (!empty($_REQUEST['busca'])) {
            $tmovs    = new EstoquTipoMovimentacaoModel();
            $tmovimen = $tmovs->getTipoMovimentacaoParaRequisicao($_REQUEST['busca']);
            if (empty($tmovimen)) {
                $ret->id   = '-1';
                $ret->text = 'Tipo de Movimentação não encontrado...';
            } else {
                // debug($tmovimen[0], true);
                $ret->id     = $tmovimen[0]->tmo_id;
                $ret->depori = $tmovimen[0]->dep_codorigem;
                $ret->depdes = $tmovimen[0]->dep_coddestino;
                $ret->deppad = $tmovimen[0]->dep_codpadrao;
            }
        }

        return $this->response->setJSON($ret);
    }

    public function buscaTipoAcao()
    {
        $ret = new \stdClass();

        if (!empty($_REQUEST['busca'])) {
            $tacao  = new OcorreTipoAcaoModel();
            $tacaos = $tacao->getTipoAcao($_REQUEST['busca']);

            if (empty($tacaos)) {
                $ret->id   = '-1';
                $ret->text = 'Tipo de Movimentação não encontrado...';
            } else {
                $ret->id   = $tacaos[0]->tpa_id;
                $ret->acao = $tacaos[0]->tpa_tipo;
            }
        }

        return $this->response->setJSON($ret);
    }

    public function buscatelasaplicaveis()
    {
        $ret = [];

        if ($_REQUEST['tipoocor']) {
            $ttipo  = new OcorreTipoOcorrenciaModel();
            $ttelas = $ttipo->getTOTelasAplicaveis($_REQUEST['tipoocor']);

            if (empty($ttelas)) {
                $o = new \stdClass();
                $o->id   = '-1';
                $o->text = 'Tipo de Ocorrência não encontrado...';
                $ret[] = $o;
            } else {
                foreach ($ttelas as $t) {
                    $o = new \stdClass();
                    $o->id   = $t->tot_id;
                    $o->text = '';
                    $ret[] = $o;
                }
            }
        }

        echo json_encode($ret);
    }

    public function buscaetiqcontroler()
    {
        $ret = [];

        if ($_REQUEST['busca']) {
            $mtela = new ConfigTelaModel();
            $tela  = $mtela->getTelaSearch($_REQUEST['busca'])[0];
            // debug($tela, true);
            if ($tela) {
                $tetqs     = new ConfigEtiquetaModel();
                $tetquetas = $tetqs->getEtiquetaTela($tela->tel_id);

                if (empty($tetquetas)) {
                    $o = new \stdClass();
                    $o->id   = '-1';
                    $o->text = 'Etiqueta não encontrada...';
                    $ret[] = $o;
                } else {
                    foreach ($tetquetas as $etq) {
                        $o = new \stdClass();
                        $o->id   = $etq->etq_id;
                        $o->text = $etq->etq_nome;
                        $ret[] = $o;
                    }
                }
            } else {
                $o = new \stdClass();
                $o->id   = '-1';
                $o->text = 'Tela não encontrada...';
                $ret[] = $o;
            }
        }

        echo json_encode($ret);
    }

    public function buscaimpressoras()
    {
        $termo    = $this->request->getGetPost('busca');
        $model    = new ConfigImpressoraModel();
        $printers = $model->getImpressoraSearch($termo);

        $ret = [];

        if (empty($printers)) {
            $o = new \stdClass();
            $o->id   = -1;
            $o->text = 'Impressora não encontrada...';
            $ret[] = $o;
        } else {
            foreach ($printers as $p) {
                $o = new \stdClass();
                $o->id   = $p->imp_id;
                $o->text = $p->imp_nome;
                $ret[] = $o;
            }
        }

        return $this->response->setJSON($ret);
    }

    public function busca_tipo_movimentacao()
    {
        $ret = [];

        $tmoModel = new EstoquTipoMovimentacaoModel();
        $lista = $tmoModel->getTipoMovimentacao();

        foreach ($lista as $tmo) {
            $ret[] = [
                'id'   => $tmo->tmo_id,
                'text' => $tmo->tmo_nome
            ];
        }

        return $this->response->setJSON($ret);
    }

    public function busca_dep_destino()
    {
        $ret = [];

        if ($_REQUEST['busca']) {
            $destinos = new EstoquDepositoModel();
            $lst = $destinos->getDestino($_REQUEST['busca']);

            if (empty($lst)) {
                $o = new \stdClass();
                $o->id   = '-1';
                $o->text = 'Depósito de Destino não encontrado...';
                $ret[] = $o;
            } else {
                foreach ($lst as $d) {
                    $o = new \stdClass();
                    $o->id   = $d->dep_codDep;
                    $o->text = $d->dep_desDep;
                    $ret[] = $o;
                }
            }
        }

        echo json_encode($ret);
    }

    public function gravasessao()
    {
        $ret = new \stdClass();

        if ($_REQUEST['msg']) {
            session()->setFlashdata('msg', $_REQUEST['msg']);
            $ret->erro = false;
        } else {
            $ret->erro = true;
        }

        echo json_encode($ret);
    }

    public function verSessao()
    {
        $ret = new \stdClass();
        $ret->sessao = session()->logged_in ?? false;
        echo json_encode($ret);
    }

    public function buscaAcoesPorTipo()
    {
        $ret = [];

        $busca = $_REQUEST['busca'] ?? null;

        if ($busca) {

            // Model correto para SUBTIPO
            $subtipoModel = new OcorreSubtOcorrenciaModel();

            // Método coerente com o que está buscando
            $lst = $subtipoModel->getSubtipoPorTipos($busca);

            if (empty($lst)) {

                $o = new \stdClass();
                $o->id   = -1;
                $o->text = 'Nenhum Subtipo encontrado';
                $ret[] = $o;
            } else {

                foreach ($lst as $l) {
                    $o = new \stdClass();
                    $o->id   = $l->sut_id;     // ID do Subtipo
                    $o->text = $l->sut_nome;   // Nome do Subtipo
                    $ret[] = $o;
                }
            }
        }

        echo json_encode($ret);
        exit;
    }

    public function buscaSubtipoPorTipo()
    {
        $ret = [];

        if ($_REQUEST['busca']) {
            $perfilId = session()->get('usu_perfil_id');
            $classe   = session()->get('cla_id') ?? false;
            $telaid   = session()->get('tel_id') ?? false;
            $subtipos = new OcorreSubtOcorrenciaModel();
            $lst = $subtipos->getSubtOcorrenciaPorTipo($_REQUEST['busca'], $perfilId, $classe, $telaid);
            if (empty($lst)) {
                $o = new \stdClass();
                $o->id   = -1;
                $o->text = 'Nenhum Subtipo encontrado';
                $ret[] = $o;
            } else {
                foreach ($lst as $l) {
                    $o = new \stdClass();
                    $o->id   = $l->sut_id;
                    $o->text = $l->sut_nome;
                    $ret[] = $o;
                }
            }
        }

        echo json_encode($ret);
        exit;
    }

    public function buscaClassePorTipo()
    {
        $ret = [];
        if ($_REQUEST['busca']) {
            $model = new OcorreTipoOcorrenciaModel();
            $lst = $model->getClassePorTipo($_REQUEST['busca']);
            if (empty($lst)) {
                $o = new \stdClass();
                $o->id   = -1;
                $o->text = 'Nenhuma Classe encontrada';
                $ret[] = $o;
            } else {
                foreach ($lst as $l) {
                    $o = new \stdClass();
                    $o->id   = $l->cla_id;
                    $o->text = $l->cla_nome;
                    $ret[] = $o;
                }
            }
        }
        echo json_encode($ret);
        exit;
    }

    public function verificaSessao()
    {
        $ret = new \stdClass();
        $ret->status = (session_status() === PHP_SESSION_ACTIVE)
            ? 'sessao_ativa'
            : 'sessao_expirada';

        echo json_encode($ret);
    }

    public function buscaPerfilPorTipo()
    {
        $ret = [];

        if ($_REQUEST['busca']) {
            $model = new OcorreTipoOcorrenciaModel();
            $lst = $model->getTipoOcorrenciaPermissao($_REQUEST['busca']);
            // debug($lst, true);
            if (empty($lst)) {
                $o = new \stdClass();
                $o->id   = -1;
                $o->text = 'Nenhum Tipo encontrado';
                $ret[] = $o;
            } else {
                foreach ($lst as $l) {
                    $o = new \stdClass();
                    $o->id   = $l->prf_id;
                    $o->text = $l->prf_nome;
                    $ret[] = $o;
                }
            }
        }

        echo json_encode($ret);
        exit;
    }

    public function busca_campo_tela(?string $busca = null)
    {
        $ret  = [];
        $etiq = true;

        $termo = $busca ?? (($_REQUEST['busca']) ?? null);
        // debug($termo, true);
        if ($termo) {
            $referer = ($_SERVER['HTTP_REFERER'] ?? null);

            if ($referer) {
                $path = parse_url($referer, PHP_URL_PATH); // extrai apenas "/produtos/editar/5"

                $segments = explode('/', trim($path, '/')); // quebra em ['produtos', 'editar', '5']

                $controller = $segments[0] ?? null; // pega 'produtos'

                if (strtolower($controller) != 'cfgetiqueta') {
                    $etiq = false;
                }
            }

            $telas = $this->tela->getTelaId($termo)[0];

            if (isset($telas->tel_model) && $telas->tel_model != null) {
                $model = $telas->tel_model;
                $compl_model = substr($model, 0, 6);
                $pasta = "App\\Models\\" . $compl_model . "\\";
                $model_atual = model($pasta . $model);
                $view   = $model_atual->view;
                if (isset($model_atual->viewoutra)) {
                    $view   = $model_atual->viewoutra;
                }
                $campos_tab = $this->admDados->getCampos($view);
                // debug($campos_tab);
                $campos_lis = array_column($campos_tab, 'NOME_COMPLETO', 'COLUMN_NAME');
                // $campos_tab = $this->admDados->getCampos($view);
                // $campos_lis = array_column($campos_tab, 'NOME_COMPLETO', 'COLUMN_NAME');

                if (sizeof($campos_lis) <= 0) {
                    $ret[0]['id'] = '-1';
                    $ret[0]['text'] = 'Campos não encontrados...';
                } else {
                    $c = 0;
                    if ($etiq) {
                        $ret[$c]['id']      = '99';
                        $ret[$c]['text']    = 'Texto Livre';
                        $c++;
                        $ret[$c]['id']      = '1';
                        $ret[$c]['text']    = 'Linha Horizontal';
                        $c++;
                        $ret[$c]['id']      = '2';
                        $ret[$c]['text']    = 'Data Atual';
                        $c++;
                        $ret[$c]['id']      = '3';
                        $ret[$c]['text']    = 'Data e Hora Atual';
                        $c++;
                        $ret[$c]['id']      = -10;
                        $ret[$c]['text']    = '';
                        $c++;
                    }
                    foreach ($campos_lis as $key => $value) {
                        $ret[$c]['id']      = $key;
                        $ret[$c]['text']    = $value;
                        $c++;
                    }
                }
            } else {
                $ret[0]['id'] = '-1';
                $ret[0]['text'] = 'Tela não Encontrada...';
            }
        }
        // debug($ret, true);
        if ($busca) {
            return $ret;
        } else {
            echo json_encode($ret);
        }
        exit;
    }

    public function buscaProdutoRequisicao($requis)
    {
        $ret = [];

        $produtos = (new EstoquRequisicaoModel)->getRequisicaoProdutos($requis);
        debug($produtos, true);
        if (!empty($produtos)) {
            $ret = $produtos;
        }
        // debug($ret);

        echo json_encode($ret);
    }


    public function buscaProdutosMergeEstoques(int $id, int|string $deposito, string $tipo): array
    {
        $modrequisicao = new EstoquRequisicaoModel();
        $modprodutos   = new ProdutProdutoModel();

        $produtos = $modrequisicao->getRequisicaoProdutos($id, $tipo);

        if ($produtos === []) {
            return [];
        }

        $pro_ids = array_unique(array_map(
            static fn(object $p): int|string => $p->pro_id,
            $produtos
        ));

        $resultadoIndexado = [];

        /*
     * IMPORTANTE:
     * Troque "lot_id" pelo nome real do campo do lote.
     * Exemplos possíveis: lot_id, lote_id, prl_id, rep_lote, lote.
     */
        foreach ($produtos as $produto) {
            $chave = $this->gerarChaveProdutoLote($produto);

            $resultadoIndexado[$chave] = (array) $produto;
        }

        $dados_ceq_produto = $modprodutos->getProdutoEstoqueCeqweb($pro_ids, $deposito);

        $prefixos = ['rep_id', 'pro_', 'prc_'];
        $dados_ceq_produto = $this->filtrarPorPrefixos($dados_ceq_produto, $prefixos);

        $dados_est_produto = $modprodutos->getProdutoEstoque($pro_ids, $deposito);

        /*
     * Estoque local.
     *
     * Se o estoque vier separado por lote, use também a chave composta.
     * Se vier apenas por produto, o mesmo estoque será aplicado em todos os lotes
     * daquele produto.
     */
        foreach ($dados_est_produto as $itemEstoque) {
            foreach ($resultadoIndexado as $chave => $produto) {
                if (($produto['pro_id'] ?? null) !== $itemEstoque->pro_id) {
                    continue;
                }

                $resultadoIndexado[$chave] = array_merge(
                    $produto,
                    (array) $itemEstoque
                );
            }
        }

        /*
     * Estoque CEQWeb.
     */
        foreach ($dados_ceq_produto as $itemCeq) {
            foreach ($resultadoIndexado as $chave => $produto) {
                if (($produto['pro_id'] ?? null) !== $itemCeq->pro_id) {
                    continue;
                }

                $resultadoIndexado[$chave] = array_merge(
                    $produto,
                    (array) $itemCeq
                );
            }
        }

        return array_values(array_map(
            static fn(array $item): object => (object) $item,
            $resultadoIndexado
        ));
    }

    private function gerarChaveProdutoLote(object $produto): string
    {
        /*
     * Ajuste aqui para o campo correto do lote.
     */
        $lote = $produto->lot_id
            ?? $produto->lote_id
            ?? $produto->prl_id
            ?? $produto->lote
            ?? 'sem_lote';

        return "{$produto->pro_id}_{$lote}";
    }

    public function filtrarPorPrefixos(array $itens, array $prefixos): array
    {
        $resultado = [];

        foreach ($itens as $item) {
            $objFiltrado = new \stdClass();

            foreach ($item as $chave => $valor) {
                foreach ($prefixos as $prefixo) {
                    if (str_starts_with($chave, $prefixo)) {
                        $objFiltrado->{$chave} = $valor;
                        break;
                    }
                }
            }

            $resultado[] = $objFiltrado;
        }

        return $resultado;
    }
}
