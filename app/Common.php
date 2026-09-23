<?php
// require('vendor/autoload.php');

use App\Libraries\Campos;
use App\Libraries\MyCampo;
use App\Models\Config\ConfigDicDadosModel;
use App\Models\Config\ConfigMenuModel;
use App\Models\Config\ConfigPerfilItemModel;
use App\Models\Config\ConfigRelatoriosModel;
use App\Models\Config\ConfigTelaListaModel;
use App\Models\Config\ConfigTelaModel;
use WebSocket\Client;

/**
 * The goal of this file is to allow developers a location
 * where they can overwrite core procedural functions and
 * replace them with their own. This file is loaded during
 * the bootstrap process and is called during the framework's
 * execution.
 *
 * This can be looked at as a `master helper` file that is
 * loaded early on, and may also contain additional functions
 * that you'd like to use throughout your entire application
 *
 * @see: https://codeigniter4.github.io/CodeIgniter4/
 */

/**
 * montaMenu
 * @param mixed $perfil_usu
 * @param mixed $tipo_usu
 * @param bool $ordenacao
 * @return array
 */
function montaMenu($perfil_usu, $tipo_usu, $ordenacao = false)
{
    // debug('Ordenação '.$ordenacao);
    // var_dump($ordenacao);
    $menu      = new ConfigMenuModel();
    $retorno   = $menu->getMenuCompleto($perfil_usu, $tipo_usu);
    $ret_menu  = [];
    $opc       = -1;
    $niv1      = -1;
    $niv2      = -1;
    $niv_atual = 0;
    // debug(count($retorno), true);
    for ($m = 0; $m < count($retorno); $m++) {
        $opc_menu = $retorno[$m];
        if ($opc_menu['men_hierarquia'] == '1') { //Raiz
            $opc++;
            if (strpbrk($opc_menu['pit_permissao'], 'C')) {
                $ret_menu[$opc] = $opc_menu;
            }
            $niv1      = -1;
            $niv2      = -1;
            $niv_atual = 0;
        }
        if ($opc_menu['men_hierarquia'] == '2') { //Menu
            $opc++;
            $niv1           = 0;
            $niv2           = -1;
            $niv_atual      = 1;
            $ret_menu[$opc] = $opc_menu;
        }
        if ($opc_menu['men_hierarquia'] == '3') { //Submenu
            $niv2                          = 0;
            $niv_atual                     = 2;
            $ret_menu[$opc]['niv1'][$niv1] = $opc_menu;
        }
        if ($opc_menu['men_hierarquia'] == '4') {
            if (strpbrk($opc_menu['pit_permissao'], 'C') || ! $perfil_usu) {
                if ($niv_atual == 2) {
                    if ($retorno[$m]['men_submenu_id'] > 0) {
                        $ret_menu[$opc]['niv1'][$niv1]['niv2'][$niv2] = $opc_menu;
                        $niv2++;
                    } else {
                        $niv1++;
                        $ret_menu[$opc]['niv1'][$niv1] = $opc_menu;
                    }
                    if (
                        $m < count($retorno) - 1
                        && $retorno[$m + 1]['men_hierarquia'] != '4'
                    ) {
                        $niv1++;
                    }
                } elseif ($niv_atual == 1) {
                    $ret_menu[$opc]['niv1'][$niv1] = $opc_menu;
                    $niv1++;
                } else {
                    if ($ret_menu[$opc - 1] != $opc_menu) {
                        $ret_menu[$opc] = $opc_menu;
                        $opc++;
                    }
                }
            } else {
                if (isset($retorno[$m + 1]['men_hierarquia']) && $retorno[$m + 1]['men_hierarquia'] != '4') {
                    $niv1++;
                }
            }
        }
    }

    // Injeta opção sintética "Relatórios" nos menus hierarquia 2 que tiverem
    // relatório cadastrado para o módulo, liberado ao perfil, sem tela vinculada.
    // Precisa rodar ANTES da limpeza de ramos vazios logo abaixo, para que um
    // cabeçalho que ganhe a opção "Relatórios" como único item não seja removido.
    $relatoriosModel = new ConfigRelatoriosModel();
    foreach ($ret_menu as $idxOpc => $itemMenu) {
        if (($itemMenu['men_hierarquia'] ?? null) != '2') {
            continue;
        }
        $modId = $itemMenu['mod_id'] ?? null;
        if (!$modId) {
            continue;
        }
        // debug($modId);
        // debug($perfil_usu);
        $relatoriosMod = $relatoriosModel->getRelatoriosPorModuloPerfil((int) $modId, (int) $perfil_usu);
        // debug($relatoriosMod);
        if (count($relatoriosMod) === 0) {
            continue;
        }
        $proxIdx = isset($ret_menu[$idxOpc]['niv1']) ? count($ret_menu[$idxOpc]['niv1']) : 0;
        $ret_menu[$idxOpc]['niv1'][$proxIdx] = [
            'men_id'         => 'rel_mod_' . $modId,
            'men_hierarquia' => '4',
            'men_menupai_id' => $itemMenu['men_id'] ?? null,
            'men_submenu_id' => null,
            'mod_id'         => $modId,
            'tel_id'         => null,
            'men_etiqueta'   => 'Relatórios',
            'men_icone'      => 'fas fa-chart-bar',
            'men_order'      => 9999,
            'men_metodo'     => 'index',
            // ANTES (BKP 30/06/2026): 'tel_controler' => 'Utils/Relatorio/index/' . $modId,
            'tel_controler'  => 'Relatorio/' . $modId,
            'pit_permissao'  => 'C',
        ];
    }

    // debug($ret_menu, true);
    $opc_menu = '';
    for ($m = count($ret_menu) - 1; $m >= 0; $m--) {
        // debug($opc_menu);
        // debug($ret_menu[$m]);
        if ($opc_menu == $ret_menu[$m]) {
            unset($ret_menu[$m]);
        } else {
            $opc_menu = $ret_menu[$m];
            if ($opc_menu['men_hierarquia'] == '2') {
                if (! isset($opc_menu['niv1']) || count($opc_menu['niv1']) == 0) {
                    unset($ret_menu[$m]);
                } else {
                    for ($s = count($opc_menu['niv1']) - 1; $s >= 0; $s--) {
                        $opc_sub = $opc_menu['niv1'][$s];
                        if ($opc_sub['men_hierarquia'] == '3') {
                            if (
                                ! array_key_exists('niv2', $opc_sub)
                                || ! isset($opc_sub['niv2'])
                                || count($opc_sub['niv2']) == 0
                            ) {
                                unset($ret_menu[$m]['niv1'][$s]);
                            }
                        }
                    }
                }
            }
        }
    }
    for ($m = count($ret_menu) - 1; $m >= 0; $m--) {
        if (isset($ret_menu[$m])) {
            $opc_menu = $ret_menu[$m];
            if ($opc_menu['men_hierarquia'] == '2') {
                if (! isset($opc_menu['niv1']) || count($opc_menu['niv1']) == 0) {
                    unset($ret_menu[$m]);
                }
            }
        }
    }
    $arr      = array_values($ret_menu);
    $ret_menu = $arr;
    return $ret_menu;
}

