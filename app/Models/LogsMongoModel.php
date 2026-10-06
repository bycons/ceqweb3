<?php 
namespace App\Models;

class LogsMongoModel
{
    private $logMongo;
    public function __construct()
    {
        $this->logMongo = (new \MongoDB\Client)->{\App\Libraries\MongoDb::database()}->Logs;
    }

    /**
     * Retorna todos os registros
     *
     * @return void
     */
    public function getAll(): object
    {
        return $this->logMongo->find();
    }

    /**
     * Retorna um objeto pelo seu MongoID
     *
     * @param [type] $id
     * @return object
     */
    public function findById($id): object
    {
        return $this->logMongo->findOne(['_id' => new \MongoDB\BSON\ObjectId($id)]);
    }

    /**
     * Retorna um objeto pelo seu MongoID
     *
     * @param string $tabela
     * @param string $registro
     * @return object
     */
    public function findByChaves($tabela, $registro): object
    {
        $result = $this->logMongo->createIndex(['log_data' => -1]);
        // return $this->logMongo->find(['log_tabela'=>$tabela,'log_id_registro'=>$registro, 'log_data'=>[ '$gt'  =>  '' ]])->limit(1);
        return $this->logMongo->findOne(array('log_tabela'=>$tabela,'log_id_registro'=>$registro, 'log_data'=>[ '$gt'  =>  '' ]));
    }

    /**
     * insertLog
     *
     * Insere o Registro na Tabela de Log
     *  
     * @param string $tabela
     * @param string $operacao
     * @param int    $registro
     * @param array  $dados
     * @return void
     */
    public function insertLog($tabela, $operacao, $registro, $dados)
    {
        $sql_data = [
            'log_tabela'        => $tabela,
            'log_operacao'      => $operacao,
            'log_id_registro'   => strval($registro),
            'log_id_usuario'    => session()->get('usu_nome'),
            'log_data'          => date('Y-m-d H:i:s'),
            'log_dados'         => $dados
        ];
        $ret = $this->logMongo->insertOne($sql_data);
        return $ret;
    }

    /**
     * Atualiza os dados
     *
     * @param array $dados
     * @return void
     */
    public function update(string $id, array $dados)
    {
        return $this->logMongo->updateOne(
            ['_id' => new \MongoDB\BSON\ObjectId($id)],
            [
                '$set' => [
                    'username' => $dados['username'],
                    'email' => $dados['email'],
                    'name' => $dados['name'],
                ]
            ]
        );
    }

    /**
     * Apaga um registro pelo MongoID
     */
    public function delete(string $id)
    {
        return $this->logMongo->deleteOne(
            ['_id' => new \MongoDB\BSON\ObjectId($id)]
        );
    }
}