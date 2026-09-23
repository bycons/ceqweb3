<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Libraries\SoapSapiens;

class BuscasSapiens extends BaseController
{
    public $data = [];
    public $menu;
    public $modulo;
    public $tela;
    public $usuario;
    public $admDados;

    public function __construct() {}

    public function buscaEmpresas()
    {
        $soapdep = new SoapSapiens('ceqweb_integra');
        // debug($soapdep, false);
        $ret_emps = $soapdep->empresasSapiens('ConsultaEmpresaFilial');

        return $ret_emps->retorno;
    }

    public function buscaDepositos()
    {
        $soapdep = new SoapSapiens('ceqweb_integra');
        $ret_deps = $soapdep->depositosSapiens('ConsultarDepositos');
        return $ret_deps->retorno;
    }

    public function buscaOrigem()
    {
        $soapdep = new SoapSapiens('ceqweb_integra');
        // debug($soapdep, false);
        $ret_deps = $soapdep->origemSapiens('ConsultarOrigem');

        return $ret_deps->retorno;
    }

    public function buscaFamilia()
    {
        $soapdep = new SoapSapiens('ceqweb_integra');
        // debug($soapdep, false);
        $ret_deps = $soapdep->familiaSapiens('ConsultarFamilia');

        return $ret_deps->retorno;
    }

    public function buscaFabricante()
    {
        $soapfab = new SoapSapiens('ceqweb_integra');
        // debug($soapdep, false);
        $ret_fabs = $soapfab->fabricanteSapiens('ConsultarFabricante');
        if (isset($ret_fabs->erro)) {
            $ret_fabs->retorno = [];
        }
        return $ret_fabs->retorno;
    }

    public function buscaProduto($codEmp, $codPro = '')
    {
        $soappro = new SoapSapiens('ceqweb_integra');
        // debug($soapdep, false);
        $ret_pros = $soappro->produtosSapiens('ConsultarProduto', $codEmp, $codPro);
        if (isset($ret_pros->retorno)) {
            return $ret_pros->retorno;
        } else {
            return false;
        }
    }

    public function buscaLotes($codLot = '')
    {
        $soappro = new SoapSapiens('ceqweb_integra');
        // debug($codLot, true);
        $ret_pros = $soappro->lotesSapiens('ConsultarLote', $codLot);

        if (isset($ret_pros->retorno)) {
            return $ret_pros->retorno;
        } else {
            return false;
        }
    }

    public function buscaLotesFaltante($codLot = '')
    {
        $soappro = new SoapSapiens('ceqweb_integra');
        // debug($codLot, true);
        $ret_pros = $soappro->lotesSapiens('ConsultarUnicoLote', $codLot);

        if (isset($ret_pros->retorno)) {
            return $ret_pros->retorno;
        } else {
            return false;
        }
    }

    public function buscaProdutoFabricante($codEmp, $codPro = '')
    {
        $soappro = new SoapSapiens('ceqweb_integra');
        // debug($soapdep, false);
        $ret_pros = $soappro->produtosSapiens('ConsultarProdutoFabricante', $codEmp, $codPro);
        if (isset($ret_pros->retorno)) {
            return $ret_pros->retorno;
        } else {
            return false;
        }
        // debug($ret_pros->retorno, true);
    }

    public function buscaEstoqueDeposito($deposito, $codpro = false)
    {
        $soapdep = new SoapSapiens('ceqweb_integra');
        // debug($soapdep, false);
        // debug("Chamei uma vez " . $deposito . " Prod " . $codpro, false);
        $ret_deps = $soapdep->estoquePorDeposito($deposito, $codpro);

        // debug($ret_deps, true);
        if (isset($ret_deps->retorno)) {
            $ret = $ret_deps->retorno;
        } else {
            $ret = [];
        }
        return $ret;
    }

    public function buscaTransacoes()
    {
        $soapdep = new SoapSapiens('ceqweb_integra');
        // debug($soapdep, false);
        $ret_tnss = $soapdep->transacoesEstoque('ConsultarTransacoesEstoque');

        return $ret_tnss->retorno;
    }
}