function montaListaTelas()
{
    $telas = new ConfigTelaModel();

    $lista_telas = $telas->getTelaPerfil();
    // debug($lista_telas, true);
    return $lista_telas;
}

function montaListaItensPerfil($perfil, $telas)
{
    $ret      = [];
    $itperfil = new ConfigPerfilItemModel();
    foreach ($telas as $key => $value) {
        $permis = $itperfil->getItemPerfilClasse($perfil, $key);
        if (count($permis) == 0) {
            $ret[$key] = '';
        } else {
            $ret[$key] = $permis[0]['pit_permissao'];
        }
    }
    return $ret;
}

/**
 * montaListagem
 * @param array $data_lis
 * @param string $chave
 * @return array
 */
function montaListagem($data_lis, $chave)
{
    $dicionario = new ConfigDicDadosModel();

    $lista      = $chave . $data_lis['listagem'];
    $arr_lista  = explode(',', $lista);
    $tabela     = $data_lis['tabela'];
    $arr_campos = [];
    array_push($arr_campos, $dicionario->getDetalhesCampo($tabela, $arr_lista));
    $cols = [];
    for ($l = 0; $l < count($arr_lista); $l++) {
        for ($c = 0; $c < count($arr_campos[0]); $c++) {
            $campo = $arr_campos[0][$c];
            if ($campo['COLUMN_NAME'] == $arr_lista[$l]) {
                array_push($cols, $arr_campos[0][$c]['COLUMN_COMMENT']);
            }
        }
    }
    array_push($cols, "Ação");

    return $cols;
}

function montaColunasLista($data_lis, $chave)
{
    $telaLista = new ConfigTelaListaModel();

    $lista      = $telaLista->getListagem($data_lis['tel_id']);
    $arr_campos = array_column($lista, 'lis_rotulo');
    if ($chave != '') {
        array_unshift($arr_campos, $chave);
    }

    $temacao     = $data_lis['temacao'] ?? true;
    if ($temacao) {
        array_push($arr_campos, "Ação");
    }

    // debug($arr_campos);
    return $arr_campos;
}

function montaColunasCampos($data_lis, $chave)
{
    $telaLista = new ConfigTelaListaModel();

    $lista      = $telaLista->getListagem($data_lis['tel_id']);
    $arr_campos = array_column($lista, 'lis_campo');
    array_unshift($arr_campos, $chave);
    // debug($arr_campos);e
    return $arr_campos;
}

