<?php namespace App\Models;

use App\Libraries\MongoDb;

class MovimMonModel {

	private $database = 'MongoDB';
	private $collection = 'Movimentos';
	private $conn;

	function __construct() {
		$mongodb = new MongoDb();
		$this->conn = $mongodb->getConn();
		$this->database = MongoDb::database();
	}

    /**
     * insertMovimento
     *
     * Insere o Movvimento na Colection de Movvimento
     *  
     * @param int    $classe
     * @param int    $registro
     * @param array  $dados
     * @return bool
     */
    public function insertMovimento($codpro, $codtns, $depori, $datmov, $qtdmov, $codlot, $depdes, $valida, $status, $msgretorno)
    {
		try {
            $sql_data = [
                'mov_data'          => date('Y-m-d H:i:s'),
                'mov_produto'		=> $codpro,
                'mov_codtns'   		=> $codtns,
                'mov_depori'    	=> $depori,
                'mov_datmov'  		=> $datmov,
                'mov_qtdmov'         	=> $qtdmov,
				'mov_codlot'			=> $codlot,
                'mov_depdes'         => $depdes,
                'mov_valida'         => $valida,
                'mov_status'         => $status,
                'mov_msgretorno'         => $msgretorno,
            ];

			$query = new \MongoDB\Driver\BulkWrite();
			$query->insert($sql_data);
			$result = $this->conn->executeBulkWrite($this->database.'.'.$this->collection, $query);

			if($result->getInsertedCount() == 1) {
				return true;
			}

			return true;
		} catch(\MongoDB\Driver\Exception\RuntimeException $ex) {
			// debug('Error while saving log: ' . $ex->getMessage(), 500);
			return false;
		}
	}

	function getMovimentos() {
		try {
			$filter =[];
            $options = [
                'sort' => ['mov_datmov' => -1],
            ];            
			$query = new \MongoDB\Driver\Query($filter, $options);

			$result = $this->conn->executeQuery($this->database . '.' . $this->collection, $query);
			// debug($result, true);
			return $result->toArray();
			
		} catch(\MongoDB\Driver\Exception\RuntimeException $ex) {
			show_error('Error while fetching logs: ' . $ex->getMessage(), 500);
		}
	}

}