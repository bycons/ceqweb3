<?php

namespace App\Models;

use App\Libraries\MongoDb;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Command;
use MongoDB\Driver\Exception\RuntimeException;
use MongoDB\Driver\Query;
use stdClass;

class LogMonModel
{

	private $database = 'MongoDB';
	private $collection = 'Logs';
	private $conn;

	function __construct()
	{
		$mongodb = new MongoDb();
		$this->conn = $mongodb->getConn();
		$this->database = MongoDb::database();
	}

	/**
	 * get_logs_last
	 * Busca o último registro do Log
	 *
	 * @param mixed $tabela
	 * @param mixed $registro
	 * @return array
	 */
	function get_logs_last($tabela, $registro)
	{
		try {
			$filter = [
				'log_tabela' => $tabela,
				'log_id_registro' => $registro,
				'log_data' => ['$gt'  =>  '']
			];
			$options = [
				'projection' => ['_id' => 0],
				'sort' => ['log_data' => 1],
				'limit' => 1
			];
			$query = new Query($filter, $options);

			$result = $this->conn->executeQuery($this->database . '.' . $this->collection, $query);

			return $result->toArray();
		} catch (\MongoDB\Driver\Exception\RuntimeException $ex) {
			show_error('Error while fetching logs: ' . $ex->getMessage(), 500);
		}
	}

	function get_logs_lastVarios($tabela, $registros)
	{
		try {
			// Define o pipeline de agregação
			$pipeline = [
				// Primeiro, filtra os documentos pela tabela, pelos registros informados e pela data
				[
					'$match' => [
						'log_tabela'      => $tabela,
						'log_id_registro' => ['$in' => $registros],
						'log_data'        => ['$gt' => '']
					]
				],
				// Ordena os registros em ordem decrescente pela data
				[
					'$sort' => ['log_data' => -1]
				],
				// Agrupa os documentos por log_id_registro e pega o primeiro documento de cada grupo (que, devido à ordenação, é o mais recente)
				[
					'$group' => [
						'_id'         => '$log_id_registro',
						'last_record' => ['$first' => '$$ROOT']
					]
				],
				// Opcional: reformata o resultado (por exemplo, removendo o _id do grupo e trazendo o log_id_registro)
				[
					'$project' => [
						'_id'            => 0,
						'log_id_registro' => '$_id',
						'last_record'    => 1
					]
				]
			];

			$command = new Command([
				'aggregate' => $this->collection,
				'pipeline'  => $pipeline,
				'cursor'    => new stdClass,
			]);

			$result = $this->conn->executeCommand($this->database, $command);
			return $result->toArray();
		} catch (RuntimeException $ex) {
			show_error('Error while fetching logs: ' . $ex->getMessage(), 500);
		}
	}

	function get_logs_firstVarios($tabela, $registros)
	{
		try {
			// Define o pipeline de agregação
			$pipeline = [
				// Filtra os documentos pela tabela, pelos registros informados e pela data
				[
					'$match' => [
						'log_tabela'      => $tabela,
						'log_id_registro' => ['$in' => $registros],
						'log_data'        => ['$gt' => '']
					]
				],
				// Ordena os registros em ordem CRESCENTE pela data
				[
					'$sort' => ['log_data' => 1]
				],
				// Agrupa por log_id_registro e pega o primeiro documento de cada grupo
				// (que, devido à ordenação crescente, é o mais antigo)
				[
					'$group' => [
						'_id'          => '$log_id_registro',
						'first_record' => ['$first' => '$$ROOT']
					]
				],
				// Reformata o resultado
				[
					'$project' => [
						'_id'             => 0,
						'log_id_registro' => '$_id',
						'first_record'    => 1
					]
				]
			];
			// debug($pipeline);
			$command = new Command([
				'aggregate' => $this->collection,
				'pipeline'  => $pipeline,
				'cursor'    => new stdClass,
			]);

			$result = $this->conn->executeCommand($this->database, $command);
			return $result->toArray();
		} catch (RuntimeException $ex) {
			show_error('Error while fetching logs: ' . $ex->getMessage(), 500);
		}
	}

	/**
	 * get_logs_all
	 * Lista todos os registros de Log do Registro
	 *
	 * @param mixed $tabela
	 * @param mixed $registro
	 * @return array
	 */
	function get_logs_all($tabela, $registro)
	{
		try {
			$filter = [
				'log_tabela' => $tabela,
				'log_id_registro' => $registro,
				'log_data' => ['$gt'  =>  '']
			];
			// debug($filter);
			$options = [
				'projection' => ['_id' => 0],
				'sort' => ['log_data' => -1],
			];
			$query = new \MongoDB\Driver\Query($filter, $options);

			$result = $this->conn->executeQuery($this->database . '.' . $this->collection, $query);

			return $result->toArray();
		} catch (\MongoDB\Driver\Exception\RuntimeException $ex) {
			show_error('Error while fetching logs: ' . $ex->getMessage(), 500);
		}
	}


	function get_logs_id($_id)
	{
		try {
			$filter = ['_id' => new \MongoDB\BSON\ObjectId($_id)];
			$query = new \MongoDB\Driver\Query($filter);

			$result = $this->conn->executeQuery($this->database . '.' . $this->collection, $query);

			foreach ($result as $logs) {
				return $logs;
			}

			return null;
		} catch (\MongoDB\Driver\Exception\RuntimeException $ex) {
			show_error('Error while fetching logs: ' . $ex->getMessage(), 500);
		}
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
		try {
			$sql_data = [
				'log_tabela'        => $tabela,
				'log_operacao'      => $operacao,
				'log_id_registro'   => strval($registro),
				'log_id_usuario'    => session()->get('usu_id'),
				'log_usuario'    	=> session()->get('usu_nome'),
				'log_data'          => date('Y-m-d H:i:s'),
				'log_dados'         => $dados
			];

			$query = new BulkWrite();
			$query->insert($sql_data);

			$result = $this->conn->executeBulkWrite($this->database . '.' . $this->collection, $query);

			if ($result->getInsertedCount() == 1) {
				return true;
			}

			return false;
		} catch (\MongoDB\Driver\Exception\RuntimeException $ex) {
			show_error('Error while saving log: ' . $ex->getMessage(), 500);
		}
	}

	function update_log($_id, $name, $email)
	{
		try {
			$query = new \MongoDB\Driver\BulkWrite();
			$query->update(['_id' => new \MongoDB\BSON\ObjectId($_id)], ['$set' => array('name' => $name, 'email' => $email)]);

			$result = $this->conn->executeBulkWrite($this->database . '.' . $this->collection, $query);

			if ($result->getModifiedCount()) {
				return true;
			}

			return false;
		} catch (\MongoDB\Driver\Exception\RuntimeException $ex) {
			show_error('Error while updating log: ' . $ex->getMessage(), 500);
		}
	}

	function delete_log($_id)
	{
		try {
			$query = new \MongoDB\Driver\BulkWrite();
			$query->delete(['_id' => new \MongoDB\BSON\ObjectId($_id)]);

			$result = $this->conn->executeBulkWrite($this->database . '.' . $this->collection, $query);

			if ($result->getDeletedCount() == 1) {
				return true;
			}

			return false;
		} catch (\MongoDB\Driver\Exception\RuntimeException $ex) {
			show_error('Error while deleting log: ' . $ex->getMessage(), 500);
		}
	}
}