function montaListaColunas($data_lis, $chave, $dados, $nome)
{
    $telaLista = new ConfigTelaListaModel();

    $lista  = $telaLista->getListagem($data_lis['tel_id']);
    $fields = array_column($lista, 'lis_campo');
    // Mostra o botão de exclusão por padrão
    $exclusao = isset($data_lis['exclusao']) ? $data_lis['exclusao'] : true;
    $edicao   = isset($data_lis['edicao']) ? $data_lis['edicao'] : true;
    array_unshift($fields, $chave);

    $temacao     = $data_lis['temacao'] ?? true;
    if ($temacao) {
        array_push($fields, 'acao');
    }
    $result = [];
    for ($p = 0; $p < sizeof($dados); $p++) {
        $dat_i        = $dados[$p];
        $temativo     = false;
        $ativo        = 0;
        $inativa      = '';
        $podeinativar = true;
        $podeeditar   = true;
        if (! isset($dat_i['tabela']) || $dat_i['tabela'] != 'cfg_status') {
            if (isset($dat_i['stt_exclusao'])) {
                if (trim($dat_i['stt_exclusao']) == 'N') {
                    $podeinativar = false;
                }
            }
            // debug($dat_i['stt_edicao']);
            if (isset($dat_i['stt_edicao'])) {
                if (trim($dat_i['stt_edicao']) == 'N') {
                    $podeeditar = false;
                }
            }
        }
        // VERIFICA SE TEM UM CAMPO DE ATIVO INATIVO
        foreach ($dat_i as $key => $value) {
            if (substr($key, -5) == 'ativo') {
                $temativo = true;
                // debug($value);
                if (trim($value) === 'A') {
                    $ativo = 1;
                }
                // debug('Ativo '.$ativo);
                break;
            }
        }
        $edit   = '';
        $exclui = '';
        if ((strlen($data_lis['permissao']) <= 3 && //se não tem todos os acessos
                strpbrk($data_lis['permissao'], 'C')) ||
            (strpbrk($data_lis['permissao'], 'C') &&
                ! $podeeditar)
        ) { // mas tem acesso de consulta
            $url_con          = $data_lis['controler'] . '/show/' . $dat_i[$chave];
            $bt_con           = new MyCampo();
            $bt_con->id       = $bt_con->nome       = 'bt_show';
            $bt_con->classep  = 'btn btn-outline-info btn-sm border-0 mx-0 fs-0';
            $bt_con->i_cone   = "<i class='far fa-eye'></i>";
            $bt_con->label    = "";
            $bt_con->place    = "Consulta";
            $bt_con->funcChan = "redireciona('{$url_con}',event)";
            $edit             = $bt_con->crBotao();
        }
        if (strpbrk($data_lis['permissao'], 'E')) {
            if ($podeeditar && $edicao) {
                $url_edi          = $data_lis['controler'] . '/edit/' . $dat_i[$chave];
                $bt_edt           = new MyCampo();
                $bt_edt->id       = $bt_edt->nome       = 'bt_edit';
                $bt_edt->classep  = 'btn btn-outline-warning btn-sm border-0 mx-0 fs-0';
                $bt_edt->i_cone   = "<i class='fas fa-edit'></i>";
                $bt_edt->label    = "";
                $bt_edt->place    = "Alterar";
                $bt_edt->funcChan = "redireciona('{$url_edi}',event)";
                $edit             = $bt_edt->crBotao();
            }
        }
        if ($temativo) {
            if ($podeinativar && strpbrk($data_lis['permissao'], 'X')) {
                $url_ati          = $data_lis['controler'] . '/ativinativ/' . $dat_i[$chave] . '/1';
                $bt_ati           = new MyCampo();
                $bt_ati->id       = $bt_ati->nome       = 'bt_ativinat';
                $bt_ati->label    = "";
                $bt_ati->i_cone   = "<i class='fa-solid fa-toggle-off fa-rotate-270'></i>";
                $bt_ati->place    = "Ativar";
                $bt_ati->classep  = 'btn btn-outline-secondary btn-sm border-0 mx-0 fs-0';
                $bt_ati->funcChan = "ativInativ('{$url_ati}','{$dat_i[$nome]}', false)";
                if ($ativo) {
                    // debug('etq_ativo '.$dat_i['etq_ativo']);
                    // debug('etq_nome '.$dat_i[$nome]);
                    $url_ina          = $data_lis['controler'] . '/ativinativ/' . $dat_i[$chave] . '/0';
                    $bt_ati->i_cone   = "<i class='fa-solid fa-toggle-on fa-rotate-270'></i>";
                    $bt_ati->place    = "Inativar";
                    $bt_ati->classep  = 'btn btn-outline-success btn-sm border-0 mx-0 fs-0';
                    $bt_ati->funcChan = "ativInativ('{$url_ina}','{$dat_i[$nome]}', true)";
                }
                $inativa = $bt_ati->crBotao();
            }
        }
        if (strpbrk($data_lis['permissao'], 'X')) {
            if ($podeinativar && $exclusao) {
                $url_del = $data_lis['controler'] . '/delete/' . $dat_i[$chave];

                $bt_del           = new MyCampo();
                $bt_del->id       = $bt_del->nome       = 'bt_delete';
                $bt_del->classep  = 'btn btn-outline-danger btn-sm border-0 mx-0 fs-0';
                $bt_del->i_cone   = "<i class='far fa-trash-alt'></i>";
                $bt_del->label    = "";
                $bt_del->place    = "Excluir";
                $bt_del->funcChan = "excluir('{$url_del}','{$dat_i[$nome]}')";
                $exclui           = $bt_del->crBotao();
            }
        }

        // se for a tela de menus
        if ($chave == 'men_id') {
            $dados[$p]['men_modulo']   = "<i class='" . $dat_i['mod_icone'] . "'></i> " . $dat_i['mod_nome'];
            $dados[$p]['men_tela']     = $dat_i['tel_nome'];
            $dados[$p]['men_etiqueta'] = "<i class='" . $dat_i['men_icone'] . "'></i> " . $dat_i['men_etiqueta'];
            $dados[$p]['men_caminho']  = "<i class='" . $dat_i['men_icone'] . "'></i> " . $dat_i['men_etiqueta'];
            if ($dat_i['men_menupai_id'] > 0) {
                $dados[$p]['men_caminho'] = "<i class='" . $dat_i['pai_icone'] . "'></i> " .
                    $dat_i['pai_etiqueta'] . " <i class='fas fa-level-up-alt fa-rotate-90'></i> " .
                    $dados[$p]['men_caminho'];
            }
            if ($dat_i['men_submenu_id'] != null && $dat_i['men_submenu_id'] > 0) {
                $dados[$p]['men_caminho'] = "<i class='" . $dat_i['pai_icone'] . "'></i> " .
                    $dat_i['pai_etiqueta'] . " <i class='fas fa-level-up-alt fa-rotate-90'></i> <i class='" .
                    $dat_i['sub_icone'] . "'></i> " . $dat_i['sub_etiqueta'] .
                    " <i class='fa fa-arrow-right-long'></i> <i class='" .
                    $dat_i['men_icone'] . "'></i> " . $dat_i['men_etiqueta'];
            }
        }
        $dados[$p]['acao'] = "<div class='col-12 float-start text-center'>";
        // $dados[$p]['acao'] = rtrim($dados[$p]['acao']);
        $dados[$p]['acao'] .= $edit . ' ' . $exclui . ' ' . $inativa . ' ';
        $dados[$p]['acao'] = rtrim($dados[$p]['acao']);
        if (isset($dados[$p]['acao_person'])) {
            // debug($dados[$p]['acao_person'], true);
            for ($bt = 0; $bt < count($dados[$p]['acao_person']); $bt++) {
                $dados[$p]['acao'] .= $dados[$p]['acao_person'][$bt] . ' ';
            }
        }
        $dados[$p]['acao'] = rtrim($dados[$p]['acao']);
        $dados[$p]['acao'] .= "</div>";
        $retor             = $dados[$p];
        $res               = [];
        for ($f = 0; $f < count($fields); $f++) {
            // testa se o campo é uma data
            if (strlen($fields[$f]) > 100) {
                $fields[$f] = strip_tags($fields[$f]);
            }
            // debug($fields[$f], false);
            if ($fields[$f] == 'stt_nome') {
                // debug('aqui');
                if (substr($retor['stt_cor'], 0, 1) == '#') {
                    $retor[$fields[$f]] = fmtEtiquetaCor($retor['stt_cor'], $retor[$fields[$f]]);
                } else {
                    $retor[$fields[$f]] = fmtEtiquetaCorBst($retor['stt_cor'], $retor[$fields[$f]]);
                }
            } else {
                if ($retor[$fields[$f]] != null && $retor[$fields[$f]] != '') {
                    $data = DateTime::createFromFormat('Y-m-d H:i:s', $retor[$fields[$f]]);
                    if ($data && $data->format('Y-m-d H:i:s') === $retor[$fields[$f]]) {
                        $retor[$fields[$f]] = "<div class='text-center'>" .
                            data_br($retor[$fields[$f]]) . "</div>";
                    } else {
                        $data = DateTime::createFromFormat('Y-m-d', $retor[$fields[$f]]);
                        if ($data && $data->format('Y-m-d') === $retor[$fields[$f]]) {
                            $retor[$fields[$f]] = "<div class='text-center'>" . data_br($retor[$fields[$f]]) .
                                "</div>";
                        } else {
                            // testa se o campo é numérico, se for alinha a direita
                            if (is_numeric($retor[$fields[$f]]) > 0) {
                                $partes = explode('.', $retor[$fields[$f]]);
                                if (isset($partes[1]) && trim($partes[1]) != '') {
                                    if (strlen($partes[1]) > 2) {
                                        $retor[$fields[$f]] = "<div class='text-end'>" .
                                            floatToQuantia($retor[$fields[$f]], $partes[1]) . "</div>";
                                    } else {
                                        $retor[$fields[$f]] = "<div class='text-end'>" .
                                            floatToMoeda($retor[$fields[$f]]) . "</div>";
                                    }
                                }
                            }
                        }
                    }
                }
                if (str_contains($fields[$f], 'msg_cor')) {
                    if (strlen($retor[$fields[$f]]) > 1) {
                        $retor[$fields[$f]] = fmtEtiquetaCorBst($retor[$fields[$f]]);
                    }
                }
            }
            array_push($res, $retor[$fields[$f]]);
        }
        // debug($res);
        array_push($result, $res);
    }
    return $result;
}

