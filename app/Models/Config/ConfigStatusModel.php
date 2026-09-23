<?php

namespace App\Models\Config;

use CodeIgniter\Model;
use App\Entities\Config\EntCfgStatus;
use App\Models\LogMonModel;

class ConfigStatusModel extends Model
{
    protected $DBGroup          = 'default';
    protected $table            = 'cfg_status';
    protected $view             = 'vw_cfg_status_relac';
    protected $primaryKey       = 'stt_id';

    protected $returnType       = EntCfgStatus::class;
    protected $useSoftDeletes   = true;
    protected $deletedField     = 'stt_excluido';

    protected $allowedFields = [
        'stt_id',
        'stt_nome',
        'mod_id',
        'tel_id',
        'cor_id',
        'stt_exclusao',
        'stt_edicao',
        'stt_impressao',
        'stt_disponivel',
        'stt_ativo',
        'stt_ordem',
        'stt_excluido',
    ];

    protected $validationRules = [
        'stt_nome' => 'required|min_length[5]',
    ];

    protected $afterInsert = ['logInsert'];
    protected $afterUpdate = ['logUpdate'];
    protected $afterDelete = ['logDelete'];

    protected function logInsert(array $data)
    {
        (new LogMonModel())->insertLog($this->table, 'Incluído', $data['id'], $data['data']);
        return $data;
    }

    protected function logUpdate(array $data)
    {
        (new LogMonModel())->insertLog($this->table, 'Alteração', $data['id'][0], $data['data']);
        return $data;
    }

    protected function logDelete(array $data)
    {
        (new LogMonModel())->insertLog($this->table, 'Excluído', $data['id'][0], $data['data']);
        return $data;
    }


    public function getStatus($stt_id = false, $ativo = null)
    {
        $db = db_connect('default');
        $builder = $db->table($this->view);
        $builder->select('*');

        // Aplica filtro de ativo SOMENTE se informado
        if ($ativo !== null) {
            $builder->where('stt_ativo', $ativo);
        }

        if ($stt_id) {
            if (is_array($stt_id)) {
                $builder->whereIn('stt_id', $stt_id);
                return $builder->get()->getResult();
            }
            $builder->where('stt_id', $stt_id);
            return $builder->get()->getFirstRow();
        }
        return $builder->get()->getResult();
    }

    public function getStatusTela(int $tel_id)
    {
        // Conecta ao banco padrão
        $db = db_connect('default');
        $builder = $db->table($this->view); // usando a VIEW como fonte
        $builder->select('*');
        $builder->where('tel_id', $tel_id);
        $builder->orderBy('stt_ordem');

        // Retorna array de objetos da Entity
        return $builder->get()->getResult(EntCfgStatus::class);
    }

    public function getStatusNomeTela($tel_id, $nome, $stt_id)
    {
        // Conecta ao banco padrão
        $db = db_connect('default');
        $builder = $db->table($this->view);
        $builder->select('*');
        $builder->where("tel_id", $tel_id);
        $builder->where("stt_nome", $nome);
        $builder->where("stt_id !=", $stt_id);
        $ret = $builder->get()->getResult(EntCfgStatus::class);
        // debug($this->db->getLastQuery());

        return $ret;
    }

    public function getStatusOrdem($stt_id = false)
    {
        // Conecta ao banco padrão
        $db = db_connect('default');
        $builder = $db->table('vw_cfg_status_ordem');
        $builder->select('*');
        if ($stt_id) {
            $builder->where("stt_id", $stt_id);
        }
        $builder->where("stt_ativo", 'A');
        $ret = $builder->get()->getResultArray();

        return $ret;
    }

    public function getStatusProximaOrdem($tel_id = false)
    {
        // Conecta ao banco padrão
        $db = db_connect('default');
        $builder = $db->table('vw_cfg_status_ordem');
        $builder->selectMax('stt_ordem');
        if ($tel_id) {
            $builder->where("tel_id", $tel_id);
        }
        $ret = $builder->get()->getResult(EntCfgStatus::class);

        return $ret;
    }

    public function getStatusSearch($termo)
    {
        $array = ['stt_nome' => $termo . '%'];
        // Conecta ao banco padrão
        $db = db_connect('default');
        $builder = $db->table($this->view);
        $builder->select('*');
        $builder->like($array);
        $ret = $builder->get()->getResult(EntCfgStatus::class);

        return $ret;
    }

    public function getProximaOrdem(int $tel_id): int
    {
        // Busca a maior ordem atual e soma 1
        return (int) ($this->selectMax('stt_ordem')
            ->where('tel_id', $tel_id)
            ->first()
            ->stt_ordem ?? 0) + 1;
    }

    public function getEtiqueta(int $tipo = 1): string
    {
        return fmtEtiquetaCor(
            $this->cor_valorrgb ?? '',
            $this->stt_nome ?? '',
            $tipo
        );
    }

    /**
     * Resolve o `tel_controler` da tela à qual um status pertence, via JOIN
     * com `cfg_tela` pelo `tel_id` do próprio status (`cfg_status.tel_id`).
     * Usado quando uma ação "Alterar Status" (T12 —
     * `OcoTrataOcorrencia::resolveAlteracoesStatus()`) precisa saber, a
     * partir do `stt_id` escolhido, a qual tela esse status pertence — e
     * portanto qual registro deve ser atualizado (Produto, Lote, etc.).
     * `tel_controler` é o mesmo valor usado no roteamento
     * (`LoginFilter::before()`/`ConfigTelaModel::getTelaSearch()`), ex.:
     * 'Produto', 'Lote'.
     */
    public function getTelaControlerDoStatus(int $stt_id): ?string
    {
        $db = db_connect('default');

        $row = $db->table('cfg_status st')
            ->select('t.tel_controler')
            ->join('cfg_tela t', 't.tel_id = st.tel_id')
            ->where('st.stt_id', $stt_id)
            ->get()
            ->getRow();

        return $row->tel_controler ?? null;
    }

    public function getStatusPorIds(array $sttIds)
    {
        if (empty($sttIds)) {
            return [];
        }

        $rows = $this->asArray()
            ->whereIn('stt_id', $sttIds)
            ->findAll();

        $map = [];
        foreach ($rows as $r) {
            $map[$r['stt_id']] = $r;
        }

        return $map;
    }
}
