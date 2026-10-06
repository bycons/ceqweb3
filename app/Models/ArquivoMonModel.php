<?php namespace App\Models;

use App\Libraries\MongoDb;

class ArquivoMonModel {

	private $database = 'MongoDB';
	private $collection = 'Arquivos';
	private $conn;

	function __construct() {
		$mongodb = new MongoDb();
		$this->conn = $mongodb->getConn();
		$this->database = MongoDb::database();
	}

    /**
     * insertArquivo
     *
     * Insere o Arquivo na Colection de Arquivos
     *  
     * @param int    $classe
     * @param int    $registro
     * @param array  $dados
     * @return bool
     */
    public function insertArquivo($controler, $aplica, $registro, $arquivo, $blob)
    {
		try {
            $sql_data = [
                'arq_controler'		=> $controler,
                'arq_aplicacao'		=> $aplica,
                'arq_id_registro'   => strval($registro),
                'arq_id_usuario'    => session()->get('usu_nome'),
                'arq_data'          => date('Y-m-d H:i:s'),
                'arq_nome'          => $arquivo['arq_nome'],
                'arq_tamanho'       => $arquivo['arq_size'],
                'arq_extensao'      => $arquivo['arq_exte'],
                'arq_tipo'          => $arquivo['arq_tipo'],
                'arq_conteudo'      => $blob
            ];
			$query = new \MongoDB\Driver\BulkWrite();
			$query->insert($sql_data);
			$result = $this->conn->executeBulkWrite($this->database.'.'.$this->collection, $query);

			if($result->getInsertedCount() == 1) {
				return true;
			} else {
				return false;
			}

		} catch(\MongoDB\Driver\Exception\RuntimeException $ex) {
			debug('Error while saving: ' . $ex->getMessage(), 500);
			return false;
		}
	}

	function getArquivos($controler,$aplica, $registro) {
		try {
			$filter = [
                'arq_controler'=>$controler,
                'arq_aplicacao'=>$aplica,
                'arq_id_registro'=>$registro, 
            ];
            $options = [
                // 'projection' => ['_id' => 0],
                'sort' => ['arq_data' => -1]
            ];            
			$query = new \MongoDB\Driver\Query($filter, $options);
			
			$result = $this->conn->executeQuery($this->database . '.' . $this->collection, $query);
			// debug($result);
			
			return $result->toArray();
			
		} catch(\MongoDB\Driver\Exception\RuntimeException $ex) {
			debug('Error while fetching logs: ' . $ex->getMessage(), 500);
		}
	}

	function getArquivoId($_id) {
		try {
			$filter = ['_id' => new \MongoDB\BSON\ObjectId($_id)];
			$query = new \MongoDB\Driver\Query($filter);

			$result = $this->conn->executeQuery($this->database.'.'.$this->collection, $query);

			foreach($result as $files) {
				return $files;
			}

			return null;
		} catch(\MongoDB\Driver\Exception\RuntimeException $ex) {
			debug('Error while fetching logs: ' . $ex->getMessage(), 500);
		}
	}

	function deleteArquivo($controler,$aplica, $registro) {
		try {
			$filter = [
                'arq_controler'=>$controler,
                'arq_aplicacao'=>$aplica,
                'arq_id_registro'=>$registro, 
            ];
			$query = new \MongoDB\Driver\BulkWrite();
			$query->delete($filter);

			$result = $this->conn->executeBulkWrite($this->database . '.' . $this->collection, $query);

			if($result->getDeletedCount() == 1) {
				return true;
			} else {
				return false;
			}
		} catch(\MongoDB\Driver\Exception\RuntimeException $ex) {
			debug('Error while deleting log: ' . $ex->getMessage(), 500);
		}
	}


}