function montaListaColunasEnt($data_lis, $chave, $dados, $nome)
{
    $telaLista = new ConfigTelaListaModel();

    $lista       = $telaLista->getListagem($data_lis['tel_id']);
    $fields      = array_column($lista, 'lis_campo');
    $exclusao    = $data_lis['exclusao'] ?? true;
    $edicao      = $data_lis['edicao'] ?? true;
    $consulta    = $data_lis['consulta'] ?? true;
    $allconsulta = $data_lis['allconsulta'] ?? false;
    $temacao     = $data_lis['temacao'] ?? true;

    array_unshift($fields, $chave);
    if ($temacao) {
        array_push($fields, 'acao');
    }

    $result = [];

    foreach ($dados as $ent) {
        $temativo     = false;
        $ativo        = 0;
        $inativa      = '';
        $podeinativar = true;
        $podeeditar   = true;

        if (! property_exists($ent, 'tabela') || $ent->tabela !== 'cfg_status') {
            if (property_exists($ent, 'stt_exclusao') && trim($ent->stt_exclusao) === 'N') {
                $podeinativar = false;
            }
            if (property_exists($ent, 'stt_edicao') && trim($ent->stt_edicao) === 'N') {
                $podeeditar = false;
            }
        }

        // Campo ativo/inativo
        foreach ($ent as $key => $value) {
            if (substr($key, -5) == 'ativo') {
                $temativo = true;
                if (trim($value) === 'A') {
                    $ativo = 1;
                }
                break;
            }
        }

        // Botões
        $edit   = '';
        $exclui = '';

        $id_valor   = $ent->{$chave};
        $nome_valor = $ent->{$nome};

        if (
            $allconsulta ||
            (
                $consulta &&
                (
                    (strlen($data_lis['permissao']) <= 3 && strpbrk($data_lis['permissao'], 'C')) ||
                    (strpbrk($data_lis['permissao'], 'C') && ! $podeeditar)
                )
            )
        ) {
            $url_con          = $data_lis['controler'] . '/show/' . $id_valor;
            $bt_con           = new MyCampo();
            $bt_con->id       = $bt_con->nome       = 'bt_show';
            $bt_con->classep  = 'btn btn-outline-info btn-sm border-0 mx-0 fs-0';
            $bt_con->i_cone   = "<i class='far fa-eye'></i>";
            $bt_con->place    = "Consultar";
            $bt_con->funcChan = "redireciona('{$url_con}',event)";
            $edit             = $bt_con->crBotao();
        }

        if (strpbrk($data_lis['permissao'], 'E') && $podeeditar && $edicao) {
            $url_edi          = $data_lis['controler'] . '/edit/' . $id_valor;
            $bt_edt           = new MyCampo();
            $bt_edt->id       = $bt_edt->nome       = 'bt_edit';
            $bt_edt->classep  = 'btn btn-outline-warning btn-sm border-0 mx-0 fs-0';
            $bt_edt->i_cone   = "<i class='fas fa-edit'></i>";
            $bt_edt->place    = "Alterar";
            $bt_edt->funcChan = "redireciona('{$url_edi}',event)";
            $edit             .= $bt_edt->crBotao();
        }

        if ($temativo && $podeinativar && strpbrk($data_lis['permissao'], 'X')) {
            $url_ati          = $data_lis['controler'] . '/ativinativ/' . $id_valor . '/1';
            $bt_ati           = new MyCampo();
            $bt_ati->id       = $bt_ati->nome       = 'bt_ativinat';
            $bt_ati->i_cone   = "<i class='fa-solid fa-toggle-off fa-rotate-270'></i>";
            $bt_ati->place    = "Ativar";
            $bt_ati->classep  = 'btn btn-outline-secondary btn-sm border-0 mx-0 fs-0';
            $bt_ati->funcChan = "ativInativ('{$url_ati}','{$nome_valor}', false)";
            if ($ativo) {
                $url_ina          = $data_lis['controler'] . '/ativinativ/' . $id_valor . '/0';
                $bt_ati->i_cone   = "<i class='fa-solid fa-toggle-on fa-rotate-270'></i>";
                $bt_ati->place    = "Inativar";
                $bt_ati->classep  = 'btn btn-outline-success btn-sm border-0 mx-0 fs-0';
                $bt_ati->funcChan = "ativInativ('{$url_ina}','{$nome_valor}', true)";
            }
            $inativa = $bt_ati->crBotao();
        }

        if (strpbrk($data_lis['permissao'], 'X') && $podeinativar && $exclusao) {
            $url_del          = $data_lis['controler'] . '/delete/' . $id_valor;
            $bt_del           = new MyCampo();
            $bt_del->id       = $bt_del->nome       = 'bt_delete';
            $bt_del->classep  = 'btn btn-outline-danger btn-sm border-0 mx-0 fs-0';
            $bt_del->i_cone   = "<i class='far fa-trash-alt'></i>";
            $bt_del->place    = "Excluir";
            $bt_del->funcChan = "excluir('{$url_del}','{$nome_valor}')";
            $exclui           = $bt_del->crBotao();
        }

        if ($temacao) {
            $ent->acao = "<div class='col-12 d-flex justify-content-between flex-nowrap'>" . rtrim("{$edit} {$exclui} {$inativa}");

            if (property_exists($ent, 'acao_person') && is_array($ent->acao_person)) {
                foreach ($ent->acao_person as $bt) {
                    $name         = '';
                    $inserirbotao = true;
                    if (preg_match('/name=["\']?([^"\'>\s]+)["\']?/', $bt, $matches)) {
                        $name = $matches[1];
                    }
                    if ($inserirbotao) {
                        $ent->acao .= $bt . ' ';
                    }
                }
                // $ent->acao = rtrim($ent->acao);
            }
            $ent->acao .= "</div>";
        }

        // Montar linha de colunas
        $linha = [];
        foreach ($fields as $field) {
            if (strlen($field) > 100) {
                $field = strip_tags($field);
            }

            $valor = $ent->{$field} ?? '';

            if ($field === 'stt_nome' && isset($ent->stt_cor)) {
                $linha[] = fmtEtiquetaCor($ent->stt_cor, $valor);
            } elseif (is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $valor)) {
                $linha[] = "<div class='text-center text-wrap' style='white-space: normal; max-width: 6em;'>" . data_br($valor) . "</div>";
            } elseif (is_numeric($valor) && $field != $chave && is_float($valor)) {
                $linha[] = "<div class='text-end'>" . (
                    strlen(strrchr($valor, '.')) > 3
                    ? floatToQuantia($valor)
                    : floatToMoeda($valor)
                ) . "</div>";
            } elseif (is_numeric($valor) && $field != $chave && is_int($valor)) {
                $linha[] = "<div class='text-end'>" . $valor . "</div>";
            } elseif (str_contains($field, 'msg_cor')) {
                if (substr($valor, 0, 1) == '#') {
                    $linha[] = fmtEtiquetaCor($valor);
                } else {
                    $linha[] = fmtEtiquetaCorBst($valor);
                }
            } else {
                $linha[] = $valor;
            }
        }

        $result[] = $linha;
    }

    return $result;
}

