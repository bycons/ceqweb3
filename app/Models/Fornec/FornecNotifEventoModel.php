<?php

namespace App\Models\Fornec;

use App\Entities\Fornecedores\EntOcoNotifEvento;
use App\Models\LogMonModel;
use CodeIgniter\Model;

/**
 * T43 — Notificação de Evento (oco_notif_evento). Segue o mesmo padrão de
 * App\Models\Fornec\FornecNotifDesvioModel (T42).
 */
class FornecNotifEventoModel extends Model
{
    protected $DBGroup    = 'dbOcorrencia';
    protected $table      = 'oco_notif_evento';
    protected $view       = 'vw_oco_notif_evento_relac';
    protected $primaryKey = 'nev_id';

    protected $returnType = EntOcoNotifEvento::class;

    protected $allowedFields = [
        'nev_id',
        'nev_qtd_adquirida',
        'nev_numero_nf',
        'nev_fornecedor',
        'nev_fabricacao',
        'nev_providencias',
        'nev_notificado',
        'nev_parecer',
        'nev_notivisa',
        'nev_notivisa_num',
        'stt_id',
        'usu_criou',
    ];

    protected $useTimestamps  = true;
    protected $createdField   = 'nev_criado';
    protected $updatedField   = 'nev_alterado';
    protected $useSoftDeletes = true;
    protected $deletedField   = 'nev_excluido';

    protected $validationRules = [
        'nev_qtd_adquirida' => 'required|is_natural',
        'nev_numero_nf'     => 'required|max_length[20]',
        'nev_fornecedor'    => 'required|min_length[5]|max_length[200]',
        'nev_fabricacao'    => 'required|valid_date[Y-m-d]',
        'nev_providencias'  => 'required|min_length[5]|max_length[500]',
        'nev_notificado'    => 'required|min_length[5]|max_length[200]',
        'nev_parecer'       => 'required|min_length[5]|max_length[500]',
        // RN03.19 — obrigatório (5 a 50 caracteres) somente quando
        // nev_notivisa = 'S'; regra condicional de negócio, não checagem
        // solta no Controller (ver App\Controllers\MyValidation::obrigatorioSeNotivisaSim()).
        'nev_notivisa_num'  => 'obrigatorioSeNotivisaSim[nev_notivisa]',
    ];

    protected $validationMessages = [
        'nev_notivisa_num' => [
            'obrigatorioSeNotivisaSim' => 'O campo Nº Notivisa é obrigatório (5 a 50 caracteres) quando a notificação ao Notivisa estiver marcada como Sim',
        ],
    ];

    protected $allowCallbacks = true;
    protected $afterInsert    = ['depoisInsert'];
    protected $afterUpdate    = ['depoisUpdate'];
    protected $afterDelete    = ['depoisDelete'];

    protected function depoisInsert(array $data)
    {
        (new LogMonModel())->insertLog($this->table, 'Incluído', $data['id'], $data['data']);
        return $data;
    }

    protected function depoisUpdate(array $data)
    {
        (new LogMonModel())->insertLog($this->table, 'Alteração', $data['id'][0], $data['data']);
        return $data;
    }

    protected function depoisDelete(array $data)
    {
        (new LogMonModel())->insertLog($this->table, 'Excluído', $data['id'][0], $data['data']);
        return $data;
    }

    public function getNotifEvento($id)
    {
        $db = db_connect('dbOcorrencia');

        $builder = $db->table($this->view);
        $builder->select('*');
        $builder->where('nev_id', $id);

        return $builder->get()->getRow();
    }

    public function getListaCompleta()
    {
        $db = db_connect('dbOcorrencia');

        $builder = $db->table($this->view);
        $builder->select('*');
        $builder->orderBy('stt_ordem, nev_data DESC, nev_id ASC');

        return $builder->get()->getResult();
    }

    public function getProdutosNotificacao(int $nev_id)
    {
        $db = db_connect('dbOcorrencia');

        $builder = $db->table('vw_oco_notif_evento_produto_relac');
        $builder->select('*');
        $builder->where('nev_id', $nev_id);
        $builder->orderBy('nvp_id');

        return $builder->get()->getResult();
    }

    public function getAcoesNotificacao(int $nev_id)
    {
        $db = db_connect('dbOcorrencia');

        $builder = $db->table('oco_notif_evento_acao');
        $builder->select('*');
        $builder->where('nev_id', $nev_id);
        $builder->orderBy('nac_ordem');

        return $builder->get()->getResult();
    }

    public function getAnexos(int $nev_id, string $origem)
    {
        $db = db_connect('dbOcorrencia');

        $builder = $db->table('oco_notif_evento_anexo');
        $builder->select('*');
        $builder->where('nev_id', $nev_id);
        $builder->where('nva_origem', $origem);
        $builder->orderBy('nva_id');

        return $builder->get()->getResult();
    }

    public function getStatusId($stat = ''): ?int
    {
        return $this->getStatusPorNome($stat);
    }


    private function getStatusPorNome(string $nome): ?int
    {
        $schemaDefault = db_connect('default')->getDatabase();
        $db            = db_connect('dbOcorrencia');

        $row = $db->table("{$schemaDefault}.cfg_status st")
            ->select('st.stt_id')
            ->join("{$schemaDefault}.cfg_tela t", 't.tel_id = st.tel_id')
            ->where('t.tel_controler', 'NotifEvento')
            ->where('st.stt_nome', $nome)
            ->get()
            ->getRow();

        return $row ? (int) $row->stt_id : null;
    }
}
