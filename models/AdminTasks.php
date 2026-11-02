<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

/**
 * Model for Admin
 */
use Ramblur\Logging\LogChannel;

class AdminTasks extends CI_Model {

	const ADMIN_MARKED_NODES_TABLE = "AdminMarkedNodes";

	function __construct() {
		parent::__construct();
	}

	/**
	 * 
	 * @param int $nodeId
	 * @param int $userId
	 * @return boolean
	 */
	public function markNode(int $nodeId, int $userId) {


		if (!$userId || !$nodeId) {
			Logger::getLogger()->addError("Invalid arguments", [$nodeId, $userId, self::class, __LINE__]);
			return false;
		}
		if ($this->db->replace(self::ADMIN_MARKED_NODES_TABLE, ["nodeId" => $nodeId, "userId" => $userId])) {
			return true;
		}
		if (!empty($this->db->error())) {
			Logger::getLogger()->addError("A database error occured", [$this->db->error()]);
		}
		return false;
	}

	/**
	 * 
	 * @param int $nodeId
	 * @param int $userId
	 * @return boolean
	 */
	public function unmarkNode(int $nodeId, int $userId) {
		if (!$userId || !$nodeId) {
			Logger::getLogger()->addError("Invalid arguments", [$nodeId, $userId, self::class, __LINE__]);
			return false;
		}
		if ($this->db->delete(self::ADMIN_MARKED_NODES_TABLE, ["nodeId" => $nodeId, "userId" => $userId])) {
			return true;
		}
		if (!empty($this->db->error())) {
			Logger::getLogger()->addError("A database error occured", $this->db->error());
		}
		return false;
	}

	/**
	 * 
	 * @param array $nodeIds
	 * @param int $userId
	 * @return boolean
	 */
	public function unmarkNodes(array $nodeIds, int $userId) {
		if (!$userId || empty($nodeIds)) {
			Logger::getLogger()->addError("Invalid arguments", [$nodeIds, $userId, self::class, __LINE__]);
			return false;
		}
		$this->db->where_in("nodeId", $nodeIds);
		if ($this->db->delete(self::ADMIN_MARKED_NODES_TABLE, ["userId" => $userId])) {
			return true;
		}
		if (!empty($this->db->error())) {
			Logger::getLogger()->addError("A database error occured", $this->db->error());
		}
		return false;
	}

	/**
	 * Toggles a given mode in the table, if it exists delete it, if not insert it
	 * @param int $nodeId
	 * @param int $userId
	 * @return bool
	 */
	public function toggleNodeMarked(int $nodeId, int $userId): bool {

		if (!$userId || !$nodeId) {
			Logger::getLogger()->addError("Invalid arguments", [$nodeId, $userId, self::class, __LINE__]);
			return false;
		}

		$nodes = $this->getMarkedNodes([$nodeId], $userId);
		if (!empty($nodes)) {
			if ($this->unmarkNode($nodeId, $userId)) {
				return true;
			} else {
				return false;
			}
		} else {
			return $this->markNode($nodeId, $userId);
		}
		return false;
	}

	/**
	 * 
	 * @param array $nodeIds
	 * @param int $userId
	 * @return boolean
	 */
	public function getMarkedNodes(array $nodeIds, int $userId) {
		if (empty($nodeIds)) {
			return false;
		}
		if (!$userId) {
			Logger::getLogger()->addError("Invalid arguments", [$nodeIds, $userId, self::class, __LINE__]);
			return false;
		}
		$result = $this->db->select("*")
				->where_in("nodeId", $nodeIds)
				->where("userId", $userId)
				->from(self::ADMIN_MARKED_NODES_TABLE)
				->get();

		if ($result->num_rows() > 0) {
			return $result->result_array();
		}
		return false;
	}

}