function montaListaEditColunas($colunas, $data_lis, $chave, $dados, $nome, $detalhe = false)
{
    $fields   = $colunas;
    $exclusao = isset($data_lis['exclusao']) ? $data_lis['exclusao'] : true;
    $edicao   = isset($data_lis['edicao']) ? $data_lis['edicao'] : true;
    array_unshift($fields, $chave);
    array_push($fields, 'acao');
    // debug($fields);
    $result = [];
    for ($p = 0; $p < sizeof($dados); $p++) {
        $dat_i        = (array) $dados[$p];
        $dados[$p]    = $dat_i;
        $temativo     = false;
        $ativo        = false;
        $inativa      = '';
        $podeinativar = true;
        $podeeditar   = true;
        if (! isset($dat_i['tabela']) || $dat_i['tabela'] != 'cfg_status') {
            if (isset($dat_i['stt_exclusao'])) {
                if (trim($dat_i['stt_exclusao']) == 'N') {
                    $podeinativar = false;
                }
            }
            if (isset($dat_i['stt_edicao'])) {
                if (trim($dat_i['stt_edicao']) == 'N') {
                    $podeeditar = false;
                }
            }
        }
        // VERIFICA SE TEM UM CAMPO DE ATIVO INATIVO
        foreach ($dat_i as $key => $value) {
            if (substr($key, -5) == 'ativo') {
                $temativo = true;
                if ($value == 'A') {
                    $ativo = true;
                }
            }
        }
        $edit   = '';
        $exclui = '';
        if ((strlen($data_lis['permissao']) < 3 && //se não tem todos os acessos
                strpbrk($data_lis['permissao'], 'C')) ||
            (strpbrk($data_lis['permissao'], 'C') &&
                ! $podeeditar)
        ) { // mas tem acesso de consulta
            $edit = anchor(
                $data_lis['controler'] . '/show/' . $dat_i[$chave],
                '<i class="far fa-eye"></i>',
                [
                    'class'              => 'btn btn-outline-info btn-sm mx-1',
                    'data-mdb-toggle'    => 'tooltip',
                    'data-mdb-placement' => 'top',
                    'title'              => 'Detalhes',
                ]
            );
        }
        if (strpbrk($data_lis['permissao'], 'E')) {
            if ($podeeditar && $edicao) {
                $edit = anchor(
                    $data_lis['controler'] . '/edit/' . $dat_i[$chave],
                    '<i class="far fa-edit"></i>',
                    [
                        'class'              => 'btn btn-outline-warning btn-sm border-0 mx-0 fs-0',
                        'data-mdb-toggle'    => 'tooltip',
                        'data-mdb-placement' => 'top',
                        'title'              => 'Alterar',
                    ]
                );
            }
        }
        if ($temativo) {
            if ($podeinativar && strpbrk($data_lis['permissao'], 'X')) {
                $url_ati = $data_lis['controler'] . '/ativinativ/' . $dat_i[$chave] . '/1';
                $url_ina = $data_lis['controler'] . '/ativinativ/' . $dat_i[$chave] . '/0';
                $inativa =
                    "<button type='button' class='btn btn-outline-secondary btn-sm border-0 mx-0 fs-0' data-mdb-toggle='tooltip'
                    data-mdb-placement='top' title='Ativar' onclick='ativInativ(\"" .
                    $url_ati .
                    "\",\"" .
                    $dat_i[$nome] .
                    "\",false)'><i class='fa-solid fa-toggle-off fa-rotate-270'></i></button>";
                if ($ativo) {
                    $inativa =
                        "<button type='button' class='btn btn-outline-success btn-sm border-0 mx-0 fs-0' data-mdb-toggle='tooltip'
                    data-mdb-placement='top' title='Inativar' onclick='ativInativ(\"" .
                        $url_ina .
                        "\",\"" .
                        $dat_i[$nome] .
                        "\",true)'><i class='fa-solid fa-toggle-off fa-rotate-90'></i></button>";
                }
            }
        }
        if (strpbrk($data_lis['permissao'], 'X')) {
            if ($podeinativar && $exclusao) {
                $url_del =
                    $data_lis['controler'] . '/delete/' . $dat_i[$chave];
                $exclui =
                    "<button type='button' class='btn btn-outline-danger btn-sm border-0 mx-0 fs-0' data-mdb-toggle='tooltip'
                        data-mdb-placement='top' title='Excluir' onclick='excluir(\"" .
                    $url_del .
                    "\",\"" .
                    $dat_i[$nome] .
                    "\")'><i class='far fa-trash-alt'></i></button>";
            }
        }

        if (isset($dat_i['stt_exclusao'])) {
            if (trim($dat_i['stt_exclusao']) == 'N') {
                $podeinativar = false;
            }
        }
        // VERIFICA SE TEM UM CAMPO DE ATIVO INATIVO
        foreach ($dat_i as $key => $value) {
            if (substr($key, -5) == 'ativo') {
                $temativo = true;
                if ($value == 'A') {
                    $ativo = true;
                }
            }
        }
        // $edit = '';
        // $exclui = '';
        $dados[$p]['acao'] = $edit . ' ' . $exclui . ' ' . $inativa;
        // debug($dados[$p]['acao']);
        $retor = $dados[$p];
        $res   = [];
        for ($f = 0; $f < count($fields); $f++) {
            // testa se o campo é uma data
            if (strlen($fields[$f]) > 100) {
                $fields[$f] = strip_tags($fields[$f]);
            }
            // debug($fields[$f], false);
            if ($fields[$f] === 'stt_nome') {
                $retor[$fields[$f]] = fmtEtiquetaCorBst($retor['stt_cor'], $retor[$fields[$f]]);
            }
            // debug($retor[$fields[$f]]);
            if (strlen($retor[$fields[$f]]) < 20) {
                $data = DateTime::createFromFormat('Y-m-d H:i:s', $retor[$fields[$f]]);
                if ($data && $data->format('Y-m-d H:i:s') === $retor[$fields[$f]]) {
                    $retor[$fields[$f]] = "<div class='text-center text-wrap' style='white-space: normal; max-width: 10em;'>" .
                        data_br($retor[$fields[$f]]) . "</div>";
                } else {
                    $data = DateTime::createFromFormat('Y-m-d', $retor[$fields[$f]]);
                    if ($data && $data->format('Y-m-d') === $retor[$fields[$f]]) {
                        $retor[$fields[$f]] = "<div class='text-center text-wrap' style='white-space: normal; max-width: 10em;'>" . data_br($retor[$fields[$f]]) .
                            "</div>";
                    } else {
                        // testa se o campo é numérico, se for alinha a direita
                        if (is_numeric($retor[$fields[$f]]) > 0) {
                            $partes = explode('.', $retor[$fields[$f]]);
                            if (isset($partes[1]) && trim($partes[1]) != '') {
                                if (strlen($partes[1]) > 2) {
                                    $retor[$fields[$f]] = "<div class='text-end'>" .
                                        floatToQuantia($retor[$fields[$f]], 3) . "</div>";
                                } else {
                                    $retor[$fields[$f]] = "<div class='text-end'>" .
                                        floatToMoeda($retor[$fields[$f]]) . "</div>";
                                }
                            }
                        }
                    }
                }
            }
            if (str_contains($fields[$f], 'msg_cor')) {
                if (strlen($retor[$fields[$f]]) > 1) {
                    $retor[$fields[$f]] = fmtEtiquetaCorBst($retor[$fields[$f]]);
                }
            }

            array_push($res, $retor[$fields[$f]]);
        }
        // debug($res);
        array_push($result, $res);
    }
    // debug($result);
    return $result;
}

