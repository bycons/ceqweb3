<?php

namespace App\Models;

use App\Libraries\MongoDb;

class NotificaMonModel
{

	private $database = 'MongoDB';
	private $collection = 'Notifica';
	private $conn;

	function __construct()
	{
		$mongodb = new MongoDb();
		$this->conn = $mongodb->getConn();
		$this->database = MongoDb::database();
	}

	/**
	 * insertNotifica
	 *
	 * Insere o Notifica na Colection de Notifica
	 *  
	 * @param int    $classe
	 * @param int    $registro
	 * @param array  $dados
	 * @return bool
	 */
	public function insertNotifica($controler, $texto, $registro, $usuario, $usuariodest, $tipo = 'I')
	{
		try {
			$sql_data = [
				'not_controler'		=> $controler,
				'not_id_registro'   => strval($registro),
				'not_id_usuario'    => $usuariodest,
				'not_usuario_orig'  => $usuario,
				'not_data'          => date('Y-m-d H:i:s'),
				'not_texto'         => $texto,
				'not_tipo'			=> $tipo,
				'not_visto'         => 'A',
			];

			// debug($sql_data);
			$query = new \MongoDB\Driver\BulkWrite();
			$query->insert($sql_data);
			$result = $this->conn->executeBulkWrite($this->database . '.' . $this->collection, $query);

			if ($result->getInsertedCount() == 1) {
				return true;
			}

			return true;
		} catch (\MongoDB\Driver\Exception\RuntimeException $ex) {
			// debug('Error while saving log: ' . $ex->getMessage(), 500);
			return false;
		}
	}

	function getNotificas($usuario)
	{
		try {
			$filter = [
				'not_id_usuario' => $usuario,
				'not_visto' => 'A',
			];
			$options = [
				// 'projection' => ['_id' => 0],
				'sort'  => ['not_data' => -1],
				'limit' => 50,
			];
			$query = new \MongoDB\Driver\Query($filter, $options);
			$result = $this->conn->executeQuery($this->database . '.' . $this->collection, $query);
			return $result->toArray();
		} catch (\MongoDB\Driver\Exception\RuntimeException $ex) {
			show_error('Error while fetching logs: ' . $ex->getMessage(), 500);
		}
	}


	function getNotificaAberta($usuario)
	{
		try {

			$filter = [
				'not_visto' => 'A',
				'not_id_usuario' => $usuario,
			];
			$options = [
				'sort'  => ['not_data' => -1],
				'limit' => 50,
			];
			$query = new \MongoDB\Driver\Query($filter, $options);

			$result = $this->conn->executeQuery($this->database . '.' . $this->collection, $query);

			return $result->toArray();
		} catch (\MongoDB\Driver\Exception\RuntimeException $ex) {
			show_error('Error while fetching logs: ' . $ex->getMessage(), 500);
		}
	}

	function getNotificaRegistro($controler, $registro, $usuario)
	{
		try {
			$filter = [
				'not_controler' => $controler,
				'not_id_registro' => $registro,
				'not_id_usuario' => $usuario,
				'not_visto' => 'A',
			];
			$options = [
				// 'projection' => ['_id' => 0],
				'sort' => ['not_data' => -1],
			];
			$query = new \MongoDB\Driver\Query($filter, $options);

			$result = $this->conn->executeQuery($this->database . '.' . $this->collection, $query);

			return $result->toArray();
		} catch (\MongoDB\Driver\Exception\RuntimeException $ex) {
			show_error('Error while fetching logs: ' . $ex->getMessage(), 500);
		}
	}

	function getNotificaId($_id)
	{
		try {
			$filter = ['_id' => new \MongoDB\BSON\ObjectId($_id)];
			$query = new \MongoDB\Driver\Query($filter);

			$result = $this->conn->executeQuery($this->database . '.' . $this->collection, $query);

			foreach ($result as $files) {
				return $files;
			}

			return null;
		} catch (\MongoDB\Driver\Exception\RuntimeException $ex) {
			show_error('Error while fetching logs: ' . $ex->getMessage(), 500);
		}
	}

	function deleteNotifica($_id)
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

	function updateNotifica($_id)
	{
		try {
			$query = new \MongoDB\Driver\BulkWrite();
			$query->update(['_id' => new \MongoDB\BSON\ObjectId($_id)], ['$set' => array('not_visto' => 'V')]);

			$result = $this->conn->executeBulkWrite($this->database . '.' . $this->collection, $query);

			if ($result->getModifiedCount()) {
				return true;
			}

			return false;
		} catch (\MongoDB\Driver\Exception\RuntimeException $ex) {
			show_error('Error while updating users: ' . $ex->getMessage(), 500);
		}
	}

	function updateAllNotifica($usuario)
	{
		try {
			$query = new \MongoDB\Driver\BulkWrite();
			$query->update(['not_id_usuario' => $usuario], ['$set' => array('not_visto' => 'V')], ['multi' => true, 'upsert' => false]);
			$result = $this->conn->executeBulkWrite($this->database . '.' . $this->collection, $query);

			if ($result->getModifiedCount()) {
				return true;
			}

			return false;
		} catch (\MongoDB\Driver\Exception\RuntimeException $ex) {
			show_error('Error while updating users: ' . $ex->getMessage(), 500);
		}
	}
}