function mostra_botao($campo, $condic)
{
    $valido = false;
    if ($condic['cond'] == 'contem') {
        if (in_array($campo, $condic['valor'])) {
            $valido = true;
        }
    }
    if ($condic['cond'] == 'igual') {
        if (is_array($condic['valor'])) {
            if (in_array($campo, $condic['valor'])) {
                $valido = true;
            }
        } else {
            if ($campo == $condic['valor']) {
                $valido = true;
            }
        }
    }
    if ($condic['cond'] == 'maior') {
        if ($campo > $condic['valor']) {
            $valido = true;
        }
    }
    if ($condic['cond'] == 'menor') {
        if ($campo < $condic['valor']) {
            $valido = true;
        }
    }
    if ($condic['cond'] == 'maiorouigual') {
        if ($campo >= $condic['valor']) {
            $valido = true;
        }
    }
    if ($condic['cond'] == 'menorouigual') {
        if ($campo <= $condic['valor']) {
            $valido = true;
        }
    }
    return $valido;
}

/**
 * ordenaSelecionados
 * @param array $lista
 * @param string $selecionados
 * @return array
 */
function ordenaSelecionados($lista, $selecionados)
{
    // debug($lista,false);
    $campos_lis = [];
    $selec      = explode(",", $selecionados);
    // debug($selecionados, true);
    foreach ($selec as $skey => $svalue) {
        foreach ($lista as $lkey => $lvalue) {
            // debug($svalue, false);
            // debug($lkey,false);
            if ($svalue == $lkey) {
                $campos_lis[$lkey] = $lvalue;
                unset($lista[$lkey]);
            }
        }
    }
    foreach ($lista as $lkey => $lvalue) {
        $campos_lis[$lkey] = $lvalue;
    }
    // debug($campos_lis,true);
    return $campos_lis;
}

function monta_filtro($data_lis, $dados_base)
{
    $dicionario = new ConfigDicDadosModel();

    $lista      = $data_lis['filtros'];
    $arr_lista  = explode(',', $lista);
    $tabela     = $data_lis['tabela'];
    $arr_campos = [];
    // array_push($arr_campos, $this->dicionario->getCampoChave($tabela)[0]);
    for ($l = 0; $l < count($arr_lista); $l++) {
        array_push($arr_campos, $dicionario->getDetalhesCampo($tabela, $arr_lista[$l])[0]);
    }
    $filtros = [];
    for ($c = 0; $c < count($arr_campos); $c++) {
        $opcoes = array_column($dados_base, $arr_campos[$c]['COLUMN_NAME'], $arr_campos[$c]['COLUMN_NAME']);

        $filt              = new Campos();
        $filt->objeto      = 'select';
        $filt->nome        = $arr_campos[$c]['COLUMN_NAME'];
        $filt->id          = $arr_campos[$c]['COLUMN_NAME'];
        $filt->label       = $arr_campos[$c]['COLUMN_COMMENT'];
        $filt->obrigatorio = false;
        $filt->size        = $arr_campos[$c]['COLUMN_SIZE'];
        $filt->opcoes      = $opcoes;
        $filt->tipo_form   = 'inline';
        $filtro            = $filt->create();

        array_push($filtros, $filtro);
    }
    // debug($filtros);
    return $filtros;
}

if (! function_exists('envia_msg_ws')) {
    function envia_msg_ws($controler, $mensagem, $tipo = 'Servidor', $usuario = 0, $id = 0)
    {
        try {
            $client = new Client("wss://127.0.0.1:8443/ws", [
                'context' => stream_context_create([
                    'ssl' => [
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true,
                    ],
                ]),
            ]);

            if ($client) {
                log_message('info', 'Conectou ao Servidor WS');
                $msg = [
                    'msg'       => $mensagem,
                    'controler' => $controler,
                    'tipo'      => $tipo,
                    'usuario'   => $usuario,
                    'id'        => $id,
                ];

                log_message('info', 'Msg WS ' . json_encode($msg));
                $client->send(json_encode($msg));
                $client->close();

                log_message('info', 'Enviou Mensagem WS: ' . json_encode($msg['msg']) . ' Usuário ' . $usuario);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Erro ao enviar WS: ' . $e->getMessage());
        }
    }
}
